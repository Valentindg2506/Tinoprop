<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(404); echo '<h1>No encontrado</h1>'; exit; }
$pdfMode = isset($_GET['pdf']) && $_GET['pdf'] === '1';

$co = $db->prepare("SELECT co.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos FROM contratos co LEFT JOIN clientes c ON co.cliente_id=c.id WHERE co.token=?");
$co->execute([$token]); $co=$co->fetch();
if (!$co) { http_response_code(404); echo '<h1>Contrato no encontrado</h1>'; exit; }

function contratoRenderText($value) {
    $text = (string) $value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    // Compatibilidad con contratos antiguos en HTML.
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\s*\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

$contenidoContrato = contratoRenderText($co['contenido'] ?? '');

if ($co['estado'] === 'enviado') {
    $db->prepare("UPDATE contratos SET estado='visto' WHERE id=?")->execute([$co['id']]);
    $co['estado'] = 'visto';
}

// Sign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firma'])) {
    $firma = $_POST['firma'];
    $nombre = trim($_POST['firmante_nombre'] ?? '');
    if ($firma && in_array($co['estado'], ['enviado','visto'])) {
        $db->prepare("UPDATE contratos SET estado='firmado', firma_imagen=?, firmante_nombre=?, firmado_at=NOW(), firmado_ip=? WHERE id=?")
            ->execute([$firma, $nombre, $_SERVER['REMOTE_ADDR'], $co['id']]);
        header('Location: contrato.php?token='.$token.'&firmado=1'); exit;
    }
}

$firmado = isset($_GET['firmado']) || $co['estado'] === 'firmado';
$config = $db->query("SELECT empresa_nombre FROM configuracion_pagos LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato - <?= htmlspecialchars($co['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>*{font-family:'Inter',sans-serif}body{background:#f8fafc}
    #signPad{border:2px dashed #cbd5e1;border-radius:8px;cursor:crosshair}
    .pdf-help{font-size:.85rem;color:#64748b}
    @page{size:A4;margin:16mm}
    @media print{.no-print{display:none!important}}</style>
</head><body>

<div class="container py-4" style="max-width:850px">

    <?php if ($pdfMode): ?>
    <div class="alert alert-info no-print">
        <strong>Exportando a PDF:</strong> Se abrirá el diálogo de impresión. Elige "Guardar como PDF".
    </div>
    <?php endif; ?>

    <?php if ($firmado): ?>
    <div class="card border-0 shadow-sm text-center p-5 mb-4">
        <i class="bi bi-check-circle text-success" style="font-size:4rem"></i>
        <h3 class="mt-3 fw-bold">Contrato Firmado</h3>
        <p class="text-muted">Firmado por <?= htmlspecialchars($co['firmante_nombre']) ?> el <?= $co['firmado_at']?date('d/m/Y H:i',strtotime($co['firmado_at'])):date('d/m/Y H:i') ?></p>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white text-center py-3">
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($config['empresa_nombre']??'') ?></h5>
            <small class="text-muted"><?= htmlspecialchars($co['titulo']) ?></small>
        </div>
        <div class="card-body p-4 p-md-5" style="line-height:1.8; white-space: pre-line;">
            <?= htmlspecialchars($contenidoContrato, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php if ($co['firma_imagen']): ?>
        <div class="card-footer bg-white p-4">
            <h6>Firma:</h6>
            <img src="<?= htmlspecialchars($co['firma_imagen'] ?? '') ?>" style="max-height:100px">
            <p class="small text-muted mt-1"><?= htmlspecialchars($co['firmante_nombre']) ?> - <?= $co['firmado_at']?date('d/m/Y H:i',strtotime($co['firmado_at'])):'' ?> - IP: <?= htmlspecialchars($co['firmado_ip']??'') ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php if (in_array($co['estado'], ['enviado','visto'])): ?>
    <div class="card border-0 shadow-sm no-print">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Firmar contrato</h5>
            <form method="POST">
                <div class="mb-3"><label class="form-label">Nombre completo</label><input type="text" name="firmante_nombre" class="form-control" required></div>
                <div class="mb-3">
                    <label class="form-label">Firma</label>
                    <canvas id="signPad" width="500" height="150" style="display:block;width:100%;max-width:500px"></canvas>
                    <input type="hidden" name="firma" id="firmaInput">
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="clearSignature()">Borrar firma</button>
                </div>
                <button type="submit" class="btn btn-success btn-lg" onclick="document.getElementById('firmaInput').value=canvas.toDataURL()"><i class="bi bi-pen"></i> Firmar Contrato</button>
            </form>
        </div>
    </div>

    <script>
    const canvas = document.getElementById('signPad');
    const ctx = canvas.getContext('2d');
    let drawing = false;
    ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1e293b';

    canvas.addEventListener('mousedown', e => { drawing=true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); });
    canvas.addEventListener('mousemove', e => { if(!drawing) return; ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); });
    canvas.addEventListener('mouseup', () => drawing=false);
    canvas.addEventListener('mouseleave', () => drawing=false);
    // Touch
    canvas.addEventListener('touchstart', e => { e.preventDefault(); const t=e.touches[0]; const r=canvas.getBoundingClientRect(); drawing=true; ctx.beginPath(); ctx.moveTo(t.clientX-r.left, t.clientY-r.top); });
    canvas.addEventListener('touchmove', e => { e.preventDefault(); if(!drawing) return; const t=e.touches[0]; const r=canvas.getBoundingClientRect(); ctx.lineTo(t.clientX-r.left, t.clientY-r.top); ctx.stroke(); });
    canvas.addEventListener('touchend', () => drawing=false);

    function clearSignature() { ctx.clearRect(0,0,canvas.width,canvas.height); }
    </script>
    <?php endif; ?>

    <div class="text-center mt-3 no-print d-flex justify-content-center gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
        <a class="btn btn-outline-danger btn-sm" href="contrato.php?token=<?= urlencode($token) ?>&pdf=1"><i class="bi bi-filetype-pdf"></i> Exportar PDF</a>
    </div>
    <div class="text-center mt-2 no-print pdf-help">Para PDF en escritorio: en la impresora selecciona "Guardar como PDF".</div>
</div>
<?php if ($pdfMode): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        window.print();
    }, 250);
});
</script>
<?php endif; ?>
</body></html>
