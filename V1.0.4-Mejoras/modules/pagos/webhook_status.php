<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if (!isAdmin()) {
    setFlash('danger', 'Solo administradores pueden acceder al estado del webhook.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Estado Webhook Stripe';
require_once __DIR__ . '/../../includes/header.php';

$config = $db->query("SELECT stripe_webhook_secret, stripe_public_key, stripe_secret_key, moneda FROM configuracion_pagos WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

$secretConfigured = !empty(trim($config['stripe_webhook_secret'] ?? ''));
$publicConfigured = !empty(trim($config['stripe_public_key'] ?? ''));
$secretKeyConfigured = !empty(trim($config['stripe_secret_key'] ?? ''));

$stmtLastPaid = $db->query("SELECT id, numero, total, fecha_pago, stripe_payment_id FROM facturas WHERE estado = 'pagada' AND metodo_pago = 'stripe' ORDER BY fecha_pago DESC, id DESC LIMIT 1");
$lastPaid = $stmtLastPaid->fetch(PDO::FETCH_ASSOC) ?: null;

$stmtLastStripeRef = $db->query("SELECT id, numero, estado, total, stripe_payment_id, fecha_pago, created_at FROM facturas WHERE stripe_payment_id IS NOT NULL AND stripe_payment_id <> '' ORDER BY id DESC LIMIT 1");
$lastStripeRef = $stmtLastStripeRef->fetch(PDO::FETCH_ASSOC) ?: null;

$stmtPending = $db->query("SELECT COUNT(*) FROM facturas WHERE stripe_payment_id IS NOT NULL AND stripe_payment_id <> '' AND estado <> 'pagada' AND estado <> 'cancelada'");
$pendingCount = (int) $stmtPending->fetchColumn();

$stmtTotalStripePaid = $db->query("SELECT COALESCE(SUM(total), 0) FROM facturas WHERE estado = 'pagada' AND metodo_pago = 'stripe'");
$totalStripePaid = (float) $stmtTotalStripePaid->fetchColumn();

$webhookUrl = APP_URL . '/api/stripe_webhook.php';
$healthOk = $secretConfigured && $publicConfigured && $secretKeyConfigured;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="config.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a configuracion</a>
    <button type="button" class="btn btn-outline-primary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Actualizar</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-5 fw-bold <?= $healthOk ? 'text-success' : 'text-danger' ?>"><?= $healthOk ? 'OK' : 'Pendiente' ?></div>
                <small class="text-muted">Configuracion webhook</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-5 fw-bold text-primary"><?= $pendingCount ?></div>
                <small class="text-muted">Pagos en espera de cierre</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-5 fw-bold text-success"><?= formatPrecio($totalStripePaid) ?></div>
                <small class="text-muted">Total cobrado por Stripe</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-3">
                <div class="fs-6 fw-bold"><?= sanitize(strtoupper($config['moneda'] ?? 'EUR')) ?></div>
                <small class="text-muted">Moneda</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="bi bi-shield-check"></i> Checklist de integracion</h6>
    </div>
    <div class="card-body">
        <div class="mb-2"><?= $publicConfigured ? '✅' : '❌' ?> Public Key configurada</div>
        <div class="mb-2"><?= $secretKeyConfigured ? '✅' : '❌' ?> Secret Key configurada</div>
        <div class="mb-2"><?= $secretConfigured ? '✅' : '❌' ?> Webhook Secret configurado</div>
        <hr>
        <label class="form-label fw-bold">URL webhook para Stripe</label>
        <div class="input-group">
            <input type="text" class="form-control" value="<?= sanitize($webhookUrl) ?>" readonly id="webhookStripeUrl">
            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('webhookStripeUrl').value)">
                <i class="bi bi-clipboard"></i>
            </button>
        </div>
        <small class="text-muted">Eventos recomendados: charge.succeeded y charge.failed</small>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-check-circle"></i> Ultimo pago confirmado</h6>
            </div>
            <div class="card-body">
                <?php if ($lastPaid): ?>
                    <p class="mb-1"><strong>Factura:</strong> <?= sanitize($lastPaid['numero']) ?></p>
                    <p class="mb-1"><strong>Importe:</strong> <?= formatPrecio($lastPaid['total']) ?></p>
                    <p class="mb-1"><strong>Fecha pago:</strong> <?= $lastPaid['fecha_pago'] ? formatFechaHora($lastPaid['fecha_pago']) : '-' ?></p>
                    <p class="mb-0"><strong>Stripe Payment ID:</strong> <code><?= sanitize($lastPaid['stripe_payment_id'] ?? '-') ?></code></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Aun no hay pagos Stripe confirmados.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-activity"></i> Ultima referencia Stripe registrada</h6>
            </div>
            <div class="card-body">
                <?php if ($lastStripeRef): ?>
                    <p class="mb-1"><strong>Factura:</strong> <?= sanitize($lastStripeRef['numero']) ?></p>
                    <p class="mb-1"><strong>Estado actual:</strong> <?= sanitize($lastStripeRef['estado']) ?></p>
                    <p class="mb-1"><strong>Creada:</strong> <?= $lastStripeRef['created_at'] ? formatFechaHora($lastStripeRef['created_at']) : '-' ?></p>
                    <p class="mb-0"><strong>Stripe Payment ID:</strong> <code><?= sanitize($lastStripeRef['stripe_payment_id']) ?></code></p>
                <?php else: ?>
                    <p class="text-muted mb-0">No hay referencias Stripe registradas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
