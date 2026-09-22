<?php
$pageTitle = 'Ver Email';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));

if (!$id) {
    setFlash('danger', 'Email no especificado.');
    header('Location: index.php');
    exit;
}

// Obtener email (verificar que pertenece al usuario)
$stmt = $db->prepare("
    SELECT em.*, ec.email as cuenta_email, ec.nombre_display,
           c.nombre as cliente_nombre, c.apellidos as cliente_apellidos, c.id as cliente_id,
           p.titulo as propiedad_titulo, p.referencia as propiedad_ref, p.id as prop_id
    FROM email_mensajes em
    JOIN email_cuentas ec ON em.cuenta_id = ec.id
    LEFT JOIN clientes c ON em.cliente_id = c.id
    LEFT JOIN propiedades p ON em.propiedad_id = p.id
    WHERE em.id = ? AND ec.usuario_id = ?
");
$stmt->execute([$id, currentUserId()]);
$email = $stmt->fetch();

if (!$email) {
    setFlash('danger', 'Email no encontrado.');
    header('Location: index.php');
    exit;
}

// Marcar como leido
if (!$email['leido']) {
    $stmtLeer = $db->prepare("UPDATE email_mensajes SET leido = 1 WHERE id = ?");
    $stmtLeer->execute([$id]);
}

$carpeta = $email['carpeta'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php?carpeta=<?= urlencode($carpeta) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver a <?= $carpeta === 'inbox' ? 'Bandeja de entrada' : ($carpeta === 'sent' ? 'Enviados' : ($carpeta === 'draft' ? 'Borradores' : 'Papelera')) ?>
    </a>
    <div class="d-flex gap-2">
        <?php if ($email['direccion'] === 'entrante' || $carpeta === 'inbox'): ?>
        <a href="compose.php?para=<?= urlencode($email['de_email']) ?>&asunto=<?= urlencode('Re: ' . $email['asunto']) ?>&cliente_id=<?= $email['cliente_id'] ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-reply"></i> Responder
        </a>
        <?php endif; ?>
        <?php if ($carpeta !== 'trash'): ?>
        <form method="POST" action="index.php" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="accion" value="mover_papelera">
            <input type="hidden" name="email_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="Mover a la papelera?">
                <i class="bi bi-trash"></i> Eliminar
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-1"><?= sanitize($email['asunto']) ?></h5>
                <div class="d-flex justify-content-between align-items-center text-muted small">
                    <div>
                        <?php if ($email['direccion'] === 'saliente'): ?>
                        <span><strong>De:</strong> <?= sanitize($email['cuenta_email']) ?></span>
                        <span class="ms-3"><strong>Para:</strong> <?= sanitize($email['para_email']) ?></span>
                        <?php else: ?>
                        <span><strong>De:</strong> <?= sanitize($email['de_email']) ?></span>
                        <span class="ms-3"><strong>Para:</strong> <?= sanitize($email['cuenta_email']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($email['cc'])): ?>
                        <span class="ms-3"><strong>CC:</strong> <?= sanitize($email['cc']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div><?= formatFechaHora($email['created_at']) ?></div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($email['cuerpo_html'])): ?>
                    <div class="email-body"><?= $email['cuerpo_html'] ?></div>
                <?php else: ?>
                    <div class="email-body" style="white-space: pre-wrap;"><?= sanitize($email['cuerpo']) ?></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($email['cliente_nombre']) || !empty($email['propiedad_titulo'])): ?>
            <div class="card-footer bg-white">
                <small class="text-muted">
                    <?php if (!empty($email['cliente_nombre'])): ?>
                    <a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $email['cliente_id'] ?>" class="text-decoration-none me-3">
                        <i class="bi bi-person"></i> <?= sanitize($email['cliente_nombre'] . ' ' . $email['cliente_apellidos']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($email['propiedad_titulo'])): ?>
                    <a href="<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $email['prop_id'] ?>" class="text-decoration-none">
                        <i class="bi bi-house-door"></i> <?= sanitize($email['propiedad_ref'] . ' - ' . $email['propiedad_titulo']) ?>
                    </a>
                    <?php endif; ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
