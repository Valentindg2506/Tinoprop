<?php
$pageTitle = 'Diagnostico de Automatizaciones';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/automatizaciones_engine.php';

$db = getDB();
$isAdm = isAdmin();
$userId = intval(currentUserId());

$checks = [];

function diagAddCheck(array &$checks, string $key, string $label, string $status, string $detail): void {
    $checks[] = [
        'key' => $key,
        'label' => $label,
        'status' => $status,
        'detail' => $detail,
    ];
}

function diagStatusMeta(string $status): array {
    if ($status === 'ok') {
        return ['class' => 'success', 'icon' => 'bi-check-circle-fill', 'text' => 'OK'];
    }
    if ($status === 'warning') {
        return ['class' => 'warning', 'icon' => 'bi-exclamation-triangle-fill', 'text' => 'Advertencia'];
    }
    return ['class' => 'danger', 'icon' => 'bi-x-octagon-fill', 'text' => 'Error'];
}

$requiredTables = ['automatizaciones', 'automatizacion_acciones', 'automatizacion_log'];
$tableOk = true;
$missingTables = [];
try {
    $stmtExisting = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()");
    $existingTables = array_map(static function ($r) {
        return (string)$r['table_name'];
    }, $stmtExisting->fetchAll(PDO::FETCH_ASSOC));

    $missingTables = array_values(array_diff($requiredTables, $existingTables));
    $tableOk = empty($missingTables);
} catch (Throwable $e) {
    // Fallback defensivo por si information_schema esta restringido.
    $missingTables = [];
    foreach ($requiredTables as $tbl) {
        try {
            $db->query("SELECT 1 FROM `" . $tbl . "` LIMIT 1");
        } catch (Throwable $inner) {
            $missingTables[] = $tbl;
        }
    }
    $tableOk = empty($missingTables);
}
if ($tableOk) {
    diagAddCheck($checks, 'tables', 'Tablas base', 'ok', 'Tablas requeridas presentes.');
} else {
    diagAddCheck($checks, 'tables', 'Tablas base', 'error', 'Faltan tablas: ' . implode(', ', $missingTables));
}

$engineOk = function_exists('automatizacionesEjecutarTrigger');
if ($engineOk) {
    diagAddCheck($checks, 'engine', 'Motor de automatizaciones', 'ok', 'Funcion automatizacionesEjecutarTrigger disponible.');
} else {
    diagAddCheck($checks, 'engine', 'Motor de automatizaciones', 'error', 'No se encontro el motor de ejecucion.');
}

$whereAuto = $isAdm ? '1=1' : 'a.created_by = ?';
$paramsAuto = $isAdm ? [] : [$userId];

$stmtStats = $db->prepare("SELECT COUNT(*) total, SUM(CASE WHEN a.activo=1 THEN 1 ELSE 0 END) activas, COALESCE(SUM(a.ejecuciones),0) ejecuciones FROM automatizaciones a WHERE $whereAuto");
$stmtStats->execute($paramsAuto);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'activas' => 0, 'ejecuciones' => 0];

if (intval($stats['total']) === 0) {
    diagAddCheck($checks, 'autos', 'Automatizaciones configuradas', 'warning', 'No hay automatizaciones creadas para este alcance.');
} elseif (intval($stats['activas']) === 0) {
    diagAddCheck($checks, 'autos', 'Automatizaciones configuradas', 'warning', 'Hay automatizaciones, pero ninguna esta activa.');
} else {
    diagAddCheck($checks, 'autos', 'Automatizaciones configuradas', 'ok', 'Total: ' . intval($stats['total']) . ', activas: ' . intval($stats['activas']) . '.');
}

$stmtNoAcc = $db->prepare("SELECT COUNT(*)
    FROM automatizaciones a
    LEFT JOIN automatizacion_acciones ac ON ac.automatizacion_id = a.id
    WHERE $whereAuto AND a.activo = 1
    GROUP BY a.id
    HAVING COUNT(ac.id) = 0");
$activeNoActions = 0;
try {
    $stmtNoAcc->execute($paramsAuto);
    $rowsNoAcc = $stmtNoAcc->fetchAll(PDO::FETCH_NUM);
    $activeNoActions = count($rowsNoAcc);
} catch (Throwable $e) {
    $activeNoActions = 0;
}
if ($activeNoActions > 0) {
    diagAddCheck($checks, 'no_actions', 'Activas sin acciones', 'warning', 'Hay ' . $activeNoActions . ' automatizacion(es) activa(s) sin acciones.');
} else {
    diagAddCheck($checks, 'no_actions', 'Activas sin acciones', 'ok', 'Todas las automatizaciones activas tienen acciones.');
}

$allowedActions = ['enviar_email','enviar_whatsapp','crear_tarea','cambiar_estado_propiedad','asignar_agente','mover_pipeline','notificar','esperar'];
$phActions = implode(',', array_fill(0, count($allowedActions), '?'));
$paramsUnsupported = $allowedActions;
$sqlUnsupported = "SELECT DISTINCT ac.tipo
    FROM automatizacion_acciones ac
    INNER JOIN automatizaciones a ON a.id = ac.automatizacion_id
    WHERE ac.tipo NOT IN ($phActions)";
if (!$isAdm) {
    $sqlUnsupported .= ' AND a.created_by = ?';
    $paramsUnsupported[] = $userId;
}
$stmtUnsupported = $db->prepare($sqlUnsupported);
$stmtUnsupported->execute($paramsUnsupported);
$unsupported = array_map(static function ($r) { return $r['tipo']; }, $stmtUnsupported->fetchAll(PDO::FETCH_ASSOC));

if (!empty($unsupported)) {
    diagAddCheck($checks, 'unsupported_actions', 'Acciones no soportadas', 'error', 'Se detectaron tipos no soportados: ' . implode(', ', $unsupported));
} else {
    diagAddCheck($checks, 'unsupported_actions', 'Acciones no soportadas', 'ok', 'No se detectaron tipos de accion invalidos.');
}

$whereLogs = $isAdm ? '1=1' : 'a.created_by = ?';
$paramsLogs = $isAdm ? [] : [$userId];
$stmtErr24 = $db->prepare("SELECT COUNT(*)
    FROM automatizacion_log l
    INNER JOIN automatizaciones a ON a.id = l.automatizacion_id
    WHERE $whereLogs AND l.created_at >= (NOW() - INTERVAL 24 HOUR) AND l.estado = 'error'");
$stmtErr24->execute($paramsLogs);
$errors24 = intval($stmtErr24->fetchColumn());

$stmtEx24 = $db->prepare("SELECT COUNT(*)
    FROM automatizacion_log l
    INNER JOIN automatizaciones a ON a.id = l.automatizacion_id
    WHERE $whereLogs AND l.created_at >= (NOW() - INTERVAL 24 HOUR) AND l.estado = 'exito'");
$stmtEx24->execute($paramsLogs);
$success24 = intval($stmtEx24->fetchColumn());

if ($errors24 > 0) {
    diagAddCheck($checks, 'errors_24h', 'Errores ultimas 24h', 'warning', 'Errores: ' . $errors24 . ' / Exitos: ' . $success24 . '. Revisar logs.');
} elseif ($success24 > 0) {
    diagAddCheck($checks, 'errors_24h', 'Actividad ultimas 24h', 'ok', 'Exitos: ' . $success24 . '. Sin errores en las ultimas 24h.');
} else {
    diagAddCheck($checks, 'errors_24h', 'Actividad ultimas 24h', 'warning', 'Sin ejecuciones registradas en las ultimas 24h.');
}

$cronPath = __DIR__ . '/../../cron_automatizaciones.php';
if (is_file($cronPath)) {
    diagAddCheck($checks, 'cron_file', 'Archivo cron', 'ok', 'Existe cron_automatizaciones.php. Falta confirmar programacion en sistema (crontab).');
} else {
    diagAddCheck($checks, 'cron_file', 'Archivo cron', 'error', 'No existe cron_automatizaciones.php.');
}

$stmtByTrigger = $db->prepare("SELECT a.trigger_tipo, COUNT(*) total, SUM(CASE WHEN a.activo=1 THEN 1 ELSE 0 END) activas, COALESCE(SUM(a.ejecuciones),0) ejecuciones
    FROM automatizaciones a
    WHERE $whereAuto
    GROUP BY a.trigger_tipo
    ORDER BY total DESC, a.trigger_tipo ASC");
$stmtByTrigger->execute($paramsAuto);
$rowsTrigger = $stmtByTrigger->fetchAll(PDO::FETCH_ASSOC);

$stmtRecentErr = $db->prepare("SELECT l.created_at, a.nombre, l.detalles
    FROM automatizacion_log l
    INNER JOIN automatizaciones a ON a.id = l.automatizacion_id
    WHERE $whereLogs AND l.estado = 'error'
    ORDER BY l.created_at DESC
    LIMIT 10");
$stmtRecentErr->execute($paramsLogs);
$recentErrors = $stmtRecentErr->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    <div class="text-muted small">
        Alcance: <?= $isAdm ? 'Administrador (global)' : 'Usuario actual' ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Automatizaciones</div>
                <div class="fs-3 fw-bold"><?= intval($stats['total']) ?></div>
                <small class="text-muted">Activas: <?= intval($stats['activas']) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Ejecuciones acumuladas</div>
                <div class="fs-3 fw-bold"><?= intval($stats['ejecuciones']) ?></div>
                <small class="text-muted">En alcance actual</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Errores (24h)</div>
                <div class="fs-3 fw-bold text-<?= $errors24 > 0 ? 'danger' : 'success' ?>"><?= $errors24 ?></div>
                <small class="text-muted">Exitos (24h): <?= $success24 ?></small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h6 class="mb-0"><i class="bi bi-shield-check"></i> Estado General</h6>
    </div>
    <div class="card-body">
        <div class="list-group list-group-flush">
            <?php foreach ($checks as $c): ?>
                <?php $meta = diagStatusMeta($c['status']); ?>
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold"><?= sanitize($c['label']) ?></div>
                        <small class="text-muted"><?= sanitize($c['detail']) ?></small>
                    </div>
                    <span class="badge text-bg-<?= $meta['class'] ?>"><i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['text'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-lightning"></i> Cobertura por Trigger</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($rowsTrigger)): ?>
                    <div class="p-4 text-muted text-center">Sin automatizaciones configuradas.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Trigger</th>
                                    <th>Total</th>
                                    <th>Activas</th>
                                    <th>Ejecuciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rowsTrigger as $r): ?>
                                    <tr>
                                        <td><?= sanitize($r['trigger_tipo']) ?></td>
                                        <td><?= intval($r['total']) ?></td>
                                        <td><?= intval($r['activas']) ?></td>
                                        <td><?= intval($r['ejecuciones']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-bug"></i> Ultimos Errores</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentErrors)): ?>
                    <div class="text-muted">Sin errores recientes.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentErrors as $e): ?>
                            <div class="list-group-item px-0">
                                <div class="fw-semibold"><?= sanitize($e['nombre']) ?></div>
                                <small class="text-muted d-block"><?= sanitize(formatFechaHora($e['created_at'])) ?></small>
                                <small class="text-danger d-block mt-1"><?= sanitize($e['detalles']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="mb-3"><i class="bi bi-terminal"></i> Recomendacion Cron</h6>
        <div class="bg-light rounded p-3 small mb-3">
            <code>*/5 * * * * php /var/www/html/CRM/cron_automatizaciones.php &gt;&gt; /var/www/html/CRM/logs/cron_automatizaciones.log 2&gt;&amp;1</code>
        </div>
        <div class="text-muted small">Si usas panel hosting (cPanel/Plesk), crea una tarea programada equivalente cada 5 minutos.</div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
