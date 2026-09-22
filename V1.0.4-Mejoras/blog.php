<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$slug = trim($_GET['slug'] ?? '');

if ($slug) {
    $bp = $db->prepare("SELECT * FROM blog_posts WHERE slug=? AND estado='publicado'"); $bp->execute([$slug]); $bp=$bp->fetch();
    if (!$bp) { http_response_code(404); echo '<h1>Articulo no encontrado</h1>'; exit; }
    $db->prepare("UPDATE blog_posts SET visitas=visitas+1 WHERE id=?")->execute([$bp['id']]);
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($bp['meta_title']?:$bp['titulo']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($bp['meta_description']??'') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>*{font-family:'Inter',sans-serif}body{background:#fff}.blog-content{font-size:1.1rem;line-height:1.8}.blog-content img{max-width:100%;border-radius:8px}</style>
    </head><body>
    <div class="container py-5" style="max-width:800px">
        <?php if ($bp['imagen_destacada']): ?><img src="<?= htmlspecialchars($bp['imagen_destacada']) ?>" class="img-fluid rounded mb-4 w-100" style="max-height:400px;object-fit:cover"><?php endif; ?>
        <h1 class="fw-bold mb-2"><?= htmlspecialchars($bp['titulo']) ?></h1>
        <div class="text-muted mb-4"><?= date('d/m/Y', strtotime($bp['publicado_at']??$bp['created_at'])) ?> &middot; <?= $bp['categoria']?htmlspecialchars($bp['categoria']):'' ?></div>
        <div class="blog-content"><?= $bp['contenido'] ?></div>
        <hr class="my-4"><a href="blog.php" class="btn btn-outline-secondary">&larr; Volver al blog</a>
    </div></body></html><?php
} else {
    $posts = $db->query("SELECT * FROM blog_posts WHERE estado='publicado' ORDER BY publicado_at DESC")->fetchAll();
    ?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>*{font-family:'Inter',sans-serif}body{background:#f8fafc}</style>
    </head><body>
    <div class="container py-5">
        <h1 class="fw-bold mb-4">Blog</h1>
        <div class="row g-4">
        <?php foreach($posts as $bp): ?>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100">
            <?php if($bp['imagen_destacada']): ?><img src="<?= htmlspecialchars($bp['imagen_destacada']) ?>" class="card-img-top" style="height:180px;object-fit:cover"><?php endif; ?>
            <div class="card-body"><h5 class="fw-bold"><a href="blog.php?slug=<?= urlencode($bp['slug']) ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($bp['titulo']) ?></a></h5>
            <p class="text-muted"><?= htmlspecialchars($bp['extracto']??mb_strimwidth(strip_tags($bp['contenido']),0,150,'...')) ?></p>
            <small class="text-muted"><?= date('d/m/Y', strtotime($bp['publicado_at'])) ?></small></div>
        </div></div>
        <?php endforeach; ?>
        </div>
    </div></body></html><?php
}
