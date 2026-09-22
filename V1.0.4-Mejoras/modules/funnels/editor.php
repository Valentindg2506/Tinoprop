<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$funnel = $db->prepare("SELECT * FROM funnels WHERE id=?"); $funnel->execute([$id]); $funnel = $funnel->fetch();
if (!$funnel) { setFlash('danger','Funnel no encontrado.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'actualizar_funnel') {
        $db->prepare("UPDATE funnels SET nombre=?, descripcion=? WHERE id=?")->execute([trim(post('nombre')), trim(post('descripcion')), $id]);
        setFlash('success','Funnel actualizado.'); header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'agregar_paso') {
        $maxOrden = $db->prepare("SELECT MAX(orden) FROM funnel_pasos WHERE funnel_id=?"); $maxOrden->execute([$id]); $maxOrden = intval($maxOrden->fetchColumn());
        $db->prepare("INSERT INTO funnel_pasos (funnel_id,orden,nombre,tipo) VALUES (?,?,?,?)")
            ->execute([$id, $maxOrden+1, trim(post('paso_nombre')), post('paso_tipo')]);
        setFlash('success','Paso agregado.'); header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'editar_paso') {
        $pid = intval(post('paso_id'));
        $db->prepare("UPDATE funnel_pasos SET nombre=?, tipo=?, landing_page_id=?, formulario_id=?, contenido_html=? WHERE id=? AND funnel_id=?")
            ->execute([trim(post('paso_nombre')), post('paso_tipo'), intval(post('landing_page_id'))?:null, intval(post('formulario_id'))?:null, $_POST['contenido_html'] ?? '', $pid, $id]);
        setFlash('success','Paso actualizado.'); header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'eliminar_paso') {
        $db->prepare("DELETE FROM funnel_pasos WHERE id=? AND funnel_id=?")->execute([intval(post('paso_id')), $id]);
        setFlash('success','Paso eliminado.'); header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'reordenar') {
        $orden = json_decode($_POST['orden_json'] ?? '[]', true);
        if ($orden) {
            $stmt = $db->prepare("UPDATE funnel_pasos SET orden=? WHERE id=? AND funnel_id=?");
            foreach ($orden as $i => $pid) $stmt->execute([$i+1, intval($pid), $id]);
        }
        setFlash('success','Orden actualizado.'); header('Location: editor.php?id='.$id); exit;
    }
}

$pageTitle = 'Editor Funnel: ' . $funnel['nombre'];
require_once __DIR__ . '/../../includes/header.php';

$pasos = $db->prepare("SELECT * FROM funnel_pasos WHERE funnel_id=? ORDER BY orden"); $pasos->execute([$id]); $pasos = $pasos->fetchAll();
try { $landings = $db->query("SELECT id, titulo FROM landing_pages ORDER BY titulo")->fetchAll(); } catch (Exception $e) { $landings = []; }
try { $formularios = $db->query("SELECT id, nombre FROM formularios ORDER BY nombre")->fetchAll(); } catch (Exception $e) { $formularios = []; }

$tipoIcons = ['landing'=>'bi-file-earmark-richtext text-primary','formulario'=>'bi-ui-checks-grid text-success','upsell'=>'bi-arrow-up-circle text-warning','downsell'=>'bi-arrow-down-circle text-info','gracias'=>'bi-check-circle text-success','custom'=>'bi-code-slash text-secondary'];
$tipoLabels = ['landing'=>'Landing Page','formulario'=>'Formulario','upsell'=>'Upsell','downsell'=>'Downsell','gracias'=>'Pagina Gracias','custom'=>'HTML Custom'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-info" onclick="navigator.clipboard.writeText('<?= APP_URL ?>/funnel.php?id=<?= $id ?>')"><i class="bi bi-link"></i> Copiar enlace</button>
        <a href="stats.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-graph-up"></i> Estadisticas</a>
    </div>
</div>

<!-- Config funnel -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <?= csrfField() ?><input type="hidden" name="accion" value="actualizar_funnel">
            <div class="col-md-4"><label class="form-label small">Nombre</label><input type="text" name="nombre" class="form-control form-control-sm" value="<?= sanitize($funnel['nombre']) ?>" required></div>
            <div class="col-md-6"><label class="form-label small">Descripcion</label><input type="text" name="descripcion" class="form-control form-control-sm" value="<?= sanitize($funnel['descripcion']) ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Guardar</button></div>
        </form>
    </div>
</div>

<!-- Funnel visual -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-funnel"></i> Pasos del Funnel</h6>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaso"><i class="bi bi-plus"></i> Agregar Paso</button>
    </div>
    <div class="card-body">
        <?php if (empty($pasos)): ?>
        <p class="text-muted text-center py-3">No hay pasos. Agrega el primero.</p>
        <?php else: ?>
        <div class="d-flex flex-wrap gap-3 align-items-center" id="funnelSteps">
            <?php foreach ($pasos as $i => $p): ?>
            <div class="funnel-step" data-id="<?= $p['id'] ?>" style="min-width:180px">
                <div class="card border-2 <?= $p['tipo']==='gracias'?'border-success':'border-primary' ?> h-100">
                    <div class="card-body text-center p-3">
                        <div class="mb-1"><i class="bi <?= $tipoIcons[$p['tipo']]??'bi-circle' ?> fs-3"></i></div>
                        <h6 class="fw-bold small mb-1"><?= sanitize($p['nombre']) ?></h6>
                        <span class="badge bg-light text-dark"><?= $tipoLabels[$p['tipo']]??$p['tipo'] ?></span>
                        <div class="mt-2 small text-muted">
                            <span><?= $p['visitas'] ?> visitas</span> &middot;
                            <span><?= $p['conversiones'] ?> conv.</span>
                            <?php if($p['visitas']>0): ?> &middot; <span><?= round($p['conversiones']/$p['visitas']*100,1) ?>%</span><?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <button class="btn btn-xs btn-outline-primary" onclick="editarPaso(<?= htmlspecialchars(json_encode($p)) ?>)"><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar paso?')">
                                <?= csrfField() ?><input type="hidden" name="accion" value="eliminar_paso"><input type="hidden" name="paso_id" value="<?= $p['id'] ?>">
                                <button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($i < count($pasos)-1): ?>
            <div class="text-muted"><i class="bi bi-arrow-right fs-4"></i></div>
            <?php endif; endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal agregar paso -->
<div class="modal fade" id="modalPaso" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <?= csrfField() ?><input type="hidden" name="accion" value="agregar_paso">
        <div class="modal-header"><h5 class="modal-title">Agregar Paso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="paso_nombre" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label">Tipo</label>
                <select name="paso_tipo" class="form-select">
                    <option value="landing">Landing Page</option>
                    <option value="formulario">Formulario</option>
                    <option value="upsell">Upsell</option>
                    <option value="downsell">Downsell</option>
                    <option value="gracias">Pagina de Gracias</option>
                    <option value="custom">HTML Custom</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Agregar</button></div>
    </form>
</div></div></div>

<!-- Modal editar paso -->
<div class="modal fade" id="modalEditPaso" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">
        <?= csrfField() ?><input type="hidden" name="accion" value="editar_paso"><input type="hidden" name="paso_id" id="ep_id">
        <div class="modal-header"><h5 class="modal-title">Editar Paso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="paso_nombre" id="ep_nombre" class="form-control" required></div>
                <div class="col-md-6">
                    <label class="form-label">Tipo</label>
                    <select name="paso_tipo" id="ep_tipo" class="form-select" onchange="togglePasoFields()">
                        <option value="landing">Landing Page</option><option value="formulario">Formulario</option>
                        <option value="upsell">Upsell</option><option value="downsell">Downsell</option>
                        <option value="gracias">Pagina de Gracias</option><option value="custom">HTML Custom</option>
                    </select>
                </div>
                <div class="col-md-6" id="ep_landing_wrap">
                    <label class="form-label">Landing Page</label>
                    <select name="landing_page_id" id="ep_landing" class="form-select">
                        <option value="0">-- Ninguna --</option>
                        <?php foreach($landings as $lp): ?><option value="<?= $lp['id'] ?>"><?= sanitize($lp['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="ep_form_wrap">
                    <label class="form-label">Formulario</label>
                    <select name="formulario_id" id="ep_form" class="form-select">
                        <option value="0">-- Ninguno --</option>
                        <?php foreach($formularios as $fm): ?><option value="<?= $fm['id'] ?>"><?= sanitize($fm['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12" id="ep_html_wrap">
                    <label class="form-label">Contenido HTML</label>
                    <textarea name="contenido_html" id="ep_html" class="form-control font-monospace" rows="8"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Guardar Paso</button></div>
    </form>
</div></div></div>

<script>
function editarPaso(p) {
    document.getElementById('ep_id').value = p.id;
    document.getElementById('ep_nombre').value = p.nombre;
    document.getElementById('ep_tipo').value = p.tipo;
    document.getElementById('ep_landing').value = p.landing_page_id || 0;
    document.getElementById('ep_form').value = p.formulario_id || 0;
    document.getElementById('ep_html').value = p.contenido_html || '';
    togglePasoFields();
    new bootstrap.Modal(document.getElementById('modalEditPaso')).show();
}
function togglePasoFields() {
    const t = document.getElementById('ep_tipo').value;
    document.getElementById('ep_landing_wrap').style.display = (t==='landing'||t==='upsell'||t==='downsell') ? '' : 'none';
    document.getElementById('ep_form_wrap').style.display = (t==='formulario') ? '' : 'none';
    document.getElementById('ep_html_wrap').style.display = (t==='custom'||t==='gracias') ? '' : 'none';
}
</script>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
