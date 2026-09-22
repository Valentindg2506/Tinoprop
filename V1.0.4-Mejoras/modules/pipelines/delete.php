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

// Verificar que la pipeline existe
$stmt = $db->prepare("SELECT nombre FROM pipelines WHERE id = ?");
$stmt->execute([$id]);
$pipeline = $stmt->fetch();

if (!$pipeline) {
    setFlash('danger', 'Pipeline no encontrada.');
    header('Location: index.php');
    exit;
}

if (!isAdmin()) {
    $ownerStmt = $db->prepare("SELECT created_by FROM pipelines WHERE id = ? LIMIT 1");
    $ownerStmt->execute([$id]);
    $ownerId = intval($ownerStmt->fetchColumn());
    if ($ownerId !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para eliminar esta pipeline.');
        header('Location: index.php');
        exit;
    }
}

// Eliminar pipeline (items y etapas se eliminan por CASCADE)
$db->prepare("DELETE FROM pipelines WHERE id = ?")->execute([$id]);

registrarActividad('eliminar', 'pipeline', $id, 'Pipeline: ' . $pipeline['nombre']);
setFlash('success', 'Pipeline "' . $pipeline['nombre'] . '" eliminada correctamente.');
header('Location: index.php');
exit;
