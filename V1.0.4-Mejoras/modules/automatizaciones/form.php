<?php
$pageTitle = 'Automatizacion';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));
$auto = null;
$acciones = [];

$templatePresets = [
    'bienvenida_cliente' => [
        'nombre' => 'Bienvenida nuevo cliente',
        'descripcion' => 'Envia un email de bienvenida y crea una tarea de seguimiento.',
        'trigger_tipo' => 'nuevo_cliente',
        'activo' => 1,
        'acciones' => [
            [
                'tipo' => 'enviar_email',
                'configuracion' => [
                    'destinatario' => 'cliente',
                    'asunto' => 'Bienvenido a nuestra inmobiliaria',
                    'mensaje' => 'Hola {{cliente_nombre}}, gracias por confiar en nosotros. Te contactaremos en breve.',
                ],
            ],
            [
                'tipo' => 'crear_tarea',
                'configuracion' => [
                    'titulo' => 'Seguimiento nuevo cliente',
                    'descripcion' => 'Contactar al cliente y relevar necesidad inicial.',
                    'prioridad' => 'media',
                    'asignar_a' => '',
                ],
            ],
        ],
    ],
    'post_visita' => [
        'nombre' => 'Post-visita',
        'descripcion' => 'Envia encuesta de satisfaccion al cliente luego de una visita realizada.',
        'trigger_tipo' => 'visita_realizada',
        'activo' => 1,
        'acciones' => [
            [
                'tipo' => 'enviar_email',
                'configuracion' => [
                    'destinatario' => 'cliente',
                    'asunto' => 'Como fue tu visita?',
                    'mensaje' => 'Hola {{cliente_nombre}}, gracias por tu tiempo. Queremos conocer tu experiencia de visita.',
                ],
            ],
        ],
    ],
    'tarea_vencida' => [
        'nombre' => 'Tarea vencida',
        'descripcion' => 'Notifica cuando una tarea supera la fecha limite.',
        'trigger_tipo' => 'tarea_vencida',
        'activo' => 1,
        'acciones' => [
            [
                'tipo' => 'notificar',
                'configuracion' => [
                    'titulo' => 'Tarea vencida detectada',
                    'mensaje' => 'Hay una tarea vencida que requiere atencion inmediata.',
                ],
            ],
        ],
    ],
];

$templateKey = trim((string) get('template'));
$selectedTemplate = (!$id && isset($templatePresets[$templateKey])) ? $templatePresets[$templateKey] : null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id = ?");
    $stmt->execute([$id]);
    $auto = $stmt->fetch();
    if (!$auto) {
        setFlash('danger', 'Automatizacion no encontrada.');
        header('Location: index.php');
        exit;
    }

    if (!isAdmin() && intval($auto['created_by']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar esta automatizacion.');
        header('Location: index.php');
        exit;
    }

    $pageTitle = 'Editar Automatizacion';

    $stmtAcc = $db->prepare("SELECT * FROM automatizacion_acciones WHERE automatizacion_id = ? ORDER BY orden ASC");
    $stmtAcc->execute([$id]);
    $acciones = $stmtAcc->fetchAll();
}

// Obtener usuarios para selects
$usuarios = $db->query("SELECT id, nombre, apellidos FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Obtener pipelines y etapas
$pipelines = $db->query("SELECT id, nombre FROM pipelines WHERE activo = 1 ORDER BY nombre")->fetchAll();
$etapas = $db->query("SELECT pe.id, pe.nombre, pe.pipeline_id FROM pipeline_etapas pe ORDER BY pe.orden")->fetchAll();

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nombre = post('nombre');
    $descripcion = post('descripcion');
    $trigger_tipo = post('trigger_tipo');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre) || empty($trigger_tipo)) {
        setFlash('danger', 'El nombre y el tipo de trigger son obligatorios.');
        header('Location: form.php' . ($id ? '?id=' . $id : ''));
        exit;
    }

    if ($id) {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT created_by FROM automatizaciones WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$id]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos para editar esta automatizacion.');
                header('Location: index.php');
                exit;
            }
        }

        $stmt = $db->prepare("UPDATE automatizaciones SET nombre = ?, descripcion = ?, trigger_tipo = ?, activo = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $trigger_tipo, $activo, $id]);
        registrarActividad('editar', 'automatizacion', $id, 'Automatizacion: ' . $nombre);
    } else {
        $stmt = $db->prepare("INSERT INTO automatizaciones (nombre, descripcion, trigger_tipo, activo, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $descripcion, $trigger_tipo, $activo, currentUserId()]);
        $id = $db->lastInsertId();
        registrarActividad('crear', 'automatizacion', $id, 'Automatizacion: ' . $nombre);
    }

    // Delete old actions and re-insert
    $db->prepare("DELETE FROM automatizacion_acciones WHERE automatizacion_id = ?")->execute([$id]);

    $tipos = $_POST['accion_tipo'] ?? [];
    $configs = $_POST['accion_config'] ?? [];

    foreach ($tipos as $i => $tipo) {
        if (empty($tipo)) continue;
        $config = $configs[$i] ?? '{}';
        // config comes as JSON string from hidden field
        $orden = $i;
        $stmt = $db->prepare("INSERT INTO automatizacion_acciones (automatizacion_id, orden, tipo, configuracion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, $orden, $tipo, $config]);
    }

    setFlash('success', 'Automatizacion guardada correctamente.');
    header('Location: index.php');
    exit;
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
];

$accionLabels = [
    'enviar_email' => 'Enviar email',
    'enviar_whatsapp' => 'Enviar WhatsApp',
    'crear_tarea' => 'Crear tarea',
    'cambiar_estado_propiedad' => 'Cambiar estado propiedad',
    'asignar_agente' => 'Asignar agente',
    'mover_pipeline' => 'Mover en pipeline',
    'notificar' => 'Crear notificacion',
    'esperar' => 'Esperar X horas',
];

$nombreValue = $auto['nombre'] ?? ($selectedTemplate['nombre'] ?? '');
$descripcionValue = $auto['descripcion'] ?? ($selectedTemplate['descripcion'] ?? '');
$triggerValue = $auto['trigger_tipo'] ?? ($selectedTemplate['trigger_tipo'] ?? '');
$activoValue = $auto ? intval($auto['activo']) : intval($selectedTemplate['activo'] ?? 1);
$initialActions = array_map(function ($a) {
    return ['tipo' => $a['tipo'], 'configuracion' => json_decode($a['configuracion'], true) ?: []];
}, $acciones);
if (empty($initialActions) && $selectedTemplate) {
    $initialActions = $selectedTemplate['acciones'];
}
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0"><?= $auto ? 'Editar' : 'Nueva' ?> Automatizacion</h5>
</div>

<form method="POST" id="formAutomatizacion">
    <?= csrfField() ?>

    <style>
        .merge-tags-panel {
            border: 1px solid rgba(99, 102, 241, 0.2);
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.06) 0%, rgba(99, 102, 241, 0.02) 100%);
            border-radius: 10px;
            padding: 10px;
            margin-top: 8px;
        }
        .merge-tags-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .merge-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .merge-tag-btn {
            border: 1px solid rgba(79, 70, 229, 0.25);
            background: #ffffff;
            color: #4338ca;
            border-radius: 999px;
            padding: 3px 9px;
            font-size: 0.75rem;
            line-height: 1.2;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .merge-tag-btn:hover {
            background: #4f46e5;
            color: #ffffff;
            border-color: #4f46e5;
        }
        .merge-tags-help {
            display: block;
            margin-top: 8px;
            font-size: 0.75rem;
            color: #64748b;
        }
        .merge-preview {
            margin-top: 8px;
            border: 1px dashed rgba(148, 163, 184, 0.7);
            background: rgba(248, 250, 252, 0.9);
            border-radius: 8px;
            padding: 8px 10px;
        }
        .merge-preview-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
        }
        .merge-preview-text {
            display: block;
            font-size: 0.82rem;
            color: #0f172a;
            white-space: pre-wrap;
            word-break: break-word;
            min-height: 18px;
        }
    </style>

    <!-- Step 1: Trigger -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent">
            <h6 class="mb-0"><i class="bi bi-lightning"></i> Paso 1: Trigger</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required maxlength="200"
                        value="<?= sanitize($nombreValue) ?>" placeholder="Nombre de la automatizacion">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tipo de trigger <span class="text-danger">*</span></label>
                    <select name="trigger_tipo" class="form-select" required>
                        <option value="">Seleccionar trigger...</option>
                        <?php foreach ($triggerLabels as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($triggerValue === $val) ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripcion opcional..."><?= sanitize($descripcionValue) ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1"
                            <?= $activoValue ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Automatizacion activa</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Actions -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-gear"></i> Paso 2: Acciones</h6>
            <button type="button" class="btn btn-sm btn-primary" onclick="addAction()">
                <i class="bi bi-plus-lg"></i> Anadir accion
            </button>
        </div>
        <div class="card-body">
            <div id="acciones-container">
                <?php if (empty($acciones)): ?>
                <p class="text-muted text-center py-3" id="no-acciones-msg">No hay acciones configuradas. Anade al menos una accion.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar Automatizacion</button>
    </div>
</form>

<!-- Template for action row -->
<template id="accion-template">
    <div class="accion-row border rounded p-3 mb-3 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-arrow-right-circle"></i> Accion <span class="accion-num"></span></h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAction(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Tipo de accion</label>
                <select class="form-select accion-tipo-select" onchange="updateConfigFields(this)">
                    <option value="">Seleccionar...</option>
                    <?php foreach ($accionLabels as $val => $label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="accion_tipo[]" class="accion-tipo-hidden" value="">
                <input type="hidden" name="accion_config[]" class="accion-config-hidden" value="{}">
            </div>
            <div class="col-md-8 config-fields">
                <p class="text-muted small mt-4">Selecciona un tipo de accion para ver los campos de configuracion.</p>
            </div>
        </div>
    </div>
</template>

<script>
// Data for selects
const usuarios = <?= json_encode($usuarios) ?>;
const pipelinesData = <?= json_encode($pipelines) ?>;
const etapasData = <?= json_encode($etapas) ?>;

// Existing actions for editing
const existingActions = <?= json_encode($initialActions) ?>;

let actionCount = 0;
const mergeSampleValues = {
    cliente_nombre: 'Juan Perez',
    cliente_email: 'juan.perez@email.com',
    cliente_telefono: '+34 600 111 222',
    agente_nombre: 'Mariano Coria',
    fecha_actual: new Date().toLocaleDateString('es-ES')
};

function renderMergeTagsPanel(targetKey) {
    const tags = [
        '{{cliente_nombre}}',
        '{{cliente_email}}',
        '{{cliente_telefono}}',
        '{{agente_nombre}}',
        '{{fecha_actual}}'
    ];

    return `
        <div class="merge-tags-panel">
            <div class="merge-tags-title">Variables dinamicas</div>
            <div class="merge-tags-list">
                ${tags.map(tag => `<button type="button" class="merge-tag-btn" data-target="${targetKey}" data-tag="${tag}">${tag}</button>`).join('')}
            </div>
            <small class="merge-tags-help">Hace clic en una variable para insertarla donde tengas el cursor.</small>
        </div>`;
}

function renderMergePreview(targetKey) {
    return `
        <div class="merge-preview" data-preview-for="${targetKey}">
            <small class="merge-preview-label">Vista previa</small>
            <span class="merge-preview-text">Escribe un mensaje para ver como se mostrara.</span>
        </div>`;
}

function applyMergePreview(text) {
    return String(text || '').replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (full, key) => {
        if (Object.prototype.hasOwnProperty.call(mergeSampleValues, key)) {
            return mergeSampleValues[key];
        }
        return full;
    });
}

function insertTextAtCursor(textarea, text) {
    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || 0;
    const before = textarea.value.substring(0, start);
    const after = textarea.value.substring(end);
    textarea.value = before + text + after;
    const nextPos = start + text.length;
    textarea.setSelectionRange(nextPos, nextPos);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function addAction(tipo = '', config = {}) {
    const container = document.getElementById('acciones-container');
    const noMsg = document.getElementById('no-acciones-msg');
    if (noMsg) noMsg.remove();

    const template = document.getElementById('accion-template');
    const clone = template.content.cloneNode(true);

    actionCount++;
    clone.querySelector('.accion-num').textContent = actionCount;

    if (tipo) {
        clone.querySelector('.accion-tipo-select').value = tipo;
        clone.querySelector('.accion-tipo-hidden').value = tipo;
        clone.querySelector('.accion-config-hidden').value = JSON.stringify(config);
    }

    container.appendChild(clone);

    if (tipo) {
        const rows = container.querySelectorAll('.accion-row');
        const lastRow = rows[rows.length - 1];
        const select = lastRow.querySelector('.accion-tipo-select');
        updateConfigFields(select, config);
    }
}

function removeAction(btn) {
    btn.closest('.accion-row').remove();
    renumberActions();
}

function renumberActions() {
    const rows = document.querySelectorAll('.accion-row');
    actionCount = rows.length;
    rows.forEach((row, i) => {
        row.querySelector('.accion-num').textContent = i + 1;
    });
}

function updateConfigFields(select, existingConfig = null) {
    const row = select.closest('.accion-row');
    const configContainer = row.querySelector('.config-fields');
    const tipoHidden = row.querySelector('.accion-tipo-hidden');
    const tipo = select.value;
    tipoHidden.value = tipo;

    if (!tipo) {
        configContainer.innerHTML = '<p class="text-muted small mt-4">Selecciona un tipo de accion para ver los campos de configuracion.</p>';
        return;
    }

    let html = '';
    const cfg = existingConfig || {};

    switch (tipo) {
        case 'enviar_email':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Destinatario</label>
                    <select class="form-select form-select-sm cfg-field" data-key="destinatario">
                        <option value="agente_asignado" ${cfg.destinatario === 'agente_asignado' ? 'selected' : ''}>Agente asignado</option>
                        <option value="cliente" ${cfg.destinatario === 'cliente' ? 'selected' : ''}>Cliente</option>
                        <option value="email_custom" ${cfg.destinatario === 'email_custom' ? 'selected' : ''}>Email personalizado</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Asunto</label>
                    <input type="text" class="form-control form-control-sm cfg-field" data-key="asunto" value="${sanitizeAttr(cfg.asunto || '')}">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Mensaje</label>
                    <textarea class="form-control form-control-sm cfg-field" data-key="mensaje" rows="2">${escapeHtml(cfg.mensaje || '')}</textarea>
                    ${renderMergeTagsPanel('mensaje')}
                    ${renderMergePreview('mensaje')}
                </div>`;
            break;
        case 'enviar_whatsapp':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Destinatario</label>
                    <select class="form-select form-select-sm cfg-field" data-key="destinatario">
                        <option value="agente_asignado" ${cfg.destinatario === 'agente_asignado' ? 'selected' : ''}>Agente asignado</option>
                        <option value="cliente" ${cfg.destinatario === 'cliente' ? 'selected' : ''}>Cliente</option>
                        <option value="telefono_custom" ${cfg.destinatario === 'telefono_custom' ? 'selected' : ''}>Telefono personalizado</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Telefono (si es personalizado)</label>
                    <input type="text" class="form-control form-control-sm cfg-field" data-key="telefono" value="${sanitizeAttr(cfg.telefono || '')}" placeholder="Ej: 34600111222">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Plantilla de mensaje</label>
                    <textarea class="form-control form-control-sm cfg-field" data-key="mensaje_template" rows="2">${escapeHtml(cfg.mensaje_template || '')}</textarea>
                    ${renderMergeTagsPanel('mensaje_template')}
                    ${renderMergePreview('mensaje_template')}
                </div>`;
            break;
        case 'crear_tarea':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Titulo</label>
                    <input type="text" class="form-control form-control-sm cfg-field" data-key="titulo" value="${sanitizeAttr(cfg.titulo || '')}">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Descripcion</label>
                    <textarea class="form-control form-control-sm cfg-field" data-key="descripcion" rows="2">${escapeHtml(cfg.descripcion || '')}</textarea>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Prioridad</label>
                        <select class="form-select form-select-sm cfg-field" data-key="prioridad">
                            <option value="baja" ${cfg.prioridad === 'baja' ? 'selected' : ''}>Baja</option>
                            <option value="media" ${cfg.prioridad === 'media' ? 'selected' : ''}>Media</option>
                            <option value="alta" ${cfg.prioridad === 'alta' ? 'selected' : ''}>Alta</option>
                            <option value="urgente" ${cfg.prioridad === 'urgente' ? 'selected' : ''}>Urgente</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Asignar a</label>
                        <select class="form-select form-select-sm cfg-field" data-key="asignar_a">
                            <option value="">Sin asignar</option>
                            ${usuarios.map(u => `<option value="${u.id}" ${cfg.asignar_a == u.id ? 'selected' : ''}>${escapeHtml(u.nombre + ' ' + u.apellidos)}</option>`).join('')}
                        </select>
                    </div>
                </div>`;
            break;
        case 'cambiar_estado_propiedad':
            html = `
                <div class="mb-2">
                    <label class="form-label small">ID Propiedad</label>
                    <input type="number" class="form-control form-control-sm cfg-field" data-key="propiedad_id" min="1" value="${sanitizeAttr(cfg.propiedad_id || '')}" placeholder="Ej: 15">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Nuevo estado</label>
                    <select class="form-select form-select-sm cfg-field" data-key="nuevo_estado">
                        <option value="disponible" ${cfg.nuevo_estado === 'disponible' ? 'selected' : ''}>Disponible</option>
                        <option value="reservada" ${cfg.nuevo_estado === 'reservada' ? 'selected' : ''}>Reservada</option>
                        <option value="vendida" ${cfg.nuevo_estado === 'vendida' ? 'selected' : ''}>Vendida</option>
                        <option value="alquilada" ${cfg.nuevo_estado === 'alquilada' ? 'selected' : ''}>Alquilada</option>
                        <option value="retirada" ${cfg.nuevo_estado === 'retirada' ? 'selected' : ''}>Retirada</option>
                    </select>
                </div>`;
            break;
        case 'asignar_agente':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Entidad</label>
                    <select class="form-select form-select-sm cfg-field" data-key="entidad">
                        <option value="cliente" ${cfg.entidad === 'cliente' ? 'selected' : ''}>Cliente</option>
                        <option value="propiedad" ${cfg.entidad === 'propiedad' ? 'selected' : ''}>Propiedad</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Agente</label>
                    <select class="form-select form-select-sm cfg-field" data-key="agente_id">
                        <option value="">Seleccionar agente...</option>
                        ${usuarios.map(u => `<option value="${u.id}" ${cfg.agente_id == u.id ? 'selected' : ''}>${escapeHtml(u.nombre + ' ' + u.apellidos)}</option>`).join('')}
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">ID Cliente</label>
                        <input type="number" class="form-control form-control-sm cfg-field" data-key="cliente_id" min="1" value="${sanitizeAttr(cfg.cliente_id || '')}">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">ID Propiedad</label>
                        <input type="number" class="form-control form-control-sm cfg-field" data-key="propiedad_id" min="1" value="${sanitizeAttr(cfg.propiedad_id || '')}">
                    </div>
                </div>`;
            break;
        case 'mover_pipeline':
            html = `
                <div class="mb-2">
                    <label class="form-label small">ID Item Pipeline</label>
                    <input type="number" class="form-control form-control-sm cfg-field" data-key="pipeline_item_id" min="1" value="${sanitizeAttr(cfg.pipeline_item_id || '')}" placeholder="Ej: 42">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Pipeline</label>
                        <select class="form-select form-select-sm cfg-field" data-key="pipeline_id" onchange="updateEtapas(this)">
                            <option value="">Seleccionar...</option>
                            ${pipelinesData.map(p => `<option value="${p.id}" ${cfg.pipeline_id == p.id ? 'selected' : ''}>${escapeHtml(p.nombre)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Etapa</label>
                        <select class="form-select form-select-sm cfg-field etapa-select" data-key="etapa_id">
                            <option value="">Seleccionar...</option>
                            ${etapasData.filter(e => cfg.pipeline_id && e.pipeline_id == cfg.pipeline_id).map(e => `<option value="${e.id}" ${cfg.etapa_id == e.id ? 'selected' : ''}>${escapeHtml(e.nombre)}</option>`).join('')}
                        </select>
                    </div>
                </div>`;
            break;
        case 'notificar':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Titulo</label>
                    <input type="text" class="form-control form-control-sm cfg-field" data-key="titulo" value="${sanitizeAttr(cfg.titulo || '')}">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Mensaje</label>
                    <textarea class="form-control form-control-sm cfg-field" data-key="mensaje" rows="2">${escapeHtml(cfg.mensaje || '')}</textarea>
                </div>`;
            break;
        case 'esperar':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Horas de espera</label>
                    <input type="number" class="form-control form-control-sm cfg-field" data-key="horas" min="1" max="720" value="${cfg.horas || 1}">
                </div>`;
            break;
    }

    configContainer.innerHTML = html;

    // Attach change listeners to config fields
    configContainer.querySelectorAll('.cfg-field').forEach(field => {
        field.addEventListener('change', () => updateConfigHidden(row));
        field.addEventListener('input', () => updateConfigHidden(row));
    });

    updateMessagePreview(row);
    updateConfigHidden(row);
}

function updateEtapas(select) {
    const pipelineId = select.value;
    const row = select.closest('.accion-row');
    const etapaSelect = row.querySelector('.etapa-select');
    if (!etapaSelect) return;

    let options = '<option value="">Seleccionar...</option>';
    etapasData.filter(e => e.pipeline_id == pipelineId).forEach(e => {
        options += `<option value="${e.id}">${escapeHtml(e.nombre)}</option>`;
    });
    etapaSelect.innerHTML = options;
    updateConfigHidden(row);
}

function updateConfigHidden(row) {
    const configHidden = row.querySelector('.accion-config-hidden');
    const fields = row.querySelectorAll('.cfg-field');
    const config = {};
    fields.forEach(f => {
        const key = f.dataset.key;
        if (key) config[key] = f.value;
    });
    configHidden.value = JSON.stringify(config);
    updateMessagePreview(row);
}

function updateMessagePreview(row) {
    const previews = row.querySelectorAll('.merge-preview[data-preview-for]');
    previews.forEach(preview => {
        const key = preview.dataset.previewFor;
        const sourceField = row.querySelector(`.cfg-field[data-key="${key}"]`);
        const textEl = preview.querySelector('.merge-preview-text');
        if (!textEl) return;

        const rawText = sourceField ? String(sourceField.value || '') : '';
        textEl.textContent = rawText.trim() ? applyMergePreview(rawText) : 'Escribe un mensaje para ver como se mostrara.';
    });
}

function sanitizeAttr(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML.replace(/"/g, '&quot;');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Before submit, update all config hidden fields
document.getElementById('formAutomatizacion').addEventListener('submit', function() {
    document.querySelectorAll('.accion-row').forEach(row => updateConfigHidden(row));
});

document.addEventListener('click', function(event) {
    const btn = event.target.closest('.merge-tag-btn');
    if (!btn) return;

    const row = btn.closest('.accion-row');
    if (!row) return;

    const targetKey = btn.dataset.target;
    const tag = btn.dataset.tag || '';
    const targetField = row.querySelector(`.cfg-field[data-key="${targetKey}"]`);
    if (!targetField) return;

    insertTextAtCursor(targetField, tag);
    updateConfigHidden(row);
});

// Initialize existing actions
document.addEventListener('DOMContentLoaded', function() {
    existingActions.forEach(a => addAction(a.tipo, a.configuracion));
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
