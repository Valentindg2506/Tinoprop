<?php
$pageTitle = 'Reportes Avanzados';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

function normalizeReportDate($value, $fallback) {
    $value = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $fallback;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return $fallback;
    }
    return $value;
}

$allowedRanges = ['7', '30', '90', '365', 'custom'];
$rango = get('rango', '30');
if (!in_array($rango, $allowedRanges, true)) {
    $rango = '30';
}

if ($rango === 'custom') {
    $defaultDesde = date('Y-m-d', strtotime('-30 days'));
    $defaultHasta = date('Y-m-d');
    $desde = normalizeReportDate(get('desde', $defaultDesde), $defaultDesde);
    $hasta = normalizeReportDate(get('hasta', $defaultHasta), $defaultHasta);
    if (strtotime($desde) > strtotime($hasta)) {
        $tmp = $desde;
        $desde = $hasta;
        $hasta = $tmp;
    }
} else {
    $days = intval($rango);
    if (!in_array((string)$days, ['7', '30', '90', '365'], true)) {
        $days = 30;
        $rango = '30';
    }
    $desde = date('Y-m-d', strtotime("-{$days} days"));
    $hasta = date('Y-m-d');
}

$desdeTs = strtotime($desde);
$hastaTs = strtotime($hasta);
$prevDesde = date('Y-m-d', $desdeTs - ($hastaTs - $desdeTs));
$prevHasta = date('Y-m-d', $desdeTs - 1);

// KPIs
function getKPI($db, $table, $dateCol, $desde, $hasta, $cond = '1=1') {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $dateCol)) {
        return 0;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE $dateCol BETWEEN ? AND ? AND $cond");
    $stmt->execute([$desde, $hasta]);
    return $stmt->fetchColumn();
}

$kpis = [
    'clientes' => [getKPI($db,'clientes','created_at',$desde,$hasta), getKPI($db,'clientes','created_at',$prevDesde,$prevHasta)],
    'propiedades' => [getKPI($db,'propiedades','created_at',$desde,$hasta), getKPI($db,'propiedades','created_at',$prevDesde,$prevHasta)],
    'visitas' => [getKPI($db,'visitas','fecha',$desde,$hasta,"estado='realizada'"), getKPI($db,'visitas','fecha',$prevDesde,$prevHasta,"estado='realizada'")],
    'operaciones' => [getKPI($db,'propiedades','updated_at',$desde,$hasta,"estado IN('vendido','alquilado')"), getKPI($db,'propiedades','updated_at',$prevDesde,$prevHasta,"estado IN('vendido','alquilado')")],
];

$stmtIngresos = $db->prepare("SELECT COALESCE(SUM(importe_total),0) FROM finanzas WHERE estado='cobrado' AND fecha BETWEEN ? AND ?");
$stmtIngresos->execute([$desde, $hasta]);
$ingresos = $stmtIngresos->fetchColumn();
$stmtIngresos->execute([$prevDesde, $prevHasta]);
$ingresosPrev = $stmtIngresos->fetchColumn();

// Charts data
$clientesSemana = $db->prepare("SELECT YEARWEEK(created_at,1) as semana, COUNT(*) as total FROM clientes WHERE created_at BETWEEN ? AND ? GROUP BY semana ORDER BY semana");
$clientesSemana->execute([$desde, $hasta]);
$clientesSemana = $clientesSemana->fetchAll();

$stmtVisitasAgente = $db->prepare("SELECT u.nombre, COUNT(*) as total
    FROM visitas v
    JOIN usuarios u ON v.agente_id = u.id
    WHERE v.fecha BETWEEN ? AND ?
    GROUP BY v.agente_id
    ORDER BY total DESC
    LIMIT 10");
$stmtVisitasAgente->execute([$desde, $hasta]);
$visitasAgente = $stmtVisitasAgente->fetchAll();

$propEstado = $db->query("SELECT estado, COUNT(*) as total FROM propiedades GROUP BY estado")->fetchAll();

$topZonas = $db->query("SELECT provincia, COUNT(*) as total FROM propiedades WHERE provincia IS NOT NULL AND provincia != '' GROUP BY provincia ORDER BY total DESC LIMIT 5")->fetchAll();

$stmtIngresosMes = $db->prepare("SELECT DATE_FORMAT(fecha,'%Y-%m') as mes, SUM(importe_total) as total
    FROM finanzas
    WHERE estado='cobrado' AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)
    GROUP BY mes
    ORDER BY mes");
$stmtIngresosMes->execute([$hasta]);
$ingresosMes = $stmtIngresosMes->fetchAll();

$origenLeads = $db->prepare("SELECT origen, COUNT(*) as total FROM clientes WHERE created_at BETWEEN ? AND ? GROUP BY origen ORDER BY total DESC");
$origenLeads->execute([$desde, $hasta]);
$origenLeads = $origenLeads->fetchAll();

// Funnel
$fLeads = getKPI($db,'clientes','created_at',$desde,$hasta);
$fVisitasProg = getKPI($db,'visitas','fecha',$desde,$hasta,"estado='programada'");
$fVisitasReal = getKPI($db,'visitas','fecha',$desde,$hasta,"estado='realizada'");
$fCerradas = getKPI($db,'propiedades','updated_at',$desde,$hasta,"estado IN('vendido','alquilado')");

// Ranking agentes
$stmtRanking = $db->prepare("SELECT u.nombre, u.apellidos,
    (SELECT COUNT(*) FROM clientes c WHERE c.agente_id = u.id AND c.created_at BETWEEN ? AND ?) as clientes,
    (SELECT COUNT(*) FROM visitas v WHERE v.agente_id = u.id AND v.fecha BETWEEN ? AND ?) as visitas,
    (SELECT COALESCE(SUM(f.importe_total),0) FROM finanzas f WHERE f.agente_id = u.id AND f.estado='cobrado' AND f.fecha BETWEEN ? AND ?) as ingresos
    FROM usuarios u
    WHERE u.activo = 1
    ORDER BY ingresos DESC");
$stmtRanking->execute([$desde, $hasta, $desde, $hasta, $desde, $hasta]);
$ranking = $stmtRanking->fetchAll();

function cambio($actual, $prev) {
    if ($prev == 0) return $actual > 0 ? 100 : 0;
    return round((($actual - $prev) / $prev) * 100);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <?php foreach (['7'=>'7d','30'=>'30d','90'=>'90d','365'=>'1a'] as $v=>$l): ?>
        <a href="?rango=<?= $v ?>" class="btn btn-sm <?= $rango==$v?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
        <?php endforeach; ?>
        <form method="GET" class="d-flex gap-1">
            <input type="hidden" name="rango" value="custom">
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>" style="width:140px">
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>" style="width:140px">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-filter"></i></button>
        </form>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Informes basicos</a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpiLabels = ['clientes'=>['Nuevos Clientes','people','primary'], 'propiedades'=>['Nuevas Propiedades','house-door','success'], 'visitas'=>['Visitas Realizadas','calendar-check','info'], 'operaciones'=>['Operaciones Cerradas','check-circle','warning']];
    foreach ($kpiLabels as $k => [$label, $icon, $color]):
        $actual = $kpis[$k][0]; $prev = $kpis[$k][1]; $change = cambio($actual, $prev);
    ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-3 fw-bold"><?= $actual ?></div>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                    <div class="text-<?= $color ?> opacity-25"><i class="bi bi-<?= $icon ?> fs-2"></i></div>
                </div>
                <div class="mt-2 small <?= $change >= 0 ? 'text-success' : 'text-danger' ?>">
                    <i class="bi bi-arrow-<?= $change >= 0 ? 'up' : 'down' ?>"></i> <?= abs($change) ?>% vs periodo anterior
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Ingresos KPI -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-3 fw-bold text-success"><?= formatPrecio($ingresos) ?></div>
                    <small class="text-muted">Ingresos cobrados en el periodo</small>
                </div>
                <div class="small <?= cambio($ingresos,$ingresosPrev) >= 0 ? 'text-success' : 'text-danger' ?>">
                    <i class="bi bi-arrow-<?= cambio($ingresos,$ingresosPrev) >= 0 ? 'up' : 'down' ?>"></i> <?= abs(cambio($ingresos,$ingresosPrev)) ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Clientes nuevos por semana</div><div class="card-body"><canvas id="cClientesSemana" height="200"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Visitas por agente</div><div class="card-body"><canvas id="cVisitasAgente" height="200"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Propiedades por estado</div><div class="card-body"><canvas id="cPropEstado" height="200"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Top zonas</div><div class="card-body"><canvas id="cTopZonas" height="200"></canvas></div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Origen de leads</div><div class="card-body"><canvas id="cOrigenLeads" height="200"></canvas></div></div></div>
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-header bg-white">Ingresos por mes (12 meses)</div><div class="card-body"><canvas id="cIngresosMes" height="150"></canvas></div></div></div>
</div>

<!-- Funnel -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><i class="bi bi-funnel"></i> Embudo Detallado</div>
    <div class="card-body">
        <div class="row text-center align-items-center">
            <?php
            $funnel = [['Leads',$fLeads,'success'],['Visitas Prog.',$fVisitasProg,'info'],['Visitas Real.',$fVisitasReal,'primary'],['Cerradas',$fCerradas,'warning']];
            foreach ($funnel as $i => [$lab,$val,$col]):
            ?>
            <?php if ($i > 0): ?>
            <div class="col-md-1 d-none d-md-block">
                <i class="bi bi-chevron-right fs-3 text-muted"></i>
                <div class="small text-muted"><?= $funnel[$i-1][1] > 0 ? round(($val/$funnel[$i-1][1])*100) : 0 ?>%</div>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <div class="p-3 rounded" style="background:var(--bs-<?= $col ?>-bg-subtle,#f0f0f0)">
                    <div class="fs-2 fw-bold text-<?= $col ?>"><?= $val ?></div>
                    <div class="text-muted small"><?= $lab ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Ranking -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><i class="bi bi-trophy"></i> Ranking de Agentes</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>#</th><th>Agente</th><th>Clientes</th><th>Visitas</th><th>Ingresos</th></tr></thead>
            <tbody>
                <?php foreach ($ranking as $i => $r): ?>
                <tr><td><?= $i+1 ?></td><td><strong><?= sanitize($r['nombre'].' '.$r['apellidos']) ?></strong></td><td><?= $r['clientes'] ?></td><td><?= $r['visitas'] ?></td><td class="fw-bold text-success"><?= formatPrecio($r['ingresos']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const colors = ['#10b981','#3b82f6','#f59e0b','#8b5cf6','#ef4444','#6b7280','#ec4899','#14b8a6'];

new Chart(document.getElementById('cClientesSemana'), {type:'line',data:{labels:<?= json_encode(array_column($clientesSemana,'semana')) ?>,datasets:[{label:'Clientes',data:<?= json_encode(array_map('intval',array_column($clientesSemana,'total'))) ?>,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,0.1)',fill:true,tension:0.3}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});

new Chart(document.getElementById('cVisitasAgente'), {type:'bar',data:{labels:<?= json_encode(array_column($visitasAgente,'nombre')) ?>,datasets:[{data:<?= json_encode(array_map('intval',array_column($visitasAgente,'total'))) ?>,backgroundColor:'#3b82f6',borderRadius:6}]},options:{responsive:true,indexAxis:'y',plugins:{legend:{display:false}}}});

new Chart(document.getElementById('cPropEstado'), {type:'doughnut',data:{labels:<?= json_encode(array_column($propEstado,'estado')) ?>,datasets:[{data:<?= json_encode(array_map('intval',array_column($propEstado,'total'))) ?>,backgroundColor:colors}]},options:{responsive:true}});

new Chart(document.getElementById('cTopZonas'), {type:'bar',data:{labels:<?= json_encode(array_column($topZonas,'provincia')) ?>,datasets:[{data:<?= json_encode(array_map('intval',array_column($topZonas,'total'))) ?>,backgroundColor:'#f59e0b',borderRadius:6}]},options:{responsive:true,indexAxis:'y',plugins:{legend:{display:false}}}});

new Chart(document.getElementById('cOrigenLeads'), {type:'pie',data:{labels:<?= json_encode(array_column($origenLeads,'origen')) ?>,datasets:[{data:<?= json_encode(array_map('intval',array_column($origenLeads,'total'))) ?>,backgroundColor:colors}]},options:{responsive:true}});

new Chart(document.getElementById('cIngresosMes'), {type:'bar',data:{labels:<?= json_encode(array_column($ingresosMes,'mes')) ?>,datasets:[{label:'Ingresos',data:<?= json_encode(array_map('floatval',array_column($ingresosMes,'total'))) ?>,backgroundColor:'rgba(16,185,129,0.7)',borderColor:'#10b981',borderWidth:1,borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
