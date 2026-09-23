<?php
$pageTitle = 'Nueva Propiedad';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/validators.php';

$db = getDB();
$id = intval(get('id'));
$propiedad = null;
$fotos = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM propiedades WHERE id = ?");
    $stmt->execute([$id]);
    $propiedad = $stmt->fetch();
    if (!$propiedad) { setFlash('danger', 'Propiedad no encontrada.'); header('Location: index.php'); exit; }
    if (!isAdmin() && intval($propiedad['agente_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar esta propiedad.');
        header('Location: index.php');
        exit;
    }
    $pageTitle = 'Editar Propiedad';

    $stmtFotos = $db->prepare("SELECT * FROM propiedad_fotos WHERE propiedad_id = ? ORDER BY orden");
    $stmtFotos->execute([$id]);
    $fotos = $stmtFotos->fetchAll();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'referencia' => $id ? $propiedad['referencia'] : generarReferencia(),
        'titulo' => post('titulo'),
        'tipo' => post('tipo'),
        'operacion' => post('operacion'),
        'estado' => post('estado', 'disponible'),
        'precio' => floatval(str_replace(',', '.', post('precio'))),
        'precio_comunidad' => post('precio_comunidad') ? floatval(str_replace(',', '.', post('precio_comunidad'))) : null,
        'superficie_construida' => post('superficie_construida') ?: null,
        'superficie_util' => post('superficie_util') ?: null,
        'superficie_parcela' => post('superficie_parcela') ?: null,
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
        'direccion' => post('direccion') ?: null,
        'numero' => post('numero') ?: null,
        'piso_puerta' => post('piso_puerta') ?: null,
        'codigo_postal' => post('codigo_postal') ?: null,
        'localidad' => post('localidad'),
        'provincia' => post('provincia'),
        'comunidad_autonoma' => post('comunidad_autonoma') ?: null,
        'latitud' => post('latitud') ?: null,
        'longitud' => post('longitud') ?: null,
        'descripcion' => $_POST['descripcion'] ?? '',
        'descripcion_interna' => $_POST['descripcion_interna'] ?? '',
        'propietario_id' => post('propietario_id') ?: null,
        'agente_id' => isAdmin()
            ? (post('agente_id') ?: ($propiedad['agente_id'] ?? currentUserId()))
            : ($propiedad['agente_id'] ?? currentUserId()),
        'fecha_captacion' => post('fecha_captacion') ?: date('Y-m-d'),
        'fecha_disponibilidad' => post('fecha_disponibilidad') ?: null,
    ];

    $erroresValidacion = validarPropiedad($data);
    if (!empty($erroresValidacion)) {
        $error = implode('<br>', $erroresValidacion);
    } elseif (empty($data['titulo']) || empty($data['tipo']) || empty($data['operacion']) || empty($data['localidad']) || empty($data['provincia'])) {
        $error = 'Por favor, completa los campos obligatorios (titulo, tipo, operacion, localidad, provincia).';
    } else {
        try {
            if ($id) {
                $fields = [];
                $values = [];
                foreach ($data as $k => $v) {
                    $fields[] = "`$k` = ?";
                    $values[] = $v;
                }
                $values[] = $id;
                $db->prepare("UPDATE propiedades SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'propiedad', $id, $data['titulo']);
            } else {
                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO propiedades (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                $id = $db->lastInsertId();
                registrarActividad('crear', 'propiedad', $id, $data['titulo']);
            }

            // Subir fotos
            if (!empty($_FILES['fotos']['name'][0])) {
                foreach ($_FILES['fotos']['tmp_name'] as $i => $tmp) {
                    if ($_FILES['fotos']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['fotos']['name'][$i],
                            'type' => $_FILES['fotos']['type'][$i],
                            'tmp_name' => $tmp,
                            'error' => $_FILES['fotos']['error'][$i],
                            'size' => $_FILES['fotos']['size'][$i],
                        ];
                        $result = uploadImage($file, 'propiedades');
                        if (isset($result['success'])) {
                            $esPrincipal = (count($fotos) === 0 && $i === 0) ? 1 : 0;
                            $db->prepare("INSERT INTO propiedad_fotos (propiedad_id, archivo, orden, es_principal) VALUES (?, ?, ?, ?)")
                                ->execute([$id, $result['filename'], $i, $esPrincipal]);
                        }
                    }
                }
            }

            setFlash('success', $propiedad ? 'Propiedad actualizada correctamente.' : 'Propiedad creada correctamente.');
            header('Location: ver.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

// Obtener agentes y propietarios para selectores
$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$propietarios = $db->query("SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre_completo FROM clientes WHERE FIND_IN_SET('propietario', tipo) ORDER BY nombre")->fetchAll();
$tipos = getTiposPropiedad();
$provincias = getProvincias();

$p = $propiedad ?? [];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <!-- Datos Principales -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-house-door"></i> Datos Principales</div>
        <div class="card-body">
            <div class="row g-3">
                <?php if ($id): ?>
                <div class="col-md-2">
                    <label class="form-label">Referencia</label>
                    <input type="text" class="form-control" value="<?= sanitize($p['referencia'] ?? '') ?>" readonly>
                </div>
                <?php endif; ?>
                <div class="col-md-<?= $id ? 4 : 6 ?>">
                    <label class="form-label">Titulo *</label>
                    <input type="text" name="titulo" class="form-control" value="<?= sanitize($p['titulo'] ?? post('titulo')) ?>" required placeholder="Ej: Piso luminoso en centro de Madrid">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo inmueble *</label>
                    <select name="tipo" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($tipos as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['tipo'] ?? post('tipo')) === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operacion *</label>
                    <select name="operacion" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <option value="venta" <?= ($p['operacion'] ?? '') === 'venta' ? 'selected' : '' ?>>Venta</option>
                        <option value="alquiler" <?= ($p['operacion'] ?? '') === 'alquiler' ? 'selected' : '' ?>>Alquiler</option>
                        <option value="alquiler_opcion_compra" <?= ($p['operacion'] ?? '') === 'alquiler_opcion_compra' ? 'selected' : '' ?>>Alquiler con opcion a compra</option>
                        <option value="traspaso" <?= ($p['operacion'] ?? '') === 'traspaso' ? 'selected' : '' ?>>Traspaso</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="disponible" <?= ($p['estado'] ?? 'disponible') === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                        <option value="reservado" <?= ($p['estado'] ?? '') === 'reservado' ? 'selected' : '' ?>>Reservado</option>
                        <option value="vendido" <?= ($p['estado'] ?? '') === 'vendido' ? 'selected' : '' ?>>Vendido</option>
                        <option value="alquilado" <?= ($p['estado'] ?? '') === 'alquilado' ? 'selected' : '' ?>>Alquilado</option>
                        <option value="retirado" <?= ($p['estado'] ?? '') === 'retirado' ? 'selected' : '' ?>>Retirado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Precio *</label>
                    <div class="input-group">
                        <input type="text" name="precio" class="form-control format-precio" value="<?= sanitize($p['precio'] ?? post('precio')) ?>" required placeholder="0.00">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gastos comunidad</label>
                    <div class="input-group">
                        <input type="text" name="precio_comunidad" class="form-control format-precio" value="<?= sanitize($p['precio_comunidad'] ?? '') ?>" placeholder="0.00">
                        <span class="input-group-text">&euro;/mes</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agente</label>
                    <select name="agente_id" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($p['agente_id'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Caracteristicas -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-rulers"></i> Caracteristicas</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Sup. construida (m2)</label>
                    <input type="number" name="superficie_construida" class="form-control" step="0.01" value="<?= sanitize($p['superficie_construida'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sup. util (m2)</label>
                    <input type="number" name="superficie_util" class="form-control" step="0.01" value="<?= sanitize($p['superficie_util'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sup. parcela (m2)</label>
                    <input type="number" name="superficie_parcela" class="form-control" step="0.01" value="<?= sanitize($p['superficie_parcela'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Hab.</label>
                    <input type="number" name="habitaciones" class="form-control" min="0" max="20" value="<?= sanitize($p['habitaciones'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Banos</label>
                    <input type="number" name="banos" class="form-control" min="0" max="10" value="<?= sanitize($p['banos'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Aseos</label>
                    <input type="number" name="aseos" class="form-control" min="0" max="10" value="<?= sanitize($p['aseos'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Planta</label>
                    <input type="text" name="planta" class="form-control" value="<?= sanitize($p['planta'] ?? '') ?>" placeholder="Ej: 3a, Bajo">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Orientacion</label>
                    <select name="orientacion" class="form-select">
                        <option value="">-</option>
                        <?php foreach (['norte','sur','este','oeste','noreste','noroeste','sureste','suroeste'] as $or): ?>
                        <option value="<?= $or ?>" <?= ($p['orientacion'] ?? '') === $or ? 'selected' : '' ?>><?= ucfirst($or) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Antiguedad (anos)</label>
                    <input type="number" name="antiguedad" class="form-control" min="0" value="<?= sanitize($p['antiguedad'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Conservacion</label>
                    <select name="estado_conservacion" class="form-select">
                        <option value="">-</option>
                        <option value="a_estrenar" <?= ($p['estado_conservacion'] ?? '') === 'a_estrenar' ? 'selected' : '' ?>>A estrenar</option>
                        <option value="buen_estado" <?= ($p['estado_conservacion'] ?? '') === 'buen_estado' ? 'selected' : '' ?>>Buen estado</option>
                        <option value="a_reformar" <?= ($p['estado_conservacion'] ?? '') === 'a_reformar' ? 'selected' : '' ?>>A reformar</option>
                        <option value="en_construccion" <?= ($p['estado_conservacion'] ?? '') === 'en_construccion' ? 'selected' : '' ?>>En construccion</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Calefaccion</label>
                    <input type="text" name="calefaccion" class="form-control" value="<?= sanitize($p['calefaccion'] ?? '') ?>" placeholder="Ej: Central, Gas individual">
                </div>
            </div>
            <hr>
            <div class="row g-3">
                <div class="col-12"><label class="form-label fw-bold">Extras</label></div>
                <?php
                $extras = ['ascensor' => 'Ascensor', 'garaje_incluido' => 'Garaje', 'trastero_incluido' => 'Trastero', 'terraza' => 'Terraza', 'balcon' => 'Balcon', 'jardin' => 'Jardin', 'piscina' => 'Piscina', 'aire_acondicionado' => 'Aire acondicionado'];
                foreach ($extras as $key => $label):
                ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="form-check">
                        <input type="checkbox" name="<?= $key ?>" class="form-check-input" id="<?= $key ?>" <?= !empty($p[$key]) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= $key ?>"><?= $label ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Ubicacion -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-geo-alt"></i> Ubicacion</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Direccion</label>
                    <input type="text" name="direccion" class="form-control" value="<?= sanitize($p['direccion'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Num.</label>
                    <input type="text" name="numero" class="form-control" value="<?= sanitize($p['numero'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Piso/Puerta</label>
                    <input type="text" name="piso_puerta" class="form-control" value="<?= sanitize($p['piso_puerta'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Codigo Postal</label>
                    <input type="text" name="codigo_postal" class="form-control" maxlength="5" value="<?= sanitize($p['codigo_postal'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Localidad *</label>
                    <input type="text" name="localidad" class="form-control" value="<?= sanitize($p['localidad'] ?? post('localidad')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Provincia *</label>
                    <select name="provincia" class="form-select" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($provincias as $prov): ?>
                        <option value="<?= $prov ?>" <?= ($p['provincia'] ?? post('provincia')) === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Comunidad Autonoma</label>
                    <input type="text" name="comunidad_autonoma" class="form-control" value="<?= sanitize($p['comunidad_autonoma'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Latitud</label>
                    <input type="text" name="latitud" class="form-control" value="<?= sanitize($p['latitud'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Longitud</label>
                    <input type="text" name="longitud" class="form-control" value="<?= sanitize($p['longitud'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Datos legales España -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-file-earmark-text"></i> Datos Legales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Referencia Catastral</label>
                    <input type="text" name="referencia_catastral" class="form-control" maxlength="25" value="<?= sanitize($p['referencia_catastral'] ?? '') ?>" placeholder="20 caracteres alfanumericos">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Certificacion Energetica</label>
                    <select name="certificacion_energetica" class="form-select">
                        <option value="">-</option>
                        <?php foreach (['A','B','C','D','E','F','G','en_tramite','exento'] as $ce): ?>
                        <option value="<?= $ce ?>" <?= ($p['certificacion_energetica'] ?? '') === $ce ? 'selected' : '' ?>><?= $ce === 'en_tramite' ? 'En tramite' : ($ce === 'exento' ? 'Exento' : $ce) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Propietario</label>
                    <select name="propietario_id" class="form-select">
                        <option value="">Sin asignar</option>
                        <?php foreach ($propietarios as $prop): ?>
                        <option value="<?= $prop['id'] ?>" <?= ($p['propietario_id'] ?? '') == $prop['id'] ? 'selected' : '' ?>><?= sanitize($prop['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Captacion</label>
                    <input type="date" name="fecha_captacion" class="form-control" value="<?= sanitize($p['fecha_captacion'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Disponibilidad</label>
                    <input type="date" name="fecha_disponibilidad" class="form-control" value="<?= sanitize($p['fecha_disponibilidad'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Descripcion -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-text-paragraph"></i> Descripcion</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Descripcion publica</label>
                <textarea name="descripcion" class="form-control" rows="5" placeholder="Descripcion que se mostrara en portales..."><?= sanitize($p['descripcion'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="form-label">Notas internas</label>
                <textarea name="descripcion_interna" class="form-control" rows="3" placeholder="Notas privadas para el equipo..."><?= sanitize($p['descripcion_interna'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Fotos -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-images"></i> Fotografias</div>
        <div class="card-body">
            <?php if (!empty($fotos)): ?>
            <div class="row g-2 mb-3 property-gallery">
                <?php foreach ($fotos as $foto): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <img src="<?= APP_URL ?>/assets/uploads/<?= sanitize($foto['archivo']) ?>" alt="" class="img-fluid rounded">
                    <div class="text-center mt-1">
                        <?php if ($foto['es_principal']): ?><span class="badge bg-primary">Principal</span><?php endif; ?>
                        <form method="POST" action="delete_foto.php" class="d-inline" onsubmit="return confirm('Eliminar esta foto?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= intval($foto['id']) ?>">
                            <input type="hidden" name="prop" value="<?= intval($id) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div>
                <label class="form-label">Subir fotos (JPG, PNG, WebP - max 10MB)</label>
                <input type="file" name="fotos[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
            </div>
        </div>
    </div>

    <!-- Botones -->
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Propiedad</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
