<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$funnel = $db->prepare("SELECT * FROM funnels WHERE id=?"); $funnel->execute([$id]); $funnel=$funnel->fetch();
if (!$funnel) { setFlash('danger','No encontrado.'); header('Location: index.php'); exit; }

$pageTitle = 'Estadisticas: ' . $funnel['nombre'];
require_once __DIR__ . '/../../includes/header.php';

$pasos = $db->prepare("SELECT * FROM funnel_pasos WHERE funnel_id=? ORDER BY orden"); $pasos->execute([$id]); $pasos=$pasos->fetchAll();
$sesiones = $db->prepare("SELECT COUNT(*) as total, SUM(completado) as completadas FROM funnel_sesiones WHERE funnel_id=?"); $sesiones->execute([$id]); $sesiones=$sesiones->fetch();
?>

<a href="editor.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver al editor</a>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= $funnel['visitas_total'] ?></h3><small class="text-muted">Visitas</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= $funnel['conversiones_total'] ?></h3><small class="text-muted">Conversiones</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= $funnel['visitas_total']>0?round($funnel['conversiones_total']/$funnel['visitas_total']*100,1):0 ?>%</h3><small class="text-muted">Tasa Conv.</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= intval($sesiones['total']) ?></h3><small class="text-muted">Sesiones</small></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h6 class="mb-0">Embudo de Conversion</h6></div>
    <div class="card-body">
        <canvas id="funnelChart" height="80"></canvas>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h6 class="mb-0">Detalle por Paso</h6></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>#</th><th>Nombre</th><th>Tipo</th><th>Visitas</th><th>Conversiones</th><th>Tasa</th><th>Drop-off</th></tr></thead>
            <tbody>
            <?php $prevVisitas = null; foreach ($pasos as $p):
                $tasa = $p['visitas']>0?round($p['conversiones']/$p['visitas']*100,1):0;
                $dropoff = $prevVisitas!==null && $prevVisitas>0 ? round((1-$p['visitas']/$prevVisitas)*100,1) : 0;
            ?>
            <tr>
                <td><?= $p['orden'] ?></td>
                <td class="fw-semibold"><?= sanitize($p['nombre']) ?></td>
                <td><span class="badge bg-light text-dark"><?= ucfirst($p['tipo']) ?></span></td>
                <td><?= $p['visitas'] ?></td>
                <td><?= $p['conversiones'] ?></td>
                <td><?= $tasa ?>%</td>
                <td><?= $prevVisitas!==null ? $dropoff.'%' : '-' ?></td>
            </tr>
            <?php $prevVisitas = $p['visitas']; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode(array_map(fn($p)=>$p['nombre'], $pasos)) ?>;
const visitas = <?= json_encode(array_map(fn($p)=>$p['visitas'], $pasos)) ?>;
const conv = <?= json_encode(array_map(fn($p)=>$p['conversiones'], $pasos)) ?>;
new Chart(document.getElementById('funnelChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {label:'Visitas', data:visitas, backgroundColor:'rgba(59,130,246,0.6)'},
            {label:'Conversiones', data:conv, backgroundColor:'rgba(16,185,129,0.6)'}
        ]
    },
    options: {responsive:true, scales:{y:{beginAtZero:true}}}
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
