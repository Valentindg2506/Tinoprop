<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    exit('Plantilla no encontrada.');
}

try {
    $stmt = $db->prepare("SELECT nombre, tipo, archivo_path, archivo_nombre FROM contrato_plantillas WHERE id = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$id]);
    $tpl = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo cargar la plantilla.');
}

if (!$tpl || ($tpl['tipo'] ?? 'texto') !== 'pdf' || empty($tpl['archivo_path'])) {
    http_response_code(404);
    exit('Plantilla PDF no disponible.');
}

// Directorio base permitido — solo se sirven archivos dentro de assets/uploads/contratos
$baseDir = realpath(__DIR__ . '/../../assets/uploads/contratos');
if ($baseDir === false) {
    http_response_code(500);
    exit('Directorio de contratos no configurado.');
}

$absolutePath = realpath(__DIR__ . '/../../' . ltrim($tpl['archivo_path'], '/'));

// Verificar que el archivo existe Y está dentro del directorio permitido (previene path traversal)
if ($absolutePath === false || !is_file($absolutePath)) {
    http_response_code(404);
    exit('Archivo PDF no encontrado.');
}
if (strncmp($absolutePath, $baseDir . DIRECTORY_SEPARATOR, strlen($baseDir) + 1) !== 0) {
    // El archivo resuelto queda fuera del directorio permitido
    logError('Path traversal bloqueado en descargar_pdf.php', [
        'usuario_id' => $_SESSION['user_id'] ?? 0,
        'archivo_path' => $tpl['archivo_path'],
        'resuelto' => $absolutePath,
    ]);
    http_response_code(403);
    exit('Acceso denegado.');
}

// Verificar que es realmente un PDF (cabecera mágica %PDF)
$fh = fopen($absolutePath, 'rb');
$magic = $fh ? fread($fh, 4) : '';
if ($fh) fclose($fh);
if ($magic !== '%PDF') {
    http_response_code(403);
    exit('Tipo de archivo no permitido.');
}

$filename = $tpl['archivo_nombre'] ?: ($tpl['nombre'] . '.pdf');
$filename = preg_replace('/[^\w\s\-\.áéíóúÁÉÍÓÚñÑ]/u', '_', $filename); // sanitizar para header

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit;
