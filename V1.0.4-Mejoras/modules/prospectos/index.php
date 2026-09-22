<?php
$pageTitle = 'Prospectos';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT p.*, u.nombre as agente_nombre FROM prospectos p LEFT JOIN usuarios u ON p.agente_id = u.id ORDER BY p.created_at DESC");
    exportarProspectos($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

// Filtros
$filtroEtapa = get('etapa');
$filtroProvincia = get('provincia');
$filtroBusqueda = get('q');
$filtroActivo = get('activo', '1');
$filtroContacto = get('contacto');
$filtroPrioridad = get('prioridad');
$viewMode = get('view', 'tabla');
$viewMode = in_array($viewMode, ['tabla', 'kanban'], true) ? $viewMode : 'tabla';
$filtroPerPage = intval(get('per_page', 50));
$perPageOptions = [10, 20, 50, 100];
if (!in_array($filtroPerPage, $perPageOptions, true)) {
    $filtroPerPage = 20;
}
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) {
    $where[] = 'p.agente_id = ?';
    $params[] = currentUserId();
}
if ($filtroEtapa) {
    // Compatibilidad con etapas antiguas para no dejar fuera registros existentes.
    if ($filtroEtapa === 'en_seguimiento') {
        $where[] = 'p.etapa IN (?,?,?)';
        $params[] = 'en_seguimiento';
        $params[] = 'seguimiento';
        $params[] = 'en_negociacion';
    } else {
        $where[] = 'p.etapa = ?';
        $params[] = $filtroEtapa;
    }
}
if ($filtroProvincia) { $where[] = 'p.provincia = ?'; $params[] = $filtroProvincia; }
if ($filtroActivo !== '') { $where[] = 'p.activo = ?'; $params[] = $filtroActivo; }
if ($filtroPrioridad) { $where[] = 'p.prioridad = ?'; $params[] = $filtroPrioridad; }
if ($filtroBusqueda) {
    $where[] = '(p.nombre LIKE ? OR p.email LIKE ? OR p.telefono LIKE ? OR p.referencia LIKE ? OR p.direccion LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq, $busq, $busq]);
}

$orderBy = 'p.created_at DESC';
if ($filtroContacto === 'hoy') {
    $where[] = 'p.fecha_proximo_contacto = CURDATE()';
    $orderBy = "COALESCE(p.hora_contacto, '23:59:59') ASC, p.created_at DESC";
} elseif ($filtroContacto === 'vencidos') {
    $where[] = 'p.fecha_proximo_contacto < CURDATE()';
    $orderBy = "p.fecha_proximo_contacto ASC, COALESCE(p.hora_contacto, '23:59:59') ASC";
} elseif ($filtroContacto === 'semana') {
    $where[] = 'p.fecha_proximo_contacto BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
    $orderBy = "p.fecha_proximo_contacto ASC, COALESCE(p.hora_contacto, '23:59:59') ASC";
} elseif ($filtroContacto === 'sin_fecha') {
    $where[] = 'p.fecha_proximo_contacto IS NULL';
} elseif ($filtroContacto === 'proximos') {
    $where[] = 'p.fecha_proximo_contacto IS NOT NULL';
    $orderBy = "p.fecha_proximo_contacto ASC, COALESCE(p.hora_contacto, '23:59:59') ASC";
}

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM prospectos p WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, $filtroPerPage, $page);

$stmtPros = $db->prepare("SELECT p.*, u.nombre as agente_nombre
    FROM prospectos p
    LEFT JOIN usuarios u ON p.agente_id = u.id
    WHERE $whereStr
    ORDER BY $orderBy
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtPros->execute($params);
$prospectos = $stmtPros->fetchAll();

$prospectosKanban = [];
if ($viewMode === 'kanban') {
    $stmtKanban = $db->prepare("SELECT p.*, u.nombre as agente_nombre
        FROM prospectos p
        LEFT JOIN usuarios u ON p.agente_id = u.id
        WHERE $whereStr
        ORDER BY p.created_at DESC");
    $stmtKanban->execute($params);
    $prospectosKanban = $stmtKanban->fetchAll();
}

$provincias = getProvincias();
$baseUrl = 'index.php?etapa=' . urlencode($filtroEtapa) . '&provincia=' . urlencode($filtroProvincia) . '&q=' . urlencode($filtroBusqueda) . '&activo=' . urlencode($filtroActivo) . '&contacto=' . urlencode($filtroContacto) . '&prioridad=' . urlencode($filtroPrioridad) . '&view=' . urlencode($viewMode) . '&per_page=' . urlencode((string)$filtroPerPage);

$etapas = [
    'nuevo_lead' => ['label' => 'Nuevo Lead', 'color' => '#06b6d4'],
    'contactado' => ['label' => 'Contactado', 'color' => '#64748b'],
    'en_seguimiento' => ['label' => 'En Seguimiento', 'color' => '#3b82f6'],
    'visita_programada' => ['label' => 'Visita Programada', 'color' => '#8b5cf6'],
    'captado' => ['label' => 'Captado', 'color' => '#10b981'],
    'descartado' => ['label' => 'Descartado', 'color' => '#ef4444'],
];

?>

<style>
@media (max-width: 767.98px) {
    .prospectos-head {
        align-items: stretch !important;
        gap: 10px;
    }

    .prospectos-head-count {
        font-size: 0.95rem;
    }

    .prospectos-head-actions {
        width: 100%;
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .prospectos-head-actions .btn {
        width: 100%;
        padding: 0.5rem 0.4rem;
        font-size: 0.82rem;
        white-space: normal;
        line-height: 1.2;
    }

    .prospectos-head-actions .btn i {
        margin-right: 4px;
    }
}

@media (max-width: 575.98px) {
    .prospectos-head-actions {
        grid-template-columns: 1fr;
    }
}

.kanban-board {
    display: grid;
    grid-template-columns: repeat(6, minmax(250px, 1fr));
    gap: 14px;
    overflow-x: auto;
    padding-bottom: 8px;
}

.kanban-column {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    min-height: 420px;
    display: flex;
    flex-direction: column;
}

.kanban-column-head {
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.kanban-column-body {
    padding: 10px;
    min-height: 340px;
}

.kanban-column-body.drag-over {
    background: #eef2ff;
    outline: 2px dashed #6366f1;
    outline-offset: -4px;
    border-radius: 10px;
}

.kanban-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #64748b;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 10px;
    box-shadow: 0 2px 6px rgba(2, 6, 23, 0.04);
    cursor: grab;
    color: #1e293b;
}

.kanban-card:active {
    cursor: grabbing;
}

.kanban-card-title {
    font-size: 0.92rem;
    line-height: 1.3;
    color: #0f172a;
}

.kanban-card-meta {
    display: block;
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.35;
}

.kanban-empty {
    border: 1px dashed #cbd5e1;
    color: #94a3b8;
    border-radius: 8px;
    text-align: center;
    padding: 18px 10px;
    font-size: 0.82rem;
}

[data-bs-theme="dark"] .kanban-column {
    background: #1f2937;
    border-color: #334155;
}

[data-bs-theme="dark"] .kanban-column-head {
    border-bottom-color: #334155;
    background: #111827;
}

[data-bs-theme="dark"] .kanban-column-head .fw-semibold {
    color: #f1f5f9 !important;
}

[data-bs-theme="dark"] .kanban-column-body.drag-over {
    background: #1e1b4b;
}

[data-bs-theme="dark"] .kanban-card {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
}

[data-bs-theme="dark"] .kanban-card-title {
    color: #f8fafc;
}

[data-bs-theme="dark"] .kanban-card-meta {
    color: #94a3b8;
}

[data-bs-theme="dark"] .kanban-empty {
    border-color: #475569;
    color: #94a3b8;
}
</style>

<?php if ($viewMode === 'tabla'): ?>
<!-- Stats rápidas por etapa -->
<div class="row g-3 mb-4">
    <?php
    $stmtStats = $db->query("SELECT
        CASE
            WHEN etapa IN ('seguimiento', 'en_negociacion') THEN 'en_seguimiento'
            ELSE etapa
        END AS etapa_normalizada,
        COUNT(*) as total
        FROM prospectos
        WHERE activo = 1
        GROUP BY etapa_normalizada");
    $statsByEtapa = [];
    while ($row = $stmtStats->fetch()) { $statsByEtapa[$row['etapa_normalizada']] = $row['total']; }
    foreach ($etapas as $eKey => $eData):
        $count = $statsByEtapa[$eKey] ?? 0;
    ?>
    <div class="col-6 col-md-2">
        <a href="index.php?etapa=<?= $eKey ?>&activo=1&view=<?= urlencode($viewMode) ?>" class="stat-card d-block text-decoration-none" style="border-left: 3px solid <?= $eData['color'] ?>;">
            <div class="stat-value" style="font-size: 1.3rem;"><?= $count ?></div>
            <div class="stat-label" style="font-size: 0.7rem;"><?= $eData['label'] ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-column flex-md-row mb-4 prospectos-head">
    <span class="text-muted prospectos-head-count"><?= $total ?> prospectos encontrados</span>
    <div class="d-flex gap-2 flex-wrap justify-content-end prospectos-head-actions">
        <div class="btn-group" role="group" aria-label="Cambiar vista">
            <a href="index.php?etapa=<?= urlencode($filtroEtapa) ?>&provincia=<?= urlencode($filtroProvincia) ?>&q=<?= urlencode($filtroBusqueda) ?>&activo=<?= urlencode($filtroActivo) ?>&contacto=<?= urlencode($filtroContacto) ?>&per_page=<?= urlencode((string)$filtroPerPage) ?>&view=tabla" class="btn btn-outline-secondary <?= $viewMode === 'tabla' ? 'active' : '' ?>">
                <i class="bi bi-table"></i> Tabla
            </a>
            <a href="index.php?etapa=<?= urlencode($filtroEtapa) ?>&provincia=<?= urlencode($filtroProvincia) ?>&q=<?= urlencode($filtroBusqueda) ?>&activo=<?= urlencode($filtroActivo) ?>&contacto=<?= urlencode($filtroContacto) ?>&per_page=<?= urlencode((string)$filtroPerPage) ?>&view=kanban" class="btn btn-outline-secondary <?= $viewMode === 'kanban' ? 'active' : '' ?>">
                <i class="bi bi-kanban"></i> Kanban
            </a>
        </div>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Prospecto</a>
        <?php if ($viewMode === 'tabla'): ?>
        <a href="import.php" class="btn btn-outline-info"><i class="bi bi-upload"></i> Importar CSV</a>
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($viewMode === 'tabla'): ?>
<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="<?= sanitize($viewMode) ?>">
        <input type="hidden" name="etapa" value="<?= sanitize($filtroEtapa) ?>">
        <div class="col-md-2">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre, email, telefono, ref..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="alta" <?= $filtroPrioridad === 'alta' ? 'selected' : '' ?>>🔴 Alta</option>
                <option value="media" <?= $filtroPrioridad === 'media' ? 'selected' : '' ?>>🟡 Media</option>
                <option value="baja" <?= $filtroPrioridad === 'baja' ? 'selected' : '' ?>>🟢 Baja</option>
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
            <label class="form-label">Contacto</label>
            <select name="contacto" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="proximos" <?= $filtroContacto === 'proximos' ? 'selected' : '' ?>>Más próximo</option>
                <option value="hoy" <?= $filtroContacto === 'hoy' ? 'selected' : '' ?>>Hoy</option>
                <option value="vencidos" <?= $filtroContacto === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
                <option value="semana" <?= $filtroContacto === 'semana' ? 'selected' : '' ?>>Próx. 7 días</option>
                <option value="sin_fecha" <?= $filtroContacto === 'sin_fecha' ? 'selected' : '' ?>>Sin fecha</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Activo</label>
            <select name="activo" class="form-select form-select-sm">
                <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>No</option>
                <option value="" <?= $filtroActivo === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label">Por hoja</label>
            <select name="per_page" class="form-select form-select-sm">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $filtroPerPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($viewMode === 'kanban'): ?>
<?php
$kanbanBuckets = [];
foreach (array_keys($etapas) as $etapaKey) {
    $kanbanBuckets[$etapaKey] = [];
}
foreach ($prospectosKanban as $pr) {
    $etapaVista = $pr['etapa'];
    if (in_array($etapaVista, ['seguimiento', 'en_negociacion'], true)) {
        $etapaVista = 'en_seguimiento';
    }
    if (!isset($kanbanBuckets[$etapaVista])) {
        $kanbanBuckets[$etapaVista] = [];
    }
    $kanbanBuckets[$etapaVista][] = $pr;
}
?>
<div class="kanban-board" id="kanbanBoard">
    <?php foreach ($etapas as $etapaKey => $etapaMeta): ?>
    <div class="kanban-column" data-etapa="<?= $etapaKey ?>">
        <div class="kanban-column-head" style="border-top: 3px solid <?= $etapaMeta['color'] ?>;">
            <div class="fw-semibold" style="font-size:.85rem; color:#0f172a;"><?= $etapaMeta['label'] ?></div>
            <span class="badge text-bg-light" id="col-count-<?= $etapaKey ?>"><?= count($kanbanBuckets[$etapaKey] ?? []) ?></span>
        </div>
        <div class="kanban-column-body" data-droppable="1" data-etapa="<?= $etapaKey ?>">
            <?php if (empty($kanbanBuckets[$etapaKey])): ?>
                <div class="kanban-empty">Sin prospectos</div>
            <?php else: ?>
                <?php foreach ($kanbanBuckets[$etapaKey] as $pr): ?>
                    <article class="kanban-card" draggable="true" data-id="<?= intval($pr['id']) ?>" data-etapa="<?= $etapaKey ?>" style="border-left-color: <?= $etapaMeta['color'] ?>;">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <a href="ver.php?id=<?= intval($pr['id']) ?>" class="kanban-card-title text-decoration-none fw-semibold"><?= sanitize($pr['nombre']) ?></a>
                            <?php if (!$pr['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                        </div>
                        <small class="kanban-card-meta d-block mb-1"><i class="bi bi-hash"></i> <?= sanitize($pr['referencia']) ?></small>
                        <?php if (!empty($pr['telefono'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-telephone"></i> <?= sanitize($pr['telefono']) ?></small><?php endif; ?>
                        <?php if (!empty($pr['email'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-envelope"></i> <?= sanitize($pr['email']) ?></small><?php endif; ?>
                        <?php if (!empty($pr['localidad']) || !empty($pr['provincia'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-geo-alt"></i> <?= sanitize(trim(($pr['localidad'] ?? '') . ' ' . ($pr['provincia'] ?? ''))) ?></small><?php endif; ?>
                        <?php if (!empty($pr['tipo_propiedad'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-house"></i> <?= sanitize($pr['tipo_propiedad']) ?></small><?php endif; ?>
                        <?php if (!empty($pr['precio_estimado'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-cash"></i> <?= formatPrecio($pr['precio_estimado']) ?></small><?php endif; ?>
                        <?php if (!empty($pr['fecha_proximo_contacto'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-clock-history"></i> Próx. contacto: <?= formatFecha($pr['fecha_proximo_contacto']) ?><?= !empty($pr['hora_contacto']) ? ' ' . substr($pr['hora_contacto'], 0, 5) : '' ?></small><?php endif; ?>
                        <?php if ($isAdm && !empty($pr['agente_nombre'])): ?><small class="kanban-card-meta d-block"><i class="bi bi-person-badge"></i> <?= sanitize($pr['agente_nombre']) ?></small><?php endif; ?>
                        <div class="d-flex justify-content-end gap-1 mt-2">
                            <?php if (!empty($pr['enlace'])): ?>
                            <a href="<?= sanitize($pr['enlace']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Abrir enlace"><i class="bi bi-box-arrow-up-right"></i></a>
                            <?php endif; ?>
                            <a href="form.php?id=<?= intval($pr['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <a href="ver.php?id=<?= intval($pr['id']) ?>" class="btn btn-sm btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($viewMode === 'tabla'): ?>
<!-- Bulk Actions Bar -->
<div id="bulkBar" class="alert alert-primary d-none align-items-center justify-content-between mb-3">
    <span><strong id="bulkCount">0</strong> prospectos seleccionados</span>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-arrow-repeat"></i> Cambiar Etapa
            </button>
            <ul class="dropdown-menu" id="bulkEtapaMenu">
                <?php foreach ($etapas as $k => $v): ?>
                <li><a class="dropdown-item" href="#" onclick="bulkChangeEtapa('<?= $k ?>');return false;">
                    <span class="badge me-1" style="background:<?= $v['color'] ?>">&nbsp;</span> <?= $v['label'] ?>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <button class="btn btn-sm btn-outline-danger" onclick="bulkDelete()">
            <i class="bi bi-trash"></i> Eliminar
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="bulkToggleActive(0)">
            <i class="bi bi-eye-slash"></i> Desactivar
        </button>
        <button class="btn btn-sm btn-outline-success" onclick="bulkToggleActive(1)">
            <i class="bi bi-eye"></i> Activar
        </button>
    </div>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                    <th>Ref.</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Etapa</th>
                    <th>Prioridad</th>
                    <th>Tipo</th>
                    <th>Enlace</th>
                    <th>Dirección</th>
                    <th>Precio Est.</th>
                    <th>m²</th>
                    <th>Publicación</th>
                    <th>Próx. Contacto</th>
                    <?php if ($isAdm): ?>
                    <th>Agente</th>
                    <?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prospectos as $pr): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input bulk-check" value="<?= $pr['id'] ?>"></td>
                    <td><strong class="text-primary"><?= sanitize($pr['referencia']) ?></strong></td>
                    <td>
                        <a href="ver.php?id=<?= $pr['id'] ?>">
                            <strong><?= sanitize($pr['nombre']) ?></strong>
                        </a>
                        <?php if (!$pr['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pr['telefono']): ?>
                        <a href="tel:<?= sanitize($pr['telefono']) ?>"><?= sanitize($pr['telefono']) ?></a>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $etapaVista = $pr['etapa'];
                        if (in_array($etapaVista, ['seguimiento', 'en_negociacion'], true)) {
                            $etapaVista = 'en_seguimiento';
                        }
                        $etapaInfo = $etapas[$etapaVista] ?? ['label' => $pr['etapa'], 'color' => '#64748b'];
                        $etapaLabelTabla = $etapaInfo['label'] === 'En Seguimiento' ? 'Seguimiento' : $etapaInfo['label'];
                        ?>
                        <span class="badge-estado" style="background: <?= $etapaInfo['color'] ?>20; color: <?= $etapaInfo['color'] ?>; white-space: nowrap;">
                            <?= $etapaLabelTabla ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $prio = $pr['prioridad'] ?? 'media';
                        $prioConfig = [
                            'alta'  => ['label' => 'Alta',  'color' => '#ef4444', 'dot' => '🔴'],
                            'media' => ['label' => 'Media', 'color' => '#f59e0b', 'dot' => '🟡'],
                            'baja'  => ['label' => 'Baja',  'color' => '#10b981', 'dot' => '🟢'],
                        ];
                        $pc = $prioConfig[$prio] ?? $prioConfig['media'];
                        ?>
                        <span class="badge-estado" style="background: <?= $pc['color'] ?>20; color: <?= $pc['color'] ?>; white-space: nowrap;">
                            <?= $pc['dot'] ?> <?= $pc['label'] ?>
                        </span>
                    </td>
                    <td><?= sanitize($pr['tipo_propiedad'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($pr['enlace'])): ?>
                            <a href="<?= sanitize($pr['enlace']) ?>" target="_blank" rel="noopener" title="<?= sanitize($pr['enlace']) ?>" aria-label="Abrir enlace de propiedad">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php else: ?>-
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize(mb_substr($pr['direccion'] ?? '-', 0, 25)) ?><?= mb_strlen($pr['direccion'] ?? '') > 25 ? '...' : '' ?></td>
                    <td class="fw-bold"><?= $pr['precio_estimado'] ? formatPrecio($pr['precio_estimado']) : '-' ?></td>
                    <td><?= $pr['superficie'] ? $pr['superficie'] . ' m²' : '-' ?></td>
                    <td>
                        <?php if (!empty($pr['fecha_publicacion_propiedad'])): ?>
                            <?php
                            $fechaPublicacion = new DateTime($pr['fecha_publicacion_propiedad']);
                            $hoyPublicacion = new DateTime('today');
                            $diasPublicada = max(0, intval($fechaPublicacion->diff($hoyPublicacion)->format('%a')));
                            ?>
                            <div><?= formatFecha($pr['fecha_publicacion_propiedad']) ?></div>
                            <small class="text-muted"><?= $diasPublicada ?> días</small>
                        <?php else: ?>-
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pr['fecha_proximo_contacto']): ?>
                            <?php
                            $proxDate = new DateTime($pr['fecha_proximo_contacto']);
                            $today = new DateTime('today');
                            $isPast = $proxDate < $today;
                            $isToday = $proxDate->format('Y-m-d') === $today->format('Y-m-d');
                            $horaProxContacto = !empty($pr['hora_contacto']) ? substr($pr['hora_contacto'], 0, 5) : '--:--';
                            ?>
                            <span class="<?= $isPast ? 'text-danger fw-bold' : ($isToday ? 'text-warning fw-bold' : '') ?>">
                                <?= formatFecha($pr['fecha_proximo_contacto']) ?>
                            </span>
                            <br><small class="text-muted"><i class="bi bi-clock"></i> <?= $horaProxContacto ?></small>
                            <?php if ($isPast): ?><i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Vencido"></i><?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <?php if ($isAdm): ?>
                    <td><?= sanitize($pr['agente_nombre'] ?? '-') ?></td>
                    <?php endif; ?>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="ver.php?id=<?= $pr['id'] ?>" class="btn btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="form.php?id=<?= $pr['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php if (!empty($pr['telefono'])): ?>
                            <?php
                            $waPhone = preg_replace('/\D/', '', $pr['telefono']);
                            if (strlen($waPhone) === 9) $waPhone = '34' . $waPhone;
                            ?>
                            <a href="https://wa.me/<?= $waPhone ?>?text=<?= urlencode('Hola ' . ($pr['nombre'] ?? '')) ?>" target="_blank" rel="noopener" class="btn btn-outline-success" title="WhatsApp" style="color:#25D366; border-color:#25D366;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16"><path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232"/></svg>
                            </a>
                            <?php endif; ?>
                            <form method="POST" action="delete.php" onsubmit="return confirm('Seguro que deseas eliminar este prospecto?')" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="id" value="<?= intval($pr['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prospectos)): ?>
                <tr><td colspan="<?= $isAdm ? 15 : 14 ?>" class="text-center text-muted py-5">
                    <i class="bi bi-person-plus fs-1 d-block mb-2"></i>No se encontraron prospectos
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>
<?php endif; ?>

<script>
const selectAll = document.getElementById('selectAll');
const bulkBar = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');
const checks = document.querySelectorAll('.bulk-check');
const csrfToken = '<?= csrfToken() ?>';
let draggedCard = null;

function updateBulkBar() {
    if (!bulkBar || !bulkCount) return;
    const selected = document.querySelectorAll('.bulk-check:checked');
    if (selected.length > 0) {
        bulkBar.classList.remove('d-none');
        bulkBar.classList.add('d-flex');
        bulkCount.textContent = selected.length;
    } else {
        bulkBar.classList.add('d-none');
        bulkBar.classList.remove('d-flex');
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        checks.forEach(c => c.checked = this.checked);
        updateBulkBar();
    });
}
checks.forEach(c => c.addEventListener('change', updateBulkBar));

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.bulk-check:checked')).map(c => c.value);
}

function bulkAction(accion, extra = {}) {
    const ids = getSelectedIds();
    if (!ids.length) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'bulk.php';
    form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '">' +
        '<input type="hidden" name="accion" value="' + accion + '">' +
        '<input type="hidden" name="ids" value="' + ids.join(',') + '">';
    for (const [k, v] of Object.entries(extra)) {
        form.innerHTML += '<input type="hidden" name="' + k + '" value="' + v + '">';
    }
    document.body.appendChild(form);
    form.submit();
}

function bulkDelete() {
    if (confirm('Seguro que deseas eliminar los prospectos seleccionados?')) {
        bulkAction('eliminar');
    }
}

function bulkToggleActive(val) {
    bulkAction('toggle_activo', { activo: val });
}

function bulkChangeEtapa(etapa) {
    bulkAction('cambiar_etapa', { etapa: etapa });
}

function updateKanbanCounts() {
    document.querySelectorAll('.kanban-column').forEach(col => {
        const etapa = col.dataset.etapa;
        const countEl = document.getElementById('col-count-' + etapa);
        if (!countEl) return;
        countEl.textContent = col.querySelectorAll('.kanban-card').length;
    });
}

function ensureKanbanEmptyState(columnBody) {
    const cards = columnBody.querySelectorAll('.kanban-card');
    let empty = columnBody.querySelector('.kanban-empty');
    if (cards.length === 0) {
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'kanban-empty';
            empty.textContent = 'Sin prospectos';
            columnBody.appendChild(empty);
        }
    } else if (empty) {
        empty.remove();
    }
}

document.querySelectorAll('.kanban-card').forEach(card => {
    card.addEventListener('dragstart', function() {
        draggedCard = card;
        card.classList.add('opacity-50');
    });
    card.addEventListener('dragend', function() {
        card.classList.remove('opacity-50');
        draggedCard = null;
    });
});

document.querySelectorAll('.kanban-column-body[data-droppable="1"]').forEach(zone => {
    zone.addEventListener('dragover', function(e) {
        e.preventDefault();
        zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', function() {
        zone.classList.remove('drag-over');
    });
    zone.addEventListener('drop', function(e) {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if (!draggedCard) return;

        const newEtapa = zone.dataset.etapa;
        const oldEtapa = draggedCard.dataset.etapa;
        if (!newEtapa || newEtapa === oldEtapa) return;

        const cardId = draggedCard.dataset.id;
        const oldZone = draggedCard.closest('.kanban-column-body');

        fetch('mover_etapa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(csrfToken) + '&id=' + encodeURIComponent(cardId) + '&etapa=' + encodeURIComponent(newEtapa)
        })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.ok) {
                alert((data && data.error) ? data.error : 'No se pudo mover el prospecto.');
                return;
            }

            const empty = zone.querySelector('.kanban-empty');
            if (empty) empty.remove();
            draggedCard.dataset.etapa = newEtapa;
            zone.appendChild(draggedCard);
            ensureKanbanEmptyState(oldZone);
            ensureKanbanEmptyState(zone);
            updateKanbanCounts();
        })
        .catch(() => {
            alert('Error de red al mover el prospecto.');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
