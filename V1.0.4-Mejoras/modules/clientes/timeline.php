<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

/**
 * Ejecuta consultas de timeline sin romper la vista si falta una tabla/columna.
 */
function timelineFetchAll(PDO $db, $sql, array $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

$id = intval(get('id'));
if (!$id) {
    setFlash('danger', 'ID de cliente no especificado.');
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();
if (!$cliente) {
    setFlash('danger', 'Cliente no encontrado.');
    header('Location: index.php');
    exit;
}

$pageTitle = 'Timeline - ' . $cliente['nombre'];
require_once __DIR__ . '/../../includes/header.php';

// Build unified timeline
$timeline = [];

// 1. Actividad log
$actividadRows = timelineFetchAll(
    $db,
    "SELECT accion AS titulo, COALESCE(detalles, '') AS descripcion, created_at AS fecha FROM actividad_log WHERE entidad = 'cliente' AND entidad_id = ? ORDER BY created_at DESC",
    [$id]
);
foreach ($actividadRows as $row) {
    $timeline[] = [
        'tipo'        => 'actividad',
        'titulo'      => $row['titulo'],
        'descripcion' => $row['descripcion'] ?? '',
        'fecha'       => $row['fecha'],
        'icono'       => 'bi-activity',
        'color'       => '#6b7280',
    ];
}

// 2. Visitas
$visitasRows = timelineFetchAll(
    $db,
    "SELECT v.fecha, v.hora, v.estado, v.comentarios, p.referencia, p.titulo AS propiedad FROM visitas v LEFT JOIN propiedades p ON v.propiedad_id = p.id WHERE v.cliente_id = ? ORDER BY v.fecha DESC",
    [$id]
);
foreach ($visitasRows as $row) {
    $titulo = 'Visita: ' . ($row['referencia'] ? $row['referencia'] . ' - ' : '') . ($row['propiedad'] ?? 'Propiedad');
    $desc = 'Estado: ' . ucfirst(str_replace('_', ' ', $row['estado']));
    if ($row['hora']) $desc .= ' | Hora: ' . substr($row['hora'], 0, 5);
    if ($row['comentarios']) $desc .= ' | ' . $row['comentarios'];
    $timeline[] = [
        'tipo'        => 'visita',
        'titulo'      => $titulo,
        'descripcion' => $desc,
        'fecha'       => $row['fecha'],
        'icono'       => 'bi-calendar-event',
        'color'       => '#10b981',
    ];
}

// 3. Tareas
$tareasRows = timelineFetchAll(
    $db,
    "SELECT titulo, descripcion, fecha_vencimiento AS fecha, estado, prioridad FROM tareas WHERE cliente_id = ? ORDER BY fecha_vencimiento DESC",
    [$id]
);
foreach ($tareasRows as $row) {
    $desc = 'Estado: ' . ucfirst($row['estado']) . ' | Prioridad: ' . ucfirst($row['prioridad']);
    if ($row['descripcion']) $desc .= ' | ' . $row['descripcion'];
    $timeline[] = [
        'tipo'        => 'tarea',
        'titulo'      => 'Tarea: ' . ($row['titulo'] ?? 'Sin titulo'),
        'descripcion' => $desc,
        'fecha'       => $row['fecha'],
        'icono'       => 'bi-check2-square',
        'color'       => '#8b5cf6',
    ];
}

// 4. Emails
$emailsRows = timelineFetchAll(
    $db,
    "SELECT em.asunto, COALESCE(NULLIF(em.cuerpo, ''), em.cuerpo_html, '') AS mensaje, em.created_at AS fecha, ec.email AS cuenta_email FROM email_mensajes em LEFT JOIN email_cuentas ec ON em.cuenta_id = ec.id WHERE em.cliente_id = ? ORDER BY em.created_at DESC",
    [$id]
);
foreach ($emailsRows as $row) {
    $desc = $row['cuenta_email'] ? 'Cuenta: ' . $row['cuenta_email'] : '';
    if ($row['mensaje']) {
        $desc .= ($desc ? ' | ' : '') . mb_substr(strip_tags($row['mensaje']), 0, 120);
    }
    $timeline[] = [
        'tipo'        => 'email',
        'titulo'      => 'Email: ' . ($row['asunto'] ?? 'Sin asunto'),
        'descripcion' => $desc,
        'fecha'       => $row['fecha'],
        'icono'       => 'bi-envelope',
        'color'       => '#3b82f6',
    ];
}

// 5. Notas (opcional: puede no existir en instalaciones antiguas)
$notasRows = timelineFetchAll(
    $db,
    "SELECT titulo, contenido, created_at AS fecha FROM notas WHERE entidad = 'cliente' AND entidad_id = ? ORDER BY created_at DESC",
    [$id]
);
foreach ($notasRows as $row) {
    $timeline[] = [
        'tipo'        => 'nota',
        'titulo'      => 'Nota: ' . ($row['titulo'] ?? 'Sin titulo'),
        'descripcion' => $row['contenido'] ?? '',
        'fecha'       => $row['fecha'],
        'icono'       => 'bi-sticky',
        'color'       => '#f59e0b',
    ];
}

// Sort all items by fecha DESC
usort($timeline, function ($a, $b) {
    return strtotime($b['fecha'] ?? '0') - strtotime($a['fecha'] ?? '0');
});

$tipoColores = [
    'visita'    => '#10b981',
    'tarea'     => '#8b5cf6',
    'email'     => '#3b82f6',
    'nota'      => '#f59e0b',
    'actividad' => '#6b7280',
];
?>

<style>
    .timeline-container {
        position: relative;
        padding-left: 40px;
    }
    .timeline-container::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 24px;
    }
    .timeline-dot {
        position: absolute;
        left: -33px;
        top: 4px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px currentColor;
        background: currentColor;
        z-index: 1;
    }
    .timeline-card {
        border-left: 3px solid;
        border-radius: 6px;
        background: #fff;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        transition: box-shadow 0.2s;
    }
    .timeline-card:hover {
        box-shadow: 0 3px 8px rgba(0,0,0,0.12);
    }
    .timeline-tipo-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 2px 8px;
        border-radius: 4px;
        color: #fff;
    }
    .timeline-fecha {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .timeline-titulo {
        font-weight: 600;
        font-size: 0.95rem;
        margin: 6px 0 4px;
    }
    .timeline-desc {
        font-size: 0.85rem;
        color: #6c757d;
        margin: 0;
        line-height: 1.5;
    }
    .empty-state-timeline {
        text-align: center;
        padding: 60px 20px;
    }
    .empty-state-timeline i {
        font-size: 3rem;
        color: #dee2e6;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <i class="bi bi-clock-history"></i>
        Timeline de <?= sanitize($cliente['nombre'] . ' ' . ($cliente['apellidos'] ?? '')) ?>
    </h4>
    <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<?php if (empty($timeline)): ?>
    <div class="card">
        <div class="card-body empty-state-timeline">
            <i class="bi bi-clock-history d-block mb-3"></i>
            <h5 class="text-muted">Sin actividad registrada</h5>
            <p class="text-muted mb-0">Aun no hay actividades, visitas, tareas, emails ni notas para este cliente.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <span class="text-muted"><i class="bi bi-list-ul"></i> <?= count($timeline) ?> actividades</span>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach ($tipoColores as $tipo => $color): ?>
                        <span class="timeline-tipo-badge" style="background:<?= $color ?>"><?= ucfirst($tipo) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="timeline-container">
                <?php foreach ($timeline as $item): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot" style="color:<?= sanitize($item['color']) ?>"></div>
                        <div class="timeline-card" style="border-left-color:<?= sanitize($item['color']) ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="timeline-tipo-badge" style="background:<?= sanitize($item['color']) ?>">
                                    <i class="<?= sanitize($item['icono']) ?>"></i> <?= ucfirst(sanitize($item['tipo'])) ?>
                                </span>
                                <span class="timeline-fecha">
                                    <i class="bi bi-clock"></i> <?= formatFechaHora($item['fecha']) ?>
                                </span>
                            </div>
                            <div class="timeline-titulo"><?= sanitize($item['titulo']) ?></div>
                            <?php if (!empty($item['descripcion'])): ?>
                                <p class="timeline-desc"><?= sanitize(mb_substr($item['descripcion'], 0, 200)) ?><?= mb_strlen($item['descripcion']) > 200 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
