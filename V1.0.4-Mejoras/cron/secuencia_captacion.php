<?php
/**
 * Agente de Automatización — Secuencia de Captación WhatsApp
 *
 * Ubicación esperada: /cron/secuencia_captacion.php
 *
 * Flujo:
 *   1. Lee whatsapp_config.modo (simulacion | real)
 *   2. Busca prospectos candidatos a entrar en la secuencia
 *      (etapa en: contactado, seguimiento, nuevo_lead — y activo = 1, con teléfono)
 *   3. Los inscribe en secuencia_captacion_tracking si aún no están
 *   4. Para cada tracking activo con programado_para <= NOW() y paso dentro de rango:
 *       - Renderiza el mensaje (reemplazo de variables)
 *       - En modo simulacion: loguea en whatsapp_mensajes con estado='simulado'
 *       - En modo real: (placeholder Twilio — hoy lanza advertencia)
 *       - Actualiza tracking: avanza paso, calcula próximo programado_para
 *       - Al completar el paso 7 marca estado='completado'
 *
 * Ejecución:
 *   - CLI:       php cron/secuencia_captacion.php
 *   - Navegador: /cron/secuencia_captacion.php?run_key=CRON_BACKUP_KEY
 *   - Cron:      * /10 * * * * php /ruta/absoluta/cron/secuencia_captacion.php >> logs/secuencia.log 2>&1
 *
 * Uso manual con opciones:
 *   php cron/secuencia_captacion.php --enroll     (solo inscribir nuevos, no envía)
 *   php cron/secuencia_captacion.php --dry        (simula sin escribir en BD)
 *   php cron/secuencia_captacion.php --limit=25   (máximo de prospectos por corrida)
 */

require_once __DIR__ . '/../config/database.php';

// ----------------------------------------------------------------
// Guard: si se llama por navegador, exigir key
// ----------------------------------------------------------------
$esCli = (php_sapi_name() === 'cli');
if (!$esCli) {
    $expectedKey = getenv('CRON_BACKUP_KEY') ?: (defined('CRON_BACKUP_KEY') ? CRON_BACKUP_KEY : '');
    $providedKey = $_GET['run_key'] ?? '';
    if (!$expectedKey || $providedKey !== $expectedKey) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// ----------------------------------------------------------------
// Parse flags
// ----------------------------------------------------------------
$opts = [
    'enroll_only' => false,
    'dry'         => false,
    'limit'       => 100,
];
if ($esCli) {
    foreach ($argv ?? [] as $a) {
        if ($a === '--enroll')           $opts['enroll_only'] = true;
        if ($a === '--dry')              $opts['dry']         = true;
        if (preg_match('/^--limit=(\d+)$/', $a, $m)) $opts['limit'] = (int)$m[1];
    }
} else {
    if (isset($_GET['enroll'])) $opts['enroll_only'] = true;
    if (isset($_GET['dry']))    $opts['dry']         = true;
    if (isset($_GET['limit']))  $opts['limit']       = max(1, (int)$_GET['limit']);
}

$db = getDB();

// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------
function log_linea($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

function renderizar_mensaje($plantilla, $prospecto, $agenteNombre) {
    $vars = [
        '{nombre}'         => trim(explode(' ', $prospecto['nombre'] ?? '')[0] ?: 'vecino/a'),
        '{nombre_completo}'=> $prospecto['nombre'] ?? '',
        '{barrio}'         => $prospecto['barrio']    ?: ($prospecto['localidad'] ?: 'tu zona'),
        '{localidad}'      => $prospecto['localidad'] ?: 'Valencia',
        '{direccion}'      => trim(($prospecto['direccion'] ?? '') . ' ' . ($prospecto['numero'] ?? '')) ?: 'tu piso',
        '{agente_nombre}'  => $agenteNombre,
    ];
    return strtr($plantilla, $vars);
}

// ----------------------------------------------------------------
// Leer configuración
// ----------------------------------------------------------------
$config = $db->query("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$modo   = $config['modo'] ?? 'simulacion';
$modoReal = ($modo === 'real');

log_linea("=== Agente Secuencia Captación — modo: $modo" . ($opts['dry'] ? ' (DRY)' : '') . " ===");

// ----------------------------------------------------------------
// Cargar plantillas activas (cacheadas en memoria)
// ----------------------------------------------------------------
$plantillas = $db->query("SELECT * FROM secuencia_captacion_plantillas
                          WHERE activo = 1 ORDER BY orden ASC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($plantillas)) {
    log_linea("No hay plantillas activas. Salgo.");
    exit(0);
}

$plantillasPorOrden = [];
foreach ($plantillas as $p) { $plantillasPorOrden[(int)$p['orden']] = $p; }
$pasoMaximo = max(array_keys($plantillasPorOrden));

// ----------------------------------------------------------------
// PASO A: Inscribir prospectos candidatos que aún no están en tracking
// ----------------------------------------------------------------
$sqlCandidatos = "
    SELECT p.id, p.nombre, p.telefono
    FROM prospectos p
    LEFT JOIN secuencia_captacion_tracking t ON t.prospecto_id = p.id
    WHERE p.activo = 1
      AND p.telefono IS NOT NULL AND p.telefono <> ''
      AND p.etapa IN ('nuevo_lead','contactado','seguimiento')
      AND t.id IS NULL
    LIMIT :lim
";
$stmt = $db->prepare($sqlCandidatos);
$stmt->bindValue(':lim', $opts['limit'], PDO::PARAM_INT);
$stmt->execute();
$candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inscriptos = 0;
$primeraPlantilla = $plantillasPorOrden[1] ?? null;
if ($primeraPlantilla && !empty($candidatos)) {
    foreach ($candidatos as $c) {
        if ($opts['dry']) {
            log_linea("DRY · inscribiría prospecto #{$c['id']} ({$c['nombre']})");
            $inscriptos++;
            continue;
        }
        try {
            // Programado para AHORA (paso 1 envío inmediato), avanzará al guardarse
            $ins = $db->prepare("INSERT INTO secuencia_captacion_tracking
                (prospecto_id, plantilla_id, paso_actual, estado, programado_para)
                VALUES (:pid, :tid, 0, 'activo', NOW())");
            $ins->execute([
                ':pid' => $c['id'],
                ':tid' => $primeraPlantilla['id'],
            ]);
            $inscriptos++;
            log_linea("Inscripto prospecto #{$c['id']} ({$c['nombre']})");
        } catch (PDOException $e) {
            log_linea("WARN al inscribir prospecto #{$c['id']}: " . $e->getMessage());
        }
    }
}
log_linea("Inscripciones nuevas: $inscriptos");

if ($opts['enroll_only']) {
    log_linea("Flag --enroll activo: no proceso envíos. Fin.");
    exit(0);
}

// ----------------------------------------------------------------
// PASO B: Procesar envíos pendientes
// ----------------------------------------------------------------
$sqlPendientes = "
    SELECT t.*, p.nombre, p.telefono, p.telefono2, p.barrio, p.localidad,
           p.direccion, p.numero, p.etapa, p.activo AS prospecto_activo,
           u.nombre AS agente_nombre
    FROM secuencia_captacion_tracking t
    JOIN prospectos p ON p.id = t.prospecto_id
    LEFT JOIN usuarios u ON u.id = p.agente_id
    WHERE t.estado = 'activo'
      AND (t.programado_para IS NULL OR t.programado_para <= NOW())
      AND t.paso_actual < :paso_max
    ORDER BY t.programado_para ASC
    LIMIT :lim
";
$stmt = $db->prepare($sqlPendientes);
$stmt->bindValue(':paso_max', $pasoMaximo, PDO::PARAM_INT);
$stmt->bindValue(':lim',      $opts['limit'], PDO::PARAM_INT);
$stmt->execute();
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enviados = 0;
$errores  = 0;
$completados = 0;

foreach ($pendientes as $tr) {
    // Descartes dinámicos: si el prospecto quedó inactivo o fue captado/descartado,
    // marcamos su tracking como descartado.
    if (!$tr['prospecto_activo'] || in_array($tr['etapa'], ['captado','descartado'], true)) {
        if (!$opts['dry']) {
            $db->prepare("UPDATE secuencia_captacion_tracking
                          SET estado='descartado'
                          WHERE id = :id")->execute([':id' => $tr['id']]);
        }
        log_linea("Descartado tracking #{$tr['id']} (etapa={$tr['etapa']}, activo={$tr['prospecto_activo']}).");
        continue;
    }

    $siguientePaso = ((int)$tr['paso_actual']) + 1;
    if (!isset($plantillasPorOrden[$siguientePaso])) {
        // No debería pasar por el filtro SQL, pero por seguridad:
        if (!$opts['dry']) {
            $db->prepare("UPDATE secuencia_captacion_tracking
                          SET estado='completado'
                          WHERE id = :id")->execute([':id' => $tr['id']]);
        }
        $completados++;
        continue;
    }

    $plantilla = $plantillasPorOrden[$siguientePaso];
    $agente    = $tr['agente_nombre'] ?: 'tu agente';
    $mensaje   = renderizar_mensaje($plantilla['mensaje'], $tr, $agente);
    $telefono  = preg_replace('/[^0-9+]/', '', $tr['telefono']);

    // Envío / simulación
    $resultado = ['ok' => false, 'estado' => 'fallido', 'error' => null, 'wa_id' => null];

    if ($opts['dry']) {
        log_linea("DRY · enviaría paso {$siguientePaso} a {$telefono} (prospecto #{$tr['prospecto_id']})");
        $resultado['ok']     = true;
        $resultado['estado'] = 'simulado';
    } elseif (!$modoReal) {
        // MODO SIMULACIÓN: log a whatsapp_mensajes
        try {
            $insMsg = $db->prepare("INSERT INTO whatsapp_mensajes
                (prospecto_id, telefono, direccion, mensaje, tipo, estado, created_at)
                VALUES (:pid, :tel, 'saliente', :msg, 'text', 'simulado', NOW())");
            $insMsg->execute([
                ':pid' => $tr['prospecto_id'],
                ':tel' => $telefono,
                ':msg' => $mensaje,
            ]);
            $resultado['ok']     = true;
            $resultado['estado'] = 'simulado';
            log_linea("SIM · paso {$siguientePaso} → prospecto #{$tr['prospecto_id']} ({$telefono})");
        } catch (PDOException $e) {
            $resultado['error'] = $e->getMessage();
            log_linea("ERROR simulación: " . $e->getMessage());
        }
    } else {
        // MODO REAL — placeholder. Cuando integremos Twilio, acá va la llamada a la API.
        $resultado['error'] = 'Modo real no implementado todavía. Cambia whatsapp_config.modo a simulacion o conecta Twilio.';
        log_linea("AVISO · Modo real no implementado. Prospecto #{$tr['prospecto_id']} no recibió nada.");
    }

    // Actualizar tracking
    if ($resultado['ok']) {
        $enviados++;
        $siguienteProgramado = null;
        $nuevoEstado = 'activo';

        if ($siguientePaso >= $pasoMaximo) {
            $nuevoEstado = 'completado';
            $completados++;
        } else {
            $diasEsperaSig = (int)($plantillasPorOrden[$siguientePaso + 1]['dias_espera'] ?? 3);
            $diasEsperaActual = (int)$plantilla['dias_espera'];
            $delta = max(1, $diasEsperaSig - $diasEsperaActual);
            $siguienteProgramado = date('Y-m-d H:i:s', strtotime("+{$delta} days"));
        }

        if (!$opts['dry']) {
            $upd = $db->prepare("UPDATE secuencia_captacion_tracking
                SET paso_actual = :paso,
                    plantilla_id = :pid,
                    estado = :estado,
                    ultimo_envio = NOW(),
                    programado_para = :prog,
                    intentos = intentos + 1,
                    error_ultimo = NULL
                WHERE id = :id");
            $upd->execute([
                ':paso'   => $siguientePaso,
                ':pid'    => $plantilla['id'],
                ':estado' => $nuevoEstado,
                ':prog'   => $siguienteProgramado,
                ':id'     => $tr['id'],
            ]);
        }
    } else {
        $errores++;
        if (!$opts['dry']) {
            $upd = $db->prepare("UPDATE secuencia_captacion_tracking
                SET intentos = intentos + 1,
                    error_ultimo = :err,
                    programado_para = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                WHERE id = :id");
            $upd->execute([
                ':err' => $resultado['error'] ?: 'desconocido',
                ':id'  => $tr['id'],
            ]);
        }
    }
}

log_linea("Resumen: enviados=$enviados, errores=$errores, completados=$completados, pendientes_procesados=" . count($pendientes));
log_linea("=== Fin agente ===");