<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/automatizaciones_engine.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verifyCsrf();

$id = intval(post('id'));
if (!$id) {
    setFlash('danger', 'Automatizacion no especificada.');
    header('Location: index.php');
    exit;
}

$db = getDB();

// Verificar que la automatizacion existe y es de tipo manual
$stmt = $db->prepare("SELECT * FROM automatizaciones WHERE id = ?");
$stmt->execute([$id]);
$auto = $stmt->fetch();

if (!$auto) {
    setFlash('danger', 'Automatizacion no encontrada.');
    header('Location: index.php');
    exit;
}

if ($auto['trigger_tipo'] !== 'manual') {
    setFlash('danger', 'Esta automatizacion no es de ejecucion manual.');
    header('Location: index.php');
    exit;
}

if (!isAdmin() && intval($auto['created_by']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para ejecutar esta automatizacion.');
    header('Location: index.php');
    exit;
}

if (!$auto['activo']) {
    setFlash('warning', 'La automatizacion esta desactivada. Activala antes de ejecutarla.');
    header('Location: index.php');
    exit;
}

// Cargar acciones ordenadas
$stmtAcc = $db->prepare("SELECT * FROM automatizacion_acciones WHERE automatizacion_id = ? ORDER BY orden ASC, id ASC");
$stmtAcc->execute([$id]);
$acciones = $stmtAcc->fetchAll();

if (empty($acciones)) {
    $db->prepare("INSERT INTO automatizacion_log (automatizacion_id, estado, detalles, entidad_tipo) VALUES (?, 'error', ?, 'manual')")
        ->execute([$id, 'Ejecucion manual sin acciones configuradas.']);
    $db->prepare("UPDATE automatizaciones SET ultima_ejecucion = NOW() WHERE id = ?")->execute([$id]);
    setFlash('danger', 'La automatizacion no tiene acciones configuradas.');
    header('Location: index.php');
    exit;
}

$stmtOwner = $db->prepare("SELECT id, nombre, apellidos, email FROM usuarios WHERE id = ? LIMIT 1");
$stmtOwner->execute([intval($auto['created_by'])]);
$owner = $stmtOwner->fetch();

$successCount = 0;
$errorCount = 0;
$pendingCount = 0;

foreach ($acciones as $acc) {
    $estado = 'pendiente';
    $detalle = 'Accion no ejecutada.';
    $config = json_decode($acc['configuracion'] ?? '{}', true);
    if (!is_array($config)) {
        $config = [];
    }

    try {
        switch ($acc['tipo']) {
            case 'crear_tarea':
                $titulo = trim((string)($config['titulo'] ?? 'Tarea automatica: ' . $auto['nombre']));
                if ($titulo === '') {
                    $titulo = 'Tarea automatica: ' . $auto['nombre'];
                }
                $descripcion = trim((string)($config['descripcion'] ?? 'Generada por automatizacion manual.'));
                $prioridad = (string)($config['prioridad'] ?? 'media');
                if (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
                    $prioridad = 'media';
                }
                $asignadoA = intval($config['asignar_a'] ?? 0);
                if ($asignadoA <= 0) {
                    $asignadoA = intval($auto['created_by']);
                }

                $dueAt = date('Y-m-d H:i:s', strtotime('+1 day'));
                $stmtTask = $db->prepare("INSERT INTO tareas (titulo, descripcion, tipo, prioridad, estado, fecha_vencimiento, asignado_a, creado_por) VALUES (?, ?, 'otro', ?, 'pendiente', ?, ?, ?)");
                $stmtTask->execute([$titulo, $descripcion, $prioridad, $dueAt, $asignadoA, intval(currentUserId())]);
                $tareaId = intval($db->lastInsertId());

                $estado = 'exito';
                $detalle = 'Tarea #' . $tareaId . ' creada y asignada al usuario #' . $asignadoA . '.';
                break;

            case 'notificar':
                $tituloNotif = trim((string)($config['titulo'] ?? 'Automatizacion ejecutada'));
                if ($tituloNotif === '') {
                    $tituloNotif = 'Automatizacion ejecutada';
                }
                $mensajeNotif = trim((string)($config['mensaje'] ?? ('Automatizacion: ' . $auto['nombre'])));
                $textoNotif = $tituloNotif . ($mensajeNotif !== '' ? (' - ' . $mensajeNotif) : '');
                $usuarioNotif = intval($config['usuario_id'] ?? 0);
                if ($usuarioNotif <= 0) {
                    $usuarioNotif = intval($auto['created_by']);
                }

                $stmtNotif = $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, enlace) VALUES (?, ?, ?)");
                $stmtNotif->execute([$usuarioNotif, $textoNotif, 'modules/automatizaciones/log.php?id=' . $id]);

                $estado = 'exito';
                $detalle = 'Notificacion creada para usuario #' . $usuarioNotif . '.';
                break;

            case 'enviar_email':
                $destinatario = '';
                $tipoDest = (string)($config['destinatario'] ?? 'agente_asignado');
                if ($tipoDest === 'email_custom' && !empty($config['email'])) {
                    $destinatario = trim((string)$config['email']);
                }
                if ($destinatario === '' && !empty($owner['email'])) {
                    // Sin contexto de entidad en ejecucion manual: fallback al creador de la automatizacion.
                    $destinatario = trim((string)$owner['email']);
                }

                if ($destinatario === '') {
                    $estado = 'pendiente';
                    $detalle = 'No se pudo resolver destinatario de email para ejecucion manual.';
                    break;
                }

                $asunto = trim((string)($config['asunto'] ?? ('Automatizacion: ' . $auto['nombre'])));
                if ($asunto === '') {
                    $asunto = 'Automatizacion: ' . $auto['nombre'];
                }
                $mensaje = trim((string)($config['mensaje'] ?? 'Ejecucion manual de automatizacion.'));
                if ($mensaje === '') {
                    $mensaje = 'Ejecucion manual de automatizacion.';
                }

                $okMail = enviarEmail($destinatario, $asunto, nl2br(sanitize($mensaje)), true);
                if ($okMail) {
                    $estado = 'exito';
                    $detalle = 'Email enviado a ' . $destinatario . '.';
                } else {
                    $estado = 'error';
                    $detalle = 'Fallo al enviar email a ' . $destinatario . '.';
                }
                break;

            case 'esperar':
                $estado = 'pendiente';
                $detalle = 'Accion de espera omitida en ejecucion manual inmediata.';
                break;

            case 'enviar_whatsapp':
                $waRes = automatizacionesEnviarWhatsapp($db, $auto, $config, [
                    'cliente_id' => intval($config['cliente_id'] ?? 0),
                    'agente_id' => intval($config['agente_id'] ?? 0),
                ]);
                $estado = $waRes['ok'] ? 'exito' : 'error';
                $detalle = (string)$waRes['detalle'];
                break;

            case 'cambiar_estado_propiedad':
                $propId = intval($config['propiedad_id'] ?? 0);
                $nuevoEstado = trim((string)($config['nuevo_estado'] ?? ''));
                if ($propId <= 0 || $nuevoEstado === '') {
                    $estado = 'pendiente';
                    $detalle = 'Debes configurar propiedad_id y nuevo_estado para esta accion.';
                    break;
                }
                $db->prepare("UPDATE propiedades SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $propId]);
                $estado = 'exito';
                $detalle = 'Propiedad #' . $propId . ' actualizada a estado "' . $nuevoEstado . '".';
                break;

            case 'asignar_agente':
                $agenteId = intval($config['agente_id'] ?? 0);
                $entidad = trim((string)($config['entidad'] ?? 'cliente'));
                if ($agenteId <= 0) {
                    $estado = 'pendiente';
                    $detalle = 'Debes configurar agente_id para esta accion.';
                    break;
                }
                if ($entidad === 'propiedad') {
                    $propId = intval($config['propiedad_id'] ?? 0);
                    if ($propId <= 0) {
                        $estado = 'pendiente';
                        $detalle = 'Debes configurar propiedad_id para asignar agente.';
                        break;
                    }
                    $db->prepare("UPDATE propiedades SET agente_id = ? WHERE id = ?")->execute([$agenteId, $propId]);
                    $estado = 'exito';
                    $detalle = 'Agente #' . $agenteId . ' asignado a propiedad #' . $propId . '.';
                } else {
                    $clienteIdCfg = intval($config['cliente_id'] ?? 0);
                    if ($clienteIdCfg <= 0) {
                        $estado = 'pendiente';
                        $detalle = 'Debes configurar cliente_id para asignar agente.';
                        break;
                    }
                    $db->prepare("UPDATE clientes SET agente_id = ? WHERE id = ?")->execute([$agenteId, $clienteIdCfg]);
                    $estado = 'exito';
                    $detalle = 'Agente #' . $agenteId . ' asignado a cliente #' . $clienteIdCfg . '.';
                }
                break;

            case 'mover_pipeline':
                $itemId = intval($config['pipeline_item_id'] ?? 0);
                $etapaId = intval($config['etapa_id'] ?? 0);
                if ($itemId <= 0 || $etapaId <= 0) {
                    $estado = 'pendiente';
                    $detalle = 'Debes configurar pipeline_item_id y etapa_id para esta accion.';
                    break;
                }
                $db->prepare("UPDATE pipeline_items SET etapa_id = ?, updated_at = NOW() WHERE id = ?")->execute([$etapaId, $itemId]);
                $estado = 'exito';
                $detalle = 'Pipeline item #' . $itemId . ' movido a etapa #' . $etapaId . '.';
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
        $successCount++;
    } elseif ($estado === 'error') {
        $errorCount++;
    } else {
        $pendingCount++;
    }

    $stmtLogAcc = $db->prepare("INSERT INTO automatizacion_log (automatizacion_id, accion_id, estado, detalles, entidad_tipo) VALUES (?, ?, ?, ?, 'manual')");
    $stmtLogAcc->execute([$id, intval($acc['id']), $estado, $detalle]);
}

$estadoFinal = 'pendiente';
if ($errorCount > 0) {
    $estadoFinal = 'error';
} elseif ($successCount > 0) {
    $estadoFinal = 'exito';
}

$resumen = 'Ejecucion manual por ' . currentUserName() . '. Exito: ' . $successCount . ', Error: ' . $errorCount . ', Pendiente: ' . $pendingCount . '.';
$db->prepare("INSERT INTO automatizacion_log (automatizacion_id, estado, detalles, entidad_tipo) VALUES (?, ?, ?, 'manual')")
    ->execute([$id, $estadoFinal, $resumen]);

// Contadores reales de ejecucion y fecha
$db->prepare("UPDATE automatizaciones SET ejecuciones = ejecuciones + 1, ultima_ejecucion = NOW() WHERE id = ?")
    ->execute([$id]);

registrarActividad('ejecutar', 'automatizacion', $id, $resumen);

if ($estadoFinal === 'exito') {
    setFlash('success', 'Automatizacion ejecutada. ' . $resumen);
} elseif ($estadoFinal === 'error') {
    setFlash('warning', 'Automatizacion ejecutada con errores. ' . $resumen);
} else {
    setFlash('info', 'Automatizacion ejecutada parcialmente. ' . $resumen);
}

header('Location: index.php');
exit;
