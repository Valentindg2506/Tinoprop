<?php
$pageTitle = 'Calendario';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Mes y anio actual
$mes = intval(get('mes', date('n')));
$anio = intval(get('anio', date('Y')));

if ($mes < 1 || $mes > 12) $mes = date('n');
if ($anio < 2020 || $anio > 2040) $anio = date('Y');

// Navegacion
$mesPrev = $mes - 1; $anioPrev = $anio;
if ($mesPrev < 1) { $mesPrev = 12; $anioPrev--; }
$mesNext = $mes + 1; $anioNext = $anio;
if ($mesNext > 12) { $mesNext = 1; $anioNext++; }

$primerDia      = mktime(0, 0, 0, $mes, 1, $anio);
$diasEnMes      = (int)date('t', $primerDia);
$diaSemanaInicio = (int)date('N', $primerDia); // 1=lun, 7=dom
$nombreMes = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
][$mes];

$mesStr  = str_pad($mes, 2, '0', STR_PAD_LEFT);
$fechaMesInicio = "$anio-$mesStr-01";
$fechaMesFin    = "$anio-$mesStr-$diasEnMes";

$uid = currentUserId();
$esAdmin = isAdmin();

// ─────────────────────────────────────────────────────
// Recolectar todos los eventos del mes en $eventos[]
// Cada evento DEBE tener: titulo, tipo, color,
//   fecha_inicio (Y-m-d H:i:s), fecha_fin, link
// ─────────────────────────────────────────────────────
$eventos = [];

// 1. Eventos de calendario propios
try {
    $stmt = $db->prepare("
        SELECT ce.id, ce.titulo, ce.tipo,
               COALESCE(ce.color,'#3b82f6') as color,
               ce.todo_dia, ce.fecha_inicio, ce.fecha_fin,
               c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
               p.titulo as propiedad_titulo
        FROM calendario_eventos ce
        LEFT JOIN clientes c ON ce.cliente_id = c.id
        LEFT JOIN propiedades p ON ce.propiedad_id = p.id
        WHERE ce.usuario_id = ?
          AND ce.fecha_inicio <= ? AND ce.fecha_fin >= ?
        ORDER BY ce.fecha_inicio ASC
    ");
    $stmt->execute([$uid, "$fechaMesFin 23:59:59", "$fechaMesInicio 00:00:00"]);
    foreach ($stmt->fetchAll() as $r) {
        $r['link'] = 'form.php?id=' . $r['id'];
        $r['fuente'] = 'calendario';
        $eventos[] = $r;
    }
} catch (Exception $e) { /* continúa sin este bloque */ }

// 2. Visitas del mes
try {
    $stmt = $db->prepare("
        SELECT v.id, v.fecha, v.hora,
               CONCAT('Visita: ', COALESCE(c.nombre,'Sin cliente'),
                      IF(p.referencia IS NOT NULL, CONCAT(' — ', p.referencia), '')) as titulo,
               'visita' as tipo, '#10b981' as color, 0 as todo_dia,
               c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
               p.titulo as propiedad_titulo
        FROM visitas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN propiedades p ON v.propiedad_id = p.id
        WHERE v.agente_id = ? AND v.fecha >= ? AND v.fecha <= ?
        ORDER BY v.fecha ASC, v.hora ASC
    ");
    $stmt->execute([$uid, $fechaMesInicio, $fechaMesFin]);
    foreach ($stmt->fetchAll() as $r) {
        $horaStr  = !empty($r['hora']) ? $r['hora'] : '09:00:00';
        $horaFin  = date('H:i:s', strtotime($horaStr . ' +1 hour'));
        $r['fecha_inicio'] = $r['fecha'] . ' ' . $horaStr;
        $r['fecha_fin']    = $r['fecha'] . ' ' . $horaFin;
        $r['todo_dia']     = 0;
        $r['link']         = '../visitas/form.php?id=' . $r['id'];
        $r['fuente']       = 'visita';
        $eventos[] = $r;
    }
} catch (Exception $e) { /* continúa */ }

// 3. Tareas con vencimiento en el mes
try {
    $stmt = $db->prepare("
        SELECT t.id, t.titulo as titulo_raw, t.prioridad, t.estado as tarea_estado,
               t.fecha_vencimiento,
               CASE t.prioridad
                   WHEN 'urgente' THEN '#ef4444'
                   WHEN 'alta'    THEN '#f59e0b'
                   ELSE '#8b5cf6'
               END as color,
               c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
               p.titulo as propiedad_titulo
        FROM tareas t
        LEFT JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN propiedades p ON t.propiedad_id = p.id
        WHERE (t.asignado_a = ? OR t.creado_por = ?)
          AND t.estado IN ('pendiente','en_progreso')
          AND DATE(t.fecha_vencimiento) >= ? AND DATE(t.fecha_vencimiento) <= ?
        ORDER BY t.fecha_vencimiento ASC
    ");
    $stmt->execute([$uid, $uid, $fechaMesInicio, $fechaMesFin]);
    foreach ($stmt->fetchAll() as $r) {
        $fv = $r['fecha_vencimiento'];
        $r['titulo']       = 'Tarea: ' . $r['titulo_raw'];
        $r['tipo']         = 'tarea';
        $r['todo_dia']     = 0;
        $r['fecha_inicio'] = $fv;
        $r['fecha_fin']    = date('Y-m-d H:i:s', strtotime($fv . ' +1 hour'));
        $r['link']         = '../tareas/form.php?id=' . $r['id'];
        $r['fuente']       = 'tarea';
        $eventos[] = $r;
    }
} catch (Exception $e) { /* continúa */ }

// 4. Prospectos: próximo contacto en el mes
try {
    // Admins ven todos; agentes solo los suyos
    $whereAgente = $esAdmin ? '' : 'AND p.agente_id = ?';
    $params = $esAdmin
        ? [$fechaMesInicio, $fechaMesFin]
        : [$fechaMesInicio, $fechaMesFin, $uid];
    $stmt = $db->prepare("
        SELECT p.id, p.nombre, p.fecha_proximo_contacto as fecha,
               u.nombre as agente_nombre
        FROM prospectos p
        LEFT JOIN usuarios u ON p.agente_id = u.id
        WHERE p.activo = 1
          AND p.etapa NOT IN ('captado','descartado')
          AND p.fecha_proximo_contacto >= ?
          AND p.fecha_proximo_contacto <= ?
          $whereAgente
        ORDER BY p.fecha_proximo_contacto ASC
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $label = $esAdmin && !empty($r['agente_nombre'])
            ? 'Contactar: ' . $r['nombre'] . ' (' . $r['agente_nombre'] . ')'
            : 'Contactar: ' . $r['nombre'];
        $eventos[] = [
            'id'              => $r['id'],
            'titulo'          => $label,
            'tipo'            => 'llamada',
            'color'           => '#f59e0b',
            'todo_dia'        => 0,
            'fecha_inicio'    => $r['fecha'] . ' 09:00:00',
            'fecha_fin'       => $r['fecha'] . ' 09:30:00',
            'cliente_nombre'  => null,
            'cliente_apellidos'=> null,
            'propiedad_titulo'=> null,
            'link'            => '../prospectos/ver.php?id=' . $r['id'],
            'fuente'          => 'prospecto',
        ];
    }
} catch (Exception $e) { /* continúa */ }

// ─────────────────────────────────────────────────────
// Organizar por día
// ─────────────────────────────────────────────────────
$eventosPorDia = [];
foreach ($eventos as $evento) {
    $tsIni = strtotime($evento['fecha_inicio']);
    $tsFin = strtotime($evento['fecha_fin']);
    if (!$tsIni) continue;
    $dIni = max(1, (int)date('j', $tsIni));
    $dFin = min($diasEnMes, (int)date('j', $tsFin ?: $tsIni));
    if ((int)date('n', $tsIni) != $mes) $dIni = 1;
    if ($tsFin && (int)date('n', $tsFin) != $mes) $dFin = $diasEnMes;
    for ($d = $dIni; $d <= $dFin; $d++) {
        $eventosPorDia[$d][] = $evento;
    }
}

// ─────────────────────────────────────────────────────
// Panel lateral: eventos de HOY (todos los tipos)
// ─────────────────────────────────────────────────────
$hoy = date('Y-m-d');
$eventosHoy = [];

try {
    $stmt = $db->prepare("
        SELECT ce.id, ce.titulo, ce.tipo, COALESCE(ce.color,'#3b82f6') as color,
               ce.todo_dia, ce.fecha_inicio, ce.fecha_fin,
               c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
        FROM calendario_eventos ce
        LEFT JOIN clientes c ON ce.cliente_id = c.id
        WHERE ce.usuario_id = ? AND DATE(ce.fecha_inicio) = ?
        ORDER BY ce.fecha_inicio ASC
    ");
    $stmt->execute([$uid, $hoy]);
    foreach ($stmt->fetchAll() as $r) {
        $r['link'] = 'form.php?id=' . $r['id'];
        $eventosHoy[] = $r;
    }
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("
        SELECT v.id, v.hora,
               CONCAT('Visita: ', COALESCE(c.nombre,'Sin cliente'),
                      IF(p.referencia IS NOT NULL, CONCAT(' — ', p.referencia), '')) as titulo,
               c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
        FROM visitas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN propiedades p ON v.propiedad_id = p.id
        WHERE v.agente_id = ? AND v.fecha = ?
        ORDER BY v.hora ASC
    ");
    $stmt->execute([$uid, $hoy]);
    foreach ($stmt->fetchAll() as $r) {
        $horaStr = !empty($r['hora']) ? $r['hora'] : '09:00:00';
        $eventosHoy[] = [
            'id'              => $r['id'],
            'titulo'          => $r['titulo'],
            'tipo'            => 'visita',
            'color'           => '#10b981',
            'todo_dia'        => 0,
            'fecha_inicio'    => $hoy . ' ' . $horaStr,
            'fecha_fin'       => $hoy . ' ' . date('H:i:s', strtotime($horaStr . ' +1 hour')),
            'cliente_nombre'  => $r['cliente_nombre'],
            'cliente_apellidos'=> $r['cliente_apellidos'],
            'link'            => '../visitas/form.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("
        SELECT t.id, t.titulo as titulo_raw, t.fecha_vencimiento,
               CASE t.prioridad WHEN 'urgente' THEN '#ef4444' WHEN 'alta' THEN '#f59e0b' ELSE '#8b5cf6' END as color,
               c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
        FROM tareas t
        LEFT JOIN clientes c ON t.cliente_id = c.id
        WHERE (t.asignado_a = ? OR t.creado_por = ?)
          AND t.estado IN ('pendiente','en_progreso')
          AND DATE(t.fecha_vencimiento) = ?
        ORDER BY t.fecha_vencimiento ASC
    ");
    $stmt->execute([$uid, $uid, $hoy]);
    foreach ($stmt->fetchAll() as $r) {
        $eventosHoy[] = [
            'id'              => $r['id'],
            'titulo'          => 'Tarea: ' . $r['titulo_raw'],
            'tipo'            => 'tarea',
            'color'           => $r['color'],
            'todo_dia'        => 0,
            'fecha_inicio'    => $r['fecha_vencimiento'],
            'fecha_fin'       => date('Y-m-d H:i:s', strtotime($r['fecha_vencimiento'] . ' +1 hour')),
            'cliente_nombre'  => $r['cliente_nombre'],
            'cliente_apellidos'=> $r['cliente_apellidos'],
            'link'            => '../tareas/form.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

try {
    $whereAgenteHoy = $esAdmin ? '' : 'AND p.agente_id = ?';
    $paramsHoy = $esAdmin ? [$hoy] : [$hoy, $uid];
    $stmt = $db->prepare("
        SELECT p.id, p.nombre FROM prospectos p
        WHERE p.activo = 1 AND p.etapa NOT IN ('captado','descartado')
          AND p.fecha_proximo_contacto = ?
          $whereAgenteHoy
    ");
    $stmt->execute($paramsHoy);
    foreach ($stmt->fetchAll() as $r) {
        $eventosHoy[] = [
            'id'              => $r['id'],
            'titulo'          => 'Contactar: ' . $r['nombre'],
            'tipo'            => 'llamada',
            'color'           => '#f59e0b',
            'todo_dia'        => 0,
            'fecha_inicio'    => $hoy . ' 09:00:00',
            'fecha_fin'       => $hoy . ' 09:30:00',
            'cliente_nombre'  => null,
            'cliente_apellidos'=> null,
            'link'            => '../prospectos/ver.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

usort($eventosHoy, fn($a, $b) => strcmp($a['fecha_inicio'], $b['fecha_inicio']));

// ─────────────────────────────────────────────────────
// Panel lateral: PRÓXIMOS eventos (todos los tipos)
// ─────────────────────────────────────────────────────
$proximos = [];

try {
    $stmt = $db->prepare("
        SELECT ce.id, ce.titulo, ce.tipo, COALESCE(ce.color,'#3b82f6') as color, ce.fecha_inicio
        FROM calendario_eventos ce
        WHERE ce.usuario_id = ? AND ce.fecha_inicio > NOW()
        ORDER BY ce.fecha_inicio ASC LIMIT 5
    ");
    $stmt->execute([$uid]);
    foreach ($stmt->fetchAll() as $r) {
        $r['link'] = 'form.php?id=' . $r['id'];
        $proximos[] = $r;
    }
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("
        SELECT v.id, v.fecha, v.hora,
               CONCAT('Visita: ', COALESCE(c.nombre,'Sin cliente'),
                      IF(p.referencia IS NOT NULL, CONCAT(' — ', p.referencia), '')) as titulo
        FROM visitas v
        LEFT JOIN clientes c ON v.cliente_id = c.id
        LEFT JOIN propiedades p ON v.propiedad_id = p.id
        WHERE v.agente_id = ? AND v.fecha >= ?
        ORDER BY v.fecha ASC, v.hora ASC LIMIT 4
    ");
    $stmt->execute([$uid, $hoy]);
    foreach ($stmt->fetchAll() as $r) {
        $horaStr = !empty($r['hora']) ? $r['hora'] : '09:00:00';
        $proximos[] = [
            'id'          => $r['id'],
            'titulo'      => $r['titulo'],
            'tipo'        => 'visita',
            'color'       => '#10b981',
            'fecha_inicio'=> $r['fecha'] . ' ' . $horaStr,
            'link'        => '../visitas/form.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

try {
    $stmt = $db->prepare("
        SELECT t.id, t.titulo as titulo_raw, t.fecha_vencimiento,
               CASE t.prioridad WHEN 'urgente' THEN '#ef4444' WHEN 'alta' THEN '#f59e0b' ELSE '#8b5cf6' END as color
        FROM tareas t
        WHERE (t.asignado_a = ? OR t.creado_por = ?)
          AND t.estado IN ('pendiente','en_progreso')
          AND t.fecha_vencimiento > NOW()
        ORDER BY t.fecha_vencimiento ASC LIMIT 4
    ");
    $stmt->execute([$uid, $uid]);
    foreach ($stmt->fetchAll() as $r) {
        $proximos[] = [
            'id'          => $r['id'],
            'titulo'      => 'Tarea: ' . $r['titulo_raw'],
            'tipo'        => 'tarea',
            'color'       => $r['color'],
            'fecha_inicio'=> $r['fecha_vencimiento'],
            'link'        => '../tareas/form.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

try {
    $whereAgenteProx = $esAdmin ? '' : 'AND p.agente_id = ?';
    $paramsProx = $esAdmin ? [$hoy] : [$hoy, $uid];
    $stmt = $db->prepare("
        SELECT p.id, p.nombre, p.fecha_proximo_contacto
        FROM prospectos p
        WHERE p.activo = 1 AND p.etapa NOT IN ('captado','descartado')
          AND p.fecha_proximo_contacto >= ?
          $whereAgenteProx
        ORDER BY p.fecha_proximo_contacto ASC LIMIT 5
    ");
    $stmt->execute($paramsProx);
    foreach ($stmt->fetchAll() as $r) {
        $proximos[] = [
            'id'          => $r['id'],
            'titulo'      => 'Contactar: ' . $r['nombre'],
            'tipo'        => 'llamada',
            'color'       => '#f59e0b',
            'fecha_inicio'=> $r['fecha_proximo_contacto'] . ' 09:00:00',
            'link'        => '../prospectos/ver.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

usort($proximos, fn($a, $b) => strcmp($a['fecha_inicio'], $b['fecha_inicio']));
$proximos = array_slice($proximos, 0, 7);

// Colores e iconos por tipo
$tipoColores = [
    'visita'=>'#10b981','reunion'=>'#3b82f6','llamada'=>'#f59e0b',
    'tarea'=>'#8b5cf6','personal'=>'#6b7280','otro'=>'#ef4444',
];
$tipoIconos = [
    'visita'=>'bi-house-door','reunion'=>'bi-people','llamada'=>'bi-telephone',
    'tarea'=>'bi-check2-square','personal'=>'bi-person','otro'=>'bi-calendar-event',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="?mes=<?= $mesPrev ?>&anio=<?= $anioPrev ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i>
        </a>
        <h5 class="mb-0"><?= $nombreMes ?> <?= $anio ?></h5>
        <a href="?mes=<?= $mesNext ?>&anio=<?= $anioNext ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-right"></i>
        </a>
        <?php if ($mes != date('n') || $anio != date('Y')): ?>
        <a href="?" class="btn btn-outline-primary btn-sm">Hoy</a>
        <?php endif; ?>
    </div>
    <a href="form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Nuevo Evento
    </a>
</div>

<div class="row g-3">
    <!-- Grilla del calendario -->
    <div class="col-lg-9">
        <div class="card shadow-sm border-0">
            <div class="card-body p-2">
                <div class="calendar-grid">
                    <div class="calendar-header">
                        <?php foreach (['Lun','Mar','Mie','Jue','Vie','Sab','Dom'] as $d): ?>
                        <div class="calendar-day-header"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="calendar-body">
                        <?php for ($i = 1; $i < $diaSemanaInicio; $i++): ?>
                        <div class="calendar-cell calendar-empty"></div>
                        <?php endfor; ?>

                        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++):
                            $esHoy = ($dia == date('j') && $mes == date('n') && $anio == date('Y'));
                        ?>
                        <div class="calendar-cell <?= $esHoy ? 'calendar-today' : '' ?>">
                            <div class="calendar-date <?= $esHoy ? 'bg-primary text-white rounded-circle' : '' ?>">
                                <?= $dia ?>
                            </div>
                            <?php if (!empty($eventosPorDia[$dia])): ?>
                                <?php foreach (array_slice($eventosPorDia[$dia], 0, 3) as $ev): ?>
                                <a href="<?= htmlspecialchars($ev['link']) ?>"
                                   class="calendar-event"
                                   style="background-color: <?= $ev['color'] ?? $tipoColores[$ev['tipo']] ?? '#10b981' ?>;"
                                   title="<?= sanitize($ev['titulo']) ?>">
                                    <small><?= date('H:i', strtotime($ev['fecha_inicio'])) ?> <?= sanitize(mb_strimwidth($ev['titulo'], 0, 18, '…')) ?></small>
                                </a>
                                <?php endforeach; ?>
                                <?php $extra = count($eventosPorDia[$dia]) - 3; if ($extra > 0): ?>
                                <small class="text-muted d-block text-center">+<?= $extra ?> más</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>

                        <?php
                        $ultimoDiaSemana = (int)date('N', mktime(0,0,0,$mes,$diasEnMes,$anio));
                        for ($i = $ultimoDiaSemana + 1; $i <= 7; $i++):
                        ?>
                        <div class="calendar-cell calendar-empty"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leyenda -->
        <div class="d-flex gap-3 flex-wrap mt-2 px-1">
            <?php foreach (['visita'=>'Visitas','llamada'=>'Próx. Contacto','tarea'=>'Tareas','reunion'=>'Reuniones','calendario'=>'Eventos'] as $tipo => $label): ?>
            <small class="d-flex align-items-center gap-1 text-muted">
                <span class="rounded-circle d-inline-block" style="width:8px;height:8px;background:<?= $tipoColores[$tipo] ?? '#3b82f6' ?>;"></span>
                <?= $label ?>
            </small>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-lg-3">
        <!-- Hoy -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-calendar-check"></i> Hoy, <?= date('d/m/Y') ?></h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($eventosHoy)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-calendar-x d-block fs-3 mb-2"></i>
                    <small>Sin eventos para hoy</small>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($eventosHoy as $ev): ?>
                    <a href="<?= htmlspecialchars($ev['link']) ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle flex-shrink-0" style="width:10px;height:10px;background:<?= $ev['color'] ?? $tipoColores[$ev['tipo']] ?? '#10b981' ?>;"></span>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-semibold small text-truncate"><?= sanitize($ev['titulo']) ?></div>
                                <small class="text-muted">
                                    <?php if (!empty($ev['todo_dia'])): ?>
                                        Todo el día
                                    <?php else: ?>
                                        <?= date('H:i', strtotime($ev['fecha_inicio'])) ?>
                                        – <?= date('H:i', strtotime($ev['fecha_fin'])) ?>
                                    <?php endif; ?>
                                </small>
                                <?php if (!empty($ev['cliente_nombre'])): ?>
                                <br><small class="text-muted"><i class="bi bi-person"></i> <?= sanitize($ev['cliente_nombre'] . ' ' . ($ev['cliente_apellidos'] ?? '')) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Próximos -->
        <?php if (!empty($proximos)): ?>
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-clock"></i> Próximos</h6>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($proximos as $ev): ?>
                <a href="<?= htmlspecialchars($ev['link']) ?>" class="list-group-item list-group-item-action py-2">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi <?= $tipoIconos[$ev['tipo']] ?? 'bi-calendar-event' ?> mt-1 flex-shrink-0"
                           style="color:<?= $ev['color'] ?? $tipoColores[$ev['tipo']] ?? '#10b981' ?>;"></i>
                        <div class="overflow-hidden">
                            <div class="small fw-semibold text-truncate"><?= sanitize($ev['titulo']) ?></div>
                            <small class="text-muted"><?= formatFechaHora($ev['fecha_inicio']) ?></small>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.calendar-grid { width: 100%; }
.calendar-header { display: grid; grid-template-columns: repeat(7, 1fr); }
.calendar-day-header { text-align:center; font-weight:600; font-size:0.85rem; padding:8px 4px; color:#64748b; border-bottom:1px solid #e2e8f0; }
.calendar-body { display: grid; grid-template-columns: repeat(7, 1fr); }
.calendar-cell { min-height:100px; border:1px solid #f1f5f9; padding:4px; }
.calendar-cell:hover { background:#f8fafc; }
.calendar-empty { background:#fafafa; }
.calendar-today { background:#f0fdf4 !important; }
.calendar-date { display:inline-block; width:28px; height:28px; line-height:28px; text-align:center; font-size:0.85rem; font-weight:500; margin-bottom:2px; }
.calendar-event { display:block; padding:1px 4px; margin-bottom:2px; border-radius:3px; color:#fff; text-decoration:none; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; font-size:0.7rem; }
.calendar-event:hover { opacity:0.85; color:#fff; }
@media (max-width:768px) {
    .calendar-cell { min-height:60px; }
    .calendar-event small { font-size:0.6rem; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
