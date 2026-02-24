<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';

$pdo = db();

$lang_preferido = preferencias_usuario_get($pdo, 'ui.idioma');
if ($lang_preferido && idioma_es_valido($lang_preferido)) {
	idioma_establecer($lang_preferido);
}

i18n_iniciar_buffer();

$publico = ['login.php', 'logout.php'];
$script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (!in_array($script_actual, $publico, true) && empty($_SESSION['usuario'])) {
	header('Location: login.php');
	exit;
}

register_shutdown_function('i18n_finalizar_buffer');
