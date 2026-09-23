<?php
/**
 * Cron de automatizaciones
 * - Trigger: tarea_vencida (una sola vez por tarea y automatizacion)
 * Ejecucion recomendada: cada 5 minutos.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/automatizaciones_engine.php';

$db = getDB();

try {
    $stmtAutos = $db->prepare("SELECT id, created_by FROM automatizaciones WHERE activo = 1 AND trigger_tipo = 'tarea_vencida'");
    $stmtAutos->execute();
    $autos = $stmtAutos->fetchAll(PDO::FETCH_ASSOC);

    if (empty($autos)) {
        echo "Sin automatizaciones activas para tarea_vencida\n";
        exit(0);
    }

    $stmtTareas = $db->prepare("SELECT id, cliente_id, propiedad_id, asignado_a, creado_por, fecha_vencimiento FROM tareas WHERE estado IN ('pendiente','en_progreso') AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < NOW() ORDER BY fecha_vencimiento ASC");
    $stmtTareas->execute();
    $tareas = $stmtTareas->fetchAll(PDO::FETCH_ASSOC);

    $procesadas = 0;

    foreach ($tareas as $t) {
        foreach ($autos as $a) {
            $autoId = intval($a['id']);
            $ownerId = intval($a['created_by']);

            // Ejecutar solo la automatizacion del usuario correspondiente al owner.
            $res = automatizacionesEjecutarTrigger('tarea_vencida', [
                'entidad_tipo' => 'tarea_vencida',
                'entidad_id' => intval($t['id']),
                'tarea_id' => intval($t['id']),
                'cliente_id' => intval($t['cliente_id'] ?? 0),
                'propiedad_id' => intval($t['propiedad_id'] ?? 0),
                'agente_id' => intval($t['asignado_a'] ?? 0),
                'actor_user_id' => intval($t['creado_por'] ?? 0),
                'owner_user_id' => $ownerId,
                'dedupe_once' => true,
            ]);

            if (!empty($res['ejecutadas'])) {
                $procesadas += intval($res['ejecutadas']);
            }
        }
    }

    echo "Cron automatizaciones OK. Ejecutadas: " . $procesadas . "\n";
    exit(0);
} catch (Throwable $e) {
    if (function_exists('logError')) {
        logError('Cron automatizaciones error: ' . $e->getMessage());
    }
    echo "ERROR cron automatizaciones: " . $e->getMessage() . "\n";
    exit(1);
}
