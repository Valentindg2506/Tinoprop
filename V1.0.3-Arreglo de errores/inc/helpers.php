<?php

define('TERMINOS_VERSION', '2026-03-02');
define('LOGIN_MAX_INTENTOS', 5);
define('LOGIN_VENTANA_SEGUNDOS', 900);
define('LOGIN_BLOQUEO_SEGUNDOS', 900);
function roles_disponibles(): array
{
    return [
        'agente_comprador' => ['label' => 'Agente Comprador', 'nivel' => 1],
        'agente_vendedor'  => ['label' => 'Agente Vendedor',  'nivel' => 1],
        'agente'           => ['label' => 'Agente',           'nivel' => 2],
        'marketing'        => ['label' => 'Marketing',        'nivel' => 3],
        'supervisor'       => ['label' => 'Supervisor',       'nivel' => 4],
        'jefe'             => ['label' => 'Jefe',             'nivel' => 5],
        'superadmin'       => ['label' => 'Super Admin',      'nivel' => 99],
    ];
}
function usuario_rol(): string
{
    return $_SESSION['usuario']['rol'] ?? 'agente';
}
function usuario_inmobiliaria_id(): int
{
    return (int) ($_SESSION['usuario']['inmobiliaria_id'] ?? 0);
}
function usuario_inmobiliaria_nombre(): string
{
    return $_SESSION['usuario']['inmobiliaria_nombre'] ?? 'Sin asignar';
}
function es_superadmin(): bool
{
    return usuario_rol() === 'superadmin';
}
function tiene_nivel(string $rol_minimo): bool
{
    $roles = roles_disponibles();
    $nivel_actual = $roles[usuario_rol()]['nivel'] ?? 0;
    $nivel_requerido = $roles[$rol_minimo]['nivel'] ?? 0;
    return $nivel_actual >= $nivel_requerido;
}
function puede_ver_vendedor(): bool
{
    $rol = usuario_rol();
    return in_array($rol, ['agente_vendedor', 'agente', 'marketing', 'supervisor', 'jefe', 'superadmin'], true);
}
function puede_ver_comprador(): bool
{
    $rol = usuario_rol();
    return in_array($rol, ['agente_comprador', 'agente', 'marketing', 'supervisor', 'jefe', 'superadmin'], true);
}
function puede_ver_sistema(): bool
{
    return tiene_nivel('supervisor');
}
function puede_gestionar_usuarios(): bool
{
    return tiene_nivel('jefe');
}
function puede_acceder_seccion(string $seccion): bool
{
    if (es_superadmin()) {
        $secciones_admin = ['admin-dashboard', 'admin-inmobiliarias', 'admin-usuarios', 'peticiones', 'configuracion', 'legal'];
        return in_array($seccion, $secciones_admin, true);
    }
    $universales = ['dashboard', 'recordatorios', 'matching', 'documentacion', 'configuracion', 'busqueda-avanzada', 'post-venta', 'legal'];
    if (in_array($seccion, $universales, true)) {
        return true;
    }
    $secciones_vendedor = [
        'clientes-vendedor', 'prospectos-vendedor', 'propiedades-vendedor',
        'alquileres-vendedor', 'proceso-vendedor', 'visitas-vendedor', 'ofertas-vendedor',
    ];
    if (in_array($seccion, $secciones_vendedor, true)) {
        return puede_ver_vendedor();
    }
    $secciones_comprador = [
        'clientes-comprador', 'prospectos-comprador', 'propiedades-comprador',
        'alquileres-comprador', 'proceso-comprador', 'visitas-comprador',
    ];
    if (in_array($seccion, $secciones_comprador, true)) {
        return puede_ver_comprador();
    }
    $secciones_sistema = ['importar-csv', 'actividad'];
    if (in_array($seccion, $secciones_sistema, true)) {
        return puede_ver_sistema();
    }
    if ($seccion === 'admin-usuarios') {
        return puede_gestionar_usuarios();
    }
    if (in_array($seccion, ['admin-inmobiliarias', 'admin-dashboard'], true)) {
        return es_superadmin();
    }
    if ($seccion === 'peticiones') {
        return tiene_nivel('jefe');
    }
    if (in_array($seccion, ['ver_cliente', 'ver_propiedad'], true)) {
        return true;
    }
    return false;
}
function verificar_acceso(string $seccion): void
{
    if (!puede_acceder_seccion($seccion)) {
        http_response_code(403);
        echo '<div class="error_acceso"><h2>⛔ Acceso denegado</h2><p>No tienes permisos para acceder a esta sección.</p><a href="?seccion=dashboard" class="btn_guardar">Volver al Dashboard</a></div>';
        exit;
    }
}
function sql_iid(string $alias = ''): string
{
    if (es_superadmin()) {
        return '';
    }
    $col = $alias ? "{$alias}.inmobiliaria_id" : 'inmobiliaria_id';
    return " AND {$col} = :iid";
}
function sql_iid_params(): array
{
    if (es_superadmin()) {
        return [];
    }
    return ['iid' => usuario_inmobiliaria_id()];
}
function sql_iid_pair(string $alias = ''): array
{
    return [sql_iid($alias), sql_iid_params()];
}
function sql_uid(string $alias = ''): string
{
    if (tiene_nivel('marketing')) {
        return '';
    }
    $col = $alias ? "{$alias}.usuario_id" : 'usuario_id';
    return " AND {$col} = :uid";
}
function sql_uid_params(): array
{
    if (tiene_nivel('marketing')) {
        return [];
    }
    return ['uid' => (int) ($_SESSION['usuario']['id'] ?? 0)];
}
function sql_uid_pair(string $alias = ''): array
{
    return [sql_uid($alias), sql_uid_params()];
}
function inmobiliarias_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inmobiliarias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            cif VARCHAR(20) DEFAULT NULL,
            direccion VARCHAR(250) DEFAULT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            logo_url VARCHAR(300) DEFAULT NULL,
            activa TINYINT(1) NOT NULL DEFAULT 1,
            max_usuarios INT UNSIGNED NOT NULL DEFAULT 10,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inmob_activa (activa)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}
function inmobiliaria_crear(PDO $pdo, array $datos): ?int
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO inmobiliarias (nombre, cif, direccion, telefono, email, max_usuarios)
         VALUES (:nombre, :cif, :direccion, :telefono, :email, :max_usuarios)'
    );
    $stmt->execute([
        'nombre'       => $datos['nombre'],
        'cif'          => $datos['cif'] ?? null,
        'direccion'    => $datos['direccion'] ?? null,
        'telefono'     => $datos['telefono'] ?? null,
        'email'        => $datos['email'] ?? null,
        'max_usuarios' => (int) ($datos['max_usuarios'] ?? 10),
    ]);
    return (int) $pdo->lastInsertId();
}
function inmobiliaria_actualizar(PDO $pdo, int $id, array $datos): bool
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'UPDATE inmobiliarias SET nombre = :nombre, cif = :cif, direccion = :direccion,
         telefono = :telefono, email = :email, max_usuarios = :max_usuarios WHERE id = :id'
    );
    $stmt->execute([
        'nombre'       => $datos['nombre'],
        'cif'          => $datos['cif'] ?? null,
        'direccion'    => $datos['direccion'] ?? null,
        'telefono'     => $datos['telefono'] ?? null,
        'email'        => $datos['email'] ?? null,
        'max_usuarios' => (int) ($datos['max_usuarios'] ?? 10),
        'id'           => $id,
    ]);
    return $stmt->rowCount() > 0;
}
function inmobiliaria_toggle_activa(PDO $pdo, int $id): bool
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('UPDATE inmobiliarias SET activa = NOT activa WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
}
function inmobiliarias_listar(PDO $pdo): array
{
    inmobiliarias_asegurar_tabla($pdo);
    return $pdo->query('SELECT i.*, (SELECT COUNT(*) FROM usuarios u WHERE u.inmobiliaria_id = i.id) AS total_usuarios FROM inmobiliarias i ORDER BY i.nombre')->fetchAll();
}
function inmobiliaria_obtener(PDO $pdo, int $id): ?array
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM inmobiliarias WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}
function inmobiliaria_eliminar(PDO $pdo, int $id): bool
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE inmobiliaria_id = :iid');
        $stmt->execute(['iid' => $id]);
        $usuario_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare('SELECT id FROM propiedades WHERE inmobiliaria_id = :iid');
        $stmt->execute(['iid' => $id]);
        $propiedad_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($propiedad_ids)) {
            $placeholders = implode(',', array_fill(0, count($propiedad_ids), '?'));
            $pdo->prepare("DELETE FROM imagenes_propiedades WHERE propiedad_id IN ($placeholders)")->execute($propiedad_ids);
        }
        $tablas_con_iid = [
            'proceso_propiedades', 'recordatorios', 'visitas', 'ofertas',
            'peticiones', 'actividad_log', 'propiedades', 'clientes'
        ];
        foreach ($tablas_con_iid as $tabla) {
            try {
                $pdo->prepare("DELETE FROM {$tabla} WHERE inmobiliaria_id = ?")->execute([$id]);
            } catch (PDOException $e) {
            }
        }
        if (!empty($usuario_ids)) {
            $placeholders = implode(',', array_fill(0, count($usuario_ids), '?'));
            $tablas_por_uid = ['preferencias_usuario', 'filtros_guardados', 'entidad_etiquetas', 'notas'];
            foreach ($tablas_por_uid as $tabla) {
                try {
                    $pdo->prepare("DELETE FROM {$tabla} WHERE usuario_id IN ($placeholders)")->execute($usuario_ids);
                } catch (PDOException $e) {
                }
            }
        }
        $pdo->prepare('DELETE FROM usuarios WHERE inmobiliaria_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM inmobiliarias WHERE id = ?')->execute([$id]);
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}
function usuarios_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $columnas = ['inmobiliaria_id', 'avatar_url', 'activo', 'password_temporal', 'terminos_aceptados'];
    foreach ($columnas as $col) {
        try {
            if ($col === 'inmobiliaria_id') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER id');
            } elseif ($col === 'avatar_url') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN avatar_url VARCHAR(300) DEFAULT NULL AFTER rol');
            } elseif ($col === 'activo') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_url');
            } elseif ($col === 'password_temporal') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN password_temporal TINYINT(1) NOT NULL DEFAULT 0 AFTER activo');
            } elseif ($col === 'terminos_aceptados') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN terminos_aceptados VARCHAR(20) DEFAULT NULL AFTER password_temporal');
            }
        } catch (\PDOException $e) {
        }
    }
    $ok = true;
}
function usuarios_listar_por_inmobiliaria(PDO $pdo, int $inmobiliaria_id): array
{
    usuarios_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, rol, activo, created_at FROM usuarios WHERE inmobiliaria_id = :iid ORDER BY nombre'
    );
    $stmt->execute(['iid' => $inmobiliaria_id]);
    return $stmt->fetchAll();
}
function usuario_crear(PDO $pdo, array $datos): array
{
    usuarios_asegurar_tabla($pdo);
    $password_plano = $datos['password'] ?? '';
    $es_temporal = !empty($datos['password_temporal']);
    if ($es_temporal && $password_plano === '') {
        $password_plano = generar_password_temporal();
    }
    $hash = password_hash($password_plano, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (inmobiliaria_id, nombre, email, password_hash, rol, activo, password_temporal)
         VALUES (:iid, :nombre, :email, :hash, :rol, 1, :temporal)'
    );
    $stmt->execute([
        'iid'      => $datos['inmobiliaria_id'],
        'nombre'   => $datos['nombre'],
        'email'    => $datos['email'],
        'hash'     => $hash,
        'rol'      => $datos['rol'],
        'temporal' => $es_temporal ? 1 : 0,
    ]);
    return [
        'id'       => (int) $pdo->lastInsertId(),
        'password' => $es_temporal ? $password_plano : null,
    ];
}
function generar_password_temporal(): string
{
    $upper  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower  = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $symbols = '!@#$%&*?';
    $pwd  = $upper[random_int(0, strlen($upper) - 1)];
    $pwd .= $symbols[random_int(0, strlen($symbols) - 1)];
    for ($i = 0; $i < 6; $i++) {
        $all = $lower . $digits;
        $pwd .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($pwd);
}
function usuario_actualizar_rol(PDO $pdo, int $id, string $rol, int $inmobiliaria_id): bool
{
    $roles_validos = array_keys(roles_disponibles());
    if (!in_array($rol, $roles_validos, true)) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id AND inmobiliaria_id = :iid');
    $stmt->execute(['rol' => $rol, 'id' => $id, 'iid' => $inmobiliaria_id]);
    return $stmt->rowCount() > 0;
}
function usuario_toggle_activo(PDO $pdo, int $id, int $inmobiliaria_id): bool
{
    $stmt = $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id = :id AND inmobiliaria_id = :iid');
    $stmt->execute(['id' => $id, 'iid' => $inmobiliaria_id]);
    return $stmt->rowCount() > 0;
}
function usuario_obtener(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, inmobiliaria_id, nombre, email, rol, activo, created_at FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}
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
function validar_password_segura(string $valor): bool
{
    return preg_match('/^(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $valor) === 1;
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
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_fecha (fecha_recordatorio),
            INDEX idx_estado (estado),
            INDEX idx_recordatorios_inmob (inmobiliaria_id)
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
        'INSERT INTO recordatorios (usuario_id, tipo, descripcion, fecha_recordatorio, hora_recordatorio, prospecto_id, estado, inmobiliaria_id)
         VALUES (:usuario_id, :tipo, :descripcion, :fecha, :hora, :prospecto_id, "pendiente", :iid)'
    );
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'fecha' => $fecha,
        'hora' => $hora,
        'prospecto_id' => $prospecto_id,
        'iid' => usuario_inmobiliaria_id(),
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
        $tabla_lista = true;
    }
}
function imagen_subir(PDO $pdo, int $propiedad_id, array $archivo): ?int
{
    imagenes_asegurar_tabla($pdo);
    $mimes_a_ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($archivo['tmp_name'] ?? '');
    if (!isset($mimes_a_ext[$mime_real])) {
        return null;
    }
    $extension = $mimes_a_ext[$mime_real];
    if (($archivo['size'] ?? 0) > 10 * 1024 * 1024) {
        return null;
    }
    $dir_imagenes = __DIR__ . '/../storage/uploads/propiedades';
    if (!is_dir($dir_imagenes)) {
        mkdir($dir_imagenes, 0755, true);
    }
    $nombre_archivo = uniqid('img_' . $propiedad_id . '_') . '.' . $extension;
    $ruta_destino = $dir_imagenes . '/' . $nombre_archivo;
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        return null;
    }
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
    $sql = 'SELECT ip.ruta_archivo FROM imagenes_propiedades ip
            INNER JOIN propiedades p ON ip.propiedad_id = p.id
            WHERE ip.id = :id' . sql_iid('p');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id] + sql_iid_params());
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$imagen) {
        return false;
    }
    $archivo = __DIR__ . '/..' . $imagen['ruta_archivo'];
    if (file_exists($archivo)) {
        unlink($archivo);
    }
    $stmt = $pdo->prepare('DELETE FROM imagenes_propiedades WHERE id = :id');
    $resultado = $stmt->execute(['id' => $id]);
    return $resultado && $stmt->rowCount() > 0;
}
function imagen_marcar_principal(PDO $pdo, int $imagen_id): bool
{
    imagenes_asegurar_tabla($pdo);
    $sql = 'SELECT ip.propiedad_id FROM imagenes_propiedades ip
            INNER JOIN propiedades p ON ip.propiedad_id = p.id
            WHERE ip.id = :id' . sql_iid('p');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $imagen_id] + sql_iid_params());
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$imagen) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE imagenes_propiedades SET es_principal = FALSE WHERE propiedad_id = :propiedad_id');
    $stmt->execute(['propiedad_id' => $imagen['propiedad_id']]);
    $stmt = $pdo->prepare('UPDATE imagenes_propiedades SET es_principal = TRUE WHERE id = :id');
    $resultado = $stmt->execute(['id' => $imagen_id]);
    return $resultado && $stmt->rowCount() > 0;
}
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
    $tabla_lista = true;
}
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
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visitas_equipo (equipo),
            INDEX idx_visitas_fecha (fecha_visita),
            INDEX idx_visitas_estado (estado),
            INDEX idx_visitas_recordatorio (recordatorio_id),
            INDEX idx_visitas_inmob (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $tabla_lista = true;
}
function visita_crear(PDO $pdo, string $equipo, ?int $propiedad_id, ?int $cliente_id, string $fecha, ?string $hora, string $observaciones, bool $crear_recordatorio = true): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }
    visitas_asegurar_tabla($pdo);
    $recordatorio_id = null;
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
        'INSERT INTO visitas (equipo, propiedad_id, cliente_id, fecha_visita, hora_visita, estado, observaciones, recordatorio_id, usuario_id, inmobiliaria_id)
         VALUES (:equipo, :propiedad_id, :cliente_id, :fecha, :hora, "pendiente", :observaciones, :recordatorio_id, :usuario_id, :iid)'
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
        'iid'             => usuario_inmobiliaria_id(),
    ]);
    return (int) $pdo->lastInsertId();
}
function visita_crear_desde_recordatorio(PDO $pdo, int $recordatorio_id, string $equipo = 'vendedor'): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }
    visitas_asegurar_tabla($pdo);
    $check = $pdo->prepare('SELECT id FROM visitas WHERE recordatorio_id = :rid');
    $check->execute(['rid' => $recordatorio_id]);
    if ($check->fetch()) {
        return null;
    }
    $rec = recordatorio_obtener($pdo, $recordatorio_id);
    if (!$rec) {
        return null;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO visitas (equipo, propiedad_id, cliente_id, fecha_visita, hora_visita, estado, observaciones, recordatorio_id, usuario_id, inmobiliaria_id)
         VALUES (:equipo, NULL, NULL, :fecha, :hora, "pendiente", :observaciones, :recordatorio_id, :usuario_id, :iid)'
    );
    $stmt->execute([
        'equipo'          => $equipo,
        'fecha'           => $rec['fecha_recordatorio'],
        'hora'            => $rec['hora_recordatorio'],
        'observaciones'   => $rec['descripcion'],
        'recordatorio_id' => $recordatorio_id,
        'usuario_id'      => $usuario_id,
        'iid'             => usuario_inmobiliaria_id(),
    ]);
    return (int) $pdo->lastInsertId();
}
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
            WHERE v.equipo = :equipo AND v.usuario_id = :usuario_id' . sql_iid('v');
    $params = ['equipo' => $equipo, 'usuario_id' => $usuario_id] + sql_iid_params();
    if ($filtro_estado !== 'todos') {
        $sql .= ' AND v.estado = :estado';
        $params['estado'] = $filtro_estado;
    }
    $sql .= ' ORDER BY v.fecha_visita ASC, v.hora_visita ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function visita_actualizar_estado(PDO $pdo, int $id, string $estado): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }
    visitas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('UPDATE visitas SET estado = :estado WHERE id = :id AND usuario_id = :usuario_id' . sql_iid());
    $stmt->execute(['estado' => $estado, 'id' => $id, 'usuario_id' => $usuario_id] + sql_iid_params());
    $ok = $stmt->rowCount() > 0;
    if ($ok) {
        $stmt2 = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id');
        $stmt2->execute(['id' => $id]);
        $rid = $stmt2->fetchColumn();
        if ($rid) {
            $estado_rec = 'pendiente';
            if ($estado === 'realizada') {
                $estado_rec = 'completado';
            }
            if ($estado === 'cancelada') {
                $estado_rec = 'cancelado';
            }
            $pdo->prepare('UPDATE recordatorios SET estado = :estado WHERE id = :id AND usuario_id = :uid')
                ->execute(['estado' => $estado_rec, 'id' => $rid, 'uid' => $usuario_id]);
        }
    }
    return $ok;
}
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
         WHERE id = :id AND usuario_id = :usuario_id' . sql_iid()
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
    ] + sql_iid_params());
    $ok = $resultado && $stmt->rowCount() > 0;
    if ($ok) {
        $stmt2 = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id');
        $stmt2->execute(['id' => $id]);
        $rid = $stmt2->fetchColumn();
        if ($rid) {
            $estado_rec = 'pendiente';
            if ($estado === 'realizada') {
                $estado_rec = 'completado';
            }
            if ($estado === 'cancelada') {
                $estado_rec = 'cancelado';
            }
            $desc = 'Visita programada';
            if ($propiedad_id) {
                $stmtP = $pdo->prepare('SELECT titulo FROM propiedades WHERE id = :id');
                $stmtP->execute(['id' => $propiedad_id]);
                $titulo = $stmtP->fetchColumn();
                if ($titulo) {
                    $desc = 'Visita: ' . $titulo;
                }
            }
            if ($observaciones) {
                $desc .= ' - ' . $observaciones;
            }
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
function visita_eliminar(PDO $pdo, int $id, bool $eliminar_recordatorio = true): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }
    visitas_asegurar_tabla($pdo);
    $rid = null;
    if ($eliminar_recordatorio) {
        $stmt = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['id' => $id, 'uid' => $usuario_id] + sql_iid_params());
        $rid = $stmt->fetchColumn();
    }
    $stmt = $pdo->prepare('DELETE FROM visitas WHERE id = :id AND usuario_id = :usuario_id' . sql_iid());
    $resultado = $stmt->execute(['id' => $id, 'usuario_id' => $usuario_id] + sql_iid_params());
    $ok = $resultado && $stmt->rowCount() > 0;
    if ($ok && $rid) {
        recordatorio_eliminar($pdo, (int) $rid);
    }
    return $ok;
}
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
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ofertas_propiedad (propiedad_id),
            INDEX idx_ofertas_estado (estado),
            INDEX idx_ofertas_fecha (fecha_oferta),
            INDEX idx_ofertas_inmobiliaria (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    try {
        $pdo->exec('ALTER TABLE ofertas ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER usuario_id');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE ofertas ADD INDEX idx_ofertas_inmobiliaria (inmobiliaria_id)');
    } catch (PDOException $e) {
    }
    $tabla_lista = true;
}
function oferta_crear(PDO $pdo, int $propiedad_id, ?int $cliente_id, ?string $nombre_ofertante, float $importe, string $fecha, string $notas = ''): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }
    ofertas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO ofertas (propiedad_id, cliente_id, nombre_ofertante, importe, fecha_oferta, estado, notas, usuario_id, inmobiliaria_id)
         VALUES (:propiedad_id, :cliente_id, :nombre_ofertante, :importe, :fecha, "pendiente", :notas, :usuario_id, :iid)'
    );
    $stmt->execute([
        'propiedad_id'     => $propiedad_id,
        'cliente_id'       => $cliente_id ?: null,
        'nombre_ofertante' => $nombre_ofertante ?: null,
        'importe'          => $importe,
        'fecha'            => $fecha,
        'notas'            => $notas,
        'usuario_id'       => $usuario_id,
        'iid'              => usuario_inmobiliaria_id(),
    ]);
    return (int) $pdo->lastInsertId();
}
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
            WHERE o.usuario_id = :usuario_id' . sql_iid('o');
    $params = ['usuario_id' => $usuario_id] + sql_iid_params();
    if ($filtro_estado !== 'todos') {
        $sql .= ' AND o.estado = :estado';
        $params['estado'] = $filtro_estado;
    }
    $sql .= ' ORDER BY o.fecha_oferta DESC, o.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function oferta_cambiar_estado(PDO $pdo, int $id, string $estado, ?float $contraoferta_importe = null): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }
    ofertas_asegurar_tabla($pdo);
    if ($estado === 'contraoferta' && $contraoferta_importe !== null) {
        $stmt = $pdo->prepare('UPDATE ofertas SET estado = :estado, contraoferta_importe = :contra WHERE id = :id AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['estado' => $estado, 'contra' => $contraoferta_importe, 'id' => $id, 'uid' => $usuario_id] + sql_iid_params());
    } else {
        $stmt = $pdo->prepare('UPDATE ofertas SET estado = :estado WHERE id = :id AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['estado' => $estado, 'id' => $id, 'uid' => $usuario_id] + sql_iid_params());
    }
    return $stmt->rowCount() > 0;
}
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
         WHERE id = :id AND usuario_id = :uid' . sql_iid()
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
    ] + sql_iid_params());
    return $resultado && $stmt->rowCount() > 0;
}
function oferta_eliminar(PDO $pdo, int $id): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }
    ofertas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('DELETE FROM ofertas WHERE id = :id AND usuario_id = :uid' . sql_iid());
    $resultado = $stmt->execute(['id' => $id, 'uid' => $usuario_id] + sql_iid_params());
    return $resultado && $stmt->rowCount() > 0;
}
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
function csrf_verify(): void
{
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        http_response_code(403);
        echo '<p style="color:red;text-align:center;margin-top:3rem;">⛔ Token CSRF inválido. Recarga la página e intenta de nuevo.</p>';
        exit;
    }
}
function csrf_verify_api(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token) {
        $json = json_decode(file_get_contents('php://input'), true);
        $token = $json['_csrf_token'] ?? $_POST['_csrf_token'] ?? '';
    }
    if (empty($token) || empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }
}
function actividad_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
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
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_act_usr (usuario_id),
            INDEX idx_act_ent (entidad, entidad_id),
            INDEX idx_act_fecha (created_at),
            INDEX idx_act_inmobiliaria (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    try {
        $pdo->exec('ALTER TABLE actividad_log ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER ip');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE actividad_log ADD INDEX idx_act_inmobiliaria (inmobiliaria_id)');
    } catch (PDOException $e) {
    }
    $ok = true;
}
function actividad_escribir_jsonl(int $uid, string $nombre, int $iid, string $accion, string $entidad, ?int $entidad_id, string $descripcion, array $datos_extra, ?string $ip): void
{
    $entrada = [
        'ts'              => date('c'),
        'usuario_id'      => $uid,
        'usuario_nombre'  => $nombre,
        'inmobiliaria_id' => $iid,
        'accion'          => $accion,
        'entidad'         => $entidad,
        'entidad_id'      => $entidad_id,
        'descripcion'     => $descripcion,
        'datos_extra'     => $datos_extra ?: new stdClass(),
        'ip'              => $ip,
    ];
    $linea = json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $ruta  = dirname(__DIR__) . '/logs/actividad_' . date('Y-m-d') . '.jsonl';
    @file_put_contents($ruta, $linea, FILE_APPEND | LOCK_EX);
}
function actividad_registrar(PDO $pdo, string $accion, string $entidad, ?int $entidad_id = null, string $descripcion = '', array $datos_extra = []): void
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    $nombre = $_SESSION['usuario']['nombre'] ?? 'Sistema';
    if ($uid <= 0) {
        return;
    }
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $iid = usuario_inmobiliaria_id();
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO actividad_log (usuario_id, usuario_nombre, accion, entidad, entidad_id, descripcion, datos_extra, ip, inmobiliaria_id)
         VALUES (:uid, :nombre, :accion, :entidad, :eid, :desc, :datos, :ip, :iid)'
    );
    $stmt->execute([
        'uid' => $uid, 'nombre' => $nombre, 'accion' => $accion,
        'entidad' => $entidad, 'eid' => $entidad_id, 'desc' => $descripcion,
        'datos' => !empty($datos_extra) ? json_encode($datos_extra) : null,
        'ip' => $ip,
        'iid' => $iid,
    ]);
    actividad_escribir_jsonl($uid, $nombre, $iid, $accion, $entidad, $entidad_id, $descripcion, $datos_extra, $ip);
}
function actividad_listar(PDO $pdo, int $limite = 20, int $offset = 0): array
{
    actividad_asegurar_tabla($pdo);
    [$iid_cond, $iid_p] = sql_iid_pair();
    $sql = 'SELECT * FROM actividad_log WHERE 1=1' . $iid_cond . ' ORDER BY created_at DESC LIMIT :lim OFFSET :off';
    $stmt = $pdo->prepare($sql);
    foreach ($iid_p as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
function actividad_contar(PDO $pdo): int
{
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM actividad_log WHERE 1=1' . sql_iid());
    $stmt->execute(sql_iid_params());
    return (int) $stmt->fetchColumn();
}
function notificaciones_generar(PDO $pdo): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    $notifs = [];
    try {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM visitas WHERE fecha_visita = CURDATE() AND estado = "pendiente" AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['uid' => $uid] + sql_iid_params());
        $n = (int) $stmt->fetchColumn();
        $seccion_visitas = puede_ver_vendedor() ? 'visitas-vendedor' : 'visitas-comprador';
        if ($n > 0) {
            $notifs[] = ['tipo' => 'info', 'icono' => '📅', 'texto' => "Tienes {$n} visita(s) hoy", 'enlace' => '?seccion=' . $seccion_visitas];
        }
    } catch (PDOException $e) {
    }
    try {
        ofertas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ofertas WHERE estado = "pendiente" AND fecha_oferta < DATE_SUB(CURDATE(), INTERVAL 5 DAY) AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['uid' => $uid] + sql_iid_params());
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $notifs[] = ['tipo' => 'aviso', 'icono' => '⚠️', 'texto' => "{$n} oferta(s) pendiente(s) hace +5 días", 'enlace' => '?seccion=ofertas-vendedor'];
        }
    } catch (PDOException $e) {
    }
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE estado = "nuevo" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY) AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['uid' => $uid] + sql_iid_params());
        $n = (int) $stmt->fetchColumn();
        $seccion_prospectos = puede_ver_vendedor() ? 'prospectos-vendedor' : 'prospectos-comprador';
        if ($n > 0) {
            $notifs[] = ['tipo' => 'aviso', 'icono' => '👤', 'texto' => "{$n} prospecto(s) sin contactar hace +2 semanas", 'enlace' => '?seccion=' . $seccion_prospectos];
        }
    } catch (PDOException $e) {
    }
    try {
        recordatorios_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE fecha_recordatorio = CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $notifs[] = ['tipo' => 'info', 'icono' => '🔔', 'texto' => "{$n} recordatorio(s) para hoy", 'enlace' => '?seccion=recordatorios'];
        }
    } catch (PDOException $e) {
    }
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE fecha_recordatorio < CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) {
            $notifs[] = ['tipo' => 'peligro', 'icono' => '🚨', 'texto' => "{$n} recordatorio(s) atrasado(s)", 'enlace' => '?seccion=recordatorios'];
        }
    } catch (PDOException $e) {
    }
    return $notifs;
}
function cache_get(string $clave, int $ttl = 60): mixed
{
    if (!isset($_SESSION['_cache'][$clave])) {
        return null;
    }
    $e = $_SESSION['_cache'][$clave];
    if (time() - $e['ts'] > $ttl) {
        unset($_SESSION['_cache'][$clave]);
        return null;
    }
    return $e['valor'];
}
function cache_set(string $clave, mixed $valor): void
{
    $_SESSION['_cache'][$clave] = ['valor' => $valor, 'ts' => time()];
}
function cache_flush(): void
{
    $_SESSION['_cache'] = [];
}
function exportar_csv(array $datos, string $nombre = 'export.csv'): void
{
    if (empty($datos)) {
        header('Content-Type: text/plain');
        echo 'Sin datos.';
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, array_keys($datos[0]), ';');
    foreach ($datos as $fila) {
        fputcsv($out, $fila, ';');
    }
    fclose($out);
    exit;
}
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
        'admin-dashboard'       => [['titulo' => 'Admin'], ['titulo' => 'Panel de Control']],
        'admin-inmobiliarias'   => [['titulo' => 'Admin'], ['titulo' => 'Inmobiliarias']],
        'admin-usuarios'        => [['titulo' => 'Admin'], ['titulo' => 'Usuarios']],
        'peticiones'            => [['titulo' => 'Sistema'], ['titulo' => 'Peticiones']],
        'legal'                 => [['titulo' => 'Marco Legal']],
    ];
    if (isset($mapa[$seccion])) {
        foreach ($mapa[$seccion] as $m) {
            $migas[] = $m;
        }
    }
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
    if ($pag['total_paginas'] <= 1) {
        return '';
    }
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
        if ($inicio > 2) {
            $html .= '<li><span class="pag_dots">…</span></li>';
        }
    }
    for ($p = $inicio; $p <= $fin; $p++) {
        $html .= ($p === $pag['pagina_actual'])
            ? '<li><span class="pag_btn pag_activa">' . $p . '</span></li>'
            : '<li><a href="' . e($base_url . $sep . 'pagina=' . $p) . '" class="pag_btn">' . $p . '</a></li>';
    }
    if ($fin < $pag['total_paginas']) {
        if ($fin < $pag['total_paginas'] - 1) {
            $html .= '<li><span class="pag_dots">…</span></li>';
        }
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
function busqueda_global(PDO $pdo, string $termino, int $limite = 10): array
{
    $resultados = [];
    $like = '%' . $termino . '%';
    $stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email, tipo FROM clientes WHERE (nombre LIKE :q OR apellido LIKE :q2 OR email LIKE :q3 OR telefono LIKE :q4)' . sql_iid() . ' LIMIT :lim');
    $stmt->bindValue('q', $like);
    $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like);
    $stmt->bindValue('q4', $like);
    foreach (sql_iid_params() as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $resultados[] = ['tipo' => 'cliente', 'icono' => '👤', 'titulo' => $r['nombre'] . ' ' . $r['apellido'],
            'detalle' => $r['email'] . ' · ' . $r['telefono'],
            'url' => 'index.php?seccion=ver_cliente&id=' . $r['id'] . '&origen=clientes-' . $r['tipo']];
    }
    $stmt = $pdo->prepare('SELECT id, titulo, ubicacion, referencia, operacion, equipo FROM propiedades WHERE (titulo LIKE :q OR ubicacion LIKE :q2 OR referencia LIKE :q3 OR direccion LIKE :q4)' . sql_iid() . ' LIMIT :lim');
    $stmt->bindValue('q', $like);
    $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like);
    $stmt->bindValue('q4', $like);
    foreach (sql_iid_params() as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_INT);
    }
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $origen = obtener_origen_propiedad($r['operacion'], $r['equipo']);
        $resultados[] = ['tipo' => 'propiedad', 'icono' => '🏠', 'titulo' => $r['titulo'],
            'detalle' => $r['ubicacion'] . ($r['referencia'] ? ' · ' . $r['referencia'] : ''),
            'url' => 'index.php?seccion=ver_propiedad&id=' . $r['id'] . '&origen=' . $origen];
    }
    try {
        $stmt = $pdo->prepare('SELECT id, nombre, telefono, tipo FROM prospectos WHERE (nombre LIKE :q OR telefono LIKE :q2)' . sql_iid() . ' LIMIT :lim');
        $stmt->bindValue('q', $like);
        $stmt->bindValue('q2', $like);
        foreach (sql_iid_params() as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = ['tipo' => 'prospecto', 'icono' => '📊', 'titulo' => $r['nombre'],
                'detalle' => $r['telefono'] ?? '', 'url' => 'index.php?seccion=prospectos-' . $r['tipo']];
        }
    } catch (PDOException $e) {
    }
    return $resultados;
}
function nota_actualizar(PDO $pdo, int $id, string $texto, string $tipo): bool
{
    [$cond, $params] = sql_iid_pair();
    $stmt = $pdo->prepare('UPDATE notas SET texto = :texto, tipo = :tipo WHERE id = :id' . $cond);
    $stmt->execute(['texto' => $texto, 'tipo' => $tipo, 'id' => $id] + $params);
    return $stmt->rowCount() > 0;
}
function nota_eliminar(PDO $pdo, int $id): bool
{
    [$cond, $params] = sql_iid_pair();
    $stmt = $pdo->prepare('DELETE FROM notas WHERE id = :id' . $cond);
    $stmt->execute(['id' => $id] + $params);
    return $stmt->rowCount() > 0;
}
function etiquetas_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
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
    if ($uid <= 0) {
        return null;
    }
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
    if ($uid <= 0) {
        return [];
    }
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
function propiedad_duplicar(PDO $pdo, int $id): ?int
{
    $stmt = $pdo->prepare('SELECT * FROM propiedades WHERE id = :id' . sql_iid());
    $stmt->execute(['id' => $id] + sql_iid_params());
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prop) {
        return null;
    }
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
function importar_csv_clientes(PDO $pdo, string $filepath, string $tipo): array
{
    $resultado = ['importados' => 0, 'errores' => [], 'lineas_error' => []];
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        $resultado['errores'][] = 'No se pudo abrir el archivo.';
        return $resultado;
    }
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
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
        'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, direccion, zona_interesada, presupuesto, comentarios, inmobiliaria_id, usuario_id)
         VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :direccion, :zona, :presupuesto, :comentarios, :iid, :uid)'
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
                'iid' => usuario_inmobiliaria_id(),
                'uid' => $_SESSION['usuario']['id'] ?? null,
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
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
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
        'INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, operacion, estado, referencia, descripcion, inmobiliaria_id, usuario_id)
         VALUES (:equipo, :titulo, :tipo, :ubicacion, :direccion, :metros, :hab, :banos, :precio, :moneda, :operacion, :estado, :ref, :desc, :iid, :uid)'
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
                'iid' => usuario_inmobiliaria_id(),
                'uid' => $_SESSION['usuario']['id'] ?? null,
            ]);
            $resultado['importados']++;
        } catch (PDOException $e) {
            $resultado['lineas_error'][] = $linea;
        }
    }
    fclose($handle);
    return $resultado;
}
function filtros_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
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
    if ($uid <= 0) {
        return null;
    }
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('INSERT INTO filtros_guardados (usuario_id, nombre, seccion, parametros) VALUES (:uid, :nombre, :seccion, :params)');
    $stmt->execute(['uid' => $uid, 'nombre' => $nombre, 'seccion' => $seccion, 'params' => json_encode($parametros)]);
    return (int) $pdo->lastInsertId();
}
function filtros_listar(PDO $pdo, string $seccion): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
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
function matching_buscar(PDO $pdo, int $limite = 20): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    $compradores = $pdo->prepare(
        "SELECT id, nombre, apellido, zona_interesada, presupuesto, operacion
         FROM clientes WHERE tipo = 'comprador' AND zona_interesada IS NOT NULL AND zona_interesada != ''" . sql_iid() . "
         ORDER BY id DESC LIMIT 50"
    );
    $compradores->execute(sql_iid_params());
    $compradores = $compradores->fetchAll(PDO::FETCH_ASSOC);
    $matches = [];
    foreach ($compradores as $comp) {
        $sql = "SELECT id, titulo, ubicacion, precio, moneda, operacion, estado, metros, habitaciones
                FROM propiedades WHERE estado = 'Disponible'" . sql_iid();
        $params = [] + sql_iid_params();
        if ($comp['zona_interesada']) {
            $sql .= ' AND (ubicacion LIKE :zona OR direccion LIKE :zona2)';
            $params['zona'] = '%' . $comp['zona_interesada'] . '%';
            $params['zona2'] = '%' . $comp['zona_interesada'] . '%';
        }
        if ($comp['presupuesto'] && $comp['presupuesto'] > 0) {
            $sql .= ' AND precio <= :max_precio';
            $params['max_precio'] = $comp['presupuesto'] * 1.15;
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
    usort($matches, fn ($a, $b) => $b['coincidencias'] <=> $a['coincidencias']);
    return array_slice($matches, 0, $limite);
}
function timeline_entidad(PDO $pdo, string $entidad_tipo, int $entidad_id): array
{
    $timeline = [];
    $stmt = $pdo->prepare(
        "SELECT 'nota' AS origen, tipo AS subtipo, texto AS descripcion, created_at
         FROM notas WHERE entity_type = :tipo AND entity_id = :id" . sql_iid() . " ORDER BY created_at DESC"
    );
    $stmt->execute(['tipo' => $entidad_tipo, 'id' => $entidad_id] + sql_iid_params());
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        "SELECT 'actividad' AS origen, accion AS subtipo, descripcion, created_at
         FROM actividad_log WHERE entidad = :tipo AND entidad_id = :id" . sql_iid() . " ORDER BY created_at DESC"
    );
    $stmt->execute(['tipo' => $entidad_tipo, 'id' => $entidad_id] + sql_iid_params());
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    if ($entidad_tipo === 'cliente') {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'visita' AS origen, estado AS subtipo, observaciones AS descripcion, fecha_visita AS created_at
             FROM visitas WHERE cliente_id = :id" . sql_iid() . " ORDER BY fecha_visita DESC"
        );
        $stmt->execute(['id' => $entidad_id] + sql_iid_params());
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($entidad_tipo === 'propiedad') {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'visita' AS origen, estado AS subtipo, observaciones AS descripcion, fecha_visita AS created_at
             FROM visitas WHERE propiedad_id = :id" . sql_iid() . " ORDER BY fecha_visita DESC"
        );
        $stmt->execute(['id' => $entidad_id] + sql_iid_params());
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
        ofertas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'oferta' AS origen, estado AS subtipo, CONCAT('Oferta: ', FORMAT(importe, 0)) AS descripcion, fecha_oferta AS created_at
             FROM ofertas WHERE propiedad_id = :id" . sql_iid() . " ORDER BY fecha_oferta DESC"
        );
        $stmt->execute(['id' => $entidad_id] + sql_iid_params());
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    usort($timeline, fn ($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $timeline;
}
function proceso_propiedades_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS proceso_propiedades (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            propiedad_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL DEFAULT 0,
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
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
    try {
        $pdo->exec('ALTER TABLE proceso_propiedades ADD COLUMN usuario_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER propiedad_id');
    } catch (\PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE proceso_propiedades ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER usuario_id');
    } catch (\PDOException $e) {
    }
    $ok = true;
}
function peticiones_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS peticiones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            inmobiliaria_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            tipo ENUM("error","mejora","consulta","urgente") NOT NULL DEFAULT "error",
            prioridad ENUM("baja","media","alta","critica") NOT NULL DEFAULT "media",
            estado ENUM("abierta","en_progreso","resuelta","cerrada") NOT NULL DEFAULT "abierta",
            asunto VARCHAR(200) NOT NULL,
            descripcion TEXT NOT NULL,
            respuesta_admin TEXT DEFAULT NULL,
            seccion_afectada VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resuelto_at DATETIME DEFAULT NULL,
            INDEX idx_pet_inmob (inmobiliaria_id),
            INDEX idx_pet_estado (estado),
            INDEX idx_pet_tipo (tipo),
            INDEX idx_pet_prioridad (prioridad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}
function peticion_crear(PDO $pdo, array $datos): ?int
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO peticiones (inmobiliaria_id, usuario_id, tipo, prioridad, asunto, descripcion, seccion_afectada)
         VALUES (:iid, :uid, :tipo, :prioridad, :asunto, :descripcion, :seccion)'
    );
    $stmt->execute([
        'iid'         => $datos['inmobiliaria_id'],
        'uid'         => $datos['usuario_id'],
        'tipo'        => $datos['tipo'] ?? 'error',
        'prioridad'   => $datos['prioridad'] ?? 'media',
        'asunto'      => $datos['asunto'],
        'descripcion' => $datos['descripcion'],
        'seccion'     => $datos['seccion_afectada'] ?? null,
    ]);
    return (int) $pdo->lastInsertId() ?: null;
}
function peticiones_listar_todas(PDO $pdo, ?string $filtro_estado = null): array
{
    peticiones_asegurar_tabla($pdo);
    $sql = 'SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email, i.nombre AS inmobiliaria_nombre
            FROM peticiones p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN inmobiliarias i ON i.id = p.inmobiliaria_id';
    $params = [];
    if ($filtro_estado && $filtro_estado !== 'todas') {
        $sql .= ' WHERE p.estado = :estado';
        $params['estado'] = $filtro_estado;
    }
    $sql .= ' ORDER BY FIELD(p.prioridad,"critica","alta","media","baja"), p.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
function peticiones_listar_por_inmobiliaria(PDO $pdo, int $inmobiliaria_id): array
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT * FROM peticiones WHERE inmobiliaria_id = :iid ORDER BY created_at DESC'
    );
    $stmt->execute(['iid' => $inmobiliaria_id]);
    return $stmt->fetchAll();
}
function peticion_obtener(PDO $pdo, int $id): ?array
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email, i.nombre AS inmobiliaria_nombre
         FROM peticiones p
         LEFT JOIN usuarios u ON u.id = p.usuario_id
         LEFT JOIN inmobiliarias i ON i.id = p.inmobiliaria_id
         WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}
function peticion_responder(PDO $pdo, int $id, string $respuesta, string $nuevo_estado): bool
{
    peticiones_asegurar_tabla($pdo);
    $resuelto = in_array($nuevo_estado, ['resuelta', 'cerrada'], true) ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare(
        'UPDATE peticiones SET respuesta_admin = :resp, estado = :estado, resuelto_at = :resuelto WHERE id = :id'
    );
    return $stmt->execute(['resp' => $respuesta, 'estado' => $nuevo_estado, 'resuelto' => $resuelto, 'id' => $id]);
}
function peticion_cambiar_estado(PDO $pdo, int $id, string $estado): bool
{
    peticiones_asegurar_tabla($pdo);
    $resuelto = in_array($estado, ['resuelta', 'cerrada'], true) ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare('UPDATE peticiones SET estado = :estado, resuelto_at = :resuelto WHERE id = :id');
    return $stmt->execute(['estado' => $estado, 'resuelto' => $resuelto, 'id' => $id]);
}
function peticiones_contar_por_estado(PDO $pdo): array
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->query(
        'SELECT estado, COUNT(*) as total FROM peticiones GROUP BY estado'
    );
    $conteo = ['abierta' => 0, 'en_progreso' => 0, 'resuelta' => 0, 'cerrada' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $conteo[$row['estado']] = (int) $row['total'];
    }
    return $conteo;
}
function sistema_obtener_estadisticas(PDO $pdo): array
{
    $stats = [];
    try {
        $stats['inmobiliarias_total'] = (int) $pdo->query('SELECT COUNT(*) FROM inmobiliarias')->fetchColumn();
        $stats['inmobiliarias_activas'] = (int) $pdo->query('SELECT COUNT(*) FROM inmobiliarias WHERE activa = 1')->fetchColumn();
    } catch (\PDOException $e) {
        $stats['inmobiliarias_total'] = 0;
        $stats['inmobiliarias_activas'] = 0;
    }
    try {
        $stats['usuarios_total'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $stats['usuarios_activos'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn();
        $stats['usuarios_pwd_temporal'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE password_temporal = 1')->fetchColumn();
    } catch (\PDOException $e) {
        $stats['usuarios_total'] = 0;
        $stats['usuarios_activos'] = 0;
        $stats['usuarios_pwd_temporal'] = 0;
    }
    try {
        $stmt = $pdo->query('SELECT rol, COUNT(*) as total FROM usuarios WHERE activo = 1 GROUP BY rol ORDER BY total DESC');
        $stats['usuarios_por_rol'] = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $stats['usuarios_por_rol'] = [];
    }
    $tablas_contar = ['clientes', 'prospectos', 'propiedades', 'visitas', 'notas', 'proceso_propiedades'];
    foreach ($tablas_contar as $tabla) {
        try {
            $stats['total_' . $tabla] = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
        } catch (\PDOException $e) {
            $stats['total_' . $tabla] = 0;
        }
    }
    try {
        $stats['clientes_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $stats['propiedades_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM propiedades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $stats['visitas_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM visitas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    } catch (\PDOException $e) {
        $stats['clientes_7d'] = 0;
        $stats['propiedades_7d'] = 0;
        $stats['visitas_7d'] = 0;
    }
    try {
        $db_name = db_config()['name'] ?? 'tinoprop';
        $stmt = $pdo->prepare(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables WHERE table_schema = :db"
        );
        $stmt->execute(['db' => $db_name]);
        $stats['db_size_mb'] = (float) ($stmt->fetchColumn() ?: 0);
    } catch (\PDOException $e) {
        $stats['db_size_mb'] = 0;
    }
    try {
        $stmt = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024, 1) AS size_kb
                             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_ROWS DESC");
        $stats['tablas'] = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $stats['tablas'] = [];
    }
    $stats['php_version'] = phpversion();
    try {
        $stats['mysql_version'] = $pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (\PDOException $e) {
        $stats['mysql_version'] = 'N/A';
    }
    $stats['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
    $stats['memory_limit'] = ini_get('memory_limit');
    $stats['max_upload'] = ini_get('upload_max_filesize');
    $stats['timezone'] = date_default_timezone_get();
    $stats['peticiones'] = peticiones_contar_por_estado($pdo);
    return $stats;
}
function db_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = (require __DIR__ . '/config.php')['db'] ?? [];
    }
    return $cfg;
}
function usuario_ha_aceptado_terminos(): bool
{
    $v = $_SESSION['usuario']['terminos_aceptados'] ?? null;
    return $v === TERMINOS_VERSION;
}
function usuario_aceptar_terminos(PDO $pdo, int $usuario_id): void
{
    $stmt = $pdo->prepare('UPDATE usuarios SET terminos_aceptados = :ver WHERE id = :id');
    $stmt->execute(['ver' => TERMINOS_VERSION, 'id' => $usuario_id]);
    $_SESSION['usuario']['terminos_aceptados'] = TERMINOS_VERSION;
}
function requiere_aceptacion_terminos(): bool
{
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $excluidos = ['login.php', 'logout.php', 'cambiar-password.php', 'aceptar-terminos.php'];
    return !in_array($script, $excluidos, true);
}
function login_intentos_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_intentos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_li_ip (ip),
            INDEX idx_li_email (email),
            INDEX idx_li_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}
function login_obtener_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $trusted_raw = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: '';
        $trusted = array_filter(array_map('trim', explode(',', $trusted_raw)));
        if (!empty($trusted) && in_array($remote, $trusted, true)) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip  = $ips[0];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $remote;
}
function login_intentos_bloqueado(PDO $pdo, string $ip, string $email): int
{
    login_intentos_asegurar_tabla($pdo);
    $limite = date('Y-m-d H:i:s', time() - LOGIN_VENTANA_SEGUNDOS);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_intentos
         WHERE (ip = :ip OR email = :email) AND created_at >= :limite'
    );
    $stmt->execute(['ip' => $ip, 'email' => $email, 'limite' => $limite]);
    $count = (int) $stmt->fetchColumn();
    if ($count < LOGIN_MAX_INTENTOS) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'SELECT MAX(created_at) FROM login_intentos
         WHERE (ip = :ip OR email = :email) AND created_at >= :limite'
    );
    $stmt->execute(['ip' => $ip, 'email' => $email, 'limite' => $limite]);
    $ultimo = $stmt->fetchColumn();
    if (!$ultimo) {
        return 0;
    }
    $expira_en = strtotime($ultimo) + LOGIN_BLOQUEO_SEGUNDOS - time();
    return max(0, $expira_en);
}
function login_intentos_registrar(PDO $pdo, string $ip, string $email): void
{
    login_intentos_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('INSERT INTO login_intentos (ip, email) VALUES (:ip, :email)');
    $stmt->execute(['ip' => $ip, 'email' => $email]);
}
function login_intentos_limpiar(PDO $pdo, string $ip, string $email): void
{
    login_intentos_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('DELETE FROM login_intentos WHERE ip = :ip AND email = :email');
    $stmt->execute(['ip' => $ip, 'email' => $email]);
}
function login_intentos_purgar(PDO $pdo): void
{
    login_intentos_asegurar_tabla($pdo);
    $pdo->exec("DELETE FROM login_intentos WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}
