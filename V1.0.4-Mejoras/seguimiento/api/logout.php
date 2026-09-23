<?php
require_once '_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { casErr('Método no permitido', 405); exit; }

$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
    try {
        casDB()->prepare('DELETE FROM tokens WHERE token = ?')->execute([trim($m[1])]);
    } catch (Exception $e) { /* ignorar */ }
}

casOk(['message' => 'Sesión cerrada']);
