<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Metodo no permitido.');
    header('Location: index.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM calendario_eventos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, currentUserId()]);
$evento = $stmt->fetch();

if (!$evento) {
    setFlash('danger', 'Evento no encontrado.');
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("DELETE FROM calendario_eventos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$id, currentUserId()]);

registrarActividad('eliminar', 'calendario_evento', $id, 'Evento eliminado: ' . $evento['titulo']);
setFlash('success', 'Evento eliminado correctamente.');
header('Location: index.php');
exit;
