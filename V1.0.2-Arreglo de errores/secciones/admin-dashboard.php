<?php
/*
 * Sección: Admin Dashboard (Solo SuperAdmin)
 * Rol: panel de control del programador/administrador del sistema.
 * Muestra: salud de la app, estadísticas, peticiones de usuarios, info del servidor.
 */
require_once __DIR__ . '/../inc/bootstrap.php';
verificar_acceso('admin-dashboard');

$pdo = db();
peticiones_asegurar_tabla($pdo);
$stats = sistema_obtener_estadisticas($pdo);
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

// POST: responder / cambiar estado de peticiones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['responder_peticion'])) {
        $pet_id = (int) ($_POST['peticion_id'] ?? 0);
        $respuesta = trim($_POST['respuesta'] ?? '');
        $nuevo_estado = $_POST['nuevo_estado'] ?? 'en_progreso';
        if ($pet_id > 0 && $respuesta !== '') {
            peticion_responder($pdo, $pet_id, $respuesta, $nuevo_estado);
            actividad_registrar($pdo, 'responder', 'peticion', $pet_id, 'Estado: ' . $nuevo_estado);
            flash_set('success', 'Petición #' . $pet_id . ' actualizada.');
        } else {
            flash_set('error', 'Debes escribir una respuesta.');
        }
        header('Location: index.php?seccion=admin-dashboard');
        exit;
    }
    if (isset($_POST['cambiar_estado_peticion'])) {
        $pet_id = (int) ($_POST['peticion_id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($pet_id > 0 && in_array($estado, ['abierta', 'en_progreso', 'resuelta', 'cerrada'], true)) {
            peticion_cambiar_estado($pdo, $pet_id, $estado);
            actividad_registrar($pdo, 'cambiar_estado', 'peticion', $pet_id, 'Nuevo estado: ' . $estado);
            flash_set('success', 'Estado actualizado.');
        }
        header('Location: index.php?seccion=admin-dashboard');
        exit;
    }
}

$filtro_peticiones = $_GET['estado_pet'] ?? 'todas';
$peticiones = peticiones_listar_todas($pdo, $filtro_peticiones !== 'todas' ? $filtro_peticiones : null);
$pet_detalle = null;
if (isset($_GET['ver_peticion'])) {
    $pet_detalle = peticion_obtener($pdo, (int) $_GET['ver_peticion']);
}
$pet_conteo = $stats['peticiones'];

// Calcular salud general
$salud_score = 100;
$salud_alertas = [];
if ($pet_conteo['abierta'] > 5) { $salud_score -= 15; $salud_alertas[] = $pet_conteo['abierta'] . ' peticiones abiertas sin atender'; }
if ($pet_conteo['abierta'] > 0 && $pet_conteo['abierta'] <= 5) { $salud_score -= 5; }
if ($stats['usuarios_pwd_temporal'] > 0) { $salud_score -= 10; $salud_alertas[] = $stats['usuarios_pwd_temporal'] . ' usuario(s) con contraseña temporal pendiente'; }
if ($stats['db_size_mb'] > 500) { $salud_score -= 10; $salud_alertas[] = 'Base de datos grande (' . $stats['db_size_mb'] . ' MB)'; }
$salud_score = max(0, $salud_score);
$salud_clase = $salud_score >= 80 ? 'salud_buena' : ($salud_score >= 50 ? 'salud_media' : 'salud_mala');
$salud_emoji = $salud_score >= 80 ? '🟢' : ($salud_score >= 50 ? '🟡' : '🔴');
?>

<?php if ($mensaje_error): ?>
    <div class="alerta alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>

<div class="seccion_header animate_fadeIn">
    <div class="seccion_titulo_row">
        <h2><span class="menu_icono">🛠️</span> Panel de Administración del Sistema</h2>
    </div>
    <p class="seccion_subtitulo">Monitoreo, peticiones y estado de la aplicación TinoProp.</p>
</div>

<!-- Salud del sistema -->
<div class="admin_dash_kpis animate_fadeIn">
    <div class="admin_kpi_card <?php echo $salud_clase; ?>">
        <div class="admin_kpi_icono"><?php echo $salud_emoji; ?></div>
        <div class="admin_kpi_valor"><?php echo $salud_score; ?>%</div>
        <div class="admin_kpi_label">Salud del Sistema</div>
    </div>
    <div class="admin_kpi_card">
        <div class="admin_kpi_icono">🏢</div>
        <div class="admin_kpi_valor"><?php echo $stats['inmobiliarias_activas']; ?> / <?php echo $stats['inmobiliarias_total']; ?></div>
        <div class="admin_kpi_label">Inmobiliarias Activas</div>
    </div>
    <div class="admin_kpi_card">
        <div class="admin_kpi_icono">👥</div>
        <div class="admin_kpi_valor"><?php echo $stats['usuarios_activos']; ?> / <?php echo $stats['usuarios_total']; ?></div>
        <div class="admin_kpi_label">Usuarios Activos</div>
    </div>
    <div class="admin_kpi_card">
        <div class="admin_kpi_icono">📩</div>
        <div class="admin_kpi_valor"><?php echo $pet_conteo['abierta'] + $pet_conteo['en_progreso']; ?></div>
        <div class="admin_kpi_label">Peticiones Pendientes</div>
    </div>
    <div class="admin_kpi_card">
        <div class="admin_kpi_icono">💾</div>
        <div class="admin_kpi_valor"><?php echo $stats['db_size_mb']; ?> MB</div>
        <div class="admin_kpi_label">Tamaño BD</div>
    </div>
</div>

<!-- Alertas del sistema -->
<?php if (!empty($salud_alertas)): ?>
<div class="panel_panel admin_alertas_panel animate_fadeIn">
    <div class="panel_header"><h3>⚠️ Alertas del Sistema</h3></div>
    <ul class="admin_alertas_lista">
        <?php foreach ($salud_alertas as $alerta): ?>
            <li class="admin_alerta_item">🔸 <?php echo e($alerta); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Actividad últimos 7 días -->
<div class="admin_dash_row animate_fadeIn">
    <div class="panel_panel admin_panel_half">
        <div class="panel_header"><h3>📈 Actividad (últimos 7 días)</h3></div>
        <div class="admin_actividad_grid">
            <div class="admin_stat_item">
                <span class="admin_stat_num"><?php echo $stats['clientes_7d']; ?></span>
                <span class="admin_stat_label">Nuevos clientes</span>
            </div>
            <div class="admin_stat_item">
                <span class="admin_stat_num"><?php echo $stats['propiedades_7d']; ?></span>
                <span class="admin_stat_label">Nuevas propiedades</span>
            </div>
            <div class="admin_stat_item">
                <span class="admin_stat_num"><?php echo $stats['visitas_7d']; ?></span>
                <span class="admin_stat_label">Nuevas visitas</span>
            </div>
        </div>
        <hr class="admin_hr">
        <div class="admin_totales_grid">
            <div class="admin_total"><strong><?php echo $stats['total_clientes']; ?></strong> Clientes totales</div>
            <div class="admin_total"><strong><?php echo $stats['total_prospectos']; ?></strong> Prospectos</div>
            <div class="admin_total"><strong><?php echo $stats['total_propiedades']; ?></strong> Propiedades</div>
            <div class="admin_total"><strong><?php echo $stats['total_visitas']; ?></strong> Visitas</div>
            <div class="admin_total"><strong><?php echo $stats['total_notas']; ?></strong> Notas</div>
            <div class="admin_total"><strong><?php echo $stats['total_proceso_propiedades']; ?></strong> Procesos</div>
        </div>
    </div>

    <div class="panel_panel admin_panel_half">
        <div class="panel_header"><h3>👥 Usuarios por Rol</h3></div>
        <?php if (empty($stats['usuarios_por_rol'])): ?>
            <p class="sin_datos_texto">No hay usuarios registrados.</p>
        <?php else: ?>
        <div class="admin_roles_lista">
            <?php
            $roles_info = roles_disponibles();
            $roles_iconos = ['superadmin' => '🛡️', 'jefe' => '👔', 'supervisor' => '📋', 'marketing' => '📢', 'agente' => '🏠', 'agente_vendedor' => '🏷️', 'agente_comprador' => '🔑'];
            foreach ($stats['usuarios_por_rol'] as $ru): ?>
            <div class="admin_rol_row">
                <span class="admin_rol_icono"><?php echo $roles_iconos[$ru['rol']] ?? '👤'; ?></span>
                <span class="admin_rol_nombre"><?php echo e($roles_info[$ru['rol']]['label'] ?? $ru['rol']); ?></span>
                <span class="admin_rol_total"><?php echo (int) $ru['total']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($stats['usuarios_pwd_temporal'] > 0): ?>
            <p class="admin_warning_text">🔐 <?php echo $stats['usuarios_pwd_temporal']; ?> usuario(s) aún con contraseña temporal.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Peticiones / Tickets -->
<div class="panel_panel animate_fadeIn">
    <div class="panel_header">
        <h3>📩 Peticiones de Usuarios</h3>
        <div class="admin_pet_filtros">
            <a href="?seccion=admin-dashboard&estado_pet=todas" class="btn_filtro <?php echo $filtro_peticiones === 'todas' ? 'btn_filtro_activo' : ''; ?>">Todas (<?php echo array_sum($pet_conteo); ?>)</a>
            <a href="?seccion=admin-dashboard&estado_pet=abierta" class="btn_filtro <?php echo $filtro_peticiones === 'abierta' ? 'btn_filtro_activo' : ''; ?>">Abiertas (<?php echo $pet_conteo['abierta']; ?>)</a>
            <a href="?seccion=admin-dashboard&estado_pet=en_progreso" class="btn_filtro <?php echo $filtro_peticiones === 'en_progreso' ? 'btn_filtro_activo' : ''; ?>">En Progreso (<?php echo $pet_conteo['en_progreso']; ?>)</a>
            <a href="?seccion=admin-dashboard&estado_pet=resuelta" class="btn_filtro <?php echo $filtro_peticiones === 'resuelta' ? 'btn_filtro_activo' : ''; ?>">Resueltas (<?php echo $pet_conteo['resuelta']; ?>)</a>
            <a href="?seccion=admin-dashboard&estado_pet=cerrada" class="btn_filtro <?php echo $filtro_peticiones === 'cerrada' ? 'btn_filtro_activo' : ''; ?>">Cerradas (<?php echo $pet_conteo['cerrada']; ?>)</a>
        </div>
    </div>

    <?php if (empty($peticiones)): ?>
        <p class="sin_datos_texto">No hay peticiones<?php echo $filtro_peticiones !== 'todas' ? ' con estado "' . e($filtro_peticiones) . '"' : ''; ?>.</p>
    <?php else: ?>
    <div class="tabla_responsive">
        <table class="tabla_datos">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Prioridad</th>
                    <th>Asunto</th>
                    <th>De</th>
                    <th>Inmobiliaria</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tipos_icono = ['error' => '🐛', 'mejora' => '💡', 'consulta' => '❓', 'urgente' => '🚨'];
                $prioridad_clase = ['baja' => 'badge_estado--disponible', 'media' => 'badge_estado--reservado', 'alta' => 'badge_estado--en_negociacion', 'critica' => 'badge_estado--vendido'];
                $estado_label = ['abierta' => 'Abierta', 'en_progreso' => 'En progreso', 'resuelta' => 'Resuelta', 'cerrada' => 'Cerrada'];
                foreach ($peticiones as $pet): ?>
                <tr class="<?php echo in_array($pet['prioridad'], ['alta', 'critica'], true) ? 'fila_prioridad_alta' : ''; ?>">
                    <td><strong>#<?php echo $pet['id']; ?></strong></td>
                    <td><?php echo $tipos_icono[$pet['tipo']] ?? '📝'; ?> <?php echo ucfirst(e($pet['tipo'])); ?></td>
                    <td><span class="badge_estado <?php echo $prioridad_clase[$pet['prioridad']] ?? ''; ?>"><?php echo ucfirst(e($pet['prioridad'])); ?></span></td>
                    <td><?php echo e($pet['asunto']); ?></td>
                    <td><?php echo e($pet['usuario_nombre'] ?? 'N/A'); ?></td>
                    <td><?php echo e($pet['inmobiliaria_nombre'] ?? 'N/A'); ?></td>
                    <td>
                        <form method="POST" class="form_inline_rol" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="cambiar_estado_peticion" value="1">
                            <input type="hidden" name="peticion_id" value="<?php echo $pet['id']; ?>">
                            <select name="estado" onchange="this.form.submit()" class="select_rol">
                                <?php foreach ($estado_label as $k => $v): ?>
                                <option value="<?php echo $k; ?>"<?php echo $pet['estado'] === $k ? ' selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                    <td><?php echo date('d/m/Y H:i', strtotime($pet['created_at'])); ?></td>
                    <td>
                        <a href="?seccion=admin-dashboard&ver_peticion=<?php echo $pet['id']; ?>&estado_pet=<?php echo e($filtro_peticiones); ?>" class="btn_editar_inline" title="Ver detalle">👁️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Detalle de petición seleccionada -->
<?php if ($pet_detalle): ?>
<div class="panel_panel admin_pet_detalle animate_fadeIn" id="detallePeticion">
    <div class="panel_header">
        <h3>📋 Petición #<?php echo $pet_detalle['id']; ?> — <?php echo e($pet_detalle['asunto']); ?></h3>
    </div>
    <div class="admin_pet_info_grid">
        <div><strong>Tipo:</strong> <?php echo $tipos_icono[$pet_detalle['tipo']] ?? '📝'; ?> <?php echo ucfirst(e($pet_detalle['tipo'])); ?></div>
        <div><strong>Prioridad:</strong> <span class="badge_estado <?php echo $prioridad_clase[$pet_detalle['prioridad']] ?? ''; ?>"><?php echo ucfirst(e($pet_detalle['prioridad'])); ?></span></div>
        <div><strong>Estado:</strong> <?php echo e($estado_label[$pet_detalle['estado']] ?? $pet_detalle['estado']); ?></div>
        <div><strong>Sección afectada:</strong> <?php echo e($pet_detalle['seccion_afectada'] ?: 'No especificada'); ?></div>
        <div><strong>Creada por:</strong> <?php echo e($pet_detalle['usuario_nombre'] ?? 'N/A'); ?> (<?php echo e($pet_detalle['usuario_email'] ?? ''); ?>)</div>
        <div><strong>Inmobiliaria:</strong> <?php echo e($pet_detalle['inmobiliaria_nombre'] ?? 'N/A'); ?></div>
        <div><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($pet_detalle['created_at'])); ?></div>
        <?php if ($pet_detalle['resuelto_at']): ?>
        <div><strong>Resuelta:</strong> <?php echo date('d/m/Y H:i', strtotime($pet_detalle['resuelto_at'])); ?></div>
        <?php endif; ?>
    </div>

    <div class="admin_pet_descripcion">
        <h4>📝 Descripción:</h4>
        <div class="admin_pet_texto"><?php echo nl2br(e($pet_detalle['descripcion'])); ?></div>
    </div>

    <?php if ($pet_detalle['respuesta_admin']): ?>
    <div class="admin_pet_respuesta_existente">
        <h4>💬 Respuesta del Administrador:</h4>
        <div class="admin_pet_texto"><?php echo nl2br(e($pet_detalle['respuesta_admin'])); ?></div>
    </div>
    <?php endif; ?>

    <div class="admin_pet_form_respuesta">
        <h4><?php echo $pet_detalle['respuesta_admin'] ? '✏️ Actualizar respuesta:' : '💬 Responder:'; ?></h4>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="responder_peticion" value="1">
            <input type="hidden" name="peticion_id" value="<?php echo $pet_detalle['id']; ?>">
            <textarea name="respuesta" rows="4" class="campo_textarea" placeholder="Escribe tu respuesta..." required><?php echo e($pet_detalle['respuesta_admin'] ?? ''); ?></textarea>
            <div class="admin_pet_form_acciones">
                <select name="nuevo_estado" class="campo_select">
                    <option value="en_progreso"<?php echo $pet_detalle['estado'] === 'en_progreso' ? ' selected' : ''; ?>>En Progreso</option>
                    <option value="resuelta">Resuelta</option>
                    <option value="cerrada">Cerrada</option>
                    <option value="abierta">Abierta</option>
                </select>
                <button type="submit" class="btn_guardar">Enviar respuesta</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Info del servidor -->
<div class="admin_dash_row animate_fadeIn">
    <div class="panel_panel admin_panel_half">
        <div class="panel_header"><h3>🖥️ Entorno del Servidor</h3></div>
        <div class="admin_server_info">
            <div class="admin_info_row"><span class="admin_info_label">PHP</span><span class="admin_info_value"><?php echo e($stats['php_version']); ?></span></div>
            <div class="admin_info_row"><span class="admin_info_label">MySQL</span><span class="admin_info_value"><?php echo e($stats['mysql_version']); ?></span></div>
            <div class="admin_info_row"><span class="admin_info_label">Servidor</span><span class="admin_info_value"><?php echo e($stats['server_software']); ?></span></div>
            <div class="admin_info_row"><span class="admin_info_label">Memoria PHP</span><span class="admin_info_value"><?php echo e($stats['memory_limit']); ?></span></div>
            <div class="admin_info_row"><span class="admin_info_label">Max Upload</span><span class="admin_info_value"><?php echo e($stats['max_upload']); ?></span></div>
            <div class="admin_info_row"><span class="admin_info_label">Zona Horaria</span><span class="admin_info_value"><?php echo e($stats['timezone']); ?></span></div>
            <div class="admin_info_row"><span class="admin_info_label">OS</span><span class="admin_info_value"><?php echo e(php_uname('s') . ' ' . php_uname('r')); ?></span></div>
        </div>
    </div>

    <div class="panel_panel admin_panel_half">
        <div class="panel_header"><h3>🗄️ Tablas de la Base de Datos</h3></div>
        <?php if (empty($stats['tablas'])): ?>
            <p class="sin_datos_texto">No se pudieron obtener las tablas.</p>
        <?php else: ?>
        <div class="tabla_responsive admin_tabla_mini">
            <table class="tabla_datos">
                <thead>
                    <tr><th>Tabla</th><th>Filas</th><th>Tamaño</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['tablas'] as $t): ?>
                    <tr>
                        <td><code><?php echo e($t['TABLE_NAME']); ?></code></td>
                        <td><?php echo number_format((int) $t['TABLE_ROWS']); ?></td>
                        <td><?php echo $t['size_kb']; ?> KB</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
