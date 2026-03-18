<?php
require_once __DIR__ . '/../inc/bootstrap.php';
$pdo = db();
$origen = 'clientes-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    $stmt = $pdo->prepare('SELECT nombre, apellido, telefono, email, operacion, created_at FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
    $stmt->execute(['tipo' => 'vendedor'] + sql_iid_params() + sql_uid_params());
    exportar_csv($stmt->fetchAll(PDO::FETCH_ASSOC), 'clientes_vendedor.csv');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['crear_cliente'])) {
        $errores = [];
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $operacion = trim($_POST['operacion'] ?? '');
        $operaciones_validas = ['Venta', 'Compra', 'Alquiler'];
        if (!validar_requerido($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (!validar_requerido($apellido)) {
            $errores[] = 'Los apellidos son obligatorios.';
        }
        if (!validar_telefono($telefono)) {
            $errores[] = 'El telefono no es valido.';
        }
        if (!validar_email($email)) {
            $errores[] = 'El email no es valido.';
        }
        if (!validar_enum($operacion, $operaciones_validas)) {
            $errores[] = 'La operacion seleccionada no es valida.';
        }
        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-cliente');
            exit;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, inmobiliaria_id, usuario_id) VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :iid, :uid)'
        );
        $stmt->execute([
            'tipo' => 'vendedor', 'nombre' => $nombre, 'apellido' => $apellido,
            'telefono' => $telefono, 'email' => $email, 'operacion' => $operacion,
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        actividad_registrar($pdo, 'crear', 'cliente', (int)$pdo->lastInsertId(), "Nuevo cliente vendedor: $nombre $apellido");
        flash_set('success', 'Cliente creado correctamente.');
    }
    if (isset($_POST['eliminar_cliente'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id" . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id' . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                $pdo->commit();
                actividad_registrar($pdo, 'eliminar', 'cliente', $id_eliminar, 'Cliente vendedor eliminado');
                flash_set('success', 'Cliente eliminado correctamente.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Error al eliminar el cliente. Inténtelo de nuevo.');
            }
        }
    }
    header('Location: index.php?seccion=' . $origen);
    exit;
}
$stmt_total = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid());
$stmt_total->execute(['tipo' => 'vendedor'] + sql_iid_params() + sql_uid_params());
$total_clientes = (int)$stmt_total->fetchColumn();
$pagina_actual = max(1, (int)($_GET['pagina'] ?? 1));
$paginacion = paginar($total_clientes, 15, $pagina_actual);
$stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email, operacion FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC LIMIT :lim OFFSET :off');
$stmt->bindValue('tipo', 'vendedor');
foreach (sql_iid_params() as $k => $v) {
    $stmt->bindValue($k, $v);
}
foreach (sql_uid_params() as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('lim', $paginacion['por_pagina'], PDO::PARAM_INT);
$stmt->bindValue('off', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
$clientes_db = $stmt->fetchAll();
?>
<div class="encabezado_seccion">
    <a href="#nuevo-cliente" class="btn_nuevo_cliente">+ Nuevo Cliente</a>
</div>
<div id="nuevo-cliente" class="form_panel">
    <h3>Crear cliente vendedor</h3>
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid" data-validar>
        <?php echo csrf_field(); ?>
        <div class="campo_input">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required>
        </div>
        <div class="campo_input">
            <label for="apellido">Apellidos</label>
            <input id="apellido" name="apellido" type="text" required>
        </div>
        <div class="campo_input">
            <label for="telefono">Telefono</label>
            <input id="telefono" name="telefono" type="tel" required>
        </div>
        <div class="campo_input">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required>
        </div>
        <div class="campo_input">
            <label for="operacion">Operacion</label>
            <select id="operacion" name="operacion">
                <option value="Venta">Venta</option>
                <option value="Compra">Compra</option>
                <option value="Alquiler">Alquiler</option>
            </select>
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_cliente" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>
<div class="barra_acciones_tabla">
    <span><?php echo $total_clientes; ?> clientes</span>
    <a href="index.php?seccion=<?php echo $origen; ?>&exportar=csv" class="btn_exportar">📥 Exportar CSV</a>
</div>
<div class="vista_toggle">
    <button type="button" class="btn_toggle_vista active" data-vista="tabla" onclick="cambiarVista('tabla')">📋 Tabla</button>
    <button type="button" class="btn_toggle_vista" data-vista="tarjetas" onclick="cambiarVista('tarjetas')">🃏 Tarjetas</button>
</div>
<div class="vista_contenido vista_tabla">
<div class="contenedor_tabla">
    <table class="tabla_datos">
        <thead>
            <tr>
                <th class="th_ordenable">Nombre</th>
                <th class="th_ordenable">Apellidos</th>
                <th class="th_ordenable">Teléfono</th>
                <th class="th_ordenable">Email</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes_db as $cliente): ?>
            <tr>
                <td><strong><?php echo e($cliente['nombre']); ?></strong></td>
                <td><?php echo e($cliente['apellido']); ?></td>
                <td><?php echo e($cliente['telefono']); ?></td>
                <td><?php echo e($cliente['email']); ?></td>
                <td>
                    <div class="acciones_inline">
                        <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-vendedor" class="btn_ver_mas">Ver más ➜</a>
                        <form method="POST" data-confirm="¿Eliminar este cliente? Esta accion no se puede deshacer.">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                            <button type="submit" name="eliminar_cliente" class="btn_peligro">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<div class="vista_contenido vista_tarjetas" style="display:none">
    <div class="tarjetas_grid">
        <?php foreach ($clientes_db as $cliente): ?>
        <div class="tarjeta_cliente_card">
            <div class="tarjeta_cliente_header">
                <div class="tarjeta_avatar"><?php echo mb_strtoupper(mb_substr($cliente['nombre'], 0, 1) . mb_substr($cliente['apellido'] ?? '', 0, 1)); ?></div>
                <div>
                    <strong><?php echo e($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? '')); ?></strong>
                    <span class="badge_estado"><?php echo e($cliente['operacion'] ?? ''); ?></span>
                </div>
            </div>
            <div class="tarjeta_cliente_body">
                <?php if (!empty($cliente['telefono'])): ?><p>📞 <?php echo e($cliente['telefono']); ?></p><?php endif; ?>
                <?php if (!empty($cliente['email'])): ?><p>📧 <?php echo e($cliente['email']); ?></p><?php endif; ?>
            </div>
            <div class="tarjeta_cliente_footer">
                <a href="?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-vendedor" class="btn_secundario btn_chico">Ver más</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php echo renderizar_paginacion($paginacion, 'index.php?seccion=' . $origen); ?>
<script>
function cambiarVista(vista) {
    document.querySelectorAll('.vista_contenido').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.btn_toggle_vista').forEach(el => el.classList.remove('active'));
    if (vista === 'tabla') {
        document.querySelector('.vista_tabla').style.display = '';
        document.querySelector('[data-vista="tabla"]').classList.add('active');
    } else {
        document.querySelector('.vista_tarjetas').style.display = '';
        document.querySelector('[data-vista="tarjetas"]').classList.add('active');
    }
    localStorage.setItem('tinoprop_vista_clientes', vista);
}
document.addEventListener('DOMContentLoaded', () => {
    const pref = localStorage.getItem('tinoprop_vista_clientes');
    if (pref === 'tarjetas') cambiarVista('tarjetas');
});
</script>
