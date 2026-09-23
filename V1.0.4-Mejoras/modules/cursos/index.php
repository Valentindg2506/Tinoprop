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
    if ($a === 'crear') {
        $titulo = trim(post('titulo'));
        $slug = preg_replace('/[^a-z0-9]+/','-',strtolower($titulo));
        $db->prepare("INSERT INTO cursos (titulo, slug, descripcion, acceso, precio, usuario_id) VALUES (?,?,?,?,?,?)")
            ->execute([$titulo, $slug, trim(post('descripcion')), post('acceso','privado'), floatval(post('precio')), currentUserId()]);
        header('Location: editor.php?id='.$db->lastInsertId()); exit;
    }
    if ($a === 'eliminar') { $db->prepare("DELETE FROM cursos WHERE id=?")->execute([intval(post('cid'))]); setFlash('success','Eliminado.'); }
    if ($a === 'toggle') { $db->prepare("UPDATE cursos SET activo=NOT activo WHERE id=?")->execute([intval(post('cid'))]); }
    header('Location: index.php'); exit;
}

$pageTitle = 'Cursos / Membresias';
require_once __DIR__ . '/../../includes/header.php';
$cursos = $db->query("SELECT c.*, (SELECT COUNT(*) FROM curso_lecciones WHERE curso_id=c.id) as total_lecciones, (SELECT COUNT(*) FROM curso_matriculas WHERE curso_id=c.id) as total_alumnos FROM cursos c ORDER BY c.created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($cursos) ?> curso<?= count($cursos)!==1?'s':'' ?></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="bi bi-plus-lg"></i> Nuevo Curso</button>
</div>

<?php if (empty($cursos)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-mortarboard fs-1 d-block mb-3"></i><h5>No hay cursos</h5><p>Crea contenido exclusivo para tus clientes.</p></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($cursos as $c): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <?php if($c['imagen']): ?><img src="<?= sanitize($c['imagen']) ?>" class="card-img-top" style="height:140px;object-fit:cover"><?php endif; ?>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <span class="badge bg-<?= $c['activo']?'success':'secondary' ?>"><?= $c['activo']?'Activo':'Inactivo' ?></span>
                    <span class="badge bg-light text-dark"><?= ucfirst($c['acceso']) ?><?= $c['precio']>0?' - '.number_format($c['precio'],0,',','.').'€':'' ?></span>
                </div>
                <h6 class="fw-bold"><?= sanitize($c['titulo']) ?></h6>
                <div class="small text-muted"><?= $c['total_lecciones'] ?> lecciones &middot; <?= $c['total_alumnos'] ?> alumnos</div>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <a href="editor.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                <div class="d-flex gap-1">
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="cid" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-<?= $c['activo']?'warning':'success' ?>"><i class="bi bi-toggle-<?= $c['activo']?'on':'off' ?>"></i></button></form>
                    <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="cid" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear">
    <div class="modal-header"><h5 class="modal-title">Nuevo Curso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="3"></textarea></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Acceso</label><select name="acceso" class="form-select"><option value="privado">Privado</option><option value="publico">Publico</option><option value="pago">De pago</option></select></div>
            <div class="col-md-6"><label class="form-label">Precio (€)</label><input type="number" name="precio" class="form-control" value="0" step="0.01"></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Crear</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
