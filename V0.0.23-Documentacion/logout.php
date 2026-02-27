<?php
/*
 * Archivo: logout.php
 * Rol: cerrar sesión del usuario actual.
 * Flujo: destruye la sesión y redirige al formulario de login.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Invalida la sesión completa y fuerza retorno al login.
session_destroy();
header('Location: login.php');
exit;
