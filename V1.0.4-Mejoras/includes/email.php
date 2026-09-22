<?php
/**
 * Sistema de Email - Compatible con Hostinger
 * Soporta mail() nativo de PHP y SMTP basico
 */

if (!isset($GLOBALS['LAST_EMAIL_ERROR'])) {
    $GLOBALS['LAST_EMAIL_ERROR'] = null;
}

function setLastEmailError($message) {
    $GLOBALS['LAST_EMAIL_ERROR'] = (string)$message;
}

function getLastEmailError() {
    return $GLOBALS['LAST_EMAIL_ERROR'] ?? null;
}

/**
 * Construye configuracion efectiva de transporte de email.
 */
function getEmailTransportConfig($userId = null) {
    static $cache = [];

    $cacheKey = $userId !== null ? ('u' . intval($userId)) : 'default';
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $cfg = [
        'method' => MAIL_METHOD,
        'host' => SMTP_HOST,
        'port' => intval(SMTP_PORT),
        'user' => SMTP_USER,
        'pass' => SMTP_PASS,
        'from' => SMTP_FROM,
        'from_name' => SMTP_FROM_NAME,
    ];

    try {
        $db = getDB();
        $stmt = null;

        if ($userId !== null) {
            $stmt = $db->prepare("SELECT * FROM email_cuentas WHERE usuario_id = ? AND activo = 1 LIMIT 1");
            $stmt->execute([intval($userId)]);
        } else {
            $stmt = $db->query("SELECT * FROM email_cuentas WHERE activo = 1 ORDER BY id ASC LIMIT 1");
        }

        $cuenta = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        if ($cuenta) {
            $cfg['from'] = !empty($cuenta['email']) ? $cuenta['email'] : $cfg['from'];
            $cfg['from_name'] = !empty($cuenta['nombre_display']) ? $cuenta['nombre_display'] : $cfg['from_name'];

            if (!empty($cuenta['smtp_host'])) {
                $cfg['method'] = 'smtp';
                $cfg['host'] = $cuenta['smtp_host'];
                $cfg['port'] = !empty($cuenta['smtp_port']) ? intval($cuenta['smtp_port']) : 587;
                $cfg['user'] = $cuenta['smtp_user'] ?? '';
                $cfg['pass'] = $cuenta['smtp_pass'] ?? '';
            }
        }
    } catch (Exception $e) {
        // Puede no existir la tabla de email en instalaciones minimas.
    }

    $cache[$cacheKey] = $cfg;
    return $cfg;
}

/**
 * Verifica preferencia de notificaciones email para un usuario.
 */
function userWantsEmailNotification($userId, $settingKey, $default = true) {
    if (empty($userId)) {
        return $default;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valor FROM usuario_ajustes WHERE usuario_id = ? AND clave = ? LIMIT 1");
        $stmt->execute([intval($userId), $settingKey]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $default;
        }
        return (string)$value === '1';
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Enviar email usando el metodo configurado
 */
function enviarEmail($destinatario, $asunto, $cuerpo, $esHTML = true) {
    try {
        setLastEmailError('');

        $senderUserId = null;
        if (function_exists('currentUserId')) {
            try {
                $senderUserId = currentUserId();
            } catch (Exception $e) {
                $senderUserId = null;
            }
        }

        $cfg = getEmailTransportConfig($senderUserId);

        if ($cfg['method'] === 'smtp' && !empty($cfg['host'])) {
            return enviarEmailSMTP($destinatario, $asunto, $cuerpo, $esHTML, $cfg);
        } else {
            return enviarEmailMail($destinatario, $asunto, $cuerpo, $esHTML, $cfg);
        }
    } catch (Exception $e) {
        setLastEmailError($e->getMessage());
        logError('Email send error: ' . $e->getMessage(), [
            'to' => $destinatario,
            'subject' => $asunto
        ]);
        return false;
    }
}

/**
 * Enviar usando mail() nativo de PHP
 */
function enviarEmailMail($destinatario, $asunto, $cuerpo, $esHTML = true, $cfg = null) {
    $cfg = is_array($cfg) ? $cfg : getEmailTransportConfig();

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $esHTML ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $cfg['from_name'] . ' <' . $cfg['from'] . '>';
    $headers[] = 'Reply-To: ' . $cfg['from'];
    $headers[] = 'X-Mailer: Tinoprop';

    if ($esHTML) {
        $cuerpo = plantillaEmail($cuerpo);
    }

    $result = mail($destinatario, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, implode("\r\n", $headers));

    if ($result) {
        logError('Email sent successfully', ['to' => $destinatario, 'subject' => $asunto]);
    } else {
        $error = 'mail() devolvio false. Verifica SMTP en modulo Email o servidor MTA local.';
        setLastEmailError($error);
        logError('Email send error: ' . $error, [
            'to' => $destinatario,
            'subject' => $asunto,
            'from' => $cfg['from'] ?? null,
            'method' => 'mail'
        ]);
    }

    return $result;
}

/**
 * Enviar usando SMTP directo (sockets) - sin dependencias externas
 */
function enviarEmailSMTP($destinatario, $asunto, $cuerpo, $esHTML = true, $cfg = null) {
    $cfg = is_array($cfg) ? $cfg : getEmailTransportConfig();

    $socket = @fsockopen(
        ($cfg['port'] == 465 ? 'ssl://' : '') . $cfg['host'],
        $cfg['port'],
        $errno, $errstr, 30
    );

    if (!$socket) {
        throw new Exception("No se pudo conectar al servidor SMTP: $errstr ($errno)");
    }

    // Leer saludo inicial del servidor
    $greeting = smtpRead($socket);
    smtpAssertResponse($greeting, [220], 'saludo inicial');

    // EHLO
    smtpCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

    // STARTTLS para puerto 587
    if ($cfg['port'] == 587) {
        smtpCommand($socket, "STARTTLS", [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('No se pudo establecer canal TLS con el servidor SMTP');
        }
        smtpCommand($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
    }

    // Autenticacion
    if (!empty($cfg['user'])) {
        smtpCommand($socket, "AUTH LOGIN", [334]);
        smtpCommand($socket, base64_encode($cfg['user']), [334], true);
        smtpCommand($socket, base64_encode($cfg['pass']), [235], true);
    }

    // Enviar
    smtpCommand($socket, "MAIL FROM:<" . $cfg['from'] . ">", [250]);
    smtpCommand($socket, "RCPT TO:<$destinatario>", [250, 251]);
    smtpCommand($socket, "DATA", [354]);

    // Headers del mensaje
    $contentType = $esHTML ? 'text/html' : 'text/plain';
    if ($esHTML) {
        $cuerpo = plantillaEmail($cuerpo);
    }

    $mensaje = "From: " . $cfg['from_name'] . " <" . $cfg['from'] . ">\r\n";
    $mensaje .= "To: $destinatario\r\n";
    $mensaje .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
    $mensaje .= "MIME-Version: 1.0\r\n";
    $mensaje .= "Content-Type: $contentType; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: base64\r\n";
    $mensaje .= "\r\n";
    $mensaje .= chunk_split(base64_encode($cuerpo));
    $mensaje .= "\r\n.";

    smtpCommand($socket, $mensaje, [250]);
    smtpCommand($socket, "QUIT", [221]);

    fclose($socket);
    return true;
}

function smtpCommand($socket, $command, $expectedCodes = [250], $sensitive = false) {
    fwrite($socket, $command . "\r\n");
    $response = smtpRead($socket);
    smtpAssertResponse($response, $expectedCodes, $sensitive ? '[comando sensible]' : $command);
    return $response;
}

function smtpRead($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    return $response;
}

function smtpAssertResponse($response, $expectedCodes, $context = '') {
    $response = (string)$response;
    $code = intval(substr($response, 0, 3));

    if (empty($expectedCodes) || in_array($code, $expectedCodes, true)) {
        return true;
    }

    $cleanResponse = trim(preg_replace('/\s+/', ' ', $response));
    $ctx = $context !== '' ? (" en " . $context) : '';
    throw new Exception("Respuesta SMTP no valida$ctx: $cleanResponse");
}

/**
 * Plantilla HTML base para emails
 */
function plantillaEmail($contenido) {
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background: #1e293b; color: #fff; padding: 20px; text-align: center;">
            <h2 style="margin: 0;">' . APP_NAME . '</h2>
        </div>
        <div style="padding: 30px;">
            ' . $contenido . '
        </div>
        <div style="background: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #6c757d;">
            <p>' . RGPD_EMPRESA . ' - ' . RGPD_DIRECCION . '</p>
            <p>Este email fue enviado desde ' . APP_NAME . '</p>
        </div>
    </div>
</body>
</html>';
}

/**
 * Enviar notificacion de nueva visita programada
 */
function notificarNuevaVisita($visita, $propiedad, $cliente, $agente) {
    if (empty($agente['id']) || !userWantsEmailNotification($agente['id'], 'notif_email_visitas', true)) {
        return false;
    }

    $asunto = "Nueva visita programada - " . $propiedad['referencia'];
    $cuerpo = "<h3>Nueva visita programada</h3>
        <p><strong>Propiedad:</strong> {$propiedad['referencia']} - " . htmlspecialchars($propiedad['titulo']) . "</p>
        <p><strong>Cliente:</strong> " . htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']) . "</p>
        <p><strong>Fecha:</strong> " . date('d/m/Y', strtotime($visita['fecha'])) . " a las " . substr($visita['hora'], 0, 5) . "</p>
        <p><strong>Direccion:</strong> " . htmlspecialchars($propiedad['direccion'] . ', ' . $propiedad['localidad']) . "</p>
        <p><a href='" . APP_URL . "/modules/visitas/index.php' style='background:#2563eb; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ver en el CRM</a></p>";

    if (!empty($agente['email'])) {
        enviarEmail($agente['email'], $asunto, $cuerpo);
    }
}

/**
 * Enviar notificacion de tarea asignada
 */
function notificarTareaAsignada($tarea, $agente) {
    if (empty($agente['id']) || !userWantsEmailNotification($agente['id'], 'notif_email_tareas', true)) {
        return false;
    }

    $asunto = "Nueva tarea asignada: " . $tarea['titulo'];
    $prioridades = ['urgente' => '🔴 URGENTE', 'alta' => '🟠 Alta', 'media' => '🟡 Media', 'baja' => '🟢 Baja'];
    $cuerpo = "<h3>Nueva tarea asignada</h3>
        <p><strong>Titulo:</strong> " . htmlspecialchars($tarea['titulo']) . "</p>
        <p><strong>Prioridad:</strong> " . ($prioridades[$tarea['prioridad']] ?? $tarea['prioridad']) . "</p>";
    if (!empty($tarea['fecha_vencimiento'])) {
        $cuerpo .= "<p><strong>Vencimiento:</strong> " . date('d/m/Y H:i', strtotime($tarea['fecha_vencimiento'])) . "</p>";
    }
    $cuerpo .= "<p><a href='" . APP_URL . "/modules/tareas/index.php' style='background:#2563eb; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ver en el CRM</a></p>";

    if (!empty($agente['email'])) {
        enviarEmail($agente['email'], $asunto, $cuerpo);
    }
}

/**
 * Enviar propiedades compatibles a un cliente
 */
function enviarMatchingCliente($cliente, $propiedades) {
    if (empty($cliente['email'])) return false;

    $asunto = "Propiedades que pueden interesarte - " . APP_NAME;
    $cuerpo = "<h3>Hola " . htmlspecialchars($cliente['nombre']) . ",</h3>
        <p>Hemos encontrado estas propiedades que coinciden con tus preferencias:</p><hr>";

    foreach ($propiedades as $p) {
        $cuerpo .= "<div style='margin: 15px 0; padding: 15px; border: 1px solid #eee; border-radius: 8px;'>
            <h4 style='margin:0 0 5px 0;'>" . htmlspecialchars($p['titulo']) . "</h4>
            <p style='color: #2563eb; font-size: 18px; font-weight: bold; margin: 5px 0;'>" . number_format($p['precio'], 0, ',', '.') . " &euro;</p>
            <p style='color: #666; margin: 5px 0;'>" . htmlspecialchars($p['localidad'] . ', ' . $p['provincia']) . "</p>
            <p style='margin: 5px 0;'>";
        if ($p['habitaciones']) $cuerpo .= $p['habitaciones'] . " hab. | ";
        if ($p['superficie_construida']) $cuerpo .= $p['superficie_construida'] . " m&sup2; | ";
        $cuerpo .= ucfirst($p['tipo']) . "</p>
        </div>";
    }

    $cuerpo .= "<hr><p style='font-size:11px; color:#999;'>Este email se ha enviado porque tienes registradas preferencias de busqueda en nuestro sistema. Si deseas dejar de recibir estos emails, contacta con nosotros en " . RGPD_EMAIL_DPD . "</p>";

    return enviarEmail($cliente['email'], $asunto, $cuerpo);
}
