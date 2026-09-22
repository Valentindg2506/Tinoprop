<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();
$codigo = trim($_GET['c'] ?? '');
if (!$codigo) { header('Location: /'); exit; }

$af = $db->prepare("SELECT * FROM afiliados WHERE codigo=? AND activo=1"); $af->execute([$codigo]); $af=$af->fetch();
if (!$af) { header('Location: /'); exit; }

// Track referral
$db->prepare("INSERT INTO afiliado_referidos (afiliado_id, ip) VALUES (?,?)")->execute([$af['id'], $_SERVER['REMOTE_ADDR']]);
$db->prepare("UPDATE afiliados SET total_referidos=total_referidos+1 WHERE id=?")->execute([$af['id']]);

// Guardar ref_code en sesión PHP (necesaria, no requiere consentimiento)
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['pending_ref_code'] = $codigo;

// Solo establecer cookie persistente si el usuario ya dio consentimiento de tracking
$consentRaw  = $_COOKIE['cookie_consent'] ?? '';
$consentData = $consentRaw ? json_decode($consentRaw, true) : null;
if (!empty($consentData['analytics'])) {
    setcookie('ref_code', $codigo, [
        'expires'  => time() + 86400 * 30,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Redirect to homepage or landing
header('Location: ' . (defined('APP_URL') ? APP_URL : '/'));
