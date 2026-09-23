<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');

    if ($a === 'crear_post') {
        $plataformas = json_encode(post('plataformas') ?: []);
        $programado = post('programado_para') ?: null;
        $estado = $programado ? 'programado' : 'borrador';
        $db->prepare("INSERT INTO social_posts (plataformas, contenido, imagen_url, enlace, estado, programado_para, usuario_id) VALUES (?,?,?,?,?,?,?)")
            ->execute([$plataformas, trim(post('contenido')), trim(post('imagen_url')), trim(post('enlace')), $estado, $programado, currentUserId()]);
        setFlash('success', 'Post creado.');
        header('Location: index.php'); exit;
    }
    if ($a === 'eliminar_post') { $db->prepare("DELETE FROM social_posts WHERE id=?")->execute([intval(post('pid'))]); setFlash('success','Eliminado.'); header('Location: index.php'); exit; }
    if ($a === 'publicar') {
        $pid = intval(post('pid'));
        // Here you would call each platform's API
        $db->prepare("UPDATE social_posts SET estado='publicado', publicado_at=NOW() WHERE id=?")->execute([$pid]);
        setFlash('success', 'Publicado.');
        header('Location: index.php'); exit;
    }
    if ($a === 'guardar_cuenta') {
        $db->prepare("INSERT INTO social_cuentas (plataforma, nombre, access_token, page_id) VALUES (?,?,?,?)")
            ->execute([post('plataforma'), trim(post('cuenta_nombre')), trim(post('access_token')), trim(post('page_id'))]);
        setFlash('success','Cuenta agregada.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Redes Sociales';
require_once __DIR__ . '/../../includes/header.php';

$posts = $db->query("SELECT sp.*, u.nombre as autor FROM social_posts sp LEFT JOIN usuarios u ON sp.usuario_id=u.id ORDER BY sp.created_at DESC LIMIT 50")->fetchAll();
$cuentas = $db->query("SELECT * FROM social_cuentas ORDER BY plataforma")->fetchAll();

$vista = get('vista', 'calendario');
$estadoClases = ['borrador'=>'secondary','programado'=>'warning','publicado'=>'success','error'=>'danger'];
$platIcons = ['facebook'=>'bi-facebook text-primary','instagram'=>'bi-instagram text-danger','google_business'=>'bi-google text-warning','linkedin'=>'bi-linkedin text-primary','twitter'=>'bi-twitter text-info'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <a href="?vista=calendario" class="btn btn-sm <?= $vista==='calendario'?'btn-primary':'btn-outline-secondary' ?>"><i class="bi bi-calendar3"></i> Calendario</a>
        <a href="?vista=lista" class="btn btn-sm <?= $vista==='lista'?'btn-primary':'btn-outline-secondary' ?>"><i class="bi bi-list"></i> Lista</a>
        <a href="?vista=cuentas" class="btn btn-sm <?= $vista==='cuentas'?'btn-primary':'btn-outline-secondary' ?>"><i class="bi bi-gear"></i> Cuentas</a>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPost"><i class="bi bi-plus-lg"></i> Nuevo Post</button>
</div>

<?php if ($vista === 'cuentas'): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between"><h6 class="mb-0">Cuentas conectadas</h6><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCuenta"><i class="bi bi-plus"></i></button></div>
    <div class="card-body">
        <?php if (empty($cuentas)): ?><p class="text-muted text-center py-3">No hay cuentas conectadas.</p>
        <?php else: foreach ($cuentas as $c): ?>
        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
            <div><i class="bi <?= $platIcons[$c['plataforma']]??'bi-globe' ?> fs-4 me-2"></i><strong><?= sanitize($c['nombre']) ?></strong> <span class="badge bg-light text-dark"><?= ucfirst($c['plataforma']) ?></span></div>
            <span class="badge bg-<?= $c['activo']?'success':'secondary' ?>"><?= $c['activo']?'Conectada':'Inactiva' ?></span>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php elseif ($vista === 'calendario'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div id="calendarView">
            <?php
            $postsByDate = [];
            foreach ($posts as $post) {
                $date = $post['programado_para'] ? date('Y-m-d', strtotime($post['programado_para'])) : date('Y-m-d', strtotime($post['created_at']));
                $postsByDate[$date][] = $post;
            }
            $start = new DateTime('monday this week');
            ?>
            <div class="row g-1">
                <?php for ($d = 0; $d < 28; $d++):
                    $date = clone $start; $date->modify("+{$d} days");
                    $key = $date->format('Y-m-d');
                    $isToday = $key === date('Y-m-d');
                ?>
                <div class="col" style="min-width:14.28%">
                    <?php if ($d < 7): ?><div class="text-center small text-muted mb-1"><?= ['Lun','Mar','Mie','Jue','Vie','Sab','Dom'][$d] ?></div><?php endif; ?>
                    <div class="border rounded p-1 <?= $isToday?'border-primary':'' ?>" style="min-height:80px">
                        <div class="small <?= $isToday?'fw-bold text-primary':'' ?>"><?= $date->format('d') ?></div>
                        <?php foreach (($postsByDate[$key]??[]) as $post): ?>
                        <div class="small bg-<?= $estadoClases[$post['estado']] ?> text-white rounded px-1 mb-1 text-truncate" style="font-size:.65rem"><?= sanitize(mb_strimwidth($post['contenido'],0,25,'...')) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Contenido</th><th>Plataformas</th><th>Estado</th><th>Programado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($posts as $post):
                $plats = json_decode($post['plataformas'], true) ?: [];
            ?>
            <tr>
                <td><?= sanitize(mb_strimwidth($post['contenido'],0,80,'...')) ?></td>
                <td><?php foreach($plats as $pl): ?><i class="bi <?= $platIcons[$pl]??'bi-globe' ?> me-1"></i><?php endforeach; ?></td>
                <td><span class="badge bg-<?= $estadoClases[$post['estado']] ?>"><?= ucfirst($post['estado']) ?></span></td>
                <td class="small"><?= $post['programado_para']?formatFechaHora($post['programado_para']):'-' ?></td>
                <td class="text-end">
                    <?php if ($post['estado']!=='publicado'): ?>
                    <form method="POST" class="d-inline"><?= csrfField() ?><input type="hidden" name="accion" value="publicar"><input type="hidden" name="pid" value="<?= $post['id'] ?>"><button class="btn btn-xs btn-outline-success"><i class="bi bi-send"></i></button></form>
                    <?php endif; ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar_post"><input type="hidden" name="pid" value="<?= $post['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal nuevo post -->
<div class="modal fade" id="modalPost" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="crear_post">
    <div class="modal-header"><h5 class="modal-title">Nuevo Post</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Plataformas</label><div class="d-flex gap-3">
            <?php foreach(['facebook','instagram','google_business','linkedin','twitter'] as $pl): ?>
            <div class="form-check"><input type="checkbox" name="plataformas[]" value="<?= $pl ?>" class="form-check-input"><label class="form-check-label"><i class="bi <?= $platIcons[$pl] ?>"></i> <?= ucfirst($pl) ?></label></div>
            <?php endforeach; ?></div>
        </div>
        <div class="mb-3"><label class="form-label">Contenido</label><textarea name="contenido" class="form-control" rows="4" required></textarea><div class="form-text" id="charCount">0 / 280 caracteres</div></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">URL Imagen</label><input type="url" name="imagen_url" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Enlace</label><input type="url" name="enlace" class="form-control"></div>
        </div>
        <div class="mt-3"><label class="form-label">Programar para</label><input type="datetime-local" name="programado_para" class="form-control"><small class="text-muted">Dejar vacio para guardar como borrador</small></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Crear Post</button></div>
</form></div></div></div>

<!-- Modal cuenta -->
<div class="modal fade" id="modalCuenta" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="guardar_cuenta">
    <div class="modal-header"><h5 class="modal-title">Agregar Cuenta</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Plataforma</label><select name="plataforma" class="form-select"><option value="facebook">Facebook</option><option value="instagram">Instagram</option><option value="google_business">Google My Business</option><option value="linkedin">LinkedIn</option><option value="twitter">Twitter/X</option></select></div>
        <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="cuenta_nombre" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Access Token</label><input type="text" name="access_token" class="form-control"><small class="text-muted">Obtenlo desde la API de cada plataforma.</small></div>
        <div class="mb-3"><label class="form-label">Page ID</label><input type="text" name="page_id" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<script>
document.querySelector('textarea[name=contenido]')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length + ' / 280 caracteres';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
