<?php
/* Seccion: Alquileres (Vendedor)
   Descripcion: Tarjetas con propiedades en alquiler
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$stmt = $pdo->prepare('SELECT id, titulo, tipo, ubicacion, metros, habitaciones, banos, precio, moneda, periodo, estado FROM propiedades WHERE equipo = :equipo AND operacion = :operacion ORDER BY id DESC');
$stmt->execute([
    'equipo' => 'vendedor',
    'operacion' => 'alquiler'
]);
$propiedades_alquiler = $stmt->fetchAll();
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
                    <?php echo e($propiedad['tipo']); ?>
                </div>
            </div>

            <div class="card_body">
                <h3><?php echo e($propiedad['titulo']); ?></h3>
                <p class="card_meta">
                    <?php echo e($propiedad['ubicacion']); ?> · <?php echo e((string) $propiedad['metros']); ?> m2 · <?php echo e((string) $propiedad['habitaciones']); ?> hab · <?php echo e((string) $propiedad['banos']); ?> banos
                </p>
                <div class="card_precios">
                    <span class="card_precio"><?php echo e(format_price((float) $propiedad['precio'], $propiedad['moneda'], $propiedad['periodo'])); ?></span>
                    <span class="badge_estado badge_estado--<?php echo e(map_estado_clase($propiedad['estado'])); ?>">
                        <?php echo e($propiedad['estado']); ?>
                    </span>
                </div>
            </div>

            <div class="card_footer">
                <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=alquileres-vendedor">Ver en detalle ➜</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
