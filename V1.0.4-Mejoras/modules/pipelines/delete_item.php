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
$pipelineId = intval($_POST['pipeline_id'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: index.php');
    exit;
}

$db = getDB();

// Verificar que el item existe
$stmt = $db->prepare("SELECT titulo, pipeline_id FROM pipeline_items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('danger', 'Item no encontrado.');
    header('Location: index.php');
    exit;
}

$pipelineId = $item['pipeline_id'];

if (!isAdmin()) {
    $ownerStmt = $db->prepare("SELECT created_by FROM pipelines WHERE id = ? LIMIT 1");
    $ownerStmt->execute([$pipelineId]);
    $ownerId = intval($ownerStmt->fetchColumn());
    if ($ownerId !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para eliminar este item.');
        header('Location: index.php');
        exit;
    }
}

$db->prepare("DELETE FROM pipeline_items WHERE id = ?")->execute([$id]);

registrarActividad('eliminar', 'pipeline_item', $id, 'Item: ' . $item['titulo']);
setFlash('success', 'Item eliminado correctamente.');
header('Location: ver.php?id=' . $pipelineId);
exit;
