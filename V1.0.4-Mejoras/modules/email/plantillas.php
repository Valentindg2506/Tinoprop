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
    $plantillaId = intval(post('plantilla_id'));

    if ($accion === 'crear' || $accion === 'editar') {
        $nombre = trim(post('nombre'));
        $asunto = trim(post('asunto'));
        $contenido = $_POST['contenido'] ?? '';
        $categoria = post('categoria','general');
        if (empty($nombre) || empty($asunto)) { setFlash('danger', 'Nombre y asunto son obligatorios.'); }
        else {
            if ($accion === 'crear') {
                $db->prepare("INSERT INTO email_plantillas (nombre, asunto, contenido, categoria, usuario_id) VALUES (?,?,?,?,?)")
                    ->execute([$nombre, $asunto, $contenido, $categoria, currentUserId()]);
                setFlash('success', 'Plantilla creada.');
            } else {
                if (!isAdmin()) {
                    $ownerStmt = $db->prepare("SELECT usuario_id FROM email_plantillas WHERE id = ? LIMIT 1");
                    $ownerStmt->execute([$plantillaId]);
                    $ownerId = intval($ownerStmt->fetchColumn());
                    if ($ownerId !== intval(currentUserId())) {
                        setFlash('danger', 'No tienes permisos sobre esta plantilla.');
                        header('Location: plantillas.php');
                        exit;
                    }
                }
                $db->prepare("UPDATE email_plantillas SET nombre=?, asunto=?, contenido=?, categoria=? WHERE id=?")
                    ->execute([$nombre, $asunto, $contenido, $categoria, $plantillaId]);
                setFlash('success', 'Plantilla actualizada.');
            }
        }
    }
    if ($accion === 'eliminar') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM email_plantillas WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$plantillaId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre esta plantilla.');
                header('Location: plantillas.php');
                exit;
            }
        }
        $db->prepare("DELETE FROM email_plantillas WHERE id = ?")->execute([$plantillaId]);
        setFlash('success', 'Plantilla eliminada.');
    }
    if ($accion === 'toggle') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM email_plantillas WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$plantillaId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre esta plantilla.');
                header('Location: plantillas.php');
                exit;
            }
        }
        $db->prepare("UPDATE email_plantillas SET activa = NOT activa WHERE id = ?")->execute([$plantillaId]);
    }
    if ($accion === 'duplicar') {
        if (!isAdmin()) {
            $ownerStmt = $db->prepare("SELECT usuario_id FROM email_plantillas WHERE id = ? LIMIT 1");
            $ownerStmt->execute([$plantillaId]);
            $ownerId = intval($ownerStmt->fetchColumn());
            if ($ownerId !== intval(currentUserId())) {
                setFlash('danger', 'No tienes permisos sobre esta plantilla.');
                header('Location: plantillas.php');
                exit;
            }
        }
        $orig = $db->prepare("SELECT * FROM email_plantillas WHERE id = ?");
        $orig->execute([$plantillaId]);
        $o = $orig->fetch();
        if ($o) {
            $db->prepare("INSERT INTO email_plantillas (nombre, asunto, contenido, categoria, usuario_id) VALUES (?,?,?,?,?)")
                ->execute(['Copia de '.$o['nombre'], $o['asunto'], $o['contenido'], $o['categoria'], currentUserId()]);
            setFlash('success', 'Plantilla duplicada.');
        }
    }
    header('Location: plantillas.php');
    exit;
}

$pageTitle = 'Plantillas de Email';
require_once __DIR__ . '/../../includes/header.php';

$catFiltro = get('cat', '');
$where = $catFiltro ? "WHERE categoria = " . $db->quote($catFiltro) : '';
$plantillas = $db->query("SELECT * FROM email_plantillas $where ORDER BY categoria, nombre")->fetchAll();

$categorias = ['general','seguimiento','bienvenida','oferta','visita','factura','recordatorio','personalizada'];
$catColores = ['general'=>'secondary','seguimiento'=>'info','bienvenida'=>'success','oferta'=>'warning','visita'=>'primary','factura'=>'dark','recordatorio'=>'danger','personalizada'=>'secondary'];

$variables = ['{{nombre}}'=>'Nombre del cliente','{{apellidos}}'=>'Apellidos','{{email}}'=>'Email','{{telefono}}'=>'Telefono','{{propiedad_titulo}}'=>'Titulo propiedad','{{propiedad_referencia}}'=>'Referencia','{{propiedad_precio}}'=>'Precio','{{fecha_visita}}'=>'Fecha visita','{{hora_visita}}'=>'Hora visita','{{factura_numero}}'=>'Numero factura','{{factura_total}}'=>'Total factura','{{empresa_nombre}}'=>'Nombre empresa','{{enlace_booking}}'=>'Enlace booking'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= APP_URL ?>/modules/marketing/index.php" class="btn btn-outline-secondary btn-sm me-2"><i class="bi bi-arrow-left"></i> Marketing</a>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPlantilla" onclick="document.getElementById('formPlantilla').reset();document.getElementById('plantillaAccion').value='crear';document.getElementById('plantillaId').value=''">
        <i class="bi bi-plus-lg"></i> Nueva Plantilla
    </button>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link <?= $catFiltro === '' ? 'active' : '' ?>" href="?">Todas</a></li>
    <?php foreach ($categorias as $cat): ?>
    <li class="nav-item"><a class="nav-link <?= $catFiltro === $cat ? 'active' : '' ?>" href="?cat=<?= $cat ?>"><?= ucfirst($cat) ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="row g-3">
    <?php foreach ($plantillas as $p): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-<?= $catColores[$p['categoria']] ?? 'secondary' ?>"><?= ucfirst($p['categoria']) ?></span>
                    <?= $p['activa'] ? '' : '<span class="badge bg-secondary">Inactiva</span>' ?>
                </div>
                <h6 class="fw-bold"><?= sanitize($p['nombre']) ?></h6>
                <small class="text-muted d-block mb-2">Asunto: <?= sanitize($p['asunto']) ?></small>
                <div class="d-flex gap-1 mt-auto">
                    <button class="btn btn-sm btn-outline-primary" onclick='editarPlantilla(<?= json_encode($p) ?>)'><i class="bi bi-pencil"></i></button>
                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="accion" value="duplicar"><input type="hidden" name="plantilla_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Duplicar"><i class="bi bi-copy"></i></button></form>
                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="plantilla_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-outline-<?= $p['activa'] ? 'warning' : 'success' ?>"><i class="bi bi-toggle-<?= $p['activa'] ? 'on' : 'off' ?>"></i></button></form>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="plantilla_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($plantillas)): ?>
    <div class="col-12 text-center text-muted py-5"><i class="bi bi-envelope-paper fs-1 d-block mb-3"></i><h5>No hay plantillas</h5></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalPlantilla" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formPlantilla">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="crear" id="plantillaAccion">
                <input type="hidden" name="plantilla_id" id="plantillaId">
                <div class="modal-header"><h6 class="modal-title"><i class="bi bi-envelope-paper"></i> Plantilla</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Nombre *</label><input type="text" name="nombre" id="pNombre" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Categoria</label>
                            <select name="categoria" id="pCategoria" class="form-select">
                                <?php foreach ($categorias as $c): ?><option value="<?= $c ?>"><?= ucfirst($c) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Asunto *</label><input type="text" name="asunto" id="pAsunto" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Contenido (HTML)</label><textarea name="contenido" id="pContenido" class="form-control font-monospace" rows="10"></textarea></div>
                    </div>
                    <div class="mt-3">
                        <h6 class="small text-muted">Variables disponibles:</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($variables as $v => $desc): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertVar('<?= $v ?>')" title="<?= $desc ?>"><?= $v ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
</div>

<script>
function editarPlantilla(p) {
    document.getElementById('plantillaAccion').value = 'editar';
    document.getElementById('plantillaId').value = p.id;
    document.getElementById('pNombre').value = p.nombre;
    document.getElementById('pAsunto').value = p.asunto;
    document.getElementById('pContenido').value = p.contenido;
    document.getElementById('pCategoria').value = p.categoria;
    new bootstrap.Modal(document.getElementById('modalPlantilla')).show();
}
function insertVar(v) {
    const ta = document.getElementById('pContenido');
    const pos = ta.selectionStart;
    ta.value = ta.value.substring(0, pos) + v + ta.value.substring(pos);
    ta.focus();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
