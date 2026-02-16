<?php
/* Seccion: Alquileres (Comprador)
   Descripcion: Tarjetas con propiedades en alquiler
*/

$propiedades_alquiler = [
    [
        "id" => 401,
        "titulo" => "Piso con Terraza",
        "tipo" => "Piso",
        "ubicacion" => "Valencia",
        "metros" => 85,
        "habitaciones" => 2,
        "banos" => 1,
        "precio" => "980 EUR/mes",
        "estado" => "Disponible"
    ],
    [
        "id" => 402,
        "titulo" => "Duplex Moderno",
        "tipo" => "Duplex",
        "ubicacion" => "Paterna",
        "metros" => 100,
        "habitaciones" => 3,
        "banos" => 2,
        "precio" => "1.150 EUR/mes",
        "estado" => "Disponible"
    ],
    [
        "id" => 403,
        "titulo" => "Estudio con Luz",
        "tipo" => "Estudio",
        "ubicacion" => "Valencia",
        "metros" => 40,
        "habitaciones" => 1,
        "banos" => 1,
        "precio" => "700 EUR/mes",
        "estado" => "Reservado"
    ],
];
?>

<div class="encabezado_seccion">
    <h2>Propiedades en Alquiler</h2>
    <button class="btn_nuevo_cliente">+ Nuevo Alquiler</button>
</div>

<div class="grid_cards">
    <?php foreach ($propiedades_alquiler as $propiedad): ?>
        <article class="card_propiedad">
            <div class="card_media">
                <span class="card_tag card_tag--alquiler">Alquiler</span>
                <div class="card_media_placeholder">
                    <?php echo $propiedad['tipo']; ?>
                </div>
            </div>

            <div class="card_body">
                <h3><?php echo $propiedad['titulo']; ?></h3>
                <p class="card_meta">
                    <?php echo $propiedad['ubicacion']; ?> · <?php echo $propiedad['metros']; ?> m2 · <?php echo $propiedad['habitaciones']; ?> hab · <?php echo $propiedad['banos']; ?> banos
                </p>
                <div class="card_precios">
                    <span class="card_precio"><?php echo $propiedad['precio']; ?></span>
                    <span class="badge_estado badge_estado--<?php echo strtolower($propiedad['estado']); ?>">
                        <?php echo $propiedad['estado']; ?>
                    </span>
                </div>
            </div>

            <div class="card_footer">
                <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=alquileres-comprador">Ver en detalle ➜</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
