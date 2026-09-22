<?php
/**
 * Inicia el flujo OAuth con Google Calendar.
 * Redirige al usuario a la pantalla de consentimiento de Google.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/google_calendar_helper.php';

requireLogin();

if (!gcalIsConfigured()) {
    setFlash('danger', 'Google Calendar no está configurado. El administrador debe añadir GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET al archivo .env.');
    header('Location: index.php');
    exit;
}

header('Location: ' . gcalGetAuthUrl());
exit;
