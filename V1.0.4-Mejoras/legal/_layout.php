<?php
/**
 * Layout compartido para páginas legales públicas.
 * Usa output buffering para sustituir automáticamente todos los
 * marcadores [LEGAL_*] con los valores del .env al final del render.
 *
 * Uso: incluir al inicio de cada página legal.
 * Definir $pageTitle y $pageLastUpdate antes de incluir.
 */
require_once __DIR__ . '/config.php';

$pageTitle      = $pageTitle      ?? 'Documento Legal';
$pageLastUpdate = $pageLastUpdate ?? date('d/m/Y');

// Capturar toda la salida para sustituir marcadores al final
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Tinoprop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; color: #1e293b; background: #f8fafc; }
        .legal-header { background: linear-gradient(135deg, #10b981 0%, #065f46 100%); color: #fff; padding: 3rem 0 2rem; }
        .legal-header h1 { font-size: 2rem; font-weight: 700; }
        .legal-header .badge-update { background: rgba(255,255,255,0.2); font-size: .8rem; padding: .35em .7em; border-radius: 20px; }
        .legal-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07); padding: 2.5rem; margin-bottom: 2rem; }
        .legal-card h2 { font-size: 1.25rem; font-weight: 600; color: #065f46; border-left: 4px solid #10b981; padding-left: .75rem; margin-top: 2rem; margin-bottom: 1rem; }
        .legal-card h2:first-child { margin-top: 0; }
        .legal-card h3 { font-size: 1rem; font-weight: 600; color: #1e293b; margin-top: 1.5rem; }
        .legal-card p, .legal-card li { line-height: 1.8; color: #374151; }
        .legal-card table { font-size: .9rem; }
        .legal-card table th { background: #f0fdf4; color: #065f46; }
        .info-box { background: #f0fdf4; border: 1px solid #6ee7b7; border-radius: 8px; padding: 1rem 1.25rem; margin: 1rem 0; font-size: .9rem; }
        .warn-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 1rem 1.25rem; margin: 1rem 0; font-size: .9rem; }
        /* Placeholder visible solo cuando falta el dato */
        .placeholder { background: #fef3c7; padding: .1em .3em; border-radius: 3px; font-style: italic; color: #92400e; }
        footer { background: #1e293b; color: #94a3b8; padding: 2rem 0; font-size: .875rem; }
        footer a { color: #6ee7b7; text-decoration: none; }
        @media print { .legal-header { background: #065f46 !important; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
<header class="legal-header">
    <div class="container">
        <a href="../index.php" class="text-white text-decoration-none d-inline-flex align-items-center gap-2 mb-3 opacity-75">
            <i class="bi bi-arrow-left"></i> Volver al CRM
        </a>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <span class="badge-update"><i class="bi bi-calendar3 me-1"></i> Última actualización: <?= htmlspecialchars($pageLastUpdate) ?></span>
    </div>
</header>
<main class="container py-4">
<?php if (!legalDatosCompletos()): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div>
        <strong>Datos de empresa incompletos.</strong>
        Rellena las variables <code>LEGAL_EMPRESA_*</code> en el archivo <code>.env</code>
        para que los documentos legales sean válidos.
        <a href="setup.php" class="alert-link ms-1">Completar ahora →</a>
    </div>
</div>
<?php endif; ?>
