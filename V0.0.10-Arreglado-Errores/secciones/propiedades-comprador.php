<?php
/* Seccion: Propiedades (Comprador)
   Descripcion: Tarjetas con propiedades en venta
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$origen = 'propiedades-comprador';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_propiedad'])) {
        $stmt = $pdo->prepare(
            'INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, periodo, operacion, estado, referencia, descripcion)
             VALUES (:equipo, :titulo, :tipo, :ubicacion, :direccion, :metros, :habitaciones, :banos, :precio, :moneda, :periodo, :operacion, :estado, :referencia, :descripcion)'
        );
        $stmt->execute([
            'equipo' => 'comprador',
            'titulo' => trim($_POST['titulo'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? ''),
            'ubicacion' => trim($_POST['ubicacion'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'metros' => $_POST['metros'] !== '' ? (int) $_POST['metros'] : null,
            'habitaciones' => $_POST['habitaciones'] !== '' ? (int) $_POST['habitaciones'] : null,
            'banos' => $_POST['banos'] !== '' ? (int) $_POST['banos'] : null,
            'precio' => $_POST['precio'] !== '' ? (float) $_POST['precio'] : 0,
            'moneda' => 'EUR',
            'periodo' => null,
            'operacion' => 'venta',
            'estado' => trim($_POST['estado'] ?? 'Disponible'),
            'referencia' => trim($_POST['referencia'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
        ]);
    }

    if (isset($_POST['eliminar_propiedad'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id");
            $stmt->execute(['id' => $id_eliminar]);

            $stmt = $pdo->prepare('DELETE FROM propiedades WHERE id = :id');
            $stmt->execute(['id' => $id_eliminar]);
        }
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

$stmt = $pdo->prepare('SELECT id, titulo, tipo, ubicacion, metros, habitaciones, banos, precio, moneda, periodo, estado FROM propiedades WHERE equipo = :equipo AND operacion = :operacion ORDER BY id DESC');
$stmt->execute([
    'equipo' => 'comprador',
    'operacion' => 'venta'
]);
$propiedades_venta = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <h2>Propiedades en Venta</h2>
    <a href="#nueva-propiedad" class="btn_nuevo_cliente">+ Nueva Propiedad</a>
</div>

<div id="nueva-propiedad" class="form_panel">
    <h3>Crear propiedad en venta</h3>
    <form method="POST" class="form_grid">
        <div class="campo_input">
            <label for="titulo">Titulo</label>
            <input id="titulo" name="titulo" type="text" required>
        </div>
        <div class="campo_input">
            <label for="tipo">Tipo</label>
            <input id="tipo" name="tipo" type="text" required>
        </div>
        <div class="campo_input">
            <label for="ubicacion">Ubicacion</label>
            <input id="ubicacion" name="ubicacion" type="text" required>
        </div>
        <div class="campo_input">
            <label for="direccion">Direccion</label>
            <input id="direccion" name="direccion" type="text">
        </div>
        <div class="campo_input">
            <label for="metros">Metros</label>
            <input id="metros" name="metros" type="number" min="0">
        </div>
        <div class="campo_input">
            <label for="habitaciones">Habitaciones</label>
            <input id="habitaciones" name="habitaciones" type="number" min="0">
        </div>
        <div class="campo_input">
            <label for="banos">Banos</label>
            <input id="banos" name="banos" type="number" min="0">
        </div>
        <div class="campo_input">
            <label for="precio">Precio (EUR)</label>
            <input id="precio" name="precio" type="number" min="0" step="0.01" required>
        </div>
        <div class="campo_input">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <option value="Disponible">Disponible</option>
                <option value="Reservado">Reservado</option>
                <option value="Vendido">Vendido</option>
            </select>
        </div>
        <div class="campo_input">
            <label for="referencia">Referencia</label>
            <input id="referencia" name="referencia" type="text">
        </div>
        <div class="campo_input">
            <label for="descripcion">Descripcion</label>
            <input id="descripcion" name="descripcion" type="text">
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_propiedad" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>

<div class="grid_cards">
    <?php foreach ($propiedades_venta as $propiedad): ?>
        <article class="card_propiedad">
            <div class="card_media">
                <span class="card_tag">Venta</span>
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
                <div class="acciones_inline">
                    <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=propiedades-comprador">Ver en detalle ➜</a>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $propiedad['id']; ?>">
                        <button type="submit" name="eliminar_propiedad" class="btn_peligro">Eliminar</button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
