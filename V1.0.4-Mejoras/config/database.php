<?php
/**
 * Configuracion de Base de Datos y Aplicacion
 * CRM Inmobiliario - España
 *
 * INSTRUCCIONES: Crea un archivo .env en la raiz del proyecto con tus credenciales
 * cp .env.example .env
 */

// Cargar variables de entorno desde .env si existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // No sobrescribir variables de entorno del sistema
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

/**
 * Obtener variable de entorno con fallback estable.
 */
function envOrDefault($key, $default) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

// Configuracion de la base de datos (sin fallbacks hardcodeados por seguridad)
define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
define('DB_NAME', envOrDefault('DB_NAME', ''));
define('DB_USER', envOrDefault('DB_USER', ''));
define('DB_PASS', envOrDefault('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// Configuracion de la aplicacion
define('APP_NAME', 'Tinoprop.es');
define('APP_VERSION', '1.1.0');
define('APP_URL', getenv('APP_URL') ?: 'https://Tinoprop.es');
define('APP_TIMEZONE', 'Europe/Madrid');
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'development' o 'production'

// Configuracion de uploads
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('IMAGE_MAX_WIDTH', 1920);
define('IMAGE_MAX_HEIGHT', 1080);
define('IMAGE_QUALITY', 85);

// Configuracion de email
define('MAIL_METHOD', 'mail'); // 'mail' para mail() de PHP, 'smtp' para SMTP directo
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@tudominio.com');
define('SMTP_FROM_NAME', 'Tinoprop');

// Configuracion de sesion
define('SESSION_LIFETIME', 3600 * 8); // 8 horas

// Seguridad: Proteccion fuerza bruta
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos en segundos

// Seguridad: Requisitos de contraseña
define('PASSWORD_MIN_LENGTH', 8);

// Logs
define('LOG_DIR', __DIR__ . '/../logs/');
define('LOG_ERRORS', true);

// Backup
define('BACKUP_DIR', __DIR__ . '/../backups/');

// LOPD/RGPD
define('RGPD_EMPRESA', 'Tu Empresa Inmobiliaria S.L.');
define('RGPD_CIF', 'B12345678');
define('RGPD_DIRECCION', 'Calle Ejemplo 1, 28001 Madrid');
define('RGPD_EMAIL_DPD', 'protecciondatos@tudominio.com');
define('RGPD_FINALIDAD', 'Gestion de la relacion comercial inmobiliaria, busqueda de inmuebles y tramitacion de operaciones de compraventa o alquiler.');
define('RGPD_BASE_LEGAL', 'Ejecucion de contrato y consentimiento del interesado.');

// Zona horaria
date_default_timezone_set(APP_TIMEZONE);

/**
 * Obtener secreto desde entorno con fallback
 */
function getEnvSecret($key, $fallback = '') {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return $fallback;
}

/**
 * Bloquear instaladores en produccion salvo que se provea clave valida.
 * Esto evita ejecuciones accidentales de install*.php en servidores activos.
 */
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isInstallerScript = (bool) preg_match('/^install.*\.php$/', $scriptName);
if ($isInstallerScript && APP_ENV === 'production') {
    $expectedInstallerKey = getEnvSecret('INSTALLER_KEY', '');
    $providedInstallerKey = $_GET['install_key'] ?? $_POST['install_key'] ?? '';

    if ($expectedInstallerKey === '') {
        // Clave no configurada — bloquear y avisar en log para que el admin la genere
        error_log('[SECURITY] INSTALLER_KEY no definida en .env — acceso a instalador bloqueado (' . $scriptName . ')');
        http_response_code(403);
        echo 'Instalador bloqueado en produccion. Configura INSTALLER_KEY en .env.';
        exit;
    }
    if (!hash_equals($expectedInstallerKey, (string)$providedInstallerKey)) {
        error_log('[SECURITY] Intento de acceso a instalador con clave incorrecta (' . $scriptName . ') IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(403);
        echo 'Clave de instalacion incorrecta.';
        exit;
    }
}

// Configurar errores segun entorno
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Crear directorio de logs si no existe
if (LOG_ERRORS && !is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Conexion PDO — usa $GLOBALS para permitir liberación explícita con releaseDB()
function getDB(): PDO {
    if (empty($GLOBALS['__db_pdo'])) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $GLOBALS['__db_pdo'] = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $GLOBALS['__db_pdo']->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            logError('DB Connection Error: ' . $e->getMessage());
            die('Error de conexion a la base de datos. Revise los logs para mas detalles.');
        }
    }
    return $GLOBALS['__db_pdo'];
}

/**
 * Libera la conexión MySQL activa devolviendo el slot al pool del servidor.
 * Pensado para usarse en bucles de long-polling donde el hilo duerme entre queries.
 * Llama a getDB() para reconectar cuando sea necesario.
 */
function releaseDB(): void {
    $GLOBALS['__db_pdo'] = null;
}

/**
 * Registrar error en archivo de log
 */
function logError($message, $context = []) {
    if (!LOG_ERRORS) return;
    $logFile = LOG_DIR . 'error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $user = $_SESSION['user_id'] ?? 'guest';
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    $line = "[$timestamp] [$ip] [user:$user] $message$contextStr" . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Error handler personalizado
 */
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    logError("PHP Error [$severity]: $message in $file:$line");
    return false;
});

set_exception_handler(function($e) {
    logError("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (APP_ENV === 'production') {
        http_response_code(500);
        echo 'Ha ocurrido un error interno. Por favor, intenta mas tarde.';
    } else {
        throw $e;
    }
});
