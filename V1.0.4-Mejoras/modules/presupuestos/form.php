<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$pres = null; $lineas = [];
if ($id) {
    $pres = $db->prepare("SELECT * FROM presupuestos WHERE id=?"); $pres->execute([$id]); $pres=$pres->fetch();
    if ($pres && !isAdmin() && intval($pres['usuario_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar este presupuesto.');
        header('Location: index.php');
        exit;
    }
    if ($pres) $lineas = json_decode($pres['lineas'], true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $clienteId = intval(post('cliente_id')) ?: null;
    $propiedadId = intval(post('propiedad_id')) ?: null;
    $titulo = trim(post('titulo'));
    $descripcion = trim(post('descripcion'));
    $fechaEmision = post('fecha_emision');
    $validezDias = intval(post('validez_dias')) ?: 30;
    $notas = trim(post('notas'));
    $condiciones = trim(post('condiciones'));
    $lineasJson = $_POST['lineas_json'] ?? '[]';
    $lineasArr = json_decode($lineasJson, true) ?: [];

    $subtotal = 0; $ivaTotal = 0;
    foreach ($lineasArr as &$l) {
        $l['cantidad'] = floatval($l['cantidad'] ?? 1);
        $l['precio_unitario'] = floatval($l['precio_unitario'] ?? 0);
        $l['iva'] = floatval($l['iva'] ?? 21);
        $lt = $l['cantidad'] * $l['precio_unitario'];
        $subtotal += $lt;
        $ivaTotal += $lt * ($l['iva'] / 100);
    }
    unset($l);
    $total = $subtotal + $ivaTotal;
    $fechaExp = date('Y-m-d', strtotime($fechaEmision . " +{$validezDias} days"));

    if (empty($titulo)) { setFlash('danger', 'Titulo obligatorio.'); }
    else {
        if ($id) {
            if (isAdmin()) {
                $db->prepare("UPDATE presupuestos SET cliente_id=?, propiedad_id=?, titulo=?, descripcion=?, lineas=?, subtotal=?, iva_total=?, total=?, validez_dias=?, fecha_emision=?, fecha_expiracion=?, notas=?, condiciones=? WHERE id=?")
                    ->execute([$clienteId, $propiedadId, $titulo, $descripcion, json_encode($lineasArr), $subtotal, $ivaTotal, $total, $validezDias, $fechaEmision, $fechaExp, $notas, $condiciones, $id]);
            } else {
                $db->prepare("UPDATE presupuestos SET cliente_id=?, propiedad_id=?, titulo=?, descripcion=?, lineas=?, subtotal=?, iva_total=?, total=?, validez_dias=?, fecha_emision=?, fecha_expiracion=?, notas=?, condiciones=? WHERE id=? AND usuario_id=?")
                    ->execute([$clienteId, $propiedadId, $titulo, $descripcion, json_encode($lineasArr), $subtotal, $ivaTotal, $total, $validezDias, $fechaEmision, $fechaExp, $notas, $condiciones, $id, currentUserId()]);
            }
        } else {
            $num = 'PRES-' . str_pad(intval($db->query("SELECT COUNT(*)+1 FROM presupuestos")->fetchColumn()), 5, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(32));
            $db->prepare("INSERT INTO presupuestos (numero, cliente_id, propiedad_id, titulo, descripcion, lineas, subtotal, iva_total, total, validez_dias, fecha_emision, fecha_expiracion, notas, condiciones, token, usuario_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num, $clienteId, $propiedadId, $titulo, $descripcion, json_encode($lineasArr), $subtotal, $ivaTotal, $total, $validezDias, $fechaEmision, $fechaExp, $notas, $condiciones, $token, currentUserId()]);
        }
        setFlash('success', 'Presupuesto guardado.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = $id ? 'Editar Presupuesto' : 'Nuevo Presupuesto';
require_once __DIR__ . '/../../includes/header.php';

$stmtClientes = $db->prepare("SELECT id, nombre, apellidos FROM clientes WHERE activo=1" . (isAdmin() ? '' : ' AND agente_id = ?') . " ORDER BY nombre");
$stmtClientes->execute(isAdmin() ? [] : [currentUserId()]);
$clientes = $stmtClientes->fetchAll();

$stmtPropiedades = $db->prepare("SELECT id, titulo, referencia FROM propiedades WHERE estado != 'retirado'" . (isAdmin() ? '' : ' AND agente_id = ?') . " ORDER BY titulo");
$stmtPropiedades->execute(isAdmin() ? [] : [currentUserId()]);
$propiedades = $stmtPropiedades->fetchAll();
$config = $db->query("SELECT iva_defecto FROM configuracion_pagos LIMIT 1")->fetch();
$ivaDefault = $config['iva_defecto'] ?? 21;
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>

<form method="POST" id="presForm">
    <?= csrfField() ?>
    <input type="hidden" name="lineas_json" id="lineasJson">

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control" value="<?= sanitize($pres['titulo']??'') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select"><option value="">--</option>
                    <?php foreach($clientes as $c): ?><option value="<?= $c['id'] ?>" <?= ($pres['cliente_id']??'')==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Propiedad</label>
                    <select name="propiedad_id" class="form-select"><option value="">--</option>
                    <?php foreach($propiedades as $pr): ?><option value="<?= $pr['id'] ?>" <?= ($pres['propiedad_id']??'')==$pr['id']?'selected':'' ?>><?= sanitize($pr['referencia'].' - '.$pr['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Descripcion</label><textarea name="descripcion" class="form-control" rows="2"><?= sanitize($pres['descripcion']??'') ?></textarea></div>
                <div class="col-md-3"><label class="form-label">Fecha emision</label><input type="date" name="fecha_emision" class="form-control" value="<?= $pres['fecha_emision']??date('Y-m-d') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Validez (dias)</label><input type="number" name="validez_dias" class="form-control" value="<?= $pres['validez_dias']??30 ?>" min="1"></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between"><h6 class="mb-0">Lineas</h6><button type="button" class="btn btn-sm btn-primary" onclick="addLinea()"><i class="bi bi-plus"></i> Nueva linea</button></div>
        <div class="card-body">
            <table class="table table-sm" id="lineasTable">
                <thead><tr><th>Descripcion</th><th style="width:80px">Cant.</th><th style="width:110px">Precio</th><th style="width:80px">IVA %</th><th style="width:100px">Total</th><th style="width:40px"></th></tr></thead>
                <tbody id="lineasBody"></tbody>
                <tfoot>
                    <tr><td colspan="4" class="text-end fw-bold">Subtotal</td><td id="subtotalDisplay">0,00 €</td><td></td></tr>
                    <tr><td colspan="4" class="text-end">IVA</td><td id="ivaDisplay">0,00 €</td><td></td></tr>
                    <tr><td colspan="4" class="text-end fw-bold fs-5">Total</td><td id="totalDisplay" class="fw-bold fs-5">0,00 €</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="3"><?= sanitize($pres['notas']??'') ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Condiciones</label><textarea name="condiciones" class="form-control" rows="3"><?= sanitize($pres['condiciones']??'Presupuesto valido por 30 dias. IVA incluido.') ?></textarea></div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
</form>

<script>
let lineas = <?= json_encode($lineas ?: []) ?>;
const ivaDefault = <?= $ivaDefault ?>;

function addLinea() { lineas.push({descripcion:'',cantidad:1,precio_unitario:0,iva:ivaDefault}); render(); }
function removeLinea(i) { lineas.splice(i,1); render(); }

function render() {
    const tbody = document.getElementById('lineasBody');
    tbody.innerHTML = '';
    let sub=0, iva=0;
    lineas.forEach((l,i) => {
        const lt = l.cantidad * l.precio_unitario;
        const li = lt * (l.iva/100);
        sub += lt; iva += li;
        tbody.innerHTML += `<tr>
            <td><input type="text" class="form-control form-control-sm" value="${l.descripcion||''}" onchange="lineas[${i}].descripcion=this.value"></td>
            <td><input type="number" class="form-control form-control-sm" value="${l.cantidad}" min="1" step="0.01" onchange="lineas[${i}].cantidad=parseFloat(this.value);render()"></td>
            <td><input type="number" class="form-control form-control-sm" value="${l.precio_unitario}" min="0" step="0.01" onchange="lineas[${i}].precio_unitario=parseFloat(this.value);render()"></td>
            <td><input type="number" class="form-control form-control-sm" value="${l.iva}" min="0" step="0.01" onchange="lineas[${i}].iva=parseFloat(this.value);render()"></td>
            <td class="fw-semibold">${(lt+li).toFixed(2)} €</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLinea(${i})"><i class="bi bi-x"></i></button></td>
        </tr>`;
    });
    document.getElementById('subtotalDisplay').textContent = sub.toFixed(2)+' €';
    document.getElementById('ivaDisplay').textContent = iva.toFixed(2)+' €';
    document.getElementById('totalDisplay').textContent = (sub+iva).toFixed(2)+' €';
}

document.getElementById('presForm').addEventListener('submit', function() {
    document.getElementById('lineasJson').value = JSON.stringify(lineas);
});

if (!lineas.length) addLinea();
render();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
