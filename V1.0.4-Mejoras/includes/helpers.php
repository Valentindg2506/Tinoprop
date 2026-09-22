<?php
/**
 * Funciones auxiliares del CRM
 */

/**
 * Sanitizar entrada de texto
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Obtener valor POST sanitizado
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? sanitize($_POST[$key]) : $default;
}

/**
 * Obtener valor GET sanitizado
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? sanitize($_GET[$key]) : $default;
}

/**
 * Formatear precio en euros
 */
function formatPrecio($precio) {
    return number_format($precio, 2, ',', '.') . ' &euro;';
}

/**
 * Formatear fecha a formato español
 */
function formatFecha($fecha) {
    if (empty($fecha)) return '-';
    $dt = new DateTime($fecha);
    return $dt->format('d/m/Y');
}

/**
 * Formatear fecha y hora
 */
function formatFechaHora($fecha) {
    if (empty($fecha)) return '-';
    $dt = new DateTime($fecha);
    return $dt->format('d/m/Y H:i');
}

/**
 * Formatear superficie
 */
function formatSuperficie($metros) {
    if (empty($metros)) return '-';
    return number_format($metros, 2, ',', '.') . ' m&sup2;';
}

/**
 * Generar referencia unica para propiedad
 */
function generarReferencia() {
    $db = getDB();
    do {
        $ref = 'INM-' . strtoupper(substr(uniqid(), -6));
        $stmt = $db->prepare("SELECT COUNT(*) FROM propiedades WHERE referencia = ?");
        $stmt->execute([$ref]);
    } while ($stmt->fetchColumn() > 0);
    return $ref;
}

/**
 * Subir imagen
 */
function uploadImage($file, $dir = 'propiedades') {
    $uploadDir = UPLOAD_DIR . $dir . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir el archivo'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['error' => 'El archivo excede el tamano maximo permitido (10MB)'];
    }

    // Verificar MIME type real (no confiar en el navegador)
    $realMime = getFileMimeType($file['tmp_name']);
    if (!in_array($realMime, ALLOWED_IMAGE_TYPES)) {
        return ['error' => 'Tipo de archivo no permitido. Solo JPG, PNG y WebP. Detectado: ' . $realMime];
    }

    // Verificar que es una imagen real
    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        return ['error' => 'El archivo no es una imagen valida'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $ext = 'jpg'; // Forzar extension segura
    }
    $filename = uniqid('img_') . '_' . time() . '.' . $ext;
    $fullPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        // Redimensionar si es necesario
        resizeImage($fullPath);
        return ['success' => true, 'filename' => $dir . '/' . $filename];
    }

    return ['error' => 'No se pudo guardar el archivo'];
}

/**
 * Subir documento
 */
function uploadDocument($file) {
    $uploadDir = UPLOAD_DIR . 'documentos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Error al subir el archivo'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['error' => 'El archivo excede el tamano maximo'];
    }

    // Verificar MIME type real
    $realMime = getFileMimeType($file['tmp_name']);
    $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
    if (!in_array($realMime, $allowedTypes)) {
        return ['error' => 'Tipo de archivo no permitido. Detectado: ' . $realMime];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        return ['error' => 'Extension de archivo no permitida'];
    }
    $filename = uniqid('doc_') . '_' . time() . '.' . $ext;

    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['success' => true, 'filename' => 'documentos/' . $filename, 'size' => $file['size']];
    }

    return ['error' => 'No se pudo guardar el archivo'];
}

/**
 * Eliminar archivo subido
 */
function deleteUpload($filename) {
    $filepath = UPLOAD_DIR . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}

/**
 * Paginacion
 */
function paginate($total, $perPage = 20, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

/**
 * Renderizar paginacion HTML
 */
function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';

    $html = '<nav><ul class="pagination justify-content-center">';

    // Anterior
    if ($pagination['current_page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($pagination['current_page'] - 1) . '">&laquo;</a></li>';
    }

    // Paginas
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }

    // Siguiente
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($pagination['current_page'] + 1) . '">&raquo;</a></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Mensaje flash (session-based)
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Crear notificaciones automaticas para prospectos con contacto vencido o para hoy.
 * Evita duplicados por usuario/prospecto en el mismo dia.
 */
function generarNotificacionesProspectosVencidos($userId = null) {
    try {
        if ($userId === null && function_exists('currentUserId')) {
            $userId = intval(currentUserId());
        }
        $userId = intval($userId);
        if ($userId <= 0) {
            return;
        }

        $db = getDB();
        $isAdm = function_exists('isAdmin') ? isAdmin() : false;

        $sql = "SELECT p.id, p.nombre, p.referencia, p.fecha_proximo_contacto
                FROM prospectos p
                                WHERE p.activo = 1
                                    AND p.fecha_proximo_contacto IS NOT NULL
                                    AND p.fecha_proximo_contacto <= CURDATE()
                  AND p.etapa NOT IN ('captado','descartado')";
        $params = [];

        if (!$isAdm) {
            $sql .= " AND p.agente_id = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY p.fecha_proximo_contacto ASC LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return;
        }

        $stmtInsert = $db->prepare(
            "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, enlace, leida, created_at)
             SELECT ?, ?, ?, 'aviso', ?, 0, NOW()
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM notificaciones
                 WHERE usuario_id = ?
                   AND enlace = ?
                   AND DATE(created_at) = CURDATE()
             )"
        );

        foreach ($rows as $r) {
            $pid = intval($r['id']);
            if ($pid <= 0) {
                continue;
            }

            $nombre = trim((string)($r['nombre'] ?? 'Prospecto'));
            if ($nombre === '') {
                $nombre = 'Prospecto';
            }
            $ref = trim((string)($r['referencia'] ?? ''));
            $fechaRaw = (string)($r['fecha_proximo_contacto'] ?? '');

            $dias = 0;
            $esHoy = false;
            if ($fechaRaw !== '') {
                try {
                    $f = new DateTime($fechaRaw);
                    $h = new DateTime('today');
                    $dias = (int)$h->diff($f)->days;
                    $esHoy = ($f->format('Y-m-d') === $h->format('Y-m-d'));
                } catch (Exception $e) {
                    $dias = 0;
                    $esHoy = false;
                }
            }

            $etiquetaRef = $ref !== '' ? (' (' . $ref . ')') : '';
            $titulo = 'Recordatorio: contactar prospecto ' . $nombre . $etiquetaRef;
            if ($esHoy) {
                $mensaje = 'Seguimiento programado para hoy.';
            } else {
                $mensaje = $dias > 0
                    ? ('Seguimiento vencido hace ' . $dias . ' dia(s).')
                    : 'Seguimiento vencido.';
            }
            $enlace = APP_URL . '/modules/prospectos/ver.php?id=' . $pid;

            $stmtInsert->execute([$userId, $titulo, $mensaje, $enlace, $userId, $enlace]);
        }
    } catch (Throwable $e) {
        // No interrumpir la UI si falla la generacion de recordatorios.
    }
}

/**
 * Token CSRF
 */
function safeRedirectTarget(string $target, string $fallback = ''): string {
    $target = trim($target);
    $fallback = $fallback !== '' ? $fallback : (defined('APP_URL') ? rtrim(APP_URL, '/') . '/index.php' : '/');
    $allowedHosts = array_filter(array_map('trim', explode(',', getenv('APP_ALLOWED_REDIRECT_HOSTS') ?: '')));

    if ($target === '') {
        return $fallback;
    }

    if (str_starts_with($target, '/')) {
        return (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . $target;
    }

    if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
        $appHost = defined('APP_URL') ? parse_url(APP_URL, PHP_URL_HOST) : null;
        $targetHost = parse_url($target, PHP_URL_HOST);
        if ($appHost && $targetHost && strcasecmp($appHost, $targetHost) === 0) {
            return $target;
        }
        foreach ($allowedHosts as $host) {
            if ($targetHost && strcasecmp($host, $targetHost) === 0) {
                return $target;
            }
        }
        return $fallback;
    }

    return (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/' . ltrim($target, '/');
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('Token de seguridad invalido. Recarga la pagina e intenta de nuevo.');
    }
}

/**
 * Provincias de España
 */
function getProvincias() {
    return [
        'A Coruña', 'Alava', 'Albacete', 'Alicante', 'Almeria', 'Asturias', 'Avila',
        'Badajoz', 'Barcelona', 'Bizkaia', 'Burgos', 'Caceres', 'Cadiz', 'Cantabria',
        'Castellon', 'Ciudad Real', 'Cordoba', 'Cuenca', 'Gipuzkoa', 'Girona', 'Granada',
        'Guadalajara', 'Huelva', 'Huesca', 'Illes Balears', 'Jaen', 'La Rioja',
        'Las Palmas', 'Leon', 'Lleida', 'Lugo', 'Madrid', 'Malaga', 'Murcia', 'Navarra',
        'Ourense', 'Palencia', 'Pontevedra', 'Salamanca', 'Santa Cruz de Tenerife',
        'Segovia', 'Sevilla', 'Soria', 'Tarragona', 'Teruel', 'Toledo', 'Valencia',
        'Valladolid', 'Zamora', 'Zaragoza', 'Ceuta', 'Melilla'
    ];
}

/**
 * Tipos de propiedad con etiquetas
 */
function getTiposPropiedad() {
    return [
        'piso' => 'Piso', 'casa' => 'Casa', 'chalet' => 'Chalet',
        'adosado' => 'Adosado', 'atico' => 'Atico', 'duplex' => 'Duplex',
        'estudio' => 'Estudio', 'local' => 'Local comercial', 'oficina' => 'Oficina',
        'nave' => 'Nave industrial', 'terreno' => 'Terreno', 'garaje' => 'Garaje',
        'trastero' => 'Trastero', 'edificio' => 'Edificio', 'otro' => 'Otro'
    ];
}

/**
 * Obtener conteo para el dashboard
 */
function getCount($table, $where = '1=1', $params = []) {
    // Endurece el identificador de tabla para evitar inyeccion SQL.
    if (!is_string($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        if (function_exists('logError')) {
            logError('getCount invalid table identifier', ['table' => $table]);
        } else {
            error_log('getCount invalid table identifier');
        }
        return 0;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE $where");
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Forzar HTTPS en produccion
 */
function forceHTTPS() {
    if (APP_ENV === 'production' && empty($_SERVER['HTTPS']) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https') {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Redimensionar imagen manteniendo proporcion
 */
function resizeImage($sourcePath, $maxWidth = null, $maxHeight = null, $quality = null) {
    $maxWidth = $maxWidth ?? IMAGE_MAX_WIDTH;
    $maxHeight = $maxHeight ?? IMAGE_MAX_HEIGHT;
    $quality = $quality ?? IMAGE_QUALITY;

    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) return false;

    $origWidth = $imageInfo[0];
    $origHeight = $imageInfo[1];
    $type = $imageInfo[2];

    // No redimensionar si ya es suficientemente pequeña
    if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
        return true;
    }

    // Calcular nuevas dimensiones manteniendo proporcion
    $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
    $newWidth = intval($origWidth * $ratio);
    $newHeight = intval($origHeight * $ratio);

    // Crear imagen segun tipo
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $source = imagecreatefromwebp($sourcePath);
            } else {
                return true; // Si no soporta webp, dejarlo como esta
            }
            break;
        default:
            return true;
    }

    if (!$source) return false;

    // Crear nueva imagen redimensionada
    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preservar transparencia para PNG
    if ($type === IMAGETYPE_PNG) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }

    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Guardar
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($resized, $sourcePath, $quality);
            break;
        case IMAGETYPE_PNG:
            imagepng($resized, $sourcePath, intval(9 - ($quality / 100 * 9)));
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                imagewebp($resized, $sourcePath, $quality);
            }
            break;
    }

    imagedestroy($source);
    imagedestroy($resized);
    return true;
}

/**
 * Verificar MIME type real del archivo (no confiar en $_FILES['type'])
 */
function getFileMimeType($filepath) {
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        return $mime;
    }
    return mime_content_type($filepath);
}
