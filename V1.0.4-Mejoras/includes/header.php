<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ajustes_helper.php';
require_once __DIR__ . '/lang.php';
requireLogin();
ob_start();

// Contar notificaciones no leidas
$db = getDB();
generarNotificacionesProspectosVencidos(currentUserId());
$stmtNotif = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
$stmtNotif->execute([currentUserId()]);
$notifCount = $stmtNotif->fetchColumn();

$userSettings = [];
try {
    $userSettings = getUserSettings();
} catch (Exception $e) {
    logError('No se pudieron cargar ajustes de usuario', ['error' => $e->getMessage()]);
}

// Load user language
$_userLang = in_array($userSettings['idioma'] ?? 'es', ['es', 'en']) ? ($userSettings['idioma'] ?? 'es') : 'es';
loadLang($_userLang);

// Load whitelabel config (colors, branding)
$_wl = null;
try {
    $_wl = $db->query("SELECT * FROM whitelabel_config WHERE id=1")->fetch();
} catch (Exception $e) {
    error_log($e->getMessage());
}

$_appName = $_wl['app_nombre'] ?? APP_NAME;
$_colorPrimary = $_wl['color_primario'] ?? '#10b981';
$_colorSecondary = $_wl['color_secundario'] ?? '#1e293b';
$_colorAccent = $_wl['color_acento'] ?? '#f59e0b';
$_logoUrl = $_wl['app_logo_url'] ?? '';
$_faviconUrl = $_wl['app_favicon_url'] ?? '';
$_customCss = $_wl['css_custom'] ?? '';
$_appIconUrl = $_faviconUrl ?: (APP_URL . '/assets/favicons/favicon_64x64.png');

$_userTheme = ($userSettings['tema'] ?? 'claro') === 'oscuro' ? 'dark' : 'light';
$_userSidebarCompacta = ($userSettings['sidebar_compacta'] ?? '0') === '1';

$_colorKeys = [
    'emerald' => '#10b981',
    'blue' => '#3b82f6',
    'purple' => '#8b5cf6',
    'orange' => '#f97316',
    'rose' => '#f43f5e',
    'cyan' => '#06b6d4',
];

$_userColorKey = $userSettings['color_primario'] ?? '';
if (isset($_colorKeys[$_userColorKey])) {
    $_colorPrimary = $_colorKeys[$_userColorKey];
}

// Compute color variants from primary
function hexToHsl($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex,0,2))/255;
    $g = hexdec(substr($hex,2,2))/255;
    $b = hexdec(substr($hex,4,2))/255;
    $max = max($r,$g,$b); $min = min($r,$g,$b);
    $l = ($max+$min)/2;
    if ($max == $min) { $h = $s = 0; }
    else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d/(2-$max-$min) : $d/($max+$min);
        if ($max == $r) $h = ($g-$b)/$d + ($g < $b ? 6 : 0);
        elseif ($max == $g) $h = ($b-$r)/$d + 2;
        else $h = ($r-$g)/$d + 4;
        $h /= 6;
    }
    return [round($h*360), round($s*100), round($l*100)];
}

list($ph, $ps, $pl) = hexToHsl($_colorPrimary);
$_primaryDarkHex = $_colorPrimary; // fallback
// Make a darker variant
$pdl = max(0, $pl - 10);
$_primaryLightRgba = "rgba(" . hexdec(substr($_colorPrimary,1,2)) . "," . hexdec(substr($_colorPrimary,3,2)) . "," . hexdec(substr($_colorPrimary,5,2)) . ",0.12)";

// Determinar pagina activa
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentPath = $_SERVER['PHP_SELF'];
$isDashboard = ($currentPath === APP_URL . '/index.php' || $currentPath === '/index.php' || !preg_match('/modules\//', $currentPath));
$allowedModulesForMenu = getAllowedModulesForCurrentUser();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($_userLang) ?>" data-bs-theme="<?= $_userTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?> - <?= htmlspecialchars($_appName) ?></title>
    <?php if ($_faviconUrl): ?>
        <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($_faviconUrl) ?>">
        <link rel="apple-touch-icon" href="<?= htmlspecialchars($_faviconUrl) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon_32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon_16x16.png">
        <link rel="icon" type="image/x-icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico">
        <link rel="icon" type="image/png" sizes="48x48" href="<?= APP_URL ?>/assets/favicons/favicon_48x48.png">
        <link rel="icon" type="image/png" sizes="64x64" href="<?= APP_URL ?>/assets/favicons/favicon_64x64.png">
        <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/favicon_180x180.png">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= htmlspecialchars($_colorPrimary) ?>;
            --primary-dark: <?= htmlspecialchars($_colorPrimary) ?>;
            --primary-light: <?= $_primaryLightRgba ?>;
            --accent: <?= htmlspecialchars($_colorAccent) ?>;
            --sidebar-active: <?= htmlspecialchars($_colorPrimary) ?>;
            --sidebar-hover: rgba(<?= hexdec(substr($_colorPrimary,1,2)) ?>,<?= hexdec(substr($_colorPrimary,3,2)) ?>,<?= hexdec(substr($_colorPrimary,5,2)) ?>,0.08);
        }
        .btn-primary { background: var(--primary) !important; border-color: var(--primary) !important; }
        .btn-primary:hover { filter: brightness(0.9); box-shadow: 0 4px 12px <?= $_primaryLightRgba ?>; }
        .btn-outline-primary { color: var(--primary) !important; border-color: var(--primary) !important; }
        .btn-outline-primary:hover { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; }
        .badge.bg-primary { background: var(--primary) !important; }
        .text-primary { color: var(--primary) !important; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px <?= $_primaryLightRgba ?>; }
        a { color: var(--primary); }
        a:hover { color: var(--primary); filter: brightness(0.85); }
        .notif-menu { width: 360px; max-width: 92vw; padding: 0; }
        .notif-header { display: flex; justify-content: space-between; align-items: center; padding: .65rem .85rem; border-bottom: 1px solid rgba(0,0,0,.06); }
        .notif-list { max-height: 360px; overflow-y: auto; }
        .notif-item { display: block; padding: .65rem .85rem; border-bottom: 1px solid rgba(0,0,0,.04); text-decoration: none; color: inherit; }
        .notif-item:last-child { border-bottom: 0; }
        .notif-item.unread { background: rgba(16,185,129,.08); }
        .notif-title { font-size: .875rem; line-height: 1.35; }
        .notif-time { font-size: .73rem; color: #6c757d; }
        .notif-type-chip { font-size: .68rem; padding: 2px 7px; border-radius: 999px; margin-left: 6px; }
        .notif-empty { padding: 1rem; text-align: center; color: #6c757d; }
        .notif-badge-dot { display:inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; margin-right: 6px; vertical-align: middle; }
        [data-bs-theme="dark"] .notif-header { border-bottom-color: rgba(255,255,255,.08); }
        [data-bs-theme="dark"] .notif-item { border-bottom-color: rgba(255,255,255,.06); }
        [data-bs-theme="dark"] .notif-item.unread { background: rgba(16,185,129,.15); }
        <?= strip_tags(str_replace(['</style', '<script', '<link', '@import'], '', $_customCss)) ?>
    </style>
    <script>
        // Dark mode: apply before render to prevent flash
        (function(){
            const serverTheme = '<?= $_userTheme ?>';
            document.documentElement.setAttribute('data-bs-theme', serverTheme);
            localStorage.setItem('theme', serverTheme);
        })();

        // Hide sidebar links for custom roles with module restrictions.
        (function () {
            const allowed = <?= json_encode($allowedModulesForMenu, JSON_UNESCAPED_UNICODE) ?>;
            if (!allowed || typeof allowed !== 'object') {
                return;
            }

            document.addEventListener('DOMContentLoaded', function () {
                const nav = document.querySelector('.sidebar-nav');
                if (!nav) return;
                nav.querySelectorAll('a.nav-link').forEach(function (a) {
                    const href = a.getAttribute('href') || '';
                    const m = href.match(/\/modules\/([^/]+)\//);
                    if (!m) return;
                    const moduleKey = m[1];
                    if (!allowed[moduleKey]) {
                        a.style.display = 'none';
                    }
                });
            });
        })();
    </script>
</head>
<body class="<?= $_userSidebarCompacta ? 'sidebar-compact' : '' ?>">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= htmlspecialchars($_appIconUrl) ?>" alt="Icono app" class="sidebar-app-icon" width="28" height="28">
            <?php if ($_logoUrl): ?>
                <img src="<?= htmlspecialchars($_logoUrl) ?>" alt="Logo" class="sidebar-logo">
            <?php else: ?>
                <h4><img src="<?= htmlspecialchars($_appIconUrl) ?>" alt="" class="sidebar-title-icon" width="18" height="18"> <?= htmlspecialchars($_appName) ?></h4>
            <?php endif; ?>
            <small class="sidebar-brand-subtitle"><?= __('topbar.software') ?></small>
        </div>
        <nav class="sidebar-nav">
            <a href="<?= APP_URL ?>/index.php" class="nav-link <?= $isDashboard && $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> <?= __('nav.dashboard') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/propiedades/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'propiedades') !== false ? 'active' : '' ?>">
                <i class="bi bi-house-door"></i> <?= __('nav.propiedades') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/clientes/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'clientes') !== false ? 'active' : '' ?>">
                <i class="bi bi-people"></i> <?= __('nav.clientes') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/prospectos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'prospectos') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-plus"></i> <?= __('nav.prospectos') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/visitas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'visitas') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar-event"></i> <?= __('nav.visitas') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/tareas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'tareas') !== false ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> <?= __('nav.tareas') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/documentos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'documentos') !== false ? 'active' : '' ?>">
                <i class="bi bi-folder"></i> <?= __('nav.documentos') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/finanzas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'finanzas') !== false ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i> <?= __('nav.finanzas') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/portales/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'portales') !== false ? 'active' : '' ?>">
                <i class="bi bi-globe"></i> <?= __('nav.portales') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/informes/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'informes') !== false ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> <?= __('nav.informes') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/pipelines/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pipelines') !== false ? 'active' : '' ?>">
                <i class="bi bi-kanban"></i> <?= __('nav.pipelines') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/calendario/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'calendario') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i> <?= __('nav.calendario') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/pagos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pagos') !== false ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> <?= __('nav.facturacion') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/presupuestos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'presupuestos') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> <?= __('nav.presupuestos') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/contratos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'contratos') !== false ? 'active' : '' ?>">
                <i class="bi bi-pen"></i> <?= __('nav.contratos') ?>
            </a>
            <hr class="mx-3 my-2">
            <small class="sidebar-section-title"><?= __('nav.section.marketing') ?></small>
            <a href="<?= APP_URL ?>/modules/marketing/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/marketing/') !== false ? 'active' : '' ?>">
                <i class="bi bi-megaphone"></i> <?= __('nav.marketing') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/formularios/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'formularios') !== false ? 'active' : '' ?>">
                <i class="bi bi-ui-checks-grid"></i> <?= __('nav.formularios') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/encuestas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'encuestas') !== false ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-data"></i> <?= __('nav.encuestas') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/funnels/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'funnels') !== false ? 'active' : '' ?>">
                <i class="bi bi-funnel"></i> <?= __('nav.funnels') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/landing/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/landing/') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-richtext"></i> <?= __('nav.landing') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/campanas/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'campanas') !== false ? 'active' : '' ?>">
                <i class="bi bi-send"></i> <?= __('nav.campanas') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/ab-testing/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'ab-testing') !== false ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i> <?= __('nav.ab_testing') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/ads/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/ads/') !== false ? 'active' : '' ?>">
                <i class="bi bi-badge-ad"></i> <?= __('nav.ads') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/social/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/social/') !== false ? 'active' : '' ?>">
                <i class="bi bi-share"></i> <?= __('nav.redes_sociales') ?>
            </a>
            <hr class="mx-3 my-2">
            <small class="sidebar-section-title"><?= __('nav.section.comunicacion') ?></small>
            <a href="<?= APP_URL ?>/modules/secuencia_captacion/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'secuencia_captacion') !== false ? 'active' : '' ?>">
                <i class="bi bi-chat-dots"></i> <?= __('nav.secuencia') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/email/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/email/') !== false ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i> <?= __('nav.email') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/whatsapp/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'whatsapp') !== false ? 'active' : '' ?>">
                <i class="bi bi-whatsapp"></i> <?= __('nav.whatsapp') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/sms/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/sms/') !== false ? 'active' : '' ?>">
                <i class="bi bi-phone"></i> <?= __('nav.sms') ?>
            </a>
            <hr class="mx-3 my-2">
            <small class="sidebar-section-title"><?= __('nav.section.contenido') ?></small>
            <a href="<?= APP_URL ?>/modules/blog/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/blog/') !== false ? 'active' : '' ?>">
                <i class="bi bi-journal-richtext"></i> <?= __('nav.blog') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/cursos/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/cursos/') !== false ? 'active' : '' ?>">
                <i class="bi bi-mortarboard"></i> <?= __('nav.cursos') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/medios/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/medios/') !== false ? 'active' : '' ?>">
                <i class="bi bi-images"></i> <?= __('nav.medios') ?>
            </a>
            <hr class="mx-3 my-2">
            <small class="sidebar-section-title"><?= __('nav.section.sistema') ?></small>
            <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'automatizaciones') !== false ? 'active' : '' ?>">
                <i class="bi bi-robot"></i> <?= __('nav.automatizaciones') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/ia/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/ia/') !== false ? 'active' : '' ?>">
                <i class="bi bi-cpu"></i> <?= __('nav.ia') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/afiliados/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'afiliados') !== false ? 'active' : '' ?>">
                <i class="bi bi-link-45deg"></i> <?= __('nav.afiliados') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/ajustes/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'ajustes') !== false ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i> <?= __('nav.ajustes') ?>
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= APP_URL ?>/modules/usuarios/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'usuarios') !== false && strpos($_SERVER['PHP_SELF'], 'backup') === false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> <?= __('nav.usuarios') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/usuarios/roles.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'usuarios/roles') !== false ? 'active' : '' ?>">
                <i class="bi bi-shield-lock"></i> <?= __('nav.roles') ?>
            </a>
            <a href="<?= APP_URL ?>/modules/usuarios/backup.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'backup') !== false ? 'active' : '' ?>">
                <i class="bi bi-database-down"></i> <?= __('nav.backups') ?>
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link me-3 d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h5 class="mb-0 page-title"><?= $pageTitle ?? 'Dashboard' ?></h5>
            </div>
            <div class="d-flex align-items-center gap-2">
                <!-- Dark mode toggle -->
                <button class="btn btn-link btn-sm" id="themeToggle" title="<?= __('topbar.change_theme') ?>">
                    <i class="bi bi-moon-stars fs-5" id="themeIcon"></i>
                </button>
                <!-- Notificaciones -->
                <div class="dropdown">
                    <button class="btn btn-link position-relative" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $notifCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-menu">
                        <div class="notif-header">
                            <h6 class="mb-0"><?= __('topbar.notifications') ?></h6>
                            <?php if ($notifCount > 0): ?>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="markAllNotifBtn"><?= __('topbar.mark_all') ?></button>
                            <?php endif; ?>
                        </div>
                        <?php
                        $stmtN = $db->prepare("SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 8");
                        $stmtN->execute([currentUserId()]);
                        $notifs = $stmtN->fetchAll();
                        if (empty($notifs)):
                        ?>
                        <div class="notif-empty"><?= __('topbar.no_notifications') ?></div>
                        <?php else:
                        ?>
                        <div class="notif-list">
                        <?php foreach ($notifs as $notif): ?>
                        <?php
                            $notifTitleLc = mb_strtolower((string)($notif['titulo'] ?? ''), 'UTF-8');
                            $notifLinkLc = mb_strtolower((string)($notif['enlace'] ?? ''), 'UTF-8');
                            $notifTypeLabel = __('notif.general');
                            $notifTypeIcon = 'bi-bell';
                            $notifTypeClass = 'bg-secondary-subtle text-secondary-emphasis';

                            if (strpos($notifLinkLc, 'contrato') !== false || strpos($notifTitleLc, 'contrato') !== false) {
                                $notifTypeLabel = __('notif.contract');
                                $notifTypeIcon = 'bi-file-earmark-text';
                                $notifTypeClass = 'bg-primary-subtle text-primary-emphasis';
                            } elseif (strpos($notifLinkLc, 'tareas') !== false || strpos($notifTitleLc, 'tarea') !== false) {
                                $notifTypeLabel = __('notif.task');
                                $notifTypeIcon = 'bi-check2-square';
                                $notifTypeClass = 'bg-warning-subtle text-warning-emphasis';
                            } elseif (strpos($notifLinkLc, 'visitas') !== false || strpos($notifTitleLc, 'visita') !== false) {
                                $notifTypeLabel = __('notif.visit');
                                $notifTypeIcon = 'bi-calendar-event';
                                $notifTypeClass = 'bg-info-subtle text-info-emphasis';
                            } elseif (strpos($notifLinkLc, 'prospect') !== false || strpos($notifTitleLc, 'prospect') !== false || strpos($notifTitleLc, 'lead') !== false) {
                                $notifTypeLabel = __('notif.lead');
                                $notifTypeIcon = 'bi-person-plus';
                                $notifTypeClass = 'bg-success-subtle text-success-emphasis';
                            } elseif (strpos($notifLinkLc, 'finanzas') !== false || strpos($notifLinkLc, 'pagos') !== false || strpos($notifTitleLc, 'pago') !== false) {
                                $notifTypeLabel = __('notif.finance');
                                $notifTypeIcon = 'bi-cash-stack';
                                $notifTypeClass = 'bg-danger-subtle text-danger-emphasis';
                            }
                        ?>
                        <a class="notif-item <?= intval($notif['leida']) === 0 ? 'unread' : '' ?>" href="<?= $notif['enlace'] ?? '#' ?>" data-notif-id="<?= intval($notif['id']) ?>">
                            <div class="notif-time"><?= formatFechaHora($notif['created_at']) ?></div>
                            <div class="notif-title">
                                <?php if (intval($notif['leida']) === 0): ?><span class="notif-badge-dot"></span><?php endif; ?>
                                <i class="bi <?= $notifTypeIcon ?> me-1"></i><?= sanitize($notif['titulo']) ?>
                                <span class="notif-type-chip <?= $notifTypeClass ?>"><?= sanitize($notifTypeLabel) ?></span>
                            </div>
                        </a>
                        <?php endforeach; endif; ?>
                        <?php if (!empty($notifs)): ?></div><?php endif; ?>
                        <div class="notif-header border-top-0">
                            <a href="<?= APP_URL ?>/notificaciones.php" class="btn btn-sm btn-outline-secondary w-100"><?= __('topbar.view_all') ?></a>
                        </div>
                    </div>
                </div>
                <!-- Usuario -->
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <span class="top-user-name"><?= sanitize(currentUserName()) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/usuarios/perfil.php"><i class="bi bi-person"></i> <?= __('topbar.my_profile') ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> <?= __('topbar.logout') ?></a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="content-wrapper">
            <?php
            $flash = getFlash();
            if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const notifApiUrl = '<?= APP_URL ?>/api/notificaciones.php';
                const csrf = '<?= csrfToken() ?>';
                const bellBadge = document.querySelector('.btn[data-bs-toggle="dropdown"] .badge');

                function reduceBellCount() {
                    if (!bellBadge) return;
                    const current = parseInt(bellBadge.textContent || '0', 10);
                    if (isNaN(current)) return;
                    const next = Math.max(0, current - 1);
                    if (next <= 0) {
                        bellBadge.remove();
                    } else {
                        bellBadge.textContent = String(next);
                    }
                }

                document.querySelectorAll('.notif-item[data-notif-id]').forEach(function (el) {
                    el.addEventListener('click', function () {
                        const notifId = this.getAttribute('data-notif-id');
                        const wasUnread = this.classList.contains('unread');
                        const data = new FormData();
                        data.append('accion', 'mark_one');
                        data.append('id', notifId);
                        data.append('csrf_token', csrf);
                        navigator.sendBeacon ? navigator.sendBeacon(notifApiUrl, data) : fetch(notifApiUrl, { method: 'POST', body: data, keepalive: true });

                        if (wasUnread) {
                            this.classList.remove('unread');
                            const dot = this.querySelector('.notif-badge-dot');
                            if (dot) dot.remove();
                            reduceBellCount();
                        }
                    });
                });

                const markAllBtn = document.getElementById('markAllNotifBtn');
                if (markAllBtn) {
                    markAllBtn.addEventListener('click', function () {
                        const data = new FormData();
                        data.append('accion', 'mark_all');
                        data.append('csrf_token', csrf);
                        fetch(notifApiUrl, { method: 'POST', body: data })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                if (res && res.success) {
                                    document.querySelectorAll('.notif-item.unread').forEach(function (it) {
                                        it.classList.remove('unread');
                                        const dot = it.querySelector('.notif-badge-dot');
                                        if (dot) dot.remove();
                                    });
                                    if (bellBadge) bellBadge.remove();
                                    markAllBtn.remove();
                                }
                            })
                            .catch(function () {});
                    });
                }
            });
            </script>
