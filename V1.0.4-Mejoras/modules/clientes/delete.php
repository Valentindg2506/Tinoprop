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
$csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT agente_id FROM clientes WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cliente = $stmt->fetch();
if (!$cliente) {
    setFlash('danger', 'Cliente no encontrado.');
    header('Location: index.php');
    exit;
}
if (!isAdmin() && intval($cliente['agente_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para eliminar este cliente.');
    header('Location: index.php');
    exit;
}

$db->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'cliente', $id);
setFlash('success', 'Cliente eliminado correctamente.');
header('Location: index.php');
exit;
