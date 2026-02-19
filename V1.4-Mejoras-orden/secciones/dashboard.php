<?php
/* Seccion: Dashboard CRM
   Descripcion: Resumen general con filtros y datos reales
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_orden_accion'])) {
    header('Content-Type: application/json; charset=utf-8');

    $accion = $_POST['dashboard_orden_accion'] ?? '';

    if ($accion === 'guardar') {
        $kpis = trim($_POST['kpis'] ?? '[]');
        $panels = trim($_POST['panels'] ?? '[]');

        $kpis_arr = json_decode($kpis, true);
        $panels_arr = json_decode($panels, true);

        if (!is_array($kpis_arr) || !is_array($panels_arr)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Formato de orden invalido.']);
            exit;
        }

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

        preferencias_usuario_set($pdo, 'dashboard.kpis.order', json_encode(array_values($kpis_arr)));
        preferencias_usuario_set($pdo, 'dashboard.panels.order', json_encode(array_values($panels_arr)));

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($accion === 'reset') {
        preferencias_usuario_delete($pdo, 'dashboard.kpis.order');
        preferencias_usuario_delete($pdo, 'dashboard.panels.order');

        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => 'Accion no valida.']);
    exit;
}

$filtro_equipo = $_GET['equipo'] ?? 'Todos';
$filtro_periodo = $_GET['periodo'] ?? 'Mes actual';
$filtro_operacion = $_GET['operacion'] ?? 'Venta y Alquiler';

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

$desde = null;
if ($filtro_periodo === 'Mes actual') {
    $desde = date('Y-m-01');
} elseif ($filtro_periodo === 'Ultimos 90 dias') {
    $desde = date('Y-m-d', strtotime('-90 days'));
} elseif ($filtro_periodo === 'Ano') {
    $desde = date('Y-01-01');
}

$condicion_equipo_clientes = $equipo_db ? ' AND tipo = :equipo' : '';
$condicion_equipo_prop = $equipo_db ? ' AND equipo = :equipo' : '';
$condicion_fecha = $desde ? ' AND created_at >= :desde' : '';

$stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE 1=1' . $condicion_equipo_clientes . $condicion_fecha);
$params = [];
if ($equipo_db) {
    $params['equipo'] = $equipo_db;
}
if ($desde) {
    $params['desde'] = $desde;
}
$stmt->execute($params);
$clientes_activos = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE 1=1' . $condicion_equipo_clientes . $condicion_fecha);
$stmt->execute($params);
$prospectos_total = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM propiedades WHERE operacion = :operacion' . $condicion_equipo_prop . $condicion_fecha
);
$params_prop = ['operacion' => 'venta'];
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

$kpis = [
    ["titulo" => "Clientes activos", "valor" => (string) $clientes_activos, "detalle" => "Filtro: " . $filtro_equipo],
    ["titulo" => "Prospectos", "valor" => (string) $prospectos_total, "detalle" => "Periodo: " . $filtro_periodo],
    ["titulo" => "Propiedades venta", "valor" => (string) $propiedades_venta, "detalle" => "Operacion: Venta"],
    ["titulo" => "Propiedades alquiler", "valor" => (string) $propiedades_alquiler, "detalle" => "Operacion: Alquiler"],
];

$stmt = $pdo->prepare(
    'SELECT estado, COUNT(*) AS total FROM prospectos WHERE 1=1' . $condicion_equipo_clientes . $condicion_fecha . ' GROUP BY estado'
);
$stmt->execute($params);
$prospectos_por_estado = $stmt->fetchAll();

$mapa_estados = [
    'Contacto' => ['nuevo', 'contactado'],
    'Visita' => ['vender', 'comprar'],
    'Oferta' => ['realizado'],
    'Cierre' => ['descartado'],
];

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
    'Contacto' => '#3498db',
    'Visita' => '#1abc9c',
    'Oferta' => '#f39c12',
    'Cierre' => '#2ecc71',
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

$stmt = $pdo->query(
    "SELECT texto, DATE_FORMAT(created_at, '%H:%i') AS hora
     FROM notas WHERE tipo = 'Aviso'
     ORDER BY created_at DESC LIMIT 3"
);
$alertas = $stmt->fetchAll();

$stmt = $pdo->query(
    "SELECT texto, DATE_FORMAT(created_at, '%H:%i') AS hora
     FROM notas ORDER BY created_at DESC LIMIT 4"
);
$actividad = $stmt->fetchAll();

$query_destacadas = 'SELECT id, titulo, visitas, ofertas, operacion, equipo FROM propiedades WHERE 1=1';
$params_destacadas = [];
if ($operacion_db) {
    $query_destacadas .= ' AND operacion = :operacion';
    $params_destacadas['operacion'] = $operacion_db;
}
if ($equipo_db) {
    $query_destacadas .= ' AND equipo = :equipo';
    $params_destacadas['equipo'] = $equipo_db;
}
$query_destacadas .= ' ORDER BY visitas DESC LIMIT 3';
$stmt = $pdo->prepare($query_destacadas);
$stmt->execute($params_destacadas);
$destacadas = $stmt->fetchAll();

$orden_kpis_guardado = preferencias_usuario_get($pdo, 'dashboard.kpis.order') ?? '[]';
$orden_panels_guardado = preferencias_usuario_get($pdo, 'dashboard.panels.order') ?? '[]';
?>

<div class="encabezado_seccion">
    <h2>Dashboard CRM</h2>
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
        <div class="kpi_card" data-dashboard-card="kpi-<?php echo e(strtolower(str_replace(' ', '-', $kpi['titulo']))); ?>" draggable="false">
            <span class="kpi_title"><?php echo e($kpi['titulo']); ?></span>
            <strong class="kpi_value"><?php echo e($kpi['valor']); ?></strong>
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
            <h3>Alertas prioritarias</h3>
            <span class="panel_hint">Seguimiento del dia</span>
        </div>

        <ul class="lista_alertas">
            <?php foreach ($alertas as $alerta): ?>
                <li>
                    <strong>Aviso</strong>
                    <span><?php echo e($alerta['texto']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-actividad" draggable="false">
        <div class="panel_header">
            <h3>Actividad reciente</h3>
            <span class="panel_hint">Equipo: <?php echo e($filtro_equipo); ?></span>
        </div>

        <ul class="lista_actividad">
            <?php foreach ($actividad as $item): ?>
                <li>
                    <span class="actividad_hora"><?php echo e($item['hora']); ?></span>
                    <span class="actividad_texto"><?php echo e($item['texto']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel_panel" data-dashboard-card="panel-destacadas" draggable="false">
        <div class="panel_header">
            <h3>Propiedades destacadas</h3>
            <span class="panel_hint">Top 3 performance</span>
        </div>

        <div class="mini_cards">
            <?php foreach ($destacadas as $propiedad): ?>
                <?php $origen = obtener_origen_propiedad($propiedad['operacion'], $propiedad['equipo']); ?>
                <div class="mini_card">
                    <strong><?php echo e($propiedad['titulo']); ?></strong>
                    <span><?php echo e((string) $propiedad['visitas']); ?> visitas · <?php echo e((string) $propiedad['ofertas']); ?> ofertas</span>
                    <a href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=<?php echo e($origen); ?>" class="btn_ver_mas">Ver detalle</a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
