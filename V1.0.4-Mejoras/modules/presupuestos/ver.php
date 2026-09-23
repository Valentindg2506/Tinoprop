<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$id = intval(get('id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');
    $pid = intval(post('pid'));

    if ($accion === 'cambiar_estado' && $pid > 0) {
        $stmtOwn = $db->prepare("SELECT usuario_id FROM presupuestos WHERE id = ? LIMIT 1");
        $stmtOwn->execute([$pid]);
        $ownerId = intval($stmtOwn->fetchColumn());
        if (!isAdmin() && $ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre este presupuesto.');
            header('Location: index.php');
            exit;
        }

        $nuevoEstado = post('nuevo_estado');
        $validos = ['borrador', 'enviado', 'aceptado', 'rechazado', 'expirado', 'convertido'];
        if (in_array($nuevoEstado, $validos, true)) {
            $db->prepare("UPDATE presupuestos SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $pid]);
            setFlash('success', 'Estado actualizado.');
        }

        header('Location: ver.php?id=' . $pid);
        exit;
    }
}

$stmt = $db->prepare("SELECT p.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email, c.telefono as cli_telefono, c.direccion as cli_direccion, c.localidad as cli_localidad, c.provincia as cli_provincia, c.dni_nie_cif as cli_cif FROM presupuestos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    setFlash('danger', 'Presupuesto no encontrado.');
    header('Location: index.php');
    exit;
}

if (!isAdmin() && intval($p['usuario_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para ver este presupuesto.');
    header('Location: index.php');
    exit;
}

$config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch();
$lineas = json_decode($p['lineas'], true) ?: [];
$estadoClases = ['borrador' => 'secondary', 'enviado' => 'primary', 'aceptado' => 'success', 'rechazado' => 'danger', 'expirado' => 'warning', 'convertido' => 'info'];

$pageTitle = 'Presupuesto ' . ($p['numero'] ?? '');
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
            <input type="hidden" name="accion" value="cambiar_estado">
            <input type="hidden" name="pid" value="<?= $id ?>">
            <select name="nuevo_estado" class="form-select form-select-sm d-inline-block" style="width:auto" onchange="this.form.submit()">
                <option value="">Cambiar estado...</option>
                <?php foreach (['borrador', 'enviado', 'aceptado', 'rechazado', 'expirado', 'convertido'] as $e): ?>
                <?php if ($e !== $p['estado']): ?>
                <option value="<?= $e ?>"><?= ucfirst($e) ?></option>
                <?php endif; endforeach; ?>
            </select>
        </form>
        <a href="form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
        <div class="row mb-4">
            <div class="col-6">
                <h4 class="fw-bold mb-1"><?= sanitize($config['empresa_nombre'] ?? 'Tinoprop') ?></h4>
                <?php if (!empty($config['empresa_cif'])): ?><p class="mb-0 small"><?= sanitize($config['empresa_cif']) ?></p><?php endif; ?>
                <?php if (!empty($config['empresa_direccion'])): ?><p class="mb-0 small text-muted"><?= nl2br(sanitize($config['empresa_direccion'])) ?></p><?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <h2 class="fw-bold text-primary mb-1">PRESUPUESTO</h2>
                <p class="fs-5 mb-1"><?= sanitize($p['numero']) ?></p>
                <span class="badge bg-<?= $estadoClases[$p['estado']] ?? 'secondary' ?> fs-6"><?= ucfirst($p['estado']) ?></span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted text-uppercase small">Para:</h6>
                <strong><?= sanitize(($p['cli_nombre'] ?? '') . ' ' . ($p['cli_apellidos'] ?? '')) ?: 'Sin cliente' ?></strong>
                <?php if ($p['cli_cif']): ?><br><small><?= sanitize($p['cli_cif']) ?></small><?php endif; ?>
                <?php if ($p['cli_email']): ?><br><small><?= sanitize($p['cli_email']) ?></small><?php endif; ?>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="mb-1"><strong>Fecha:</strong> <?= formatFecha($p['fecha_emision']) ?></p>
                <p class="mb-1"><strong>Valido hasta:</strong> <?= $p['fecha_expiracion'] ? formatFecha($p['fecha_expiracion']) : '-' ?></p>
            </div>
        </div>

        <?php if (!empty($p['descripcion'])): ?>
        <p class="mb-4"><?= nl2br(sanitize($p['descripcion'])) ?></p>
        <?php endif; ?>

        <table class="table">
            <thead class="table-light">
                <tr><th>Descripcion</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lineas as $l):
                $cant = floatval($l['cantidad'] ?? 1);
                $precio = floatval($l['precio_unitario'] ?? 0);
                $iva = floatval($l['iva'] ?? 21);
                $lt = $cant * $precio;
                $li = $lt * ($iva / 100);
            ?>
            <tr>
                <td><?= sanitize($l['descripcion'] ?? '') ?></td>
                <td class="text-end"><?= number_format($cant, 2, ',', '.') ?></td>
                <td class="text-end"><?= formatPrecio($precio) ?></td>
                <td class="text-end"><?= number_format($iva, 2, ',', '.') ?>%</td>
                <td class="text-end fw-semibold"><?= formatPrecio($lt + $li) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="text-end fw-bold">Subtotal</td><td class="text-end"><?= formatPrecio($p['subtotal']) ?></td></tr>
                <tr><td colspan="4" class="text-end">IVA</td><td class="text-end"><?= formatPrecio($p['iva_total']) ?></td></tr>
                <tr><td colspan="4" class="text-end fw-bold fs-5">Total</td><td class="text-end fw-bold fs-5" style="color:#10b981"><?= formatPrecio($p['total']) ?></td></tr>
            </tfoot>
        </table>

        <?php if (!empty($p['condiciones'])): ?>
        <div class="mt-4 p-3 bg-light rounded">
            <h6 class="small text-uppercase text-muted">Condiciones</h6>
            <p class="small mb-0"><?= nl2br(sanitize($p['condiciones'])) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($p['notas'])): ?>
        <div class="mt-3">
            <h6 class="small text-uppercase text-muted">Notas</h6>
            <p class="small mb-0"><?= nl2br(sanitize($p['notas'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
