<?php
/**
 * Sistema de Autenticacion con proteccion contra fuerza bruta
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configurar cookies seguras antes de iniciar sesion
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', 1); // Previene session fixation con IDs externos
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Verificar si la IP esta bloqueada por intentos fallidos
 */
function isLoginBlocked() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'login_attempts_' . md5($ip);

    if (!isset($_SESSION[$key])) return false;

    $data = $_SESSION[$key];
    // Limpiar si ya paso el tiempo de bloqueo
    if (time() - $data['last_attempt'] > LOGIN_LOCKOUT_TIME) {
        unset($_SESSION[$key]);
        return false;
    }

    return $data['count'] >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Registrar intento fallido de login
 */
function registerFailedLogin() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'login_attempts_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'last_attempt' => time()];
    }

    $_SESSION[$key]['count']++;
    $_SESSION[$key]['last_attempt'] = time();

    logError('Failed login attempt', ['ip' => $ip, 'attempts' => $_SESSION[$key]['count']]);
}

/**
 * Limpiar intentos fallidos tras login exitoso
 */
function clearFailedLogins() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'login_attempts_' . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Obtener tiempo restante de bloqueo en segundos
 */
function getLoginLockoutRemaining() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'login_attempts_' . md5($ip);

    if (!isset($_SESSION[$key])) return 0;

    $elapsed = time() - $_SESSION[$key]['last_attempt'];
    $remaining = LOGIN_LOCKOUT_TIME - $elapsed;
    return max(0, $remaining);
}

function login($email, $password) {
    // Verificar bloqueo por fuerza bruta
    if (isLoginBlocked()) {
        return ['error' => 'blocked', 'remaining' => getLoginLockoutRemaining()];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerar ID de sesion para prevenir session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'] . ' ' . $user['apellidos'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_rol'] = $user['rol'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['login_time'] = time();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Limpiar intentos fallidos
        clearFailedLogins();

        // Actualizar ultimo acceso
        $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        registrarActividad('login', 'usuario', $user['id'], 'Inicio de sesion');
        return ['success' => true];
    }

    // Registrar intento fallido
    registerFailedLogin();
    return ['error' => 'invalid'];
}

function logout() {
    if (isLoggedIn()) {
        registrarActividad('logout', 'usuario', $_SESSION['user_id'], 'Cierre de sesion');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    // Verificar expiracion de sesion
    if (time() - $_SESSION['login_time'] >= SESSION_LIFETIME) {
        return false;
    }
    // Verificar que la IP no cambio (proteccion contra session hijacking)
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        logError('Session IP mismatch detected', [
            'session_ip' => $_SESSION['user_ip'],
            'current_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        return false;
    }
    return true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        if (isset($_SESSION['user_id'])) {
            // Sesion expirada
            session_destroy();
        }
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    // Aplicar permisos por modulo para roles personalizados.
    enforceModuleAccessForRequest();
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_rol'] !== 'admin') {
        setFlash('danger', 'No tienes permisos para acceder a esta seccion.');
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentUserName() {
    return $_SESSION['user_nombre'] ?? '';
}

function currentUserRole() {
    return $_SESSION['user_rol'] ?? '';
}

/**
 * Catalogo de modulos configurables para roles personalizados.
 */
function customRoleModuleCatalog() {
    return [
        'propiedades' => 'Propiedades',
        'clientes' => 'Clientes',
        'prospectos' => 'Prospectos',
        'visitas' => 'Visitas',
        'tareas' => 'Tareas',
        'documentos' => 'Documentos',
        'finanzas' => 'Finanzas',
        'portales' => 'Portales',
        'informes' => 'Informes',
        'pipelines' => 'Pipelines',
        'calendario' => 'Calendario',
        'pagos' => 'Facturacion',
        'presupuestos' => 'Presupuestos',
        'contratos' => 'Contratos',
        'marketing' => 'Marketing',
        'formularios' => 'Formularios',
        'encuestas' => 'Encuestas',
        'funnels' => 'Funnels',
        'landing' => 'Landing Pages',
        'campanas' => 'Campanas Drip',
        'ab-testing' => 'A/B Testing',
        'ads' => 'Ads Report',
        'social' => 'Redes Sociales',
        'email' => 'Email',
        'secuencia_captacion' => 'Secuencia Captación',
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'blog' => 'Blog',
        'cursos' => 'Cursos',
        'medios' => 'Medios',
        'automatizaciones' => 'Automatizaciones',
        'ia' => 'IA Asistente',
        'afiliados' => 'Afiliados',
        'ajustes' => 'Ajustes',
    ];
}

function getCurrentUserCustomRoleId() {
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }

    $loaded = true;
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT role_id FROM usuario_roles WHERE user_id = ? LIMIT 1");
        $stmt->execute([intval(currentUserId())]);
        $roleId = $stmt->fetchColumn();
        $cached = $roleId ? intval($roleId) : null;
    } catch (Exception $e) {
        $cached = null;
    }

    return $cached;
}

function getAllowedModulesForCurrentUser() {
    static $cached = null;
    static $loaded = false;

    if ($loaded) {
        return $cached;
    }

    $loaded = true;
    $cached = null;

    if (!isLoggedIn() || isAdmin()) {
        return null;
    }

    $roleId = getCurrentUserCustomRoleId();
    if (!$roleId) {
        // Sin rol personalizado: no limitar (comportamiento actual de agente).
        return null;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT modulo FROM role_modulos WHERE role_id = ? AND permitido = 1");
        $stmt->execute([$roleId]);
        $mods = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $allowed = [];
        foreach ($mods as $m) {
            $allowed[(string)$m] = true;
        }
        $cached = $allowed;
    } catch (Exception $e) {
        $cached = null;
    }

    return $cached;
}

function canAccessModule($moduleKey) {
    $moduleKey = trim((string)$moduleKey);
    if ($moduleKey === '') {
        return true;
    }

    if (isAdmin()) {
        return true;
    }

    $allowed = getAllowedModulesForCurrentUser();
    if ($allowed === null) {
        // Sin restricciones para agentes sin rol personalizado.
        return true;
    }

    return !empty($allowed[$moduleKey]);
}

function enforceModuleAccessForRequest() {
    if (!isLoggedIn() || isAdmin()) {
        return;
    }

    $path = $_SERVER['PHP_SELF'] ?? '';
    if (!preg_match('#/modules/([^/]+)/#', $path, $m)) {
        return;
    }

    $moduleKey = trim($m[1] ?? '');
    if ($moduleKey === '' || $moduleKey === 'usuarios') {
        return;
    }

    if (!canAccessModule($moduleKey)) {
        if (function_exists('setFlash')) {
            setFlash('danger', 'Tu rol no tiene acceso a este modulo.');
        }
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function isAdmin() {
    return ($_SESSION['user_rol'] ?? '') === 'admin';
}

/**
 * Validar fortaleza de contraseña
 */
function validatePassword($password) {
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Debe contener al menos una letra mayuscula.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Debe contener al menos una letra minuscula.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Debe contener al menos un numero.';
    }
    return $errors;
}

function registrarActividad($accion, $entidad, $entidad_id = null, $detalles = null) {
    if (!isset($_SESSION['user_id'])) return;
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO actividad_log (usuario_id, accion, entidad, entidad_id, detalles, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $accion,
            $entidad,
            $entidad_id,
            $detalles,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        logError('Activity log error: ' . $e->getMessage());
    }
}
