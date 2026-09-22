<?php
$pageTitle = 'Log de Automatizacion';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));

if (!$id) {
    setFlash('danger', 'Automatizacion no especificada.');
    header('Location: index.php');
    exit;
}

// Obtener automatizacion
$stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id = ?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    setFlash('danger', 'Automatizacion no encontrada.');
    header('Location: index.php');
    exit;
}

// Paginacion
$page = max(1, intval(get('page', 1)));
$perPage = 20;

$stmtCount = $db->prepare("SELECT COUNT(*) FROM automatizacion_log WHERE automatizacion_id = ?");
$stmtCount->execute([$id]);
$total = $stmtCount->fetchColumn();

$pagination = paginate($total, $perPage, $page);

// Obtener logs
$stmtLog = $db->prepare("
    SELECT l.*, a.tipo as accion_tipo
    FROM automatizacion_log l
    LEFT JOIN automatizacion_acciones a ON l.accion_id = a.id
    WHERE l.automatizacion_id = ?
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$stmtLog->execute([$id, $perPage, $pagination['offset']]);
$logs = $stmtLog->fetchAll();

$estadoBadge = [
    'exito' => 'bg-success',
    'error' => 'bg-danger',
    'pendiente' => 'bg-warning text-dark',
];
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0">Log: <?= sanitize($auto['nombre']) ?></h5>
    <span class="badge bg-secondary"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
</div>

<?php if (empty($logs)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-list-check fs-1 d-block mb-3"></i>
    <h5>No hay registros de ejecucion</h5>
    <p>Esta automatizacion aun no se ha ejecutado.</p>
</div>
<?php else: ?>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th>Accion</th>
                    <th>Entidad</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= formatFechaHora($log['created_at']) ?></td>
                    <td>
                        <span class="badge <?= $estadoBadge[$log['estado']] ?? 'bg-secondary' ?>">
                            <?= sanitize($log['estado']) ?>
                        </span>
                    </td>
                    <td><?= $log['accion_tipo'] ? sanitize($log['accion_tipo']) : '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <?php if ($log['entidad_tipo']): ?>
                            <span class="badge bg-light text-dark"><?= sanitize($log['entidad_tipo']) ?></span>
                            <?php if ($log['entidad_id']): ?>
                                #<?= intval($log['entidad_id']) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['detalles']): ?>
                            <small><?= sanitize(mb_strimwidth($log['detalles'], 0, 120, '...')) ?></small>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= renderPagination($pagination, 'log.php?id=' . $id) ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
