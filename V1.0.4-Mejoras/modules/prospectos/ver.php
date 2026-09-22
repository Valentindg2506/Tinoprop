<?php
$pageTitle = 'Detalle Prospecto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/custom_fields_helper.php';

$db = getDB();
$id = intval(get('id'));

$stmt = $db->prepare("SELECT p.*, u.nombre as agente_nombre, u.apellidos as agente_apellidos FROM prospectos p LEFT JOIN usuarios u ON p.agente_id = u.id WHERE p.id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { setFlash('danger', 'Prospecto no encontrado.'); header('Location: index.php'); exit; }

// Convertir a cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'convertir_cliente') {
    verifyCsrf();
    try {
        $clienteData = [
            'nombre' => $p['nombre'],
            'email' => $p['email'],
            'telefono' => $p['telefono'],
            'telefono2' => $p['telefono2'],
            'tipo' => 'propietario',
            'origen' => 'otro',
            'direccion' => $p['direccion'],
            'localidad' => $p['localidad'],
            'provincia' => $p['provincia'],
            'codigo_postal' => $p['codigo_postal'],
            'notas' => 'Convertido desde prospecto ' . $p['referencia'] . '. ' . ($p['notas'] ?? ''),
            'agente_id' => $p['agente_id'],
            'activo' => 1,
        ];
        $fields = array_keys($clienteData);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';

        $db->beginTransaction();
        $db->prepare("INSERT INTO clientes (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($clienteData));
        $clienteId = $db->lastInsertId();
        $db->prepare("UPDATE prospectos SET etapa = 'captado', estado = 'captado' WHERE id = ?")->execute([$id]);
        $db->commit();

        registrarActividad('convertir', 'prospecto', $id, 'Convertido a cliente #' . $clienteId);
        setFlash('success', 'Prospecto convertido a cliente correctamente. <a href="' . APP_URL . '/modules/clientes/ver.php?id=' . $clienteId . '">Ver cliente</a>');
        header('Location: ver.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('danger', 'Error al convertir: ' . $e->getMessage());
    }
}

// Convertir a propiedad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'convertir_propiedad') {
    verifyCsrf();
    try {
        $tiposValidos = array_keys(getTiposPropiedad());
        $tipoRaw = mb_strtolower(trim((string)($p['tipo_propiedad'] ?? '')));
        $tipoMap = [
            'atico' => 'atico',
            'a?tico' => 'atico',
            'local comercial' => 'local',
            'nave industrial' => 'nave',
        ];
        $tipo = $tipoMap[$tipoRaw] ?? preg_replace('/[^a-z_]/', '', str_replace(' ', '_', $tipoRaw));
        if (!in_array($tipo, $tiposValidos, true)) {
            $tipo = 'otro';
        }

        $operacion = (string)($p['operacion'] ?? 'venta');
        if (!in_array($operacion, ['venta', 'alquiler', 'alquiler_opcion_compra', 'traspaso'], true)) {
            $operacion = 'venta';
        }

        $precio = $p['precio_propietario'] ?? $p['precio_estimado'] ?? 0;
        $precio = is_numeric($precio) ? (float)$precio : 0;

        $tituloBase = trim((string)($p['tipo_propiedad'] ?? 'Propiedad'));
        $tituloZona = trim((string)($p['localidad'] ?? $p['provincia'] ?? ''));
        $titulo = $tituloBase . ($tituloZona !== '' ? ' en ' . $tituloZona : ' captada desde prospecto');

        $descripcionInterna = 'Convertida desde prospecto ' . $p['referencia'] . ' (ID ' . $id . ').';
        if (!empty($p['descripcion_interna'])) {
            $descripcionInterna .= "\n" . trim((string)$p['descripcion_interna']);
        }

        $propiedadData = [
            'referencia' => generarReferencia(),
            'titulo' => $titulo,
            'tipo' => $tipo,
            'operacion' => $operacion,
            'estado' => 'disponible',
            'precio' => $precio,
            'precio_comunidad' => $p['precio_comunidad'] ?: null,
            'superficie_construida' => $p['superficie_construida'] ?: ($p['superficie'] ?: null),
            'superficie_util' => $p['superficie_util'] ?: null,
            'superficie_parcela' => $p['superficie_parcela'] ?: null,
            'habitaciones' => $p['habitaciones'] ?: null,
            'banos' => $p['banos'] ?: null,
            'aseos' => $p['aseos'] ?: null,
            'planta' => $p['planta'] ?: null,
            'ascensor' => !empty($p['ascensor']) ? 1 : 0,
            'garaje_incluido' => !empty($p['garaje_incluido']) ? 1 : 0,
            'trastero_incluido' => !empty($p['trastero_incluido']) ? 1 : 0,
            'terraza' => !empty($p['terraza']) ? 1 : 0,
            'balcon' => !empty($p['balcon']) ? 1 : 0,
            'jardin' => !empty($p['jardin']) ? 1 : 0,
            'piscina' => !empty($p['piscina']) ? 1 : 0,
            'aire_acondicionado' => !empty($p['aire_acondicionado']) ? 1 : 0,
            'calefaccion' => $p['calefaccion'] ?: null,
            'orientacion' => $p['orientacion'] ?: null,
            'antiguedad' => $p['antiguedad'] ?: null,
            'estado_conservacion' => $p['estado_conservacion'] ?: null,
            'certificacion_energetica' => $p['certificacion_energetica'] ?: null,
            'referencia_catastral' => $p['referencia_catastral'] ?: null,
            'direccion' => $p['direccion'] ?: null,
            'numero' => $p['numero'] ?: null,
            'piso_puerta' => $p['piso_puerta'] ?: null,
            'codigo_postal' => $p['codigo_postal'] ?: null,
            'localidad' => $p['localidad'] ?: null,
            'provincia' => $p['provincia'] ?: null,
            'comunidad_autonoma' => $p['comunidad_autonoma'] ?: null,
            'latitud' => null,
            'longitud' => null,
            'descripcion' => $p['descripcion'] ?: ($p['notas'] ?: ''),
            'descripcion_interna' => $descripcionInterna,
            'propietario_id' => null,
            'agente_id' => $p['agente_id'] ?: null,
            'fecha_captacion' => date('Y-m-d'),
            'fecha_disponibilidad' => null,
        ];

        $fields = array_keys($propiedadData);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $db->beginTransaction();
        $db->prepare("INSERT INTO propiedades (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($propiedadData));
        $propiedadId = $db->lastInsertId();
        $db->prepare("UPDATE prospectos SET etapa = 'captado', estado = 'captado' WHERE id = ?")->execute([$id]);
        $db->commit();

        registrarActividad('convertir', 'prospecto', $id, 'Convertido a propiedad #' . $propiedadId);
        setFlash('success', 'Prospecto convertido a propiedad correctamente. <a href="' . APP_URL . '/modules/propiedades/ver.php?id=' . $propiedadId . '">Ver propiedad</a>');
        header('Location: ver.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('danger', 'Error al convertir a propiedad: ' . $e->getMessage());
    }
}

$etapas = [
    'nuevo_lead' => ['label' => 'Nuevo Lead', 'color' => '#06b6d4', 'icon' => 'bi-star'],
    'contactado' => ['label' => 'Contactado', 'color' => '#64748b', 'icon' => 'bi-telephone'],
    'seguimiento' => ['label' => 'Seguimiento', 'color' => '#3b82f6', 'icon' => 'bi-arrow-repeat'],
    'visita_programada' => ['label' => 'Visita Programada', 'color' => '#8b5cf6', 'icon' => 'bi-calendar-check'],
    'captado' => ['label' => 'Captado', 'color' => '#10b981', 'icon' => 'bi-check-circle'],
    'descartado' => ['label' => 'Descartado', 'color' => '#ef4444', 'icon' => 'bi-x-circle'],
];

$estados = [
    'nuevo_lead' => 'Nuevo lead',
    'contactado' => 'Contactado',
    'seguimiento' => 'Seguimiento',
    'visita_programada' => 'Visita programada',
    'captado' => 'Captado',
    'descartado' => 'Descartado',
];

$temperaturas = ['frio' => ['label' => 'Frío', 'color' => '#3b82f6', 'icon' => 'bi-snow'], 'templado' => ['label' => 'Templado', 'color' => '#f59e0b', 'icon' => 'bi-thermometer-half'], 'caliente' => ['label' => 'Caliente', 'color' => '#ef4444', 'icon' => 'bi-fire']];

$etapaActual = $p['etapa'];
if ($etapaActual === 'en_negociacion') {
    $etapaActual = 'seguimiento';
}
$etapaInfo = $etapas[$etapaActual] ?? ['label' => $p['etapa'], 'color' => '#64748b', 'icon' => 'bi-circle'];
$tempInfo = $temperaturas[$p['temperatura'] ?? 'frio'] ?? $temperaturas['frio'];

// Historial de contactos
$stmtHist = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                           FROM historial_prospectos h 
                           LEFT JOIN usuarios u ON h.usuario_id = u.id 
                           WHERE h.prospecto_id = ? 
                           ORDER BY COALESCE(h.fecha_evento, h.created_at) DESC LIMIT 50");
$stmtHist->execute([$id]);
$historial = $stmtHist->fetchAll();

// Historial de Propiedad
$historialPropiedad = [];
try {
    $stmtHP = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos
                             FROM historial_propiedad_prospecto h
                             LEFT JOIN usuarios u ON h.usuario_id = u.id
                             WHERE h.prospecto_id = ?
                             ORDER BY h.created_at DESC LIMIT 50");
    $stmtHP->execute([$id]);
    $historialPropiedad = $stmtHP->fetchAll();
} catch (Exception $e) { /* tabla aún no migrada */ }

// Custom fields
$customValues = getCustomFieldValues($id, 'prospecto');
$customFields = getCustomFields('prospecto');

// Agentes para select
$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();

$tiposHistorial = [
    'llamada' => ['label' => 'Llamada', 'icon' => 'bi-telephone', 'color' => '#3b82f6'],
    'email' => ['label' => 'Email', 'icon' => 'bi-envelope', 'color' => '#8b5cf6'],
    'visita' => ['label' => 'Visita', 'icon' => 'bi-house-door', 'color' => '#10b981'],
    'nota' => ['label' => 'Nota', 'icon' => 'bi-sticky', 'color' => '#f59e0b'],
    'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'color' => '#25d366'],
    'otro' => ['label' => 'Otro', 'icon' => 'bi-chat-dots', 'color' => '#6b7280'],
];

$tiposHistorialPropiedad = [
    'subida_precio' => ['label' => 'Subida de precio', 'icon' => 'bi-graph-up-arrow', 'color' => '#ef4444'],
    'bajada_precio' => ['label' => 'Bajada de precio', 'icon' => 'bi-graph-down-arrow', 'color' => '#10b981'],
    'modificacion' => ['label' => 'Modificación', 'icon' => 'bi-pencil-square', 'color' => '#3b82f6'],
    'publicacion' => ['label' => 'Publicación', 'icon' => 'bi-megaphone', 'color' => '#8b5cf6'],
    'retirada' => ['label' => 'Retirada', 'icon' => 'bi-eye-slash', 'color' => '#6b7280'],
    'otro' => ['label' => 'Otro', 'icon' => 'bi-three-dots', 'color' => '#94a3b8'],
];
?>

<style>
/* Inline Edit Styles */
.editable-field { position: relative; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.editable-field:hover { background: var(--primary-light, rgba(16,185,129,0.08)); }
.editable-field .edit-icon { opacity: 0; font-size: 0.75rem; color: var(--primary); transition: opacity 0.2s; }
.editable-field:hover .edit-icon { opacity: 1; }
.editable-field.editing { background: transparent; }
.inline-input { border: 2px solid var(--primary); border-radius: 6px; padding: 4px 8px; font-size: inherit; width: 100%; outline: none; }
.inline-input:focus { box-shadow: 0 0 0 3px var(--primary-light); }
.inline-select { border: 2px solid var(--primary); border-radius: 6px; padding: 4px 8px; font-size: inherit; outline: none; }
.save-indicator { position: absolute; right: -20px; top: 50%; transform: translateY(-50%); }
.save-indicator.success { color: #10b981; }
.save-indicator.error { color: #ef4444; }

/* Timeline Styles */
.timeline { position: relative; padding-left: 0; }
.timeline-entry { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.05); position: relative; }
.timeline-entry:last-child { border-bottom: none; }
.timeline-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.timeline-content { flex: 1; min-width: 0; }
.timeline-header { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap; }
.timeline-header .name { font-weight: 600; font-size: 0.85rem; }
.timeline-header .date { font-size: 0.75rem; color: #94a3b8; }
.timeline-header .tipo-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 8px; font-weight: 500; }
.timeline-text { font-size: 0.9rem; color: #374151; line-height: 1.5; }
[data-bs-theme="dark"] .timeline-text { color: #d1d5db; }
.timeline-actions { opacity: 0; transition: opacity 0.15s; }
.timeline-entry:hover .timeline-actions { opacity: 1; }
@media (hover: none), (max-width: 991.98px) {
    .timeline-actions { opacity: 1; }
}

/* Mini Calendar Container */
.mini-cal-wrapper { background: #fff; border-radius: 8px; }
[data-bs-theme="dark"] .mini-cal-wrapper { background: #1e293b; }
.mini-cal-wrapper .flatpickr-calendar { box-shadow: none !important; width: 100% !important; }
.task-list-day { max-height: 160px; overflow-y: auto; }
.task-item { padding: 5px 8px; border-radius: 6px; margin-bottom: 3px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; }

/* Add contact form */
.add-contact-form { border-top: 1px solid rgba(0,0,0,0.08); padding-top: 12px; }
.contact-type-selector { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.contact-type-btn { border: 1px solid #e2e8f0; background: transparent; padding: 3px 10px; border-radius: 16px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 4px; }
.contact-type-btn:hover, .contact-type-btn.active { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }

/* Historial Propiedad */
.prop-hist-entry { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
.prop-hist-entry:last-child { border-bottom: none; }
.prop-hist-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; color: #fff; }
.prop-hist-content { flex: 1; min-width: 0; }
.prop-hist-meta { font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
.price-change { font-weight: 600; font-size: 0.85rem; }
.prop-type-selector { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.prop-type-btn { border: 1px solid #e2e8f0; background: transparent; padding: 3px 10px; border-radius: 16px; font-size: 0.75rem; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 4px; }
.prop-type-btn:hover, .prop-type-btn.active { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
</style>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div class="d-flex align-items-center gap-3">
        <a href="index.php" class="btn btn-outline-secondary" id="btn-volver">
            <i class="bi bi-arrow-left"></i> Volver al listado
        </a>
        <span class="badge fs-6" style="background: <?= $etapaInfo['color'] ?>;">
            <i class="bi <?= $etapaInfo['icon'] ?>"></i> <?= $etapaInfo['label'] ?>
        </span>
        <span class="editable-field" data-field="etapa" data-type="select" data-options='<?= json_encode(array_map(fn($e) => $e['label'], $etapas)) ?>' data-value="<?= $etapaActual ?>">
            <i class="bi bi-pencil edit-icon"></i>
        </span>
        <span class="badge" style="background: <?= $tempInfo['color'] ?>;">
            <i class="bi <?= $tempInfo['icon'] ?>"></i> <?= $tempInfo['label'] ?>
        </span>
        <?php /* exclusividad badge eliminado */ ?>
        <?php if (!$p['activo']): ?><span class="badge bg-secondary ms-1">Inactivo</span><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <?php if ($etapaActual !== 'captado' && $etapaActual !== 'descartado'): ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('¿Convertir este prospecto en cliente?')">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="convertir_cliente">
            <button type="submit" class="btn btn-success"><i class="bi bi-person-check"></i> Convertir a Cliente</button>
        </form>
        <form method="POST" class="d-inline" onsubmit="return confirm('¿Convertir este prospecto en propiedad?')">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="convertir_propiedad">
            <button type="submit" class="btn btn-outline-success"><i class="bi bi-house-check"></i> Convertir a Propiedad</button>
        </form>
        <?php endif; ?>
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Formulario Completo</a>
        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar este prospecto?')">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= intval($id) ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<!-- Pipeline Progress -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center position-relative" style="z-index: 1;">
            <?php
            $etapaKeys = array_keys($etapas);
            $currentIdx = array_search($etapaActual, $etapaKeys, true);
            if ($currentIdx === false) $currentIdx = 0;
            foreach ($etapas as $eKey => $eData):
                $idx = array_search($eKey, $etapaKeys);
                $isActive = $eKey === $etapaActual;
                $isPast = $idx < $currentIdx;
            ?>
            <div class="text-center flex-fill">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1"
                     style="width: 36px; height: 36px; font-size: 0.9rem;
                            background: <?= $isActive ? $eData['color'] : ($isPast ? $eData['color'] . '30' : '#e2e8f030') ?>;
                            color: <?= $isActive ? '#fff' : ($isPast ? $eData['color'] : '#94a3b8') ?>;
                            border: 2px solid <?= $isActive || $isPast ? $eData['color'] : '#e2e8f0' ?>;">
                    <i class="bi <?= $eData['icon'] ?>"></i>
                </div>
                <div class="small <?= $isActive ? 'fw-bold' : '' ?>" style="font-size: 0.7rem; color: <?= $isActive ? $eData['color'] : '#94a3b8' ?>;">
                    <?= $eData['label'] ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <!-- Info del Prospecto (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person"></i> Datos del Prospecto</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">
                        <span class="editable-field" data-field="nombre" data-type="text" data-value="<?= sanitize($p['nombre']) ?>">
                            <?= sanitize($p['nombre']) ?> <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </h5>
                    <span class="badge bg-primary"><?= sanitize($p['referencia']) ?></span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Email</small>
                    <span class="editable-field" data-field="email" data-type="email" data-value="<?= sanitize($p['email'] ?? '') ?>">
                        <?php if ($p['email']): ?>
                            <a href="mailto:<?= sanitize($p['email']) ?>"><?= sanitize($p['email']) ?></a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Teléfono</small>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="editable-field" data-field="telefono" data-type="tel" data-value="<?= sanitize($p['telefono'] ?? '') ?>">
                            <?php if ($p['telefono']): ?>
                                <a href="tel:<?= sanitize($p['telefono']) ?>"><?= sanitize($p['telefono']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                        <?php if ($p['telefono']): ?>
                            <?php
                            $telWa = preg_replace('/[^0-9]/', '', $p['telefono']);
                            if (strlen($telWa) === 9) $telWa = '34' . $telWa;
                            ?>
                            <a href="https://wa.me/<?= $telWa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success py-0 px-2" style="font-size:0.75rem;" title="Enviar WhatsApp">
                                <i class="bi bi-whatsapp"></i> WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Teléfono 2</small>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="editable-field" data-field="telefono2" data-type="tel" data-value="<?= sanitize($p['telefono2'] ?? '') ?>">
                            <?php if ($p['telefono2']): ?>
                                <a href="tel:<?= sanitize($p['telefono2']) ?>"><?= sanitize($p['telefono2']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                        <?php if ($p['telefono2']): ?>
                            <?php
                            $tel2Wa = preg_replace('/[^0-9]/', '', $p['telefono2']);
                            if (strlen($tel2Wa) === 9) $tel2Wa = '34' . $tel2Wa;
                            ?>
                            <a href="https://wa.me/<?= $tel2Wa ?>" target="_blank" rel="noopener" class="btn btn-sm btn-success py-0 px-2" style="font-size:0.75rem;" title="Enviar WhatsApp">
                                <i class="bi bi-whatsapp"></i> WA
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Estado</small>
                    <span class="editable-field" data-field="etapa" data-type="select" data-options='<?= json_encode($estados) ?>' data-value="<?= $etapaActual ?>">
                        <span class="badge-estado badge-<?= $etapaActual ?>"><?= $estados[$etapaActual] ?? $etapaActual ?></span>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <div class="mb-2">
                    <small class="text-muted d-block">Temperatura</small>
                    <span class="editable-field" data-field="temperatura" data-type="select" data-options='<?= json_encode(array_map(fn($t) => $t['label'], $temperaturas)) ?>' data-value="<?= $p['temperatura'] ?? 'frio' ?>">
                        <span class="badge" style="background: <?= $tempInfo['color'] ?>"><i class="bi <?= $tempInfo['icon'] ?>"></i> <?= $tempInfo['label'] ?></span>
                        <i class="bi bi-pencil edit-icon"></i>
                    </span>
                </div>

                <?php
                // Mostrar copropietarios si existen
                $copropietarios = [];
                if (!empty($p['propietarios_json'])) {
                    $copropietarios = json_decode($p['propietarios_json'], true) ?? [];
                }
                if (!empty($copropietarios)):
                ?>
                <hr>
                <div class="mb-2">
                    <small class="text-muted d-block fw-semibold"><i class="bi bi-people"></i> Copropietarios</small>
                    <?php foreach ($copropietarios as $idx => $co): ?>
                    <div class="border rounded p-2 mt-1" style="font-size:0.85rem;">
                        <div class="fw-semibold"><?= sanitize($co['nombre'] ?? 'Propietario ' . ($idx+2)) ?></div>
                        <?php if (!empty($co['telefono'])): ?>
                        <?php
                        $coTelWa = preg_replace('/[^0-9]/', '', $co['telefono']);
                        if (strlen($coTelWa) === 9) $coTelWa = '34' . $coTelWa;
                        ?>
                        <div><a href="tel:<?= sanitize($co['telefono']) ?>"><?= sanitize($co['telefono']) ?></a>
                            <a href="https://wa.me/<?= $coTelWa ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm py-0 px-1 ms-1" style="font-size:0.7rem;"><i class="bi bi-whatsapp"></i></a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($co['email'])): ?>
                        <div><a href="mailto:<?= sanitize($co['email']) ?>"><?= sanitize($co['email']) ?></a></div>
                        <?php endif; ?>
                        <?php if (!empty($co['dni'])): ?>
                        <div class="text-muted"><?= sanitize($co['dni']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <p class="mb-0 text-muted mt-3"><small>Alta: <?= formatFecha($p['created_at']) ?></small></p>
            </div>
        </div>

        <!-- Seguimiento con Mini Calendario -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-calendar-event"></i> Seguimiento</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="detail-label">Fecha Publicación</div>
                        <span class="editable-field" data-field="fecha_publicacion_propiedad" data-type="date" data-value="<?= $p['fecha_publicacion_propiedad'] ?? '' ?>">
                            <?php if (!empty($p['fecha_publicacion_propiedad'])): ?>
                                <?php
                                $fechaPub = new DateTime($p['fecha_publicacion_propiedad']);
                                $hoyPub = new DateTime('today');
                                $diasPub = max(0, intval($fechaPub->diff($hoyPub)->format('%a')));
                                ?>
                                <span class="detail-value"><?= formatFecha($p['fecha_publicacion_propiedad']) ?> (<?= $diasPub ?> días)</span>
                            <?php else: ?>
                                <span class="detail-value">-</span>
                            <?php endif; ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Primer Contacto</div>
                        <span class="editable-field" data-field="fecha_contacto" data-type="date" data-value="<?= $p['fecha_contacto'] ?? '' ?>">
                            <span class="detail-value"><?= $p['fecha_contacto'] ? formatFecha($p['fecha_contacto']) : '-' ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Próx. Contacto</div>
                        <span class="editable-field" data-field="fecha_proximo_contacto" data-type="date" data-value="<?= $p['fecha_proximo_contacto'] ?? '' ?>">
                            <?php if ($p['fecha_proximo_contacto']): ?>
                                <?php
                                $proxDate = new DateTime($p['fecha_proximo_contacto']);
                                $today = new DateTime('today');
                                $isPast = $proxDate < $today;
                                $isToday = $proxDate->format('Y-m-d') === $today->format('Y-m-d');
                                ?>
                                <span class="<?= $isPast ? 'text-danger fw-bold' : ($isToday ? 'text-warning fw-bold' : '') ?>">
                                    <?= formatFecha($p['fecha_proximo_contacto']) ?>
                                </span>
                                <?php if ($isPast): ?><br><small class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Vencido</small><?php endif; ?>
                                <?php if ($isToday): ?><br><small class="text-warning"><i class="bi bi-clock"></i> Hoy</small><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Hora de Contacto</div>
                        <span class="editable-field" data-field="hora_contacto" data-type="time" data-value="<?= $p['hora_contacto'] ?? '' ?>">
                            <span class="detail-value"><?= !empty($p['hora_contacto']) ? substr($p['hora_contacto'], 0, 5) : '-' ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Mejor Horario Contacto</div>
                        <span class="editable-field" data-field="mejor_horario_contacto" data-type="text" data-value="<?= sanitize($p['mejor_horario_contacto'] ?? '') ?>">
                            <span class="detail-value"><?= !empty($p['mejor_horario_contacto']) ? sanitize($p['mejor_horario_contacto']) : '-' ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <?php /* comision y exclusividad eliminados de esta vista */ ?>
                </div>

                <!-- Mini Calendar -->
                <hr>
                <div class="mini-cal-wrapper">
                    <div id="miniCalendar"></div>
                    <div id="miniCalTasks" class="mt-2" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="small fw-bold mb-0" id="miniCalDate"></h6>
                            <span id="miniCalCount" class="badge bg-primary rounded-pill" style="font-size:0.65rem; display:none;"></span>
                        </div>
                        <div id="miniCalTaskList" class="task-list-day"></div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <input type="time" id="horaProxContacto" class="form-control form-control-sm" value="09:00" style="width:110px; flex-shrink:0;">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" id="btnSetProxContacto">
                                <i class="bi bi-calendar-plus"></i> Fijar como próximo contacto
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agente -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-badge"></i> Agente Asignado</div>
            <div class="card-body">
                <span class="editable-field" data-field="agente_id" data-type="select" data-options='<?= json_encode(array_column($agentes, 'nombre_completo', 'id')) ?>' data-value="<?= $p['agente_id'] ?>">
                    <strong><?= sanitize(($p['agente_nombre'] ?? '') . ' ' . ($p['agente_apellidos'] ?? '')) ?></strong>
                    <i class="bi bi-pencil edit-icon"></i>
                </span>
            </div>
        </div>

        <!-- Custom Fields -->
        <?php if (!empty($customFields)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</div>
            <div class="card-body">
                <?php foreach ($customFields as $cf): ?>
                    <?php $cfVal = $customValues[$cf['slug']]['valor'] ?? ''; ?>
                    <div class="mb-2">
                        <small class="text-muted d-block"><?= sanitize($cf['nombre']) ?></small>
                        <span class="editable-field" data-field="cf_<?= $cf['id'] ?>" data-type="custom_field" data-custom-field-id="<?= $cf['id'] ?>" data-value="<?= sanitize($cfVal) ?>">
                            <?= $cfVal ?: '<span class="text-muted">-</span>' ?>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <!-- Datos de la Propiedad (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-house-door"></i> Datos de la Propiedad</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $propFields = [
                        ['field' => 'tipo_propiedad', 'label' => 'Tipo', 'type' => 'select', 'col' => 'col-md-4',
                         'options' => json_encode(array_combine(['Piso','Casa','Chalet','Adosado','Atico','Duplex','Estudio','Local','Oficina','Nave','Terreno','Garaje','Edificio','Otro'], ['Piso','Casa','Chalet','Adosado','Atico','Duplex','Estudio','Local','Oficina','Nave','Terreno','Garaje','Edificio','Otro']))],
                        ['field' => 'precio_estimado', 'label' => 'Precio Estimado', 'type' => 'number', 'col' => 'col-md-4', 'format' => 'precio'],
                        ['field' => 'precio_propietario', 'label' => 'Precio Propietario', 'type' => 'number', 'col' => 'col-md-4', 'format' => 'precio'],
                        ['field' => 'superficie', 'label' => 'Superficie', 'type' => 'number', 'col' => 'col-md-3', 'format' => 'superficie'],
                        ['field' => 'habitaciones', 'label' => 'Habitaciones', 'type' => 'number', 'col' => 'col-md-3'],
                        ['field' => 'direccion',   'label' => 'Dirección',  'type' => 'text', 'col' => 'col-md-4'],
                        ['field' => 'numero',      'label' => 'Nº',         'type' => 'text', 'col' => 'col-md-2'],
                        ['field' => 'piso_puerta', 'label' => 'Piso',       'type' => 'text', 'col' => 'col-md-2'],
                        ['field' => 'escalera',    'label' => 'Escalera',   'type' => 'text', 'col' => 'col-md-2'],
                        ['field' => 'puerta',      'label' => 'Puerta',     'type' => 'text', 'col' => 'col-md-2'],
                        ['field' => 'barrio', 'label' => 'Barrio / Zona', 'type' => 'text', 'col' => 'col-md-3'],
                        ['field' => 'localidad', 'label' => 'Localidad', 'type' => 'text', 'col' => 'col-md-3'],
                        ['field' => 'provincia', 'label' => 'Provincia', 'type' => 'text', 'col' => 'col-md-3'],
                        ['field' => 'codigo_postal', 'label' => 'Código Postal', 'type' => 'text', 'col' => 'col-md-3'],
                    ];
                    foreach ($propFields as $pf):
                        $val = $p[$pf['field']] ?? '';
                        $displayVal = $val ?: '-';
                        if (($pf['format'] ?? '') === 'precio' && $val) $displayVal = formatPrecio($val);
                        if (($pf['format'] ?? '') === 'superficie' && $val) $displayVal = formatSuperficie($val);
                        $isFormatted = in_array(($pf['format'] ?? ''), ['precio', 'superficie'], true);
                    ?>
                    <div class="<?= $pf['col'] ?>">
                        <div class="detail-label"><?= $pf['label'] ?></div>
                        <span class="editable-field" data-field="<?= $pf['field'] ?>" data-type="<?= $pf['type'] ?>" data-value="<?= sanitize($val) ?>" <?= isset($pf['options']) ? "data-options='" . $pf['options'] . "'" : '' ?>>
                            <span class="detail-value <?= ($pf['format'] ?? '') === 'precio' ? 'fw-bold' : '' ?>"><?= $isFormatted ? $displayVal : sanitize($displayVal) ?></span>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($p['enlace']): ?>
                    <div class="col-12">
                        <div class="detail-label">Enlace</div>
                        <span class="editable-field" data-field="enlace" data-type="url" data-value="<?= sanitize($p['enlace']) ?>">
                            <a href="<?= sanitize($p['enlace']) ?>" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right"></i> <?= sanitize(mb_substr($p['enlace'], 0, 60)) ?><?= mb_strlen($p['enlace']) > 60 ? '...' : '' ?>
                            </a>
                            <i class="bi bi-pencil edit-icon"></i>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Historial de Propiedad -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-house-gear"></i> Historial de Propiedad</span>
                <span class="badge bg-secondary"><?= count($historialPropiedad) ?></span>
            </div>
            <div class="card-body">
                <!-- Formulario añadir entrada -->
                <div class="add-contact-form mb-3">
                    <div class="prop-type-selector" id="propTipoSelector">
                        <?php foreach ($tiposHistorialPropiedad as $tKey => $tData): ?>
                        <button type="button" class="prop-type-btn <?= $tKey === 'modificacion' ? 'active' : '' ?>" data-tipo="<?= $tKey ?>">
                            <i class="bi <?= $tData['icon'] ?>"></i> <?= $tData['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div id="propPrecioFields" class="row g-2 mb-2" style="display:none!important;">
                        <div class="col-6">
                            <input type="number" id="propPrecioAnterior" class="form-control form-control-sm" placeholder="Precio anterior (€)" min="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <input type="number" id="propPrecioNuevo" class="form-control form-control-sm" placeholder="Precio nuevo (€)" min="0" step="0.01">
                        </div>
                    </div>
                    <input type="hidden" id="propTipoSeleccionado" value="modificacion">
                    <div class="d-flex gap-2">
                        <input type="text" id="propDescripcion" class="form-control form-control-sm" placeholder="Descripción del cambio (opcional)">
                        <button type="button" class="btn btn-primary btn-sm" id="btnAddPropHistorial" style="white-space:nowrap;">
                            <i class="bi bi-plus-lg"></i> Registrar
                        </button>
                    </div>
                </div>

                <!-- Timeline propiedad -->
                <div id="propHistorialContainer">
                    <?php if (empty($historialPropiedad)): ?>
                        <p class="text-muted mb-0 small" id="emptyPropHistorial">Sin historial de propiedad registrado</p>
                    <?php else: ?>
                        <?php foreach ($historialPropiedad as $hp):
                            $hpTipo = $tiposHistorialPropiedad[$hp['tipo']] ?? $tiposHistorialPropiedad['otro'];
                            $iniciales = strtoupper(mb_substr($hp['usuario_nombre'] ?? '?', 0, 1) . mb_substr($hp['usuario_apellidos'] ?? '', 0, 1));
                        ?>
                        <div class="prop-hist-entry" data-id="<?= $hp['id'] ?>">
                            <div class="prop-hist-icon" style="background: <?= $hpTipo['color'] ?>;">
                                <i class="bi <?= $hpTipo['icon'] ?>"></i>
                            </div>
                            <div class="prop-hist-content">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold small"><?= $hpTipo['label'] ?></span>
                                    <?php if ($hp['precio_anterior'] !== null || $hp['precio_nuevo'] !== null): ?>
                                        <span class="price-change" style="color: <?= $hpTipo['color'] ?>;">
                                            <?php if ($hp['precio_anterior'] !== null): ?>
                                                <?= number_format($hp['precio_anterior'], 0, ',', '.') ?> €
                                            <?php endif; ?>
                                            <?php if ($hp['precio_anterior'] !== null && $hp['precio_nuevo'] !== null): ?>
                                                <i class="bi bi-arrow-right"></i>
                                            <?php endif; ?>
                                            <?php if ($hp['precio_nuevo'] !== null): ?>
                                                <?= number_format($hp['precio_nuevo'], 0, ',', '.') ?> €
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-link text-danger p-0 ms-auto" onclick="deletePropHistorial(<?= $hp['id'] ?>)" title="Eliminar">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <?php if ($hp['descripcion']): ?>
                                    <div class="small text-body-secondary mt-1"><?= nl2br(sanitize($hp['descripcion'])) ?></div>
                                <?php endif; ?>
                                <div class="prop-hist-meta">
                                    <?= sanitize(($hp['usuario_nombre'] ?? '') . ' ' . mb_substr($hp['usuario_apellidos'] ?? '', 0, 1)) ?>. &middot; <?= date('d/m/Y H:i', strtotime($hp['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Próxima Acción -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-lightning-charge"></i> Próxima Acción</div>
            <div class="card-body">
                <span class="editable-field d-block" data-field="proxima_accion" data-type="textarea" data-value="<?= sanitize($p['proxima_accion'] ?? '') ?>">
                    <?= !empty($p['proxima_accion']) ? nl2br(sanitize($p['proxima_accion'])) : '<span class="text-muted">¿Qué hay que hacer con este prospecto?</span>' ?>
                    <i class="bi bi-pencil edit-icon"></i>
                </span>
            </div>
        </div>

        <!-- Notas (Editable) -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-chat-text"></i> Notas</div>
            <div class="card-body">
                <span class="editable-field d-block" data-field="notas" data-type="textarea" data-value="<?= sanitize($p['notas'] ?? '') ?>">
                    <?= $p['notas'] ? nl2br(sanitize($p['notas'])) : '<span class="text-muted">Sin notas</span>' ?>
                    <i class="bi bi-pencil edit-icon"></i>
                </span>
            </div>
        </div>

        <!-- Historial de Contactos (Timeline) -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> Historial de Contactos</span>
                <span class="badge bg-secondary"><?= count($historial) ?></span>
            </div>
            <div class="card-body">
                <!-- Add new contact form -->
                <div class="add-contact-form mb-3">
                    <div class="contact-type-selector">
                        <?php foreach ($tiposHistorial as $tKey => $tData): ?>
                        <button type="button" class="contact-type-btn <?= $tKey === 'nota' ? 'active' : '' ?>" data-tipo="<?= $tKey ?>">
                            <i class="bi <?= $tData['icon'] ?>"></i> <?= $tData['label'] ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <input type="hidden" id="historialTipo" value="nota">
                        <textarea id="historialContenido" class="form-control form-control-sm" rows="2" placeholder="Añadir contacto o nota..." style="resize: none;"></textarea>
                        <button type="button" class="btn btn-primary btn-sm align-self-end" id="btnAddHistorial" style="white-space: nowrap;">
                            <i class="bi bi-plus-lg"></i> Añadir
                        </button>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="timeline" id="timelineContainer">
                    <?php if (empty($historial)): ?>
                        <p class="text-muted mb-0" id="emptyHistorial">Sin historial de contactos registrado</p>
                    <?php else: ?>
                        <?php foreach ($historial as $h):
                            $hTipo = $tiposHistorial[$h['tipo']] ?? $tiposHistorial['otro'];
                            $iniciales = strtoupper(mb_substr($h['usuario_nombre'] ?? '?', 0, 1) . mb_substr($h['usuario_apellidos'] ?? '', 0, 1));
                            $fechaHistorial = $h['fecha_evento'] ?: $h['created_at'];
                        ?>
                        <div class="timeline-entry" data-id="<?= $h['id'] ?>">
                            <div class="timeline-avatar" style="background: <?= $hTipo['color'] ?>;"><?= $iniciales ?></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="name"><?= sanitize(($h['usuario_nombre'] ?? '') . ' ' . mb_substr($h['usuario_apellidos'] ?? '', 0, 1)) ?>.</span>
                                    <span class="tipo-badge" style="background: <?= $hTipo['color'] ?>20; color: <?= $hTipo['color'] ?>;">
                                        <i class="bi <?= $hTipo['icon'] ?>"></i> <?= $hTipo['label'] ?>
                                    </span>
                                    <span class="date"><?= date('d/m/Y H:i', strtotime($fechaHistorial)) ?></span>
                                    <div class="timeline-actions ms-auto">
                                        <button
                                            class="btn btn-sm btn-link text-secondary p-0 me-2 btn-edit-historial-fecha"
                                            data-id="<?= $h['id'] ?>"
                                            data-fecha="<?= date('Y-m-d\TH:i', strtotime($fechaHistorial)) ?>"
                                            title="Editar fecha/hora">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteHistorial(<?= $h['id'] ?>)" title="Eliminar">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="timeline-text"><?= nl2br(sanitize($h['contenido'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal editar fecha/hora historial -->
<div class="modal fade" id="historialFechaModal" tabindex="-1" aria-labelledby="historialFechaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historialFechaModalLabel"><i class="bi bi-clock-history"></i> Editar fecha y hora</h5>
                <button type="button" class="btn-close" id="btnCerrarHistorialFechaModal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label for="historialFechaInput" class="form-label">Selecciona fecha y hora</label>
                <input type="datetime-local" id="historialFechaInput" class="form-control" step="300">
                <div class="form-text">Selecciona fecha y hora en formato 24h.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnCancelarHistorialFechaModal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarHistorialFecha">
                    <i class="bi bi-check2"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

<script>
const PROSPECTO_ID = <?= $id ?>;
const CSRF_TOKEN = '<?= csrfToken() ?>';
const API_URL = '<?= APP_URL ?>/api/prospectos.php';
let historialEditEntradaId = null;
const historialFechaModalEl = document.getElementById('historialFechaModal');
const historialFechaInput = document.getElementById('historialFechaInput');
let historialFechaModal = null;
let historialFechaBackdrop = null;

if (historialFechaModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    historialFechaModal = new bootstrap.Modal(historialFechaModalEl);
}

function openHistorialFechaModal() {
    if (!historialFechaModalEl) return;
    if (historialFechaModal) {
        historialFechaModal.show();
        return;
    }

    historialFechaModalEl.style.display = 'block';
    historialFechaModalEl.classList.add('show');
    historialFechaModalEl.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');

    historialFechaBackdrop = document.createElement('div');
    historialFechaBackdrop.className = 'modal-backdrop fade show';
    document.body.appendChild(historialFechaBackdrop);
}

function closeHistorialFechaModal() {
    if (!historialFechaModalEl) return;
    if (historialFechaModal) {
        historialFechaModal.hide();
        return;
    }

    historialFechaModalEl.classList.remove('show');
    historialFechaModalEl.style.display = 'none';
    historialFechaModalEl.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    if (historialFechaBackdrop) {
        historialFechaBackdrop.remove();
        historialFechaBackdrop = null;
    }
}

// ─────────────────────────────────
// INLINE EDITING
// ─────────────────────────────────
document.querySelectorAll('.editable-field').forEach(el => {
    el.addEventListener('click', function(e) {
        if (this.classList.contains('editing')) return;
        if (e.target.tagName === 'A') return; // Don't interfere with links

        const field = this.dataset.field;
        const type = this.dataset.type;
        const currentVal = this.dataset.value || '';
        const originalHTML = this.innerHTML;

        this.classList.add('editing');

        let input;
        if (type === 'textarea') {
            input = document.createElement('textarea');
            input.className = 'inline-input';
            input.rows = 3;
            input.value = currentVal;
        } else if (type === 'select') {
            input = document.createElement('select');
            input.className = 'inline-select';
            const options = JSON.parse(this.dataset.options || '{}');
            input.innerHTML = '<option value="">-</option>';
            for (const [key, label] of Object.entries(options)) {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = label;
                if (key === currentVal) opt.selected = true;
                input.appendChild(opt);
            }
        } else if (type === 'toggle') {
            // Toggle boolean
            const newVal = currentVal === '1' ? '0' : '1';
            saveInlineField(field, newVal, this, originalHTML);
            return;
        } else if (type === 'date') {
            input = document.createElement('input');
            input.type = 'date';
            input.className = 'inline-input';
            input.value = currentVal;
        } else if (type === 'time') {
            input = document.createElement('input');
            input.type = 'time';
            input.className = 'inline-input';
            input.value = currentVal;
        } else {
            input = document.createElement('input');
            input.type = type === 'number' ? 'number' : (type === 'email' ? 'email' : 'text');
            input.className = 'inline-input';
            input.value = currentVal;
            if (type === 'number') input.step = 'any';
        }

        this.innerHTML = '';
        this.appendChild(input);
        input.focus();
        if (input.select) input.select();

        const save = () => {
            const newVal = input.value;
            if (newVal !== currentVal) {
                saveInlineField(field, newVal, el, originalHTML);
            } else {
                el.innerHTML = originalHTML;
                el.classList.remove('editing');
            }
        };

        input.addEventListener('blur', () => setTimeout(save, 150));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && type !== 'textarea') { e.preventDefault(); save(); }
            if (e.key === 'Escape') { el.innerHTML = originalHTML; el.classList.remove('editing'); }
        });
    });
});

function saveInlineField(field, value, el, originalHTML) {
    // Show saving spinner
    el.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';

    const isCustomField = el.dataset.type === 'custom_field';
    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);

    if (isCustomField) {
        body.append('accion', 'editar_custom_field');
        body.append('prospecto_id', PROSPECTO_ID);
        body.append('field_id', el.dataset.customFieldId);
        body.append('valor', value);
    } else {
        body.append('accion', 'editar_campo');
        body.append('id', PROSPECTO_ID);
        body.append('campo', field);
        body.append('valor', value);
    }

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            el.dataset.value = value;
            // Reload to get proper formatting
            location.reload();
        } else {
            el.innerHTML = originalHTML;
            el.classList.remove('editing');
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(() => {
        el.innerHTML = originalHTML;
        el.classList.remove('editing');
        alert('Error de conexión');
    });
}

// ─────────────────────────────────
// MINI CALENDAR (Flatpickr)
// ─────────────────────────────────
let selectedCalDate = null;

if (typeof flatpickr === 'function' && document.getElementById('miniCalendar')) {
    const defaultCalDate = '<?= !empty($p['fecha_proximo_contacto']) ? $p['fecha_proximo_contacto'] : date('Y-m-d') ?>';
    flatpickr('#miniCalendar', {
        inline: true,
        locale: 'es',
        dateFormat: 'Y-m-d',
        defaultDate: defaultCalDate,
        onReady: function(selectedDates, dateStr) {
            // Cargar eventos del día inicial automáticamente
            const fecha = dateStr || defaultCalDate;
            selectedCalDate = fecha;
            loadTasksForDay(fecha);
        },
        onChange: function(selectedDates, dateStr) {
            selectedCalDate = dateStr;
            loadTasksForDay(dateStr);
        }
    });
} else {
    const miniCalendarContainer = document.getElementById('miniCalendar');
    if (miniCalendarContainer) {
        miniCalendarContainer.innerHTML = '<div class="alert alert-warning py-2 px-3 mb-0 small">No se pudo cargar el calendario en este momento.</div>';
    }
}

const tipoEventoIconos = {
    tarea: 'bi-check2-square', llamada: 'bi-telephone', visita: 'bi-house-door',
    reunion: 'bi-people', email: 'bi-envelope', personal: 'bi-person',
    otro: 'bi-calendar-event'
};

function loadTasksForDay(fecha) {
    if (!document.getElementById('miniCalTasks') || !document.getElementById('miniCalTaskList') || !document.getElementById('miniCalDate')) {
        return;
    }
    document.getElementById('miniCalTasks').style.display = 'block';
    document.getElementById('miniCalDate').textContent = new Date(fecha + 'T12:00:00').toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });
    document.getElementById('miniCalTaskList').innerHTML = '<div class="text-center"><span class="spinner-border spinner-border-sm"></span></div>';

    fetch(`${API_URL}?accion=tareas_dia&prospecto_id=${PROSPECTO_ID}&fecha=${fecha}`)
    .then(r => r.json())
    .then(data => {
        const list = document.getElementById('miniCalTaskList');
        const countBadge = document.getElementById('miniCalCount');
        if (!list) return;
        const eventos = (data && (data.eventos || data.tareas)) || [];
        if (countBadge) {
            if (eventos.length > 0) {
                countBadge.textContent = eventos.length + ' actividad' + (eventos.length !== 1 ? 'es' : '');
                countBadge.style.display = '';
            } else {
                countBadge.style.display = 'none';
            }
        }
        if (eventos.length > 0) {
            list.innerHTML = eventos.map(ev => {
                const color = ev.color || '#6b7280';
                const icono = tipoEventoIconos[ev.tipo] || 'bi-calendar-event';
                const hora = ev.hora || '';
                return `<div class="task-item" style="background:${color}15; border-left:3px solid ${color}">
                    <i class="bi ${icono}" style="color:${color}; flex-shrink:0;"></i>
                    <div style="min-width:0; overflow:hidden; flex:1;">
                        ${hora ? `<span class="fw-semibold me-1" style="color:${color}; font-size:0.75rem;">${hora}</span>` : ''}
                        <span style="font-size:0.8rem;">${ev.titulo}</span>
                    </div>
                </div>`;
            }).join('');
        } else {
            list.innerHTML = '<div class="text-muted small text-center py-2"><i class="bi bi-calendar-x"></i> Sin eventos este día</div>';
        }
    })
    .catch(() => {
        const list = document.getElementById('miniCalTaskList');
        if (list) list.innerHTML = '<div class="text-muted small text-center py-2"><i class="bi bi-exclamation-circle"></i> No se pudo cargar</div>';
    });
}

const btnSetProxContacto = document.getElementById('btnSetProxContacto');
if (btnSetProxContacto) btnSetProxContacto.addEventListener('click', function() {
    if (!selectedCalDate) return;
    const horaInput = document.getElementById('horaProxContacto');
    const hora = horaInput ? horaInput.value : '09:00';
    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'set_proximo_contacto');
    body.append('prospecto_id', PROSPECTO_ID);
    body.append('fecha', selectedCalDate);
    body.append('hora', hora);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
});

// ─────────────────────────────────
// HISTORIAL DE PROPIEDAD
// ─────────────────────────────────
const tiposHP = <?= json_encode($tiposHistorialPropiedad) ?>;
const precioTipos = ['subida_precio', 'bajada_precio'];

document.querySelectorAll('.prop-type-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.prop-type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('propTipoSeleccionado').value = this.dataset.tipo;
        const precioFields = document.getElementById('propPrecioFields');
        if (precioFields) {
            precioFields.style.setProperty('display', precioTipos.includes(this.dataset.tipo) ? 'flex' : 'none', 'important');
        }
    });
});

document.getElementById('btnAddPropHistorial').addEventListener('click', function() {
    const tipo = document.getElementById('propTipoSeleccionado').value;
    const descripcion = document.getElementById('propDescripcion').value.trim();
    const precioAnterior = document.getElementById('propPrecioAnterior').value;
    const precioNuevo = document.getElementById('propPrecioNuevo').value;

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'add_historial_propiedad');
    body.append('prospecto_id', PROSPECTO_ID);
    body.append('tipo', tipo);
    body.append('descripcion', descripcion);
    body.append('precio_anterior', precioAnterior);
    body.append('precio_nuevo', precioNuevo);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Registrar';
        if (data.success) {
            const e = data.entrada;
            const tc = tiposHP[e.tipo] || tiposHP['otro'];
            const empty = document.getElementById('emptyPropHistorial');
            if (empty) empty.remove();

            let precioHtml = '';
            if (e.precio_anterior || e.precio_nuevo) {
                precioHtml = `<span class="price-change" style="color:${tc.color};">`;
                if (e.precio_anterior) precioHtml += `${Number(e.precio_anterior).toLocaleString('es-ES')} €`;
                if (e.precio_anterior && e.precio_nuevo) precioHtml += ` <i class="bi bi-arrow-right"></i> `;
                if (e.precio_nuevo) precioHtml += `${Number(e.precio_nuevo).toLocaleString('es-ES')} €`;
                precioHtml += '</span>';
            }

            const html = `<div class="prop-hist-entry" data-id="${e.id}" style="animation:fadeIn 0.3s ease;">
                <div class="prop-hist-icon" style="background:${tc.color};"><i class="bi ${tc.icon}"></i></div>
                <div class="prop-hist-content">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-semibold small">${tc.label}</span>
                        ${precioHtml}
                        <button class="btn btn-sm btn-link text-danger p-0 ms-auto" onclick="deletePropHistorial(${e.id})" title="Eliminar"><i class="bi bi-x-lg"></i></button>
                    </div>
                    ${e.descripcion ? `<div class="small text-body-secondary mt-1">${e.descripcion.replace(/\n/g,'<br>')}</div>` : ''}
                    <div class="prop-hist-meta">${e.usuario_iniciales} ${e.usuario} &middot; ${e.fecha}</div>
                </div>
            </div>`;
            document.getElementById('propHistorialContainer').insertAdjacentHTML('afterbegin', html);
            document.getElementById('propDescripcion').value = '';
            document.getElementById('propPrecioAnterior').value = '';
            document.getElementById('propPrecioNuevo').value = '';
            const badge = document.querySelector('.card-header:has(#propHistorialContainer) .badge.bg-secondary, [data-prop-hist-badge]');
            // update badge count
            const headers = document.querySelectorAll('.card-header');
            headers.forEach(h => {
                if (h.textContent.includes('Historial de Propiedad')) {
                    const b = h.querySelector('.badge');
                    if (b) b.textContent = parseInt(b.textContent || '0') + 1;
                }
            });
        } else {
            alert('Error: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Registrar';
        alert('Error de conexión');
    });
});

function deletePropHistorial(id) {
    if (!confirm('¿Eliminar esta entrada del historial?')) return;
    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'delete_historial_propiedad');
    body.append('entrada_id', id);
    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.querySelector(`.prop-hist-entry[data-id="${id}"]`);
            if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
            document.querySelectorAll('.card-header').forEach(h => {
                if (h.textContent.includes('Historial de Propiedad')) {
                    const b = h.querySelector('.badge');
                    if (b) b.textContent = Math.max(0, parseInt(b.textContent) - 1);
                }
            });
        }
    });
}

// ─────────────────────────────────
// HISTORIAL DE CONTACTOS
// ─────────────────────────────────
const tipoConfig = <?= json_encode($tiposHistorial) ?>;

// Type selector
document.querySelectorAll('.contact-type-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.contact-type-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('historialTipo').value = this.dataset.tipo;
    });
});

// Add historial entry
document.getElementById('btnAddHistorial').addEventListener('click', addHistorialEntry);
document.getElementById('historialContenido').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        addHistorialEntry();
    }
});

function addHistorialEntry() {
    const contenido = document.getElementById('historialContenido').value.trim();
    const tipo = document.getElementById('historialTipo').value;
    if (!contenido) return;

    const btn = document.getElementById('btnAddHistorial');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'add_historial');
    body.append('prospecto_id', PROSPECTO_ID);
    body.append('contenido', contenido);
    body.append('tipo', tipo);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Añadir';

        if (data.success) {
            const e = data.entrada;
            const tc = tipoConfig[e.tipo] || tipoConfig['otro'];
            const empty = document.getElementById('emptyHistorial');
            if (empty) empty.remove();

            const html = `
            <div class="timeline-entry" data-id="${e.id}" style="animation: fadeIn 0.3s ease;">
                <div class="timeline-avatar" style="background: ${tc.color};">${e.usuario_iniciales}</div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="name">${e.usuario}</span>
                        <span class="tipo-badge" style="background: ${tc.color}20; color: ${tc.color};">
                            <i class="bi ${tc.icon}"></i> ${tc.label}
                        </span>
                        <span class="date">${e.fecha}</span>
                        <div class="timeline-actions ms-auto">
                            <button class="btn btn-sm btn-link text-secondary p-0 me-2 btn-edit-historial-fecha" data-id="${e.id}" data-fecha="${e.fecha_iso}" title="Editar fecha/hora">
                                <i class="bi bi-clock-history"></i>
                            </button>
                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteHistorial(${e.id})" title="Eliminar">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="timeline-text">${e.contenido.replace(/\n/g, '<br>')}</div>
                </div>
            </div>`;

            document.getElementById('timelineContainer').insertAdjacentHTML('afterbegin', html);
            document.getElementById('historialContenido').value = '';

            // Update badge count
            const badge = document.querySelector('.card-header .badge.bg-secondary');
            if (badge) badge.textContent = parseInt(badge.textContent) + 1;
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-lg"></i> Añadir';
        alert('Error de conexión');
    });
}

function deleteHistorial(id) {
    if (!confirm('¿Eliminar esta entrada?')) return;

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'delete_historial');
    body.append('entrada_id', id);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.querySelector(`.timeline-entry[data-id="${id}"]`);
            if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
            const badge = document.querySelector('.card-header .badge.bg-secondary');
            if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1);
        }
    });
}

function openHistorialFechaEditor(id, fechaActual) {
    if (!historialFechaInput) return;

    historialEditEntradaId = id;
    if (fechaActual) {
        historialFechaInput.value = fechaActual;
    } else {
        const now = new Date();
        now.setMinutes(now.getMinutes() - (now.getMinutes() % 5));
        historialFechaInput.value = now.toISOString().slice(0, 16);
    }
    openHistorialFechaModal();
}

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-edit-historial-fecha');
    if (!btn) return;
    e.preventDefault();
    const id = parseInt(btn.dataset.id || '0', 10);
    const fecha = btn.dataset.fecha || '';
    if (!id) return;
    openHistorialFechaEditor(id, fecha);
});

const btnCerrarHistorialFechaModal = document.getElementById('btnCerrarHistorialFechaModal');
if (btnCerrarHistorialFechaModal) btnCerrarHistorialFechaModal.addEventListener('click', closeHistorialFechaModal);
const btnCancelarHistorialFechaModal = document.getElementById('btnCancelarHistorialFechaModal');
if (btnCancelarHistorialFechaModal) btnCancelarHistorialFechaModal.addEventListener('click', closeHistorialFechaModal);

const btnGuardarHistorialFecha = document.getElementById('btnGuardarHistorialFecha');
if (btnGuardarHistorialFecha) btnGuardarHistorialFecha.addEventListener('click', function() {
    if (!historialEditEntradaId) return;
    if (!historialFechaInput) return;
    const valor = historialFechaInput.value.trim();
    if (!valor) {
        alert('Selecciona una fecha y hora.');
        return;
    }

    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    const body = new FormData();
    body.append('csrf_token', CSRF_TOKEN);
    body.append('accion', 'edit_historial_fecha');
    body.append('entrada_id', historialEditEntradaId);
    body.append('fecha_evento', valor);

    fetch(API_URL, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (data.success) {
            closeHistorialFechaModal();
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar la fecha'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert('Error de conexión');
    });
});
</script>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
