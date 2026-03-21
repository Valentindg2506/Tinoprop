<?php
/**
 * ============================================================================
 * ARCHIVO: prospectos-vendedor.php
 * SECCIÓN: Tablero Kanban de Prospectos del equipo Vendedor
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Gestiona los prospectos (posibles futuros clientes) del equipo vendedor
 * mediante un tablero visual tipo Kanban (como Trello). Cada prospecto
 * es una tarjeta que se puede mover entre columnas según su estado:
 *   Nuevo → Contactado → No Contesta → Realizado → Descartado
 *
 * FUNCIONALIDADES:
 *   - CREAR prospectos vendedores con nombre, interés y teléfono
 *   - EDITAR prospectos existentes (formulario inline en cada tarjeta)
 *   - ELIMINAR prospectos con confirmación
 *   - MOVER tarjetas entre columnas (drag & drop vía AJAX)
 *   - CONVERTIR un prospecto "realizado" en cliente vendedor oficial
 *
 * ¿QUÉ ES UN KANBAN?
 * Es un sistema visual de gestión de tareas con columnas que representan
 * estados. Las tarjetas se mueven de izquierda a derecha conforme avanzan
 * en el proceso. Es muy usado en ventas para seguir el "pipeline".
 *
 * SEGURIDAD:
 * - csrf_verify() en cada acción POST
 * - puede_ver_vendedor() verifica que el usuario tiene permiso
 * - sql_iid() asegura aislamiento entre inmobiliarias (multi-tenant)
 * - Sentencias preparadas PDO contra inyección SQL
 */
/* Seccion: Kanban de Prospectos (Vendedor)
    Descripcion: Tablero visual conectado a MySQL
*/

/* Se carga el sistema base: sesión, base de datos, funciones auxiliares */
require_once __DIR__ . '/../inc/bootstrap.php';

/* CONTROL DE ACCESO: si el usuario no tiene permiso para ver la sección
   de vendedor, se devuelve un error 403 (Prohibido) y se detiene.
   Esto protege también contra peticiones AJAX directas. */
if (!puede_ver_vendedor()) { http_response_code(403); exit; }

/* COLUMNAS DEL KANBAN: definen los estados posibles de un prospecto.
   La clave (ej: 'nuevo') se guarda en la BD, el valor (ej: 'Nuevo')
   es lo que se muestra al usuario en la interfaz. */
$columnas_kanban = [
    "nuevo" => "Nuevo",
    "contactado" => "Contactado",
    "no_contesta" => "No Contesta",
    "realizado" => "Realizado",
    "descartado" => "Descartado"
];

/* Conexión a la base de datos y variables de sección */
$pdo = db();
$origen = 'prospectos-vendedor';
/* Mensajes flash: se muestran una única vez tras cada operación */
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ========================================================================
   CONTROLADOR POST — Procesar acciones del usuario
   ========================================================================
   Este bloque gestiona 5 acciones posibles:
   1. mover_prospecto_drag — Arrastrar tarjeta entre columnas (AJAX)
   2. crear_prospecto — Crear nuevo prospecto
   3. editar_prospecto — Modificar datos de un prospecto existente
   4. eliminar_prospecto — Borrar un prospecto
   5. convertir_a_cliente — Promover prospecto a cliente oficial */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    /* ----------------------------------------------------------------
       ACCIÓN 1: MOVER TARJETA (Drag & Drop)
       ----------------------------------------------------------------
       Cuando el usuario arrastra una tarjeta de una columna a otra,
       JavaScript envía una petición AJAX (sin recargar la página).
       Se responde con JSON en vez de HTML. */
    if (isset($_POST['mover_prospecto_drag'])) {
        /* Se indica al navegador que la respuesta es JSON */
        header('Content-Type: application/json; charset=utf-8');

        /* Se obtiene el ID del prospecto y el nuevo estado (columna destino) */
        $id_mover = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';

        /* Validación: el ID debe ser positivo y el estado debe ser
           una de las columnas válidas del kanban */
        if ($id_mover <= 0 || !validar_enum($estado, array_keys($columnas_kanban))) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de movimiento no validos.']);
            exit;
        }

        /* Actualiza el estado del prospecto en la BD.
           sql_iid() asegura que solo se modifiquen datos de la misma inmobiliaria. */
        $stmt = $pdo->prepare('UPDATE prospectos SET estado = :estado WHERE id = :id AND tipo = :tipo' . sql_iid());
        $stmt->execute([
            'estado' => $estado,
            'id' => $id_mover,
            'tipo' => 'vendedor',
        ] + sql_iid_params());

        echo json_encode(['ok' => true]);
        exit;
    }

    /* ----------------------------------------------------------------
       ACCIÓN 2: CREAR NUEVO PROSPECTO VENDEDOR
       ----------------------------------------------------------------
       Se recogen datos básicos: nombre, interés (qué busca) y teléfono. */
    if (isset($_POST['crear_prospecto'])) {
        /* Recogida de datos del formulario con validación */
        $errores = [];
        $nombre = trim($_POST['nombre'] ?? '');
        $interes = trim($_POST['interes'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado = $_POST['estado'] ?? 'nuevo';

        if (!validar_requerido($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (!validar_requerido($interes)) {
            $errores[] = 'El interes es obligatorio.';
        }
        if (!validar_telefono($telefono)) {
            $errores[] = 'El telefono no es valido.';
        }
        if (!validar_enum($estado, array_keys($columnas_kanban))) {
            $errores[] = 'El estado seleccionado no es valido.';
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-prospecto');
            exit;
        }

        /* INSERCIÓN EN BD: se guarda el nuevo prospecto con tipo='vendedor',
           vinculado a la inmobiliaria y al usuario que lo creó. */
        $stmt = $pdo->prepare(
            'INSERT INTO prospectos (tipo, nombre, interes, estado, telefono, inmobiliaria_id, usuario_id)
             VALUES (:tipo, :nombre, :interes, :estado, :telefono, :iid, :uid)'
        );
        $stmt->execute([
            'tipo' => 'vendedor',
            'nombre' => $nombre,
            'interes' => $interes,
            'estado' => $estado,
            'telefono' => $telefono,
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        flash_set('success', 'Prospecto creado correctamente.');
    }

    /* ----------------------------------------------------------------
       ACCIÓN 3: EDITAR PROSPECTO EXISTENTE
       ----------------------------------------------------------------
       Permite modificar nombre, interés, teléfono y estado de un
       prospecto desde el formulario inline de cada tarjeta. */
    if (isset($_POST['editar_prospecto'])) {
        /* Se obtiene el ID del prospecto a editar */
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            $errores = [];
            $nombre = trim($_POST['nombre'] ?? '');
            $interes = trim($_POST['interes'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $estado = $_POST['estado'] ?? 'nuevo';

            if (!validar_requerido($nombre)) {
                $errores[] = 'El nombre es obligatorio.';
            }
            if (!validar_requerido($interes)) {
                $errores[] = 'El interes es obligatorio.';
            }
            if (!validar_telefono($telefono)) {
                $errores[] = 'El telefono no es valido.';
            }
            if (!validar_enum($estado, array_keys($columnas_kanban))) {
                $errores[] = 'El estado seleccionado no es valido.';
            }

            if (!empty($errores)) {
                flash_set('error', implode(' ', $errores));
                header('Location: index.php?seccion=' . $origen);
                exit;
            }

            /* UPDATE actualiza solo los campos modificados del prospecto.
               sql_iid() asegura que no se modifiquen datos de otra inmobiliaria. */
            $stmt = $pdo->prepare(
                'UPDATE prospectos SET nombre = :nombre, interes = :interes, estado = :estado, telefono = :telefono WHERE id = :id' . sql_iid()
            );
            $stmt->execute([
                'nombre' => $nombre,
                'interes' => $interes,
                'estado' => $estado,
                'telefono' => $telefono,
                'id' => $id_editar,
            ] + sql_iid_params());
            flash_set('success', 'Prospecto actualizado correctamente.');
        }
    }

    /* ----------------------------------------------------------------
       ACCIÓN 4: ELIMINAR PROSPECTO
       ----------------------------------------------------------------
       Borra definitivamente un prospecto del tipo vendedor. */
    if (isset($_POST['eliminar_prospecto'])) {
        /* Se convierte a entero por seguridad y se borra solo si es válido */
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare('DELETE FROM prospectos WHERE id = :id AND tipo = :tipo' . sql_iid());
            $stmt->execute(['id' => $id_eliminar, 'tipo' => 'vendedor'] + sql_iid_params());
        }
        flash_set('success', 'Prospecto eliminado correctamente.');
    }

    /* ----------------------------------------------------------------
       ACCIÓN 5: CONVERTIR PROSPECTO A CLIENTE
       ----------------------------------------------------------------
       Cuando un prospecto llega al estado "realizado", se puede
       promover a cliente oficial. Se buscan sus datos en la tabla
       prospectos y se insertan en la tabla clientes. */
    if (isset($_POST['convertir_a_cliente'])) {
        /* Se busca el prospecto a convertir */
        $id_conv = (int) ($_POST['id'] ?? 0);
        if ($id_conv > 0) {
            $stmt = $pdo->prepare('SELECT nombre, telefono, interes FROM prospectos WHERE id = :id AND tipo = :tipo' . sql_iid());
            $stmt->execute(['id' => $id_conv, 'tipo' => 'vendedor'] + sql_iid_params());
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                /* Se intenta separar el nombre completo en nombre y apellido.
                   explode(' ', texto, 2) divide en máximo 2 partes por el primer espacio. */
                $partes = explode(' ', $p['nombre'], 2);
                $nombre_c = $partes[0];
                $apellido_c = $partes[1] ?? '';
                /* Se inserta como nuevo cliente de tipo vendedor en la tabla clientes */
                $stmt2 = $pdo->prepare(
                    'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, inmobiliaria_id, usuario_id)
                     VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :iid, :uid)'
                );
                $stmt2->execute([
                    'tipo' => 'vendedor',
                    'nombre' => $nombre_c,
                    'apellido' => $apellido_c,
                    'telefono' => $p['telefono'],
                    'email' => '',
                    'operacion' => 'Venta',
                    'iid' => usuario_inmobiliaria_id(),
                    'uid' => $_SESSION['usuario']['id'] ?? null,
                ]);
                flash_set('success', 'Prospecto convertido a cliente correctamente. Puedes completar sus datos en la sección Clientes.');
            } else {
                flash_set('error', 'No se encontró el prospecto.');
            }
        }
    }

    /* Patrón PRG: redirigir tras POST para evitar reenvíos al recargar. */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ========================================================================
   CONSULTA DE DATOS PARA EL KANBAN
   ========================================================================
   Se obtienen TODOS los prospectos vendedores de la BD. No se usa
   paginación porque el kanban necesita mostrar todas las tarjetas
   distribuidas en sus respectivas columnas. */
$stmt = $pdo->prepare('SELECT id, nombre, interes, estado, telefono, created_at FROM prospectos WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
$stmt->execute(['tipo' => 'vendedor'] + sql_iid_params() + sql_uid_params());
$prospectos_db = $stmt->fetchAll();
?>

<!-- =====================================================================
     PARTE VISUAL (HTML) — Tablero Kanban de Prospectos Vendedor
     =====================================================================
     El tablero muestra columnas (estados) con tarjetas (prospectos).
     Cada tarjeta tiene: nombre, interés, teléfono, acciones y un
     formulario de edición inline desplegable. -->

<!-- Botón para activar modo edición (drag & drop) y crear prospecto -->
<div class="encabezado_seccion">
    <div class="acciones_dashboard">
        <button type="button" class="btn_guardar btn-editar-kanban">Editar orden</button>
        <a href="#nuevo-prospecto" class="btn_nuevo_cliente">+ Nuevo Prospecto</a>
    </div>
</div>

<!-- =====================================================================
     FORMULARIO DE ALTA — Crear un nuevo prospecto vendedor
     ===================================================================== -->
<div id="nuevo-prospecto" class="form_panel">
    <h3>Crear prospecto vendedor</h3>
    <!-- Mensajes flash de feedback al usuario -->
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid">
        <?php echo csrf_field(); ?>
        <div class="campo_input">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required>
        </div>
        <div class="campo_input">
            <label for="interes">Interes</label>
            <input id="interes" name="interes" type="text" required>
        </div>
        <div class="campo_input">
            <label for="telefono">Telefono</label>
            <input id="telefono" name="telefono" type="text" required>
        </div>
        <div class="campo_input">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
                    <option value="<?php echo e($clave_estado); ?>"><?php echo e($titulo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_prospecto" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>

<!-- =====================================================================
     TABLERO KANBAN — Columnas con tarjetas de prospectos
     =====================================================================
     Se genera una columna por cada estado definido en $columnas_kanban.
     Dentro de cada columna se colocan las tarjetas de los prospectos
     que tienen ese estado. -->
<div class="kanban_contenedor">
    
    <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
        <!-- COLUMNA DEL KANBAN: una por cada estado (nuevo, contactado, etc.) -->
        <div class="kanban_columna" data-estado="<?php echo e($clave_estado); ?>">
            
            <!-- Cabecera de columna: nombre del estado y contador de tarjetas -->
            <div class="kanban_header <?php echo $clave_estado; ?>">
                <h3><?php echo $titulo; ?></h3>
                <span class="contador">
                    <?php 
                    /* Contamos cuántos prospectos hay en esta columna
                       usando array_filter para filtrar por estado */
                    $total = count(array_filter($prospectos_db, function($p) use ($clave_estado) {
                        return $p['estado'] == $clave_estado;
                    }));
                    echo $total;
                    ?>
                </span>
            </div>

            <!-- Cuerpo de la columna: contiene las tarjetas de prospectos -->
            <div class="kanban_body" data-estado="<?php echo e($clave_estado); ?>">
                <?php 
                /* Se recorren TODOS los prospectos pero solo se muestran
                   los que pertenecen a esta columna (mismo estado) */
                foreach ($prospectos_db as $prospecto): 
                    if($prospecto['estado'] == $clave_estado):
                ?>
                    <!-- TARJETA DE PROSPECTO: muestra datos del contacto -->
                    <div class="tarjeta_prospecto tarjeta_prospecto--enriquecida" 
                        draggable="false" 
                        data-id="<?php echo $prospecto['id']; ?>"
                         id="prospecto_<?php echo $prospecto['id']; ?>">
                        
                        <h4><?php echo e($prospecto['nombre']); ?></h4>
                        <p class="interes">"<?php echo e($prospecto['interes']); ?>"</p>
                        <div class="datos_contacto">
                            <span>📞 <?php echo e($prospecto['telefono']); ?></span>
                            <?php if (!empty($prospecto['telefono'])): $tel_p = preg_replace('/[^0-9+]/', '', $prospecto['telefono']); ?>
                            <a href="https://wa.me/<?php echo ltrim($tel_p, '+'); ?>" target="_blank" class="btn_wa_mini" title="WhatsApp">💬</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($prospecto['created_at'])): ?>
                        <span class="tarjeta_fecha_mini">📅 <?php echo date('d/m', strtotime($prospecto['created_at'])); ?></span>
                        <?php endif; ?>
                        <div class="acciones_tarjeta">
                            <?php if ($prospecto['estado'] === 'realizado'): ?>
                            <form method="POST" data-confirm="¿Convertir este prospecto en cliente vendedor?">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $prospecto['id']; ?>">
                                <button type="submit" name="convertir_a_cliente" class="btn_guardar btn_chico">👤 A cliente</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" data-confirm="¿Eliminar este prospecto? Esta accion no se puede deshacer.">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $prospecto['id']; ?>">
                                <button type="submit" name="eliminar_prospecto" class="btn_peligro">Eliminar</button>
                            </form>
                        </div>

                        <details class="detalle_inline">
                            <summary>Editar</summary>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $prospecto['id']; ?>">
                                <div class="campo_input">
                                    <label>Nombre</label>
                                    <input name="nombre" type="text" value="<?php echo e($prospecto['nombre']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Interes</label>
                                    <input name="interes" type="text" value="<?php echo e($prospecto['interes']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Telefono</label>
                                    <input name="telefono" type="text" value="<?php echo e($prospecto['telefono']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Estado</label>
                                    <select name="estado">
                                        <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
                                            <option value="<?php echo e($clave_estado); ?>" <?php echo $prospecto['estado'] === $clave_estado ? 'selected' : ''; ?>>
                                                <?php echo e($titulo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="editar_prospecto" class="btn_guardar">Guardar cambios</button>
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
