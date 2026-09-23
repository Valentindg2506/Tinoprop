<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
$db = getDB();

$codigo = trim($_GET['c'] ?? '');
if (!$codigo) { header('Location: ' . APP_URL); exit; }

$stmt = $db->prepare("SELECT * FROM trigger_links WHERE codigo = ? AND activo = 1");
$stmt->execute([$codigo]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link) { header('Location: ' . APP_URL); exit; }

// Log click
$db->prepare("INSERT INTO trigger_clicks (link_id, ip, user_agent, referer) VALUES (?, ?, ?, ?)")
    ->execute([$link['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $_SERVER['HTTP_REFERER'] ?? '']);
$db->prepare("UPDATE trigger_links SET total_clicks = total_clicks + 1 WHERE id = ?")->execute([$link['id']]);

// Execute action
if ($link['accion_tipo'] === 'notificacion' && $link['usuario_id']) {
    $msg = $link['accion_valor'] ?: 'Clic en: ' . $link['nombre'];
    $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, enlace) VALUES (?, ?, ?)")
        ->execute([$link['usuario_id'], $msg, 'modules/marketing/trigger_links.php']);
}

header('Location: ' . safeRedirectTarget($link['url_destino'], APP_URL));
exit;
