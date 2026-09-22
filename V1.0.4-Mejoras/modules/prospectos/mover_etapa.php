<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo no permitido.']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$etapa = trim((string)($_POST['etapa'] ?? ''));
$csrf = $_POST['csrf_token'] ?? '';

$etapasValidas = ['nuevo_lead', 'contactado', 'en_seguimiento', 'visita_programada', 'captado', 'descartado'];
if ($id <= 0 || !in_array($etapa, $etapasValidas, true) || $csrf !== csrfToken()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Solicitud no valida.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare('SELECT agente_id FROM prospectos WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$prospecto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prospecto) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Prospecto no encontrado.']);
    exit;
}

if (!isAdmin() && intval($prospecto['agente_id']) !== intval(currentUserId())) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos para mover este prospecto.']);
    exit;
}

$mapEtapa = [
    'nuevo_lead' => 'nuevo_lead',
    'contactado' => 'contactado',
    'en_seguimiento' => 'seguimiento',
    'visita_programada' => 'visita_programada',
    'captado' => 'captado',
    'descartado' => 'descartado',
];
$etapaGuardada = $mapEtapa[$etapa] ?? $etapa;

$estadoMap = [
    'nuevo_lead' => 'nuevo_lead',
    'contactado' => 'contactado',
    'en_seguimiento' => 'en_seguimiento',
    'visita_programada' => 'visita_programada',
    'captado' => 'captado',
    'descartado' => 'descartado',
];
$estadoGuardado = $estadoMap[$etapa] ?? 'contactado';

$upd = $db->prepare('UPDATE prospectos SET etapa = ?, estado = ?, updated_at = NOW() WHERE id = ?');
$upd->execute([$etapaGuardada, $estadoGuardado, $id]);

registrarActividad('actualizar', 'prospecto', $id, 'Cambio de etapa en kanban a ' . $etapa);

echo json_encode(['ok' => true]);
