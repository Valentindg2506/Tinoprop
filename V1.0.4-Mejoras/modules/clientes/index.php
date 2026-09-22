<?php
$pageTitle = 'Clientes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

// Exportar CSV
if (get('accion') === 'exportar') {
    $db = getDB();
    $exportStmt = $db->query("SELECT c.*, u.nombre as agente_nombre FROM clientes c LEFT JOIN usuarios u ON c.agente_id = u.id ORDER BY c.created_at DESC");
    exportarClientes($exportStmt->fetchAll());
    exit;
}

$db = getDB();
$isAdm = isAdmin();

// Filtros
$filtroTipo = get('tipo');
$filtroOrigen = get('origen');
$filtroProvincia = get('provincia');
$filtroBusqueda = get('q');
$filtroActivo = get('activo', '1');
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) {
    $where[] = 'c.agente_id = ?';
    $params[] = currentUserId();
}
if ($filtroTipo) { $where[] = 'FIND_IN_SET(?, c.tipo)'; $params[] = $filtroTipo; }
if ($filtroOrigen) { $where[] = 'c.origen = ?'; $params[] = $filtroOrigen; }
if ($filtroProvincia) { $where[] = 'c.provincia = ?'; $params[] = $filtroProvincia; }
if ($filtroActivo !== '') { $where[] = 'c.activo = ?'; $params[] = $filtroActivo; }
if ($filtroBusqueda) {
    $where[] = '(c.nombre LIKE ? OR c.apellidos LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.dni_nie_cif LIKE ?)';
    $busq = '%' . $filtroBusqueda . '%';
    $params = array_merge($params, [$busq, $busq, $busq, $busq, $busq]);
}

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM clientes c WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmtCli = $db->prepare("SELECT c.*, u.nombre as agente_nombre
    FROM clientes c
    LEFT JOIN usuarios u ON c.agente_id = u.id
    WHERE $whereStr
    ORDER BY c.created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtCli->execute($params);
$clientes = $stmtCli->fetchAll();

$provincias = getProvincias();
$baseUrl = 'index.php?tipo=' . urlencode($filtroTipo) . '&origen=' . urlencode($filtroOrigen) . '&provincia=' . urlencode($filtroProvincia) . '&q=' . urlencode($filtroBusqueda) . '&activo=' . urlencode($filtroActivo);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= $total ?> clientes encontrados</span>
    <div class="d-flex gap-2">
        <a href="index.php?accion=exportar" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Cliente</a>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Buscar</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Nombre, email, telefono, DNI..." value="<?= sanitize($filtroBusqueda) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="comprador" <?= $filtroTipo === 'comprador' ? 'selected' : '' ?>>Comprador</option>
                <option value="vendedor" <?= $filtroTipo === 'vendedor' ? 'selected' : '' ?>>Vendedor</option>
                <option value="inquilino" <?= $filtroTipo === 'inquilino' ? 'selected' : '' ?>>Inquilino</option>
                <option value="propietario" <?= $filtroTipo === 'propietario' ? 'selected' : '' ?>>Propietario</option>
                <option value="inversor" <?= $filtroTipo === 'inversor' ? 'selected' : '' ?>>Inversor</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Origen</label>
            <select name="origen" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="web" <?= $filtroOrigen === 'web' ? 'selected' : '' ?>>Web</option>
                <option value="telefono" <?= $filtroOrigen === 'telefono' ? 'selected' : '' ?>>Telefono</option>
                <option value="oficina" <?= $filtroOrigen === 'oficina' ? 'selected' : '' ?>>Oficina</option>
                <option value="referido" <?= $filtroOrigen === 'referido' ? 'selected' : '' ?>>Referido</option>
                <option value="portal" <?= $filtroOrigen === 'portal' ? 'selected' : '' ?>>Portal</option>
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
        <div class="col-md-1">
            <label class="form-label">Activo</label>
            <select name="activo" class="form-select form-select-sm">
                <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Si</option>
                <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>No</option>
                <option value="" <?= $filtroActivo === '' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
        </div>
    </form>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkBar" class="alert alert-primary d-none align-items-center justify-content-between mb-3">
    <span><strong id="bulkCount">0</strong> clientes seleccionados</span>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-tag"></i> Asignar Tag
            </button>
            <ul class="dropdown-menu" id="bulkTagMenu">
                <li><span class="dropdown-item text-muted">Cargando tags...</span></li>
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
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Email</th>
                    <th>Telefono</th>
                    <th>DNI/NIE/CIF</th>
                    <th>Provincia</th>
                    <th>Agente</th>
                    <th>Fecha Alta</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input bulk-check" value="<?= $c['id'] ?>"></td>
                    <td>
                        <a href="ver.php?id=<?= $c['id'] ?>">
                            <strong><?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?></strong>
                        </a>
                        <?php if (!$c['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $tipoBadge = ['comprador'=>'bg-info','vendedor'=>'bg-success','inquilino'=>'bg-warning text-dark','propietario'=>'bg-primary','inversor'=>'bg-danger'];
                        foreach (explode(',', $c['tipo']) as $t):
                            $t = trim($t);
                            if (!$t) continue;
                            $bc = $tipoBadge[$t] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $bc ?> me-1"><?= ucfirst($t) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td><?= sanitize($c['email'] ?? '-') ?></td>
                    <td><?= sanitize($c['telefono'] ?? '-') ?></td>
                    <td><?= sanitize($c['dni_nie_cif'] ?? '-') ?></td>
                    <td><?= sanitize($c['provincia'] ?? '-') ?></td>
                    <td><?= sanitize($c['agente_nombre'] ?? '-') ?></td>
                    <td><?= formatFecha($c['created_at']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="ver.php?id=<?= $c['id'] ?>" class="btn btn-outline-primary" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="form.php?id=<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Seguro que deseas eliminar este cliente?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= intval($c['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clientes)): ?>
                <tr><td colspan="10" class="text-center text-muted py-5">
                    <i class="bi bi-people fs-1 d-block mb-2"></i>No se encontraron clientes
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<script>
const selectAll = document.getElementById('selectAll');
const bulkBar = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');
const checks = document.querySelectorAll('.bulk-check');
const csrfToken = '<?= csrfToken() ?>';

function updateBulkBar() {
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

selectAll.addEventListener('change', function() {
    checks.forEach(c => c.checked = this.checked);
    updateBulkBar();
});
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
    if (confirm('Seguro que deseas eliminar los clientes seleccionados?')) {
        bulkAction('eliminar');
    }
}

function bulkToggleActive(val) {
    bulkAction('toggle_activo', { activo: val });
}

function bulkAssignTag(tagId) {
    bulkAction('asignar_tag', { tag_id: tagId });
}

// Load tags into bulk menu
fetch('tags.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'accion=listar_tags&csrf_token=' + csrfToken
}).then(r => r.json()).then(data => {
    const menu = document.getElementById('bulkTagMenu');
    if (data.tags && data.tags.length) {
        menu.innerHTML = data.tags.map(t =>
            '<li><a class="dropdown-item" href="#" onclick="bulkAssignTag(' + t.id + ');return false;">' +
            '<span class="badge me-1" style="background:' + t.color + '">&nbsp;</span>' + t.nombre + '</a></li>'
        ).join('');
    } else {
        menu.innerHTML = '<li><span class="dropdown-item text-muted">No hay tags</span></li>';
    }
}).catch(() => {});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
