<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
if (!isAdmin()) { setFlash('danger','Solo administradores.'); header('Location: ../ajustes/index.php'); exit; }
$db = getDB();

// Check/create whitelabel table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS whitelabel_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        app_nombre VARCHAR(200) DEFAULT 'Tinoprop',
        app_logo_url VARCHAR(500) DEFAULT '',
        app_favicon_url VARCHAR(500) DEFAULT '',
        color_primario VARCHAR(7) DEFAULT '#10b981',
        color_secundario VARCHAR(7) DEFAULT '#1e293b',
        color_acento VARCHAR(7) DEFAULT '#f59e0b',
        dominio_custom VARCHAR(200) DEFAULT '',
        email_remitente VARCHAR(200) DEFAULT '',
        footer_texto VARCHAR(500) DEFAULT '',
        login_titulo VARCHAR(200) DEFAULT 'Iniciar Sesion',
        login_subtitulo VARCHAR(300) DEFAULT '',
        login_fondo_url VARCHAR(500) DEFAULT '',
        css_custom TEXT DEFAULT '',
        ocultar_powered_by TINYINT(1) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("INSERT IGNORE INTO whitelabel_config (id) VALUES (1)");
} catch (Exception $e) {
    error_log($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $db->prepare("UPDATE whitelabel_config SET app_nombre=?, app_logo_url=?, app_favicon_url=?, color_primario=?, color_secundario=?, color_acento=?, dominio_custom=?, email_remitente=?, footer_texto=?, login_titulo=?, login_subtitulo=?, login_fondo_url=?, css_custom=?, ocultar_powered_by=? WHERE id=1")
        ->execute([trim(post('app_nombre')), trim(post('app_logo_url')), trim(post('app_favicon_url')),
            post('color_primario','#10b981'), post('color_secundario','#1e293b'), post('color_acento','#f59e0b'),
            trim(post('dominio_custom')), trim(post('email_remitente')), trim(post('footer_texto')),
            trim(post('login_titulo')), trim(post('login_subtitulo')), trim(post('login_fondo_url')),
            $_POST['css_custom'] ?? '', intval(post('ocultar_powered_by'))]);
    setFlash('success','Configuracion guardada.');
    header('Location: whitelabel.php'); exit;
}

$pageTitle = 'White Label';
require_once __DIR__ . '/../../includes/header.php';
$wl = $db->query("SELECT * FROM whitelabel_config WHERE id=1")->fetch();
?>

<a href="../ajustes/index.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left"></i> Ajustes</a>

<form method="POST">
    <?= csrfField() ?>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-palette"></i> Marca</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nombre de la aplicacion</label><input type="text" name="app_nombre" class="form-control" value="<?= sanitize($wl['app_nombre']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Email remitente</label><input type="email" name="email_remitente" class="form-control" value="<?= sanitize($wl['email_remitente']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">URL Logo</label><input type="url" name="app_logo_url" class="form-control" value="<?= sanitize($wl['app_logo_url']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">URL Favicon</label><input type="url" name="app_favicon_url" class="form-control" value="<?= sanitize($wl['app_favicon_url']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Dominio personalizado</label><input type="text" name="dominio_custom" class="form-control" value="<?= sanitize($wl['dominio_custom']) ?>" placeholder="crm.tudominio.com"></div>
                        <div class="col-md-6"><label class="form-label">Texto del footer</label><input type="text" name="footer_texto" class="form-control" value="<?= sanitize($wl['footer_texto']) ?>"></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-paint-bucket"></i> Colores</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Primario</label><div class="d-flex gap-2"><input type="color" name="color_primario" class="form-control form-control-color" value="<?= $wl['color_primario'] ?>"><input type="text" class="form-control" value="<?= $wl['color_primario'] ?>" disabled></div></div>
                        <div class="col-md-4"><label class="form-label">Secundario</label><div class="d-flex gap-2"><input type="color" name="color_secundario" class="form-control form-control-color" value="<?= $wl['color_secundario'] ?>"><input type="text" class="form-control" value="<?= $wl['color_secundario'] ?>" disabled></div></div>
                        <div class="col-md-4"><label class="form-label">Acento</label><div class="d-flex gap-2"><input type="color" name="color_acento" class="form-control form-control-color" value="<?= $wl['color_acento'] ?>"><input type="text" class="form-control" value="<?= $wl['color_acento'] ?>" disabled></div></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-box-arrow-in-right"></i> Pagina de Login</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Titulo</label><input type="text" name="login_titulo" class="form-control" value="<?= sanitize($wl['login_titulo']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Subtitulo</label><input type="text" name="login_subtitulo" class="form-control" value="<?= sanitize($wl['login_subtitulo']) ?>"></div>
                        <div class="col-12"><label class="form-label">URL Fondo</label><input type="url" name="login_fondo_url" class="form-control" value="<?= sanitize($wl['login_fondo_url']) ?>"></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-code-slash"></i> CSS Personalizado</h6></div>
                <div class="card-body">
                    <textarea name="css_custom" class="form-control font-monospace" rows="8"><?= htmlspecialchars($wl['css_custom']??'') ?></textarea>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0">Vista previa</h6></div>
                <div class="card-body text-center">
                    <?php if ($wl['app_logo_url']): ?><img src="<?= sanitize($wl['app_logo_url']) ?>" style="max-height:60px" class="mb-2"><br><?php endif; ?>
                    <h5 class="fw-bold"><?= sanitize($wl['app_nombre']) ?></h5>
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <div style="width:40px;height:40px;border-radius:8px;background:<?= $wl['color_primario'] ?>"></div>
                        <div style="width:40px;height:40px;border-radius:8px;background:<?= $wl['color_secundario'] ?>"></div>
                        <div style="width:40px;height:40px;border-radius:8px;background:<?= $wl['color_acento'] ?>"></div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input type="checkbox" name="ocultar_powered_by" value="1" class="form-check-input" <?= $wl['ocultar_powered_by']?'checked':'' ?>>
                        <label class="form-check-label">Ocultar "Powered by Tinoprop"</label>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Guardar Configuracion</button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
