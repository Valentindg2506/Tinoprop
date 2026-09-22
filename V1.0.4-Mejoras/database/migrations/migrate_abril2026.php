<?php
/**
 * Migración Abril 2026
 * - Añade columna proxima_accion a prospectos
 * - Crea tabla historial_propiedad_prospecto
 *
 * Ejecutar UNA SOLA VEZ desde el servidor de producción.
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
    "ALTER TABLE prospectos ADD COLUMN IF NOT EXISTS proxima_accion TEXT DEFAULT NULL AFTER notas",
    "CREATE TABLE IF NOT EXISTS historial_propiedad_prospecto (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prospecto_id INT NOT NULL,
        usuario_id INT DEFAULT NULL,
        tipo ENUM('subida_precio','bajada_precio','modificacion','publicacion','retirada','otro') NOT NULL DEFAULT 'otro',
        descripcion TEXT DEFAULT NULL,
        precio_anterior DECIMAL(12,2) DEFAULT NULL,
        precio_nuevo DECIMAL(12,2) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_prospecto (prospecto_id),
        INDEX idx_created (created_at)
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
<html lang="es"><head><meta charset="UTF-8"><title>Migración Abril 2026</title>
<style>body{font-family:monospace;padding:30px;max-width:700px;margin:0 auto}.ok{color:#10b981}.error{color:#ef4444}</style>
</head><body>
<h2>Migración Abril 2026</h2>
<?php foreach ($results as [$t, $msg]): ?>
<p class="<?= $t ?>"><strong>[<?= strtoupper($t) ?>]</strong> <?= htmlspecialchars($msg) ?></p>
<?php endforeach; ?>
<hr>
<p><strong>✓ Listo. Puedes borrar este archivo del servidor.</strong></p>
</body></html>
