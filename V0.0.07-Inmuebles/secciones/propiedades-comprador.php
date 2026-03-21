<?php
/* Seccion: Propiedades (Comprador)
   Descripcion: Tarjetas con propiedades en venta
*/

$propiedades_venta = [
    [
        "id" => 201,
        "titulo" => "Apartamento Centrico",
        "tipo" => "Piso",
        "ubicacion" => "Valencia",
        "metros" => 95,
        "habitaciones" => 2,
        "banos" => 1,
        "precio" => "185.000 EUR",
        "estado" => "Disponible"
    ],
    [
        "id" => 202,
        "titulo" => "Chalet con Jardin",
        "tipo" => "Chalet",
        "ubicacion" => "Torrent",
        "metros" => 180,
        "habitaciones" => 4,
        "banos" => 2,
        "precio" => "320.000 EUR",
        "estado" => "Reservado"
    ],
    [
        "id" => 203,
        "titulo" => "Duplex Moderno",
        "tipo" => "Duplex",
        "ubicacion" => "Valencia",
        "metros" => 120,
        "habitaciones" => 3,
        "banos" => 2,
        "precio" => "255.000 EUR",
        "estado" => "Disponible"
    ],
];
?>

<div class="encabezado_seccion">
    <h2>Propiedades en Venta</h2>
    <button class="btn_nuevo_cliente">+ Nueva Propiedad</button>
</div>

<div class="grid_cards">
    <?php foreach ($propiedades_venta as $propiedad): ?>
        <article class="card_propiedad">
            <div class="card_media">
                <span class="card_tag">Venta</span>
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
                <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=propiedades-comprador">Ver en detalle ➜</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
