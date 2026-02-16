<?php
/* Seccion: Kanban de Prospectos (Vendedor)
    Descripcion: Tablero visual conectado a MySQL
*/
require_once __DIR__ . '/../inc/bootstrap.php';

// 1. Definimos las columnas del Kanban
$columnas_kanban = [
    "nuevo" => "Nuevo",
    "contactado" => "Contactado",
    "no_contesta" => "No Contesta",
    "vender" => "Vender",
    "comprar" => "Comprar",
    "realizado" => "Realizado",
    "descartado" => "Descartado"
];

$pdo = db();
$stmt = $pdo->prepare('SELECT id, nombre, interes, estado, telefono FROM prospectos WHERE tipo = :tipo ORDER BY id DESC');
$stmt->execute(['tipo' => 'vendedor']);
$prospectos_db = $stmt->fetchAll();
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
                        
                        <h4><?php echo e($prospecto['nombre']); ?></h4>
                        <p class="interes">"<?php echo e($prospecto['interes']); ?>"</p>
                        <div class="datos_contacto">
                            <span>📞 <?php echo e($prospecto['telefono']); ?></span>
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
