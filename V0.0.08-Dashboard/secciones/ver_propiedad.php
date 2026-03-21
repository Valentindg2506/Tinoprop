<?php
/* Seccion: Detalle de Propiedad
   Descripcion: Ficha editable con notas del inmueble
*/

$id_propiedad = isset($_GET['id']) ? $_GET['id'] : 0;
$origen = isset($_GET['origen']) ? $_GET['origen'] : 'propiedades-vendedor';

$datos_propiedad = [
    "id" => $id_propiedad,
    "titulo" => "Apartamento Centrico",
    "tipo" => "Piso",
    "precio" => "185.000 EUR",
    "operacion" => "Venta",
    "estado" => "Disponible",
    "ubicacion" => "Valencia",
    "direccion" => "Calle Colon 25",
    "metros" => "95",
    "habitaciones" => "2",
    "banos" => "1",
    "referencia" => "TP-VAL-102",
    "descripcion" => "Apartamento reformado con balcon y luz natural."
];

$notas_propiedad = [
    ["fecha" => "2026-02-09", "tipo" => "Aviso", "texto" => "Preparar fotos nuevas antes de publicacion."],
    ["fecha" => "2026-02-12", "tipo" => "Nota", "texto" => "Cliente interesado en visita el jueves."],
    ["fecha" => "2026-02-14", "tipo" => "Aviso", "texto" => "Revisar certificado energetico."],
];
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo $origen; ?>" class="btn_volver">⬅ Volver al listado</a>
    <h2>Detalle de Propiedad</h2>
</div>

<form action="" method="POST">
    <div class="detalle_layout">
        <div class="detalle_col detalle_col--info">
            <div class="tarjeta_ficha tarjeta_ficha--compacta">
                <div class="grid_datos">
                    <div class="campo_dato">
                        <label>Titulo:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="titulo" name="titulo" value="<?php echo $datos_propiedad['titulo']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('titulo')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Tipo:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="tipo" name="tipo" value="<?php echo $datos_propiedad['tipo']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('tipo')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Operacion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="operacion" name="operacion" value="<?php echo $datos_propiedad['operacion']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('operacion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Estado:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="estado" name="estado" value="<?php echo $datos_propiedad['estado']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('estado')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Precio:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="precio" name="precio" value="<?php echo $datos_propiedad['precio']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('precio')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Ubicacion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="ubicacion" name="ubicacion" value="<?php echo $datos_propiedad['ubicacion']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('ubicacion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato full_width">
                        <label>Direccion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="direccion" name="direccion" value="<?php echo $datos_propiedad['direccion']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('direccion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Metros:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="metros" name="metros" value="<?php echo $datos_propiedad['metros']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('metros')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Habitaciones:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="habitaciones" name="habitaciones" value="<?php echo $datos_propiedad['habitaciones']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('habitaciones')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Banos:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="banos" name="banos" value="<?php echo $datos_propiedad['banos']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('banos')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Referencia:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="referencia" name="referencia" value="<?php echo $datos_propiedad['referencia']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('referencia')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato full_width">
                        <label>Descripcion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="descripcion" name="descripcion" value="<?php echo $datos_propiedad['descripcion']; ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('descripcion')">✎</span>
                        </div>
                    </div>
                </div>

                <div class="area_botones">
                    <button type="submit" class="btn_guardar">Guardar Cambios</button>
                </div>
            </div>
        </div>

        <aside class="detalle_col detalle_col--notas">
            <div class="panel_notas">
                <div class="panel_header">
                    <h3>Notas y avisos</h3>
                    <span class="panel_hint">Seguimiento del inmueble</span>
                </div>

                <ul class="lista_notas">
                    <?php foreach ($notas_propiedad as $nota): ?>
                        <li class="nota_item">
                            <div class="nota_meta">
                                <span class="nota_tipo"><?php echo $nota['tipo']; ?></span>
                                <span class="nota_fecha"><?php echo $nota['fecha']; ?></span>
                            </div>
                            <p><?php echo $nota['texto']; ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="campo_nota">
                    <label for="nota_tipo">Tipo</label>
                    <select id="nota_tipo" name="nota_tipo">
                        <option value="Nota">Nota</option>
                        <option value="Aviso">Aviso</option>
                    </select>
                </div>

                <div class="campo_nota">
                    <label for="nota_nueva">Contenido</label>
                    <textarea id="nota_nueva" name="nota_nueva" rows="4" placeholder="Escribe una nota o aviso..."></textarea>
                </div>

                <div class="area_botones area_botones--notas">
                    <button type="submit" name="guardar_nota" class="btn_guardar">Guardar nota</button>
                </div>
            </div>
        </aside>
    </div>
</form>

<script>
function activarEdicion(idCampo) {
    let input = document.getElementById(idCampo);

    input.removeAttribute('readonly');
    input.focus();
    input.classList.add('editando');
}
</script>
