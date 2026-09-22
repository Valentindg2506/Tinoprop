<?php
$pageTitle = 'Ajustes';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/ajustes_helper.php';
require_once __DIR__ . '/../../includes/google_calendar_helper.php';

$db = getDB();

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Idioma
    $nuevoIdioma = in_array(post('idioma'), ['es', 'en']) ? post('idioma') : 'es';
    setUserSetting('idioma', $nuevoIdioma);

    // Apariencia
    setUserSetting('tema', post('tema', 'claro'));
    setUserSetting('color_primario', post('color_primario', 'emerald'));
    setUserSetting('sidebar_compacta', isset($_POST['sidebar_compacta']) ? '1' : '0');

    // Dashboard
    $widgets = $_POST['dashboard_widgets'] ?? [];
    setUserSetting('dashboard_widgets', implode(',', array_map('sanitize', $widgets)));
    setUserSetting('items_por_pagina', post('items_por_pagina', '20'));

    // Notificaciones
    setUserSetting('notif_email_visitas', isset($_POST['notif_email_visitas']) ? '1' : '0');
    setUserSetting('notif_email_tareas', isset($_POST['notif_email_tareas']) ? '1' : '0');
    setUserSetting('notif_email_semanal', isset($_POST['notif_email_semanal']) ? '1' : '0');
    setUserSetting('notif_browser', isset($_POST['notif_browser']) ? '1' : '0');

    // Datos empresa (solo admin)
    if (isAdmin()) {
        setUserSetting('empresa_nombre', post('empresa_nombre'));
        setUserSetting('empresa_cif', post('empresa_cif'));
        setUserSetting('empresa_direccion', post('empresa_direccion'));
        setUserSetting('empresa_email_dpd', post('empresa_email_dpd'));

        if (!empty($_FILES['empresa_logo']['name'])) {
            $logo = uploadImage($_FILES['empresa_logo'], 'empresa');
            if (isset($logo['success'])) {
                setUserSetting('empresa_logo', $logo['filename']);
            }
        }
    }

    // Reload translations for the saved language so flash message is in the right language
    loadLang($nuevoIdioma);

    registrarActividad('actualizar', 'ajustes', currentUserId(), 'Ajustes de usuario actualizados');
    setFlash('success', __('settings.saved'));
    header('Location: index.php');
    exit;
}

// Google Calendar token del usuario actual
$gcalToken = null;
try { $gcalToken = gcalGetToken($db, currentUserId()); } catch (Exception $e) {}

// Load current settings
$settings = getUserSettings();
$tema = $settings['tema'] ?? 'claro';
$colorPrimario = $settings['color_primario'] ?? 'emerald';
$sidebarCompacta = ($settings['sidebar_compacta'] ?? '0') === '1';
$dashboardWidgets = explode(',', $settings['dashboard_widgets'] ?? 'kpis,finanzas,proximas_visitas,tareas,ultimas_propiedades,actividad');
$itemsPorPagina = $settings['items_por_pagina'] ?? '20';
$notifEmailVisitas = ($settings['notif_email_visitas'] ?? '1') === '1';
$notifEmailTareas = ($settings['notif_email_tareas'] ?? '1') === '1';
$notifEmailSemanal = ($settings['notif_email_semanal'] ?? '0') === '1';
$notifBrowser = ($settings['notif_browser'] ?? '0') === '1';
$idiomaActual = $settings['idioma'] ?? 'es';

$colores = [
    'emerald' => ['label' => __('color.emerald'), 'hex' => '#10b981'],
    'blue'    => ['label' => __('color.blue'),    'hex' => '#3b82f6'],
    'purple'  => ['label' => __('color.purple'),  'hex' => '#8b5cf6'],
    'orange'  => ['label' => __('color.orange'),  'hex' => '#f97316'],
    'rose'    => ['label' => __('color.rose'),    'hex' => '#f43f5e'],
    'cyan'    => ['label' => __('color.cyan'),    'hex' => '#06b6d4'],
];

$widgetOptions = [
    'kpis'                => __('widget.kpis'),
    'finanzas'            => __('widget.finanzas'),
    'proximas_visitas'    => __('widget.proximas_visitas'),
    'tareas'              => __('widget.tareas'),
    'ultimas_propiedades' => __('widget.ultimas_propiedades'),
    'actividad'           => __('widget.actividad'),
];
?>

<div class="d-flex align-items-center justify-content-between gap-2 mb-4">
    <h5 class="mb-0"><i class="bi bi-sliders"></i> <?= __('settings.title') ?></h5>
    <a href="mcp_connector.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-robot"></i> <?= __('settings.mcp_btn') ?>
    </a>
</div>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="row g-4">
        <!-- Card: Idioma -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-translate"></i> <?= __('settings.language') ?></h6>
                </div>
                <div class="card-body">
                    <label class="form-label"><?= __('settings.language_label') ?></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="idioma" id="idiomaEs" value="es" <?= $idiomaActual === 'es' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="idiomaEs">
                                <span style="font-size:1.1em;">🇪🇸</span> <?= __('settings.language.es') ?>
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="idioma" id="idiomaEn" value="en" <?= $idiomaActual === 'en' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="idiomaEn">
                                <span style="font-size:1.1em;">🇺🇸</span> <?= __('settings.language.en') ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Apariencia -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-palette"></i> <?= __('settings.appearance') ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.theme') ?></label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tema" id="temaClaro" value="claro" <?= $tema === 'claro' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="temaClaro"><i class="bi bi-sun"></i> <?= __('settings.light') ?></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="tema" id="temaOscuro" value="oscuro" <?= $tema === 'oscuro' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="temaOscuro"><i class="bi bi-moon"></i> <?= __('settings.dark') ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.primary_color') ?></label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach ($colores as $key => $color): ?>
                            <label class="color-option position-relative" style="cursor: pointer;">
                                <input type="radio" name="color_primario" value="<?= $key ?>" class="d-none" <?= $colorPrimario === $key ? 'checked' : '' ?>>
                                <span class="d-inline-block rounded-circle border border-2" style="width: 36px; height: 36px; background-color: <?= $color['hex'] ?>; <?= $colorPrimario === $key ? 'border-color: #000 !important;' : '' ?>" title="<?= htmlspecialchars($color['label']) ?>"></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sidebar_compacta" id="sidebarCompacta" value="1" <?= $sidebarCompacta ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sidebarCompacta"><?= __('settings.compact_sidebar') ?></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Dashboard -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-speedometer2"></i> <?= __('settings.dashboard') ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.visible_widgets') ?></label>
                        <?php foreach ($widgetOptions as $key => $label): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="dashboard_widgets[]" value="<?= $key ?>" id="widget_<?= $key ?>" <?= in_array($key, $dashboardWidgets) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="widget_<?= $key ?>"><?= htmlspecialchars($label) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-0">
                        <label class="form-label"><?= __('settings.items_per_page') ?></label>
                        <select name="items_por_pagina" class="form-select">
                            <option value="10" <?= $itemsPorPagina === '10' ? 'selected' : '' ?>>10</option>
                            <option value="20" <?= $itemsPorPagina === '20' ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $itemsPorPagina === '50' ? 'selected' : '' ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Notificaciones -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-bell"></i> <?= __('settings.notifications') ?></h6>
                </div>
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notif_email_visitas" id="notifVisitas" value="1" <?= $notifEmailVisitas ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifVisitas"><?= __('settings.email_visits') ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notif_email_tareas" id="notifTareas" value="1" <?= $notifEmailTareas ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifTareas"><?= __('settings.email_tasks') ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="notif_email_semanal" id="notifSemanal" value="1" <?= $notifEmailSemanal ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifSemanal"><?= __('settings.email_weekly') ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notif_browser" id="notifBrowser" value="1" <?= $notifBrowser ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notifBrowser"><?= __('settings.browser_notif') ?></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Google Calendar -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1" style="vertical-align:-2px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?= __('settings.gcal') ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!gcalIsConfigured()): ?>
                        <div class="alert alert-warning mb-0 small">
                            <?= __('settings.gcal.not_configured') ?>
                        </div>
                    <?php elseif ($gcalToken): ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge bg-success fs-6"><i class="bi bi-check-circle"></i> <?= __('settings.gcal.connected') ?></span>
                            <?php if (!empty($gcalToken['google_email'])): ?>
                                <span class="text-muted small"><?= sanitize($gcalToken['google_email']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mb-3">
                            <?= __('settings.gcal.sync_desc') ?>
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" id="btnGcalSync" class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-repeat"></i> <?= __('settings.gcal.sync_now') ?>
                            </button>
                            <form method="POST" action="google_calendar_disconnect.php" class="d-inline" onsubmit="return confirm('<?= htmlspecialchars(__('settings.gcal.disconnect_confirm')) ?>')">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-x-circle"></i> <?= __('settings.gcal.disconnect') ?>
                                </button>
                            </form>
                        </div>
                        <div id="gcalSyncResult" class="mt-3 small"></div>
                    <?php else: ?>
                        <p class="small text-muted mb-3">
                            <?= __('settings.gcal.connect_desc') ?>
                        </p>
                        <a href="google_calendar_connect.php" class="btn btn-outline-primary btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" class="me-1" style="vertical-align:-2px"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            <?= __('settings.gcal.connect') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card: Datos Empresa (admin only) -->
        <?php if (isAdmin()): ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0"><i class="bi bi-building"></i> <?= __('settings.company_data') ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.company_name') ?></label>
                        <input type="text" name="empresa_nombre" class="form-control" value="<?= sanitize($settings['empresa_nombre'] ?? '') ?>" maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.cif') ?></label>
                        <input type="text" name="empresa_cif" class="form-control" value="<?= sanitize($settings['empresa_cif'] ?? '') ?>" maxlength="20">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.address') ?></label>
                        <input type="text" name="empresa_direccion" class="form-control" value="<?= sanitize($settings['empresa_direccion'] ?? '') ?>" maxlength="300">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('settings.dpd_email') ?></label>
                        <input type="email" name="empresa_email_dpd" class="form-control" value="<?= sanitize($settings['empresa_email_dpd'] ?? '') ?>" maxlength="200">
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= __('settings.logo') ?></label>
                        <input type="file" name="empresa_logo" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($settings['empresa_logo'])): ?>
                            <small class="text-muted mt-1 d-block"><?= __('settings.current_logo') ?><?= sanitize($settings['empresa_logo']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= __('settings.save') ?></button>
    </div>
</form>

<!-- Acceso rapido a herramientas -->
<div class="card mt-4 shadow-sm border-0">
    <div class="card-header"><i class="bi bi-tools"></i> <?= __('settings.tools') ?></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <a href="custom_fields.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-ui-checks-grid"></i> <?= __('settings.custom_fields') ?>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= APP_URL ?>/modules/calendario/booking_config.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-calendar-check"></i> <?= __('settings.booking') ?>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= APP_URL ?>/modules/automatizaciones/index.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-robot"></i> <?= __('settings.automations') ?>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= APP_URL ?>/legal/setup.php" class="btn btn-outline-success w-100">
                    <i class="bi bi-shield-check"></i> <?= __('settings.legal') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.color-option input:checked + span {
    border-color: #000 !important;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.2);
}
</style>

<script>
(function () {
    const btn = document.getElementById('btnGcalSync');
    if (!btn) return;

    const labelSyncNow = <?= json_encode(__('settings.gcal.sync_now')) ?>;
    const labelSyncing = <?= json_encode(__('settings.gcal.syncing')) ?>;
    const labelUnknownError = <?= json_encode(__('settings.unknown_error')) ?>;
    const labelConnError = <?= json_encode(__('settings.connection_error')) ?>;

    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + labelSyncing;
        const result = document.getElementById('gcalSyncResult');
        result.textContent = '';

        const fd = new FormData();
        fd.append('csrf_token', '<?= csrfToken() ?>');

        fetch('google_calendar_sync.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    result.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> ' + data.message + '</span>';
                } else {
                    result.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> ' + (data.error || labelUnknownError) + '</span>';
                }
            })
            .catch(() => {
                result.innerHTML = '<span class="text-danger">' + labelConnError + '</span>';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> ' + labelSyncNow;
            });
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
