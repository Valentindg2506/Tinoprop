<?php
/**
 * Migración: divide piso_puerta en piso + puerta + escalera
 * - Renombra piso_puerta a piso (mantiene datos existentes)
 * - Añade columnas puerta y escalera
 *
 * Ejecutar UNA SOLA VEZ. Borrar después.
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
    // Mantiene piso_puerta con los datos existentes, solo cambia el nombre lógico.
    // No renombramos la columna en DB para no romper nada existente —
    // simplemente añadimos las dos nuevas y relabelizamos en el front.
    "ALTER TABLE prospectos ADD COLUMN IF NOT EXISTS escalera VARCHAR(20) DEFAULT NULL AFTER piso_puerta",
    "ALTER TABLE prospectos ADD COLUMN IF NOT EXISTS puerta VARCHAR(20) DEFAULT NULL AFTER escalera",
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
<html lang="es"><head><meta charset="UTF-8"><title>Migración Piso/Puerta</title>
<style>body{font-family:monospace;padding:30px;max-width:800px;margin:0 auto}.ok{color:#10b981}.error{color:#ef4444}</style>
</head><body>
<h2>Migración: Piso / Escalera / Puerta</h2>
<?php foreach ($results as [$t, $msg]): ?>
<p class="<?= $t ?>"><strong>[<?= strtoupper($t) ?>]</strong> <?= htmlspecialchars($msg) ?></p>
<?php endforeach; ?>
<hr>
<p><strong>✓ Listo. Borra este archivo del servidor.</strong></p>
<p>Los datos existentes en <code>piso_puerta</code> se conservan intactos y se mostrarán en el campo "Piso".</p>
</body></html>
