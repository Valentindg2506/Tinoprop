<?php
$pageTitle = 'Detalle Cliente';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));

function clienteSafeFetchAll(PDO $db, $sql, array $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

$stmt = $db->prepare("SELECT c.*, u.nombre as agente_nombre, u.apellidos as agente_apellidos FROM clientes c LEFT JOIN usuarios u ON c.agente_id = u.id WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { setFlash('danger', 'Cliente no encontrado.'); header('Location: index.php'); exit; }

// Propiedades como propietario
$propiedades = clienteSafeFetchAll($db, "SELECT id, referencia, titulo, tipo, operacion, precio, estado FROM propiedades WHERE propietario_id = ? ORDER BY created_at DESC", [$id]);

// Visitas
$visitas = clienteSafeFetchAll($db, "SELECT v.*, p.referencia, p.titulo as propiedad FROM visitas v JOIN propiedades p ON v.propiedad_id = p.id WHERE v.cliente_id = ? ORDER BY v.fecha DESC LIMIT 10", [$id]);

// Documentos
$docs = clienteSafeFetchAll($db, "SELECT * FROM documentos WHERE cliente_id = ? ORDER BY created_at DESC", [$id]);

// Actividad reciente
$actividad = clienteSafeFetchAll($db, "SELECT a.*, u.nombre as actor FROM actividad_log a LEFT JOIN usuarios u ON a.usuario_id = u.id WHERE a.entidad_tipo = 'cliente' AND a.entidad_id = ? ORDER BY a.created_at DESC LIMIT 20", [$id]);

// Propiedades compatibles
$matchQuery = "SELECT p.id, p.referencia, p.titulo, p.tipo, p.precio, p.localidad, p.provincia, p.habitaciones, p.superficie_construida
    FROM propiedades p WHERE p.estado = 'disponible'";
$matchParams = [];
if ($c['operacion_interes'] && $c['operacion_interes'] !== 'ambas') { $matchQuery .= " AND p.operacion = ?"; $matchParams[] = $c['operacion_interes']; }
if ($c['presupuesto_max']) { $matchQuery .= " AND p.precio <= ?"; $matchParams[] = $c['presupuesto_max']; }
if ($c['presupuesto_min']) { $matchQuery .= " AND p.precio >= ?"; $matchParams[] = $c['presupuesto_min']; }
if ($c['habitaciones_min']) { $matchQuery .= " AND p.habitaciones >= ?"; $matchParams[] = $c['habitaciones_min']; }
if ($c['superficie_min']) { $matchQuery .= " AND p.superficie_construida >= ?"; $matchParams[] = $c['superficie_min']; }
$matchQuery .= " ORDER BY p.created_at DESC LIMIT 10";
$matches = clienteSafeFetchAll($db, $matchQuery, $matchParams);

// Tags
$clienteTags = clienteSafeFetchAll($db, "SELECT t.id, t.nombre, t.color FROM tags t JOIN cliente_tags ct ON t.id = ct.tag_id WHERE ct.cliente_id = ? ORDER BY t.nombre", [$id]);

// Campos personalizados
$customFields = clienteSafeFetchAll($db, "SELECT cf.*, cfv.valor FROM custom_fields cf LEFT JOIN custom_field_values cfv ON cf.id = cfv.field_id AND cfv.entidad_id = ? WHERE cf.entidad = 'cliente' AND cf.activo = 1 ORDER BY cf.orden", [$id]);

$tipos = getTiposPropiedad();
$tipoBadge = ['comprador'=>'bg-info','vendedor'=>'bg-success','inquilino'=>'bg-warning text-dark','propietario'=>'bg-primary','inversor'=>'bg-danger'];

// WhatsApp helper
function waNum($tel) {
    $clean = preg_replace('/[^0-9]/', '', $tel);
    return strlen($clean) === 9 ? '34' . $clean : $clean;
}
?>

<style>
.detail-label { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.detail-value { font-size: 0.9rem; }
.timeline-entry { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,0.06); }
.timeline-entry:last-child { border-bottom: none; }
.tl-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #fff; flex-shrink: 0; }
.tl-content { flex: 1; min-width: 0; }
.info-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 0.8rem; padding: 3px 10px; border-radius: 20px; border: 1px solid; text-decoration: none; transition: all .15s; }
.info-btn:hover { opacity: .8; }
</style>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        <?php
        foreach (explode(',', $c['tipo']) as $t):
            $t = trim($t); if (!$t) continue;
            $bc = $tipoBadge[$t] ?? 'bg-secondary';
        ?>
        <span class="badge <?= $bc ?> fs-6"><?= ucfirst($t) ?></span>
        <?php endforeach; ?>
        <?php if (!$c['activo']): ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <a href="timeline.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history"></i> Timeline</a>
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Editar</a>
        <a href="rgpd.php?id=<?= $id ?>" class="btn btn-outline-info"><i class="bi bi-shield-lock"></i> RGPD</a>
        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar este cliente?')">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= intval($id) ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Columna izquierda -->
    <div class="col-lg-4">

        <!-- Datos personales -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person"></i> Datos del Cliente</div>
            <div class="card-body">
                <h5 class="mb-3"><?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?></h5>

                <?php if ($c['dni_nie_cif']): ?>
                <div class="mb-2">
                    <small class="text-muted d-block">DNI / NIE / CIF</small>
                    <span><?= sanitize($c['dni_nie_cif']) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($c['email']): ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Email</small>
                    <a href="mailto:<?= sanitize($c['email']) ?>"><?= sanitize($c['email']) ?></a>
                </div>
                <?php endif; ?>

                <div class="mb-2">
                    <small class="text-muted d-block">Teléfono</small>
                    <?php if ($c['telefono']): ?>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="tel:<?= sanitize($c['telefono']) ?>"><?= sanitize($c['telefono']) ?></a>
                        <a href="https://wa.me/<?= waNum($c['telefono']) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-success py-0 px-2" style="font-size:0.75rem;">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                    </div>
                    <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                </div>

                <?php if ($c['telefono2']): ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Teléfono 2</small>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="tel:<?= sanitize($c['telefono2']) ?>"><?= sanitize($c['telefono2']) ?></a>
                        <a href="https://wa.me/<?= waNum($c['telefono2']) ?>" target="_blank" rel="noopener"
                           class="btn btn-sm btn-success py-0 px-2" style="font-size:0.75rem;">
                            <i class="bi bi-whatsapp"></i> WA
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($c['direccion']): ?>
                <div class="mb-2">
                    <small class="text-muted d-block">Dirección</small>
                    <span><?= sanitize($c['direccion']) ?><?= $c['localidad'] ? ', ' . sanitize($c['localidad']) : '' ?><?= $c['provincia'] ? ' (' . sanitize($c['provincia']) . ')' : '' ?></span>
                </div>
                <?php endif; ?>

                <div class="mb-0 mt-3">
                    <small class="text-muted">Origen: <?= ucfirst($c['origen']) ?> &nbsp;·&nbsp; Alta: <?= formatFecha($c['created_at']) ?></small>
                </div>
            </div>
        </div>

        <!-- Preferencias -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-search"></i> Preferencias de Búsqueda</div>
            <div class="card-body">
                <?php if (!$c['operacion_interes'] && !$c['zona_interes'] && !$c['presupuesto_min'] && !$c['presupuesto_max']): ?>
                    <p class="text-muted mb-0 small">Sin preferencias definidas</p>
                <?php else: ?>
                <div class="row g-2">
                    <?php if ($c['operacion_interes']): ?>
                    <div class="col-6"><div class="detail-label">Operación</div><div class="detail-value"><?= ucfirst($c['operacion_interes']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['tipo_inmueble_interes']): ?>
                    <div class="col-6"><div class="detail-label">Tipo inmueble</div><div class="detail-value"><?= sanitize($c['tipo_inmueble_interes']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['presupuesto_min'] || $c['presupuesto_max']): ?>
                    <div class="col-12"><div class="detail-label">Presupuesto</div><div class="detail-value fw-semibold"><?= $c['presupuesto_min'] ? formatPrecio($c['presupuesto_min']) : '0' ?> – <?= $c['presupuesto_max'] ? formatPrecio($c['presupuesto_max']) : 'sin límite' ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['zona_interes']): ?>
                    <div class="col-12"><div class="detail-label">Zona</div><div class="detail-value"><?= sanitize($c['zona_interes']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['habitaciones_min']): ?>
                    <div class="col-6"><div class="detail-label">Hab. mínimas</div><div class="detail-value"><?= $c['habitaciones_min'] ?></div></div>
                    <?php endif; ?>
                    <?php if ($c['superficie_min']): ?>
                    <div class="col-6"><div class="detail-label">Sup. mínima</div><div class="detail-value"><?= formatSuperficie($c['superficie_min']) ?></div></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notas -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-chat-text"></i> Notas</div>
            <div class="card-body">
                <?php if ($c['notas']): ?>
                    <?= nl2br(sanitize($c['notas'])) ?>
                <?php else: ?>
                    <p class="text-muted mb-0 small">Sin notas</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tags -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-tags"></i> Etiquetas</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTags"><i class="bi bi-plus"></i></button>
            </div>
            <div class="card-body" id="tagsContainer">
                <?php if (empty($clienteTags)): ?>
                    <span class="text-muted small" id="noTagsMsg">Sin etiquetas</span>
                <?php else: ?>
                    <?php foreach ($clienteTags as $tag): ?>
                    <span class="badge me-1 mb-1" style="background:<?= sanitize($tag['color']) ?>; font-size:0.8rem;">
                        <?= sanitize($tag['nombre']) ?>
                        <a href="#" class="text-white ms-1" onclick="quitarTag(<?= $tag['id'] ?>); return false;" title="Quitar">&times;</a>
                    </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Campos Personalizados -->
        <?php if (!empty($customFields)): ?>
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</div>
            <div class="card-body">
                <?php foreach ($customFields as $cf): ?>
                <div class="mb-2">
                    <div class="detail-label"><?= sanitize($cf['nombre']) ?></div>
                    <div class="detail-value">
                        <?php if ($cf['tipo'] === 'checkbox'): ?>
                            <?= $cf['valor'] ? '<i class="bi bi-check-circle text-success"></i> Sí' : '<span class="text-muted">No</span>' ?>
                        <?php else: ?>
                            <?= sanitize($cf['valor'] ?? '-') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Agente -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-badge"></i> Agente Asignado</div>
            <div class="card-body">
                <strong><?= sanitize(trim(($c['agente_nombre'] ?? '') . ' ' . ($c['agente_apellidos'] ?? ''))) ?: '<span class="text-muted">Sin asignar</span>' ?></strong>
            </div>
        </div>
    </div>

    <!-- Columna derecha -->
    <div class="col-lg-8">

        <!-- Propiedades compatibles -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-house-heart"></i> Propiedades Compatibles</span>
                <span class="badge bg-secondary"><?= count($matches) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($matches)): ?>
                <p class="text-muted text-center py-4 mb-0">
                    <i class="bi bi-house-x d-block fs-3 mb-2"></i>
                    No se encontraron propiedades compatibles con las preferencias
                </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Ref.</th><th>Título</th><th>Tipo</th><th>Precio</th><th>Ubicación</th><th>Hab.</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($matches as $m): ?>
                        <tr onclick="location.href='<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $m['id'] ?>'" style="cursor:pointer">
                            <td><strong><?= sanitize($m['referencia']) ?></strong></td>
                            <td><?= sanitize(mb_substr($m['titulo'], 0, 35)) ?><?= mb_strlen($m['titulo']) > 35 ? '…' : '' ?></td>
                            <td><?= $tipos[$m['tipo']] ?? sanitize($m['tipo']) ?></td>
                            <td class="fw-bold text-nowrap"><?= formatPrecio($m['precio']) ?></td>
                            <td><?= sanitize($m['localidad'] ?? '') ?>, <?= sanitize($m['provincia'] ?? '') ?></td>
                            <td><?= $m['habitaciones'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Propiedades como propietario -->
        <?php if (!empty($propiedades)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-house-door"></i> Propiedades (como propietario)</span>
                <span class="badge bg-secondary"><?= count($propiedades) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Ref.</th><th>Título</th><th>Operación</th><th>Precio</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($propiedades as $pr): ?>
                        <tr onclick="location.href='<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $pr['id'] ?>'" style="cursor:pointer">
                            <td><strong><?= sanitize($pr['referencia']) ?></strong></td>
                            <td><?= sanitize(mb_substr($pr['titulo'], 0, 35)) ?></td>
                            <td><?= ucfirst($pr['operacion']) ?></td>
                            <td class="fw-bold"><?= formatPrecio($pr['precio']) ?></td>
                            <td><span class="badge-estado badge-<?= $pr['estado'] ?>"><?= ucfirst($pr['estado']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Visitas -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event"></i> Visitas</span>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-secondary"><?= count($visitas) ?></span>
                    <a href="<?= APP_URL ?>/modules/visitas/form.php?cliente_id=<?= $id ?>" class="btn btn-sm btn-outline-primary py-0">+ Programar</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($visitas)): ?>
                <p class="text-muted text-center py-3 mb-0 small">Sin visitas registradas</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Fecha</th><th>Propiedad</th><th>Estado</th><th>Valoración</th></tr></thead>
                        <tbody>
                        <?php foreach ($visitas as $v): ?>
                        <tr>
                            <td class="text-nowrap"><?= formatFecha($v['fecha']) ?> <?= substr($v['hora'] ?? '', 0, 5) ?></td>
                            <td><a href="<?= APP_URL ?>/modules/propiedades/ver.php?id=<?= $v['propiedad_id'] ?>"><?= sanitize($v['referencia']) ?> – <?= sanitize(mb_substr($v['propiedad'], 0, 30)) ?></a></td>
                            <td><span class="badge-estado badge-<?= $v['estado'] ?>"><?= ucfirst(str_replace('_',' ',$v['estado'])) ?></span></td>
                            <td><?= $v['valoracion'] ? str_repeat('★', intval($v['valoracion'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documentos -->
        <?php if (!empty($docs)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-folder2-open"></i> Documentos</span>
                <span class="badge bg-secondary"><?= count($docs) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Nombre</th><th>Tipo</th><th>Fecha</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($docs as $d): ?>
                        <tr>
                            <td><?= sanitize($d['nombre']) ?></td>
                            <td><?= sanitize($d['tipo'] ?? '-') ?></td>
                            <td><?= formatFecha($d['created_at']) ?></td>
                            <td>
                                <?php if (!empty($d['archivo'])): ?>
                                <a href="<?= APP_URL ?>/modules/documentos/descargar.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary py-0"><i class="bi bi-download"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actividad reciente -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history"></i> Actividad Reciente</span>
                <a href="timeline.php?id=<?= $id ?>" class="btn btn-sm btn-outline-secondary py-0">Ver todo</a>
            </div>
            <div class="card-body">
                <?php if (empty($actividad)): ?>
                <p class="text-muted small mb-0">Sin actividad registrada</p>
                <?php else: ?>
                <div class="timeline">
                    <?php
                    $accionIconos = [
                        'crear'   => ['color'=>'#10b981','icon'=>'bi-plus-circle'],
                        'editar'  => ['color'=>'#3b82f6','icon'=>'bi-pencil'],
                        'ver'     => ['color'=>'#94a3b8','icon'=>'bi-eye'],
                        'eliminar'=> ['color'=>'#ef4444','icon'=>'bi-trash'],
                        'contacto'=> ['color'=>'#8b5cf6','icon'=>'bi-telephone'],
                        'default' => ['color'=>'#64748b','icon'=>'bi-activity'],
                    ];
                    foreach ($actividad as $act):
                        $ai = $accionIconos[$act['accion']] ?? $accionIconos['default'];
                    ?>
                    <div class="timeline-entry">
                        <div class="tl-dot" style="background:<?= $ai['color'] ?>;">
                            <i class="bi <?= $ai['icon'] ?>"></i>
                        </div>
                        <div class="tl-content">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="fw-semibold small"><?= sanitize($act['actor'] ?? 'Sistema') ?></span>
                                <span class="badge" style="background:<?= $ai['color'] ?>20; color:<?= $ai['color'] ?>; font-size:0.65rem;"><?= ucfirst($act['accion']) ?></span>
                                <span class="text-muted" style="font-size:0.75rem;"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($act['descripcion'])): ?>
                            <div class="small text-body-secondary mt-1"><?= sanitize($act['descripcion']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Modal Tags -->
<div class="modal fade" id="modalTags" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-tags"></i> Gestionar Tags</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="tagsList" class="mb-3">Cargando...</div>
                <hr>
                <h6 class="small text-muted">Crear nuevo tag</h6>
                <div class="input-group input-group-sm">
                    <input type="text" id="nuevoTagNombre" class="form-control" placeholder="Nombre" maxlength="50">
                    <input type="color" id="nuevoTagColor" class="form-control form-control-color" value="#10b981" style="max-width:40px">
                    <button class="btn btn-primary" onclick="crearTag()"><i class="bi bi-plus"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const clienteId = <?= $id ?>;
const csrf = '<?= csrfToken() ?>';

function quitarTag(tagId) {
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=quitar_tag&cliente_id=' + clienteId + '&tag_id=' + tagId + '&csrf_token=' + csrf
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function agregarTag(tagId) {
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=agregar_tag&cliente_id=' + clienteId + '&tag_id=' + tagId + '&csrf_token=' + csrf
    }).then(r => r.json()).then(d => { if (d.success) location.reload(); });
}

function crearTag() {
    const nombre = document.getElementById('nuevoTagNombre').value.trim();
    const color = document.getElementById('nuevoTagColor').value;
    if (!nombre) return;
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=crear_tag&nombre=' + encodeURIComponent(nombre) + '&color=' + encodeURIComponent(color) + '&csrf_token=' + csrf
    }).then(r => r.json()).then(d => {
        if (d.success && d.tag) agregarTag(d.tag.id);
    });
}

document.getElementById('modalTags').addEventListener('show.bs.modal', function() {
    fetch('tags.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=listar_tags&csrf_token=' + csrf
    }).then(r => r.json()).then(data => {
        const list = document.getElementById('tagsList');
        if (data.tags && data.tags.length) {
            list.innerHTML = data.tags.map(t =>
                '<a href="#" class="badge me-1 mb-1 text-decoration-none" style="background:' + t.color + '; font-size:0.8rem;" onclick="agregarTag(' + t.id + ');return false;">' +
                t.nombre + ' <i class="bi bi-plus-circle"></i></a>'
            ).join('');
        } else {
            list.innerHTML = '<span class="text-muted">No hay tags creados</span>';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
