<?php
$pageTitle = 'Informes y Estadisticas';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$isAdm = isAdmin();
$agenteId = intval(currentUserId());

// Periodo seleccionado
$periodo = get('periodo', 'mes');
$anio = intval(get('anio', date('Y')));
$mes = intval(get('mes_num', date('m')));

// ==========================================
// ESTADISTICAS DE PROPIEDADES
// ==========================================
if ($isAdm) {
    $propPorTipo = $db->query("SELECT tipo, COUNT(*) as total FROM propiedades WHERE 1=1 GROUP BY tipo ORDER BY total DESC")->fetchAll();
    $propPorEstado = $db->query("SELECT estado, COUNT(*) as total FROM propiedades WHERE 1=1 GROUP BY estado")->fetchAll();
    $propPorOperacion = $db->query("SELECT operacion, COUNT(*) as total FROM propiedades WHERE 1=1 GROUP BY operacion")->fetchAll();
    $propPorProvincia = $db->query("SELECT provincia, COUNT(*) as total FROM propiedades WHERE provincia IS NOT NULL GROUP BY provincia ORDER BY total DESC LIMIT 10")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT tipo, COUNT(*) as total FROM propiedades WHERE 1=1 AND agente_id = ? GROUP BY tipo ORDER BY total DESC");
    $stmt->execute([$agenteId]);
    $propPorTipo = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT estado, COUNT(*) as total FROM propiedades WHERE 1=1 AND agente_id = ? GROUP BY estado");
    $stmt->execute([$agenteId]);
    $propPorEstado = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT operacion, COUNT(*) as total FROM propiedades WHERE 1=1 AND agente_id = ? GROUP BY operacion");
    $stmt->execute([$agenteId]);
    $propPorOperacion = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT provincia, COUNT(*) as total FROM propiedades WHERE provincia IS NOT NULL AND agente_id = ? GROUP BY provincia ORDER BY total DESC LIMIT 10");
    $stmt->execute([$agenteId]);
    $propPorProvincia = $stmt->fetchAll();
}

// Precio medio por tipo
if ($isAdm) {
    $precioMedio = $db->query("SELECT tipo, ROUND(AVG(precio), 2) as precio_medio, ROUND(AVG(CASE WHEN superficie_construida > 0 THEN precio/superficie_construida END), 2) as precio_m2
        FROM propiedades WHERE estado = 'disponible' GROUP BY tipo ORDER BY precio_medio DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT tipo, ROUND(AVG(precio), 2) as precio_medio, ROUND(AVG(CASE WHEN superficie_construida > 0 THEN precio/superficie_construida END), 2) as precio_m2
        FROM propiedades WHERE estado = 'disponible' AND agente_id = ? GROUP BY tipo ORDER BY precio_medio DESC");
    $stmt->execute([$agenteId]);
    $precioMedio = $stmt->fetchAll();
}

// ==========================================
// ESTADISTICAS DE VENTAS/ALQUILERES
// ==========================================
if ($isAdm) {
    $ventasMes = $db->query("SELECT DATE_FORMAT(updated_at, '%Y-%m') as mes, COUNT(*) as total,
        SUM(CASE WHEN operacion = 'venta' THEN 1 ELSE 0 END) as ventas,
        SUM(CASE WHEN operacion = 'alquiler' THEN 1 ELSE 0 END) as alquileres
        FROM propiedades WHERE estado IN ('vendido','alquilado')
        AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(updated_at, '%Y-%m') ORDER BY mes")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT DATE_FORMAT(updated_at, '%Y-%m') as mes, COUNT(*) as total,
        SUM(CASE WHEN operacion = 'venta' THEN 1 ELSE 0 END) as ventas,
        SUM(CASE WHEN operacion = 'alquiler' THEN 1 ELSE 0 END) as alquileres
        FROM propiedades WHERE estado IN ('vendido','alquilado')
        AND agente_id = ?
        AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(updated_at, '%Y-%m') ORDER BY mes");
    $stmt->execute([$agenteId]);
    $ventasMes = $stmt->fetchAll();
}

// ==========================================
// ESTADISTICAS DE CLIENTES
// ==========================================
if ($isAdm) {
    $clientesPorTipo = $db->query("SELECT
        SUM(FIND_IN_SET('comprador', tipo) > 0) as compradores,
        SUM(FIND_IN_SET('vendedor', tipo) > 0) as vendedores,
        SUM(FIND_IN_SET('inquilino', tipo) > 0) as inquilinos,
        SUM(FIND_IN_SET('propietario', tipo) > 0) as propietarios,
        SUM(FIND_IN_SET('inversor', tipo) > 0) as inversores,
        COUNT(*) as total
        FROM clientes WHERE activo = 1")->fetch();
} else {
    $stmt = $db->prepare("SELECT
        SUM(FIND_IN_SET('comprador', tipo) > 0) as compradores,
        SUM(FIND_IN_SET('vendedor', tipo) > 0) as vendedores,
        SUM(FIND_IN_SET('inquilino', tipo) > 0) as inquilinos,
        SUM(FIND_IN_SET('propietario', tipo) > 0) as propietarios,
        SUM(FIND_IN_SET('inversor', tipo) > 0) as inversores,
        COUNT(*) as total
        FROM clientes WHERE activo = 1 AND agente_id = ?");
    $stmt->execute([$agenteId]);
    $clientesPorTipo = $stmt->fetch();
}

if ($isAdm) {
    $clientesPorOrigen = $db->query("SELECT origen, COUNT(*) as total FROM clientes WHERE activo = 1 GROUP BY origen ORDER BY total DESC")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT origen, COUNT(*) as total FROM clientes WHERE activo = 1 AND agente_id = ? GROUP BY origen ORDER BY total DESC");
    $stmt->execute([$agenteId]);
    $clientesPorOrigen = $stmt->fetchAll();
}

// ==========================================
// ESTADISTICAS DE VISITAS
// ==========================================
if ($isAdm) {
    $visitasMesData = $db->query("SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as total,
        SUM(estado = 'realizada') as realizadas,
        SUM(estado = 'cancelada') as canceladas,
        SUM(estado = 'no_presentado') as no_presentados
        FROM visitas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha, '%Y-%m') ORDER BY mes")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT DATE_FORMAT(fecha, '%Y-%m') as mes, COUNT(*) as total,
        SUM(estado = 'realizada') as realizadas,
        SUM(estado = 'cancelada') as canceladas,
        SUM(estado = 'no_presentado') as no_presentados
        FROM visitas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND agente_id = ?
        GROUP BY DATE_FORMAT(fecha, '%Y-%m') ORDER BY mes");
    $stmt->execute([$agenteId]);
    $visitasMesData = $stmt->fetchAll();
}

// ==========================================
// ESTADISTICAS FINANCIERAS
// ==========================================
if ($isAdm) {
    $finanzasMes = $db->query("SELECT DATE_FORMAT(fecha, '%Y-%m') as mes,
        SUM(CASE WHEN tipo LIKE 'comision%' AND estado = 'cobrado' THEN importe_total ELSE 0 END) as comisiones_cobradas,
        SUM(CASE WHEN tipo = 'honorarios' AND estado = 'cobrado' THEN importe_total ELSE 0 END) as honorarios_cobrados,
        SUM(CASE WHEN tipo = 'gasto' AND estado = 'pagado' THEN importe_total ELSE 0 END) as gastos
        FROM finanzas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(fecha, '%Y-%m') ORDER BY mes")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT DATE_FORMAT(fecha, '%Y-%m') as mes,
        SUM(CASE WHEN tipo LIKE 'comision%' AND estado = 'cobrado' THEN importe_total ELSE 0 END) as comisiones_cobradas,
        SUM(CASE WHEN tipo = 'honorarios' AND estado = 'cobrado' THEN importe_total ELSE 0 END) as honorarios_cobrados,
        SUM(CASE WHEN tipo = 'gasto' AND estado = 'pagado' THEN importe_total ELSE 0 END) as gastos
        FROM finanzas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND agente_id = ?
        GROUP BY DATE_FORMAT(fecha, '%Y-%m') ORDER BY mes");
    $stmt->execute([$agenteId]);
    $finanzasMes = $stmt->fetchAll();
}

// Ranking de agentes (solo admin)
$rankingAgentes = [];
if ($isAdm) {
    $rankingAgentes = $db->query("SELECT u.id, CONCAT(u.nombre, ' ', u.apellidos) as nombre,
        (SELECT COUNT(*) FROM propiedades p WHERE p.agente_id = u.id) as propiedades,
        (SELECT COUNT(*) FROM propiedades p WHERE p.agente_id = u.id AND p.estado IN ('vendido','alquilado')) as cerradas,
        (SELECT COUNT(*) FROM visitas v WHERE v.agente_id = u.id AND v.estado = 'realizada') as visitas,
        (SELECT COALESCE(SUM(f.importe_total), 0) FROM finanzas f WHERE f.agente_id = u.id AND f.estado = 'cobrado' AND f.tipo LIKE 'comision%') as comisiones
        FROM usuarios u WHERE u.activo = 1 ORDER BY comisiones DESC")->fetchAll();
}

$tipos = getTiposPropiedad();
?>

<!-- Propiedades por estado y tipo -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-pie-chart"></i> Propiedades por Estado</div>
            <div class="card-body">
                <?php foreach ($propPorEstado as $e): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><span class="badge-estado badge-<?= $e['estado'] ?>"><?= ucfirst($e['estado']) ?></span></span>
                    <strong><?= $e['total'] ?></strong>
                </div>
                <?php endforeach; ?>
                <?php if (empty($propPorEstado)): ?>
                <p class="text-muted text-center">Sin datos</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart"></i> Propiedades por Tipo</div>
            <div class="card-body">
                <?php
                $maxTipo = max(array_column($propPorTipo, 'total') ?: [1]);
                foreach ($propPorTipo as $t): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small><?= $tipos[$t['tipo']] ?? $t['tipo'] ?></small>
                        <small class="fw-bold"><?= $t['total'] ?></small>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" style="width: <?= round($t['total'] / $maxTipo * 100) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Precio medio y Top provincias -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-currency-euro"></i> Precio Medio por Tipo</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Tipo</th><th>Precio Medio</th><th>Precio/m2</th></tr></thead>
                    <tbody>
                    <?php foreach ($precioMedio as $pm): ?>
                    <tr>
                        <td><?= $tipos[$pm['tipo']] ?? $pm['tipo'] ?></td>
                        <td><?= formatPrecio($pm['precio_medio']) ?></td>
                        <td><?= $pm['precio_m2'] ? formatPrecio($pm['precio_m2']) . '/m&sup2;' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-geo-alt"></i> Top 10 Provincias</div>
            <div class="card-body">
                <?php
                $maxProv = max(array_column($propPorProvincia, 'total') ?: [1]);
                foreach ($propPorProvincia as $pv): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small><?= sanitize($pv['provincia']) ?></small>
                        <small class="fw-bold"><?= $pv['total'] ?></small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-info" style="width: <?= round($pv['total'] / $maxProv * 100) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Clientes -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-people"></i> Clientes por Tipo</div>
            <div class="card-body">
                <div class="row text-center">
                    <?php
                    $tiposCli = ['compradores'=>['Compradores','primary'], 'vendedores'=>['Vendedores','success'], 'inquilinos'=>['Inquilinos','info'], 'propietarios'=>['Propietarios','warning'], 'inversores'=>['Inversores','danger']];
                    foreach ($tiposCli as $k => [$label, $color]):
                    ?>
                    <div class="col">
                        <h4 class="text-<?= $color ?>"><?= $clientesPorTipo[$k] ?? 0 ?></h4>
                        <small class="text-muted"><?= $label ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <hr>
                <p class="text-center mb-0"><strong>Total clientes activos: <?= $clientesPorTipo['total'] ?? 0 ?></strong></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-funnel"></i> Clientes por Origen</div>
            <div class="card-body">
                <?php foreach ($clientesPorOrigen as $co): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span><?= ucfirst($co['origen']) ?></span>
                    <strong><?= $co['total'] ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Visitas mensuales -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-event"></i> Visitas por Mes (Ultimos 6 meses)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Mes</th><th>Total</th><th>Realizadas</th><th>Canceladas</th><th>No presentados</th><th>Tasa Exito</th></tr></thead>
                    <tbody>
                    <?php foreach ($visitasMesData as $vm): ?>
                    <tr>
                        <td><?= $vm['mes'] ?></td>
                        <td><strong><?= $vm['total'] ?></strong></td>
                        <td class="text-success"><?= $vm['realizadas'] ?></td>
                        <td class="text-danger"><?= $vm['canceladas'] ?></td>
                        <td class="text-warning"><?= $vm['no_presentados'] ?></td>
                        <td><?= $vm['total'] > 0 ? round(($vm['realizadas'] / $vm['total']) * 100) . '%' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($visitasMesData)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Sin datos de visitas</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Finanzas mensuales -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-cash-stack"></i> Resumen Financiero Mensual (12 meses)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Mes</th><th>Comisiones</th><th>Honorarios</th><th>Gastos</th><th>Balance</th></tr></thead>
                    <tbody>
                    <?php foreach ($finanzasMes as $fm): ?>
                    <?php $balance = $fm['comisiones_cobradas'] + $fm['honorarios_cobrados'] - $fm['gastos']; ?>
                    <tr>
                        <td><?= $fm['mes'] ?></td>
                        <td class="text-success"><?= formatPrecio($fm['comisiones_cobradas']) ?></td>
                        <td class="text-primary"><?= formatPrecio($fm['honorarios_cobrados']) ?></td>
                        <td class="text-danger"><?= formatPrecio($fm['gastos']) ?></td>
                        <td class="fw-bold <?= $balance >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatPrecio($balance) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($finanzasMes)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin datos financieros</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Ranking agentes (solo admin) -->
<?php if ($isAdm && !empty($rankingAgentes)): ?>
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-trophy"></i> Rendimiento de Agentes</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Agente</th><th>Propiedades</th><th>Cerradas</th><th>Visitas</th><th>Comisiones</th></tr></thead>
                    <tbody>
                    <?php foreach ($rankingAgentes as $ag): ?>
                    <tr>
                        <td><strong><?= sanitize($ag['nombre']) ?></strong></td>
                        <td><?= $ag['propiedades'] ?></td>
                        <td><?= $ag['cerradas'] ?></td>
                        <td><?= $ag['visitas'] ?></td>
                        <td class="fw-bold text-success"><?= formatPrecio($ag['comisiones']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
