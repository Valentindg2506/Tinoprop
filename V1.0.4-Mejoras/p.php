<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { http_response_code(404); echo '<h1>Pagina no encontrada</h1>'; exit; }

$stmt = $db->prepare("SELECT * FROM landing_pages WHERE slug = ? AND activa = 1");
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) { http_response_code(404); echo '<h1>Pagina no encontrada</h1>'; exit; }

$db->prepare("UPDATE landing_pages SET visitas = visitas + 1 WHERE id = ?")->execute([$page['id']]);

$secciones = json_decode($page['secciones'], true) ?: [];
$color = $page['color_primario'] ?? '#10b981';
$fondo = $page['color_fondo'] ?? '#ffffff';
$titulo = htmlspecialchars($page['meta_titulo'] ?: $page['titulo']);
$desc = htmlspecialchars($page['meta_descripcion'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?></title>
    <?php if ($desc): ?><meta name="description" content="<?= $desc ?>"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --lp-color: <?= $color ?>; --lp-bg: <?= $fondo ?>; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--lp-bg); }
        .lp-hero { position: relative; padding: 100px 0; color: #fff; text-align: center; background-size: cover; background-position: center; }
        .lp-hero-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
        .lp-hero-content { position: relative; z-index: 1; }
        .lp-hero h1 { font-size: 3rem; font-weight: 800; margin-bottom: 1rem; }
        .lp-hero p { font-size: 1.2rem; opacity: 0.9; }
        .lp-btn { background: var(--lp-color); border: none; color: #fff; padding: 14px 36px; border-radius: 8px; font-weight: 600; font-size: 1.1rem; text-decoration: none; display: inline-block; }
        .lp-btn:hover { filter: brightness(0.9); color: #fff; }
        .lp-features { padding: 60px 0; }
        .lp-feature-item { text-align: center; padding: 20px; }
        .lp-feature-item i { font-size: 2.5rem; color: var(--lp-color); margin-bottom: 1rem; display: block; }
        .lp-cta { padding: 60px 0; text-align: center; color: #fff; }
        .lp-section { padding: 40px 0; }
        .lp-contacto i { color: var(--lp-color); }
        <?= $page['custom_css'] ?? '' ?>
    </style>
</head>
<body>

<?php foreach ($secciones as $sec):
    $cfg = $sec['config'] ?? [];
    switch ($sec['type']):
        case 'hero': ?>
            <section class="lp-hero" style="<?= !empty($cfg['imagen_fondo_url']) ? 'background-image:url('.htmlspecialchars($cfg['imagen_fondo_url']).')' : 'background:var(--lp-color)' ?>">
                <div class="lp-hero-overlay" style="background:rgba(0,0,0,<?= $cfg['overlay_opacity'] ?? 0.5 ?>)"></div>
                <div class="container lp-hero-content">
                    <h1><?= htmlspecialchars($cfg['titulo'] ?? '') ?></h1>
                    <p><?= htmlspecialchars($cfg['subtitulo'] ?? '') ?></p>
                    <?php if (!empty($cfg['texto_boton'])): ?>
                    <a href="<?= htmlspecialchars($cfg['enlace_boton'] ?? '#') ?>" class="lp-btn mt-3"><?= htmlspecialchars($cfg['texto_boton']) ?></a>
                    <?php endif; ?>
                </div>
            </section>
        <?php break;

        case 'texto': ?>
            <section class="lp-section">
                <div class="container" style="max-width:800px;text-align:<?= $cfg['alineacion'] ?? 'centro' ?>">
                    <?= $cfg['contenido'] ?? '' ?>
                </div>
            </section>
        <?php break;

        case 'caracteristicas':
            $items = $cfg['items'] ?? []; ?>
            <section class="lp-features">
                <div class="container">
                    <div class="row g-4">
                        <?php foreach ($items as $item): ?>
                        <div class="col-md-4">
                            <div class="lp-feature-item">
                                <i class="bi <?= htmlspecialchars($item['icono'] ?? 'bi-star') ?>"></i>
                                <h5 class="fw-bold"><?= htmlspecialchars($item['titulo'] ?? '') ?></h5>
                                <p class="text-muted"><?= htmlspecialchars($item['descripcion'] ?? '') ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php break;

        case 'cta': ?>
            <section class="lp-cta" style="background:<?= htmlspecialchars($cfg['color_fondo'] ?? $color) ?>">
                <div class="container">
                    <h2 class="fw-bold mb-3"><?= htmlspecialchars($cfg['titulo'] ?? '') ?></h2>
                    <p class="mb-4 opacity-75"><?= htmlspecialchars($cfg['descripcion'] ?? '') ?></p>
                    <a href="<?= htmlspecialchars($cfg['enlace_boton'] ?? '#') ?>" class="lp-btn" style="background:#fff;color:<?= htmlspecialchars($cfg['color_fondo'] ?? $color) ?>"><?= htmlspecialchars($cfg['texto_boton'] ?? 'Contactar') ?></a>
                </div>
            </section>
        <?php break;

        case 'imagen': ?>
            <section class="lp-section">
                <div class="container text-center">
                    <img src="<?= htmlspecialchars($cfg['imagen_url'] ?? '') ?>" alt="<?= htmlspecialchars($cfg['alt_text'] ?? '') ?>" style="max-width:<?= htmlspecialchars($cfg['ancho'] ?? '100%') ?>;height:auto;border-radius:12px">
                </div>
            </section>
        <?php break;

        case 'contacto': ?>
            <section class="lp-section" id="contacto">
                <div class="container" style="max-width:600px">
                    <h3 class="text-center fw-bold mb-4">Contacto</h3>
                    <div class="row g-3 text-center">
                        <?php if (!empty($cfg['telefono'])): ?><div class="col-md-4 lp-contacto"><i class="bi bi-telephone fs-3 d-block mb-2"></i><a href="tel:<?= htmlspecialchars($cfg['telefono']) ?>"><?= htmlspecialchars($cfg['telefono']) ?></a></div><?php endif; ?>
                        <?php if (!empty($cfg['email'])): ?><div class="col-md-4 lp-contacto"><i class="bi bi-envelope fs-3 d-block mb-2"></i><a href="mailto:<?= htmlspecialchars($cfg['email']) ?>"><?= htmlspecialchars($cfg['email']) ?></a></div><?php endif; ?>
                        <?php if (!empty($cfg['direccion'])): ?><div class="col-md-4 lp-contacto"><i class="bi bi-geo-alt fs-3 d-block mb-2"></i><?= htmlspecialchars($cfg['direccion']) ?></div><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php break;

        case 'separador': ?>
            <div style="height:<?= intval($cfg['altura'] ?? 40) ?>px"></div>
        <?php break;
    endswitch;
endforeach; ?>

<footer style="text-align:center;padding:2rem;color:#94a3b8;font-size:0.85rem">
    Powered by Tinoprop
</footer>

</body>
</html>
