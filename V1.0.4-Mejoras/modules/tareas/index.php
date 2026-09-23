<?php
$pageTitle = 'Tareas';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$isAdm = isAdmin();

$filtroEstado = get('estado', 'pendiente');
$filtroPrioridad = get('prioridad');
$filtroTipo = get('tipo');
$page = max(1, intval(get('page', 1)));

$where = [];
$params = [];

if (!$isAdm) { $where[] = 't.asignado_a = ?'; $params[] = currentUserId(); }
if ($filtroEstado) { $where[] = 't.estado = ?'; $params[] = $filtroEstado; }
if ($filtroPrioridad) { $where[] = 't.prioridad = ?'; $params[] = $filtroPrioridad; }
if ($filtroTipo) { $where[] = 't.tipo = ?'; $params[] = $filtroTipo; }

$whereStr = empty($where) ? '1=1' : implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM tareas t WHERE $whereStr");
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$pagination = paginate($total, 20, $page);

$stmtTar = $db->prepare("SELECT t.*, p.referencia as prop_ref, c.nombre as cliente_nombre, u.nombre as agente_nombre, u2.nombre as creador_nombre
    FROM tareas t
    LEFT JOIN propiedades p ON t.propiedad_id = p.id
    LEFT JOIN clientes c ON t.cliente_id = c.id
    LEFT JOIN usuarios u ON t.asignado_a = u.id
    LEFT JOIN usuarios u2 ON t.creado_por = u2.id
    WHERE $whereStr
    ORDER BY FIELD(t.prioridad, 'urgente','alta','media','baja'), t.fecha_vencimiento ASC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtTar->execute($params);
$tareas = $stmtTar->fetchAll();

$baseUrl = 'index.php?estado=' . urlencode($filtroEstado) . '&prioridad=' . urlencode($filtroPrioridad) . '&tipo=' . urlencode($filtroTipo);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= $total ?> tareas</span>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Tarea</a>
</div>

<div class="filter-bar">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="en_progreso" <?= $filtroEstado === 'en_progreso' ? 'selected' : '' ?>>En progreso</option>
                <option value="completada" <?= $filtroEstado === 'completada' ? 'selected' : '' ?>>Completada</option>
                <option value="cancelada" <?= $filtroEstado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Prioridad</label>
            <select name="prioridad" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="urgente" <?= $filtroPrioridad === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                <option value="alta" <?= $filtroPrioridad === 'alta' ? 'selected' : '' ?>>Alta</option>
                <option value="media" <?= $filtroPrioridad === 'media' ? 'selected' : '' ?>>Media</option>
                <option value="baja" <?= $filtroPrioridad === 'baja' ? 'selected' : '' ?>>Baja</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (['llamada'=>'Llamada','email'=>'Email','reunion'=>'Reunion','visita'=>'Visita','gestion'=>'Gestion','documentacion'=>'Documentacion','otro'=>'Otro'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $filtroTipo === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
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
            <thead><tr><th></th><th>Titulo</th><th>Tipo</th><th>Relacionado</th><th>Asignado</th><th>Vencimiento</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($tareas as $t): ?>
            <tr>
                <td><span class="prioridad-<?= $t['prioridad'] ?>"><i class="bi bi-circle-fill" style="font-size:0.6rem"></i></span></td>
                <td><strong><?= sanitize($t['titulo']) ?></strong>
                    <?php if ($t['descripcion']): ?><br><small class="text-muted"><?= sanitize(mb_substr($t['descripcion'], 0, 50)) ?></small><?php endif; ?>
                </td>
                <td><?= ucfirst($t['tipo']) ?></td>
                <td>
                    <?php if ($t['prop_ref']): ?><i class="bi bi-house"></i> <?= sanitize($t['prop_ref']) ?><br><?php endif; ?>
                    <?php if ($t['cliente_nombre']): ?><i class="bi bi-person"></i> <?= sanitize($t['cliente_nombre']) ?><?php endif; ?>
                    <?php if (!$t['prop_ref'] && !$t['cliente_nombre']): ?>-<?php endif; ?>
                </td>
                <td><?= sanitize($t['agente_nombre'] ?? '-') ?></td>
                <td>
                    <?php if ($t['fecha_vencimiento']): ?>
                    <span class="<?= strtotime($t['fecha_vencimiento']) < time() && $t['estado'] === 'pendiente' ? 'text-danger fw-bold' : '' ?>">
                        <?= formatFechaHora($t['fecha_vencimiento']) ?>
                    </span>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td><span class="badge-estado badge-<?= $t['estado'] ?>"><?= ucfirst(str_replace('_',' ',$t['estado'])) ?></span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="form.php?id=<?= $t['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <?php if ($t['estado'] !== 'completada'): ?>
                        <form method="POST" action="complete.php" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= intval($t['id']) ?>">
                            <button type="submit" class="btn btn-outline-success" title="Completar"><i class="bi bi-check-lg"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="delete.php" onsubmit="return confirm('Eliminar esta tarea?')" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= intval($t['id']) ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tareas)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-check-circle fs-1 d-block mb-2"></i>No se encontraron tareas</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, $baseUrl) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
