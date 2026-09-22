<?php
/**
 * Webhook Stripe para confirmacion server-to-server de pagos.
 * Requiere configurar stripe_webhook_secret en configuracion_pagos.
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

function stripeLog($message) {
    if (function_exists('logError')) {
        logError($message);
    } else {
        error_log($message);
    }
}

function badRequest($msg) {
    http_response_code(400);
    echo $msg;
    exit;
}

$config = $db->query("SELECT stripe_webhook_secret FROM configuracion_pagos WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$webhookSecret = trim($config['stripe_webhook_secret'] ?? '');
if ($webhookSecret === '') {
    badRequest('Webhook no configurado');
}

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    badRequest('Payload vacio');
}

$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
if ($sigHeader === '') {
    badRequest('Firma ausente');
}

$timestamp = null;
$signatures = [];
foreach (explode(',', $sigHeader) as $part) {
    $part = trim($part);
    if (strpos($part, '=') === false) {
        continue;
    }
    list($k, $v) = explode('=', $part, 2);
    if ($k === 't') {
        $timestamp = intval($v);
    } elseif ($k === 'v1') {
        $signatures[] = $v;
    }
}

if (!$timestamp || empty($signatures)) {
    badRequest('Firma invalida');
}

// Tolerancia de reloj de 5 minutos.
if (abs(time() - $timestamp) > 300) {
    badRequest('Firma expirada');
}

$signedPayload = $timestamp . '.' . $payload;
$expectedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);
$validSig = false;
foreach ($signatures as $sig) {
    if (hash_equals($expectedSig, $sig)) {
        $validSig = true;
        break;
    }
}

if (!$validSig) {
    badRequest('Firma no valida');
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['type'])) {
    badRequest('Evento invalido');
}

$eventType = $event['type'];
$object = $event['data']['object'] ?? [];

try {
    if ($eventType === 'charge.succeeded') {
        $chargeId = $object['id'] ?? null;
        $metadata = $object['metadata'] ?? [];
        $facturaId = intval($metadata['factura_id'] ?? 0);
        $tokenPago = trim($metadata['token_pago'] ?? '');

        if ($facturaId > 0) {
            if ($tokenPago !== '') {
                $stmt = $db->prepare("UPDATE facturas SET estado = 'pagada', metodo_pago = 'stripe', fecha_pago = NOW(), stripe_payment_id = ? WHERE id = ? AND token_pago = ? AND estado <> 'pagada' AND estado <> 'cancelada'");
                $stmt->execute([$chargeId, $facturaId, $tokenPago]);
            } else {
                $stmt = $db->prepare("UPDATE facturas SET estado = 'pagada', metodo_pago = 'stripe', fecha_pago = NOW(), stripe_payment_id = ? WHERE id = ? AND estado <> 'pagada' AND estado <> 'cancelada'");
                $stmt->execute([$chargeId, $facturaId]);
            }
        }
    }

    if ($eventType === 'charge.failed') {
        $metadata = $object['metadata'] ?? [];
        $facturaId = intval($metadata['factura_id'] ?? 0);
        if ($facturaId > 0) {
            // No cambia estado a pagada; solo registra trazabilidad.
            stripeLog('Stripe charge.failed para factura ' . $facturaId);
        }
    }
} catch (Exception $e) {
    stripeLog('Stripe webhook processing error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error interno';
    exit;
}

http_response_code(200);
echo 'ok';
