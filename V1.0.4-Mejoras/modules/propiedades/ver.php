<?php
$pageTitle = 'Detalle Propiedad';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));

$stmt = $db->prepare("SELECT p.*, u.nombre as agente_nombre, u.apellidos as agente_apellidos,
    c.nombre as propietario_nombre, c.apellidos as propietario_apellidos, c.telefono as propietario_telefono
    FROM propiedades p
    LEFT JOIN usuarios u ON p.agente_id = u.id
    LEFT JOIN clientes c ON p.propietario_id = c.id
    WHERE p.id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { setFlash('danger', 'Propiedad no encontrada.'); header('Location: index.php'); exit; }

// Fotos
$fotos = $db->prepare("SELECT * FROM propiedad_fotos WHERE propiedad_id = ? ORDER BY es_principal DESC, orden");
$fotos->execute([$id]);
$fotos = $fotos->fetchAll();

// Visitas de esta propiedad
$visitas = $db->prepare("SELECT v.*, c.nombre as cliente_nombre, c.apellidos as cliente_apellidos
    FROM visitas v JOIN clientes c ON v.cliente_id = c.id
    WHERE v.propiedad_id = ? ORDER BY v.fecha DESC LIMIT 10");
$visitas->execute([$id]);
$visitas = $visitas->fetchAll();

// Publicaciones en portales
$portales = $db->prepare("SELECT pp.*, po.nombre as portal_nombre
    FROM propiedad_portales pp JOIN portales po ON pp.portal_id = po.id
    WHERE pp.propiedad_id = ?");
$portales->execute([$id]);
$portales = $portales->fetchAll();

// Documentos
$docs = $db->prepare("SELECT * FROM documentos WHERE propiedad_id = ? ORDER BY created_at DESC");
$docs->execute([$id]);
$docs = $docs->fetchAll();

$tipos = getTiposPropiedad();
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <span class="badge-estado badge-<?= $p['estado'] ?> fs-6"><?= ucfirst($p['estado']) ?></span>
        <span class="badge bg-primary fs-6"><?= ucfirst(str_replace('_', ' ', $p['operacion'])) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="form.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Eliminar esta propiedad?')">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= intval($id) ?>">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
        </form>
        <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
</div>

<div class="row g-4">
    <!-- Columna principal -->
    <div class="col-lg-8">
        <!-- Galeria -->
        <?php if (!empty($fotos)): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2 property-gallery">
                    <?php foreach ($fotos as $foto): ?>
                    <div class="col-6 col-md-4">
                        <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($foto['archivo']) ?>" alt="<?= sanitize($foto['titulo'] ?? '') ?>" class="img-fluid rounded">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info principal -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-info-circle"></i> Informacion General</div>
            <div class="card-body">
                <h4><?= sanitize($p['titulo']) ?></h4>
                <p class="text-muted"><i class="bi bi-geo-alt"></i> <?= sanitize($p['direccion'] ?? '') ?> <?= sanitize($p['numero'] ?? '') ?>, <?= sanitize($p['localidad']) ?>, <?= sanitize($p['provincia']) ?> <?= sanitize($p['codigo_postal'] ?? '') ?></p>

                <div class="row g-3 mb-4">
                    <div class="col-4 col-md-3">
                        <div class="detail-label">Referencia</div>
                        <div class="detail-value fw-bold"><?= sanitize($p['referencia']) ?></div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="detail-label">Tipo</div>
                        <div class="detail-value"><?= $tipos[$p['tipo']] ?? $p['tipo'] ?></div>
                    </div>
                    <div class="col-4 col-md-3">
                        <div class="detail-label">Precio</div>
                        <div class="detail-value fw-bold text-primary fs-5"><?= formatPrecio($p['precio']) ?></div>
                    </div>
                    <?php if ($p['precio_comunidad']): ?>
                    <div class="col-4 col-md-3">
                        <div class="detail-label">Comunidad</div>
                        <div class="detail-value"><?= formatPrecio($p['precio_comunidad']) ?>/mes</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
                    <?php if ($p['superficie_construida']): ?>
                    <div class="col-4 col-md-2"><div class="detail-label">Sup. Construida</div><div class="detail-value"><?= formatSuperficie($p['superficie_construida']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['superficie_util']): ?>
                    <div class="col-4 col-md-2"><div class="detail-label">Sup. Util</div><div class="detail-value"><?= formatSuperficie($p['superficie_util']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['habitaciones']): ?>
                    <div class="col-4 col-md-2"><div class="detail-label">Habitaciones</div><div class="detail-value"><?= $p['habitaciones'] ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['banos']): ?>
                    <div class="col-4 col-md-2"><div class="detail-label">Banos</div><div class="detail-value"><?= $p['banos'] ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['planta']): ?>
                    <div class="col-4 col-md-2"><div class="detail-label">Planta</div><div class="detail-value"><?= sanitize($p['planta']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($p['orientacion']): ?>
                    <div class="col-4 col-md-2"><div class="detail-label">Orientacion</div><div class="detail-value"><?= ucfirst($p['orientacion']) ?></div></div>
                    <?php endif; ?>
                </div>

                <!-- Extras -->
                <div class="mt-3">
                    <?php
                    $extras = ['ascensor'=>'Ascensor','garaje_incluido'=>'Garaje','trastero_incluido'=>'Trastero','terraza'=>'Terraza','balcon'=>'Balcon','jardin'=>'Jardin','piscina'=>'Piscina','aire_acondicionado'=>'A/C'];
                    foreach ($extras as $key => $label):
                        if ($p[$key]):
                    ?>
                    <span class="badge bg-light text-dark border me-1 mb-1"><i class="bi bi-check-circle text-success"></i> <?= $label ?></span>
                    <?php endif; endforeach; ?>
                </div>

                <?php if ($p['descripcion']): ?>
                <hr>
                <h6>Descripcion</h6>
                <p><?= nl2br(sanitize($p['descripcion'])) ?></p>
                <?php endif; ?>

                <?php if ($p['descripcion_interna']): ?>
                <hr>
                <h6 class="text-warning"><i class="bi bi-lock"></i> Notas Internas</h6>
                <p class="text-muted"><?= nl2br(sanitize($p['descripcion_interna'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Datos legales -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-file-earmark-text"></i> Datos Legales</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="detail-label">Ref. Catastral</div>
                        <div class="detail-value"><?= sanitize($p['referencia_catastral'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Cert. Energetica</div>
                        <div class="detail-value"><?= sanitize($p['certificacion_energetica'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="detail-label">Antiguedad</div>
                        <div class="detail-value"><?= $p['antiguedad'] ? $p['antiguedad'] . ' anos' : '-' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visitas -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event"></i> Visitas (<?= count($visitas) ?>)</span>
                <a href="<?= APP_URL ?>/modules/visitas/form.php?propiedad_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">+ Programar</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($visitas)): ?>
                <p class="text-muted text-center py-3">Sin visitas registradas</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Estado</th><th>Valoracion</th></tr></thead>
                    <tbody>
                    <?php foreach ($visitas as $v): ?>
                    <tr>
                        <td><?= formatFecha($v['fecha']) ?></td>
                        <td><?= substr($v['hora'], 0, 5) ?></td>
                        <td><a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $v['cliente_id'] ?>"><?= sanitize($v['cliente_nombre'] . ' ' . $v['cliente_apellidos']) ?></a></td>
                        <td><span class="badge-estado badge-<?= $v['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $v['estado'])) ?></span></td>
                        <td><?= $v['valoracion'] ? str_repeat('&#9733;', $v['valoracion']) : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar derecho -->
    <div class="col-lg-4">
        <!-- Propietario -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person"></i> Propietario</div>
            <div class="card-body">
                <?php if ($p['propietario_nombre']): ?>
                <p class="mb-1"><strong><?= sanitize($p['propietario_nombre'] . ' ' . $p['propietario_apellidos']) ?></strong></p>
                <p class="mb-0"><i class="bi bi-telephone"></i> <?= sanitize($p['propietario_telefono'] ?? '-') ?></p>
                <a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $p['propietario_id'] ?>" class="btn btn-sm btn-outline-primary mt-2">Ver ficha</a>
                <?php else: ?>
                <p class="text-muted">Sin propietario asignado</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Agente -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-badge"></i> Agente</div>
            <div class="card-body">
                <p class="mb-1"><strong><?= sanitize($p['agente_nombre'] . ' ' . $p['agente_apellidos']) ?></strong></p>
                <p class="text-muted mb-0">Captacion: <?= formatFecha($p['fecha_captacion']) ?></p>
            </div>
        </div>

        <!-- Portales -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-globe"></i> Portales</span>
                <a href="<?= APP_URL ?>/modules/portales/index.php?propiedad_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">Gestionar</a>
            </div>
            <div class="card-body">
                <?php if (empty($portales)): ?>
                <p class="text-muted">No publicado en portales</p>
                <?php else: ?>
                <?php foreach ($portales as $portal): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><?= sanitize($portal['portal_nombre']) ?></span>
                    <span class="badge-estado badge-<?= $portal['estado'] ?>"><?= ucfirst($portal['estado']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documentos -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-folder"></i> Documentos (<?= count($docs) ?>)</span>
                <a href="<?= APP_URL ?>/modules/documentos/form.php?propiedad_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">+ Subir</a>
            </div>
            <div class="card-body">
                <?php if (empty($docs)): ?>
                <p class="text-muted">Sin documentos</p>
                <?php else: ?>
                <?php foreach ($docs as $doc): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <i class="bi bi-file-earmark"></i>
                        <a href="<?= APP_URL ?>/assets/uploads/<?= sanitize($doc['archivo']) ?>" target="_blank"><?= sanitize($doc['nombre']) ?></a>
                    </div>
                    <small class="text-muted"><?= ucfirst(str_replace('_', ' ', $doc['tipo'])) ?></small>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Matching -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-people"></i> Clientes Compatibles</div>
            <div class="card-body">
                <?php
                // Buscar clientes cuyas preferencias coincidan
                $matchQuery = "SELECT c.id, c.nombre, c.apellidos, c.telefono FROM clientes c WHERE c.activo = 1";
                $matchParams = [];
                $conditions = [];

                if ($p['operacion'] === 'venta' || $p['operacion'] === 'alquiler') {
                    $conditions[] = "(c.operacion_interes = ? OR c.operacion_interes = 'ambas')";
                    $matchParams[] = $p['operacion'];
                }
                if ($p['precio']) {
                    $conditions[] = "(c.presupuesto_max IS NULL OR c.presupuesto_max >= ?)";
                    $matchParams[] = $p['precio'];
                    $conditions[] = "(c.presupuesto_min IS NULL OR c.presupuesto_min <= ?)";
                    $matchParams[] = $p['precio'];
                }
                if ($p['provincia']) {
                    $conditions[] = "(c.zona_interes IS NULL OR c.zona_interes LIKE ?)";
                    $matchParams[] = '%' . $p['provincia'] . '%';
                }

                if (!empty($conditions)) {
                    $matchQuery .= ' AND ' . implode(' AND ', $conditions);
                }
                $matchQuery .= ' LIMIT 5';

                $stmtMatch = $db->prepare($matchQuery);
                $stmtMatch->execute($matchParams);
                $matches = $stmtMatch->fetchAll();
                ?>
                <?php if (empty($matches)): ?>
                <p class="text-muted">No se encontraron coincidencias</p>
                <?php else: ?>
                <?php foreach ($matches as $m): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <a href="<?= APP_URL ?>/modules/clientes/ver.php?id=<?= $m['id'] ?>"><?= sanitize($m['nombre'] . ' ' . $m['apellidos']) ?></a>
                    <small><?= sanitize($m['telefono'] ?? '') ?></small>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
