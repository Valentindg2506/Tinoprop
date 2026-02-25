<?php
/* Sección: Detalle de Cliente
   Descripción: Ficha con campos editables
*/

// 1. Capturamos el ID de la URL
$id_cliente = isset($_GET['id']) ? $_GET['id'] : 0;

// 2. Simulamos la búsqueda en BD (Esto luego será un SELECT * FROM clientes WHERE id = $id)
$datos_cliente = [
    "id" => $id_cliente,
    "nombre" => "Valentin",
    "apellido" => "De Gennaro",
    "telefono" => "600-123-456",
    "email" => "valentin@dam.com",
    "operacion" => "Compra",
    "direccion" => "Av. del Puerto, 12, Valencia",
    "genero" => "Masculino",
    "fecha_de_nacimiento" => "1990-05-10",
    "presupuesto" => "250000",
    "zona_interesada" => "Ruzafa",
    "comentarios" => "Cliente interesado en aticos por la zona de Ruzafa."
];
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=clientes-vendedor" class="btn_volver">⬅ Volver al listado</a>
    <h2>Ficha del Cliente</h2>
</div>

<div class="tarjeta_ficha">
    <form action="" method="POST">
        
        <div class="grid_datos">
            
            <div class="campo_dato">
                <label>Nombre:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="nombre" name="nombre" value="<?php echo $datos_cliente['nombre']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('nombre')">✎</span>
                </div>
            </div>

            <div class="campo_dato">
                <label>Apellidos:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="apellido" name="apellido" value="<?php echo $datos_cliente['apellido']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('apellido')">✎</span>
                </div>
            </div>

            <div class="campo_dato">
                <label>Teléfono:</label>
                <div class="input_con_lapiz">
                    <input type="tel" id="telefono" name="telefono" value="<?php echo $datos_cliente['telefono']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('telefono')">✎</span>
                </div>
            </div>
            
            <div class="campo_dato">
                <label>Tipo de operación:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="operacion" name="operacion" value="<?php echo $datos_cliente['operacion']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('operacion')">✎</span>
                </div>
            </div>

            <div class="campo_dato">
                <label>Email:</label>
                <div class="input_con_lapiz">
                    <input type="email" id="email" name="email" value="<?php echo $datos_cliente['email']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('email')">✎</span>
                </div>
            </div>

            <div class="campo_dato full_width">
                <label>Dirección:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="direccion" name="direccion" value="<?php echo $datos_cliente['direccion']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('direccion')">✎</span>
                </div>
            </div>
            
            <div class="campo_dato">
                <label>Genero:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="genero" name="genero" value="<?php echo $datos_cliente['genero']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('genero')">✎</span>
                </div>
            </div>
            
            <div class="campo_dato">
                <label>Fecha de nacimiento:</label>
                <div class="input_con_lapiz">
                    <input type="date" id="fecha_de_nacimiento" name="fecha_de_nacimiento" value="<?php echo $datos_cliente['fecha_de_nacimiento']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('fecha_de_nacimiento')">✎</span>
                </div>
            </div>
            
            <div class="campo_dato">
                <label>Presupuesto:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="presupuesto" name="presupuesto" value="<?php echo $datos_cliente['presupuesto']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('presupuesto')">✎</span>
                </div>
            </div>
            
            <div class="campo_dato">
                <label>Zona interesada:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="zona_interesada" name="zona_interesada" value="<?php echo $datos_cliente['zona_interesada']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('zona_interesada')">✎</span>
                </div>
            </div>
            
            <div class="campo_dato full_width">
                <label>Comentarios:</label>
                <div class="input_con_lapiz">
                    <input type="text" id="comentarios" name="comentarios" value="<?php echo $datos_cliente['comentarios']; ?>" readonly>
                    <span class="lapiz_editar" onclick="activarEdicion('comentarios')">✎</span>
                </div>
            </div>

        </div>

        <div class="area_botones">
            <button type="submit" class="btn_guardar">Guardar Cambios</button>
        </div>

    </form>
</div>

<script>
function activarEdicion(idCampo) {
    let input = document.getElementById(idCampo);

    // Habilita edicion y enfoca el campo seleccionado.
    input.removeAttribute('readonly');
    input.focus();

    // Refuerzo visual mientras se edita.
    input.classList.add('editando');
}
</script>
