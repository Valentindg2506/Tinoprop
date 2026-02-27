<?php
/*
 * Archivo: register.php
 * Rol: procesador de registro para el formulario embebido en login.php.
 * Flujo: valida datos -> guarda errores en sesión si existen -> redirige al panel registro.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Si ya hay sesión, evita registrar desde una sesión abierta.
if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$errores = [];
$datos = [
    'nombre' => trim($_POST['nombre'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
];

$password = $_POST['password'] ?? '';
$password_repetir = $_POST['password_repetir'] ?? '';

if (!validar_requerido($datos['nombre'])) {
    $errores['nombre'] = 'El nombre es obligatorio.';
}

if (!validar_email($datos['email'])) {
    $errores['email'] = 'El correo no tiene un formato válido.';
}

if (!validar_password_segura($password)) {
    $errores['password'] = 'La contraseña debe tener 8-16 carácteres, 1 Mayúscula y 1 Símbolo';
}

if ($password !== $password_repetir) {
    $errores['password_repetir'] = 'La confirmación de contraseña no coincide.';
}

if (!empty($errores)) {
    $_SESSION['errores_registro'] = $errores;
    $_SESSION['datos_registro'] = $datos;
    header('Location: login.php');
    exit;
}

 $pdo = db();

// Evita cuentas duplicadas por email.
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $datos['email']]);

if ($stmt->fetch()) {
    $_SESSION['errores_registro'] = ['email' => 'Ese email ya está registrado.'];
    $_SESSION['datos_registro'] = $datos;
    header('Location: login.php');
    exit;
}

// Inserta usuario con hash seguro para mantener compatibilidad con login actual.
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (:nombre, :email, :password_hash, :rol)'
);
$stmt->execute([
    'nombre' => $datos['nombre'],
    'email' => $datos['email'],
    'password_hash' => $hash,
    'rol' => 'admin',
]);

$usuario_id = (int) $pdo->lastInsertId();
$_SESSION['usuario'] = [
    'id' => $usuario_id,
    'nombre' => $datos['nombre'],
    'email' => $datos['email'],
    'rol' => 'admin',
];

header('Location: index.php');
exit;
