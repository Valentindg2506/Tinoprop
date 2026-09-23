<?php
$pageTitle = 'Nuevo Usuario';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$db = getDB();
$id = intval(get('id'));
$usuario = null;
$customRoleId = null;

function userRolesSystemAvailable(PDO $db): bool {
    try {
        $db->query("SELECT 1 FROM roles LIMIT 1");
        $db->query("SELECT 1 FROM usuario_roles LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$rolesSystemAvailable = userRolesSystemAvailable($db);
$customRoles = [];
if ($rolesSystemAvailable) {
    $customRoles = $db->query("SELECT id, nombre FROM roles WHERE activo = 1 AND LOWER(nombre) <> 'agente' ORDER BY nombre ASC")->fetchAll();
}

if ($id) {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    if (!$usuario) { setFlash('danger', 'Usuario no encontrado.'); header('Location: index.php'); exit; }
    $pageTitle = 'Editar Usuario';

    if ($rolesSystemAvailable) {
        $stmtUr = $db->prepare("SELECT role_id FROM usuario_roles WHERE user_id = ? LIMIT 1");
        $stmtUr->execute([$id]);
        $customRoleId = $stmtUr->fetchColumn() ?: null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'nombre' => post('nombre'),
        'apellidos' => post('apellidos'),
        'email' => post('email'),
        'telefono' => post('telefono') ?: null,
        'rol' => post('rol', 'agente'),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];
    $selectedCustomRoleId = intval(post('custom_role_id')) ?: null;

    if (empty($data['nombre']) || empty($data['email'])) {
        $error = 'Nombre y email son obligatorios.';
    } else {
        try {
            if ($id) {
                $fields = []; $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }

                // Cambiar password solo si se proporciona
                if (!empty($_POST['password'])) {
                    $fields[] = "`password` = ?";
                    $values[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                $values[] = $id;
                $db->prepare("UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);

                if ($rolesSystemAvailable) {
                    if ($data['rol'] === 'admin' || !$selectedCustomRoleId) {
                        $db->prepare("DELETE FROM usuario_roles WHERE user_id = ?")->execute([$id]);
                    } else {
                        $stmtRole = $db->prepare("SELECT id FROM roles WHERE id = ? AND activo = 1 LIMIT 1");
                        $stmtRole->execute([$selectedCustomRoleId]);
                        if ($stmtRole->fetchColumn()) {
                            $db->prepare("REPLACE INTO usuario_roles (user_id, role_id) VALUES (?, ?)")->execute([$id, $selectedCustomRoleId]);
                        }
                    }
                }

                registrarActividad('editar', 'usuario', $id, $data['nombre']);
            } else {
                if (empty($_POST['password'])) {
                    $error = 'La contraseña es obligatoria para nuevos usuarios.';
                } else {
                    $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $fields = array_keys($data);
                    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                    $db->prepare("INSERT INTO usuarios (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                    $newUserId = intval($db->lastInsertId());

                    if ($rolesSystemAvailable && $data['rol'] !== 'admin' && $selectedCustomRoleId) {
                        $stmtRole = $db->prepare("SELECT id FROM roles WHERE id = ? AND activo = 1 LIMIT 1");
                        $stmtRole->execute([$selectedCustomRoleId]);
                        if ($stmtRole->fetchColumn()) {
                            $db->prepare("REPLACE INTO usuario_roles (user_id, role_id) VALUES (?, ?)")->execute([$newUserId, $selectedCustomRoleId]);
                        }
                    }

                    registrarActividad('crear', 'usuario', $newUserId, $data['nombre']);
                }
            }

            if (empty($error)) {
                setFlash('success', $usuario ? 'Usuario actualizado.' : 'Usuario creado.');
                header('Location: index.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Ya existe un usuario con ese email.';
            } else {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

$u = $usuario ?? [];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-person"></i> Datos del Usuario</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= sanitize($u['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Apellidos *</label>
                    <input type="text" name="apellidos" class="form-control" value="<?= sanitize($u['apellidos'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($u['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telefono</label>
                    <input type="tel" name="telefono" class="form-control" value="<?= sanitize($u['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contraseña <?= $id ? '(dejar vacio para no cambiar)' : '*' ?></label>
                    <input type="password" name="password" class="form-control" <?= $id ? '' : 'required' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rol</label>
                    <select name="rol" id="rol_base" class="form-select">
                        <option value="agente" <?= ($u['rol'] ?? 'agente') === 'agente' ? 'selected' : '' ?>>Agente</option>
                        <option value="admin" <?= ($u['rol'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                <?php if ($rolesSystemAvailable): ?>
                <div class="col-md-3">
                    <label class="form-label">Rol personalizado</label>
                    <select name="custom_role_id" id="custom_role_id" class="form-select">
                        <option value="">Sin rol personalizado</option>
                        <?php foreach ($customRoles as $cr): ?>
                            <option value="<?= intval($cr['id']) ?>" <?= intval($customRoleId ?: 0) === intval($cr['id']) ? 'selected' : '' ?>><?= sanitize($cr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">No afecta al rol base agente. Si eliges admin, se ignora este campo.</small>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($u['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Usuario</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php if ($rolesSystemAvailable): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var rolBase = document.getElementById('rol_base');
    var customRole = document.getElementById('custom_role_id');
    if (!rolBase || !customRole) return;

    function syncCustomRoleState() {
        if (rolBase.value === 'admin') {
            customRole.value = '';
            customRole.disabled = true;
        } else {
            customRole.disabled = false;
        }
    }

    rolBase.addEventListener('change', syncCustomRoleState);
    syncCustomRoleState();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
