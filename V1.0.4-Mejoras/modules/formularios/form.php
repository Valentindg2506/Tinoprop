<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$id = intval(get('id'));
$form = null;
$campos = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM formularios WHERE id = ?");
    $stmt->execute([$id]);
    $form = $stmt->fetch();
    if (!$form) { setFlash('danger', 'Formulario no encontrado.'); header('Location: index.php'); exit; }
    if (!isAdmin() && intval($form['usuario_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar este formulario.');
        header('Location: index.php');
        exit;
    }
    $campos = json_decode($form['campos'], true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nombre = trim(post('nombre'));
    $descripcion = trim(post('descripcion'));
    $color = post('color_primario', '#10b981');
    $texto_boton = trim(post('texto_boton', 'Enviar'));
    $mensaje_exito = trim(post('mensaje_exito'));
    $redirect_url = trim(post('redirect_url'));
    $email_notif = trim(post('email_notificacion'));
    $crear_cliente = post('crear_cliente') ? 1 : 0;

    $camposJson = $_POST['campos_json'] ?? '[]';
    $camposArr = json_decode($camposJson, true);

    if (empty($nombre)) {
        setFlash('danger', 'El nombre es obligatorio.');
    } elseif (!is_array($camposArr) || empty($camposArr)) {
        setFlash('danger', 'Debes agregar al menos un campo.');
    } else {
        if ($id) {
            if (isAdmin()) {
                $stmt = $db->prepare("UPDATE formularios SET nombre=?, descripcion=?, campos=?, color_primario=?, texto_boton=?, mensaje_exito=?, redirect_url=?, email_notificacion=?, crear_cliente=? WHERE id=?");
                $stmt->execute([$nombre, $descripcion, json_encode($camposArr), $color, $texto_boton, $mensaje_exito, $redirect_url, $email_notif, $crear_cliente, $id]);
            } else {
                $stmt = $db->prepare("UPDATE formularios SET nombre=?, descripcion=?, campos=?, color_primario=?, texto_boton=?, mensaje_exito=?, redirect_url=?, email_notificacion=?, crear_cliente=? WHERE id=? AND usuario_id=?");
                $stmt->execute([$nombre, $descripcion, json_encode($camposArr), $color, $texto_boton, $mensaje_exito, $redirect_url, $email_notif, $crear_cliente, $id, currentUserId()]);
            }
            registrarActividad('actualizar', 'formulario', $id, $nombre);
        } else {
            $stmt = $db->prepare("INSERT INTO formularios (nombre, descripcion, campos, color_primario, texto_boton, mensaje_exito, redirect_url, email_notificacion, crear_cliente, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$nombre, $descripcion, json_encode($camposArr), $color, $texto_boton, $mensaje_exito, $redirect_url, $email_notif, $crear_cliente, currentUserId()]);
            registrarActividad('crear', 'formulario', $db->lastInsertId(), $nombre);
        }
        setFlash('success', 'Formulario guardado.');
        header('Location: index.php');
        exit;
    }
    header('Location: form.php' . ($id ? '?id=' . $id : ''));
    exit;
}

$pageTitle = $id ? 'Editar Formulario' : 'Nuevo Formulario';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form method="POST" id="formBuilder">
    <?= csrfField() ?>
    <input type="hidden" name="campos_json" id="camposJson">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-gear"></i> Configuracion</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Nombre del formulario <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required value="<?= sanitize($form['nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color</label>
                            <input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= sanitize($form['color_primario'] ?? '#10b981') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripcion</label>
                            <input type="text" name="descripcion" class="form-control" value="<?= sanitize($form['descripcion'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Texto del boton</label>
                            <input type="text" name="texto_boton" class="form-control" value="<?= sanitize($form['texto_boton'] ?? 'Enviar') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email notificacion</label>
                            <input type="email" name="email_notificacion" class="form-control" value="<?= sanitize($form['email_notificacion'] ?? '') ?>" placeholder="Opcional">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mensaje de exito</label>
                            <textarea name="mensaje_exito" class="form-control" rows="2"><?= sanitize($form['mensaje_exito'] ?? 'Formulario enviado correctamente. Nos pondremos en contacto.') ?></textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">URL de redireccion (opcional)</label>
                            <input type="url" name="redirect_url" class="form-control" value="<?= sanitize($form['redirect_url'] ?? '') ?>" placeholder="https://...">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="crear_cliente" class="form-check-input" id="crearCliente" value="1" <?= ($form['crear_cliente'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="crearCliente">Auto-crear cliente</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-ui-checks-grid"></i> Campos del formulario</h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addField()"><i class="bi bi-plus"></i> Agregar campo</button>
                </div>
                <div class="card-body" id="fieldsContainer">
                    <p class="text-muted text-center" id="emptyMsg">Agrega campos al formulario usando el boton de arriba.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-save"></i> Guardar Formulario</button>
                    <?php if ($id): ?>
                    <a href="<?= APP_URL ?>/formulario.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-eye"></i> Ver formulario publico
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><h6 class="mb-0">Tipos de campo</h6></div>
                <div class="card-body small text-muted">
                    <strong>texto</strong> - Texto corto<br>
                    <strong>email</strong> - Correo electronico<br>
                    <strong>telefono</strong> - Numero de telefono<br>
                    <strong>numero</strong> - Valor numerico<br>
                    <strong>textarea</strong> - Texto largo<br>
                    <strong>select</strong> - Desplegable (separar opciones con coma)<br>
                    <strong>checkbox</strong> - Casilla Si/No<br>
                    <strong>fecha</strong> - Selector de fecha
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let fields = <?= json_encode($campos) ?>;
const container = document.getElementById('fieldsContainer');
const emptyMsg = document.getElementById('emptyMsg');

function renderFields() {
    const existing = container.querySelectorAll('.field-row');
    existing.forEach(el => el.remove());
    emptyMsg.style.display = fields.length ? 'none' : 'block';

    fields.forEach((f, i) => {
        const div = document.createElement('div');
        div.className = 'field-row border rounded p-3 mb-2';
        div.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" placeholder="Etiqueta" value="${esc(f.label)}" onchange="fields[${i}].label=this.value">
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" onchange="fields[${i}].type=this.value; renderFields()">
                        ${['texto','email','telefono','numero','textarea','select','checkbox','fecha'].map(t => `<option value="${t}" ${f.type===t?'selected':''}>${t}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm" placeholder="Placeholder" value="${esc(f.placeholder||'')}" onchange="fields[${i}].placeholder=this.value">
                </div>
                ${f.type === 'select' ? `<div class="col-md-2"><input type="text" class="form-control form-control-sm" placeholder="Op1,Op2,Op3" value="${esc(f.options||'')}" onchange="fields[${i}].options=this.value"></div>` : '<div class="col-md-2"></div>'}
                <div class="col-md-1 text-center">
                    <div class="form-check form-check-inline" title="Obligatorio">
                        <input type="checkbox" class="form-check-input" ${f.required?'checked':''} onchange="fields[${i}].required=this.checked">
                    </div>
                </div>
                <div class="col-md-1 text-end">
                    ${i > 0 ? `<a href="#" onclick="moveField(${i},-1);return false" class="text-muted me-1" title="Subir"><i class="bi bi-arrow-up"></i></a>` : ''}
                    <a href="#" onclick="removeField(${i});return false" class="text-danger" title="Eliminar"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML.replace(/"/g, '&quot;'); }
function addField() { fields.push({label:'',type:'texto',required:false,placeholder:'',options:''}); renderFields(); }
function removeField(i) { fields.splice(i, 1); renderFields(); }
function moveField(i, dir) { [fields[i], fields[i+dir]] = [fields[i+dir], fields[i]]; renderFields(); }

document.getElementById('formBuilder').addEventListener('submit', function() {
    document.getElementById('camposJson').value = JSON.stringify(fields);
});

renderFields();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
