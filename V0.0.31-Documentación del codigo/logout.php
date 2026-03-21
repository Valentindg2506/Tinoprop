<?php
/*
 * ==========================================================================
 * Archivo: logout.php — Cerrar Sesión del Usuario en TinoProp
 * ==========================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo se ejecuta cuando el usuario hace clic en "Cerrar sesión".
 * Su trabajo es eliminar completamente la sesión del usuario para que
 * nadie más pueda usar su cuenta desde ese navegador.
 *
 * ¿QUÉ ES UNA SESIÓN?
 * Cuando un usuario inicia sesión (login), PHP crea una "sesión": un
 * espacio temporal en el servidor donde se guardan datos del usuario
 * (su nombre, su ID, su inmobiliaria, etc.). También se guarda una
 * "cookie" en el navegador del usuario (un pequeño archivo) que
 * identifica esa sesión. Cada vez que el usuario navega por la app,
 * PHP lee esa cookie para saber quién es.
 *
 * ¿QUÉ HACE EL LOGOUT PASO A PASO?
 * 1. Vacía todas las variables de sesión ($_SESSION = []).
 * 2. Elimina la cookie de sesión del navegador (la marca como expirada).
 * 3. Destruye la sesión en el servidor (session_destroy()).
 * 4. Redirige al usuario a la página de login.
 *
 * SEGURIDAD:
 * - Al destruir tanto los datos del servidor como la cookie del navegador,
 *   nos aseguramos de que la sesión no pueda ser reutilizada.
 * - time() - 42000 pone la cookie en el pasado, forzando al navegador
 *   a eliminarla inmediatamente.
 */

// Cargamos el bootstrap para tener acceso a la sesión activa y funciones globales.
require_once __DIR__ . '/inc/bootstrap.php';

// ── PASO 1: Vaciar los datos de sesión ──
// $_SESSION es un array global de PHP que contiene toda la información
// del usuario logueado (nombre, email, rol, inmobiliaria, etc.).
// Al asignarlo a un array vacío [], borramos todos esos datos.
$_SESSION = [];

// ── PASO 2: Eliminar la cookie de sesión del navegador ──
// ini_get('session.use_cookies') comprueba si PHP está usando cookies
// para manejar las sesiones (que es lo normal en aplicaciones web).
if (ini_get('session.use_cookies')) {
    // Obtenemos los parámetros de la cookie de sesión actual
    // (ruta, dominio, si es segura, etc.) para poder eliminarla correctamente.
    $p = session_get_cookie_params();

    // Enviamos la misma cookie pero con fecha de expiración en el PASADO.
    // time() - 42000 = hace ~11.6 horas. Al recibir una cookie expirada,
    // el navegador la borra automáticamente de su almacenamiento.
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// ── PASO 3: Destruir la sesión en el servidor ──
// Esto elimina el archivo de sesión que PHP guardó en el servidor.
// Combinado con los pasos anteriores, la sesión queda 100% eliminada.
session_destroy();

// ── PASO 4: Redirigir al login ──
// header('Location: ...') envía una cabecera HTTP 302 que le dice al
// navegador que vaya a login.php. El usuario verá la pantalla de login.
header('Location: login.php');

// exit detiene la ejecución del script inmediatamente. Es OBLIGATORIO
// ponerlo después de un header('Location'), porque si no PHP seguiría
// ejecutando código aunque el navegador ya esté redirigiendo.
exit;
