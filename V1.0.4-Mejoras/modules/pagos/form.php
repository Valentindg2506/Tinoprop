<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$id = intval(get('id'));
$factura = null;
$lineas = [];

if ($id) {
    $stmt = $db->prepare("SELECT * FROM facturas WHERE id = ?");
    $stmt->execute([$id]);
    $factura = $stmt->fetch();
    if (!$factura) { setFlash('danger', 'Factura no encontrada.'); header('Location: index.php'); exit; }
    if (!isAdmin() && intval($factura['usuario_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar esta factura.');
        header('Location: index.php');
        exit;
    }
    $lineas = json_decode($factura['lineas'], true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $cliente_id = intval(post('cliente_id')) ?: null;
    $propiedad_id = intval(post('propiedad_id')) ?: null;
    $concepto = trim(post('concepto'));
    $fecha_emision = post('fecha_emision');
    $fecha_vencimiento = post('fecha_vencimiento') ?: null;
    $notas = trim(post('notas'));
    $metodo_pago = post('metodo_pago', '');
    $lineasJson = $_POST['lineas_json'] ?? '[]';
    $lineasArr = json_decode($lineasJson, true) ?: [];

    $subtotal = 0; $ivaTotal = 0;
    foreach ($lineasArr as &$l) {
        $l['cantidad'] = floatval($l['cantidad'] ?? 1);
        $l['precio_unitario'] = floatval($l['precio_unitario'] ?? 0);
        $l['iva'] = floatval($l['iva'] ?? 21);
        $lineTotal = $l['cantidad'] * $l['precio_unitario'];
        $subtotal += $lineTotal;
        $ivaTotal += $lineTotal * ($l['iva'] / 100);
    }
    unset($l);
    $total = $subtotal + $ivaTotal;

    if (empty($concepto)) { setFlash('danger', 'El concepto es obligatorio.'); }
    elseif (empty($lineasArr)) { setFlash('danger', 'Agrega al menos una linea.'); }
    else {
        if ($id) {
            if (isAdmin()) {
                $stmt = $db->prepare("UPDATE facturas SET cliente_id=?, propiedad_id=?, concepto=?, lineas=?, subtotal=?, iva_total=?, total=?, fecha_emision=?, fecha_vencimiento=?, notas=?, metodo_pago=? WHERE id=?");
                $stmt->execute([$cliente_id, $propiedad_id, $concepto, json_encode($lineasArr), $subtotal, $ivaTotal, $total, $fecha_emision, $fecha_vencimiento, $notas, $metodo_pago, $id]);
            } else {
                $stmt = $db->prepare("UPDATE facturas SET cliente_id=?, propiedad_id=?, concepto=?, lineas=?, subtotal=?, iva_total=?, total=?, fecha_emision=?, fecha_vencimiento=?, notas=?, metodo_pago=? WHERE id=? AND usuario_id=?");
                $stmt->execute([$cliente_id, $propiedad_id, $concepto, json_encode($lineasArr), $subtotal, $ivaTotal, $total, $fecha_emision, $fecha_vencimiento, $notas, $metodo_pago, $id, currentUserId()]);
            }
        } else {
            $config = $db->query("SELECT prefijo_factura, siguiente_numero FROM configuracion_pagos LIMIT 1")->fetch();
            $numero = ($config['prefijo_factura'] ?? 'FAC-') . str_pad($config['siguiente_numero'] ?? 1, 5, '0', STR_PAD_LEFT);
            $tokenPago = bin2hex(random_bytes(32));
            $stmt = $db->prepare("INSERT INTO facturas (numero, cliente_id, propiedad_id, concepto, lineas, subtotal, iva_total, total, fecha_emision, fecha_vencimiento, notas, metodo_pago, token_pago, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$numero, $cliente_id, $propiedad_id, $concepto, json_encode($lineasArr), $subtotal, $ivaTotal, $total, $fecha_emision, $fecha_vencimiento, $notas, $metodo_pago, $tokenPago, currentUserId()]);
            $db->exec("UPDATE configuracion_pagos SET siguiente_numero = siguiente_numero + 1 WHERE id = 1");
            registrarActividad('crear', 'factura', $db->lastInsertId(), $numero);
        }
        setFlash('success', 'Factura guardada.');
        header('Location: index.php');
        exit;
    }
    header('Location: form.php' . ($id ? '?id=' . $id : ''));
    exit;
}

$pageTitle = $id ? 'Editar Factura' : 'Nueva Factura';
require_once __DIR__ . '/../../includes/header.php';

$stmtClientes = $db->prepare("SELECT id, nombre, apellidos FROM clientes WHERE activo = 1" . (isAdmin() ? '' : ' AND agente_id = ?') . " ORDER BY nombre");
$stmtClientes->execute(isAdmin() ? [] : [currentUserId()]);
$clientes = $stmtClientes->fetchAll();

$stmtPropiedades = $db->prepare("SELECT id, referencia, titulo FROM propiedades" . (isAdmin() ? '' : ' WHERE agente_id = ?') . " ORDER BY referencia");
$stmtPropiedades->execute(isAdmin() ? [] : [currentUserId()]);
$propiedades = $stmtPropiedades->fetchAll();
$config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch();
$ivaDefecto = $config['iva_defecto'] ?? 21;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
</div>

<form method="POST" id="facturaForm">
    <?= csrfField() ?>
    <input type="hidden" name="lineas_json" id="lineasJson">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-receipt"></i> Datos de la factura</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cliente</label>
                            <select name="cliente_id" class="form-select">
                                <option value="">Sin cliente</option>
                                <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($factura['cliente_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['nombre'] . ' ' . $c['apellidos']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Propiedad (opcional)</label>
                            <select name="propiedad_id" class="form-select">
                                <option value="">Ninguna</option>
                                <?php foreach ($propiedades as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($factura['propiedad_id'] ?? '') == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['referencia'] . ' - ' . $p['titulo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Concepto <span class="text-danger">*</span></label>
                            <input type="text" name="concepto" class="form-control" required value="<?= sanitize($factura['concepto'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha emision</label>
                            <input type="date" name="fecha_emision" class="form-control" value="<?= $factura['fecha_emision'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha vencimiento</label>
                            <input type="date" name="fecha_vencimiento" class="form-control" value="<?= $factura['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Metodo de pago</label>
                            <select name="metodo_pago" class="form-select">
                                <option value="">-</option>
                                <?php foreach (['transferencia','efectivo','tarjeta','stripe','bizum'] as $mp): ?>
                                <option value="<?= $mp ?>" <?= ($factura['metodo_pago'] ?? '') === $mp ? 'selected' : '' ?>><?= ucfirst($mp) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notas" class="form-control" rows="2"><?= sanitize($factura['notas'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-ol"></i> Lineas</h6>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addLinea()"><i class="bi bi-plus"></i> Agregar linea</button>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0" id="lineasTable">
                        <thead class="table-light">
                            <tr><th>Descripcion</th><th style="width:80px">Cant.</th><th style="width:120px">Precio</th><th style="width:80px">IVA%</th><th style="width:120px">Total</th><th style="width:40px"></th></tr>
                        </thead>
                        <tbody id="lineasBody"></tbody>
                        <tfoot class="table-light">
                            <tr><td colspan="4" class="text-end fw-bold">Subtotal</td><td id="subtotalDisplay" class="fw-bold">0,00 &euro;</td><td></td></tr>
                            <tr><td colspan="4" class="text-end fw-bold">IVA</td><td id="ivaDisplay" class="fw-bold">0,00 &euro;</td><td></td></tr>
                            <tr><td colspan="4" class="text-end fw-bold fs-5">TOTAL</td><td id="totalDisplay" class="fw-bold fs-5 text-success">0,00 &euro;</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Guardar Factura</button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
let lineas = <?= json_encode($lineas) ?>;
const ivaDefecto = <?= $ivaDefecto ?>;

function renderLineas() {
    const body = document.getElementById('lineasBody');
    body.innerHTML = '';
    let subtotal = 0, iva = 0;
    lineas.forEach((l, i) => {
        const cant = parseFloat(l.cantidad) || 1;
        const precio = parseFloat(l.precio_unitario) || 0;
        const ivaP = parseFloat(l.iva) || 0;
        const lineTotal = cant * precio;
        const lineIva = lineTotal * (ivaP / 100);
        subtotal += lineTotal;
        iva += lineIva;
        body.innerHTML += `<tr>
            <td><input type="text" class="form-control form-control-sm" value="${l.descripcion||''}" onchange="lineas[${i}].descripcion=this.value"></td>
            <td><input type="number" class="form-control form-control-sm" value="${cant}" min="1" step="0.01" oninput="lineas[${i}].cantidad=this.value;renderLineas()"></td>
            <td><input type="number" class="form-control form-control-sm" value="${precio}" min="0" step="0.01" oninput="lineas[${i}].precio_unitario=this.value;renderLineas()"></td>
            <td><input type="number" class="form-control form-control-sm" value="${ivaP}" min="0" step="0.01" oninput="lineas[${i}].iva=this.value;renderLineas()"></td>
            <td class="fw-bold">${(lineTotal + lineIva).toFixed(2)} &euro;</td>
            <td><a href="#" class="text-danger" onclick="lineas.splice(${i},1);renderLineas();return false"><i class="bi bi-x-lg"></i></a></td>
        </tr>`;
    });
    document.getElementById('subtotalDisplay').innerHTML = subtotal.toFixed(2) + ' &euro;';
    document.getElementById('ivaDisplay').innerHTML = iva.toFixed(2) + ' &euro;';
    document.getElementById('totalDisplay').innerHTML = (subtotal + iva).toFixed(2) + ' &euro;';
}

function addLinea() {
    lineas.push({descripcion:'', cantidad:1, precio_unitario:0, iva:ivaDefecto});
    renderLineas();
}

document.getElementById('facturaForm').addEventListener('submit', function() {
    document.getElementById('lineasJson').value = JSON.stringify(lineas);
});

if (!lineas.length) addLinea();
renderLineas();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
