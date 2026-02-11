<?php
/* Sección: Kanban de Prospectos
   Descripción: Tablero visual para gestionar el estado de los leads
*/

// 1. Definimos las columnas del Kanban
$columnas_kanban = [
    "nuevo" => "Nuevo",
    "contactado" => "Contactado",
    "no_contesta" => "No Contesta",
    "realizado" => "Realizado",
    "descartado" => "Descartado"
];

// 2. Simulamos datos de la BD
$prospectos_db = [
    ["id" => 1, "nombre" => "Laura Gómez", "interes" => "Busca ático centro", "estado" => "nuevo", "tel" => "600-111-222"],
    ["id" => 2, "nombre" => "Pedro Ruiz", "interes" => "Vende piso playa", "estado" => "contactado", "tel" => "611-222-333"],
    ["id" => 3, "nombre" => "Marta Díaz", "interes" => "Inversión local", "estado" => "no_contesta", "tel" => "622-333-444"],
    ["id" => 4, "nombre" => "Javier S.", "interes" => "Quiere vender ya", "estado" => "vender", "tel" => "633-444-555"],
    ["id" => 5, "nombre" => "Sofía L.", "interes" => "Compra primera vivienda", "estado" => "comprar", "tel" => "644-555-666"],
    ["id" => 6, "nombre" => "Carlos M.", "interes" => "Alquiler vacacional", "estado" => "nuevo", "tel" => "655-666-777"],
    ["id" => 7, "nombre" => "Luis T.", "interes" => "Venta heredada", "estado" => "descartado", "tel" => "666-777-888"],
    ["id" => 8, "nombre" => "Ana B.", "interes" => "Ya compró", "estado" => "realizado", "tel" => "677-888-999"]
];
?>

<div class="encabezado_seccion">
    <h2>Tablero de Prospectos</h2>
    <button class="btn_nuevo_cliente">+ Nuevo Prospecto</button>
</div>

<div class="kanban_contenedor">
    
    <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
        
        <div class="kanban_columna">
            
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

            <div class="kanban_body">
                <?php 
                // Filtramos y mostramos solo los de esta columna
                foreach ($prospectos_db as $prospecto): 
                    if($prospecto['estado'] == $clave_estado):
                ?>
                    <div class="tarjeta_prospecto" 
                         draggable="true" 
                         id="prospecto_<?php echo $prospecto['id']; ?>">
                        
                        <h4><?php echo $prospecto['nombre']; ?></h4>
                        <p class="interes">"<?php echo $prospecto['interes']; ?>"</p>
                        <div class="datos_contacto">
                            <span>📞 <?php echo $prospecto['tel']; ?></span>
                        </div>
                        <div class="acciones_tarjeta">
                            <button title="Mover">➜</button>
                            <button title="Editar">✎</button>
                        </div>
                    </div>
                <?php 
                    endif; 
                endforeach; 
                ?>
            </div>
            
        </div>

    <?php endforeach; ?>

</div>
