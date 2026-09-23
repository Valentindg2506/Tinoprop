<?php
/**
 * Desconecta Google Calendar del usuario actual.
 * Revoca el token en Google y elimina el registro de la BD.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/google_calendar_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verifyCsrf();

$db    = getDB();
$token = gcalGetToken($db, currentUserId());

if ($token) {
    gcalRevokeToken($token['access_token']);
    if (!empty($token['refresh_token'])) {
        gcalRevokeToken($token['refresh_token']);
    }
    gcalDeleteToken($db, currentUserId());
    setFlash('success', 'Google Calendar desconectado correctamente. Se han eliminado todos los mapeos de eventos.');
} else {
    setFlash('warning', 'No había ninguna cuenta de Google conectada.');
}

header('Location: index.php');
exit;
