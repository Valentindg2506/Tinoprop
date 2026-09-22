<?php
/**
 * AJAX endpoint para mover un item a otra etapa
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/automatizaciones_engine.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

verifyCsrf();

$itemId = intval($_POST['item_id'] ?? 0);
$etapaId = intval($_POST['etapa_id'] ?? 0);

if (!$itemId || !$etapaId) {
    echo json_encode(['success' => false, 'error' => 'Parametros incompletos']);
    exit;
}

$db = getDB();

// Verificar que el item existe
$stmt = $db->prepare("SELECT * FROM pipeline_items WHERE id = ?");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    echo json_encode(['success' => false, 'error' => 'Item no encontrado']);
    exit;
}

if (!isAdmin()) {
    $ownerStmt = $db->prepare("SELECT created_by FROM pipelines WHERE id = ? LIMIT 1");
    $ownerStmt->execute([$item['pipeline_id']]);
    $ownerId = intval($ownerStmt->fetchColumn());
    if ($ownerId !== intval(currentUserId())) {
        echo json_encode(['success' => false, 'error' => 'Sin permisos para mover este item']);
        exit;
    }
}

// Verificar que la etapa existe y pertenece a la misma pipeline
$stmtEtapa = $db->prepare("SELECT * FROM pipeline_etapas WHERE id = ? AND pipeline_id = ?");
$stmtEtapa->execute([$etapaId, $item['pipeline_id']]);
$etapa = $stmtEtapa->fetch();

if (!$etapa) {
    echo json_encode(['success' => false, 'error' => 'Etapa no valida para esta pipeline']);
    exit;
}

// Mover el item
$stmtUpdate = $db->prepare("UPDATE pipeline_items SET etapa_id = ?, updated_at = NOW() WHERE id = ?");
$stmtUpdate->execute([$etapaId, $itemId]);

registrarActividad('mover', 'pipeline_item', $itemId, 'Movido a etapa: ' . $etapa['nombre']);

try {
    automatizacionesEjecutarTrigger('pipeline_etapa_cambiada', [
        'entidad_tipo' => 'pipeline_item',
        'entidad_id' => intval($itemId),
        'pipeline_item_id' => intval($itemId),
        'cliente_id' => intval($item['cliente_id'] ?? 0),
        'propiedad_id' => intval($item['propiedad_id'] ?? 0),
        'actor_user_id' => intval(currentUserId()),
        'owner_user_id' => intval(currentUserId()),
        'etapa_id' => intval($etapaId),
    ]);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('Error trigger pipeline_etapa_cambiada: ' . $e->getMessage());
    }
}

echo json_encode(['success' => true, 'etapa' => $etapa['nombre']]);
