<?php
require_once __DIR__ . '/../inc/bootstrap.php';
verificar_acceso('peticiones');
$pdo = db();
peticiones_asegurar_tabla($pdo);
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');
$usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
$inmob_id = usuario_inmobiliaria_id();
$secciones_app = [
    '' => '— Seleccionar sección —',
    'dashboard' => 'Dashboard',
    'clientes' => 'Clientes',
    'prospectos' => 'Prospectos',
    'propiedades' => 'Propiedades',
    'alquileres' => 'Alquileres',
    'visitas' => 'Visitas',
    'ofertas' => 'Ofertas',
    'proceso' => 'Proceso',
    'post-venta' => 'Post-Venta',
    'busqueda-avanzada' => 'Búsqueda Avanzada',
    'matching' => 'Matching',
    'recordatorios' => 'Recordatorios',
    'importar-csv' => 'Importar CSV',
    'admin-usuarios' => 'Gestión Usuarios',
    'configuracion' => 'Configuración',
    'general' => 'General / Otro',
    'login' => 'Login / Acceso',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_peticion'])) {
    csrf_verify();
    $asunto = trim($_POST['asunto'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = $_POST['tipo'] ?? 'error';
    $prioridad = $_POST['prioridad'] ?? 'media';
    $seccion = $_POST['seccion_afectada'] ?? '';
    $errores = [];
    if ($asunto === '') {
        $errores[] = 'El asunto es obligatorio.';
    }
    if (strlen($asunto) > 200) {
        $errores[] = 'El asunto no puede superar 200 caracteres.';
    }
    if ($descripcion === '') {
        $errores[] = 'La descripción es obligatoria.';
    }
    if (!in_array($tipo, ['error', 'mejora', 'consulta', 'urgente'], true)) {
        $errores[] = 'Tipo no válido.';
    }
    if (!in_array($prioridad, ['baja', 'media', 'alta', 'critica'], true)) {
        $errores[] = 'Prioridad no válida.';
    }
    if (!empty($errores)) {
        flash_set('error', implode(' ', $errores));
    } else {
        $new_pet_id = peticion_crear($pdo, [
            'inmobiliaria_id' => $inmob_id,
            'usuario_id' => $usuario_id,
            'tipo' => $tipo,
            'prioridad' => $prioridad,
            'asunto' => $asunto,
            'descripcion' => $descripcion,
            'seccion_afectada' => $seccion ?: null,
        ]);
        actividad_registrar($pdo, 'crear', 'peticion', (int) $new_pet_id, 'Petición: ' . $asunto);
        flash_set('success', 'Petición enviada correctamente. El administrador la revisará pronto.');
    }
    header('Location: index.php?seccion=peticiones');
    exit;
}
$peticiones = peticiones_listar_por_inmobiliaria($pdo, $inmob_id);
$tipos_icono = ['error' => '🐛', 'mejora' => '💡', 'consulta' => '❓', 'urgente' => '🚨'];
$prioridad_clase = ['baja' => 'badge_estado--disponible', 'media' => 'badge_estado--reservado', 'alta' => 'badge_estado--en_negociacion', 'critica' => 'badge_estado--vendido'];
$estado_label = ['abierta' => 'Abierta', 'en_progreso' => 'En progreso', 'resuelta' => 'Resuelta', 'cerrada' => 'Cerrada'];
$estado_clase = ['abierta' => 'badge_estado--disponible', 'en_progreso' => 'badge_estado--reservado', 'resuelta' => 'badge_estado--en_negociacion', 'cerrada' => 'badge_estado--vendido'];
?>
<?php if ($mensaje_error): ?>
    <div class="alerta alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>
<div class="seccion_header animate_fadeIn">
    <div class="seccion_titulo_row">
        <h2><span class="menu_icono">📩</span> Peticiones al Administrador</h2>
    </div>
    <p class="seccion_subtitulo">Reporta errores, solicita mejoras o realiza consultas al equipo técnico.</p>
</div>
<!-- Formulario nueva petición -->
<div class="panel_panel admin_form_panel animate_fadeIn">
    <div class="panel_header">
        <h3>➕ Nueva Petición</h3>
    </div>
    <form method="POST" class="admin_form_grid">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="crear_peticion" value="1">
        <div class="campo">
            <label>Tipo *</label>
            <select name="tipo" required>
                <option value="error">🐛 Error / Bug</option>
                <option value="mejora">💡 Solicitud de mejora</option>
                <option value="consulta">❓ Consulta</option>
                <option value="urgente">🚨 Urgente</option>
            </select>
        </div>
        <div class="campo">
            <label>Prioridad *</label>
            <select name="prioridad" required>
                <option value="baja">Baja</option>
                <option value="media" selected>Media</option>
                <option value="alta">Alta</option>
                <option value="critica">Crítica</option>
            </select>
        </div>
        <div class="campo">
            <label>Sección afectada</label>
            <select name="seccion_afectada">
                <?php foreach ($secciones_app as $val => $label): ?>
                    <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo campo_full">
            <label>Asunto *</label>
            <input type="text" name="asunto" required maxlength="200" placeholder="Breve descripción del problema o solicitud">
        </div>
        <div class="campo campo_full">
            <label>Descripción detallada *</label>
            <textarea name="descripcion" rows="5" class="campo_textarea" required
                      placeholder="Describe el error, pasos para reproducirlo, qué esperabas que pasara, etc."></textarea>
        </div>
        <div class="campo campo_acciones">
            <button type="submit" class="btn_guardar">📩 Enviar petición</button>
        </div>
    </form>
</div>
<!-- Historial de peticiones -->
<div class="panel_panel animate_fadeIn">
    <div class="panel_header">
        <h3>📋 Mis Peticiones (<?php echo count($peticiones); ?>)</h3>
    </div>
    <?php if (empty($peticiones)): ?>
        <p class="sin_datos_texto">No has enviado peticiones aún.</p>
    <?php else: ?>
    <div class="tabla_responsive">
        <table class="tabla_datos">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipo</th>
                    <th>Prioridad</th>
                    <th>Asunto</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Respuesta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($peticiones as $pet): ?>
                <tr>
                    <td><strong>#<?php echo $pet['id']; ?></strong></td>
                    <td><?php echo $tipos_icono[$pet['tipo']] ?? '📝'; ?> <?php echo ucfirst(e($pet['tipo'])); ?></td>
                    <td><span class="badge_estado <?php echo $prioridad_clase[$pet['prioridad']] ?? ''; ?>"><?php echo ucfirst(e($pet['prioridad'])); ?></span></td>
                    <td>
                        <strong><?php echo e($pet['asunto']); ?></strong>
                        <?php if ($pet['seccion_afectada']): ?>
                            <br><small class="texto_sec">Sección: <?php echo e($pet['seccion_afectada']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge_estado <?php echo $estado_clase[$pet['estado']] ?? ''; ?>"><?php echo $estado_label[$pet['estado']] ?? $pet['estado']; ?></span></td>
                    <td><?php echo date('d/m/Y', strtotime($pet['created_at'])); ?></td>
                    <td>
                        <?php if ($pet['respuesta_admin']): ?>
                            <details class="pet_respuesta_details">
                                <summary>Ver respuesta</summary>
                                <div class="pet_respuesta_contenido"><?php echo nl2br(e($pet['respuesta_admin'])); ?></div>
                                <?php if ($pet['resuelto_at']): ?>
                                    <small class="texto_sec">Resuelta: <?php echo date('d/m/Y H:i', strtotime($pet['resuelto_at'])); ?></small>
                                <?php endif; ?>
                            </details>
                        <?php else: ?>
                            <span class="texto_sec">Pendiente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
