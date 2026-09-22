<?php
$pageTitle = 'Gestion de Usuarios';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$db = getDB();
try {
    $usuarios = $db->query("SELECT u.*, r.nombre AS custom_role_nombre
        FROM usuarios u
        LEFT JOIN usuario_roles ur ON ur.user_id = u.id
        LEFT JOIN roles r ON r.id = ur.role_id
        ORDER BY u.nombre")->fetchAll();
} catch (Throwable $e) {
    $usuarios = $db->query("SELECT * FROM usuarios ORDER BY nombre")->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($usuarios) ?> usuarios</span>
    <div class="d-flex gap-2">
        <a href="roles.php" class="btn btn-outline-secondary"><i class="bi bi-shield-lock"></i> Roles</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Usuario</a>
    </div>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Nombre</th><th>Email</th><th>Telefono</th><th>Rol</th><th>Rol personalizado</th><th>Estado</th><th>Ultimo Acceso</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><strong><?= sanitize($u['nombre'] . ' ' . $u['apellidos']) ?></strong></td>
                <td><?= sanitize($u['email']) ?></td>
                <td><?= sanitize($u['telefono'] ?? '-') ?></td>
                <td><span class="badge bg-<?= $u['rol'] === 'admin' ? 'danger' : 'primary' ?>"><?= ucfirst($u['rol']) ?></span></td>
                <td>
                    <?php if (!empty($u['custom_role_nombre']) && $u['rol'] !== 'admin'): ?>
                        <span class="badge bg-info text-dark"><?= sanitize($u['custom_role_nombre']) ?></span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td><?= $u['activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                <td><?= $u['ultimo_acceso'] ? formatFechaHora($u['ultimo_acceso']) : 'Nunca' ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="form.php?id=<?= $u['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <?php if ($u['id'] !== currentUserId()): ?>
                        <form method="POST" action="delete.php" onsubmit="return confirm('Eliminar este usuario?')" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="id" value="<?= intval($u['id']) ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
