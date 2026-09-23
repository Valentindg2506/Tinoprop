<?php
$pageTitle = 'Finanzas';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT f.*, p.referencia as prop_ref, u.nombre as agente_nombre FROM finanzas f LEFT JOIN propiedades p ON f.propiedad_id = p.id LEFT JOIN usuarios u ON f.agente_id = u.id ORDER BY f.fecha DESC");
    exportarFinanzas($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

$filtroTipo = get('tipo');
$filtroEstado = get('estado');
$filtroMes = get('mes');
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) { $where[] = 'f.agente_id = ?'; $params[] = currentUserId(); }
if ($filtroTipo) { $where[] = 'f.tipo = ?'; $params[] = $filtroTipo; }
if ($filtroEstado) { $where[] = 'f.estado = ?'; $params[] = $filtroEstado; }
if ($filtroMes) {
    $where[] = 'DATE_FORMAT(f.fecha, "%Y-%m") = ?';
    $params[] = $filtroMes;
}
$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

// Totales
$stmtTotales = $db->prepare("SELECT
    COALESCE(SUM(CASE WHEN estado = 'cobrado' THEN importe_total ELSE 0 END), 0) as total_cobrado,
    COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN importe_total ELSE 0 END), 0) as total_pendiente,
    COALESCE(SUM(CASE WHEN tipo LIKE 'comision%' THEN importe_total ELSE 0 END), 0) as total_comisiones,
    COUNT(*) as num_registros
    FROM finanzas f WHERE $whereStr");
$stmtTotales->execute($params);
$totales = $stmtTotales->fetch();

$stmtCount = $db->prepare("SELECT COUNT(*) FROM finanzas f WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmtFin = $db->prepare("SELECT f.*, p.referencia as prop_ref, c.nombre as cliente_nombre, u.nombre as agente_nombre
    FROM finanzas f
    LEFT JOIN propiedades p ON f.propiedad_id = p.id
    LEFT JOIN clientes c ON f.cliente_id = c.id
    LEFT JOIN usuarios u ON f.agente_id = u.id
    WHERE $whereStr ORDER BY f.fecha DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtFin->execute($params);
$registros = $stmtFin->fetchAll();

$tiposFin = ['comision_venta'=>'Comision venta','comision_alquiler'=>'Comision alquiler','honorarios'=>'Honorarios','gasto'=>'Gasto','ingreso_otro'=>'Otro ingreso'];
$baseUrl = 'index.php?tipo=' . urlencode($filtroTipo) . '&estado=' . urlencode($filtroEstado) . '&mes=' . urlencode($filtroMes);
?>

<!-- Resumen financiero -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-success"><?= formatPrecio($totales['total_cobrado']) ?></div>
            <div class="stat-label">Total Cobrado</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-warning"><?= formatPrecio($totales['total_pendiente']) ?></div>
            <div class="stat-label">Pendiente de Cobro</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-primary"><?= formatPrecio($totales['total_comisiones']) ?></div>
            <div class="stat-label">Total Comisiones</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= $total ?> registros</span>
    <div class="d-flex gap-2">
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Registro</a>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Mes</label>
            <input type="month" name="mes" class="form-control form-control-sm" value="<?= sanitize($filtroMes) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($tiposFin as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtroTipo === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="cobrado" <?= $filtroEstado === 'cobrado' ? 'selected' : '' ?>>Cobrado</option>
                <option value="pagado" <?= $filtroEstado === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                <option value="anulado" <?= $filtroEstado === 'anulado' ? 'selected' : '' ?>>Anulado</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th>Propiedad</th><th>Importe</th><th>IVA</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
            <tr>
                <td><?= formatFecha($r['fecha']) ?></td>
                <td><strong><?= sanitize($r['concepto']) ?></strong>
                    <?php if ($r['factura_numero']): ?><br><small class="text-muted">Factura: <?= sanitize($r['factura_numero']) ?></small><?php endif; ?>
                </td>
                <td><?= $tiposFin[$r['tipo']] ?? $r['tipo'] ?></td>
                <td><?= $r['prop_ref'] ? sanitize($r['prop_ref']) : '-' ?></td>
                <td class="text-nowrap"><?= formatPrecio($r['importe']) ?></td>
                <td><?= $r['iva'] ?>%</td>
                <td class="text-nowrap fw-bold"><?= formatPrecio($r['importe_total']) ?></td>
                <td><span class="badge-estado badge-<?= $r['estado'] ?>"><?= ucfirst($r['estado']) ?></span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="form.php?id=<?= $r['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar este registro?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= intval($r['id']) ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($registros)): ?>
            <tr><td colspan="9" class="text-center text-muted py-5"><i class="bi bi-cash-stack fs-1 d-block mb-2"></i>No hay registros financieros</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
