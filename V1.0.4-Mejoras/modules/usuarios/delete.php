<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Metodo no permitido.'); header('Location: index.php'); exit;
}
$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';
if (!$id || $csrf !== csrfToken() || $id === currentUserId()) {
    setFlash('danger', 'No puedes eliminar tu propia cuenta.'); header('Location: index.php'); exit;
}
$db = getDB();
$db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'usuario', $id);
setFlash('success', 'Usuario eliminado.');
header('Location: index.php'); exit;
