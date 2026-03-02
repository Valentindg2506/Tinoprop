<?php
/**
 * ========================================================================================
 * ARCHIVO: proceso-vendedor.php
 * SECCIÓN: Pipeline de Proceso de Propiedades (Equipo Vendedor)
 * ========================================================================================
 *
 * PROPÓSITO:
 * Implementa un tablero Kanban visual para gestionar el pipeline (flujo de trabajo)
 * de las propiedades del equipo vendedor. Cada propiedad atraviesa varias etapas
 * desde su captación hasta su cierre o descarte.
 *
 * ¿QUÉ ES UN KANBAN?
 * Un kanban es un tablero con columnas que representan etapas de un proceso.
 * Las propiedades se muestran como "tarjetas" que se pueden arrastrar de una
 * columna a otra para indicar su progreso. Es como un tablero de corcho con
 * post-its que se van moviendo de izquierda a derecha.
 *
 * ETAPAS DEL PIPELINE (de izquierda a derecha):
 * 1. Captación → Se contacta al propietario para captar la propiedad.
 * 2. Documentación → Se recopila documentación legal y técnica.
 * 3. Publicada → La propiedad ya está anunciada en portales.
 * 4. Visitas → Se están realizando visitas con compradores potenciales.
 * 5. Negociación → Hay ofertas sobre la mesa, se negocia el precio.
 * 6. Cerrada → La operación se ha completado con éxito.
 * 7. Descartada → Se ha descartado la propiedad (el dueño retira, etc.).
 *
 * FUNCIONALIDAD:
 * - Arrastrar tarjetas entre columnas (drag & drop vía AJAX sin recargar página).
 * - Crear un nuevo proceso asignado a una propiedad existente.
 * - Editar el proceso (cambiar etapa y notas).
 * - Eliminar un proceso definitivamente.
 *
 * SEGURIDAD:
 * - puede_ver_vendedor(): verifica que el usuario tiene permisos para esta sección.
 * - csrf_verify(), e(), sql_iid(): protección CSRF, XSS y multi-tenant.
 *
 * @version V0.0.31
 */

/* Bootstrap: inicializa sesión, BD, funciones y autenticación. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Control de acceso: si el usuario no tiene permiso para ver la sección vendedor,
   devolvemos error HTTP 403 (prohibido) y cerramos la ejecución.
   Esto protege también las peticiones AJAX de arrastre de tarjetas. */
if (!puede_ver_vendedor()) { http_response_code(403); exit; }

/* Definición de las columnas del tablero Kanban.
   La clave (ej: 'captacion') se usa internamente en la BD.
   El valor (ej: 'Captación') es lo que ve el usuario en pantalla. */
$columnas_proceso = [
    "captacion"     => "Captación",
    "documentacion" => "Documentación",
    "publicada"     => "Publicada",
    "visitas"       => "Visitas",
    "negociacion"   => "Negociación",
    "cerrada"       => "Cerrada",
    "descartada"    => "Descartada"
];

/* Conexión a la BD y aseguramos que la tabla 'proceso_propiedades' exista. */
$pdo = db();
proceso_propiedades_asegurar_tabla($pdo);

$origen = 'proceso-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ═════════════════════════════════════════════════════════════════════════════
   CONTROLADOR POST — Procesa movimientos drag & drop y operaciones CRUD.
   ═════════════════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    /* ── ACCIÓN: MOVER TARJETA (Drag & Drop vía AJAX) ─────────────────
       Cuando el usuario arrastra una tarjeta de una columna a otra,
       JavaScript envía una petición POST con el ID del proceso y la
       nueva etapa. Respondemos con JSON (no HTML) porque es una
       petición AJAX (sin recargar la página). */
    if (isset($_POST['mover_proceso_drag'])) {
        /* Indicamos que la respuesta es JSON. */
        header('Content-Type: application/json; charset=utf-8');

        $id_mover = (int) ($_POST['id'] ?? 0);
        $etapa = $_POST['etapa'] ?? '';

        /* Validamos que el ID sea positivo y la etapa exista en nuestro mapa. */
        if ($id_mover <= 0 || !validar_enum($etapa, array_keys($columnas_proceso))) {
            http_response_code(422); // 422 = datos no procesables
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de movimiento no validos.']);
            exit;
        }

        /* Actualizamos la etapa del proceso en la BD.
           La condición equipo = 'vendedor' + sql_iid() aseguran que solo
           modificamos procesos del equipo vendedor de nuestra inmobiliaria. */
        $stmt = $pdo->prepare('UPDATE proceso_propiedades SET etapa = :etapa WHERE id = :id AND equipo = :equipo' . sql_iid());
        $stmt->execute([
            'etapa' => $etapa,
            'id'    => $id_mover,
            'equipo' => 'vendedor',
        ] + sql_iid_params());

        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── ACCIÓN: CREAR NUEVO PROCESO ─────────────────────────────────────
       Vincula una propiedad existente a un proceso de seguimiento.
       Cada propiedad solo puede tener un proceso activo a la vez. */
    if (isset($_POST['crear_proceso'])) {
        $errores = [];
        $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
        $etapa = $_POST['etapa'] ?? 'captacion'; // Por defecto empieza en Captación
        $notas = trim($_POST['notas'] ?? '');

        if ($propiedad_id <= 0) {
            $errores[] = 'Debes seleccionar una propiedad.';
        }
        if (!validar_enum($etapa, array_keys($columnas_proceso))) {
            $errores[] = 'La etapa seleccionada no es valida.';
        }

        /* Verificamos que la propiedad exista y pertenezca al equipo vendedor. */
        if ($propiedad_id > 0) {
            $check = $pdo->prepare('SELECT id FROM propiedades WHERE id = :id AND equipo = :equipo' . sql_iid());
            $check->execute(['id' => $propiedad_id, 'equipo' => 'vendedor'] + sql_iid_params());
            if (!$check->fetch()) {
                $errores[] = 'La propiedad seleccionada no existe o no pertenece al equipo vendedor.';
            }
        }

        /* Verificamos que no exista ya un proceso para esta misma propiedad,
           porque cada propiedad solo puede estar en un pipeline a la vez. */
        if ($propiedad_id > 0 && empty($errores)) {
            $check = $pdo->prepare('SELECT id FROM proceso_propiedades WHERE propiedad_id = :pid AND equipo = :equipo' . sql_iid());
            $check->execute(['pid' => $propiedad_id, 'equipo' => 'vendedor'] + sql_iid_params());
            if ($check->fetch()) {
                $errores[] = 'Ya existe un proceso para esta propiedad.';
            }
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-proceso');
            exit;
        }

        /* Insertamos el nuevo proceso en la BD con la etapa inicial
           y el ID de inmobiliaria para aislamiento multi-tenant. */
        $stmt = $pdo->prepare(
            'INSERT INTO proceso_propiedades (propiedad_id, equipo, etapa, notas, inmobiliaria_id)
             VALUES (:propiedad_id, :equipo, :etapa, :notas, :iid)'
        );
        $stmt->execute([
            'propiedad_id' => $propiedad_id,
            'equipo'       => 'vendedor',
            'etapa'        => $etapa,
            'notas'        => $notas,
            'iid'          => usuario_inmobiliaria_id(),
        ]);
        flash_set('success', 'Proceso creado correctamente.');
    }

    /* ── ACCIÓN: EDITAR PROCESO EXISTENTE ───────────────────────────────
       Permite cambiar la etapa y las notas de un proceso desde el
       formulario inline que aparece al hacer clic en "Editar" en la tarjeta. */
    if (isset($_POST['editar_proceso'])) {
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            $errores = [];
            $etapa = $_POST['etapa'] ?? 'captacion';
            $notas = trim($_POST['notas'] ?? '');

            if (!validar_enum($etapa, array_keys($columnas_proceso))) {
                $errores[] = 'La etapa seleccionada no es valida.';
            }

            if (!empty($errores)) {
                flash_set('error', implode(' ', $errores));
                header('Location: index.php?seccion=' . $origen);
                exit;
            }

            /* Actualizamos la etapa y notas del proceso. La condición equipo + sql_iid()
               asegura que solo se modifiquen datos de nuestra inmobiliaria. */
            $stmt = $pdo->prepare(
                'UPDATE proceso_propiedades SET etapa = :etapa, notas = :notas WHERE id = :id AND equipo = :equipo' . sql_iid()
            );
            $stmt->execute([
                'etapa'  => $etapa,
                'notas'  => $notas,
                'id'     => $id_editar,
                'equipo' => 'vendedor',
            ] + sql_iid_params());
            flash_set('success', 'Proceso actualizado correctamente.');
        }
    }

    /* ── ACCIÓN: ELIMINAR PROCESO ───────────────────────────────────────
       Borra definitivamente el proceso (no la propiedad, solo su seguimiento). */
    if (isset($_POST['eliminar_proceso'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare('DELETE FROM proceso_propiedades WHERE id = :id AND equipo = :equipo' . sql_iid());
            $stmt->execute(['id' => $id_eliminar, 'equipo' => 'vendedor'] + sql_iid_params());
        }
        flash_set('success', 'Proceso eliminado correctamente.');
    }

    /* Redirección POST-Redirect-GET. */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ══════ CARGA DE DATOS PARA EL TABLERO KANBAN ══════════════════════════════════ */

/* Consulta SQL que obtiene todos los procesos del equipo vendedor
   junto con los datos de su propiedad asociada (título, tipo, ubicación, precio...).
   INNER JOIN: solo trae procesos cuya propiedad aún existe.
   Ordenamos por ID descendente (los más recientes primero). */
$stmt = $pdo->prepare(
    'SELECT pp.id, pp.propiedad_id, pp.etapa, pp.notas,
            p.titulo, p.tipo, p.ubicacion, p.precio, p.moneda, p.estado AS estado_propiedad, p.referencia
     FROM proceso_propiedades pp
     INNER JOIN propiedades p ON pp.propiedad_id = p.id
     WHERE pp.equipo = :equipo' . sql_iid('pp') . '
     ORDER BY pp.id DESC'
);
$stmt->execute(['equipo' => 'vendedor'] + sql_iid_params());
$procesos_db = $stmt->fetchAll();

/* Propiedades disponibles: propiedades del equipo vendedor que todavía
   NO tienen un proceso asignado. Esto evita duplicados en el kanban.
   La subconsulta NOT IN (...) excluye las que ya están en proceso_propiedades. */
$stmt = $pdo->prepare(
    'SELECT p.id, p.titulo, p.referencia, p.operacion
     FROM propiedades p
     WHERE p.equipo = :equipo' . sql_iid('p') . '
       AND p.id NOT IN (SELECT propiedad_id FROM proceso_propiedades WHERE equipo = :equipo2)
     ORDER BY p.id DESC'
);
$stmt->execute(['equipo' => 'vendedor', 'equipo2' => 'vendedor'] + sql_iid_params());
$propiedades_disponibles = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <div class="acciones_dashboard">
        <button type="button" class="btn_guardar btn-editar-kanban-proceso">Editar orden</button>
        <a href="#nuevo-proceso" class="btn_nuevo_cliente">+ Nuevo Proceso</a>
    </div>
</div>

<div id="nuevo-proceso" class="form_panel">
    <h3>Asignar proceso a propiedad</h3>
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid">
        <?php echo csrf_field(); ?>
        <div class="campo_input">
            <label for="propiedad_id">Propiedad</label>
            <select id="propiedad_id" name="propiedad_id" required>
                <option value="">-- Selecciona una propiedad --</option>
                <?php foreach ($propiedades_disponibles as $prop): ?>
                    <option value="<?php echo $prop['id']; ?>">
                        <?php echo e($prop['referencia'] ? $prop['referencia'] . ' - ' : ''); ?><?php echo e($prop['titulo']); ?> (<?php echo e($prop['operacion']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo_input">
            <label for="etapa">Etapa inicial</label>
            <select id="etapa" name="etapa">
                <?php foreach ($columnas_proceso as $clave_etapa => $titulo): ?>
                    <option value="<?php echo e($clave_etapa); ?>"><?php echo e($titulo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo_input">
            <label for="notas">Notas</label>
            <input id="notas" name="notas" type="text" placeholder="Observaciones del proceso...">
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_proceso" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>

<div class="kanban_contenedor">
    
    <?php foreach ($columnas_proceso as $clave_etapa => $titulo): ?>
        
        <div class="kanban_columna" data-estado="<?php echo e($clave_etapa); ?>">
            
            <div class="kanban_header <?php echo $clave_etapa; ?>">
                <h3><?php echo $titulo; ?></h3>
                <span class="contador">
                    <?php 
                    $total = count(array_filter($procesos_db, function($p) use ($clave_etapa) {
                        return $p['etapa'] == $clave_etapa;
                    }));
                    echo $total;
                    ?>
                </span>
            </div>

            <div class="kanban_body" data-estado="<?php echo e($clave_etapa); ?>">
                <?php 
                foreach ($procesos_db as $proceso): 
                    if($proceso['etapa'] == $clave_etapa):
                ?>
                    <div class="tarjeta_proceso" 
                        draggable="false" 
                        data-id="<?php echo $proceso['id']; ?>"
                        id="proceso_<?php echo $proceso['id']; ?>">
                        
                        <h4><?php echo e($proceso['titulo']); ?></h4>
                        <p class="interes">
                            <?php echo e($proceso['tipo']); ?> · <?php echo e($proceso['ubicacion']); ?>
                        </p>
                        <div class="datos_contacto">
                            <span>💰 <?php echo e(format_price((float) $proceso['precio'], $proceso['moneda'], null)); ?></span>
                            <?php if ($proceso['referencia']): ?>
                                <span style="margin-left: 8px;">📋 <?php echo e($proceso['referencia']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="datos_contacto">
                            <span class="badge_estado badge_estado--<?php echo e(map_estado_clase($proceso['estado_propiedad'])); ?>">
                                <?php echo e($proceso['estado_propiedad']); ?>
                            </span>
                        </div>
                        <?php if ($proceso['notas']): ?>
                            <p class="interes" style="margin-top: 4px;">📝 <?php echo e($proceso['notas']); ?></p>
                        <?php endif; ?>
                        <div class="acciones_tarjeta">
                            <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $proceso['propiedad_id']; ?>&origen=proceso-vendedor" title="Ver propiedad">👁️</a>
                            <form method="POST" data-confirm="¿Eliminar este proceso? Esta accion no se puede deshacer.">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $proceso['id']; ?>">
                                <button type="submit" name="eliminar_proceso" class="btn_peligro">Eliminar</button>
                            </form>
                        </div>

                        <details class="detalle_inline">
                            <summary>Editar</summary>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $proceso['id']; ?>">
                                <div class="campo_input">
                                    <label>Etapa</label>
                                    <select name="etapa">
                                        <?php foreach ($columnas_proceso as $clave_e => $titulo_e): ?>
                                            <option value="<?php echo e($clave_e); ?>" <?php echo $proceso['etapa'] === $clave_e ? 'selected' : ''; ?>>
                                                <?php echo e($titulo_e); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="campo_input">
                                    <label>Notas</label>
                                    <input name="notas" type="text" value="<?php echo e($proceso['notas'] ?? ''); ?>">
                                </div>
                                <button type="submit" name="editar_proceso" class="btn_guardar">Guardar cambios</button>
                            </form>
                        </details>
                    </div>
                <?php 
                    endif; 
                endforeach; 
                ?>
            </div>
            
        </div>

    <?php endforeach; ?>

</div>
