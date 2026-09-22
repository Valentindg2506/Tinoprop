<?php
/**
 * Cron job para procesar campanas drip
 * Ejecutar cada 5 minutos: * /5 * * * * php /ruta/cron_campanas.php
 */
require_once __DIR__ . '/config/database.php';
$db = getDB();

// Get active campaigns contacts that need processing
$pendientes = $db->query("
    SELECT cc.*, c.nombre, c.apellidos, c.email, c.telefono,
           cam.tipo as campana_tipo, cam.id as campana_id
    FROM campana_contactos cc
    JOIN clientes c ON cc.cliente_id = c.id
    JOIN campanas cam ON cc.campana_id = cam.id
    WHERE cc.estado = 'activo'
    AND cc.proximo_envio IS NOT NULL
    AND cc.proximo_envio <= NOW()
    AND cam.estado = 'activa'
    LIMIT 50
")->fetchAll();

foreach ($pendientes as $cc) {
    $siguientePaso = $cc['paso_actual'] + 1;

    // Get next step
    $paso = $db->prepare("SELECT * FROM campana_pasos WHERE campana_id = ? AND orden = ?");
    $paso->execute([$cc['campana_id'], $siguientePaso]);
    $paso = $paso->fetch();

    if (!$paso) {
        // Campaign complete for this contact
        $db->prepare("UPDATE campana_contactos SET estado='completado', proximo_envio=NULL WHERE id=?")->execute([$cc['id']]);
        continue;
    }

    if ($paso['tipo'] === 'esperar') {
        // Schedule next step after wait
        $db->prepare("UPDATE campana_contactos SET paso_actual=?, proximo_envio=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?")
            ->execute([$siguientePaso, $paso['esperar_minutos'], $cc['id']]);
        continue;
    }

    // Process email/sms step
    $contenido = $paso['contenido'];
    $contenido = str_replace(['{{nombre}}','{{apellidos}}','{{email}}','{{telefono}}'],
        [$cc['nombre']??'',$cc['apellidos']??'',$cc['email']??'',$cc['telefono']??''], $contenido);

    $enviado = false;
    if ($paso['tipo'] === 'email' && !empty($cc['email'])) {
        // Send email
        $asunto = str_replace('{{nombre}}', $cc['nombre']??'', $paso['asunto']);
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
        $config = $db->query("SELECT * FROM configuracion_email LIMIT 1")->fetch();
        if ($config && $config['smtp_host']) {
            // Use SMTP if configured (simplified - in production use PHPMailer)
            $enviado = @mail($cc['email'], $asunto, nl2br($contenido), $headers);
        } else {
            $enviado = @mail($cc['email'], $asunto, nl2br($contenido), $headers);
        }
    } elseif ($paso['tipo'] === 'sms' && !empty($cc['telefono'])) {
        // Send SMS via configured provider
        $smsConfig = $db->query("SELECT * FROM sms_config WHERE activo=1 LIMIT 1")->fetch();
        if ($smsConfig) {
            if ($smsConfig['proveedor'] === 'twilio') {
                $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$smsConfig['api_sid']}/Messages.json");
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERPWD => $smsConfig['api_sid'].':'.$smsConfig['api_secret'],
                    CURLOPT_POSTFIELDS => http_build_query(['To'=>$cc['telefono'],'From'=>$smsConfig['telefono_remitente'],'Body'=>$contenido])
                ]);
                $resp = curl_exec($ch); curl_close($ch);
                $enviado = strpos($resp, '"sid"') !== false;
            }
        }
    }

    if ($enviado) {
        $db->prepare("UPDATE campana_pasos SET enviados = enviados + 1 WHERE id=?")->execute([$paso['id']]);
        $db->prepare("UPDATE campanas SET enviados = enviados + 1 WHERE id=?")->execute([$cc['campana_id']]);
    }

    // Move to next step
    $nextStep = $siguientePaso + 1;
    $nextPaso = $db->prepare("SELECT * FROM campana_pasos WHERE campana_id=? AND orden=?"); $nextPaso->execute([$cc['campana_id'], $nextStep]); $nextPaso=$nextPaso->fetch();

    if ($nextPaso) {
        $espera = $nextPaso['tipo'] === 'esperar' ? $nextPaso['esperar_minutos'] : 0;
        $db->prepare("UPDATE campana_contactos SET paso_actual=?, proximo_envio=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?")
            ->execute([$siguientePaso, max(1, $espera), $cc['id']]);
    } else {
        $db->prepare("UPDATE campana_contactos SET paso_actual=?, estado='completado', proximo_envio=NULL WHERE id=?")
            ->execute([$siguientePaso, $cc['id']]);
    }
}

if (php_sapi_name() === 'cli') echo "Procesados: " . count($pendientes) . " contactos\n";
