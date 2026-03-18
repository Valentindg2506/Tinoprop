<?php
require_once __DIR__ . '/../inc/bootstrap.php';
if (!puede_ver_comprador()) {
    http_response_code(403);
    exit;
}
$columnas_kanban = [
    "nuevo" => "Nuevo",
    "contactado" => "Contactado",
    "no_contesta" => "No Contesta",
    "realizado" => "Realizado",
    "descartado" => "Descartado"
];
$pdo = db();
$origen = 'prospectos-comprador';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
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
    if (isset($_POST['crear_prospecto'])) {
        $errores = [];
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $interes = trim($_POST['interes'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
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
            'INSERT INTO prospectos (tipo, nombre, apellido, interes, estado, telefono, email, inmobiliaria_id, usuario_id)
             VALUES (:tipo, :nombre, :apellido, :interes, :estado, :telefono, :email, :iid, :uid)'
        );
        $stmt->execute([
            'tipo' => 'comprador',
            'nombre' => $nombre,
            'apellido' => $apellido,
            'interes' => $interes,
            'estado' => $estado,
            'telefono' => $telefono,
            'email' => $email,
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        flash_set('success', 'Prospecto creado correctamente.');
    }
    if (isset($_POST['editar_prospecto'])) {
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            $errores = [];
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $interes = trim($_POST['interes'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
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
                'UPDATE prospectos SET nombre = :nombre, apellido = :apellido, interes = :interes, estado = :estado, telefono = :telefono, email = :email WHERE id = :id' . sql_iid()
            );
            $stmt->execute([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'interes' => $interes,
                'estado' => $estado,
                'telefono' => $telefono,
                'email' => $email,
                'id' => $id_editar,
            ] + sql_iid_params());
            flash_set('success', 'Prospecto actualizado correctamente.');
        }
    }
    if (isset($_POST['eliminar_prospecto'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare('DELETE FROM prospectos WHERE id = :id AND tipo = :tipo' . sql_iid());
            $stmt->execute(['id' => $id_eliminar, 'tipo' => 'comprador'] + sql_iid_params());
        }
        flash_set('success', 'Prospecto eliminado correctamente.');
    }
    if (isset($_POST['convertir_a_cliente'])) {
        $id_conv = (int) ($_POST['id'] ?? 0);
        if ($id_conv > 0) {
            $stmt = $pdo->prepare('SELECT nombre, apellido, email, telefono, interes FROM prospectos WHERE id = :id AND tipo = :tipo' . sql_iid());
            $stmt->execute(['id' => $id_conv, 'tipo' => 'comprador'] + sql_iid_params());
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $stmt2 = $pdo->prepare(
                    'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, inmobiliaria_id, usuario_id)
                     VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :iid, :uid)'
                );
                $stmt2->execute([
                    'tipo' => 'comprador',
                    'nombre' => $p['nombre'],
                    'apellido' => $p['apellido'],
                    'telefono' => $p['telefono'],
                    'email' => $p['email'],
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
    header('Location: index.php?seccion=' . $origen);
    exit;
}
$stmt = $pdo->prepare('SELECT id, nombre, apellido, email, interes, estado, telefono, created_at FROM prospectos WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
$stmt->execute(['tipo' => 'comprador'] + sql_iid_params() + sql_uid_params());
$prospectos_db = $stmt->fetchAll();
?>
<div class="encabezado_seccion">
    <div class="acciones_dashboard">
        <button type="button" class="btn_guardar btn-editar-kanban">Editar orden</button>
        <a href="#nuevo-prospecto" class="btn_nuevo_cliente">+ Nuevo Prospecto</a>
    </div>
</div>
<div id="nuevo-prospecto" class="form_panel">
    <h3>Crear prospecto comprador</h3>
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
            <label for="apellido">Apellidos</label>
            <input id="apellido" name="apellido" type="text">
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
            <label for="email">Email</label>
            <input id="email" name="email" type="email">
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
<div class="kanban_contenedor">
    <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
        <div class="kanban_columna" data-estado="<?php echo e($clave_estado); ?>">
            <div class="kanban_header <?php echo $clave_estado; ?>">
                <h3><?php echo $titulo; ?></h3>
                <span class="contador">
                    <?php
                    $total = count(array_filter($prospectos_db, function ($p) use ($clave_estado) {
                        return $p['estado'] == $clave_estado;
                    }));
        echo $total;
        ?>
                </span>
            </div>
            <div class="kanban_body" data-estado="<?php echo e($clave_estado); ?>">
                <?php
                foreach ($prospectos_db as $prospecto):
                    if ($prospecto['estado'] == $clave_estado):
                        ?>
                    <div class="tarjeta_prospecto tarjeta_prospecto--enriquecida"
                        draggable="true"
                        data-id="<?php echo $prospecto['id']; ?>"
                        id="prospecto_<?php echo $prospecto['id']; ?>">
                        <h4><?php echo e($prospecto['nombre'] . ' ' . ($prospecto['apellido'] ?? '')); ?></h4>
                        <p class="interes">"<?php echo e($prospecto['interes']); ?>"</p>
                        <div class="datos_contacto">
                            <?php if (!empty($prospecto['telefono'])): ?>
                                <span>📞 <?php echo e($prospecto['telefono']); ?></span>
                                <?php $tel_p = preg_replace('/[^0-9+]/', '', $prospecto['telefono']); ?>
                                <a href="https://wa.me/<?php echo ltrim($tel_p, '+'); ?>" target="_blank" class="btn_wa_mini" title="WhatsApp">💬</a>
                            <?php endif; ?>
                            <?php if (!empty($prospecto['email'])): ?>
                                <br><span>📧 <?php echo e($prospecto['email']); ?></span>
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
                            <summary>Más información</summary>
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $prospecto['id']; ?>">
                                <div class="campo_input">
                                    <label>Nombre</label>
                                    <input name="nombre" type="text" value="<?php echo e($prospecto['nombre']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Apellidos</label>
                                    <input name="apellido" type="text" value="<?php echo e($prospecto['apellido'] ?? ''); ?>">
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
                                    <label>Email</label>
                                    <input name="email" type="email" value="<?php echo e($prospecto['email'] ?? ''); ?>">
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
