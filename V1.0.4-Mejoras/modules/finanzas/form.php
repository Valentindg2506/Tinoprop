<?php
$pageTitle = 'Nuevo Registro Financiero';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));
$registro = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM finanzas WHERE id = ?");
    $stmt->execute([$id]);
    $registro = $stmt->fetch();
    if (!$registro) { setFlash('danger', 'Registro no encontrado.'); header('Location: index.php'); exit; }
    $pageTitle = 'Editar Registro Financiero';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $importe = floatval(str_replace(',', '.', post('importe')));
    $iva = floatval(post('iva', 21));
    $importeTotal = $importe * (1 + $iva / 100);

    $data = [
        'tipo' => post('tipo'),
        'concepto' => post('concepto'),
        'importe' => $importe,
        'iva' => $iva,
        'importe_total' => round($importeTotal, 2),
        'fecha' => post('fecha'),
        'estado' => post('estado', 'pendiente'),
        'propiedad_id' => post('propiedad_id') ?: null,
        'cliente_id' => post('cliente_id') ?: null,
        'agente_id' => post('agente_id') ?: currentUserId(),
        'factura_numero' => post('factura_numero') ?: null,
        'notas' => $_POST['notas'] ?? null,
    ];

    if (empty($data['concepto']) || empty($data['fecha'])) {
        $error = 'Concepto y fecha son obligatorios.';
    } else {
        try {
            if ($id) {
                $fields = []; $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }
                $values[] = $id;
                $db->prepare("UPDATE finanzas SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'finanza', $id, $data['concepto']);
            } else {
                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO finanzas (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                registrarActividad('crear', 'finanza', $db->lastInsertId(), $data['concepto']);
            }
            setFlash('success', $registro ? 'Registro actualizado.' : 'Registro creado.');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$propiedades = $db->query("SELECT id, CONCAT(referencia, ' - ', titulo) as nombre FROM propiedades ORDER BY referencia")->fetchAll();
$clientes = $db->query("SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre_completo FROM clientes ORDER BY nombre")->fetchAll();
$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();
$r = $registro ?? [];
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-cash-stack"></i> Datos Financieros</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" class="form-select" required>
                        <option value="comision_venta" <?= ($r['tipo'] ?? '') === 'comision_venta' ? 'selected' : '' ?>>Comision venta</option>
                        <option value="comision_alquiler" <?= ($r['tipo'] ?? '') === 'comision_alquiler' ? 'selected' : '' ?>>Comision alquiler</option>
                        <option value="honorarios" <?= ($r['tipo'] ?? '') === 'honorarios' ? 'selected' : '' ?>>Honorarios</option>
                        <option value="gasto" <?= ($r['tipo'] ?? '') === 'gasto' ? 'selected' : '' ?>>Gasto</option>
                        <option value="ingreso_otro" <?= ($r['tipo'] ?? '') === 'ingreso_otro' ? 'selected' : '' ?>>Otro ingreso</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Concepto *</label>
                    <input type="text" name="concepto" class="form-control" value="<?= sanitize($r['concepto'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Importe *</label>
                    <div class="input-group">
                        <input type="text" name="importe" class="form-control format-precio" value="<?= sanitize($r['importe'] ?? '') ?>" required>
                        <span class="input-group-text">&euro;</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">IVA %</label>
                    <select name="iva" class="form-select">
                        <option value="21" <?= ($r['iva'] ?? 21) == 21 ? 'selected' : '' ?>>21%</option>
                        <option value="10" <?= ($r['iva'] ?? '') == 10 ? 'selected' : '' ?>>10%</option>
                        <option value="4" <?= ($r['iva'] ?? '') == 4 ? 'selected' : '' ?>>4%</option>
                        <option value="0" <?= ($r['iva'] ?? '') == 0 ? 'selected' : '' ?>>0% (Exento)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" value="<?= sanitize($r['fecha'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="pendiente" <?= ($r['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="cobrado" <?= ($r['estado'] ?? '') === 'cobrado' ? 'selected' : '' ?>>Cobrado</option>
                        <option value="pagado" <?= ($r['estado'] ?? '') === 'pagado' ? 'selected' : '' ?>>Pagado</option>
                        <option value="anulado" <?= ($r['estado'] ?? '') === 'anulado' ? 'selected' : '' ?>>Anulado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">N. Factura</label>
                    <input type="text" name="factura_numero" class="form-control" value="<?= sanitize($r['factura_numero'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Agente</label>
                    <select name="agente_id" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($r['agente_id'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Propiedad</label>
                    <select name="propiedad_id" class="form-select">
                        <option value="">Ninguna</option>
                        <?php foreach ($propiedades as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= ($r['propiedad_id'] ?? '') == $pr['id'] ? 'selected' : '' ?>><?= sanitize($pr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Ninguno</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($r['cliente_id'] ?? '') == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="3"><?= sanitize($r['notas'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Registro</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
