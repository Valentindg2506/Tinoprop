<?php
/**
 * Migración: Google Calendar Integration
 * Crea las tablas google_calendar_tokens y google_calendar_event_map.
 *
 * Ejecutar UNA SOLA VEZ desde el servidor de producción (admin login requerido).
 * Borrar este archivo después de ejecutarlo.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo 'Acceso denegado. Debes ser administrador.';
    exit;
}

$db = getDB();

$queries = [
    "CREATE TABLE IF NOT EXISTS google_calendar_tokens (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id        INT NOT NULL,
        access_token      TEXT NOT NULL,
        refresh_token     TEXT DEFAULT NULL,
        expires_at        INT NOT NULL DEFAULT 0,
        google_email      VARCHAR(255) DEFAULT NULL,
        google_calendar_id VARCHAR(255) DEFAULT 'primary',
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_usuario (usuario_id),
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS google_calendar_event_map (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id        INT NOT NULL,
        entidad_tipo      ENUM('tarea','visita','prospecto','calendario') NOT NULL,
        entidad_id        INT NOT NULL,
        google_event_id   VARCHAR(255) NOT NULL,
        google_calendar_id VARCHAR(255) DEFAULT 'primary',
        created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_entidad (usuario_id, entidad_tipo, entidad_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_google_event (google_event_id(191))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

$results = [];
foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $results[] = ['ok', substr(trim($sql), 0, 80)];
    } catch (PDOException $e) {
        $results[] = ['error', $e->getMessage()];
    }
}
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Migración Google Calendar</title>
<style>body{font-family:monospace;padding:30px;max-width:700px;margin:0 auto}.ok{color:#10b981}.error{color:#ef4444}</style>
</head><body>
<h2>Migración: Google Calendar Integration</h2>
<?php foreach ($results as [$t, $msg]): ?>
<p class="<?= $t ?>"><strong>[<?= strtoupper($t) ?>]</strong> <?= htmlspecialchars($msg) ?></p>
<?php endforeach; ?>
<hr>
<p><strong>✓ Listo. Recuerda borrar este archivo del servidor.</strong></p>
<p>Próximo paso: añade <code>GOOGLE_CLIENT_ID</code> y <code>GOOGLE_CLIENT_SECRET</code> al archivo <code>.env</code>.</p>
</body></html>
