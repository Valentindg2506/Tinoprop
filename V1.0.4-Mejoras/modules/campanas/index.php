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
    $cid = intval(post('cid'));
    if ($accion === 'crear') {
        $nombre = trim(post('nombre'));
        if ($nombre) {
            $db->prepare("INSERT INTO campanas (nombre, tipo, descripcion, usuario_id) VALUES (?,?,?,?)")
                ->execute([$nombre, post('tipo','email'), trim(post('descripcion')), currentUserId()]);
            header('Location: editor.php?id='.$db->lastInsertId()); exit;
        }
    }
    if (($accion === 'eliminar' || $accion === 'toggle') && $cid > 0 && !isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM campanas WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$cid]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre esta campana.');
            header('Location: index.php');
            exit;
        }
    }

    if ($accion === 'eliminar') { $db->prepare("DELETE FROM campanas WHERE id=?")->execute([$cid]); setFlash('success','Eliminada.'); }
    if ($accion === 'toggle') {
        $c = $db->prepare("SELECT estado FROM campanas WHERE id=?"); $c->execute([$cid]); $c=$c->fetch();
        $nuevo = $c['estado']==='activa' ? 'pausada' : 'activa';
        $db->prepare("UPDATE campanas SET estado=? WHERE id=?")->execute([$nuevo, $cid]);
    }
    header('Location: index.php'); exit;
}

$pageTitle = 'Campanas';
require_once __DIR__ . '/../../includes/header.php';
$campanas = $db->query("SELECT c.*, (SELECT COUNT(*) FROM campana_pasos WHERE campana_id=c.id) as total_pasos FROM campanas c ORDER BY c.created_at DESC")->fetchAll();
$estadoClases = ['borrador'=>'secondary','activa'=>'success','pausada'=>'warning','completada'=>'info'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($campanas) ?> campana<?= count($campanas)!==1?'s':'' ?></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="bi bi-plus-lg"></i> Nueva Campana</button>
</div>

<?php if (empty($campanas)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-send fs-1 d-block mb-3"></i>
    <h5>No hay campanas</h5>
    <p>Crea secuencias automaticas de emails y SMS para nutrir leads.</p>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($campanas as $c): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($c['nombre']) ?></h6>
                    <span class="badge bg-<?= $estadoClases[$c['estado']] ?>"><?= ucfirst($c['estado']) ?></span>
                </div>
                <div class="small text-muted mb-2">
                    <i class="bi bi-<?= $c['tipo']==='email'?'envelope':($c['tipo']==='sms'?'phone':'shuffle') ?>"></i> <?= ucfirst($c['tipo']) ?>
                    &middot; <?= $c['total_pasos'] ?> pasos
                </div>
                <div class="row g-1 text-center small">
                    <div class="col-3"><div class="bg-light rounded p-1"><strong><?= $c['total_contactos'] ?></strong><br><small class="text-muted">Contactos</small></div></div>
                    <div class="col-3"><div class="bg-light rounded p-1"><strong><?= $c['enviados'] ?></strong><br><small class="text-muted">Enviados</small></div></div>
                    <div class="col-3"><div class="bg-light rounded p-1"><strong><?= $c['abiertos'] ?></strong><br><small class="text-muted">Abiertos</small></div></div>
                    <div class="col-3"><div class="bg-light rounded p-1"><strong><?= $c['clicks'] ?></strong><br><small class="text-muted">Clicks</small></div></div>
                </div>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <a href="editor.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                <div class="d-flex gap-1">
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="cid" value="<?= $c['id'] ?>">
                        <button class="btn btn-sm btn-outline-<?= $c['estado']==='activa'?'warning':'success' ?>"><?= $c['estado']==='activa'?'Pausar':'Activar' ?></button></form>
                    <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="cid" value="<?= $c['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear">
    <div class="modal-header"><h5 class="modal-title">Nueva Campana Drip</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="email">Email</option><option value="sms">SMS</option><option value="mixta">Mixta (Email + SMS)</option></select></div>
        <div class="mb-3"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Crear</button></div>
</form></div></div></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
