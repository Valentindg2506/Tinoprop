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
$propId = intval($_POST['prop'] ?? 0);
$csrf = $_POST['csrf'] ?? $_POST['csrf_token'] ?? '';

if (!$id || $csrf !== csrfToken()) {
    setFlash('danger', 'Solicitud no valida.');
    header('Location: form.php?id=' . $propId);
    exit;
}

$db = getDB();
$foto = $db->prepare("SELECT pf.archivo, pf.propiedad_id, p.agente_id FROM propiedad_fotos pf JOIN propiedades p ON p.id = pf.propiedad_id WHERE pf.id = ? LIMIT 1");
$foto->execute([$id]);
$foto = $foto->fetch();

if ($foto) {
    if (!isAdmin() && intval($foto['agente_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para eliminar esta foto.');
        header('Location: form.php?id=' . intval($foto['propiedad_id']));
        exit;
    }

    $propId = intval($foto['propiedad_id']);
    deleteUpload($foto['archivo']);
    $db->prepare("DELETE FROM propiedad_fotos WHERE id = ?")->execute([$id]);
}

setFlash('success', 'Foto eliminada.');
header('Location: form.php?id=' . $propId);
exit;
