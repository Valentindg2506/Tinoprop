<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$id = intval($_GET['id'] ?? 0);
$enc = $db->prepare("SELECT * FROM encuestas WHERE id=? AND activo=1"); $enc->execute([$id]); $enc=$enc->fetch();
if (!$enc) { http_response_code(404); echo '<h1>Encuesta no encontrada</h1>'; exit; }

$preguntas = json_decode($enc['preguntas'], true) ?: [];
$enviada = false;
$puntuacionTotal = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuestas = [];
    foreach ($preguntas as $i => $p) {
        $key = 'q_'.$i;
        $val = $_POST[$key] ?? '';
        if (is_array($val)) $val = implode(', ', $val);
        $respuestas[$i] = ['pregunta' => $p['pregunta'], 'respuesta' => $val];

        // Calculate score
        if (!empty($p['puntos'])) {
            if (isset($p['puntos'][$val])) $puntuacionTotal += $p['puntos'][$val];
        }
    }

    $clienteId = null;
    if ($enc['crear_cliente']) {
        // Try to find email in responses
        foreach ($preguntas as $i => $p) {
            if ($p['tipo'] === 'email' && !empty($respuestas[$i]['respuesta'])) {
                $email = $respuestas[$i]['respuesta'];
                $existing = $db->prepare("SELECT id FROM clientes WHERE email=?"); $existing->execute([$email]); $existing=$existing->fetch();
                if ($existing) { $clienteId = $existing['id']; }
                else {
                    $db->prepare("INSERT INTO clientes (email, nombre, created_at) VALUES (?,?,NOW())")->execute([$email, $email]);
                    $clienteId = $db->lastInsertId();
                }
                break;
            }
        }
    }

    $db->prepare("INSERT INTO encuesta_respuestas (encuesta_id, cliente_id, respuestas, puntuacion, ip, completada) VALUES (?,?,?,?,?,1)")
        ->execute([$id, $clienteId, json_encode($respuestas), $puntuacionTotal, $_SERVER['REMOTE_ADDR']]);
    $db->prepare("UPDATE encuestas SET total_respuestas = total_respuestas + 1 WHERE id=?")->execute([$id]);
    $enviada = true;
}

$color = $enc['color_primario'] ?: '#10b981';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($enc['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>*{font-family:'Inter',sans-serif}body{background:#f8fafc;min-height:100vh}.enc-card{max-width:650px;margin:40px auto}.progress-enc{height:4px}.btn-option{border:2px solid #e2e8f0;border-radius:8px;padding:10px 16px;text-align:left;width:100%;margin-bottom:6px;transition:all .2s}.btn-option:hover,.btn-option.active{border-color:<?= $color ?>;background:<?= $color ?>11}</style>
</head>
<body>

<div class="enc-card">
    <?php if ($enviada): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-check-circle" style="font-size:4rem;color:<?= $color ?>"></i>
        <h3 class="mt-3 fw-bold">Gracias</h3>
        <p class="text-muted">Tu respuesta ha sido registrada.</p>
        <?php if ($puntuacionTotal > 0): ?><p>Tu puntuacion: <strong><?= $puntuacionTotal ?></strong></p><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header text-white text-center py-4" style="background:<?= $color ?>">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($enc['nombre']) ?></h4>
            <?php if ($enc['descripcion']): ?><p class="mb-0 opacity-75"><?= htmlspecialchars($enc['descripcion']) ?></p><?php endif; ?>
        </div>
        <form method="POST">
            <div class="card-body p-4">
                <?php foreach ($preguntas as $i => $p):
                    $condicion = $p['condicion_mostrar'] ?? null;
                    $dataAttr = $condicion ? 'data-show-if-q="'.($condicion['pregunta_idx']-1).'" data-show-if-val="'.htmlspecialchars($condicion['valor']).'"' : '';
                ?>
                <div class="mb-4 pregunta-wrap" <?= $dataAttr ?>>
                    <label class="form-label fw-semibold"><?= ($i+1) ?>. <?= htmlspecialchars($p['pregunta']) ?> <?= $p['requerida']?'<span class="text-danger">*</span>':'' ?></label>

                    <?php if ($p['tipo'] === 'texto'): ?>
                    <input type="text" name="q_<?= $i ?>" class="form-control" <?= $p['requerida']?'required':'' ?>>
                    <?php elseif ($p['tipo'] === 'email'): ?>
                    <input type="email" name="q_<?= $i ?>" class="form-control" <?= $p['requerida']?'required':'' ?>>
                    <?php elseif ($p['tipo'] === 'telefono'): ?>
                    <input type="tel" name="q_<?= $i ?>" class="form-control" <?= $p['requerida']?'required':'' ?>>
                    <?php elseif ($p['tipo'] === 'fecha'): ?>
                    <input type="date" name="q_<?= $i ?>" class="form-control" <?= $p['requerida']?'required':'' ?>>
                    <?php elseif ($p['tipo'] === 'si_no'): ?>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-option flex-fill" onclick="this.parentNode.querySelectorAll('.btn-option').forEach(b=>b.classList.remove('active'));this.classList.add('active');this.parentNode.querySelector('input').value='Si'">Si</button>
                        <button type="button" class="btn btn-option flex-fill" onclick="this.parentNode.querySelectorAll('.btn-option').forEach(b=>b.classList.remove('active'));this.classList.add('active');this.parentNode.querySelector('input').value='No'">No</button>
                        <input type="hidden" name="q_<?= $i ?>" <?= $p['requerida']?'required':'' ?>>
                    </div>
                    <?php elseif ($p['tipo'] === 'opcion_unica'): ?>
                    <?php foreach (($p['opciones']??[]) as $opt): ?>
                    <div class="form-check"><input type="radio" name="q_<?= $i ?>" value="<?= htmlspecialchars($opt) ?>" class="form-check-input" <?= $p['requerida']?'required':'' ?>><label class="form-check-label"><?= htmlspecialchars($opt) ?></label></div>
                    <?php endforeach; ?>
                    <?php elseif ($p['tipo'] === 'opcion_multiple'): ?>
                    <?php foreach (($p['opciones']??[]) as $opt): ?>
                    <div class="form-check"><input type="checkbox" name="q_<?= $i ?>[]" value="<?= htmlspecialchars($opt) ?>" class="form-check-input"><label class="form-check-label"><?= htmlspecialchars($opt) ?></label></div>
                    <?php endforeach; ?>
                    <?php elseif ($p['tipo'] === 'escala' || $p['tipo'] === 'nps'): ?>
                    <?php $max = $p['tipo']==='nps'?10:10; $min = $p['tipo']==='nps'?0:1; ?>
                    <div class="d-flex gap-1 flex-wrap">
                        <?php for ($n=$min;$n<=$max;$n++): ?>
                        <button type="button" class="btn btn-option text-center" style="width:42px;padding:8px 4px" onclick="this.parentNode.querySelectorAll('.btn-option').forEach(b=>b.classList.remove('active'));this.classList.add('active');this.parentNode.querySelector('input[type=hidden]').value='<?= $n ?>'"><?= $n ?></button>
                        <?php endfor; ?>
                        <input type="hidden" name="q_<?= $i ?>" <?= $p['requerida']?'required':'' ?>>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-white text-center p-4">
                <button type="submit" class="btn btn-lg text-white px-5" style="background:<?= $color ?>">Enviar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// Conditional logic
document.querySelectorAll('.pregunta-wrap[data-show-if-q]').forEach(el => {
    el.style.display = 'none';
});
document.querySelectorAll('input, select').forEach(input => {
    input.addEventListener('change', function() {
        document.querySelectorAll('.pregunta-wrap[data-show-if-q]').forEach(el => {
            const qi = el.dataset.showIfQ;
            const val = el.dataset.showIfVal;
            const inputs = document.querySelectorAll('[name="q_'+qi+'"]');
            let currentVal = '';
            inputs.forEach(inp => { if(inp.checked || inp.type==='hidden') currentVal = inp.value; });
            el.style.display = currentVal === val ? '' : 'none';
        });
    });
});
</script>
</body></html>
