<?php
/**
 * Registro de consentimiento de cookies (consent log RGPD).
 * POST: guarda la decisión del usuario en la tabla cookie_consents.
 */
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('{}');
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['necessary'])) {
    http_response_code(400);
    exit('{}');
}

$decision = json_encode([
    'necessary' => true,
    'analytics' => (bool)($data['analytics'] ?? false),
    'timestamp' => $data['timestamp'] ?? date('c'),
    'version'   => $data['version'] ?? '1.0',
]);

try {
    $db = getDB();

    // Crear tabla si no existe (auto-migración)
    $db->exec("CREATE TABLE IF NOT EXISTS cookie_consents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        user_agent VARCHAR(500),
        decision JSON NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip (ip),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->prepare("INSERT INTO cookie_consents (ip, user_agent, decision) VALUES (?, ?, ?)")
       ->execute([
           $_SERVER['REMOTE_ADDR'] ?? '',
           substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
           $decision,
       ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    // No bloquear la UX si falla el log
    http_response_code(200);
    echo json_encode(['ok' => false]);
}
