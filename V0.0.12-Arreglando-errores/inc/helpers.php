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
