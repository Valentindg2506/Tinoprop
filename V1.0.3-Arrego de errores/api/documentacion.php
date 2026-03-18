<?php

require_once __DIR__ . '/../inc/bootstrap.php';
function doc_json_response(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function doc_normalizar_entidad(?string $entity_type): ?string
{
    if ($entity_type === 'cliente' || $entity_type === 'propiedad') {
        return $entity_type;
    }
    return null;
}
function doc_base_dir(): string
{
    $base = __DIR__ . '/../storage/documentacion';
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }
    return $base;
}
function doc_entity_dir(string $entity_type, int $entity_id, string $kind): string
{
    $folder = $entity_type === 'cliente' ? 'clientes' : 'propiedades';
    $kind_folder = $kind === 'generated' ? 'generados' : 'subidos';
    $path = doc_base_dir() . '/' . $folder . '/' . $entity_id . '/' . $kind_folder;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    return $path;
}
function doc_size_label(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}
function doc_list_files(string $path, string $entity_type, int $entity_id, string $kind): array
{
    if (!is_dir($path)) {
        return [];
    }
    $result = [];
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $path . '/' . $item;
        if (!is_file($full)) {
            continue;
        }
        $result[] = [
            'name' => $item,
            'size' => filesize($full),
            'size_label' => doc_size_label((int) filesize($full)),
            'modified' => date('Y-m-d H:i:s', (int) filemtime($full)),
            'download_url' => 'api/documentacion.php?action=download&entity_type=' . urlencode($entity_type) . '&entity_id=' . $entity_id . '&kind=' . urlencode($kind) . '&file=' . urlencode($item),
        ];
    }
    usort($result, static function (array $a, array $b): int {
        return strcmp($b['modified'], $a['modified']);
    });
    return $result;
}
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$acciones_escritura_doc = ['upload', 'save_pdf'];
if ($action && in_array($action, $acciones_escritura_doc)) {
    csrf_verify_api();
}
function doc_verificar_tenant(string $entity_type, int $entity_id): bool
{
    if (es_superadmin()) {
        return true;
    }
    $pdo = db();
    $tabla = $entity_type === 'cliente' ? 'clientes' : 'propiedades';
    $stmt = $pdo->prepare("SELECT id FROM {$tabla} WHERE id = :id AND inmobiliaria_id = :iid LIMIT 1");
    $stmt->execute(['id' => $entity_id, 'iid' => usuario_inmobiliaria_id()]);
    return (bool) $stmt->fetch();
}
if ($action === 'download') {
    $entity_type = doc_normalizar_entidad($_GET['entity_type'] ?? null);
    $entity_id = (int) ($_GET['entity_id'] ?? 0);
    $kind = ($_GET['kind'] ?? 'uploaded') === 'generated' ? 'generated' : 'uploaded';
    $file = basename((string) ($_GET['file'] ?? ''));
    if (!$entity_type || $entity_id <= 0 || $file === '') {
        http_response_code(400);
        echo 'Solicitud inválida';
        exit;
    }
    if (!doc_verificar_tenant($entity_type, $entity_id)) {
        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }
    $dir = doc_entity_dir($entity_type, $entity_id, $kind);
    $full = $dir . '/' . $file;
    if (!is_file($full)) {
        http_response_code(404);
        echo 'Archivo no encontrado';
        exit;
    }
    $mime = mime_content_type($full) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $file) . '"');
    readfile($full);
    exit;
}
if (!$action) {
    doc_json_response(400, ['success' => false, 'message' => 'Acción no especificada']);
}
try {
    if ($action === 'list_files') {
        $entity_type = doc_normalizar_entidad($_GET['entity_type'] ?? null);
        $entity_id = (int) ($_GET['entity_id'] ?? 0);
        if (!$entity_type || $entity_id <= 0) {
            doc_json_response(400, ['success' => false, 'message' => 'Entidad inválida']);
        }
        if (!doc_verificar_tenant($entity_type, $entity_id)) {
            doc_json_response(403, ['success' => false, 'message' => 'Acceso denegado']);
        }
        $uploaded_dir = doc_entity_dir($entity_type, $entity_id, 'uploaded');
        $generated_dir = doc_entity_dir($entity_type, $entity_id, 'generated');
        doc_json_response(200, [
            'success' => true,
            'uploaded' => doc_list_files($uploaded_dir, $entity_type, $entity_id, 'uploaded'),
            'generated' => doc_list_files($generated_dir, $entity_type, $entity_id, 'generated'),
        ]);
    }
    if ($action === 'upload') {
        $entity_type = doc_normalizar_entidad($_POST['entity_type'] ?? null);
        $entity_id = (int) ($_POST['entity_id'] ?? 0);
        if (!$entity_type || $entity_id <= 0) {
            doc_json_response(400, ['success' => false, 'message' => 'Entidad inválida']);
        }
        if (!doc_verificar_tenant($entity_type, $entity_id)) {
            doc_json_response(403, ['success' => false, 'message' => 'Acceso denegado']);
        }
        if (empty($_FILES['documento']) || !isset($_FILES['documento']['tmp_name'])) {
            doc_json_response(400, ['success' => false, 'message' => 'No se recibió archivo']);
        }
        $file = $_FILES['documento'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            doc_json_response(400, ['success' => false, 'message' => 'Error en subida de archivo']);
        }
        $max_size = 20 * 1024 * 1024;
        if ((int) $file['size'] > $max_size) {
            doc_json_response(400, ['success' => false, 'message' => 'Archivo demasiado grande (máx 20MB)']);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            doc_json_response(400, ['success' => false, 'message' => 'Solo se permiten PDF o imágenes (JPG/PNG/WEBP)']);
        }
        $ext = $allowed[$mime];
        $base_name = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name) ?: 'archivo';
        $final_name = date('Ymd_His') . '_' . $safe_name . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest_dir = doc_entity_dir($entity_type, $entity_id, 'uploaded');
        $dest = $dest_dir . '/' . $final_name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            doc_json_response(500, ['success' => false, 'message' => 'No se pudo guardar el archivo']);
        }
        doc_json_response(200, ['success' => true, 'message' => 'Archivo subido correctamente', 'file' => $final_name]);
    }
    if ($action === 'save_pdf') {
        $payload = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $entity_type = doc_normalizar_entidad($payload['entity_type'] ?? null);
        $entity_id = (int) ($payload['entity_id'] ?? 0);
        $template_key = trim((string) ($payload['template_key'] ?? 'plantilla'));
        $template_name = trim((string) ($payload['template_name'] ?? 'Plantilla'));
        $pdf_base64 = (string) ($payload['pdf_base64'] ?? '');
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        if (!$entity_type || $entity_id <= 0 || $pdf_base64 === '') {
            doc_json_response(400, ['success' => false, 'message' => 'Datos incompletos para guardar PDF']);
        }
        if (!doc_verificar_tenant($entity_type, $entity_id)) {
            doc_json_response(403, ['success' => false, 'message' => 'Acceso denegado']);
        }
        if (strpos($pdf_base64, 'base64,') === false) {
            doc_json_response(400, ['success' => false, 'message' => 'Formato PDF inválido']);
        }
        $parts = explode('base64,', $pdf_base64, 2);
        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            doc_json_response(400, ['success' => false, 'message' => 'No se pudo decodificar el PDF']);
        }
        $safe_tpl = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template_key) ?: 'plantilla';
        $file_name = date('Ymd_His') . '_' . $safe_tpl . '_' . bin2hex(random_bytes(3)) . '.pdf';
        $dest_dir = doc_entity_dir($entity_type, $entity_id, 'generated');
        $dest = $dest_dir . '/' . $file_name;
        if (file_put_contents($dest, $binary) === false) {
            doc_json_response(500, ['success' => false, 'message' => 'No se pudo guardar la copia del PDF']);
        }
        $meta = [
            'template_key' => $template_key,
            'template_name' => $template_name,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'generated_at' => date('c'),
            'generated_by' => $_SESSION['usuario']['email'] ?? 'sistema',
            'fields' => $fields,
            'pdf_file' => $file_name,
        ];
        file_put_contents($dest . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        doc_json_response(200, [
            'success' => true,
            'message' => 'PDF generado y copia guardada',
            'download_name' => $file_name,
        ]);
    }
    doc_json_response(400, ['success' => false, 'message' => 'Acción no válida']);
} catch (Throwable $e) {
    doc_json_response(500, ['success' => false, 'message' => 'Error interno del servidor.']);
}
