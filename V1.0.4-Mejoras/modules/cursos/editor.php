<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$curso = $db->prepare("SELECT * FROM cursos WHERE id=?"); $curso->execute([$id]); $curso=$curso->fetch();
if (!$curso) { setFlash('danger','No encontrado.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'actualizar') {
        $db->prepare("UPDATE cursos SET titulo=?, descripcion=?, imagen=?, acceso=?, precio=? WHERE id=?")
            ->execute([trim(post('titulo')), trim(post('descripcion')), trim(post('imagen')), post('acceso'), floatval(post('precio')), $id]);
        setFlash('success','Curso actualizado.');
    }
    if ($a === 'agregar_leccion') {
        $max = $db->prepare("SELECT MAX(orden) FROM curso_lecciones WHERE curso_id=?"); $max->execute([$id]);
        $db->prepare("INSERT INTO curso_lecciones (curso_id, titulo, contenido, tipo, video_url, orden, duracion_min) VALUES (?,?,?,?,?,?,?)")
            ->execute([$id, trim(post('leccion_titulo')), trim(post('leccion_contenido')), post('leccion_tipo','texto'), trim(post('video_url')), intval($max->fetchColumn())+1, intval(post('duracion_min'))]);
        setFlash('success','Leccion agregada.');
    }
    if ($a === 'editar_leccion') {
        $db->prepare("UPDATE curso_lecciones SET titulo=?, contenido=?, tipo=?, video_url=?, duracion_min=? WHERE id=? AND curso_id=?")
            ->execute([trim(post('leccion_titulo')), trim(post('leccion_contenido')), post('leccion_tipo'), trim(post('video_url')), intval(post('duracion_min')), intval(post('leccion_id')), $id]);
        setFlash('success','Leccion actualizada.');
    }
    if ($a === 'eliminar_leccion') {
        $db->prepare("DELETE FROM curso_lecciones WHERE id=? AND curso_id=?")->execute([intval(post('leccion_id')), $id]);
        setFlash('success','Eliminada.');
    }
    header('Location: editor.php?id='.$id); exit;
}

$pageTitle = 'Editar Curso';
require_once __DIR__ . '/../../includes/header.php';
$lecciones = $db->prepare("SELECT * FROM curso_lecciones WHERE curso_id=? ORDER BY orden"); $lecciones->execute([$id]); $lecciones=$lecciones->fetchAll();
$matriculas = $db->prepare("SELECT cm.*, c.nombre, c.apellidos, c.email FROM curso_matriculas cm LEFT JOIN clientes c ON cm.cliente_id=c.id WHERE cm.curso_id=? ORDER BY cm.created_at DESC LIMIT 20"); $matriculas->execute([$id]); $matriculas=$matriculas->fetchAll();
$tipoIcons = ['texto'=>'bi-file-text','video'=>'bi-play-circle','pdf'=>'bi-file-pdf','quiz'=>'bi-question-circle'];
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>

<form method="POST">
    <?= csrfField() ?><input type="hidden" name="accion" value="actualizar">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control" value="<?= sanitize($curso['titulo']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Acceso</label><select name="acceso" class="form-select"><option value="privado" <?= $curso['acceso']==='privado'?'selected':'' ?>>Privado</option><option value="publico" <?= $curso['acceso']==='publico'?'selected':'' ?>>Publico</option><option value="pago" <?= $curso['acceso']==='pago'?'selected':'' ?>>De pago</option></select></div>
            <div class="col-md-2"><label class="form-label">Precio €</label><input type="number" name="precio" class="form-control" value="<?= $curso['precio'] ?>" step="0.01"></div>
            <div class="col-md-3"><label class="form-label">Imagen URL</label><input type="url" name="imagen" class="form-control" value="<?= sanitize($curso['imagen']) ?>"></div>
            <div class="col-md-10"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="2"><?= sanitize($curso['descripcion']) ?></textarea></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Guardar</button></div>
        </div>
    </div></div>
</form>

<!-- Lecciones -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between"><h6 class="mb-0"><i class="bi bi-list-ol"></i> Lecciones (<?= count($lecciones) ?>)</h6>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalLeccion"><i class="bi bi-plus"></i></button></div>
    <div class="card-body">
        <?php if (empty($lecciones)): ?><p class="text-muted text-center py-3">Agrega la primera leccion.</p>
        <?php else: foreach ($lecciones as $l): ?>
        <div class="d-flex align-items-center border-bottom py-2">
            <span class="badge bg-light text-dark me-2"><?= $l['orden'] ?></span>
            <i class="bi <?= $tipoIcons[$l['tipo']]??'bi-circle' ?> me-2 text-primary"></i>
            <div class="flex-grow-1"><strong class="small"><?= sanitize($l['titulo']) ?></strong><?= $l['duracion_min']?' <small class="text-muted">('.$l['duracion_min'].' min)</small>':'' ?></div>
            <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar_leccion"><input type="hidden" name="leccion_id" value="<?= $l['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Matriculas -->
<?php if (!empty($matriculas)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-people"></i> Alumnos (<?= count($matriculas) ?>)</h6></div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Alumno</th><th>Progreso</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>
    <?php foreach ($matriculas as $m): ?>
    <tr><td><?= sanitize(($m['nombre']??'').' '.($m['apellidos']??'')) ?></td><td><div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:<?= $m['progreso'] ?>%"></div></div></td><td><span class="badge bg-<?= ['activa'=>'primary','completada'=>'success','cancelada'=>'danger'][$m['estado']] ?>"><?= $m['estado'] ?></span></td><td class="small"><?= formatFecha($m['created_at']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalLeccion" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="agregar_leccion">
    <div class="modal-header"><h5 class="modal-title">Nueva Leccion</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Titulo</label><input type="text" name="leccion_titulo" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Tipo</label><select name="leccion_tipo" class="form-select"><option value="texto">Texto</option><option value="video">Video</option><option value="pdf">PDF</option><option value="quiz">Quiz</option></select></div>
            <div class="col-md-3"><label class="form-label">Duracion (min)</label><input type="number" name="duracion_min" class="form-control" value="0"></div>
            <div class="col-12"><label class="form-label">URL Video</label><input type="url" name="video_url" class="form-control"></div>
            <div class="col-12"><label class="form-label">Contenido</label><textarea name="leccion_contenido" class="form-control" rows="8"></textarea></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Agregar</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
