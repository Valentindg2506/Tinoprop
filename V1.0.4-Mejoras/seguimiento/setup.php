<?php
/**
 * setup.php — Crea el usuario administrador de CAS.
 * EJECUTAR UNA SOLA VEZ en tu hosting: visita tudominio.com/setup.php
 * Elimina o renombra este archivo después de usarlo.
 */

// Cargar config
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim(trim($v), '"\'');
    }
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario    = trim($_POST['usuario'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $setupKey   = $_POST['setup_key'] ?? '';

    // Clave de seguridad básica para evitar accesos no autorizados
    if ($setupKey !== ($_ENV['SETUP_KEY'] ?? 'cas_setup_2024')) {
        $errors[] = 'Clave de configuración incorrecta.';
    }
    if (strlen($usuario) < 3) $errors[] = 'El usuario debe tener al menos 3 caracteres.';
    if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden.';

    if (!$errors) {
        try {
            $pdo = new PDO(
                'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') .
                ';dbname=' . ($_ENV['DB_NAME'] ?? '') . ';charset=utf8mb4',
                $_ENV['DB_USER'] ?? '',
                $_ENV['DB_PASS'] ?? '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (usuario, password_hash) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE password_hash = ?'
            );
            $stmt->execute([$usuario, $hash, $hash]);
            $success = true;
        } catch (Exception $e) {
            $errors[] = 'Error de base de datos: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CAS — Configuración inicial</title>
<style>
body{font-family:system-ui,sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{background:#fff;border-radius:16px;padding:32px;max-width:400px;width:90%;box-shadow:0 4px 24px rgba(0,0,0,.1)}
h1{font-size:22px;color:#1e293b;margin:0 0 4px}
p{color:#64748b;font-size:14px;margin:0 0 24px}
label{display:block;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
input{width:100%;padding:10px 12px;border:2px solid #e2e8f0;border-radius:8px;font-size:14px;box-sizing:border-box;margin-bottom:14px;outline:none}
input:focus{border-color:#6366f1}
button{width:100%;padding:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer}
.err{background:#fef2f2;border:2px solid #fca5a5;border-radius:8px;padding:10px 14px;color:#ef4444;font-size:13px;margin-bottom:14px}
.ok{background:#f0fdf4;border:2px solid #86efac;border-radius:8px;padding:14px;color:#16a34a;font-size:14px;font-weight:600}
.warn{background:#fffbeb;border:2px solid #fbbf24;border-radius:8px;padding:10px 14px;color:#92400e;font-size:12px;margin-top:14px}
</style>
</head>
<body>
<div class="card">
  <h1>⚙️ CAS — Configuración</h1>
  <p>Crea tu usuario administrador. Ejecuta esto una sola vez.</p>

  <?php if ($success): ?>
    <div class="ok">
      ✅ Usuario creado correctamente. Ahora puedes acceder a la app.<br><br>
      <strong>Importante:</strong> Elimina o renombra este archivo (setup.php) del servidor para evitar accesos no autorizados.
    </div>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="err"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif ?>
    <form method="post">
      <label>Clave de configuración</label>
      <input type="password" name="setup_key" placeholder="cas_setup_2024" required>

      <label>Usuario</label>
      <input type="text" name="usuario" value="mariano" required>

      <label>Contraseña (mín. 8 caracteres)</label>
      <input type="password" name="password" required>

      <label>Repetir contraseña</label>
      <input type="password" name="password2" required>

      <button type="submit">Crear usuario</button>
    </form>
    <div class="warn">
      ⚠️ La clave de configuración por defecto es <code>cas_setup_2024</code>.
      Puedes cambiarla añadiendo <code>SETUP_KEY=tu_clave</code> al .env.
    </div>
  <?php endif ?>
</div>
</body>
</html>
