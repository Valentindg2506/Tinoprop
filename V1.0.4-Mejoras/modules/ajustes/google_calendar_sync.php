<?php
/**
 * Endpoint AJAX: sincroniza los eventos del CRM del usuario actual a Google Calendar.
 * POST con csrf_token.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/google_calendar_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar CSRF
$csrfToken = $_POST['csrf_token'] ?? '';
if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

$db     = getDB();
$userId = currentUserId();

$token = gcalGetToken($db, $userId);
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Google Calendar no está conectado']);
    exit;
}

$stats = gcalSyncForUser($db, $userId);

if (isset($stats['error'])) {
    echo json_encode(['success' => false, 'error' => $stats['error']]);
    exit;
}

$total = $stats['creados'] + $stats['actualizados'];
$msg   = "Sincronización completada: {$stats['creados']} eventos nuevos, {$stats['actualizados']} actualizados";
if ($stats['errores'] > 0) $msg .= " ({$stats['errores']} con error)";

registrarActividad('sincronizar', 'google_calendar', $userId, $msg);

echo json_encode(['success' => true, 'message' => $msg, 'stats' => $stats]);
