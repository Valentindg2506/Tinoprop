<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $pid = intval(post('pid'));
    if ($pid > 0 && !isAdmin()) {
        $ownerStmt = $db->prepare("SELECT usuario_id FROM presupuestos WHERE id = ? LIMIT 1");
        $ownerStmt->execute([$pid]);
        $ownerId = intval($ownerStmt->fetchColumn());
        if ($ownerId !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos sobre este presupuesto.');
            header('Location: index.php');
            exit;
        }
    }
    if (post('accion') === 'cambiar_estado') {
        $validos = ['borrador','enviado','aceptado','rechazado','expirado','convertido'];
        $estado = post('nuevo_estado');
        if (in_array($estado, $validos)) {
            $db->prepare("UPDATE presupuestos SET estado=? WHERE id=?")->execute([$estado, $pid]);
        }
    }
    if (post('accion') === 'convertir_factura') {
        $p = $db->prepare("SELECT * FROM presupuestos WHERE id=?"); $p->execute([$pid]); $p=$p->fetch();
        if ($p && $p['estado'] === 'aceptado') {
            $config = $db->query("SELECT prefijo_factura, siguiente_numero FROM configuracion_pagos LIMIT 1")->fetch();
            $numero = ($config['prefijo_factura']??'FAC-') . str_pad($config['siguiente_numero']??1, 5, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO facturas (numero, cliente_id, propiedad_id, concepto, lineas, subtotal, iva_total, total, fecha_emision, token_pago, usuario_id) VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?)")
                ->execute([$numero, $p['cliente_id'], $p['propiedad_id'], $p['titulo'], $p['lineas'], $p['subtotal'], $p['iva_total'], $p['total'], $token, currentUserId()]);
            $facturaId = $db->lastInsertId();
            $db->exec("UPDATE configuracion_pagos SET siguiente_numero=siguiente_numero+1 WHERE id=1");
            $db->prepare("UPDATE presupuestos SET estado='convertido', factura_id=? WHERE id=?")->execute([$facturaId, $pid]);
            setFlash('success', 'Factura '.$numero.' creada desde presupuesto.');
        }
    }
    header('Location: index.php'); exit;
}

$pageTitle = 'Presupuestos';
require_once __DIR__ . '/../../includes/header.php';

$estado = get('estado', '');
$where = [];
$params = [];
if (!isAdmin()) {
    $where[] = 'p.usuario_id = ?';
    $params[] = currentUserId();
}
if ($estado) {
    $where[] = 'p.estado = ?';
    $params[] = $estado;
}
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
$presupuestos = $db->prepare("SELECT p.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos FROM presupuestos p LEFT JOIN clientes c ON p.cliente_id=c.id $whereSql ORDER BY p.created_at DESC");
$presupuestos->execute($params); $presupuestos=$presupuestos->fetchAll();

$estadoClases = ['borrador'=>'secondary','enviado'=>'primary','aceptado'=>'success','rechazado'=>'danger','expirado'=>'warning','convertido'=>'info'];
$statsSql = "SELECT estado, COUNT(*) as total, SUM(total) as suma FROM presupuestos" . (isAdmin() ? '' : ' WHERE usuario_id = ?') . " GROUP BY estado";
$statsStmt = $db->prepare($statsSql);
$statsStmt->execute(isAdmin() ? [] : [currentUserId()]);
$stats = $statsStmt->fetchAll(PDO::FETCH_UNIQUE);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-sm <?= !$estado?'btn-primary':'btn-outline-secondary' ?>">Todos</a>
        <?php foreach(['borrador','enviado','aceptado','rechazado'] as $e): ?>
        <a href="?estado=<?= $e ?>" class="btn btn-sm <?= $estado===$e?'btn-'.$estadoClases[$e]:'btn-outline-secondary' ?>"><?= ucfirst($e) ?></a>
        <?php endforeach; ?>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Presupuesto</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold mb-0"><?= count($presupuestos) ?></h4><small class="text-muted">Total</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold text-success mb-0"><?= number_format(floatval($stats['aceptado']['suma']??0),0,',','.') ?> &euro;</h4><small class="text-muted">Aceptados</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold text-primary mb-0"><?= number_format(floatval($stats['enviado']['suma']??0),0,',','.') ?> &euro;</h4><small class="text-muted">Pendientes</small></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3"><h4 class="fw-bold text-danger mb-0"><?= number_format(floatval($stats['rechazado']['suma']??0),0,',','.') ?> &euro;</h4><small class="text-muted">Rechazados</small></div></div>
</div>

<?php if (empty($presupuestos)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-file-earmark-text fs-1 d-block mb-3"></i><h5>No hay presupuestos</h5></div>
<?php else: ?>
<div class="card border-0 shadow-sm" style="overflow: visible;">
    <div class="table-responsive" style="overflow: visible;">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Numero</th><th>Cliente</th><th>Titulo</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Expira</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($presupuestos as $p): ?>
            <tr>
                <td class="fw-semibold"><?= sanitize($p['numero']) ?></td>
                <td><?= sanitize(($p['cli_nombre']??'').' '.($p['cli_apellidos']??'')) ?></td>
                <td><?= sanitize($p['titulo']) ?></td>
                <td class="fw-bold"><?= number_format($p['total'],2,',','.') ?> &euro;</td>
                <td><span class="badge bg-<?= $estadoClases[$p['estado']] ?>"><?= ucfirst($p['estado']) ?></span></td>
                <td><?= formatFecha($p['fecha_emision']) ?></td>
                <td><?= $p['fecha_expiracion']?formatFecha($p['fecha_expiracion']):'-' ?></td>
                <td class="text-end">
                    <div class="dropdown"><button class="btn btn-link btn-sm text-muted p-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="ver.php?id=<?= $p['id'] ?>"><i class="bi bi-eye"></i> Ver</a></li>
                        <li><a class="dropdown-item" href="form.php?id=<?= $p['id'] ?>"><i class="bi bi-pencil"></i> Editar</a></li>
                        <li><button class="dropdown-item" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/presupuesto.php?token=<?= $p['token'] ?>')"><i class="bi bi-link"></i> Copiar enlace</button></li>
                        <?php if ($p['estado']==='aceptado'): ?>
                        <li><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="convertir_factura"><input type="hidden" name="pid" value="<?= $p['id'] ?>"><button class="dropdown-item text-success"><i class="bi bi-receipt"></i> Convertir a Factura</button></form></li>
                        <?php endif; ?>
                    </ul></div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
