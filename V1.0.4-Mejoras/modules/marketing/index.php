<?php
$pageTitle = 'Marketing';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$userId = currentUserId();

// ═══════════════════════════════════════════
// SAFE QUERY HELPER — handles missing tables
// ═══════════════════════════════════════════
function safeQuery($db, $sql, $params = [], $default = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return $default;
    }
}
function safeQueryOne($db, $sql, $params = [], $default = null) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: $default;
    } catch (PDOException $e) {
        return $default;
    }
}
function safeCount($db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ═══════════════════════════════════════════
// KPIs — Gather all metrics
// ═══════════════════════════════════════════

// Campañas
$campanasActivas = safeCount($db, "SELECT COUNT(*) FROM campanas WHERE estado = 'activa'");
$campanasTotal = safeCount($db, "SELECT COUNT(*) FROM campanas");
$campanaStats = safeQueryOne($db, "SELECT
    COALESCE(SUM(enviados), 0) as total_enviados,
    COALESCE(SUM(abiertos), 0) as total_abiertos,
    COALESCE(SUM(clicks), 0) as total_clicks,
    COALESCE(SUM(total_contactos), 0) as total_contactos
    FROM campanas") ?? ['total_enviados' => 0, 'total_abiertos' => 0, 'total_clicks' => 0, 'total_contactos' => 0];
$tasaApertura = $campanaStats['total_enviados'] > 0
    ? round(($campanaStats['total_abiertos'] / $campanaStats['total_enviados']) * 100, 1) : 0;
$tasaClicks = $campanaStats['total_enviados'] > 0
    ? round(($campanaStats['total_clicks'] / $campanaStats['total_enviados']) * 100, 1) : 0;

// Leads este mes
$leadsEsteMes = safeCount($db, "SELECT COUNT(*) FROM prospectos WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$leadsMesAnterior = safeCount($db, "SELECT COUNT(*) FROM prospectos WHERE MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)");
$leadsTrend = $leadsMesAnterior > 0 ? round((($leadsEsteMes - $leadsMesAnterior) / $leadsMesAnterior) * 100) : ($leadsEsteMes > 0 ? 100 : 0);

// Trigger Links
$triggerClicks = safeCount($db, "SELECT COALESCE(SUM(total_clicks), 0) FROM trigger_links");
$triggerClicksMes = safeCount($db, "SELECT COUNT(*) FROM trigger_clicks WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$triggerLinksTotal = safeCount($db, "SELECT COUNT(*) FROM trigger_links WHERE activo = 1");

// Reputación
$reputacionStats = safeQueryOne($db, "SELECT
    COUNT(*) as total,
    SUM(estado = 'completada') as completadas,
    SUM(estado = 'enviada') as enviadas,
    AVG(CASE WHEN valoracion IS NOT NULL THEN valoracion END) as media
    FROM resenas_solicitudes") ?? ['total' => 0, 'completadas' => 0, 'enviadas' => 0, 'media' => null];
$tasaConversion = ($reputacionStats['total'] ?? 0) > 0
    ? round((($reputacionStats['completadas'] ?? 0) / $reputacionStats['total']) * 100) : 0;

// Social posts
$socialPosts = safeCount($db, "SELECT COUNT(*) FROM social_posts");
$socialPublicados = safeCount($db, "SELECT COUNT(*) FROM social_posts WHERE estado = 'publicado'");
$socialProgramados = safeCount($db, "SELECT COUNT(*) FROM social_posts WHERE estado = 'programado'");

// Formularios
$formulariosActivos = safeCount($db, "SELECT COUNT(*) FROM formularios WHERE activo = 1");
$formEnviosMes = safeCount($db, "SELECT COUNT(*) FROM formulario_envios WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");

// Emails enviados este mes
$emailsEnviados = safeCount($db, "SELECT COUNT(*) FROM email_mensajes WHERE direccion = 'saliente' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");

// ═══════════════════════════════════════════
// DATA — Tables and lists
// ═══════════════════════════════════════════

// Campañas recientes
$campanasRecientes = safeQuery($db, "SELECT * FROM campanas ORDER BY updated_at DESC LIMIT 6");
$estadoClases = ['borrador'=>'secondary','activa'=>'success','pausada'=>'warning','completada'=>'info'];

// Top Trigger Links
$topTrigger = safeQuery($db, "SELECT * FROM trigger_links WHERE activo = 1 ORDER BY total_clicks DESC LIMIT 5");
$maxClicks = 1;
foreach ($topTrigger as $tl) { if ($tl['total_clicks'] > $maxClicks) $maxClicks = $tl['total_clicks']; }

// Trigger clicks últimos 7 días
$clicksDiarios = safeQuery($db, "SELECT DATE(created_at) as dia, COUNT(*) as total
    FROM trigger_clicks
    WHERE created_at >= CURDATE() - INTERVAL 7 DAY
    GROUP BY DATE(created_at) ORDER BY dia ASC");
$clicksMap = [];
foreach ($clicksDiarios as $cd) { $clicksMap[$cd['dia']] = $cd['total']; }
$ultimos7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $ultimos7[$d] = $clicksMap[$d] ?? 0;
}
$maxClickDia = max(1, max($ultimos7));

// Últimas reseñas
$ultimasResenas = safeQuery($db, "SELECT rs.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos
    FROM resenas_solicitudes rs
    LEFT JOIN clientes c ON rs.cliente_id = c.id
    ORDER BY rs.created_at DESC LIMIT 5");

// Social posts recientes
$socialRecientes = safeQuery($db, "SELECT * FROM social_posts ORDER BY created_at DESC LIMIT 5");

// Leads por origen — intenta prospectos primero, luego clientes
$leadsPorOrigen = safeQuery($db, "SELECT COALESCE(origen,'otro') as origen, COUNT(*) as total FROM prospectos
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    GROUP BY COALESCE(origen,'otro') ORDER BY total DESC");
if (empty($leadsPorOrigen)) {
    $leadsPorOrigen = safeQuery($db, "SELECT COALESCE(origen,'otro') as origen, COUNT(*) as total FROM clientes
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
        GROUP BY COALESCE(origen,'otro') ORDER BY total DESC");
}
$origenColores = [
    'web'=>'#3b82f6','telefono'=>'#10b981','oficina'=>'#f59e0b','referido'=>'#8b5cf6',
    'portal'=>'#ef4444','otro'=>'#64748b','formulario'=>'#06b6d4','facebook'=>'#1877f2',
    'instagram'=>'#e4405f','google'=>'#ea4335','whatsapp'=>'#25d366'
];
$totalLeadsPorOrigen = array_sum(array_column($leadsPorOrigen, 'total'));

// Formularios con envíos
$formulariosLista = safeQuery($db, "SELECT f.id, f.nombre, f.activo,
    (SELECT COUNT(*) FROM formulario_envios fe WHERE fe.formulario_id = f.id) as total_envios,
    (SELECT COUNT(*) FROM formulario_envios fe WHERE fe.formulario_id = f.id AND MONTH(fe.created_at) = MONTH(CURDATE())) as envios_mes
    FROM formularios f ORDER BY total_envios DESC LIMIT 5");

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* Marketing Command Center Styles */
.mkt-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 20px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.mkt-header::before {
    content: '';
    position: absolute;
    top: -50%; right: -20%;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.mkt-header h3 { font-weight: 800; letter-spacing: -0.5px; margin: 0; position: relative; }
.mkt-header p { color: #a5b4fc; margin: 4px 0 0; position: relative; font-size: 0.9rem; }

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.kpi-item {
    background: var(--bs-body-bg, #fff);
    border: 1px solid rgba(0,0,0,0.06);
    border-radius: 12px;
    padding: 16px 18px;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    overflow: hidden;
}
.kpi-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.kpi-item .kpi-number {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -1px;
}
.kpi-item .kpi-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748b;
    margin-top: 4px;
    font-weight: 600;
}
.kpi-item .kpi-sub {
    font-size: 0.72rem;
    margin-top: 6px;
}
.kpi-item .kpi-icon {
    position: absolute;
    right: 14px; top: 14px;
    font-size: 1.6rem;
    opacity: 0.1;
}
.kpi-trend-up { color: #10b981; }
.kpi-trend-down { color: #ef4444; }

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.qa-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 14px 8px;
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.06);
    background: var(--bs-body-bg, #fff);
    color: var(--bs-body-color);
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    transition: all 0.2s;
}
.qa-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    color: var(--primary, #6366f1);
    border-color: var(--primary, #6366f1);
}
.qa-btn i { font-size: 1.3rem; }

.section-head {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 6px 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
}

.bar-chart-container { display: flex; align-items: flex-end; gap: 6px; height: 80px; }
.bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.bar-fill {
    width: 100%;
    min-height: 4px;
    border-radius: 4px 4px 0 0;
    background: linear-gradient(180deg, #6366f1, #818cf8);
    transition: height 0.5s ease;
}
.bar-label {
    font-size: 0.6rem;
    color: #94a3b8;
    white-space: nowrap;
}
.bar-value {
    font-size: 0.65rem;
    font-weight: 700;
    color: #6366f1;
}

.funnel-bar {
    height: 8px;
    border-radius: 4px;
    background: #e2e8f0;
    overflow: hidden;
    margin-bottom: 4px;
}
.funnel-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

.star-display { color: #f59e0b; font-size: 0.85rem; }
.star-empty { color: #e2e8f0; }

.mini-table { font-size: 0.78rem; }
.mini-table th {
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    color: #64748b;
    padding: 6px 10px;
    border-bottom: 2px solid #e2e8f0;
}
.mini-table td { padding: 8px 10px; vertical-align: middle; }

.source-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.source-bar-fill {
    height: 20px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    padding: 0 8px;
    font-size: 0.65rem;
    font-weight: 600;
    color: #fff;
    min-width: 28px;
    transition: width 0.5s ease;
}
.source-label {
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 80px;
}
.source-count {
    font-size: 0.75rem;
    font-weight: 700;
    min-width: 30px;
    text-align: right;
}
</style>

<!-- Header -->
<div class="mkt-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3><i class="bi bi-megaphone-fill"></i> Centro de Marketing</h3>
            <p>Métricas, campañas y herramientas de crecimiento — <?= date('F Y') ?></p>
        </div>
        <a href="analytics.php" class="btn btn-outline-light btn-sm"><i class="bi bi-graph-up-arrow"></i> Analytics Detallados</a>
    </div>
</div>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi-item" style="border-left: 3px solid #6366f1;">
        <i class="bi bi-send-fill kpi-icon" style="color: #6366f1;"></i>
        <div class="kpi-number" style="color: #6366f1;"><?= $campanasActivas ?></div>
        <div class="kpi-label">Campañas Activas</div>
        <div class="kpi-sub text-muted"><?= $campanasTotal ?> total</div>
    </div>
    <div class="kpi-item" style="border-left: 3px solid #10b981;">
        <i class="bi bi-envelope-check kpi-icon" style="color: #10b981;"></i>
        <div class="kpi-number" style="color: #10b981;"><?= number_format($campanaStats['total_enviados']) ?></div>
        <div class="kpi-label">Emails Enviados</div>
        <div class="kpi-sub">
            <span class="<?= $tasaApertura > 20 ? 'kpi-trend-up' : 'kpi-trend-down' ?>">
                <?= $tasaApertura ?>% apertura
            </span>
            · <?= $tasaClicks ?>% clicks
        </div>
    </div>
    <div class="kpi-item" style="border-left: 3px solid #3b82f6;">
        <i class="bi bi-person-plus-fill kpi-icon" style="color: #3b82f6;"></i>
        <div class="kpi-number" style="color: #3b82f6;"><?= $leadsEsteMes ?></div>
        <div class="kpi-label">Leads Este Mes</div>
        <div class="kpi-sub">
            <?php if ($leadsTrend > 0): ?>
                <span class="kpi-trend-up"><i class="bi bi-arrow-up"></i> +<?= $leadsTrend ?>%</span>
            <?php elseif ($leadsTrend < 0): ?>
                <span class="kpi-trend-down"><i class="bi bi-arrow-down"></i> <?= $leadsTrend ?>%</span>
            <?php else: ?>
                <span class="text-muted">= vs mes anterior</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="kpi-item" style="border-left: 3px solid #f59e0b;">
        <i class="bi bi-link-45deg kpi-icon" style="color: #f59e0b;"></i>
        <div class="kpi-number" style="color: #f59e0b;"><?= number_format($triggerClicks) ?></div>
        <div class="kpi-label">Clicks Trigger Links</div>
        <div class="kpi-sub text-muted"><?= $triggerClicksMes ?> este mes · <?= $triggerLinksTotal ?> links</div>
    </div>
    <div class="kpi-item" style="border-left: 3px solid #ec4899;">
        <i class="bi bi-star-fill kpi-icon" style="color: #ec4899;"></i>
        <div class="kpi-number" style="color: #ec4899;">
            <?= $reputacionStats['media'] ? number_format($reputacionStats['media'], 1) : '—' ?>
        </div>
        <div class="kpi-label">Valoración Media</div>
        <div class="kpi-sub">
            <span class="text-muted"><?= $reputacionStats['completadas'] ?? 0 ?> reseñas</span>
            · <?= $tasaConversion ?>% conversión
        </div>
    </div>
    <div class="kpi-item" style="border-left: 3px solid #06b6d4;">
        <i class="bi bi-share-fill kpi-icon" style="color: #06b6d4;"></i>
        <div class="kpi-number" style="color: #06b6d4;"><?= $socialPublicados ?></div>
        <div class="kpi-label">Posts Publicados</div>
        <div class="kpi-sub text-muted"><?= $socialProgramados ?> programados · <?= $socialPosts ?> total</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="section-head" style="background: #eef2ff; color: #4338ca;">
    <i class="bi bi-lightning-charge-fill"></i> ACCIONES RÁPIDAS
</div>
<div class="quick-actions-grid">
    <a href="<?= APP_URL ?>/modules/campanas/index.php" class="qa-btn">
        <i class="bi bi-send text-primary"></i> Nueva Campaña
    </a>
    <a href="trigger_links.php" class="qa-btn">
        <i class="bi bi-link-45deg text-warning"></i> Trigger Link
    </a>
    <a href="reputacion.php" class="qa-btn">
        <i class="bi bi-star text-danger"></i> Solicitar Reseña
    </a>
    <a href="<?= APP_URL ?>/modules/landing/index.php" class="qa-btn">
        <i class="bi bi-file-earmark-richtext text-info"></i> Landing Page
    </a>
    <a href="<?= APP_URL ?>/modules/social/index.php" class="qa-btn">
        <i class="bi bi-instagram text-danger"></i> Post Social
    </a>
    <a href="<?= APP_URL ?>/modules/formularios/index.php" class="qa-btn">
        <i class="bi bi-ui-checks-grid text-success"></i> Formulario
    </a>
    <a href="<?= APP_URL ?>/modules/ab-testing/index.php" class="qa-btn">
        <i class="bi bi-arrow-left-right text-purple" style="color:#8b5cf6"></i> A/B Test
    </a>
    <a href="<?= APP_URL ?>/modules/email/compose.php" class="qa-btn">
        <i class="bi bi-envelope-plus text-primary"></i> Enviar Email
    </a>
</div>

<!-- Row: Campañas + Funnel -->
<div class="row g-3 mb-3">
    <!-- Campañas recientes -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                <span class="section-head mb-0" style="background: #dbeafe; color: #1e40af;">
                    <i class="bi bi-send"></i> CAMPAÑAS
                </span>
                <a href="<?= APP_URL ?>/modules/campanas/index.php" class="btn btn-sm btn-outline-primary">Ver todas</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($campanasRecientes)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-send fs-1 d-block mb-2 opacity-25"></i>
                        <p class="mb-2">Sin campañas aún</p>
                        <a href="<?= APP_URL ?>/modules/campanas/index.php" class="btn btn-sm btn-primary">Crear primera campaña</a>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="mini-table table table-hover mb-0">
                        <thead><tr>
                            <th>Campaña</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th class="text-center">Enviados</th>
                            <th class="text-center">Abiertos</th>
                            <th class="text-center">Clicks</th>
                            <th>Rendimiento</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($campanasRecientes as $c):
                            $cApertura = $c['enviados'] > 0 ? round(($c['abiertos']/$c['enviados'])*100) : 0;
                            $cClicks = $c['enviados'] > 0 ? round(($c['clicks']/$c['enviados'])*100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= APP_URL ?>/modules/campanas/editor.php?id=<?= $c['id'] ?>" class="text-decoration-none fw-bold">
                                        <?= sanitize($c['nombre']) ?>
                                    </a>
                                </td>
                                <td>
                                    <i class="bi bi-<?= $c['tipo']==='email'?'envelope':($c['tipo']==='sms'?'phone':'shuffle') ?> text-muted"></i>
                                    <?= ucfirst($c['tipo']) ?>
                                </td>
                                <td><span class="badge bg-<?= $estadoClases[$c['estado']] ?? 'secondary' ?>"><?= ucfirst($c['estado']) ?></span></td>
                                <td class="text-center fw-bold"><?= number_format($c['enviados']) ?></td>
                                <td class="text-center"><?= number_format($c['abiertos']) ?></td>
                                <td class="text-center"><?= number_format($c['clicks']) ?></td>
                                <td style="min-width: 120px;">
                                    <div class="funnel-bar">
                                        <div class="funnel-fill" style="width:<?= $cApertura ?>%; background: linear-gradient(90deg, #6366f1, #818cf8);"></div>
                                    </div>
                                    <div class="d-flex justify-content-between" style="font-size:0.6rem; color:#94a3b8;">
                                        <span><?= $cApertura ?>% apertura</span>
                                        <span><?= $cClicks ?>% clicks</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Leads por origen -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent py-2">
                <span class="section-head mb-0" style="background: #dbeafe; color: #1e40af;">
                    <i class="bi bi-funnel"></i> LEADS POR ORIGEN
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($leadsPorOrigen)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-funnel fs-2 d-block mb-2 opacity-25"></i>
                        <small>Sin datos este mes</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($leadsPorOrigen as $lo):
                        $pct = $totalLeadsPorOrigen > 0 ? round(($lo['total'] / $totalLeadsPorOrigen) * 100) : 0;
                        $color = $origenColores[$lo['origen'] ?? 'otro'] ?? '#64748b';
                    ?>
                    <div class="source-bar">
                        <span class="source-label"><?= ucfirst(sanitize($lo['origen'] ?? 'Otro')) ?></span>
                        <div style="flex:1;">
                            <div class="source-bar-fill" style="width:<?= max(15,$pct) ?>%; background:<?= $color ?>;">
                                <?= $pct ?>%
                            </div>
                        </div>
                        <span class="source-count"><?= $lo['total'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-2">
                        <small class="text-muted"><?= $totalLeadsPorOrigen ?> leads totales este mes</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row: Trigger Links + Reputación -->
<div class="row g-3 mb-3">
    <!-- Trigger Links Analytics -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                <span class="section-head mb-0" style="background: #fef3c7; color: #92400e;">
                    <i class="bi bi-link-45deg"></i> TRIGGER LINKS
                </span>
                <a href="trigger_links.php" class="btn btn-sm btn-outline-warning">Gestionar</a>
            </div>
            <div class="card-body">
                <!-- Mini chart: clicks últimos 7 días -->
                <div class="mb-3">
                    <small class="text-muted fw-bold" style="font-size:0.65rem; letter-spacing:0.5px;">CLICKS ÚLTIMOS 7 DÍAS</small>
                    <div class="bar-chart-container mt-1">
                        <?php foreach ($ultimos7 as $dia => $clicks): ?>
                        <div class="bar-col">
                            <div class="bar-value"><?= $clicks ?></div>
                            <div class="bar-fill" style="height: <?= ($clicks / $maxClickDia) * 100 ?>%;"></div>
                            <div class="bar-label"><?= date('D', strtotime($dia)) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Top links -->
                <?php if (!empty($topTrigger)): ?>
                <?php foreach ($topTrigger as $tl): ?>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color: rgba(0,0,0,0.04) !important;">
                    <div>
                        <div class="fw-bold small"><?= sanitize($tl['nombre']) ?></div>
                        <div style="font-size:0.65rem;" class="text-muted"><?= sanitize(mb_strimwidth($tl['url_destino'], 0, 40, '...')) ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold" style="color:#f59e0b;"><?= number_format($tl['total_clicks']) ?></div>
                        <div style="font-size:0.6rem;" class="text-muted">clicks</div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-link-45deg fs-2 d-block mb-2 opacity-25"></i>
                        <small>Sin trigger links</small><br>
                        <a href="trigger_links.php" class="btn btn-sm btn-warning text-white mt-2">Crear primero</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reputación -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                <span class="section-head mb-0" style="background: #fce7f3; color: #9d174d;">
                    <i class="bi bi-star-fill"></i> REPUTACIÓN
                </span>
                <a href="reputacion.php" class="btn btn-sm btn-outline-danger">Gestionar</a>
            </div>
            <div class="card-body">
                <!-- Stars display -->
                <div class="text-center mb-3">
                    <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; line-height: 1;">
                        <?= $reputacionStats['media'] ? number_format($reputacionStats['media'], 1) : '—' ?>
                    </div>
                    <div class="star-display">
                        <?php
                        $media = floatval($reputacionStats['media'] ?? 0);
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= floor($media)) echo '<i class="bi bi-star-fill"></i>';
                            elseif ($i - 0.5 <= $media) echo '<i class="bi bi-star-half"></i>';
                            else echo '<i class="bi bi-star star-empty"></i>';
                        }
                        ?>
                    </div>
                    <div class="mt-1">
                        <small class="text-muted"><?= $reputacionStats['completadas'] ?? 0 ?> reseñas completadas de <?= $reputacionStats['total'] ?? 0 ?> solicitudes</small>
                    </div>
                </div>

                <!-- Conversion funnel -->
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-bold text-primary"><?= $reputacionStats['total'] ?? 0 ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Solicitadas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-bold text-warning"><?= $reputacionStats['enviadas'] ?? 0 ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Enviadas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-bold text-success"><?= $reputacionStats['completadas'] ?? 0 ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Completadas</div>
                        </div>
                    </div>
                </div>

                <!-- Últimas solicitudes -->
                <?php if (!empty($ultimasResenas)): ?>
                <?php foreach (array_slice($ultimasResenas, 0, 3) as $rs): ?>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color: rgba(0,0,0,0.04) !important;">
                    <div>
                        <div class="small fw-bold"><?= sanitize(($rs['cli_nombre'] ?? '') . ' ' . ($rs['cli_apellidos'] ?? '')) ?></div>
                        <div style="font-size:0.65rem;" class="text-muted"><?= formatFecha($rs['created_at']) ?></div>
                    </div>
                    <div>
                        <?php if ($rs['valoracion']): ?>
                            <span class="star-display"><?= str_repeat('★', $rs['valoracion']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-<?= $rs['estado']==='completada'?'success':($rs['estado']==='enviada'?'primary':'secondary') ?>" style="font-size:0.65rem;">
                                <?= ucfirst($rs['estado']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-2">
                        <small>Sin solicitudes aún</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row: Social + Formularios -->
<div class="row g-3 mb-3">
    <!-- Social Media -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                <span class="section-head mb-0" style="background: #e0f2fe; color: #0369a1;">
                    <i class="bi bi-share"></i> REDES SOCIALES
                </span>
                <a href="<?= APP_URL ?>/modules/social/index.php" class="btn btn-sm btn-outline-info">Gestionar</a>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="rounded p-2" style="background: rgba(37,211,102,0.08);">
                            <div class="fw-bold" style="color:#25d366;"><?= $socialPublicados ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Publicados</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background: rgba(99,102,241,0.08);">
                            <div class="fw-bold" style="color:#6366f1;"><?= $socialProgramados ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Programados</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background: rgba(100,116,139,0.08);">
                            <div class="fw-bold text-secondary"><?= $socialPosts ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Total</div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($socialRecientes)): ?>
                    <?php foreach (array_slice($socialRecientes, 0, 4) as $sp):
                        $socialEstadoC = ['borrador'=>'secondary','programado'=>'primary','publicado'=>'success','error'=>'danger'];
                    ?>
                    <div class="d-flex align-items-center justify-content-between py-2 border-bottom" style="border-color: rgba(0,0,0,0.04) !important;">
                        <div style="flex:1; min-width:0;">
                            <div class="small text-truncate"><?= sanitize(mb_strimwidth($sp['contenido'], 0, 60, '...')) ?></div>
                            <div style="font-size:0.65rem;" class="text-muted"><?= formatFechaHora($sp['created_at']) ?></div>
                        </div>
                        <span class="badge bg-<?= $socialEstadoC[$sp['estado']] ?? 'secondary' ?> ms-2" style="font-size:0.6rem;">
                            <?= ucfirst($sp['estado']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-share fs-2 d-block mb-2 opacity-25"></i>
                        <small>Sin posts sociales</small><br>
                        <a href="<?= APP_URL ?>/modules/social/index.php" class="btn btn-sm btn-info text-white mt-2">Crear post</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formularios -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2">
                <span class="section-head mb-0" style="background: #d1fae5; color: #065f46;">
                    <i class="bi bi-ui-checks-grid"></i> FORMULARIOS & CAPTACIÓN
                </span>
                <a href="<?= APP_URL ?>/modules/formularios/index.php" class="btn btn-sm btn-outline-success">Gestionar</a>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4">
                        <div class="rounded p-2" style="background: rgba(16,185,129,0.08);">
                            <div class="fw-bold text-success"><?= $formulariosActivos ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Activos</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background: rgba(59,130,246,0.08);">
                            <div class="fw-bold text-primary"><?= $formEnviosMes ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Envíos / Mes</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="rounded p-2" style="background: rgba(245,158,11,0.08);">
                            <div class="fw-bold text-warning"><?= $emailsEnviados ?></div>
                            <div style="font-size:0.6rem;" class="text-muted text-uppercase">Emails / Mes</div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($formulariosLista)): ?>
                    <table class="mini-table table table-sm mb-0">
                        <thead><tr><th>Formulario</th><th class="text-center">Este Mes</th><th class="text-center">Total</th><th>Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($formulariosLista as $f): ?>
                        <tr>
                            <td class="fw-bold"><?= sanitize($f['nombre']) ?></td>
                            <td class="text-center"><?= $f['envios_mes'] ?></td>
                            <td class="text-center"><?= $f['total_envios'] ?></td>
                            <td><span class="badge bg-<?= $f['activo'] ? 'success' : 'secondary' ?>" style="font-size:0.6rem;"><?= $f['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-ui-checks-grid fs-2 d-block mb-2 opacity-25"></i>
                        <small>Sin formularios</small><br>
                        <a href="<?= APP_URL ?>/modules/formularios/index.php" class="btn btn-sm btn-success mt-2">Crear formulario</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tools Directory -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-2">
        <span class="section-head mb-0" style="background: #f1f5f9; color: #475569;">
            <i class="bi bi-grid-3x3-gap"></i> TODAS LAS HERRAMIENTAS
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/campanas/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-send text-primary"></i> Campañas Drip
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="trigger_links.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-link-45deg text-warning"></i> Trigger Links
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="reputacion.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-star text-danger"></i> Reputación
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/email/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-envelope text-info"></i> Email Marketing
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/email/plantillas.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-envelope-paper text-secondary"></i> Plantillas
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/social/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-share text-success"></i> Redes Sociales
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/formularios/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-ui-checks-grid text-success"></i> Formularios
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/funnels/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-funnel text-primary"></i> Funnels
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/landing/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-file-earmark-richtext text-info"></i> Landing Pages
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/ab-testing/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-arrow-left-right" style="color:#8b5cf6"></i> A/B Testing
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/ads/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-badge-ad text-danger"></i> Ads Report
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="<?= APP_URL ?>/modules/blog/index.php" class="qa-btn h-100 w-100">
                    <i class="bi bi-journal-richtext text-secondary"></i> Blog / SEO
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
