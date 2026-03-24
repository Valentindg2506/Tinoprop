<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);
$__env_path = dirname(__DIR__) . '/.env';
if (is_file($__env_path)) {
    $__lines = file($__env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($__lines as $__line) {
        $__line = trim($__line);
        if ($__line === '' || $__line[0] === '#') {
            continue;
        }
        if (strpos($__line, '=') !== false) {
            [$__k, $__v] = explode('=', $__line, 2);
            $__k = trim($__k);
            $__v = trim($__v);
            if (!array_key_exists($__k, $_ENV)) {
                $_ENV[$__k] = $__v;
                putenv("$__k=$__v");
            }
        }
    }
}
unset($__env_path, $__lines, $__line, $__k, $__v);
$log_path = $_ENV['ERROR_LOG_PATH'] ?? getenv('ERROR_LOG_PATH') ?: dirname(__DIR__) . '/logs/php_errors.log';
if (!str_starts_with($log_path, '/')) {
    $log_path = dirname(__DIR__) . '/' . $log_path;
}
ini_set('error_log', $log_path);
unset($log_path);
set_exception_handler(function (Throwable $e): void {
    error_log('[TinoProp FATAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title>'
       . '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f5f5;color:#333}'
       . '.box{text-align:center;padding:2rem;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:500px}'
       . 'h1{color:#c0392b}a{color:#2980b9}</style></head>'
       . '<body><div class="box"><h1>Error interno</h1>'
       . '<p>Se ha producido un error inesperado. El equipo técnico ha sido notificado.</p>'
       . '<a href="index.php">Volver al inicio</a></div></body></html>';
    exit(1);
});
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/idioma.php';
$pdo = db();
$lang_preferido = preferencias_usuario_get($pdo, 'ui.idioma');
if ($lang_preferido && idioma_es_valido($lang_preferido)) {
    idioma_establecer($lang_preferido);
}
i18n_iniciar_buffer();
$publico = ['login.php', 'logout.php', 'cambiar-password.php', 'aceptar-terminos.php'];
$script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!in_array($script_actual, $publico, true) && empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}
if (!in_array($script_actual, $publico, true)
    && !empty($_SESSION['usuario'])
    && !empty($_SESSION['usuario']['password_temporal'])
) {
    header('Location: cambiar-password.php');
    exit;
}
$es_seccion_legal = ($script_actual === 'index.php' && ($_GET['seccion'] ?? '') === 'legal');
if (!in_array($script_actual, $publico, true)
    && !$es_seccion_legal
    && !empty($_SESSION['usuario'])
    && !usuario_ha_aceptado_terminos()
) {
    header('Location: aceptar-terminos.php');
    exit;
}
register_shutdown_function('i18n_finalizar_buffer');
