<?php
$pageTitle = 'Nuevo Prospecto';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../includes/custom_fields_helper.php';

$db = getDB();

// Asegurar columna propietarios_json (migración automática segura)
try {
    $db->exec("ALTER TABLE prospectos ADD COLUMN propietarios_json MEDIUMTEXT NULL DEFAULT NULL");
} catch (PDOException $migrErr) {
    // Columna ya existe — ignorar error 42S21 (duplicate column)
}
$id = intval(get('id'));
$prospecto = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM prospectos WHERE id = ?");
    $stmt->execute([$id]);
    $prospecto = $stmt->fetch();
    if (!$prospecto) { setFlash('danger', 'Prospecto no encontrado.'); header('Location: index.php'); exit; }
    $pageTitle = 'Editar Prospecto: ' . $prospecto['referencia'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'nombre' => post('nombre'),
        'telefono' => post('telefono') ?: null,
        'telefono2' => post('telefono2') ?: null,
        'email' => post('email') ?: null,
        'etapa' => post('etapa', 'nuevo_lead'),
        'tipo_propiedad' => post('tipo_propiedad') ?: null,
        'operacion' => post('operacion') ?: null,
        'direccion' => post('direccion') ?: null,
        'numero' => post('numero') ?: null,
        'piso_puerta' => post('piso_puerta') ?: null,
        'escalera' => post('escalera') ?: null,
        'puerta' => post('puerta') ?: null,
        'barrio' => post('barrio') ?: null,
        'localidad' => post('localidad') ?: null,
        'provincia' => post('provincia') ?: null,
        'comunidad_autonoma' => post('comunidad_autonoma') ?: null,
        'codigo_postal' => post('codigo_postal') ?: null,
        'precio_estimado' => post('precio_estimado') ? floatval(str_replace(',', '.', post('precio_estimado'))) : null,
        'precio_propietario' => post('precio_propietario') ? floatval(str_replace(',', '.', post('precio_propietario'))) : null,
        'precio_comunidad' => post('precio_comunidad') ? floatval(str_replace(',', '.', post('precio_comunidad'))) : null,
        'superficie' => post('superficie') ? floatval(str_replace(',', '.', post('superficie'))) : null,
        'superficie_construida' => post('superficie_construida') ? floatval(str_replace(',', '.', post('superficie_construida'))) : null,
        'superficie_util' => post('superficie_util') ? floatval(str_replace(',', '.', post('superficie_util'))) : null,
        'superficie_parcela' => post('superficie_parcela') ? floatval(str_replace(',', '.', post('superficie_parcela'))) : null,
        'habitaciones' => post('habitaciones') ?: null,
        'banos' => post('banos') ?: null,
        'aseos' => post('aseos') ?: null,
        'planta' => post('planta') ?: null,
        'ascensor' => isset($_POST['ascensor']) ? 1 : 0,
        'garaje_incluido' => isset($_POST['garaje_incluido']) ? 1 : 0,
        'trastero_incluido' => isset($_POST['trastero_incluido']) ? 1 : 0,
        'terraza' => isset($_POST['terraza']) ? 1 : 0,
        'balcon' => isset($_POST['balcon']) ? 1 : 0,
        'jardin' => isset($_POST['jardin']) ? 1 : 0,
        'piscina' => isset($_POST['piscina']) ? 1 : 0,
        'aire_acondicionado' => isset($_POST['aire_acondicionado']) ? 1 : 0,
        'calefaccion' => post('calefaccion') ?: null,
        'orientacion' => post('orientacion') ?: null,
        'antiguedad' => post('antiguedad') ?: null,
        'estado_conservacion' => post('estado_conservacion') ?: null,
        'certificacion_energetica' => post('certificacion_energetica') ?: null,
        'referencia_catastral' => post('referencia_catastral') ?: null,
        'enlace' => post('enlace') ?: null,
        'descripcion' => $_POST['descripcion'] ?? null,
        'descripcion_interna' => $_POST['descripcion_interna'] ?? null,
        'fecha_publicacion_propiedad' => post('fecha_publicacion_propiedad') ?: null,
        'fecha_contacto' => post('fecha_contacto') ?: null,
        'hora_contacto' => post('hora_contacto') ?: null,
        'mejor_horario_contacto' => post('mejor_horario_contacto') ?: null,
        'fecha_proximo_contacto' => post('fecha_proximo_contacto') ?: null,
        'estado' => post('estado', 'nuevo_lead'),
        'temperatura' => post('temperatura', 'frio'),
        'prioridad' => post('prioridad', 'media'),
        'comision' => post('comision') ? floatval(str_replace(',', '.', post('comision'))) : null,
        'exclusividad' => isset($_POST['exclusividad']) ? 1 : 0,
        'notas' => $_POST['notas'] ?? null,
        'reformas' => $_POST['reformas'] ?? null,
        'historial_contactos' => $_POST['historial_contactos'] ?? null,
        'agente_id' => post('agente_id') ?: currentUserId(),
        'activo' => isset($_POST['activo']) ? 1 : 0,
        'propietarios_json' => !empty($_POST['propietarios_json']) ? $_POST['propietarios_json'] : null,
    ];

    // Auto-desactivar cuando se marca como descartado
    if ($data['etapa'] === 'descartado') {
        $data['activo'] = 0;
    }

    $erroresValidacion = validarProspecto($data);

    if (!empty($erroresValidacion)) {
        $error = implode('<br>', $erroresValidacion);
    } elseif (empty($data['nombre'])) {
        $error = 'El nombre es obligatorio.';
    } else {
        try {
            if ($id) {
                $fields = []; $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }
                $values[] = $id;
                $db->prepare("UPDATE prospectos SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'prospecto', $id, $data['nombre']);
            } else {
                $stmtRef = $db->query("SELECT MAX(CAST(SUBSTRING(referencia, 3) AS UNSIGNED)) as max_ref FROM prospectos WHERE referencia LIKE 'PR%'");
                $maxRef = $stmtRef->fetch()['max_ref'] ?? 0;
                $data['referencia'] = 'PR' . str_pad($maxRef + 1, 3, '0', STR_PAD_LEFT);

                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO prospectos (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                $id = $db->lastInsertId();
                registrarActividad('crear', 'prospecto', $id, $data['nombre']);
            }
            saveCustomFieldValues($id, 'prospecto', $_POST);
            setFlash('success', $prospecto ? 'Prospecto actualizado.' : 'Prospecto creado correctamente.');
            header('Location: ver.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$provincias = getProvincias();
$p = $prospecto ?? [];

// Preservar datos del formulario cuando hay un error de validación
if (!empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = array_merge($p, $_POST);
    // Re-evaluar checkboxes (no vienen en POST si están desmarcados)
    foreach (['ascensor','garaje_incluido','trastero_incluido','terraza','balcon','jardin','piscina','aire_acondicionado','exclusividad','activo'] as $cb) {
        $p[$cb] = isset($_POST[$cb]) ? 1 : 0;
    }
}

$etapas = [
    'nuevo_lead' => 'Nuevo Lead',
    'contactado' => 'Contactado',
    'seguimiento' => 'En Seguimiento',
    'visita_programada' => 'Visita Programada',
    'en_negociacion' => 'En Negociación',
    'captado' => 'Captado',
    'descartado' => 'Descartado',
];

$estados = [
    'nuevo_lead' => 'Nuevo lead',
    'contactado' => 'Contactado',
    'en_seguimiento' => 'En seguimiento',
    'visita_programada' => 'Visita programada',
    'captado' => 'Captado',
    'descartado' => 'Descartado',
];

$tiposPropiedad = ['Piso','Casa','Chalet','Adosado','Atico','Duplex','Estudio','Local','Oficina','Nave','Terreno','Garaje','Trastero','Edificio','Otro'];
$operaciones = ['venta' => 'Venta', 'alquiler' => 'Alquiler', 'alquiler_opcion_compra' => 'Alquiler con opción a compra', 'traspaso' => 'Traspaso'];
$orientaciones = ['norte'=>'Norte','sur'=>'Sur','este'=>'Este','oeste'=>'Oeste','noreste'=>'Noreste','noroeste'=>'Noroeste','sureste'=>'Sureste','suroeste'=>'Suroeste'];
$conservaciones = ['a_estrenar'=>'A estrenar','buen_estado'=>'Buen estado','a_reformar'=>'A reformar','en_construccion'=>'En construcción'];
$energeticas = ['A'=>'A','B'=>'B','C'=>'C','D'=>'D','E'=>'E','F'=>'F','G'=>'G','en_tramite'=>'En trámite','exento'=>'Exento'];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-person"></i> Datos del Prospecto</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= sanitize($p['nombre'] ?? post('nombre')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Teléfono *</label>
                    <div class="input-group">
                        <select id="prefijo_tel1" class="form-select" style="max-width:120px; flex-shrink:0;">
                            <?php
                            $prefijos = [
                                '+34'=>'🇪🇸 +34','+33'=>'🇫🇷 +33','+49'=>'🇩🇪 +49','+44'=>'🇬🇧 +44',
                                '+39'=>'🇮🇹 +39','+351'=>'🇵🇹 +351','+212'=>'🇲🇦 +212','+1'=>'🇺🇸 +1',
                                '+54'=>'🇦🇷 +54','+52'=>'🇲🇽 +52','+57'=>'🇨🇴 +57','+56'=>'🇨🇱 +56',
                                '+51'=>'🇵🇪 +51','+58'=>'🇻🇪 +58','+593'=>'🇪🇨 +593','+53'=>'🇨🇺 +53',
                                '+40'=>'🇷🇴 +40','+380'=>'🇺🇦 +380','+7'=>'🇷🇺 +7','+86'=>'🇨🇳 +86',
                                '+32'=>'🇧🇪 +32','+31'=>'🇳🇱 +31','+41'=>'🇨🇭 +41','+43'=>'🇦🇹 +43',
                            ];
                            foreach ($prefijos as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="tel" id="tel1_numero" class="form-control" placeholder="612345678" required>
                        <input type="hidden" name="telefono" id="telefono_combined" value="<?= sanitize($p['telefono'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Teléfono 2</label>
                    <div class="input-group">
                        <select id="prefijo_tel2" class="form-select" style="max-width:120px; flex-shrink:0;">
                            <?php foreach ($prefijos as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="tel" id="tel2_numero" class="form-control" placeholder="612345678">
                        <input type="hidden" name="telefono2" id="telefono2_combined" value="<?= sanitize($p['telefono2'] ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Referencia</label>
                    <?php if ($id): ?>
                    <input type="text" class="form-control" value="<?= sanitize($p['referencia'] ?? '') ?>" readonly>
                    <?php else: ?>
                    <input type="text" class="form-control text-muted" value="Auto" readonly>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($p['email'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Etapa Pipeline</label>
                    <select name="etapa" class="form-select">
                        <?php foreach ($etapas as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['etapa'] ?? 'nuevo_lead') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach ($estados as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['estado'] ?? 'nuevo_lead') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Temperatura</label>
                    <select name="temperatura" class="form-select">
                        <option value="frio" <?= ($p['temperatura'] ?? 'frio') === 'frio' ? 'selected' : '' ?>>❄️ Frío</option>
                        <option value="templado" <?= ($p['temperatura'] ?? '') === 'templado' ? 'selected' : '' ?>>🌡️ Templado</option>
                        <option value="caliente" <?= ($p['temperatura'] ?? '') === 'caliente' ? 'selected' : '' ?>>🔥 Caliente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Prioridad</label>
                    <select name="prioridad" class="form-select">
                        <option value="alta" <?= ($p['prioridad'] ?? 'media') === 'alta' ? 'selected' : '' ?>>🔴 Alta</option>
                        <option value="media" <?= ($p['prioridad'] ?? 'media') === 'media' ? 'selected' : '' ?>>🟡 Media</option>
                        <option value="baja" <?= ($p['prioridad'] ?? 'media') === 'baja' ? 'selected' : '' ?>>🟢 Baja</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Enlace *</label>
                    <input type="url" name="enlace" class="form-control" placeholder="https://..." value="<?= sanitize($p['enlace'] ?? '') ?>" required>
                </div>
            </div>
        </div>
    </div>

    <!-- Propietarios adicionales -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people"></i> Copropietarios <small class="text-muted fw-normal">(si la propiedad tiene más de un propietario)</small></span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddOwner">
                <i class="bi bi-person-plus"></i> Añadir copropietario
            </button>
        </div>
        <div class="card-body">
            <div id="ownersContainer"></div>
            <p id="ownersEmpty" class="text-muted small mb-0">Sin copropietarios. Pulsa «Añadir copropietario» si la propiedad tiene más de un titular.</p>
            <input type="hidden" name="propietarios_json" id="propietarios_json_field" value="<?= sanitize($p['propietarios_json'] ?? '') ?>">
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-house-door"></i> Datos de la Propiedad</div>
        <div class="card-body">
            <div class="row g-3">
                <!-- Tipo y Operación -->
                <div class="col-md-3">
                    <label class="form-label">Tipo de Propiedad</label>
                    <select name="tipo_propiedad" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($tiposPropiedad as $tp): ?>
                        <option value="<?= $tp ?>" <?= ($p['tipo_propiedad'] ?? '') === $tp ? 'selected' : '' ?>><?= $tp ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operación</label>
                    <select name="operacion" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($operaciones as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['operacion'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Precios -->
                <div class="col-md-3">
                    <label class="form-label">Precio Estimado</label>
                    <div class="input-group">
                        <input type="text" name="precio_estimado" class="form-control format-precio" value="<?= sanitize($p['precio_estimado'] ?? '') ?>">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio Propietario</label>
                    <div class="input-group">
                        <input type="text" name="precio_propietario" class="form-control format-precio" value="<?= sanitize($p['precio_propietario'] ?? '') ?>">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Comunidad (€/mes)</label>
                    <input type="text" name="precio_comunidad" class="form-control" value="<?= sanitize($p['precio_comunidad'] ?? '') ?>">
                </div>

                <!-- Superficies -->
                <div class="col-md-2">
                    <label class="form-label">Sup. Total (m²)</label>
                    <input type="number" name="superficie" class="form-control" step="0.01" value="<?= sanitize($p['superficie'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sup. Construida</label>
                    <input type="number" name="superficie_construida" class="form-control" step="0.01" value="<?= sanitize($p['superficie_construida'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sup. Útil</label>
                    <input type="number" name="superficie_util" class="form-control" step="0.01" value="<?= sanitize($p['superficie_util'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sup. Parcela</label>
                    <input type="number" name="superficie_parcela" class="form-control" step="0.01" value="<?= sanitize($p['superficie_parcela'] ?? '') ?>">
                </div>

                <!-- Habitaciones / Baños -->
                <div class="col-md-2">
                    <label class="form-label">Habitaciones</label>
                    <input type="number" name="habitaciones" class="form-control" min="0" value="<?= sanitize($p['habitaciones'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Baños</label>
                    <input type="number" name="banos" class="form-control" min="0" value="<?= sanitize($p['banos'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Aseos</label>
                    <input type="number" name="aseos" class="form-control" min="0" value="<?= sanitize($p['aseos'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Planta</label>
                    <input type="text" name="planta" class="form-control" value="<?= sanitize($p['planta'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Antigüedad (años)</label>
                    <input type="number" name="antiguedad" class="form-control" min="0" value="<?= sanitize($p['antiguedad'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Comisión (%)</label>
                    <div class="input-group">
                        <input type="text" name="comision" class="form-control" value="<?= sanitize($p['comision'] ?? '') ?>">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <!-- Dirección -->
                <div class="col-md-4">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control" value="<?= sanitize($p['direccion'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Nº</label>
                    <input type="text" name="numero" class="form-control" value="<?= sanitize($p['numero'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Piso</label>
                    <input type="text" name="piso_puerta" class="form-control" placeholder="2º" value="<?= sanitize($p['piso_puerta'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Escalera</label>
                    <input type="text" name="escalera" class="form-control" placeholder="A" value="<?= sanitize($p['escalera'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Puerta</label>
                    <input type="text" name="puerta" class="form-control" placeholder="1" value="<?= sanitize($p['puerta'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Barrio / Zona</label>
                    <input type="text" name="barrio" class="form-control" value="<?= sanitize($p['barrio'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">C. Postal</label>
                    <input type="text" name="codigo_postal" class="form-control" maxlength="5" value="<?= sanitize($p['codigo_postal'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Localidad</label>
                    <input type="text" name="localidad" class="form-control" value="<?= sanitize($p['localidad'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Provincia</label>
                    <select name="provincia" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($provincias as $prov): ?>
                        <option value="<?= $prov ?>" <?= ($p['provincia'] ?? '') === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Comunidad Autónoma</label>
                    <input type="text" name="comunidad_autonoma" class="form-control" value="<?= sanitize($p['comunidad_autonoma'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ref. Catastral</label>
                    <input type="text" name="referencia_catastral" class="form-control" maxlength="25" value="<?= sanitize($p['referencia_catastral'] ?? '') ?>">
                </div>

                <!-- Características / Selects -->
                <div class="col-md-3">
                    <label class="form-label">Orientación</label>
                    <select name="orientacion" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($orientaciones as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['orientacion'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Conservación</label>
                    <select name="estado_conservacion" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($conservaciones as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['estado_conservacion'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cert. Energética</label>
                    <select name="certificacion_energetica" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($energeticas as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['certificacion_energetica'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Calefacción</label>
                    <input type="text" name="calefaccion" class="form-control" placeholder="Gas, eléctrica..." value="<?= sanitize($p['calefaccion'] ?? '') ?>">
                </div>


                <!-- Checkboxes extras -->
                <div class="col-12">
                    <label class="form-label d-block">Extras</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php
                        $checks = [
                            'ascensor' => 'Ascensor', 'garaje_incluido' => 'Garaje', 'trastero_incluido' => 'Trastero',
                            'terraza' => 'Terraza', 'balcon' => 'Balcón', 'jardin' => 'Jardín',
                            'piscina' => 'Piscina', 'aire_acondicionado' => 'Aire Acond.',
                        ];
                        foreach ($checks as $ck => $cl): ?>
                        <div class="form-check">
                            <input type="checkbox" name="<?= $ck ?>" class="form-check-input" id="<?= $ck ?>" <?= ($p[$ck] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= $ck ?>"><?= $cl ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-calendar-event"></i> Seguimiento</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Publicación Propiedad *</label>
                    <input type="date" name="fecha_publicacion_propiedad" class="form-control" value="<?= sanitize($p['fecha_publicacion_propiedad'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Primer Contacto</label>
                    <input type="date" name="fecha_contacto" class="form-control" value="<?= sanitize($p['fecha_contacto'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hora de Contacto</label>
                    <input type="time" name="hora_contacto" class="form-control" value="<?= sanitize($p['hora_contacto'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mejor Horario de Contacto</label>
                    <input type="text" name="mejor_horario_contacto" class="form-control" placeholder="Ej: 10:00 - 13:00" value="<?= sanitize($p['mejor_horario_contacto'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Próximo Contacto</label>
                    <input type="date" name="fecha_proximo_contacto" class="form-control" value="<?= sanitize($p['fecha_proximo_contacto'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agente Asignado</label>
                    <select name="agente_id" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($p['agente_id'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-3 pt-1">
                        <div class="form-check">
                            <input type="checkbox" name="exclusividad" class="form-check-input" id="exclusividad" <?= ($p['exclusividad'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="exclusividad">Exclusividad</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($p['activo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-chat-text"></i> Notas y Descripciones</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Descripción (pública)</label>
                    <textarea name="descripcion" class="form-control" rows="4" placeholder="Descripción del inmueble..."><?= sanitize($p['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descripción interna</label>
                    <textarea name="descripcion_interna" class="form-control" rows="4" placeholder="Notas internas sobre el inmueble..."><?= sanitize($p['descripcion_interna'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notas Generales</label>
                    <textarea name="notas" class="form-control" rows="3" placeholder="Notas sobre el prospecto..."><?= sanitize($p['notas'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Reformas</label>
                    <textarea name="reformas" class="form-control" rows="3" placeholder="Info de reformas necesarias/realizadas..."><?= sanitize($p['reformas'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <?php
    $customValues = $id ? getCustomFieldValues($id, 'prospecto') : [];
    $customFieldsList = getCustomFields('prospecto');
    if (!empty($customFieldsList)):
    ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</div>
        <div class="card-body">
            <?php renderCustomFieldsForm('prospecto', $customValues); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Prospecto</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
// ── Selector de prefijo telefónico ──────────────────────────────
(function() {
    const prefijos = ['+34','+33','+49','+44','+39','+351','+212','+1','+54','+52','+57','+56','+51','+58','+593','+53','+40','+380','+7','+86','+32','+31','+41','+43'];

    function parsePhone(stored, prefixSel, numInput) {
        if (!stored) { prefixSel.value = '+34'; return; }
        const clean = stored.replace(/[\s\-\(\)]/g, '');
        if (clean.startsWith('+')) {
            // Buscar el prefijo más largo que coincida
            const match = prefijos.filter(p => clean.startsWith(p)).sort((a,b) => b.length - a.length)[0];
            if (match) {
                prefixSel.value = match;
                numInput.value = clean.slice(match.length);
                return;
            }
        }
        prefixSel.value = '+34';
        numInput.value = clean.startsWith('0') ? clean.slice(1) : clean;
    }

    function combinePhone(prefixSel, numInput, hiddenInput) {
        const num = numInput.value.trim();
        if (!num) { hiddenInput.value = ''; return; }
        const prefix = prefixSel.value;
        // Si el número ya tiene prefijo, no duplicar
        if (num.startsWith('+')) { hiddenInput.value = num; return; }
        hiddenInput.value = prefix + num;
    }

    const pref1 = document.getElementById('prefijo_tel1');
    const num1  = document.getElementById('tel1_numero');
    const hid1  = document.getElementById('telefono_combined');
    if (pref1 && num1 && hid1) {
        parsePhone(hid1.value, pref1, num1);
        [pref1, num1].forEach(el => el.addEventListener('input', () => combinePhone(pref1, num1, hid1)));
        [pref1, num1].forEach(el => el.addEventListener('change', () => combinePhone(pref1, num1, hid1)));
    }

    const pref2 = document.getElementById('prefijo_tel2');
    const num2  = document.getElementById('tel2_numero');
    const hid2  = document.getElementById('telefono2_combined');
    if (pref2 && num2 && hid2) {
        parsePhone(hid2.value, pref2, num2);
        [pref2, num2].forEach(el => el.addEventListener('input', () => combinePhone(pref2, num2, hid2)));
        [pref2, num2].forEach(el => el.addEventListener('change', () => combinePhone(pref2, num2, hid2)));
    }

    // Asegurar que los campos hidden están actualizados antes de enviar
    const form = document.querySelector('form[method="POST"]');
    if (form) {
        form.addEventListener('submit', () => {
            if (pref1 && num1 && hid1) combinePhone(pref1, num1, hid1);
            if (pref2 && num2 && hid2) combinePhone(pref2, num2, hid2);
        });
    }
})();

// ── Copropietarios ───────────────────────────────────────────────
(function() {
    const container = document.getElementById('ownersContainer');
    const emptyMsg  = document.getElementById('ownersEmpty');
    const jsonField = document.getElementById('propietarios_json_field');
    const btnAdd    = document.getElementById('btnAddOwner');
    let owners = [];

    // Cargar propietarios existentes
    try {
        const raw = jsonField ? jsonField.value : '';
        if (raw) owners = JSON.parse(raw) || [];
    } catch(e) { owners = []; }

    function prefixOptions(selected) {
        const prefijos = {'+34':'🇪🇸','+33':'🇫🇷','+49':'🇩🇪','+44':'🇬🇧','+39':'🇮🇹','+351':'🇵🇹','+212':'🇲🇦','+1':'🇺🇸','+54':'🇦🇷','+52':'🇲🇽','+57':'🇨🇴','+56':'🇨🇱','+51':'🇵🇪','+58':'🇻🇪','+40':'🇷🇴','+380':'🇺🇦','+7':'🇷🇺'};
        return Object.entries(prefijos).map(([k,f]) =>
            `<option value="${k}" ${k===selected?'selected':''}>${f} ${k}</option>`
        ).join('');
    }

    function parseStored(tel) {
        if (!tel) return {prefix:'+34', num:''};
        const c = tel.replace(/[\s\-\(\)]/g,'');
        if (c.startsWith('+')) {
            const pList = ['+351','+380','+593','+212','+34','+33','+49','+44','+39','+1','+54','+52','+57','+56','+51','+58','+40','+7'];
            const m = pList.find(p => c.startsWith(p));
            if (m) return {prefix:m, num:c.slice(m.length)};
        }
        return {prefix:'+34', num:c};
    }

    function render() {
        if (!container) return;
        if (owners.length === 0) {
            container.innerHTML = '';
            if (emptyMsg) emptyMsg.style.display = '';
            return;
        }
        if (emptyMsg) emptyMsg.style.display = 'none';
        container.innerHTML = owners.map((o, i) => {
            const tp = parseStored(o.telefono);
            return `<div class="border rounded p-3 mb-2 owner-row" data-idx="${i}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small text-muted">Propietario ${i+2}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-owner" data-idx="${i}"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm owner-nombre" data-idx="${i}" placeholder="Nombre" value="${o.nombre||''}">
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <select class="form-select form-select-sm owner-prefix" data-idx="${i}" style="max-width:100px;">${prefixOptions(tp.prefix)}</select>
                            <input type="tel" class="form-control owner-tel" data-idx="${i}" placeholder="612345678" value="${tp.num}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <input type="email" class="form-control form-control-sm owner-email" data-idx="${i}" placeholder="Email" value="${o.email||''}">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm owner-dni" data-idx="${i}" placeholder="DNI/NIE/CIF" value="${o.dni||''}">
                    </div>
                </div>
            </div>`;
        }).join('');

        // Eventos
        container.querySelectorAll('.btn-remove-owner').forEach(btn => {
            btn.addEventListener('click', () => { owners.splice(parseInt(btn.dataset.idx), 1); serializeAndRender(); });
        });
        ['owner-nombre','owner-email','owner-dni'].forEach(cls => {
            container.querySelectorAll('.'+cls).forEach(inp => {
                inp.addEventListener('input', serializeOwners);
            });
        });
        container.querySelectorAll('.owner-prefix,.owner-tel').forEach(inp => {
            inp.addEventListener('change', serializeOwners);
            inp.addEventListener('input', serializeOwners);
        });
    }

    function serializeOwners() {
        container.querySelectorAll('.owner-row').forEach(row => {
            const i = parseInt(row.dataset.idx);
            const prefix = (row.querySelector('.owner-prefix')||{}).value || '+34';
            const num    = (row.querySelector('.owner-tel')||{}).value.trim() || '';
            owners[i] = {
                nombre:   (row.querySelector('.owner-nombre')||{}).value || '',
                telefono: num ? prefix + num : '',
                email:    (row.querySelector('.owner-email')||{}).value || '',
                dni:      (row.querySelector('.owner-dni')||{}).value || '',
            };
        });
        if (jsonField) jsonField.value = JSON.stringify(owners);
    }

    function serializeAndRender() {
        serializeOwners();
        render();
        if (jsonField) jsonField.value = JSON.stringify(owners);
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            owners.push({nombre:'',telefono:'',email:'',dni:''});
            serializeAndRender();
        });
    }

    render();

    // Serializar antes de enviar
    const form = document.querySelector('form[method="POST"]');
    if (form) form.addEventListener('submit', serializeOwners);
})();

// ── Detección de duplicados ──────────────────────────────────────
(function() {
    const excludeId = <?= $id ?: 0 ?>;
    const excludeTipo = 'prospecto';

    function checkDuplicate(input, campo) {
        const valor = input.value.trim();
        let warnEl = input.parentElement.querySelector('.dup-warn');
        if (!warnEl) {
            warnEl = document.createElement('div');
            warnEl.className = 'dup-warn text-danger small mt-1';
            input.parentElement.appendChild(warnEl);
        }
        warnEl.textContent = '';
        if (!valor) return;

        fetch(`../../api/check_duplicate.php?campo=${encodeURIComponent(campo)}&valor=${encodeURIComponent(valor)}&exclude_id=${excludeId}&exclude_tipo=${excludeTipo}`)
            .then(r => r.json())
            .then(data => {
                if (data.matches && data.matches.length > 0) {
                    const names = data.matches.map(m => `${m.tipo}: ${m.nombre}${m.referencia ? ' (' + m.referencia + ')' : ''}`).join(', ');
                    warnEl.textContent = `⚠ Ya existe con este dato: ${names}`;
                }
            })
            .catch(() => {});
    }

    document.querySelectorAll('input[name="telefono"], input[name="telefono2"]').forEach(el => {
        el.addEventListener('blur', () => checkDuplicate(el, 'telefono'));
    });
    const emailEl = document.querySelector('input[name="email"]');
    if (emailEl) emailEl.addEventListener('blur', () => checkDuplicate(emailEl, 'email'));
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
