<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$page = null;
$secciones = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM landing_pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch();
    if (!$page) { setFlash('danger','No encontrada.'); header('Location: index.php'); exit; }
    $secciones = json_decode($page['secciones'], true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $titulo = trim(post('titulo'));
    $slug = trim(post('slug')) ?: preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', mb_strtolower($titulo)));
    $meta_titulo = trim(post('meta_titulo'));
    $meta_desc = trim(post('meta_descripcion'));
    $color = post('color_primario','#10b981');
    $color_fondo = post('color_fondo','#ffffff');
    $css = $_POST['custom_css'] ?? '';
    $seccionesJson = $_POST['secciones_json'] ?? '[]';

    if (empty($titulo)) { setFlash('danger','Titulo obligatorio.'); }
    else {
        if ($id) {
            $db->prepare("UPDATE landing_pages SET titulo=?,slug=?,secciones=?,meta_titulo=?,meta_descripcion=?,color_primario=?,color_fondo=?,custom_css=? WHERE id=?")
                ->execute([$titulo,$slug,$seccionesJson,$meta_titulo,$meta_desc,$color,$color_fondo,$css,$id]);
        } else {
            $db->prepare("INSERT INTO landing_pages (titulo,slug,secciones,meta_titulo,meta_descripcion,color_primario,color_fondo,custom_css,usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$titulo,$slug,$seccionesJson,$meta_titulo,$meta_desc,$color,$color_fondo,$css,currentUserId()]);
            $id = $db->lastInsertId();
        }
        setFlash('success','Pagina guardada.');
        header('Location: index.php');
        exit;
    }
    header('Location: editor.php' . ($id ? '?id='.$id : ''));
    exit;
}

$pageTitle = $id ? 'Editar Landing Page' : 'Nueva Landing Page';
require_once __DIR__ . '/../../includes/header.php';
?>

<form method="POST" id="editorForm">
    <?= csrfField() ?>
    <input type="hidden" name="secciones_json" id="seccionesJson">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <div class="d-flex gap-2">
            <?php if ($id): ?><a href="<?= APP_URL ?>/p.php?slug=<?= urlencode($page['slug'] ?? '') ?>" target="_blank" class="btn btn-outline-info btn-sm"><i class="bi bi-eye"></i> Ver</a><?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Guardar</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label">Titulo *</label><input type="text" name="titulo" class="form-control" required value="<?= sanitize($page['titulo'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="<?= sanitize($page['slug'] ?? '') ?>" placeholder="auto-generado"></div>
        <div class="col-md-2"><label class="form-label">Color</label><input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= $page['color_primario'] ?? '#10b981' ?>"></div>
        <div class="col-md-2"><label class="form-label">Fondo</label><input type="color" name="color_fondo" class="form-control form-control-color w-100" value="<?= $page['color_fondo'] ?? '#ffffff' ?>"></div>
        <div class="col-md-6"><label class="form-label">Meta titulo</label><input type="text" name="meta_titulo" class="form-control" value="<?= sanitize($page['meta_titulo'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Meta descripcion</label><input type="text" name="meta_descripcion" class="form-control" value="<?= sanitize($page['meta_descripcion'] ?? '') ?>"></div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-layers"></i> Secciones</h6>
            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus"></i> Agregar</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="addSection('hero');return false"><i class="bi bi-image"></i> Hero Banner</a></li>
                    <li><a class="dropdown-item" href="#" onclick="addSection('texto');return false"><i class="bi bi-text-paragraph"></i> Texto</a></li>
                    <li><a class="dropdown-item" href="#" onclick="addSection('caracteristicas');return false"><i class="bi bi-grid-3x3-gap"></i> Caracteristicas</a></li>
                    <li><a class="dropdown-item" href="#" onclick="addSection('cta');return false"><i class="bi bi-megaphone"></i> Call to Action</a></li>
                    <li><a class="dropdown-item" href="#" onclick="addSection('imagen');return false"><i class="bi bi-image"></i> Imagen</a></li>
                    <li><a class="dropdown-item" href="#" onclick="addSection('contacto');return false"><i class="bi bi-telephone"></i> Contacto</a></li>
                    <li><a class="dropdown-item" href="#" onclick="addSection('separador');return false"><i class="bi bi-dash-lg"></i> Separador</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body" id="sectionsContainer">
            <p class="text-muted text-center" id="emptySecMsg">Agrega secciones usando el boton de arriba.</p>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">CSS personalizado (opcional)</label>
        <textarea name="custom_css" class="form-control font-monospace" rows="3"><?= sanitize($page['custom_css'] ?? '') ?></textarea>
    </div>
</form>

<script>
let sections = <?= json_encode($secciones) ?>;

const sectionDefaults = {
    hero: {titulo:'Tu titulo aqui',subtitulo:'Subtitulo descriptivo',texto_boton:'Contactar',enlace_boton:'#contacto',imagen_fondo_url:'',overlay_opacity:0.5},
    texto: {contenido:'<p>Tu contenido aqui...</p>',alineacion:'centro'},
    caracteristicas: {items:[{icono:'bi-house-heart',titulo:'Caracteristica 1',descripcion:'Descripcion'},{icono:'bi-shield-check',titulo:'Caracteristica 2',descripcion:'Descripcion'},{icono:'bi-geo-alt',titulo:'Caracteristica 3',descripcion:'Descripcion'}]},
    cta: {titulo:'Listo para empezar?',descripcion:'Contactanos hoy mismo',texto_boton:'Contactar',enlace_boton:'#',color_fondo:'#10b981'},
    imagen: {imagen_url:'',alt_text:'',ancho:'100%'},
    contacto: {telefono:'',email:'',direccion:''},
    separador: {tipo:'linea',altura:40}
};

function addSection(type) {
    sections.push({type: type, config: JSON.parse(JSON.stringify(sectionDefaults[type] || {}))});
    renderSections();
}

function renderSections() {
    const c = document.getElementById('sectionsContainer');
    document.getElementById('emptySecMsg').style.display = sections.length ? 'none' : 'block';
    c.querySelectorAll('.sec-row').forEach(el => el.remove());

    sections.forEach((s, i) => {
        const div = document.createElement('div');
        div.className = 'sec-row border rounded p-3 mb-2';
        let configHtml = '';

        if (s.type === 'hero') {
            configHtml = `<div class="row g-2"><div class="col-6"><input class="form-control form-control-sm" placeholder="Titulo" value="${esc(s.config.titulo)}" onchange="sections[${i}].config.titulo=this.value"></div><div class="col-6"><input class="form-control form-control-sm" placeholder="Subtitulo" value="${esc(s.config.subtitulo)}" onchange="sections[${i}].config.subtitulo=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="Texto boton" value="${esc(s.config.texto_boton)}" onchange="sections[${i}].config.texto_boton=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="Enlace boton" value="${esc(s.config.enlace_boton)}" onchange="sections[${i}].config.enlace_boton=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="URL imagen fondo" value="${esc(s.config.imagen_fondo_url||'')}" onchange="sections[${i}].config.imagen_fondo_url=this.value"></div></div>`;
        } else if (s.type === 'texto') {
            configHtml = `<textarea class="form-control form-control-sm" rows="3" onchange="sections[${i}].config.contenido=this.value">${esc(s.config.contenido)}</textarea>`;
        } else if (s.type === 'cta') {
            configHtml = `<div class="row g-2"><div class="col-4"><input class="form-control form-control-sm" placeholder="Titulo" value="${esc(s.config.titulo)}" onchange="sections[${i}].config.titulo=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="Boton" value="${esc(s.config.texto_boton)}" onchange="sections[${i}].config.texto_boton=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="Enlace" value="${esc(s.config.enlace_boton)}" onchange="sections[${i}].config.enlace_boton=this.value"></div></div>`;
        } else if (s.type === 'imagen') {
            configHtml = `<input class="form-control form-control-sm" placeholder="URL de imagen" value="${esc(s.config.imagen_url||'')}" onchange="sections[${i}].config.imagen_url=this.value">`;
        } else if (s.type === 'contacto') {
            configHtml = `<div class="row g-2"><div class="col-4"><input class="form-control form-control-sm" placeholder="Telefono" value="${esc(s.config.telefono||'')}" onchange="sections[${i}].config.telefono=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="Email" value="${esc(s.config.email||'')}" onchange="sections[${i}].config.email=this.value"></div><div class="col-4"><input class="form-control form-control-sm" placeholder="Direccion" value="${esc(s.config.direccion||'')}" onchange="sections[${i}].config.direccion=this.value"></div></div>`;
        } else if (s.type === 'caracteristicas') {
            configHtml = '<small class="text-muted">3 items predefinidos (editar desde JSON avanzado)</small>';
        } else {
            configHtml = '<small class="text-muted">Separador visual</small>';
        }

        div.innerHTML = `<div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge bg-primary">${s.type.toUpperCase()}</span>
            <div>
                ${i > 0 ? `<a href="#" class="text-muted me-2" onclick="moveSection(${i},-1);return false"><i class="bi bi-arrow-up"></i></a>` : ''}
                ${i < sections.length-1 ? `<a href="#" class="text-muted me-2" onclick="moveSection(${i},1);return false"><i class="bi bi-arrow-down"></i></a>` : ''}
                <a href="#" class="text-danger" onclick="sections.splice(${i},1);renderSections();return false"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>${configHtml}`;
        c.appendChild(div);
    });
}

function esc(s) { const d = document.createElement('div'); d.textContent = s||''; return d.innerHTML.replace(/"/g,'&quot;'); }
function moveSection(i,dir) { [sections[i],sections[i+dir]]=[sections[i+dir],sections[i]]; renderSections(); }

document.getElementById('editorForm').addEventListener('submit', function() {
    document.getElementById('seccionesJson').value = JSON.stringify(sections);
});

renderSections();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
