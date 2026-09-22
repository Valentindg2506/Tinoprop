<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$userId = intval(currentUserId());
generarNotificacionesProspectosVencidos($userId);

// Abrir notificacion: marcar leida y redirigir al enlace si existe.
$openId = intval($_GET['open'] ?? 0);
if ($openId > 0) {
    $stmtOpen = $db->prepare("SELECT enlace FROM notificaciones WHERE id = ? AND usuario_id = ? LIMIT 1");
    $stmtOpen->execute([$openId, $userId]);
    $openNotif = $stmtOpen->fetch();

    if ($openNotif) {
        $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?")->execute([$openId, $userId]);
        $target = trim((string)($openNotif['enlace'] ?? ''));
        if ($target !== '') {
            header('Location: ' . safeRedirectTarget($target, APP_URL . '/notificaciones.php'));
            exit;
        }
    }

    header('Location: notificaciones.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'mark_all') {
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$userId]);
        setFlash('success', 'Todas las notificaciones se marcaron como leidas.');
        header('Location: notificaciones.php');
        exit;
    }

    if ($accion === 'mark_one') {
        $id = intval(post('id'));
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$id, $userId]);
        }
        header('Location: notificaciones.php');
        exit;
    }
}

$estado = get('estado', 'todas');
$q = trim((string)get('q', ''));
$page = max(1, intval(get('page', 1)));
$perPage = 20;

$where = ["usuario_id = ?"];
$params = [$userId];

if ($estado === 'no_leidas') {
    $where[] = "leida = 0";
} elseif ($estado === 'leidas') {
    $where[] = "leida = 1";
}

if ($q !== '') {
    $where[] = "titulo LIKE ?";
    $params[] = '%' . $q . '%';
}

$whereSql = implode(' AND ', $where);

$stmtCount = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE $whereSql");
$stmtCount->execute($params);
$total = intval($stmtCount->fetchColumn());

$pagination = paginate($total, $perPage, $page);

$stmtItems = $db->prepare("SELECT * FROM notificaciones WHERE $whereSql ORDER BY created_at DESC LIMIT ? OFFSET ?");
$itemsParams = $params;
$itemsParams[] = intval($pagination['per_page']);
$itemsParams[] = intval($pagination['offset']);
$stmtItems->execute($itemsParams);
$items = $stmtItems->fetchAll();

$stmtUnread = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
$stmtUnread->execute([$userId]);
$unreadCount = intval($stmtUnread->fetchColumn());

$pageTitle = 'Notificaciones';
require_once __DIR__ . '/includes/header.php';

$baseUrl = 'notificaciones.php?estado=' . urlencode($estado) . '&q=' . urlencode($q);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-0"><i class="bi bi-bell"></i> Notificaciones</h5>
        <small class="text-muted"><?= $total ?> resultado<?= $total === 1 ? '' : 's' ?><?= $unreadCount > 0 ? ' | ' . $unreadCount . ' sin leer' : '' ?></small>
    </div>
    <div>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="mark_all">
            <button type="submit" class="btn btn-outline-primary btn-sm" <?= $unreadCount === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-check2-all"></i> Marcar todas leidas
            </button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="todas" <?= $estado === 'todas' ? 'selected' : '' ?>>Todas</option>
                    <option value="no_leidas" <?= $estado === 'no_leidas' ? 'selected' : '' ?>>No leidas</option>
                    <option value="leidas" <?= $estado === 'leidas' ? 'selected' : '' ?>>Leidas</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?= sanitize($q) ?>" placeholder="Buscar por titulo...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                <a href="notificaciones.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
        No hay notificaciones para los filtros seleccionados.
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 14%">Tipo</th>
                    <th style="width: 44%">Notificacion</th>
                    <th style="width: 18%">Fecha</th>
                    <th style="width: 12%">Estado</th>
                    <th class="text-end" style="width: 12%">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $n): ?>
                <?php $isUnread = intval($n['leida']) === 0; ?>
                <?php
                    $notifTitleLc = mb_strtolower((string)($n['titulo'] ?? ''), 'UTF-8');
                    $notifLinkLc = mb_strtolower((string)($n['enlace'] ?? ''), 'UTF-8');
                    $notifTypeLabel = 'General';
                    $notifTypeIcon = 'bi-bell';
                    $notifTypeClass = 'bg-secondary';

                    if (strpos($notifLinkLc, 'contrato') !== false || strpos($notifTitleLc, 'contrato') !== false) {
                        $notifTypeLabel = 'Contrato';
                        $notifTypeIcon = 'bi-file-earmark-text';
                        $notifTypeClass = 'bg-primary';
                    } elseif (strpos($notifLinkLc, 'tareas') !== false || strpos($notifTitleLc, 'tarea') !== false) {
                        $notifTypeLabel = 'Tarea';
                        $notifTypeIcon = 'bi-check2-square';
                        $notifTypeClass = 'bg-warning text-dark';
                    } elseif (strpos($notifLinkLc, 'visitas') !== false || strpos($notifTitleLc, 'visita') !== false) {
                        $notifTypeLabel = 'Visita';
                        $notifTypeIcon = 'bi-calendar-event';
                        $notifTypeClass = 'bg-info text-dark';
                    } elseif (strpos($notifLinkLc, 'prospect') !== false || strpos($notifTitleLc, 'prospect') !== false || strpos($notifTitleLc, 'lead') !== false) {
                        $notifTypeLabel = 'Lead';
                        $notifTypeIcon = 'bi-person-plus';
                        $notifTypeClass = 'bg-success';
                    } elseif (strpos($notifLinkLc, 'finanzas') !== false || strpos($notifLinkLc, 'pagos') !== false || strpos($notifTitleLc, 'pago') !== false) {
                        $notifTypeLabel = 'Finanzas';
                        $notifTypeIcon = 'bi-cash-stack';
                        $notifTypeClass = 'bg-danger';
                    }
                ?>
                <tr class="<?= $isUnread ? 'table-success-subtle' : '' ?>">
                    <td>
                        <span class="badge <?= $notifTypeClass ?>"><i class="bi <?= $notifTypeIcon ?> me-1"></i><?= sanitize($notifTypeLabel) ?></span>
                    </td>
                    <td>
                        <?php if ($isUnread): ?><span class="badge bg-success me-2">Nueva</span><?php endif; ?>
                        <?= sanitize($n['titulo']) ?>
                    </td>
                    <td><?= formatFechaHora($n['created_at']) ?></td>
                    <td>
                        <span class="badge bg-<?= $isUnread ? 'warning text-dark' : 'secondary' ?>">
                            <?= $isUnread ? 'Sin leer' : 'Leida' ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <?php if ($isUnread): ?>
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="accion" value="mark_one">
                                <input type="hidden" name="id" value="<?= intval($n['id']) ?>">
                                <button class="btn btn-outline-success" title="Marcar leida"><i class="bi bi-check2"></i></button>
                            </form>
                            <?php endif; ?>
                            <a href="notificaciones.php?open=<?= intval($n['id']) ?>" class="btn btn-outline-primary" title="Abrir enlace">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">
    <?= renderPagination($pagination, $baseUrl) ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
