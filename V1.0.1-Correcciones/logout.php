<?php
/*
 * Archivo: logout.php
 * Rol: cerrar sesión del usuario actual.
 * Flujo: destruye la sesión y redirige al formulario de login.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Invalida la sesión completa y fuerza retorno al login.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
