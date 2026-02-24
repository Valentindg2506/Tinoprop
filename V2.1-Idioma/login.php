<?php
require_once __DIR__ . '/inc/bootstrap.php';

$mensaje = '';

if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email !== '' && $password !== '') {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

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
