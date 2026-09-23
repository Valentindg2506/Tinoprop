<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'matches' => []]);
    exit;
}

$campo = $_GET['campo'] ?? '';
$valor = trim($_GET['valor'] ?? '');
$excludeId = intval($_GET['exclude_id'] ?? 0);
$excludeTipo = $_GET['exclude_tipo'] ?? ''; // 'prospecto' o 'cliente'

if (!in_array($campo, ['telefono', 'telefono2', 'email']) || $valor === '') {
    echo json_encode(['success' => true, 'matches' => []]);
    exit;
}

$db = getDB();
$matches = [];

// Check prospectos
try {
    $col = ($campo === 'email') ? 'email' : $campo;
    $excludeClause = ($excludeTipo === 'prospecto' && $excludeId > 0) ? ' AND id != ?' : '';
    $params = [$valor];
    if ($excludeClause) $params[] = $excludeId;

    // telefono can match telefono OR telefono2
    if ($campo === 'telefono' || $campo === 'telefono2') {
        $whereExclude = ($excludeTipo === 'prospecto' && $excludeId > 0) ? ' AND id != ?' : '';
        $sql = "SELECT id, nombre, referencia, 'prospecto' as tipo FROM prospectos
                WHERE (telefono = ? OR telefono2 = ?) $whereExclude LIMIT 5";
        $pms = [$valor, $valor];
        if ($whereExclude) $pms[] = $excludeId;
    } else {
        $sql = "SELECT id, nombre, referencia, 'prospecto' as tipo FROM prospectos
                WHERE email = ? $excludeClause LIMIT 5";
        $pms = $params;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($pms);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $matches[] = [
            'tipo' => 'Prospecto',
            'nombre' => $r['nombre'],
            'referencia' => $r['referencia'] ?? '',
            'url' => '../modules/prospectos/ver.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

// Check clientes
try {
    $excludeClause = ($excludeTipo === 'cliente' && $excludeId > 0) ? ' AND id != ?' : '';

    if ($campo === 'telefono' || $campo === 'telefono2') {
        $whereExclude = ($excludeTipo === 'cliente' && $excludeId > 0) ? ' AND id != ?' : '';
        $sql = "SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre, 'cliente' as tipo FROM clientes
                WHERE (telefono = ? OR telefono2 = ?) $whereExclude LIMIT 5";
        $pms = [$valor, $valor];
        if ($whereExclude) $pms[] = $excludeId;
    } else {
        $sql = "SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre, 'cliente' as tipo FROM clientes
                WHERE email = ? $excludeClause LIMIT 5";
        $pms = [$valor];
        if ($excludeClause) $pms[] = $excludeId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($pms);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $matches[] = [
            'tipo' => 'Cliente',
            'nombre' => trim($r['nombre']),
            'referencia' => '',
            'url' => '../modules/clientes/ver.php?id=' . $r['id'],
        ];
    }
} catch (Exception $e) {}

echo json_encode(['success' => true, 'matches' => $matches]);
