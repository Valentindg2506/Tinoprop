<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$enc = $db->prepare("SELECT * FROM encuestas WHERE id=?"); $enc->execute([$id]); $enc=$enc->fetch();
if (!$enc) { setFlash('danger','No encontrada.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $db->prepare("UPDATE encuestas SET nombre=?, descripcion=?, preguntas=?, color_primario=?, crear_cliente=? WHERE id=?")
        ->execute([trim(post('nombre')), trim(post('descripcion')), $_POST['preguntas_json'] ?? '[]', post('color_primario','#10b981'), intval(post('crear_cliente')), $id]);
    setFlash('success','Encuesta guardada.');
    header('Location: form.php?id='.$id); exit;
}

$pageTitle = 'Editar Encuesta';
require_once __DIR__ . '/../../includes/header.php';
$preguntas = json_decode($enc['preguntas'], true) ?: [];
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>

<form method="POST" id="encForm">
    <?= csrfField() ?>
    <input type="hidden" name="preguntas_json" id="preguntasJson">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" value="<?= sanitize($enc['nombre']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Descripcion</label><input type="text" name="descripcion" class="form-control" value="<?= sanitize($enc['descripcion']) ?>"></div>
                <div class="col-md-2"><label class="form-label">Color</label><input type="color" name="color_primario" class="form-control form-control-color" value="<?= $enc['color_primario'] ?>"></div>
                <div class="col-md-2"><label class="form-label">Crear cliente</label><select name="crear_cliente" class="form-select"><option value="0" <?= !$enc['crear_cliente']?'selected':'' ?>>No</option><option value="1" <?= $enc['crear_cliente']?'selected':'' ?>>Si</option></select></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-list-ol"></i> Preguntas</h6>
            <button type="button" class="btn btn-sm btn-primary" onclick="addPregunta()"><i class="bi bi-plus"></i> Pregunta</button>
        </div>
        <div class="card-body" id="preguntasContainer"></div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Encuesta</button>
    <a href="<?= APP_URL ?>/encuesta.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-info"><i class="bi bi-eye"></i> Vista previa</a>
</form>

<script>
let preguntas = <?= json_encode($preguntas) ?>;

const tipoPreguntas = {
    texto: 'Texto libre',
    opcion_unica: 'Opcion unica',
    opcion_multiple: 'Opcion multiple',
    escala: 'Escala (1-10)',
    nps: 'NPS (0-10)',
    si_no: 'Si/No',
    fecha: 'Fecha',
    email: 'Email',
    telefono: 'Telefono'
};

function addPregunta() {
    preguntas.push({pregunta:'', tipo:'texto', requerida:true, opciones:[], puntos:{}, condicion_mostrar:null});
    render();
}

function render() {
    const c = document.getElementById('preguntasContainer');
    c.innerHTML = '';
    preguntas.forEach((p, i) => {
        let opcionesHtml = '';
        if (['opcion_unica','opcion_multiple'].includes(p.tipo)) {
            opcionesHtml = '<div class="ms-3 mt-2"><label class="form-label small">Opciones (una por linea)</label>';
            opcionesHtml += '<textarea class="form-control form-control-sm" rows="3" onchange="updateOpciones('+i+',this.value)">'+(p.opciones||[]).join('\n')+'</textarea>';
            opcionesHtml += '<div class="mt-1"><label class="form-label small">Puntos por opcion (opcion:puntos, uno por linea)</label>';
            opcionesHtml += '<textarea class="form-control form-control-sm" rows="2" onchange="updatePuntos('+i+',this.value)">'+Object.entries(p.puntos||{}).map(([k,v])=>k+':'+v).join('\n')+'</textarea></div></div>';
        }
        if (['escala','nps'].includes(p.tipo)) {
            opcionesHtml = '<div class="ms-3 mt-2"><label class="form-label small">Puntos por valor (valor:puntos)</label>';
            opcionesHtml += '<textarea class="form-control form-control-sm" rows="2" onchange="updatePuntos('+i+',this.value)">'+Object.entries(p.puntos||{}).map(([k,v])=>k+':'+v).join('\n')+'</textarea></div>';
        }

        let condicionHtml = '';
        if (i > 0) {
            condicionHtml = '<div class="col-md-4"><label class="form-label small">Mostrar si pregunta #</label><div class="input-group input-group-sm">';
            condicionHtml += '<input type="number" class="form-control" min="1" max="'+i+'" value="'+(p.condicion_mostrar?.pregunta_idx||'')+'" onchange="updateCondicion('+i+',\'idx\',this.value)">';
            condicionHtml += '<input type="text" class="form-control" placeholder="= valor" value="'+(p.condicion_mostrar?.valor||'')+'" onchange="updateCondicion('+i+',\'val\',this.value)">';
            condicionHtml += '</div><small class="text-muted">Dejar vacio para mostrar siempre</small></div>';
        }

        const tipoOpts = Object.entries(tipoPreguntas).map(([k,v])=>`<option value="${k}" ${p.tipo===k?'selected':''}>${v}</option>`).join('');

        c.innerHTML += `
        <div class="border rounded p-3 mb-3 bg-light">
            <div class="d-flex justify-content-between mb-2">
                <strong class="small">Pregunta ${i+1}</strong>
                <div><button type="button" class="btn btn-xs btn-outline-secondary" onclick="movePregunta(${i},-1)"><i class="bi bi-arrow-up"></i></button>
                <button type="button" class="btn btn-xs btn-outline-danger" onclick="removePregunta(${i})"><i class="bi bi-trash"></i></button></div>
            </div>
            <div class="row g-2">
                <div class="col-md-5"><input type="text" class="form-control form-control-sm" placeholder="Texto de la pregunta" value="${esc(p.pregunta)}" onchange="preguntas[${i}].pregunta=this.value"></div>
                <div class="col-md-3"><select class="form-select form-select-sm" onchange="preguntas[${i}].tipo=this.value;render()">${tipoOpts}</select></div>
                <div class="col-md-1"><div class="form-check mt-1"><input type="checkbox" class="form-check-input" ${p.requerida?'checked':''} onchange="preguntas[${i}].requerida=this.checked"><label class="form-check-label small">Req.</label></div></div>
                ${condicionHtml}
            </div>
            ${opcionesHtml}
        </div>`;
    });
}

function updateOpciones(i, val) { preguntas[i].opciones = val.split('\n').filter(x=>x.trim()); }
function updatePuntos(i, val) {
    const pts = {};
    val.split('\n').forEach(l => { const [k,v] = l.split(':'); if(k&&v) pts[k.trim()] = parseFloat(v); });
    preguntas[i].puntos = pts;
}
function updateCondicion(i, field, val) {
    if (!preguntas[i].condicion_mostrar) preguntas[i].condicion_mostrar = {};
    if (field === 'idx') preguntas[i].condicion_mostrar.pregunta_idx = parseInt(val) || null;
    if (field === 'val') preguntas[i].condicion_mostrar.valor = val;
    if (!preguntas[i].condicion_mostrar.pregunta_idx && !preguntas[i].condicion_mostrar.valor) preguntas[i].condicion_mostrar = null;
}
function removePregunta(i) { preguntas.splice(i, 1); render(); }
function movePregunta(i, dir) { if (i+dir<0||i+dir>=preguntas.length) return; [preguntas[i],preguntas[i+dir]]=[preguntas[i+dir],preguntas[i]]; render(); }
function esc(s) { const d=document.createElement('div');d.textContent=s||'';return d.innerHTML.replace(/"/g,'&quot;'); }

document.getElementById('encForm').addEventListener('submit', function() {
    document.getElementById('preguntasJson').value = JSON.stringify(preguntas);
});

render();
</script>
<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
