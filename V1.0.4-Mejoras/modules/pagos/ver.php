<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fid = intval(post('factura_id'));
    $estado = post('nuevo_estado');

    if (!isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM facturas WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$fid]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre esta factura.');
            header('Location: index.php');
            exit;
        }
    }

    $validos = ['borrador','enviada','pagada','vencida','cancelada'];
    if (in_array($estado, $validos)) {
        $db->prepare("UPDATE facturas SET estado = ? WHERE id = ?")->execute([$estado, $fid]);
        setFlash('success', 'Estado actualizado.');
    }
    header('Location: ver.php?id=' . $fid);
    exit;
}

$id = intval(get('id'));
$stmt = $db->prepare("SELECT f.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email, c.telefono as cli_telefono, c.direccion as cli_direccion, c.localidad as cli_localidad, c.provincia as cli_provincia, c.dni_nie_cif as cli_cif FROM facturas f LEFT JOIN clientes c ON f.cliente_id = c.id WHERE f.id = ?");
$stmt->execute([$id]);
$f = $stmt->fetch();
if (!$f) { setFlash('danger', 'Factura no encontrada.'); header('Location: index.php'); exit; }
if (!isAdmin() && intval($f['usuario_id']) !== intval(currentUserId())) { setFlash('danger', 'No tienes permisos para ver esta factura.'); header('Location: index.php'); exit; }

$config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch();
$lineas = json_decode($f['lineas'], true) ?: [];
$estadoClases = ['borrador'=>'secondary','enviada'=>'primary','pagada'=>'success','vencida'=>'danger','cancelada'=>'dark'];

$pageTitle = 'Factura ' . $f['numero'];
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
@media print {
    .sidebar, .top-navbar, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .content-wrapper { padding: 0 !important; }
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    <div class="d-flex gap-2">
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="factura_id" value="<?= $id ?>">
            <select name="nuevo_estado" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                <option value="">Cambiar estado...</option>
                <?php foreach (['borrador','enviada','pagada','vencida','cancelada'] as $e): ?>
                <?php if ($e !== $f['estado']): ?>
                <option value="<?= $e ?>"><?= ucfirst($e) ?></option>
                <?php endif; endforeach; ?>
            </select>
        </form>
        <button class="btn btn-sm btn-outline-info" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/pagar.php?token=<?= urlencode($f['token_pago'] ?? '') ?>');this.innerHTML='<i class=\'bi bi-check\'></i> Copiado';setTimeout(()=>this.innerHTML='<i class=\'bi bi-link-45deg\'></i> Enlace pago',2000)"><i class="bi bi-link-45deg"></i> Enlace pago</button>
        <a href="form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
        <div class="row mb-4">
            <div class="col-6">
                <h4 class="fw-bold mb-1"><?= sanitize($config['empresa_nombre'] ?: 'Tinoprop') ?></h4>
                <?php if ($config['empresa_cif']): ?><p class="mb-0 small"><?= sanitize($config['empresa_cif']) ?></p><?php endif; ?>
                <?php if ($config['empresa_direccion']): ?><p class="mb-0 small text-muted"><?= nl2br(sanitize($config['empresa_direccion'])) ?></p><?php endif; ?>
                <?php if ($config['empresa_email']): ?><p class="mb-0 small"><?= sanitize($config['empresa_email']) ?></p><?php endif; ?>
                <?php if ($config['empresa_telefono']): ?><p class="mb-0 small"><?= sanitize($config['empresa_telefono']) ?></p><?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <h2 class="fw-bold text-primary mb-1">FACTURA</h2>
                <p class="fs-5 mb-1"><?= sanitize($f['numero']) ?></p>
                <span class="badge bg-<?= $estadoClases[$f['estado']] ?> fs-6"><?= ucfirst($f['estado']) ?></span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small">Facturar a:</h6>
                <strong><?= sanitize(($f['cli_nombre'] ?? '') . ' ' . ($f['cli_apellidos'] ?? '')) ?: 'Sin cliente' ?></strong>
                <?php if ($f['cli_cif']): ?><br><small><?= sanitize($f['cli_cif']) ?></small><?php endif; ?>
                <?php if ($f['cli_direccion']): ?><br><small class="text-muted"><?= sanitize($f['cli_direccion']) ?>, <?= sanitize($f['cli_localidad'] ?? '') ?> <?= sanitize($f['cli_provincia'] ?? '') ?></small><?php endif; ?>
                <?php if ($f['cli_email']): ?><br><small><?= sanitize($f['cli_email']) ?></small><?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Emision:</strong> <?= formatFecha($f['fecha_emision']) ?></p>
                <?php if ($f['fecha_vencimiento']): ?>
                <p class="mb-1"><strong>Vencimiento:</strong> <?= formatFecha($f['fecha_vencimiento']) ?></p>
                <?php endif; ?>
                <?php if ($f['metodo_pago']): ?>
                <p class="mb-0"><strong>Pago:</strong> <?= ucfirst($f['metodo_pago']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <p class="fw-bold mb-3"><?= sanitize($f['concepto']) ?></p>

        <table class="table">
            <thead class="table-light">
                <tr><th>Descripcion</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lineas as $l):
                    $lineTotal = ($l['cantidad'] ?? 1) * ($l['precio_unitario'] ?? 0);
                    $lineIva = $lineTotal * (($l['iva'] ?? 0) / 100);
                ?>
                <tr>
                    <td><?= sanitize($l['descripcion'] ?? '') ?></td>
                    <td class="text-center"><?= $l['cantidad'] ?? 1 ?></td>
                    <td class="text-end"><?= formatPrecio($l['precio_unitario'] ?? 0) ?></td>
                    <td class="text-end"><?= $l['iva'] ?? 0 ?>%</td>
                    <td class="text-end fw-bold"><?= formatPrecio($lineTotal + $lineIva) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end border-0">Subtotal</td><td class="text-end border-0"><?= formatPrecio($f['subtotal']) ?></td></tr>
                <tr><td colspan="4" class="text-end border-0">IVA</td><td class="text-end border-0"><?= formatPrecio($f['iva_total']) ?></td></tr>
                <tr><td colspan="4" class="text-end border-0 fs-5 fw-bold">TOTAL</td><td class="text-end border-0 fs-5 fw-bold text-success"><?= formatPrecio($f['total']) ?></td></tr>
            </tfoot>
        </table>

        <?php if ($f['notas']): ?>
        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="text-muted small">NOTAS</h6>
            <p class="mb-0"><?= nl2br(sanitize($f['notas'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
