<?php
$pageTitle = 'Portales Inmobiliarios';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

$propiedadId = intval(get('propiedad_id'));

// Si viene con propiedad_id, mostrar portales de esa propiedad
if ($propiedadId) {
    $prop = $db->prepare("SELECT * FROM propiedades WHERE id = ?");
    $prop->execute([$propiedadId]);
    $prop = $prop->fetch();
    if (!$prop) { setFlash('danger', 'Propiedad no encontrada.'); header('Location: index.php'); exit; }

    // Obtener portales y estado de publicacion
    $stmtPortales = $db->prepare("SELECT po.*, pp.id as pub_id, pp.estado as pub_estado, pp.url_publicacion, pp.fecha_publicacion, pp.notas as pub_notas
        FROM portales po
        LEFT JOIN propiedad_portales pp ON po.id = pp.portal_id AND pp.propiedad_id = ?
        WHERE po.activo = 1
        ORDER BY po.nombre");
    $stmtPortales->execute([$propiedadId]);
    $portales = $stmtPortales->fetchAll();
} else {
    // Vista general: propiedades con sus publicaciones
    $page = max(1, intval(get('page', 1)));
    $stmtCount = $db->query("SELECT COUNT(*) FROM propiedades WHERE estado = 'disponible'");
    $total = $stmtCount->fetchColumn();
    $pagination = paginate($total, 20, $page);

    $stmtProps = $db->prepare("SELECT p.id, p.referencia, p.titulo, p.localidad, p.provincia, p.precio,
        (SELECT COUNT(*) FROM propiedad_portales pp WHERE pp.propiedad_id = p.id AND pp.estado = 'publicado') as num_publicado,
        (SELECT GROUP_CONCAT(po.nombre SEPARATOR ', ') FROM propiedad_portales pp JOIN portales po ON pp.portal_id = po.id WHERE pp.propiedad_id = p.id AND pp.estado = 'publicado') as portales_publicados
        FROM propiedades p
        WHERE p.estado = 'disponible'
        ORDER BY p.created_at DESC
        LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}");
    $stmtProps->execute();
    $propiedades = $stmtProps->fetchAll();
}

// Procesar formulario de publicacion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $propiedadId) {
    verifyCsrf();

    if (!isAdmin() && intval($prop['agente_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para gestionar portales de esta propiedad.');
        header('Location: index.php?propiedad_id=' . $propiedadId);
        exit;
    }

    $portalId = intval(post('portal_id'));
    $accion = post('accion');

    if ($accion === 'publicar') {
        $db->prepare("INSERT INTO propiedad_portales (propiedad_id, portal_id, estado, url_publicacion, fecha_publicacion, notas)
            VALUES (?, ?, 'publicado', ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE estado = 'publicado', url_publicacion = VALUES(url_publicacion), fecha_actualizacion = CURDATE(), notas = VALUES(notas)")
            ->execute([$propiedadId, $portalId, post('url_publicacion') ?: null, post('notas') ?: null]);
        registrarActividad('publicar_portal', 'propiedad', $propiedadId);
        setFlash('success', 'Propiedad publicada en el portal.');
    } elseif ($accion === 'retirar') {
        $db->prepare("UPDATE propiedad_portales SET estado = 'retirado', fecha_actualizacion = CURDATE() WHERE propiedad_id = ? AND portal_id = ?")
            ->execute([$propiedadId, $portalId]);
        setFlash('success', 'Publicacion retirada.');
    } elseif ($accion === 'eliminar') {
        $db->prepare("DELETE FROM propiedad_portales WHERE propiedad_id = ? AND portal_id = ?")
            ->execute([$propiedadId, $portalId]);
        setFlash('success', 'Publicacion eliminada.');
    }

    header('Location: index.php?propiedad_id=' . $propiedadId);
    exit;
}
?>

<?php if ($propiedadId && isset($prop)): ?>
<!-- Vista de portales para una propiedad especifica -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1">Portales para: <?= sanitize($prop['referencia']) ?> - <?= sanitize($prop['titulo']) ?></h5>
        <a href="<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $propiedadId ?>" class="text-muted"><i class="bi bi-arrow-left"></i> Volver a la propiedad</a>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($portales as $portal): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-globe"></i> <?= sanitize($portal['nombre']) ?></strong>
                <?php if ($portal['pub_estado']): ?>
                <span class="badge-estado badge-<?= $portal['pub_estado'] ?>"><?= ucfirst($portal['pub_estado']) ?></span>
                <?php else: ?>
                <span class="badge bg-light text-muted">No publicado</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($portal['pub_estado'] === 'publicado'): ?>
                    <?php if ($portal['url_publicacion']): ?>
                    <p class="mb-2"><a href="<?= sanitize($portal['url_publicacion']) ?>" target="_blank"><i class="bi bi-link-45deg"></i> Ver anuncio</a></p>
                    <?php endif; ?>
                    <p class="text-muted mb-2"><small>Publicado: <?= formatFecha($portal['fecha_publicacion']) ?></small></p>
                    <?php if ($portal['pub_notas']): ?>
                    <p class="text-muted mb-2"><small><?= sanitize($portal['pub_notas']) ?></small></p>
                    <?php endif; ?>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="portal_id" value="<?= $portal['id'] ?>">
                        <input type="hidden" name="accion" value="retirar">
                        <button type="submit" class="btn btn-sm btn-outline-warning">Retirar</button>
                    </form>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="portal_id" value="<?= $portal['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Eliminar esta publicacion?">Eliminar</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="portal_id" value="<?= $portal['id'] ?>">
                        <input type="hidden" name="accion" value="publicar">
                        <div class="mb-2">
                            <input type="url" name="url_publicacion" class="form-control form-control-sm" placeholder="URL del anuncio (opcional)">
                        </div>
                        <div class="mb-2">
                            <input type="text" name="notas" class="form-control form-control-sm" placeholder="Notas (opcional)">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Marcar como publicado</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- Vista general de publicaciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted">Propiedades disponibles y su estado en portales</span>
</div>

<div class="table-container">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Referencia</th><th>Titulo</th><th>Ubicacion</th><th>Precio</th><th>Portales</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($propiedades as $pr): ?>
            <tr>
                <td><strong><?= sanitize($pr['referencia']) ?></strong></td>
                <td><?= sanitize(mb_substr($pr['titulo'], 0, 40)) ?></td>
                <td><?= sanitize($pr['localidad']) ?>, <?= sanitize($pr['provincia']) ?></td>
                <td class="text-nowrap"><?= formatPrecio($pr['precio']) ?></td>
                <td>
                    <?php if ($pr['num_publicado'] > 0): ?>
                    <span class="badge bg-success"><?= $pr['num_publicado'] ?> portales</span>
                    <br><small class="text-muted"><?= sanitize($pr['portales_publicados']) ?></small>
                    <?php else: ?>
                    <span class="badge bg-light text-muted">Sin publicar</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="index.php?propiedad_id=<?= $pr['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-globe"></i> Gestionar</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($propiedades)): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">No hay propiedades disponibles</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($pagination)): ?>
<?= renderPagination($pagination, 'index.php?x=1') ?>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
