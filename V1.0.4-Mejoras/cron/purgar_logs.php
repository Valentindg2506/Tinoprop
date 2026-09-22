<?php
/**
 * Cron: Purga de logs RGPD
 *
 * Elimina registros de actividad y acciones IA con más de 12 meses.
 * Conserva consentimientos de cookies durante 3 años (obligación documental RGPD).
 *
 * Añadir al crontab del servidor:
 *   0 3 1 * * php /var/www/html/CRM/cron/purgar_logs.php >> /var/log/crm_rgpd_purge.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo ejecutable desde CLI.');
}

require_once __DIR__ . '/../config/database.php';

$db     = getDB();
$inicio = microtime(true);
$fecha  = date('Y-m-d H:i:s');
$limite12m = date('Y-m-d H:i:s', strtotime('-12 months'));
$limite3y  = date('Y-m-d H:i:s', strtotime('-3 years'));

echo "[$fecha] Iniciando purga de logs RGPD...\n";

$tareas = [
    [
        'tabla'     => 'actividad_log',
        'columna'   => 'created_at',
        'limite'    => $limite12m,
        'descripcion' => 'Registro de actividad de usuarios (12 meses)',
    ],
    [
        'tabla'     => 'ia_acciones_log',
        'columna'   => 'created_at',
        'limite'    => $limite12m,
        'descripcion' => 'Log de acciones del asistente IA (12 meses)',
    ],
    [
        'tabla'     => 'cookie_consents',
        'columna'   => 'created_at',
        'limite'    => $limite3y,
        'descripcion' => 'Registros de consentimiento de cookies (3 años)',
    ],
];

$totalEliminados = 0;

foreach ($tareas as $tarea) {
    try {
        // Verificar que la tabla existe antes de intentar borrar
        $existe = $db->query("SHOW TABLES LIKE '{$tarea['tabla']}'")->fetch();
        if (!$existe) {
            echo "  SKIP  · {$tarea['tabla']} — tabla no existe aún\n";
            continue;
        }

        $stmt = $db->prepare(
            "DELETE FROM `{$tarea['tabla']}` WHERE `{$tarea['columna']}` < ?"
        );
        $stmt->execute([$tarea['limite']]);
        $eliminados = $stmt->rowCount();
        $totalEliminados += $eliminados;

        echo "  OK    · {$tarea['descripcion']}: {$eliminados} registros eliminados\n";
    } catch (Throwable $e) {
        echo "  ERROR · {$tarea['tabla']}: " . $e->getMessage() . "\n";
    }
}

$duracion = round(microtime(true) - $inicio, 3);
echo "\nTotal eliminados: {$totalEliminados} registros en {$duracion}s\n";
echo "[" . date('Y-m-d H:i:s') . "] Purga completada.\n";
