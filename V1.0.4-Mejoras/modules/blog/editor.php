<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$bp = null;
if ($id) { $bp = $db->prepare("SELECT * FROM blog_posts WHERE id=?"); $bp->execute([$id]); $bp=$bp->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $titulo = trim(post('titulo'));
    $slug = post('slug') ? trim(post('slug')) : preg_replace('/[^a-z0-9]+/','-',strtolower(preg_replace('/[áéíóúñü]/u', '', $titulo)));
    $slug = trim($slug, '-');
    $extracto = trim(post('extracto'));
    $contenido = $_POST['contenido'] ?? '';
    $imagenDest = trim(post('imagen_destacada'));
    $categoria = trim(post('categoria'));
    $tags = trim(post('tags'));
    $metaTitle = trim(post('meta_title')) ?: $titulo;
    $metaDesc = trim(post('meta_description')) ?: mb_strimwidth(strip_tags($extracto ?: $contenido), 0, 160);
    $estado = post('estado', 'borrador');
    $propiedadId = intval(post('propiedad_id')) ?: null;

    if (empty($titulo)) { setFlash('danger', 'Titulo obligatorio.'); }
    else {
        if ($id) {
            $db->prepare("UPDATE blog_posts SET titulo=?, slug=?, extracto=?, contenido=?, imagen_destacada=?, categoria=?, tags=?, meta_title=?, meta_description=?, estado=?, propiedad_id=?, publicado_at=IF(?='publicado' AND publicado_at IS NULL, NOW(), publicado_at) WHERE id=?")
                ->execute([$titulo, $slug, $extracto, $contenido, $imagenDest, $categoria, $tags, $metaTitle, $metaDesc, $estado, $propiedadId, $estado, $id]);
        } else {
            $db->prepare("INSERT INTO blog_posts (titulo, slug, extracto, contenido, imagen_destacada, categoria, tags, meta_title, meta_description, estado, propiedad_id, usuario_id, publicado_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,IF(?='publicado',NOW(),NULL))")
                ->execute([$titulo, $slug, $extracto, $contenido, $imagenDest, $categoria, $tags, $metaTitle, $metaDesc, $estado, $propiedadId, currentUserId(), $estado]);
        }
        setFlash('success', 'Articulo guardado.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = $id ? 'Editar Articulo' : 'Nuevo Articulo';
require_once __DIR__ . '/../../includes/header.php';
$propiedades = $db->query("SELECT id, titulo, referencia FROM propiedades WHERE estado != 'retirado' ORDER BY titulo")->fetchAll();
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>

<form method="POST">
    <?= csrfField() ?>
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control form-control-lg" value="<?= sanitize($bp['titulo']??'') ?>" required></div>
                    <div class="mb-3"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="<?= sanitize($bp['slug']??'') ?>" placeholder="auto-generado"></div>
                    <div class="mb-3"><label class="form-label">Extracto</label><textarea name="extracto" class="form-control" rows="2"><?= sanitize($bp['extracto']??'') ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Contenido</label><textarea name="contenido" class="form-control" rows="20" id="contenido"><?= htmlspecialchars($bp['contenido']??'') ?></textarea></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Estado</label>
                        <select name="estado" class="form-select"><option value="borrador" <?= ($bp['estado']??'')==='borrador'?'selected':'' ?>>Borrador</option><option value="publicado" <?= ($bp['estado']??'')==='publicado'?'selected':'' ?>>Publicado</option></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Categoria</label><input type="text" name="categoria" class="form-control" value="<?= sanitize($bp['categoria']??'') ?>" placeholder="Ej: Noticias, Guias"></div>
                    <div class="mb-3"><label class="form-label">Tags</label><input type="text" name="tags" class="form-control" value="<?= sanitize($bp['tags']??'') ?>" placeholder="comprar, madrid, inversiones"></div>
                    <div class="mb-3"><label class="form-label">Imagen destacada URL</label><input type="url" name="imagen_destacada" class="form-control" value="<?= sanitize($bp['imagen_destacada']??'') ?>"></div>
                    <div class="mb-3"><label class="form-label">Propiedad vinculada</label>
                        <select name="propiedad_id" class="form-select"><option value="">Ninguna</option>
                        <?php foreach($propiedades as $pr): ?><option value="<?= $pr['id'] ?>" <?= ($bp['propiedad_id']??'')==$pr['id']?'selected':'' ?>><?= sanitize($pr['referencia'].' - '.$pr['titulo']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Guardar</button>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="small text-uppercase text-muted">SEO</h6>
                    <div class="mb-2"><label class="form-label small">Meta titulo</label><input type="text" name="meta_title" class="form-control form-control-sm" value="<?= sanitize($bp['meta_title']??'') ?>"></div>
                    <div class="mb-2"><label class="form-label small">Meta descripcion</label><textarea name="meta_description" class="form-control form-control-sm" rows="2"><?= sanitize($bp['meta_description']??'') ?></textarea></div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
