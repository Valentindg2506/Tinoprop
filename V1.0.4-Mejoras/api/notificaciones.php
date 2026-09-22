<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if ($csrf !== csrfToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF invalido']);
    exit;
}

$accion = trim((string)($_POST['accion'] ?? ''));
$userId = intval(currentUserId());
$db = getDB();

try {
    if ($accion === 'mark_one') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID invalido']);
            exit;
        }

        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($accion === 'mark_all') {
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Accion no valida']);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('Notificaciones API error: ' . $e->getMessage(), ['accion' => $accion, 'user_id' => $userId]);
    } else {
        error_log($e->getMessage());
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
