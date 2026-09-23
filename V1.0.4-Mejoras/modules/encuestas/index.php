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
        $db->prepare("INSERT INTO encuestas (nombre, descripcion, usuario_id) VALUES (?,?,?)")
            ->execute([trim(post('nombre')), trim(post('descripcion')), currentUserId()]);
        header('Location: form.php?id='.$db->lastInsertId()); exit;
    }
    if ($a === 'eliminar') { $db->prepare("DELETE FROM encuestas WHERE id=?")->execute([intval(post('eid'))]); setFlash('success','Eliminada.'); }
    if ($a === 'toggle') { $db->prepare("UPDATE encuestas SET activo=NOT activo WHERE id=?")->execute([intval(post('eid'))]); }
    header('Location: index.php'); exit;
}

$pageTitle = 'Encuestas';
require_once __DIR__ . '/../../includes/header.php';
$encuestas = $db->query("SELECT * FROM encuestas ORDER BY created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($encuestas) ?> encuesta<?= count($encuestas)!==1?'s':'' ?></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="bi bi-plus-lg"></i> Nueva Encuesta</button>
</div>

<?php if (empty($encuestas)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-clipboard2-data fs-1 d-block mb-3"></i><h5>No hay encuestas</h5><p>Crea encuestas con puntuacion y logica condicional.</p></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($encuestas as $e):
        $preguntas = json_decode($e['preguntas'], true) ?: [];
    ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($e['nombre']) ?></h6>
                    <span class="badge bg-<?= $e['activo']?'success':'secondary' ?>"><?= $e['activo']?'Activa':'Inactiva' ?></span>
                </div>
                <div class="small text-muted"><?= count($preguntas) ?> preguntas &middot; <?= $e['total_respuestas'] ?> respuestas</div>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <a href="form.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                <div class="d-flex gap-1">
                    <a href="resultados.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-bar-chart"></i></a>
                    <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/encuesta.php?id=<?= $e['id'] ?>')"><i class="bi bi-link"></i></button>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="eid" value="<?= $e['id'] ?>"><button class="btn btn-sm btn-outline-<?= $e['activo']?'warning':'success' ?>"><i class="bi bi-toggle-<?= $e['activo']?'on':'off' ?>"></i></button></form>
                    <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="eid" value="<?= $e['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear">
    <div class="modal-header"><h5 class="modal-title">Nueva Encuesta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Crear</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
