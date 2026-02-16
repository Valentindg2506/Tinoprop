<?php
/* Sección: Clientes (Vista Previa)
    Descripción: Tabla resumen con botón para ver detalle completo
*/

// Simulación de Base de Datos (Esto luego vendrá de MySQL)
$clientes_db = [
    ["id" => 1, "nombre" => "Valentín", "apellido" => "De Gennaro", "tel" => "666-111-222", "email" => "valentin@email.com"],
    ["id" => 2, "nombre" => "Ana", "apellido" => "López", "tel" => "666-333-444", "email" => "ana.lopez@email.com"],
    ["id" => 3, "nombre" => "Carlos", "apellido" => "Pérez", "tel" => "666-555-666", "email" => "carlos.p@email.com"],
    ["id" => 4, "nombre" => "María", "apellido" => "García", "tel" => "666-777-888", "email" => "maria.g@email.com"],
];
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
            <?php foreach($clientes_db as $cliente): ?>
            <tr>
                <td><strong><?php echo $cliente['nombre']; ?></strong></td>
                <td><?php echo $cliente['apellido']; ?></td>
                <td><?php echo $cliente['tel']; ?></td>
                <td><?php echo $cliente['email']; ?></td>
                <td>
                    <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>" class="btn_ver_mas">
                        Ver más ➜
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
