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

verifyCsrf();

$itemId = intval($_POST['item_id'] ?? 0);
$pipelineId = intval($_POST['pipeline_id'] ?? 0);

if (!$itemId) {
    setFlash('danger', 'Item no valido.');
    header('Location: index.php');
    exit;
}

$db = getDB();

function pipelineHasProspectoColumn(PDO $db): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM pipeline_items LIKE 'prospecto_id'");
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function pipelineEtapasHasConversionColumn(PDO $db): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM pipeline_etapas LIKE 'permitir_conversion'");
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

if (!pipelineHasProspectoColumn($db)) {
    setFlash('danger', 'La base de datos no tiene soporte para prospectos en pipeline_items.');
    header('Location: ver.php?id=' . ($pipelineId ?: ''));
    exit;
}

$hasConversionColumn = pipelineEtapasHasConversionColumn($db);

$stmt = $db->prepare("SELECT pi.*, pe.nombre AS etapa_nombre, pe.permitir_conversion, p.created_by AS pipeline_owner
    FROM pipeline_items pi
    LEFT JOIN pipeline_etapas pe ON pi.etapa_id = pe.id
    LEFT JOIN pipelines p ON pi.pipeline_id = p.id
    WHERE pi.id = ?");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('danger', 'Item no encontrado.');
    header('Location: index.php');
    exit;
}

$pipelineId = intval($item['pipeline_id']);

if (!isAdmin() && intval($item['pipeline_owner']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para convertir este item.');
    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

if (empty($item['prospecto_id'])) {
    setFlash('danger', 'Este item no tiene prospecto asociado.');
    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

if (!empty($item['cliente_id'])) {
    setFlash('danger', 'Este item ya tiene un cliente asociado.');
    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

$etapaNombre = mb_strtolower((string)($item['etapa_nombre'] ?? ''), 'UTF-8');
$etapaPermiteConversion = $hasConversionColumn ? !empty($item['permitir_conversion']) : (mb_strpos($etapaNombre, 'cerr') !== false);
if (!$etapaPermiteConversion) {
    setFlash('danger', 'Solo puedes convertir prospectos en etapas cerradas.');
    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

$stmtPros = $db->prepare("SELECT * FROM prospectos WHERE id = ?");
$stmtPros->execute([intval($item['prospecto_id'])]);
$prospecto = $stmtPros->fetch();

if (!$prospecto) {
    setFlash('danger', 'Prospecto no encontrado.');
    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

try {
    $db->beginTransaction();

    $clienteId = 0;
    $email = trim((string)($prospecto['email'] ?? ''));

    if ($email !== '') {
        $stmtCliente = $db->prepare("SELECT id FROM clientes WHERE email = ? LIMIT 1");
        $stmtCliente->execute([$email]);
        $clienteId = intval($stmtCliente->fetchColumn());
    }

    if (!$clienteId) {
        $clienteData = [
            'nombre' => $prospecto['nombre'] ?? 'Sin nombre',
            'email' => $email !== '' ? $email : null,
            'telefono' => $prospecto['telefono'] ?? null,
            'telefono2' => $prospecto['telefono2'] ?? null,
            'tipo' => 'propietario',
            'origen' => 'otro',
            'direccion' => $prospecto['direccion'] ?? null,
            'localidad' => $prospecto['localidad'] ?? null,
            'provincia' => $prospecto['provincia'] ?? null,
            'codigo_postal' => $prospecto['codigo_postal'] ?? null,
            'notas' => 'Convertido desde pipeline/prospecto ' . ($prospecto['referencia'] ?? '#'.$prospecto['id']) . '. ' . ($prospecto['notas'] ?? ''),
            'agente_id' => $prospecto['agente_id'] ?? currentUserId(),
            'activo' => 1,
        ];

        $fields = array_keys($clienteData);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        $db->prepare("INSERT INTO clientes (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($clienteData));
        $clienteId = intval($db->lastInsertId());
    }

    $db->prepare("UPDATE pipeline_items SET cliente_id = ?, prospecto_id = NULL, updated_at = NOW() WHERE id = ?")
        ->execute([$clienteId, $itemId]);

    $db->prepare("UPDATE prospectos SET etapa = 'captado', estado = 'captado' WHERE id = ?")
        ->execute([intval($prospecto['id'])]);

    registrarActividad('convertir', 'pipeline_item', $itemId, 'Prospecto #' . intval($prospecto['id']) . ' convertido a cliente #' . $clienteId);
    registrarActividad('convertir', 'prospecto', intval($prospecto['id']), 'Convertido a cliente #' . $clienteId . ' desde pipeline #' . $pipelineId);

    $db->commit();

    setFlash('success', 'Prospecto convertido y vinculado al item. <a href="' . APP_URL . '/modules/clientes/ver.php?id=' . $clienteId . '">Ver cliente</a>');
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    setFlash('danger', 'Error al convertir prospecto: ' . $e->getMessage());
}

header('Location: ver.php?id=' . $pipelineId);
exit;
