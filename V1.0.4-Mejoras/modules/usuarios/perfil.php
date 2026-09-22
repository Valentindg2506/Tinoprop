<?php
$pageTitle = 'Mi Perfil';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([currentUserId()]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nombre = post('nombre');
    $apellidos = post('apellidos');
    $telefono = post('telefono') ?: null;

    if (empty($nombre)) {
        $error = 'El nombre es obligatorio.';
    } else {
        $db->prepare("UPDATE usuarios SET nombre = ?, apellidos = ?, telefono = ? WHERE id = ?")
            ->execute([$nombre, $apellidos, $telefono, currentUserId()]);

        // Cambiar password
        if (!empty($_POST['password_nueva'])) {
            if (empty($_POST['password_actual']) || !password_verify($_POST['password_actual'], $usuario['password'])) {
                $error = 'La contraseña actual no es correcta.';
            } elseif (strlen($_POST['password_nueva']) < 6) {
                $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
            } else {
                $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
                    ->execute([password_hash($_POST['password_nueva'], PASSWORD_DEFAULT), currentUserId()]);
                setFlash('success', 'Perfil y contraseña actualizados.');
                $_SESSION['user_nombre'] = $nombre . ' ' . $apellidos;
                header('Location: perfil.php');
                exit;
            }
        }

        if (empty($error)) {
            $_SESSION['user_nombre'] = $nombre . ' ' . $apellidos;
            setFlash('success', 'Perfil actualizado.');
            header('Location: perfil.php');
            exit;
        }
    }
}

$u = $usuario;
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <form method="POST">
            <?= csrfField() ?>
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-person"></i> Mis Datos</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" value="<?= sanitize($u['nombre']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" name="apellidos" class="form-control" value="<?= sanitize($u['apellidos']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= sanitize($u['email']) ?>" readonly>
                            <small class="text-muted">Contacta al administrador para cambiar el email</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefono</label>
                            <input type="tel" name="telefono" class="form-control" value="<?= sanitize($u['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control" value="<?= ucfirst($u['rol']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ultimo acceso</label>
                            <input type="text" class="form-control" value="<?= $u['ultimo_acceso'] ? formatFechaHora($u['ultimo_acceso']) : 'Nunca' ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-lock"></i> Cambiar Contraseña</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Contraseña actual</label>
                            <input type="password" name="password_actual" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nueva contraseña</label>
                            <input type="password" name="password_nueva" class="form-control">
                            <small class="text-muted">Minimo 6 caracteres. Dejar vacio para no cambiar.</small>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> Guardar Cambios</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
