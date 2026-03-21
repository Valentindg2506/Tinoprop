<?php
/*
 * Archivo: cambiar-password.php — V0.0.31 Pre-Producción
 * Rol: pantalla de cambio obligatorio de contraseña temporal.
 * Flujo: el usuario con password_temporal=1 llega aquí tras el login.
 *        Debe establecer una nueva contraseña que cumpla la política de seguridad.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Si no hay sesión, redirigir al login.
if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Si no tiene password temporal, no necesita estar aquí.
if (empty($_SESSION['usuario']['password_temporal'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nueva = $_POST['nueva_password'] ?? '';
    $repetir = $_POST['repetir_password'] ?? '';

    if ($nueva === '' || $repetir === '') {
        $mensaje = 'Completa ambos campos de contraseña.';
        $mensaje_tipo = 'error';
    } elseif (!validar_password_segura($nueva)) {
        $mensaje = 'La contraseña debe tener al menos 8 caracteres, 1 mayúscula y 1 símbolo.';
        $mensaje_tipo = 'error';
    } elseif ($nueva !== $repetir) {
        $mensaje = 'Las contraseñas no coinciden.';
        $mensaje_tipo = 'error';
    } else {
        $pdo = db();
        $uid = (int) $_SESSION['usuario']['id'];
        $hash = password_hash($nueva, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash, password_temporal = 0 WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $uid]);

        // Quitar la marca de la sesión
        $_SESSION['usuario']['password_temporal'] = false;

        actividad_registrar($pdo, 'editar', 'usuario', $uid, 'Contraseña temporal cambiada por el usuario');

        header('Location: index.php');
        exit;
    }
}

$nombre = e($_SESSION['usuario']['nombre'] ?? 'Usuario');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña — TinoProp</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container login-solo" id="container">
        <div class="form-container sign-in-container" style="width:100%;">
            <form method="POST" action="cambiar-password.php">
                <h1>🔑 Nueva Contraseña</h1>
                <p class="login-subtitulo">Hola <?php echo $nombre; ?>, tu cuenta tiene una contraseña temporal.<br>Debes cambiarla antes de continuar.</p>

                <div class="input-group">
                    <input type="password" name="nueva_password" placeholder="Nueva contraseña" required
                           minlength="8"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}"
                           title="Mínimo 8 caracteres, al menos 1 mayúscula y 1 símbolo."
                           autocomplete="new-password">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <div class="input-group">
                    <input type="password" name="repetir_password" placeholder="Repetir contraseña" required
                           minlength="8"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}"
                           title="Mínimo 8 caracteres, al menos 1 mayúscula y 1 símbolo."
                           autocomplete="new-password">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <?php if ($mensaje): ?>
                    <p class="error-text"><?php echo e($mensaje); ?></p>
                <?php endif; ?>

                <?php echo csrf_field(); ?>
                <button type="submit">Establecer Contraseña</button>

                <p class="login-ayuda">
                    Requisitos: mínimo 8 caracteres, al menos 1 mayúscula y 1 símbolo.
                </p>
            </form>
        </div>
    </div>
</body>
</html>
