<?php
$pageTitle = 'Roles y Permisos';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();

$db = getDB();

function ensureRolesSchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(120) NOT NULL UNIQUE,
        descripcion VARCHAR(255) DEFAULT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_roles_nombre (nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS role_modulos (
        role_id INT NOT NULL,
        modulo VARCHAR(100) NOT NULL,
        permitido TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (role_id, modulo),
        INDEX idx_role_modulo (modulo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS usuario_roles (
        user_id INT NOT NULL PRIMARY KEY,
        role_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_roles_role (role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

ensureRolesSchema($db);
$moduleCatalog = customRoleModuleCatalog();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'guardar_rol') {
        $roleId = intval(post('role_id'));
        $nombre = trim(post('nombre'));
        $descripcion = trim(post('descripcion'));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            setFlash('danger', 'El nombre del rol es obligatorio.');
            header('Location: roles.php');
            exit;
        }

        if (mb_strtolower($nombre, 'UTF-8') === 'agente') {
            setFlash('danger', 'El rol agente no se puede crear ni modificar desde aqui.');
            header('Location: roles.php');
            exit;
        }

        if ($roleId) {
            $stmt = $db->prepare("UPDATE roles SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion ?: null, $activo, $roleId]);
            registrarActividad('editar', 'role', $roleId, 'Rol: ' . $nombre);
            setFlash('success', 'Rol actualizado correctamente.');
        } else {
            $stmt = $db->prepare("INSERT INTO roles (nombre, descripcion, activo, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion ?: null, $activo, currentUserId()]);
            registrarActividad('crear', 'role', $db->lastInsertId(), 'Rol: ' . $nombre);
            setFlash('success', 'Rol creado correctamente.');
        }

        header('Location: roles.php');
        exit;
    }

    if ($accion === 'guardar_permisos') {
        $roleId = intval(post('role_id'));
        if (!$roleId) {
            setFlash('danger', 'Rol no valido.');
            header('Location: roles.php');
            exit;
        }

        $roleStmt = $db->prepare("SELECT nombre FROM roles WHERE id = ? LIMIT 1");
        $roleStmt->execute([$roleId]);
        $roleName = $roleStmt->fetchColumn();
        if (!$roleName) {
            setFlash('danger', 'Rol no encontrado.');
            header('Location: roles.php');
            exit;
        }

        $selected = $_POST['modulos'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $allowedKeys = array_keys($moduleCatalog);
        $selected = array_values(array_filter($selected, function ($m) use ($allowedKeys) {
            return in_array($m, $allowedKeys, true);
        }));

        $db->prepare("DELETE FROM role_modulos WHERE role_id = ?")->execute([$roleId]);
        if (!empty($selected)) {
            $ins = $db->prepare("INSERT INTO role_modulos (role_id, modulo, permitido) VALUES (?, ?, 1)");
            foreach ($selected as $modulo) {
                $ins->execute([$roleId, $modulo]);
            }
        }

        registrarActividad('editar', 'role_permissions', $roleId, 'Permisos actualizados para rol: ' . $roleName);
        setFlash('success', 'Permisos del rol actualizados.');
        header('Location: roles.php');
        exit;
    }

    if ($accion === 'eliminar_rol') {
        $roleId = intval(post('role_id'));
        if (!$roleId) {
            setFlash('danger', 'Rol no valido.');
            header('Location: roles.php');
            exit;
        }

        $roleStmt = $db->prepare("SELECT nombre FROM roles WHERE id = ? LIMIT 1");
        $roleStmt->execute([$roleId]);
        $roleName = (string) $roleStmt->fetchColumn();
        if ($roleName === '') {
            setFlash('danger', 'Rol no encontrado.');
            header('Location: roles.php');
            exit;
        }

        if (mb_strtolower($roleName, 'UTF-8') === 'agente') {
            setFlash('danger', 'El rol agente no se puede eliminar.');
            header('Location: roles.php');
            exit;
        }

        $db->prepare("DELETE FROM usuario_roles WHERE role_id = ?")->execute([$roleId]);
        $db->prepare("DELETE FROM role_modulos WHERE role_id = ?")->execute([$roleId]);
        $db->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);

        registrarActividad('eliminar', 'role', $roleId, 'Rol eliminado: ' . $roleName);
        setFlash('success', 'Rol eliminado correctamente.');
        header('Location: roles.php');
        exit;
    }
}

$roles = $db->query("SELECT r.*, (SELECT COUNT(*) FROM usuario_roles ur WHERE ur.role_id = r.id) AS total_usuarios FROM roles r ORDER BY r.nombre ASC")->fetchAll();
$allPermsStmt = $db->query("SELECT role_id, modulo FROM role_modulos WHERE permitido = 1");
$allPermsRows = $allPermsStmt->fetchAll();
$permsByRole = [];
foreach ($allPermsRows as $row) {
    $rid = intval($row['role_id']);
    if (!isset($permsByRole[$rid])) {
        $permsByRole[$rid] = [];
    }
    $permsByRole[$rid][$row['modulo']] = true;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a Usuarios</a>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="openRoleModal()"><i class="bi bi-plus-lg"></i> Nuevo Rol</button>
</div>

<div class="alert alert-info">
    Los roles personalizados aplican permisos por modulo. El rol base agente no se modifica.
</div>

<?php if (empty($roles)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-shield-lock fs-1 d-block mb-2"></i>
        Aun no hay roles personalizados.
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($roles as $role): ?>
    <?php $rid = intval($role['id']); $rolePerms = $permsByRole[$rid] ?? []; ?>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= sanitize($role['nombre']) ?></strong>
                    <?php if (!$role['activo']): ?><span class="badge bg-secondary ms-2">Inactivo</span><?php endif; ?>
                    <span class="badge bg-light text-dark ms-2"><?= intval($role['total_usuarios']) ?> usuarios</span>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" onclick='openRoleModal(<?= htmlspecialchars(json_encode($role), ENT_QUOTES, "UTF-8") ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" onsubmit="return confirm('Eliminar este rol? Se desasignara de los usuarios.')" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="accion" value="eliminar_rol">
                        <input type="hidden" name="role_id" value="<?= $rid ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3"><?= sanitize($role['descripcion'] ?? '') ?></p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="guardar_permisos">
                    <input type="hidden" name="role_id" value="<?= $rid ?>">
                    <div class="row g-2">
                        <?php foreach ($moduleCatalog as $modKey => $modLabel): ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= sanitize($modKey) ?>" id="r<?= $rid ?>_<?= sanitize($modKey) ?>" <?= !empty($rolePerms[$modKey]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="r<?= $rid ?>_<?= sanitize($modKey) ?>"><?= sanitize($modLabel) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Guardar permisos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="accion" value="guardar_rol">
        <input type="hidden" name="role_id" id="role_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="roleModalTitle">Nuevo Rol</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" class="form-control" name="nombre" id="role_nombre" required maxlength="120">
          </div>
          <div class="mb-3">
            <label class="form-label">Descripcion</label>
            <textarea class="form-control" name="descripcion" id="role_descripcion" rows="3" maxlength="255"></textarea>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activo" id="role_activo" checked>
            <label class="form-check-label" for="role_activo">Activo</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openRoleModal(role) {
    const idEl = document.getElementById('role_id');
    const nameEl = document.getElementById('role_nombre');
    const descEl = document.getElementById('role_descripcion');
    const activeEl = document.getElementById('role_activo');
    const titleEl = document.getElementById('roleModalTitle');

    if (role && role.id) {
        idEl.value = role.id;
        nameEl.value = role.nombre || '';
        descEl.value = role.descripcion || '';
        activeEl.checked = !!parseInt(role.activo, 10);
        titleEl.textContent = 'Editar Rol';
    } else {
        idEl.value = '0';
        nameEl.value = '';
        descEl.value = '';
        activeEl.checked = true;
        titleEl.textContent = 'Nuevo Rol';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
