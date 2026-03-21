<?php
/*
 * Archivo: login.php — V0.0.30 Pre-Producción
 * Rol: pantalla de acceso. Solo login (los usuarios los crea el SuperAdmin o el Jefe).
 */
require_once __DIR__ . '/inc/bootstrap.php';

$mensaje = '';

// Si ya existe sesión activa, redirige al panel principal.
if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// Procesa autenticación cuando el formulario se envía por POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $mensaje = 'Completa email y contraseña.';
    } elseif (!validar_email($email)) {
        $mensaje = 'El correo no tiene un formato válido.';
    } else {
        $pdo = db();
        usuarios_asegurar_tabla($pdo);

        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.email, u.password_hash, u.rol, u.inmobiliaria_id, u.activo, u.password_temporal,
                    u.terminos_aceptados,
                    COALESCE(i.nombre, "Sin asignar") AS inmobiliaria_nombre,
                    COALESCE(i.activa, 1) AS inmobiliaria_activa
             FROM usuarios u
             LEFT JOIN inmobiliarias i ON u.inmobiliaria_id = i.id
             WHERE u.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            if (empty($usuario['activo'])) {
                $mensaje = 'Tu cuenta está desactivada. Contacta con tu administrador.';
            } elseif (empty($usuario['inmobiliaria_activa']) && $usuario['rol'] !== 'superadmin') {
                $mensaje = 'Tu inmobiliaria está desactivada. Contacta con el administrador.';
            } else {
                session_regenerate_id(true);
                $_SESSION['usuario'] = [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'email' => $usuario['email'],
                    'rol' => $usuario['rol'],
                    'inmobiliaria_id' => (int) $usuario['inmobiliaria_id'],
                    'inmobiliaria_nombre' => $usuario['inmobiliaria_nombre'],
                    'password_temporal' => (bool) $usuario['password_temporal'],
                    'terminos_aceptados' => $usuario['terminos_aceptados'] ?? null,
                ];
                actividad_registrar($pdo, 'login', 'usuario', $usuario['id'], 'Inicio de sesión: ' . $usuario['nombre']);

                // Si tiene password temporal, redirigir a cambio obligatorio
                if ($usuario['password_temporal']) {
                    header('Location: cambiar-password.php');
                } elseif (!usuario_ha_aceptado_terminos()) {
                    header('Location: aceptar-terminos.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            }
        } else {
            $mensaje = 'Credenciales inválidas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — TinoProp</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container login-solo" id="container">
        <div class="form-container sign-in-container" style="width:100%;">
            <form method="POST" action="login.php">
                <h1>🏠 TinoProp</h1>
                <p class="login-subtitulo">Gestión Inmobiliaria</p>

                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required
                           value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email">
                    <i class="fa-solid fa-envelope"></i>
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Contraseña" required
                           autocomplete="current-password">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <?php if ($mensaje): ?>
                    <p class="error-text"><?php echo e($mensaje); ?></p>
                <?php endif; ?>

                <?php echo csrf_field(); ?>
                <button type="submit">Iniciar Sesión</button>
                <p class="login-ayuda">Contacta con tu administrador si no tienes cuenta.</p>
            </form>
        </div>
    </div>
</body>
</html>
