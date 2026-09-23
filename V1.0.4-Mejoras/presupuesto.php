<?php
require_once __DIR__ . '/config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$db = getDB();

$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(404); echo '<h1>No encontrado</h1>'; exit; }

$p = $db->prepare("SELECT p.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email, c.telefono as cli_telefono, c.direccion as cli_dir, c.localidad as cli_loc, c.provincia as cli_prov, c.dni_nie_cif as cli_cif FROM presupuestos p LEFT JOIN clientes c ON p.cliente_id=c.id WHERE p.token=?");
$p->execute([$token]); $p=$p->fetch();
if (!$p) { http_response_code(404); echo '<h1>Presupuesto no encontrado</h1>'; exit; }

$config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch();
$lineas = json_decode($p['lineas'], true) ?: [];

if (empty($_SESSION['public_csrf_token'])) {
    $_SESSION['public_csrf_token'] = bin2hex(random_bytes(32));
}
$publicCsrfToken = $_SESSION['public_csrf_token'];

// Accept/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['decision']??'', ['aceptar','rechazar'])) {
    $csrf = $_POST['public_csrf_token'] ?? '';
    if (!hash_equals($publicCsrfToken, $csrf)) {
        http_response_code(403);
        echo '<h1>Solicitud invalida</h1>';
        exit;
    }
    $nuevoEstado = $_POST['decision'] === 'aceptar' ? 'aceptado' : 'rechazado';
    if (in_array($p['estado'], ['enviado','borrador'])) {
        $db->prepare("UPDATE presupuestos SET estado=?, aceptado_at=NOW(), aceptado_ip=? WHERE id=?")
            ->execute([$nuevoEstado, $_SERVER['REMOTE_ADDR'], $p['id']]);
        header('Location: presupuesto.php?token='.$token); exit;
    }
}

$estadoClases = ['borrador'=>'secondary','enviado'=>'primary','aceptado'=>'success','rechazado'=>'danger','expirado'=>'warning','convertido'=>'info'];
?>
<!DOCTYPE html>
<html lang="es"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto <?= htmlspecialchars($p['numero']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>*{font-family:'Inter',sans-serif}@media print{.no-print{display:none!important}}</style>
</head><body class="bg-light">

<div class="container py-4" style="max-width:800px">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <div class="row mb-4">
                <div class="col-6">
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($config['empresa_nombre']??'Tinoprop') ?></h4>
                    <?php if($config['empresa_cif']??''): ?><p class="mb-0 small"><?= htmlspecialchars($config['empresa_cif']) ?></p><?php endif; ?>
                    <?php if($config['empresa_direccion']??''): ?><p class="mb-0 small text-muted"><?= nl2br(htmlspecialchars($config['empresa_direccion'])) ?></p><?php endif; ?>
                </div>
                <div class="col-6 text-end">
                    <h2 class="fw-bold text-primary mb-1">PRESUPUESTO</h2>
                    <p class="fs-5 mb-1"><?= htmlspecialchars($p['numero']) ?></p>
                    <span class="badge bg-<?= $estadoClases[$p['estado']] ?> fs-6"><?= ucfirst($p['estado']) ?></span>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h6 class="text-muted small text-uppercase">Para:</h6>
                    <strong><?= htmlspecialchars(($p['cli_nombre']??'').' '.($p['cli_apellidos']??'')) ?></strong>
                    <?php if($p['cli_cif']): ?><br><small><?= htmlspecialchars($p['cli_cif']) ?></small><?php endif; ?>
                    <?php if($p['cli_email']): ?><br><small><?= htmlspecialchars($p['cli_email']) ?></small><?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($p['fecha_emision'])) ?></p>
                    <p class="mb-1"><strong>Valido hasta:</strong> <?= $p['fecha_expiracion']?date('d/m/Y',strtotime($p['fecha_expiracion'])):'-' ?></p>
                </div>
            </div>

            <?php if ($p['descripcion']): ?><p class="mb-4"><?= nl2br(htmlspecialchars($p['descripcion'])) ?></p><?php endif; ?>

            <table class="table"><thead class="table-light"><tr><th>Descripcion</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php foreach ($lineas as $l):
                $lt = floatval($l['cantidad']??1) * floatval($l['precio_unitario']??0);
                $li = $lt * (floatval($l['iva']??21)/100);
            ?>
            <tr><td><?= htmlspecialchars($l['descripcion']??'') ?></td><td class="text-end"><?= $l['cantidad'] ?></td><td class="text-end"><?= number_format($l['precio_unitario'],2,',','.') ?> &euro;</td><td class="text-end"><?= $l['iva'] ?>%</td><td class="text-end fw-semibold"><?= number_format($lt+$li,2,',','.') ?> &euro;</td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end fw-bold">Subtotal</td><td class="text-end"><?= number_format($p['subtotal'],2,',','.') ?> &euro;</td></tr>
                <tr><td colspan="4" class="text-end">IVA</td><td class="text-end"><?= number_format($p['iva_total'],2,',','.') ?> &euro;</td></tr>
                <tr><td colspan="4" class="text-end fw-bold fs-5">Total</td><td class="text-end fw-bold fs-5" style="color:#10b981"><?= number_format($p['total'],2,',','.') ?> &euro;</td></tr>
            </tfoot>
            </table>

            <?php if ($p['condiciones']): ?><div class="mt-4 p-3 bg-light rounded"><h6 class="small text-uppercase text-muted">Condiciones</h6><p class="small mb-0"><?= nl2br(htmlspecialchars($p['condiciones'])) ?></p></div><?php endif; ?>
            <?php if ($p['notas']): ?><div class="mt-3"><h6 class="small text-uppercase text-muted">Notas</h6><p class="small mb-0"><?= nl2br(htmlspecialchars($p['notas'])) ?></p></div><?php endif; ?>

            <?php if (in_array($p['estado'], ['enviado','borrador'])): ?>
            <div class="mt-4 text-center no-print">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="public_csrf_token" value="<?= htmlspecialchars($publicCsrfToken) ?>">
                    <input type="hidden" name="decision" value="aceptar">
                    <button class="btn btn-success btn-lg me-2"><i class="bi bi-check-lg"></i> Aceptar Presupuesto</button>
                </form>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="public_csrf_token" value="<?= htmlspecialchars($publicCsrfToken) ?>">
                    <input type="hidden" name="decision" value="rechazar">
                    <button class="btn btn-outline-danger btn-lg"><i class="bi bi-x-lg"></i> Rechazar</button>
                </form>
            </div>
            <?php elseif ($p['estado'] === 'aceptado'): ?>
            <div class="alert alert-success text-center mt-4"><i class="bi bi-check-circle"></i> Presupuesto aceptado<?= $p['aceptado_at']?' el '.date('d/m/Y H:i',strtotime($p['aceptado_at'])):'' ?></div>
            <?php elseif ($p['estado'] === 'rechazado'): ?>
            <div class="alert alert-danger text-center mt-4"><i class="bi bi-x-circle"></i> Presupuesto rechazado</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="text-center mt-3 no-print"><button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button></div>
</div>
</body></html>
