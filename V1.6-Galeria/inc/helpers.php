<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

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

function validar_telefono(string $valor): bool
{
    return (bool) preg_match('/^[0-9+()\s-]{6,20}$/', $valor);
}

function validar_enum(string $valor, array $permitidos): bool
{
    return in_array($valor, $permitidos, true);
}

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

function imagenes_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

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
    $dir_imagenes = __DIR__ . '/../uploads/propiedades';
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
        'ruta_archivo' => '/uploads/propiedades/' . $nombre_archivo,
    ]);

    return $resultado ? (int) $pdo->lastInsertId() : null;
}

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
