<?php
$pageTitle = 'Analytics de Marketing';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

// Safe helpers
function _safeQ($db, $sql, $p = []) {
    try { $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) { return []; }
}
function _safeOne($db, $sql, $p = []) {
    try { $s = $db->prepare($sql); $s->execute($p); return $s->fetch(PDO::FETCH_ASSOC); } catch (PDOException $e) { return null; }
}
function _safeVal($db, $sql, $p = []) {
    try { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); } catch (PDOException $e) { return 0; }
}

// Período
$periodo = $_GET['periodo'] ?? '30';
$periodoLabel = ['7'=>'7 días', '30'=>'30 días', '90'=>'90 días', '365'=>'12 meses'];

// ═══ Leads por día (gráfico temporal) ═══
$leadsPorDia = _safeQ($db, "SELECT DATE(created_at) as dia, COUNT(*) as total
    FROM prospectos
    WHERE created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY DATE(created_at)
    ORDER BY dia ASC", [$periodo]);
$leadsMap = [];
foreach ($leadsPorDia as $l) { $leadsMap[$l['dia']] = $l['total']; }
$leadsDias = [];
for ($i = intval($periodo)-1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $leadsDias[$d] = $leadsMap[$d] ?? 0;
}
$maxLeadDia = max(1, max($leadsDias ?: [1]));

// ═══ Leads por origen ═══
$porOrigen = _safeQ($db, "SELECT COALESCE(origen,'desconocido') as origen, COUNT(*) as total
    FROM prospectos
    WHERE created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY COALESCE(origen,'desconocido') ORDER BY total DESC", [$periodo]);
// Fallback a clientes si prospectos no tiene datos
if (empty($porOrigen)) {
    $porOrigen = _safeQ($db, "SELECT COALESCE(origen,'desconocido') as origen, COUNT(*) as total
        FROM clientes
        WHERE created_at >= CURDATE() - INTERVAL ? DAY
        GROUP BY COALESCE(origen,'desconocido') ORDER BY total DESC", [$periodo]);
}
$totalOrigen = array_sum(array_column($porOrigen, 'total'));

// ═══ Leads por etapa pipeline ═══
$porEtapa = _safeQ($db, "SELECT etapa, COUNT(*) as total
    FROM prospectos
    WHERE created_at >= CURDATE() - INTERVAL ? DAY
    GROUP BY etapa ORDER BY total DESC", [$periodo]);
$etapaColores = [
    'nuevo_lead'=>'#3b82f6','contactado'=>'#06b6d4','calificado'=>'#10b981',
    'visita_programada'=>'#f59e0b','negociacion'=>'#8b5cf6','captado'=>'#22c55e',
    'descartado'=>'#ef4444','primer_contacto'=>'#64748b'
];

// ═══ Campañas rendimiento ═══
$campanasRend = _safeQ($db, "SELECT nombre, enviados, abiertos, clicks,
    CASE WHEN enviados > 0 THEN ROUND((abiertos/enviados)*100,1) ELSE 0 END as tasa_apertura,
    CASE WHEN enviados > 0 THEN ROUND((clicks/enviados)*100,1) ELSE 0 END as tasa_clicks
    FROM campanas WHERE enviados > 0 ORDER BY tasa_apertura DESC LIMIT 10");

// ═══ Trigger links top ═══
$triggerTop = _safeQ($db, "SELECT nombre, total_clicks, url_destino FROM trigger_links ORDER BY total_clicks DESC LIMIT 10");
$maxTrigger = max(1, max(array_column($triggerTop ?: [['total_clicks'=>1]], 'total_clicks')));

// ═══ Formularios rendimiento ═══
$formRend = _safeQ($db, "SELECT f.nombre,
    (SELECT COUNT(*) FROM formulario_envios WHERE formulario_id = f.id) as total_envios,
    (SELECT COUNT(*) FROM formulario_envios WHERE formulario_id = f.id AND created_at >= CURDATE() - INTERVAL ? DAY) as envios_periodo
    FROM formularios f ORDER BY total_envios DESC LIMIT 10", [$periodo]);

// ═══ UTM Data ═══
$utmSources = _safeQ($db, "SELECT utm_source, COUNT(*) as total
    FROM marketing_utm
    WHERE created_at >= CURDATE() - INTERVAL ? DAY AND utm_source != ''
    GROUP BY utm_source ORDER BY total DESC LIMIT 10", [$periodo]);
$utmMediums = _safeQ($db, "SELECT utm_medium, COUNT(*) as total
    FROM marketing_utm
    WHERE created_at >= CURDATE() - INTERVAL ? DAY AND utm_medium != ''
    GROUP BY utm_medium ORDER BY total DESC LIMIT 10", [$periodo]);
$utmCampaigns = _safeQ($db, "SELECT utm_campaign, COUNT(*) as total
    FROM marketing_utm
    WHERE created_at >= CURDATE() - INTERVAL ? DAY AND utm_campaign != ''
    GROUP BY utm_campaign ORDER BY total DESC LIMIT 10", [$periodo]);

// Colores para orígenes
$origColores = [
    'web'=>'#3b82f6','telefono'=>'#10b981','oficina'=>'#f59e0b','referido'=>'#8b5cf6',
    'portal'=>'#ef4444','otro'=>'#64748b','formulario'=>'#06b6d4','facebook'=>'#1877f2',
    'instagram'=>'#e4405f','google'=>'#ea4335','whatsapp'=>'#25d366','desconocido'=>'#94a3b8'
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.analytics-header {
    background: linear-gradient(135deg, #0f172a, #1e1b4b 60%, #4338ca);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 16px;
    color: #fff;
}
.analytics-header h4 { font-weight: 800; margin: 0; }
.analytics-header p { color: #a5b4fc; margin: 2px 0 0; font-size: 0.85rem; }
.periodo-tabs { display: flex; gap: 6px; }
.periodo-tab {
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    color: rgba(255,255,255,0.6);
    transition: all 0.2s;
}
.periodo-tab:hover { color: #fff; background: rgba(255,255,255,0.1); }
.periodo-tab.active { color: #fff; background: rgba(99,102,241,0.5); }

.chart-area {
    position: relative;
    height: 140px;
    display: flex;
    align-items: flex-end;
    gap: 1px;
    padding-bottom: 20px;
}
.chart-bar {
    flex: 1;
    min-height: 2px;
    border-radius: 2px 2px 0 0;
    background: linear-gradient(180deg, #6366f1, #a5b4fc);
    transition: height 0.3s;
    position: relative;
    cursor: pointer;
}
.chart-bar:hover { opacity: 0.85; }
.chart-bar:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1e1b4b;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.6rem;
    white-space: nowrap;
    z-index: 10;
}

.hbar { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.hbar-label { font-size: 0.72rem; font-weight: 600; min-width: 90px; }
.hbar-track { flex: 1; height: 18px; background: rgba(0,0,0,0.04); border-radius: 4px; overflow: hidden; }
.hbar-fill { height: 100%; border-radius: 4px; display: flex; align-items: center; padding: 0 6px; font-size: 0.6rem; font-weight: 700; color: #fff; min-width: 20px; transition: width 0.5s; }
.hbar-count { font-size: 0.72rem; font-weight: 700; min-width: 35px; text-align: right; }

.mini-stat { text-align: center; padding: 12px 8px; }
.mini-stat .val { font-size: 1.4rem; font-weight: 800; line-height: 1; }
.mini-stat .lbl { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-top: 2px; }

.sec-title {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748b;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sec-title i { font-size: 0.8rem; }
</style>

<!-- Header -->
<div class="analytics-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="index.php" class="text-white text-decoration-none" style="opacity:0.6;font-size:0.8rem;"><i class="bi bi-arrow-left"></i> Marketing</a>
            </div>
            <h4><i class="bi bi-graph-up-arrow"></i> Analytics</h4>
            <p>Rendimiento de marketing · últimos <?= $periodoLabel[$periodo] ?? "$periodo días" ?></p>
        </div>
        <div class="periodo-tabs">
            <?php foreach ($periodoLabel as $pKey => $pLabel): ?>
                <a href="?periodo=<?= $pKey ?>" class="periodo-tab <?= $periodo == $pKey ? 'active' : '' ?>"><?= $pLabel ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Leads Timeline -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="sec-title mb-0"><i class="bi bi-graph-up"></i> CAPTACIÓN DE LEADS</div>
            <div style="font-size:0.75rem;">
                <strong style="color:#6366f1;"><?= array_sum($leadsDias) ?></strong> <span class="text-muted">leads en <?= $periodoLabel[$periodo] ?? "$periodo d" ?></span>
            </div>
        </div>
        <div class="chart-area">
            <?php
            $showLabels = intval($periodo) <= 30;
            foreach ($leadsDias as $dia => $cnt): ?>
            <div class="chart-bar"
                 style="height: <?= max(2, ($cnt / $maxLeadDia) * 100) ?>%;"
                 data-tooltip="<?= date('d M', strtotime($dia)) ?>: <?= $cnt ?> leads"
                 title="<?= date('d M', strtotime($dia)) ?>: <?= $cnt ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($showLabels): ?>
        <div class="d-flex justify-content-between" style="font-size:0.55rem; color:#94a3b8; margin-top:2px;">
            <span><?= date('d M', strtotime("-" . (intval($periodo)-1) . " days")) ?></span>
            <span>Hoy</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Row: Origen + Etapa -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="sec-title"><i class="bi bi-funnel"></i> LEADS POR ORIGEN</div>
                <?php if (empty($porOrigen)): ?>
                    <div class="text-center text-muted py-4"><small>Sin datos en este período</small></div>
                <?php else: ?>
                    <?php foreach ($porOrigen as $po):
                        $pct = $totalOrigen > 0 ? round(($po['total']/$totalOrigen)*100) : 0;
                        $color = $origColores[$po['origen']] ?? '#64748b';
                    ?>
                    <div class="hbar">
                        <div class="hbar-label"><?= ucfirst(sanitize($po['origen'])) ?></div>
                        <div class="hbar-track">
                            <div class="hbar-fill" style="width:<?= max(8,$pct) ?>%; background:<?= $color ?>;"><?= $pct ?>%</div>
                        </div>
                        <div class="hbar-count"><?= $po['total'] ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="sec-title"><i class="bi bi-diagram-3"></i> LEADS POR ETAPA</div>
                <?php if (empty($porEtapa)): ?>
                    <div class="text-center text-muted py-4"><small>Sin datos en este período</small></div>
                <?php else: ?>
                    <?php $totalEtapa = array_sum(array_column($porEtapa, 'total'));
                    foreach ($porEtapa as $pe):
                        $pct = $totalEtapa > 0 ? round(($pe['total']/$totalEtapa)*100) : 0;
                        $color = $etapaColores[$pe['etapa']] ?? '#64748b';
                    ?>
                    <div class="hbar">
                        <div class="hbar-label"><?= ucfirst(str_replace('_',' ', sanitize($pe['etapa']))) ?></div>
                        <div class="hbar-track">
                            <div class="hbar-fill" style="width:<?= max(8,$pct) ?>%; background:<?= $color ?>;"><?= $pct ?>%</div>
                        </div>
                        <div class="hbar-count"><?= $pe['total'] ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row: Campañas + Trigger Links -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="sec-title"><i class="bi bi-send"></i> RENDIMIENTO DE CAMPAÑAS</div>
                <?php if (empty($campanasRend)): ?>
                    <div class="text-center text-muted py-4"><small>Sin campañas con envíos</small></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:0.75rem;">
                        <thead><tr><th>Campaña</th><th class="text-center">Envíos</th><th class="text-center">Apertura</th><th class="text-center">Clicks</th></tr></thead>
                        <tbody>
                        <?php foreach ($campanasRend as $cr): ?>
                        <tr>
                            <td class="fw-bold"><?= sanitize(mb_strimwidth($cr['nombre'], 0, 30, '...')) ?></td>
                            <td class="text-center"><?= number_format($cr['enviados']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $cr['tasa_apertura'] > 20 ? 'success' : ($cr['tasa_apertura'] > 10 ? 'warning' : 'danger') ?>" style="font-size:0.65rem;">
                                    <?= $cr['tasa_apertura'] ?>%
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $cr['tasa_clicks'] > 5 ? 'success' : ($cr['tasa_clicks'] > 2 ? 'warning' : 'secondary') ?>" style="font-size:0.65rem;">
                                    <?= $cr['tasa_clicks'] ?>%
                                </span>
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
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="sec-title"><i class="bi bi-link-45deg"></i> TOP TRIGGER LINKS</div>
                <?php if (empty($triggerTop)): ?>
                    <div class="text-center text-muted py-4"><small>Sin trigger links</small></div>
                <?php else: ?>
                    <?php foreach ($triggerTop as $tt): ?>
                    <div class="hbar">
                        <div class="hbar-label" title="<?= sanitize($tt['url_destino']) ?>"><?= sanitize(mb_strimwidth($tt['nombre'], 0, 20, '...')) ?></div>
                        <div class="hbar-track">
                            <div class="hbar-fill" style="width:<?= max(8, round(($tt['total_clicks']/$maxTrigger)*100)) ?>%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"><?= $tt['total_clicks'] ?></div>
                        </div>
                        <div class="hbar-count"><?= $tt['total_clicks'] ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- UTM Tracking -->
<?php if (!empty($utmSources) || !empty($utmMediums) || !empty($utmCampaigns)): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="sec-title"><i class="bi bi-link-45deg"></i> UTM TRACKING</div>
        <div class="row g-3">
            <div class="col-md-4">
                <h6 class="small fw-bold text-muted">Por Source</h6>
                <?php if (empty($utmSources)): ?><small class="text-muted">Sin datos</small>
                <?php else: ?>
                    <?php $maxUTM = max(array_column($utmSources, 'total'));
                    foreach ($utmSources as $us): ?>
                    <div class="hbar">
                        <div class="hbar-label"><?= sanitize($us['utm_source']) ?></div>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(10,round(($us['total']/$maxUTM)*100)) ?>%; background:#6366f1;"><?= $us['total'] ?></div></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h6 class="small fw-bold text-muted">Por Medium</h6>
                <?php if (empty($utmMediums)): ?><small class="text-muted">Sin datos</small>
                <?php else: ?>
                    <?php $maxUTM = max(array_column($utmMediums, 'total'));
                    foreach ($utmMediums as $um): ?>
                    <div class="hbar">
                        <div class="hbar-label"><?= sanitize($um['utm_medium']) ?></div>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(10,round(($um['total']/$maxUTM)*100)) ?>%; background:#10b981;"><?= $um['total'] ?></div></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h6 class="small fw-bold text-muted">Por Campaign</h6>
                <?php if (empty($utmCampaigns)): ?><small class="text-muted">Sin datos</small>
                <?php else: ?>
                    <?php $maxUTM = max(array_column($utmCampaigns, 'total'));
                    foreach ($utmCampaigns as $uc): ?>
                    <div class="hbar">
                        <div class="hbar-label"><?= sanitize(mb_strimwidth($uc['utm_campaign'],0,15,'...')) ?></div>
                        <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(10,round(($uc['total']/$maxUTM)*100)) ?>%; background:#f59e0b;"><?= $uc['total'] ?></div></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Formularios -->
<?php if (!empty($formRend)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="sec-title"><i class="bi bi-ui-checks-grid"></i> FORMULARIOS — ENVÍOS</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:0.75rem;">
                <thead><tr><th>Formulario</th><th class="text-center">Este Período</th><th class="text-center">Total</th><th>Barra</th></tr></thead>
                <tbody>
                <?php $maxForm = max(1, max(array_column($formRend, 'total_envios')));
                foreach ($formRend as $fr): ?>
                <tr>
                    <td class="fw-bold"><?= sanitize($fr['nombre']) ?></td>
                    <td class="text-center fw-bold" style="color:#10b981;"><?= $fr['envios_periodo'] ?></td>
                    <td class="text-center"><?= $fr['total_envios'] ?></td>
                    <td style="min-width:120px;">
                        <div style="height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?= round(($fr['total_envios']/$maxForm)*100) ?>%; background: linear-gradient(90deg, #10b981, #34d399); border-radius:4px;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
