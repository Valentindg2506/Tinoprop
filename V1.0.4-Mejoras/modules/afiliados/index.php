<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'crear') {
        $clienteId = intval(post('cliente_id'));
        $cl = $db->prepare("SELECT * FROM clientes WHERE id=?"); $cl->execute([$clienteId]); $cl=$cl->fetch();
        if ($cl) {
            $codigo = strtoupper(substr(md5($cl['email'].time()), 0, 8));
            $db->prepare("INSERT INTO afiliados (cliente_id, codigo, nombre, email, comision_porcentaje) VALUES (?,?,?,?,?)")
                ->execute([$clienteId, $codigo, ($cl['nombre']??'').' '.($cl['apellidos']??''), $cl['email']??'', floatval(post('comision',10))]);
            setFlash('success','Afiliado creado. Codigo: '.$codigo);
        }
    }
    if ($a === 'toggle') { $db->prepare("UPDATE afiliados SET activo=NOT activo WHERE id=?")->execute([intval(post('aid'))]); }
    if ($a === 'pagar') {
        $aid = intval(post('aid'));
        $db->prepare("UPDATE afiliado_referidos SET pagado=1 WHERE afiliado_id=? AND pagado=0")->execute([$aid]);
        $db->prepare("UPDATE afiliados SET saldo_pendiente=0 WHERE id=?")->execute([$aid]);
        setFlash('success','Comisiones marcadas como pagadas.');
    }
    header('Location: index.php'); exit;
}

$pageTitle = 'Programa de Afiliados';
require_once __DIR__ . '/../../includes/header.php';
$afiliados = $db->query("SELECT * FROM afiliados ORDER BY created_at DESC")->fetchAll();
$clientes = $db->query("SELECT id, nombre, apellidos, email FROM clientes WHERE activo=1 AND id NOT IN (SELECT cliente_id FROM afiliados) ORDER BY nombre")->fetchAll();
$totalComisiones = array_sum(array_column($afiliados, 'total_comisiones'));
$totalPendiente = array_sum(array_column($afiliados, 'saldo_pendiente'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($afiliados) ?> afiliado<?= count($afiliados)!==1?'s':'' ?></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="bi bi-plus-lg"></i> Nuevo Afiliado</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold mb-0"><?= count($afiliados) ?></h4><small class="text-muted">Afiliados</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold mb-0"><?= array_sum(array_column($afiliados,'total_referidos')) ?></h4><small class="text-muted">Referidos</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold text-success mb-0"><?= number_format($totalComisiones,0,',','.') ?>&euro;</h4><small class="text-muted">Comisiones Total</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold text-warning mb-0"><?= number_format($totalPendiente,0,',','.') ?>&euro;</h4><small class="text-muted">Pendiente Pago</small></div></div>
</div>

<?php if (!empty($afiliados)): ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Afiliado</th><th>Codigo</th><th>Comision %</th><th>Referidos</th><th>Comisiones</th><th>Pendiente</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($afiliados as $af): ?>
        <tr>
            <td><strong><?= sanitize($af['nombre']) ?></strong><br><small class="text-muted"><?= sanitize($af['email']) ?></small></td>
            <td><code><?= $af['codigo'] ?></code> <button class="btn btn-xs btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/ref.php?c=<?= $af['codigo'] ?>')"><i class="bi bi-link"></i></button></td>
            <td><?= $af['comision_porcentaje'] ?>%</td>
            <td><?= $af['total_referidos'] ?></td>
            <td><?= number_format($af['total_comisiones'],2,',','.') ?>&euro;</td>
            <td class="fw-bold text-warning"><?= number_format($af['saldo_pendiente'],2,',','.') ?>&euro;</td>
            <td><span class="badge bg-<?= $af['activo']?'success':'secondary' ?>"><?= $af['activo']?'Activo':'Inactivo' ?></span></td>
            <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                    <?php if ($af['saldo_pendiente'] > 0): ?>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="pagar"><input type="hidden" name="aid" value="<?= $af['id'] ?>"><button class="btn btn-xs btn-outline-success" title="Marcar pagado"><i class="bi bi-cash"></i></button></form>
                    <?php endif; ?>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="aid" value="<?= $af['id'] ?>"><button class="btn btn-xs btn-outline-<?= $af['activo']?'warning':'success' ?>"><i class="bi bi-toggle-<?= $af['activo']?'on':'off' ?>"></i></button></form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear">
    <div class="modal-header"><h5 class="modal-title">Nuevo Afiliado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Cliente</label><select name="cliente_id" class="form-select" required><option value="">Seleccionar...</option><?php foreach($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellidos'].' - '.$c['email']) ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Comision (%)</label><input type="number" name="comision" class="form-control" value="10" min="0" max="100" step="0.5"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Crear</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
