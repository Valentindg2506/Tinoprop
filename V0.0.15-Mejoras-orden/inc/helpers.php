<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_price(float $precio, string $moneda, ?string $periodo): string
{
    $formato = number_format($precio, 0, ',', '.');
    $texto = $formato . ' ' . $moneda;

    if (!empty($periodo)) {
        $texto .= '/' . $periodo;
    }

    return $texto;
}

function map_estado_clase(string $estado): string
{
    return strtolower(str_replace(' ', '_', $estado));
}

function obtener_origen_propiedad(string $operacion, string $equipo): string
{
    if ($operacion === 'alquiler') {
        return $equipo === 'comprador' ? 'alquileres-comprador' : 'alquileres-vendedor';
    }

    return $equipo === 'comprador' ? 'propiedades-comprador' : 'propiedades-vendedor';
}

function flash_set(string $key, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $mensaje = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $mensaje;
}

function validar_requerido(string $valor): bool
{
    return trim($valor) !== '';
}

function validar_email(string $valor): bool
{
    return filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
}

function validar_telefono(string $valor): bool
{
    return (bool) preg_match('/^[0-9+()\s-]{6,20}$/', $valor);
}

function validar_enum(string $valor, array $permitidos): bool
{
    return in_array($valor, $permitidos, true);
}

function preferencias_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS preferencias_usuario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            clave VARCHAR(120) NOT NULL,
            valor LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_usuario_clave (usuario_id, clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

function preferencias_usuario_get(PDO $pdo, string $clave): ?string
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('SELECT valor FROM preferencias_usuario WHERE usuario_id = :usuario_id AND clave = :clave LIMIT 1');
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
    ]);

    $valor = $stmt->fetchColumn();
    if ($valor === false) {
        return null;
    }

    return (string) $valor;
}

function preferencias_usuario_set(PDO $pdo, string $clave, string $valor): void
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO preferencias_usuario (usuario_id, clave, valor)
         VALUES (:usuario_id, :clave, :valor)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
        'valor' => $valor,
    ]);
}

function preferencias_usuario_delete(PDO $pdo, string $clave): void
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM preferencias_usuario WHERE usuario_id = :usuario_id AND clave = :clave');
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
    ]);
}
