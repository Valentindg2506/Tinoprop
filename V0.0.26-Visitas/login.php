<?php
/*
 * Archivo: login.php
 * Rol: pantalla de acceso con diseño dual (login/registro) estilo Proyecto-Entornos.
 * Flujo: procesa login por POST y muestra registro en panel deslizante contra register.php.
 */
require_once __DIR__ . '/inc/bootstrap.php';

$mensaje = '';
$errores_registro = $_SESSION['errores_registro'] ?? [];
$datos_registro = $_SESSION['datos_registro'] ?? [];
unset($_SESSION['errores_registro'], $_SESSION['datos_registro']);

// Si ya existe sesión activa, evita mostrar login y redirige al panel principal.
if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// Procesa autenticación cuando el formulario se envía por POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lee credenciales del formulario (email con trim para evitar espacios accidentales).
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Aplica las mismas restricciones de Proyecto-Entornos en inicio de sesión.
    if ($email === '' || $password === '') {
        $mensaje = 'Completa email y contraseña.';
    } elseif (!validar_email($email)) {
        $mensaje = 'El correo no tiene un formato válido.';
    } elseif (!validar_password_segura($password)) {
        $mensaje = 'La contraseña debe tener 8-16 carácteres, 1 Mayúscula y 1 Símbolo';
    } else {
        $pdo = db();

        // Busca usuario por email y obtiene hash de contraseña para verificar.
        $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        // Si hash coincide, inicializa sesión con los datos de contexto necesarios.
        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            $_SESSION['usuario'] = [
                'id' => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
                'rol' => $usuario['rol'],
            ];
            header('Location: index.php');
            exit;
        }

        // Mensaje genérico para no revelar si falló email o contraseña.
        $mensaje = 'Credenciales invalidas.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TinoProp</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container<?php echo !empty($errores_registro) ? ' right-panel-active' : ''; ?>" id="container">
        <div class="form-container sign-up-container">
            <form action="register.php" method="POST">
                <h1>Crear Cuenta</h1>

                <div class="input-group">
                    <input type="text" name="nombre" placeholder="Nombre completo" required
                           value="<?php echo e($datos_registro['nombre'] ?? ''); ?>">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required
                           value="<?php echo e($datos_registro['email'] ?? ''); ?>"
                           style="<?php echo isset($errores_registro['email']) ? 'border: 2px solid red;' : ''; ?>">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <?php if (isset($errores_registro['email'])): ?>
                    <small class="error-text"><?php echo e($errores_registro['email']); ?></small>
                <?php endif; ?>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Contraseña" required minlength="8" maxlength="16"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,16}"
                           title="8-16 caracteres, al menos 1 mayúscula y 1 símbolo."
                           style="<?php echo isset($errores_registro['password']) ? 'border: 2px solid red;' : ''; ?>">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <?php if (isset($errores_registro['password'])): ?>
                    <small class="error-text"><?php echo e($errores_registro['password']); ?></small>
                <?php endif; ?>

                <div class="input-group">
                    <input type="password" name="password_repetir" placeholder="Repetir contraseña" required minlength="8" maxlength="16"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,16}"
                           title="8-16 caracteres, al menos 1 mayúscula y 1 símbolo.">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <?php if (isset($errores_registro['password_repetir'])): ?>
                    <small class="error-text"><?php echo e($errores_registro['password_repetir']); ?></small>
                <?php endif; ?>

                <?php if (isset($errores_registro['general'])): ?>
                    <small class="error-text"><?php echo e($errores_registro['general']); ?></small>
                <?php endif; ?>

                <button type="submit">Registrarse</button>
            </form>
        </div>

        <div class="form-container sign-in-container">
            <form method="POST" action="login.php">
                <h1>Iniciar Sesión</h1>

                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required
                           value="<?php echo e($_POST['email'] ?? ''); ?>">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Contraseña" required minlength="8" maxlength="16"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,16}"
                           title="8-16 caracteres, al menos 1 mayúscula y 1 símbolo.">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <?php if ($mensaje): ?>
                    <p class="error-text"><?php echo e($mensaje); ?></p>
                <?php endif; ?>

                <button type="submit">Iniciar Sesión</button>
            </form>
        </div>

        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>¡Hola, Bienvenido!</h1>
                    <p>Introduce tus datos personales y comienza tu experiencia con TinoProp.</p>
                    <button class="ghost" id="signIn">Iniciar Sesión</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>¡Bienvenido de nuevo!</h1>
                    <p>Para mantenerte conectado con nosotros, inicia sesión con tus datos.</p>
                    <button class="ghost" id="signUp">Registrarse</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const container = document.getElementById('container');

        signUpButton.addEventListener('click', () => {
            container.classList.add('right-panel-active');
        });

        signInButton.addEventListener('click', () => {
            container.classList.remove('right-panel-active');
        });
    </script>
</body>
</html>
