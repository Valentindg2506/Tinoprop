<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

// Crear pipeline (antes del header para poder hacer redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'crear') {
    verifyCsrf();

    $countStmt = $db->query("SELECT COUNT(*) FROM pipelines");
    $totalPipelines = $countStmt->fetchColumn();

    if ($totalPipelines >= 4) {
        setFlash('danger', 'Has alcanzado el limite maximo de 4 pipelines.');
    } else {
        $nombre = post('nombre');
        $descripcion = post('descripcion');
        $color = post('color', '#10b981');

        if (empty($nombre)) {
            setFlash('danger', 'El nombre de la pipeline es obligatorio.');
        } else {
            $stmt = $db->prepare("INSERT INTO pipelines (nombre, descripcion, color, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $color, currentUserId()]);
            $pipelineId = $db->lastInsertId();

            $etapasDefault = [
                ['Nuevo', '#64748b', 0],
                ['En progreso', '#3b82f6', 1],
                ['Negociacion', '#f59e0b', 2],
                ['Cerrado', '#10b981', 3],
            ];
            $stmtEtapa = $db->prepare("INSERT INTO pipeline_etapas (pipeline_id, nombre, color, orden) VALUES (?, ?, ?, ?)");
            foreach ($etapasDefault as $etapa) {
                $stmtEtapa->execute([$pipelineId, $etapa[0], $etapa[1], $etapa[2]]);
            }

            registrarActividad('crear', 'pipeline', $pipelineId, 'Pipeline: ' . $nombre);
            setFlash('success', 'Pipeline creada correctamente con etapas por defecto.');
        }
    }

    header('Location: index.php');
    exit;
}

$pageTitle = 'Pipelines';
require_once __DIR__ . '/../../includes/header.php';

// Obtener pipelines
$pipelines = $db->query("
    SELECT p.*,
        u.nombre as creador_nombre,
        (SELECT COUNT(*) FROM pipeline_items pi WHERE pi.pipeline_id = p.id) as total_items,
        (SELECT COUNT(*) FROM pipeline_etapas pe WHERE pe.pipeline_id = p.id) as total_etapas
    FROM pipelines p
    LEFT JOIN usuarios u ON p.created_by = u.id
    ORDER BY p.created_at DESC
")->fetchAll();

$totalPipelines = count($pipelines);
?>

<!-- Barra de acciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted"><?= $totalPipelines ?> de 4 pipelines creadas</span>
    </div>
    <div>
        <?php if ($totalPipelines < 4): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNueva">
            <i class="bi bi-plus-lg"></i> Nueva Pipeline
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-secondary" disabled title="Limite de 4 pipelines alcanzado">
            <i class="bi bi-plus-lg"></i> Nueva Pipeline
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Pipelines como cards -->
<?php if (empty($pipelines)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-kanban fs-1 d-block mb-3"></i>
    <h5>No hay pipelines creadas</h5>
    <p>Crea tu primera pipeline para empezar a gestionar tus operaciones visualmente.</p>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNueva">
        <i class="bi bi-plus-lg"></i> Crear Pipeline
    </button>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($pipelines as $pipe): ?>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0" style="border-top: 4px solid <?= sanitize($pipe['color']) ?> !important;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title mb-0"><?= sanitize($pipe['nombre']) ?></h5>
                    <div class="dropdown">
                        <button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="config.php?id=<?= $pipe['id'] ?>"><i class="bi bi-gear"></i> Configurar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="delete.php" class="px-3" onsubmit="return confirm('Seguro que deseas eliminar esta pipeline y todos sus items?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= intval($pipe['id']) ?>">
                                    <button type="submit" class="dropdown-item text-danger px-0"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                <p class="card-text text-muted small mb-3"><?= sanitize($pipe['descripcion']) ?: '<em>Sin descripcion</em>' ?></p>
                <div class="d-flex gap-3 mb-3">
                    <div class="text-center">
                        <div class="fw-bold fs-5"><?= $pipe['total_items'] ?></div>
                        <small class="text-muted">Items</small>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold fs-5"><?= $pipe['total_etapas'] ?></div>
                        <small class="text-muted">Etapas</small>
                    </div>
                </div>
                <?php if (!$pipe['activo']): ?>
                    <span class="badge bg-secondary mb-2">Inactiva</span>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent border-0 pb-3">
                <a href="ver.php?id=<?= $pipe['id'] ?>" class="btn btn-sm btn-primary w-100">
                    <i class="bi bi-kanban"></i> Ver Pipeline
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Nueva Pipeline -->
<div class="modal fade" id="modalNueva" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-kanban"></i> Nueva Pipeline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100" placeholder="Ej: Ventas, Alquileres...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripcion</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Descripcion opcional de la pipeline..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#10b981">
                    </div>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle"></i> Se crearan 4 etapas por defecto: Nuevo, En progreso, Negociacion y Cerrado. Puedes personalizarlas despues.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Crear Pipeline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
