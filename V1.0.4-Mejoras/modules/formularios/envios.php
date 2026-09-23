<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$formId = intval(get('formulario_id'));

$stmt = $db->prepare("SELECT * FROM formularios WHERE id = ?");
$stmt->execute([$formId]);
$form = $stmt->fetch();
if (!$form) { setFlash('danger', 'Formulario no encontrado.'); header('Location: index.php'); exit; }
if (!isAdmin() && intval($form['usuario_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos sobre este formulario.');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');
    if ($accion === 'marcar_leido') {
        $db->prepare("UPDATE formulario_envios SET leido = 1 WHERE formulario_id = ?")->execute([$formId]);
        setFlash('success', 'Todos marcados como leidos.');
    }
    if ($accion === 'eliminar_envio') {
        $db->prepare("DELETE FROM formulario_envios WHERE id = ? AND formulario_id = ?")->execute([intval(post('envio_id')), $formId]);
    }
    header('Location: envios.php?formulario_id=' . $formId);
    exit;
}

$pageTitle = 'Envios - ' . $form['nombre'];
require_once __DIR__ . '/../../includes/header.php';

$campos = json_decode($form['campos'], true) ?: [];
$page = max(1, intval(get('page', 1)));
$total = $db->prepare("SELECT COUNT(*) FROM formulario_envios WHERE formulario_id = ?");
$total->execute([$formId]);
$totalCount = $total->fetchColumn();
$pagination = paginate($totalCount, 20, $page);

$stmtEnvios = $db->prepare("SELECT fe.*, c.nombre as cliente_nombre FROM formulario_envios fe LEFT JOIN clientes c ON fe.cliente_id = c.id WHERE fe.formulario_id = ? ORDER BY fe.created_at DESC LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
$stmtEnvios->execute([$formId]);
$envios = $stmtEnvios->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    <div class="d-flex gap-2">
        <span class="text-muted align-self-center"><?= $totalCount ?> envios</span>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="marcar_leido">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-all"></i> Marcar todos leidos</button>
        </form>
    </div>
</div>

<?php if (empty($envios)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
    <h5>No hay envios todavia</h5>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:30px"></th>
                    <?php foreach (array_slice($campos, 0, 4) as $c): ?>
                    <th><?= sanitize($c['label']) ?></th>
                    <?php endforeach; ?>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($envios as $e):
                    $datos = json_decode($e['datos'], true) ?: [];
                ?>
                <tr class="<?= $e['leido'] ? '' : 'fw-bold' ?>">
                    <td><?= $e['leido'] ? '' : '<span class="badge bg-primary rounded-pill">N</span>' ?></td>
                    <?php foreach (array_slice($campos, 0, 4) as $c): ?>
                    <td><?= sanitize(mb_strimwidth($datos[$c['label']] ?? '-', 0, 50, '...')) ?></td>
                    <?php endforeach; ?>
                    <td>
                        <?php if ($e['cliente_id']): ?>
                        <a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $e['cliente_id'] ?>"><?= sanitize($e['cliente_nombre']) ?></a>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= formatFechaHora($e['created_at']) ?></small></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick='verDetalle(<?= json_encode($datos) ?>, "<?= formatFechaHora($e['created_at']) ?>", "<?= sanitize($e['ip'] ?? '') ?>")'><i class="bi bi-eye"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="accion" value="eliminar_envio">
                            <input type="hidden" name="envio_id" value="<?= $e['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= renderPagination($pagination, 'envios.php?formulario_id=' . $formId) ?>
<?php endif; ?>

<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-list-ul"></i> Detalle del envio</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent"></div>
        </div>
    </div>
</div>

<script>
function verDetalle(datos, fecha, ip) {
    let html = '<table class="table table-sm"><tbody>';
    for (const [k, v] of Object.entries(datos)) {
        html += '<tr><td class="fw-bold">' + k + '</td><td>' + (v || '-') + '</td></tr>';
    }
    html += '<tr><td class="fw-bold">Fecha</td><td>' + fecha + '</td></tr>';
    html += '<tr><td class="fw-bold">IP</td><td>' + ip + '</td></tr>';
    html += '</tbody></table>';
    document.getElementById('detalleContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('modalDetalle')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
