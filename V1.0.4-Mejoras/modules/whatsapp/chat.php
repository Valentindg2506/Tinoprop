<?php
// Detectar si es petición AJAX antes de cualquier output
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isAjax) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/helpers.php';
    requireLogin();
    header('Content-Type: application/json; charset=utf-8');
} else {
    $pageTitle = 'WhatsApp Chat';
    require_once __DIR__ . '/../../includes/header.php';
}

$db     = getDB();
$userId = (int) currentUserId();

// ── Cargar credenciales Meta Cloud API ───────────────────────────────────────
function waGetConfig(PDO $db): array {
    $cfg = [
        'access_token'    => getenv('META_WA_ACCESS_TOKEN')    ?: '',
        'phone_number_id' => getenv('META_WA_PHONE_NUMBER_ID') ?: '',
    ];
    if (!$cfg['access_token'] || !$cfg['phone_number_id']) {
        try {
            $stmt = $db->query("SELECT access_token, phone_number_id FROM whatsapp_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
            $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($row) {
                $cfg['access_token']    = $cfg['access_token']    ?: ($row['access_token']    ?? '');
                $cfg['phone_number_id'] = $cfg['phone_number_id'] ?: ($row['phone_number_id'] ?? '');
            }
        } catch (Throwable $e) {}
    }
    return $cfg;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAjax) {
        verifyCsrf();
    } else {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
            exit;
        }
    }

    $telefono = post('telefono');
    $accion   = post('accion');

    if (empty($telefono)) {
        if ($isAjax) { echo json_encode(['success' => false, 'error' => 'Teléfono no especificado.']); exit; }
        setFlash('danger', 'Telefono no especificado.');
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php');
        exit;
    }

    // ── Vincular cliente ─────────────────────────────────────────────────────
    if ($accion === 'vincular') {
        $clienteId = post('cliente_id');
        if (!empty($clienteId)) {
            $stmt = $db->prepare("UPDATE whatsapp_mensajes SET cliente_id = ? WHERE telefono = ? AND created_by = ?");
            $stmt->execute([$clienteId, $telefono, $userId]);
            registrarActividad('vincular', 'whatsapp', $clienteId, 'Vincular telefono ' . $telefono . ' a cliente');
            setFlash('success', 'Cliente vinculado correctamente.');
        } else {
            setFlash('danger', 'Debes seleccionar un cliente.');
        }
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    // ── Enviar mensaje ───────────────────────────────────────────────────────
    $mensaje = post('mensaje');

    if (empty($mensaje)) {
        if ($isAjax) { echo json_encode(['success' => false, 'error' => 'El mensaje no puede estar vacío.']); exit; }
        setFlash('danger', 'El mensaje no puede estar vacio.');
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    // Obtener cliente vinculado
    $stmtCliente = $db->prepare("SELECT cliente_id FROM whatsapp_mensajes WHERE telefono = ? AND created_by = ? AND cliente_id IS NOT NULL LIMIT 1");
    $stmtCliente->execute([$telefono, $userId]);
    $clienteId = $stmtCliente->fetchColumn() ?: null;

    // Cargar config Meta
    $waCfg = waGetConfig($db);
    $accessToken    = $waCfg['access_token'];
    $phoneNumberId  = $waCfg['phone_number_id'];

    if (!$accessToken || !$phoneNumberId) {
        $errMsg = 'WhatsApp no está configurado. <a href="' . APP_URL . '/modules/whatsapp/config.php">Configura Meta API</a>.';
        if ($isAjax) { echo json_encode(['success' => false, 'error' => strip_tags($errMsg)]); exit; }
        setFlash('danger', $errMsg);
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    // Normalizar número de destino (solo dígitos, con +)
    $telefonoDestino = preg_replace('/[^0-9]/', '', (string)$telefono);
    if ($telefonoDestino === '') {
        if ($isAjax) { echo json_encode(['success' => false, 'error' => 'Número de teléfono inválido.']); exit; }
        header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
        exit;
    }

    $waEstado    = 'fallido';
    $waMessageId = null;
    $errorEnvio  = '';

    if (!function_exists('curl_init')) {
        $errorEnvio = 'cURL no está habilitado en el servidor.';
    } else {
        // ── Meta Cloud API — Enviar mensaje con reintentos ───────────────────
        $endpoint = "https://graph.facebook.com/v20.0/{$phoneNumberId}/messages";
        $payload  = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $telefonoDestino,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $mensaje],
        ]);

        $maxIntentos = 3;
        $intentosRealizados = 0;

        while ($intentosRealizados < $maxIntentos) {
            $intentosRealizados++;

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 30, // Aumentado a 30s para cubrir latencia de Meta
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $responseBody = curl_exec($ch);
            $curlError    = curl_error($ch);
            $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $responseData = is_string($responseBody) ? json_decode($responseBody, true) : null;

            if ($curlError !== '') {
                $errorEnvio = 'Error de conexión: ' . $curlError;
                // Reintento en error de red
                if ($intentosRealizados < $maxIntentos) { sleep(2); continue; }
                break;
            }

            if ($httpCode >= 200 && $httpCode < 300 && !empty($responseData['messages'][0]['id'])) {
                $waEstado    = 'enviado';
                $waMessageId = $responseData['messages'][0]['id'];
                $errorEnvio  = '';
                break; // Éxito — salir del bucle
            }

            $apiMsg  = $responseData['error']['message'] ?? '';
            $apiCode = isset($responseData['error']['code']) ? '(código ' . $responseData['error']['code'] . ')' : '';
            $errorEnvio = trim($apiMsg . ' ' . $apiCode) ?: 'HTTP ' . $httpCode . ' inesperado desde Meta.';

            // Reintentar solo en errores transitorios (429 rate-limit, 5xx servidor)
            $esTransitorio = $httpCode === 429 || $httpCode >= 500;
            if ($esTransitorio && $intentosRealizados < $maxIntentos) {
                $espera = $httpCode === 429 ? 5 : 2; // 5s si rate-limit, 2s si error de servidor
                sleep($espera);
                continue;
            }
            break; // Error permanente (4xx que no es 429) — no reintentar
        }
    }

    // Guardar en BD
    $stmt = $db->prepare("
        INSERT INTO whatsapp_mensajes (cliente_id, telefono, direccion, mensaje, tipo, wa_message_id, estado, created_by, created_at)
        VALUES (?, ?, 'saliente', ?, 'text', ?, ?, ?, NOW())
    ");
    $stmt->execute([$clienteId, $telefono, $mensaje, $waMessageId, $waEstado, $userId]);
    $nuevoId = (int)$db->lastInsertId();

    registrarActividad('enviar', 'whatsapp_mensaje', $nuevoId, 'Mensaje a ' . $telefono);

    if ($isAjax) {
        if ($waEstado === 'fallido') {
            echo json_encode(['success' => false, 'error' => $errorEnvio ?: 'Error al enviar el mensaje.', 'id' => $nuevoId, 'estado' => $waEstado]);
        } else {
            echo json_encode(['success' => true, 'id' => $nuevoId, 'estado' => $waEstado, 'created_at' => date('Y-m-d H:i:s')]);
        }
        exit;
    }

    if ($waEstado === 'fallido') {
        setFlash('danger', 'Error al enviar: ' . $errorEnvio);
    }
    header('Location: ' . APP_URL . '/modules/whatsapp/index.php?telefono=' . urlencode($telefono));
    exit;
}

header('Location: ' . APP_URL . '/modules/whatsapp/index.php');
exit;
