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
    $fid = intval(post('factura_id'));

    if (($accion === 'cambiar_estado' || $accion === 'eliminar') && $fid > 0 && !isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM facturas WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$fid]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre esta factura.');
            header('Location: index.php');
            exit;
        }
    }

    if ($accion === 'cambiar_estado') {
        $estado = post('nuevo_estado');
        $validos = ['borrador','enviada','pagada','vencida','cancelada'];
        if (in_array($estado, $validos)) {
            $db->prepare("UPDATE facturas SET estado = ? WHERE id = ?")->execute([$estado, $fid]);
            setFlash('success', 'Estado actualizado.');
        }
    }
    if ($accion === 'eliminar') {
        $db->prepare("DELETE FROM facturas WHERE id = ?")->execute([$fid]);
        setFlash('success', 'Factura eliminada.');
    }
    header('Location: index.php');
    exit;
}

$pageTitle = 'Facturacion';
require_once __DIR__ . '/../../includes/header.php';

$filtroEstado = get('estado');
$busqueda = get('q');
$page = max(1, intval(get('page', 1)));

$where = '1=1';
$params = [];
if (!isAdmin()) { $where .= ' AND f.usuario_id = ?'; $params[] = currentUserId(); }
if ($filtroEstado) { $where .= ' AND f.estado = ?'; $params[] = $filtroEstado; }
if ($busqueda) { $where .= ' AND (f.numero LIKE ? OR f.concepto LIKE ? OR c.nombre LIKE ?)'; $b = "%$busqueda%"; $params = array_merge($params, [$b,$b,$b]); }

$total = $db->prepare("SELECT COUNT(*) FROM facturas f LEFT JOIN clientes c ON f.cliente_id = c.id WHERE $where");
$total->execute($params);
$pagination = paginate($total->fetchColumn(), 20, $page);

$stmt = $db->prepare("SELECT f.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos FROM facturas f LEFT JOIN clientes c ON f.cliente_id = c.id WHERE $where ORDER BY f.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmt->execute($params);
$facturas = $stmt->fetchAll();

// Stats
$statsSql = "SELECT COALESCE(SUM(total),0) as total_facturado, COALESCE(SUM(CASE WHEN estado='pagada' THEN total ELSE 0 END),0) as cobrado, COALESCE(SUM(CASE WHEN estado IN('enviada','borrador') THEN total ELSE 0 END),0) as pendiente, COALESCE(SUM(CASE WHEN estado='vencida' THEN total ELSE 0 END),0) as vencido FROM facturas" . (isAdmin() ? '' : ' WHERE usuario_id = ?');
$statsStmt = $db->prepare($statsSql);
$statsStmt->execute(isAdmin() ? [] : [currentUserId()]);
$stats = $statsStmt->fetch();

$estadoClases = ['borrador'=>'secondary','enviada'=>'primary','pagada'=>'success','vencida'=>'danger','cancelada'=>'dark'];
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-3">
            <div class="fs-5 fw-bold"><?= formatPrecio($stats['total_facturado']) ?></div>
            <small class="text-muted">Total facturado</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-3">
            <div class="fs-5 fw-bold text-success"><?= formatPrecio($stats['cobrado']) ?></div>
            <small class="text-muted">Cobrado</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-3">
            <div class="fs-5 fw-bold text-warning"><?= formatPrecio($stats['pendiente']) ?></div>
            <small class="text-muted">Pendiente</small>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-3">
            <div class="fs-5 fw-bold text-danger"><?= formatPrecio($stats['vencido']) ?></div>
            <small class="text-muted">Vencido</small>
        </div></div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2">
        <select name="estado" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            <?php foreach ($estadoClases as $e => $cl): ?>
            <option value="<?= $e ?>" <?= $filtroEstado === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar..." value="<?= sanitize($busqueda) ?>" style="width:200px">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex gap-2">
        <a href="config.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Config</a>
        <a href="form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva Factura</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Numero</th><th>Cliente</th><th>Concepto</th><th>Total</th><th>Estado</th><th>Emision</th><th>Vencimiento</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($facturas as $f): ?>
                <tr>
                    <td><strong><?= sanitize($f['numero']) ?></strong></td>
                    <td><?= sanitize(($f['cliente_nombre'] ?? '') . ' ' . ($f['cliente_apellidos'] ?? '')) ?: '<span class="text-muted">-</span>' ?></td>
                    <td><?= sanitize(mb_strimwidth($f['concepto'], 0, 40, '...')) ?></td>
                    <td class="fw-bold"><?= formatPrecio($f['total']) ?></td>
                    <td><span class="badge bg-<?= $estadoClases[$f['estado']] ?? 'secondary' ?>"><?= ucfirst($f['estado']) ?></span></td>
                    <td><small><?= formatFecha($f['fecha_emision']) ?></small></td>
                    <td><small><?= $f['fecha_vencimiento'] ? formatFecha($f['fecha_vencimiento']) : '-' ?></small></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <div class="btn-group btn-group-sm">
                                <a href="ver.php?id=<?= $f['id'] ?>" class="btn btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <a href="form.php?id=<?= $f['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="Cambiar estado"><i class="bi bi-arrow-repeat"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($estadoClases as $est => $cl): if ($est === $f['estado']) continue; ?>
                                    <li>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="factura_id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $est ?>">
                                            <button class="dropdown-item"><span class="badge bg-<?= $cl ?> me-1">&nbsp;</span> <?= ucfirst($est) ?></button>
                                        </form>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta factura?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="factura_id" value="<?= $f['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($facturas)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-receipt fs-1 d-block mb-2"></i>No hay facturas</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= renderPagination($pagination, 'index.php?estado=' . urlencode($filtroEstado) . '&q=' . urlencode($busqueda)) ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
