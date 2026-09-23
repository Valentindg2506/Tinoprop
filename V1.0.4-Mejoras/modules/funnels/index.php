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
    $fid = intval(post('fid'));
    if ($accion === 'crear') {
        $nombre = trim(post('nombre'));
        if ($nombre) {
            $db->prepare("INSERT INTO funnels (nombre, descripcion, usuario_id) VALUES (?,?,?)")
                ->execute([$nombre, trim(post('descripcion')), currentUserId()]);
            $fid = $db->lastInsertId();
            // Crear pasos por defecto
            $pasos = [
                ['orden'=>1,'nombre'=>'Pagina de captura','tipo'=>'landing'],
                ['orden'=>2,'nombre'=>'Formulario','tipo'=>'formulario'],
                ['orden'=>3,'nombre'=>'Gracias','tipo'=>'gracias']
            ];
            $stmt = $db->prepare("INSERT INTO funnel_pasos (funnel_id,orden,nombre,tipo) VALUES (?,?,?,?)");
            foreach ($pasos as $p) $stmt->execute([$fid,$p['orden'],$p['nombre'],$p['tipo']]);
            setFlash('success','Funnel creado.');
            header('Location: editor.php?id='.$fid); exit;
        }
    }
    if (($accion === 'eliminar' || $accion === 'toggle') && $fid > 0 && !isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM funnels WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$fid]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre este funnel.');
            header('Location: index.php');
            exit;
        }
    }

    if ($accion === 'eliminar') {
        $db->prepare("DELETE FROM funnels WHERE id=?")->execute([$fid]);
        setFlash('success','Funnel eliminado.');
    }
    if ($accion === 'toggle') {
        $db->prepare("UPDATE funnels SET activo = NOT activo WHERE id=?")->execute([$fid]);
    }
    header('Location: index.php'); exit;
}

$pageTitle = 'Funnels';
require_once __DIR__ . '/../../includes/header.php';

$funnels = $db->query("SELECT f.*, (SELECT COUNT(*) FROM funnel_pasos WHERE funnel_id=f.id) as total_pasos FROM funnels f ORDER BY f.created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($funnels) ?> funnel<?= count($funnels)!==1?'s':'' ?></span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevo"><i class="bi bi-plus-lg"></i> Nuevo Funnel</button>
</div>

<?php if (empty($funnels)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-funnel fs-1 d-block mb-3"></i>
    <h5>No hay funnels</h5>
    <p>Crea embudos de conversion multi-paso para captar clientes.</p>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($funnels as $f):
        $tasa = $f['visitas_total'] > 0 ? round($f['conversiones_total']/$f['visitas_total']*100,1) : 0;
    ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0"><?= sanitize($f['nombre']) ?></h6>
                    <span class="badge bg-<?= $f['activo']?'success':'secondary' ?>"><?= $f['activo']?'Activo':'Inactivo' ?></span>
                </div>
                <?php if ($f['descripcion']): ?><p class="text-muted small mb-2"><?= sanitize($f['descripcion']) ?></p><?php endif; ?>
                <div class="d-flex gap-3 text-muted small">
                    <span><i class="bi bi-layers"></i> <?= $f['total_pasos'] ?> pasos</span>
                    <span><i class="bi bi-eye"></i> <?= $f['visitas_total'] ?> visitas</span>
                    <span><i class="bi bi-check-circle"></i> <?= $tasa ?>% conv.</span>
                </div>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <a href="editor.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-info" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/funnel.php?id=<?= $f['id'] ?>')"><i class="bi bi-link"></i></button>
                    <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="fid" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-outline-<?= $f['activo']?'warning':'success' ?>"><i class="bi bi-toggle-<?= $f['activo']?'on':'off' ?>"></i></button></form>
                    <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="fid" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="modalNuevo" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="accion" value="crear">
        <div class="modal-header"><h5 class="modal-title">Nuevo Funnel</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear Funnel</button></div>
    </form>
</div></div></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
