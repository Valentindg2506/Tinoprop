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
        $db->prepare("INSERT INTO ab_tests (nombre, tipo, variante_a_config, variante_b_config, usuario_id) VALUES (?,?,?,?,?)")
            ->execute([trim(post('nombre')), post('tipo','email'), json_encode(['asunto'=>trim($_POST['asunto_a']??''),'contenido'=>trim($_POST['contenido_a']??'')]),
                json_encode(['asunto'=>trim($_POST['asunto_b']??''),'contenido'=>trim($_POST['contenido_b']??'')]), currentUserId()]);
        setFlash('success','Test creado.');
        header('Location: index.php'); exit;
    }
    if ($a === 'toggle') {
        $t = $db->prepare("SELECT estado FROM ab_tests WHERE id=?"); $t->execute([intval(post('tid'))]); $t=$t->fetch();
        $nuevo = $t['estado']==='activo'?'completado':'activo';
        $db->prepare("UPDATE ab_tests SET estado=? WHERE id=?")->execute([$nuevo, intval(post('tid'))]);
        header('Location: index.php'); exit;
    }
    if ($a === 'eliminar') { $db->prepare("DELETE FROM ab_tests WHERE id=?")->execute([intval(post('tid'))]); setFlash('success','Eliminado.'); header('Location: index.php'); exit; }
}

$pageTitle = 'A/B Testing';
require_once __DIR__ . '/../../includes/header.php';

$tests = $db->query("SELECT * FROM ab_tests ORDER BY created_at DESC")->fetchAll();
$estadoClases = ['borrador'=>'secondary','activo'=>'success','completado'=>'info'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($tests) ?> test<?= count($tests)!==1?'s':'' ?></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="bi bi-plus-lg"></i> Nuevo Test A/B</button>
</div>

<?php if (empty($tests)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-arrow-left-right fs-1 d-block mb-3"></i><h5>No hay tests A/B</h5><p>Compara dos versiones de emails o landing pages.</p></div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($tests as $t):
        $tasaA = $t['visitas_a']>0 ? round($t['conversiones_a']/$t['visitas_a']*100,1) : 0;
        $tasaB = $t['visitas_b']>0 ? round($t['conversiones_b']/$t['visitas_b']*100,1) : 0;
        $ganando = $tasaA > $tasaB ? 'A' : ($tasaB > $tasaA ? 'B' : '-');
    ?>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($t['nombre']) ?></h6>
                    <span class="badge bg-<?= $estadoClases[$t['estado']] ?>"><?= ucfirst($t['estado']) ?></span>
                </div>
                <small class="text-muted"><?= ucfirst($t['tipo']) ?></small>
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <div class="border rounded p-2 text-center <?= $ganando==='A'?'border-success':'' ?>">
                            <strong class="text-primary">Variante A</strong>
                            <div class="row mt-1">
                                <div class="col-4"><small class="d-block text-muted">Visitas</small><strong><?= $t['visitas_a'] ?></strong></div>
                                <div class="col-4"><small class="d-block text-muted">Conv.</small><strong><?= $t['conversiones_a'] ?></strong></div>
                                <div class="col-4"><small class="d-block text-muted">Tasa</small><strong><?= $tasaA ?>%</strong></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2 text-center <?= $ganando==='B'?'border-success':'' ?>">
                            <strong class="text-danger">Variante B</strong>
                            <div class="row mt-1">
                                <div class="col-4"><small class="d-block text-muted">Visitas</small><strong><?= $t['visitas_b'] ?></strong></div>
                                <div class="col-4"><small class="d-block text-muted">Conv.</small><strong><?= $t['conversiones_b'] ?></strong></div>
                                <div class="col-4"><small class="d-block text-muted">Tasa</small><strong><?= $tasaB ?>%</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($ganando !== '-'): ?><div class="text-center mt-2"><span class="badge bg-success">Ganando: Variante <?= $ganando ?></span></div><?php endif; ?>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-end gap-1">
                <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="tid" value="<?= $t['id'] ?>"><button class="btn btn-xs btn-outline-<?= $t['estado']==='activo'?'warning':'success' ?>"><?= $t['estado']==='activo'?'Detener':'Activar' ?></button></form>
                <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="tid" value="<?= $t['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear">
    <div class="modal-header"><h5 class="modal-title">Nuevo Test A/B</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Tipo</label><select name="tipo" class="form-select"><option value="email">Email</option><option value="landing">Landing Page</option></select></div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <h6 class="text-primary">Variante A</h6>
                <div class="mb-2"><input type="text" name="asunto_a" class="form-control form-control-sm" placeholder="Asunto A"></div>
                <textarea name="contenido_a" class="form-control form-control-sm" rows="4" placeholder="Contenido A"></textarea>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger">Variante B</h6>
                <div class="mb-2"><input type="text" name="asunto_b" class="form-control form-control-sm" placeholder="Asunto B"></div>
                <textarea name="contenido_b" class="form-control form-control-sm" rows="4" placeholder="Contenido B"></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Crear Test</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
