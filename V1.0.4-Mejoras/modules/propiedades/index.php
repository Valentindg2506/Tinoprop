<?php
$pageTitle = 'Propiedades';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT p.*, u.nombre as agente_nombre FROM propiedades p LEFT JOIN usuarios u ON p.agente_id = u.id ORDER BY p.created_at DESC");
    exportarPropiedades($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

// Filtros
$filtroTipo = get('tipo');
$filtroOperacion = get('operacion');
$filtroEstado = get('estado', 'disponible');
$filtroProvincia = get('provincia');
$filtroBusqueda = get('q');
$page = max(1, intval(get('page', 1)));

// Construir query
$where = [];
$params = [];

if (!$isAdm) {
    $where[] = 'p.agente_id = ?';
    $params[] = currentUserId();
}
if ($filtroTipo) { $where[] = 'p.tipo = ?'; $params[] = $filtroTipo; }
if ($filtroOperacion) { $where[] = 'p.operacion = ?'; $params[] = $filtroOperacion; }
if ($filtroEstado) { $where[] = 'p.estado = ?'; $params[] = $filtroEstado; }
if ($filtroProvincia) { $where[] = 'p.provincia = ?'; $params[] = $filtroProvincia; }
if ($filtroBusqueda) {
    $where[] = '(p.referencia LIKE ? OR p.titulo LIKE ? OR p.localidad LIKE ? OR p.direccion LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq, $busq]);
}

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

// Contar total
$stmtCount = $db->prepare("SELECT COUNT(*) FROM propiedades p WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

// Obtener propiedades
$stmtProp = $db->prepare("SELECT p.*, u.nombre as agente_nombre,
    (SELECT archivo FROM propiedad_fotos WHERE propiedad_id = p.id AND es_principal = 1 LIMIT 1) as foto_principal
    FROM propiedades p
    LEFT JOIN usuarios u ON p.agente_id = u.id
    WHERE $whereStr
    ORDER BY p.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtProp->execute($params);
$propiedades = $stmtProp->fetchAll();

$tipos = getTiposPropiedad();
$provincias = getProvincias();
$baseUrl = 'index.php?tipo=' . urlencode($filtroTipo) . '&operacion=' . urlencode($filtroOperacion) . '&estado=' . urlencode($filtroEstado) . '&provincia=' . urlencode($filtroProvincia) . '&q=' . urlencode($filtroBusqueda);
?>

<!-- Barra de acciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted"><?= $total ?> propiedades encontradas</span>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Propiedad</a>
    </div>
</div>

<!-- Filtros -->
<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Ref, titulo, ciudad..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($tipos as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtroTipo === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Operacion</label>
            <select name="operacion" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="venta" <?= $filtroOperacion === 'venta' ? 'selected' : '' ?>>Venta</option>
                <option value="alquiler" <?= $filtroOperacion === 'alquiler' ? 'selected' : '' ?>>Alquiler</option>
                <option value="alquiler_opcion_compra" <?= $filtroOperacion === 'alquiler_opcion_compra' ? 'selected' : '' ?>>Alquiler con opcion</option>
                <option value="traspaso" <?= $filtroOperacion === 'traspaso' ? 'selected' : '' ?>>Traspaso</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="disponible" <?= $filtroEstado === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                <option value="reservado" <?= $filtroEstado === 'reservado' ? 'selected' : '' ?>>Reservado</option>
                <option value="vendido" <?= $filtroEstado === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                <option value="alquilado" <?= $filtroEstado === 'alquilado' ? 'selected' : '' ?>>Alquilado</option>
                <option value="retirado" <?= $filtroEstado === 'retirado' ? 'selected' : '' ?>>Retirado</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Provincia</label>
            <select name="provincia" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($provincias as $prov): ?>
                <option value="<?= $prov ?>" <?= $filtroProvincia === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </form>
</div>

<!-- Tabla de propiedades -->
<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover" id="tablaPropiedades">
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Referencia</th>
                    <th>Titulo</th>
                    <th>Tipo</th>
                    <th>Operacion</th>
                    <th>Precio</th>
                    <th>Ubicacion</th>
                    <th>Sup.</th>
                    <th>Hab.</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($propiedades as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['foto_principal']): ?>
                        <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($p['foto_principal']) ?>" class="property-thumb" alt="">
                        <?php else: ?>
                        <div class="property-thumb bg-light d-flex align-items-center justify-content-center">
                            <i class="bi bi-image text-muted"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= sanitize($p['referencia']) ?></strong></td>
                    <td><a href="ver.php?id=<?= $p['id'] ?>"><?= sanitize(mb_substr($p['titulo'], 0, 35)) ?></a></td>
                    <td><?= $tipos[$p['tipo']] ?? $p['tipo'] ?></td>
                    <td><?= ucfirst(str_replace('_', ' ', $p['operacion'])) ?></td>
                    <td class="text-nowrap fw-bold"><?= formatPrecio($p['precio']) ?></td>
                    <td><?= sanitize($p['localidad']) ?><br><small class="text-muted"><?= sanitize($p['provincia']) ?></small></td>
                    <td><?= $p['superficie_construida'] ? formatSuperficie($p['superficie_construida']) : '-' ?></td>
                    <td><?= $p['habitaciones'] ?? '-' ?></td>
                    <td><span class="badge-estado badge-<?= $p['estado'] ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="ver.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="form.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Seguro que deseas eliminar esta propiedad?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= intval($p['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($propiedades)): ?>
                <tr><td colspan="11" class="text-center text-muted py-5">
                    <i class="bi bi-house fs-1 d-block mb-2"></i>No se encontraron propiedades
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
