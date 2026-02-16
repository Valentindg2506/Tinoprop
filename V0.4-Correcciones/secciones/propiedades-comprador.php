<?php
/* Seccion: Propiedades (Comprador)
   Descripcion: Listado base con acciones rapidas
*/
?>

<div class="encabezado_seccion">
    <h2>Gestion de Propiedades</h2>
    <button class="btn_nuevo_cliente">+ Nueva Propiedad</button>
</div>

<div class="contenedor_tabla">
    <table class="tabla_datos">
        <thead>
            <tr>
                <th>Titulo</th>
                <th>Tipo</th>
                <th>Localizacion</th>
                <th>Precio</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td><strong>Apartamento Centrico</strong></td>
                <td>Piso</td>
                <td>Valencia</td>
                <td>185.000 EUR</td>
                <td>Disponible</td>
                <td>
                    <button class="btn_ver_mas">Ver mas ➜</button>
                </td>
            </tr>

            <tr>
                <td><strong>Chalet con Jardin</strong></td>
                <td>Chalet</td>
                <td>Torrent</td>
                <td>320.000 EUR</td>
                <td>Reservado</td>
                <td>
                    <button class="btn_ver_mas">Ver mas ➜</button>
                </td>
            </tr>

            <tr>
                <td><strong>Local Comercial</strong></td>
                <td>Local</td>
                <td>Paterna</td>
                <td>1.200 EUR/mes</td>
                <td>Alquiler</td>
                <td>
                    <button class="btn_ver_mas">Ver mas ➜</button>
                </td>
            </tr>
        </tbody>
    </table>
</div>
