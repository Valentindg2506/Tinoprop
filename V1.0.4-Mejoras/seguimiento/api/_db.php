<?php
// Carga el .env desde la raíz del proyecto
function _casLoadEnv(): void {
    $path = __DIR__ . '/../.env';
    if (!file_exists($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim(trim($v), '"\'');
        if (!array_key_exists($k, $_ENV)) { $_ENV[$k] = $v; putenv("$k=$v"); }
    }
}
_casLoadEnv();

function casDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $host    = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname  = $_ENV['DB_NAME'] ?? '';
    $user    = $_ENV['DB_USER'] ?? '';
    $pass    = $_ENV['DB_PASS'] ?? '';
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

function casAuth(): int {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    // También acepta token en query string como fallback (útil para DELETE con algunos hosts)
    if (!$auth && isset($_GET['token'])) $auth = 'Bearer ' . $_GET['token'];
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        casErr('No autorizado', 401); exit;
    }
    $token = trim($m[1]);
    $stmt = casDB()->prepare('SELECT usuario_id FROM tokens WHERE token=? AND expires_at>NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) { casErr('Token inválido o expirado', 401); exit; }
    return (int)$row['usuario_id'];
}

function casOk(mixed $data = null): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
}

function casErr(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}

function casBody(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// Cabeceras comunes
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
