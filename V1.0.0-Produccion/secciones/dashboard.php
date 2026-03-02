<?php
/* Seccion: Dashboard CRM
   Descripcion: Resumen general con filtros y datos reales
*/
/*
 * Sección: Dashboard
 * Rol: mostrar KPIs, actividad, embudo y recordatorios próximos.
 * Incluye: filtros persistentes por usuario y guardado de orden de widgets por POST.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

// Formatea minutos transcurridos como texto legible ("hace 5 min", "hace 2h", etc.).
function tiempo_relativo(int $minutos): string
{
    if ($minutos < 1) return 'ahora';
    if ($minutos < 60) return 'hace ' . $minutos . ' min';
    $horas = (int) ($minutos / 60);
    if ($horas < 24) return 'hace ' . $horas . 'h';
    $dias = (int) ($horas / 24);
    if ($dias < 30) return 'hace ' . $dias . 'd';
    return 'hace +30d';
}

// Catálogo de valores permitidos para filtros del dashboard.
$pdo = db();
$equipos_validos = ['Todos', 'Vendedor', 'Comprador'];
$periodos_validos = ['Mes actual', 'Ultimos 90 dias', 'Ano'];
$operaciones_validas = ['Venta y Alquiler', 'Venta', 'Alquiler'];

// Endpoint POST interno: guarda o resetea el orden visual de KPIs y paneles.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_orden_accion'])) {
    csrf_verify();
    header('Content-Type: application/json; charset=utf-8');

    $accion = $_POST['dashboard_orden_accion'] ?? '';

    if ($accion === 'guardar') {
        $kpis = trim($_POST['kpis'] ?? '[]');
        $panels = trim($_POST['panels'] ?? '[]');

        // Decodifica arrays JSON enviados desde frontend para el orden de widgets.
        $kpis_arr = json_decode($kpis, true);
        $panels_arr = json_decode($panels, true);

        if (!is_array($kpis_arr) || !is_array($panels_arr)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Formato de orden invalido.']);
            exit;
        }

        // Valida formato de cada identificador de tarjeta para evitar entradas inválidas.
        $valida = static function (array $items): bool {
            foreach ($items as $item) {
                if (!is_string($item) || !preg_match('/^[a-z0-9\-]+$/', $item)) {
                    return false;
                }
            }

            return true;
        };

        if (!$valida($kpis_arr) || !$valida($panels_arr)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Items de orden invalidos.']);
            exit;
        }

        // Persiste el orden por usuario en preferencias.
        preferencias_usuario_set($pdo, 'dashboard.kpis.order', json_encode(array_values($kpis_arr)));
        preferencias_usuario_set($pdo, 'dashboard.panels.order', json_encode(array_values($panels_arr)));

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'reset') {
        // Elimina preferencias guardadas para restaurar orden por defecto.
        preferencias_usuario_delete($pdo, 'dashboard.kpis.order');
        preferencias_usuario_delete($pdo, 'dashboard.panels.order');

        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => 'Accion no valida.']);
    exit;
}

// Carga valores por defecto de filtros desde preferencias del usuario.
$pref_equipo = preferencias_usuario_get($pdo, 'dashboard.filtro.equipo') ?? 'Todos';
if (!in_array($pref_equipo, $equipos_validos, true)) {
    $pref_equipo = 'Todos';
}
$pref_periodo = preferencias_usuario_get($pdo, 'dashboard.filtro.periodo') ?? 'Mes actual';
if (!in_array($pref_periodo, $periodos_validos, true)) {
    $pref_periodo = 'Mes actual';
}
$pref_operacion = preferencias_usuario_get($pdo, 'dashboard.filtro.operacion') ?? 'Venta y Alquiler';
if (!in_array($pref_operacion, $operaciones_validas, true)) {
    $pref_operacion = 'Venta y Alquiler';
}

// Si no llega filtro por URL, se usa el preferido guardado.
$filtro_equipo = $_GET['equipo'] ?? $pref_equipo;
$filtro_periodo = $_GET['periodo'] ?? $pref_periodo;
$filtro_operacion = $_GET['operacion'] ?? $pref_operacion;

if (!in_array($filtro_equipo, $equipos_validos, true)) {
    $filtro_equipo = 'Todos';
}
if (!in_array($filtro_periodo, $periodos_validos, true)) {
    $filtro_periodo = 'Mes actual';
}
if (!in_array($filtro_operacion, $operaciones_validas, true)) {
    $filtro_operacion = 'Venta y Alquiler';
}

// Mapeo de filtros de UI a valores usados en la base de datos.
$equipo_db = null;
if ($filtro_equipo === 'Vendedor') {
    $equipo_db = 'vendedor';
} elseif ($filtro_equipo === 'Comprador') {
    $equipo_db = 'comprador';
}

$operacion_db = null;
if ($filtro_operacion === 'Venta') {
    $operacion_db = 'venta';
} elseif ($filtro_operacion === 'Alquiler') {
    $operacion_db = 'alquiler';
}

// Fecha mínima de referencia según periodo seleccionado.
$desde = null;
if ($filtro_periodo === 'Mes actual') {
    $desde = date('Y-m-01');
} elseif ($filtro_periodo === 'Ultimos 90 dias') {
    $desde = date('Y-m-d', strtotime('-90 days'));
} elseif ($filtro_periodo === 'Ano') {
    $desde = date('Y-01-01');
}

// Condiciones dinámicas para reutilizar en consultas de KPIs.
$condicion_equipo_clientes = $equipo_db ? ' AND tipo = :equipo' : '';
$condicion_equipo_prop = $equipo_db ? ' AND equipo = :equipo' : '';
$condicion_fecha = $desde ? ' AND created_at >= :desde' : '';
$condicion_iid = sql_iid();
$iid_params = sql_iid_params();
$condicion_uid = sql_uid();
$uid_params = sql_uid_params();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE 1=1' . $condicion_equipo_clientes . $condicion_fecha . $condicion_iid . $condicion_uid);
$params = [] + $iid_params + $uid_params;
if ($equipo_db) {
    $params['equipo'] = $equipo_db;
}
if ($desde) {
    $params['desde'] = $desde;
}

// KPI 1: clientes activos bajo filtros.
$stmt->execute($params);
$clientes_activos = (int) $stmt->fetchColumn();

// KPI 2: total de prospectos bajo filtros.
$stmt = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE 1=1' . $condicion_equipo_clientes . $condicion_fecha . $condicion_iid . $condicion_uid);
$stmt->execute($params);
$prospectos_total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM propiedades WHERE operacion = :operacion' . $condicion_equipo_prop . $condicion_fecha . $condicion_iid . $condicion_uid
);

// KPI 3/4: propiedades de venta y alquiler bajo filtros.
$params_prop = ['operacion' => 'venta'] + $iid_params + $uid_params;
if ($equipo_db) {
    $params_prop['equipo'] = $equipo_db;
}
if ($desde) {
    $params_prop['desde'] = $desde;
}
$stmt->execute($params_prop);
$propiedades_venta = (int) $stmt->fetchColumn();

$params_prop['operacion'] = 'alquiler';
$stmt->execute($params_prop);
$propiedades_alquiler = (int) $stmt->fetchColumn();

// KPI 5: visitas pendientes de hoy.
visitas_asegurar_tabla($pdo);
$usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM visitas WHERE fecha_visita = CURDATE() AND estado = "pendiente" AND usuario_id = :uid' . $condicion_iid);
$stmt->execute(['uid' => $usuario_id] + $iid_params);
$visitas_pendientes_hoy = (int) $stmt->fetchColumn();

// KPI 6: propiedades reservadas bajo filtros.
$sql_res = 'SELECT COUNT(*) FROM propiedades WHERE estado = "Reservado"' . $condicion_equipo_prop . $condicion_iid . $condicion_uid;
$params_res = [] + $iid_params + $uid_params;
if ($equipo_db) { $params_res['equipo'] = $equipo_db; }
$stmt = $pdo->prepare($sql_res);
$stmt->execute($params_res);
$propiedades_reservadas = (int) $stmt->fetchColumn();

// Estructura final de KPIs con iconos descriptivos.
// Calcular periodo anterior para comparación
$desde_anterior = null;
if ($filtro_periodo === 'Mes actual') {
    $desde_anterior = date('Y-m-01', strtotime('-1 month'));
    $hasta_anterior = date('Y-m-t', strtotime('-1 month'));
} elseif ($filtro_periodo === 'Ultimos 90 dias') {
    $desde_anterior = date('Y-m-d', strtotime('-180 days'));
    $hasta_anterior = date('Y-m-d', strtotime('-91 days'));
} elseif ($filtro_periodo === 'Ano') {
    $desde_anterior = date('Y-01-01', strtotime('-1 year'));
    $hasta_anterior = date('Y-12-31', strtotime('-1 year'));
}

// KPIs periodo anterior
$prev_clientes = 0; $prev_prospectos = 0; $prev_venta = 0; $prev_alquiler = 0;
if ($desde_anterior) {
    $cond_prev = ' AND created_at >= :desde_ant AND created_at <= :hasta_ant';
    $p_ant = [] + $iid_params + $uid_params;
    if ($equipo_db) $p_ant['equipo'] = $equipo_db;
    $p_ant['desde_ant'] = $desde_anterior;
    $p_ant['hasta_ant'] = $hasta_anterior;

    $st = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE 1=1' . $condicion_equipo_clientes . $cond_prev . $condicion_iid . $condicion_uid);
    $st->execute($p_ant); $prev_clientes = (int) $st->fetchColumn();

    $st = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE 1=1' . $condicion_equipo_clientes . $cond_prev . $condicion_iid . $condicion_uid);
    $st->execute($p_ant); $prev_prospectos = (int) $st->fetchColumn();

    $p_ant2 = $p_ant; $p_ant2['operacion'] = 'venta';
    $st = $pdo->prepare('SELECT COUNT(*) FROM propiedades WHERE operacion = :operacion' . $condicion_equipo_prop . $cond_prev . $condicion_iid . $condicion_uid);
    $st->execute($p_ant2); $prev_venta = (int) $st->fetchColumn();

    $p_ant2['operacion'] = 'alquiler';
    $st->execute($p_ant2); $prev_alquiler = (int) $st->fetchColumn();
}

// Calcular % cambio
$calc_cambio = function(int $actual, int $anterior): ?int {
    if ($anterior === 0) return $actual > 0 ? 100 : null;
    return (int) round((($actual - $anterior) / $anterior) * 100);
};

$kpis = [
    ["titulo" => "Clientes activos", "valor" => (string) $clientes_activos, "detalle" => "Filtro: " . $filtro_equipo, "icono" => "👥", "cambio" => $calc_cambio($clientes_activos, $prev_clientes)],
    ["titulo" => "Prospectos", "valor" => (string) $prospectos_total, "detalle" => "Periodo: " . $filtro_periodo, "icono" => "📊", "cambio" => $calc_cambio($prospectos_total, $prev_prospectos)],
    ["titulo" => "Prop. venta", "valor" => (string) $propiedades_venta, "detalle" => "Operacion: Venta", "icono" => "🏠", "cambio" => $calc_cambio($propiedades_venta, $prev_venta)],
    ["titulo" => "Prop. alquiler", "valor" => (string) $propiedades_alquiler, "detalle" => "Operacion: Alquiler", "icono" => "🔑", "cambio" => $calc_cambio($propiedades_alquiler, $prev_alquiler)],
    ["titulo" => "Visitas hoy", "valor" => (string) $visitas_pendientes_hoy, "detalle" => "Pendientes para hoy", "icono" => "📋", "cambio" => null],
    ["titulo" => "Reservadas", "valor" => (string) $propiedades_reservadas, "detalle" => "En proceso de cierre", "icono" => "🔒", "cambio" => null],
];

$stmt = $pdo->prepare(
    'SELECT estado, COUNT(*) AS total FROM prospectos WHERE 1=1' . $condicion_equipo_clientes . $condicion_fecha . $condicion_iid . $condicion_uid . ' GROUP BY estado'
);
$stmt->execute($params);
$prospectos_por_estado = $stmt->fetchAll();

$mapa_estados = [
    'Nuevo'       => ['nuevo'],
    'Contacto'    => ['contactado', 'no_contesta'],
    'Cerrado'     => ['realizado'],
    'Descartado'  => ['descartado'],
];

// Construcción de embudo comercial agregando estados del pipeline.
$conteo_estados = [];
foreach ($prospectos_por_estado as $fila) {
    $conteo_estados[$fila['estado']] = (int) $fila['total'];
}

$total_embudo = array_sum($conteo_estados);
if ($total_embudo === 0) {
    $total_embudo = 1;
}

$embudo = [];
$colores = [
    'Nuevo'       => '#3498db',
    'Contacto'    => '#f39c12',
    'Cerrado'     => '#2ecc71',
    'Descartado'  => '#e74c3c',
];
foreach ($mapa_estados as $etapa => $estados) {
    $suma = 0;
    foreach ($estados as $estado) {
        $suma += $conteo_estados[$estado] ?? 0;
    }
    $valor = (int) round(($suma / $total_embudo) * 100);
    $embudo[] = [
        'etapa' => $etapa,
        'valor' => $valor,
        'color' => $colores[$etapa]
    ];
}

// Alertas: avisos con contexto de entidad (cliente/propiedad) y tiempo relativo.
$stmt = $pdo->prepare(
    "SELECT n.tipo AS nota_tipo, n.texto, n.entity_type, n.entity_id,
            DATE_FORMAT(n.created_at, '%H:%i') AS hora,
            TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) AS minutos_hace,
            COALESCE(c.nombre, p.titulo) AS entity_nombre,
            c.apellido AS entity_apellido
     FROM notas n
     LEFT JOIN clientes c ON n.entity_type = 'cliente' AND n.entity_id = c.id
     LEFT JOIN propiedades p ON n.entity_type = 'propiedad' AND n.entity_id = p.id
     WHERE n.tipo = 'Aviso'" . sql_iid('n') . "
     ORDER BY n.created_at DESC LIMIT 5"
);
$stmt->execute(sql_iid_params());
$alertas = $stmt->fetchAll();

// Actividad reciente: últimas notas/avisos con contexto y tiempo relativo.
$stmt = $pdo->prepare(
    "SELECT n.tipo AS nota_tipo, n.texto, n.entity_type, n.entity_id,
            DATE_FORMAT(n.created_at, '%H:%i') AS hora,
            TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) AS minutos_hace,
            COALESCE(c.nombre, p.titulo) AS entity_nombre,
            c.apellido AS entity_apellido
     FROM notas n
     LEFT JOIN clientes c ON n.entity_type = 'cliente' AND n.entity_id = c.id
     LEFT JOIN propiedades p ON n.entity_type = 'propiedad' AND n.entity_id = p.id
     WHERE 1=1" . sql_iid('n') . "
     ORDER BY n.created_at DESC LIMIT 5"
);
$stmt->execute(sql_iid_params());
$actividad = $stmt->fetchAll();

// Panel de propiedades destacadas (top por visitas reales).
visitas_asegurar_tabla($pdo);
ofertas_asegurar_tabla($pdo);
$query_destacadas = 'SELECT p.id, p.titulo, p.operacion, p.equipo, p.precio, p.moneda, p.periodo, p.estado,
    (SELECT COUNT(*) FROM visitas v WHERE v.propiedad_id = p.id) AS visitas_real,
    (SELECT COUNT(*) FROM ofertas o WHERE o.propiedad_id = p.id) AS ofertas_real
    FROM propiedades p WHERE 1=1' . sql_iid('p') . sql_uid('p');
$params_destacadas = [] + $iid_params + $uid_params;
if ($operacion_db) {
    $query_destacadas .= ' AND p.operacion = :operacion';
    $params_destacadas['operacion'] = $operacion_db;
}
if ($equipo_db) {
    $query_destacadas .= ' AND p.equipo = :equipo';
    $params_destacadas['equipo'] = $equipo_db;
}
$query_destacadas .= ' ORDER BY visitas_real DESC LIMIT 3';
$stmt = $pdo->prepare($query_destacadas);
$stmt->execute($params_destacadas);
$destacadas = $stmt->fetchAll();

// Widget: próximos recordatorios del usuario en los siguientes 7 días.
recordatorios_asegurar_tabla($pdo);
$usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
$fecha_hoy = date('Y-m-d');
$fecha_fin = date('Y-m-d', strtotime('+7 days'));

$stmt = $pdo->prepare(
    'SELECT id, tipo, descripcion, fecha_recordatorio, hora_recordatorio, estado
     FROM recordatorios
     WHERE usuario_id = :usuario_id
     AND fecha_recordatorio >= :fecha_hoy
     AND fecha_recordatorio <= :fecha_fin
     AND estado != "cancelado"' . $condicion_iid . '
     ORDER BY fecha_recordatorio ASC, hora_recordatorio ASC
     LIMIT 5'
);
$stmt->execute([
    'usuario_id' => $usuario_id,
    'fecha_hoy' => $fecha_hoy,
    'fecha_fin' => $fecha_fin,
] + $iid_params);
$proximos_recordatorios = $stmt->fetchAll();

// Panel de visitas programadas para hoy.
$stmt = $pdo->prepare(
    'SELECT v.id, v.hora_visita, v.estado, v.observaciones, v.equipo,
            p.titulo AS propiedad_titulo, p.referencia AS propiedad_ref,
            c.nombre AS cliente_nombre, c.apellido AS cliente_apellido
     FROM visitas v
     LEFT JOIN propiedades p ON v.propiedad_id = p.id
     LEFT JOIN clientes c ON v.cliente_id = c.id
     WHERE v.fecha_visita = CURDATE() AND v.usuario_id = :uid' . sql_iid('v') . '
     ORDER BY v.hora_visita ASC'
);
$stmt->execute(['uid' => $usuario_id] + $iid_params);
$visitas_hoy_detalle = $stmt->fetchAll();

// Orden guardado por usuario para restaurar distribución de tarjetas en frontend.
$orden_kpis_guardado = preferencias_usuario_get($pdo, 'dashboard.kpis.order') ?? '[]';
$orden_panels_guardado = preferencias_usuario_get($pdo, 'dashboard.panels.order') ?? '[]';
// Panel Supervisor: rendimiento por agente (visible si nivel >= supervisor)
// Usa LEFT JOIN + GROUP BY en lugar de subconsultas correlativas para evitar N+1.
$panel_supervisor = [];
if (tiene_nivel('supervisor')) {
    $desde_sv = $desde ?? date('Y-m-01');
    $sql_agentes = 'SELECT u.id, u.nombre, u.email, u.rol,
        COALESCE(c_cnt.total, 0)  AS total_clientes,
        COALESCE(p_cnt.total, 0)  AS total_propiedades,
        COALESCE(pr_cnt.total, 0) AS total_prospectos,
        COALESCE(v_cnt.total, 0)  AS visitas_periodo,
        COALESCE(o_cnt.total, 0)  AS total_ofertas
        FROM usuarios u
        LEFT JOIN (SELECT usuario_id, COUNT(*) AS total FROM clientes WHERE 1=1' . sql_iid() . ' GROUP BY usuario_id) c_cnt ON c_cnt.usuario_id = u.id
        LEFT JOIN (SELECT usuario_id, COUNT(*) AS total FROM propiedades WHERE 1=1' . sql_iid() . ' GROUP BY usuario_id) p_cnt ON p_cnt.usuario_id = u.id
        LEFT JOIN (SELECT usuario_id, COUNT(*) AS total FROM prospectos WHERE 1=1' . sql_iid() . ' GROUP BY usuario_id) pr_cnt ON pr_cnt.usuario_id = u.id
        LEFT JOIN (SELECT usuario_id, COUNT(*) AS total FROM visitas WHERE fecha_visita >= :desde_sv' . sql_iid() . ' GROUP BY usuario_id) v_cnt ON v_cnt.usuario_id = u.id
        LEFT JOIN (SELECT usuario_id, COUNT(*) AS total FROM ofertas WHERE 1=1' . sql_iid() . ' GROUP BY usuario_id) o_cnt ON o_cnt.usuario_id = u.id
        WHERE u.rol IN ("agente","agente_vendedor","agente_comprador")' . sql_iid('u') . '
        ORDER BY visitas_periodo DESC';
    $params_sv = ['desde_sv' => $desde_sv] + sql_iid_params();
    $stmt_sv = $pdo->prepare($sql_agentes);
    $stmt_sv->execute($params_sv);
    $panel_supervisor = $stmt_sv->fetchAll(PDO::FETCH_ASSOC);
}

// Panel Jefe: informe general de la empresa (visible si nivel >= jefe)
$panel_jefe = [];
if (tiene_nivel('jefe')) {
    // Total usuarios activos
    $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE activo = 1' . sql_iid());
    $st->execute(sql_iid_params());
    $jefe_total_usuarios = (int) $st->fetchColumn();

    // Valor total en cartera (propiedades activas)
    $st = $pdo->prepare('SELECT COALESCE(SUM(precio), 0) FROM propiedades WHERE estado = "Disponible"' . sql_iid());
    $st->execute(sql_iid_params());
    $jefe_valor_cartera = (float) $st->fetchColumn();

    // Valor en reservas
    $st = $pdo->prepare('SELECT COALESCE(SUM(precio), 0) FROM propiedades WHERE estado = "Reservado"' . sql_iid());
    $st->execute(sql_iid_params());
    $jefe_valor_reservas = (float) $st->fetchColumn();

    // Tasa de conversión: prospectos realizados / total prospectos
    $st = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE estado = "realizado"' . sql_iid());
    $st->execute(sql_iid_params());
    $prosp_realizados = (int) $st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE 1=1' . sql_iid());
    $st->execute(sql_iid_params());
    $prosp_totales_jefe = (int) $st->fetchColumn();
    $jefe_tasa_conversion = $prosp_totales_jefe > 0 ? round(($prosp_realizados / $prosp_totales_jefe) * 100, 1) : 0;

    // Mejor agente del periodo
    $st = $pdo->prepare('SELECT u.nombre, COUNT(v.id) AS total FROM visitas v INNER JOIN usuarios u ON v.usuario_id = u.id WHERE v.fecha_visita >= :desde_jf' . sql_iid('v') . ' GROUP BY v.usuario_id ORDER BY total DESC LIMIT 1');
    $st->execute(['desde_jf' => $desde ?? date('Y-m-01')] + sql_iid_params());
    $jefe_mejor_agente = $st->fetch(PDO::FETCH_ASSOC);

    $panel_jefe = [
        'total_usuarios' => $jefe_total_usuarios,
        'valor_cartera' => $jefe_valor_cartera,
        'valor_reservas' => $jefe_valor_reservas,
        'tasa_conversion' => $jefe_tasa_conversion,
        'mejor_agente' => $jefe_mejor_agente,
    ];
}
?>

<div class="encabezado_seccion">
    <div class="acciones_dashboard">
        <button type="button" id="btnEditarOrdenDashboard" class="btn_guardar">Editar orden</button>
        <button type="button" id="btnResetOrdenDashboard" class="btn_secundario">Restablecer orden</button>
        <span id="estadoOrdenDashboard" class="estado_orden" aria-live="polite"></span>
    </div>
</div>

<form class="barra_filtros" method="GET">
    <input type="hidden" name="seccion" value="dashboard">

    <div class="filtro_item">
        <label for="equipo">Equipo</label>
        <select id="equipo" name="equipo">
            <option value="Todos" <?php echo $filtro_equipo === 'Todos' ? 'selected' : ''; ?>>Todos</option>
            <option value="Vendedor" <?php echo $filtro_equipo === 'Vendedor' ? 'selected' : ''; ?>>Vendedor</option>
            <option value="Comprador" <?php echo $filtro_equipo === 'Comprador' ? 'selected' : ''; ?>>Comprador</option>
        </select>
    </div>

    <div class="filtro_item">
        <label for="periodo">Periodo</label>
        <select id="periodo" name="periodo">
            <option value="Mes actual" <?php echo $filtro_periodo === 'Mes actual' ? 'selected' : ''; ?>>Mes actual</option>
            <option value="Ultimos 90 dias" <?php echo $filtro_periodo === 'Ultimos 90 dias' ? 'selected' : ''; ?>>Ultimos 90 dias</option>
            <option value="Ano" <?php echo $filtro_periodo === 'Ano' ? 'selected' : ''; ?>>Ano</option>
        </select>
    </div>

    <div class="filtro_item">
        <label for="operacion">Operacion</label>
        <select id="operacion" name="operacion">
            <option value="Venta y Alquiler" <?php echo $filtro_operacion === 'Venta y Alquiler' ? 'selected' : ''; ?>>Venta y Alquiler</option>
            <option value="Venta" <?php echo $filtro_operacion === 'Venta' ? 'selected' : ''; ?>>Solo Venta</option>
            <option value="Alquiler" <?php echo $filtro_operacion === 'Alquiler' ? 'selected' : ''; ?>>Solo Alquiler</option>
        </select>
    </div>

    <button class="btn_guardar" type="submit">Aplicar filtros</button>
</form>

<div class="dashboard_kpis">
    <?php foreach ($kpis as $kpi): ?>
        <div class="kpi_card" data-dashboard-card="kpi-<?php echo e(strtolower(str_replace([' ', '.'], '-', $kpi['titulo']))); ?>" draggable="false">
            <div class="kpi_header_row">
                <span class="kpi_icon"><?php echo $kpi['icono']; ?></span>
                <span class="kpi_title"><?php echo e($kpi['titulo']); ?></span>
            </div>
            <strong class="kpi_value"><?php echo e($kpi['valor']); ?></strong>
            <?php if (isset($kpi['cambio']) && $kpi['cambio'] !== null): ?>
                <span class="kpi_cambio kpi_cambio--<?php echo $kpi['cambio'] >= 0 ? 'up' : 'down'; ?>">
                    <?php echo $kpi['cambio'] >= 0 ? '▲' : '▼'; ?> <?php echo abs($kpi['cambio']); ?>% vs anterior
                </span>
            <?php endif; ?>
            <span class="kpi_detail"><?php echo e($kpi['detalle']); ?></span>
        </div>
    <?php endforeach; ?>
</div>

<script>
window.tinoPrefDashboard = {
    kpis: <?php echo $orden_kpis_guardado; ?>,
    panels: <?php echo $orden_panels_guardado; ?>
};
</script>

<!-- Gráficos Chart.js -->
<?php
// Datos para gráficos
$embudo_labels = array_map(fn($e) => $e['etapa'], $embudo);
$embudo_valores = array_map(fn($e) => $e['valor'], $embudo);
$embudo_colores = array_map(fn($e) => $e['color'], $embudo);

// Visitas por estado (respeta filtros del dashboard)
$sql_vis_chart = 'SELECT estado, COUNT(*) AS total FROM visitas WHERE usuario_id = :uid' . $condicion_iid;
$p_vis = ['uid' => $usuario_id] + $iid_params;
if ($equipo_db) { $sql_vis_chart .= ' AND equipo = :equipo'; $p_vis['equipo'] = $equipo_db; }
if ($desde) { $sql_vis_chart .= ' AND fecha_visita >= :desde'; $p_vis['desde'] = $desde; }
$sql_vis_chart .= ' GROUP BY estado';
$stmt_vis_chart = $pdo->prepare($sql_vis_chart);
$stmt_vis_chart->execute($p_vis);
$visitas_chart = $stmt_vis_chart->fetchAll();
$vis_labels = array_column($visitas_chart, 'estado');
$vis_valores = array_map('intval', array_column($visitas_chart, 'total'));

// Ofertas por estado (respeta filtros del dashboard — JOIN propiedades para equipo)
ofertas_asegurar_tabla($pdo);
$sql_of_chart = 'SELECT o.estado, COUNT(*) AS total FROM ofertas o';
$p_of = ['uid' => $usuario_id] + $iid_params;
if ($equipo_db) {
    $sql_of_chart .= ' INNER JOIN propiedades p ON o.propiedad_id = p.id';
}
$sql_of_chart .= ' WHERE o.usuario_id = :uid' . sql_iid('o');
if ($equipo_db) { $sql_of_chart .= ' AND p.equipo = :equipo'; $p_of['equipo'] = $equipo_db; }
if ($desde) { $sql_of_chart .= ' AND o.fecha_oferta >= :desde'; $p_of['desde'] = $desde; }
$sql_of_chart .= ' GROUP BY o.estado';
$stmt_of_chart = $pdo->prepare($sql_of_chart);
$stmt_of_chart->execute($p_of);
$ofertas_chart = $stmt_of_chart->fetchAll();
$of_labels = array_column($ofertas_chart, 'estado');
$of_valores = array_map('intval', array_column($ofertas_chart, 'total'));

// Propiedades por estado (respeta filtros del dashboard)
$sql_prop_chart = 'SELECT estado, COUNT(*) AS total FROM propiedades WHERE 1=1' . $condicion_iid . $condicion_uid;
$p_prop = [] + $iid_params + $uid_params;
if ($equipo_db) { $sql_prop_chart .= ' AND equipo = :equipo'; $p_prop['equipo'] = $equipo_db; }
if ($operacion_db) { $sql_prop_chart .= ' AND operacion = :operacion'; $p_prop['operacion'] = $operacion_db; }
if ($desde) { $sql_prop_chart .= ' AND created_at >= :desde'; $p_prop['desde'] = $desde; }
$sql_prop_chart .= ' GROUP BY estado';
$stmt_prop_chart = $pdo->prepare($sql_prop_chart);
$stmt_prop_chart->execute($p_prop);
$prop_chart = $stmt_prop_chart->fetchAll();
$prop_labels = array_column($prop_chart, 'estado');
$prop_valores = array_map('intval', array_column($prop_chart, 'total'));
?>

<div class="graficos_grid">
    <div class="grafico_contenedor" data-dashboard-card="chart-embudo" draggable="false">
        <div class="grafico_titulo">Embudo Comercial</div>
        <?php if (array_sum($embudo_valores) > 0): ?>
            <canvas id="chartEmbudo"></canvas>
        <?php else: ?>
            <p class="sin_datos_texto">No hay prospectos en este periodo</p>
        <?php endif; ?>
    </div>
    <div class="grafico_contenedor" data-dashboard-card="chart-visitas" draggable="false">
        <div class="grafico_titulo">Visitas por Estado</div>
        <?php if (!empty($vis_valores) && array_sum($vis_valores) > 0): ?>
            <canvas id="chartVisitas"></canvas>
        <?php else: ?>
            <p class="sin_datos_texto">No hay visitas en este periodo</p>
        <?php endif; ?>
    </div>
    <div class="grafico_contenedor" data-dashboard-card="chart-ofertas" draggable="false">
        <div class="grafico_titulo">Ofertas por Estado</div>
        <?php if (!empty($of_valores) && array_sum($of_valores) > 0): ?>
            <canvas id="chartOfertas"></canvas>
        <?php else: ?>
            <p class="sin_datos_texto">No hay ofertas en este periodo</p>
        <?php endif; ?>
    </div>
    <div class="grafico_contenedor" data-dashboard-card="chart-propiedades" draggable="false">
        <div class="grafico_titulo">Propiedades por Estado</div>
        <?php if (!empty($prop_valores) && array_sum($prop_valores) > 0): ?>
            <canvas id="chartPropiedades"></canvas>
        <?php else: ?>
            <p class="sin_datos_texto">No hay propiedades en este periodo</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') return;

    const isDark = document.body.classList.contains('tema-oscuro');
    const textColor = isDark ? '#e2e8f0' : '#0f172a';
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    Chart.defaults.color = textColor;

    const paletaColores = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#8b5cf6', '#ec4899', '#14b8a6'];

    // Embudo
    const elEmbudo = document.getElementById('chartEmbudo');
    if (elEmbudo) {
        new Chart(elEmbudo, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($embudo_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($embudo_valores); ?>,
                    backgroundColor: <?php echo json_encode($embudo_colores); ?>,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, max: 100, ticks: { callback: v => v + '%' } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // Visitas
    const elVisitas = document.getElementById('chartVisitas');
    if (elVisitas) {
        new Chart(elVisitas, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($vis_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($vis_valores); ?>,
                    backgroundColor: paletaColores.slice(0, <?php echo count($vis_labels); ?>),
                    borderWidth: 2,
                    borderColor: isDark ? '#131c2e' : '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 12 } } }
            }
        });
    }

    // Ofertas
    const elOfertas = document.getElementById('chartOfertas');
    if (elOfertas) {
        new Chart(elOfertas, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($of_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($of_valores); ?>,
                    backgroundColor: paletaColores.slice(0, <?php echo count($of_labels); ?>),
                    borderWidth: 2,
                    borderColor: isDark ? '#131c2e' : '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 12 } } }
            }
        });
    }

    // Propiedades por estado
    const elPropiedades = document.getElementById('chartPropiedades');
    if (elPropiedades) {
        new Chart(elPropiedades, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($prop_labels); ?>,
                datasets: [{
                    label: 'Propiedades',
                    data: <?php echo json_encode($prop_valores); ?>,
                    backgroundColor: paletaColores.slice(0, <?php echo count($prop_labels); ?>),
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: gridColor }, beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
});
</script>

<div class="dashboard_grid">
    <section class="panel_panel" data-dashboard-card="panel-embudo" draggable="false">
        <div class="panel_header">
            <h3>Embudo comercial</h3>
            <span class="panel_hint"><?php echo e($filtro_periodo); ?> · <?php echo e($filtro_operacion); ?></span>
        </div>

        <div class="embudo_lista">
            <?php foreach ($embudo as $fila): ?>
                <div class="embudo_item">
                    <span class="embudo_label"><?php echo e($fila['etapa']); ?></span>
                    <div class="embudo_barra">
                        <span style="width: <?php echo $fila['valor']; ?>%; background: <?php echo $fila['color']; ?>;"></span>
                    </div>
                    <span class="embudo_valor"><?php echo e((string) $fila['valor']); ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-alertas" draggable="false">
        <div class="panel_header">
            <h3>⚠️ Últimos avisos</h3>
            <span class="panel_hint">Notas de seguimiento</span>
        </div>

        <?php if (empty($alertas)): ?>
            <p class="sin_datos_texto">No hay avisos recientes</p>
        <?php else: ?>
        <ul class="lista_alertas">
            <?php foreach ($alertas as $alerta): ?>
                <li>
                    <div class="alerta_cabecera">
                        <span class="alerta_origen">
                            <?php echo $alerta['entity_type'] === 'propiedad' ? '🏠' : '👤'; ?>
                            <strong>
                                <?php echo e($alerta['entity_nombre'] ?? 'Sin asignar'); ?>
                                <?php if (!empty($alerta['entity_apellido'])): ?>
                                    <?php echo e($alerta['entity_apellido']); ?>
                                <?php endif; ?>
                            </strong>
                        </span>
                        <span class="alerta_tiempo"><?php echo tiempo_relativo((int) $alerta['minutos_hace']); ?></span>
                    </div>
                    <span class="alerta_texto"><?php echo e($alerta['texto']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-actividad" draggable="false">
        <div class="panel_header">
            <h3>📝 Actividad reciente</h3>
            <span class="panel_hint">Últimas notas y avisos</span>
        </div>

        <?php if (empty($actividad)): ?>
            <p class="sin_datos_texto">No hay actividad reciente</p>
        <?php else: ?>
        <ul class="lista_actividad">
            <?php foreach ($actividad as $item): ?>
                <li>
                    <span class="actividad_icono"><?php echo $item['entity_type'] === 'propiedad' ? '🏠' : '👤'; ?></span>
                    <div class="actividad_contenido">
                        <span class="actividad_entity">
                            <?php echo e($item['entity_nombre'] ?? ''); ?>
                            <?php if (!empty($item['entity_apellido'])): ?>
                                <?php echo e($item['entity_apellido']); ?>
                            <?php endif; ?>
                        </span>
                        <span class="actividad_texto"><?php echo e($item['texto']); ?></span>
                    </div>
                    <span class="actividad_hora"><?php echo tiempo_relativo((int) $item['minutos_hace']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-destacadas" draggable="false">
        <div class="panel_header">
            <h3>🏆 Propiedades destacadas</h3>
            <span class="panel_hint">Top por visitas</span>
        </div>

        <?php if (empty($destacadas)): ?>
            <p class="sin_datos_texto">No hay propiedades con visitas</p>
        <?php else: ?>
        <div class="mini_cards">
            <?php foreach ($destacadas as $propiedad): ?>
                <?php $origen = obtener_origen_propiedad($propiedad['operacion'], $propiedad['equipo']); ?>
                <div class="mini_card">
                    <div class="mini_card_top">
                        <strong><?php echo e($propiedad['titulo']); ?></strong>
                        <span class="badge_estado badge_estado--<?php echo e(map_estado_clase($propiedad['estado'])); ?>"><?php echo e($propiedad['estado']); ?></span>
                    </div>
                    <span class="mini_card_precio"><?php echo e(format_price((float) $propiedad['precio'], $propiedad['moneda'], $propiedad['periodo'])); ?></span>
                    <span class="mini_card_stats">👁 <?php echo (int) $propiedad['visitas_real']; ?> visitas · 📩 <?php echo (int) $propiedad['ofertas_real']; ?> ofertas</span>
                    <a href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=<?php echo e($origen); ?>" class="btn_ver_mas">Ver detalle ➜</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-recordatorios" draggable="false">
        <div class="panel_header">
            <h3>📅 Próximos recordatorios</h3>
            <span class="panel_hint">Próximos 7 días</span>
        </div>

        <?php if (empty($proximos_recordatorios)): ?>
            <div class="sin_datos">
                <p>No hay recordatorios próximos</p>
                <a href="?seccion=recordatorios" class="btn_ver_mas">Ver calendario</a>
            </div>
        <?php else: ?>
            <ul class="lista_recordatorios_dash">
                <?php foreach ($proximos_recordatorios as $rec): ?>
                    <li class="rec_item rec_estado_<?php echo e($rec['estado']); ?>">
                        <div class="rec_fecha_hora">
                            <span class="rec_fecha"><?php echo date('d/m', strtotime($rec['fecha_recordatorio'])); ?></span>
                            <?php if ($rec['hora_recordatorio']): ?>
                                <span class="rec_hora"><?php echo date('H:i', strtotime($rec['hora_recordatorio'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rec_contenido">
                            <span class="rec_tipo"><?php echo e($rec['tipo']); ?></span>
                            <span class="rec_desc"><?php echo e(substr($rec['descripcion'], 0, 40)); ?><?php echo strlen($rec['descripcion']) > 40 ? '...' : ''; ?></span>
                        </div>
                        <a href="?seccion=recordatorios&fecha=<?php echo $rec['fecha_recordatorio']; ?>" class="btn_ir">→</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="?seccion=recordatorios" class="btn_ver_todos">Ver todos →</a>
        <?php endif; ?>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-visitas-hoy" draggable="false">
        <div class="panel_header">
            <h3>📅 Visitas de hoy</h3>
            <span class="panel_hint"><?php echo date('d/m/Y'); ?></span>
        </div>

        <?php if (empty($visitas_hoy_detalle)): ?>
            <div class="sin_datos">
                <p>No hay visitas programadas para hoy</p>
                <a href="?seccion=visitas-vendedor" class="btn_ver_mas">Ver todas las visitas</a>
            </div>
        <?php else: ?>
            <ul class="lista_visitas_dash">
                <?php foreach ($visitas_hoy_detalle as $vis): ?>
                    <li class="visita_dash_item visita_dash_<?php echo e($vis['estado']); ?>">
                        <span class="visita_dash_hora"><?php echo $vis['hora_visita'] ? date('H:i', strtotime($vis['hora_visita'])) : '--:--'; ?></span>
                        <div class="visita_dash_info">
                            <strong><?php echo e($vis['propiedad_titulo'] ?? 'Sin propiedad'); ?></strong>
                            <?php if (!empty($vis['propiedad_ref'])): ?>
                                <small class="visita_dash_ref"><?php echo e($vis['propiedad_ref']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($vis['cliente_nombre'])): ?>
                                <small>con <?php echo e($vis['cliente_nombre'] . ' ' . $vis['cliente_apellido']); ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="visita_dash_estado visita_dash_estado_<?php echo e($vis['estado']); ?>"><?php echo ucfirst(e($vis['estado'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="?seccion=visitas-vendedor" class="btn_ver_todos">Ver todas →</a>
        <?php endif; ?>
    </section>

    <?php if (tiene_nivel('supervisor') && !empty($panel_supervisor)): ?>
    <section class="panel_panel panel_panel--wide" data-dashboard-card="panel-supervisor" draggable="false">
        <div class="panel_header">
            <h3>👥 Rendimiento del equipo</h3>
            <span class="panel_hint">Estadísticas por agente</span>
        </div>
        <div class="tabla_responsive">
            <table class="tabla_listado tabla_listado--compacta">
                <thead>
                    <tr>
                        <th>Agente</th>
                        <th>Rol</th>
                        <th>Clientes</th>
                        <th>Propiedades</th>
                        <th>Prospectos</th>
                        <th>Visitas (periodo)</th>
                        <th>Ofertas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($panel_supervisor as $ag): ?>
                    <tr>
                        <td><strong><?php echo e($ag['nombre']); ?></strong></td>
                        <td><span class="badge_rol"><?php echo e(ucfirst(str_replace('_', ' ', $ag['rol']))); ?></span></td>
                        <td><?php echo (int)$ag['total_clientes']; ?></td>
                        <td><?php echo (int)$ag['total_propiedades']; ?></td>
                        <td><?php echo (int)$ag['total_prospectos']; ?></td>
                        <td><strong><?php echo (int)$ag['visitas_periodo']; ?></strong></td>
                        <td><?php echo (int)$ag['total_ofertas']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <?php if (tiene_nivel('jefe') && !empty($panel_jefe)): ?>
    <section class="panel_panel panel_panel--wide" data-dashboard-card="panel-jefe" draggable="false">
        <div class="panel_header">
            <h3>🏢 Informe de empresa</h3>
            <span class="panel_hint">Resumen ejecutivo</span>
        </div>
        <div class="dashboard_kpis dashboard_kpis--mini">
            <div class="kpi_card kpi_card--mini">
                <span class="kpi_icon">👤</span>
                <strong class="kpi_value"><?php echo $panel_jefe['total_usuarios']; ?></strong>
                <span class="kpi_title">Usuarios activos</span>
            </div>
            <div class="kpi_card kpi_card--mini">
                <span class="kpi_icon">💰</span>
                <strong class="kpi_value"><?php echo number_format($panel_jefe['valor_cartera'], 0, ',', '.'); ?> €</strong>
                <span class="kpi_title">Valor en cartera</span>
            </div>
            <div class="kpi_card kpi_card--mini">
                <span class="kpi_icon">🔒</span>
                <strong class="kpi_value"><?php echo number_format($panel_jefe['valor_reservas'], 0, ',', '.'); ?> €</strong>
                <span class="kpi_title">Valor reservado</span>
            </div>
            <div class="kpi_card kpi_card--mini">
                <span class="kpi_icon">📈</span>
                <strong class="kpi_value"><?php echo $panel_jefe['tasa_conversion']; ?>%</strong>
                <span class="kpi_title">Tasa conversión</span>
            </div>
            <?php if ($panel_jefe['mejor_agente']): ?>
            <div class="kpi_card kpi_card--mini">
                <span class="kpi_icon">🏆</span>
                <strong class="kpi_value"><?php echo e($panel_jefe['mejor_agente']['nombre']); ?></strong>
                <span class="kpi_title">Mejor agente (<?php echo (int)$panel_jefe['mejor_agente']['total']; ?> visitas)</span>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
