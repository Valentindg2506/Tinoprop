<?php
/*
 * Archivo: inc/bootstrap.php
 * Rol: inicialización común para todas las peticiones PHP.
 * Tareas: session_start, carga de DB/helpers/idioma, aplicación de idioma preferido,
 *         inicio/fin de buffer i18n y protección de rutas privadas.
 * Flujo: inicializa entorno -> aplica idioma -> protege acceso -> traduce salida final.
 */
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
$publico = ['login.php', 'register.php', 'logout.php'];
$script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Guardia de autenticación: todo lo no público exige sesión activa.
if (!in_array($script_actual, $publico, true) && empty($_SESSION['usuario'])) {
	header('Location: login.php');
	exit;
}

// En shutdown se traduce/imprime el buffer final según idioma activo.
register_shutdown_function('i18n_finalizar_buffer');
