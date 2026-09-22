<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verifyCsrf();

$db = getDB();
$accion = post('accion');
$idsRaw = post('ids');
$ids = array_filter(array_map('intval', explode(',', $idsRaw)));

if (empty($ids)) {
    setFlash('danger', 'No se seleccionaron clientes.');
    header('Location: index.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

if (!isAdmin()) {
    $stmtPermitidos = $db->prepare("SELECT id FROM clientes WHERE agente_id = ? AND id IN ($placeholders)");
    $stmtPermitidos->execute(array_merge([currentUserId()], $ids));
    $ids = array_map('intval', $stmtPermitidos->fetchAll(PDO::FETCH_COLUMN));
    if (empty($ids)) {
        setFlash('danger', 'No tienes permisos sobre los clientes seleccionados.');
        header('Location: index.php');
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
}

switch ($accion) {
    case 'eliminar':
        $stmt = $db->prepare("DELETE FROM clientes WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        registrarActividad('eliminar', 'cliente', 0, 'Eliminacion masiva: ' . count($ids) . ' clientes');
        setFlash('success', count($ids) . ' clientes eliminados.');
        break;

    case 'toggle_activo':
        $activo = intval(post('activo'));
        $stmt = $db->prepare("UPDATE clientes SET activo = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$activo], $ids));
        $label = $activo ? 'activados' : 'desactivados';
        registrarActividad('actualizar', 'cliente', 0, 'Cambio masivo estado: ' . count($ids) . ' clientes ' . $label);
        setFlash('success', count($ids) . ' clientes ' . $label . '.');
        break;

    case 'asignar_tag':
        $tagId = intval(post('tag_id'));
        if ($tagId > 0) {
            $stmt = $db->prepare("INSERT IGNORE INTO cliente_tags (cliente_id, tag_id) VALUES (?, ?)");
            foreach ($ids as $clienteId) {
                $stmt->execute([$clienteId, $tagId]);
            }
            $tagNombre = $db->prepare("SELECT nombre FROM tags WHERE id = ?");
            $tagNombre->execute([$tagId]);
            $nombre = $tagNombre->fetchColumn();
            registrarActividad('agregar_tag', 'cliente', 0, 'Tag masivo "' . $nombre . '" a ' . count($ids) . ' clientes');
            setFlash('success', 'Tag asignado a ' . count($ids) . ' clientes.');
        }
        break;

    default:
        setFlash('danger', 'Accion no reconocida.');
        break;
}

header('Location: index.php');
exit;
