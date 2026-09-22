<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { setFlash('danger', 'Metodo no permitido.'); header('Location: index.php'); exit; }
if (!$id || $csrf !== csrfToken()) { setFlash('danger', 'Solicitud no valida.'); header('Location: index.php'); exit; }

$db = getDB();
$stmt = $db->prepare("SELECT agente_id FROM visitas WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$visita = $stmt->fetch();
if (!$visita) { setFlash('danger', 'Visita no encontrada.'); header('Location: index.php'); exit; }
if (!isAdmin() && intval($visita['agente_id']) !== intval(currentUserId())) { setFlash('danger', 'No tienes permisos para eliminar esta visita.'); header('Location: index.php'); exit; }
$db->prepare("DELETE FROM visitas WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'visita', $id);
setFlash('success', 'Visita eliminada.');
header('Location: index.php');
exit;
