<?php
/* Seccion: Alquileres (Comprador)
   Descripcion: Tarjetas con propiedades en alquiler
*/
/*
 * Sección: Alquileres comprador
 * Rol: alta, listado y baja de propiedades en alquiler para equipo comprador.
 * Operaciones principales: crear_alquiler, eliminar_propiedad.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$origen = 'alquileres-comprador';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

// Controlador POST del módulo: alta y baja de propiedades en alquiler del equipo comprador.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['crear_alquiler'])) {
        // Recoge y valida datos mínimos de alquiler.
        $errores = [];
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $precio = $_POST['precio'] !== '' ? (float) $_POST['precio'] : 0;
        $estado = trim($_POST['estado'] ?? 'Disponible');
        $estados_validos = ['Disponible', 'Reservado', 'Vendido'];

        if (!validar_requerido($titulo)) {
            $errores[] = 'El titulo es obligatorio.';
        }
        if (!validar_requerido($tipo)) {
            $errores[] = 'El tipo es obligatorio.';
        }
        if (!validar_requerido($ubicacion)) {
            $errores[] = 'La ubicacion es obligatoria.';
        }
        if ($precio <= 0) {
            $errores[] = 'El precio debe ser mayor a 0.';
        }
        if (!validar_enum($estado, $estados_validos)) {
            $errores[] = 'El estado seleccionado no es valido.';
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-alquiler');
            exit;
        }

        // Inserta propiedad con operacion alquiler y periodo mensual.
        $stmt = $pdo->prepare(
            'INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, periodo, operacion, estado, referencia, descripcion, inmobiliaria_id, usuario_id)
             VALUES (:equipo, :titulo, :tipo, :ubicacion, :direccion, :metros, :habitaciones, :banos, :precio, :moneda, :periodo, :operacion, :estado, :referencia, :descripcion, :iid, :uid)'
        );
        $stmt->execute([
            'equipo' => 'comprador',
            'titulo' => $titulo,
            'tipo' => $tipo,
            'ubicacion' => $ubicacion,
            'direccion' => trim($_POST['direccion'] ?? ''),
            'metros' => $_POST['metros'] !== '' ? (int) $_POST['metros'] : null,
            'habitaciones' => $_POST['habitaciones'] !== '' ? (int) $_POST['habitaciones'] : null,
            'banos' => $_POST['banos'] !== '' ? (int) $_POST['banos'] : null,
            'precio' => $precio,
            'moneda' => 'EUR',
            'periodo' => 'mes',
            'operacion' => 'alquiler',
            'estado' => $estado,
            'referencia' => trim($_POST['referencia'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        flash_set('success', 'Alquiler creado correctamente.');
    }

    if (isset($_POST['eliminar_propiedad'])) {
        // Elimina notas asociadas y luego la propiedad en alquiler.
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id" . sql_iid());
            $stmt->execute(['id' => $id_eliminar] + sql_iid_params());

            $stmt = $pdo->prepare('DELETE FROM propiedades WHERE id = :id' . sql_iid());
            $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
        }
        flash_set('success', 'Alquiler eliminado correctamente.');
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

// Listado de alquileres del equipo comprador para tarjetas.
$stmt = $pdo->prepare('SELECT id, titulo, tipo, ubicacion, metros, habitaciones, banos, precio, moneda, periodo, estado FROM propiedades WHERE equipo = :equipo AND operacion = :operacion' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
$stmt->execute([
    'equipo' => 'comprador',
    'operacion' => 'alquiler'
] + sql_iid_params() + sql_uid_params());
$propiedades_alquiler = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <a href="#nuevo-alquiler" class="btn_nuevo_cliente">+ Nuevo Alquiler</a>
</div>

<div id="nuevo-alquiler" class="form_panel">
    <h3>Crear propiedad en alquiler</h3>
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid">
        <?php echo csrf_field(); ?>
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
            <label for="precio">Precio (EUR/mes)</label>
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
            <button type="submit" name="crear_alquiler" class="btn_guardar">Guardar</button>
        </div>
    </form>
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
                <div class="acciones_inline">
                    <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=alquileres-comprador">Ver en detalle ➜</a>
                    <form method="POST" data-confirm="¿Eliminar este alquiler? Esta accion no se puede deshacer.">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $propiedad['id']; ?>">
                        <button type="submit" name="eliminar_propiedad" class="btn_peligro">Eliminar</button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
