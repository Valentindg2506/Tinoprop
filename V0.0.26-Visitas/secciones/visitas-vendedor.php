<?php
/*
 * Sección: Visitas (Vendedor)
 * Rol: tabla para control de visitas a propiedades del equipo vendedor.
 * Sincronización bidireccional con recordatorios:
 * - Al crear visita aquí → se crea recordatorio automático en el calendario.
 * - Al crear recordatorio tipo "Visita" → se crea entrada en esta tabla.
 * Acciones: crear_visita, editar_visita, cambiar_estado, eliminar_visita.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$origen = 'visitas-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

$estados_visita = ['pendiente', 'realizada', 'cancelada'];

// Controlador POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_visita'])) {
        $errores = [];
        $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
        $fecha = trim($_POST['fecha_visita'] ?? '');
        $hora = trim($_POST['hora_visita'] ?? '') ?: null;
        $observaciones = trim($_POST['observaciones'] ?? '');

        if (!$fecha) {
            $errores[] = 'La fecha de visita es obligatoria.';
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nueva-visita');
            exit;
        }

        $id = visita_crear($pdo, 'vendedor', $propiedad_id ?: null, $cliente_id ?: null, $fecha, $hora, $observaciones, true);

        if ($id) {
            flash_set('success', 'Visita creada correctamente y añadida al calendario de recordatorios.');
        } else {
            flash_set('error', 'Error al crear la visita.');
        }
    }

    if (isset($_POST['editar_visita'])) {
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
            $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
            $fecha = trim($_POST['fecha_visita'] ?? '');
            $hora = trim($_POST['hora_visita'] ?? '') ?: null;
            $estado = trim($_POST['estado'] ?? 'pendiente');
            $observaciones = trim($_POST['observaciones'] ?? '');

            if ($fecha && validar_enum($estado, $estados_visita)) {
                $ok = visita_actualizar($pdo, $id_editar, $propiedad_id ?: null, $cliente_id ?: null, $fecha, $hora, $estado, $observaciones);
                flash_set($ok ? 'success' : 'error', $ok ? 'Visita actualizada correctamente.' : 'Error al actualizar.');
            } else {
                flash_set('error', 'Datos no válidos.');
            }
        }
    }

    if (isset($_POST['cambiar_estado'])) {
        $id_cambiar = (int) ($_POST['id'] ?? 0);
        $nuevo_estado = trim($_POST['estado'] ?? '');
        if ($id_cambiar > 0 && validar_enum($nuevo_estado, $estados_visita)) {
            $ok = visita_actualizar_estado($pdo, $id_cambiar, $nuevo_estado);
            flash_set($ok ? 'success' : 'error', $ok ? 'Estado actualizado.' : 'Error al cambiar estado.');
        }
    }

    if (isset($_POST['eliminar_visita'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $ok = visita_eliminar($pdo, $id_eliminar, true);
            flash_set($ok ? 'success' : 'error', $ok ? 'Visita eliminada correctamente.' : 'Error al eliminar.');
        }
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

// Filtro de estado desde GET
$filtro = $_GET['estado'] ?? 'todos';
if (!in_array($filtro, ['todos', 'pendiente', 'realizada', 'cancelada'])) {
    $filtro = 'todos';
}

// Listado de visitas
$visitas = visitas_listar($pdo, 'vendedor', $filtro);

// Propiedades del equipo vendedor para el select
$stmt = $pdo->prepare('SELECT id, titulo, referencia FROM propiedades WHERE equipo = :equipo ORDER BY titulo ASC');
$stmt->execute(['equipo' => 'vendedor']);
$propiedades_list = $stmt->fetchAll();

// Clientes vendedor para el select
$stmt = $pdo->prepare('SELECT id, nombre, apellido FROM clientes WHERE tipo = :tipo ORDER BY nombre ASC');
$stmt->execute(['tipo' => 'vendedor']);
$clientes_list = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <h2>📋 Visitas - Vendedor</h2>
    <div class="acciones_dashboard">
        <a href="#nueva-visita" class="btn_nuevo_cliente">+ Nueva Visita</a>
    </div>
</div>

<?php if ($mensaje_error): ?>
    <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>

<!-- Formulario nueva visita -->
<div id="nueva-visita" class="form_panel">
    <h3>Programar nueva visita</h3>
    <p class="config_hint">
        ℹ️ Al crear una visita se añadirá automáticamente un recordatorio en el calendario.
    </p>
    <form method="POST" class="form_grid">
        <div class="campo_input">
            <label for="propiedad_id">Propiedad</label>
            <select id="propiedad_id" name="propiedad_id">
                <option value="">-- Sin propiedad --</option>
                <?php foreach ($propiedades_list as $prop): ?>
                    <option value="<?php echo $prop['id']; ?>">
                        <?php echo e($prop['referencia'] ? $prop['referencia'] . ' - ' : ''); ?><?php echo e($prop['titulo']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo_input">
            <label for="cliente_id">Cliente</label>
            <select id="cliente_id" name="cliente_id">
                <option value="">-- Sin cliente --</option>
                <?php foreach ($clientes_list as $cli): ?>
                    <option value="<?php echo $cli['id']; ?>">
                        <?php echo e($cli['nombre'] . ' ' . $cli['apellido']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo_input">
            <label for="fecha_visita">Fecha</label>
            <input id="fecha_visita" name="fecha_visita" type="date" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="campo_input">
            <label for="hora_visita">Hora</label>
            <input id="hora_visita" name="hora_visita" type="time">
        </div>
        <div class="campo_input full_width">
            <label for="observaciones">Observaciones</label>
            <input id="observaciones" name="observaciones" type="text" placeholder="Notas sobre la visita...">
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_visita" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>

<!-- Filtros -->
<div class="filtros_visitas">
    <a href="?seccion=<?php echo $origen; ?>&estado=todos" class="filtro_btn <?php echo $filtro === 'todos' ? 'activo' : ''; ?>">Todas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=pendiente" class="filtro_btn <?php echo $filtro === 'pendiente' ? 'activo' : ''; ?>">🕐 Pendientes</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=realizada" class="filtro_btn <?php echo $filtro === 'realizada' ? 'activo' : ''; ?>">✅ Realizadas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=cancelada" class="filtro_btn <?php echo $filtro === 'cancelada' ? 'activo' : ''; ?>">❌ Canceladas</a>
</div>

<!-- Tabla de visitas -->
<div class="contenedor_tabla">
    <?php if (empty($visitas)): ?>
        <p class="sin_recordatorios">No hay visitas <?php echo $filtro !== 'todos' ? 'con estado "' . $filtro . '"' : 'registradas'; ?>.</p>
    <?php else: ?>
    <table class="tabla_datos tabla_visitas">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Propiedad</th>
                <th>Cliente</th>
                <th>Estado</th>
                <th>Observaciones</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($visitas as $v): ?>
            <tr class="fila_visita estado_visita_<?php echo e($v['estado']); ?>">
                <td>
                    <strong><?php echo date('d/m/Y', strtotime($v['fecha_visita'])); ?></strong>
                    <?php
                    $hoy = date('Y-m-d');
                    if ($v['fecha_visita'] === $hoy && $v['estado'] === 'pendiente') {
                        echo '<span class="badge_hoy">HOY</span>';
                    } elseif ($v['fecha_visita'] < $hoy && $v['estado'] === 'pendiente') {
                        echo '<span class="badge_vencida">VENCIDA</span>';
                    } elseif ($v['fecha_visita'] > $hoy && $v['estado'] === 'pendiente') {
                        $dias = (int) ((strtotime($v['fecha_visita']) - strtotime($hoy)) / 86400);
                        echo '<span class="badge_proxima">en ' . $dias . 'd</span>';
                    }
                    ?>
                </td>
                <td><?php echo $v['hora_visita'] ? date('H:i', strtotime($v['hora_visita'])) : '-'; ?></td>
                <td>
                    <?php if ($v['propiedad_titulo']): ?>
                        <a href="index.php?seccion=ver_propiedad&id=<?php echo $v['propiedad_id']; ?>&origen=<?php echo $origen; ?>">
                            <?php echo e($v['propiedad_titulo']); ?>
                        </a>
                        <?php if ($v['propiedad_ref']): ?>
                            <br><small><?php echo e($v['propiedad_ref']); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="texto_muted">Sin asignar</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($v['cliente_nombre']): ?>
                        <?php echo e($v['cliente_nombre'] . ' ' . $v['cliente_apellido']); ?>
                        <?php if ($v['cliente_telefono']): ?>
                            <br><small>📞 <?php echo e($v['cliente_telefono']); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="texto_muted">Sin asignar</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" class="form_inline">
                        <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                        <select name="estado" onchange="this.form.submit()" class="select_estado select_estado_<?php echo e($v['estado']); ?>">
                            <?php foreach ($estados_visita as $est): ?>
                                <option value="<?php echo e($est); ?>" <?php echo $v['estado'] === $est ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(e($est)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="cambiar_estado" value="1">
                    </form>
                </td>
                <td>
                    <span class="texto_observaciones"><?php echo e($v['observaciones'] ?: '-'); ?></span>
                </td>
                <td>
                    <div class="acciones_inline">
                        <details class="detalle_inline_visita">
                            <summary class="btn_icono btn_icono_editar" title="Editar">✏️</summary>
                            <div class="form_editar_visita">
                                <form method="POST" class="form_grid">
                                    <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                    <div class="campo_input">
                                        <label>Propiedad</label>
                                        <select name="propiedad_id">
                                            <option value="">-- Sin propiedad --</option>
                                            <?php foreach ($propiedades_list as $prop): ?>
                                                <option value="<?php echo $prop['id']; ?>" <?php echo ($v['propiedad_id'] == $prop['id']) ? 'selected' : ''; ?>>
                                                    <?php echo e($prop['referencia'] ? $prop['referencia'] . ' - ' : ''); ?><?php echo e($prop['titulo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo_input">
                                        <label>Cliente</label>
                                        <select name="cliente_id">
                                            <option value="">-- Sin cliente --</option>
                                            <?php foreach ($clientes_list as $cli): ?>
                                                <option value="<?php echo $cli['id']; ?>" <?php echo ($v['cliente_id'] == $cli['id']) ? 'selected' : ''; ?>>
                                                    <?php echo e($cli['nombre'] . ' ' . $cli['apellido']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo_input">
                                        <label>Fecha</label>
                                        <input name="fecha_visita" type="date" value="<?php echo e($v['fecha_visita']); ?>" required>
                                    </div>
                                    <div class="campo_input">
                                        <label>Hora</label>
                                        <input name="hora_visita" type="time" value="<?php echo e($v['hora_visita'] ?? ''); ?>">
                                    </div>
                                    <div class="campo_input">
                                        <label>Estado</label>
                                        <select name="estado">
                                            <?php foreach ($estados_visita as $est): ?>
                                                <option value="<?php echo e($est); ?>" <?php echo $v['estado'] === $est ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(e($est)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo_input">
                                        <label>Observaciones</label>
                                        <input name="observaciones" type="text" value="<?php echo e($v['observaciones'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" name="editar_visita" class="btn_guardar">Guardar</button>
                                </form>
                            </div>
                        </details>
                        <form method="POST" data-confirm="¿Eliminar esta visita y su recordatorio asociado? Esta accion no se puede deshacer.">
                            <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                            <button type="submit" name="eliminar_visita" class="btn_icono btn_icono_eliminar" title="Eliminar">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
