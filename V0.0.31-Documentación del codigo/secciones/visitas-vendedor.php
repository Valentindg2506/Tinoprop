<?php
/**
 * ========================================================================================
 * ARCHIVO: visitas-vendedor.php
 * SECCIÓN: Gestión de Visitas del Equipo Vendedor
 * ========================================================================================
 *
 * PROPÓSITO:
 * Este archivo gestiona las visitas a propiedades del equipo vendedor dentro del CRM
 * inmobiliario TinoProp. Permite a los agentes programar, editar, cambiar el estado
 * y eliminar visitas a las propiedades que están en venta.
 *
 * FUNCIONALIDAD PRINCIPAL:
 * - Crear nuevas visitas asociadas a una propiedad y/o cliente del equipo vendedor.
 * - Editar datos de visitas existentes (fecha, hora, propiedad, cliente, observaciones).
 * - Cambiar el estado de una visita (pendiente, realizada, cancelada) directamente
 *   desde la tabla con un selector desplegable.
 * - Eliminar visitas (también borra el recordatorio asociado en el calendario).
 * - Exportar todas las visitas del usuario a un archivo CSV.
 *
 * SINCRONIZACIÓN CON CALENDARIO:
 * Las visitas están sincronizadas bidireccionalmente con los recordatorios:
 * - Al crear una visita aquí → se crea automáticamente un recordatorio en el calendario.
 * - Al crear un recordatorio tipo "Visita" en el calendario → se crea entrada aquí.
 * Esto asegura que el agente nunca pierda de vista una visita programada.
 *
 * SEGURIDAD:
 * - csrf_verify(): Protege contra ataques CSRF (envíos de formularios falsos).
 * - e(): Escapa texto para evitar ataques XSS (inyección de código malicioso).
 * - sql_iid() / sql_iid_params(): Filtro multi-tenant que asegura que cada
 *   inmobiliaria solo vea sus propios datos.
 *
 * ACCIONES POST DISPONIBLES:
 * - crear_visita: Programa una nueva visita.
 * - editar_visita: Modifica los datos de una visita existente.
 * - cambiar_estado: Actualiza solo el estado de una visita.
 * - eliminar_visita: Borra una visita y su recordatorio asociado.
 *
 * @version V0.0.31
 */

/* Cargamos el bootstrap: este archivo inicializa la sesión, la conexión a base de datos,
   las funciones auxiliares (e(), csrf_verify(), flash_set/get, etc.) y verifica
   que el usuario esté autenticado. Sin él, nada funciona. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Obtenemos la conexión PDO a la base de datos.
   $pdo es el objeto que nos permite ejecutar consultas SQL de forma segura. */
$pdo = db();

/* Variable que identifica esta sección. Se usa para construir URLs de redirección
   y para saber de dónde viene el usuario al navegar entre páginas. */
$origen = 'visitas-vendedor';

/* ── EXPORTACIÓN CSV ──────────────────────────────────────────────────────────
   Si el usuario hace clic en "Exportar CSV", descargamos todas sus visitas
   del equipo vendedor en formato CSV (archivo de texto separado por comas
   que se puede abrir con Excel). */
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    /* Nos aseguramos de que la tabla 'visitas' exista en la base de datos.
       Si es la primera vez que se usa, la crea automáticamente. */
    visitas_asegurar_tabla($pdo);

    /* Obtenemos el ID del usuario actual desde la sesión para filtrar solo sus visitas. */
    $uid = (int)($_SESSION['usuario']['id'] ?? 0);

    /* Consulta SQL que obtiene todas las visitas del vendedor junto con
       los datos de la propiedad y del cliente asociado.
       - LEFT JOIN: une las tablas incluso si no hay propiedad o cliente asignado.
       - sql_iid('v'): añade filtro de inmobiliaria para seguridad multi-tenant.
       - ORDER BY v.id DESC: las más recientes primero. */
    $stmt = $pdo->prepare('SELECT v.*, p.titulo AS propiedad, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido FROM visitas v LEFT JOIN propiedades p ON v.propiedad_id = p.id LEFT JOIN clientes c ON v.cliente_id = c.id WHERE v.usuario_id = :uid AND v.equipo = "vendedor"' . sql_iid('v') . ' ORDER BY v.id DESC');
    $stmt->execute(['uid' => $uid] + sql_iid_params());

    /* Descarga automática del archivo CSV con los resultados. */
    exportar_csv($stmt->fetchAll(PDO::FETCH_ASSOC), 'visitas_vendedor.csv');
}
/* ── MENSAJES FLASH ────────────────────────────────────────────────────────────
   Los mensajes flash son notificaciones temporales que se muestran una sola vez.
   Se guardan en la sesión y se eliminan al leerlos. Sirven para mostrar
   "Visita creada correctamente" o "Error al crear la visita" tras una acción. */
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* Los tres estados posibles de una visita. Se usan tanto para validación
   como para los filtros y los selectores del formulario. */
$estados_visita = ['pendiente', 'realizada', 'cancelada'];

/* ══════════════════════════════════════════════════════════════════════════════
   CONTROLADOR POST — Procesa los formularios enviados por el usuario.
   Toda acción que modifica datos (crear, editar, eliminar) se hace por POST
   por seguridad. Nunca se modifican datos con GET (la barra de direcciones).
   ══════════════════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Verificamos el token CSRF: esto asegura que el formulario fue enviado
       realmente desde nuestra página y no desde un sitio malicioso externo. */
    csrf_verify();

    /* ── ACCIÓN: CREAR NUEVA VISITA ─────────────────────────────────────────── */
    if (isset($_POST['crear_visita'])) {
        /* Array para acumular errores de validación antes de intentar guardar. */
        $errores = [];

        /* Recogemos los datos del formulario.
           - (int): convertimos a número entero para prevenir inyección SQL.
           - trim(): eliminamos espacios en blanco al inicio y final del texto.
           - ?? 0 / ?? '': valor por defecto si el campo no viene en el formulario. */
        $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
        $fecha = trim($_POST['fecha_visita'] ?? '');
        $hora = trim($_POST['hora_visita'] ?? '') ?: null; // Si no hay hora, guardamos null
        $observaciones = trim($_POST['observaciones'] ?? '');

        /* Validación: la fecha es el único campo obligatorio. */
        if (!$fecha) {
            $errores[] = 'La fecha de visita es obligatoria.';
        }

        /* Si hay errores, los guardamos como mensaje flash y redirigimos
           al formulario (con el ancla #nueva-visita para hacer scroll automático). */
        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nueva-visita');
            exit;
        }

        /* Llamamos a la función auxiliar que inserta la visita en la BD.
           El último parámetro 'true' indica que también debe crear un
           recordatorio en el calendario (sincronización bidireccional). */
        $id = visita_crear($pdo, 'vendedor', $propiedad_id ?: null, $cliente_id ?: null, $fecha, $hora, $observaciones, true);

        if ($id) {
            /* Registramos la acción en el log de actividad para auditoría. */
            actividad_registrar($pdo, 'crear', 'visita', $id, 'Visita vendedor creada');
            flash_set('success', 'Visita creada correctamente y añadida al calendario de recordatorios.');
        } else {
            flash_set('error', 'Error al crear la visita.');
        }
    }

    /* ── ACCIÓN: EDITAR VISITA EXISTENTE ──────────────────────────────────── */
    if (isset($_POST['editar_visita'])) {
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            /* Recogemos todos los campos editables del formulario de edición. */
            $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
            $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
            $fecha = trim($_POST['fecha_visita'] ?? '');
            $hora = trim($_POST['hora_visita'] ?? '') ?: null;
            $estado = trim($_POST['estado'] ?? 'pendiente');
            $observaciones = trim($_POST['observaciones'] ?? '');

            /* validar_enum() comprueba que el estado sea uno de los valores permitidos
               ('pendiente', 'realizada', 'cancelada'). Esto previene que alguien
               manipule el formulario e inyecte un valor no válido. */
            if ($fecha && validar_enum($estado, $estados_visita)) {
                $ok = visita_actualizar($pdo, $id_editar, $propiedad_id ?: null, $cliente_id ?: null, $fecha, $hora, $estado, $observaciones);
                flash_set($ok ? 'success' : 'error', $ok ? 'Visita actualizada correctamente.' : 'Error al actualizar.');
            } else {
                flash_set('error', 'Datos no válidos.');
            }
        }
    }

    /* ── ACCIÓN: CAMBIAR ESTADO RÁPIDO ────────────────────────────────────── 
       Esta acción se dispara cuando el usuario cambia el estado directamente
       desde el selector desplegable en la tabla (sin abrir el formulario completo).
       El formulario se envía automáticamente al cambiar el valor (onchange). */
    if (isset($_POST['cambiar_estado'])) {
        $id_cambiar = (int) ($_POST['id'] ?? 0);
        $nuevo_estado = trim($_POST['estado'] ?? '');
        if ($id_cambiar > 0 && validar_enum($nuevo_estado, $estados_visita)) {
            $ok = visita_actualizar_estado($pdo, $id_cambiar, $nuevo_estado);
            flash_set($ok ? 'success' : 'error', $ok ? 'Estado actualizado.' : 'Error al cambiar estado.');
        }
    }

    /* ── ACCIÓN: ELIMINAR VISITA ─────────────────────────────────────────── 
       El parámetro 'true' en visita_eliminar() indica que también debe borrar
       el recordatorio del calendario asociado a esta visita. */
    if (isset($_POST['eliminar_visita'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $ok = visita_eliminar($pdo, $id_eliminar, true);
            if ($ok) { actividad_registrar($pdo, 'eliminar', 'visita', $id_eliminar, 'Visita vendedor eliminada'); }
            flash_set($ok ? 'success' : 'error', $ok ? 'Visita eliminada correctamente.' : 'Error al eliminar.');
        }
    }

    /* Patrón POST-Redirect-GET: tras procesar cualquier acción, redirigimos
       al usuario a la misma página con GET. Esto evita que al pulsar F5
       se reenvíe el formulario y se duplique la acción. */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════════
   CARGA DE DATOS — Preparamos toda la información que necesita la vista (HTML).
   ══════════════════════════════════════════════════════════════════════════════ */

/* Leemos el filtro de estado desde la URL (?estado=pendiente).
   Si no se pasa o es un valor no válido, mostramos todas las visitas. */
$filtro = $_GET['estado'] ?? 'todos';
if (!in_array($filtro, ['todos', 'pendiente', 'realizada', 'cancelada'])) {
    $filtro = 'todos';
}

/* Obtenemos la lista de visitas del equipo vendedor, filtrada por estado.
   La función visitas_listar() ya incluye los JOINs con propiedades y clientes. */
$visitas = visitas_listar($pdo, 'vendedor', $filtro);

/* Cargamos las propiedades del equipo vendedor para el desplegable <select>
   del formulario de creación/edición de visitas.
   - sql_iid(): añade la condición AND inmobiliaria_id = ? para multi-tenant.
   - sql_iid_params(): devuelve el valor del parámetro de inmobiliaria. */
$stmt = $pdo->prepare('SELECT id, titulo, referencia FROM propiedades WHERE equipo = :equipo' . sql_iid() . ' ORDER BY titulo ASC');
$stmt->execute(['equipo' => 'vendedor'] + sql_iid_params());
$propiedades_list = $stmt->fetchAll();

/* Cargamos los clientes de tipo vendedor para el desplegable <select>.
   Solo aparecerán clientes de la misma inmobiliaria (filtro multi-tenant). */
$stmt = $pdo->prepare('SELECT id, nombre, apellido FROM clientes WHERE tipo = :tipo' . sql_iid() . ' ORDER BY nombre ASC');
$stmt->execute(['tipo' => 'vendedor'] + sql_iid_params());
$clientes_list = $stmt->fetchAll();
?>

<!-- ══════════════════════════════════════════════════════════════════════════
     VISTA HTML — Todo lo que el usuario ve en pantalla.
     Desde aquí se mezcla PHP con HTML para generar la página dinámica.
     ══════════════════════════════════════════════════════════════════════════ -->

<!-- Encabezado de la sección con el botón para crear una nueva visita.
     El href="#nueva-visita" hace scroll hasta el formulario de más abajo. -->
<div class="encabezado_seccion">
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

<!-- ── FORMULARIO: NUEVA VISITA ────────────────────────────────────────────
     Este formulario permite al agente programar una nueva visita.
     Al enviarse, los datos van al controlador POST de arriba (crear_visita).
     - method="POST": envía datos de forma segura (no visibles en la URL).
     - data-validar: atributo que activa la validación JavaScript del lado del cliente.
     - csrf_field(): genera un campo oculto con un token de seguridad único. -->
<div id="nueva-visita" class="form_panel">
    <h3>Programar nueva visita</h3>
    <p class="config_hint">
        ℹ️ Al crear una visita se añadirá automáticamente un recordatorio en el calendario.
    </p>
    <form method="POST" class="form_grid" data-validar>
        <?php /* Token CSRF: campo oculto que protege contra envíos de formularios falsificados */ ?>
        <?php echo csrf_field(); ?>
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

<!-- ── FILTROS DE ESTADO ───────────────────────────────────────────────────
     Botones para filtrar las visitas por estado. Cada enlace recarga la página
     con un parámetro ?estado=X en la URL, que se lee arriba en $filtro. -->
<div class="filtros_visitas">
    <a href="?seccion=<?php echo $origen; ?>&estado=todos" class="filtro_btn <?php echo $filtro === 'todos' ? 'activo' : ''; ?>">Todas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=pendiente" class="filtro_btn <?php echo $filtro === 'pendiente' ? 'activo' : ''; ?>">🕐 Pendientes</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=realizada" class="filtro_btn <?php echo $filtro === 'realizada' ? 'activo' : ''; ?>">✅ Realizadas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=cancelada" class="filtro_btn <?php echo $filtro === 'cancelada' ? 'activo' : ''; ?>">❌ Canceladas</a>
</div>

<!-- ── TABLA DE VISITAS ────────────────────────────────────────────────────
     Muestra todas las visitas en formato tabla con columnas ordenables.
     Cada fila incluye: fecha, hora, propiedad, cliente, estado, observaciones
     y acciones (editar/eliminar). -->
<div class="barra_acciones_tabla">
    <span>Visitas vendedor</span>
    <a href="index.php?seccion=visitas-vendedor&exportar=csv" class="btn_exportar">📥 Exportar CSV</a>
</div>
<div class="contenedor_tabla">
    <?php if (empty($visitas)): ?>
        <p class="sin_recordatorios">No hay visitas <?php echo $filtro !== 'todos' ? 'con estado "' . $filtro . '"' : 'registradas'; ?>.</p>
    <?php else: ?>
    <table class="tabla_datos tabla_visitas">
        <thead>
            <tr>
                <th class="th_ordenable">Fecha</th>
                <th class="th_ordenable">Hora</th>
                <th class="th_ordenable">Propiedad</th>
                <th class="th_ordenable">Cliente</th>
                <th class="th_ordenable">Estado</th>
                <th class="th_ordenable">Observaciones</th>
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
                        <?php echo csrf_field(); ?>
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
                                    <?php echo csrf_field(); ?>
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
                            <?php echo csrf_field(); ?>
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
