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
$stmt = $db->prepare("SELECT agente_id FROM prospectos WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$prospecto = $stmt->fetch();
if (!$prospecto) {
    setFlash('danger', 'Prospecto no encontrado.');
    header('Location: index.php');
    exit;
}
if (!isAdmin() && intval($prospecto['agente_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para eliminar este prospecto.');
    header('Location: index.php');
    exit;
}
$db->prepare("DELETE FROM prospectos WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'prospecto', $id);
setFlash('success', 'Prospecto eliminado correctamente.');
header('Location: index.php');
exit;
