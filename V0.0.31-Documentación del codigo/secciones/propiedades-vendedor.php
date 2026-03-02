<?php
/**
 * ============================================================================
 * ARCHIVO: propiedades-vendedor.php
 * SECCIÓN: Propiedades en Venta del equipo Vendedor
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Gestiona las propiedades inmobiliarias que están en VENTA y son
 * responsabilidad del equipo vendedor. Permite:
 *   - CREAR nuevas propiedades con datos como título, tipo, ubicación,
 *     metros, habitaciones, baños, precio, estado y referencia
 *   - LISTAR propiedades en formato de tarjetas visuales (cards)
 *   - ELIMINAR propiedades con sus notas asociadas
 *   - EXPORTAR la lista a CSV (compatible con Excel)
 *
 * VISUALIZACIÓN:
 * A diferencia de los clientes (que usan tabla), las propiedades se
 * muestran en tarjetas (cards) con información visual: tipo de inmueble,
 * precio formateado, badge de estado (Disponible/Reservado/Vendido)
 * y botón para ver la ficha completa.
 *
 * SEGURIDAD:
 * - csrf_verify() contra ataques CSRF
 * - e() escapa HTML contra XSS
 * - sql_iid() aislamiento multi-tenant entre inmobiliarias
 * - Transacciones SQL para eliminación segúra (notas + propiedad)
 * - Sentencias preparadas PDO contra inyección SQL
 */

/* Se carga el sistema base: sesión, BD, funciones auxiliares */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Conexión a BD y variables de sección */
$pdo = db();
$origen = 'propiedades-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ========================================================================
   EXPORTAR A CSV
   ========================================================================
   Genera un archivo descargable con todas las propiedades en venta
   del equipo vendedor. Útil para informes o trabajo con Excel. */
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    $stmt = $pdo->prepare('SELECT titulo, operacion, precio, moneda, ubicacion, referencia, estado, created_at FROM propiedades WHERE equipo = :eq' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
    $stmt->execute(['eq' => 'vendedor'] + sql_iid_params() + sql_uid_params());
    exportar_csv($stmt->fetchAll(PDO::FETCH_ASSOC), 'propiedades_vendedor.csv');
}

/* ========================================================================
   CONTROLADOR POST — Crear y eliminar propiedades en venta
   ========================================================================
   Procesa dos acciones posibles:
   1. crear_propiedad — Insertar nueva propiedad con todos sus datos
   2. eliminar_propiedad — Borrar propiedad y notas asociadas */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Verificación de seguridad CSRF */
    csrf_verify();

    /* ----------------------------------------------------------------
       ACCIÓN: CREAR NUEVA PROPIEDAD EN VENTA
       ----------------------------------------------------------------
       Recoge datos del formulario: título, tipo de inmueble (piso, casa...),
       ubicación, metros cuadrados, habitaciones, baños, precio, etc. */
    if (isset($_POST['crear_propiedad'])) {
        /* Se recogen y validan los campos obligatorios */
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
            header('Location: index.php?seccion=' . $origen . '#nueva-propiedad');
            exit;
        }

        /* INSERCIÓN EN BD: guarda la propiedad con todos sus atributos.
           'equipo' = 'vendedor' indica que pertenece al equipo de ventas.
           'operacion' = 'venta' indica que es una propiedad para vender (no alquiler).
           'moneda' = 'EUR' y 'periodo' = null (no es alquiler mensual).
           Los campos opcionales (metros, habitaciones, baños) pueden ser null. */
        $stmt = $pdo->prepare(
            'INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, periodo, operacion, estado, referencia, descripcion, inmobiliaria_id, usuario_id)
             VALUES (:equipo, :titulo, :tipo, :ubicacion, :direccion, :metros, :habitaciones, :banos, :precio, :moneda, :periodo, :operacion, :estado, :referencia, :descripcion, :iid, :uid)'
        );
        $stmt->execute([
            'equipo' => 'vendedor',
            'titulo' => $titulo,
            'tipo' => $tipo,
            'ubicacion' => $ubicacion,
            'direccion' => trim($_POST['direccion'] ?? ''),
            'metros' => $_POST['metros'] !== '' ? (int) $_POST['metros'] : null,
            'habitaciones' => $_POST['habitaciones'] !== '' ? (int) $_POST['habitaciones'] : null,
            'banos' => $_POST['banos'] !== '' ? (int) $_POST['banos'] : null,
            'precio' => $precio,
            'moneda' => 'EUR',
            'periodo' => null,
            'operacion' => 'venta',
            'estado' => $estado,
            'referencia' => trim($_POST['referencia'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        /* Se registra la creación en el log de actividad del sistema */
        actividad_registrar($pdo, 'crear', 'propiedad', $pdo->lastInsertId(), $titulo);
        flash_set('success', 'Propiedad creada correctamente.');
    }

    /* ----------------------------------------------------------------
       ACCIÓN: ELIMINAR PROPIEDAD
       ----------------------------------------------------------------
       Usa transacción SQL para borrar primero las notas asociadas y
       luego la propiedad. Si falla, rollBack() deshace todo. */
    if (isset($_POST['eliminar_propiedad'])) {
        /* Eliminación segura con transacción atómica */
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id" . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());

                $stmt = $pdo->prepare('DELETE FROM propiedades WHERE id = :id' . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                $pdo->commit();
                actividad_registrar($pdo, 'eliminar', 'propiedad', $id_eliminar, '');
                flash_set('success', 'Propiedad eliminada correctamente.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Error al eliminar la propiedad. Inténtelo de nuevo.');
            }
        }
    }

    /* Patrón PRG: redirigir tras POST */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ========================================================================
   CONSULTA DE PROPIEDADES EN VENTA
   ========================================================================
   Se obtienen todas las propiedades del equipo vendedor con operación
   de venta. Se filtran por inmobiliaria (sql_iid) y usuario (sql_uid). */
$stmt = $pdo->prepare('SELECT id, titulo, tipo, ubicacion, metros, habitaciones, banos, precio, moneda, periodo, estado FROM propiedades WHERE equipo = :equipo AND operacion = :operacion' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
$stmt->execute([
    'equipo' => 'vendedor',
    'operacion' => 'venta'
] + sql_iid_params() + sql_uid_params());
$propiedades_venta = $stmt->fetchAll();
?>

<!-- =====================================================================
     PARTE VISUAL (HTML) — Tarjetas de propiedades en venta
     =====================================================================
     Las propiedades se muestran como tarjetas (cards) con imagen/tipo,
     datos básicos (ubicación, metros, habitaciones), precio formateado
     y badge de estado (Disponible, Reservado, Vendido). -->

<!-- Botón para mostrar el formulario de nueva propiedad -->
<div class="encabezado_seccion">
    <a href="#nueva-propiedad" class="btn_nuevo_cliente">+ Nueva Propiedad</a>
</div>

<!-- =====================================================================
     FORMULARIO DE ALTA — Crear nueva propiedad en venta
     =====================================================================
     Campos: título, tipo, ubicación, dirección, metros, habitaciones,
     baños, precio en EUR, estado, referencia (código interno) y descripción. -->
<div id="nueva-propiedad" class="form_panel">
    <h3>Crear propiedad en venta</h3>
    <!-- Mensajes flash de feedback -->
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid" data-validar>
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

<!-- Barra con botón de exportar a CSV -->
<div class="barra_acciones_tabla">
    <span>Propiedades</span>
    <a href="index.php?seccion=propiedades-vendedor&exportar=csv" class="btn_exportar">📥 Exportar CSV</a>
</div>

<!-- =====================================================================
     GRID DE TARJETAS DE PROPIEDADES
     =====================================================================
     Cada propiedad se muestra como una tarjeta visual con:
     - Etiqueta "Venta" y tipo de inmueble
     - Título, ubicación, metros, habitaciones y baños
     - Precio formateado con format_price() y badge de estado
     - Botones: "Ver en detalle" y "Eliminar" -->
<div class="grid_cards">
    <?php foreach ($propiedades_venta as $propiedad): ?>
        <!-- TARJETA DE PROPIEDAD -->
        <article class="card_propiedad">
            <div class="card_media">
                <span class="card_tag">Venta</span>
                <div class="card_media_placeholder">
                    <?php echo e($propiedad['tipo']); ?>
                </div>
            </div>

            <!-- Cuerpo de la tarjeta con datos principales -->
            <div class="card_body">
                <h3><?php echo e($propiedad['titulo']); ?></h3>
                <!-- Metadatos: ubicación, superficie, habitaciones, baños -->
                <p class="card_meta">
                    <?php echo e($propiedad['ubicacion']); ?> · <?php echo e((string) $propiedad['metros']); ?> m2 · <?php echo e((string) $propiedad['habitaciones']); ?> hab · <?php echo e((string) $propiedad['banos']); ?> banos
                </p>
                <!-- Precio formateado y badge de estado con color dinámico -->
                <div class="card_precios">
                    <span class="card_precio"><?php echo e(format_price((float) $propiedad['precio'], $propiedad['moneda'], $propiedad['periodo'])); ?></span>
                    <!-- map_estado_clase() convierte el estado en una clase CSS para colores -->
                    <span class="badge_estado badge_estado--<?php echo e(map_estado_clase($propiedad['estado'])); ?>">
                        <?php echo e($propiedad['estado']); ?>
                    </span>
                </div>
            </div>

            <div class="card_footer">
                <div class="acciones_inline">
                    <a class="btn_ver_mas" href="index.php?seccion=ver_propiedad&id=<?php echo $propiedad['id']; ?>&origen=propiedades-vendedor">Ver en detalle ➜</a>
                    <form method="POST" data-confirm="¿Eliminar esta propiedad? Esta accion no se puede deshacer.">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $propiedad['id']; ?>">
                        <button type="submit" name="eliminar_propiedad" class="btn_peligro">Eliminar</button>
                    </form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
