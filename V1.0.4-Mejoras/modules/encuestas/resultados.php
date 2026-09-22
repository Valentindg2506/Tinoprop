<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

$id = intval(get('id'));
$enc = $db->prepare("SELECT * FROM encuestas WHERE id=?"); $enc->execute([$id]); $enc=$enc->fetch();
if (!$enc) { setFlash('danger','No encontrada.'); header('Location: index.php'); exit; }

$pageTitle = 'Resultados: ' . $enc['nombre'];
require_once __DIR__ . '/../../includes/header.php';

$preguntas = json_decode($enc['preguntas'], true) ?: [];
$respuestas = $db->prepare("SELECT * FROM encuesta_respuestas WHERE encuesta_id=? ORDER BY created_at DESC");
$respuestas->execute([$id]); $respuestas=$respuestas->fetchAll();

// Calculate stats per question
$stats = [];
foreach ($preguntas as $i => $p) {
    $vals = [];
    foreach ($respuestas as $r) {
        $rd = json_decode($r['respuestas'], true) ?: [];
        if (isset($rd[$i])) $vals[] = $rd[$i]['respuesta'];
    }
    $stats[$i] = ['total' => count($vals), 'values' => $vals];
    if (in_array($p['tipo'], ['opcion_unica','opcion_multiple','si_no'])) {
        $counts = array_count_values(array_filter($vals));
        arsort($counts);
        $stats[$i]['counts'] = $counts;
    }
    if (in_array($p['tipo'], ['escala','nps'])) {
        $numVals = array_filter(array_map('floatval', $vals));
        $stats[$i]['promedio'] = $numVals ? round(array_sum($numVals)/count($numVals), 1) : 0;
    }
}

$avgScore = $respuestas ? round(array_sum(array_column($respuestas, 'puntuacion'))/count($respuestas), 1) : 0;
?>

<a href="index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Volver</a>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= count($respuestas) ?></h3><small class="text-muted">Respuestas</small></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= $avgScore ?></h3><small class="text-muted">Puntuacion promedio</small></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm text-center p-3"><h3 class="fw-bold mb-0"><?= count($preguntas) ?></h3><small class="text-muted">Preguntas</small></div></div>
</div>

<?php foreach ($preguntas as $i => $p): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="fw-bold"><?= ($i+1) ?>. <?= sanitize($p['pregunta']) ?></h6>
        <small class="text-muted"><?= $stats[$i]['total'] ?> respuestas</small>

        <?php if (isset($stats[$i]['counts'])): ?>
        <div class="mt-2">
            <?php foreach ($stats[$i]['counts'] as $opt => $cnt):
                $pct = $stats[$i]['total'] > 0 ? round($cnt/$stats[$i]['total']*100) : 0;
            ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="small" style="min-width:100px"><?= sanitize($opt) ?></span>
                <div class="progress flex-grow-1" style="height:20px"><div class="progress-bar" style="width:<?= $pct ?>%;background:#10b981"><?= $cnt ?> (<?= $pct ?>%)</div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif (isset($stats[$i]['promedio'])): ?>
        <div class="mt-2"><span class="fs-4 fw-bold" style="color:#10b981"><?= $stats[$i]['promedio'] ?></span> <span class="text-muted">/ 10 promedio</span></div>
        <?php else: ?>
        <div class="mt-2">
            <?php foreach (array_slice($stats[$i]['values'], 0, 5) as $v): ?>
            <div class="small border-bottom py-1"><?= sanitize($v) ?></div>
            <?php endforeach; ?>
            <?php if (count($stats[$i]['values']) > 5): ?><small class="text-muted">+<?= count($stats[$i]['values'])-5 ?> mas</small><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
