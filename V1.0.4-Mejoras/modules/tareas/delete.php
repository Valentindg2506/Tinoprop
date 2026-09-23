<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { setFlash('danger', 'Metodo no permitido.'); header('Location: index.php'); exit; }
$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';
if (!$id || $csrf !== csrfToken()) { setFlash('danger', 'Solicitud no valida.'); header('Location: index.php'); exit; }
$db = getDB();
$stmt = $db->prepare("SELECT asignado_a, creado_por FROM tareas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$tarea = $stmt->fetch();
if (!$tarea) { setFlash('danger', 'Tarea no encontrada.'); header('Location: index.php'); exit; }
if (!isAdmin() && intval($tarea['asignado_a']) !== intval(currentUserId()) && intval($tarea['creado_por']) !== intval(currentUserId())) {
	setFlash('danger', 'No tienes permisos para eliminar esta tarea.'); header('Location: index.php'); exit;
}
$db->prepare("DELETE FROM tareas WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'tarea', $id);
setFlash('success', 'Tarea eliminada.');
header('Location: index.php'); exit;
