<?php
/*
 * Archivo: login.php
 * Rol: autenticación de usuarios.
 * Flujo: valida credenciales por POST, consulta tabla usuarios, verifica password_hash,
 *        crea sesión y redirige a index.php.
 */
require_once __DIR__ . '/inc/bootstrap.php';

$mensaje = '';

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

    // Validación mínima de entrada antes de consultar DB.
    if ($email !== '' && $password !== '') {
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
    }

    // Mensaje genérico para no revelar si falló email o contraseña.
    $mensaje = 'Credenciales invalidas.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TinoProp</title>
    <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
    <main class="login_contenedor">
        <div class="login_shell">
            <div class="login_hero">
                <span class="login_chip">TinoProp CRM</span>
                <h1>Gestiona con claridad</h1>
                <p>Centraliza clientes, propiedades y tareas en un panel ligero y profesional.</p>
                <ul class="login_bullets">
                    <li>Acceso seguro y unificado</li>
                    <li>Clientes y propiedades al día</li>
                    <li>Recordatorios y tareas sin olvidos</li>
                </ul>
            </div>

            <section class="login_card">
                <header class="login_header">
                    <p class="login_badge">Versión 2.3</p>
                    <h2>Acceso al panel</h2>
                    <p class="login_subtitulo">Ingresa con tus credenciales corporativas.</p>
                </header>

                <?php if ($mensaje): ?>
                    <div class="login_alerta"><?php echo e($mensaje); ?></div>
                <?php endif; ?>

                <form method="POST" class="login_form">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required>

                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>

                    <button class="btn_guardar" type="submit">Entrar</button>
                </form>

                <p class="login_hint">Tus credenciales se transmiten de forma cifrada.</p>
            </section>
        </div>
    </main>
</body>
</html>
