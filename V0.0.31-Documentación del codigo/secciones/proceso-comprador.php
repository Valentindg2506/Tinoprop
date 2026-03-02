<?php
/**
 * ========================================================================================
 * ARCHIVO: proceso-comprador.php
 * SECCIÓN: Pipeline de Proceso de Propiedades (Equipo Comprador)
 * ========================================================================================
 *
 * PROPÓSITO:
 * Tablero Kanban para gestionar el flujo de trabajo de propiedades del equipo comprador.
 * Funciona idénticamente a proceso-vendedor.php pero filtrado por equipo 'comprador'.
 *
 * ¿POR QUÉ HAY DOS ARCHIVOS SEPARADOS?
 * El equipo vendedor gestiona propiedades que la inmobiliaria tiene en cartera para vender.
 * El equipo comprador busca propiedades en el mercado para sus clientes compradores.
 * Cada equipo tiene su propio pipeline independiente.
 *
 * ETAPAS: Captación → Documentación → Publicada → Visitas → Negociación → Cerrada → Descartada
 *
 * FUNCIONALIDAD:
 * - Drag & drop AJAX para mover tarjetas entre columnas.
 * - CRUD completo de procesos (crear, editar, eliminar).
 * - Cada propiedad solo puede tener un proceso activo a la vez.
 *
 * @version V0.0.31
 */

/* Bootstrap: inicializa sesión, BD, funciones y autenticación. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Control de acceso: solo usuarios con permisos de equipo comprador pueden entrar.
   Retorna HTTP 403 (prohibido) si no tiene acceso. */
if (!puede_ver_comprador()) { http_response_code(403); exit; }

/* Columnas del tablero Kanban. La clave se almacena en la BD,
   el valor es el nombre visible en la interfaz. */
$columnas_proceso = [
    "captacion"     => "Captación",
    "documentacion" => "Documentación",
    "publicada"     => "Publicada",
    "visitas"       => "Visitas",
    "negociacion"   => "Negociación",
    "cerrada"       => "Cerrada",
    "descartada"    => "Descartada"
];

/* Conexión BD y auto-creación de la tabla si no existe. */
$pdo = db();
proceso_propiedades_asegurar_tabla($pdo);
$origen = 'proceso-comprador';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ═════════════════════════════════════════════════════════════════════════════
   CONTROLADOR POST — Procesa drag & drop AJAX y operaciones CRUD.
   ═════════════════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    /* ── MOVER TARJETA POR DRAG & DROP (petición AJAX, respuesta JSON) ── */
    if (isset($_POST['mover_proceso_drag'])) {
        header('Content-Type: application/json; charset=utf-8');

        $id_mover = (int) ($_POST['id'] ?? 0);
        $etapa = $_POST['etapa'] ?? '';

        /* Validamos datos antes de actualizar. */
        if ($id_mover <= 0 || !validar_enum($etapa, array_keys($columnas_proceso))) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de movimiento no validos.']);
            exit;
        }

        /* Actualizamos la etapa. Filtramos por equipo='comprador' y inmobiliaria. */
        $stmt = $pdo->prepare('UPDATE proceso_propiedades SET etapa = :etapa WHERE id = :id AND equipo = :equipo' . sql_iid());
        $stmt->execute([
            'etapa' => $etapa,
            'id'    => $id_mover,
            'equipo' => 'comprador',
        ] + sql_iid_params());

        echo json_encode(['ok' => true]);
        exit;
    }

    /* ── CREAR NUEVO PROCESO PARA PROPIEDAD DEL COMPRADOR ────────────── */
    if (isset($_POST['crear_proceso'])) {
        $errores = [];
        $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
        $etapa = $_POST['etapa'] ?? 'captacion';
        $notas = trim($_POST['notas'] ?? '');

        if ($propiedad_id <= 0) {
            $errores[] = 'Debes seleccionar una propiedad.';
        }
        if (!validar_enum($etapa, array_keys($columnas_proceso))) {
            $errores[] = 'La etapa seleccionada no es valida.';
        }

        /* Verificar que la propiedad existe y pertenece al equipo comprador. */
        if ($propiedad_id > 0) {
            $check = $pdo->prepare('SELECT id FROM propiedades WHERE id = :id AND equipo = :equipo' . sql_iid());
            $check->execute(['id' => $propiedad_id, 'equipo' => 'comprador'] + sql_iid_params());
            if (!$check->fetch()) {
                $errores[] = 'La propiedad seleccionada no existe o no pertenece al equipo comprador.';
            }
        }

        /* Verificar que no haya duplicados (una propiedad = un proceso). */
        if ($propiedad_id > 0 && empty($errores)) {
            $check = $pdo->prepare('SELECT id FROM proceso_propiedades WHERE propiedad_id = :pid AND equipo = :equipo' . sql_iid());
            $check->execute(['pid' => $propiedad_id, 'equipo' => 'comprador'] + sql_iid_params());
            if ($check->fetch()) {
                $errores[] = 'Ya existe un proceso para esta propiedad.';
            }
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-proceso');
            exit;
        }

        /* Insertamos el nuevo proceso en la BD para el equipo comprador. */
        $stmt = $pdo->prepare(
            'INSERT INTO proceso_propiedades (propiedad_id, equipo, etapa, notas, inmobiliaria_id)
             VALUES (:propiedad_id, :equipo, :etapa, :notas, :iid)'
        );
        $stmt->execute([
            'propiedad_id' => $propiedad_id,
            'equipo'       => 'comprador',
            'etapa'        => $etapa,
            'notas'        => $notas,
            'iid'          => usuario_inmobiliaria_id(),
        ]);
        flash_set('success', 'Proceso creado correctamente.');
    }

    /* ── EDITAR PROCESO (cambiar etapa y notas) ──────────────────────── */
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

            /* Actualizamos solo la etapa y las notas del proceso. */
            $stmt = $pdo->prepare(
                'UPDATE proceso_propiedades SET etapa = :etapa, notas = :notas WHERE id = :id AND equipo = :equipo' . sql_iid()
            );
            $stmt->execute([
                'etapa'  => $etapa,
                'notas'  => $notas,
                'id'     => $id_editar,
                'equipo' => 'comprador',
            ] + sql_iid_params());
            flash_set('success', 'Proceso actualizado correctamente.');
        }
    }

    /* ── ELIMINAR PROCESO (solo el seguimiento, no la propiedad) ───── */
    if (isset($_POST['eliminar_proceso'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare('DELETE FROM proceso_propiedades WHERE id = :id AND equipo = :equipo' . sql_iid());
            $stmt->execute(['id' => $id_eliminar, 'equipo' => 'comprador'] + sql_iid_params());
        }
        flash_set('success', 'Proceso eliminado correctamente.');
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ══════ CARGA DE DATOS PARA EL TABLERO KANBAN ══════════════════════════════════ */

/* Obtenemos todos los procesos del equipo comprador con datos de su propiedad.
   INNER JOIN: solo trae procesos cuya propiedad aún existe. */
$stmt = $pdo->prepare(
    'SELECT pp.id, pp.propiedad_id, pp.etapa, pp.notas,
            p.titulo, p.tipo, p.ubicacion, p.precio, p.moneda, p.estado AS estado_propiedad, p.referencia
     FROM proceso_propiedades pp
     INNER JOIN propiedades p ON pp.propiedad_id = p.id
     WHERE pp.equipo = :equipo' . sql_iid('pp') . '
     ORDER BY pp.id DESC'
);
$stmt->execute(['equipo' => 'comprador'] + sql_iid_params());
$procesos_db = $stmt->fetchAll();

/* Propiedades del equipo comprador que aún NO tienen proceso asignado.
   Se excluyen las que ya están en la tabla proceso_propiedades. */
$stmt = $pdo->prepare(
    'SELECT p.id, p.titulo, p.referencia, p.operacion
     FROM propiedades p
     WHERE p.equipo = :equipo' . sql_iid('p') . '
       AND p.id NOT IN (SELECT propiedad_id FROM proceso_propiedades WHERE equipo = :equipo2)
     ORDER BY p.id DESC'
);
$stmt->execute(['equipo' => 'comprador', 'equipo2' => 'comprador'] + sql_iid_params());
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
                            <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $proceso['propiedad_id']; ?>&origen=proceso-comprador" title="Ver propiedad">👁️</a>
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
