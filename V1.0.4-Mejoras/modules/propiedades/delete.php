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

$stmt = $db->prepare("SELECT agente_id FROM propiedades WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$propiedad = $stmt->fetch();

if (!$propiedad) {
    setFlash('danger', 'Propiedad no encontrada.');
    header('Location: index.php');
    exit;
}

if (!isAdmin() && intval($propiedad['agente_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para eliminar esta propiedad.');
    header('Location: index.php');
    exit;
}

// Eliminar fotos del disco
$fotos = $db->prepare("SELECT archivo FROM propiedad_fotos WHERE propiedad_id = ?");
$fotos->execute([$id]);
foreach ($fotos->fetchAll() as $foto) {
    deleteUpload($foto['archivo']);
}

$db->prepare("DELETE FROM propiedades WHERE id = ?")->execute([$id]);
registrarActividad('eliminar', 'propiedad', $id);
setFlash('success', 'Propiedad eliminada correctamente.');
header('Location: index.php');
exit;
