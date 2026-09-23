<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$uploadDir = __DIR__ . '/../../uploads/medios/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');

    if ($a === 'subir' && !empty($_FILES['archivo']['name'])) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf','video/mp4','application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $file = $_FILES['archivo'];
        if ($file['size'] > 20*1024*1024) { setFlash('danger','Archivo demasiado grande (max 20MB).'); }
        elseif (!in_array($file['type'], $allowed)) { setFlash('danger','Tipo de archivo no permitido.'); }
        else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = uniqid().'_'.time().'.'.$ext;
            $carpeta = trim(post('carpeta','general'));
            $subDir = $uploadDir . $carpeta . '/';
            if (!is_dir($subDir)) mkdir($subDir, 0755, true);

            if (move_uploaded_file($file['tmp_name'], $subDir . $newName)) {
                $tipo = 'otro';
                if (str_starts_with($file['type'], 'image/')) $tipo = 'imagen';
                elseif (str_starts_with($file['type'], 'video/')) $tipo = 'video';
                elseif (str_contains($file['type'], 'pdf') || str_contains($file['type'], 'document')) $tipo = 'documento';

                $ancho = $alto = null;
                if ($tipo === 'imagen' && function_exists('getimagesize')) {
                    $info = @getimagesize($subDir . $newName);
                    if ($info) { $ancho = $info[0]; $alto = $info[1]; }
                }

                $db->prepare("INSERT INTO medios (nombre, archivo, tipo, mime_type, tamano, ancho, alto, carpeta, usuario_id) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$file['name'], $carpeta.'/'.$newName, $tipo, $file['type'], $file['size'], $ancho, $alto, $carpeta, currentUserId()]);
                setFlash('success', 'Archivo subido.');
            } else { setFlash('danger', 'Error al subir archivo.'); }
        }
        header('Location: index.php?carpeta='.urlencode($carpeta)); exit;
    }
    if ($a === 'eliminar') {
        $mid = intval(post('mid'));
        $m = $db->prepare("SELECT * FROM medios WHERE id=?"); $m->execute([$mid]); $m=$m->fetch();
        if ($m) {
            @unlink($uploadDir . $m['archivo']);
            $db->prepare("DELETE FROM medios WHERE id=?")->execute([$mid]);
            setFlash('success','Eliminado.');
        }
        header('Location: index.php'); exit;
    }
    if ($a === 'crear_carpeta') {
        setFlash('success','Carpeta creada.'); // Folders are virtual in DB
        header('Location: index.php?carpeta='.urlencode(trim(post('nueva_carpeta')))); exit;
    }
}

$pageTitle = 'Gestor de Medios';
require_once __DIR__ . '/../../includes/header.php';

$carpeta = get('carpeta', '');
$tipo = get('tipo', '');
$where = "WHERE 1=1";
$params = [];
if ($carpeta) { $where .= " AND carpeta = ?"; $params[] = $carpeta; }
if ($tipo) { $where .= " AND tipo = ?"; $params[] = $tipo; }

$medios = $db->prepare("SELECT * FROM medios $where ORDER BY created_at DESC"); $medios->execute($params); $medios=$medios->fetchAll();
$carpetas = $db->query("SELECT DISTINCT carpeta FROM medios ORDER BY carpeta")->fetchAll(PDO::FETCH_COLUMN);
$totalSize = array_sum(array_column($medios, 'tamano'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-sm <?= !$carpeta&&!$tipo?'btn-primary':'btn-outline-secondary' ?>">Todos</a>
        <a href="?tipo=imagen" class="btn btn-sm <?= $tipo==='imagen'?'btn-primary':'btn-outline-secondary' ?>"><i class="bi bi-image"></i> Imagenes</a>
        <a href="?tipo=documento" class="btn btn-sm <?= $tipo==='documento'?'btn-primary':'btn-outline-secondary' ?>"><i class="bi bi-file-pdf"></i> Docs</a>
        <a href="?tipo=video" class="btn btn-sm <?= $tipo==='video'?'btn-primary':'btn-outline-secondary' ?>"><i class="bi bi-play-circle"></i> Videos</a>
    </div>
    <div class="d-flex gap-2">
        <small class="text-muted align-self-center"><?= count($medios) ?> archivos &middot; <?= round($totalSize/1024/1024, 1) ?> MB</small>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSubir"><i class="bi bi-upload"></i> Subir</button>
    </div>
</div>

<div class="row g-3">
    <!-- Carpetas sidebar -->
    <div class="col-md-2">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-2">
                <h6 class="small text-uppercase text-muted px-2">Carpetas</h6>
                <a href="index.php" class="d-block small px-2 py-1 rounded <?= !$carpeta?'bg-primary text-white':'text-dark' ?> text-decoration-none">Todas</a>
                <?php foreach ($carpetas as $c): ?>
                <a href="?carpeta=<?= urlencode($c) ?>" class="d-block small px-2 py-1 rounded <?= $carpeta===$c?'bg-primary text-white':'text-dark' ?> text-decoration-none"><i class="bi bi-folder"></i> <?= sanitize($c) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Grid -->
    <div class="col-md-10">
        <?php if (empty($medios)): ?>
        <div class="text-center text-muted py-5"><i class="bi bi-cloud-upload fs-1 d-block mb-3"></i><h5>No hay archivos</h5></div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($medios as $m):
                $url = APP_URL . '/uploads/medios/' . $m['archivo'];
            ?>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <?php if ($m['tipo'] === 'imagen'): ?>
                    <img src="<?= $url ?>" class="card-img-top" style="height:120px;object-fit:cover" loading="lazy">
                    <?php else: ?>
                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height:120px">
                        <i class="bi <?= $m['tipo']==='video'?'bi-play-circle':'bi-file-earmark' ?> fs-1 text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body p-2">
                        <div class="small fw-semibold text-truncate"><?= sanitize($m['nombre']) ?></div>
                        <div class="small text-muted"><?= round($m['tamano']/1024) ?> KB<?= $m['ancho']?' &middot; '.$m['ancho'].'x'.$m['alto']:'' ?></div>
                    </div>
                    <div class="card-footer bg-white border-0 p-2 d-flex justify-content-between">
                        <button class="btn btn-xs btn-outline-info" onclick="navigator.clipboard.writeText('<?= $url ?>');this.innerHTML='Copiado'"><i class="bi bi-link"></i> URL</button>
                        <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="mid" value="<?= $m['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal subir -->
<div class="modal fade" id="modalSubir" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="accion" value="subir">
    <div class="modal-header"><h5 class="modal-title">Subir Archivo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Archivo</label><input type="file" name="archivo" class="form-control" required accept="image/*,.pdf,.doc,.docx,.mp4"></div>
        <div class="mb-3"><label class="form-label">Carpeta</label><input type="text" name="carpeta" class="form-control" value="<?= sanitize($carpeta?:'general') ?>" list="carpetasList">
            <datalist id="carpetasList"><?php foreach($carpetas as $c): ?><option value="<?= sanitize($c) ?>"><?php endforeach; ?></datalist>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-upload"></i> Subir</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
