<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');
    $pageId = intval(post('page_id'));

    if (($accion === 'eliminar' || $accion === 'toggle') && $pageId > 0 && !isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM landing_pages WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$pageId]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre esta landing page.');
            header('Location: index.php');
            exit;
        }
    }

    if ($accion === 'eliminar') {
        $db->prepare("DELETE FROM landing_pages WHERE id = ?")->execute([$pageId]);
        setFlash('success', 'Pagina eliminada.');
    }
    if ($accion === 'toggle') {
        $db->prepare("UPDATE landing_pages SET activa = NOT activa WHERE id = ?")->execute([$pageId]);
    }
    header('Location: index.php');
    exit;
}

$pageTitle = 'Landing Pages';
require_once __DIR__ . '/../../includes/header.php';

$pages = $db->query("SELECT * FROM landing_pages ORDER BY created_at DESC")->fetchAll();
$totalVisitas = array_sum(array_column($pages, 'visitas'));
$totalConv = array_sum(array_column($pages, 'conversiones'));
?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold"><?= count($pages) ?></div><small class="text-muted">Total paginas</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-primary"><?= $totalVisitas ?></div><small class="text-muted">Total visitas</small></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-success"><?= $totalConv ?></div><small class="text-muted">Conversiones</small></div></div></div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span></span>
    <a href="editor.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Landing Page</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Titulo</th><th>URL</th><th>Visitas</th><th>Conversiones</th><th>Tasa</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                <?php foreach ($pages as $p):
                    $tasa = $p['visitas'] > 0 ? round(($p['conversiones']/$p['visitas'])*100,1) : 0;
                ?>
                <tr>
                    <td><strong><?= sanitize($p['titulo']) ?></strong></td>
                    <td><a href="<?= APP_URL ?>/p.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="small">/p.php?slug=<?= sanitize($p['slug']) ?></a></td>
                    <td><?= $p['visitas'] ?></td>
                    <td><?= $p['conversiones'] ?></td>
                    <td><span class="badge bg-<?= $tasa > 5 ? 'success' : ($tasa > 0 ? 'warning' : 'secondary') ?>"><?= $tasa ?>%</span></td>
                    <td>
                        <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="page_id" value="<?= $p['id'] ?>">
                            <button class="badge border-0 bg-<?= $p['activa']?'success':'secondary' ?>" style="cursor:pointer"><?= $p['activa']?'Activa':'Inactiva' ?></button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a href="editor.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="page_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pages)): ?><tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-file-earmark-richtext fs-1 d-block mb-2"></i>No hay landing pages</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
