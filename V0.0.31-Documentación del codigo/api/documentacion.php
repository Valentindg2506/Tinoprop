<?php
/*
 * ==========================================================================
 * Archivo: api/documentacion.php — API de Documentación de TinoProp
 * ==========================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo gestiona la documentación adjunta a clientes y propiedades
 * del CRM inmobiliario. Permite subir, listar, descargar y generar PDFs
 * asociados a cada entidad (cliente o propiedad).
 *
 * ACCIONES DISPONIBLES:
 * - "list_files" → Lista todos los documentos de un cliente/propiedad.
 * - "upload"     → Sube un archivo (PDF, JPG, PNG, WEBP) a un cliente/propiedad.
 * - "save_pdf"   → Guarda un PDF generado desde una plantilla del frontend.
 * - "download"   → Descarga/visualiza un documento existente.
 *
 * ORGANIZACIÓN DE ARCHIVOS EN DISCO:
 * Los documentos se guardan en el sistema de archivos (NO en la base de datos)
 * con la siguiente estructura:
 *   storage/documentacion/
 *   ├── clientes/
 *   │   ├── {id_cliente}/
 *   │   │   ├── subidos/       ← Archivos que el usuario subió manualmente
 *   │   │   └── generados/     ← PDFs generados desde plantillas
 *   └── propiedades/
 *       ├── {id_propiedad}/
 *       │   ├── subidos/
 *       │   └── generados/
 *
 * SEGURIDAD:
 * - Verificación CSRF en operaciones de escritura (upload, save_pdf).
 * - Verificación de tenant: cada usuario solo accede a documentos de
 *   su propia inmobiliaria (excepto SuperAdmin que ve todo).
 * - Validación de tipo MIME para evitar subida de archivos maliciosos.
 * - Límite de tamaño: máximo 20MB por archivo.
 * - Nombres de archivo sanitizados para evitar ataques de path traversal.
 */

// Cargamos bootstrap: sesión, BD, funciones helper, autenticación
require_once __DIR__ . '/../inc/bootstrap.php';

/*
 * ==========================================================================
 * FUNCIONES AUXILIARES (HELPERS)
 * ==========================================================================
 * Estas funciones se definen aquí porque son específicas de este endpoint.
 * Encapsulan tareas repetitivas para no repetir código.
 */

/**
 * Devuelve una respuesta JSON consistente con código HTTP.
 *
 * ¿Por qué existe esta función?
 * En lugar de repetir http_response_code() + header() + json_encode() + exit
 * en cada lugar, lo centralizamos aquí. Si algún día cambia el formato
 * de respuesta, solo modificamos esta función.
 *
 * @param int   $code    Código HTTP (200=OK, 400=error del cliente, 500=error del servidor)
 * @param array $payload Array con los datos a devolver como JSON
 */
function doc_json_response(int $code, array $payload): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normaliza y valida el tipo de entidad admitido por el endpoint.
 *
 * Solo permitimos dos tipos de entidad: "cliente" y "propiedad".
 * Si se recibe cualquier otro valor (ej: un intento de inyección),
 * devuelve null para que el código que llama sepa que es inválido.
 *
 * @param string|null $entity_type El tipo recibido desde el navegador
 * @return string|null El tipo validado o null si no es válido
 */
function doc_normalizar_entidad(?string $entity_type): ?string
{
    if ($entity_type === 'cliente' || $entity_type === 'propiedad') {
        return $entity_type;
    }

    return null;
}

/**
 * Obtiene la ruta base de almacenamiento documental y crea la carpeta si no existe.
 *
 * Esta función devuelve la ruta absoluta donde se guardarán todos los
 * documentos. Si la carpeta no existe aún (ej: primera vez que se sube
 * un documento), la crea automáticamente con permisos 0775.
 *
 * @return string Ruta absoluta al directorio base de documentos
 */
function doc_base_dir(): string
{
    $base = __DIR__ . '/../storage/documentacion';
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }

    return $base;
}

/**
 * Construye la ruta absoluta de almacenamiento para una entidad específica.
 *
 * Ejemplo: doc_entity_dir('cliente', 42, 'uploaded')
 * → /ruta/storage/documentacion/clientes/42/subidos/
 *
 * @param string $entity_type Tipo de entidad ('cliente' o 'propiedad')
 * @param int    $entity_id   ID de la entidad
 * @param string $kind        Tipo de documento ('uploaded' o 'generated')
 * @return string Ruta absoluta al directorio de documentos de esa entidad
 */
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

/**
 * Convierte un tamaño en bytes a una etiqueta legible para humanos.
 *
 * Ejemplos:
 * - 512       → "512 B"    (bytes)
 * - 2048      → "2 KB"     (kilobytes)
 * - 1572864   → "1.5 MB"   (megabytes)
 *
 * @param int $bytes Tamaño del archivo en bytes
 * @return string Tamaño formateado con unidad
 */
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

/**
 * Lista archivos de una carpeta documental con metadatos para la interfaz.
 *
 * Recorre todos los archivos de un directorio y devuelve un array con
 * información útil para mostrar en la UI: nombre, tamaño, fecha y URL
 * de descarga. Los archivos se ordenan del más reciente al más antiguo.
 *
 * @param string $path        Ruta absoluta del directorio a listar
 * @param string $entity_type Tipo de entidad (para construir URL de descarga)
 * @param int    $entity_id   ID de la entidad (para construir URL de descarga)
 * @param string $kind        Tipo de documento (para construir URL de descarga)
 * @return array Lista de archivos con metadatos
 */
function doc_list_files(string $path, string $entity_type, int $entity_id, string $kind): array
{
    if (!is_dir($path)) {
        return [];
    }

    // scandir() lee el contenido de la carpeta. Devuelve un array
    // con los nombres de archivos y carpetas, incluyendo "." y "..".
    $result = [];
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        // Ignoramos "." (directorio actual) y ".." (directorio padre),
        // que siempre aparecen en cualquier listado de directorio.
        if ($item === '.' || $item === '..') {
            continue;
        }

        // Solo procesamos archivos, no subdirectorios
        $full = $path . '/' . $item;
        if (!is_file($full)) {
            continue;
        }

        // Cada archivo se devuelve con su nombre, tamaño (en bytes y legible),
        // fecha de modificación y una URL lista para usar como enlace de descarga.
        $result[] = [
            'name' => $item,
            'size' => filesize($full),
            'size_label' => doc_size_label((int) filesize($full)),
            'modified' => date('Y-m-d H:i:s', (int) filemtime($full)), // filemtime = fecha de última modificación
            'download_url' => 'api/documentacion.php?action=download&entity_type=' . urlencode($entity_type) . '&entity_id=' . $entity_id . '&kind=' . urlencode($kind) . '&file=' . urlencode($item),
        ];
    }

    // Ordenamos por fecha de modificación descendente (más reciente primero)
    usort($result, static function (array $a, array $b): int {
        return strcmp($b['modified'], $a['modified']);
    });

    return $result;
}

// ────────────────────────────────────────────────────────────────
// LÓGICA PRINCIPAL DEL ENDPOINT
// ────────────────────────────────────────────────────────────────

// Determinamos qué acción se solicita (desde GET o POST)
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Protección CSRF solo para acciones que MODIFICAN datos en el servidor.
// Las acciones de lectura (list_files, download) no necesitan CSRF.
$acciones_escritura_doc = ['upload', 'save_pdf'];
if ($action && in_array($action, $acciones_escritura_doc)) {
    csrf_verify_api();
}

/**
 * Verifica que la entidad (cliente o propiedad) pertenezca a la inmobiliaria
 * del usuario actual. Esto es la base del sistema MULTI-TENANT:
 *
 * ¿Qué es multi-tenant? Significa que múltiples inmobiliarias usan la misma
 * aplicación, pero cada una solo ve SUS propios datos. Un agente de la
 * inmobiliaria A no puede ver los documentos de la inmobiliaria B.
 *
 * El SuperAdmin puede acceder a todo (es el administrador global del sistema).
 *
 * @param string $entity_type 'cliente' o 'propiedad'
 * @param int    $entity_id   ID de la entidad a verificar
 * @return bool true si el usuario tiene acceso, false si no
 */
function doc_verificar_tenant(string $entity_type, int $entity_id): bool
{
    // SuperAdmin tiene acceso total a todos los datos
    if (es_superadmin()) return true;

    $pdo = db();
    // Verificamos que el registro exista Y pertenezca a la inmobiliaria del usuario
    $tabla = $entity_type === 'cliente' ? 'clientes' : 'propiedades';
    $stmt = $pdo->prepare("SELECT id FROM {$tabla} WHERE id = :id AND inmobiliaria_id = :iid LIMIT 1");
    $stmt->execute(['id' => $entity_id, 'iid' => usuario_inmobiliaria_id()]);
    return (bool) $stmt->fetch(); // true si encontró el registro, false si no
}

// =============================================================
// ACCIÓN: DESCARGAR/VISUALIZAR UN DOCUMENTO
// =============================================================
// Esta acción se procesa FUERA del try/catch porque no devuelve JSON,
// sino el archivo real (PDF, imagen, etc.) para que el navegador
// lo muestre o descargue.
if ($action === 'download') {
    // Recogemos y validamos todos los parámetros necesarios
    $entity_type = doc_normalizar_entidad($_GET['entity_type'] ?? null);
    $entity_id = (int) ($_GET['entity_id'] ?? 0);
    $kind = ($_GET['kind'] ?? 'uploaded') === 'generated' ? 'generated' : 'uploaded';
    // basename() extrae solo el nombre del archivo, eliminando cualquier
    // ruta maliciosa (ej: "../../etc/passwd" → "passwd"). Esto previene
    // ataques de "path traversal" donde alguien intenta leer archivos del sistema.
    $file = basename((string) ($_GET['file'] ?? ''));

    if (!$entity_type || $entity_id <= 0 || $file === '') {
        http_response_code(400);
        echo 'Solicitud inválida';
        exit;
    }

    // Verificación de tenant: el usuario solo puede descargar documentos
    // de clientes/propiedades de SU inmobiliaria
    if (!doc_verificar_tenant($entity_type, $entity_id)) {
        http_response_code(403); // 403 = Prohibido (no tienes permisos)
        echo 'Acceso denegado';
        exit;
    }

    // Construimos la ruta completa y verificamos que el archivo exista
    $dir = doc_entity_dir($entity_type, $entity_id, $kind);
    $full = $dir . '/' . $file;
    if (!is_file($full)) {
        http_response_code(404); // 404 = No encontrado
        echo 'Archivo no encontrado';
        exit;
    }

    // Detectamos el tipo MIME del archivo (ej: "application/pdf", "image/jpeg")
    // para que el navegador sepa cómo mostrarlo.
    $mime = mime_content_type($full) ?: 'application/octet-stream';

    // Enviamos las cabeceras HTTP necesarias para la descarga:
    // - Content-Type: le dice al navegador qué tipo de archivo es
    // - Content-Length: tamaño del archivo en bytes
    // - Content-Disposition: "inline" = abrirlo en el navegador (no forzar descarga)
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $file) . '"');

    // readfile() envía el contenido del archivo directamente al navegador
    readfile($full);
    exit;
}

// Si no se especificó ninguna acción, devolvemos error
if (!$action) {
    doc_json_response(400, ['success' => false, 'message' => 'Acción no especificada']);
}

try {
    // =============================================================
    // ACCIÓN: LISTAR ARCHIVOS DE UNA ENTIDAD
    // =============================================================
    // Devuelve la lista de documentos (subidos + generados) de un
    // cliente o propiedad específico.
    if ($action === 'list_files') {
        $entity_type = doc_normalizar_entidad($_GET['entity_type'] ?? null);
        $entity_id = (int) ($_GET['entity_id'] ?? 0);

        if (!$entity_type || $entity_id <= 0) {
            doc_json_response(400, ['success' => false, 'message' => 'Entidad inválida']);
        }

        // Verificación multi-tenant: solo puede ver documentos de su inmobiliaria
        if (!doc_verificar_tenant($entity_type, $entity_id)) {
            doc_json_response(403, ['success' => false, 'message' => 'Acceso denegado']);
        }

        // Obtenemos las rutas de ambas carpetas (subidos y generados)
        $uploaded_dir = doc_entity_dir($entity_type, $entity_id, 'uploaded');
        $generated_dir = doc_entity_dir($entity_type, $entity_id, 'generated');

        // Respondemos con ambas listas de archivos
        doc_json_response(200, [
            'success' => true,
            'uploaded' => doc_list_files($uploaded_dir, $entity_type, $entity_id, 'uploaded'),    // Documentos subidos por el usuario
            'generated' => doc_list_files($generated_dir, $entity_type, $entity_id, 'generated'), // PDFs generados desde plantillas
        ]);
    }

    // =============================================================
    // ACCIÓN: SUBIR UN ARCHIVO
    // =============================================================
    // Permite subir documentos (PDF, imágenes) asociados a un cliente
    // o propiedad. Incluye validaciones de seguridad estrictas.
    if ($action === 'upload') {
        $entity_type = doc_normalizar_entidad($_POST['entity_type'] ?? null);
        $entity_id = (int) ($_POST['entity_id'] ?? 0);

        if (!$entity_type || $entity_id <= 0) {
            doc_json_response(400, ['success' => false, 'message' => 'Entidad inválida']);
        }

        // Verificación multi-tenant
        if (!doc_verificar_tenant($entity_type, $entity_id)) {
            doc_json_response(403, ['success' => false, 'message' => 'Acceso denegado']);
        }

        // Comprobamos que se recibió un archivo en el campo "documento" del formulario
        if (empty($_FILES['documento']) || !isset($_FILES['documento']['tmp_name'])) {
            doc_json_response(400, ['success' => false, 'message' => 'No se recibió archivo']);
        }

        $file = $_FILES['documento'];

        // Verificamos que no hubo errores durante la subida
        // UPLOAD_ERR_OK (0) = todo correcto
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            doc_json_response(400, ['success' => false, 'message' => 'Error en subida de archivo']);
        }

        // LÍMITE DE TAMAÑO: 20 MB máximo
        // 20 * 1024 * 1024 = 20.971.520 bytes = 20 megabytes
        $max_size = 20 * 1024 * 1024;
        if ((int) $file['size'] > $max_size) {
            doc_json_response(400, ['success' => false, 'message' => 'Archivo demasiado grande (máx 20MB)']);
        }

        // VALIDACIÓN DE TIPO MIME (seguridad crítica)
        // Usamos finfo para detectar el tipo REAL del archivo (no confiamos en
        // la extensión que podría ser falsificada). Solo permitimos:
        // - PDF: documentos, contratos, etc.
        // - JPG/PNG/WEBP: fotos de documentos, DNI, etc.
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

        // GENERACIÓN DE NOMBRE SEGURO PARA EL ARCHIVO
        // Creamos un nombre único que incluye:
        // - Fecha y hora actual (para ordenar cronológicamente)
        // - Nombre original sanitizado (sin caracteres problemáticos)
        // - 3 bytes aleatorios en hexadecimal (para evitar colisiones)
        // - Extensión basada en el tipo MIME real (no la original)
        $ext = $allowed[$mime];
        $base_name = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        // preg_replace elimina cualquier carácter que no sea letra, número, guión o guión bajo
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base_name) ?: 'archivo';
        $final_name = date('Ymd_His') . '_' . $safe_name . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

        // Movemos el archivo desde la ubicación temporal de PHP a su destino final.
        // move_uploaded_file() es la forma SEGURA de mover archivos subidos
        // (PHP verifica internamente que el archivo fue realmente subido).
        $dest_dir = doc_entity_dir($entity_type, $entity_id, 'uploaded');
        $dest = $dest_dir . '/' . $final_name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            doc_json_response(500, ['success' => false, 'message' => 'No se pudo guardar el archivo']);
        }

        doc_json_response(200, ['success' => true, 'message' => 'Archivo subido correctamente', 'file' => $final_name]);
    }

    // =============================================================
    // ACCIÓN: GUARDAR PDF GENERADO DESDE PLANTILLA
    // =============================================================
    // El frontend genera un PDF (ej: contrato de alquiler, ficha de
    // propiedad) usando una plantilla y lo envía como base64 para
    // guardarlo como copia en el servidor.
    if ($action === 'save_pdf') {
        // Leemos el cuerpo JSON de la petición con todos los datos
        $payload = json_decode((string) file_get_contents('php://input'), true) ?: [];

        // Extraemos los campos del payload JSON
        $entity_type = doc_normalizar_entidad($payload['entity_type'] ?? null);
        $entity_id = (int) ($payload['entity_id'] ?? 0);
        $template_key = trim((string) ($payload['template_key'] ?? 'plantilla'));   // Identificador de la plantilla usada
        $template_name = trim((string) ($payload['template_name'] ?? 'Plantilla')); // Nombre legible de la plantilla
        $pdf_base64 = (string) ($payload['pdf_base64'] ?? '');                      // El PDF codificado en base64
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];   // Campos rellenados en la plantilla

        if (!$entity_type || $entity_id <= 0 || $pdf_base64 === '') {
            doc_json_response(400, ['success' => false, 'message' => 'Datos incompletos para guardar PDF']);
        }

        // Verificación multi-tenant
        if (!doc_verificar_tenant($entity_type, $entity_id)) {
            doc_json_response(403, ['success' => false, 'message' => 'Acceso denegado']);
        }

        // El PDF viene como una cadena base64 con prefijo (ej: "data:application/pdf;base64,...")
        // Verificamos que tenga el marcador "base64," para poder separar los datos
        if (strpos($pdf_base64, 'base64,') === false) {
            doc_json_response(400, ['success' => false, 'message' => 'Formato PDF inválido']);
        }

        // Separamos el prefijo del contenido base64 y lo decodificamos a binario.
        // Base64 es una forma de representar datos binarios como texto
        // (necesario para enviarlos por JSON que solo admite texto).
        $parts = explode('base64,', $pdf_base64, 2);
        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            doc_json_response(400, ['success' => false, 'message' => 'No se pudo decodificar el PDF']);
        }

        // Generamos nombre seguro para el PDF
        $safe_tpl = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template_key) ?: 'plantilla';
        $file_name = date('Ymd_His') . '_' . $safe_tpl . '_' . bin2hex(random_bytes(3)) . '.pdf';

        // Guardamos el PDF en la carpeta de documentos "generados" de esa entidad
        $dest_dir = doc_entity_dir($entity_type, $entity_id, 'generated');
        $dest = $dest_dir . '/' . $file_name;

        if (file_put_contents($dest, $binary) === false) {
            doc_json_response(500, ['success' => false, 'message' => 'No se pudo guardar la copia del PDF']);
        }

        // Guardamos también un archivo JSON con METADATOS del documento generado.
        // Esto permite saber después: qué plantilla se usó, quién lo generó,
        // cuándo, y qué campos se rellenaron. Es útil para auditoría.
        $meta = [
            'template_key' => $template_key,      // Identificador interno de la plantilla
            'template_name' => $template_name,    // Nombre legible (ej: "Contrato Alquiler")
            'entity_type' => $entity_type,        // 'cliente' o 'propiedad'
            'entity_id' => $entity_id,            // ID de la entidad
            'generated_at' => date('c'),          // Fecha ISO 8601 de generación
            'generated_by' => $_SESSION['usuario']['email'] ?? 'sistema', // Quién lo generó
            'fields' => $fields,                  // Campos rellenados en la plantilla
            'pdf_file' => $file_name,             // Nombre del archivo PDF guardado
        ];
        // Guardamos el JSON junto al PDF (mismo nombre + .json)
        file_put_contents($dest . '.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        doc_json_response(200, [
            'success' => true,
            'message' => 'PDF generado y copia guardada',
            'download_name' => $file_name,
        ]);
    }

    // Si llegamos aquí, la acción no coincidió con ninguna conocida
    doc_json_response(400, ['success' => false, 'message' => 'Acción no válida']);
} catch (Throwable $e) {
    // Capturamos CUALQUIER error (Throwable incluye tanto Exception como Error)
    // y respondemos de forma segura sin revelar detalles internos.
    doc_json_response(500, ['success' => false, 'message' => 'Error interno del servidor.']);
}
