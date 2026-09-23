<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

// CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');
    $wfId = intval(post('wf_id'));

    if ($accion === 'guardar') {
        $id = intval(post('workflow_id'));
        $nombre = trim(post('nombre'));
        $descripcion = trim(post('descripcion'));
        $trigger_tipo = post('trigger_tipo', 'manual');
        $nodos = $_POST['nodos_json'] ?? '[]';
        $conexiones = $_POST['conexiones_json'] ?? '[]';

        if (empty($nombre)) { setFlash('danger', 'Nombre obligatorio.'); header('Location: workflows.php'); exit; }

        if ($id) {
            if (!isAdmin()) {
                $ownerStmt = $db->prepare("SELECT usuario_id FROM workflows WHERE id = ? LIMIT 1");
                $ownerStmt->execute([$id]);
                $ownerId = intval($ownerStmt->fetchColumn());
                if ($ownerId !== intval(currentUserId())) {
                    setFlash('danger', 'No tienes permisos sobre este workflow.');
                    header('Location: workflows.php');
                    exit;
                }
            }
            $db->prepare("UPDATE workflows SET nombre=?, descripcion=?, trigger_tipo=?, nodos=?, conexiones=?, updated_at=NOW() WHERE id=?")
                ->execute([$nombre, $descripcion, $trigger_tipo, $nodos, $conexiones, $id]);
        } else {
            $db->prepare("INSERT INTO workflows (nombre, descripcion, trigger_tipo, nodos, conexiones, usuario_id) VALUES (?,?,?,?,?,?)")
                ->execute([$nombre, $descripcion, $trigger_tipo, $nodos, $conexiones, currentUserId()]);
            $id = $db->lastInsertId();
        }
        setFlash('success', 'Workflow guardado.');
        header('Location: workflows.php?editar=' . $id);
        exit;
    }

    if ($accion === 'toggle') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM workflows WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$wfId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre este workflow.');
                header('Location: workflows.php');
                exit;
            }
        }
        $db->prepare("UPDATE workflows SET activo = NOT activo WHERE id = ?")->execute([$wfId]);
        header('Location: workflows.php'); exit;
    }

    if ($accion === 'eliminar') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM workflows WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$wfId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre este workflow.');
                header('Location: workflows.php');
                exit;
            }
        }
        $db->prepare("DELETE FROM workflows WHERE id = ?")->execute([$wfId]);
        setFlash('success', 'Workflow eliminado.');
        header('Location: workflows.php'); exit;
    }
}

$editarId = intval(get('editar'));
$workflow = null;
if ($editarId) {
    $stmt = $db->prepare("SELECT * FROM workflows WHERE id = ?");
    $stmt->execute([$editarId]);
    $workflow = $stmt->fetch();

    if ($workflow && !isAdmin() && intval($workflow['usuario_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar este workflow.');
        header('Location: workflows.php');
        exit;
    }
}

$pageTitle = $workflow ? 'Editor de Workflow' : 'Workflows Visuales';
require_once __DIR__ . '/../../includes/header.php';

if ($workflow):
    $nodos = json_decode($workflow['nodos'], true) ?: [];
    $conexiones = json_decode($workflow['conexiones'], true) ?: [];
?>

<form method="POST" id="wfForm">
    <?= csrfField() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="workflow_id" value="<?= $workflow['id'] ?>">
    <input type="hidden" name="nodos_json" id="nodosJson">
    <input type="hidden" name="conexiones_json" id="conexionesJson">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="workflows.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Guardar</button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><input type="text" name="nombre" class="form-control" placeholder="Nombre del workflow" required value="<?= sanitize($workflow['nombre']) ?>"></div>
        <div class="col-md-4"><input type="text" name="descripcion" class="form-control" placeholder="Descripcion" value="<?= sanitize($workflow['descripcion']) ?>"></div>
        <div class="col-md-4">
            <select name="trigger_tipo" class="form-select">
                <option value="manual" <?= $workflow['trigger_tipo']==='manual'?'selected':'' ?>>Manual</option>
                <option value="nuevo_cliente" <?= $workflow['trigger_tipo']==='nuevo_cliente'?'selected':'' ?>>Nuevo cliente</option>
                <option value="nueva_visita" <?= $workflow['trigger_tipo']==='nueva_visita'?'selected':'' ?>>Nueva visita</option>
                <option value="visita_realizada" <?= $workflow['trigger_tipo']==='visita_realizada'?'selected':'' ?>>Visita realizada</option>
                <option value="pipeline_cambio" <?= $workflow['trigger_tipo']==='pipeline_cambio'?'selected':'' ?>>Cambio de pipeline</option>
                <option value="formulario_enviado" <?= $workflow['trigger_tipo']==='formulario_enviado'?'selected':'' ?>>Formulario enviado</option>
                <option value="tag_asignado" <?= $workflow['trigger_tipo']==='tag_asignado'?'selected':'' ?>>Tag asignado</option>
            </select>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-diagram-3"></i> Editor Visual</h6>
        <div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus"></i> Agregar Nodo</button>
            <ul class="dropdown-menu">
                <li><h6 class="dropdown-header">Acciones</h6></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('enviar_email');return false"><i class="bi bi-envelope text-primary"></i> Enviar Email</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('enviar_sms');return false"><i class="bi bi-phone text-success"></i> Enviar SMS</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('enviar_whatsapp');return false"><i class="bi bi-whatsapp text-success"></i> Enviar WhatsApp</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('crear_tarea');return false"><i class="bi bi-check2-square text-warning"></i> Crear Tarea</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('asignar_tag');return false"><i class="bi bi-tag text-info"></i> Asignar Tag</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('notificacion');return false"><i class="bi bi-bell text-danger"></i> Notificacion</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('webhook');return false"><i class="bi bi-globe text-secondary"></i> Webhook</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Control</h6></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('esperar');return false"><i class="bi bi-clock text-muted"></i> Esperar</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('condicion');return false"><i class="bi bi-signpost-split text-purple"></i> Condicion If/Else</a></li>
                <li><a class="dropdown-item" href="#" onclick="addNode('mover_pipeline');return false"><i class="bi bi-kanban text-primary"></i> Mover en Pipeline</a></li>
            </ul>
        </div>
    </div>
    <div class="card-body p-0" style="min-height:500px;position:relative;overflow:auto;background:#f8fafc url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2220%22 height=%2220%22><circle cx=%221%22 cy=%221%22 r=%220.5%22 fill=%22%23e2e8f0%22/></svg>') repeat;">
        <div id="wfCanvas" style="position:relative;min-height:500px;width:100%;">
            <svg id="wfSvg" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;"></svg>
            <div id="wfNodes" style="position:relative;z-index:2;"></div>
        </div>
    </div>
</div>

<style>
.wf-node {
    position: absolute;
    width: 220px;
    background: #fff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 0;
    cursor: move;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: box-shadow 0.2s;
    user-select: none;
}
.wf-node:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
.wf-node.selected { border-color: #3b82f6; }
.wf-node-header {
    padding: 10px 14px;
    border-radius: 10px 10px 0 0;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
}
.wf-node-body { padding: 10px 14px; font-size: 0.8rem; }
.wf-node-body input, .wf-node-body select, .wf-node-body textarea {
    font-size: 0.8rem;
    padding: 4px 8px;
}
.wf-connector {
    width: 14px; height: 14px;
    background: #94a3b8;
    border: 2px solid #fff;
    border-radius: 50%;
    position: absolute;
    cursor: crosshair;
    z-index: 5;
}
.wf-connector.out { bottom: -7px; left: 50%; transform: translateX(-50%); }
.wf-connector.in { top: -7px; left: 50%; transform: translateX(-50%); }
.wf-connector:hover { background: #3b82f6; transform: translateX(-50%) scale(1.3); }
</style>

<script>
let nodes = <?= json_encode($nodos) ?>;
let connections = <?= json_encode($conexiones) ?>;
let nodeIdCounter = nodes.length ? Math.max(...nodes.map(n => parseInt(n.id.replace('n','')))) + 1 : 1;
let dragging = null, dragOffset = {x:0,y:0};
let connecting = null;

const nodeTypes = {
    enviar_email:    {label:'Enviar Email', icon:'bi-envelope', color:'#3b82f6', fields:[{key:'plantilla',label:'Plantilla/Asunto',type:'text'},{key:'mensaje',label:'Mensaje',type:'textarea'}]},
    enviar_sms:      {label:'Enviar SMS', icon:'bi-phone', color:'#10b981', fields:[{key:'mensaje',label:'Mensaje',type:'textarea'}]},
    enviar_whatsapp: {label:'Enviar WhatsApp', icon:'bi-whatsapp', color:'#25d366', fields:[{key:'mensaje',label:'Mensaje',type:'textarea'}]},
    crear_tarea:     {label:'Crear Tarea', icon:'bi-check2-square', color:'#f59e0b', fields:[{key:'titulo',label:'Titulo tarea',type:'text'},{key:'dias',label:'Dias para vencer',type:'number'}]},
    asignar_tag:     {label:'Asignar Tag', icon:'bi-tag', color:'#06b6d4', fields:[{key:'tag',label:'Nombre del tag',type:'text'}]},
    notificacion:    {label:'Notificacion', icon:'bi-bell', color:'#ef4444', fields:[{key:'titulo',label:'Titulo',type:'text'},{key:'mensaje',label:'Mensaje',type:'text'}]},
    webhook:         {label:'Webhook', icon:'bi-globe', color:'#6b7280', fields:[{key:'url',label:'URL',type:'text'},{key:'metodo',label:'Metodo',type:'select',options:['POST','GET','PUT']}]},
    esperar:         {label:'Esperar', icon:'bi-clock', color:'#94a3b8', fields:[{key:'minutos',label:'Minutos',type:'number'}]},
    condicion:       {label:'Condicion', icon:'bi-signpost-split', color:'#8b5cf6', fields:[{key:'campo',label:'Campo',type:'text'},{key:'operador',label:'Operador',type:'select',options:['igual','no_igual','contiene','mayor','menor']},{key:'valor',label:'Valor',type:'text'}]},
    mover_pipeline:  {label:'Mover Pipeline', icon:'bi-kanban', color:'#0ea5e9', fields:[{key:'pipeline',label:'Pipeline',type:'text'},{key:'etapa',label:'Etapa',type:'text'}]}
};

function addNode(type) {
    const id = 'n' + nodeIdCounter++;
    const existingCount = nodes.length;
    nodes.push({
        id: id,
        type: type,
        x: 40 + (existingCount % 3) * 260,
        y: 60 + Math.floor(existingCount / 3) * 180,
        config: {}
    });
    render();
}

function render() {
    const container = document.getElementById('wfNodes');
    container.innerHTML = '';

    nodes.forEach((node, idx) => {
        const nt = nodeTypes[node.type] || {label:node.type, icon:'bi-circle', color:'#6b7280', fields:[]};
        const div = document.createElement('div');
        div.className = 'wf-node';
        div.id = node.id;
        div.style.left = node.x + 'px';
        div.style.top = node.y + 'px';

        let fieldsHtml = '';
        nt.fields.forEach(f => {
            const val = node.config[f.key] || '';
            if (f.type === 'textarea') {
                fieldsHtml += `<textarea class="form-control form-control-sm mb-1" placeholder="${f.label}" data-field="${f.key}" rows="2">${esc(val)}</textarea>`;
            } else if (f.type === 'select') {
                fieldsHtml += `<select class="form-select form-select-sm mb-1" data-field="${f.key}">${(f.options||[]).map(o=>`<option value="${o}" ${val===o?'selected':''}>${o}</option>`).join('')}</select>`;
            } else {
                fieldsHtml += `<input type="${f.type||'text'}" class="form-control form-control-sm mb-1" placeholder="${f.label}" data-field="${f.key}" value="${esc(val)}">`;
            }
        });

        div.innerHTML = `
            <div class="wf-connector in" data-node="${node.id}" data-dir="in"></div>
            <div class="wf-node-header" style="background:${nt.color}">
                <span><i class="bi ${nt.icon} me-1"></i> ${nt.label}</span>
                <a href="#" class="text-white" onclick="removeNode('${node.id}');return false"><i class="bi bi-x-lg"></i></a>
            </div>
            <div class="wf-node-body">${fieldsHtml}</div>
            <div class="wf-connector out" data-node="${node.id}" data-dir="out"></div>
        `;

        // Drag
        div.addEventListener('mousedown', function(e) {
            if (e.target.closest('.wf-connector') || e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT' || e.target.tagName === 'A') return;
            dragging = node.id;
            const rect = div.getBoundingClientRect();
            dragOffset.x = e.clientX - rect.left;
            dragOffset.y = e.clientY - rect.top;
            e.preventDefault();
        });

        // Field changes
        div.querySelectorAll('[data-field]').forEach(input => {
            input.addEventListener('change', function() {
                node.config[this.dataset.field] = this.value;
            });
            input.addEventListener('input', function() {
                node.config[this.dataset.field] = this.value;
            });
        });

        // Connectors
        div.querySelectorAll('.wf-connector').forEach(conn => {
            conn.addEventListener('mousedown', function(e) {
                e.stopPropagation();
                if (this.dataset.dir === 'out') {
                    connecting = {from: node.id};
                }
            });
            conn.addEventListener('mouseup', function(e) {
                e.stopPropagation();
                if (connecting && this.dataset.dir === 'in' && connecting.from !== node.id) {
                    const exists = connections.find(c => c.from === connecting.from && c.to === node.id);
                    if (!exists) {
                        connections.push({from: connecting.from, to: node.id});
                        drawConnections();
                    }
                }
                connecting = null;
            });
        });

        container.appendChild(div);
    });

    drawConnections();
}

function removeNode(id) {
    nodes = nodes.filter(n => n.id !== id);
    connections = connections.filter(c => c.from !== id && c.to !== id);
    render();
}

function drawConnections() {
    const svg = document.getElementById('wfSvg');
    svg.innerHTML = '';
    const canvas = document.getElementById('wfCanvas');
    const canvasRect = canvas.getBoundingClientRect();

    connections.forEach((conn, idx) => {
        const fromEl = document.getElementById(conn.from);
        const toEl = document.getElementById(conn.to);
        if (!fromEl || !toEl) return;

        const fromConn = fromEl.querySelector('.wf-connector.out');
        const toConn = toEl.querySelector('.wf-connector.in');
        const fromRect = fromConn.getBoundingClientRect();
        const toRect = toConn.getBoundingClientRect();

        const x1 = fromRect.left - canvasRect.left + 7;
        const y1 = fromRect.top - canvasRect.top + 7;
        const x2 = toRect.left - canvasRect.left + 7;
        const y2 = toRect.top - canvasRect.top + 7;

        const midY = (y1 + y2) / 2;
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M${x1},${y1} C${x1},${midY} ${x2},${midY} ${x2},${y2}`);
        path.setAttribute('stroke', '#94a3b8');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        path.setAttribute('marker-end', 'url(#arrowhead)');
        path.style.pointerEvents = 'stroke';
        path.style.cursor = 'pointer';
        path.addEventListener('dblclick', function() {
            connections.splice(idx, 1);
            drawConnections();
        });
        svg.appendChild(path);
    });

    // Arrowhead marker
    if (!svg.querySelector('#arrowhead')) {
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        defs.innerHTML = '<marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto"><polygon points="0 0, 10 3.5, 0 7" fill="#94a3b8"/></marker>';
        svg.prepend(defs);
    }
}

document.addEventListener('mousemove', function(e) {
    if (!dragging) return;
    const canvas = document.getElementById('wfCanvas');
    const canvasRect = canvas.getBoundingClientRect();
    const node = nodes.find(n => n.id === dragging);
    if (!node) return;
    node.x = Math.max(0, e.clientX - canvasRect.left - dragOffset.x);
    node.y = Math.max(0, e.clientY - canvasRect.top - dragOffset.y);
    const el = document.getElementById(dragging);
    if (el) {
        el.style.left = node.x + 'px';
        el.style.top = node.y + 'px';
    }
    drawConnections();
});

document.addEventListener('mouseup', function() {
    dragging = null;
    connecting = null;
});

function esc(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML.replace(/"/g,'&quot;'); }

document.getElementById('wfForm').addEventListener('submit', function() {
    document.getElementById('nodosJson').value = JSON.stringify(nodes);
    document.getElementById('conexionesJson').value = JSON.stringify(connections);
});

render();
</script>

<?php else:
    // List workflows
    if (isAdmin()) {
        $workflows = $db->query("SELECT w.*, u.nombre as creador FROM workflows w LEFT JOIN usuarios u ON w.usuario_id = u.id ORDER BY w.updated_at DESC")->fetchAll();
    } else {
        $stmtWfList = $db->prepare("SELECT w.*, u.nombre as creador FROM workflows w LEFT JOIN usuarios u ON w.usuario_id = u.id WHERE w.usuario_id = ? ORDER BY w.updated_at DESC");
        $stmtWfList->execute([intval(currentUserId())]);
        $workflows = $stmtWfList->fetchAll();
    }
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Automatizaciones</a>
    </div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="nombre" value="Nuevo Workflow">
        <input type="hidden" name="descripcion" value="">
        <input type="hidden" name="trigger_tipo" value="manual">
        <input type="hidden" name="nodos_json" value="[]">
        <input type="hidden" name="conexiones_json" value="[]">
        <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo Workflow</button>
    </form>
</div>

<?php if (empty($workflows)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-diagram-3 fs-1 d-block mb-3"></i>
    <h5>No hay workflows</h5>
    <p>Crea flujos de trabajo visuales con nodos arrastrables.</p>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($workflows as $w): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($w['nombre']) ?></h6>
                    <span class="badge bg-<?= $w['activo'] ? 'success' : 'secondary' ?>"><?= $w['activo'] ? 'Activo' : 'Inactivo' ?></span>
                </div>
                <?php if ($w['descripcion']): ?><p class="text-muted small mb-2"><?= sanitize($w['descripcion']) ?></p><?php endif; ?>
                <div class="small text-muted mb-2">
                    <i class="bi bi-lightning"></i> <?= ucfirst(str_replace('_',' ',$w['trigger_tipo'])) ?>
                    &middot; <?= count(json_decode($w['nodos'],true)?:[]) ?> nodos
                    &middot; <?= $w['ejecuciones'] ?> ejecuciones
                </div>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <a href="workflows.php?editar=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                <div class="d-flex gap-1">
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="wf_id" value="<?= $w['id'] ?>">
                        <button class="btn btn-sm btn-outline-<?= $w['activo']?'warning':'success' ?>"><?= $w['activo']?'Desactivar':'Activar' ?></button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Eliminar workflow?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="wf_id" value="<?= $w['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
