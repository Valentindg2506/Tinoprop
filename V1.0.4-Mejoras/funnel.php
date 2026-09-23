<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$funnelId = intval($_GET['id'] ?? 0);
$pasoNum = intval($_GET['paso'] ?? 1);

$funnel = $db->prepare("SELECT * FROM funnels WHERE id=? AND activo=1"); $funnel->execute([$funnelId]); $funnel=$funnel->fetch();
if (!$funnel) { http_response_code(404); echo '<h1>Funnel no encontrado</h1>'; exit; }

$paso = $db->prepare("SELECT * FROM funnel_pasos WHERE funnel_id=? AND orden=?"); $paso->execute([$funnelId, $pasoNum]); $paso=$paso->fetch();
if (!$paso) {
    $paso = $db->prepare("SELECT * FROM funnel_pasos WHERE funnel_id=? ORDER BY orden LIMIT 1"); $paso->execute([$funnelId]); $paso=$paso->fetch();
    if (!$paso) { echo '<h1>Funnel vacio</h1>'; exit; }
    $pasoNum = $paso['orden'];
}

// Comprobar consentimiento de cookies
$consentRaw  = $_COOKIE['cookie_consent'] ?? '';
$consentData = $consentRaw ? json_decode($consentRaw, true) : null;
$hasConsent  = !empty($consentData['analytics']);

// Track visit — solo establecer cookie persistente si hay consentimiento
if (session_status() === PHP_SESSION_NONE) session_start();
$visitorId = $_COOKIE['funnel_visitor'] ?? ($_SESSION['funnel_visitor'] ?? bin2hex(random_bytes(16)));
$_SESSION['funnel_visitor'] = $visitorId; // guardar siempre en sesión (cookie necesaria)
if (!isset($_COOKIE['funnel_visitor']) && $hasConsent) {
    setcookie('funnel_visitor', $visitorId, [
        'expires'  => time() + 86400 * 30,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
// También persistir ref_code si venía de sesión y ahora hay consentimiento
if ($hasConsent && empty($_COOKIE['ref_code']) && !empty($_SESSION['pending_ref_code'])) {
    setcookie('ref_code', $_SESSION['pending_ref_code'], [
        'expires'  => time() + 86400 * 30,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_SESSION['pending_ref_code']);
}

$db->prepare("UPDATE funnel_pasos SET visitas = visitas + 1 WHERE id=?")->execute([$paso['id']]);
if ($pasoNum === 1) $db->prepare("UPDATE funnels SET visitas_total = visitas_total + 1 WHERE id=?")->execute([$funnelId]);

// Check/create session
$sesion = $db->prepare("SELECT * FROM funnel_sesiones WHERE funnel_id=? AND visitor_id=? ORDER BY created_at DESC LIMIT 1");
$sesion->execute([$funnelId, $visitorId]); $sesion=$sesion->fetch();
if (!$sesion) {
    $db->prepare("INSERT INTO funnel_sesiones (funnel_id, visitor_id, paso_actual, ip, user_agent) VALUES (?,?,?,?,?)")
        ->execute([$funnelId, $visitorId, $pasoNum, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']??'']);
} else {
    $db->prepare("UPDATE funnel_sesiones SET paso_actual=?, updated_at=NOW() WHERE id=?")->execute([$pasoNum, $sesion['id']]);
}

$totalPasos = $db->prepare("SELECT COUNT(*) FROM funnel_pasos WHERE funnel_id=?"); $totalPasos->execute([$funnelId]); $totalPasos=intval($totalPasos->fetchColumn());
$siguientePaso = $pasoNum < $totalPasos ? $pasoNum + 1 : null;
$urlSiguiente = $siguientePaso ? "funnel.php?id={$funnelId}&paso={$siguientePaso}" : null;

// If conversion (going to next step), track it
if (isset($_GET['converted'])) {
    $prevPaso = $db->prepare("SELECT * FROM funnel_pasos WHERE funnel_id=? AND orden=?"); $prevPaso->execute([$funnelId, $pasoNum-1]); $prevPaso=$prevPaso->fetch();
    if ($prevPaso) {
        $db->prepare("UPDATE funnel_pasos SET conversiones = conversiones + 1 WHERE id=?")->execute([$prevPaso['id']]);
    }
    if ($pasoNum >= $totalPasos) {
        $db->prepare("UPDATE funnels SET conversiones_total = conversiones_total + 1 WHERE id=?")->execute([$funnelId]);
        if ($sesion) $db->prepare("UPDATE funnel_sesiones SET completado=1 WHERE id=?")->execute([$sesion['id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paso['nombre']) ?> - <?= htmlspecialchars($funnel['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>*{font-family:'Inter',sans-serif}body{background:#f8fafc;min-height:100vh}</style>
</head>
<body>

<?php if ($paso['tipo'] === 'landing' && $paso['landing_page_id']): ?>
    <?php
    $lp = $db->prepare("SELECT * FROM landing_pages WHERE id=?"); $lp->execute([$paso['landing_page_id']]); $lp=$lp->fetch();
    if ($lp) {
        $secciones = json_decode($lp['secciones'], true) ?: [];
        // Render landing inline (simplified)
        echo '<div class="container py-5">';
        foreach ($secciones as $s) {
            $t = $s['tipo'] ?? 'texto';
            if ($t === 'hero') {
                echo '<div class="text-center py-5"><h1 class="display-4 fw-bold">'.htmlspecialchars($s['titulo']??'').'</h1><p class="lead">'.htmlspecialchars($s['subtitulo']??'').'</p>';
                if ($urlSiguiente) echo '<a href="'.$urlSiguiente.'&converted=1" class="btn btn-primary btn-lg mt-3">'.htmlspecialchars($s['boton_texto']??'Continuar').'</a>';
                echo '</div>';
            } elseif ($t === 'texto') {
                echo '<div class="py-4">'.nl2br(htmlspecialchars($s['contenido']??'')).'</div>';
            } elseif ($t === 'cta') {
                echo '<div class="text-center py-4"><h3>'.htmlspecialchars($s['titulo']??'').'</h3>';
                if ($urlSiguiente) echo '<a href="'.$urlSiguiente.'&converted=1" class="btn btn-success btn-lg">'.htmlspecialchars($s['boton_texto']??'Siguiente').'</a>';
                echo '</div>';
            }
        }
        echo '</div>';
    }
    ?>
<?php elseif ($paso['tipo'] === 'formulario' && $paso['formulario_id']): ?>
    <div class="container py-5" style="max-width:600px">
        <h3 class="text-center mb-4"><?= htmlspecialchars($paso['nombre']) ?></h3>
        <iframe src="<?= APP_URL ?>/formulario.php?id=<?= $paso['formulario_id'] ?>&funnel=<?= $funnelId ?>&paso=<?= $pasoNum ?>" style="width:100%;min-height:600px;border:none"></iframe>
        <?php if ($urlSiguiente): ?>
        <div class="text-center mt-3"><a href="<?= $urlSiguiente ?>&converted=1" class="btn btn-primary">Continuar <i class="bi bi-arrow-right"></i></a></div>
        <?php endif; ?>
    </div>
<?php elseif ($paso['tipo'] === 'gracias'): ?>
    <div class="container py-5 text-center" style="max-width:600px">
        <i class="bi bi-check-circle text-success" style="font-size:5rem"></i>
        <h2 class="fw-bold mt-3"><?= htmlspecialchars($paso['nombre']) ?></h2>
        <?php if ($paso['contenido_html']): ?>
            <div class="mt-3"><?= $paso['contenido_html'] ?></div>
        <?php else: ?>
            <p class="text-muted mt-3">Gracias por completar este proceso.</p>
        <?php endif; ?>
    </div>
<?php elseif ($paso['tipo'] === 'upsell' || $paso['tipo'] === 'downsell'): ?>
    <div class="container py-5 text-center" style="max-width:700px">
        <h2 class="fw-bold"><?= htmlspecialchars($paso['nombre']) ?></h2>
        <?php if ($paso['contenido_html']): ?><div class="my-4"><?= $paso['contenido_html'] ?></div><?php endif; ?>
        <?php if ($urlSiguiente): ?>
        <div class="d-flex gap-3 justify-content-center mt-4">
            <a href="<?= $urlSiguiente ?>&converted=1" class="btn btn-success btn-lg">Si, quiero</a>
            <a href="<?= $urlSiguiente ?>" class="btn btn-outline-secondary btn-lg">No, gracias</a>
        </div>
        <?php endif; ?>
    </div>
<?php elseif ($paso['tipo'] === 'custom'): ?>
    <?= $paso['contenido_html'] ?>
    <?php if ($urlSiguiente): ?>
    <div class="text-center py-3"><a href="<?= $urlSiguiente ?>&converted=1" class="btn btn-primary">Siguiente</a></div>
    <?php endif; ?>
<?php endif; ?>

<!-- Banner de consentimiento de cookies (RGPD/LSSI) -->
<?php if (!$consentRaw): ?>
<div id="cookie-banner" class="position-fixed bottom-0 start-0 end-0 shadow-lg" style="z-index:9999;background:#1e293b;color:#f1f5f9;padding:.9rem 0;">
    <div class="container d-flex flex-column flex-md-row align-items-md-center gap-2">
        <p class="mb-0 small flex-grow-1">
            <i class="bi bi-cookie me-1" style="color:#fbbf24"></i>
            Usamos cookies para analizar el recorrido del visitante en este funnel y atribuir correctamente las conversiones.
            <a href="<?= defined('APP_URL') ? APP_URL : '' ?>/legal/cookies.php" target="_blank" style="color:#6ee7b7">Más información</a>.
        </p>
        <div class="d-flex gap-2 flex-shrink-0">
            <button id="btn-rechazar" class="btn btn-sm btn-outline-light">Solo necesarias</button>
            <button id="btn-aceptar" class="btn btn-sm" style="background:#10b981;color:#fff;border:none">Aceptar</button>
        </div>
    </div>
</div>
<script>
(function(){
    function setConsent(analytics){
        var d={necessary:true,analytics:analytics,timestamp:new Date().toISOString(),version:'1.0'};
        var exp=new Date(Date.now()+365*864e5).toUTCString();
        var secure=location.protocol==='https:'?';Secure':'';
        document.cookie='cookie_consent='+encodeURIComponent(JSON.stringify(d))+';expires='+exp+';path=/;SameSite=Lax'+secure;
        // Registrar en servidor
        fetch('<?= defined('APP_URL') ? APP_URL : '' ?>/api/cookie_consent.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).catch(function(){});
        document.getElementById('cookie-banner').style.display='none';
        // Si aceptó tracking y hay un ref_code pendiente en la URL, persistirlo
        if(analytics){
            var params=new URLSearchParams(location.search);
            if(params.get('ref')){
                fetch('<?= defined('APP_URL') ? APP_URL : '' ?>/ref.php?c='+encodeURIComponent(params.get('ref'))).catch(function(){});
            }
        }
    }
    document.getElementById('btn-aceptar').addEventListener('click',function(){setConsent(true);});
    document.getElementById('btn-rechazar').addEventListener('click',function(){setConsent(false);});
})();
</script>
<?php endif; ?>

<!-- Progress bar -->
<div class="fixed-bottom bg-white border-top p-2" style="z-index:<?= $consentRaw ? '100' : '9998' ?>">
    <div class="container">
        <div class="progress" style="height:6px">
            <div class="progress-bar bg-success" style="width:<?= $totalPasos > 0 ? round($pasoNum/$totalPasos*100) : 0 ?>%"></div>
        </div>
        <small class="text-muted">Paso <?= $pasoNum ?> de <?= $totalPasos ?></small>
    </div>
</div>

</body></html>
