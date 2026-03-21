<?php
/* Seccion: Clientes (Comprador)
   Descripcion: Tabla con datos reales desde MySQL
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email FROM clientes WHERE tipo = :tipo ORDER BY id DESC');
$stmt->execute(['tipo' => 'comprador']);
$clientes_db = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <h2>Gestión de Clientes</h2>
    <a href="#" class="btn_nuevo_cliente">+ Nuevo Cliente</a>
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
                    <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-comprador" class="btn_ver_mas">
                        Ver más ➜
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
