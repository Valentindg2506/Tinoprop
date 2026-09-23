<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

// Sesión pública para token CSRF (no requiere login)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

$db = getDB();

$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM formularios WHERE id = ? AND activo = 1");
$stmt->execute([$id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

$mensaje = '';
$tipoMsg = '';

// Generar token CSRF de un solo uso por sesión + formulario
$csrfKey = 'csrf_form_' . $id;
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[$csrfKey];

if ($form && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF antes de cualquier procesamiento
    $providedToken = $_POST['_csrf'] ?? '';
    if (!hash_equals($csrfToken, $providedToken)) {
        $mensaje = 'Solicitud inválida. Recarga la página e inténtalo de nuevo.';
        $tipoMsg = 'danger';
        goto render;
    }
    // Rotar token tras uso válido
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION[$csrfKey];

    $campos = json_decode($form['campos'], true) ?: [];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Rate limit: max 5/hora por IP
    $stmtRate = $db->prepare("SELECT COUNT(*) FROM formulario_envios WHERE formulario_id = ? AND ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmtRate->execute([$id, $ip]);
    if ($stmtRate->fetchColumn() >= 5) {
        $mensaje = 'Has superado el limite de envios. Intenta de nuevo mas tarde.';
        $tipoMsg = 'danger';
    } else {
        $datos = [];
        $errores = [];
        foreach ($campos as $campo) {
            $key = $campo['label'];
            $val = trim($_POST[$key] ?? '');
            if ($campo['required'] && empty($val) && $campo['type'] !== 'checkbox') {
                $errores[] = $key . ' es obligatorio.';
            }
            if ($campo['type'] === 'email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $errores[] = $key . ' no es un email valido.';
            }
            $datos[$key] = $campo['type'] === 'checkbox' ? (!empty($val) ? 'Si' : 'No') : $val;
        }

        // Validar consentimiento RGPD obligatorio
        if (empty($_POST['consentimiento_rgpd'])) {
            $errores[] = 'Debes aceptar la Política de Privacidad para enviar el formulario.';
        }

        if (!empty($errores)) {
            $mensaje = implode('<br>', $errores);
            $tipoMsg = 'danger';
        } else {
            $clienteId = null;
            if ($form['crear_cliente']) {
                $nombre = '';
                $email = '';
                $telefono = '';
                foreach ($campos as $c) {
                    $v = $datos[$c['label']] ?? '';
                    if ($c['type'] === 'email' && !empty($v)) $email = $v;
                    if ($c['type'] === 'telefono' && !empty($v)) $telefono = $v;
                    if (stripos($c['label'], 'nombre') !== false && !empty($v)) $nombre = $v;
                }
                if ($nombre || $email) {
                    $parts = explode(' ', $nombre, 2);
                    $stmtCli = $db->prepare("INSERT INTO clientes (nombre, apellidos, email, telefono, origen, tipo, activo) VALUES (?, ?, ?, ?, 'web', 'comprador', 1)");
                    $stmtCli->execute([$parts[0] ?? '', $parts[1] ?? '', $email, $telefono]);
                    $clienteId = $db->lastInsertId();
                }
            }

            // Asegurar columnas de consentimiento RGPD (auto-migración silenciosa)
            try {
                $db->exec("ALTER TABLE formulario_envios ADD COLUMN IF NOT EXISTS consentimiento_rgpd TINYINT(1) NOT NULL DEFAULT 0");
                $db->exec("ALTER TABLE formulario_envios ADD COLUMN IF NOT EXISTS consentimiento_at DATETIME NULL");
                $db->exec("ALTER TABLE formulario_envios ADD COLUMN IF NOT EXISTS consentimiento_ip VARCHAR(45) NULL");
            } catch (Throwable $e) {}

            $stmtIns = $db->prepare("INSERT INTO formulario_envios (formulario_id, datos, ip, user_agent, cliente_id, consentimiento_rgpd, consentimiento_at, consentimiento_ip) VALUES (?, ?, ?, ?, ?, 1, NOW(), ?)");
            $stmtIns->execute([$id, json_encode($datos), $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $clienteId, $ip]);

            if (!empty($form['redirect_url'])) {
                header('Location: ' . safeRedirectTarget($form['redirect_url'], APP_URL));
                exit;
            }
            $mensaje = $form['mensaje_exito'];
            $tipoMsg = 'success';
        }
    }
}

$color = $form['color_primario'] ?? '#10b981';
$campos = $form ? (json_decode($form['campos'], true) ?: []) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['nombre'] ?? 'Formulario') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --color-primary: <?= $color ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .form-card { background: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); max-width: 600px; width: 100%; }
        .form-header { background: var(--color-primary); color: #fff; padding: 2rem; border-radius: 16px 16px 0 0; text-align: center; }
        .btn-submit { background: var(--color-primary); border-color: var(--color-primary); color: #fff; font-weight: 600; padding: 0.75rem 2rem; }
        .btn-submit:hover { filter: brightness(0.9); color: #fff; }
        .form-control:focus, .form-select:focus { border-color: var(--color-primary); box-shadow: 0 0 0 0.2rem rgba(0,0,0,0.05); }
    </style>
</head>
<body>
<?php render: if (!$form): ?>
    <div class="text-center">
        <i class="bi bi-exclamation-circle" style="font-size:3rem;color:#94a3b8"></i>
        <h4 class="mt-3 text-muted">Formulario no disponible</h4>
    </div>
<?php else: ?>
    <div class="form-card m-3">
        <div class="form-header">
            <h3 class="mb-1"><?= htmlspecialchars($form['nombre']) ?></h3>
            <?php if ($form['descripcion']): ?>
            <p class="mb-0 opacity-75"><?= htmlspecialchars($form['descripcion']) ?></p>
            <?php endif; ?>
        </div>
        <div class="p-4">
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipoMsg ?>">
                <?php if ($tipoMsg === 'success'): ?><i class="bi bi-check-circle-fill me-2"></i><?php endif; ?>
                <?= $mensaje ?>
            </div>
            <?php endif; ?>

            <?php if ($tipoMsg !== 'success'): ?>
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <?php foreach ($campos as $c): ?>
                <div class="mb-3">
                    <label class="form-label fw-medium">
                        <?= htmlspecialchars($c['label']) ?>
                        <?php if ($c['required']): ?><span class="text-danger">*</span><?php endif; ?>
                    </label>
                    <?php if ($c['type'] === 'textarea'): ?>
                        <textarea name="<?= htmlspecialchars($c['label']) ?>" class="form-control" rows="3" placeholder="<?= htmlspecialchars($c['placeholder'] ?? '') ?>" <?= $c['required'] ? 'required' : '' ?>><?= htmlspecialchars($_POST[$c['label']] ?? '') ?></textarea>
                    <?php elseif ($c['type'] === 'select'): ?>
                        <select name="<?= htmlspecialchars($c['label']) ?>" class="form-select" <?= $c['required'] ? 'required' : '' ?>>
                            <option value="">Seleccionar...</option>
                            <?php foreach (explode(',', $c['options'] ?? '') as $opt): ?>
                            <option value="<?= htmlspecialchars(trim($opt)) ?>" <?= ($_POST[$c['label']] ?? '') === trim($opt) ? 'selected' : '' ?>><?= htmlspecialchars(trim($opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($c['type'] === 'checkbox'): ?>
                        <div class="form-check">
                            <input type="checkbox" name="<?= htmlspecialchars($c['label']) ?>" class="form-check-input" value="1">
                            <label class="form-check-label">Si</label>
                        </div>
                    <?php else:
                        $htmlType = match($c['type']) { 'email'=>'email', 'telefono'=>'tel', 'numero'=>'number', 'fecha'=>'date', default=>'text' };
                    ?>
                        <input type="<?= $htmlType ?>" name="<?= htmlspecialchars($c['label']) ?>" class="form-control" placeholder="<?= htmlspecialchars($c['placeholder'] ?? '') ?>" value="<?= htmlspecialchars($_POST[$c['label']] ?? '') ?>" <?= $c['required'] ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <!-- Consentimiento RGPD — obligatorio (Art. 13 RGPD / LOPDGDD) -->
                <div class="mt-4 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.85rem;color:#64748b">
                    <p class="mb-2">
                        <strong>Información sobre protección de datos:</strong>
                        Los datos facilitados serán tratados por el responsable de este formulario con la finalidad de
                        gestionar tu solicitud. Tus datos no serán cedidos a terceros salvo obligación legal.
                        Puedes ejercer tus derechos de acceso, rectificación, supresión y demás
                        contactando con el responsable del tratamiento.
                    </p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="consentimiento_rgpd"
                               id="consentimientoRgpd" value="1" required
                               <?= !empty($_POST['consentimiento_rgpd']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-medium" for="consentimientoRgpd" style="color:#374151">
                            He leído y acepto el tratamiento de mis datos personales según la
                            <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/legal/privacidad.php"
                               target="_blank" style="color:var(--color-primary)">Política de Privacidad</a>. *
                        </label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-submit btn-lg"><?= htmlspecialchars($form['texto_boton']) ?></button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
