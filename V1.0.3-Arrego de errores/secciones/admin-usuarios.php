<?php
require_once __DIR__ . '/../inc/bootstrap.php';
verificar_acceso('admin-usuarios');
$pdo = db();
usuarios_asegurar_tabla($pdo);
inmobiliarias_asegurar_tabla($pdo);
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');
if (es_superadmin() && isset($_GET['inmobiliaria_id'])) {
    $inmob_id = (int) $_GET['inmobiliaria_id'];
} else {
    $inmob_id = usuario_inmobiliaria_id();
}
$inmobiliaria = inmobiliaria_obtener($pdo, $inmob_id);
if (!$inmobiliaria) {
    echo '<div class="error_acceso"><h2>Inmobiliaria no encontrada</h2><a href="?seccion=dashboard" class="btn_guardar">Volver</a></div>';
    return;
}
if (!es_superadmin() && $inmob_id !== usuario_inmobiliaria_id()) {
    http_response_code(403);
    echo '<div class="error_acceso"><h2>⛔ Acceso denegado</h2></div>';
    return;
}
$roles = roles_disponibles();
$roles_asignables = $roles;
unset($roles_asignables['superadmin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['crear_usuario'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'agente';
        $errores = [];
        if (!validar_requerido($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (!validar_email($email)) {
            $errores[] = 'Email no válido.';
        }
        if (!isset($roles_asignables[$rol])) {
            $errores[] = 'Rol no válido.';
        }
        if (empty($errores)) {
            $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = :e AND inmobiliaria_id = :iid LIMIT 1');
            $check->execute(['e' => $email, 'iid' => $inmob_id]);
            if ($check->fetch()) {
                $errores[] = 'Ese email ya está registrado en esta inmobiliaria.';
            }
        }
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
            $resultado = usuario_crear($pdo, [
                'inmobiliaria_id' => $inmob_id,
                'nombre' => $nombre,
                'email' => $email,
                'password' => '',
                'password_temporal' => true,
                'rol' => $rol,
            ]);
            $_SESSION['_pwd_temporal_nuevo'] = $resultado['password'];
            flash_set('success', 'Usuario "' . $nombre . '" creado como ' . ($roles[$rol]['label'] ?? $rol) . '. Se ha generado una contraseña temporal — cópiala antes de cerrar esta página.');
        }
        header('Location: index.php?seccion=admin-usuarios&inmobiliaria_id=' . $inmob_id);
        exit;
    }
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
    if (isset($_POST['reset_password'])) {
        $uid = (int) ($_POST['usuario_id'] ?? 0);
        if ($uid > 0) {
            $nueva_temp = generar_password_temporal();
            $hash = password_hash($nueva_temp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :h, password_temporal = 1 WHERE id = :id AND inmobiliaria_id = :iid');
            $stmt->execute(['h' => $hash, 'id' => $uid, 'iid' => $inmob_id]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['_pwd_temporal_reset'] = $nueva_temp;
                flash_set('success', 'Contraseña reseteada correctamente — cópiala antes de cerrar esta página.');
            } else {
                flash_set('error', 'Usuario no encontrado en esta inmobiliaria.');
            }
        }
        header('Location: index.php?seccion=admin-usuarios&inmobiliaria_id=' . $inmob_id);
        exit;
    }
}
$usuarios = usuarios_listar_por_inmobiliaria($pdo, $inmob_id);
$mi_id = (int) ($_SESSION['usuario']['id'] ?? 0);
?>
<?php if ($mensaje_error): ?>
    <div class="alerta alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>
<?php
$pwd_tmp_nuevo = $_SESSION['_pwd_temporal_nuevo'] ?? null;
$pwd_tmp_reset = $_SESSION['_pwd_temporal_reset'] ?? null;
unset($_SESSION['_pwd_temporal_nuevo'], $_SESSION['_pwd_temporal_reset']);
?>
<?php if ($pwd_tmp_nuevo || $pwd_tmp_reset): ?>
    <div class="alerta alerta_aviso" style="font-family:monospace;word-break:break-all">
        🔑 Contraseña temporal generada: <strong><?php echo e($pwd_tmp_nuevo ?? $pwd_tmp_reset); ?></strong>
        — Cópiala ahora. No se volverá a mostrar.
    </div>
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
