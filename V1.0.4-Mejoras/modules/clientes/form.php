<?php
$pageTitle = 'Nuevo Cliente';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/validators.php';
require_once __DIR__ . '/../../includes/automatizaciones_engine.php';

$db = getDB();
$id = intval(get('id'));
$cliente = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    if (!$cliente) { setFlash('danger', 'Cliente no encontrado.'); header('Location: index.php'); exit; }
    if (!isAdmin() && intval($cliente['agente_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar este cliente.');
        header('Location: index.php');
        exit;
    }
    $pageTitle = 'Editar Cliente';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $tipos = isset($_POST['tipo']) ? implode(',', $_POST['tipo']) : '';

    $data = [
        'nombre' => post('nombre'),
        'apellidos' => post('apellidos'),
        'email' => post('email') ?: null,
        'telefono' => post('telefono') ?: null,
        'telefono2' => post('telefono2') ?: null,
        'dni_nie_cif' => post('dni_nie_cif') ?: null,
        'tipo' => $tipos,
        'origen' => post('origen', 'otro'),
        'direccion' => post('direccion') ?: null,
        'codigo_postal' => post('codigo_postal') ?: null,
        'localidad' => post('localidad') ?: null,
        'provincia' => post('provincia') ?: null,
        'notas' => $_POST['notas'] ?? null,
        'presupuesto_min' => post('presupuesto_min') ? floatval(str_replace(',', '.', post('presupuesto_min'))) : null,
        'presupuesto_max' => post('presupuesto_max') ? floatval(str_replace(',', '.', post('presupuesto_max'))) : null,
        'zona_interes' => post('zona_interes') ?: null,
        'tipo_inmueble_interes' => post('tipo_inmueble_interes') ?: null,
        'habitaciones_min' => post('habitaciones_min') ?: null,
        'superficie_min' => post('superficie_min') ?: null,
        'operacion_interes' => post('operacion_interes') ?: null,
        'agente_id' => isAdmin()
            ? (post('agente_id') ?: ($cliente['agente_id'] ?? currentUserId()))
            : ($cliente['agente_id'] ?? currentUserId()),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];

    // Validacion avanzada
    $validationData = $data;
    $validationData['tipo'] = $tipos;
    $validationData['telefono'] = $_POST['telefono'] ?? '';
    $validationData['telefono2'] = $_POST['telefono2'] ?? '';
    $validationData['dni_nie_cif'] = $_POST['dni_nie_cif'] ?? '';
    $validationData['email'] = $_POST['email'] ?? '';
    $validationData['codigo_postal'] = $_POST['codigo_postal'] ?? '';
    $erroresValidacion = validarCliente($validationData);

    if (!empty($erroresValidacion)) {
        $error = implode('<br>', $erroresValidacion);
    } elseif (empty($data['nombre']) || empty($tipos)) {
        $error = 'Nombre y tipo de cliente son obligatorios.';
    } else {
        try {
            if ($id) {
                $fields = [];
                $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }
                $values[] = $id;
                $db->prepare("UPDATE clientes SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'cliente', $id, $data['nombre']);
            } else {
                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO clientes (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                $id = $db->lastInsertId();
                registrarActividad('crear', 'cliente', $id, $data['nombre']);

                try {
                    automatizacionesEjecutarTrigger('nuevo_cliente', [
                        'entidad_tipo' => 'cliente',
                        'entidad_id' => intval($id),
                        'cliente_id' => intval($id),
                        'agente_id' => intval($data['agente_id'] ?? 0),
                        'actor_user_id' => intval(currentUserId()),
                        'owner_user_id' => intval(currentUserId()),
                    ]);
                } catch (Throwable $e) {
                    if (function_exists('logError')) {
                        logError('Error trigger nuevo_cliente: ' . $e->getMessage());
                    }
                }
            }
            setFlash('success', $cliente ? 'Cliente actualizado.' : 'Cliente creado correctamente.');
            header('Location: ver.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$provincias = getProvincias();
$c = $cliente ?? [];
$tiposCliente = $c ? explode(',', $c['tipo']) : [];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-person"></i> Datos Personales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= sanitize($c['nombre'] ?? post('nombre')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" value="<?= sanitize($c['apellidos'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">DNI/NIE/CIF</label>
                    <input type="text" name="dni_nie_cif" class="form-control" value="<?= sanitize($c['dni_nie_cif'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($c['email'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telefono</label>
                    <input type="tel" name="telefono" class="form-control" value="<?= sanitize($c['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telefono 2</label>
                    <input type="tel" name="telefono2" class="form-control" value="<?= sanitize($c['telefono2'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Origen</label>
                    <select name="origen" class="form-select">
                        <?php foreach (['web'=>'Web','telefono'=>'Telefono','oficina'=>'Oficina','referido'=>'Referido','portal'=>'Portal','otro'=>'Otro'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($c['origen'] ?? 'otro') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Tipo de cliente *</label>
                    <div class="d-flex gap-4">
                        <?php foreach (['comprador'=>'Comprador','vendedor'=>'Vendedor','inquilino'=>'Inquilino','propietario'=>'Propietario','inversor'=>'Inversor'] as $k => $v): ?>
                        <div class="form-check">
                            <input type="checkbox" name="tipo[]" value="<?= $k ?>" class="form-check-input" id="tipo_<?= $k ?>" <?= in_array($k, $tiposCliente) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tipo_<?= $k ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-geo-alt"></i> Direccion</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Direccion</label>
                    <input type="text" name="direccion" class="form-control" value="<?= sanitize($c['direccion'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Codigo Postal</label>
                    <input type="text" name="codigo_postal" class="form-control" maxlength="5" value="<?= sanitize($c['codigo_postal'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Localidad</label>
                    <input type="text" name="localidad" class="form-control" value="<?= sanitize($c['localidad'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Provincia</label>
                    <select name="provincia" class="form-select">
                        <option value="">-</option>
                        <?php foreach ($provincias as $prov): ?>
                        <option value="<?= $prov ?>" <?= ($c['provincia'] ?? '') === $prov ? 'selected' : '' ?>><?= $prov ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-search"></i> Preferencias de Busqueda</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Operacion interes</label>
                    <select name="operacion_interes" class="form-select">
                        <option value="">-</option>
                        <option value="venta" <?= ($c['operacion_interes'] ?? '') === 'venta' ? 'selected' : '' ?>>Compra</option>
                        <option value="alquiler" <?= ($c['operacion_interes'] ?? '') === 'alquiler' ? 'selected' : '' ?>>Alquiler</option>
                        <option value="ambas" <?= ($c['operacion_interes'] ?? '') === 'ambas' ? 'selected' : '' ?>>Ambas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo inmueble interes</label>
                    <input type="text" name="tipo_inmueble_interes" class="form-control" value="<?= sanitize($c['tipo_inmueble_interes'] ?? '') ?>" placeholder="Ej: piso, casa">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Presupuesto min.</label>
                    <div class="input-group">
                        <input type="text" name="presupuesto_min" class="form-control format-precio" value="<?= sanitize($c['presupuesto_min'] ?? '') ?>">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Presupuesto max.</label>
                    <div class="input-group">
                        <input type="text" name="presupuesto_max" class="form-control format-precio" value="<?= sanitize($c['presupuesto_max'] ?? '') ?>">
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Zona de interes</label>
                    <input type="text" name="zona_interes" class="form-control" value="<?= sanitize($c['zona_interes'] ?? '') ?>" placeholder="Ej: Madrid centro, Costa del Sol">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hab. minimas</label>
                    <input type="number" name="habitaciones_min" class="form-control" min="0" value="<?= sanitize($c['habitaciones_min'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Superficie min. (m2)</label>
                    <input type="number" name="superficie_min" class="form-control" step="0.01" value="<?= sanitize($c['superficie_min'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agente asignado</label>
                    <select name="agente_id" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($c['agente_id'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-chat-text"></i> Notas</div>
        <div class="card-body">
            <textarea name="notas" class="form-control" rows="4" placeholder="Notas sobre el cliente..."><?= sanitize($c['notas'] ?? '') ?></textarea>
            <div class="form-check mt-3">
                <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($c['activo'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Cliente activo</label>
            </div>
            <?php if (!$id): ?>
            <div class="form-check mt-2">
                <input type="checkbox" name="rgpd_consent" class="form-check-input" id="rgpd_consent" required>
                <label class="form-check-label" for="rgpd_consent">
                    El cliente ha sido informado y consiente el tratamiento de sus datos personales segun la
                    <strong>RGPD/LOPD</strong> (Responsable: <?= RGPD_EMPRESA ?>. Finalidad: <?= RGPD_FINALIDAD ?>. Contacto DPD: <?= RGPD_EMAIL_DPD ?>).
                </label>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Cliente</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
(function() {
    const excludeId = <?= $id ?: 0 ?>;
    const excludeTipo = 'cliente';

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
