<?php
/*
 * Archivo: inc/helpers.php
 * Rol: utilidades transversales del backend.
 * Grupos de funciones:
 * 1) Utilidades de salida/formato (escape HTML, precio, clases de estado).
 * 2) Mensajería flash y validaciones de formulario.
 * 3) Preferencias por usuario (persistidas en BD).
 * 4) CRUD de recordatorios.
 * 5) Gestión de imágenes de propiedades.
 * 6) Creación/ajuste de la tabla de propiedades scrapeadas.
 * 7) CSRF tokens.
 * 8) Historial de actividad (log).
 * 9) Notificaciones inteligentes.
 * 10) Caché de consultas.
 * 11) Exportación CSV.
 * 12) Breadcrumbs.
 */

/* Escapa texto para mostrarlo en HTML sin ejecutar contenido peligroso. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* Formatea un precio con separador de miles y sufijo de periodo (si aplica). */
function format_price(float $precio, string $moneda, ?string $periodo): string
{
    $formato = number_format($precio, 0, ',', '.');
    $texto = $formato . ' ' . $moneda;

    if (!empty($periodo)) {
        $texto .= '/' . $periodo;
    }

    return $texto;
}

function map_estado_clase(string $estado): string
{
    return strtolower(str_replace(' ', '_', $estado));
}

/* Determina la sección de origen de una propiedad para construir el enlace de retorno. */
function obtener_origen_propiedad(string $operacion, string $equipo): string
{
    if ($operacion === 'alquiler') {
        return $equipo === 'comprador' ? 'alquileres-comprador' : 'alquileres-vendedor';
    }

    return $equipo === 'comprador' ? 'propiedades-comprador' : 'propiedades-vendedor';
}

function flash_set(string $key, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $message;
}

/* Lee un mensaje flash de sesión y lo elimina (uso de una sola vez). */
function flash_get(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $mensaje = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $mensaje;
}

function validar_requerido(string $valor): bool
{
    return trim($valor) !== '';
}

function validar_email(string $valor): bool
{
    return filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
}

/*
 * Reglas de contraseña alineadas con Proyecto-Entornos:
 * - Longitud entre 8 y 16 caracteres.
 * - Al menos una letra mayúscula.
 * - Al menos un símbolo (incluye guion bajo por compatibilidad).
 */
function validar_password_segura(string $valor): bool
{
    return preg_match('/^(?=.*[A-Z])(?=.*[\W_]).{8,16}$/', $valor) === 1;
}

function validar_telefono(string $valor): bool
{
    return (bool) preg_match('/^[0-9+()\s-]{6,20}$/', $valor);
}

function validar_enum(string $valor, array $permitidos): bool
{
    return in_array($valor, $permitidos, true);
}

/* Crea la tabla de preferencias de usuario si todavía no existe. */
function preferencias_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS preferencias_usuario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            clave VARCHAR(120) NOT NULL,
            valor LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_usuario_clave (usuario_id, clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

function preferencias_usuario_get(PDO $pdo, string $clave): ?string
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('SELECT valor FROM preferencias_usuario WHERE usuario_id = :usuario_id AND clave = :clave LIMIT 1');
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
    ]);

    $valor = $stmt->fetchColumn();
    if ($valor === false) {
        return null;
    }

    return (string) $valor;
}

/* Inserta o actualiza una preferencia clave/valor para el usuario de sesión. */
function preferencias_usuario_set(PDO $pdo, string $clave, string $valor): void
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO preferencias_usuario (usuario_id, clave, valor)
         VALUES (:usuario_id, :clave, :valor)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
        'valor' => $valor,
    ]);
}

function preferencias_usuario_delete(PDO $pdo, string $clave): void
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM preferencias_usuario WHERE usuario_id = :usuario_id AND clave = :clave');
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
    ]);
}

/* Crea la tabla de recordatorios si no existe. */
function recordatorios_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS recordatorios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            descripcion TEXT NOT NULL,
            fecha_recordatorio DATE NOT NULL,
            hora_recordatorio TIME,
            prospecto_id INT,
            estado VARCHAR(20) DEFAULT "pendiente",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_fecha (fecha_recordatorio),
            INDEX idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

function recordatorio_crear(PDO $pdo, string $tipo, string $descripcion, string $fecha, ?string $hora, ?int $prospecto_id): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO recordatorios (usuario_id, tipo, descripcion, fecha_recordatorio, hora_recordatorio, prospecto_id, estado)
         VALUES (:usuario_id, :tipo, :descripcion, :fecha, :hora, :prospecto_id, "pendiente")'
    );
    
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'fecha' => $fecha,
        'hora' => $hora,
        'prospecto_id' => $prospecto_id,
    ]);

    return (int) $pdo->lastInsertId();
}

/* Devuelve todos los recordatorios del usuario para una fecha concreta. */
function recordatorios_por_fecha(PDO $pdo, string $fecha): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM recordatorios 
         WHERE usuario_id = :usuario_id AND fecha_recordatorio = :fecha
         ORDER BY hora_recordatorio ASC'
    );
    
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'fecha' => $fecha,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function recordatorios_por_mes(PDO $pdo, int $mes, int $ano): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM recordatorios 
         WHERE usuario_id = :usuario_id 
         AND YEAR(fecha_recordatorio) = :ano
         AND MONTH(fecha_recordatorio) = :mes
         ORDER BY fecha_recordatorio ASC, hora_recordatorio ASC'
    );
    
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'mes' => $mes,
        'ano' => $ano,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Recupera un recordatorio por ID validando que pertenezca al usuario de sesión. */
function recordatorio_obtener(PDO $pdo, int $id): ?array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE id = :id AND usuario_id = :usuario_id');
    $stmt->execute([
        'id' => $id,
        'usuario_id' => $usuario_id,
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ?: null;
}

function recordatorio_actualizar(PDO $pdo, int $id, string $tipo, string $descripcion, string $fecha, ?string $hora, ?int $prospecto_id, string $estado): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'UPDATE recordatorios 
         SET tipo = :tipo, descripcion = :descripcion, fecha_recordatorio = :fecha, 
             hora_recordatorio = :hora, prospecto_id = :prospecto_id, estado = :estado
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    
    $resultado = $stmt->execute([
        'id' => $id,
        'usuario_id' => $usuario_id,
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'fecha' => $fecha,
        'hora' => $hora,
        'prospecto_id' => $prospecto_id,
        'estado' => $estado,
    ]);

    return $resultado && $stmt->rowCount() > 0;
}

/* Elimina un recordatorio por ID para el usuario autenticado. */
function recordatorio_eliminar(PDO $pdo, int $id): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM recordatorios WHERE id = :id AND usuario_id = :usuario_id');
    $resultado = $stmt->execute([
        'id' => $id,
        'usuario_id' => $usuario_id,
    ]);

    return $resultado && $stmt->rowCount() > 0;
}

/* ===== FUNCIONES PARA IMÁGENES DE PROPIEDADES ===== */

/* Crea la tabla de imágenes de propiedades y su relación con propiedades. */
function imagenes_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS imagenes_propiedades (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                propiedad_id INT UNSIGNED NOT NULL,
                nombre_archivo VARCHAR(255) NOT NULL,
                nombre_original VARCHAR(255),
                ruta_archivo VARCHAR(500) NOT NULL,
                es_principal BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_propiedad_id (propiedad_id),
                FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $tabla_lista = true;
    } catch (PDOException $e) {
        // La tabla probablemente ya existe, ignorar error
        $tabla_lista = true;
    }
}

function imagen_subir(PDO $pdo, int $propiedad_id, array $archivo): ?int
{
    imagenes_asegurar_tabla($pdo);

    // Validar que el archivo sea una imagen
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($archivo['type'] ?? '', $tipos_permitidos)) {
        return null;
    }

    // Crear directorio si no existe
    $dir_imagenes = __DIR__ . '/../storage/uploads/propiedades';
    if (!is_dir($dir_imagenes)) {
        mkdir($dir_imagenes, 0755, true);
    }

    // Generar nombre único para el archivo
    $extension = pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION);
    $nombre_archivo = uniqid('img_' . $propiedad_id . '_') . '.' . $extension;
    $ruta_destino = $dir_imagenes . '/' . $nombre_archivo;

    // Mover archivo subido
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        return null;
    }

    // Guardar en BD
    $stmt = $pdo->prepare(
        'INSERT INTO imagenes_propiedades (propiedad_id, nombre_archivo, nombre_original, ruta_archivo)
         VALUES (:propiedad_id, :nombre_archivo, :nombre_original, :ruta_archivo)'
    );

    $resultado = $stmt->execute([
        'propiedad_id' => $propiedad_id,
        'nombre_archivo' => $nombre_archivo,
        'nombre_original' => $archivo['name'] ?? '',
        'ruta_archivo' => '/storage/uploads/propiedades/' . $nombre_archivo,
    ]);

    return $resultado ? (int) $pdo->lastInsertId() : null;
}

/* Devuelve todas las imágenes de una propiedad priorizando la principal. */
function imagenes_obtener_propiedad(PDO $pdo, int $propiedad_id): array
{
    imagenes_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, ruta_archivo, nombre_original, es_principal
         FROM imagenes_propiedades
         WHERE propiedad_id = :propiedad_id
         ORDER BY es_principal DESC, created_at ASC'
    );

    $stmt->execute(['propiedad_id' => $propiedad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function imagen_obtener_principal(PDO $pdo, int $propiedad_id): ?array
{
    imagenes_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT ruta_archivo, nombre_original FROM imagenes_propiedades
         WHERE propiedad_id = :propiedad_id
         ORDER BY es_principal DESC, created_at ASC
         LIMIT 1'
    );

    $stmt->execute(['propiedad_id' => $propiedad_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ?: null;
}

/* Elimina imagen en BD y, si existe, su archivo físico en disco. */
function imagen_eliminar(PDO $pdo, int $id): bool
{
    imagenes_asegurar_tabla($pdo);

    // Obtener archivo antes de eliminar
    $stmt = $pdo->prepare('SELECT ruta_archivo FROM imagenes_propiedades WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$imagen) {
        return false;
    }

    // Eliminar archivo físico
    $archivo = __DIR__ . '/..' . $imagen['ruta_archivo'];
    if (file_exists($archivo)) {
        unlink($archivo);
    }

    // Eliminar de BD
    $stmt = $pdo->prepare('DELETE FROM imagenes_propiedades WHERE id = :id');
    $resultado = $stmt->execute(['id' => $id]);

    return $resultado && $stmt->rowCount() > 0;
}

function imagen_marcar_principal(PDO $pdo, int $imagen_id): bool
{
    imagenes_asegurar_tabla($pdo);

    // Obtener propiedad_id de la imagen
    $stmt = $pdo->prepare('SELECT propiedad_id FROM imagenes_propiedades WHERE id = :id');
    $stmt->execute(['id' => $imagen_id]);
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$imagen) {
        return false;
    }

    // Desmarcar todas las imágenes de esta propiedad
    $stmt = $pdo->prepare('UPDATE imagenes_propiedades SET es_principal = FALSE WHERE propiedad_id = :propiedad_id');
    $stmt->execute(['propiedad_id' => $imagen['propiedad_id']]);

    // Marcar esta como principal
    $stmt = $pdo->prepare('UPDATE imagenes_propiedades SET es_principal = TRUE WHERE id = :id');
    $resultado = $stmt->execute(['id' => $imagen_id]);

    return $resultado && $stmt->rowCount() > 0;
}

/* Crea/actualiza estructura de la tabla cacheada de propiedades scrapeadas. */
function scraped_propiedades_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS scraped_propiedades (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            fuente VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            tipo VARCHAR(100) DEFAULT NULL,
            operacion VARCHAR(50) DEFAULT NULL,
            precio DECIMAL(12,2) DEFAULT NULL,
            moneda VARCHAR(10) DEFAULT "EUR",
            ubicacion VARCHAR(200) DEFAULT NULL,
            zona VARCHAR(120) DEFAULT NULL,
            ciudad VARCHAR(80) DEFAULT NULL,
            provincia VARCHAR(80) DEFAULT NULL,
            direccion VARCHAR(255) DEFAULT NULL,
            habitaciones TINYINT DEFAULT NULL,
            banos TINYINT DEFAULT NULL,
            metros INT DEFAULT NULL,
            descripcion TEXT DEFAULT NULL,
            imagen_url VARCHAR(500) DEFAULT NULL,
            imagenes_json LONGTEXT DEFAULT NULL,
            ascensor TINYINT DEFAULT NULL,
            url VARCHAR(400) NOT NULL,
            raw_hash CHAR(64) NOT NULL,
            scrape_run VARCHAR(80) DEFAULT NULL,
            scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_url (url),
            KEY idx_ciudad (ciudad),
            KEY idx_precio (precio),
            KEY idx_zona (zona),
            KEY idx_operacion (operacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    try {
        $pdo->exec('ALTER TABLE scraped_propiedades ADD COLUMN imagen_url VARCHAR(500) DEFAULT NULL');
    } catch (PDOException $e) {
        // Duplicate column => ignore
        if ($e->getCode() !== '42S21') {
            throw $e;
        }
    }

    try {
        $pdo->exec('ALTER TABLE scraped_propiedades ADD COLUMN imagenes_json LONGTEXT DEFAULT NULL');
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S21') {
            throw $e;
        }
    }

    try {
        $pdo->exec('ALTER TABLE scraped_propiedades ADD COLUMN ascensor TINYINT DEFAULT NULL');
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S21') {
            throw $e;
        }
    }

    $tabla_lista = true;
}

/* ===== FUNCIONES PARA VISITAS ===== */

/* Crea la tabla de visitas si no existe. */
function visitas_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS visitas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            equipo ENUM("vendedor","comprador") NOT NULL,
            propiedad_id INT UNSIGNED DEFAULT NULL,
            cliente_id INT UNSIGNED DEFAULT NULL,
            fecha_visita DATE NOT NULL,
            hora_visita TIME DEFAULT NULL,
            estado ENUM("pendiente","realizada","cancelada") NOT NULL DEFAULT "pendiente",
            observaciones TEXT DEFAULT NULL,
            recordatorio_id INT DEFAULT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visitas_equipo (equipo),
            INDEX idx_visitas_fecha (fecha_visita),
            INDEX idx_visitas_estado (estado),
            INDEX idx_visitas_recordatorio (recordatorio_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

/* Crea una visita y opcionalmente sincroniza creando un recordatorio asociado. */
function visita_crear(PDO $pdo, string $equipo, ?int $propiedad_id, ?int $cliente_id, string $fecha, ?string $hora, string $observaciones, bool $crear_recordatorio = true): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    visitas_asegurar_tabla($pdo);

    $recordatorio_id = null;

    // Sincronización: crear recordatorio de tipo "Visita" automáticamente.
    if ($crear_recordatorio) {
        $desc_recordatorio = 'Visita programada';
        if ($propiedad_id) {
            $stmt = $pdo->prepare('SELECT titulo FROM propiedades WHERE id = :id');
            $stmt->execute(['id' => $propiedad_id]);
            $prop = $stmt->fetchColumn();
            if ($prop) {
                $desc_recordatorio = 'Visita: ' . $prop;
            }
        }
        if ($observaciones) {
            $desc_recordatorio .= ' - ' . $observaciones;
        }

        recordatorios_asegurar_tabla($pdo);
        $recordatorio_id = recordatorio_crear($pdo, 'Visita', $desc_recordatorio, $fecha, $hora, null);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO visitas (equipo, propiedad_id, cliente_id, fecha_visita, hora_visita, estado, observaciones, recordatorio_id, usuario_id)
         VALUES (:equipo, :propiedad_id, :cliente_id, :fecha, :hora, "pendiente", :observaciones, :recordatorio_id, :usuario_id)'
    );
    $stmt->execute([
        'equipo'          => $equipo,
        'propiedad_id'    => $propiedad_id ?: null,
        'cliente_id'      => $cliente_id ?: null,
        'fecha'           => $fecha,
        'hora'            => $hora ?: null,
        'observaciones'   => $observaciones,
        'recordatorio_id' => $recordatorio_id,
        'usuario_id'      => $usuario_id,
    ]);

    return (int) $pdo->lastInsertId();
}

/* Crea una visita desde un recordatorio de tipo "Visita" (sincronización inversa). */
function visita_crear_desde_recordatorio(PDO $pdo, int $recordatorio_id, string $equipo = 'vendedor'): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    visitas_asegurar_tabla($pdo);

    // Verificar que no exista ya una visita vinculada a este recordatorio.
    $check = $pdo->prepare('SELECT id FROM visitas WHERE recordatorio_id = :rid');
    $check->execute(['rid' => $recordatorio_id]);
    if ($check->fetch()) {
        return null; // Ya existe
    }

    // Obtener datos del recordatorio.
    $rec = recordatorio_obtener($pdo, $recordatorio_id);
    if (!$rec) {
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO visitas (equipo, propiedad_id, cliente_id, fecha_visita, hora_visita, estado, observaciones, recordatorio_id, usuario_id)
         VALUES (:equipo, NULL, NULL, :fecha, :hora, "pendiente", :observaciones, :recordatorio_id, :usuario_id)'
    );
    $stmt->execute([
        'equipo'          => $equipo,
        'fecha'           => $rec['fecha_recordatorio'],
        'hora'            => $rec['hora_recordatorio'],
        'observaciones'   => $rec['descripcion'],
        'recordatorio_id' => $recordatorio_id,
        'usuario_id'      => $usuario_id,
    ]);

    return (int) $pdo->lastInsertId();
}

/* Obtiene visitas para un equipo con datos de propiedad y cliente. */
function visitas_listar(PDO $pdo, string $equipo, string $filtro_estado = 'todos'): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    visitas_asegurar_tabla($pdo);

    $sql = 'SELECT v.*, 
                   p.titulo AS propiedad_titulo, p.referencia AS propiedad_ref, p.ubicacion AS propiedad_ubicacion,
                   c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.telefono AS cliente_telefono
            FROM visitas v
            LEFT JOIN propiedades p ON v.propiedad_id = p.id
            LEFT JOIN clientes c ON v.cliente_id = c.id
            WHERE v.equipo = :equipo AND v.usuario_id = :usuario_id';

    $params = ['equipo' => $equipo, 'usuario_id' => $usuario_id];

    if ($filtro_estado !== 'todos') {
        $sql .= ' AND v.estado = :estado';
        $params['estado'] = $filtro_estado;
    }

    $sql .= ' ORDER BY v.fecha_visita ASC, v.hora_visita ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Actualiza estado de una visita y sincroniza con su recordatorio vinculado. */
function visita_actualizar_estado(PDO $pdo, int $id, string $estado): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    visitas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('UPDATE visitas SET estado = :estado WHERE id = :id AND usuario_id = :usuario_id');
    $stmt->execute(['estado' => $estado, 'id' => $id, 'usuario_id' => $usuario_id]);
    $ok = $stmt->rowCount() > 0;

    // Sincronizar estado con recordatorio vinculado
    if ($ok) {
        $stmt2 = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id');
        $stmt2->execute(['id' => $id]);
        $rid = $stmt2->fetchColumn();

        if ($rid) {
            $estado_rec = 'pendiente';
            if ($estado === 'realizada') $estado_rec = 'completado';
            if ($estado === 'cancelada') $estado_rec = 'cancelado';

            $pdo->prepare('UPDATE recordatorios SET estado = :estado WHERE id = :id AND usuario_id = :uid')
                ->execute(['estado' => $estado_rec, 'id' => $rid, 'uid' => $usuario_id]);
        }
    }

    return $ok;
}

/* Actualiza todos los campos de una visita. */
function visita_actualizar(PDO $pdo, int $id, ?int $propiedad_id, ?int $cliente_id, string $fecha, ?string $hora, string $estado, string $observaciones): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    visitas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'UPDATE visitas 
         SET propiedad_id = :propiedad_id, cliente_id = :cliente_id, fecha_visita = :fecha, 
             hora_visita = :hora, estado = :estado, observaciones = :observaciones
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    $resultado = $stmt->execute([
        'propiedad_id'  => $propiedad_id ?: null,
        'cliente_id'    => $cliente_id ?: null,
        'fecha'         => $fecha,
        'hora'          => $hora ?: null,
        'estado'        => $estado,
        'observaciones' => $observaciones,
        'id'            => $id,
        'usuario_id'    => $usuario_id,
    ]);

    $ok = $resultado && $stmt->rowCount() > 0;

    // Sincronizar cambios con recordatorio vinculado
    if ($ok) {
        $stmt2 = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id');
        $stmt2->execute(['id' => $id]);
        $rid = $stmt2->fetchColumn();

        if ($rid) {
            $estado_rec = 'pendiente';
            if ($estado === 'realizada') $estado_rec = 'completado';
            if ($estado === 'cancelada') $estado_rec = 'cancelado';

            $desc = 'Visita programada';
            if ($propiedad_id) {
                $stmtP = $pdo->prepare('SELECT titulo FROM propiedades WHERE id = :id');
                $stmtP->execute(['id' => $propiedad_id]);
                $titulo = $stmtP->fetchColumn();
                if ($titulo) $desc = 'Visita: ' . $titulo;
            }
            if ($observaciones) $desc .= ' - ' . $observaciones;

            $pdo->prepare(
                'UPDATE recordatorios SET tipo = "Visita", descripcion = :desc, fecha_recordatorio = :fecha, 
                 hora_recordatorio = :hora, estado = :estado WHERE id = :id AND usuario_id = :uid'
            )->execute([
                'desc'   => $desc,
                'fecha'  => $fecha,
                'hora'   => $hora ?: null,
                'estado' => $estado_rec,
                'id'     => $rid,
                'uid'    => $usuario_id,
            ]);
        }
    }

    return $ok;
}

/* Elimina una visita y opcionalmente su recordatorio vinculado. */
function visita_eliminar(PDO $pdo, int $id, bool $eliminar_recordatorio = true): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    visitas_asegurar_tabla($pdo);

    // Obtener recordatorio vinculado antes de eliminar
    $rid = null;
    if ($eliminar_recordatorio) {
        $stmt = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $usuario_id]);
        $rid = $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare('DELETE FROM visitas WHERE id = :id AND usuario_id = :usuario_id');
    $resultado = $stmt->execute(['id' => $id, 'usuario_id' => $usuario_id]);
    $ok = $resultado && $stmt->rowCount() > 0;

    // Limpiar recordatorio vinculado
    if ($ok && $rid) {
        recordatorio_eliminar($pdo, (int) $rid);
    }

    return $ok;
}


/* =============================================
   OFERTAS — CRUD para gestión de ofertas por propiedad
   ============================================= */

/* Crea la tabla de ofertas si no existe. */
function ofertas_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ofertas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            propiedad_id INT UNSIGNED NOT NULL,
            cliente_id INT UNSIGNED DEFAULT NULL,
            nombre_ofertante VARCHAR(200) DEFAULT NULL,
            importe DECIMAL(12,2) NOT NULL,
            fecha_oferta DATE NOT NULL,
            estado ENUM("pendiente","aceptada","rechazada","contraoferta") NOT NULL DEFAULT "pendiente",
            contraoferta_importe DECIMAL(12,2) DEFAULT NULL,
            notas TEXT DEFAULT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ofertas_propiedad (propiedad_id),
            INDEX idx_ofertas_estado (estado),
            INDEX idx_ofertas_fecha (fecha_oferta)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

/* Crea una nueva oferta. */
function oferta_crear(PDO $pdo, int $propiedad_id, ?int $cliente_id, ?string $nombre_ofertante, float $importe, string $fecha, string $notas = ''): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    ofertas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO ofertas (propiedad_id, cliente_id, nombre_ofertante, importe, fecha_oferta, estado, notas, usuario_id)
         VALUES (:propiedad_id, :cliente_id, :nombre_ofertante, :importe, :fecha, "pendiente", :notas, :usuario_id)'
    );
    $stmt->execute([
        'propiedad_id'     => $propiedad_id,
        'cliente_id'       => $cliente_id ?: null,
        'nombre_ofertante' => $nombre_ofertante ?: null,
        'importe'          => $importe,
        'fecha'            => $fecha,
        'notas'            => $notas,
        'usuario_id'       => $usuario_id,
    ]);

    return (int) $pdo->lastInsertId();
}

/* Lista ofertas con datos de propiedad y cliente. */
function ofertas_listar(PDO $pdo, string $filtro_estado = 'todos'): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    ofertas_asegurar_tabla($pdo);

    $sql = 'SELECT o.*,
                   p.titulo AS propiedad_titulo, p.referencia AS propiedad_ref, p.precio AS propiedad_precio, p.ubicacion AS propiedad_ubicacion,
                   c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.telefono AS cliente_telefono
            FROM ofertas o
            LEFT JOIN propiedades p ON o.propiedad_id = p.id
            LEFT JOIN clientes c ON o.cliente_id = c.id
            WHERE o.usuario_id = :usuario_id';

    $params = ['usuario_id' => $usuario_id];

    if ($filtro_estado !== 'todos') {
        $sql .= ' AND o.estado = :estado';
        $params['estado'] = $filtro_estado;
    }

    $sql .= ' ORDER BY o.fecha_oferta DESC, o.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Actualiza estado de una oferta (y opcionalmente la contraoferta). */
function oferta_cambiar_estado(PDO $pdo, int $id, string $estado, ?float $contraoferta_importe = null): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    ofertas_asegurar_tabla($pdo);

    if ($estado === 'contraoferta' && $contraoferta_importe !== null) {
        $stmt = $pdo->prepare('UPDATE ofertas SET estado = :estado, contraoferta_importe = :contra WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['estado' => $estado, 'contra' => $contraoferta_importe, 'id' => $id, 'uid' => $usuario_id]);
    } else {
        $stmt = $pdo->prepare('UPDATE ofertas SET estado = :estado WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['estado' => $estado, 'id' => $id, 'uid' => $usuario_id]);
    }

    return $stmt->rowCount() > 0;
}

/* Actualiza todos los campos de una oferta. */
function oferta_actualizar(PDO $pdo, int $id, int $propiedad_id, ?int $cliente_id, ?string $nombre_ofertante, float $importe, string $fecha, string $estado, ?float $contraoferta_importe, string $notas): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    ofertas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'UPDATE ofertas
         SET propiedad_id = :propiedad_id, cliente_id = :cliente_id, nombre_ofertante = :nombre,
             importe = :importe, fecha_oferta = :fecha, estado = :estado,
             contraoferta_importe = :contra, notas = :notas
         WHERE id = :id AND usuario_id = :uid'
    );
    $resultado = $stmt->execute([
        'propiedad_id' => $propiedad_id,
        'cliente_id'   => $cliente_id ?: null,
        'nombre'       => $nombre_ofertante ?: null,
        'importe'      => $importe,
        'fecha'        => $fecha,
        'estado'       => $estado,
        'contra'       => $contraoferta_importe,
        'notas'        => $notas,
        'id'           => $id,
        'uid'          => $usuario_id,
    ]);

    return $resultado && $stmt->rowCount() > 0;
}

/* Elimina una oferta. */
function oferta_eliminar(PDO $pdo, int $id): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    ofertas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM ofertas WHERE id = :id AND usuario_id = :uid');
    $resultado = $stmt->execute(['id' => $id, 'uid' => $usuario_id]);

    return $resultado && $stmt->rowCount() > 0;
}


/* =============================================
   7. CSRF TOKENS — Protección contra ataques CSRF
   ============================================= */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/* Verifica CSRF desde APIs (header X-CSRF-Token, JSON body o POST). */
function csrf_verify_api(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token) {
        $json = json_decode(file_get_contents('php://input'), true);
        $token = $json['_csrf_token'] ?? $_POST['_csrf_token'] ?? $_GET['_csrf_token'] ?? '';
    }
    if (empty($token) || empty($_SESSION['_csrf_token'])) return false;
    return hash_equals($_SESSION['_csrf_token'], $token);
}


/* =============================================
   8. HISTORIAL DE ACTIVIDAD
   ============================================= */

function actividad_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS actividad_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            usuario_nombre VARCHAR(100) DEFAULT NULL,
            accion VARCHAR(50) NOT NULL,
            entidad VARCHAR(50) NOT NULL,
            entidad_id INT UNSIGNED DEFAULT NULL,
            descripcion TEXT DEFAULT NULL,
            datos_extra JSON DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_act_usr (usuario_id),
            INDEX idx_act_ent (entidad, entidad_id),
            INDEX idx_act_fecha (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

function actividad_registrar(PDO $pdo, string $accion, string $entidad, ?int $entidad_id = null, string $descripcion = '', array $datos_extra = []): void
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    $nombre = $_SESSION['usuario']['nombre'] ?? 'Sistema';
    if ($uid <= 0) return;
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO actividad_log (usuario_id, usuario_nombre, accion, entidad, entidad_id, descripcion, datos_extra, ip)
         VALUES (:uid, :nombre, :accion, :entidad, :eid, :desc, :datos, :ip)'
    );
    $stmt->execute([
        'uid' => $uid, 'nombre' => $nombre, 'accion' => $accion,
        'entidad' => $entidad, 'eid' => $entidad_id, 'desc' => $descripcion,
        'datos' => !empty($datos_extra) ? json_encode($datos_extra) : null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function actividad_listar(PDO $pdo, int $limite = 20, int $offset = 0): array
{
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM actividad_log ORDER BY created_at DESC LIMIT :lim OFFSET :off');
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function actividad_contar(PDO $pdo): int
{
    actividad_asegurar_tabla($pdo);
    return (int) $pdo->query('SELECT COUNT(*) FROM actividad_log')->fetchColumn();
}


/* =============================================
   9. NOTIFICACIONES INTELIGENTES
   ============================================= */

function notificaciones_generar(PDO $pdo): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];
    $notifs = [];

    try {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM visitas WHERE fecha_visita = CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'info', 'icono' => '📅', 'texto' => "Tienes {$n} visita(s) hoy", 'enlace' => '?seccion=visitas-vendedor'];
    } catch (PDOException $e) {}

    try {
        ofertas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ofertas WHERE estado = "pendiente" AND fecha_oferta < DATE_SUB(CURDATE(), INTERVAL 5 DAY) AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'aviso', 'icono' => '⚠️', 'texto' => "{$n} oferta(s) pendiente(s) hace +5 días", 'enlace' => '?seccion=ofertas-vendedor'];
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE estado = "nuevo" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY) AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'aviso', 'icono' => '👤', 'texto' => "{$n} prospecto(s) sin contactar hace +2 semanas", 'enlace' => '?seccion=prospectos-vendedor'];
    } catch (PDOException $e) {}

    try {
        recordatorios_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE fecha_recordatorio = CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'info', 'icono' => '🔔', 'texto' => "{$n} recordatorio(s) para hoy", 'enlace' => '?seccion=recordatorios'];
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE fecha_recordatorio < CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'peligro', 'icono' => '🚨', 'texto' => "{$n} recordatorio(s) atrasado(s)", 'enlace' => '?seccion=recordatorios'];
    } catch (PDOException $e) {}

    return $notifs;
}


/* =============================================
   10. CACHÉ DE CONSULTAS
   ============================================= */

function cache_get(string $clave, int $ttl = 60): mixed
{
    if (!isset($_SESSION['_cache'][$clave])) return null;
    $e = $_SESSION['_cache'][$clave];
    if (time() - $e['ts'] > $ttl) { unset($_SESSION['_cache'][$clave]); return null; }
    return $e['valor'];
}

function cache_set(string $clave, mixed $valor): void
{
    $_SESSION['_cache'][$clave] = ['valor' => $valor, 'ts' => time()];
}

function cache_flush(): void { $_SESSION['_cache'] = []; }


/* =============================================
   11. EXPORTACIÓN CSV
   ============================================= */

function exportar_csv(array $datos, string $nombre = 'export.csv'): void
{
    if (empty($datos)) { header('Content-Type: text/plain'); echo 'Sin datos.'; exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, array_keys($datos[0]), ';');
    foreach ($datos as $fila) fputcsv($out, $fila, ';');
    fclose($out);
    exit;
}


/* =============================================
   12. BREADCRUMBS
   ============================================= */

function generar_breadcrumbs(string $seccion): array
{
    $migas = [['titulo' => 'Inicio', 'url' => '?seccion=dashboard']];
    $mapa = [
        'dashboard'             => [['titulo' => 'Dashboard']],
        'recordatorios'         => [['titulo' => 'Recordatorios']],
        'documentacion'         => [['titulo' => 'Documentación']],
        'configuracion'         => [['titulo' => 'Sistema'], ['titulo' => 'Configuración']],
        'clientes-vendedor'     => [['titulo' => 'Vendedor'], ['titulo' => 'Clientes']],
        'clientes-comprador'    => [['titulo' => 'Comprador'], ['titulo' => 'Clientes']],
        'prospectos-vendedor'   => [['titulo' => 'Vendedor'], ['titulo' => 'Prospectos']],
        'prospectos-comprador'  => [['titulo' => 'Comprador'], ['titulo' => 'Prospectos']],
        'propiedades-vendedor'  => [['titulo' => 'Vendedor'], ['titulo' => 'Propiedades']],
        'propiedades-comprador' => [['titulo' => 'Comprador'], ['titulo' => 'Propiedades']],
        'alquileres-vendedor'   => [['titulo' => 'Vendedor'], ['titulo' => 'Alquileres']],
        'alquileres-comprador'  => [['titulo' => 'Comprador'], ['titulo' => 'Alquileres']],
        'busqueda-avanzada'     => [['titulo' => 'Búsqueda Avanzada']],
        'proceso-vendedor'      => [['titulo' => 'Vendedor'], ['titulo' => 'Proceso']],
        'proceso-comprador'     => [['titulo' => 'Comprador'], ['titulo' => 'Proceso']],
        'visitas-vendedor'      => [['titulo' => 'Vendedor'], ['titulo' => 'Visitas']],
        'visitas-comprador'     => [['titulo' => 'Comprador'], ['titulo' => 'Visitas']],
        'ofertas-vendedor'      => [['titulo' => 'Vendedor'], ['titulo' => 'Ofertas']],
        'matching'              => [['titulo' => 'Matching']],
        'post-venta'            => [['titulo' => 'Post-Venta']],
        'importar-csv'          => [['titulo' => 'Sistema'], ['titulo' => 'Importar CSV']],
        'ver_propiedad'         => [['titulo' => 'Propiedades'], ['titulo' => 'Detalle']],
        'ver_cliente'           => [['titulo' => 'Clientes'], ['titulo' => 'Detalle']],
        'actividad'             => [['titulo' => 'Sistema'], ['titulo' => 'Historial']],
    ];
    if (isset($mapa[$seccion])) foreach ($mapa[$seccion] as $m) $migas[] = $m;
    return $migas;
}

function renderizar_breadcrumbs(string $seccion): string
{
    $migas = generar_breadcrumbs($seccion);
    $html = '<nav class="breadcrumbs" aria-label="Navegación"><ol>';
    $total = count($migas);
    foreach ($migas as $i => $m) {
        if ($i === $total - 1) {
            $html .= '<li class="breadcrumb_actual" aria-current="page">' . e($m['titulo']) . '</li>';
        } elseif (!empty($m['url'])) {
            $html .= '<li><a href="' . e($m['url']) . '">' . e($m['titulo']) . '</a></li>';
        } else {
            $html .= '<li>' . e($m['titulo']) . '</li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}


/* =============================================
   13. PAGINACIÓN
   ============================================= */

function paginar(int $total, int $por_pagina = 15, int $pagina_actual = 1): array
{
    $total_pag = max(1, (int) ceil($total / $por_pagina));
    $pagina_actual = max(1, min($pagina_actual, $total_pag));
    return [
        'total' => $total, 'por_pagina' => $por_pagina,
        'pagina_actual' => $pagina_actual, 'total_paginas' => $total_pag,
        'offset' => ($pagina_actual - 1) * $por_pagina,
        'tiene_anterior' => $pagina_actual > 1,
        'tiene_siguiente' => $pagina_actual < $total_pag,
    ];
}

function renderizar_paginacion(array $pag, string $base_url): string
{
    if ($pag['total_paginas'] <= 1) return '';
    $sep = str_contains($base_url, '?') ? '&' : '?';
    $html = '<nav class="paginacion"><ul>';
    if ($pag['tiene_anterior']) {
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=' . ($pag['pagina_actual'] - 1)) . '" class="pag_btn">← Anterior</a></li>';
    } else {
        $html .= '<li><span class="pag_btn pag_disabled">← Anterior</span></li>';
    }
    $rango = 2;
    $inicio = max(1, $pag['pagina_actual'] - $rango);
    $fin = min($pag['total_paginas'], $pag['pagina_actual'] + $rango);
    if ($inicio > 1) {
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=1') . '" class="pag_btn">1</a></li>';
        if ($inicio > 2) $html .= '<li><span class="pag_dots">…</span></li>';
    }
    for ($p = $inicio; $p <= $fin; $p++) {
        $html .= ($p === $pag['pagina_actual'])
            ? '<li><span class="pag_btn pag_activa">' . $p . '</span></li>'
            : '<li><a href="' . e($base_url . $sep . 'pagina=' . $p) . '" class="pag_btn">' . $p . '</a></li>';
    }
    if ($fin < $pag['total_paginas']) {
        if ($fin < $pag['total_paginas'] - 1) $html .= '<li><span class="pag_dots">…</span></li>';
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=' . $pag['total_paginas']) . '" class="pag_btn">' . $pag['total_paginas'] . '</a></li>';
    }
    if ($pag['tiene_siguiente']) {
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=' . ($pag['pagina_actual'] + 1)) . '" class="pag_btn">Siguiente →</a></li>';
    } else {
        $html .= '<li><span class="pag_btn pag_disabled">Siguiente →</span></li>';
    }
    $html .= '</ul></nav>';
    $html .= '<p class="pag_info">Página ' . $pag['pagina_actual'] . ' de ' . $pag['total_paginas'] . ' (' . $pag['total'] . ' registros)</p>';
    return $html;
}


/* =============================================
   14. BÚSQUEDA GLOBAL
   ============================================= */

function busqueda_global(PDO $pdo, string $termino, int $limite = 10): array
{
    $resultados = [];
    $like = '%' . $termino . '%';

    $stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email, tipo FROM clientes WHERE nombre LIKE :q OR apellido LIKE :q2 OR email LIKE :q3 OR telefono LIKE :q4 LIMIT :lim');
    $stmt->bindValue('q', $like); $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like); $stmt->bindValue('q4', $like);
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $resultados[] = ['tipo' => 'cliente', 'icono' => '👤', 'titulo' => $r['nombre'] . ' ' . $r['apellido'],
            'detalle' => $r['email'] . ' · ' . $r['telefono'],
            'url' => 'index.php?seccion=ver_cliente&id=' . $r['id'] . '&origen=clientes-' . $r['tipo']];
    }

    $stmt = $pdo->prepare('SELECT id, titulo, ubicacion, referencia, operacion, equipo FROM propiedades WHERE titulo LIKE :q OR ubicacion LIKE :q2 OR referencia LIKE :q3 OR direccion LIKE :q4 LIMIT :lim');
    $stmt->bindValue('q', $like); $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like); $stmt->bindValue('q4', $like);
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $origen = obtener_origen_propiedad($r['operacion'], $r['equipo']);
        $resultados[] = ['tipo' => 'propiedad', 'icono' => '🏠', 'titulo' => $r['titulo'],
            'detalle' => $r['ubicacion'] . ($r['referencia'] ? ' · ' . $r['referencia'] : ''),
            'url' => 'index.php?seccion=ver_propiedad&id=' . $r['id'] . '&origen=' . $origen];
    }

    try {
        $stmt = $pdo->prepare('SELECT id, nombre, telefono, tipo FROM prospectos WHERE nombre LIKE :q OR telefono LIKE :q2 LIMIT :lim');
        $stmt->bindValue('q', $like); $stmt->bindValue('q2', $like);
        $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = ['tipo' => 'prospecto', 'icono' => '📊', 'titulo' => $r['nombre'],
                'detalle' => $r['telefono'] ?? '', 'url' => 'index.php?seccion=prospectos-' . $r['tipo']];
        }
    } catch (PDOException $e) {}

    return $resultados;
}


/* =============================================
   15. EDITAR Y ELIMINAR NOTAS
   ============================================= */

function nota_actualizar(PDO $pdo, int $id, string $texto, string $tipo): bool
{
    $stmt = $pdo->prepare('UPDATE notas SET texto = :texto, tipo = :tipo WHERE id = :id');
    $stmt->execute(['texto' => $texto, 'tipo' => $tipo, 'id' => $id]);
    return $stmt->rowCount() > 0;
}

function nota_eliminar(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM notas WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
}


/* =============================================
   16. SISTEMA DE ETIQUETAS/TAGS
   ============================================= */

function etiquetas_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS etiquetas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT "#3b82f6",
            usuario_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uniq_nombre_usr (nombre, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS entidad_etiquetas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entidad_tipo VARCHAR(30) NOT NULL,
            entidad_id INT UNSIGNED NOT NULL,
            etiqueta_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uniq_ent_tag (entidad_tipo, entidad_id, etiqueta_id),
            INDEX idx_entidad (entidad_tipo, entidad_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

function etiqueta_crear(PDO $pdo, string $nombre, string $color): ?int
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return null;
    etiquetas_asegurar_tabla($pdo);
    try {
        $stmt = $pdo->prepare('INSERT INTO etiquetas (nombre, color, usuario_id) VALUES (:nombre, :color, :uid)');
        $stmt->execute(['nombre' => $nombre, 'color' => $color, 'uid' => $uid]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

function etiquetas_listar(PDO $pdo): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];
    etiquetas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM etiquetas WHERE usuario_id = :uid ORDER BY nombre');
    $stmt->execute(['uid' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function etiqueta_eliminar(PDO $pdo, int $id): bool
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    etiquetas_asegurar_tabla($pdo);
    $pdo->prepare('DELETE FROM entidad_etiquetas WHERE etiqueta_id = :id')->execute(['id' => $id]);
    $stmt = $pdo->prepare('DELETE FROM etiquetas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    return $stmt->rowCount() > 0;
}

function entidad_asignar_etiqueta(PDO $pdo, string $tipo, int $entidad_id, int $etiqueta_id): bool
{
    etiquetas_asegurar_tabla($pdo);
    try {
        $stmt = $pdo->prepare('INSERT INTO entidad_etiquetas (entidad_tipo, entidad_id, etiqueta_id) VALUES (:tipo, :eid, :tid)');
        $stmt->execute(['tipo' => $tipo, 'eid' => $entidad_id, 'tid' => $etiqueta_id]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function entidad_quitar_etiqueta(PDO $pdo, string $tipo, int $entidad_id, int $etiqueta_id): bool
{
    etiquetas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('DELETE FROM entidad_etiquetas WHERE entidad_tipo = :tipo AND entidad_id = :eid AND etiqueta_id = :tid');
    $stmt->execute(['tipo' => $tipo, 'eid' => $entidad_id, 'tid' => $etiqueta_id]);
    return $stmt->rowCount() > 0;
}

function entidad_obtener_etiquetas(PDO $pdo, string $tipo, int $entidad_id): array
{
    etiquetas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT e.* FROM etiquetas e
         INNER JOIN entidad_etiquetas ee ON e.id = ee.etiqueta_id
         WHERE ee.entidad_tipo = :tipo AND ee.entidad_id = :eid
         ORDER BY e.nombre'
    );
    $stmt->execute(['tipo' => $tipo, 'eid' => $entidad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


/* =============================================
   17. DUPLICAR PROPIEDAD
   ============================================= */

function propiedad_duplicar(PDO $pdo, int $id): ?int
{
    $stmt = $pdo->prepare('SELECT * FROM propiedades WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prop) return null;

    unset($prop['id'], $prop['created_at'], $prop['updated_at']);
    $prop['titulo'] = $prop['titulo'] . ' (copia)';
    $prop['referencia'] = $prop['referencia'] ? $prop['referencia'] . '-COPIA' : '';
    $prop['estado'] = 'Disponible';

    $cols = implode(', ', array_keys($prop));
    $placeholders = ':' . implode(', :', array_keys($prop));
    $stmt = $pdo->prepare("INSERT INTO propiedades ($cols) VALUES ($placeholders)");
    $stmt->execute($prop);
    return (int) $pdo->lastInsertId();
}


/* =============================================
   18. IMPORTACIÓN MASIVA CSV
   ============================================= */

function importar_csv_clientes(PDO $pdo, string $filepath, string $tipo): array
{
    $resultado = ['importados' => 0, 'errores' => [], 'lineas_error' => []];
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        $resultado['errores'][] = 'No se pudo abrir el archivo.';
        return $resultado;
    }

    // Detectar BOM UTF-8
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $cabeceras = fgetcsv($handle, 0, ';');
    if (!$cabeceras) {
        $resultado['errores'][] = 'No se pudieron leer las cabeceras.';
        fclose($handle);
        return $resultado;
    }
    $cabeceras = array_map('strtolower', array_map('trim', $cabeceras));

    $campos_requeridos = ['nombre', 'apellido'];
    foreach ($campos_requeridos as $campo) {
        if (!in_array($campo, $cabeceras)) {
            $resultado['errores'][] = "Falta columna obligatoria: $campo";
            fclose($handle);
            return $resultado;
        }
    }

    $linea = 1;
    $stmt = $pdo->prepare(
        'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, direccion, zona_interesada, presupuesto, comentarios)
         VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :direccion, :zona, :presupuesto, :comentarios)'
    );

    while (($fila = fgetcsv($handle, 0, ';')) !== false) {
        $linea++;
        if (count($fila) < count($cabeceras)) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        $datos = array_combine($cabeceras, $fila);
        $nombre = trim($datos['nombre'] ?? '');
        $apellido = trim($datos['apellido'] ?? '');
        if (!$nombre || !$apellido) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        try {
            $stmt->execute([
                'tipo' => $tipo,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'telefono' => trim($datos['telefono'] ?? ''),
                'email' => trim($datos['email'] ?? ''),
                'operacion' => trim($datos['operacion'] ?? 'Venta'),
                'direccion' => trim($datos['direccion'] ?? ''),
                'zona' => trim($datos['zona_interesada'] ?? $datos['zona'] ?? ''),
                'presupuesto' => is_numeric($datos['presupuesto'] ?? '') ? (float)$datos['presupuesto'] : null,
                'comentarios' => trim($datos['comentarios'] ?? ''),
            ]);
            $resultado['importados']++;
        } catch (PDOException $e) {
            $resultado['lineas_error'][] = $linea;
        }
    }
    fclose($handle);
    return $resultado;
}

function importar_csv_propiedades(PDO $pdo, string $filepath, string $equipo): array
{
    $resultado = ['importados' => 0, 'errores' => [], 'lineas_error' => []];
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        $resultado['errores'][] = 'No se pudo abrir el archivo.';
        return $resultado;
    }

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $cabeceras = fgetcsv($handle, 0, ';');
    if (!$cabeceras) {
        $resultado['errores'][] = 'No se pudieron leer las cabeceras.';
        fclose($handle);
        return $resultado;
    }
    $cabeceras = array_map('strtolower', array_map('trim', $cabeceras));

    if (!in_array('titulo', $cabeceras)) {
        $resultado['errores'][] = 'Falta columna obligatoria: titulo';
        fclose($handle);
        return $resultado;
    }

    $linea = 1;
    $stmt = $pdo->prepare(
        'INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, operacion, estado, referencia, descripcion)
         VALUES (:equipo, :titulo, :tipo, :ubicacion, :direccion, :metros, :hab, :banos, :precio, :moneda, :operacion, :estado, :ref, :desc)'
    );

    while (($fila = fgetcsv($handle, 0, ';')) !== false) {
        $linea++;
        if (count($fila) < count($cabeceras)) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        $datos = array_combine($cabeceras, $fila);
        $titulo = trim($datos['titulo'] ?? '');
        if (!$titulo) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        try {
            $stmt->execute([
                'equipo' => $equipo,
                'titulo' => $titulo,
                'tipo' => trim($datos['tipo'] ?? 'Piso'),
                'ubicacion' => trim($datos['ubicacion'] ?? ''),
                'direccion' => trim($datos['direccion'] ?? ''),
                'metros' => is_numeric($datos['metros'] ?? '') ? (int)$datos['metros'] : null,
                'hab' => is_numeric($datos['habitaciones'] ?? '') ? (int)$datos['habitaciones'] : null,
                'banos' => is_numeric($datos['banos'] ?? $datos['baños'] ?? '') ? (int)($datos['banos'] ?? $datos['baños'] ?? 0) : null,
                'precio' => is_numeric($datos['precio'] ?? '') ? (float)$datos['precio'] : 0,
                'moneda' => trim($datos['moneda'] ?? 'EUR'),
                'operacion' => strtolower(trim($datos['operacion'] ?? 'venta')),
                'estado' => trim($datos['estado'] ?? 'Disponible'),
                'ref' => trim($datos['referencia'] ?? ''),
                'desc' => trim($datos['descripcion'] ?? ''),
            ]);
            $resultado['importados']++;
        } catch (PDOException $e) {
            $resultado['lineas_error'][] = $linea;
        }
    }
    fclose($handle);
    return $resultado;
}


/* =============================================
   19. FILTROS GUARDADOS
   ============================================= */

function filtros_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS filtros_guardados (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            seccion VARCHAR(50) NOT NULL,
            parametros JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_filtro_usr (usuario_id, seccion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

function filtro_guardar(PDO $pdo, string $nombre, string $seccion, array $parametros): ?int
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return null;
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('INSERT INTO filtros_guardados (usuario_id, nombre, seccion, parametros) VALUES (:uid, :nombre, :seccion, :params)');
    $stmt->execute(['uid' => $uid, 'nombre' => $nombre, 'seccion' => $seccion, 'params' => json_encode($parametros)]);
    return (int) $pdo->lastInsertId();
}

function filtros_listar(PDO $pdo, string $seccion): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM filtros_guardados WHERE usuario_id = :uid AND seccion = :seccion ORDER BY nombre');
    $stmt->execute(['uid' => $uid, 'seccion' => $seccion]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function filtro_eliminar(PDO $pdo, int $id): bool
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('DELETE FROM filtros_guardados WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    return $stmt->rowCount() > 0;
}


/* =============================================
   20. MATCHING AUTOMÁTICO COMPRADOR-PROPIEDAD
   ============================================= */

function matching_buscar(PDO $pdo, int $limite = 20): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];

    $compradores = $pdo->query(
        "SELECT id, nombre, apellido, zona_interesada, presupuesto, operacion
         FROM clientes WHERE tipo = 'comprador' AND zona_interesada IS NOT NULL AND zona_interesada != ''
         ORDER BY id DESC LIMIT 50"
    )->fetchAll(PDO::FETCH_ASSOC);

    $matches = [];
    foreach ($compradores as $comp) {
        $sql = "SELECT id, titulo, ubicacion, precio, moneda, operacion, estado, metros, habitaciones
                FROM propiedades WHERE estado = 'Disponible'";
        $params = [];

        if ($comp['zona_interesada']) {
            $sql .= ' AND (ubicacion LIKE :zona OR direccion LIKE :zona2)';
            $params['zona'] = '%' . $comp['zona_interesada'] . '%';
            $params['zona2'] = '%' . $comp['zona_interesada'] . '%';
        }
        if ($comp['presupuesto'] && $comp['presupuesto'] > 0) {
            $sql .= ' AND precio <= :max_precio';
            $params['max_precio'] = $comp['presupuesto'] * 1.15; // 15% tolerancia
        }

        $sql .= ' ORDER BY precio ASC LIMIT 5';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $props = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($props)) {
            $matches[] = [
                'comprador' => $comp,
                'propiedades' => $props,
                'coincidencias' => count($props),
            ];
        }
    }

    usort($matches, fn($a, $b) => $b['coincidencias'] <=> $a['coincidencias']);
    return array_slice($matches, 0, $limite);
}


/* =============================================
   21. TIMELINE DE ACTIVIDAD POR ENTIDAD
   ============================================= */

function timeline_entidad(PDO $pdo, string $entidad_tipo, int $entidad_id): array
{
    $timeline = [];

    // Notas
    $stmt = $pdo->prepare(
        "SELECT 'nota' AS origen, tipo AS subtipo, texto AS descripcion, created_at
         FROM notas WHERE entity_type = :tipo AND entity_id = :id ORDER BY created_at DESC"
    );
    $stmt->execute(['tipo' => $entidad_tipo, 'id' => $entidad_id]);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Actividad log
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        "SELECT 'actividad' AS origen, accion AS subtipo, descripcion, created_at
         FROM actividad_log WHERE entidad = :tipo AND entidad_id = :id ORDER BY created_at DESC"
    );
    $stmt->execute(['tipo' => $entidad_tipo, 'id' => $entidad_id]);
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Visitas (solo para clientes o propiedades)
    if ($entidad_tipo === 'cliente') {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'visita' AS origen, estado AS subtipo, observaciones AS descripcion, fecha_visita AS created_at
             FROM visitas WHERE cliente_id = :id ORDER BY fecha_visita DESC"
        );
        $stmt->execute(['id' => $entidad_id]);
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($entidad_tipo === 'propiedad') {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'visita' AS origen, estado AS subtipo, observaciones AS descripcion, fecha_visita AS created_at
             FROM visitas WHERE propiedad_id = :id ORDER BY fecha_visita DESC"
        );
        $stmt->execute(['id' => $entidad_id]);
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

        ofertas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'oferta' AS origen, estado AS subtipo, CONCAT('Oferta: ', FORMAT(importe, 0)) AS descripcion, fecha_oferta AS created_at
             FROM ofertas WHERE propiedad_id = :id ORDER BY fecha_oferta DESC"
        );
        $stmt->execute(['id' => $entidad_id]);
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Ordenar cronológicamente (más reciente primero)
    usort($timeline, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $timeline;
}

/* =============================================
   22. PROCESO PROPIEDADES – ASEGURAR TABLA
   ============================================= */

function proceso_propiedades_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS proceso_propiedades (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            propiedad_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL DEFAULT 0,
            equipo ENUM("vendedor","comprador") NOT NULL,
            etapa VARCHAR(50) NOT NULL DEFAULT "captacion",
            notas TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_proceso_propiedad (propiedad_id),
            INDEX idx_proceso_equipo (equipo),
            INDEX idx_proceso_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Migración: añadir usuario_id si la tabla ya existía sin ella */
    try {
        $pdo->exec('ALTER TABLE proceso_propiedades ADD COLUMN usuario_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER propiedad_id');
    } catch (\PDOException $e) {
        /* columna ya existe – ignorar */
    }

    $ok = true;
}

/* FIN de helpers.php */
