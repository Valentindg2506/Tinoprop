<?php
$pageTitle = 'Item de Pipeline';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../includes/custom_fields_helper.php';

$db = getDB();

function pipelineHasProspectoColumn(PDO $db): bool {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM pipeline_items LIKE 'prospecto_id'");
        return (bool) $stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$hasProspectoColumn = pipelineHasProspectoColumn($db);

$itemId = intval(get('id'));
$pipelineId = intval(get('pipeline_id'));
$item = null;
$prospectoActual = null;
$prospectoCustomValues = [];
$prospectoCustomFields = [];

// Si estamos editando, cargar item
if ($itemId) {
    $stmt = $db->prepare("SELECT * FROM pipeline_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        setFlash('danger', 'Item no encontrado.');
        header('Location: index.php');
        exit;
    }
    $pipelineId = $item['pipeline_id'];
}

if ($hasProspectoColumn && $item && !empty($item['prospecto_id'])) {
    $stmtProsActual = $db->prepare("SELECT * FROM prospectos WHERE id = ? LIMIT 1");
    $stmtProsActual->execute([intval($item['prospecto_id'])]);
    $prospectoActual = $stmtProsActual->fetch() ?: null;
    if ($prospectoActual) {
        $prospectoCustomValues = getCustomFieldValues(intval($prospectoActual['id']), 'prospecto');
        $prospectoCustomFields = getCustomFields('prospecto');
    }
}

if (!$pipelineId) {
    setFlash('danger', 'Pipeline no especificada.');
    header('Location: index.php');
    exit;
}

// Verificar que la pipeline existe
$stmtP = $db->prepare("SELECT * FROM pipelines WHERE id = ?");
$stmtP->execute([$pipelineId]);
$pipeline = $stmtP->fetch();
if (!$pipeline) {
    setFlash('danger', 'Pipeline no encontrada.');
    header('Location: index.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $titulo = post('titulo');
    $etapaId = intval(post('etapa_id'));
    $propiedadId = intval(post('propiedad_id')) ?: null;
    $clienteId = intval(post('cliente_id')) ?: null;
    $prospectoId = $hasProspectoColumn ? (intval(post('prospecto_id')) ?: null) : null;
    $valor = post('valor') !== '' ? floatval(str_replace(',', '.', post('valor'))) : null;
    $notas = post('notas');
    $prioridad = post('prioridad', 'media');

    if (empty($titulo) || !$etapaId) {
        setFlash('danger', 'El titulo y la etapa son obligatorios.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($hasProspectoColumn && $clienteId && $prospectoId) {
        setFlash('danger', 'Selecciona solo cliente o prospecto, no ambos a la vez.');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Validar prioridad
    if (!in_array($prioridad, ['baja', 'media', 'alta'])) {
        $prioridad = 'media';
    }

    if ($hasProspectoColumn && $prospectoId) {
        $stmtProsOwner = $db->prepare("SELECT agente_id FROM prospectos WHERE id = ? LIMIT 1");
        $stmtProsOwner->execute([$prospectoId]);
        $prosAgenteId = $stmtProsOwner->fetchColumn();

        if ($prosAgenteId === false) {
            setFlash('danger', 'El prospecto seleccionado no existe.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        if (!isAdmin() && intval($prosAgenteId) !== intval(currentUserId())) {
            setFlash('danger', 'No tienes permisos para editar este prospecto.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        if (post('sync_prospecto') === '1') {
            $prospectoData = [
                'nombre' => post('pros_nombre'),
                'telefono' => post('pros_telefono') ?: null,
                'telefono2' => post('pros_telefono2') ?: null,
                'email' => post('pros_email') ?: null,
                'etapa' => post('pros_etapa', 'nuevo_lead'),
                'estado' => post('pros_estado', 'nuevo'),
                'temperatura' => post('pros_temperatura', 'frio'),
                'tipo_propiedad' => post('pros_tipo_propiedad') ?: null,
                'operacion' => post('pros_operacion') ?: null,
                'direccion' => post('pros_direccion') ?: null,
                'numero' => post('pros_numero') ?: null,
                'piso_puerta' => post('pros_piso_puerta') ?: null,
                'barrio' => post('pros_barrio') ?: null,
                'localidad' => post('pros_localidad') ?: null,
                'provincia' => post('pros_provincia') ?: null,
                'comunidad_autonoma' => post('pros_comunidad_autonoma') ?: null,
                'codigo_postal' => post('pros_codigo_postal') ?: null,
                'precio_estimado' => post('pros_precio_estimado') ? floatval(str_replace(',', '.', post('pros_precio_estimado'))) : null,
                'precio_propietario' => post('pros_precio_propietario') ? floatval(str_replace(',', '.', post('pros_precio_propietario'))) : null,
                'precio_comunidad' => post('pros_precio_comunidad') ? floatval(str_replace(',', '.', post('pros_precio_comunidad'))) : null,
                'superficie' => post('pros_superficie') ? floatval(str_replace(',', '.', post('pros_superficie'))) : null,
                'superficie_construida' => post('pros_superficie_construida') ? floatval(str_replace(',', '.', post('pros_superficie_construida'))) : null,
                'superficie_util' => post('pros_superficie_util') ? floatval(str_replace(',', '.', post('pros_superficie_util'))) : null,
                'superficie_parcela' => post('pros_superficie_parcela') ? floatval(str_replace(',', '.', post('pros_superficie_parcela'))) : null,
                'habitaciones' => post('pros_habitaciones') ?: null,
                'banos' => post('pros_banos') ?: null,
                'aseos' => post('pros_aseos') ?: null,
                'planta' => post('pros_planta') ?: null,
                'calefaccion' => post('pros_calefaccion') ?: null,
                'orientacion' => post('pros_orientacion') ?: null,
                'antiguedad' => post('pros_antiguedad') ?: null,
                'estado_conservacion' => post('pros_estado_conservacion') ?: null,
                'certificacion_energetica' => post('pros_certificacion_energetica') ?: null,
                'referencia_catastral' => post('pros_referencia_catastral') ?: null,
                'enlace' => post('pros_enlace') ?: null,
                'fecha_contacto' => post('pros_fecha_contacto') ?: null,
                'fecha_proximo_contacto' => post('pros_fecha_proximo_contacto') ?: null,
                'comision' => post('pros_comision') ? floatval(str_replace(',', '.', post('pros_comision'))) : null,
                'exclusividad' => isset($_POST['pros_exclusividad']) ? 1 : 0,
                'ascensor' => isset($_POST['pros_ascensor']) ? 1 : 0,
                'garaje_incluido' => isset($_POST['pros_garaje_incluido']) ? 1 : 0,
                'trastero_incluido' => isset($_POST['pros_trastero_incluido']) ? 1 : 0,
                'terraza' => isset($_POST['pros_terraza']) ? 1 : 0,
                'balcon' => isset($_POST['pros_balcon']) ? 1 : 0,
                'jardin' => isset($_POST['pros_jardin']) ? 1 : 0,
                'piscina' => isset($_POST['pros_piscina']) ? 1 : 0,
                'aire_acondicionado' => isset($_POST['pros_aire_acondicionado']) ? 1 : 0,
                'descripcion' => $_POST['pros_descripcion'] ?? null,
                'descripcion_interna' => $_POST['pros_descripcion_interna'] ?? null,
                'notas' => $_POST['pros_notas'] ?? null,
                'reformas' => $_POST['pros_reformas'] ?? null,
                'historial_contactos' => $_POST['pros_historial_contactos'] ?? null,
            ];

            $erroresProspecto = validarProspecto($prospectoData);
            if (!empty($erroresProspecto)) {
                setFlash('danger', implode('<br>', $erroresProspecto));
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            }

            $prospectoData['agente_id'] = post('pros_agente_id') ?: $prosAgenteId;
            $prospectoData['activo'] = isset($_POST['pros_activo']) ? 1 : 0;

            $fieldsPros = [];
            $valuesPros = [];
            foreach ($prospectoData as $k => $v) {
                $fieldsPros[] = "`$k` = ?";
                $valuesPros[] = $v;
            }
            $valuesPros[] = $prospectoId;
            $db->prepare("UPDATE prospectos SET " . implode(', ', $fieldsPros) . " WHERE id = ?")
                ->execute($valuesPros);

            saveCustomFieldValues($prospectoId, 'prospecto', $_POST);
        }
    }

    if ($itemId) {
        // Actualizar
        if ($hasProspectoColumn) {
            $stmt = $db->prepare("UPDATE pipeline_items SET titulo = ?, etapa_id = ?, propiedad_id = ?, cliente_id = ?, prospecto_id = ?, valor = ?, notas = ?, prioridad = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$titulo, $etapaId, $propiedadId, $clienteId, $prospectoId, $valor, $notas, $prioridad, $itemId]);
        } else {
            $stmt = $db->prepare("UPDATE pipeline_items SET titulo = ?, etapa_id = ?, propiedad_id = ?, cliente_id = ?, valor = ?, notas = ?, prioridad = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$titulo, $etapaId, $propiedadId, $clienteId, $valor, $notas, $prioridad, $itemId]);
        }
        registrarActividad('editar', 'pipeline_item', $itemId, 'Item: ' . $titulo);
        setFlash('success', 'Item actualizado correctamente.');
    } else {
        // Crear
        if ($hasProspectoColumn) {
            $stmt = $db->prepare("INSERT INTO pipeline_items (pipeline_id, etapa_id, titulo, propiedad_id, cliente_id, prospecto_id, valor, notas, prioridad, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pipelineId, $etapaId, $titulo, $propiedadId, $clienteId, $prospectoId, $valor, $notas, $prioridad, currentUserId()]);
        } else {
            $stmt = $db->prepare("INSERT INTO pipeline_items (pipeline_id, etapa_id, titulo, propiedad_id, cliente_id, valor, notas, prioridad, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pipelineId, $etapaId, $titulo, $propiedadId, $clienteId, $valor, $notas, $prioridad, currentUserId()]);
        }
        registrarActividad('crear', 'pipeline_item', $db->lastInsertId(), 'Item: ' . $titulo);
        setFlash('success', 'Item creado correctamente.');
    }

    header('Location: ver.php?id=' . $pipelineId);
    exit;
}

// Obtener etapas de la pipeline
$stmtEtapas = $db->prepare("SELECT * FROM pipeline_etapas WHERE pipeline_id = ? ORDER BY orden ASC");
$stmtEtapas->execute([$pipelineId]);
$etapas = $stmtEtapas->fetchAll();

// Obtener clientes para el select
$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes ORDER BY nombre ASC")->fetchAll();

// Obtener propiedades para el select
$isAdm = isAdmin();

// Obtener prospectos para el select (si la columna existe en pipeline_items)
$prospectos = [];
if ($hasProspectoColumn) {
    try {
        if ($isAdm) {
            $prospectos = $db->query("SELECT id, referencia, nombre, telefono FROM prospectos WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();
        } else {
            $stmtPros = $db->prepare("SELECT id, referencia, nombre, telefono FROM prospectos WHERE activo = 1 AND agente_id = ? ORDER BY nombre ASC");
            $stmtPros->execute([currentUserId()]);
            $prospectos = $stmtPros->fetchAll();
        }
    } catch (Throwable $e) {
        $prospectos = [];
        $hasProspectoColumn = false;
    }
}
if ($isAdm) {
    $propiedades = $db->query("SELECT id, referencia, titulo FROM propiedades WHERE estado = 'disponible' ORDER BY referencia ASC")->fetchAll();
} else {
    $stmtProp = $db->prepare("SELECT id, referencia, titulo FROM propiedades WHERE estado = 'disponible' AND agente_id = ? ORDER BY referencia ASC");
    $stmtProp->execute([currentUserId()]);
    $propiedades = $stmtProp->fetchAll();
}

$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$provincias = getProvincias();
$etapasProspecto = [
    'nuevo_lead' => 'Nuevo Lead',
    'contactado' => 'Contactado',
    'seguimiento' => 'En Seguimiento',
    'visita_programada' => 'Visita Programada',
    'en_negociacion' => 'En Negociación',
    'captado' => 'Captado',
    'descartado' => 'Descartado',
];
$estadosProspecto = [
    'nuevo' => 'Nuevo',
    'en_proceso' => 'En Proceso',
    'pendiente' => 'Pendiente Respuesta',
    'sin_interes' => 'Sin Interés',
    'captado' => 'Captado',
];
$tiposPropiedad = ['Piso','Casa','Chalet','Adosado','Atico','Duplex','Estudio','Local','Oficina','Nave','Terreno','Garaje','Trastero','Edificio','Otro'];
$operaciones = ['venta' => 'Venta', 'alquiler' => 'Alquiler', 'alquiler_opcion_compra' => 'Alquiler con opción a compra', 'traspaso' => 'Traspaso'];
$orientaciones = ['norte'=>'Norte','sur'=>'Sur','este'=>'Este','oeste'=>'Oeste','noreste'=>'Noreste','noroeste'=>'Noroeste','sureste'=>'Sureste','suroeste'=>'Suroeste'];
$conservaciones = ['a_estrenar'=>'A estrenar','buen_estado'=>'Buen estado','a_reformar'=>'A reformar','en_construccion'=>'En construcción'];
$energeticas = ['A'=>'A','B'=>'B','C'=>'C','D'=>'D','E'=>'E','F'=>'F','G'=>'G','en_tramite'=>'En trámite','exento'=>'Exento'];

// Pre-seleccionar etapa si viene por GET
$preEtapa = intval(get('etapa_id'));
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="ver.php?id=<?= $pipelineId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0"><?= $itemId ? 'Editar' : 'Nuevo' ?> Item - <?= sanitize($pipeline['nombre']) ?></h5>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Titulo <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required maxlength="200"
                            value="<?= $item ? sanitize($item['titulo']) : '' ?>"
                            placeholder="Titulo del item">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Etapa <span class="text-danger">*</span></label>
                            <select name="etapa_id" class="form-select" required>
                                <option value="">-- Seleccionar etapa --</option>
                                <?php foreach ($etapas as $etapa): ?>
                                <option value="<?= $etapa['id'] ?>"
                                    <?= ($item && $item['etapa_id'] == $etapa['id']) || $preEtapa == $etapa['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($etapa['nombre']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prioridad</label>
                            <select name="prioridad" class="form-select">
                                <option value="baja" <?= ($item && $item['prioridad'] === 'baja') ? 'selected' : '' ?>>Baja</option>
                                <option value="media" <?= (!$item || $item['prioridad'] === 'media') ? 'selected' : '' ?>>Media</option>
                                <option value="alta" <?= ($item && $item['prioridad'] === 'alta') ? 'selected' : '' ?>>Alta</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Cliente <small class="text-muted">(opcional)</small></label>
                            <select name="cliente_id" id="cliente_id" class="form-select">
                                <option value="">-- Sin cliente --</option>
                                <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($item && $item['cliente_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prospecto <small class="text-muted">(opcional)</small></label>
                            <select name="prospecto_id" id="prospecto_id" class="form-select" <?= $hasProspectoColumn ? '' : 'disabled' ?>>
                                <option value="">-- Sin prospecto --</option>
                                <?php foreach ($prospectos as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($item && isset($item['prospecto_id']) && $item['prospecto_id'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= sanitize(($p['referencia'] ? $p['referencia'] . ' - ' : '') . $p['nombre'] . ($p['telefono'] ? ' (' . $p['telefono'] . ')' : '')) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($hasProspectoColumn): ?>
                                <small class="text-muted">Solo puedes asociar uno: cliente o prospecto.</small>
                            <?php endif; ?>
                            <?php if (!$hasProspectoColumn): ?>
                                <small class="text-muted">Actualiza la estructura de la base de datos para habilitar prospectos en pipeline.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Propiedad <small class="text-muted">(opcional)</small></label>
                            <select name="propiedad_id" class="form-select">
                                <option value="">-- Sin propiedad --</option>
                                <?php foreach ($propiedades as $prop): ?>
                                <option value="<?= $prop['id'] ?>" <?= ($item && $item['propiedad_id'] == $prop['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($prop['referencia'] . ' - ' . mb_substr($prop['titulo'], 0, 40)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Valor <small class="text-muted">(opcional)</small></label>
                        <div class="input-group">
                            <input type="text" name="valor" class="form-control"
                                value="<?= $item && $item['valor'] ? number_format($item['valor'], 2, ',', '.') : '' ?>"
                                placeholder="0,00">
                            <span class="input-group-text">&euro;</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notas <small class="text-muted">(opcional)</small></label>
                        <textarea name="notas" class="form-control" rows="3" placeholder="Notas adicionales..."><?= $item ? sanitize($item['notas']) : '' ?></textarea>
                    </div>

                    <?php if ($hasProspectoColumn && $prospectoActual): ?>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0"><i class="bi bi-person-badge"></i> Edición de Prospecto Asociado</h6>
                        <a href="../prospectos/form.php?id=<?= intval($prospectoActual['id']) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                            <i class="bi bi-box-arrow-up-right"></i> Formulario completo
                        </a>
                    </div>
                    <input type="hidden" name="sync_prospecto" value="1">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="pros_nombre" class="form-control" required value="<?= sanitize($prospectoActual['nombre'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="pros_email" class="form-control" value="<?= sanitize($prospectoActual['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="pros_telefono" class="form-control" value="<?= sanitize($prospectoActual['telefono'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Teléfono 2</label>
                            <input type="tel" name="pros_telefono2" class="form-control" value="<?= sanitize($prospectoActual['telefono2'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Etapa</label>
                            <select name="pros_etapa" class="form-select">
                                <?php foreach ($etapasProspecto as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($prospectoActual['etapa'] ?? 'nuevo_lead') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="pros_estado" class="form-select">
                                <?php foreach ($estadosProspecto as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($prospectoActual['estado'] ?? 'nuevo') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Temperatura</label>
                            <select name="pros_temperatura" class="form-select">
                                <option value="frio" <?= ($prospectoActual['temperatura'] ?? 'frio') === 'frio' ? 'selected' : '' ?>>Frío</option>
                                <option value="templado" <?= ($prospectoActual['temperatura'] ?? '') === 'templado' ? 'selected' : '' ?>>Templado</option>
                                <option value="caliente" <?= ($prospectoActual['temperatura'] ?? '') === 'caliente' ? 'selected' : '' ?>>Caliente</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Agente</label>
                            <select name="pros_agente_id" class="form-select" <?= $isAdm ? '' : 'disabled' ?>>
                                <?php foreach ($agentes as $ag): ?>
                                <option value="<?= $ag['id'] ?>" <?= intval($prospectoActual['agente_id'] ?? 0) === intval($ag['id']) ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$isAdm): ?>
                                <input type="hidden" name="pros_agente_id" value="<?= intval($prospectoActual['agente_id'] ?? currentUserId()) ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tipo de propiedad</label>
                            <select name="pros_tipo_propiedad" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($tiposPropiedad as $tp): ?>
                                <option value="<?= $tp ?>" <?= ($prospectoActual['tipo_propiedad'] ?? '') === $tp ? 'selected' : '' ?>><?= $tp ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Operación</label>
                            <select name="pros_operacion" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($operaciones as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($prospectoActual['operacion'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Precio estimado</label>
                            <input type="text" name="pros_precio_estimado" class="form-control" value="<?= sanitize($prospectoActual['precio_estimado'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Precio propietario</label>
                            <input type="text" name="pros_precio_propietario" class="form-control" value="<?= sanitize($prospectoActual['precio_propietario'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="pros_direccion" class="form-control" value="<?= sanitize($prospectoActual['direccion'] ?? '') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Nº</label>
                            <input type="text" name="pros_numero" class="form-control" value="<?= sanitize($prospectoActual['numero'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Piso/Puerta</label>
                            <input type="text" name="pros_piso_puerta" class="form-control" value="<?= sanitize($prospectoActual['piso_puerta'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Barrio</label>
                            <input type="text" name="pros_barrio" class="form-control" value="<?= sanitize($prospectoActual['barrio'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">CP</label>
                            <input type="text" name="pros_codigo_postal" class="form-control" value="<?= sanitize($prospectoActual['codigo_postal'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Localidad</label>
                            <input type="text" name="pros_localidad" class="form-control" value="<?= sanitize($prospectoActual['localidad'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Provincia</label>
                            <select name="pros_provincia" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($provincias as $prov): ?>
                                <option value="<?= sanitize($prov) ?>" <?= ($prospectoActual['provincia'] ?? '') === $prov ? 'selected' : '' ?>><?= sanitize($prov) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Comunidad autónoma</label>
                            <input type="text" name="pros_comunidad_autonoma" class="form-control" value="<?= sanitize($prospectoActual['comunidad_autonoma'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="pros_activo" name="pros_activo" <?= !isset($prospectoActual['activo']) || intval($prospectoActual['activo']) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="pros_activo">Prospecto activo</label>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Superficie</label>
                            <input type="text" name="pros_superficie" class="form-control" value="<?= sanitize($prospectoActual['superficie'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sup. construida</label>
                            <input type="text" name="pros_superficie_construida" class="form-control" value="<?= sanitize($prospectoActual['superficie_construida'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sup. útil</label>
                            <input type="text" name="pros_superficie_util" class="form-control" value="<?= sanitize($prospectoActual['superficie_util'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sup. parcela</label>
                            <input type="text" name="pros_superficie_parcela" class="form-control" value="<?= sanitize($prospectoActual['superficie_parcela'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hab.</label>
                            <input type="number" min="0" name="pros_habitaciones" class="form-control" value="<?= sanitize($prospectoActual['habitaciones'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Baños</label>
                            <input type="number" min="0" name="pros_banos" class="form-control" value="<?= sanitize($prospectoActual['banos'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Aseos</label>
                            <input type="number" min="0" name="pros_aseos" class="form-control" value="<?= sanitize($prospectoActual['aseos'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Planta</label>
                            <input type="text" name="pros_planta" class="form-control" value="<?= sanitize($prospectoActual['planta'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Antigüedad</label>
                            <input type="number" min="0" name="pros_antiguedad" class="form-control" value="<?= sanitize($prospectoActual['antiguedad'] ?? '') ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Comisión (%)</label>
                            <input type="text" name="pros_comision" class="form-control" value="<?= sanitize($prospectoActual['comision'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Comunidad (€/mes)</label>
                            <input type="text" name="pros_precio_comunidad" class="form-control" value="<?= sanitize($prospectoActual['precio_comunidad'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Orientación</label>
                            <select name="pros_orientacion" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($orientaciones as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($prospectoActual['orientacion'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Conservación</label>
                            <select name="pros_estado_conservacion" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($conservaciones as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($prospectoActual['estado_conservacion'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cert. energética</label>
                            <select name="pros_certificacion_energetica" class="form-select">
                                <option value="">-</option>
                                <?php foreach ($energeticas as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($prospectoActual['certificacion_energetica'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Calefacción</label>
                            <input type="text" name="pros_calefaccion" class="form-control" value="<?= sanitize($prospectoActual['calefaccion'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Referencia catastral</label>
                            <input type="text" name="pros_referencia_catastral" class="form-control" value="<?= sanitize($prospectoActual['referencia_catastral'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Enlace</label>
                            <input type="url" name="pros_enlace" class="form-control" value="<?= sanitize($prospectoActual['enlace'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Primer contacto</label>
                            <input type="date" name="pros_fecha_contacto" class="form-control" value="<?= sanitize($prospectoActual['fecha_contacto'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Próximo contacto</label>
                            <input type="date" name="pros_fecha_proximo_contacto" class="form-control" value="<?= sanitize($prospectoActual['fecha_proximo_contacto'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label d-block">Extras</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php
                                $checksPros = [
                                    'ascensor' => 'Ascensor',
                                    'garaje_incluido' => 'Garaje',
                                    'trastero_incluido' => 'Trastero',
                                    'terraza' => 'Terraza',
                                    'balcon' => 'Balcón',
                                    'jardin' => 'Jardín',
                                    'piscina' => 'Piscina',
                                    'aire_acondicionado' => 'Aire Acond.',
                                    'exclusividad' => 'Exclusividad',
                                ];
                                foreach ($checksPros as $ck => $cl):
                                    $fieldName = 'pros_' . $ck;
                                ?>
                                <div class="form-check">
                                    <input type="checkbox" name="<?= $fieldName ?>" class="form-check-input" id="<?= $fieldName ?>" <?= !empty($prospectoActual[$ck]) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= $fieldName ?>"><?= $cl ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Descripción</label>
                            <textarea name="pros_descripcion" class="form-control" rows="2"><?= sanitize($prospectoActual['descripcion'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descripción interna</label>
                            <textarea name="pros_descripcion_interna" class="form-control" rows="2"><?= sanitize($prospectoActual['descripcion_interna'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas del prospecto</label>
                            <textarea name="pros_notas" class="form-control" rows="3"><?= sanitize($prospectoActual['notas'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reformas</label>
                            <textarea name="pros_reformas" class="form-control" rows="3"><?= sanitize($prospectoActual['reformas'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Historial de contactos</label>
                            <textarea name="pros_historial_contactos" class="form-control" rows="3"><?= sanitize($prospectoActual['historial_contactos'] ?? '') ?></textarea>
                        </div>

                        <?php if (!empty($prospectoCustomFields)): ?>
                        <div class="col-12">
                            <div class="card border mt-2">
                                <div class="card-header bg-light">
                                    <strong>Campos personalizados</strong>
                                </div>
                                <div class="card-body">
                                    <?php renderCustomFieldsForm('prospecto', $prospectoCustomValues); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($hasProspectoColumn): ?>
                    <div class="alert alert-info">
                        Selecciona un prospecto y guarda el item para habilitar su edición completa desde este formulario.
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="ver.php?id=<?= $pipelineId ?>" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-<?= $itemId ? 'check-lg' : 'plus-lg' ?>"></i>
                            <?= $itemId ? 'Guardar Cambios' : 'Crear Item' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($hasProspectoColumn): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const clienteSelect = document.getElementById('cliente_id');
    const prospectoSelect = document.getElementById('prospecto_id');

    if (!clienteSelect || !prospectoSelect) {
        return;
    }

    function syncRelatedSelects() {
        prospectoSelect.disabled = clienteSelect.value !== '';
        clienteSelect.disabled = prospectoSelect.value !== '';
    }

    clienteSelect.addEventListener('change', syncRelatedSelects);
    prospectoSelect.addEventListener('change', syncRelatedSelects);
    syncRelatedSelects();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
