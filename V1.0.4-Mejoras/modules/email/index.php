<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

// Carpeta actual
$carpeta = get('carpeta', 'inbox');
$carpetasValidas = ['inbox', 'sent', 'draft', 'trash'];
if (!in_array($carpeta, $carpetasValidas)) {
    $carpeta = 'inbox';
}

// POST handlers antes del header para poder hacer redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'toggle_leido') {
    verifyCsrf();
    $emailId = post('email_id');
    $stmtToggle = $db->prepare("UPDATE email_mensajes SET leido = NOT leido WHERE id = ? AND cuenta_id IN (SELECT id FROM email_cuentas WHERE usuario_id = ?)");
    $stmtToggle->execute([$emailId, currentUserId()]);
    header('Location: ' . APP_URL . '/modules/email/index.php?carpeta=' . urlencode($carpeta));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'toggle_destacado') {
    verifyCsrf();
    $emailId = post('email_id');
    $stmtStar = $db->prepare("UPDATE email_mensajes SET destacado = NOT destacado WHERE id = ? AND cuenta_id IN (SELECT id FROM email_cuentas WHERE usuario_id = ?)");
    $stmtStar->execute([$emailId, currentUserId()]);
    header('Location: ' . APP_URL . '/modules/email/index.php?carpeta=' . urlencode($carpeta));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'mover_papelera') {
    verifyCsrf();
    $emailId = post('email_id');
    $stmtTrash = $db->prepare("UPDATE email_mensajes SET carpeta = 'trash' WHERE id = ? AND cuenta_id IN (SELECT id FROM email_cuentas WHERE usuario_id = ?)");
    $stmtTrash->execute([$emailId, currentUserId()]);
    setFlash('success', 'Email movido a la papelera.');
    header('Location: ' . APP_URL . '/modules/email/index.php?carpeta=' . urlencode($carpeta));
    exit;
}

$pageTitle = 'Email';
require_once __DIR__ . '/../../includes/header.php';

// Obtener cuenta de email del usuario actual
$stmtCuenta = $db->prepare("SELECT * FROM email_cuentas WHERE usuario_id = ? AND activo = 1 LIMIT 1");
$stmtCuenta->execute([currentUserId()]);
$cuenta = $stmtCuenta->fetch();

$carpetaLabels = [
    'inbox' => 'Bandeja de entrada',
    'sent' => 'Enviados',
    'draft' => 'Borradores',
    'trash' => 'Papelera',
];

$busqueda = get('buscar');

// Obtener conteos por carpeta
$conteos = ['inbox' => 0, 'sent' => 0, 'draft' => 0, 'trash' => 0];
if ($cuenta) {
    $stmtCount = $db->prepare("SELECT carpeta, COUNT(*) as total FROM email_mensajes WHERE cuenta_id = ? GROUP BY carpeta");
    $stmtCount->execute([$cuenta['id']]);
    foreach ($stmtCount->fetchAll() as $row) {
        $conteos[$row['carpeta']] = $row['total'];
    }
}

// No leidos inbox
$noLeidos = 0;
if ($cuenta) {
    $stmtNL = $db->prepare("SELECT COUNT(*) FROM email_mensajes WHERE cuenta_id = ? AND carpeta = 'inbox' AND leido = 0");
    $stmtNL->execute([$cuenta['id']]);
    $noLeidos = $stmtNL->fetchColumn();
}

// Obtener emails
$emails = [];
if ($cuenta) {
    $sql = "SELECT em.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
            FROM email_mensajes em
            LEFT JOIN clientes c ON em.cliente_id = c.id
            WHERE em.cuenta_id = ? AND em.carpeta = ?";
    $params = [$cuenta['id'], $carpeta];

    if (!empty($busqueda)) {
        $sql .= " AND (em.asunto LIKE ? OR em.de_email LIKE ? OR em.para_email LIKE ? OR em.cuerpo LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
    }

    $sql .= " ORDER BY em.created_at DESC";

    // Paginacion
    $page = max(1, intval(get('page', 1)));
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM email_mensajes WHERE cuenta_id = ? AND carpeta = ?" . (!empty($busqueda) ? " AND (asunto LIKE ? OR de_email LIKE ? OR para_email LIKE ? OR cuerpo LIKE ?)" : ""));
    $stmtTotal->execute($params);
    $total = $stmtTotal->fetchColumn();
    $pagination = paginate($total, 20, $page);

    $sql .= " LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $emails = $stmt->fetchAll();
}
?>

<?php if (!$cuenta): ?>
<div class="text-center py-5">
    <i class="bi bi-envelope fs-1 d-block mb-3 text-muted"></i>
    <h5>No tienes una cuenta de email configurada</h5>
    <p class="text-muted">Configura tu cuenta de email para poder enviar y recibir correos desde el CRM.</p>
    <a href="<?= APP_URL ?>/modules/email/config.php" class="btn btn-primary">
        <i class="bi bi-gear"></i> Configurar cuenta de email
    </a>
</div>
<?php else: ?>

<div class="row g-3">
    <!-- Sidebar de carpetas -->
    <div class="col-md-3 col-lg-2">
        <a href="<?= APP_URL ?>/modules/email/compose.php" class="btn btn-primary w-100 mb-3">
            <i class="bi bi-pencil-square"></i> Redactar
        </a>

        <div class="list-group list-group-flush">
            <?php
            $iconos = ['inbox' => 'bi-inbox', 'sent' => 'bi-send', 'draft' => 'bi-file-earmark', 'trash' => 'bi-trash'];
            foreach ($carpetaLabels as $key => $label):
            ?>
            <a href="<?= APP_URL ?>/modules/email/index.php?carpeta=<?= $key ?>"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $carpeta === $key ? 'active' : '' ?>">
                <span><i class="bi <?= $iconos[$key] ?>"></i> <?= $label ?></span>
                <?php if ($conteos[$key] > 0): ?>
                    <span class="badge <?= $carpeta === $key ? 'bg-white text-primary' : 'bg-secondary' ?> rounded-pill"><?= $conteos[$key] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <hr>
        <a href="<?= APP_URL ?>/modules/email/config.php" class="btn btn-outline-secondary btn-sm w-100">
            <i class="bi bi-gear"></i> Configuracion
        </a>
    </div>

    <!-- Lista de emails -->
    <div class="col-md-9 col-lg-10">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi <?= $iconos[$carpeta] ?>"></i> <?= $carpetaLabels[$carpeta] ?>
                    <?php if ($noLeidos > 0 && $carpeta === 'inbox'): ?>
                        <span class="badge bg-primary"><?= $noLeidos ?> sin leer</span>
                    <?php endif; ?>
                </h6>
                <form method="GET" class="d-flex gap-2" style="max-width: 300px;">
                    <input type="hidden" name="carpeta" value="<?= sanitize($carpeta) ?>">
                    <input type="text" name="buscar" class="form-control form-control-sm" placeholder="Buscar emails..." value="<?= sanitize($busqueda) ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php if (empty($emails)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-envelope-open fs-1 d-block mb-2"></i>
                        <p>No hay emails en esta carpeta.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <tbody>
                                <?php foreach ($emails as $email): ?>
                                <tr class="<?= !$email['leido'] ? 'fw-bold' : '' ?>" style="cursor: pointer;">
                                    <td style="width: 40px;" class="text-center align-middle">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="accion" value="toggle_destacado">
                                            <input type="hidden" name="email_id" value="<?= $email['id'] ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0" title="Destacar">
                                                <i class="bi <?= $email['destacado'] ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="align-middle" style="width: 200px;">
                                        <a href="<?= APP_URL ?>/modules/email/ver.php?id=<?= $email['id'] ?>" class="text-decoration-none text-dark">
                                            <?php if ($carpeta === 'sent' || $carpeta === 'draft'): ?>
                                                <small class="text-muted">Para:</small> <?= sanitize($email['para_email']) ?>
                                            <?php else: ?>
                                                <?= sanitize($email['de_email']) ?>
                                            <?php endif; ?>
                                        </a>
                                    </td>
                                    <td class="align-middle">
                                        <a href="<?= APP_URL ?>/modules/email/ver.php?id=<?= $email['id'] ?>" class="text-decoration-none text-dark">
                                            <?= sanitize($email['asunto']) ?>
                                            <span class="text-muted fw-normal"> - <?= sanitize(mb_substr(strip_tags($email['cuerpo']), 0, 80)) ?>...</span>
                                        </a>
                                    </td>
                                    <td class="align-middle text-end text-nowrap" style="width: 120px;">
                                        <small class="text-muted"><?= formatFechaHora($email['created_at']) ?></small>
                                    </td>
                                    <td class="align-middle text-end" style="width: 80px;">
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="accion" value="toggle_leido">
                                            <input type="hidden" name="email_id" value="<?= $email['id'] ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted" title="<?= $email['leido'] ? 'Marcar como no leido' : 'Marcar como leido' ?>">
                                                <i class="bi <?= $email['leido'] ? 'bi-envelope-open' : 'bi-envelope-fill' ?>"></i>
                                            </button>
                                        </form>
                                        <?php if ($carpeta !== 'trash'): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="accion" value="mover_papelera">
                                            <input type="hidden" name="email_id" value="<?= $email['id'] ?>">
                                            <button type="submit" class="btn btn-link btn-sm p-0 text-muted" title="Eliminar" data-confirm="Mover este email a la papelera?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($emails) && isset($pagination)): ?>
            <div class="card-footer bg-white">
                <?= renderPagination($pagination, APP_URL . '/modules/email/index.php?carpeta=' . urlencode($carpeta) . (!empty($busqueda) ? '&buscar=' . urlencode($busqueda) : '')) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
