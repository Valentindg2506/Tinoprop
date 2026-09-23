<?php
/**
 * Callback OAuth de Google Calendar.
 * Intercambia el código de autorización por tokens y los guarda en la BD.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/google_calendar_helper.php';

requireLogin();

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    setFlash('danger', 'Google rechazó la autorización: ' . htmlspecialchars($error));
    header('Location: index.php');
    exit;
}

if (!$code) {
    setFlash('danger', 'No se recibió código de autorización de Google.');
    header('Location: index.php');
    exit;
}

if (!gcalVerifyState($state)) {
    setFlash('danger', 'Error de seguridad en la conexión con Google. Inténtalo de nuevo.');
    header('Location: index.php');
    exit;
}

$tokenData = gcalExchangeCode($code);
if (!$tokenData) {
    setFlash('danger', 'No se pudo obtener el token de Google. Inténtalo de nuevo en unos minutos.');
    header('Location: index.php');
    exit;
}

$db          = getDB();
$googleEmail = gcalGetUserEmail($tokenData['access_token']);
gcalSaveToken($db, currentUserId(), $tokenData, $googleEmail);

$msg = 'Google Calendar conectado correctamente';
if ($googleEmail) $msg .= " ({$googleEmail})";
$msg .= '. Ya puedes sincronizar tus eventos.';
setFlash('success', $msg);

header('Location: index.php');
exit;
