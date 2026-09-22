<?php
/**
 * Panel de configuración de datos legales.
 * Solo accesible para admins del CRM.
 */
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
require_once __DIR__ . '/config.php';

$saved   = false;
$errors  = [];
$envFile = __DIR__ . '/../.env';

// ── Guardar ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token CSRF inválido. Recarga la página.';
    } else {
        $fields = [
            'LEGAL_EMPRESA_NOMBRE'             => trim($_POST['nombre']    ?? ''),
            'LEGAL_EMPRESA_CIF'                => trim($_POST['cif']       ?? ''),
            'LEGAL_EMPRESA_DIRECCION'          => trim($_POST['direccion'] ?? ''),
            'LEGAL_EMPRESA_CIUDAD'             => trim($_POST['ciudad']    ?? ''),
            'LEGAL_EMPRESA_CP'                 => trim($_POST['cp']        ?? ''),
            'LEGAL_EMPRESA_EMAIL_PRIVACIDAD'   => trim($_POST['email']     ?? ''),
            'LEGAL_EMPRESA_TELEFONO'           => trim($_POST['telefono']  ?? ''),
            'LEGAL_EMPRESA_REGISTRO_MERCANTIL' => trim($_POST['registro']  ?? ''),
            'LEGAL_URL_PRECIOS'                => trim($_POST['url_precios'] ?? ''),
        ];

        if (empty($fields['LEGAL_EMPRESA_NOMBRE'])) $errors[] = 'El nombre de la empresa es obligatorio.';
        if (empty($fields['LEGAL_EMPRESA_CIF']))    $errors[] = 'El CIF/NIF es obligatorio.';

        if (empty($errors)) {
            // Leer .env actual
            $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

            // Actualizar o insertar cada variable
            foreach ($fields as $key => $val) {
                $escapedVal = str_replace(["\n", "\r"], '', $val); // sanitizar
                if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $envContent)) {
                    // Actualizar línea existente
                    $envContent = preg_replace(
                        '/^' . preg_quote($key, '/') . '=.*$/m',
                        $key . '=' . $escapedVal,
                        $envContent
                    );
                } else {
                    // Añadir al bloque LEGAL si existe, o al final
                    if (strpos($envContent, '# ─── DATOS LEGALES') !== false) {
                        $envContent = preg_replace(
                            '/(# ─{3} DATOS LEGALES.*?# ─{3,}[^\n]*\n)/s',
                            '$1' . $key . '=' . $escapedVal . "\n",
                            $envContent
                        );
                    } else {
                        $envContent .= "\n" . $key . '=' . $escapedVal;
                    }
                }
            }

            if (file_put_contents($envFile, $envContent) !== false) {
                // Actualizar putenv en tiempo de ejecución (para que config.php las vea ahora)
                foreach ($fields as $key => $val) {
                    putenv("$key=$val");
                }
                $saved = true;
            } else {
                $errors[] = 'No se pudo escribir el archivo .env. Verifica los permisos.';
            }
        }
    }
}

// Generar CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Valores actuales
$current = [
    'nombre'     => getenv('LEGAL_EMPRESA_NOMBRE')             ?: '',
    'cif'        => getenv('LEGAL_EMPRESA_CIF')                ?: '',
    'direccion'  => getenv('LEGAL_EMPRESA_DIRECCION')          ?: '',
    'ciudad'     => getenv('LEGAL_EMPRESA_CIUDAD')             ?: '',
    'cp'         => getenv('LEGAL_EMPRESA_CP')                 ?: '',
    'email'      => getenv('LEGAL_EMPRESA_EMAIL_PRIVACIDAD')   ?: '',
    'telefono'   => getenv('LEGAL_EMPRESA_TELEFONO')           ?: '',
    'registro'   => getenv('LEGAL_EMPRESA_REGISTRO_MERCANTIL') ?: '',
    'url_precios'=> getenv('LEGAL_URL_PRECIOS')                ?: '',
];
?>

<div class="container-fluid py-4">
<div class="row justify-content-center">
<div class="col-lg-8">

    <div class="d-flex align-items-center gap-3 mb-4">
        <div class="bg-success bg-opacity-10 rounded-3 p-3">
            <i class="bi bi-shield-check text-success fs-3"></i>
        </div>
        <div>
            <h4 class="mb-0 fw-bold">Configuración de datos legales</h4>
            <p class="text-muted mb-0 small">Estos datos se usan automáticamente en todos los documentos legales del CRM</p>
        </div>
    </div>

    <?php if ($saved): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <div><strong>¡Guardado!</strong> Los documentos legales ya muestran los datos actualizados.</div>
    </div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold py-3">
                <i class="bi bi-building me-2 text-success"></i> Datos de la empresa
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Razón social / Nombre empresa <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= htmlspecialchars($current['nombre']) ?>"
                               placeholder="Ej: Tinoprop Solutions, S.L." required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CIF / NIF <span class="text-danger">*</span></label>
                        <input type="text" name="cif" class="form-control"
                               value="<?= htmlspecialchars($current['cif']) ?>"
                               placeholder="Ej: B12345678">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Dirección fiscal (calle y número)</label>
                        <input type="text" name="direccion" class="form-control"
                               value="<?= htmlspecialchars($current['direccion']) ?>"
                               placeholder="Ej: Calle Gran Vía, 28">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Código postal</label>
                        <input type="text" name="cp" class="form-control"
                               value="<?= htmlspecialchars($current['cp']) ?>"
                               placeholder="Ej: 28013" maxlength="5">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Ciudad</label>
                        <input type="text" name="ciudad" class="form-control"
                               value="<?= htmlspecialchars($current['ciudad']) ?>"
                               placeholder="Ej: Madrid">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Teléfono</label>
                        <input type="text" name="telefono" class="form-control"
                               value="<?= htmlspecialchars($current['telefono']) ?>"
                               placeholder="Ej: +34 900 000 000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Registro Mercantil</label>
                        <input type="text" name="registro" class="form-control"
                               value="<?= htmlspecialchars($current['registro']) ?>"
                               placeholder="Ej: Reg. Mercantil Madrid, T. 1234, F. 56">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold py-3">
                <i class="bi bi-envelope me-2 text-success"></i> Contacto y URLs
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email de privacidad / DPO</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= htmlspecialchars($current['email']) ?>"
                               placeholder="Ej: privacidad@tuempresa.es">
                        <div class="form-text">Aparece en la Política de Privacidad y documentos legales.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">URL página de precios</label>
                        <input type="url" name="url_precios" class="form-control"
                               value="<?= htmlspecialchars($current['url_precios']) ?>"
                               placeholder="Ej: https://tinoprop.es/precios">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <button type="submit" class="btn btn-success px-4">
                <i class="bi bi-save me-1"></i> Guardar cambios
            </button>
            <div class="ms-2 text-muted small">
                Los cambios se aplican inmediatamente a todos los documentos.
            </div>
        </div>
    </form>

    <!-- Vista previa de documentos -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="bi bi-eye me-2 text-success"></i> Vista previa de documentos
        </div>
        <div class="card-body">
            <div class="row g-2">
                <?php
                $docs = [
                    ['Política de Privacidad', 'privacidad.php',  'bi-shield-lock-fill'],
                    ['Política de Cookies',    'cookies.php',     'bi-cookie'],
                    ['Aviso Legal',            'aviso-legal.php', 'bi-file-earmark-text-fill'],
                    ['Términos y Condiciones', 'terminos.php',    'bi-file-earmark-check-fill'],
                ];
                foreach ($docs as [$label, $url, $icon]):
                ?>
                <div class="col-sm-6">
                    <a href="<?= $url ?>" target="_blank"
                       class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-dark hover-bg-light">
                        <i class="bi <?= $icon ?> text-success"></i>
                        <span class="small"><?= $label ?></span>
                        <i class="bi bi-box-arrow-up-right ms-auto text-muted" style="font-size:.75rem"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Documentos internos -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white border-bottom fw-semibold py-3">
            <i class="bi bi-folder2-open me-2 text-success"></i> Documentos internos de compliance
        </div>
        <div class="card-body">
            <?php
            $mdDocs = [
                ['Contrato Encargado Tratamiento (DPA)', 'contrato-encargado.md'],
                ['Registro de Actividades (RAT)',         'registro-actividades.md'],
                ['Base Legal por Tratamiento',            'base-legal.md'],
                ['Evaluación de Riesgos',                 'evaluacion-riesgos.md'],
                ['Medidas de Seguridad',                  'medidas-seguridad.md'],
                ['Cláusulas de Consentimiento',           'clausulas-consentimiento.md'],
                ['Checklist RGPD',                        'checklist-rgpd.md'],
                ['Requisitos Técnicos',                   'requisitos-tecnicos.md'],
            ];
            foreach ($mdDocs as [$label, $file]):
            ?>
            <a href="ver_doc.php?doc=<?= urlencode($file) ?>" target="_blank"
               class="d-flex align-items-center gap-2 p-2 rounded border mb-1 text-decoration-none text-dark">
                <i class="bi bi-file-earmark-text text-success"></i>
                <span class="small"><?= htmlspecialchars($label) ?></span>
                <span class="badge bg-secondary ms-auto small">MD</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

</div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
