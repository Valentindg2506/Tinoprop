<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

function contratoPlainText($value) {
    $text = (string) $value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Compatibilidad con contratos/plantillas antiguas que contenian HTML.
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\s*\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function contratoPlantillasHasPdfSupport(PDO $db): bool {
    try {
        $c1 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'tipo'");
        $c2 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'archivo_path'");
        return (bool)$c1->fetch() && (bool)$c2->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$id = intval(get('id'));
$co = null;
if ($id) { $co = $db->prepare("SELECT * FROM contratos WHERE id=?"); $co->execute([$id]); $co=$co->fetch(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $titulo = trim(post('titulo'));
    $clienteId = intval(post('cliente_id')) ?: null;
    $propiedadId = intval(post('propiedad_id')) ?: null;
    $contenido = contratoPlainText($_POST['contenido'] ?? '');
    $fechaExp = post('fecha_expiracion') ?: null;

    // Replace variables
    if ($clienteId) {
        $cl = $db->prepare("SELECT * FROM clientes WHERE id=?"); $cl->execute([$clienteId]); $cl=$cl->fetch();
        if ($cl) {
            $contenido = str_replace(['{{cliente_nombre}}','{{cliente_dni}}','{{cliente_email}}','{{cliente_telefono}}'],
                [($cl['nombre']??'').' '.($cl['apellidos']??''), $cl['dni_nie_cif']??'', $cl['email']??'', $cl['telefono']??''], $contenido);
        }
    }
    if ($propiedadId) {
        $pr = $db->prepare("SELECT * FROM propiedades WHERE id=?"); $pr->execute([$propiedadId]); $pr=$pr->fetch();
        if ($pr) {
            $contenido = str_replace(['{{propiedad_direccion}}','{{propiedad_referencia}}','{{propiedad_precio}}','{{propiedad_titulo}}'],
                [$pr['direccion']??'', $pr['referencia']??'', number_format($pr['precio']??0,2,',','.'), $pr['titulo']??''], $contenido);
        }
    }
    $config = $db->query("SELECT * FROM configuracion_pagos LIMIT 1")->fetch();
    $contenido = str_replace(['{{empresa_nombre}}','{{empresa_cif}}','{{fecha}}','{{ciudad}}'],
        [$config['empresa_nombre']??'', $config['empresa_cif']??'', date('d/m/Y'), ''], $contenido);

    if ($id) {
        $db->prepare("UPDATE contratos SET titulo=?, cliente_id=?, propiedad_id=?, contenido=?, fecha_expiracion=? WHERE id=?")
            ->execute([$titulo, $clienteId, $propiedadId, $contenido, $fechaExp, $id]);
    } else {
        $token = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO contratos (titulo, cliente_id, propiedad_id, contenido, token, fecha_expiracion, usuario_id) VALUES (?,?,?,?,?,?,?)")
            ->execute([$titulo, $clienteId, $propiedadId, $contenido, $token, $fechaExp, currentUserId()]);
    }
    setFlash('success','Contrato guardado.');
    header('Location: index.php'); exit;
}

$pageTitle = $id ? 'Editar Contrato' : 'Nuevo Contrato';
require_once __DIR__ . '/../../includes/header.php';

$clientes = $db->query("SELECT id, nombre, apellidos, dni_nie_cif, email, telefono FROM clientes WHERE activo=1 ORDER BY nombre")->fetchAll();
$propiedades = $db->query("SELECT id, titulo, referencia, direccion, precio FROM propiedades WHERE estado != 'retirado' ORDER BY titulo")->fetchAll();
$hasPdfTemplateSupport = contratoPlantillasHasPdfSupport($db);
if ($hasPdfTemplateSupport) {
    $plantillas = $db->query("SELECT * FROM contrato_plantillas WHERE activo=1 ORDER BY nombre")->fetchAll();
    $plantillasTexto = array_values(array_filter($plantillas, function ($pl) {
        return ($pl['tipo'] ?? 'texto') !== 'pdf';
    }));
    $plantillasPdf = array_values(array_filter($plantillas, function ($pl) {
        return ($pl['tipo'] ?? 'texto') === 'pdf' && !empty($pl['archivo_path']);
    }));
} else {
    $plantillasTexto = $db->query("SELECT * FROM contrato_plantillas WHERE activo=1 ORDER BY nombre")->fetchAll();
    $plantillasPdf = [];
}
$cfgEmpresa = $db->query("SELECT empresa_nombre, empresa_cif FROM configuracion_pagos LIMIT 1")->fetch();
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>
<?php if (!empty($co['token'])): ?>
<a href="<?= APP_URL ?>/contrato.php?token=<?= urlencode($co['token']) ?>&pdf=1" target="_blank" class="btn btn-outline-danger btn-sm mb-3 ms-2"><i class="bi bi-filetype-pdf"></i> Exportar PDF</a>
<a href="<?= APP_URL ?>/contrato.php?token=<?= urlencode($co['token']) ?>" target="_blank" class="btn btn-outline-dark btn-sm mb-3 ms-2"><i class="bi bi-eye"></i> Ver Contrato</a>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Titulo</label><input type="text" name="titulo" class="form-control" value="<?= sanitize($co['titulo']??'') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select"><option value="">--</option>
                    <?php foreach($clientes as $c): ?><option value="<?= $c['id'] ?>" data-nombre="<?= htmlspecialchars(($c['nombre'] ?? '').' '.($c['apellidos'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-dni="<?= htmlspecialchars($c['dni_nie_cif'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-telefono="<?= htmlspecialchars($c['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= ($co['cliente_id']??'')==$c['id']?'selected':'' ?>><?= sanitize($c['nombre'].' '.$c['apellidos']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Propiedad</label>
                    <select name="propiedad_id" class="form-select"><option value="">--</option>
                    <?php foreach($propiedades as $p): ?><option value="<?= $p['id'] ?>" data-direccion="<?= htmlspecialchars($p['direccion'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-referencia="<?= htmlspecialchars($p['referencia'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-precio="<?= htmlspecialchars(number_format($p['precio'] ?? 0,2,',','.'), ENT_QUOTES, 'UTF-8') ?>" data-titulo="<?= htmlspecialchars($p['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= ($co['propiedad_id']??'')==$p['id']?'selected':'' ?>><?= sanitize($p['referencia'].' - '.$p['titulo']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Expira</label><input type="date" name="fecha_expiracion" class="form-control" value="<?= $co['fecha_expiracion']??'' ?>"></div>
            </div>
        </div>
    </div>

    <?php if (!$id && !empty($plantillasTexto)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0">Usar plantilla de texto</h6></div>
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($plantillasTexto as $pl): ?>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('contenido').value=this.dataset.content" data-content="<?= htmlspecialchars(contratoPlainText($pl['contenido'])) ?>"><?= sanitize($pl['nombre']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$id && !empty($plantillasPdf)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0">Plantillas PDF de referencia</h6></div>
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($plantillasPdf as $pl): ?>
                <a class="btn btn-outline-danger btn-sm" href="descargar_pdf.php?id=<?= intval($pl['id']) ?>" target="_blank">
                    <i class="bi bi-filetype-pdf"></i> <?= sanitize($pl['nombre']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <small class="text-muted d-block mt-2">Las plantillas PDF se pueden abrir y usar como referencia mientras redactas el contrato en texto.</small>
        </div>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between">
            <h6 class="mb-0">Contenido del Contrato</h6>
            <small class="text-muted">Escribe en texto normal. No necesitas HTML. Variables: {{cliente_nombre}}, {{cliente_dni}}, {{propiedad_direccion}}, {{propiedad_precio}}, {{empresa_nombre}}, {{fecha}}</small>
        </div>
        <div class="card-body"><textarea name="contenido" id="contenido" class="form-control" rows="25"><?= htmlspecialchars(contratoPlainText($co['contenido']??'')) ?></textarea></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0">Vista previa en tiempo real</h6>
        </div>
        <div class="card-body">
            <div id="previewContrato" class="border rounded p-3 bg-light" style="white-space: pre-line; min-height: 220px;"></div>
        </div>
    </div>

    <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const contenido = document.getElementById('contenido');
    const preview = document.getElementById('previewContrato');
    const clienteSelect = document.querySelector('select[name="cliente_id"]');
    const propiedadSelect = document.querySelector('select[name="propiedad_id"]');

    const empresaNombre = <?= json_encode($cfgEmpresa['empresa_nombre'] ?? '') ?>;
    const empresaCif = <?= json_encode($cfgEmpresa['empresa_cif'] ?? '') ?>;
    const fechaHoy = <?= json_encode(date('d/m/Y')) ?>;

    function getSelectedData(selectEl, key) {
        if (!selectEl) return '';
        const opt = selectEl.options[selectEl.selectedIndex];
        if (!opt) return '';
        return opt.dataset[key] || '';
    }

    function renderPreview() {
        if (!contenido || !preview) return;
        let text = contenido.value || '';

        const vars = {
            '{{cliente_nombre}}': getSelectedData(clienteSelect, 'nombre'),
            '{{cliente_dni}}': getSelectedData(clienteSelect, 'dni'),
            '{{cliente_email}}': getSelectedData(clienteSelect, 'email'),
            '{{cliente_telefono}}': getSelectedData(clienteSelect, 'telefono'),
            '{{propiedad_direccion}}': getSelectedData(propiedadSelect, 'direccion'),
            '{{propiedad_referencia}}': getSelectedData(propiedadSelect, 'referencia'),
            '{{propiedad_precio}}': getSelectedData(propiedadSelect, 'precio'),
            '{{propiedad_titulo}}': getSelectedData(propiedadSelect, 'titulo'),
            '{{empresa_nombre}}': empresaNombre,
            '{{empresa_cif}}': empresaCif,
            '{{fecha}}': fechaHoy,
            '{{ciudad}}': ''
        };

        Object.keys(vars).forEach(function (k) {
            text = text.split(k).join(vars[k] || '');
        });

        preview.textContent = text || 'Empieza a escribir el contenido del contrato para ver la vista previa.';
    }

    contenido && contenido.addEventListener('input', renderPreview);
    clienteSelect && clienteSelect.addEventListener('change', renderPreview);
    propiedadSelect && propiedadSelect.addEventListener('change', renderPreview);
    renderPreview();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
