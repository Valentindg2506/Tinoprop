<?php
/* Seccion: Alquileres (Vendedor)
   Descripcion: Tarjetas con propiedades en alquiler
*/

$propiedades_alquiler = [
    [
        "id" => 301,
        "titulo" => "Loft Urban",
        "tipo" => "Loft",
        "ubicacion" => "Valencia",
        "metros" => 60,
        "habitaciones" => 1,
        "banos" => 1,
        "precio" => "850 EUR/mes",
        "estado" => "Disponible"
    ],
    [
        "id" => 302,
        "titulo" => "Piso Familiar",
        "tipo" => "Piso",
        "ubicacion" => "Burjassot",
        "metros" => 90,
        "habitaciones" => 3,
        "banos" => 2,
        "precio" => "1.050 EUR/mes",
        "estado" => "Reservado"
    ],
    [
        "id" => 303,
        "titulo" => "Estudio Centro",
        "tipo" => "Estudio",
        "ubicacion" => "Valencia",
        "metros" => 45,
        "habitaciones" => 1,
        "banos" => 1,
        "precio" => "720 EUR/mes",
        "estado" => "Disponible"
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
                <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=alquileres-vendedor">Ver en detalle ➜</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
