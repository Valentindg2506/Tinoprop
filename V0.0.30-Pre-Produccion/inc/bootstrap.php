<?php
/*
 * Archivo: inc/bootstrap.php
 * Rol: inicialización común para todas las peticiones PHP.
 * Tareas: session_start, carga de DB/helpers/idioma, aplicación de idioma preferido,
 *         inicio/fin de buffer i18n y protección de rutas privadas.
 * Flujo: inicializa entorno -> aplica idioma -> protege acceso -> traduce salida final.
 */

// Producción: ocultar errores al usuario (se logean en servidor).
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Cookies de sesión seguras.
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

session_start();
// Carga dependencias base compartidas por toda petición.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/idioma.php';

// Conexión PDO disponible para módulos incluidos después de bootstrap.
$pdo = db();

// Aplica idioma preferido del usuario si existe y es válido.
$lang_preferido = preferencias_usuario_get($pdo, 'ui.idioma');
if ($lang_preferido && idioma_es_valido($lang_preferido)) {
	idioma_establecer($lang_preferido);
}

// Inicia buffer para poder traducir HTML completo al final de la respuesta.
i18n_iniciar_buffer();

// Lista de scripts públicos que no requieren sesión autenticada.
$publico = ['login.php', 'logout.php', 'cambiar-password.php', 'aceptar-terminos.php'];
$script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Guardia de autenticación: todo lo no público exige sesión activa.
if (!in_array($script_actual, $publico, true) && empty($_SESSION['usuario'])) {
	header('Location: login.php');
	exit;
}

// Forzar cambio de contraseña temporal antes de acceder a cualquier sección.
if (!in_array($script_actual, $publico, true)
    && !empty($_SESSION['usuario'])
    && !empty($_SESSION['usuario']['password_temporal'])
) {
	header('Location: cambiar-password.php');
	exit;
}

// Forzar aceptación de Términos y Condiciones vigentes antes de acceder a la app.
if (!in_array($script_actual, $publico, true)
    && !empty($_SESSION['usuario'])
    && !usuario_ha_aceptado_terminos()
) {
	header('Location: aceptar-terminos.php');
	exit;
}

// En shutdown se traduce/imprime el buffer final según idioma activo.
register_shutdown_function('i18n_finalizar_buffer');
