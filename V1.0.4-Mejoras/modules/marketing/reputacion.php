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

    if ($accion === 'guardar_config') {
        $db->prepare("UPDATE reputacion_config SET google_review_link=?, mensaje_solicitud=?, activo=? WHERE id=1")
            ->execute([trim(post('google_review_link')), trim(post('mensaje_solicitud')), post('activo') ? 1 : 0]);
        setFlash('success', 'Configuración guardada.');
    }
    if ($accion === 'solicitar') {
        $clienteId = intval(post('cliente_id'));
        $tipo = post('tipo', 'google');
        $cfg = $db->query("SELECT * FROM reputacion_config WHERE id = 1")->fetch();
        $db->prepare("INSERT INTO resenas_solicitudes (cliente_id, tipo, enlace_resena, estado, enviada_at) VALUES (?,?,?,'enviada',NOW())")
            ->execute([$clienteId, $tipo, $cfg['google_review_link'] ?? '']);
        setFlash('success', 'Solicitud de reseña creada.');
    }
    if ($accion === 'solicitar_masivo') {
        $ids = post('cliente_ids');
        $tipo = post('tipo', 'google');
        $cfg = $db->query("SELECT * FROM reputacion_config WHERE id = 1")->fetch();
        if ($ids) {
            $idsArr = array_filter(array_map('intval', explode(',', $ids)));
            $stmt = $db->prepare("INSERT INTO resenas_solicitudes (cliente_id, tipo, enlace_resena, estado, enviada_at) VALUES (?,?,?,'enviada',NOW())");
            $count = 0;
            foreach ($idsArr as $cid) {
                $stmt->execute([$cid, $tipo, $cfg['google_review_link'] ?? '']);
                $count++;
            }
            setFlash('success', "$count solicitudes creadas.");
        }
    }
    if ($accion === 'cambiar_estado') {
        $id = intval(post('solicitud_id'));
        $estado = post('nuevo_estado');
        $val = intval(post('valoracion')) ?: null;
        $db->prepare("UPDATE resenas_solicitudes SET estado=?, valoracion=?, completada_at=IF(?='completada',NOW(),completada_at) WHERE id=?")
            ->execute([$estado, $val, $estado, $id]);
        setFlash('success', 'Estado actualizado.');
    }
    if ($accion === 'eliminar') {
        $db->prepare("DELETE FROM resenas_solicitudes WHERE id = ?")->execute([intval(post('solicitud_id'))]);
        setFlash('success', 'Solicitud eliminada.');
    }
    header('Location: reputacion.php');
    exit;
}

$pageTitle = 'Reputación';
require_once __DIR__ . '/../../includes/header.php';

$cfg = $db->query("SELECT * FROM reputacion_config WHERE id = 1")->fetch();
$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(estado='pendiente') as pendientes,
    SUM(estado='enviada') as enviadas,
    SUM(estado='completada') as completadas,
    SUM(estado='ignorada') as ignoradas,
    AVG(CASE WHEN valoracion IS NOT NULL THEN valoracion END) as media,
    COUNT(CASE WHEN valoracion IS NOT NULL THEN 1 END) as con_valoracion
    FROM resenas_solicitudes")->fetch();

// Distribución de estrellas
$distEstrellas = $db->query("SELECT valoracion, COUNT(*) as total FROM resenas_solicitudes WHERE valoracion IS NOT NULL GROUP BY valoracion ORDER BY valoracion DESC")->fetchAll();
$totalValoraciones = $stats['con_valoracion'] ?? 0;
$distMap = [];
foreach ($distEstrellas as $d) { $distMap[$d['valoracion']] = $d['total']; }

// Filtro
$filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtroBusca = isset($_GET['q']) ? trim($_GET['q']) : '';

$whereConditions = [];
$whereParams = [];
if ($filtroEstado) {
    $whereConditions[] = "rs.estado = ?";
    $whereParams[] = $filtroEstado;
}
if ($filtroBusca) {
    $whereConditions[] = "(c.nombre LIKE ? OR c.apellidos LIKE ?)";
    $whereParams[] = "%$filtroBusca%";
    $whereParams[] = "%$filtroBusca%";
}
$whereSQL = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$stmtSol = $db->prepare("SELECT rs.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.email as cli_email, c.telefono as cli_telefono
    FROM resenas_solicitudes rs
    LEFT JOIN clientes c ON rs.cliente_id = c.id
    $whereSQL
    ORDER BY rs.created_at DESC LIMIT 100");
$stmtSol->execute($whereParams);
$solicitudes = $stmtSol->fetchAll();

$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Solicitudes este mes vs anterior
$esteMes = $db->query("SELECT COUNT(*) FROM resenas_solicitudes WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
$mesAnt = $db->query("SELECT COUNT(*) FROM resenas_solicitudes WHERE MONTH(created_at)=MONTH(CURDATE()-INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE()-INTERVAL 1 MONTH)")->fetchColumn();

$estadoClases = ['pendiente'=>'warning','enviada'=>'primary','completada'=>'success','ignorada'=>'secondary'];
$estadoIconos = ['pendiente'=>'clock','enviada'=>'send','completada'=>'check-circle','ignorada'=>'x-circle'];
?>

<style>
.rep-hero {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 50%, #fbbf24 100%);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    color: #78350f;
    position: relative;
    overflow: hidden;
}
.rep-hero::before {
    content: '';
    position: absolute;
    top: -30%; right: -10%;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(245,158,11,0.2) 0%, transparent 70%);
    border-radius: 50%;
}
.rep-score {
    font-size: 4rem;
    font-weight: 900;
    line-height: 1;
    color: #92400e;
    position: relative;
}
.star-lg { font-size: 1.5rem; color: #f59e0b; }
.star-lg-empty { font-size: 1.5rem; color: #d4a95e; opacity: 0.4; }

.dist-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.dist-stars { min-width: 75px; font-size: 0.8rem; color: #f59e0b; text-align: right; }
.dist-bar { flex: 1; height: 10px; background: rgba(0,0,0,0.06); border-radius: 5px; overflow: hidden; }
.dist-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #fbbf24); border-radius: 5px; transition: width 0.5s; }
.dist-count { min-width: 30px; font-size: 0.75rem; font-weight: 700; color: #64748b; }

.stat-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.stat-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid rgba(0,0,0,0.06);
    background: var(--bs-body-bg, #fff);
    text-decoration: none;
    color: var(--bs-body-color);
    transition: all 0.2s;
}
.stat-pill:hover, .stat-pill.active { box-shadow: 0 2px 8px rgba(0,0,0,0.1); transform: scale(1.03); }
.stat-pill .pill-count { font-size: 1rem; font-weight: 800; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Marketing</a>
    <div class="d-flex gap-2">
        <button class="btn btn-warning text-white btn-sm" data-bs-toggle="modal" data-bs-target="#modalSolicitar"><i class="bi bi-star"></i> Nueva Solicitud</button>
        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalMasivo"><i class="bi bi-people"></i> Envío Masivo</button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#configPanel"><i class="bi bi-gear"></i></button>
    </div>
</div>

<!-- Config Panel (collapsed) -->
<div class="collapse mb-3" id="configPanel">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-2"><h6 class="mb-0"><i class="bi bi-gear"></i> Configuración</h6></div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="accion" value="guardar_config">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Enlace Google Reviews</label>
                        <input type="url" name="google_review_link" class="form-control" value="<?= sanitize($cfg['google_review_link'] ?? '') ?>" placeholder="https://g.page/...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mensaje solicitud</label>
                        <textarea name="mensaje_solicitud" class="form-control" rows="2"><?= sanitize($cfg['mensaje_solicitud'] ?? '') ?></textarea>
                        <small class="text-muted">Usa {{nombre}} para el nombre del cliente</small>
                    </div>
                    <div class="col-md-2 d-flex flex-column justify-content-end">
                        <div class="form-check form-switch mb-2">
                            <input type="checkbox" name="activo" class="form-check-input" value="1" <?= ($cfg['activo'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label">Activo</label>
                        </div>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hero Score -->
<div class="rep-hero">
    <div class="row align-items-center">
        <div class="col-md-4 text-center">
            <div class="rep-score"><?= $stats['media'] ? number_format($stats['media'], 1) : '—' ?></div>
            <div class="mt-1">
                <?php
                $media = floatval($stats['media'] ?? 0);
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= floor($media)) echo '<i class="bi bi-star-fill star-lg"></i>';
                    elseif ($i - 0.5 <= $media) echo '<i class="bi bi-star-half star-lg"></i>';
                    else echo '<i class="bi bi-star star-lg-empty"></i>';
                }
                ?>
            </div>
            <div class="small mt-1" style="color:#92400e;"><?= $totalValoraciones ?> valoraciones</div>
        </div>
        <div class="col-md-4">
            <!-- Distribución estrellas -->
            <?php for ($s = 5; $s >= 1; $s--):
                $cnt = $distMap[$s] ?? 0;
                $pct = $totalValoraciones > 0 ? round(($cnt / $totalValoraciones) * 100) : 0;
            ?>
            <div class="dist-row">
                <div class="dist-stars"><?= str_repeat('★', $s) ?></div>
                <div class="dist-bar"><div class="dist-fill" style="width: <?= $pct ?>%;"></div></div>
                <div class="dist-count"><?= $cnt ?></div>
            </div>
            <?php endfor; ?>
        </div>
        <div class="col-md-4 text-center">
            <div style="font-size:2rem; font-weight:800; color:#92400e;"><?= $stats['total'] ?? 0 ?></div>
            <div class="small" style="color:#92400e;">solicitudes totales</div>
            <div class="mt-2 d-flex justify-content-center gap-3">
                <div>
                    <div class="fw-bold"><?= $esteMes ?></div>
                    <div style="font-size:0.6rem;" class="text-uppercase">Este mes</div>
                </div>
                <div>
                    <div class="fw-bold"><?= $mesAnt ?></div>
                    <div style="font-size:0.6rem;" class="text-uppercase">Mes anterior</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Filter Pills -->
<div class="stat-pills">
    <a href="reputacion.php" class="stat-pill <?= !$filtroEstado ? 'active' : '' ?>">
        <span class="pill-count"><?= $stats['total'] ?? 0 ?></span> Todas
    </a>
    <?php foreach (['pendiente','enviada','completada','ignorada'] as $est): ?>
    <a href="?estado=<?= $est ?>" class="stat-pill <?= $filtroEstado === $est ? 'active' : '' ?>">
        <i class="bi bi-<?= $estadoIconos[$est] ?> text-<?= $estadoClases[$est] ?>"></i>
        <span class="pill-count"><?= $stats[$est.'s'] ?? 0 ?></span> <?= ucfirst($est).'s' ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search bar -->
<div class="mb-3">
    <form class="d-flex gap-2" method="GET">
        <?php if ($filtroEstado): ?><input type="hidden" name="estado" value="<?= sanitize($filtroEstado) ?>"><?php endif; ?>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre del cliente..." value="<?= sanitize($filtroBusca) ?>" style="max-width:300px;">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        <?php if ($filtroBusca): ?><a href="reputacion.php<?= $filtroEstado ? "?estado=$filtroEstado" : '' ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a><?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:0.82rem;">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Valoración</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $s): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= sanitize(($s['cli_nombre'] ?? '') . ' ' . ($s['cli_apellidos'] ?? '')) ?></div>
                        </td>
                        <td>
                            <?php if ($s['cli_email']): ?><small class="d-block text-muted"><i class="bi bi-envelope"></i> <?= sanitize($s['cli_email']) ?></small><?php endif; ?>
                            <?php if ($s['cli_telefono']): ?><small class="d-block text-muted"><i class="bi bi-phone"></i> <?= sanitize($s['cli_telefono']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $s['tipo']==='google'?'danger':($s['tipo']==='whatsapp'?'success':'info') ?>">
                                <i class="bi bi-<?= $s['tipo']==='google'?'google':($s['tipo']==='whatsapp'?'whatsapp':'envelope') ?>"></i>
                                <?= ucfirst($s['tipo']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= $estadoClases[$s['estado']] ?>">
                                <i class="bi bi-<?= $estadoIconos[$s['estado']] ?>"></i> <?= ucfirst($s['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($s['valoracion']): ?>
                                <span style="color:#f59e0b; font-size: 0.9rem;"><?= str_repeat('★', $s['valoracion']) ?><?= str_repeat('☆', 5 - $s['valoracion']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?= formatFecha($s['created_at']) ?></small>
                            <?php if ($s['completada_at']): ?>
                                <br><small class="text-success"><i class="bi bi-check"></i> <?= formatFecha($s['completada_at']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" class="d-inline-flex gap-1 align-items-center">
                                <?= csrfField() ?>
                                <input type="hidden" name="accion" value="cambiar_estado">
                                <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                <select name="nuevo_estado" class="form-select form-select-sm" style="width:auto;font-size:0.72rem;" onchange="this.form.submit()">
                                    <option value="">Cambiar...</option>
                                    <?php foreach (['enviada','completada','ignorada'] as $e): ?>
                                        <option value="<?= $e ?>" <?= $s['estado']===$e?'selected':'' ?>><?= ucfirst($e) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="valoracion" class="form-control form-control-sm" style="width:54px;font-size:0.72rem;" min="1" max="5" placeholder="★" value="<?= $s['valoracion'] ?? '' ?>">
                            </form>
                            <form method="POST" class="d-inline ms-1" onsubmit="return confirm('¿Eliminar esta solicitud?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($solicitudes)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-star fs-1 d-block mb-2 opacity-25"></i>
                        <?= $filtroEstado || $filtroBusca ? 'Sin resultados para este filtro' : 'Sin solicitudes de reseñas' ?>
                    </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Solicitar -->
<div class="modal fade" id="modalSolicitar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="accion" value="solicitar">
                <div class="modal-header"><h6 class="modal-title"><i class="bi bi-star"></i> Nueva Solicitud de Reseña</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente *</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($clientes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Canal</label>
                        <select name="tipo" class="form-select">
                            <option value="google">Google Reviews</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning text-white">Enviar Solicitud</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Masivo -->
<div class="modal fade" id="modalMasivo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?><input type="hidden" name="accion" value="solicitar_masivo">
                <input type="hidden" name="cliente_ids" id="clienteIdsMasivo" value="">
                <div class="modal-header"><h6 class="modal-title"><i class="bi bi-people"></i> Envío Masivo de Solicitudes</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Canal</label>
                        <select name="tipo" class="form-select">
                            <option value="google">Google Reviews</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Clientes</label>
                        <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm" id="buscarClienteMasivo" placeholder="Buscar...">
                            </div>
                            <div class="form-check mb-1">
                                <input type="checkbox" class="form-check-input" id="selTodos">
                                <label class="form-check-label fw-bold" for="selTodos">Seleccionar todos</label>
                            </div>
                            <hr class="my-1">
                            <?php foreach ($clientes as $c): ?>
                            <div class="form-check mb-1 cliente-check-item">
                                <input type="checkbox" class="form-check-input cliente-check" value="<?= $c['id'] ?>" id="cl_<?= $c['id'] ?>">
                                <label class="form-check-label" for="cl_<?= $c['id'] ?>"><?= sanitize($c['nombre'].' '.$c['apellidos']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted"><span id="countSeleccion">0</span> clientes seleccionados</small>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-warning text-white">Enviar a Seleccionados</button></div>
            </form>
        </div>
    </div>
</div>

<script>
// Bulk select logic
document.addEventListener('DOMContentLoaded', function() {
    var checks = document.querySelectorAll('.cliente-check');
    var selTodos = document.getElementById('selTodos');
    var hiddenInput = document.getElementById('clienteIdsMasivo');
    var countSpan = document.getElementById('countSeleccion');
    var buscar = document.getElementById('buscarClienteMasivo');

    function updateSelection() {
        var ids = [];
        checks.forEach(function(c) { if (c.checked) ids.push(c.value); });
        hiddenInput.value = ids.join(',');
        countSpan.textContent = ids.length;
    }

    if (selTodos) {
        selTodos.addEventListener('change', function() {
            checks.forEach(function(c) {
                if (c.closest('.cliente-check-item').style.display !== 'none') c.checked = selTodos.checked;
            });
            updateSelection();
        });
    }
    checks.forEach(function(c) { c.addEventListener('change', updateSelection); });

    if (buscar) {
        buscar.addEventListener('keyup', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.cliente-check-item').forEach(function(item) {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
