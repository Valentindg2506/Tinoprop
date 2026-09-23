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

// Verificar que la automatizacion existe
$stmt = $db->prepare("SELECT nombre FROM automatizaciones WHERE id = ?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    setFlash('danger', 'Automatizacion no encontrada.');
    header('Location: index.php');
    exit;
}

if (!isAdmin()) {
    $ownerStmt = $db->prepare("SELECT created_by FROM automatizaciones WHERE id = ? LIMIT 1");
    $ownerStmt->execute([$id]);
    $ownerId = intval($ownerStmt->fetchColumn());
    if ($ownerId !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para eliminar esta automatizacion.');
        header('Location: index.php');
        exit;
    }
}

// Eliminar automatizacion (acciones y log se eliminan por CASCADE)
$db->prepare("DELETE FROM automatizaciones WHERE id = ?")->execute([$id]);

registrarActividad('eliminar', 'automatizacion', $id, 'Automatizacion: ' . $auto['nombre']);
setFlash('success', 'Automatizacion "' . $auto['nombre'] . '" eliminada correctamente.');
header('Location: index.php');
exit;
