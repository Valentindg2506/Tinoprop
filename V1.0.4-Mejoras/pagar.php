<?php
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDB();

$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(404); echo '<h1>Enlace no valido</h1>'; exit; }

$stmt = $db->prepare("SELECT f.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email FROM facturas f LEFT JOIN clientes c ON f.cliente_id = c.id WHERE f.token_pago = ?");
$stmt->execute([$token]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$f) { http_response_code(404); echo '<h1>Factura no encontrada</h1>'; exit; }
if ($f['estado'] === 'pagada') { $pagada = true; }
if ($f['estado'] === 'cancelada') { echo '<h1>Factura cancelada</h1>'; exit; }

$config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$lineas = json_decode($f['lineas'], true) ?: [];
$moneda = $config['moneda'] ?? 'EUR';
$stripeKey = $config['stripe_public_key'] ?? '';
$stripeSecret = $config['stripe_secret_key'] ?? '';
$empresa = $config['empresa_nombre'] ?? 'Empresa';
$paymentError = '';
$paymentPending = false;

if (empty($_SESSION['public_csrf_token'])) {
    $_SESSION['public_csrf_token'] = bin2hex(random_bytes(32));
}
$publicCsrfToken = $_SESSION['public_csrf_token'];

// Handle Stripe payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_intent'])) {
    $csrf = $_POST['public_csrf_token'] ?? '';
    if (!hash_equals($publicCsrfToken, $csrf)) {
        http_response_code(403);
        echo '<h1>Solicitud invalida</h1>';
        exit;
    }
    if (empty($stripeKey) || empty($stripeSecret) || in_array($f['estado'], ['pagada', 'cancelada'], true)) {
        http_response_code(400);
        echo '<h1>Operacion no permitida</h1>';
        exit;
    }

    $stripeToken = trim($_POST['payment_intent'] ?? '');
    if ($stripeToken === '') {
        $paymentError = 'No se recibio token de pago.';
    } elseif (!function_exists('curl_init')) {
        $paymentError = 'El servidor no tiene soporte cURL para procesar pagos.';
    } else {
        $amountCents = (int) round(floatval($f['total']) * 100);
        if ($amountCents <= 0) {
            $paymentError = 'Importe de factura invalido.';
        } else {
            $payload = http_build_query([
                'amount' => $amountCents,
                'currency' => strtolower($moneda),
                'source' => $stripeToken,
                'description' => 'Pago factura ' . ($f['numero'] ?? ''),
                'metadata[factura_id]' => (string) $f['id'],
                'metadata[token_pago]' => $token,
            ]);

            $idempotencyKey = 'factura_' . $f['id'] . '_token_' . hash('sha256', $token);
            $ch = curl_init('https://api.stripe.com/v1/charges');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERPWD => $stripeSecret . ':',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Idempotency-Key: ' . $idempotencyKey,
                ],
            ]);

            $resp = curl_exec($ch);
            $curlErr = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp === false) {
                $paymentError = 'No se pudo conectar con Stripe.';
                if (function_exists('logError')) {
                    logError('Stripe charge transport error: ' . $curlErr);
                } else {
                    error_log('Stripe charge transport error: ' . $curlErr);
                }
            } else {
                $charge = json_decode($resp, true);
                $succeeded = $httpCode >= 200 && $httpCode < 300 && !empty($charge['paid']) && ($charge['status'] ?? '') === 'succeeded';

                if ($succeeded) {
                    // Webhook-first: el estado final se confirma solo por api/stripe_webhook.php
                    $stripePaymentId = $charge['id'] ?? null;
                    if (!empty($stripePaymentId)) {
                        $db->prepare("UPDATE facturas SET stripe_payment_id = ?, metodo_pago = 'stripe' WHERE id = ? AND estado <> 'pagada' AND estado <> 'cancelada'")
                           ->execute([$stripePaymentId, $f['id']]);
                    }
                    header('Location: pagar.php?token=' . urlencode($token) . '&pending=1');
                    exit;
                }

                $stripeMsg = $charge['error']['message'] ?? 'Pago rechazado por Stripe.';
                $paymentError = $stripeMsg;
                if (function_exists('logError')) {
                    logError('Stripe charge error: HTTP ' . $httpCode . ' - ' . $stripeMsg);
                } else {
                    error_log('Stripe charge error: HTTP ' . $httpCode . ' - ' . $stripeMsg);
                }
            }
        }
    }
}

$showSuccess = isset($_GET['ok']) || ($f['estado'] === 'pagada');
$paymentPending = isset($_GET['pending']) && !$showSuccess;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar Factura <?= htmlspecialchars($f['numero']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .pay-card { max-width: 520px; width: 100%; }
        .line-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
        .line-item:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="pay-card">
    <?php if ($showSuccess): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-check-circle text-success" style="font-size:4rem"></i>
        <h3 class="mt-3 fw-bold">Pago completado</h3>
        <p class="text-muted">Factura <?= htmlspecialchars($f['numero']) ?> pagada correctamente.</p>
        <p class="text-muted">Gracias por tu pago.</p>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white text-center py-4">
            <h5 class="fw-bold mb-1"><?= htmlspecialchars($empresa) ?></h5>
            <p class="text-muted mb-0">Factura <?= htmlspecialchars($f['numero']) ?></p>
        </div>
        <div class="card-body">
            <?php if ($paymentPending): ?>
            <div class="alert alert-warning" role="alert">
                Pago procesado. Estamos esperando confirmacion segura del banco via webhook.
                Si en unos segundos no cambia, recarga esta pagina.
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <small class="text-muted">Cliente</small>
                    <div class="fw-semibold"><?= htmlspecialchars(($f['cli_nombre'] ?? '') . ' ' . ($f['cli_apellidos'] ?? '')) ?></div>
                </div>
                <div class="text-end">
                    <small class="text-muted">Fecha</small>
                    <div><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></div>
                </div>
            </div>

            <?php if ($f['fecha_vencimiento']): ?>
            <div class="mb-3">
                <small class="text-muted">Vencimiento:</small> <?= date('d/m/Y', strtotime($f['fecha_vencimiento'])) ?>
            </div>
            <?php endif; ?>

            <hr>
            <h6 class="fw-bold mb-2">Detalle</h6>
            <?php foreach ($lineas as $l): ?>
            <div class="line-item">
                <div>
                    <div><?= htmlspecialchars($l['descripcion'] ?? '') ?></div>
                    <small class="text-muted"><?= intval($l['cantidad'] ?? 1) ?> x <?= number_format(floatval($l['precio_unitario'] ?? 0), 2, ',', '.') ?> <?= $moneda ?></small>
                </div>
                <div class="fw-semibold"><?= number_format((floatval($l['cantidad'] ?? 1) * floatval($l['precio_unitario'] ?? 0)), 2, ',', '.') ?> <?= $moneda ?></div>
            </div>
            <?php endforeach; ?>

            <hr>
            <div class="d-flex justify-content-between">
                <span>Subtotal</span>
                <span><?= number_format(floatval($f['subtotal']), 2, ',', '.') ?> <?= $moneda ?></span>
            </div>
            <?php if (floatval($f['iva_total']) > 0): ?>
            <div class="d-flex justify-content-between text-muted">
                <span>IVA</span>
                <span><?= number_format(floatval($f['iva_total']), 2, ',', '.') ?> <?= $moneda ?></span>
            </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between fw-bold fs-5 mt-2" style="color:#10b981">
                <span>Total</span>
                <span><?= number_format(floatval($f['total']), 2, ',', '.') ?> <?= $moneda ?></span>
            </div>
        </div>

        <?php if (!empty($stripeKey)): ?>
        <div class="card-footer bg-white p-4">
            <div id="stripe-payment">
                <div id="card-element" class="form-control mb-3" style="padding:12px"></div>
                <div id="card-errors" class="text-danger small mb-2"><?= !empty($paymentError) ? htmlspecialchars($paymentError) : '' ?></div>
                <button id="payBtn" class="btn btn-success w-100 btn-lg">
                    <i class="bi bi-lock"></i> Pagar <?= number_format(floatval($f['total']), 2, ',', '.') ?> <?= $moneda ?>
                </button>
            </div>
        </div>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
        const stripe = Stripe('<?= htmlspecialchars($stripeKey) ?>');
        const elements = stripe.elements();
        const card = elements.create('card', {style:{base:{fontSize:'16px',fontFamily:'Inter, sans-serif'}}});
        card.mount('#card-element');
        card.on('change', function(e) {
            document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
        });
        document.getElementById('payBtn').addEventListener('click', async function() {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Procesando...';
            const {token, error} = await stripe.createToken(card);
            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-lock"></i> Pagar <?= number_format(floatval($f['total']), 2, ',', '.') ?> <?= $moneda ?>';
            } else {
                const form = document.createElement('form');
                form.method = 'POST';
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'payment_intent'; input.value = token.id;
                form.appendChild(input);
                const csrf = document.createElement('input');
                csrf.type = 'hidden'; csrf.name = 'public_csrf_token'; csrf.value = '<?= htmlspecialchars($publicCsrfToken) ?>';
                form.appendChild(csrf);
                document.body.appendChild(form);
                form.submit();
            }
        });
        </script>
        <?php else: ?>
        <div class="card-footer bg-white p-4 text-center">
            <p class="text-muted mb-2">Para realizar el pago, contacte con nosotros:</p>
            <?php if (!empty($config['empresa_email'])): ?>
            <a href="mailto:<?= htmlspecialchars($config['empresa_email']) ?>" class="btn btn-outline-primary">
                <i class="bi bi-envelope"></i> <?= htmlspecialchars($config['empresa_email']) ?>
            </a>
            <?php endif; ?>
            <?php if (!empty($config['empresa_telefono'])): ?>
            <a href="tel:<?= htmlspecialchars($config['empresa_telefono']) ?>" class="btn btn-outline-success ms-2">
                <i class="bi bi-telephone"></i> <?= htmlspecialchars($config['empresa_telefono']) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="text-center mt-3">
        <small class="text-muted">Powered by Tinoprop</small>
    </div>
</div>

</body>
</html>
