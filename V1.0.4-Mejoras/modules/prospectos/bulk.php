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
    setFlash('danger', 'No se seleccionaron prospectos.');
    header('Location: index.php');
    exit;
}

if (!isAdmin()) {
    $filterPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $ownedStmt = $db->prepare("SELECT id FROM prospectos WHERE agente_id = ? AND id IN ($filterPlaceholders)");
    $ownedStmt->execute(array_merge([currentUserId()], $ids));
    $ids = array_map('intval', $ownedStmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($ids)) {
        setFlash('danger', 'No tienes permisos para operar sobre los prospectos seleccionados.');
        header('Location: index.php');
        exit;
    }
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

switch ($accion) {
    case 'eliminar':
        $stmt = $db->prepare("DELETE FROM prospectos WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        registrarActividad('eliminar', 'prospecto', 0, 'Eliminacion masiva: ' . count($ids) . ' prospectos');
        setFlash('success', count($ids) . ' prospectos eliminados.');
        break;

    case 'toggle_activo':
        $activo = intval(post('activo'));
        $stmt = $db->prepare("UPDATE prospectos SET activo = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$activo], $ids));
        $label = $activo ? 'activados' : 'desactivados';
        registrarActividad('actualizar', 'prospecto', 0, 'Cambio masivo estado: ' . count($ids) . ' prospectos ' . $label);
        setFlash('success', count($ids) . ' prospectos ' . $label . '.');
        break;

    case 'cambiar_etapa':
        $etapa = post('etapa');
        $etapasValidas = ['nuevo_lead','contactado','en_seguimiento','visita_programada','captado','descartado'];
        if (in_array($etapa, $etapasValidas)) {
            $stmt = $db->prepare("UPDATE prospectos SET etapa = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$etapa], $ids));
            registrarActividad('actualizar', 'prospecto', 0, 'Cambio masivo etapa a "' . $etapa . '": ' . count($ids) . ' prospectos');
            setFlash('success', count($ids) . ' prospectos actualizados a etapa "' . ucfirst(str_replace('_', ' ', $etapa)) . '".');
        } else {
            setFlash('danger', 'Etapa no válida.');
        }
        break;

    default:
        setFlash('danger', 'Accion no reconocida.');
        break;
}

header('Location: index.php');
exit;
