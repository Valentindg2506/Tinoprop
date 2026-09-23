<?php
// Configurar atributos de seguridad antes de iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', 1);
    session_start();
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// Forzar HTTPS en produccion
forceHTTPS();

// Si ya esta logueado, redirigir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$blocked = false;
$remaining = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Token de seguridad invalido. Recarga la pagina e intenta de nuevo.';
    } else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor, introduce tu email y contraseña.';
    } else {
        $result = login($email, $password);

        if (isset($result['success'])) {
            header('Location: index.php');
            exit;
        } elseif ($result['error'] === 'blocked') {
            $blocked = true;
            $remaining = $result['remaining'];
            $minutos = ceil($remaining / 60);
            $error = "Demasiados intentos fallidos. Cuenta bloqueada durante $minutos minutos.";
        } else {
            $attemptsLeft = LOGIN_MAX_ATTEMPTS - ($_SESSION['login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? '')]['count'] ?? 0);
            if ($attemptsLeft <= 2 && $attemptsLeft > 0) {
                $error = "Email o contraseña incorrectos. Te quedan $attemptsLeft intentos.";
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
        }
    }
    } // end CSRF else
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesion - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <div class="login-logo"><i class="bi bi-buildings"></i></div>
                <h2><?= APP_NAME ?></h2>
                <p class="text-muted">Plataforma Inmobiliaria</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php if ($blocked): ?>
                <i class="bi bi-shield-exclamation"></i>
                <?php endif; ?>
                <?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php" <?= $blocked ? 'class="opacity-50"' : '' ?>>
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="tu@email.com"
                               value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus <?= $blocked ? 'disabled' : '' ?>>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Tu contraseña" required <?= $blocked ? 'disabled' : '' ?>>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2" <?= $blocked ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesion
                </button>
            </form>

            <?php if ($blocked): ?>
            <div class="text-center mt-3">
                <small class="text-muted" id="countdown">Bloqueado por <span id="timer"><?= ceil($remaining / 60) ?></span> minutos</small>
            </div>
            <script>
                let seconds = <?= $remaining ?>;
                const timer = document.getElementById('timer');
                setInterval(function() {
                    seconds--;
                    if (seconds <= 0) { location.reload(); return; }
                    const min = Math.ceil(seconds / 60);
                    timer.textContent = min;
                }, 1000);
            </script>
            <?php endif; ?>

            <div class="text-center mt-4">
                <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
            </div>
        </div>
    </div>
</body>
</html>
