<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$publico = ['login.php', 'logout.php'];
$script_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');

if (!in_array($script_actual, $publico, true) && empty($_SESSION['usuario'])) {
	header('Location: login.php');
	exit;
}
