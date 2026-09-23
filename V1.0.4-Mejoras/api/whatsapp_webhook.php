<?php
/**
 * Webhook endpoint — WhatsApp Business Cloud API (Meta)
 *
 * GET  → Verificación del webhook por Meta
 * POST → Mensajes entrantes y actualizaciones de estado
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

// ── Verificar firma HMAC en peticiones POST (X-Hub-Signature-256) ────────────
// Protege contra mensajes falsos que no vengan de Meta
function waVerifySignature(string $rawBody): bool {
    $appSecret = getenv('META_APP_SECRET') ?: '';

    // Sin secret configurado: bloquear — no aceptar webhooks no verificados
    if ($appSecret === '') {
        error_log('[WhatsApp Webhook] META_APP_SECRET no configurado — petición rechazada.');
        return false;
    }

    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if ($signature === '') return false;

    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
    return hash_equals($expected, $signature);
}

// ── Cargar verify token ───────────────────────────────────────────────────────
function waWebhookVerifyToken(PDO $db): string {
    $token = getenv('META_WA_VERIFY_TOKEN') ?: '';
    if ($token) return $token;
    try {
        $stmt = $db->query("SELECT webhook_verify_token FROM whatsapp_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
        $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row && !empty($row['webhook_verify_token'])) return $row['webhook_verify_token'];
    } catch (Throwable $e) {}
    return '';
}

// ── Determinar propietario del mensaje entrante ───────────────────────────────
function waOwnerUserId(PDO $db, string $telefono): int {
    // 1. Mismo agente de conversaciones previas con este número
    try {
        $stmt = $db->prepare("SELECT created_by FROM whatsapp_mensajes WHERE telefono = ? AND created_by IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$telefono]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['created_by'])) return (int)$row['created_by'];
    } catch (Throwable $e) {}

    // 2. Primer admin
    try {
        $stmt = $db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1");
        $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($row) return (int)$row['id'];
    } catch (Throwable $e) {}

    return 1;
}

// ── Vincular número a cliente ─────────────────────────────────────────────────
function waFindCliente(PDO $db, string $telefono): ?int {
    $limpio = preg_replace('/[^0-9]/', '', $telefono);
    try {
        // Buscar por últimos 9 dígitos en la tabla clientes
        $stmt = $db->prepare("SELECT id FROM clientes WHERE REPLACE(REPLACE(REPLACE(telefono,' ',''),'+',''),'-','') LIKE ? LIMIT 1");
        $stmt->execute(['%' . substr($limpio, -9) . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];

        // Reutilizar vínculo previo de mensajes
        $stmt = $db->prepare("SELECT cliente_id FROM whatsapp_mensajes WHERE telefono = ? AND cliente_id IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$telefono]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['cliente_id'];
    } catch (Throwable $e) {}

    return null;
}

// ════════════════════════════════════════════════════════════════════════════════
// GET — Verificación del webhook
// ════════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

    $verifyToken = waWebhookVerifyToken($db);

    if ($mode === 'subscribe' && $verifyToken !== '' && $token === $verifyToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// ════════════════════════════════════════════════════════════════════════════════
// POST — Eventos de Meta
// ════════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');

    // Verificar firma HMAC de Meta antes de procesar
    if (!waVerifySignature($raw)) {
        http_response_code(403);
        echo 'Invalid signature';
        exit;
    }

    $data = json_decode($raw, true);

    // Solo procesamos eventos de whatsapp_business_account
    if (!$data || ($data['object'] ?? '') !== 'whatsapp_business_account') {
        http_response_code(200);
        echo 'OK';
        exit;
    }

    foreach ($data['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            if (($change['field'] ?? '') !== 'messages') continue;

            $value = $change['value'] ?? [];

            // ── Actualizaciones de estado de entrega ──────────────────────────
            foreach ($value['statuses'] ?? [] as $status) {
                $msgId     = $status['id']     ?? '';
                $msgStatus = $status['status'] ?? '';
                $estadoMap = [
                    'sent'      => 'enviado',
                    'delivered' => 'entregado',
                    'read'      => 'leido',
                    'failed'    => 'fallido',
                ];
                $nuevoEstado = $estadoMap[$msgStatus] ?? null;
                if ($nuevoEstado && $msgId) {
                    try {
                        $db->prepare("UPDATE whatsapp_mensajes SET estado = ? WHERE wa_message_id = ?")
                           ->execute([$nuevoEstado, $msgId]);
                    } catch (Throwable $e) {}
                }
            }

            // ── Mensajes entrantes ────────────────────────────────────────────
            foreach ($value['messages'] ?? [] as $message) {
                $from    = $message['from'] ?? '';
                $msgId   = $message['id']   ?? '';
                $msgType = $message['type'] ?? 'text';

                if (!$from || !$msgId) continue;

                // Evitar duplicados
                try {
                    $stmtDup = $db->prepare("SELECT id FROM whatsapp_mensajes WHERE wa_message_id = ? LIMIT 1");
                    $stmtDup->execute([$msgId]);
                    if ($stmtDup->fetch()) continue;
                } catch (Throwable $e) {}

                // Construir cuerpo del mensaje según tipo
                $body = '';
                $tipo = 'text';

                switch ($msgType) {
                    case 'text':
                        $body = $message['text']['body'] ?? '';
                        $tipo = 'text';
                        break;
                    case 'image':
                        $caption = $message['image']['caption'] ?? '';
                        $body    = '[Imagen' . ($caption ? ': ' . $caption : '') . ']';
                        $tipo    = 'image';
                        break;
                    case 'document':
                        $filename = $message['document']['filename'] ?? 'documento';
                        $body     = '[Documento: ' . $filename . ']';
                        $tipo     = 'document';
                        break;
                    case 'audio':
                        $body = '[Audio]';
                        $tipo = 'text';
                        break;
                    case 'video':
                        $caption = $message['video']['caption'] ?? '';
                        $body    = '[Vídeo' . ($caption ? ': ' . $caption : '') . ']';
                        $tipo    = 'image';
                        break;
                    case 'sticker':
                        $body = '[Sticker]';
                        $tipo = 'image';
                        break;
                    case 'location':
                        $lat = $message['location']['latitude']  ?? '';
                        $lng = $message['location']['longitude'] ?? '';
                        $body = '[Ubicación: ' . $lat . ', ' . $lng . ']';
                        $tipo = 'text';
                        break;
                    case 'contacts':
                        $body = '[Contacto compartido]';
                        $tipo = 'text';
                        break;
                    default:
                        $body = '[Mensaje tipo: ' . $msgType . ']';
                        $tipo = 'text';
                }

                if ($body === '') continue;

                $ownerUserId = waOwnerUserId($db, $from);
                $clienteId   = waFindCliente($db, $from);

                try {
                    $db->prepare("
                        INSERT INTO whatsapp_mensajes
                            (cliente_id, telefono, direccion, mensaje, tipo, wa_message_id, estado, created_by, created_at)
                        VALUES (?, ?, 'entrante', ?, ?, ?, 'recibido', ?, NOW())
                    ")->execute([$clienteId, $from, $body, $tipo, $msgId, $ownerUserId]);
                } catch (Throwable $e) {}
            }
        }
    }
}

http_response_code(200);
echo 'OK';
