<?php
/**
 * IA Tools Engine — Definiciones y ejecución de herramientas CRM
 *
 * Cada herramienta tiene:
 *   - Definición para la API de Anthropic (getToolDefinitions)
 *   - Función de ejecución (executeTool)
 *
 * Herramientas disponibles:
 *   Lectura:  consultar_prospectos, consultar_propiedades, consultar_clientes,
 *             consultar_pipelines, consultar_finanzas, consultar_tareas,
 *             consultar_actividad, analizar_kpis
 *   Escritura: calificar_prospecto, crear_tarea, crear_notificacion, actualizar_prospecto
 */

/**
 * Devuelve las definiciones de herramientas en formato Anthropic Tool Use
 */
function getToolDefinitions() {
    return [
        [
            'name' => 'consultar_prospectos',
            'description' => 'Busca y filtra prospectos (leads de propietarios) en el CRM. Puede filtrar por etapa, zona, temperatura, etc. Devuelve datos resumidos.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'etapa' => ['type' => 'string', 'description' => 'Filtrar por etapa: nuevo_lead, contactado, seguimiento, visita_programada, captado, descartado', 'enum' => ['nuevo_lead','contactado','seguimiento','visita_programada','captado','descartado','']],
                    'temperatura' => ['type' => 'string', 'description' => 'Filtrar por temperatura: frio, templado, caliente', 'enum' => ['frio','templado','caliente','']],
                    'localidad' => ['type' => 'string', 'description' => 'Filtrar por localidad/ciudad'],
                    'provincia' => ['type' => 'string', 'description' => 'Filtrar por provincia'],
                    'busqueda' => ['type' => 'string', 'description' => 'Búsqueda libre por nombre, teléfono, email o referencia'],
                    'activo' => ['type' => 'string', 'description' => 'Filtrar por activo: 1=solo activos, 0=inactivos, vacío=todos', 'enum' => ['1','0','']],
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de resultados (default 20, max 50)'],
                    'ordenar_por' => ['type' => 'string', 'description' => 'Ordenar por: created_at, fecha_proximo_contacto, precio_estimado', 'enum' => ['created_at','fecha_proximo_contacto','precio_estimado','']],
                    'contacto_hoy' => ['type' => 'boolean', 'description' => 'Si true, solo prospectos con contacto programado para hoy'],
                    'contacto_vencido' => ['type' => 'boolean', 'description' => 'Si true, solo prospectos con contacto vencido'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'consultar_propiedades',
            'description' => 'Busca propiedades inmobiliarias en el CRM. Puede filtrar por tipo, zona, precio, estado, etc.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'tipo' => ['type' => 'string', 'description' => 'Tipo de propiedad: piso, casa, chalet, adosado, atico, local, oficina, terreno, etc.'],
                    'operacion' => ['type' => 'string', 'description' => 'Tipo de operación: venta, alquiler', 'enum' => ['venta','alquiler','']],
                    'estado' => ['type' => 'string', 'description' => 'Estado: disponible, reservado, vendido, alquilado, retirado', 'enum' => ['disponible','reservado','vendido','alquilado','retirado','']],
                    'provincia' => ['type' => 'string', 'description' => 'Provincia'],
                    'localidad' => ['type' => 'string', 'description' => 'Localidad/ciudad'],
                    'precio_min' => ['type' => 'number', 'description' => 'Precio mínimo en euros'],
                    'precio_max' => ['type' => 'number', 'description' => 'Precio máximo en euros'],
                    'habitaciones_min' => ['type' => 'integer', 'description' => 'Mínimo de habitaciones'],
                    'busqueda' => ['type' => 'string', 'description' => 'Búsqueda libre por título, referencia, dirección'],
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de resultados (default 20, max 50)'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'consultar_clientes',
            'description' => 'Busca clientes (compradores, vendedores, inquilinos, inversores) en el CRM.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'tipo' => ['type' => 'string', 'description' => 'Tipo de cliente: comprador, vendedor, inquilino, propietario, inversor'],
                    'busqueda' => ['type' => 'string', 'description' => 'Búsqueda por nombre, email, teléfono'],
                    'provincia' => ['type' => 'string', 'description' => 'Provincia'],
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de resultados (default 20, max 50)'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'consultar_pipelines',
            'description' => 'Consulta el estado de los pipelines (embudos de ventas/Kanban). Muestra pipelines, etapas y sus items con valores.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'pipeline_id' => ['type' => 'integer', 'description' => 'ID de pipeline específico para ver detalle. Si vacío, lista todos.'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'consultar_finanzas',
            'description' => 'Consulta datos financieros: comisiones, ingresos, gastos. Puede filtrar por período y estado.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'periodo' => ['type' => 'string', 'description' => 'Período: hoy, semana, mes, trimestre, año, todo', 'enum' => ['hoy','semana','mes','trimestre','año','todo']],
                    'estado' => ['type' => 'string', 'description' => 'Estado: pendiente, cobrado, pagado, anulado', 'enum' => ['pendiente','cobrado','pagado','anulado','']],
                    'tipo' => ['type' => 'string', 'description' => 'Tipo: comision_venta, comision_alquiler, honorarios, gasto, ingreso_otro'],
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de registros detallados (default 20)'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'consultar_tareas',
            'description' => 'Consulta tareas del CRM. Puede filtrar por estado, prioridad, asignación.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'estado' => ['type' => 'string', 'description' => 'Estado: pendiente, en_progreso, completada, cancelada', 'enum' => ['pendiente','en_progreso','completada','cancelada','']],
                    'prioridad' => ['type' => 'string', 'description' => 'Prioridad: baja, media, alta, urgente', 'enum' => ['baja','media','alta','urgente','']],
                    'vencidas' => ['type' => 'boolean', 'description' => 'Si true, sólo tareas vencidas'],
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de resultados (default 20)'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'consultar_actividad',
            'description' => 'Consulta la actividad reciente del CRM (logins, creaciones, ediciones, etc.)',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'limite' => ['type' => 'integer', 'description' => 'Máximo de registros (default 20, max 50)'],
                    'entidad' => ['type' => 'string', 'description' => 'Filtrar por tipo de entidad: propiedad, cliente, prospecto, tarea, visita, etc.'],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'analizar_kpis',
            'description' => 'Calcula KPIs globales del negocio inmobiliario: totales, ratios, rendimiento por agente, tendencias.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'periodo' => ['type' => 'string', 'description' => 'Período para KPIs: mes, trimestre, año', 'enum' => ['mes','trimestre','año']],
                ],
                'required' => [],
            ],
        ],
        [
            'name' => 'calificar_prospecto',
            'description' => 'Cambia la temperatura (frio/templado/caliente) y/o la etapa de un prospecto. Usar después de analizar sus datos.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'prospecto_id' => ['type' => 'integer', 'description' => 'ID del prospecto a calificar'],
                    'temperatura' => ['type' => 'string', 'description' => 'Nueva temperatura: frio, templado, caliente', 'enum' => ['frio','templado','caliente']],
                    'etapa' => ['type' => 'string', 'description' => 'Nueva etapa (opcional)', 'enum' => ['nuevo_lead','contactado','seguimiento','visita_programada','captado','descartado','']],
                    'notas' => ['type' => 'string', 'description' => 'Notas/comentario sobre la calificación'],
                ],
                'required' => ['prospecto_id'],
            ],
        ],
        [
            'name' => 'crear_tarea',
            'description' => 'Crea una nueva tarea en el CRM asignada a un usuario.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'titulo' => ['type' => 'string', 'description' => 'Título de la tarea'],
                    'descripcion' => ['type' => 'string', 'description' => 'Descripción detallada'],
                    'tipo' => ['type' => 'string', 'description' => 'Tipo: llamada, email, reunion, visita, gestion, documentacion, otro', 'enum' => ['llamada','email','reunion','visita','gestion','documentacion','otro']],
                    'prioridad' => ['type' => 'string', 'description' => 'Prioridad: baja, media, alta, urgente', 'enum' => ['baja','media','alta','urgente']],
                    'dias_vencimiento' => ['type' => 'integer', 'description' => 'Días hasta vencimiento (default 1)'],
                ],
                'required' => ['titulo'],
            ],
        ],
        [
            'name' => 'crear_notificacion',
            'description' => 'Envía una notificación interna a un usuario del CRM.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'titulo' => ['type' => 'string', 'description' => 'Título de la notificación'],
                    'mensaje' => ['type' => 'string', 'description' => 'Contenido de la notificación'],
                ],
                'required' => ['titulo'],
            ],
        ],
        [
            'name' => 'actualizar_prospecto',
            'description' => 'Actualiza campos de un prospecto existente. Solo modifica los campos proporcionados.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'prospecto_id' => ['type' => 'integer', 'description' => 'ID del prospecto'],
                    'fecha_proximo_contacto' => ['type' => 'string', 'description' => 'Fecha próximo contacto (YYYY-MM-DD)'],
                    'hora_contacto' => ['type' => 'string', 'description' => 'Hora de contacto (HH:MM)'],
                    'notas' => ['type' => 'string', 'description' => 'Notas adicionales (se añaden a las existentes)'],
                    'descripcion_interna' => ['type' => 'string', 'description' => 'Descripción interna'],
                ],
                'required' => ['prospecto_id'],
            ],
        ],
        [
            'name' => 'enviar_email',
            'description' => 'Envía un email desde el CRM. IMPORTANTE: Antes de ejecutar esta herramienta, SIEMPRE describe al usuario qué email vas a enviar y espera confirmación.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'destinatario' => ['type' => 'string', 'description' => 'Email del destinatario'],
                    'asunto' => ['type' => 'string', 'description' => 'Asunto del email'],
                    'cuerpo' => ['type' => 'string', 'description' => 'Contenido del email (puede incluir HTML básico)'],
                ],
                'required' => ['destinatario', 'asunto', 'cuerpo'],
            ],
        ],
        [
            'name' => 'enviar_whatsapp',
            'description' => 'Envía un mensaje de WhatsApp a un número de teléfono via WhatsApp Cloud API. IMPORTANTE: Antes de ejecutar, SIEMPRE describe al usuario qué mensaje vas a enviar y espera confirmación. Requiere configuración de WhatsApp Business activa.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'telefono' => ['type' => 'string', 'description' => 'Número de teléfono en formato internacional (ej: 34612345678)'],
                    'mensaje' => ['type' => 'string', 'description' => 'Contenido del mensaje de texto'],
                ],
                'required' => ['telefono', 'mensaje'],
            ],
        ],
        [
            'name' => 'diagnosticar_app',
            'description' => 'Diagnostica problemas del CRM: verifica tablas de la BD, configuraciones, archivos, errores PHP recientes, y estado de módulos. Útil para ayudar con problemas técnicos de la aplicación.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'area' => ['type' => 'string', 'description' => 'Área a diagnosticar: tablas (verifica todas las tablas BD), config (configuraciones del CRM), errores (últimos errores del log), modulos (estado de módulos instalados), whatsapp (config de WhatsApp), email (config de email), todo (diagnóstico completo)', 'enum' => ['tablas','config','errores','modulos','whatsapp','email','todo']],
                ],
                'required' => ['area'],
            ],
        ],
    ];
}

/**
 * Ejecuta una herramienta y devuelve el resultado
 */
function executeTool($toolName, $input, $userId) {
    $db = getDB();

    switch ($toolName) {
        case 'consultar_prospectos':
            return toolConsultarProspectos($db, $input);
        case 'consultar_propiedades':
            return toolConsultarPropiedades($db, $input);
        case 'consultar_clientes':
            return toolConsultarClientes($db, $input);
        case 'consultar_pipelines':
            return toolConsultarPipelines($db, $input);
        case 'consultar_finanzas':
            return toolConsultarFinanzas($db, $input);
        case 'consultar_tareas':
            return toolConsultarTareas($db, $input, $userId);
        case 'consultar_actividad':
            return toolConsultarActividad($db, $input);
        case 'analizar_kpis':
            return toolAnalizarKPIs($db, $input);
        case 'calificar_prospecto':
            return toolCalificarProspecto($db, $input, $userId);
        case 'crear_tarea':
            return toolCrearTarea($db, $input, $userId);
        case 'crear_notificacion':
            return toolCrearNotificacion($db, $input, $userId);
        case 'actualizar_prospecto':
            return toolActualizarProspecto($db, $input, $userId);
        case 'enviar_email':
            return toolEnviarEmail($db, $input, $userId);
        case 'enviar_whatsapp':
            return toolEnviarWhatsapp($db, $input, $userId);
        case 'diagnosticar_app':
            return toolDiagnosticarApp($db, $input);
        default:
            return ['error' => "Herramienta '$toolName' no reconocida."];
    }
}

// ================================================================
// HERRAMIENTAS DE LECTURA
// ================================================================

function toolConsultarProspectos($db, $input) {
    $where = ['1=1'];
    $params = [];
    $limit = min((int)($input['limite'] ?? 20), 50);

    if (!empty($input['etapa'])) { $where[] = "p.etapa = ?"; $params[] = $input['etapa']; }
    if (!empty($input['temperatura'])) { $where[] = "p.temperatura = ?"; $params[] = $input['temperatura']; }
    if (!empty($input['localidad'])) { $where[] = "p.localidad LIKE ?"; $params[] = '%'.$input['localidad'].'%'; }
    if (!empty($input['provincia'])) { $where[] = "p.provincia LIKE ?"; $params[] = '%'.$input['provincia'].'%'; }
    if (!empty($input['busqueda'])) {
        $q = '%'.$input['busqueda'].'%';
        $where[] = "(p.nombre LIKE ? OR p.telefono LIKE ? OR p.email LIKE ? OR p.referencia LIKE ?)";
        $params = array_merge($params, [$q,$q,$q,$q]);
    }
    if (isset($input['activo']) && $input['activo'] !== '') { $where[] = "p.activo = ?"; $params[] = (int)$input['activo']; }
    if (!empty($input['contacto_hoy'])) { $where[] = "p.fecha_proximo_contacto = CURDATE()"; }
    if (!empty($input['contacto_vencido'])) { $where[] = "p.fecha_proximo_contacto < CURDATE()"; }

    $orderBy = 'p.created_at DESC';
    if (!empty($input['ordenar_por'])) {
        $allowed = ['created_at' => 'p.created_at DESC', 'fecha_proximo_contacto' => 'p.fecha_proximo_contacto ASC', 'precio_estimado' => 'p.precio_estimado DESC'];
        $orderBy = $allowed[$input['ordenar_por']] ?? $orderBy;
    }

    $whereStr = implode(' AND ', $where);

    // Conteo total
    $stmtC = $db->prepare("SELECT COUNT(*) FROM prospectos p WHERE $whereStr");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    // Datos
    $sql = "SELECT p.id, p.referencia, p.nombre, p.telefono, p.email, p.etapa, p.temperatura,
                   p.tipo_propiedad, p.direccion, p.barrio, p.localidad, p.provincia,
                   p.precio_estimado, p.superficie, p.habitaciones,
                   p.fecha_proximo_contacto, p.hora_contacto, p.notas, p.enlace,
                   p.activo, p.created_at, u.nombre AS agente_nombre
            FROM prospectos p
            LEFT JOIN usuarios u ON p.agente_id = u.id
            WHERE $whereStr ORDER BY $orderBy LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total_encontrados' => $total,
        'mostrando' => count($rows),
        'prospectos' => $rows,
    ];
}

function toolConsultarPropiedades($db, $input) {
    $where = ['1=1'];
    $params = [];
    $limit = min((int)($input['limite'] ?? 20), 50);

    if (!empty($input['tipo']))       { $where[] = "p.tipo = ?"; $params[] = $input['tipo']; }
    if (!empty($input['operacion']))  { $where[] = "p.operacion = ?"; $params[] = $input['operacion']; }
    if (!empty($input['estado']))     { $where[] = "p.estado = ?"; $params[] = $input['estado']; }
    if (!empty($input['provincia']))  { $where[] = "p.provincia LIKE ?"; $params[] = '%'.$input['provincia'].'%'; }
    if (!empty($input['localidad']))  { $where[] = "p.localidad LIKE ?"; $params[] = '%'.$input['localidad'].'%'; }
    if (!empty($input['precio_min'])) { $where[] = "p.precio >= ?"; $params[] = (float)$input['precio_min']; }
    if (!empty($input['precio_max'])) { $where[] = "p.precio <= ?"; $params[] = (float)$input['precio_max']; }
    if (!empty($input['habitaciones_min'])) { $where[] = "p.habitaciones >= ?"; $params[] = (int)$input['habitaciones_min']; }
    if (!empty($input['busqueda'])) {
        $q = '%'.$input['busqueda'].'%';
        $where[] = "(p.titulo LIKE ? OR p.referencia LIKE ? OR p.direccion LIKE ?)";
        $params = array_merge($params, [$q,$q,$q]);
    }

    $whereStr = implode(' AND ', $where);

    $stmtC = $db->prepare("SELECT COUNT(*) FROM propiedades p WHERE $whereStr");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    $sql = "SELECT p.id, p.referencia, p.titulo, p.tipo, p.operacion, p.estado, p.precio,
                   p.superficie_construida, p.habitaciones, p.banos, p.direccion,
                   p.localidad, p.provincia, p.created_at, u.nombre AS agente_nombre
            FROM propiedades p
            LEFT JOIN usuarios u ON p.agente_id = u.id
            WHERE $whereStr ORDER BY p.created_at DESC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['total_encontrados' => $total, 'mostrando' => min($limit, $total), 'propiedades' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function toolConsultarClientes($db, $input) {
    $where = ['1=1'];
    $params = [];
    $limit = min((int)($input['limite'] ?? 20), 50);

    if (!empty($input['tipo']))      { $where[] = "FIND_IN_SET(?, c.tipo) > 0"; $params[] = $input['tipo']; }
    if (!empty($input['provincia'])) { $where[] = "c.provincia LIKE ?"; $params[] = '%'.$input['provincia'].'%'; }
    if (!empty($input['busqueda'])) {
        $q = '%'.$input['busqueda'].'%';
        $where[] = "(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)";
        $params = array_merge($params, [$q,$q,$q]);
    }
    $whereStr = implode(' AND ', $where);

    $stmtC = $db->prepare("SELECT COUNT(*) FROM clientes c WHERE $whereStr");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();

    $sql = "SELECT c.id, c.nombre, c.apellidos, c.email, c.telefono, c.tipo, c.origen,
                   c.localidad, c.provincia, c.presupuesto_min, c.presupuesto_max,
                   c.zona_interes, c.activo, c.created_at
            FROM clientes c WHERE $whereStr ORDER BY c.created_at DESC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['total_encontrados' => $total, 'clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function toolConsultarPipelines($db, $input) {
    if (!empty($input['pipeline_id'])) {
        $pid = (int)$input['pipeline_id'];
        $pipeline = $db->prepare("SELECT * FROM pipelines WHERE id = ?");
        $pipeline->execute([$pid]);
        $p = $pipeline->fetch(PDO::FETCH_ASSOC);
        if (!$p) return ['error' => 'Pipeline no encontrado.'];

        $stmtE = $db->prepare("SELECT * FROM pipeline_etapas WHERE pipeline_id = ? ORDER BY orden ASC");
        $stmtE->execute([$pid]);
        $etapas = $stmtE->fetchAll(PDO::FETCH_ASSOC);

        $result = ['pipeline' => $p, 'etapas' => []];
        foreach ($etapas as $e) {
            $stmtI = $db->prepare("SELECT pi.*, pr.nombre AS prospecto_nombre, cl.nombre AS cliente_nombre, prop.titulo AS propiedad_titulo
                FROM pipeline_items pi
                LEFT JOIN prospectos pr ON pi.prospecto_id = pr.id
                LEFT JOIN clientes cl ON pi.cliente_id = cl.id
                LEFT JOIN propiedades prop ON pi.propiedad_id = prop.id
                WHERE pi.etapa_id = ? ORDER BY pi.created_at DESC LIMIT 20");
            $stmtI->execute([$e['id']]);
            $e['items'] = $stmtI->fetchAll(PDO::FETCH_ASSOC);
            $e['items_count'] = count($e['items']);
            $e['valor_total'] = array_sum(array_column($e['items'], 'valor'));
            $result['etapas'][] = $e;
        }
        return $result;
    }

    // Listar todos los pipelines con resumen
    $pipelines = $db->query("SELECT p.*, (SELECT COUNT(*) FROM pipeline_items pi WHERE pi.pipeline_id = p.id) AS items_total,
        (SELECT COALESCE(SUM(pi2.valor),0) FROM pipeline_items pi2 WHERE pi2.pipeline_id = p.id) AS valor_total
        FROM pipelines p WHERE p.activo = 1 ORDER BY p.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    return ['pipelines' => $pipelines, 'total' => count($pipelines)];
}

function toolConsultarFinanzas($db, $input) {
    $where = ['1=1'];
    $params = [];
    $limit = min((int)($input['limite'] ?? 20), 50);

    $periodo = $input['periodo'] ?? 'mes';
    switch ($periodo) {
        case 'hoy': $where[] = "f.fecha = CURDATE()"; break;
        case 'semana': $where[] = "f.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
        case 'mes': $where[] = "f.fecha >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"; break;
        case 'trimestre': $where[] = "f.fecha >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"; break;
        case 'año': $where[] = "f.fecha >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"; break;
    }

    if (!empty($input['estado'])) { $where[] = "f.estado = ?"; $params[] = $input['estado']; }
    if (!empty($input['tipo']))   { $where[] = "f.tipo = ?"; $params[] = $input['tipo']; }

    $whereStr = implode(' AND ', $where);

    // Resumen
    $stmtR = $db->prepare("SELECT
        COUNT(*) as total_registros,
        COALESCE(SUM(CASE WHEN f.tipo IN ('comision_venta','comision_alquiler','honorarios','ingreso_otro') THEN f.importe_total ELSE 0 END), 0) AS total_ingresos,
        COALESCE(SUM(CASE WHEN f.tipo = 'gasto' THEN f.importe_total ELSE 0 END), 0) AS total_gastos,
        COALESCE(SUM(CASE WHEN f.estado = 'pendiente' THEN f.importe_total ELSE 0 END), 0) AS pendiente_cobro,
        COALESCE(SUM(CASE WHEN f.estado = 'cobrado' THEN f.importe_total ELSE 0 END), 0) AS cobrado
        FROM finanzas f WHERE $whereStr");
    $stmtR->execute($params);
    $resumen = $stmtR->fetch(PDO::FETCH_ASSOC);

    $sql = "SELECT f.id, f.tipo, f.concepto, f.importe, f.iva, f.importe_total, f.fecha, f.estado,
                   u.nombre AS agente_nombre
            FROM finanzas f LEFT JOIN usuarios u ON f.agente_id = u.id
            WHERE $whereStr ORDER BY f.fecha DESC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['periodo' => $periodo, 'resumen' => $resumen, 'registros' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function toolConsultarTareas($db, $input, $userId) {
    $where = ['1=1'];
    $params = [];
    $limit = min((int)($input['limite'] ?? 20), 50);

    if (!empty($input['estado']))    { $where[] = "t.estado = ?"; $params[] = $input['estado']; }
    if (!empty($input['prioridad'])) { $where[] = "t.prioridad = ?"; $params[] = $input['prioridad']; }
    if (!empty($input['vencidas']))  { $where[] = "t.fecha_vencimiento < NOW() AND t.estado NOT IN ('completada','cancelada')"; }

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT t.id, t.titulo, t.tipo, t.prioridad, t.estado, t.fecha_vencimiento, t.fecha_completada,
                   u.nombre AS asignado_a_nombre
            FROM tareas t
            LEFT JOIN usuarios u ON t.asignado_a = u.id
            WHERE $whereStr ORDER BY t.fecha_vencimiento ASC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['tareas' => $rows, 'total' => count($rows)];
}

function toolConsultarActividad($db, $input) {
    $limit = min((int)($input['limite'] ?? 20), 50);
    $where = ['1=1'];
    $params = [];

    if (!empty($input['entidad'])) { $where[] = "a.entidad = ?"; $params[] = $input['entidad']; }

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT a.id, a.accion, a.entidad, a.entidad_id, a.detalles, a.created_at, u.nombre AS usuario
            FROM actividad_log a LEFT JOIN usuarios u ON a.usuario_id = u.id
            WHERE $whereStr ORDER BY a.created_at DESC LIMIT $limit";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['actividad' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function toolAnalizarKPIs($db, $input) {
    $periodo = $input['periodo'] ?? 'mes';
    $dateFilter = '';
    switch ($periodo) {
        case 'mes': $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"; break;
        case 'trimestre': $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)"; break;
        case 'año': $dateFilter = "AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"; break;
    }
    $dateFiltFinanzas = str_replace('created_at', 'fecha', $dateFilter);

    $kpis = [];

    // Prospectos
    $kpis['prospectos'] = [
        'total_activos' => (int)$db->query("SELECT COUNT(*) FROM prospectos WHERE activo = 1")->fetchColumn(),
        'nuevos_periodo' => (int)$db->query("SELECT COUNT(*) FROM prospectos WHERE 1=1 $dateFilter")->fetchColumn(),
        'por_etapa' => $db->query("SELECT etapa, COUNT(*) as total FROM prospectos WHERE activo = 1 GROUP BY etapa ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC),
        'por_temperatura' => $db->query("SELECT temperatura, COUNT(*) as total FROM prospectos WHERE activo = 1 AND temperatura IS NOT NULL GROUP BY temperatura")->fetchAll(PDO::FETCH_ASSOC),
        'contactos_vencidos' => (int)$db->query("SELECT COUNT(*) FROM prospectos WHERE activo = 1 AND fecha_proximo_contacto < CURDATE() AND etapa NOT IN ('captado','descartado')")->fetchColumn(),
        'contactos_hoy' => (int)$db->query("SELECT COUNT(*) FROM prospectos WHERE activo = 1 AND fecha_proximo_contacto = CURDATE()")->fetchColumn(),
    ];

    // Propiedades
    $kpis['propiedades'] = [
        'total' => (int)$db->query("SELECT COUNT(*) FROM propiedades")->fetchColumn(),
        'disponibles' => (int)$db->query("SELECT COUNT(*) FROM propiedades WHERE estado = 'disponible'")->fetchColumn(),
        'vendidas_periodo' => (int)$db->query("SELECT COUNT(*) FROM propiedades WHERE estado = 'vendido' $dateFilter")->fetchColumn(),
        'por_estado' => $db->query("SELECT estado, COUNT(*) as total FROM propiedades GROUP BY estado")->fetchAll(PDO::FETCH_ASSOC),
        'precio_medio' => (float)$db->query("SELECT COALESCE(AVG(precio),0) FROM propiedades WHERE estado = 'disponible'")->fetchColumn(),
    ];

    // Finanzas
    $kpis['finanzas'] = [
        'ingresos_periodo' => (float)$db->query("SELECT COALESCE(SUM(importe_total),0) FROM finanzas WHERE tipo != 'gasto' AND estado = 'cobrado' $dateFiltFinanzas")->fetchColumn(),
        'gastos_periodo' => (float)$db->query("SELECT COALESCE(SUM(importe_total),0) FROM finanzas WHERE tipo = 'gasto' $dateFiltFinanzas")->fetchColumn(),
        'pendiente_cobro' => (float)$db->query("SELECT COALESCE(SUM(importe_total),0) FROM finanzas WHERE estado = 'pendiente'")->fetchColumn(),
    ];

    // Tareas
    $kpis['tareas'] = [
        'pendientes' => (int)$db->query("SELECT COUNT(*) FROM tareas WHERE estado = 'pendiente'")->fetchColumn(),
        'vencidas' => (int)$db->query("SELECT COUNT(*) FROM tareas WHERE estado NOT IN ('completada','cancelada') AND fecha_vencimiento < NOW()")->fetchColumn(),
        'completadas_periodo' => (int)$db->query("SELECT COUNT(*) FROM tareas WHERE estado = 'completada' " . str_replace('created_at','fecha_completada',$dateFilter))->fetchColumn(),
    ];

    // Rendimiento agentes
    $kpis['agentes'] = $db->query("SELECT u.id, u.nombre,
        (SELECT COUNT(*) FROM prospectos WHERE agente_id = u.id AND activo = 1) as prospectos_activos,
        (SELECT COUNT(*) FROM propiedades WHERE agente_id = u.id AND estado = 'disponible') as propiedades_activas,
        (SELECT COUNT(*) FROM tareas WHERE asignado_a = u.id AND estado = 'pendiente') as tareas_pendientes
        FROM usuarios u WHERE u.activo = 1 ORDER BY prospectos_activos DESC")->fetchAll(PDO::FETCH_ASSOC);

    $kpis['periodo'] = $periodo;
    return $kpis;
}

// ================================================================
// HERRAMIENTAS DE ESCRITURA
// ================================================================

function toolCalificarProspecto($db, $input, $userId) {
    $id = (int)($input['prospecto_id'] ?? 0);
    if ($id <= 0) return ['error' => 'prospecto_id requerido.'];

    $stmt = $db->prepare("SELECT id, nombre, etapa, temperatura FROM prospectos WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) return ['error' => "Prospecto #$id no encontrado."];

    $updates = [];
    $params = [];
    $cambios = [];

    if (!empty($input['temperatura'])) {
        $updates[] = "temperatura = ?"; $params[] = $input['temperatura'];
        $cambios[] = "temperatura: {$p['temperatura']} → {$input['temperatura']}";
    }
    if (!empty($input['etapa'])) {
        $updates[] = "etapa = ?"; $params[] = $input['etapa'];
        $cambios[] = "etapa: {$p['etapa']} → {$input['etapa']}";
    }
    if (!empty($input['notas'])) {
        $existingNotas = '';
        $stmtN = $db->prepare("SELECT notas FROM prospectos WHERE id = ?");
        $stmtN->execute([$id]);
        $existingNotas = $stmtN->fetchColumn() ?: '';
        $nuevaNota = "[IA " . date('d/m/Y H:i') . "] " . $input['notas'];
        $updates[] = "notas = ?";
        $params[] = $existingNotas ? $existingNotas . "\n" . $nuevaNota : $nuevaNota;
    }

    if (empty($updates)) return ['error' => 'Debe proporcionar temperatura o etapa a cambiar.'];

    $params[] = $id;
    $db->prepare("UPDATE prospectos SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

    return [
        'exito' => true,
        'prospecto' => $p['nombre'],
        'cambios' => $cambios,
        'mensaje' => "Prospecto #{$id} ({$p['nombre']}) calificado correctamente.",
    ];
}

function toolCrearTarea($db, $input, $userId) {
    $titulo = trim($input['titulo'] ?? '');
    if (!$titulo) return ['error' => 'Título de tarea requerido.'];

    $tipo = $input['tipo'] ?? 'otro';
    $prioridad = $input['prioridad'] ?? 'media';
    $descripcion = $input['descripcion'] ?? '';
    $diasVencimiento = (int)($input['dias_vencimiento'] ?? 1);
    $fechaVenc = date('Y-m-d H:i:s', strtotime("+{$diasVencimiento} days"));

    $stmt = $db->prepare("INSERT INTO tareas (titulo, descripcion, tipo, prioridad, estado, fecha_vencimiento, asignado_a, creado_por) VALUES (?, ?, ?, ?, 'pendiente', ?, ?, ?)");
    $stmt->execute([$titulo, $descripcion, $tipo, $prioridad, $fechaVenc, $userId, $userId]);
    $tareaId = (int)$db->lastInsertId();

    return [
        'exito' => true,
        'tarea_id' => $tareaId,
        'mensaje' => "Tarea #{$tareaId} '$titulo' creada con prioridad $prioridad, vence el " . date('d/m/Y', strtotime($fechaVenc)) . ".",
    ];
}

function toolCrearNotificacion($db, $input, $userId) {
    $titulo = trim($input['titulo'] ?? '');
    if (!$titulo) return ['error' => 'Título de notificación requerido.'];

    $mensaje = $input['mensaje'] ?? '';
    $stmt = $db->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leida) VALUES (?, ?, ?, 'info', 0)");
    $stmt->execute([$userId, $titulo, $mensaje]);

    return ['exito' => true, 'mensaje' => "Notificación creada: '$titulo'"];
}

function toolActualizarProspecto($db, $input, $userId) {
    $id = (int)($input['prospecto_id'] ?? 0);
    if ($id <= 0) return ['error' => 'prospecto_id requerido.'];

    $stmt = $db->prepare("SELECT id, nombre FROM prospectos WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) return ['error' => "Prospecto #$id no encontrado."];

    $updates = [];
    $params = [];
    $cambios = [];

    if (!empty($input['fecha_proximo_contacto'])) {
        $updates[] = "fecha_proximo_contacto = ?"; $params[] = $input['fecha_proximo_contacto'];
        $cambios[] = "próximo contacto → {$input['fecha_proximo_contacto']}";
    }
    if (!empty($input['hora_contacto'])) {
        $updates[] = "hora_contacto = ?"; $params[] = $input['hora_contacto'];
        $cambios[] = "hora contacto → {$input['hora_contacto']}";
    }
    if (!empty($input['descripcion_interna'])) {
        $updates[] = "descripcion_interna = ?"; $params[] = $input['descripcion_interna'];
        $cambios[] = "descripción interna actualizada";
    }
    if (!empty($input['notas'])) {
        $existingNotas = $db->prepare("SELECT notas FROM prospectos WHERE id = ?");
        $existingNotas->execute([$id]);
        $old = $existingNotas->fetchColumn() ?: '';
        $nuevaNota = "[IA " . date('d/m/Y H:i') . "] " . $input['notas'];
        $updates[] = "notas = ?";
        $params[] = $old ? $old . "\n" . $nuevaNota : $nuevaNota;
        $cambios[] = "notas añadidas";
    }

    if (empty($updates)) return ['error' => 'Debe proporcionar al menos un campo a actualizar.'];

    $params[] = $id;
    $db->prepare("UPDATE prospectos SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

    return ['exito' => true, 'prospecto' => $p['nombre'], 'cambios' => $cambios];
}

// ================================================================
// COMUNICACIÓN: Email + WhatsApp
// ================================================================

function toolEnviarEmail($db, $input, $userId) {
    $dest = trim($input['destinatario'] ?? '');
    $asunto = trim($input['asunto'] ?? '');
    $cuerpo = trim($input['cuerpo'] ?? '');

    if (!$dest || !filter_var($dest, FILTER_VALIDATE_EMAIL)) return ['error' => 'Email destinatario inválido.'];
    if (!$asunto) return ['error' => 'Asunto requerido.'];
    if (!$cuerpo) return ['error' => 'Cuerpo del email requerido.'];

    // Use the CRM's email system
    require_once __DIR__ . '/../../includes/email.php';
    $ok = enviarEmail($dest, $asunto, nl2br(htmlspecialchars($cuerpo)), true);

    if ($ok) {
        return ['exito' => true, 'mensaje' => "Email enviado correctamente a $dest con asunto: '$asunto'"];
    } else {
        $err = function_exists('getLastEmailError') ? getLastEmailError() : 'Error desconocido';
        return ['error' => "No se pudo enviar el email a $dest. Error: $err"];
    }
}

function toolEnviarWhatsapp($db, $input, $userId) {
    $telefono = preg_replace('/[^0-9]/', '', trim($input['telefono'] ?? ''));
    $mensaje = trim($input['mensaje'] ?? '');

    if (!$telefono || strlen($telefono) < 9) return ['error' => 'Número de teléfono inválido.'];
    if (!$mensaje) return ['error' => 'Mensaje requerido.'];

    $sid = getenv('TWILIO_ACCOUNT_SID') ?: '';
    $token = getenv('TWILIO_AUTH_TOKEN') ?: '';
    $from = getenv('TWILIO_WHATSAPP_FROM') ?: '';

    // If not in env, check database config (fallback)
    if (!$sid || !$token || !$from) {
        try {
            $stmt = $db->prepare("SELECT account_sid, auth_token, phone_number FROM twilio_config WHERE activo = 1 ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $twCfg = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($twCfg) {
                $sid = $sid ?: $twCfg['account_sid'];
                $token = $token ?: $twCfg['auth_token'];
                $from = $from ?: $twCfg['phone_number'];
            }
        } catch (Throwable $e) {
            // table might not exist, ignore
        }
    }

    if (!$sid || !$token || !$from) {
        return ['error' => 'Twilio WhatsApp no está configurado. Configura las variables TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN y TWILIO_WHATSAPP_FROM en el .env.'];
    }
    
    // Ensure "whatsapp:" prefix
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

    if ($curlErr) return ['error' => "Error de conexión con Twilio API: $curlErr"];

    $resp = json_decode($respBody, true);
    if ($httpCode >= 200 && $httpCode < 300 && !empty($resp['sid'])) {
        // Log the message in whatsapp_mensajes
        try {
            $db->prepare("INSERT INTO whatsapp_mensajes (telefono, direccion, mensaje, tipo, wa_message_id, estado, created_by) VALUES (?, 'saliente', ?, 'text', ?, 'enviado', ?)")
               ->execute([$telefono, $mensaje, $resp['sid'], $userId]);
        } catch (Throwable $e) { /* non-critical */ }

        return ['exito' => true, 'mensaje' => "WhatsApp enviado a $telefono (SID: {$resp['sid']})"];
    }

    $errMsg = $resp['message'] ?? "HTTP $httpCode sin detalle";
    return ['error' => "Error Twilio API: $errMsg"];
}

// ================================================================
// DIAGNÓSTICO: Modo Agente
// ================================================================

function toolDiagnosticarApp($db, $input) {
    $area = $input['area'] ?? 'todo';
    $result = [];

    // ----- TABLAS -----
    if ($area === 'tablas' || $area === 'todo') {
        $expectedTables = [
            'usuarios', 'propiedades', 'propiedad_fotos', 'clientes', 'visitas',
            'tareas', 'documentos', 'finanzas', 'portales', 'propiedad_portales',
            'actividad_log', 'notificaciones', 'prospectos',
            'pipelines', 'pipeline_etapas', 'pipeline_items',
            'whatsapp_config', 'whatsapp_mensajes',
            'ia_config', 'ia_conversaciones', 'ia_mensajes', 'ia_acciones_log',
            'automatizaciones', 'automatizacion_acciones', 'automatizacion_log',
            'secuencia_captacion_plantillas', 'secuencia_captacion_tracking',
            'email_cuentas', 'email_plantillas',
        ];

        $existingTables = [];
        $rows = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $t) $existingTables[] = $t;

        $tablas = [];
        foreach ($expectedTables as $t) {
            $exists = in_array($t, $existingTables);
            $count = null;
            if ($exists) {
                try { $count = (int)$db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
                catch (Throwable $e) { $count = 'error'; }
            }
            $tablas[$t] = ['exists' => $exists, 'rows' => $count];
        }

        $otherTables = array_diff($existingTables, $expectedTables);
        $result['tablas'] = [
            'esperadas' => count($expectedTables),
            'encontradas' => count(array_filter($tablas, fn($t) => $t['exists'])),
            'faltantes' => array_keys(array_filter($tablas, fn($t) => !$t['exists'])),
            'detalle' => $tablas,
            'otras_tablas' => array_values($otherTables),
        ];
    }

    // ----- CONFIG -----
    if ($area === 'config' || $area === 'todo') {
        $result['config'] = [
            'app_name' => defined('APP_NAME') ? APP_NAME : 'NO DEFINIDO',
            'app_url' => defined('APP_URL') ? APP_URL : 'NO DEFINIDO',
            'app_env' => defined('APP_ENV') ? APP_ENV : 'NO DEFINIDO',
            'db_host' => defined('DB_HOST') ? DB_HOST : 'NO DEFINIDO',
            'db_name' => defined('DB_NAME') ? DB_NAME : 'NO DEFINIDO',
            'upload_dir' => defined('UPLOAD_DIR') ? UPLOAD_DIR : 'NO DEFINIDO',
            'upload_dir_writable' => defined('UPLOAD_DIR') ? is_writable(UPLOAD_DIR) : false,
            'log_dir' => defined('LOG_DIR') ? LOG_DIR : 'NO DEFINIDO',
            'log_dir_writable' => defined('LOG_DIR') ? is_writable(LOG_DIR) : false,
            'php_version' => PHP_VERSION,
            'curl_available' => function_exists('curl_init'),
            'gd_available' => extension_loaded('gd'),
            'timezone' => date_default_timezone_get(),
        ];
    }

    // ----- ERRORES -----
    if ($area === 'errores' || $area === 'todo') {
        $errores = [];
        $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../../logs/';
        $logFile = $logDir . 'error_' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            $lines = array_filter(array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -20));
            $errores = $lines;
        }
        $result['errores'] = [
            'log_file' => $logFile,
            'log_exists' => file_exists($logFile),
            'ultimas_lineas' => $errores,
        ];
    }

    // ----- MÓDULOS -----
    if ($area === 'modulos' || $area === 'todo') {
        $modulesDir = __DIR__ . '/../../modules/';
        $modulos = [];
        if (is_dir($modulesDir)) {
            $dirs = array_filter(scandir($modulesDir), fn($d) => $d !== '.' && $d !== '..' && is_dir($modulesDir . $d));
            foreach ($dirs as $d) {
                $indexExists = file_exists($modulesDir . $d . '/index.php');
                $modulos[$d] = ['installed' => $indexExists];
            }
        }
        $result['modulos'] = $modulos;
    }

    // ----- WHATSAPP -----
    if ($area === 'whatsapp' || $area === 'todo') {
        try {
            $wa = $db->query("SELECT id, activo, phone_number_id, modo, created_at FROM whatsapp_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $result['whatsapp'] = $wa ?: ['status' => 'No configurado'];
            if ($wa) {
                $result['whatsapp']['has_access_token'] = !empty($db->query("SELECT access_token FROM whatsapp_config ORDER BY id DESC LIMIT 1")->fetchColumn());
                $result['whatsapp']['mensajes_total'] = (int)$db->query("SELECT COUNT(*) FROM whatsapp_mensajes")->fetchColumn();
                $result['whatsapp']['mensajes_hoy'] = (int)$db->query("SELECT COUNT(*) FROM whatsapp_mensajes WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            }
        } catch (Throwable $e) {
            $result['whatsapp'] = ['error' => $e->getMessage()];
        }
    }

    // ----- EMAIL -----
    if ($area === 'email' || $area === 'todo') {
        $result['email'] = [
            'method' => defined('MAIL_METHOD') ? MAIL_METHOD : 'NO DEFINIDO',
            'smtp_host' => defined('SMTP_HOST') ? (SMTP_HOST ?: '(vacío)') : 'NO DEFINIDO',
            'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 'NO DEFINIDO',
            'from' => defined('SMTP_FROM') ? SMTP_FROM : 'NO DEFINIDO',
        ];
        try {
            $cuentas = $db->query("SELECT id, email, smtp_host, activo FROM email_cuentas ORDER BY id ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            $result['email']['cuentas_bd'] = $cuentas;
        } catch (Throwable $e) {
            $result['email']['cuentas_bd'] = 'Tabla email_cuentas no existe';
        }
    }

    return $result;
}

