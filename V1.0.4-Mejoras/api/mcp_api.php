<?php
/**
 * API REST para el conector MCP del CRM
 * Autenticación: Bearer token generado en Ajustes → Conector Claude (MCP)
 * El token se almacena en usuario_ajustes con clave = 'mcp_token'
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email.php';

define('MCP_API_VERSION', '3');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Autenticar por Bearer token ────────────────────────────────────────────
function getMcpUser(PDO $db): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return null;
    $token = trim($m[1]);
    if (strlen($token) < 32) return null;
    $stmt = $db->prepare(
        "SELECT u.* FROM usuarios u
         JOIN usuario_ajustes ua ON ua.usuario_id = u.id
         WHERE ua.clave = 'mcp_token' AND ua.valor = ? AND u.activo = 1 LIMIT 1"
    );
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$db      = getDB();
$action  = $_GET['action'] ?? '';

// VERIFY THIS FILE IS BEING USED - Added unique marker at 2024-12-21 08:30
if ($action === 'verify_file_version') {
    echo json_encode(['file_version' => 'v20241221_083000', 'action' => $action]);
    exit;
}

// ── Intercambio público login → token MCP para OAuth web ─────────────────
if ($action === 'mcp_token_by_login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($input['email'] ?? ($_POST['email'] ?? ''));
    $password = (string)($input['password'] ?? ($_POST['password'] ?? ''));

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing_credentials', 'message' => 'email y password son obligatorios'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'invalid_credentials', 'message' => 'Credenciales inválidas'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $db->prepare("SELECT valor FROM usuario_ajustes WHERE usuario_id = ? AND clave = 'mcp_token' LIMIT 1");
    $stmt->execute([(int)$user['id']]);
    $token = trim((string)$stmt->fetchColumn());

    $generated = false;

    if ($token === '' || strlen($token) < 32) {
        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO usuario_ajustes (usuario_id, clave, valor) VALUES (?, 'mcp_token', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->execute([(int)$user['id'], $token]);
        $generated = true;
    }

    echo json_encode([
        'success' => true,
        'usuario' => $user['nombre'] . ' ' . ($user['apellidos'] ?? ''),
        'rol'     => $user['rol'] ?? null,
        'token'   => $token,
        'generated' => $generated,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// If we're here, action was NOT mcp_token_by_login. Check if it's a public debug endpoint.
if ($action === 'test_debug_endpoint') {
    echo json_encode(['status' => 'endpoint_working', 'received_action' => $action, 'match_result' => ($action === 'mcp_token_by_login')]);
    exit;
}

$user    = getMcpUser($db);

// ── Diagnóstico temporal: endpoints públicos para debug ───────────────────
if (($_GET['action'] ?? '') === 'db_debug') {
    // Muestra cuántos tokens MCP hay en la BD y los últimos 4 chars (sin exponer el token completo)
    $stmt2 = $db->query("SELECT ua.usuario_id, u.nombre, u.activo, RIGHT(ua.valor,4) as ultimos4, LENGTH(ua.valor) as longitud FROM usuario_ajustes ua JOIN usuarios u ON ua.usuario_id = u.id WHERE ua.clave = 'mcp_token' AND ua.valor != ''");
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['tokens_en_bd' => count($rows), 'entradas' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['action'] ?? '') === 'test_auth') {
    $t = trim($_GET['t'] ?? '');
    if (strlen($t) < 32) { echo json_encode(['valid' => false, 'reason' => 'token_corto']); exit; }
    $stmt2 = $db->prepare("SELECT u.id, u.nombre, u.rol, u.activo FROM usuarios u JOIN usuario_ajustes ua ON ua.usuario_id = u.id WHERE ua.clave = 'mcp_token' AND ua.valor = ? LIMIT 1");
    $stmt2->execute([$t]);
    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['valid' => false, 'reason' => 'no_encontrado_en_bd']); exit; }
    if (!$row['activo']) { echo json_encode(['valid' => false, 'reason' => 'usuario_inactivo', 'usuario' => $row['nombre']]); exit; }
    echo json_encode(['valid' => true, 'usuario' => $row['nombre'], 'rol' => $row['rol']]); exit;
}

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Token MCP inválido o expirado. Genera uno en Ajustes → Conector Claude (MCP).']);
    exit;
}

$isAdmin = ($user['rol'] ?? '') === 'admin';
$userId  = (int)$user['id'];
$action  = $_GET['action'] ?? '';

$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}

// ── Helpers ────────────────────────────────────────────────────────────────
function agentFilter(bool $isAdmin, int $userId, string $alias = 'p'): array {
    if ($isAdmin) return ['', []];
    return [" AND {$alias}.agente_id = ?", [$userId]];
}

function ok($data): void {
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
}

// ── Acciones ───────────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ══════════════════════════════════════════════════════════════════════
        // DASHBOARD
        // ══════════════════════════════════════════════════════════════════════

        case 'resumen': {
            $af  = $isAdmin ? '' : " AND agente_id = $userId";
            $afP = $isAdmin ? '' : " AND p.agente_id = $userId";

            $stmt = $db->query("SELECT etapa, COUNT(*) as total FROM prospectos WHERE activo = 1 $af GROUP BY etapa ORDER BY FIELD(etapa,'nuevo_lead','contactado','seguimiento','visita_programada','captado','descartado')");
            $porEtapa = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->query("SELECT COUNT(*) FROM clientes WHERE activo = 1 $af");
            $totalClientes = (int)$stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM tareas WHERE estado = 'pendiente' AND DATE(fecha_vencimiento) = CURDATE() AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$userId, $userId]);
            $tareasPendientesHoy = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE p.activo = 1 AND p.fecha_proximo_contacto = CURDATE() $afP");
            $contactosHoy = (int)$stmt->fetchColumn();

            $stmt = $db->query("SELECT COUNT(*) FROM prospectos WHERE activo = 1 AND etapa NOT IN ('captado','descartado') $af");
            $prospectosPipeline = (int)$stmt->fetchColumn();

            // Tareas vencidas sin completar
            $stmt = $db->prepare("SELECT COUNT(*) FROM tareas WHERE estado = 'pendiente' AND fecha_vencimiento < NOW() AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$userId, $userId]);
            $tareasVencidas = (int)$stmt->fetchColumn();

            ok([
                'resumen' => [
                    'prospectos_activos_pipeline' => $prospectosPipeline,
                    'prospectos_por_etapa'        => $porEtapa,
                    'total_clientes'              => $totalClientes,
                    'tareas_pendientes_hoy'       => $tareasPendientesHoy,
                    'tareas_vencidas'             => $tareasVencidas,
                    'prospectos_a_contactar_hoy'  => $contactosHoy,
                ]
            ]);
            break;
        }

        case 'estadisticas': {
            $periodo = $_GET['periodo'] ?? 'mes';
            $today = new DateTimeImmutable('today');

            switch ($periodo) {
                case 'hoy':
                    $desdeDate = $today;
                    $hastaDate = $today;
                    break;
                case 'semana':
                    $desdeDate = $today->sub(new DateInterval('P7D'));
                    $hastaDate = $today;
                    break;
                case 'trimestre':
                    $desdeDate = $today->sub(new DateInterval('P3M'));
                    $hastaDate = $today;
                    break;
                case 'anio':
                    $desdeDate = new DateTimeImmutable($today->format('Y-01-01'));
                    $hastaDate = $today;
                    break;
                default:
                    $desdeDate = new DateTimeImmutable($today->format('Y-m-01'));
                    $hastaDate = $today;
            }

            $desde = $desdeDate->format('Y-m-d');
            $hasta = $hastaDate->format('Y-m-d');

            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(total),0) as importe FROM facturas WHERE estado = 'pagada' AND DATE(fecha_emision) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if (!$isAdmin) {
                $sql .= " AND usuario_id = ?";
                $params[] = $userId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $facturas = $stmt->fetch(PDO::FETCH_ASSOC);

            $sql = "SELECT COUNT(*) as total, COALESCE(SUM(total),0) as importe FROM presupuestos WHERE estado = 'aceptado' AND DATE(fecha_emision) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if (!$isAdmin) {
                $sql .= " AND usuario_id = ?";
                $params[] = $userId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $presupuestos = $stmt->fetch(PDO::FETCH_ASSOC);

            $sql = "SELECT COUNT(*) FROM contratos WHERE estado = 'firmado' AND DATE(created_at) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if (!$isAdmin) {
                $sql .= " AND usuario_id = ?";
                $params[] = $userId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $contratosFirmados = (int)$stmt->fetchColumn();

            $sql = "SELECT COUNT(*) FROM prospectos WHERE DATE(created_at) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if (!$isAdmin) {
                $sql .= " AND agente_id = ?";
                $params[] = $userId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $prospectoNuevos = (int)$stmt->fetchColumn();

            $sql = "SELECT COUNT(*) FROM prospectos WHERE etapa = 'captado' AND DATE(updated_at) BETWEEN ? AND ?";
            $params = [$desde, $hasta];
            if (!$isAdmin) {
                $sql .= " AND agente_id = ?";
                $params[] = $userId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $captados = (int)$stmt->fetchColumn();

            ok([
                'estadisticas' => [
                    'periodo'                => $periodo,
                    'facturas_cobradas'      => (int)$facturas['total'],
                    'importe_cobrado'        => round((float)$facturas['importe'], 2),
                    'presupuestos_aceptados' => (int)$presupuestos['total'],
                    'importe_presupuestado'  => round((float)$presupuestos['importe'], 2),
                    'contratos_firmados'     => $contratosFirmados,
                    'prospectos_nuevos'      => $prospectoNuevos,
                    'prospectos_captados'    => $captados,
                ]
            ]);
            break;
        }

        case 'buscar': {
            $q = trim($_GET['q'] ?? '');
            if (!$q) { err('Parámetro q requerido'); break; }
            $like = "%$q%";
            $af = $isAdmin ? '' : " AND agente_id = $userId";

            $stmt = $db->prepare("SELECT id, nombre, telefono, email, etapa, temperatura FROM prospectos WHERE activo = 1 AND (nombre LIKE ? OR telefono LIKE ? OR email LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $prospectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT id, CONCAT(nombre,' ',COALESCE(apellidos,'')) as nombre, tipo, email, telefono FROM clientes WHERE activo = 1 AND (nombre LIKE ? OR apellidos LIKE ? OR email LIKE ? OR telefono LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like, $like]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT id, referencia, titulo, tipo, estado, precio FROM propiedades WHERE (titulo LIKE ? OR referencia LIKE ? OR localidad LIKE ?) $af LIMIT 10");
            $stmt->execute([$like, $like, $like]);
            $propiedades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ok(['prospectos' => $prospectos, 'clientes' => $clientes, 'propiedades' => $propiedades]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PROSPECTOS
        // ══════════════════════════════════════════════════════════════════════

        case 'prospectos': {
            $q            = '%' . ($_GET['q'] ?? '') . '%';
            $etapa        = $_GET['etapa'] ?? '';
            $temp         = $_GET['temperatura'] ?? '';
            $contactarHoy = ($_GET['contactar_hoy'] ?? '') === '1';
            $limit        = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['p.activo = 1'];
            $params = [];
            if (!$isAdmin)     { $where[] = 'p.agente_id = ?';   $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[]  = '(p.nombre LIKE ? OR p.telefono LIKE ? OR p.email LIKE ?)';
                $params   = array_merge($params, [$q, $q, $q]);
            }
            if ($etapa)        { $where[] = 'p.etapa = ?';        $params[] = $etapa; }
            if ($temp)         { $where[] = 'p.temperatura = ?';  $params[] = $temp; }
            if ($contactarHoy) { $where[] = 'p.fecha_proximo_contacto = CURDATE()'; }

            $sql  = "SELECT p.id, p.referencia, p.nombre, p.telefono, p.email,
                            p.etapa, p.temperatura, p.tipo_propiedad,
                            p.precio_estimado, p.localidad, p.provincia,
                            DATE_FORMAT(p.fecha_proximo_contacto,'%d/%m/%Y') as fecha_contacto,
                            p.proxima_accion
                     FROM prospectos p
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY p.fecha_proximo_contacto ASC, p.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['total' => count($rows = $stmt->fetchAll(PDO::FETCH_ASSOC)), 'prospectos' => $rows]);
            break;
        }

        case 'prospecto': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }
            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare(
                "SELECT p.*, u.nombre as agente_nombre FROM prospectos p
                 LEFT JOIN usuarios u ON p.agente_id = u.id
                 WHERE p.id = ? $af LIMIT 1"
            );
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Prospecto no encontrado', 404); break; }

            $h = $db->prepare(
                "SELECT tipo, contenido, DATE_FORMAT(COALESCE(fecha_evento,created_at),'%d/%m/%Y %H:%i') as fecha
                 FROM historial_prospectos WHERE prospecto_id = ?
                 ORDER BY COALESCE(fecha_evento,created_at) DESC LIMIT 10"
            );
            $h->execute([$id]);
            $p['historial_reciente'] = $h->fetchAll(PDO::FETCH_ASSOC);

            // Tareas relacionadas (por cliente si existe, si no las últimas del agente)
            $p['tareas_pendientes'] = [];

            unset($p['propietarios_json']);
            ok(['prospecto' => $p]);
            break;
        }

        case 'crear_prospecto': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { err('El nombre es obligatorio'); break; }

            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM prospectos WHERE referencia LIKE 'PR%'");
            $ref  = 'PR' . str_pad((int)$stmt->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);

            $ins = $db->prepare(
                "INSERT INTO prospectos
                 (referencia, nombre, telefono, email, tipo_propiedad, precio_estimado,
                  localidad, provincia, notas, etapa, temperatura, agente_id, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)"
            );
            $ins->execute([
                $ref, $nombre,
                $body['telefono']        ?? null,
                $body['email']           ?? null,
                $body['tipo_propiedad']  ?? null,
                isset($body['precio_estimado']) ? (float)$body['precio_estimado'] : null,
                $body['localidad']       ?? null,
                $body['provincia']       ?? null,
                $body['notas']           ?? null,
                $body['etapa']           ?? 'nuevo_lead',
                $body['temperatura']     ?? 'frio',
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'prospecto', $newId, "Prospecto $ref creado por IA");
            ok(['id' => $newId, 'referencia' => $ref, 'mensaje' => "Prospecto $ref creado correctamente"]);
            break;
        }

        case 'actualizar_prospecto': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $campos = [];
            $params = [];
            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','negociacion','captado','descartado'];
            $tempsOk  = ['frio','templado','caliente'];

            if (!empty($body['etapa']) && in_array($body['etapa'], $etapasOk)) {
                $campos[] = 'etapa = ?'; $params[] = $body['etapa'];
            }
            if (!empty($body['temperatura']) && in_array($body['temperatura'], $tempsOk)) {
                $campos[] = 'temperatura = ?'; $params[] = $body['temperatura'];
            }
            if (isset($body['notas_internas'])) {
                $campos[] = 'notas = ?'; $params[] = $body['notas_internas'];
            }
            if (isset($body['fecha_proximo_contacto'])) {
                $campos[] = 'fecha_proximo_contacto = ?'; $params[] = $body['fecha_proximo_contacto'];
            }
            if (isset($body['proxima_accion'])) {
                $campos[] = 'proxima_accion = ?'; $params[] = $body['proxima_accion'];
            }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE prospectos SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            if (function_exists('registrarActividad')) registrarActividad('editar', 'prospecto', $id, 'Actualizado por IA');
            ok(['mensaje' => 'Prospecto actualizado correctamente']);
            break;
        }

        case 'anadir_nota': {
            $pid      = (int)($body['prospecto_id'] ?? 0);
            $contenido = trim($body['contenido'] ?? '');
            $tiposOk  = ['nota','llamada','email','visita','whatsapp','otro'];
            $tipo     = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'nota';

            if (!$pid || !$contenido) { err('prospecto_id y contenido son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $chk = $db->prepare("SELECT id FROM prospectos WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$pid], $ap));
            if (!$chk->fetch()) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $stmt = $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)");
            $stmt->execute([$pid, $userId, $contenido, $tipo]);

            // Actualizar fecha de último contacto
            $db->prepare("UPDATE prospectos SET updated_at = NOW() WHERE id = ?")->execute([$pid]);

            ok(['mensaje' => 'Nota añadida correctamente', 'id' => (int)$db->lastInsertId()]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CLIENTES
        // ══════════════════════════════════════════════════════════════════════

        case 'clientes': {
            $q     = '%' . ($_GET['q'] ?? '') . '%';
            $tipo  = $_GET['tipo'] ?? '';
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['c.activo = 1'];
            $params = [];
            if (!$isAdmin) { $where[] = 'c.agente_id = ?'; $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[]  = '(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.apellidos LIKE ?)';
                $params   = array_merge($params, [$q, $q, $q, $q]);
            }
            if ($tipo) { $where[] = 'FIND_IN_SET(?, c.tipo)'; $params[] = $tipo; }

            $sql  = "SELECT c.id, c.nombre, c.apellidos, c.tipo, c.email,
                            c.telefono, c.localidad, c.provincia,
                            DATE_FORMAT(c.created_at,'%d/%m/%Y') as alta
                     FROM clientes c
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY c.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'cliente': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'c');
            $stmt = $db->prepare(
                "SELECT c.*, u.nombre as agente_nombre FROM clientes c
                 LEFT JOIN usuarios u ON c.agente_id = u.id
                 WHERE c.id = ? $af LIMIT 1"
            );
            $stmt->execute(array_merge([$id], $ap));
            $c = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$c) { err('Cliente no encontrado', 404); break; }

            // Presupuestos del cliente
            $stmt2 = $db->prepare("SELECT id, titulo, total, estado, DATE_FORMAT(fecha_emision,'%d/%m/%Y') as fecha FROM presupuestos WHERE cliente_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt2->execute([$id]);
            $c['presupuestos_recientes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            ok(['cliente' => $c]);
            break;
        }

        case 'crear_cliente': {
            $nombre = trim($body['nombre'] ?? '');
            if (!$nombre) { err('El nombre es obligatorio'); break; }

            $ins = $db->prepare(
                "INSERT INTO clientes (nombre, apellidos, tipo, email, telefono, localidad, provincia, notas, agente_id, activo)
                 VALUES (?,?,?,?,?,?,?,?,?,1)"
            );
            $ins->execute([
                $nombre,
                $body['apellidos'] ?? null,
                $body['tipo']      ?? 'comprador',
                $body['email']     ?? null,
                $body['telefono']  ?? null,
                $body['localidad'] ?? null,
                $body['provincia'] ?? null,
                $body['notas']     ?? null,
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'cliente', $newId, 'Cliente creado por IA');
            ok(['id' => $newId, 'mensaje' => 'Cliente creado correctamente']);
            break;
        }

        case 'actualizar_cliente': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'c');
            $chk = $db->prepare("SELECT id FROM clientes c WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { err('Cliente no encontrado o sin acceso', 403); break; }

            $campos = []; $params = [];
            foreach (['nombre','apellidos','tipo','email','telefono','localidad','provincia','notas'] as $f) {
                if (isset($body[$f])) { $campos[] = "$f = ?"; $params[] = $body[$f]; }
            }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE clientes SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            ok(['mensaje' => 'Cliente actualizado correctamente']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // TAREAS
        // ══════════════════════════════════════════════════════════════════════

        case 'tareas': {
            $estado  = $_GET['estado'] ?? '';
            $soloHoy = ($_GET['solo_hoy'] ?? '') === '1';

            $where  = ['(t.asignado_a = ? OR t.creado_por = ?)'];
            $params = [$userId, $userId];
            if ($estado)  { $where[] = 't.estado = ?';                         $params[] = $estado; }
            if ($soloHoy) { $where[] = 'DATE(t.fecha_vencimiento) = CURDATE()'; }

            $sql  = "SELECT t.id, t.titulo, t.tipo, t.estado, t.prioridad,
                            DATE_FORMAT(t.fecha_vencimiento,'%d/%m/%Y %H:%i') as vencimiento
                     FROM tareas t
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY t.fecha_vencimiento ASC LIMIT 50";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['tareas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_tarea': {
            $titulo = trim($body['titulo'] ?? '');
            if (!$titulo) { err('El título es obligatorio'); break; }

            $tiposOk = ['llamada','email','reunion','visita','otro'];
            $tipo    = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'otro';

            $ins = $db->prepare(
                "INSERT INTO tareas (titulo, tipo, descripcion, prioridad, estado, fecha_vencimiento, asignado_a, creado_por, cliente_id, propiedad_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $titulo, $tipo,
                $body['descripcion']       ?? null,
                $body['prioridad']         ?? 'media',
                'pendiente',
                $body['fecha_vencimiento'] ?? null,
                $userId,
                $userId,
                isset($body['cliente_id'])   ? (int)$body['cliente_id']   : null,
                isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null,
            ]);
            ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Tarea creada correctamente']);
            break;
        }

        case 'completar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $stmt = $db->prepare("UPDATE tareas SET estado = 'completada', updated_at = NOW() WHERE id = ? AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->rowCount()) { err('Tarea no encontrada o sin acceso', 403); break; }
            ok(['mensaje' => 'Tarea marcada como completada']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PROPIEDADES
        // ══════════════════════════════════════════════════════════════════════

        case 'propiedades': {
            $q     = '%' . ($_GET['q'] ?? '') . '%';
            $est   = $_GET['estado'] ?? '';
            $max   = isset($_GET['max']) ? (float)$_GET['max'] : null;
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['1=1'];
            $params = [];
            if (!$isAdmin) { $where[] = 'p.agente_id = ?'; $params[] = $userId; }
            if ($_GET['q'] ?? '') {
                $where[] = '(p.titulo LIKE ? OR p.referencia LIKE ? OR p.localidad LIKE ?)';
                $params  = array_merge($params, [$q, $q, $q]);
            }
            if ($est) { $where[] = 'p.estado = ?'; $params[] = $est; }
            if ($max) { $where[] = 'p.precio <= ?'; $params[] = $max; }

            $sql  = "SELECT p.id, p.referencia, p.titulo, p.tipo, p.estado,
                            p.precio, p.localidad, p.provincia,
                            p.habitaciones, p.banos, p.superficie_construida, p.superficie_util
                     FROM propiedades p
                     WHERE " . implode(' AND ', $where) .
                    " ORDER BY p.created_at DESC LIMIT $limit";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['propiedades' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'propiedad': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'p');
            $stmt = $db->prepare(
                "SELECT p.*, u.nombre as agente_nombre FROM propiedades p
                 LEFT JOIN usuarios u ON p.agente_id = u.id
                 WHERE p.id = ? $af LIMIT 1"
            );
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Propiedad no encontrada', 404); break; }

            // Fotos
            $f = $db->prepare("SELECT url FROM propiedad_fotos WHERE propiedad_id = ? ORDER BY orden ASC LIMIT 5");
            $f->execute([$id]);
            $p['fotos'] = $f->fetchAll(PDO::FETCH_COLUMN);

            ok(['propiedad' => $p]);
            break;
        }

        case 'crear_propiedad': {
            $titulo = trim($body['titulo'] ?? '');
            if (!$titulo) { err('El título es obligatorio'); break; }

            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(referencia,3) AS UNSIGNED)) FROM propiedades WHERE referencia LIKE 'IM%'");
            $ref  = 'IM' . str_pad((int)$stmt->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

            $ins = $db->prepare(
                "INSERT INTO propiedades (referencia, titulo, tipo, estado, precio, localidad, provincia, descripcion, habitaciones, banos, superficie_construida, agente_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $ref, $titulo,
                $body['tipo']         ?? 'piso',
                $body['estado']       ?? 'disponible',
                isset($body['precio'])       ? (float)$body['precio']       : null,
                $body['localidad']    ?? null,
                $body['provincia']    ?? null,
                $body['descripcion']  ?? null,
                isset($body['habitaciones']) ? (int)$body['habitaciones']   : null,
                isset($body['banos'])        ? (int)$body['banos']          : null,
                isset($body['metros'])       ? (float)$body['metros']       : null,
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            if (function_exists('registrarActividad')) registrarActividad('crear', 'propiedad', $newId, "Propiedad $ref creada por IA");
            ok(['id' => $newId, 'referencia' => $ref, 'mensaje' => "Propiedad $ref creada correctamente"]);
            break;
        }

        case 'actualizar_propiedad': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'p');
            $chk = $db->prepare("SELECT id FROM propiedades p WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$id], $ap));
            if (!$chk->fetch()) { err('Propiedad no encontrada o sin acceso', 403); break; }

            $campos = []; $params = [];
            $estadosOk = ['disponible','reservado','vendido','alquilado','retirado'];
            if (!empty($body['estado']) && in_array($body['estado'], $estadosOk)) {
                $campos[] = 'estado = ?'; $params[] = $body['estado'];
            }
            if (isset($body['precio'])) { $campos[] = 'precio = ?'; $params[] = (float)$body['precio']; }
            if (isset($body['notas']))  { $campos[] = 'descripcion = ?'; $params[] = $body['notas']; }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE propiedades SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            ok(['mensaje' => 'Propiedad actualizada correctamente']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PRESUPUESTOS
        // ══════════════════════════════════════════════════════════════════════

        case 'presupuestos': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND pr.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'pr.estado = ?'; $params[] = $estado; }

            $sql = "SELECT pr.id, pr.titulo, pr.total, pr.estado,
                           c.nombre as cliente_nombre,
                           DATE_FORMAT(pr.fecha_emision,'%d/%m/%Y') as fecha,
                           DATE_FORMAT(pr.fecha_expiracion,'%d/%m/%Y') as expira
                    FROM presupuestos pr
                    LEFT JOIN clientes c ON pr.cliente_id = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY pr.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['presupuestos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'presupuesto': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND pr.usuario_id = $userId";
            $stmt = $db->prepare(
                "SELECT pr.*, c.nombre as cliente_nombre, c.email as cliente_email
                 FROM presupuestos pr
                 LEFT JOIN clientes c ON pr.cliente_id = c.id
                 WHERE pr.id = ? $af LIMIT 1"
            );
            $stmt->execute([$id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Presupuesto no encontrado', 404); break; }
            if (!empty($p['lineas'])) $p['lineas'] = json_decode($p['lineas'], true) ?? [];
            ok(['presupuesto' => $p]);
            break;
        }

        case 'crear_presupuesto': {
            $total = isset($body['total']) ? (float)$body['total'] : null;
            if ($total === null) { err('El total es obligatorio'); break; }

            $titulo = trim($body['titulo'] ?? 'Presupuesto');
            if (!$titulo) $titulo = 'Presupuesto';

            // Número de presupuesto
            $stmt = $db->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero,'-',-1) AS UNSIGNED)) FROM presupuestos");
            $num  = (int)$stmt->fetchColumn() + 1;
            $numero = 'P-' . date('Y') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);

            $iva     = round($total * 0.21, 2);
            $subtotal = round($total - $iva, 2);
            $lineas  = $body['detalles'] ? [['concepto' => $body['detalles'], 'cantidad' => 1, 'precio' => $subtotal]] : [];
            $validez = (int)($body['validez_dias'] ?? 30);

            $ins = $db->prepare(
                "INSERT INTO presupuestos (numero, cliente_id, titulo, descripcion, lineas, subtotal, iva_total, total, validez_dias, fecha_emision, fecha_expiracion, notas, estado, usuario_id)
                 VALUES (?,?,?,?,?,?,?,?,?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?,?)"
            );
            $ins->execute([
                $numero,
                isset($body['cliente_id']) ? (int)$body['cliente_id'] : null,
                $titulo,
                $body['detalles']  ?? null,
                json_encode($lineas),
                $subtotal, $iva, $total,
                $validez,
                $body['notas'] ?? null,
                'borrador',
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            ok(['id' => $newId, 'numero' => $numero, 'mensaje' => "Presupuesto $numero creado correctamente"]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // FACTURAS
        // ══════════════════════════════════════════════════════════════════════

        case 'facturas': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND f.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'f.estado = ?'; $params[] = $estado; }

            $sql = "SELECT f.id, f.numero, f.concepto, f.total, f.estado,
                           c.nombre as cliente_nombre,
                           DATE_FORMAT(f.fecha_emision,'%d/%m/%Y') as fecha,
                           DATE_FORMAT(f.fecha_vencimiento,'%d/%m/%Y') as vence
                    FROM facturas f
                    LEFT JOIN clientes c ON f.cliente_id = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY f.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['facturas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'factura': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND f.usuario_id = $userId";
            $stmt = $db->prepare(
                "SELECT f.*, c.nombre as cliente_nombre, c.email as cliente_email, c.dni_nie_cif
                 FROM facturas f
                 LEFT JOIN clientes c ON f.cliente_id = c.id
                 WHERE f.id = ? $af LIMIT 1"
            );
            $stmt->execute([$id]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$f) { err('Factura no encontrada', 404); break; }
            if (!empty($f['lineas'])) $f['lineas'] = json_decode($f['lineas'], true) ?? [];
            ok(['factura' => $f]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CONTRATOS
        // ══════════════════════════════════════════════════════════════════════

        case 'contratos': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND ct.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'ct.estado = ?'; $params[] = $estado; }

            $sql = "SELECT ct.id, ct.titulo, ct.estado, ct.firmante_nombre,
                           c.nombre as cliente_nombre,
                           DATE_FORMAT(ct.created_at,'%d/%m/%Y') as creado,
                           DATE_FORMAT(ct.firmado_at,'%d/%m/%Y') as firmado
                    FROM contratos ct
                    LEFT JOIN clientes c ON ct.cliente_id = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY ct.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['contratos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'contrato': {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND ct.usuario_id = $userId";
            $stmt = $db->prepare(
                "SELECT ct.id, ct.titulo, ct.estado, ct.firmante_nombre,
                        ct.firmado_at, ct.firmado_ip,
                        DATE_FORMAT(ct.fecha_expiracion,'%d/%m/%Y') as expira,
                        c.nombre as cliente_nombre, c.email as cliente_email,
                        p.titulo as propiedad_titulo
                 FROM contratos ct
                 LEFT JOIN clientes c   ON ct.cliente_id   = c.id
                 LEFT JOIN propiedades p ON ct.propiedad_id = p.id
                 WHERE ct.id = ? $af LIMIT 1"
            );
            $stmt->execute([$id]);
            $ct = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ct) { err('Contrato no encontrado', 404); break; }
            ok(['contrato' => $ct]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // VISITAS
        // ══════════════════════════════════════════════════════════════════════

        case 'visitas': {
            $estado  = $_GET['estado'] ?? '';
            $soloHoy = ($_GET['solo_hoy'] ?? '') === '1';
            $limit   = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af      = $isAdmin ? '' : " AND v.agente_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado)  { $where[] = 'v.estado = ?';                 $params[] = $estado; }
            if ($soloHoy) { $where[] = 'v.fecha = CURDATE()'; }

            $sql = "SELECT v.id, v.estado,
                           CONCAT(DATE_FORMAT(v.fecha,'%d/%m/%Y'),' ',COALESCE(TIME_FORMAT(v.hora,'%H:%i'),'')) as fecha,
                           v.duracion_minutos, v.comentarios,
                           p.titulo as propiedad, p.localidad,
                           c.nombre as contacto
                    FROM visitas v
                    LEFT JOIN propiedades p ON v.propiedad_id = p.id
                    LEFT JOIN clientes c    ON v.cliente_id   = c.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY v.fecha ASC, v.hora ASC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['visitas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_visita': {
            $propId = (int)($body['propiedad_id'] ?? 0);
            $fecha  = trim($body['fecha'] ?? '');
            if (!$propId || !$fecha) { err('propiedad_id y fecha son obligatorios'); break; }

            // Separar fecha y hora (acepta "YYYY-MM-DD HH:MM" o "YYYY-MM-DD")
            $ts    = strtotime($fecha);
            if (!$ts) { err('Formato de fecha inválido. Usa YYYY-MM-DD HH:MM'); break; }
            $fechaDate = date('Y-m-d', $ts);
            $fechaHora = date('H:i:s', $ts);

            $ins = $db->prepare(
                "INSERT INTO visitas (propiedad_id, cliente_id, fecha, hora, duracion_minutos, comentarios, estado, agente_id)
                 VALUES (?,?,?,?,?,?,'programada',?)"
            );
            $ins->execute([
                $propId,
                isset($body['cliente_id']) ? (int)$body['cliente_id'] : null,
                $fechaDate,
                $fechaHora,
                (int)($body['duracion_min'] ?? 60),
                $body['notas'] ?? null,
                $userId,
            ]);
            $newId = (int)$db->lastInsertId();

            // Crear tarea recordatorio automática
            $tituloTarea = 'Visita programada - ' . date('d/m/Y H:i', $ts);
            $db->prepare("INSERT INTO tareas (titulo, tipo, estado, fecha_vencimiento, asignado_a, creado_por, propiedad_id) VALUES (?,?,?,?,?,?,?)")
               ->execute([$tituloTarea, 'visita', 'pendiente', $fechaDt, $userId, $userId, $propId]);

            ok(['id' => $newId, 'mensaje' => "Visita programada para el $fecha"]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // AUTOMATIZACIONES
        // ══════════════════════════════════════════════════════════════════════

        case 'automatizaciones': {
            $af = $isAdmin ? '' : " AND created_by = $userId";
            $stmt = $db->query(
                "SELECT id, nombre, trigger_tipo, activo, ejecuciones,
                        DATE_FORMAT(ultima_ejecucion,'%d/%m/%Y %H:%i') as ultima_ejecucion
                 FROM automatizaciones WHERE activo = 1 $af ORDER BY nombre ASC"
            );
            ok(['automatizaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'iniciar_automatizacion': {
            $autoId      = (int)($body['automatizacion_id'] ?? 0);
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            if (!$autoId || !$prospectoId) { err('automatizacion_id y prospecto_id son obligatorios'); break; }

            // Verificar que la automatización existe y tiene acceso
            $af = $isAdmin ? '' : " AND created_by = $userId";
            $stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id = ? AND activo = 1 $af LIMIT 1");
            $stmt->execute([$autoId]);
            if (!$stmt->fetch()) { err('Automatización no encontrada o sin acceso', 403); break; }

            // Obtener datos del prospecto para contexto
            $stmtP = $db->prepare("SELECT agente_id FROM prospectos WHERE id = ? LIMIT 1");
            $stmtP->execute([$prospectoId]);
            $prospecto = $stmtP->fetch(PDO::FETCH_ASSOC);

            require_once __DIR__ . '/../includes/automatizaciones_engine.php';
            $resultado = automatizacionesEjecutarTrigger('manual_ia', [
                'entidad_tipo'   => 'prospecto',
                'entidad_id'     => $prospectoId,
                'prospecto_id'   => $prospectoId,
                'agente_id'      => $prospecto['agente_id'] ?? $userId,
                'actor_user_id'  => $userId,
                'owner_user_id'  => $userId,
            ]);

            ok(['resultado' => $resultado, 'mensaje' => 'Automatización iniciada']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // COMUNICACIÓN
        // ══════════════════════════════════════════════════════════════════════

        case 'enviar_whatsapp': {
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            $mensaje     = trim($body['mensaje'] ?? '');
            if (!$prospectoId || !$mensaje) { err('prospecto_id y mensaje son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$prospectoId], $ap));
            $prospecto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prospecto) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $telefono = preg_replace('/[^0-9]/', '', $prospecto['telefono'] ?? '');
            if (!$telefono) { err('El prospecto no tiene teléfono registrado'); break; }

            // Obtener configuración Twilio
            $twCfg = null;
            $sid   = getenv('TWILIO_ACCOUNT_SID') ?: '';
            $token = getenv('TWILIO_AUTH_TOKEN')  ?: '';
            $from  = getenv('TWILIO_WHATSAPP_FROM') ?: '';
            if (!$sid || !$token || !$from) {
                $stmtCfg = $db->query("SELECT account_sid, auth_token, phone_number FROM twilio_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
                $twCfg   = $stmtCfg ? $stmtCfg->fetch(PDO::FETCH_ASSOC) : null;
                if ($twCfg) { $sid = $sid ?: $twCfg['account_sid']; $token = $token ?: $twCfg['auth_token']; $from = $from ?: $twCfg['phone_number']; }
            }
            if (!$sid || !$token || !$from) { err('WhatsApp (Twilio) no configurado en el CRM'); break; }

            if (strpos($from, 'whatsapp:') !== 0) $from = 'whatsapp:' . $from;
            $to  = 'whatsapp:+' . $telefono;
            $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $mensaje]),
                CURLOPT_USERPWD => "$sid:$token", CURLOPT_TIMEOUT => 20,
            ]);
            $resp     = json_decode(curl_exec($ch), true);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && !empty($resp['sid'])) {
                // Registrar en historial
                $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")
                   ->execute([$prospectoId, $userId, "WhatsApp enviado: $mensaje", 'whatsapp']);
                ok(['mensaje' => 'WhatsApp enviado correctamente', 'sid' => $resp['sid']]);
            } else {
                err('Error al enviar WhatsApp: ' . ($resp['message'] ?? "HTTP $httpCode"));
            }
            break;
        }

        case 'enviar_email': {
            $prospectoId = (int)($body['prospecto_id'] ?? 0);
            $asunto      = trim($body['asunto'] ?? '');
            $cuerpo      = trim($body['cuerpo_html'] ?? '');
            if (!$prospectoId || !$asunto || !$cuerpo) { err('prospecto_id, asunto y cuerpo_html son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$prospectoId], $ap));
            $prospecto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prospecto) { err('Prospecto no encontrado o sin acceso', 403); break; }

            $emailDest = trim($prospecto['email'] ?? '');
            if (!$emailDest) { err('El prospecto no tiene email registrado'); break; }

            $enviado = enviarEmail($emailDest, $asunto, $cuerpo, true, $userId);
            if ($enviado) {
                $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?,?,?,?)")
                   ->execute([$prospectoId, $userId, "Email enviado: $asunto", 'email']);
                ok(['mensaje' => "Email enviado correctamente a $emailDest"]);
            } else {
                err('Error al enviar email: ' . (getLastEmailError() ?? 'Error desconocido'));
            }
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CAMPAÑAS
        // ══════════════════════════════════════════════════════════════════════

        case 'campanas': {
            $estado = $_GET['estado'] ?? '';
            $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            $af     = $isAdmin ? '' : " AND ca.usuario_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'ca.estado = ?'; $params[] = $estado; }

            $sql = "SELECT ca.id, ca.nombre, ca.tipo, ca.estado,
                           ca.total_contactos, ca.enviados, ca.abiertos, ca.clicks,
                           DATE_FORMAT(ca.created_at,'%d/%m/%Y') as creada
                    FROM campanas ca
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY ca.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['campanas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'version': {
            ok(['version' => MCP_API_VERSION]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // ACCIONES RÁPIDAS DE PROSPECTOS
        // ══════════════════════════════════════════════════════════════════════

        case 'programar_contacto': {
            // Cambiar fecha de próximo contacto y/o próxima acción
            // Acepta un ID o un array de IDs (campo ids[] o ids separados por coma)
            $fecha  = trim($body['fecha'] ?? '');          // YYYY-MM-DD
            $accion = trim($body['proxima_accion'] ?? '');
            $temp   = trim($body['temperatura'] ?? '');
            $etapa  = trim($body['etapa'] ?? '');

            // Resolver IDs: puede venir como id, ids (array) o ids (string "1,2,3")
            $ids = [];
            if (!empty($body['id']))  $ids[] = (int)$body['id'];
            if (!empty($body['ids'])) {
                $raw = is_array($body['ids']) ? $body['ids'] : explode(',', $body['ids']);
                foreach ($raw as $r) { $v = (int)trim($r); if ($v > 0) $ids[] = $v; }
            }
            $ids = array_unique(array_filter($ids));
            if (empty($ids)) { err('Debes indicar id o ids de los prospectos'); break; }

            $campos = []; $params = [];
            if ($fecha)  { $campos[] = 'fecha_proximo_contacto = ?'; $params[] = $fecha; }
            if ($accion) { $campos[] = 'proxima_accion = ?';         $params[] = $accion; }

            $tempsOk = ['frio','templado','caliente'];
            if ($temp && in_array($temp, $tempsOk)) { $campos[] = 'temperatura = ?'; $params[] = $temp; }

            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'];
            if ($etapa && in_array($etapa, $etapasOk)) { $campos[] = 'etapa = ?'; $params[] = $etapa; }

            if (empty($campos)) { err('Debes indicar al menos fecha, proxima_accion, temperatura o etapa'); break; }

            $ph = implode(',', array_fill(0, count($ids), '?'));
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $sql = "UPDATE prospectos SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id IN ($ph) $af";
            $db->prepare($sql)->execute(array_merge($params, $ids));

            ok(['mensaje' => count($ids) . ' prospecto(s) actualizados', 'ids' => $ids]);
            break;
        }

        case 'convertir_cliente': {
            $id = (int)($body['prospecto_id'] ?? $body['id'] ?? 0);
            if (!$id) { err('prospecto_id requerido'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId);
            $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ? $af LIMIT 1");
            $stmt->execute(array_merge([$id], $ap));
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) { err('Prospecto no encontrado o sin acceso', 403); break; }

            // Verificar si ya existe cliente con ese email/teléfono
            if ($p['email']) {
                $dup = $db->prepare("SELECT id FROM clientes WHERE email = ? LIMIT 1");
                $dup->execute([$p['email']]);
                if ($dup->fetchColumn()) { err('Ya existe un cliente con ese email'); break; }
            }

            $tipo = $body['tipo'] ?? 'vendedor';
            $ins  = $db->prepare("INSERT INTO clientes (nombre, apellidos, email, telefono, tipo, localidad, provincia, notas, agente_id, activo, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,NOW())");
            $ins->execute([$p['nombre'], null, $p['email'], $p['telefono'], $tipo, $p['localidad'], $p['provincia'], $p['notas'], $p['agente_id'] ?? $userId]);
            $clienteId = (int)$db->lastInsertId();

            // Marcar prospecto como captado
            $db->prepare("UPDATE prospectos SET etapa = 'captado', activo = 0, updated_at = NOW() WHERE id = ?")->execute([$id]);

            ok(['cliente_id' => $clienteId, 'mensaje' => "Prospecto convertido a cliente #$clienteId"]);
            break;
        }

        case 'mover_etapas': {
            // Mover uno o varios prospectos a una etapa
            $etapa  = trim($body['etapa'] ?? '');
            $etapasOk = ['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'];
            if (!in_array($etapa, $etapasOk)) { err('Etapa inválida. Válidas: ' . implode(', ', $etapasOk)); break; }

            $ids = [];
            if (!empty($body['id']))  $ids[] = (int)$body['id'];
            if (!empty($body['ids'])) {
                $raw = is_array($body['ids']) ? $body['ids'] : explode(',', $body['ids']);
                foreach ($raw as $r) { $v = (int)trim($r); if ($v > 0) $ids[] = $v; }
            }
            $ids = array_unique(array_filter($ids));
            if (empty($ids)) { err('Debes indicar id o ids'); break; }

            $ph = implode(',', array_fill(0, count($ids), '?'));
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $db->prepare("UPDATE prospectos SET etapa = ?, updated_at = NOW() WHERE id IN ($ph) $af")
               ->execute(array_merge([$etapa], $ids));

            ok(['mensaje' => count($ids) . ' prospecto(s) movidos a etapa "' . $etapa . '"', 'ids' => $ids]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // NOTAS DE CLIENTES
        // ══════════════════════════════════════════════════════════════════════

        case 'anadir_nota_cliente': {
            $clienteId = (int)($body['cliente_id'] ?? 0);
            $contenido = trim($body['contenido'] ?? '');
            if (!$clienteId || !$contenido) { err('cliente_id y contenido son obligatorios'); break; }

            [$af, $ap] = agentFilter($isAdmin, $userId, 'c');
            $chk = $db->prepare("SELECT id FROM clientes c WHERE id = ? $af LIMIT 1");
            $chk->execute(array_merge([$clienteId], $ap));
            if (!$chk->fetch()) { err('Cliente no encontrado o sin acceso', 403); break; }

            // Guardar como evento de calendario vinculado al cliente
            $tipo  = $body['tipo'] ?? 'nota';
            $db->prepare("INSERT INTO calendario_eventos (titulo, tipo, fecha_inicio, fecha_fin, todo_dia, cliente_id, usuario_id, descripcion) VALUES (?,?,NOW(),NOW(),1,?,?,?)")
               ->execute(["Nota: " . substr($contenido, 0, 50), $tipo, $clienteId, $userId, $contenido]);

            ok(['mensaje' => 'Nota añadida al cliente', 'id' => (int)$db->lastInsertId()]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // TAREAS — ACTUALIZAR / CANCELAR
        // ══════════════════════════════════════════════════════════════════════

        case 'actualizar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $stmt = $db->prepare("SELECT id FROM tareas WHERE id = ? AND (asignado_a = ? OR creado_por = ?) LIMIT 1");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->fetch()) { err('Tarea no encontrada o sin acceso', 403); break; }

            $campos = []; $params = [];
            $editables = ['titulo','descripcion','tipo','prioridad','fecha_vencimiento'];
            foreach ($editables as $f) {
                if (isset($body[$f])) { $campos[] = "$f = ?"; $params[] = $body[$f]; }
            }
            $estadosOk = ['pendiente','en_progreso','completada','cancelada'];
            if (!empty($body['estado']) && in_array($body['estado'], $estadosOk)) {
                $campos[] = 'estado = ?'; $params[] = $body['estado'];
                if ($body['estado'] === 'completada') { $campos[] = 'fecha_completada = NOW()'; }
            }
            if (empty($campos)) { err('Nada que actualizar'); break; }

            $params[] = $id;
            $db->prepare("UPDATE tareas SET " . implode(', ', $campos) . ", updated_at = NOW() WHERE id = ?")->execute($params);
            ok(['mensaje' => 'Tarea actualizada correctamente']);
            break;
        }

        case 'cancelar_tarea': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }
            $stmt = $db->prepare("UPDATE tareas SET estado = 'cancelada', updated_at = NOW() WHERE id = ? AND (asignado_a = ? OR creado_por = ?)");
            $stmt->execute([$id, $userId, $userId]);
            if (!$stmt->rowCount()) { err('Tarea no encontrada o sin acceso', 403); break; }
            ok(['mensaje' => 'Tarea cancelada']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // VISITAS — ACTUALIZAR ESTADO
        // ══════════════════════════════════════════════════════════════════════

        case 'actualizar_visita': {
            $id     = (int)($body['id'] ?? 0);
            $estado = trim($body['estado'] ?? '');
            if (!$id) { err('ID requerido'); break; }

            $estadosOk = ['programada','realizada','cancelada','no_presentado'];
            if (!in_array($estado, $estadosOk)) { err('Estado inválido. Válidos: ' . implode(', ', $estadosOk)); break; }

            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->prepare("UPDATE visitas SET estado = ?, updated_at = NOW() WHERE id = ? $af");
            $stmt->execute([$estado, $id]);
            if (!$stmt->rowCount()) { err('Visita no encontrada o sin acceso', 403); break; }

            ok(['mensaje' => "Visita marcada como $estado"]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CALENDARIO
        // ══════════════════════════════════════════════════════════════════════

        case 'calendario': {
            $desde  = $_GET['desde'] ?? date('Y-m-d');
            $hasta  = $_GET['hasta'] ?? date('Y-m-d', strtotime('+30 days'));
            $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $af     = $isAdmin ? '' : " AND e.usuario_id = $userId";

            $stmt = $db->prepare(
                "SELECT e.id, e.titulo, e.tipo, e.todo_dia,
                        DATE_FORMAT(e.fecha_inicio,'%d/%m/%Y %H:%i') as inicio,
                        DATE_FORMAT(e.fecha_fin,'%d/%m/%Y %H:%i') as fin,
                        e.ubicacion,
                        c.nombre as cliente_nombre,
                        p.titulo as propiedad_titulo
                 FROM calendario_eventos e
                 LEFT JOIN clientes c    ON e.cliente_id    = c.id
                 LEFT JOIN propiedades p ON e.propiedad_id  = p.id
                 WHERE e.fecha_inicio >= ? AND e.fecha_inicio <= ? $af
                 ORDER BY e.fecha_inicio ASC LIMIT $limit"
            );
            $stmt->execute([$desde . ' 00:00:00', $hasta . ' 23:59:59']);
            ok(['eventos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'crear_evento': {
            $titulo = trim($body['titulo'] ?? '');
            $inicio = trim($body['fecha_inicio'] ?? '');
            if (!$titulo || !$inicio) { err('titulo y fecha_inicio son obligatorios'); break; }

            $fin    = $body['fecha_fin']  ?? $inicio;
            $todoDia = !empty($body['todo_dia']) ? 1 : 0;
            $tiposOk = ['tarea','reunion','visita','cita','otra'];
            $tipo   = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'cita';

            $ins = $db->prepare(
                "INSERT INTO calendario_eventos (titulo, descripcion, tipo, fecha_inicio, fecha_fin, todo_dia, ubicacion, cliente_id, propiedad_id, usuario_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $titulo,
                $body['descripcion'] ?? null,
                $tipo,
                date('Y-m-d H:i:s', strtotime($inicio)),
                date('Y-m-d H:i:s', strtotime($fin)),
                $todoDia,
                $body['ubicacion'] ?? null,
                isset($body['cliente_id'])   ? (int)$body['cliente_id']   : null,
                isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null,
                $userId,
            ]);
            ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Evento creado en el calendario']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // FINANZAS / COMISIONES
        // ══════════════════════════════════════════════════════════════════════

        case 'finanzas': {
            $estado  = $_GET['estado'] ?? '';
            $tipo    = $_GET['tipo']   ?? '';
            $limit   = min(100, max(1, (int)($_GET['limit'] ?? 20)));
            $af      = $isAdmin ? '' : " AND f.agente_id = $userId";

            $where  = ['1=1'];
            $params = [];
            if ($estado) { $where[] = 'f.estado = ?'; $params[] = $estado; }
            if ($tipo)   { $where[] = 'f.tipo = ?';   $params[] = $tipo; }

            $sql = "SELECT f.id, f.tipo, f.concepto, f.importe, f.importe_total, f.estado,
                           DATE_FORMAT(f.fecha,'%d/%m/%Y') as fecha,
                           c.nombre as cliente_nombre,
                           p.titulo as propiedad_titulo
                    FROM finanzas f
                    LEFT JOIN clientes c    ON f.cliente_id    = c.id
                    LEFT JOIN propiedades p ON f.propiedad_id  = p.id
                    WHERE " . implode(' AND ', $where) . " $af
                    ORDER BY f.fecha DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Totales
            $totStmt = $db->prepare("SELECT COALESCE(SUM(importe_total),0) as total, estado FROM finanzas f WHERE 1=1 $af GROUP BY estado");
            $totStmt->execute();
            $totales = [];
            foreach ($totStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $totales[$row['estado']] = round((float)$row['total'], 2);
            }

            ok(['finanzas' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'totales' => $totales]);
            break;
        }

        case 'crear_finanza': {
            $concepto  = trim($body['concepto'] ?? '');
            $importe   = isset($body['importe']) ? (float)$body['importe'] : null;
            if (!$concepto || $importe === null) { err('concepto e importe son obligatorios'); break; }

            $tiposOk = ['comision_venta','comision_alquiler','honorarios','gasto','ingreso_otro'];
            $tipo    = in_array($body['tipo'] ?? '', $tiposOk) ? $body['tipo'] : 'honorarios';
            $iva     = (float)($body['iva'] ?? 0);
            $total   = $importe + $iva;

            $ins = $db->prepare(
                "INSERT INTO finanzas (tipo, concepto, importe, iva, importe_total, fecha, estado, propiedad_id, cliente_id, agente_id, notas)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([
                $tipo, $concepto, $importe, $iva, $total,
                $body['fecha'] ?? date('Y-m-d'),
                $body['estado'] ?? 'pendiente',
                isset($body['propiedad_id']) ? (int)$body['propiedad_id'] : null,
                isset($body['cliente_id'])   ? (int)$body['cliente_id']   : null,
                $userId,
                $body['notas'] ?? null,
            ]);
            ok(['id' => (int)$db->lastInsertId(), 'mensaje' => 'Registro financiero creado']);
            break;
        }

        case 'marcar_cobrado': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }
            $af = $isAdmin ? '' : " AND agente_id = $userId";
            $stmt = $db->prepare("UPDATE finanzas SET estado = 'cobrado', updated_at = NOW() WHERE id = ? $af");
            $stmt->execute([$id]);
            if (!$stmt->rowCount()) { err('Registro no encontrado o sin acceso', 403); break; }
            ok(['mensaje' => 'Marcado como cobrado']);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // DOCUMENTOS
        // ══════════════════════════════════════════════════════════════════════

        case 'documentos': {
            $clienteId   = (int)($_GET['cliente_id'] ?? 0);
            $propiedadId = (int)($_GET['propiedad_id'] ?? 0);
            $limit       = min(50, max(1, (int)($_GET['limit'] ?? 20)));

            $where  = ['1=1'];
            $params = [];
            if ($clienteId)   { $where[] = 'd.cliente_id = ?';   $params[] = $clienteId; }
            if ($propiedadId) { $where[] = 'd.propiedad_id = ?'; $params[] = $propiedadId; }

            $sql = "SELECT d.id, d.nombre, d.tipo, d.tamano,
                           DATE_FORMAT(d.created_at,'%d/%m/%Y') as subido,
                           c.nombre as cliente_nombre,
                           p.titulo as propiedad_titulo
                    FROM documentos d
                    LEFT JOIN clientes c    ON d.cliente_id    = c.id
                    LEFT JOIN propiedades p ON d.propiedad_id  = p.id
                    WHERE " . implode(' AND ', $where) .
                   " ORDER BY d.created_at DESC LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            ok(['documentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // INFORMES
        // ══════════════════════════════════════════════════════════════════════

        case 'informe_prospectos': {
            $af  = $isAdmin ? '' : " AND agente_id = $userId";
            $afP = $isAdmin ? '' : " AND p.agente_id = $userId";

            // Embudo por etapa
            $stmt = $db->query("SELECT etapa, COUNT(*) as total, temperatura, AVG(precio_estimado) as precio_medio FROM prospectos WHERE activo = 1 $af GROUP BY etapa, temperatura ORDER BY FIELD(etapa,'nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'), temperatura");
            $embudo = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por temperatura
            $stmt = $db->query("SELECT temperatura, COUNT(*) as total FROM prospectos WHERE activo = 1 $af GROUP BY temperatura");
            $temperaturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Por prioridad
            $stmt = $db->query("SELECT prioridad, COUNT(*) as total FROM prospectos WHERE activo = 1 $af GROUP BY prioridad");
            $prioridades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contactar esta semana
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE activo = 1 AND fecha_proximo_contacto BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) $afP");
            $semana = (int)$stmt->fetchColumn();

            // Vencidos (sin contactar)
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE activo = 1 AND fecha_proximo_contacto < CURDATE() AND etapa NOT IN ('captado','descartado') $afP");
            $vencidos = (int)$stmt->fetchColumn();

            // Captados este mes
            $stmt = $db->query("SELECT COUNT(*) FROM prospectos p WHERE etapa = 'captado' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE()) $afP");
            $captadosMes = (int)$stmt->fetchColumn();

            ok([
                'informe' => [
                    'embudo_por_etapa_temperatura' => $embudo,
                    'por_temperatura'              => $temperaturas,
                    'por_prioridad'                => $prioridades,
                    'a_contactar_esta_semana'      => $semana,
                    'vencidos_sin_contactar'       => $vencidos,
                    'captados_este_mes'            => $captadosMes,
                ]
            ]);
            break;
        }

        case 'informe_finanzas': {
            $af = $isAdmin ? '' : " AND agente_id = $userId";

            // Por mes (últimos 6)
            $stmt = $db->query("SELECT DATE_FORMAT(fecha,'%Y-%m') as mes, tipo, SUM(importe_total) as total, COUNT(*) as operaciones FROM finanzas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $af GROUP BY mes, tipo ORDER BY mes DESC, tipo");
            $porMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pendiente de cobro
            $stmt = $db->query("SELECT tipo, COUNT(*) as registros, SUM(importe_total) as importe FROM finanzas WHERE estado = 'pendiente' $af GROUP BY tipo");
            $pendiente = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cobrado este año
            $stmt = $db->query("SELECT COALESCE(SUM(importe_total),0) FROM finanzas WHERE estado = 'cobrado' AND YEAR(fecha) = YEAR(CURDATE()) $af");
            $anio = round((float)$stmt->fetchColumn(), 2);

            ok(['informe' => ['por_mes' => $porMes, 'pendiente_cobro' => $pendiente, 'cobrado_anio_actual' => $anio]]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PIPELINES / KANBAN
        // ══════════════════════════════════════════════════════════════════════

        case 'pipeline_kanban': {
            $pipelineId = (int)($_GET['pipeline_id'] ?? 0);
            $af = $isAdmin ? '' : " AND pl.created_by = $userId";

            // Listar pipelines disponibles
            $stmt = $db->query("SELECT pl.id, pl.nombre, pl.descripcion, pl.color, (SELECT COUNT(*) FROM pipeline_items WHERE pipeline_id = pl.id) as total_items FROM pipelines pl WHERE pl.activo = 1 $af ORDER BY pl.id ASC");
            $pipelines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$pipelineId && !empty($pipelines)) $pipelineId = (int)$pipelines[0]['id'];

            $items = [];
            if ($pipelineId) {
                $stmt = $db->query("SELECT pe.id, pe.nombre as etapa, pe.color, pe.orden FROM pipeline_etapas pe WHERE pe.pipeline_id = $pipelineId ORDER BY pe.orden ASC");
                $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($etapas as &$etapa) {
                    $stmt2 = $db->prepare(
                        "SELECT pi.id, pi.titulo, pi.valor, pi.prioridad,
                                COALESCE(p.nombre, c.nombre) as contacto,
                                pr.titulo as propiedad_titulo
                         FROM pipeline_items pi
                         LEFT JOIN prospectos p  ON pi.prospecto_id  = p.id
                         LEFT JOIN clientes c    ON pi.cliente_id    = c.id
                         LEFT JOIN propiedades pr ON pi.propiedad_id = pr.id
                         WHERE pi.etapa_id = ? ORDER BY pi.created_at DESC LIMIT 20"
                    );
                    $stmt2->execute([$etapa['id']]);
                    $etapa['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                }
                $items = $etapas;
            }

            ok(['pipelines' => $pipelines, 'kanban' => $items, 'pipeline_activo' => $pipelineId]);
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // PORTALES INMOBILIARIOS
        // ══════════════════════════════════════════════════════════════════════

        case 'portales_propiedad': {
            $propId = (int)($_GET['propiedad_id'] ?? 0);
            if (!$propId) { err('propiedad_id requerido'); break; }

            // Portales donde está publicada
            $stmt = $db->prepare(
                "SELECT po.id, po.nombre, po.url as portal_url,
                        pp.estado, pp.url_publicacion,
                        DATE_FORMAT(pp.fecha_publicacion,'%d/%m/%Y') as publicado,
                        DATE_FORMAT(pp.fecha_actualizacion,'%d/%m/%Y') as actualizado,
                        pp.notas
                 FROM portales po
                 LEFT JOIN propiedad_portales pp ON pp.portal_id = po.id AND pp.propiedad_id = ?
                 WHERE po.activo = 1
                 ORDER BY po.nombre ASC"
            );
            $stmt->execute([$propId]);
            ok(['portales' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        case 'publicar_portal': {
            $propId   = (int)($body['propiedad_id'] ?? 0);
            $portalId = (int)($body['portal_id'] ?? 0);
            $accion   = $body['accion'] ?? 'publicar'; // publicar | retirar
            if (!$propId || !$portalId) { err('propiedad_id y portal_id son obligatorios'); break; }

            if ($accion === 'retirar') {
                $db->prepare("UPDATE propiedad_portales SET estado = 'retirado', fecha_actualizacion = CURDATE() WHERE propiedad_id = ? AND portal_id = ?")->execute([$propId, $portalId]);
                ok(['mensaje' => 'Propiedad retirada del portal']);
            } else {
                $stmt = $db->prepare("INSERT INTO propiedad_portales (propiedad_id, portal_id, estado, url_publicacion, fecha_publicacion, notas) VALUES (?,?,'publicado',?,CURDATE(),?) ON DUPLICATE KEY UPDATE estado='publicado', url_publicacion=VALUES(url_publicacion), fecha_actualizacion=CURDATE()");
                $stmt->execute([$propId, $portalId, $body['url'] ?? null, $body['notas'] ?? null]);
                ok(['mensaje' => 'Propiedad publicada en el portal']);
            }
            break;
        }

        // ══════════════════════════════════════════════════════════════════════
        // CONTRATOS — ENVIAR
        // ══════════════════════════════════════════════════════════════════════

        case 'enviar_contrato': {
            $id = (int)($body['id'] ?? 0);
            if (!$id) { err('ID requerido'); break; }

            $af = $isAdmin ? '' : " AND ct.usuario_id = $userId";
            $stmt = $db->prepare("SELECT ct.*, c.email as cliente_email, c.nombre as cliente_nombre FROM contratos ct LEFT JOIN clientes c ON ct.cliente_id = c.id WHERE ct.id = ? $af LIMIT 1");
            $stmt->execute([$id]);
            $ct = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ct) { err('Contrato no encontrado o sin acceso', 403); break; }
            if (empty($ct['cliente_email'])) { err('El cliente no tiene email registrado'); break; }

            $link   = getenv('APP_URL') ?: 'https://tinoprop.es';
            $link  .= "/contrato.php?token=" . $ct['token'];
            $asunto = "Contrato para firmar: " . $ct['titulo'];
            $cuerpo = "<p>Hola {$ct['cliente_nombre']},</p><p>Le enviamos el contrato <strong>{$ct['titulo']}</strong> para su revisión y firma.</p><p><a href=\"$link\">Firmar contrato</a></p>";

            $enviado = enviarEmail($ct['cliente_email'], $asunto, $cuerpo, true, $userId);
            if ($enviado) {
                $db->prepare("UPDATE contratos SET estado = 'enviado', updated_at = NOW() WHERE id = ?")->execute([$id]);
                ok(['mensaje' => "Contrato enviado a {$ct['cliente_email']}"]);
            } else {
                err('Error al enviar el email: ' . (getLastEmailError() ?? 'Error desconocido'));
            }
            break;
        }

        // ── DEBUG temporal: solo accesible con token válido ────────────────────
        case 'schema': {
            $tabla = preg_replace('/[^a-z_]/', '', strtolower($_GET['tabla'] ?? ''));
            if (!$tabla) { err('Parámetro tabla requerido'); break; }
            $stmt = $db->prepare("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
            $stmt->execute([$tabla]);
            ok(['tabla' => $tabla, 'columnas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        }

        default:
            err("Acción desconocida: '$action'");
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
