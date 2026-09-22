<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/encryption.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'guardar_cuenta') {
        $accessToken = trim(post('access_token'));
        $encryptedToken = $accessToken !== '' ? encryptField($accessToken) : '';
        $db->prepare("INSERT INTO ads_cuentas (plataforma, nombre, account_id, access_token) VALUES (?,?,?,?)")
            ->execute([post('plataforma'), trim(post('nombre')), trim(post('account_id')), $encryptedToken]);
        setFlash('success','Cuenta agregada.');
        header('Location: index.php'); exit;
    }
    if ($a === 'agregar_campana') {
        $cuentaId = intval(post('cuenta_id')) ?: null;
        $db->prepare("INSERT INTO ads_campanas (cuenta_id, nombre, plataforma, presupuesto, gasto, impresiones, clicks, conversiones, fecha_inicio, fecha_fin) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$cuentaId, trim(post('nombre')), post('plataforma'), floatval(post('presupuesto')), floatval(post('gasto')),
                intval(post('impresiones')), intval(post('clicks')), intval(post('conversiones')), post('fecha_inicio')?:null, post('fecha_fin')?:null]);
        setFlash('success','Campana agregada.');
        header('Location: index.php'); exit;
    }
    if ($a === 'eliminar') { $db->prepare("DELETE FROM ads_campanas WHERE id=?")->execute([intval(post('aid'))]); setFlash('success','Eliminada.'); header('Location: index.php'); exit; }
}

$pageTitle = 'Reporting de Ads';
require_once __DIR__ . '/../../includes/header.php';

$cuentas = $db->query("SELECT * FROM ads_cuentas ORDER BY plataforma")->fetchAll();
$campanas = $db->query("SELECT ac.*, a.nombre as cuenta_nombre FROM ads_campanas ac LEFT JOIN ads_cuentas a ON ac.cuenta_id=a.id ORDER BY ac.created_at DESC")->fetchAll();

$totalGasto = array_sum(array_column($campanas, 'gasto'));
$totalClicks = array_sum(array_column($campanas, 'clicks'));
$totalConv = array_sum(array_column($campanas, 'conversiones'));
$totalImpresiones = array_sum(array_column($campanas, 'impresiones'));
$cpl = $totalConv > 0 ? $totalGasto / $totalConv : 0;
$ctr = $totalImpresiones > 0 ? round($totalClicks / $totalImpresiones * 100, 2) : 0;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCuenta"><i class="bi bi-gear"></i> Cuentas</button>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCampana"><i class="bi bi-plus-lg"></i> Agregar Campana</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card border-0 shadow-sm text-center p-3"><h5 class="fw-bold mb-0"><?= number_format($totalGasto,0,',','.') ?>&euro;</h5><small class="text-muted">Gasto Total</small></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm text-center p-3"><h5 class="fw-bold mb-0"><?= number_format($totalImpresiones,0,',','.') ?></h5><small class="text-muted">Impresiones</small></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm text-center p-3"><h5 class="fw-bold mb-0"><?= number_format($totalClicks,0,',','.') ?></h5><small class="text-muted">Clicks</small></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm text-center p-3"><h5 class="fw-bold mb-0"><?= $ctr ?>%</h5><small class="text-muted">CTR</small></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm text-center p-3"><h5 class="fw-bold mb-0"><?= $totalConv ?></h5><small class="text-muted">Conversiones</small></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm text-center p-3"><h5 class="fw-bold mb-0"><?= number_format($cpl,2,',','.') ?>&euro;</h5><small class="text-muted">CPL</small></div></div>
</div>

<?php if (!empty($campanas)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-bar-chart"></i> Rendimiento por Campana</h6></div>
    <div class="card-body"><canvas id="adsChart" height="80"></canvas></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Campana</th><th>Plataforma</th><th>Presupuesto</th><th>Gasto</th><th>Impresiones</th><th>Clicks</th><th>CTR</th><th>Conv.</th><th>CPL</th><th class="text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($campanas as $c):
                $cctr = $c['impresiones']>0 ? round($c['clicks']/$c['impresiones']*100,2) : 0;
                $ccpl = $c['conversiones']>0 ? round($c['gasto']/$c['conversiones'],2) : 0;
            ?>
            <tr>
                <td class="fw-semibold"><?= sanitize($c['nombre']) ?></td>
                <td><i class="bi bi-<?= $c['plataforma']==='facebook'?'facebook text-primary':'google text-warning' ?>"></i> <?= ucfirst($c['plataforma']) ?></td>
                <td><?= number_format($c['presupuesto'],0,',','.') ?>&euro;</td>
                <td><?= number_format($c['gasto'],0,',','.') ?>&euro;</td>
                <td><?= number_format($c['impresiones'],0,',','.') ?></td>
                <td><?= number_format($c['clicks'],0,',','.') ?></td>
                <td><?= $cctr ?>%</td>
                <td><?= $c['conversiones'] ?></td>
                <td><?= number_format($ccpl,2,',','.') ?>&euro;</td>
                <td class="text-end"><form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="aid" value="<?= $c['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('adsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($c)=>$c['nombre'], $campanas)) ?>,
        datasets: [
            {label:'Gasto (€)', data:<?= json_encode(array_map(fn($c)=>$c['gasto'], $campanas)) ?>, backgroundColor:'rgba(239,68,68,0.6)'},
            {label:'Clicks', data:<?= json_encode(array_map(fn($c)=>$c['clicks'], $campanas)) ?>, backgroundColor:'rgba(59,130,246,0.6)'},
            {label:'Conversiones', data:<?= json_encode(array_map(fn($c)=>$c['conversiones'], $campanas)) ?>, backgroundColor:'rgba(16,185,129,0.6)'}
        ]
    },
    options:{responsive:true,scales:{y:{beginAtZero:true}}}
});
</script>
<?php else: ?>
<div class="text-center text-muted py-5"><i class="bi bi-megaphone fs-1 d-block mb-3"></i><h5>No hay campanas de ads</h5><p>Agrega campanas de Facebook/Google Ads para ver el rendimiento.</p></div>
<?php endif; ?>

<!-- Modal cuenta -->
<div class="modal fade" id="modalCuenta" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="guardar_cuenta">
    <div class="modal-header"><h5 class="modal-title">Cuenta de Ads</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><select name="plataforma" class="form-select"><option value="facebook">Facebook Ads</option><option value="google">Google Ads</option></select></div>
        <div class="mb-3"><input type="text" name="nombre" class="form-control" placeholder="Nombre" required></div>
        <div class="mb-3"><input type="text" name="account_id" class="form-control" placeholder="Account ID"></div>
        <div class="mb-3"><input type="text" name="access_token" class="form-control" placeholder="Access Token"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
</form></div></div></div>

<!-- Modal campana -->
<div class="modal fade" id="modalCampana" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="agregar_campana">
    <div class="modal-header"><h5 class="modal-title">Agregar Campana</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><input type="text" name="nombre" class="form-control" placeholder="Nombre campana" required></div>
        <div class="row g-2">
            <div class="col-md-6"><select name="plataforma" class="form-select"><option value="facebook">Facebook</option><option value="google">Google</option></select></div>
            <div class="col-md-6"><select name="cuenta_id" class="form-select"><option value="">Cuenta...</option><?php foreach($cuentas as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nombre']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-4"><input type="number" name="presupuesto" class="form-control" placeholder="Presupuesto €" step="0.01"></div>
            <div class="col-md-4"><input type="number" name="gasto" class="form-control" placeholder="Gasto €" step="0.01"></div>
            <div class="col-md-4"><input type="number" name="impresiones" class="form-control" placeholder="Impresiones"></div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-6"><input type="number" name="clicks" class="form-control" placeholder="Clicks"></div>
            <div class="col-md-6"><input type="number" name="conversiones" class="form-control" placeholder="Conversiones"></div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-6"><label class="form-label small">Inicio</label><input type="date" name="fecha_inicio" class="form-control"></div>
            <div class="col-md-6"><label class="form-label small">Fin</label><input type="date" name="fecha_fin" class="form-control"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Agregar</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
