<?php
/**
 * Migración: Añade columna prioridad a la tabla prospectos
 * Valores: alta, media, baja (default media)
 *
 * Ejecutar UNA SOLA VEZ desde el servidor de producción (admin login requerido).
 * Borrar este archivo después de ejecutarlo.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo 'Acceso denegado. Debes ser administrador.';
    exit;
}

$db = getDB();

$queries = [
    "ALTER TABLE prospectos ADD COLUMN IF NOT EXISTS prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media' AFTER temperatura",
    "ALTER TABLE prospectos ADD INDEX IF NOT EXISTS idx_prioridad (prioridad)",
];

$results = [];
foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $results[] = ['ok', substr(trim($sql), 0, 100)];
    } catch (PDOException $e) {
        $results[] = ['error', $e->getMessage()];
    }
}
?><!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Migración: Prioridad Prospectos</title>
<style>body{font-family:monospace;padding:30px;max-width:700px;margin:0 auto}.ok{color:#10b981}.error{color:#ef4444}</style>
</head><body>
<h2>Migración: Prioridad en Prospectos</h2>
<?php foreach ($results as [$t, $msg]): ?>
<p class="<?= $t ?>"><strong>[<?= strtoupper($t) ?>]</strong> <?= htmlspecialchars($msg) ?></p>
<?php endforeach; ?>
<hr>
<p><strong>✓ Listo. Puedes borrar este archivo del servidor.</strong></p>
<p>La columna <code>prioridad</code> (baja/media/alta) ha sido añadida a prospectos con valor por defecto <code>media</code>.</p>
</body></html>
