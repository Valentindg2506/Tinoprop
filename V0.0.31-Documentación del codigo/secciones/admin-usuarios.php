<?php
/*
 * ============================================================================
 * SECCIÓN: GESTIÓN DE USUARIOS — TinoProp CRM Inmobiliario
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo permite gestionar los USUARIOS (cuentas de acceso) dentro
 * de una inmobiliaria. Cada inmobiliaria tiene su propio equipo de agentes
 * y jefes, y desde aquí se pueden administrar.
 *
 * FUNCIONALIDADES:
 *   1. CREAR USUARIO: se le asigna nombre, email, rol y una contraseña
 *      temporal automática que deberá cambiar en su primer acceso.
 *   2. CAMBIAR ROL: modificar los permisos de un usuario (agente → supervisor).
 *   3. ACTIVAR / DESACTIVAR: bloquear el acceso sin borrar la cuenta.
 *   4. RESETEAR CONTRASEÑA: generar una nueva contraseña temporal.
 *
 * ¿QUIÉN PUEDE ACCEDER?
 * - SuperAdmin: gestiona los usuarios de CUALQUIER inmobiliaria.
 * - Jefe: gestiona SOLO los usuarios de SU propia inmobiliaria.
 * - Nadie puede crear superadmins desde aquí (por seguridad).
 * - Nadie puede desactivarse a sí mismo (para no quedarse fuera del sistema).
 *
 * ROLES DISPONIBLES:
 *   👔 Jefe: acceso completo + gestión de usuarios
 *   📋 Supervisor: acceso completo + importar/historial/configuración
 *   📢 Marketing: propiedades, alquileres y búsqueda avanzada
 *   🏠 Agente: clientes, prospectos, visitas, ofertas (vendedor + comprador)
 *   🏷️ Agente Vendedor: solo secciones del equipo vendedor
 *   🔑 Agente Comprador: solo secciones del equipo comprador
 *
 * LÍMITE DE USUARIOS:
 * Cada inmobiliaria tiene un número máximo de usuarios permitidos
 * (definido al crear la inmobiliaria). No se pueden crear más si se
 * alcanza ese límite.
 *
 * ============================================================================
 */

/* Cargamos bootstrap y verificamos permisos de acceso a esta sección. */
require_once __DIR__ . '/../inc/bootstrap.php';
verificar_acceso('admin-usuarios');

/* Conexión a la BD. */
$pdo = db();

/* Nos aseguramos de que las tablas necesarias existan. */
usuarios_asegurar_tabla($pdo);
inmobiliarias_asegurar_tabla($pdo);

$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ============================================================================
 * DETERMINAR QUÉ INMOBILIARIA SE GESTIONA
 * ============================================================================
 * Si el usuario es SuperAdmin, puede gestionar cualquier inmobiliaria
 * pasando su ID por URL (?inmobiliaria_id=3).
 * Si es un Jefe normal, solo puede gestionar SU propia inmobiliaria
 * (la que tiene asignada en su cuenta). */
if (es_superadmin() && isset($_GET['inmobiliaria_id'])) {
    $inmob_id = (int) $_GET['inmobiliaria_id'];
} else {
    /* usuario_inmobiliaria_id() devuelve el ID de la inmobiliaria del
       usuario que está logueado actualmente. */
    $inmob_id = usuario_inmobiliaria_id();
}

/* Obtenemos los datos de la inmobiliaria. Si no existe, mostramos error. */
$inmobiliaria = inmobiliaria_obtener($pdo, $inmob_id);
if (!$inmobiliaria) {
    echo '<div class="error_acceso"><h2>Inmobiliaria no encontrada</h2><a href="?seccion=dashboard" class="btn_guardar">Volver</a></div>';
    return;
}

/* Verificación de seguridad adicional: si el usuario NO es superadmin,
   comprobamos que esté intentando gestionar SU propia inmobiliaria.
   Si intenta gestionar otra, le bloqueamos con un error 403 (Prohibido). */
if (!es_superadmin() && $inmob_id !== usuario_inmobiliaria_id()) {
    http_response_code(403);
    echo '<div class="error_acceso"><h2>⛔ Acceso denegado</h2></div>';
    return;
}

/* Cargamos la lista de roles disponibles en el sistema. */
$roles = roles_disponibles();

/* Creamos una copia de los roles SIN 'superadmin', porque nadie puede
   crear o asignar el rol superadmin desde esta interfaz.
   Esto es una medida de seguridad: el superadmin solo se configura
   directamente en la base de datos. */
$roles_asignables = $roles;
unset($roles_asignables['superadmin']);

/* ============================================================================
 * BLOQUE POST: Acciones de gestión de usuarios
 * ============================================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    /* ── ACCIÓN: CREAR NUEVO USUARIO ──
       Se valida el nombre, email y rol. Se verifica que el email no esté
       ya registrado y que no se haya alcanzado el límite de usuarios. */
    if (isset($_POST['crear_usuario'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'agente';

        $errores = [];
        if (!validar_requerido($nombre)) $errores[] = 'El nombre es obligatorio.';
        if (!validar_email($email)) $errores[] = 'Email no válido.';
        if (!isset($roles_asignables[$rol])) $errores[] = 'Rol no válido.';

        /* Comprobamos que el email no esté ya registrado en el sistema.
           ¿Por qué? Porque el email es el "nombre de usuario" para iniciar
           sesión, y no puede haber dos cuentas con el mismo email. */
        if (empty($errores)) {
            $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e LIMIT 1');
            $check->execute(['e' => $email]);
            if ($check->fetch()) $errores[] = 'Ese email ya está registrado.';
        }

        /* Comprobamos que la inmobiliaria no haya alcanzado su límite de usuarios.
           COUNT(*) cuenta cuántos usuarios tiene ya, y lo comparamos con max_usuarios. */
        if (empty($errores)) {
            $count = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE inmobiliaria_id = :iid');
            $count->execute(['iid' => $inmob_id]);
            if ((int) $count->fetchColumn() >= (int) $inmobiliaria['max_usuarios']) {
                $errores[] = 'Se alcanzó el límite de usuarios (' . $inmobiliaria['max_usuarios'] . ').';
            }
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
        } else {
            /* Creamos el usuario con una contraseña temporal generada automáticamente.
               password_temporal = true indica que al primer login deberá cambiarla.
               La contraseña se muestra UNA SOLA VEZ en el mensaje flash. */
            $resultado = usuario_crear($pdo, [
                'inmobiliaria_id' => $inmob_id,
                'nombre' => $nombre,
                'email' => $email,
                'password' => '',
                'password_temporal' => true,
                'rol' => $rol,
            ]);
            $pwd_temporal = $resultado['password'];
            flash_set('success', 'Usuario "' . $nombre . '" creado como ' . ($roles[$rol]['label'] ?? $rol) . '. Contraseña temporal: ' . $pwd_temporal . ' — El usuario deberá cambiarla en su primer inicio de sesión.');
        }
        header('Location: index.php?seccion=admin-usuarios&inmobiliaria_id=' . $inmob_id);
        exit;
    }

    /* ── ACCIÓN: CAMBIAR ROL DE UN USUARIO ──
       Cambia los permisos de un usuario. Solo se aceptan roles
       de la lista de asignables (sin superadmin). */
    if (isset($_POST['cambiar_rol'])) {
        $uid = (int) ($_POST['usuario_id'] ?? 0);
        $nuevo_rol = $_POST['rol'] ?? '';
        if ($uid > 0 && isset($roles_asignables[$nuevo_rol])) {
            usuario_actualizar_rol($pdo, $uid, $nuevo_rol, $inmob_id);
            flash_set('success', 'Rol actualizado correctamente.');
        }
        header('Location: index.php?seccion=admin-usuarios&inmobiliaria_id=' . $inmob_id);
        exit;
    }

    /* ── ACCIÓN: ACTIVAR / DESACTIVAR USUARIO ──
       Impide que un usuario acceda al sistema sin borrar su cuenta.
       IMPORTANTE: no se permite desactivarse a uno mismo, porque
       si lo hicieras, te quedarías fuera del sistema sin poder volver. */
    if (isset($_POST['toggle_usuario'])) {
        $uid = (int) ($_POST['usuario_id'] ?? 0);
        $mi_id = (int) ($_SESSION['usuario']['id'] ?? 0);
        if ($uid > 0 && $uid !== $mi_id) {
            usuario_toggle_activo($pdo, $uid, $inmob_id);
            flash_set('success', 'Estado del usuario actualizado.');
        } else {
            flash_set('error', 'No puedes desactivar tu propia cuenta.');
        }
        header('Location: index.php?seccion=admin-usuarios&inmobiliaria_id=' . $inmob_id);
        exit;
    }

    /* ── ACCIÓN: RESETEAR CONTRASEÑA ──
       Genera una nueva contraseña temporal aleatoria y la asigna al usuario.
       password_hash() crea un hash seguro (la contraseña no se guarda en texto
       plano, sino cifrada; nadie puede leerla directamente en la BD).
       password_temporal = 1 obliga al usuario a cambiarla en su próximo login. */
    if (isset($_POST['reset_password'])) {
        $uid = (int) ($_POST['usuario_id'] ?? 0);
        if ($uid > 0) {
            $nueva_temp = generar_password_temporal();
            $hash = password_hash($nueva_temp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :h, password_temporal = 1 WHERE id = :id AND inmobiliaria_id = :iid');
            $stmt->execute(['h' => $hash, 'id' => $uid, 'iid' => $inmob_id]);
            flash_set('success', 'Contraseña reseteada. Nueva contraseña temporal: ' . $nueva_temp);
        }
        header('Location: index.php?seccion=admin-usuarios&inmobiliaria_id=' . $inmob_id);
        exit;
    }
}

/* ============================================================================
 * CARGA DE DATOS PARA LA INTERFAZ
 * ============================================================================ */

/* Obtenemos todos los usuarios que pertenecen a esta inmobiliaria. */
$usuarios = usuarios_listar_por_inmobiliaria($pdo, $inmob_id);

/* Guardamos nuestro propio ID para identificar cuál somos en la tabla.
   Así podemos mostrar "(tú)" junto a nuestro nombre y evitar que nos
   desactivemos a nosotros mismos. */
$mi_id = (int) ($_SESSION['usuario']['id'] ?? 0);
?>

<?php if ($mensaje_error): ?>
    <div class="alerta alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>

<div class="seccion_header animate_fadeIn">
    <div class="seccion_titulo_row">
        <h2><span class="menu_icono">👥</span> Usuarios — <?php echo e($inmobiliaria['nombre']); ?></h2>
        <?php if (es_superadmin()): ?>
            <a href="?seccion=admin-inmobiliarias" class="btn_secundario">← Volver a Inmobiliarias</a>
        <?php endif; ?>
    </div>
    <p class="seccion_subtitulo">Gestiona los usuarios y roles de tu equipo.</p>
</div>

<!-- Formulario nuevo usuario -->
<div class="panel_panel admin_form_panel animate_fadeIn">
    <div class="panel_header">
        <h3>➕ Nuevo Usuario</h3>
        <span class="panel_hint"><?php echo count($usuarios); ?> / <?php echo (int) $inmobiliaria['max_usuarios']; ?> usuarios</span>
    </div>
    <form method="POST" class="admin_form_grid">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="crear_usuario" value="1">

        <div class="campo">
            <label>Nombre *</label>
            <input type="text" name="nombre" required placeholder="Nombre completo">
        </div>
        <div class="campo">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="usuario@email.com">
        </div>
        <div class="campo">
            <label>Rol *</label>
            <select name="rol" required>
                <?php foreach ($roles_asignables as $key => $info): ?>
                    <option value="<?php echo e($key); ?>"<?php echo $key === 'agente' ? ' selected' : ''; ?>>
                        <?php echo e($info['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo campo_acciones">
            <button type="submit" class="btn_guardar">Crear usuario</button>
            <p class="campo_ayuda">🔑 Se generará una contraseña temporal que el usuario deberá cambiar en su primer inicio de sesión.</p>
        </div>
    </form>
</div>

<!-- Roles: leyenda visual -->
<div class="panel_panel animate_fadeIn">
    <div class="panel_header"><h3>🎭 Roles del sistema</h3></div>
    <div class="roles_leyenda">
        <?php
        $roles_iconos = [
            'jefe' => '👔', 'supervisor' => '📋', 'marketing' => '📢',
            'agente' => '🏠', 'agente_vendedor' => '🏷️', 'agente_comprador' => '🔑',
        ];
        foreach ($roles_asignables as $key => $info): ?>
            <div class="rol_chip">
                <span class="rol_chip_icono"><?php echo $roles_iconos[$key] ?? '👤'; ?></span>
                <span class="rol_chip_label"><?php echo e($info['label']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="roles_permisos_info">
        <p><strong>Jefe:</strong> Acceso completo + gestión de usuarios.</p>
        <p><strong>Supervisor:</strong> Acceso completo + importar/historial/configuración.</p>
        <p><strong>Marketing:</strong> Propiedades, alquileres y búsqueda avanzada de ambos equipos.</p>
        <p><strong>Agente:</strong> Clientes, prospectos, visitas, ofertas — vendedor + comprador.</p>
        <p><strong>Agente Vendedor:</strong> Solo secciones del equipo vendedor.</p>
        <p><strong>Agente Comprador:</strong> Solo secciones del equipo comprador.</p>
    </div>
</div>

<!-- Lista de usuarios -->
<div class="panel_panel animate_fadeIn">
    <div class="panel_header">
        <h3>📋 Equipo actual</h3>
    </div>

    <?php if (empty($usuarios)): ?>
        <p class="sin_datos_texto">No hay usuarios en esta inmobiliaria.</p>
    <?php else: ?>
    <div class="tabla_responsive">
        <table class="tabla_datos">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Miembro desde</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usr): ?>
                <tr class="<?php echo empty($usr['activo']) ? 'fila_inactiva' : ''; ?> <?php echo $usr['id'] === $mi_id ? 'fila_actual' : ''; ?>">
                    <td>
                        <strong><?php echo e($usr['nombre']); ?></strong>
                        <?php if ($usr['id'] === $mi_id): ?><small class="badge_yo">(tú)</small><?php endif; ?>
                    </td>
                    <td><?php echo e($usr['email']); ?></td>
                    <td>
                        <?php if ($usr['id'] !== $mi_id): ?>
                        <form method="POST" class="form_inline_rol">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="cambiar_rol" value="1">
                            <input type="hidden" name="usuario_id" value="<?php echo $usr['id']; ?>">
                            <select name="rol" onchange="this.form.submit()" class="select_rol">
                                <?php foreach ($roles_asignables as $key => $info): ?>
                                    <option value="<?php echo e($key); ?>"<?php echo $usr['rol'] === $key ? ' selected' : ''; ?>>
                                        <?php echo e($info['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                            <span class="badge_rol"><?php echo e($roles[$usr['rol']]['label'] ?? $usr['rol']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge_estado badge_estado--<?php echo $usr['activo'] ? 'disponible' : 'reservado'; ?>">
                            <?php echo $usr['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($usr['created_at'])); ?></td>
                    <td class="acciones_celda">
                        <?php if ($usr['id'] !== $mi_id): ?>
                        <form method="POST" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="toggle_usuario" value="1">
                            <input type="hidden" name="usuario_id" value="<?php echo $usr['id']; ?>">
                            <button type="submit" class="btn_editar_inline" title="<?php echo $usr['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                <?php echo $usr['activo'] ? '🔒' : '🔓'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('¿Resetear la contraseña de este usuario?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="reset_password" value="1">
                            <input type="hidden" name="usuario_id" value="<?php echo $usr['id']; ?>">
                            <button type="submit" class="btn_editar_inline" title="Resetear contraseña">
                                🔑
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="texto_sec">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
