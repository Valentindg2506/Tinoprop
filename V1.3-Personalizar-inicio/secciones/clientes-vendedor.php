<?php
/* Seccion: Clientes (Vendedor)
   Descripcion: Tabla con datos reales desde MySQL
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$origen = 'clientes-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion)
             VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion)'
        );
        $stmt->execute([
            'tipo' => 'vendedor',
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono,
            'email' => $email,
            'operacion' => $operacion,
        ]);
        flash_set('success', 'Cliente creado correctamente.');
    }

    if (isset($_POST['eliminar_cliente'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id");
            $stmt->execute(['id' => $id_eliminar]);

            $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
            $stmt->execute(['id' => $id_eliminar]);
        }
        flash_set('success', 'Cliente eliminado correctamente.');
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

$stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email FROM clientes WHERE tipo = :tipo ORDER BY id DESC');
$stmt->execute(['tipo' => 'vendedor']);
$clientes_db = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <h2>Gestión de Clientes</h2>
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
    <form method="POST" class="form_grid">
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
            <input id="telefono" name="telefono" type="text" required>
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

<div class="contenedor_tabla">
    <table class="tabla_datos">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellidos</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Acciones</th> </tr>
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
                        <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-vendedor" class="btn_ver_mas">
                            Ver más ➜
                        </a>
                        <form method="POST" data-confirm="¿Eliminar este cliente? Esta accion no se puede deshacer.">
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
