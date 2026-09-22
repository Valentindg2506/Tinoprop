<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');
    $linkId = intval(post('link_id'));

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre = trim(post('nombre'));
        $url_destino = trim(post('url_destino'));
        $accion_tipo = post('accion_tipo', 'ninguna');
        $accion_valor = trim(post('accion_valor'));

        if (empty($nombre) || empty($url_destino)) {
            setFlash('danger', 'Nombre y URL destino son obligatorios.');
        } else {
            if ($accion === 'crear') {
                $codigo = substr(bin2hex(random_bytes(4)), 0, 8);
                $db->prepare("INSERT INTO trigger_links (nombre, codigo, url_destino, accion_tipo, accion_valor, usuario_id) VALUES (?,?,?,?,?,?)")
                    ->execute([$nombre, $codigo, $url_destino, $accion_tipo, $accion_valor, currentUserId()]);
                setFlash('success', 'Trigger Link creado.');
            } else {
                if (!isAdmin()) {
                    $ownerStmt = $db->prepare("SELECT usuario_id FROM trigger_links WHERE id = ? LIMIT 1");
                    $ownerStmt->execute([$linkId]);
                    $ownerId = intval($ownerStmt->fetchColumn());
                    if ($ownerId !== intval(currentUserId())) {
                        setFlash('danger', 'No tienes permisos sobre este trigger link.');
                        header('Location: trigger_links.php');
                        exit;
                    }
                }
                $db->prepare("UPDATE trigger_links SET nombre=?, url_destino=?, accion_tipo=?, accion_valor=? WHERE id=?")
                    ->execute([$nombre, $url_destino, $accion_tipo, $accion_valor, $linkId]);
                setFlash('success', 'Trigger Link actualizado.');
            }
        }
    }
    if ($accion === 'eliminar') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM trigger_links WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$linkId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre este trigger link.');
                header('Location: trigger_links.php');
                exit;
            }
        }
        $db->prepare("DELETE FROM trigger_links WHERE id = ?")->execute([$linkId]);
        setFlash('success', 'Eliminado.');
    }
    if ($accion === 'toggle') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM trigger_links WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$linkId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre este trigger link.');
                header('Location: trigger_links.php');
                exit;
            }
        }
        $db->prepare("UPDATE trigger_links SET activo = NOT activo WHERE id = ?")->execute([$linkId]);
    }
    header('Location: trigger_links.php');
    exit;
}

$pageTitle = 'Trigger Links';
require_once __DIR__ . '/../../includes/header.php';

$links = $db->query("SELECT * FROM trigger_links ORDER BY created_at DESC")->fetchAll();

// Analytics: clicks por link últimos 30 días
$clicksStats = [];
try {
    $stmt30 = $db->query("SELECT link_id, DATE(created_at) as dia, COUNT(*) as total
        FROM trigger_clicks
        WHERE created_at >= CURDATE() - INTERVAL 30 DAY
        GROUP BY link_id, DATE(created_at)
        ORDER BY dia ASC");
    $raw = $stmt30->fetchAll();
    foreach ($raw as $r) {
        $clicksStats[$r['link_id']][$r['dia']] = $r['total'];
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Total clicks últimos 30 días
$totalClicks30d = 0;
try { $totalClicks30d = $db->query("SELECT COUNT(*) FROM trigger_clicks WHERE created_at >= CURDATE() - INTERVAL 30 DAY")->fetchColumn(); } catch (Exception $e) {
    error_log($e->getMessage());
}

// Clicks últimos 7 días para el gráfico general
$ultimos7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $ultimos7[$d] = 0;
}
try {
    $stmt7 = $db->query("SELECT DATE(created_at) as dia, COUNT(*) as total FROM trigger_clicks WHERE created_at >= CURDATE() - INTERVAL 7 DAY GROUP BY DATE(created_at)");
    foreach ($stmt7->fetchAll() as $r) {
        if (isset($ultimos7[$r['dia']])) $ultimos7[$r['dia']] = $r['total'];
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}
$maxClick7 = max(1, max($ultimos7));

// Clicks recientes detallados
$clicksRecientes = [];
try {
    $clicksRecientes = $db->query("SELECT tc.*, tl.nombre as link_nombre
        FROM trigger_clicks tc
        LEFT JOIN trigger_links tl ON tc.link_id = tl.id
        ORDER BY tc.created_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
}

// Vista: ¿detalle de un link?
$verDetalle = isset($_GET['detalle']) ? intval($_GET['detalle']) : 0;
$detalleLink = null;
$detalleClicks = [];
if ($verDetalle) {
    $stmt = $db->prepare("SELECT * FROM trigger_links WHERE id = ?");
    $stmt->execute([$verDetalle]);
    $detalleLink = $stmt->fetch();
    if ($detalleLink) {
        try {
            $detalleClicks = $db->prepare("SELECT * FROM trigger_clicks WHERE link_id = ? ORDER BY created_at DESC LIMIT 50");
            $detalleClicks->execute([$verDetalle]);
            $detalleClicks = $detalleClicks->fetchAll();
        } catch (PDOException $e) { $detalleClicks = []; }
    }
}
?>

<style>
.tl-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px; }
.tl-stat { background: var(--bs-body-bg, #fff); border: 1px solid rgba(0,0,0,0.06); border-radius: 10px; padding: 14px; text-align: center; }
.tl-stat .stat-val { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.tl-stat .stat-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-top: 4px; }
.bar-chart { display: flex; align-items: flex-end; gap: 4px; height: 70px; }
.bar-c { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; }
.bar-f { width: 100%; min-height: 3px; border-radius: 3px 3px 0 0; background: linear-gradient(180deg, #f59e0b, #fbbf24); transition: height 0.4s; }
.bar-v { font-size: 0.6rem; font-weight: 700; color: #f59e0b; }
.bar-l { font-size: 0.55rem; color: #94a3b8; }
.click-log { font-size: 0.75rem; }
.click-log td { padding: 6px 10px; vertical-align: middle; }
.click-log th { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; padding: 6px 10px; }
.sparkline { display: inline-flex; align-items: flex-end; gap: 1px; height: 20px; }
.sparkline-bar { width: 3px; min-height: 1px; border-radius: 1px; background: #f59e0b; opacity: 0.7; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Marketing</a>
        <?php if ($detalleLink): ?>
            <a href="trigger_links.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Todos los links</a>
        <?php endif; ?>
    </div>
    <?php if (!$detalleLink): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="bi bi-plus-lg"></i> Nuevo Trigger Link</button>
    <?php endif; ?>
</div>

<?php if ($detalleLink): ?>
<!-- ═══ VISTA DETALLE ═══ -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h4 class="fw-bold mb-1"><?= sanitize($detalleLink['nombre']) ?></h4>
                <div class="small text-muted mb-2">
                    <code><?= APP_URL ?>/t.php?c=<?= sanitize($detalleLink['codigo']) ?></code>
                    <button class="btn btn-sm border-0 p-0 ms-1" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/t.php?c=<?= $detalleLink['codigo'] ?>')"><i class="bi bi-clipboard text-primary"></i></button>
                </div>
                <div class="small text-muted">
                    <i class="bi bi-arrow-right"></i> <?= sanitize($detalleLink['url_destino']) ?>
                </div>
            </div>
            <div class="text-end">
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; line-height: 1;"><?= number_format($detalleLink['total_clicks']) ?></div>
                <div class="small text-muted">clicks totales</div>
            </div>
        </div>
    </div>
</div>

<!-- Clicks log -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-2"><h6 class="mb-0"><i class="bi bi-list-ul"></i> Registro de Clicks (últimos 50)</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="click-log table table-hover mb-0">
                <thead class="table-light"><tr><th>#</th><th>Fecha</th><th>IP</th><th>Navegador</th><th>Referer</th></tr></thead>
                <tbody>
                <?php foreach ($detalleClicks as $idx => $dc): ?>
                <tr>
                    <td class="fw-bold text-muted"><?= $idx + 1 ?></td>
                    <td><?= formatFechaHora($dc['created_at']) ?></td>
                    <td><code class="small"><?= sanitize($dc['ip'] ?? '-') ?></code></td>
                    <td class="small"><?= sanitize(mb_strimwidth($dc['user_agent'] ?? '-', 0, 50, '...')) ?></td>
                    <td class="small text-muted"><?= sanitize(mb_strimwidth($dc['referer'] ?? '(directo)', 0, 40, '...')) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($detalleClicks)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Sin clicks registrados</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══ VISTA LISTADO ═══ -->

<!-- Stats -->
<div class="tl-stats">
    <div class="tl-stat" style="border-left: 3px solid #f59e0b;">
        <div class="stat-val" style="color:#f59e0b;"><?= count($links) ?></div>
        <div class="stat-label">Links Totales</div>
    </div>
    <div class="tl-stat" style="border-left: 3px solid #10b981;">
        <div class="stat-val" style="color:#10b981;"><?= count(array_filter($links, fn($l) => $l['activo'])) ?></div>
        <div class="stat-label">Activos</div>
    </div>
    <div class="tl-stat" style="border-left: 3px solid #3b82f6;">
        <div class="stat-val" style="color:#3b82f6;"><?= number_format(array_sum(array_column($links, 'total_clicks'))) ?></div>
        <div class="stat-label">Clicks Totales</div>
    </div>
    <div class="tl-stat" style="border-left: 3px solid #8b5cf6;">
        <div class="stat-val" style="color:#8b5cf6;"><?= $totalClicks30d ?></div>
        <div class="stat-label">Clicks (30 días)</div>
    </div>
</div>

<!-- Gráfico 7 días -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <small class="text-muted fw-bold" style="font-size:0.65rem; letter-spacing:0.5px;">CLICKS ÚLTIMOS 7 DÍAS</small>
            <small class="fw-bold" style="color:#f59e0b;"><?= array_sum($ultimos7) ?> clicks</small>
        </div>
        <div class="bar-chart">
            <?php foreach ($ultimos7 as $dia => $clicks): ?>
            <div class="bar-c">
                <div class="bar-v"><?= $clicks ?></div>
                <div class="bar-f" style="height: <?= max(4, ($clicks / $maxClick7) * 100) ?>%;"></div>
                <div class="bar-l"><?= date('D d', strtotime($dia)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Links Table -->
<div class="card border-0 shadow-sm mb-3">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>URL Corta</th>
                    <th>Destino</th>
                    <th>Acción</th>
                    <th class="text-center">Clicks</th>
                    <th>Tendencia</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $l):
                    // Sparkline últimos 14 días
                    $spark = [];
                    for ($i = 13; $i >= 0; $i--) {
                        $d = date('Y-m-d', strtotime("-$i days"));
                        $spark[] = $clicksStats[$l['id']][$d] ?? 0;
                    }
                    $sparkMax = max(1, max($spark));
                ?>
                <tr>
                    <td><strong><?= sanitize($l['nombre']) ?></strong></td>
                    <td>
                        <code class="small"><?= APP_URL ?>/t.php?c=<?= sanitize($l['codigo']) ?></code>
                        <button class="btn btn-sm border-0 p-0 ms-1" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/t.php?c=<?= $l['codigo'] ?>')"><i class="bi bi-clipboard text-primary"></i></button>
                    </td>
                    <td><small class="text-muted"><?= sanitize(mb_strimwidth($l['url_destino'],0,35,'...')) ?></small></td>
                    <td><span class="badge bg-secondary"><?= $l['accion_tipo'] ?></span></td>
                    <td class="text-center"><strong><?= number_format($l['total_clicks']) ?></strong></td>
                    <td>
                        <a href="?detalle=<?= $l['id'] ?>" class="text-decoration-none" title="Ver detalle">
                        <div class="sparkline">
                            <?php foreach ($spark as $sv): ?>
                            <div class="sparkline-bar" style="height: <?= max(1, ($sv/$sparkMax)*20) ?>px;"></div>
                            <?php endforeach; ?>
                        </div>
                        </a>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                            <button class="badge border-0 bg-<?= $l['activo'] ? 'success' : 'secondary' ?>" style="cursor:pointer"><?= $l['activo'] ? 'Activo' : 'Inactivo' ?></button>
                        </form>
                    </td>
                    <td class="text-end">
                        <a href="?detalle=<?= $l['id'] ?>" class="btn btn-sm btn-outline-info" title="Analytics"><i class="bi bi-graph-up"></i></a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')">
                            <?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="link_id" value="<?= $l['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($links)): ?><tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-link-45deg fs-1 d-block mb-2"></i>No hay trigger links</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent clicks log -->
<?php if (!empty($clicksRecientes)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent py-2"><h6 class="mb-0"><i class="bi bi-clock-history"></i> Clicks Recientes</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="click-log table table-hover mb-0">
                <thead class="table-light"><tr><th>Link</th><th>Fecha</th><th>IP</th><th>Referer</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($clicksRecientes, 0, 10) as $dc): ?>
                <tr>
                    <td class="fw-bold"><?= sanitize($dc['link_nombre'] ?? '-') ?></td>
                    <td><?= formatFechaHora($dc['created_at']) ?></td>
                    <td><code class="small"><?= sanitize($dc['ip'] ?? '-') ?></code></td>
                    <td class="small text-muted"><?= sanitize(mb_strimwidth($dc['referer'] ?? '(directo)', 0, 50, '...')) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // fin vista listado ?>

<!-- Modal Crear -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header"><h6 class="modal-title"><i class="bi bi-link-45deg"></i> Nuevo Trigger Link</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" name="nombre" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">URL destino *</label><input type="url" name="url_destino" class="form-control" required placeholder="https://..."></div>
                    <div class="mb-3">
                        <label class="form-label">Acción al hacer clic</label>
                        <select name="accion_tipo" class="form-select">
                            <option value="ninguna">Ninguna</option>
                            <option value="tag">Asignar tag</option>
                            <option value="notificacion">Enviar notificación</option>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Valor de la acción</label><input type="text" name="accion_valor" class="form-control" placeholder="Ej: ID del tag"><small class="text-muted">Para tag: ID del tag. Para notificación: mensaje.</small></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Crear</button></div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
