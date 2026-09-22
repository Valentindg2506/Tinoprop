<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'eliminar') { $db->prepare("DELETE FROM blog_posts WHERE id=?")->execute([intval(post('bid'))]); setFlash('success','Eliminado.'); }
    if ($a === 'toggle') {
        $b = $db->prepare("SELECT estado FROM blog_posts WHERE id=?"); $b->execute([intval(post('bid'))]); $b=$b->fetch();
        $nuevo = $b['estado']==='publicado'?'borrador':'publicado';
        $db->prepare("UPDATE blog_posts SET estado=?, publicado_at=IF(?='publicado',NOW(),publicado_at) WHERE id=?")->execute([$nuevo, $nuevo, intval(post('bid'))]);
    }
    header('Location: index.php'); exit;
}

$pageTitle = 'Blog';
require_once __DIR__ . '/../../includes/header.php';

$posts = $db->query("SELECT bp.*, u.nombre as autor FROM blog_posts bp LEFT JOIN usuarios u ON bp.usuario_id=u.id ORDER BY bp.created_at DESC")->fetchAll();
$estadoClases = ['borrador'=>'secondary','publicado'=>'success','archivado'=>'warning'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($posts) ?> articulo<?= count($posts)!==1?'s':'' ?></span>
    <a href="editor.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Articulo</a>
</div>

<?php if (empty($posts)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-journal-richtext fs-1 d-block mb-3"></i><h5>No hay articulos</h5><p>Publica articulos SEO vinculados a propiedades.</p></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($posts as $bp): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <?php if ($bp['imagen_destacada']): ?>
            <img src="<?= sanitize($bp['imagen_destacada']) ?>" class="card-img-top" style="height:160px;object-fit:cover" alt="">
            <?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge bg-<?= $estadoClases[$bp['estado']] ?>"><?= ucfirst($bp['estado']) ?></span>
                    <small class="text-muted"><?= $bp['visitas'] ?> visitas</small>
                </div>
                <h6 class="fw-bold"><?= sanitize($bp['titulo']) ?></h6>
                <?php if ($bp['extracto']): ?><p class="text-muted small"><?= sanitize(mb_strimwidth($bp['extracto'],0,120,'...')) ?></p><?php endif; ?>
                <small class="text-muted"><?= $bp['autor']??'Anonimo' ?> &middot; <?= $bp['publicado_at']?formatFecha($bp['publicado_at']):formatFecha($bp['created_at']) ?></small>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <a href="editor.php?id=<?= $bp['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <div class="d-flex gap-1">
                    <?php if ($bp['estado']==='publicado'): ?><a href="<?= APP_URL ?>/blog.php?slug=<?= urlencode($bp['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a><?php endif; ?>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="bid" value="<?= $bp['id'] ?>"><button class="btn btn-sm btn-outline-<?= $bp['estado']==='publicado'?'warning':'success' ?>"><?= $bp['estado']==='publicado'?'Despublicar':'Publicar' ?></button></form>
                    <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="bid" value="<?= $bp['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
