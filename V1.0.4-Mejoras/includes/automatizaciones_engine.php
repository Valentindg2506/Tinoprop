<?php
/**
 * Motor de automatizaciones por trigger.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/email.php';

if (!function_exists('automatizacionesGetUsuario')) {
    function automatizacionesGetUsuario(PDO $db, int $userId): ?array {
        if ($userId <= 0) {
            return null;
        }
        $stmt = $db->prepare("SELECT id, nombre, apellidos, email FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('automatizacionesGetCliente')) {
    function automatizacionesGetCliente(PDO $db, int $clienteId): ?array {
        if ($clienteId <= 0) {
            return null;
        }
        $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
        $stmt->execute([$clienteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('automatizacionesInsertLog')) {
    function automatizacionesInsertLog(PDO $db, int $autoId, ?int $accionId, string $estado, string $detalles, ?string $entidadTipo = null, ?int $entidadId = null): void {
        $stmt = $db->prepare("INSERT INTO automatizacion_log (automatizacion_id, accion_id, estado, detalles, entidad_tipo, entidad_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$autoId, $accionId, $estado, $detalles, $entidadTipo, $entidadId]);
    }
}

if (!function_exists('automatizacionesResolverTelefono')) {
    function automatizacionesResolverTelefono(PDO $db, array $auto, array $config, array $contexto): string {
        $destinatario = (string)($config['destinatario'] ?? 'cliente');
        if ($destinatario === 'telefono_custom') {
            return preg_replace('/[^0-9]/', '', (string)($config['telefono'] ?? ''));
        }

        $clienteId = intval($contexto['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $cli = automatizacionesGetCliente($db, $clienteId);
            if ($cli) {
                return preg_replace('/[^0-9]/', '', (string)($cli['telefono'] ?? ''));
            }
        }

        return '';
    }
}

if (!function_exists('automatizacionesEnviarWhatsapp')) {
function automatizacionesEnviarWhatsapp(PDO $db, array $auto, array $config, array $contexto): array {
        $telefono = automatizacionesResolverTelefono($db, $auto, $config, $contexto);
        if ($telefono === '') {
            return ['ok' => false, 'detalle' => 'No se pudo resolver telefono destino.'];
        }

        $sid = getenv('TWILIO_ACCOUNT_SID') ?: '';
        $token = getenv('TWILIO_AUTH_TOKEN') ?: '';
        $from = getenv('TWILIO_WHATSAPP_FROM') ?: '';

        if (!$sid || !$token || !$from) {
            try {
                $stmtCfg = $db->prepare("SELECT account_sid, auth_token, phone_number FROM twilio_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
                $stmtCfg->execute();
                $twCfg = $stmtCfg->fetch(PDO::FETCH_ASSOC);
                if ($twCfg) {
                    $sid = $sid ?: $twCfg['account_sid'];
                    $token = $token ?: $twCfg['auth_token'];
                    $from = $from ?: $twCfg['phone_number'];
                }
            } catch (Throwable $e) {}
        }

        if (!$sid || !$token || !$from) {
            return ['ok' => false, 'detalle' => 'Twilio WhatsApp no está configurado en el .env.'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'detalle' => 'cURL no disponible en servidor.'];
        }

        $mensaje = trim((string)($config['mensaje_template'] ?? 'Mensaje automatico'));
        if ($mensaje === '') {
            $mensaje = 'Mensaje automatico';
        }

        if (strpos($from, 'whatsapp:') !== 0) $from = 'whatsapp:' . $from;
        $to = 'whatsapp:+' . $telefono;

        $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $payload = http_build_query([
            'To' => $to,
            'From' => $from,
            'Body' => $mensaje
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_USERPWD => "$sid:$token",
            CURLOPT_TIMEOUT => 20,
        ]);

        $respBody = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resp = is_string($respBody) ? json_decode($respBody, true) : null;
        if ($curlErr !== '') {
            return ['ok' => false, 'detalle' => 'Error de conexion con Twilio: ' . $curlErr];
        }

        if ($httpCode >= 200 && $httpCode < 300 && !empty($resp['sid'])) {
            return ['ok' => true, 'detalle' => 'WhatsApp enviado (SID ' . $resp['sid'] . ').'];
        }

        $msg = trim((string)($resp['message'] ?? ''));
        $code = (string)($resp['code'] ?? '');
        $detalle = $msg !== '' ? ($code !== '' ? ('Codigo ' . $code . ': ' . $msg) : $msg) : ('HTTP ' . $httpCode . ' en API de Twilio.');
        return ['ok' => false, 'detalle' => $detalle];
    }
}

if (!function_exists('automatizacionesResolverEmail')) {
    function automatizacionesResolverEmail(PDO $db, array $auto, array $config, array $contexto): string {
        $destinatario = (string)($config['destinatario'] ?? 'agente_asignado');

        if ($destinatario === 'email_custom') {
            return trim((string)($config['email'] ?? ''));
        }

        if ($destinatario === 'cliente') {
            $clienteId = intval($contexto['cliente_id'] ?? 0);
            if ($clienteId > 0) {
                $cli = automatizacionesGetCliente($db, $clienteId);
                return trim((string)($cli['email'] ?? ''));
            }
            return '';
        }

        $agenteId = intval($contexto['agente_id'] ?? 0);
        if ($agenteId <= 0) {
            $agenteId = intval($auto['created_by']);
        }
        $usr = automatizacionesGetUsuario($db, $agenteId);
        return trim((string)($usr['email'] ?? ''));
    }
}

if (!function_exists('automatizacionesEjecutarTrigger')) {
    function automatizacionesEjecutarTrigger(string $triggerTipo, array $contexto = []): array {
        $db = getDB();
        $ownerUserId = intval($contexto['owner_user_id'] ?? 0);

        $sql = "SELECT * FROM automatizaciones WHERE activo = 1 AND trigger_tipo = ?";
        $params = [$triggerTipo];

        if ($ownerUserId > 0) {
            $sql .= " AND created_by = ?";
            $params[] = $ownerUserId;
        }

        $sql .= " ORDER BY id ASC";

        $stmtAuto = $db->prepare($sql);
        $stmtAuto->execute($params);
        $autos = $stmtAuto->fetchAll(PDO::FETCH_ASSOC);

        $out = [
            'trigger' => $triggerTipo,
            'automatizaciones' => count($autos),
            'ejecutadas' => 0,
            'errores' => 0,
        ];

        foreach ($autos as $auto) {
            $autoId = intval($auto['id']);
            $ok = 0;
            $err = 0;
            $pend = 0;

            $entidadTipo = (string)($contexto['entidad_tipo'] ?? $triggerTipo);
            $entidadId = intval($contexto['entidad_id'] ?? 0);
            $dedupeOnce = !empty($contexto['dedupe_once']);

            if ($dedupeOnce && $entidadId > 0) {
                $stmtDedupe = $db->prepare("SELECT id FROM automatizacion_log WHERE automatizacion_id = ? AND entidad_tipo = ? AND entidad_id = ? LIMIT 1");
                $stmtDedupe->execute([$autoId, $entidadTipo, $entidadId]);
                if ($stmtDedupe->fetchColumn()) {
                    continue;
                }
            }

            try {
                $stmtAcc = $db->prepare("SELECT * FROM automatizacion_acciones WHERE automatizacion_id = ? ORDER BY orden ASC, id ASC");
                $stmtAcc->execute([$autoId]);
                $acciones = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

                if (empty($acciones)) {
                    automatizacionesInsertLog($db, $autoId, null, 'error', 'Automatizacion sin acciones configuradas.', $triggerTipo, intval($contexto['entidad_id'] ?? 0) ?: null);
                    $db->prepare("UPDATE automatizaciones SET ultima_ejecucion = NOW() WHERE id = ?")->execute([$autoId]);
                    $out['errores']++;
                    continue;
                }

                foreach ($acciones as $acc) {
                    $accionId = intval($acc['id']);
                    $estado = 'pendiente';
                    $detalle = 'Accion no ejecutada.';
                    $config = json_decode($acc['configuracion'] ?? '{}', true);
                    if (!is_array($config)) {
                        $config = [];
                    }

                    try {
                        switch ((string)$acc['tipo']) {
                            case 'crear_tarea':
                                $titulo = trim((string)($config['titulo'] ?? ('Tarea automatica: ' . $auto['nombre'])));
                                if ($titulo === '') {
                                    $titulo = 'Tarea automatica: ' . $auto['nombre'];
                                }
                                $descripcion = trim((string)($config['descripcion'] ?? 'Generada por automatizacion.'));
                                $prioridad = (string)($config['prioridad'] ?? 'media');
                                if (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
                                    $prioridad = 'media';
                                }
                                $asignadoA = intval($config['asignar_a'] ?? 0);
                                if ($asignadoA <= 0) {
                                    $asignadoA = intval($contexto['agente_id'] ?? 0);
                                }
                                if ($asignadoA <= 0) {
                                    $asignadoA = intval($auto['created_by']);
                                }

                                $dueAt = date('Y-m-d H:i:s', strtotime('+1 day'));
                                $stmtTask = $db->prepare("INSERT INTO tareas (titulo, descripcion, tipo, prioridad, estado, fecha_vencimiento, asignado_a, creado_por, cliente_id, propiedad_id) VALUES (?, ?, 'otro', ?, 'pendiente', ?, ?, ?, ?, ?)");
                                $stmtTask->execute([
                                    $titulo,
                                    $descripcion,
                                    $prioridad,
                                    $dueAt,
                                    $asignadoA,
                                    intval($contexto['actor_user_id'] ?? $auto['created_by']),
                                    intval($contexto['cliente_id'] ?? 0) ?: null,
                                    intval($contexto['propiedad_id'] ?? 0) ?: null,
                                ]);
                                $estado = 'exito';
                                $detalle = 'Tarea #' . intval($db->lastInsertId()) . ' creada.';
                                break;

                            case 'notificar':
                                $tituloNotif = trim((string)($config['titulo'] ?? 'Automatizacion ejecutada'));
                                if ($tituloNotif === '') {
                                    $tituloNotif = 'Automatizacion ejecutada';
                                }
                                $mensajeNotif = trim((string)($config['mensaje'] ?? ('Automatizacion: ' . $auto['nombre'])));
                                $usuarioNotif = intval($config['usuario_id'] ?? 0);
                                if ($usuarioNotif <= 0) {
                                    $usuarioNotif = intval($contexto['agente_id'] ?? 0);
                                }
                                if ($usuarioNotif <= 0) {
                                    $usuarioNotif = intval($auto['created_by']);
                                }
                                $textoNotif = $tituloNotif . ($mensajeNotif !== '' ? (' - ' . $mensajeNotif) : '');
                                $stmtNotif = $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, enlace) VALUES (?, ?, ?)");
                                $stmtNotif->execute([$usuarioNotif, $textoNotif, 'modules/automatizaciones/log.php?id=' . $autoId]);
                                $estado = 'exito';
                                $detalle = 'Notificacion creada para usuario #' . $usuarioNotif . '.';
                                break;

                            case 'enviar_email':
                                $dest = automatizacionesResolverEmail($db, $auto, $config, $contexto);
                                if ($dest === '') {
                                    $estado = 'pendiente';
                                    $detalle = 'No se pudo resolver destinatario de email.';
                                    break;
                                }
                                $asunto = trim((string)($config['asunto'] ?? ('Automatizacion: ' . $auto['nombre'])));
                                if ($asunto === '') {
                                    $asunto = 'Automatizacion: ' . $auto['nombre'];
                                }
                                $mensaje = trim((string)($config['mensaje'] ?? 'Ejecucion automatica.'));
                                if ($mensaje === '') {
                                    $mensaje = 'Ejecucion automatica.';
                                }
                                $okMail = enviarEmail($dest, $asunto, nl2br(sanitize($mensaje)), true);
                                if ($okMail) {
                                    $estado = 'exito';
                                    $detalle = 'Email enviado a ' . $dest . '.';
                                } else {
                                    $estado = 'error';
                                    $detalle = 'Fallo al enviar email a ' . $dest . '.';
                                }
                                break;

                            case 'enviar_whatsapp':
                                $waRes = automatizacionesEnviarWhatsapp($db, $auto, $config, $contexto);
                                $estado = $waRes['ok'] ? 'exito' : 'error';
                                $detalle = (string)$waRes['detalle'];
                                break;

                            case 'cambiar_estado_propiedad':
                                $propId = intval($contexto['propiedad_id'] ?? 0);
                                if ($propId <= 0) {
                                    $propId = intval($config['propiedad_id'] ?? 0);
                                }
                                $nuevoEstado = trim((string)($config['nuevo_estado'] ?? ''));
                                if ($propId <= 0 || $nuevoEstado === '') {
                                    $estado = 'pendiente';
                                    $detalle = 'Falta propiedad_id o nuevo_estado para cambiar estado.';
                                    break;
                                }
                                $db->prepare("UPDATE propiedades SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $propId]);
                                $estado = 'exito';
                                $detalle = 'Propiedad #' . $propId . ' cambiada a estado "' . $nuevoEstado . '".';
                                break;

                            case 'asignar_agente':
                                $agenteId = intval($config['agente_id'] ?? 0);
                                if ($agenteId <= 0) {
                                    $estado = 'pendiente';
                                    $detalle = 'agente_id no configurado.';
                                    break;
                                }
                                $entidad = (string)($config['entidad'] ?? 'cliente');
                                if ($entidad === 'propiedad') {
                                    $propId = intval($contexto['propiedad_id'] ?? 0);
                                    if ($propId <= 0) {
                                        $propId = intval($config['propiedad_id'] ?? 0);
                                    }
                                    if ($propId <= 0) {
                                        $estado = 'pendiente';
                                        $detalle = 'No se pudo resolver propiedad para asignar agente.';
                                        break;
                                    }
                                    $db->prepare("UPDATE propiedades SET agente_id = ? WHERE id = ?")->execute([$agenteId, $propId]);
                                    $estado = 'exito';
                                    $detalle = 'Agente #' . $agenteId . ' asignado a propiedad #' . $propId . '.';
                                } else {
                                    $cliId = intval($contexto['cliente_id'] ?? 0);
                                    if ($cliId <= 0) {
                                        $cliId = intval($config['cliente_id'] ?? 0);
                                    }
                                    if ($cliId <= 0) {
                                        $estado = 'pendiente';
                                        $detalle = 'No se pudo resolver cliente para asignar agente.';
                                        break;
                                    }
                                    $db->prepare("UPDATE clientes SET agente_id = ? WHERE id = ?")->execute([$agenteId, $cliId]);
                                    $estado = 'exito';
                                    $detalle = 'Agente #' . $agenteId . ' asignado a cliente #' . $cliId . '.';
                                }
                                break;

                            case 'mover_pipeline':
                                $itemId = intval($contexto['pipeline_item_id'] ?? 0);
                                if ($itemId <= 0) {
                                    $itemId = intval($config['pipeline_item_id'] ?? 0);
                                }
                                $etapaId = intval($config['etapa_id'] ?? 0);
                                if ($itemId <= 0 || $etapaId <= 0) {
                                    $estado = 'pendiente';
                                    $detalle = 'Falta pipeline_item_id o etapa_id para mover pipeline.';
                                    break;
                                }
                                $db->prepare("UPDATE pipeline_items SET etapa_id = ?, updated_at = NOW() WHERE id = ?")->execute([$etapaId, $itemId]);
                                $estado = 'exito';
                                $detalle = 'Pipeline item #' . $itemId . ' movido a etapa #' . $etapaId . '.';
                                break;

                            case 'esperar':
                                $estado = 'pendiente';
                                $detalle = 'Accion de espera registrada para procesamiento diferido.';
                                break;

                            default:
                                $estado = 'error';
                                $detalle = 'Tipo de accion no soportado: ' . $acc['tipo'];
                                break;
                        }
                    } catch (Throwable $e) {
                        $estado = 'error';
                        $detalle = 'Excepcion en accion "' . $acc['tipo'] . '": ' . $e->getMessage();
                    }

                    if ($estado === 'exito') {
                        $ok++;
                    } elseif ($estado === 'error') {
                        $err++;
                    } else {
                        $pend++;
                    }

                    automatizacionesInsertLog(
                        $db,
                        $autoId,
                        $accionId,
                        $estado,
                        $detalle,
                        $entidadTipo,
                        $entidadId ?: null
                    );
                }

                $estadoFinal = 'pendiente';
                if ($err > 0) {
                    $estadoFinal = 'error';
                } elseif ($ok > 0) {
                    $estadoFinal = 'exito';
                }

                $resumen = 'Trigger ' . $triggerTipo . '. Exito: ' . $ok . ', Error: ' . $err . ', Pendiente: ' . $pend . '.';
                automatizacionesInsertLog(
                    $db,
                    $autoId,
                    null,
                    $estadoFinal,
                    $resumen,
                    $entidadTipo,
                    $entidadId ?: null
                );

                $db->prepare("UPDATE automatizaciones SET ejecuciones = ejecuciones + 1, ultima_ejecucion = NOW() WHERE id = ?")
                    ->execute([$autoId]);

                if (function_exists('registrarActividad')) {
                    registrarActividad('ejecutar', 'automatizacion', $autoId, $resumen);
                }

                $out['ejecutadas']++;
                if ($estadoFinal === 'error') {
                    $out['errores']++;
                }
            } catch (Throwable $e) {
                $out['errores']++;
                try {
                    automatizacionesInsertLog($db, $autoId, null, 'error', 'Error general de automatizacion: ' . $e->getMessage(), $entidadTipo, $entidadId ?: null);
                } catch (Throwable $ignored) {
                }
            }
        }

        return $out;
    }
}
