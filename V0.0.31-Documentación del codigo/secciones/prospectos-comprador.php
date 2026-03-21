<?php
/**
 * ============================================================================
 * ARCHIVO: prospectos-comprador.php
 * SECCIÓN: Tablero Kanban de Prospectos del equipo Comprador
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Gestiona los prospectos (posibles futuros clientes) del equipo comprador
 * mediante un tablero visual tipo Kanban. Cada prospecto es una tarjeta
 * que avanza por las columnas según su estado en el proceso de captación:
 *   Nuevo → Contactado → No Contesta → Realizado → Descartado
 *
 * DIFERENCIA CON prospectos-vendedor.php:
 * La lógica es idéntica pero filtra por tipo='comprador'. Los prospectos
 * compradores son personas interesadas en adquirir un inmueble.
 * Al convertirse a cliente, se insertan como tipo='comprador' con
 * operación='Compra'.
 *
 * FUNCIONALIDADES:
 *   - CREAR, EDITAR, ELIMINAR prospectos compradores
 *   - MOVER tarjetas entre columnas (drag & drop vía AJAX)
 *   - CONVERTIR prospectos "realizados" en clientes compradores
 *
 * SEGURIDAD:
 * - csrf_verify(), puede_ver_comprador(), sql_iid(), sentencias preparadas PDO
 */
/* Seccion: Kanban de Prospectos (Comprador)
    Descripcion: Tablero visual conectado a MySQL
*/

/* Se carga el sistema base */
require_once __DIR__ . '/../inc/bootstrap.php';

/* CONTROL DE ACCESO: solo usuarios con rol de comprador pueden acceder.
   Si no tiene permiso, se devuelve error 403 y se corta la ejecución. */
if (!puede_ver_comprador()) { http_response_code(403); exit; }

/* COLUMNAS DEL KANBAN: los 5 estados posibles de un prospecto.
   Representan el flujo del proceso de captación de compradores. */
$columnas_kanban = [
    "nuevo" => "Nuevo",
    "contactado" => "Contactado",
    "no_contesta" => "No Contesta",
    "realizado" => "Realizado",
    "descartado" => "Descartado"
];

/* Conexión a BD y variables de sección */
$pdo = db();
$origen = 'prospectos-comprador';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ========================================================================
   CONTROLADOR POST — Procesar acciones del usuario
   ========================================================================
   Gestiona 5 acciones: mover (AJAX), crear, editar, eliminar y convertir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    /* ----------------------------------------------------------------
       ACCIÓN 1: MOVER TARJETA (Drag & Drop AJAX)
       ----------------------------------------------------------------
       Responde con JSON sin recargar la página. */
    if (isset($_POST['mover_prospecto_drag'])) {
        header('Content-Type: application/json; charset=utf-8');

        $id_mover = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';

        if ($id_mover <= 0 || !validar_enum($estado, array_keys($columnas_kanban))) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de movimiento no validos.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE prospectos SET estado = :estado WHERE id = :id AND tipo = :tipo' . sql_iid());
        $stmt->execute([
            'estado' => $estado,
            'id' => $id_mover,
            'tipo' => 'comprador',
        ] + sql_iid_params());

        echo json_encode(['ok' => true]);
        exit;
    }

    /* ----------------------------------------------------------------
       ACCIÓN 2: CREAR NUEVO PROSPECTO COMPRADOR
       ---------------------------------------------------------------- */
    if (isset($_POST['crear_prospecto'])) {
        /* Recogida de datos y validación */
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

        $stmt = $pdo->prepare(
            'INSERT INTO prospectos (tipo, nombre, interes, estado, telefono, inmobiliaria_id, usuario_id)
             VALUES (:tipo, :nombre, :interes, :estado, :telefono, :iid, :uid)'
        );
        $stmt->execute([
            'tipo' => 'comprador',
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
       ---------------------------------------------------------------- */
    if (isset($_POST['editar_prospecto'])) {
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
       ---------------------------------------------------------------- */
    if (isset($_POST['eliminar_prospecto'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare('DELETE FROM prospectos WHERE id = :id AND tipo = :tipo' . sql_iid());
            $stmt->execute(['id' => $id_eliminar, 'tipo' => 'comprador'] + sql_iid_params());
        }
        flash_set('success', 'Prospecto eliminado correctamente.');
    }

    /* ----------------------------------------------------------------
       ACCIÓN 5: CONVERTIR PROSPECTO A CLIENTE COMPRADOR
       ----------------------------------------------------------------
       Busca los datos del prospecto y crea un nuevo registro en la
       tabla clientes con tipo='comprador' y operación='Compra'. */
    if (isset($_POST['convertir_a_cliente'])) {
        $id_conv = (int) ($_POST['id'] ?? 0);
        if ($id_conv > 0) {
            $stmt = $pdo->prepare('SELECT nombre, telefono, interes FROM prospectos WHERE id = :id AND tipo = :tipo' . sql_iid());
            $stmt->execute(['id' => $id_conv, 'tipo' => 'comprador'] + sql_iid_params());
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $partes = explode(' ', $p['nombre'], 2);
                $nombre_c = $partes[0];
                $apellido_c = $partes[1] ?? '';
                $stmt2 = $pdo->prepare(
                    'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, inmobiliaria_id, usuario_id)
                     VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :iid, :uid)'
                );
                $stmt2->execute([
                    'tipo' => 'comprador',
                    'nombre' => $nombre_c,
                    'apellido' => $apellido_c,
                    'telefono' => $p['telefono'],
                    'email' => '',
                    'operacion' => 'Compra',
                    'iid' => usuario_inmobiliaria_id(),
                    'uid' => $_SESSION['usuario']['id'] ?? null,
                ]);
                flash_set('success', 'Prospecto convertido a cliente correctamente. Puedes completar sus datos en la sección Clientes.');
            } else {
                flash_set('error', 'No se encontró el prospecto.');
            }
        }
    }

    /* Patrón PRG: redirigir tras POST para evitar reenvíos. */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ========================================================================
   CONSULTA DE DATOS PARA EL KANBAN
   ========================================================================
   Se obtienen todos los prospectos compradores para distribuirlos
   en las columnas del tablero. */
$stmt = $pdo->prepare('SELECT id, nombre, interes, estado, telefono, created_at FROM prospectos WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
$stmt->execute(['tipo' => 'comprador'] + sql_iid_params() + sql_uid_params());
$prospectos_db = $stmt->fetchAll();
?>

<!-- =====================================================================
     PARTE VISUAL (HTML) — Tablero Kanban de Prospectos Comprador
     ===================================================================== -->

<div class="encabezado_seccion">
    <div class="acciones_dashboard">
        <button type="button" class="btn_guardar btn-editar-kanban">Editar orden</button>
        <a href="#nuevo-prospecto" class="btn_nuevo_cliente">+ Nuevo Prospecto</a>
    </div>
</div>

<!-- FORMULARIO DE ALTA de prospecto comprador -->
<div id="nuevo-prospecto" class="form_panel">
    <h3>Crear prospecto comprador</h3>
    <!-- Mensajes flash de feedback -->
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

<!-- TABLERO KANBAN: columnas con tarjetas de prospectos compradores -->
<div class="kanban_contenedor">
    
    <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
        <!-- COLUMNA del kanban: un estado (nuevo, contactado, etc.) -->
        <div class="kanban_columna" data-estado="<?php echo e($clave_estado); ?>">
            
            <div class="kanban_header <?php echo $clave_estado; ?>">
                <h3><?php echo $titulo; ?></h3>
                <span class="contador">
                    <?php 
                    $total = count(array_filter($prospectos_db, function($p) use ($clave_estado) {
                        return $p['estado'] == $clave_estado;
                    }));
                    echo $total;
                    ?>
                </span>
            </div>

            <div class="kanban_body" data-estado="<?php echo e($clave_estado); ?>">
                <?php 
                /* Se filtran y muestran solo los prospectos de esta columna */
                foreach ($prospectos_db as $prospecto): 
                    if($prospecto['estado'] == $clave_estado):
                ?>
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
                            <form method="POST" data-confirm="¿Convertir este prospecto en cliente comprador?">
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
