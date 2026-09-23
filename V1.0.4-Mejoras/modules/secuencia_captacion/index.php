<?php
/**
 * Dashboard — Secuencia de Captación WhatsApp
 * Ubicación: /modules/secuencia_captacion/index.php
 *
 * Módulo integrado con el layout estándar del CRM:
 *   - require auth vía includes/header.php
 *   - usa getDB() de config/database.php
 *   - renderiza con el design system del CRM (sidebar, topbar, dark mode)
 */

$pageTitle = 'Secuencia Captación';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// ----------------------------------------------------------------
// Acciones POST
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_modo') {
        $modo = $_POST['modo'] === 'real' ? 'real' : 'simulacion';
        $db->prepare("UPDATE whatsapp_config SET modo = :m WHERE id = (SELECT id FROM (SELECT id FROM whatsapp_config ORDER BY id DESC LIMIT 1) t)")
           ->execute([':m' => $modo]);
        setFlash('success', "Modo actualizado a: <strong>$modo</strong>");
    }

    if ($accion === 'editar_plantilla') {
        $id      = (int)($_POST['id'] ?? 0);
        $titulo  = trim($_POST['titulo'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $dias    = (int)($_POST['dias_espera'] ?? 0);
        $activo  = isset($_POST['activo']) ? 1 : 0;
        if ($id > 0 && $titulo && $mensaje) {
            $db->prepare("UPDATE secuencia_captacion_plantillas
                          SET titulo=:t, mensaje=:m, dias_espera=:d, activo=:a
                          WHERE id=:id")
               ->execute([':t'=>$titulo, ':m'=>$mensaje, ':d'=>$dias, ':a'=>$activo, ':id'=>$id]);
            setFlash('success', "Plantilla #$id actualizada correctamente.");
        }
    }

    if ($accion === 'pausar_tracking') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE secuencia_captacion_tracking SET estado='pausado' WHERE id=:id")
               ->execute([':id'=>$id]);
            setFlash('success', "Tracking #$id pausado.");
        }
    }

    if ($accion === 'reactivar_tracking') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE secuencia_captacion_tracking
                          SET estado='activo', programado_para = NOW()
                          WHERE id=:id")->execute([':id'=>$id]);
            setFlash('success', "Tracking #$id reactivado.");
        }
    }

    if ($accion === 'ejecutar_agente') {
        // Ejecuta el cron en modo sincrónico (limit bajo para evitar timeout)
        $output = [];
        $cmd = escapeshellcmd(PHP_BINARY) . ' ' .
               escapeshellarg(__DIR__ . '/../../cron/secuencia_captacion.php') .
               ' --limit=20 2>&1';
        @exec($cmd, $output);
        setFlash('success', "Agente ejecutado. " . count($output) . " líneas de log.");
    }

    header('Location: ' . APP_URL . '/modules/secuencia_captacion/index.php');
    exit;
}

// ----------------------------------------------------------------
// Datos para el dashboard
// ----------------------------------------------------------------
$config = $db->query("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$modoActual = $config['modo'] ?? 'simulacion';

$kpis = [
    'en_secuencia' => (int)$db->query("SELECT COUNT(*) FROM secuencia_captacion_tracking WHERE estado='activo'")->fetchColumn(),
    'completados'  => (int)$db->query("SELECT COUNT(*) FROM secuencia_captacion_tracking WHERE estado='completado'")->fetchColumn(),
    'descartados'  => (int)$db->query("SELECT COUNT(*) FROM secuencia_captacion_tracking WHERE estado='descartado'")->fetchColumn(),
    'enviados_hoy' => (int)$db->query("SELECT COUNT(*) FROM whatsapp_mensajes
                                        WHERE direccion='saliente'
                                          AND DATE(created_at) = CURDATE()
                                          AND prospecto_id IS NOT NULL")->fetchColumn(),
    'pendientes'   => (int)$db->query("SELECT COUNT(*) FROM secuencia_captacion_tracking
                                        WHERE estado='activo'
                                          AND (programado_para IS NULL OR programado_para <= NOW())")->fetchColumn(),
];

$plantillas = $db->query("SELECT * FROM secuencia_captacion_plantillas ORDER BY orden ASC")
                 ->fetchAll(PDO::FETCH_ASSOC);

$filtroEstado = $_GET['estado'] ?? 'activo';
$estadosValidos = ['activo','pausado','completado','descartado','respondido','todos'];
if (!in_array($filtroEstado, $estadosValidos, true)) $filtroEstado = 'activo';

$wheres = [];
$params = [];
if ($filtroEstado !== 'todos') {
    $wheres[] = "t.estado = :estado";
    $params[':estado'] = $filtroEstado;
}
$whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

$sqlTracking = "
    SELECT t.*, p.nombre AS prospecto_nombre, p.telefono, p.barrio, p.localidad, p.etapa,
           pl.orden AS ultimo_orden, pl.titulo AS ultima_plantilla
    FROM secuencia_captacion_tracking t
    JOIN prospectos p ON p.id = t.prospecto_id
    LEFT JOIN secuencia_captacion_plantillas pl ON pl.id = t.plantilla_id
    $whereSql
    ORDER BY t.programado_para ASC, t.id DESC
    LIMIT 100
";
$stmt = $db->prepare($sqlTracking);
foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
$stmt->execute();
$trackings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estado color mapping
$estadoColors = [
    'activo'      => '#10b981',
    'pausado'     => '#f59e0b',
    'completado'  => '#06b6d4',
    'descartado'  => '#64748b',
    'respondido'  => '#3b82f6',
];
?>

<style>
/* Secuencia Captación — Estilos específicos del módulo */
.seq-modo-badge {
    font-size: .85rem;
    padding: .45rem .85rem;
    border-radius: 20px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.seq-modo-real { background: rgba(239,68,68,0.12); color: #ef4444; }
.seq-modo-sim  { background: rgba(245,158,11,0.12); color: #f59e0b; }

.seq-plantilla-card {
    border-left: 4px solid var(--primary);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.seq-plantilla-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}
.seq-plantilla-card.inactive {
    border-left-color: var(--text-muted);
    opacity: .6;
}

.seq-msg-preview {
    white-space: pre-wrap;
    background: var(--bg-page);
    padding: .75rem;
    border-radius: var(--radius-sm);
    font-size: .83rem;
    max-height: 180px;
    overflow: auto;
    border: 1px solid var(--border);
    line-height: 1.5;
    font-family: 'Inter', sans-serif;
    color: var(--text-secondary);
}

.seq-badge-estado {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.73rem;
    font-weight: 600;
    letter-spacing: 0.2px;
}

.seq-paso-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 52px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    background: rgba(16,185,129,0.1);
    color: var(--primary);
}

[data-bs-theme="dark"] .seq-msg-preview {
    background: #0f172a;
    border-color: var(--border);
}
</style>

<!-- Header & Mode -->
<div class="d-flex justify-content-between align-items-center flex-column flex-md-row mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <h4 class="mb-0" style="font-weight:700; color: var(--text-primary); letter-spacing: -0.3px;">
                <i class="bi bi-whatsapp" style="color: #25D366;"></i> Secuencia de Captación
            </h4>
            <span class="seq-modo-badge <?= $modoActual === 'real' ? 'seq-modo-real' : 'seq-modo-sim' ?>">
                <i class="bi bi-<?= $modoActual === 'real' ? 'broadcast-pin' : 'shield-check' ?>"></i>
                <?= strtoupper($modoActual) ?>
            </span>
        </div>
        <p class="text-muted mb-0" style="font-size:.88rem;">Agente de nurturing automático por WhatsApp para propietarios</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" class="d-flex align-items-center gap-2">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="cambiar_modo">
            <select name="modo" class="form-select form-select-sm" style="width:140px;">
                <option value="simulacion" <?= $modoActual==='simulacion'?'selected':'' ?>>Simulación</option>
                <option value="real"       <?= $modoActual==='real'?'selected':'' ?>>Real (Twilio)</option>
            </select>
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Cambiar</button>
        </form>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="ejecutar_agente">
            <button class="btn btn-sm btn-primary">
                <i class="bi bi-play-circle"></i> Ejecutar agente
            </button>
        </form>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <?php
    $kpiData = [
        ['label' => 'En Secuencia',  'value' => $kpis['en_secuencia'], 'color' => '#10b981', 'icon' => 'bi-arrow-repeat'],
        ['label' => 'Enviados Hoy',  'value' => $kpis['enviados_hoy'], 'color' => '#3b82f6', 'icon' => 'bi-send-check'],
        ['label' => 'Pendientes',    'value' => $kpis['pendientes'],   'color' => '#f59e0b', 'icon' => 'bi-clock-history'],
        ['label' => 'Completados',   'value' => $kpis['completados'],  'color' => '#06b6d4', 'icon' => 'bi-check-circle'],
        ['label' => 'Descartados',   'value' => $kpis['descartados'],  'color' => '#64748b', 'icon' => 'bi-x-circle'],
    ];
    foreach ($kpiData as $kpi):
    ?>
    <div class="col-6 col-md">
        <div class="stat-card" style="border-left: 3px solid <?= $kpi['color'] ?>;">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: <?= $kpi['color'] ?>15; color: <?= $kpi['color'] ?>;">
                    <i class="bi <?= $kpi['icon'] ?>"></i>
                </div>
                <div>
                    <div class="stat-value" style="font-size:1.5rem;"><?= $kpi['value'] ?></div>
                    <div class="stat-label"><?= $kpi['label'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tab-tracking">
            <i class="bi bi-people me-1"></i> Tracking de prospectos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-plantillas">
            <i class="bi bi-chat-square-text me-1"></i> Plantillas (<?= count($plantillas) ?> mensajes)
        </a>
    </li>
</ul>

<div class="tab-content">

    <!-- TRACKING -->
    <div class="tab-pane fade show active" id="tab-tracking">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <i class="bi bi-list-check"></i>
                    <strong>Prospectos en seguimiento</strong>
                    <span class="badge bg-secondary ms-2"><?= count($trackings) ?></span>
                </div>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label class="mb-0 small text-muted">Filtrar:</label>
                    <select name="estado" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <?php foreach ($estadosValidos as $est): ?>
                            <option value="<?= $est ?>" <?= $filtroEstado===$est?'selected':'' ?>>
                                <?= ucfirst($est) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Prospecto</th>
                                <th>Teléfono</th>
                                <th>Zona</th>
                                <th>Etapa</th>
                                <th class="text-center">Paso</th>
                                <th>Último mensaje</th>
                                <th>Próximo envío</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trackings)): ?>
                                <tr><td colspan="10" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    Sin prospectos en estado "<?= htmlspecialchars($filtroEstado) ?>".
                                </td></tr>
                            <?php endif; ?>
                            <?php foreach ($trackings as $t): ?>
                                <tr>
                                    <td class="text-muted">#<?= $t['id'] ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/modules/prospectos/ver.php?id=<?= (int)$t['prospecto_id'] ?>" class="text-decoration-none">
                                            <strong><?= htmlspecialchars($t['prospecto_nombre']) ?></strong>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($t['telefono']) ?></td>
                                    <td><small><?= htmlspecialchars(($t['barrio'] ?: '—') . ', ' . ($t['localidad'] ?: '—')) ?></small></td>
                                    <td>
                                        <?php
                                        $etapaColors = [
                                            'nuevo_lead' => '#06b6d4',
                                            'contactado' => '#64748b',
                                            'en_seguimiento' => '#3b82f6',
                                            'seguimiento' => '#3b82f6',
                                            'visita_programada' => '#8b5cf6',
                                            'captado' => '#10b981',
                                            'descartado' => '#ef4444',
                                        ];
                                        $eColor = $etapaColors[$t['etapa']] ?? '#64748b';
                                        ?>
                                        <span class="badge-estado" style="background: <?= $eColor ?>20; color: <?= $eColor ?>;">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $t['etapa']))) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="seq-paso-badge"><?= (int)$t['paso_actual'] ?> / <?= count($plantillas) ?></span>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($t['ultima_plantilla'] ?: '—') ?></small></td>
                                    <td>
                                        <?php if ($t['programado_para']): ?>
                                            <small><?= formatFechaHora($t['programado_para']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $eColor = $estadoColors[$t['estado']] ?? '#64748b'; ?>
                                        <span class="seq-badge-estado" style="background: <?= $eColor ?>20; color: <?= $eColor ?>;">
                                            <?= ucfirst($t['estado']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                            <?php if ($t['estado'] === 'activo'): ?>
                                                <input type="hidden" name="accion" value="pausar_tracking">
                                                <button class="btn btn-sm btn-outline-warning" title="Pausar"><i class="bi bi-pause-fill"></i></button>
                                            <?php elseif ($t['estado'] === 'pausado'): ?>
                                                <input type="hidden" name="accion" value="reactivar_tracking">
                                                <button class="btn btn-sm btn-outline-success" title="Reactivar"><i class="bi bi-play-fill"></i></button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- PLANTILLAS -->
    <div class="tab-pane fade" id="tab-plantillas">
        <div class="row g-3">
            <?php foreach ($plantillas as $p): ?>
                <div class="col-md-6">
                    <div class="card seq-plantilla-card <?= $p['activo'] ? '' : 'inactive' ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary" style="min-width:28px;"><?= $p['orden'] ?></span>
                                <strong><?= htmlspecialchars($p['titulo']) ?></strong>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <small class="text-muted"><i class="bi bi-clock"></i> Día +<?= (int)$p['dias_espera'] ?></small>
                                <?php if (!$p['activo']): ?>
                                    <span class="seq-badge-estado" style="background:rgba(100,116,139,0.12); color:#64748b;">Inactiva</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <pre class="seq-msg-preview"><?= htmlspecialchars($p['mensaje']) ?></pre>
                            <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal"
                                    data-bs-target="#editModal<?= $p['id'] ?>">
                                <i class="bi bi-pencil"></i> Editar
                            </button>
                        </div>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="editModal<?= $p['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <form method="post" class="modal-content">
                                <?= csrfField() ?>
                                <input type="hidden" name="accion" value="editar_plantilla">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2" style="color:var(--primary);"></i>Editar plantilla #<?= $p['orden'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Título</label>
                                        <input type="text" name="titulo" class="form-control"
                                               value="<?= htmlspecialchars($p['titulo']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mensaje</label>
                                        <textarea name="mensaje" class="form-control" rows="10" required><?= htmlspecialchars($p['mensaje']) ?></textarea>
                                        <small class="text-muted">
                                            Variables disponibles:
                                            <code>{nombre}</code> <code>{nombre_completo}</code>
                                            <code>{barrio}</code> <code>{localidad}</code>
                                            <code>{direccion}</code> <code>{agente_nombre}</code>
                                        </small>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Días de espera</label>
                                            <input type="number" name="dias_espera" class="form-control"
                                                   value="<?= (int)$p['dias_espera'] ?>" min="0">
                                        </div>
                                        <div class="col-md-6 mb-3 d-flex align-items-end">
                                            <div class="form-check">
                                                <input type="checkbox" name="activo" class="form-check-input"
                                                       id="act<?= $p['id'] ?>" <?= $p['activo']?'checked':'' ?>>
                                                <label class="form-check-label" for="act<?= $p['id'] ?>">Plantilla activa</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Footer informativo -->
<div class="card mt-4">
    <div class="card-body" style="font-size:.85rem; color: var(--text-muted);">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-info-circle fs-5" style="color: var(--primary); flex-shrink:0; margin-top:2px;"></i>
            <div>
                <strong style="color: var(--text-secondary);">Cómo funciona el agente:</strong>
                cada vez que corre, primero inscribe prospectos nuevos (etapa <code>nuevo_lead</code> / <code>contactado</code> / <code>seguimiento</code>, con teléfono y activos),
                luego procesa los envíos pendientes según los días de espera de cada plantilla.
                En modo <strong>simulación</strong>, cada mensaje queda registrado en <code>whatsapp_mensajes</code> con estado <code>simulado</code>,
                sin enviar nada real. Cuando conectemos Twilio, pasamos a modo <strong>real</strong>.
                <br>
                <strong style="color: var(--text-secondary);">Cron recomendado:</strong>
                <code>*/10 * * * * php <?= realpath(__DIR__ . '/../../cron/secuencia_captacion.php') ?: '/ruta/absoluta/cron/secuencia_captacion.php' ?> >> logs/secuencia.log 2>&1</code>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>