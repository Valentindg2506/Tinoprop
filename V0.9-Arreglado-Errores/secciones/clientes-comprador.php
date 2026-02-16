<?php
/* Seccion: Clientes (Comprador)
   Descripcion: Tabla con datos reales desde MySQL
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$origen = 'clientes-comprador';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_cliente'])) {
        $stmt = $pdo->prepare(
            'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion)
             VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion)'
        );
        $stmt->execute([
            'tipo' => 'comprador',
            'nombre' => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'operacion' => trim($_POST['operacion'] ?? ''),
        ]);
    }

    if (isset($_POST['eliminar_cliente'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id");
            $stmt->execute(['id' => $id_eliminar]);

            $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
            $stmt->execute(['id' => $id_eliminar]);
        }
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

$stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email FROM clientes WHERE tipo = :tipo ORDER BY id DESC');
$stmt->execute(['tipo' => 'comprador']);
$clientes_db = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <h2>Gestión de Clientes</h2>
    <a href="#nuevo-cliente" class="btn_nuevo_cliente">+ Nuevo Cliente</a>
</div>

<div id="nuevo-cliente" class="form_panel">
    <h3>Crear cliente comprador</h3>
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
                        <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-comprador" class="btn_ver_mas">
                            Ver más ➜
                        </a>
                        <form method="POST">
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
