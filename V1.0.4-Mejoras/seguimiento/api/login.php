<?php
require_once '_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { casErr('Método no permitido', 405); exit; }

$body     = casBody();
$usuario  = trim($body['usuario'] ?? '');
$password = $body['password'] ?? '';

if (!$usuario || !$password) { casErr('Usuario y contraseña requeridos'); exit; }

try {
    $db   = casDB();
    $stmt = $db->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $row  = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        casErr('Credenciales incorrectas', 401); exit;
    }

    // Generar token seguro
    $token  = bin2hex(random_bytes(32));
    $ttl    = (int)($_ENV['TOKEN_TTL'] ?? 2592000); // 30 días
    $expiry = date('Y-m-d H:i:s', time() + $ttl);

    $db->prepare('INSERT INTO tokens (token, usuario_id, expires_at) VALUES (?, ?, ?)')
       ->execute([$token, $row['id'], $expiry]);

    // Limpiar tokens viejos del mismo usuario
    $db->prepare('DELETE FROM tokens WHERE usuario_id = ? AND expires_at < NOW()')
       ->execute([$row['id']]);

    echo json_encode([
        'ok'         => true,
        'token'      => $token,
        'expires_at' => $expiry,
        'usuario'    => $usuario,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
