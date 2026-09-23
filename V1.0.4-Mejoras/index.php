<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();
$userId = currentUserId();
$isAdm = isAdmin();
$userName = currentUserName();

// ═══════════════════════════════════════════
// KPIs PRINCIPALES
// ═══════════════════════════════════════════

// Prospectos Activos (captación) - no descartados ni captados
$prospActivos = getCount('prospectos', $isAdm ? "etapa NOT IN ('descartado','captado') AND activo = 1" : "etapa NOT IN ('descartado','captado') AND activo = 1 AND agente_id = ?", $isAdm ? [] : [$userId]);

// Propiedades en Cartera
$propCartera = getCount('propiedades', $isAdm ? "estado = 'disponible'" : "estado = 'disponible' AND agente_id = ?", $isAdm ? [] : [$userId]);

// Clientes Activos
$clientesActivos = getCount('clientes', $isAdm ? 'activo = 1' : 'activo = 1 AND agente_id = ?', $isAdm ? [] : [$userId]);

// Visitas este mes
$visitasMes = getCount('visitas', $isAdm ? 'MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())' : 'MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND agente_id = ?', $isAdm ? [] : [$userId]);
$visitasHoy = getCount('visitas', $isAdm ? 'fecha = CURDATE()' : 'fecha = CURDATE() AND agente_id = ?', $isAdm ? [] : [$userId]);

// ═══════════════════════════════════════════
// GANANCIA POTENCIAL TOTAL EN CARTERA
// ═══════════════════════════════════════════
$stmtGanancia = $db->prepare("
    SELECT COALESCE(SUM(
        CASE WHEN p.precio_estimado IS NOT NULL AND p.comision IS NOT NULL 
        THEN p.precio_estimado * p.comision / 100 
        ELSE 0 END
    ), 0) as ganancia_potencial
    FROM prospectos p 
    WHERE p.etapa NOT IN ('descartado') AND p.activo = 1" .
    ($isAdm ? '' : ' AND p.agente_id = ?'));
$stmtGanancia->execute($isAdm ? [] : [$userId]);
$gananciaPotencial = $stmtGanancia->fetch()['ganancia_potencial'];

// Ganancia por propiedades en cartera
$stmtGananciaCartera = $db->prepare("
    SELECT COALESCE(SUM(p.precio * 0.05), 0) as ganancia_cartera
    FROM propiedades p 
    WHERE p.estado IN ('disponible','reservado')" .
    ($isAdm ? '' : ' AND p.agente_id = ?'));
$stmtGananciaCartera->execute($isAdm ? [] : [$userId]);
$gananciaCartera = $stmtGananciaCartera->fetch()['ganancia_cartera'];

$gananciaTotalPotencial = $gananciaPotencial + $gananciaCartera;

// Captados este mes
$stmtCaptados = $db->prepare("
    SELECT COUNT(*) as captados_mes
    FROM prospectos
    WHERE etapa = 'captado' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())" .
    ($isAdm ? '' : ' AND agente_id = ?'));
$stmtCaptados->execute($isAdm ? [] : [$userId]);
$captadosMes = $stmtCaptados->fetch()['captados_mes'];

// ═══════════════════════════════════════════
// COMISIONES / PENDIENTE / % COBRADO
// ═══════════════════════════════════════════
$stmtFin = $db->prepare("SELECT
    COALESCE(SUM(CASE WHEN estado = 'cobrado' THEN importe_total ELSE 0 END), 0) as comisiones_cobradas,
    COALESCE(SUM(CASE WHEN estado = 'pendiente' THEN importe_total ELSE 0 END), 0) as pendiente_cobro,
    COALESCE(SUM(importe_total), 0) as total_general
    FROM finanzas WHERE tipo IN ('comision_venta','comision_alquiler','honorarios')" .
    ($isAdm ? '' : ' AND agente_id = ?'));
$stmtFin->execute($isAdm ? [] : [$userId]);
$finanzas = $stmtFin->fetch();
$pctCobrado = ($gananciaTotalPotencial > 0) ? round(($finanzas['comisiones_cobradas'] / $gananciaTotalPotencial) * 100, 1) : 0;

// ═══════════════════════════════════════════
// PIPELINE CAPTACIÓN (prospectos por etapa)
// ═══════════════════════════════════════════
$stmtPipeCapt = $db->prepare("SELECT etapa, COUNT(*) as total FROM prospectos WHERE activo = 1" .
    ($isAdm ? '' : ' AND agente_id = ?') . " GROUP BY etapa ORDER BY FIELD(etapa, 'nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado')");
$stmtPipeCapt->execute($isAdm ? [] : [$userId]);
$pipelineCaptacion = $stmtPipeCapt->fetchAll(PDO::FETCH_KEY_PAIR);

$etapasLabels = [
    'nuevo_lead' => 'Nuevo Lead',
    'contactado' => '1er Contacto',
    'seguimiento' => 'Seguimiento',
    'visita_programada' => 'Visita',
    'en_negociacion' => 'Negociando',
    'captado' => 'Captado',
    'descartado' => 'Descartado',
];

// ═══════════════════════════════════════════
// CARTERA — ETAPAS (propiedades por estado)
// ═══════════════════════════════════════════
$stmtCartera = $db->query("SELECT estado, COUNT(*) as total FROM propiedades GROUP BY estado");
$carteraEtapas = $stmtCartera->fetchAll(PDO::FETCH_KEY_PAIR);

$carteraLabels = [
    'disponible' => 'Disponible',
    'reservado' => 'Reservado',
    'vendido' => 'Vendido',
    'alquilado' => 'Alquilado',
    'retirado' => 'Retirado',
];

// ═══════════════════════════════════════════
// CONTACTOS DEL MES (historial_prospectos)
// ═══════════════════════════════════════════
$mesActual  = date('Y-m');
$contactosMes = ['llamada' => 0, 'email' => 0, 'visita' => 0, 'whatsapp' => 0, 'nota' => 0, 'otro' => 0];
$contactosMesTotal = 0;
$contactosHoy = 0;
try {
    $stmtCMes = $db->prepare("
        SELECT tipo, COUNT(*) as total
        FROM historial_prospectos
        WHERE usuario_id = ?
          AND DATE_FORMAT(COALESCE(fecha_evento, created_at), '%Y-%m') = ?
        GROUP BY tipo
    ");
    $stmtCMes->execute([$userId, $mesActual]);
    foreach ($stmtCMes->fetchAll() as $row) {
        $contactosMes[$row['tipo']] = intval($row['total']);
        $contactosMesTotal += intval($row['total']);
    }
    $stmtCHoy = $db->prepare("
        SELECT COUNT(*) FROM historial_prospectos
        WHERE usuario_id = ? AND DATE(COALESCE(fecha_evento, created_at)) = CURDATE()
    ");
    $stmtCHoy->execute([$userId]);
    $contactosHoy = intval($stmtCHoy->fetchColumn());
} catch (Exception $e) {}

// ═══════════════════════════════════════════
// CONTACTAR HOY — Top 50 Prospectos por Urgencia
// ═══════════════════════════════════════════
$stmtContactarHoy = $db->prepare("
    SELECT p.id, p.nombre, p.telefono, p.etapa, p.temperatura, p.fecha_proximo_contacto,
           DATEDIFF(CURDATE(), COALESCE(p.fecha_proximo_contacto, p.updated_at)) as dias_sin_contacto,
           p.referencia
    FROM prospectos p 
    WHERE p.activo = 1 AND p.etapa NOT IN ('captado','descartado')
    " . ($isAdm ? '' : ' AND p.agente_id = ?') . "
    ORDER BY 
        CASE WHEN p.fecha_proximo_contacto <= CURDATE() THEN 0 ELSE 1 END,
        dias_sin_contacto DESC
    LIMIT 50");
$stmtContactarHoy->execute($isAdm ? [] : [$userId]);
$prospectosList = $stmtContactarHoy->fetchAll();

// ═══════════════════════════════════════════
// CLIENTES A CONTACTAR HOY
// ═══════════════════════════════════════════
$stmtClientesHoy = $db->prepare("
    SELECT c.id, c.nombre, c.apellidos, c.telefono, c.tipo, c.updated_at,
           t.titulo as proxima_accion, t.fecha_vencimiento,
           DATEDIFF(CURDATE(), c.updated_at) as dias_sin_contacto
    FROM clientes c
    LEFT JOIN tareas t ON t.cliente_id = c.id AND t.estado IN ('pendiente','en_progreso')
    WHERE c.activo = 1
    " . ($isAdm ? '' : ' AND c.agente_id = ?') . "
    AND (t.fecha_vencimiento <= CURDATE() OR t.fecha_vencimiento IS NULL)
    GROUP BY c.id
    ORDER BY dias_sin_contacto DESC
    LIMIT 20");
$stmtClientesHoy->execute($isAdm ? [] : [$userId]);
$clientesList = $stmtClientesHoy->fetchAll();

// Tareas vencidas / pendientes
$tareasPendientes = getCount('tareas', $isAdm ? 'estado IN ("pendiente","en_progreso")' : 'estado IN ("pendiente","en_progreso") AND asignado_a = ?', $isAdm ? [] : [$userId]);
$tareasVencidas = getCount('tareas', $isAdm ? 'estado = "pendiente" AND fecha_vencimiento < NOW()' : 'estado = "pendiente" AND fecha_vencimiento < NOW() AND asignado_a = ?', $isAdm ? [] : [$userId]);

$etapaColors = [
    'nuevo_lead' => '#06b6d4',
    'contactado' => '#64748b',
    'seguimiento' => '#3b82f6',
    'visita_programada' => '#8b5cf6',
    'en_negociacion' => '#f59e0b',
    'captado' => '#10b981',
    'descartado' => '#ef4444',
];

$tempLabels = ['frio' => 'Frío', 'templado' => 'Templado', 'caliente' => 'Caliente'];
$tempColors = ['frio' => '#3b82f6', 'templado' => '#f59e0b', 'caliente' => '#ef4444'];
?>

<style>
    /* Dashboard Premium Styles */
    .dash-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 12px;
        padding: 20px 24px;
        margin-bottom: 20px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dash-header h4 {
        margin: 0;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .dash-header small {
        color: #94a3b8;
    }

    .dash-header .date-badge {
        background: rgba(255, 255, 255, 0.1);
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .kpi-card {
        border-radius: 10px;
        padding: 16px 20px;
        color: #fff;
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        text-decoration: none;
        display: block;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .kpi-card .kpi-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }

    .kpi-card .kpi-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.9;
        margin-top: 4px;
    }

    .kpi-card .kpi-icon {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 2.5rem;
        opacity: 0.15;
    }

    .metric-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .metric-box {
        background: var(--bs-body-bg, #fff);
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 10px;
        padding: 14px 18px;
        text-align: center;
    }

    .metric-box .metric-value {
        font-size: 1.4rem;
        font-weight: 700;
    }

    .metric-box .metric-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-top: 2px;
    }

    .pipeline-table {
        width: 100%;
        font-size: 0.8rem;
    }

    .pipeline-table td,
    .pipeline-table th {
        padding: 6px 10px;
    }

    .pipeline-table th {
        background: #f1f5f9;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    [data-bs-theme="dark"] .pipeline-table th {
        background: #1e293b;
    }

    .pipeline-table tr:hover td {
        background: rgba(16, 185, 129, 0.04);
    }

    .prospect-table {
        width: 100%;
        font-size: 0.78rem;
        border-collapse: separate;
        border-spacing: 0;
    }

    .prospect-table th {
        background: #0f172a;
        color: #fff;
        padding: 8px 10px;
        font-weight: 600;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .prospect-table td {
        padding: 6px 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        vertical-align: middle;
    }

    .prospect-table tbody tr:hover {
        background: rgba(16, 185, 129, 0.06);
    }

    .prospect-table .row-danger {
        background: rgba(239, 68, 68, 0.08);
    }

    .prospect-table .row-warning {
        background: rgba(245, 158, 11, 0.06);
    }

    .prospect-table .row-muted {
        background: rgba(100, 116, 139, 0.05);
        color: #94a3b8;
    }

    .temp-badge {
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.68rem;
        font-weight: 600;
    }

    .section-title {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 8px 14px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        line-height: 1.35;
        text-align: left;
        white-space: normal;
    }

    .scroll-table {
        max-height: 500px;
        overflow: auto;
        border-radius: 8px;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }

    .metric-row-ganancia {
        grid-template-columns: repeat(4, 1fr);
    }

    .mobile-card-list {
        display: none;
        padding: 10px;
    }

    .mobile-lead-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        background: #fff;
        padding: 10px 11px;
        margin-bottom: 10px;
        box-shadow: 0 6px 14px rgba(15, 23, 42, 0.05);
    }

    .mobile-lead-card:last-child {
        margin-bottom: 0;
    }

    .mobile-lead-card.is-danger {
        border-color: rgba(239, 68, 68, 0.35);
        background: rgba(254, 242, 242, 0.9);
    }

    .mobile-lead-card.is-warning {
        border-color: rgba(245, 158, 11, 0.35);
        background: rgba(255, 251, 235, 0.95);
    }

    .mobile-lead-card.is-muted {
        border-color: rgba(100, 116, 139, 0.25);
        background: rgba(241, 245, 249, 0.85);
    }

    .mobile-lead-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 7px;
    }

    .mobile-lead-name {
        font-size: 0.86rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.25;
        margin: 0;
    }

    .mobile-lead-stage {
        font-size: 0.68rem;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
    }

    .mobile-lead-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px 10px;
        font-size: 0.72rem;
        color: #64748b;
    }

    .mobile-lead-meta strong {
        color: #0f172a;
        font-weight: 700;
    }

    .mobile-card-empty {
        border: 1px dashed rgba(100, 116, 139, 0.35);
        border-radius: 12px;
        padding: 16px 10px;
        text-align: center;
        color: #64748b;
        font-size: 0.8rem;
    }

    [data-bs-theme="dark"] .mobile-lead-card {
        background: #0f172a;
        border-color: #334155;
        box-shadow: none;
    }

    [data-bs-theme="dark"] .mobile-lead-card.is-danger {
        background: rgba(127, 29, 29, 0.2);
        border-color: rgba(248, 113, 113, 0.35);
    }

    [data-bs-theme="dark"] .mobile-lead-card.is-warning {
        background: rgba(120, 53, 15, 0.22);
        border-color: rgba(251, 191, 36, 0.35);
    }

    [data-bs-theme="dark"] .mobile-lead-card.is-muted {
        background: rgba(51, 65, 85, 0.35);
        border-color: rgba(148, 163, 184, 0.35);
    }

    [data-bs-theme="dark"] .mobile-lead-name {
        color: #f1f5f9;
    }

    [data-bs-theme="dark"] .mobile-lead-stage {
        color: #cbd5e1;
    }

    [data-bs-theme="dark"] .mobile-lead-meta {
        color: #94a3b8;
    }

    [data-bs-theme="dark"] .mobile-lead-meta strong {
        color: #e2e8f0;
    }

    @media (max-width: 767.98px) {
        .kpi-grid,
        .finance-strip {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            gap: 10px;
            margin: 0;
            padding: 2px 2px 8px;
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x proximity;
        }

        .kpi-grid .kpi-col,
        .finance-strip .finance-col {
            flex: 0 0 78%;
            max-width: 78%;
            padding: 0;
            scroll-snap-align: start;
        }

        .kpi-card {
            padding: 12px 14px;
            min-height: 96px;
            border-radius: 14px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
        }

        .kpi-card .kpi-value {
            font-size: 1.8rem;
        }

        .kpi-card .kpi-icon {
            font-size: 2rem;
            right: 10px;
        }

        .kpi-card .kpi-label {
            font-size: 0.64rem;
            letter-spacing: 0.6px;
        }

        .metric-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 8px;
        }

        .metric-row-ganancia {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .metric-box {
            padding: 12px 10px;
            border-radius: 14px;
        }

        .metric-box .metric-value {
            font-size: 1.2rem;
        }

        .metric-box .metric-label {
            font-size: 0.64rem;
            letter-spacing: 0.35px;
        }

        .pipeline-table {
            min-width: 0;
        }

        .prospect-table {
            min-width: 460px;
        }

        .scroll-table {
            -webkit-overflow-scrolling: touch;
            max-height: 420px;
        }

        .section-title {
            font-size: 0.66rem;
            padding: 7px 10px;
            letter-spacing: 0.7px;
        }

        .quick-action {
            border-radius: 14px;
            min-height: 74px;
            justify-content: center;
            gap: 10px;
            padding: 12px 10px;
            font-size: 0.86rem;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
        }

        .quick-action i {
            margin-right: 0;
            font-size: 1.2rem;
        }

        .table-prospectos th:nth-child(1),
        .table-prospectos td:nth-child(1),
        .table-prospectos th:nth-child(5),
        .table-prospectos td:nth-child(5),
        .table-clientes th:nth-child(1),
        .table-clientes td:nth-child(1) {
            display: none;
        }

        .table-prospectos th,
        .table-clientes th {
            font-size: 0.64rem;
            padding: 8px 8px;
        }

        .table-prospectos td,
        .table-clientes td {
            font-size: 0.73rem;
            padding: 8px 8px;
        }

        .mobile-hide {
            display: none !important;
        }

        .legend-mobile {
            text-align: left !important;
            font-size: 0.72rem;
            line-height: 1.7;
        }

        .desktop-table-wrapper {
            display: block;
        }

        .mobile-card-list {
            display: none !important;
        }
    }

    @media (max-width: 575.98px) {
        .kpi-grid .kpi-col,
        .finance-strip .finance-col {
            flex-basis: 86%;
            max-width: 86%;
        }

        .metric-row,
        .metric-row-ganancia {
            grid-template-columns: 1fr;
        }

        .quick-action {
            justify-content: flex-start;
            padding-left: 12px;
        }

    }
</style>


<!-- KPIs Principales -->
<div class="row g-3 mb-3 kpi-grid">
    <div class="col-6 col-lg-3 kpi-col">
        <a href="<?= APP_URL ?>/modules/prospectos/index.php" class="kpi-card" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
            <div class="kpi-value"><?= $prospActivos ?></div>
            <div class="kpi-label">Prospectos Activos<br><small>(captación)</small></div>
            <i class="bi bi-person-plus kpi-icon"></i>
        </a>
    </div>
    <div class="col-6 col-lg-3 kpi-col">
        <a href="<?= APP_URL ?>/modules/propiedades/index.php" class="kpi-card" style="background: linear-gradient(135deg, #047857, #10b981);">
            <div class="kpi-value"><?= $propCartera ?></div>
            <div class="kpi-label">Propiedades<br><small>en cartera</small></div>
            <i class="bi bi-house-door kpi-icon"></i>
        </a>
    </div>
    <div class="col-6 col-lg-3 kpi-col">
        <a href="<?= APP_URL ?>/modules/clientes/index.php" class="kpi-card" style="background: linear-gradient(135deg, #b45309, #f59e0b);">
            <div class="kpi-value"><?= $clientesActivos ?></div>
            <div class="kpi-label">Clientes<br><small>activos</small></div>
            <i class="bi bi-people kpi-icon"></i>
        </a>
    </div>
    <div class="col-6 col-lg-3 kpi-col">
        <a href="<?= APP_URL ?>/modules/visitas/index.php" class="kpi-card" style="background: linear-gradient(135deg, #7c3aed, #a78bfa);">
            <div class="kpi-value"><?= $visitasMes ?></div>
            <div class="kpi-label">Visitas este mes<br><small><?= $visitasHoy ?> hoy</small></div>
            <i class="bi bi-calendar-event kpi-icon"></i>
        </a>
    </div>
</div>

<!-- Ganancia Potencial -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="text-center mb-2">
            <span class="section-title" style="background: #fef3c720; color: #b45309;">
                <i class="bi bi-piggy-bank"></i> GANANCIA POTENCIAL TOTAL EN CARTERA
            </span>
        </div>
        <div class="metric-row metric-row-ganancia">
            <div class="metric-box">
                <div class="metric-value text-success"><?= formatPrecio($gananciaPotencial) ?></div>
                <div class="metric-label">Prospectos</div>
            </div>
            <div class="metric-box">
                <div class="metric-value text-primary"><?= formatPrecio($gananciaCartera) ?></div>
                <div class="metric-label">Cartera</div>
            </div>
            <div class="metric-box">
                <div class="metric-value" style="color: #7c3aed;"><?= $captadosMes ?></div>
                <div class="metric-label">Captados este mes</div>
            </div>
            <div class="metric-box"
                style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-color: #10b981;">
                <div class="metric-value" style="color: #047857;"><?= formatPrecio($gananciaTotalPotencial) ?></div>
                <div class="metric-label fw-bold">Total Potencial</div>
            </div>
        </div>
    </div>
</div>

<!-- Comisiones, Pendiente, % -->
<div class="row g-3 mb-3 finance-strip">
    <div class="col-md-4 finance-col">
        <div class="metric-box" style="background: #f0fdf4; border-color: #10b981;">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <i class="bi bi-check-circle text-success"></i>
                <span class="metric-label mb-0">COMISIONES COBRADAS</span>
            </div>
            <div class="metric-value text-success"><?= formatPrecio($finanzas['comisiones_cobradas']) ?></div>
        </div>
    </div>
    <div class="col-md-4 finance-col">
        <div class="metric-box" style="background: #fef3c7; border-color: #f59e0b;">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <i class="bi bi-hourglass-split text-warning"></i>
                <span class="metric-label mb-0">PENDIENTE DE COBRO</span>
            </div>
            <div class="metric-value text-warning"><?= formatPrecio($finanzas['pendiente_cobro']) ?></div>
        </div>
    </div>
    <div class="col-md-4 finance-col">
        <div class="metric-box" style="background: #ede9fe; border-color: #8b5cf6;">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <i class="bi bi-percent text-purple"></i>
                <span class="metric-label mb-0">% DEL POTENCIAL COBRADO</span>
            </div>
            <div class="metric-value" style="color: #7c3aed;"><?= $pctCobrado ?>%</div>
        </div>
    </div>
</div>

<!-- Pipelines Row -->
<div class="row g-3 mb-3">
    <!-- Captación Pipeline -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header py-2">
                <span class="section-title" style="background: #dbeafe; color: #1e40af; font-size: 0.68rem;">
                    <i class="bi bi-funnel"></i> CAPTACIÓN — PIPELINE
                </span>
            </div>
            <div class="card-body p-0">
                <table class="pipeline-table">
                    <thead>
                        <tr>
                            <th>Etapa</th>
                            <th class="text-end">Nº</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etapasLabels as $eKey => $eLabel): ?>
                            <tr>
                                <td>
                                    <span
                                        style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $etapaColors[$eKey] ?>; margin-right:6px;"></span>
                                    <?= $eLabel ?>
                                    <?php if ($eKey === 'captado'): ?> <i class="bi bi-check-circle-fill text-success"
                                            style="font-size:0.7rem"></i><?php endif; ?>
                                    <?php if ($eKey === 'descartado'): ?> <i class="bi bi-x-circle-fill text-danger"
                                            style="font-size:0.7rem"></i><?php endif; ?>
                                </td>
                                <td class="text-end fw-bold"><?= $pipelineCaptacion[$eKey] ?? 0 ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Cartera Etapas -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header py-2">
                <span class="section-title" style="background: #d1fae5; color: #047857; font-size: 0.68rem;">
                    <i class="bi bi-house-door"></i> CARTERA — ETAPAS
                </span>
            </div>
            <div class="card-body p-0">
                <table class="pipeline-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th class="text-end">Nº</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $carteraColors = ['disponible' => '#10b981', 'reservado' => '#f59e0b', 'vendido' => '#3b82f6', 'alquilado' => '#8b5cf6', 'retirado' => '#6b7280'];
                        foreach ($carteraLabels as $cKey => $cLabel): ?>
                            <tr>
                                <td>
                                    <span
                                        style="display:inline-block; width:8px; height:8px; border-radius:50%; background:<?= $carteraColors[$cKey] ?? '#6b7280' ?>; margin-right:6px;"></span>
                                    <?= $cLabel ?>
                                </td>
                                <td class="text-end fw-bold"><?= $carteraEtapas[$cKey] ?? 0 ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Contactos del mes -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header py-2">
                <span class="section-title" style="background: #fce7f3; color: #be185d; font-size: 0.68rem;">
                    <?php $mesesEs = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']; ?>
                    <i class="bi bi-telephone-fill"></i> MIS CONTACTOS — <?= strtoupper($mesesEs[intval(date('n'))] ?? date('F')) ?>
                </span>
            </div>
            <div class="card-body d-flex flex-column">
                <!-- Total grande -->
                <div class="text-center mb-3">
                    <div style="font-size: 2.8rem; font-weight: 800; line-height: 1; color: var(--bs-primary);">
                        <?= $contactosMesTotal ?>
                    </div>
                    <div class="text-muted small mt-1">contactos este mes</div>
                    <div class="mt-1">
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-calendar-day"></i> Hoy: <strong><?= $contactosHoy ?></strong>
                        </span>
                    </div>
                </div>
                <!-- Desglose por tipo -->
                <div class="mt-auto">
                    <?php
                    $tiposContacto = [
                        'llamada'  => ['Llamadas',  'bi-telephone',       '#3b82f6'],
                        'whatsapp' => ['WhatsApp',  'bi-whatsapp',        '#25d366'],
                        'email'    => ['Emails',    'bi-envelope',        '#f59e0b'],
                        'visita'   => ['Visitas',   'bi-house-door',      '#10b981'],
                        'nota'     => ['Notas',     'bi-sticky',          '#8b5cf6'],
                        'otro'     => ['Otros',     'bi-three-dots',      '#6b7280'],
                    ];
                    foreach ($tiposContacto as $tipo => [$label, $icon, $color]):
                        $n = $contactosMes[$tipo] ?? 0;
                        if ($n === 0) continue;
                        $pct = $contactosMesTotal > 0 ? round(($n / $contactosMesTotal) * 100) : 0;
                    ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center small mb-1">
                            <span><i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i> <?= $label ?></span>
                            <strong><?= $n ?></strong>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar" style="width:<?= $pct ?>%; background:<?= $color ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($contactosMesTotal === 0): ?>
                    <p class="text-muted small text-center mb-0">Aún no hay contactos registrados este mes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Acciones rápidas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/prospectos/form.php" class="quick-action">
            <i class="bi bi-person-plus text-primary"></i>
            <span>Nuevo Prospecto</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/propiedades/form.php" class="quick-action">
            <i class="bi bi-house-add text-success"></i>
            <span>Nueva Propiedad</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/visitas/form.php" class="quick-action">
            <i class="bi bi-calendar-plus text-info"></i>
            <span>Nueva Visita</span>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= APP_URL ?>/modules/tareas/form.php" class="quick-action">
            <i class="bi bi-plus-square text-warning"></i>
            <span>Nueva Tarea</span>
        </a>
    </div>
</div>

<!-- TABLES: CONTACTAR HOY -->
<div class="row g-3 mb-4">
    <!-- Prospectos por Urgencia -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="section-title" style="background: #fee2e2; color: #991b1b; font-size: 0.68rem;">
                    <i class="bi bi-telephone-forward"></i> CONTACTAR HOY — Top <?= count($prospectosList) ?> Prospectos
                    por Urgencia
                </span>
                <a href="<?= APP_URL ?>/modules/prospectos/index.php" class="btn btn-sm btn-outline-primary mobile-hide">Ver
                    todos</a>
            </div>
            <div class="scroll-table desktop-table-wrapper" style="border: none; border-radius: 0;">
                <table class="prospect-table table-prospectos">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Etapa</th>
                            <th>Temp.</th>
                            <th>Próx. Cont.</th>
                            <th>Días S/C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prospectosList as $idx => $pr):
                            $dias = intval($pr['dias_sin_contacto']);
                            $rowClass = '';
                            if ($pr['fecha_proximo_contacto'] && $pr['fecha_proximo_contacto'] < date('Y-m-d'))
                                $rowClass = 'row-danger';
                            elseif ($dias > 15)
                                $rowClass = 'row-muted';
                            elseif ($dias >= 5)
                                $rowClass = 'row-warning';
                            $tc = $tempColors[$pr['temperatura'] ?? 'frio'] ?? '#3b82f6';
                            ?>
                            <tr class="<?= $rowClass ?>" style="cursor:pointer"
                                onclick="location.href='<?= APP_URL ?>/modules/prospectos/ver.php?id=<?= $pr['id'] ?>'">
                                <td class="fw-bold"><?= $idx + 1 ?></td>
                                <td>
                                    <strong><?= sanitize($pr['nombre']) ?></strong>
                                </td>
                                <td>
                                    <?php if ($pr['telefono']): ?>
                                        <a href="tel:<?= sanitize($pr['telefono']) ?>" onclick="event.stopPropagation()"
                                            class="text-decoration-none"><?= sanitize($pr['telefono']) ?></a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <span
                                        style="display:inline-block;width:6px;height:6px;border-radius:50%;background:<?= $etapaColors[$pr['etapa']] ?? '#6b7280' ?>;margin-right:4px;"></span>
                                    <?= $etapasLabels[$pr['etapa']] ?? $pr['etapa'] ?>
                                </td>
                                <td>
                                    <span class="temp-badge"
                                        style="background: <?= $tc ?>20; color: <?= $tc ?>;"><?= $tempLabels[$pr['temperatura'] ?? 'frio'] ?? 'Frío' ?></span>
                                </td>
                                <td><?= $pr['fecha_proximo_contacto'] ? formatFecha($pr['fecha_proximo_contacto']) : '-' ?>
                                </td>
                                <td class="text-center">
                                    <span
                                        class="fw-bold <?= $dias > 15 ? 'text-danger' : ($dias > 7 ? 'text-warning' : 'text-success') ?>"><?= max(0, $dias) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($prospectosList)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4"><i
                                        class="bi bi-check-circle fs-4 d-block mb-2"></i>¡Sin prospectos urgentes!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list">
                <?php foreach ($prospectosList as $idx => $pr):
                    $dias = intval($pr['dias_sin_contacto']);
                    $cardClass = '';
                    if ($pr['fecha_proximo_contacto'] && $pr['fecha_proximo_contacto'] < date('Y-m-d')) {
                        $cardClass = 'is-danger';
                    } elseif ($dias > 15) {
                        $cardClass = 'is-muted';
                    } elseif ($dias >= 5) {
                        $cardClass = 'is-warning';
                    }
                    ?>
                    <div class="mobile-lead-card <?= $cardClass ?>" style="cursor:pointer"
                        onclick="location.href='<?= APP_URL ?>/modules/prospectos/ver.php?id=<?= $pr['id'] ?>'">
                        <div class="mobile-lead-top">
                            <p class="mobile-lead-name mb-0"><?= sanitize($pr['nombre']) ?></p>
                            <span class="mobile-lead-stage"><?= $etapasLabels[$pr['etapa']] ?? $pr['etapa'] ?></span>
                        </div>
                        <div class="mobile-lead-meta">
                            <div>Tel: <strong><?php if ($pr['telefono']): ?><a href="tel:<?= sanitize($pr['telefono']) ?>"
                                        onclick="event.stopPropagation()"><?= sanitize($pr['telefono']) ?></a><?php else: ?>-<?php endif; ?></strong></div>
                            <div>Días: <strong
                                    class="<?= $dias > 15 ? 'text-danger' : ($dias > 7 ? 'text-warning' : 'text-success') ?>"><?= max(0, $dias) ?></strong>
                            </div>
                            <div>Próx: <strong><?= $pr['fecha_proximo_contacto'] ? formatFecha($pr['fecha_proximo_contacto']) : '-' ?></strong>
                            </div>
                            <div>Temp: <strong><?= $tempLabels[$pr['temperatura'] ?? 'frio'] ?? 'Frío' ?></strong></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($prospectosList)): ?>
                    <div class="mobile-card-empty">
                        <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                        ¡Sin prospectos urgentes!
                    </div>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/modules/prospectos/index.php" class="btn btn-sm btn-outline-primary w-100 mt-2">Ver todos</a>
            </div>
        </div>
    </div>

    <!-- Clientes a Contactar -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span class="section-title" style="background: #dbeafe; color: #1e40af; font-size: 0.68rem;">
                    <i class="bi bi-people"></i> PSI — Clientes
                </span>
                <a href="<?= APP_URL ?>/modules/clientes/index.php" class="btn btn-sm btn-outline-primary mobile-hide">Ver todos</a>
            </div>
            <div class="scroll-table desktop-table-wrapper" style="border: none; border-radius: 0;">
                <table class="prospect-table table-clientes">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Próx. Acción</th>
                            <th>D. S/C</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientesList as $idx => $cl):
                            $dias = intval($cl['dias_sin_contacto']);
                            $rowClass = $dias > 15 ? 'row-muted' : ($dias > 7 ? 'row-warning' : '');
                            ?>
                            <tr class="<?= $rowClass ?>" style="cursor:pointer"
                                onclick="location.href='<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $cl['id'] ?>'">
                                <td class="fw-bold"><?= $idx + 1 ?></td>
                                <td><strong><?= sanitize($cl['nombre'] . ' ' . ($cl['apellidos'] ?? '')) ?></strong></td>
                                <td>
                                    <?php if ($cl['telefono']): ?>
                                        <a href="tel:<?= sanitize($cl['telefono']) ?>"
                                            onclick="event.stopPropagation()"><?= sanitize($cl['telefono']) ?></a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="small"><?= sanitize(mb_strimwidth($cl['proxima_accion'] ?? '-', 0, 30, '...')) ?>
                                </td>
                                <td class="text-center">
                                    <span
                                        class="fw-bold <?= $dias > 15 ? 'text-danger' : ($dias > 7 ? 'text-warning' : 'text-success') ?>"><?= max(0, $dias) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clientesList)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4"><i
                                        class="bi bi-check-circle fs-4 d-block mb-2"></i>Sin clientes pendientes</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list">
                <?php foreach ($clientesList as $idx => $cl):
                    $dias = intval($cl['dias_sin_contacto']);
                    $cardClass = $dias > 15 ? 'is-muted' : ($dias > 7 ? 'is-warning' : '');
                    ?>
                    <div class="mobile-lead-card <?= $cardClass ?>" style="cursor:pointer"
                        onclick="location.href='<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $cl['id'] ?>'">
                        <div class="mobile-lead-top">
                            <p class="mobile-lead-name mb-0"><?= sanitize($cl['nombre'] . ' ' . ($cl['apellidos'] ?? '')) ?></p>
                            <span class="mobile-lead-stage">PSI</span>
                        </div>
                        <div class="mobile-lead-meta">
                            <div>Tel: <strong><?php if ($cl['telefono']): ?><a href="tel:<?= sanitize($cl['telefono']) ?>"
                                        onclick="event.stopPropagation()"><?= sanitize($cl['telefono']) ?></a><?php else: ?>-<?php endif; ?></strong></div>
                            <div>Días: <strong
                                    class="<?= $dias > 15 ? 'text-danger' : ($dias > 7 ? 'text-warning' : 'text-success') ?>"><?= max(0, $dias) ?></strong>
                            </div>
                            <div style="grid-column: 1 / -1;">Acción: <strong><?= sanitize(mb_strimwidth($cl['proxima_accion'] ?? '-', 0, 55, '...')) ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($clientesList)): ?>
                    <div class="mobile-card-empty">
                        <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                        Sin clientes pendientes
                    </div>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/modules/clientes/index.php" class="btn btn-sm btn-outline-primary w-100 mt-2">Ver todos</a>
            </div>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="mb-4 text-center legend-mobile">
    <small class="text-muted">
        <span
            style="display:inline-block; width:10px; height:10px; background:rgba(239,68,68,0.15); border-radius:2px; margin-right:2px;"></span>
        Vencido
        &nbsp;&nbsp;
        <span
            style="display:inline-block; width:10px; height:10px; background:rgba(245,158,11,0.15); border-radius:2px; margin-right:2px;"></span>
        5-15 días sin contacto
        &nbsp;&nbsp;
        <span
            style="display:inline-block; width:10px; height:10px; background:rgba(100,116,139,0.1); border-radius:2px; margin-right:2px;"></span>
        >15 días sin actividad
    </small>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>