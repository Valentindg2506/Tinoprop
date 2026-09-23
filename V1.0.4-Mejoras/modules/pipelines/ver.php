<?php
$pageTitle = 'Pipeline';
require_once __DIR__ . '/../../includes/header.php';

$id = intval(get('id'));
if (!$id) {
    setFlash('danger', 'Pipeline no encontrada.');
    header('Location: index.php');
    exit;
}

$db = getDB();

function pipelineHasProspectoColumn(PDO $db): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM pipeline_items LIKE 'prospecto_id'");
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function pipelineEtapasHasConversionColumn(PDO $db): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM pipeline_etapas LIKE 'permitir_conversion'");
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$hasProspectoColumn = pipelineHasProspectoColumn($db);
$hasConversionColumn = pipelineEtapasHasConversionColumn($db);

// Obtener pipeline
$stmt = $db->prepare("SELECT * FROM pipelines WHERE id = ?");
$stmt->execute([$id]);
$pipeline = $stmt->fetch();

if (!$pipeline) {
    setFlash('danger', 'Pipeline no encontrada.');
    header('Location: index.php');
    exit;
}

$pageTitle = sanitize($pipeline['nombre']);

// Obtener etapas
if ($hasConversionColumn) {
    $stmtEtapas = $db->prepare("SELECT * FROM pipeline_etapas WHERE pipeline_id = ? ORDER BY orden ASC");
    $stmtEtapas->execute([$id]);
    $etapas = $stmtEtapas->fetchAll();
} else {
    $stmtEtapas = $db->prepare("SELECT *, 0 AS permitir_conversion FROM pipeline_etapas WHERE pipeline_id = ? ORDER BY orden ASC");
    $stmtEtapas->execute([$id]);
    $etapas = $stmtEtapas->fetchAll();
}

// Obtener items con datos relacionados
$itemsSql = "
    SELECT pi.*,
        c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
        p.referencia as propiedad_ref, p.titulo as propiedad_titulo";

if ($hasProspectoColumn) {
    $itemsSql .= ", pr.nombre as prospecto_nombre, pr.referencia as prospecto_referencia";
}

$itemsSql .= "
    FROM pipeline_items pi
    LEFT JOIN clientes c ON pi.cliente_id = c.id
    LEFT JOIN propiedades p ON pi.propiedad_id = p.id";

if ($hasProspectoColumn) {
    $itemsSql .= "\n    LEFT JOIN prospectos pr ON pi.prospecto_id = pr.id";
}

$itemsSql .= "
    WHERE pi.pipeline_id = ?
    ORDER BY pi.updated_at DESC
";

$stmtItems = $db->prepare($itemsSql);
$stmtItems->execute([$id]);
$allItems = $stmtItems->fetchAll();

// Agrupar items por etapa
$itemsPorEtapa = [];
foreach ($etapas as $etapa) {
    $itemsPorEtapa[$etapa['id']] = [];
}
foreach ($allItems as $item) {
    $itemsPorEtapa[$item['etapa_id']][] = $item;
}

$prioridadColores = [
    'baja' => '#10b981',
    'media' => '#f59e0b',
    'alta' => '#ef4444',
];
?>

<style>
.kanban-wrapper {
    overflow-x: auto;
    padding-bottom: 1rem;
    -webkit-overflow-scrolling: touch;
}
.kanban-board {
    display: flex;
    gap: 1rem;
    min-height: 60vh;
    align-items: flex-start;
}
.kanban-column {
    min-width: 280px;
    max-width: 320px;
    flex: 1 0 280px;
    background: #f8fafc;
    border-radius: 0.75rem;
    border-top: 3px solid var(--col-color, #64748b);
    display: flex;
    flex-direction: column;
    transition: background 0.2s;
}
.kanban-column.drag-over {
    background: #ecfdf5;
    box-shadow: 0 0 0 2px #10b981;
}
.kanban-column-header {
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}
.kanban-column-header h6 {
    margin: 0;
    font-weight: 600;
    font-size: 0.875rem;
    color: #334155;
}
.kanban-column-count {
    background: #e2e8f0;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.125rem 0.5rem;
    border-radius: 1rem;
}
.kanban-column-body {
    padding: 0.5rem;
    flex: 1;
    min-height: 100px;
}
.kanban-card {
    background: #fff;
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    cursor: grab;
    transition: box-shadow 0.2s, transform 0.15s;
    border: 1px solid #e2e8f0;
    position: relative;
}
.kanban-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-1px);
}
.kanban-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}
.kanban-card-title {
    font-weight: 600;
    font-size: 0.875rem;
    color: #1e293b;
    margin-bottom: 0.375rem;
}
.kanban-card-meta {
    font-size: 0.75rem;
    color: #64748b;
}
.kanban-card-meta i {
    width: 14px;
    text-align: center;
}
.kanban-card-value {
    font-weight: 700;
    color: #10b981;
    font-size: 0.875rem;
    margin-top: 0.375rem;
}
.priority-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
}
.kanban-card-actions {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.5rem;
    padding-top: 0.375rem;
    border-top: 1px solid #f1f5f9;
}
.kanban-card-actions a {
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
}
.kanban-card-time {
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}
</style>

<!-- Barra de acciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <div>
            <h4 class="mb-0" style="color: <?= sanitize($pipeline['color']) ?>">
                <i class="bi bi-kanban"></i> <?= sanitize($pipeline['nombre']) ?>
            </h4>
            <?php if ($pipeline['descripcion']): ?>
            <small class="text-muted"><?= sanitize($pipeline['descripcion']) ?></small>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="config.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Configurar</a>
        <a href="form_item.php?pipeline_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo Item</a>
    </div>
</div>

<!-- Kanban Board -->
<div class="kanban-wrapper">
    <div class="kanban-board">
        <?php foreach ($etapas as $etapa): ?>
        <div class="kanban-column" style="--col-color: <?= sanitize($etapa['color']) ?>" data-etapa-id="<?= $etapa['id'] ?>">
            <div class="kanban-column-header">
                <h6><span style="color: <?= sanitize($etapa['color']) ?>"><i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i></span> <?= sanitize($etapa['nombre']) ?></h6>
                <span class="kanban-column-count"><?= count($itemsPorEtapa[$etapa['id']] ?? []) ?></span>
            </div>
            <div class="kanban-column-body" data-etapa-id="<?= $etapa['id'] ?>">
                <?php foreach (($itemsPorEtapa[$etapa['id']] ?? []) as $item): ?>
                <?php
                    $etapaPermiteConversion = !empty($etapa['permitir_conversion']) || (!$hasConversionColumn && stripos((string)($etapa['nombre'] ?? ''), 'cerr') !== false);
                    $canConvertProspecto = !empty($item['prospecto_id']) && empty($item['cliente_id']) && $etapaPermiteConversion;
                ?>
                <div class="kanban-card" draggable="true" data-item-id="<?= $item['id'] ?>">
                    <span class="priority-dot" style="background: <?= $prioridadColores[$item['prioridad']] ?>" title="Prioridad: <?= $item['prioridad'] ?>"></span>
                    <div class="kanban-card-title"><?= sanitize($item['titulo']) ?></div>
                    <div class="kanban-card-meta">
                        <?php if ($item['cliente_nombre']): ?>
                        <div><i class="bi bi-person"></i> <?= sanitize($item['cliente_nombre'] . ' ' . $item['cliente_apellidos']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item['prospecto_nombre'])): ?>
                        <div><i class="bi bi-person-badge"></i> <?= sanitize((!empty($item['prospecto_referencia']) ? $item['prospecto_referencia'] . ' - ' : '') . $item['prospecto_nombre']) ?></div>
                        <?php endif; ?>
                        <?php if ($item['propiedad_ref']): ?>
                        <div><i class="bi bi-house"></i> <?= sanitize($item['propiedad_ref']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($item['valor']): ?>
                    <div class="kanban-card-value"><?= formatPrecio($item['valor']) ?></div>
                    <?php endif; ?>
                    <div class="kanban-card-time"><?= formatFechaHora($item['updated_at']) ?></div>
                    <div class="kanban-card-actions">
                        <a href="form_item.php?id=<?= $item['id'] ?>&pipeline_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                        <?php if ($canConvertProspecto): ?>
                        <form method="POST" action="convertir_prospecto.php" class="d-inline" onsubmit="return confirm('¿Convertir este prospecto a cliente y vincularlo al item?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="item_id" value="<?= intval($item['id']) ?>">
                            <input type="hidden" name="pipeline_id" value="<?= intval($id) ?>">
                            <button type="submit" class="btn btn-outline-success btn-sm" title="Convertir prospecto a cliente"><i class="bi bi-person-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="delete_item.php" class="d-inline" onsubmit="return confirm('Eliminar este item?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= intval($item['id']) ?>">
                            <input type="hidden" name="pipeline_id" value="<?= intval($id) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($etapas)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-columns-gap fs-1 d-block mb-3"></i>
    <h5>No hay etapas configuradas</h5>
    <p>Configura las etapas de esta pipeline para empezar a usarla.</p>
    <a href="config.php?id=<?= $id ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar Etapas</a>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let draggedCard = null;
    const csrfToken = '<?= csrfToken() ?>';

    // Drag start
    document.querySelectorAll('.kanban-card').forEach(function(card) {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.itemId);
        });

        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            document.querySelectorAll('.kanban-column').forEach(function(col) {
                col.classList.remove('drag-over');
            });
            draggedCard = null;
        });
    });

    // Drop zones (column bodies)
    document.querySelectorAll('.kanban-column-body').forEach(function(zone) {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.closest('.kanban-column').classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function(e) {
            // Only remove if truly leaving the column
            if (!this.closest('.kanban-column').contains(e.relatedTarget)) {
                this.closest('.kanban-column').classList.remove('drag-over');
            }
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.closest('.kanban-column').classList.remove('drag-over');

            if (!draggedCard) return;

            var itemId = draggedCard.dataset.itemId;
            var newEtapaId = this.dataset.etapaId;
            var oldEtapaId = draggedCard.closest('.kanban-column-body').dataset.etapaId;

            // Move card visually
            this.appendChild(draggedCard);

            // Update counters
            updateColumnCounts();

            // Only call API if stage actually changed
            if (newEtapaId !== oldEtapaId) {
                var formData = new FormData();
                formData.append('item_id', itemId);
                formData.append('etapa_id', newEtapaId);
                formData.append('csrf_token', csrfToken);

                fetch('mover.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data.success) {
                        alert('Error al mover el item: ' + (data.error || 'Error desconocido'));
                        location.reload();
                    }
                })
                .catch(function() {
                    alert('Error de conexion. Se recargara la pagina.');
                    location.reload();
                });
            }
        });
    });

    function updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(function(col) {
            var count = col.querySelector('.kanban-column-body').querySelectorAll('.kanban-card').length;
            col.querySelector('.kanban-column-count').textContent = count;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
