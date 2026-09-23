<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$campana = $db->prepare("SELECT * FROM campanas WHERE id=?"); $campana->execute([$id]); $campana=$campana->fetch();
if (!$campana) { setFlash('danger','No encontrada.'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'actualizar') {
        $db->prepare("UPDATE campanas SET nombre=?, descripcion=?, tipo=?, filtro_tags=? WHERE id=?")
            ->execute([trim(post('nombre')), trim(post('descripcion')), post('tipo'), post('filtro_tags')?:'[]', $id]);
        setFlash('success','Campana actualizada.');
        header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'agregar_paso') {
        $max = $db->prepare("SELECT MAX(orden) FROM campana_pasos WHERE campana_id=?"); $max->execute([$id]);
        $db->prepare("INSERT INTO campana_pasos (campana_id, orden, tipo, asunto, contenido, esperar_minutos) VALUES (?,?,?,?,?,?)")
            ->execute([$id, intval($max->fetchColumn())+1, post('paso_tipo'), trim(post('paso_asunto')), trim(post('paso_contenido')), intval(post('esperar_minutos'))]);
        setFlash('success','Paso agregado.');
        header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'editar_paso') {
        $db->prepare("UPDATE campana_pasos SET tipo=?, asunto=?, contenido=?, esperar_minutos=? WHERE id=? AND campana_id=?")
            ->execute([post('paso_tipo'), trim(post('paso_asunto')), trim(post('paso_contenido')), intval(post('esperar_minutos')), intval(post('paso_id')), $id]);
        setFlash('success','Paso actualizado.');
        header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'eliminar_paso') {
        $db->prepare("DELETE FROM campana_pasos WHERE id=? AND campana_id=?")->execute([intval(post('paso_id')), $id]);
        setFlash('success','Paso eliminado.');
        header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'agregar_contactos') {
        $tagId = intval(post('tag_id'));
        if ($tagId) {
            $clientes = $db->prepare("SELECT DISTINCT c.id FROM clientes c INNER JOIN cliente_tags ct ON c.id=ct.cliente_id WHERE ct.tag_id=? AND c.id NOT IN (SELECT cliente_id FROM campana_contactos WHERE campana_id=?)");
            $clientes->execute([$tagId, $id]);
            $stmt = $db->prepare("INSERT INTO campana_contactos (campana_id, cliente_id, estado, proximo_envio) VALUES (?,?,'pendiente',NOW())");
            $count = 0;
            foreach ($clientes->fetchAll() as $c) { $stmt->execute([$id, $c['id']]); $count++; }
            $db->prepare("UPDATE campanas SET total_contactos = total_contactos + ? WHERE id=?")->execute([$count, $id]);
            setFlash('success', $count.' contactos agregados.');
        } else {
            $clienteIds = post('cliente_ids');
            if ($clienteIds) {
                $ids = array_filter(array_map('intval', explode(',', $clienteIds)));
                $stmt = $db->prepare("INSERT IGNORE INTO campana_contactos (campana_id, cliente_id, estado, proximo_envio) VALUES (?,?,'pendiente',NOW())");
                $count = 0;
                foreach ($ids as $cid) { $stmt->execute([$id, $cid]); $count++; }
                $db->prepare("UPDATE campanas SET total_contactos = total_contactos + ? WHERE id=?")->execute([$count, $id]);
                setFlash('success', $count.' contactos agregados.');
            }
        }
        header('Location: editor.php?id='.$id); exit;
    }
    if ($accion === 'lanzar') {
        $db->prepare("UPDATE campanas SET estado='activa' WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE campana_contactos SET estado='activo', proximo_envio=NOW() WHERE campana_id=? AND estado='pendiente'")->execute([$id]);
        setFlash('success','Campana lanzada.');
        header('Location: editor.php?id='.$id); exit;
    }
}

$pageTitle = 'Editar Campana';
require_once __DIR__ . '/../../includes/header.php';

$pasos = $db->prepare("SELECT * FROM campana_pasos WHERE campana_id=? ORDER BY orden"); $pasos->execute([$id]); $pasos=$pasos->fetchAll();
$contactos = $db->prepare("SELECT cc.*, c.nombre, c.apellidos, c.email, c.telefono FROM campana_contactos cc LEFT JOIN clientes c ON cc.cliente_id=c.id WHERE cc.campana_id=? ORDER BY cc.created_at DESC LIMIT 50"); $contactos->execute([$id]); $contactos=$contactos->fetchAll();
$tags = $db->query("SELECT * FROM tags ORDER BY nombre")->fetchAll();
$plantillas = $db->query("SELECT id, nombre FROM email_plantillas ORDER BY nombre")->fetchAll();

$tipoIcons = ['email'=>'bi-envelope text-primary','sms'=>'bi-phone text-success','esperar'=>'bi-clock text-warning','condicion'=>'bi-signpost-split text-purple'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
    <div class="d-flex gap-2">
        <span class="badge bg-<?= ['borrador'=>'secondary','activa'=>'success','pausada'=>'warning','completada'=>'info'][$campana['estado']] ?> fs-6"><?= ucfirst($campana['estado']) ?></span>
        <?php if ($campana['estado'] !== 'activa'): ?>
        <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="lanzar"><button class="btn btn-success btn-sm"><i class="bi bi-play"></i> Lanzar</button></form>
        <?php endif; ?>
    </div>
</div>

<!-- Config -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" class="row g-2 align-items-end">
            <?= csrfField() ?><input type="hidden" name="accion" value="actualizar">
            <div class="col-md-3"><label class="form-label small">Nombre</label><input type="text" name="nombre" class="form-control form-control-sm" value="<?= sanitize($campana['nombre']) ?>" required></div>
            <div class="col-md-2"><label class="form-label small">Tipo</label><select name="tipo" class="form-select form-select-sm"><option value="email" <?= $campana['tipo']==='email'?'selected':'' ?>>Email</option><option value="sms" <?= $campana['tipo']==='sms'?'selected':'' ?>>SMS</option><option value="mixta" <?= $campana['tipo']==='mixta'?'selected':'' ?>>Mixta</option></select></div>
            <div class="col-md-5"><label class="form-label small">Descripcion</label><input type="text" name="descripcion" class="form-control form-control-sm" value="<?= sanitize($campana['descripcion']) ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Guardar</button></div>
        </form>
    </div>
</div>

<!-- Secuencia -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list-ol"></i> Secuencia (<?= count($pasos) ?> pasos)</h6>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaso"><i class="bi bi-plus"></i> Paso</button>
    </div>
    <div class="card-body">
        <?php if (empty($pasos)): ?>
        <p class="text-muted text-center py-3">Agrega pasos a la secuencia.</p>
        <?php else: ?>
        <div class="timeline-sequence">
            <?php foreach ($pasos as $i => $p): ?>
            <div class="d-flex align-items-start mb-3">
                <div class="me-3 text-center" style="min-width:40px">
                    <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px">
                        <i class="bi <?= $tipoIcons[$p['tipo']]??'bi-circle' ?>"></i>
                    </div>
                    <?php if ($i < count($pasos)-1): ?><div class="border-start mx-auto" style="height:30px;width:0"></div><?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <div class="card border">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong class="small"><?= ucfirst($p['tipo']) ?><?= $p['tipo']==='esperar'?' ('.$p['esperar_minutos'].' min)':'' ?></strong>
                                    <?php if ($p['asunto']): ?><br><small><?= sanitize($p['asunto']) ?></small><?php endif; ?>
                                    <?php if ($p['contenido']): ?><br><small class="text-muted"><?= sanitize(mb_strimwidth($p['contenido'],0,100,'...')) ?></small><?php endif; ?>
                                </div>
                                <div class="d-flex gap-1">
                                    <small class="text-muted"><?= $p['enviados'] ?> env.</small>
                                    <button class="btn btn-xs btn-outline-primary" onclick='editPaso(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i></button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar_paso"><input type="hidden" name="paso_id" value="<?= $p['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Contactos -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-people"></i> Contactos (<?= $campana['total_contactos'] ?>)</h6>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalContactos"><i class="bi bi-plus"></i> Agregar</button>
    </div>
    <?php if (!empty($contactos)): ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Nombre</th><th>Email/Tel</th><th>Paso</th><th>Estado</th><th>Proximo envio</th></tr></thead>
            <tbody>
            <?php foreach ($contactos as $cc): ?>
            <tr>
                <td><?= sanitize(($cc['nombre']??'').' '.($cc['apellidos']??'')) ?></td>
                <td class="small"><?= sanitize($cc['email']??$cc['telefono']??'') ?></td>
                <td><?= $cc['paso_actual'] ?>/<?= count($pasos) ?></td>
                <td><span class="badge bg-<?= ['pendiente'=>'secondary','activo'=>'primary','completado'=>'success','cancelado'=>'danger'][$cc['estado']] ?>"><?= $cc['estado'] ?></span></td>
                <td class="small"><?= $cc['proximo_envio']?formatFechaHora($cc['proximo_envio']):'-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal agregar paso -->
<div class="modal fade" id="modalPaso" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="agregar_paso">
    <div class="modal-header"><h5 class="modal-title">Nuevo Paso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Tipo</label><select name="paso_tipo" class="form-select" onchange="togglePasoType(this.value)"><option value="email">Email</option><option value="sms">SMS</option><option value="esperar">Esperar</option></select></div>
        <div id="pasoEmailFields">
            <div class="mb-3"><label class="form-label">Asunto</label><input type="text" name="paso_asunto" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Contenido</label><textarea name="paso_contenido" class="form-control" rows="5" placeholder="Variables: {{nombre}}, {{apellidos}}, {{email}}"></textarea></div>
        </div>
        <div id="pasoEsperarFields" style="display:none">
            <div class="mb-3"><label class="form-label">Esperar (minutos)</label><input type="number" name="esperar_minutos" class="form-control" value="1440" min="1"><small class="text-muted">1440 min = 1 dia, 10080 min = 1 semana</small></div>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Agregar</button></div>
</form></div></div></div>

<!-- Modal editar paso -->
<div class="modal fade" id="modalEditPaso" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="editar_paso"><input type="hidden" name="paso_id" id="ep_id">
    <div class="modal-header"><h5 class="modal-title">Editar Paso</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Tipo</label><select name="paso_tipo" id="ep_tipo" class="form-select"><option value="email">Email</option><option value="sms">SMS</option><option value="esperar">Esperar</option></select></div>
        <div class="mb-3"><label class="form-label">Asunto</label><input type="text" name="paso_asunto" id="ep_asunto" class="form-control"></div>
        <div class="mb-3"><label class="form-label">Contenido</label><textarea name="paso_contenido" id="ep_contenido" class="form-control" rows="5"></textarea></div>
        <div class="mb-3"><label class="form-label">Esperar (min)</label><input type="number" name="esperar_minutos" id="ep_esperar" class="form-control"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
</form></div></div></div>

<!-- Modal agregar contactos -->
<div class="modal fade" id="modalContactos" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="agregar_contactos">
    <div class="modal-header"><h5 class="modal-title">Agregar Contactos</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Por Tag</label>
            <select name="tag_id" class="form-select"><option value="">-- Seleccionar tag --</option>
            <?php foreach($tags as $t): ?><option value="<?= $t['id'] ?>"><?= sanitize($t['nombre']) ?></option><?php endforeach; ?>
            </select>
            <small class="text-muted">Todos los clientes con este tag se agregaran a la campana.</small>
        </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Agregar</button></div>
</form></div></div></div>

<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>
<script>
function togglePasoType(t) {
    document.getElementById('pasoEmailFields').style.display = t==='esperar'?'none':'';
    document.getElementById('pasoEsperarFields').style.display = t==='esperar'?'':'none';
}
function editPaso(p) {
    document.getElementById('ep_id').value=p.id;
    document.getElementById('ep_tipo').value=p.tipo;
    document.getElementById('ep_asunto').value=p.asunto||'';
    document.getElementById('ep_contenido').value=p.contenido||'';
    document.getElementById('ep_esperar').value=p.esperar_minutos||0;
    new bootstrap.Modal(document.getElementById('modalEditPaso')).show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
