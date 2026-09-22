<?php
/**
 * Visor de documentos markdown internos de compliance.
 * Sustituye los marcadores [NOMBRE EMPRESA] etc. con los datos del .env.
 * Solo accesible para admins del CRM.
 */
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
require_once __DIR__ . '/config.php';

$allowed = [
    'contrato-encargado.md',
    'registro-actividades.md',
    'base-legal.md',
    'evaluacion-riesgos.md',
    'medidas-seguridad.md',
    'clausulas-consentimiento.md',
    'checklist-rgpd.md',
    'requisitos-tecnicos.md',
];

$doc = basename($_GET['doc'] ?? '');
if (!in_array($doc, $allowed)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$filePath = __DIR__ . '/docs/' . $doc;
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$content = file_get_contents($filePath);

// Sustituir marcadores con los datos del .env
$markers = [
    '[NOMBRE EMPRESA]'            => LEGAL_NOMBRE,
    '[RAZÓN SOCIAL]'              => LEGAL_NOMBRE,
    '[NOMBRE EMPRESA / RAZÓN SOCIAL]' => LEGAL_NOMBRE,
    '[CIF]'                       => LEGAL_CIF,
    '[CIF/NIF]'                   => LEGAL_CIF,
    '[DIRECCIÓN]'                 => LEGAL_DIRECCION_COMPLETA,
    '[DIRECCIÓN FISCAL]'          => LEGAL_DIRECCION_COMPLETA,
    '[DIRECCIÓN FISCAL COMPLETA]' => LEGAL_DIRECCION_COMPLETA,
    '[CIUDAD]'                    => LEGAL_CIUDAD,
    '[EMAIL PRIVACIDAD]'          => LEGAL_EMAIL,
    '[EMAIL DE CONTACTO]'         => LEGAL_EMAIL,
    '[TELÉFONO]'                  => LEGAL_TELEFONO,
    '[REGISTRO]'                  => LEGAL_REGISTRO,
    '[URL DE PRECIOS]'            => LEGAL_URL_PRECIOS,
];

foreach ($markers as $marker => $value) {
    if ($value !== '') {
        $content = str_replace($marker, $value, $content);
    }
}

// Convertir Markdown a HTML (parser mínimo sin dependencias)
function mdToHtml(string $md): string {
    $html = htmlspecialchars($md, ENT_QUOTES, 'UTF-8');

    // Bloques de código (preservar primero)
    $codeBlocks = [];
    $html = preg_replace_callback('/```[\w]*\n(.*?)```/s', function($m) use (&$codeBlocks) {
        $id = '__CODE_' . count($codeBlocks) . '__';
        $codeBlocks[$id] = '<pre class="bg-light border rounded p-3 small"><code>' . $m[1] . '</code></pre>';
        return $id;
    }, $html);

    // Código inline
    $html = preg_replace('/`([^`]+)`/', '<code class="bg-light px-1 rounded">$1</code>', $html);

    // Encabezados
    $html = preg_replace('/^#### (.+)$/m',  '<h6 class="fw-bold mt-3">$1</h6>', $html);
    $html = preg_replace('/^### (.+)$/m',   '<h5 class="fw-bold mt-3 text-success">$1</h5>', $html);
    $html = preg_replace('/^## (.+)$/m',    '<h4 class="fw-bold mt-4 mb-2 border-start border-4 border-success ps-2">$1</h4>', $html);
    $html = preg_replace('/^# (.+)$/m',     '<h3 class="fw-bold mt-4 mb-3">$1</h3>', $html);

    // Negritas e itálica
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/_(.+?)_/',       '<em>$1</em>', $html);

    // Tablas
    $html = preg_replace_callback('/(\|.+\|)\n(\|[-| :]+\|)\n((?:\|.+\|\n?)+)/', function($m) {
        $head = array_map('trim', explode('|', trim($m[1], '|')));
        $rows = array_filter(explode("\n", trim($m[3])));
        $out  = '<div class="table-responsive"><table class="table table-sm table-bordered table-hover">';
        $out .= '<thead class="table-light"><tr>' . implode('', array_map(fn($h) => "<th>$h</th>", $head)) . '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $cells = array_map('trim', explode('|', trim($row, '|')));
            $out .= '<tr>' . implode('', array_map(fn($c) => "<td>$c</td>", $cells)) . '</tr>';
        }
        $out .= '</tbody></table></div>';
        return $out;
    }, $html);

    // Listas no ordenadas
    $html = preg_replace_callback('/((?:^- .+\n?)+)/m', function($m) {
        $items = preg_replace('/^- (.+)$/m', '<li>$1</li>', trim($m[1]));
        return '<ul class="mb-2">' . $items . '</ul>';
    }, $html);

    // Listas ordenadas
    $html = preg_replace_callback('/((?:^\d+\. .+\n?)+)/m', function($m) {
        $items = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', trim($m[1]));
        return '<ol class="mb-2">' . $items . '</ol>';
    }, $html);

    // Línea horizontal
    $html = preg_replace('/^---$/m', '<hr>', $html);

    // Párrafos (líneas que no son etiquetas HTML)
    $lines = explode("\n", $html);
    $result = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '<')) {
            $result .= $line . "\n";
        } else {
            $result .= '<p class="mb-2">' . $trimmed . '</p>' . "\n";
        }
    }

    // Restaurar bloques de código
    foreach ($codeBlocks as $id => $block) {
        $result = str_replace(htmlspecialchars($id), $block, $result);
    }

    return $result;
}

$htmlContent = mdToHtml($content);

// Nombre amigable del documento
$docTitles = [
    'contrato-encargado.md'    => 'Contrato de Encargado del Tratamiento (DPA)',
    'registro-actividades.md'  => 'Registro de Actividades de Tratamiento (RAT)',
    'base-legal.md'            => 'Base Legal por Tratamiento',
    'evaluacion-riesgos.md'    => 'Evaluación de Riesgos RGPD',
    'medidas-seguridad.md'     => 'Medidas de Seguridad',
    'clausulas-consentimiento.md' => 'Cláusulas de Consentimiento',
    'checklist-rgpd.md'        => 'Checklist de Cumplimiento RGPD',
    'requisitos-tecnicos.md'   => 'Requisitos Técnicos de Cumplimiento',
];
$docTitle = $docTitles[$doc] ?? $doc;
?>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <a href="setup.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <strong><?= htmlspecialchars($docTitle) ?></strong>
            <span class="text-muted small ms-2">docs/<?= htmlspecialchars($doc) ?></span>
        </div>
        <div class="d-flex gap-2">
            <?php if (!legalDatosCompletos()): ?>
            <a href="setup.php" class="btn btn-sm btn-warning">
                <i class="bi bi-exclamation-triangle me-1"></i> Completar datos empresa
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Imprimir / PDF
            </button>
        </div>
    </div>

    <?php if (!legalDatosCompletos()): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-info-circle me-1"></i>
        Los marcadores <strong>[en amarillo]</strong> indican datos de empresa no configurados.
        <a href="setup.php" class="alert-link">Completar en configuración →</a>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4" style="max-width:900px; font-size:.95rem; line-height:1.8">
            <?= $htmlContent ?>
        </div>
    </div>
</div>

<style>
    @media print {
        .sidebar, nav, .btn, .alert, header { display: none !important; }
        .card { box-shadow: none !important; border: none !important; }
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
