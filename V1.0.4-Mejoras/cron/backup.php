<?php
/**
 * Cron endpoint para backups automaticos
 * Configurar en Hostinger: cron job cada dia/semana
 * URL: https://tudominio.com/cron/backup.php?key=TU_CLAVE_SECRETA
 *
 * Ejemplo cron: 0 3 * * * curl -s "https://tudominio.com/cron/backup.php?key=TU_CLAVE_SECRETA"
 */

// No necesita sesion ni login, pero si clave secreta
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/backup.php';

// Clave secreta para proteger el endpoint
// Recomendado: definir CRON_BACKUP_KEY en .env
$cronSecret = getEnvSecret('CRON_BACKUP_KEY', hash('sha256', DB_NAME . '|' . DB_USER . '|' . DB_PASS));

// Verificar clave
$key = $_GET['key'] ?? '';
if ($key === '' || !hash_equals($cronSecret, $key)) {
    http_response_code(403);
    echo "Acceso denegado.\n";
    logError('Cron backup: acceso denegado con clave incorrecta', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    exit;
}

// Generar backup
$result = generarBackup();

if (isset($result['success'])) {
    // Limpiar backups antiguos (mantener 10)
    $eliminados = limpiarBackupsAntiguos(10);

    $mensaje = "Backup creado: {$result['filename']} (" . round($result['size'] / 1024, 1) . " KB)";
    if ($eliminados > 0) {
        $mensaje .= " | $eliminados backups antiguos eliminados";
    }

    logError('Cron backup exitoso', ['file' => $result['filename'], 'size' => $result['size']]);
    echo "OK: $mensaje\n";
} else {
    $error = $result['error'] ?? 'Error desconocido';
    logError('Cron backup fallido', ['error' => $error]);
    http_response_code(500);
    echo "ERROR: $error\n";
}
