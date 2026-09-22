<?php
$pageTitle = 'Visitas';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT v.*, p.referencia, p.titulo as propiedad, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos, u.nombre as agente_nombre FROM visitas v JOIN propiedades p ON v.propiedad_id = p.id JOIN clientes c ON v.cliente_id = c.id LEFT JOIN usuarios u ON v.agente_id = u.id ORDER BY v.fecha DESC");
    exportarVisitas($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

$filtroEstado = get('estado');
$filtroFecha = get('fecha');
$filtroBusqueda = get('q');
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) { $where[] = 'v.agente_id = ?'; $params[] = currentUserId(); }
if ($filtroEstado) { $where[] = 'v.estado = ?'; $params[] = $filtroEstado; }
if ($filtroFecha === 'hoy') { $where[] = 'v.fecha = CURDATE()'; }
elseif ($filtroFecha === 'semana') { $where[] = 'v.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'; }
elseif ($filtroFecha === 'mes') { $where[] = 'MONTH(v.fecha) = MONTH(CURDATE()) AND YEAR(v.fecha) = YEAR(CURDATE())'; }
if ($filtroBusqueda) {
    $where[] = '(p.referencia LIKE ? OR p.titulo LIKE ? OR c.nombre LIKE ? OR c.apellidos LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq, $busq]);
}

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM visitas v JOIN propiedades p ON v.propiedad_id = p.id JOIN clientes c ON v.cliente_id = c.id WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmtVis = $db->prepare("SELECT v.*, p.referencia, p.titulo as propiedad, p.direccion as prop_direccion, p.localidad as prop_localidad,
    c.nombre as cliente_nombre, c.apellidos as cliente_apellidos, c.telefono as cliente_telefono,
    u.nombre as agente_nombre
    FROM visitas v
    JOIN propiedades p ON v.propiedad_id = p.id
    JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN usuarios u ON v.agente_id = u.id
    WHERE $whereStr
    ORDER BY v.fecha DESC, v.hora DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtVis->execute($params);
$visitas = $stmtVis->fetchAll();

$baseUrl = 'index.php?estado=' . urlencode($filtroEstado) . '&fecha=' . urlencode($filtroFecha) . '&q=' . urlencode($filtroBusqueda);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= $total ?> visitas</span>
    <div class="d-flex gap-2">
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Visita</a>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Propiedad, cliente..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="programada" <?= $filtroEstado === 'programada' ? 'selected' : '' ?>>Programada</option>
                <option value="realizada" <?= $filtroEstado === 'realizada' ? 'selected' : '' ?>>Realizada</option>
                <option value="cancelada" <?= $filtroEstado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                <option value="no_presentado" <?= $filtroEstado === 'no_presentado' ? 'selected' : '' ?>>No presentado</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Periodo</label>
            <select name="fecha" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="hoy" <?= $filtroFecha === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                <option value="semana" <?= $filtroFecha === 'semana' ? 'selected' : '' ?>>Esta semana</option>
                <option value="mes" <?= $filtroFecha === 'mes' ? 'selected' : '' ?>>Este mes</option>
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
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Propiedad</th>
                    <th>Cliente</th>
                    <th>Agente</th>
                    <th>Estado</th>
                    <th>Valoracion</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visitas as $v): ?>
                <tr>
                    <td><?= formatFecha($v['fecha']) ?></td>
                    <td><?= substr($v['hora'], 0, 5) ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $v['propiedad_id'] ?>">
                            <strong><?= sanitize($v['referencia']) ?></strong>
                        </a><br>
                        <small class="text-muted"><?= sanitize($v['prop_localidad'] ?? '') ?></small>
                    </td>
                    <td>
                        <a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $v['cliente_id'] ?>">
                            <?= sanitize($v['cliente_nombre'] . ' ' . $v['cliente_apellidos']) ?>
                        </a><br>
                        <small class="text-muted"><?= sanitize($v['cliente_telefono'] ?? '') ?></small>
                    </td>
                    <td><?= sanitize($v['agente_nombre'] ?? '-') ?></td>
                    <td><span class="badge-estado badge-<?= $v['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $v['estado'])) ?></span></td>
                    <td><?= $v['valoracion'] ? str_repeat('&#9733;', $v['valoracion']) . str_repeat('&#9734;', 5 - $v['valoracion']) : '-' ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="form.php?id=<?= $v['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar esta visita?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= intval($v['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($visitas)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-calendar-x fs-1 d-block mb-2"></i>No se encontraron visitas</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
