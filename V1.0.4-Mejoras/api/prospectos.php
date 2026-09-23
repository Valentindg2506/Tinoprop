<?php
/**
 * API para Prospectos - Operaciones AJAX
 * Soporta: edición inline, historial CRUD, tareas por día, próximo contacto
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$db = getDB();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function usuarioPuedeAccederProspecto($db, $prospectoId) {
    if (!$prospectoId) return false;
    if (isAdmin()) return true;
    $stmt = $db->prepare("SELECT agente_id FROM prospectos WHERE id = ? LIMIT 1");
    $stmt->execute([$prospectoId]);
    $row = $stmt->fetch();
    if (!$row) return false;
    return intval($row['agente_id']) === intval(currentUserId());
}

function usuarioPuedeEditarHistorial($db, $entradaId) {
    if (!$entradaId) return false;
    if (isAdmin()) return true;
    $stmt = $db->prepare("SELECT usuario_id FROM historial_prospectos WHERE id = ? LIMIT 1");
    $stmt->execute([$entradaId]);
    $row = $stmt->fetch();
    if (!$row) return false;
    return intval($row['usuario_id']) === intval(currentUserId());
}

// Verify CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
}

switch ($accion) {

    // ─────────────────────────────────────────────
    // EDICIÓN INLINE DE CAMPO
    // ─────────────────────────────────────────────
    case 'editar_campo':
        $id = intval($_POST['id'] ?? 0);
        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';

        if (!$id || !$campo) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }
        if (!usuarioPuedeAccederProspecto($db, $id)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos sobre el prospecto']);
            exit;
        }

        // Campos permitidos para edición inline
        $camposPermitidos = [
            'nombre', 'email', 'telefono', 'telefono2', 'etapa', 'estado', 'temperatura', 'prioridad',
            'tipo_propiedad', 'operacion', 'direccion', 'numero', 'piso_puerta', 'escalera', 'puerta', 'barrio',
            'localidad', 'provincia', 'comunidad_autonoma', 'codigo_postal',
            'precio_estimado', 'precio_propietario', 'precio_comunidad',
            'superficie', 'superficie_construida', 'superficie_util', 'superficie_parcela',
            'habitaciones', 'banos', 'aseos', 'planta',
            'ascensor', 'garaje_incluido', 'trastero_incluido', 'terraza', 'balcon', 'jardin', 'piscina', 'aire_acondicionado',
            'calefaccion', 'orientacion', 'antiguedad', 'estado_conservacion', 'certificacion_energetica', 'referencia_catastral',
            'enlace', 'descripcion', 'descripcion_interna', 'comision', 'exclusividad', 'notas', 'reformas', 'proxima_accion',
            'fecha_publicacion_propiedad', 'fecha_contacto', 'hora_contacto', 'mejor_horario_contacto',
            'fecha_proximo_contacto', 'agente_id'
        ];

        if (!in_array($campo, $camposPermitidos)) {
            echo json_encode(['success' => false, 'error' => 'Campo no permitido: ' . $campo]);
            exit;
        }
        if ($campo === 'agente_id' && !isAdmin()) {
            echo json_encode(['success' => false, 'error' => 'Solo un administrador puede reasignar el agente']);
            exit;
        }

        // Sanitizar y convertir según tipo
        $camposNumericos = ['precio_estimado', 'precio_propietario', 'precio_comunidad', 'superficie', 'superficie_construida', 'superficie_util', 'superficie_parcela', 'habitaciones', 'banos', 'aseos', 'antiguedad', 'comision'];
        $camposBoolean = ['exclusividad', 'ascensor', 'garaje_incluido', 'trastero_incluido', 'terraza', 'balcon', 'jardin', 'piscina', 'aire_acondicionado'];

        if (in_array($campo, $camposNumericos)) {
            $valor = $valor !== '' ? floatval(str_replace(',', '.', $valor)) : null;
        } elseif (in_array($campo, $camposBoolean)) {
            $valor = intval($valor) ? 1 : 0;
        } elseif ($campo === 'agente_id') {
            $valor = intval($valor);
        } elseif ($valor === '') {
            $valor = null;
        }

        try {
            $stmt = $db->prepare("UPDATE prospectos SET `$campo` = ? WHERE id = ?");
            $stmt->execute([$valor, $id]);

            // Auto-desactivar cuando se marca como descartado
            if ($campo === 'etapa' && $valor === 'descartado') {
                $db->prepare("UPDATE prospectos SET activo = 0 WHERE id = ?")->execute([$id]);
            }

            registrarActividad('editar_inline', 'prospecto', $id, "Campo: $campo");

            // Devolver valor formateado
            $valorFormateado = $valor;
            if (in_array($campo, ['precio_estimado', 'precio_propietario'])) {
                $valorFormateado = $valor ? number_format($valor, 2, ',', '.') . ' €' : '-';
            } elseif ($campo === 'superficie') {
                $valorFormateado = $valor ? number_format($valor, 2, ',', '.') . ' m²' : '-';
            } elseif ($campo === 'comision') {
                $valorFormateado = $valor ? $valor . '%' : '-';
            } elseif (in_array($campo, ['fecha_publicacion_propiedad', 'fecha_contacto', 'fecha_proximo_contacto'])) {
                $valorFormateado = $valor ? date('d/m/Y', strtotime($valor)) : '-';
            } elseif ($campo === 'hora_contacto') {
                $valorFormateado = $valor ? substr($valor, 0, 5) : '-';
            }

            echo json_encode(['success' => true, 'valor_formateado' => $valorFormateado]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // HISTORIAL DE CONTACTOS
    // ─────────────────────────────────────────────
    case 'add_historial':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $contenido = trim($_POST['contenido'] ?? '');
        $tipo = $_POST['tipo'] ?? 'nota';

        if (!$prospectoId || !$contenido) {
            echo json_encode(['success' => false, 'error' => 'Prospecto y contenido son obligatorios']);
            exit;
        }
        if (!usuarioPuedeAccederProspecto($db, $prospectoId)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos sobre el prospecto']);
            exit;
        }

        $tiposPermitidos = ['llamada', 'email', 'visita', 'nota', 'whatsapp', 'otro'];
        if (!in_array($tipo, $tiposPermitidos)) $tipo = 'nota';

        try {
            $stmt = $db->prepare("INSERT INTO historial_prospectos (prospecto_id, usuario_id, contenido, tipo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$prospectoId, currentUserId(), $contenido, $tipo]);
            $newId = $db->lastInsertId();

            // Obtener el registro recién creado con datos del usuario
            $stmt2 = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                                   FROM historial_prospectos h 
                                   LEFT JOIN usuarios u ON h.usuario_id = u.id 
                                   WHERE h.id = ?");
            $stmt2->execute([$newId]);
            $entrada = $stmt2->fetch();
            $fechaHistorial = $entrada['fecha_evento'] ?: $entrada['created_at'];

            registrarActividad('contacto', 'prospecto', $prospectoId, "[$tipo] $contenido");

            echo json_encode([
                'success' => true,
                'entrada' => [
                    'id' => $entrada['id'],
                    'contenido' => htmlspecialchars($entrada['contenido']),
                    'tipo' => $entrada['tipo'],
                    'usuario' => htmlspecialchars(($entrada['usuario_nombre'] ?? '') . ' ' . ($entrada['usuario_apellidos'] ?? '')),
                    'usuario_iniciales' => strtoupper(mb_substr($entrada['usuario_nombre'] ?? '', 0, 1) . mb_substr($entrada['usuario_apellidos'] ?? '', 0, 1)),
                    'fecha' => date('d/m/Y H:i', strtotime($fechaHistorial)),
                    'fecha_iso' => date('Y-m-d\\TH:i', strtotime($fechaHistorial)),
                    'fecha_relativa' => 'Ahora',
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'get_historial':
        $prospectoId = intval($_GET['prospecto_id'] ?? 0);
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        if (!usuarioPuedeAccederProspecto($db, $prospectoId)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos sobre el prospecto']);
            exit;
        }

        $stmt = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos 
                              FROM historial_prospectos h 
                              LEFT JOIN usuarios u ON h.usuario_id = u.id 
                              WHERE h.prospecto_id = ? 
                              ORDER BY COALESCE(h.fecha_evento, h.created_at) DESC 
                              LIMIT $perPage OFFSET $offset");
        $stmt->execute([$prospectoId]);
        $entradas = $stmt->fetchAll();

        $total = $db->prepare("SELECT COUNT(*) FROM historial_prospectos WHERE prospecto_id = ?");
        $total->execute([$prospectoId]);

        $result = [];
        foreach ($entradas as $e) {
            $result[] = [
                'id' => $e['id'],
                'contenido' => htmlspecialchars($e['contenido']),
                'tipo' => $e['tipo'],
                'usuario' => htmlspecialchars(($e['usuario_nombre'] ?? '') . ' ' . ($e['usuario_apellidos'] ?? '')),
                'usuario_iniciales' => strtoupper(mb_substr($e['usuario_nombre'] ?? '', 0, 1) . mb_substr($e['usuario_apellidos'] ?? '', 0, 1)),
                'fecha' => date('d/m/Y H:i', strtotime($e['fecha_evento'] ?: $e['created_at'])),
                'fecha_iso' => date('Y-m-d\\TH:i', strtotime($e['fecha_evento'] ?: $e['created_at'])),
            ];
        }

        echo json_encode(['success' => true, 'entradas' => $result, 'total' => $total->fetchColumn()]);
        break;

    case 'delete_historial':
        $entradaId = intval($_POST['entrada_id'] ?? 0);
        if (!$entradaId) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            exit;
        }
        try {
            $db->prepare("DELETE FROM historial_prospectos WHERE id = ? AND usuario_id = ?")->execute([$entradaId, currentUserId()]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'edit_historial_fecha':
        $entradaId = intval($_POST['entrada_id'] ?? 0);
        $fechaEvento = trim($_POST['fecha_evento'] ?? '');

        if (!$entradaId || !$fechaEvento) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }
        if (!usuarioPuedeEditarHistorial($db, $entradaId)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos para editar esta entrada']);
            exit;
        }

        $dt = DateTime::createFromFormat('Y-m-d\\TH:i', $fechaEvento);
        if (!$dt) {
            echo json_encode(['success' => false, 'error' => 'Formato de fecha/hora inválido']);
            exit;
        }

        try {
            $valorSql = $dt->format('Y-m-d H:i:s');
            $db->prepare("UPDATE historial_prospectos SET fecha_evento = ? WHERE id = ?")
               ->execute([$valorSql, $entradaId]);
            echo json_encode(['success' => true, 'fecha' => date('d/m/Y H:i', strtotime($valorSql)), 'fecha_iso' => $dt->format('Y-m-d\\TH:i')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // EVENTOS POR DÍA (mini calendario - todos los tipos)
    // ─────────────────────────────────────────────
    case 'tareas_dia':
        $prospectoId = intval($_GET['prospecto_id'] ?? 0);
        $fecha = preg_replace('/[^0-9\-]/', '', $_GET['fecha'] ?? date('Y-m-d'));
        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }
        $uid = currentUserId();
        $eventos = [];

        // Tareas
        try {
            $st = $db->prepare("SELECT t.titulo, t.estado, t.prioridad,
                                       DATE_FORMAT(t.fecha_vencimiento,'%H:%i') as hora,
                                       CASE t.prioridad WHEN 'urgente' THEN '#ef4444' WHEN 'alta' THEN '#f59e0b' ELSE '#8b5cf6' END as color
                                FROM tareas t
                                WHERE DATE(t.fecha_vencimiento) = ?
                                  AND (t.asignado_a = ? OR t.creado_por = ?)
                                ORDER BY t.fecha_vencimiento ASC");
            $st->execute([$fecha, $uid, $uid]);
            foreach ($st->fetchAll() as $r) {
                $eventos[] = ['tipo' => 'tarea', 'titulo' => $r['titulo'],
                              'hora' => $r['hora'] ?: '00:00', 'color' => $r['color'], 'estado' => $r['estado']];
            }
        } catch (Exception $e) {}

        // Eventos de calendario
        try {
            $st = $db->prepare("SELECT ce.titulo, ce.tipo, ce.todo_dia,
                                       DATE_FORMAT(ce.fecha_inicio,'%H:%i') as hora,
                                       COALESCE(ce.color,'#3b82f6') as color
                                FROM calendario_eventos ce
                                WHERE ce.usuario_id = ? AND DATE(ce.fecha_inicio) = ?
                                ORDER BY ce.fecha_inicio ASC");
            $st->execute([$uid, $fecha]);
            foreach ($st->fetchAll() as $r) {
                $eventos[] = ['tipo' => $r['tipo'], 'titulo' => $r['titulo'],
                              'hora' => $r['todo_dia'] ? '🕐 Todo el día' : ($r['hora'] ?: '00:00'),
                              'color' => $r['color'], 'estado' => null];
            }
        } catch (Exception $e) {}

        // Visitas
        try {
            $st = $db->prepare("SELECT CONCAT('Visita: ', COALESCE(c.nombre,'Sin cliente'),
                                       IF(p.referencia IS NOT NULL, CONCAT(' — ',p.referencia),'')) as titulo,
                                       DATE_FORMAT(v.hora,'%H:%i') as hora
                                FROM visitas v
                                LEFT JOIN clientes c ON v.cliente_id = c.id
                                LEFT JOIN propiedades p ON v.propiedad_id = p.id
                                WHERE v.agente_id = ? AND v.fecha = ?
                                ORDER BY v.hora ASC");
            $st->execute([$uid, $fecha]);
            foreach ($st->fetchAll() as $r) {
                $eventos[] = ['tipo' => 'visita', 'titulo' => $r['titulo'],
                              'hora' => $r['hora'] ?: '09:00', 'color' => '#10b981', 'estado' => null];
            }
        } catch (Exception $e) {}

        // Prospectos con próximo contacto ese día
        try {
            $esAdminApi = isAdmin();
            $whereAg = $esAdminApi ? '' : 'AND p.agente_id = ?';
            $paramsAg = $esAdminApi ? [$fecha] : [$fecha, $uid];
            $st = $db->prepare("SELECT p.nombre, DATE_FORMAT(p.hora_contacto,'%H:%i') as hora_c FROM prospectos p
                                WHERE p.activo = 1
                                  AND p.etapa NOT IN ('captado','descartado')
                                  AND p.fecha_proximo_contacto = ?
                                  $whereAg");
            $st->execute($paramsAg);
            foreach ($st->fetchAll() as $r) {
                $eventos[] = ['tipo' => 'llamada', 'titulo' => 'Contactar: ' . $r['nombre'],
                              'hora' => $r['hora_c'] ?: '09:00', 'color' => '#f59e0b', 'estado' => null];
            }
        } catch (Exception $e) {}

        usort($eventos, fn($a, $b) => strcmp($a['hora'], $b['hora']));
        echo json_encode(['success' => true, 'tareas' => $eventos, 'eventos' => $eventos, 'fecha' => $fecha]);
        break;

    // ─────────────────────────────────────────────
    // ACTUALIZAR PRÓXIMO CONTACTO
    // ─────────────────────────────────────────────
    case 'set_proximo_contacto':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        $horaRaw = $_POST['hora'] ?? '';
        $hora = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaRaw) ? $horaRaw : null;

        if (!$prospectoId || !$fecha) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }
        if (!usuarioPuedeAccederProspecto($db, $prospectoId)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos sobre el prospecto']);
            exit;
        }

        try {
            $db->prepare("UPDATE prospectos SET fecha_proximo_contacto = ?, hora_contacto = ? WHERE id = ?")
               ->execute([$fecha, $hora, $prospectoId]);
            $horaLabel = $hora ? ' ' . substr($hora, 0, 5) : '';
            registrarActividad('seguimiento', 'prospecto', $prospectoId, "Próximo contacto: $fecha$horaLabel");
            echo json_encode(['success' => true, 'fecha_formateada' => date('d/m/Y', strtotime($fecha))]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // EDITAR CAMPO PERSONALIZADO
    // ─────────────────────────────────────────────
    case 'editar_custom_field':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $fieldId = intval($_POST['field_id'] ?? 0);
        $valor = $_POST['valor'] ?? '';

        if (!$prospectoId || !$fieldId) {
            echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }
        if (!usuarioPuedeAccederProspecto($db, $prospectoId)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos sobre el prospecto']);
            exit;
        }

        try {
            $stmt = $db->prepare("INSERT INTO custom_field_values (field_id, entidad_id, valor) 
                                  VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
            $stmt->execute([$fieldId, $prospectoId, $valor]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─────────────────────────────────────────────
    // HISTORIAL DE PROPIEDAD
    // ─────────────────────────────────────────────
    case 'add_historial_propiedad':
        $prospectoId = intval($_POST['prospecto_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'otro';
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precioAnterior = $_POST['precio_anterior'] !== '' ? floatval(str_replace(['.', ','], ['', '.'], $_POST['precio_anterior'] ?? '')) : null;
        $precioNuevo = $_POST['precio_nuevo'] !== '' ? floatval(str_replace(['.', ','], ['', '.'], $_POST['precio_nuevo'] ?? '')) : null;

        if (!$prospectoId) {
            echo json_encode(['success' => false, 'error' => 'Prospecto requerido']);
            exit;
        }
        if (!usuarioPuedeAccederProspecto($db, $prospectoId)) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos']);
            exit;
        }

        $tiposValidos = ['subida_precio', 'bajada_precio', 'modificacion', 'publicacion', 'retirada', 'otro'];
        if (!in_array($tipo, $tiposValidos)) $tipo = 'otro';

        try {
            $stmt = $db->prepare("INSERT INTO historial_propiedad_prospecto
                (prospecto_id, usuario_id, tipo, descripcion, precio_anterior, precio_nuevo)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$prospectoId, currentUserId(), $tipo, $descripcion ?: null, $precioAnterior, $precioNuevo]);
            $newId = $db->lastInsertId();

            $stmt2 = $db->prepare("SELECT h.*, u.nombre as usuario_nombre, u.apellidos as usuario_apellidos
                                   FROM historial_propiedad_prospecto h
                                   LEFT JOIN usuarios u ON h.usuario_id = u.id
                                   WHERE h.id = ?");
            $stmt2->execute([$newId]);
            $entrada = $stmt2->fetch();

            echo json_encode([
                'success' => true,
                'entrada' => [
                    'id' => $entrada['id'],
                    'tipo' => $entrada['tipo'],
                    'descripcion' => htmlspecialchars($entrada['descripcion'] ?? ''),
                    'precio_anterior' => $entrada['precio_anterior'],
                    'precio_nuevo' => $entrada['precio_nuevo'],
                    'usuario' => htmlspecialchars(($entrada['usuario_nombre'] ?? '') . ' ' . ($entrada['usuario_apellidos'] ?? '')),
                    'usuario_iniciales' => strtoupper(mb_substr($entrada['usuario_nombre'] ?? '', 0, 1) . mb_substr($entrada['usuario_apellidos'] ?? '', 0, 1)),
                    'fecha' => date('d/m/Y H:i', strtotime($entrada['created_at'])),
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'delete_historial_propiedad':
        $entradaId = intval($_POST['entrada_id'] ?? 0);
        if (!$entradaId) {
            echo json_encode(['success' => false, 'error' => 'ID requerido']);
            exit;
        }
        try {
            if (isAdmin()) {
                $db->prepare("DELETE FROM historial_propiedad_prospecto WHERE id = ?")->execute([$entradaId]);
            } else {
                $db->prepare("DELETE FROM historial_propiedad_prospecto WHERE id = ? AND usuario_id = ?")->execute([$entradaId, currentUserId()]);
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no reconocida: ' . $accion]);
        break;
}
