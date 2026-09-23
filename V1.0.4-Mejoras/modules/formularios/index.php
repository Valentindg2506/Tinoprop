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
    $id = intval(post('id'));

    if (($accion === 'toggle' || $accion === 'eliminar') && $id > 0 && !isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM formularios WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$id]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre este formulario.');
            header('Location: index.php');
            exit;
        }
    }

    if ($accion === 'toggle') {
        $db->prepare("UPDATE formularios SET activo = NOT activo WHERE id = ?")->execute([$id]);
    }
    if ($accion === 'eliminar') {
        $db->prepare("DELETE FROM formularios WHERE id = ?")->execute([$id]);
        setFlash('success', 'Formulario eliminado.');
    }
    header('Location: index.php');
    exit;
}

$pageTitle = 'Formularios Web';
require_once __DIR__ . '/../../includes/header.php';

$formsSql = "SELECT f.*, (SELECT COUNT(*) FROM formulario_envios WHERE formulario_id = f.id) as total_envios, (SELECT COUNT(*) FROM formulario_envios WHERE formulario_id = f.id AND leido = 0) as no_leidos FROM formularios f" . (isAdmin() ? '' : ' WHERE f.usuario_id = ?') . " ORDER BY f.created_at DESC";
$formsStmt = $db->prepare($formsSql);
$formsStmt->execute(isAdmin() ? [] : [currentUserId()]);
$forms = $formsStmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($forms) ?> formularios</span>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Formulario</a>
</div>

<?php if (empty($forms)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-ui-checks fs-1 d-block mb-3"></i>
    <h5>No hay formularios creados</h5>
    <p>Crea formularios web para capturar leads automaticamente.</p>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Envios</th>
                    <th>No leidos</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forms as $f): ?>
                <tr>
                    <td>
                        <strong><?= sanitize($f['nombre']) ?></strong>
                        <?php if ($f['descripcion']): ?>
                        <br><small class="text-muted"><?= sanitize(mb_strimwidth($f['descripcion'], 0, 60, '...')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?= $f['total_envios'] ?></span></td>
                    <td>
                        <?php if ($f['no_leidos'] > 0): ?>
                        <span class="badge bg-danger"><?= $f['no_leidos'] ?></span>
                        <?php else: ?>
                        <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                            <button type="submit" class="badge border-0 bg-<?= $f['activo'] ? 'success' : 'secondary' ?>" style="cursor:pointer">
                                <?= $f['activo'] ? 'Activo' : 'Inactivo' ?>
                            </button>
                        </form>
                    </td>
                    <td><small><?= formatFecha($f['created_at']) ?></small></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="envios.php?formulario_id=<?= $f['id'] ?>" class="btn btn-outline-primary" title="Ver envios"><i class="bi bi-inbox"></i></a>
                            <a href="form.php?id=<?= $f['id'] ?>" class="btn btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-outline-info" title="Copiar enlace" onclick="copiarEnlace(<?= $f['id'] ?>)"><i class="bi bi-link-45deg"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar este formulario?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
function copiarEnlace(id) {
    const url = '<?= APP_URL ?>/formulario.php?id=' + id;
    navigator.clipboard.writeText(url).then(() => alert('Enlace copiado: ' + url));
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
