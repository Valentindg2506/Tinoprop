<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

// Toggle activo/inactivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'toggle') {
    verifyCsrf();
    $id = intval(post('id'));

    if (!isAdmin()) {
        $ownerStmt = $db->prepare("SELECT created_by FROM automatizaciones WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$id]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre esta automatizacion.');
            header('Location: index.php');
            exit;
        }
    }

    $stmt = $db->prepare("UPDATE automatizaciones SET activo = NOT activo WHERE id = ?");
    $stmt->execute([$id]);
    registrarActividad('actualizar', 'automatizacion', $id, 'Toggle estado automatizacion');
    setFlash('success', 'Estado de la automatizacion actualizado.');
    header('Location: index.php');
    exit;
}

// Duplicar automatizacion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'duplicar') {
    verifyCsrf();
    $id = intval(post('id'));

    if (!isAdmin()) {
        $ownerStmt = $db->prepare("SELECT created_by FROM automatizaciones WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$id]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre esta automatizacion.');
            header('Location: index.php');
            exit;
        }
    }

    $orig = $db->prepare("SELECT * FROM automatizaciones WHERE id=?"); $orig->execute([$id]); $orig=$orig->fetch();
    if ($orig) {
        $db->prepare("INSERT INTO automatizaciones (nombre, descripcion, trigger_tipo, trigger_condiciones, activo, created_by) VALUES (?,?,?,?,0,?)")
            ->execute(['Copia de '.$orig['nombre'], $orig['descripcion'], $orig['trigger_tipo'], $orig['trigger_condiciones'], currentUserId()]);
        $newId = $db->lastInsertId();
        // Copy actions
        $acciones = $db->prepare("SELECT * FROM automatizacion_acciones WHERE automatizacion_id=? ORDER BY orden");
        $acciones->execute([$id]);
        foreach ($acciones->fetchAll() as $acc) {
            $db->prepare("INSERT INTO automatizacion_acciones (automatizacion_id, tipo, configuracion, orden) VALUES (?,?,?,?)")
                ->execute([$newId, $acc['tipo'], $acc['configuracion'], $acc['orden']]);
        }
        setFlash('success', 'Automatizacion duplicada.');
    }
    header('Location: index.php');
    exit;
}

$pageTitle = 'Automatizaciones';
require_once __DIR__ . '/../../includes/header.php';

// Obtener automatizaciones
if (isAdmin()) {
    $automatizaciones = $db->query("
        SELECT a.*, u.nombre as creador_nombre
        FROM automatizaciones a
        LEFT JOIN usuarios u ON a.created_by = u.id
        ORDER BY a.activo DESC, a.created_at DESC
    ")->fetchAll();
} else {
    $stmtAutos = $db->prepare("
        SELECT a.*, u.nombre as creador_nombre
        FROM automatizaciones a
        LEFT JOIN usuarios u ON a.created_by = u.id
        WHERE a.created_by = ?
        ORDER BY a.activo DESC, a.created_at DESC
    ");
    $stmtAutos->execute([intval(currentUserId())]);
    $automatizaciones = $stmtAutos->fetchAll();
}

// Contar acciones por automatizacion
$accionesCount = [];
if (!empty($automatizaciones)) {
    $ids = array_column($automatizaciones, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT automatizacion_id, COUNT(*) as total FROM automatizacion_acciones WHERE automatizacion_id IN ($placeholders) GROUP BY automatizacion_id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $accionesCount[$row['automatizacion_id']] = $row['total'];
    }
}

$triggerLabels = [
    'nuevo_cliente' => 'Nuevo cliente creado',
    'nueva_propiedad' => 'Nueva propiedad captada',
    'nueva_visita' => 'Visita programada',
    'visita_realizada' => 'Visita realizada',
    'tarea_vencida' => 'Tarea vencida',
    'pipeline_etapa_cambiada' => 'Cambio de etapa en pipeline',
    'nuevo_documento' => 'Documento subido',
    'manual' => 'Ejecucion manual',
    'nuevo_formulario' => 'Formulario enviado',
    'contrato_firmado' => 'Contrato firmado',
    'factura_pagada' => 'Factura pagada',
    'presupuesto_aceptado' => 'Presupuesto aceptado',
];

$triggerIcons = [
    'nuevo_cliente' => 'bi-person-plus',
    'nueva_propiedad' => 'bi-house-add',
    'nueva_visita' => 'bi-calendar-plus',
    'visita_realizada' => 'bi-calendar-check',
    'tarea_vencida' => 'bi-exclamation-triangle',
    'pipeline_etapa_cambiada' => 'bi-arrow-right-circle',
    'nuevo_documento' => 'bi-file-earmark-arrow-up',
    'manual' => 'bi-hand-index',
    'nuevo_formulario' => 'bi-ui-checks',
    'contrato_firmado' => 'bi-pen',
    'factura_pagada' => 'bi-cash-coin',
    'presupuesto_aceptado' => 'bi-check-circle',
];

$triggerColors = [
    'nuevo_cliente' => '#10b981',
    'nueva_propiedad' => '#06b6d4',
    'nueva_visita' => '#8b5cf6',
    'visita_realizada' => '#059669',
    'tarea_vencida' => '#ef4444',
    'pipeline_etapa_cambiada' => '#f59e0b',
    'nuevo_documento' => '#6366f1',
    'manual' => '#64748b',
    'nuevo_formulario' => '#ec4899',
    'contrato_firmado' => '#14b8a6',
    'factura_pagada' => '#22c55e',
    'presupuesto_aceptado' => '#3b82f6',
];

$totalAutomatizaciones = count($automatizaciones);
$activas = array_filter($automatizaciones, fn($a) => $a['activo']);
$totalEjecuciones = array_sum(array_column($automatizaciones, 'ejecuciones'));
$totalAcciones = array_sum($accionesCount);
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total</div>
                    <div class="stat-value"><?= $totalAutomatizaciones ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(16,185,129,0.1);color:#10b981"><i class="bi bi-robot"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Activas</div>
                    <div class="stat-value"><?= count($activas) ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(34,197,94,0.1);color:#22c55e"><i class="bi bi-toggle-on"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Ejecuciones</div>
                    <div class="stat-value"><?= number_format($totalEjecuciones) ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(99,102,241,0.1);color:#6366f1"><i class="bi bi-play-circle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Acciones</div>
                    <div class="stat-value"><?= $totalAcciones ?></div>
                </div>
                <div class="stat-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b"><i class="bi bi-lightning"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Action bar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="text-muted"><?= $totalAutomatizaciones ?> automatizacion<?= $totalAutomatizaciones !== 1 ? 'es' : '' ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="diagnostico.php" class="btn btn-outline-secondary">
            <i class="bi bi-shield-check"></i> Diagnostico
        </a>
        <a href="workflows.php" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3"></i> Workflows Visuales
        </a>
        <a href="form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nueva Automatizacion
        </a>
    </div>
</div>

<?php if (empty($automatizaciones)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-robot fs-1 d-block mb-3 text-muted"></i>
        <h5>No hay automatizaciones creadas</h5>
        <p class="text-muted">Automatiza tareas repetitivas: envio de emails, asignacion de tareas, notificaciones y mas.</p>
        <div class="d-flex gap-2 justify-content-center mt-3">
            <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Crear Automatizacion</a>
            <a href="workflows.php" class="btn btn-outline-primary"><i class="bi bi-diagram-3"></i> Workflow Visual</a>
        </div>
    </div>
</div>
<?php else: ?>

<!-- Automations grid -->
<div class="row g-3">
    <?php foreach ($automatizaciones as $auto):
        $color = $triggerColors[$auto['trigger_tipo']] ?? '#64748b';
        $numAcciones = $accionesCount[$auto['id']] ?? 0;
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="auto-card">
            <div class="auto-status">
                <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="toggle">
                    <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-<?= $auto['activo'] ? 'success' : 'outline-secondary' ?>" style="min-width:80px">
                        <i class="bi bi-toggle-<?= $auto['activo'] ? 'on' : 'off' ?>"></i>
                        <?= $auto['activo'] ? 'Activa' : 'Inactiva' ?>
                    </button>
                </form>
            </div>
            <div class="auto-icon" style="background:<?= $color ?>20;color:<?= $color ?>">
                <i class="bi <?= $triggerIcons[$auto['trigger_tipo']] ?? 'bi-lightning' ?>"></i>
            </div>
            <h6 class="fw-bold mb-1" style="color:var(--text-primary)"><?= sanitize($auto['nombre']) ?></h6>
            <?php if (!empty($auto['descripcion'])): ?>
                <small class="text-muted d-block mb-2"><?= sanitize(mb_strimwidth($auto['descripcion'], 0, 100, '...')) ?></small>
            <?php endif; ?>
            <span class="auto-trigger">
                <i class="bi <?= $triggerIcons[$auto['trigger_tipo']] ?? 'bi-lightning' ?> me-1"></i>
                <?= $triggerLabels[$auto['trigger_tipo']] ?? ucfirst($auto['trigger_tipo']) ?>
            </span>
            <div class="auto-stats">
                <div><strong><?= $numAcciones ?></strong> acciones</div>
                <div><strong><?= intval($auto['ejecuciones']) ?></strong> ejecuciones</div>
                <div><?= $auto['ultima_ejecucion'] ? formatFecha($auto['ultima_ejecucion']) : '<span class="text-muted">Nunca</span>' ?></div>
            </div>
            <div class="d-flex gap-1 mt-3">
                <a href="form.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="bi bi-pencil"></i> Editar</a>
                <a href="log.php?id=<?= $auto['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-check"></i></a>
                <?php if ($auto['trigger_tipo'] === 'manual'): ?>
                <form method="POST" action="ejecutar.php" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                    <button class="btn btn-sm btn-outline-success" title="Ejecutar"><i class="bi bi-play-fill"></i></button>
                </form>
                <?php endif; ?>
                <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="duplicar">
                    <input type="hidden" name="id" value="<?= $auto['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" title="Duplicar"><i class="bi bi-copy"></i></button>
                </form>
                <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar esta automatizacion?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= intval($auto['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Quick templates section -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-lightning"></i> Plantillas rapidas</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex align-items-start p-3 rounded border" style="background:var(--bg-page)">
                    <div class="me-3"><i class="bi bi-person-plus fs-4 text-success"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1" style="font-size:0.9rem">Bienvenida nuevo cliente</h6>
                        <p class="text-muted small mb-0">Envia un email de bienvenida y asigna una tarea de seguimiento al crear un cliente.</p>
                        <a href="form.php?template=bienvenida_cliente" class="btn btn-sm btn-outline-primary mt-2">Usar plantilla</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start p-3 rounded border" style="background:var(--bg-page)">
                    <div class="me-3"><i class="bi bi-calendar-check fs-4 text-primary"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1" style="font-size:0.9rem">Post-visita</h6>
                        <p class="text-muted small mb-0">Envia encuesta de satisfaccion despues de cada visita realizada.</p>
                        <a href="form.php?template=post_visita" class="btn btn-sm btn-outline-primary mt-2">Usar plantilla</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex align-items-start p-3 rounded border" style="background:var(--bg-page)">
                    <div class="me-3"><i class="bi bi-exclamation-triangle fs-4 text-danger"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1" style="font-size:0.9rem">Tarea vencida</h6>
                        <p class="text-muted small mb-0">Notifica al agente y al administrador cuando una tarea supera la fecha limite.</p>
                        <a href="form.php?template=tarea_vencida" class="btn btn-sm btn-outline-primary mt-2">Usar plantilla</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
