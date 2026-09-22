<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin();

$db     = getDB();
$userId = (int) currentUserId();

// Liberar lock de sesión para no bloquear otras peticiones del mismo usuario
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$telefonoActivo = trim((string)($_GET['telefono'] ?? ''));
$busqueda       = trim((string)($_GET['buscar'] ?? ''));
$sinceId        = max(0, (int)($_GET['since_id'] ?? 0));
$waitSeconds    = (int)($_GET['wait'] ?? 12);
$waitSeconds    = max(0, min(25, $waitSeconds));

if ($waitSeconds > 0) {
    @set_time_limit($waitSeconds + 5);
}

function waPollCursor(PDO $db, int $userId, string $telefonoActivo): int {
    if ($telefonoActivo !== '') {
        $stmt = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM whatsapp_mensajes WHERE created_by = ? AND telefono = ?");
        $stmt->execute([$userId, $telefonoActivo]);
        return (int)$stmt->fetchColumn();
    }
    $stmt = $db->prepare("SELECT COALESCE(MAX(id), 0) FROM whatsapp_mensajes WHERE created_by = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

try {
    // Long-polling: esperar hasta que haya mensajes nuevos.
    // Libera la conexión MySQL durante los períodos de sleep para no agotar el pool.
    // Con 50+ usuarios simultáneos, retener una conexión 25s mientras se hacen queries
    // cada 750ms agota los slots disponibles. Aquí: conectar → query → desconectar →
    // sleep 750ms → repetir. La conexión se mantiene <5ms por ciclo en lugar de 750ms.
    if ($waitSeconds > 0 && $sinceId > 0) {
        // Liberar AMBAS referencias: la variable local $db y el cache global.
        // Si solo se libera una, PHP mantiene el objeto PDO vivo (ref-counting).
        $db = null;
        releaseDB();
        $start = time();
        while (true) {
            // getDB() abre una conexión efímera; releaseDB() la cierra tras la query.
            // La PDO no se asigna a ninguna variable local → solo $GLOBALS['__db_pdo']
            // la referencia → releaseDB() la libera de verdad.
            $cursorNow = waPollCursor(getDB(), $userId, $telefonoActivo);
            releaseDB();
            if ($cursorNow > $sinceId) {
                break;
            }
            if ((time() - $start) >= $waitSeconds) {
                break;
            }
            usleep(750000);
        }
        $db = getDB(); // reconectar para las queries principales
    }

    // ── Conversaciones ───────────────────────────────────────────────────────
    // ANY_VALUE() evita fallo con ONLY_FULL_GROUP_BY en MySQL 5.7+
    $sqlConversaciones = "
        SELECT
            wm.telefono,
            MAX(c.nombre)    AS cliente_nombre,
            MAX(c.apellidos) AS cliente_apellidos,
            MAX(c.id)        AS cliente_id,
            MAX(wm.created_at)     AS ultimo_mensaje_fecha,
            (SELECT wm2.mensaje FROM whatsapp_mensajes wm2
             WHERE wm2.telefono = wm.telefono AND wm2.created_by = wm.created_by
             ORDER BY wm2.created_at DESC LIMIT 1) AS ultimo_mensaje,
            (SELECT COUNT(*) FROM whatsapp_mensajes wm3
             WHERE wm3.telefono = wm.telefono AND wm3.created_by = wm.created_by
               AND wm3.direccion = 'entrante' AND wm3.estado = 'recibido') AS no_leidos
        FROM whatsapp_mensajes wm
        LEFT JOIN clientes c ON wm.cliente_id = c.id
    ";

    $params = [$userId];
    $sqlConversaciones .= " WHERE wm.created_by = ?";
    if ($busqueda !== '') {
        $sqlConversaciones .= " AND (wm.telefono LIKE ? OR c.nombre LIKE ? OR c.apellidos LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }
    $sqlConversaciones .= " GROUP BY wm.telefono ORDER BY ultimo_mensaje_fecha DESC";

    $stmtConv = $db->prepare($sqlConversaciones);
    $stmtConv->execute($params);
    $conversaciones = $stmtConv->fetchAll(PDO::FETCH_ASSOC);

    // ── Mensajes del chat activo ─────────────────────────────────────────────
    $mensajes        = [];
    $clienteChat     = null;
    $latestMessageId = 0;
    $cursorId        = waPollCursor($db, $userId, $telefonoActivo);

    if ($telefonoActivo !== '') {
        // Marcar entrantes como leídos
        $stmtRead = $db->prepare("
            UPDATE whatsapp_mensajes SET estado = 'leido'
            WHERE telefono = ? AND created_by = ? AND direccion = 'entrante' AND estado = 'recibido'
        ");
        $stmtRead->execute([$telefonoActivo, $userId]);

        $stmtMsg = $db->prepare("
            SELECT wm.id, wm.telefono, wm.direccion, wm.mensaje, wm.estado, wm.created_at
            FROM whatsapp_mensajes wm
            WHERE wm.telefono = ? AND wm.created_by = ?
            ORDER BY wm.created_at ASC, wm.id ASC
        ");
        $stmtMsg->execute([$telefonoActivo, $userId]);
        $mensajes = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($mensajes)) {
            $last            = end($mensajes);
            $latestMessageId = (int)($last['id'] ?? 0);
        }

        $stmtCliente = $db->prepare("
            SELECT c.id, c.nombre, c.apellidos
            FROM whatsapp_mensajes wm
            JOIN clientes c ON wm.cliente_id = c.id
            WHERE wm.telefono = ? AND wm.created_by = ? AND wm.cliente_id IS NOT NULL
            LIMIT 1
        ");
        $stmtCliente->execute([$telefonoActivo, $userId]);
        $clienteChat = $stmtCliente->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        'success'           => true,
        'telefono_activo'   => $telefonoActivo,
        'conversaciones'    => $conversaciones,
        'mensajes'          => $mensajes,
        'cliente_chat'      => $clienteChat,
        'latest_message_id' => $latestMessageId,
        'cursor_id'         => $cursorId,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('WhatsApp polling error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno al actualizar WhatsApp',
    ], JSON_UNESCAPED_UNICODE);
}
