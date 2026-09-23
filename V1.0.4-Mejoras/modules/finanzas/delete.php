<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Metodo no permitido.');
    header('Location: index.php');
    exit;
}

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT agente_id FROM finanzas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$registro = $stmt->fetch();
if (!$registro) {
    setFlash('danger', 'Registro no encontrado.');
    header('Location: index.php');
    exit;
}
if (!isAdmin() && intval($registro['agente_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para eliminar este registro.');
    header('Location: index.php');
    exit;
}
$db->prepare("DELETE FROM finanzas WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'finanza', $id);
setFlash('success', 'Registro eliminado.');
header('Location: index.php');
exit;
