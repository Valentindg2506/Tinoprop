<?php
/**
 * ========================================================================================
 * ARCHIVO: busqueda-avanzada.php
 * SECCIÓN: Búsqueda Avanzada de Propiedades (Datos de Habitaclia)
 * ========================================================================================
 *
 * PROPÓSITO:
 * Permite buscar y filtrar propiedades que han sido obtenidas automáticamente
 * mediante web scraping del portal inmobiliario Habitaclia. Los datos se
 * almacenan en una tabla caché local (scraped_propiedades) para consultarlos
 * rápidamente sin hacer peticiones al portal externo cada vez.
 *
 * ¿QUÉ ES WEB SCRAPING?
 * Es una técnica que extrae datos automáticamente de páginas web. En este caso,
 * un script Python (scripts/scrape_habitaclia.py) visita Habitaclia,
 * recoge la información de las propiedades y la guarda en nuestra base de datos.
 *
 * FLUJO DE DATOS:
 * 1. El script Python hace scraping de Habitaclia y guarda en scraped_propiedades.
 * 2. Este archivo PHP lee esos datos cacheados y permite filtrarlos.
 * 3. El usuario puede filtrar por: texto libre, zona, operación (venta/alquiler),
 *    rango de precios, mínimo de habitaciones/baños, y elegir orden.
 * 4. Los resultados se muestran como tarjetas con carrousel de imágenes.
 *
 * IMPORTANTE:
 * - Esta sección NO modifica datos (no hay POST). Solo lee y filtra.
 * - Los resultados se limitan a 100 para evitar sobrecargar el navegador.
 * - Se enfoca en propiedades de Valencia por defecto.
 *
 * @version V0.0.31
 */

/* Bootstrap: inicializa sesión, BD, funciones y autenticación. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Conexión a base de datos. */
$pdo = db();

/* Nos aseguramos de que la tabla 'scraped_propiedades' exista.
   Si es la primera vez que se accede, la crea automáticamente con
   todas las columnas necesarias (título, precio, ubicación, etc.). */
scraped_propiedades_asegurar_tabla($pdo);

/* ══════ NORMALIZACIÓN DE FILTROS ═════════════════════════════════════════════════ */
/* Leemos todos los filtros de la URL (parámetros GET) y los limpiamos.
   Cada filtro es opcional: si no viene, se deja vacío o null.
   - trim(): elimina espacios en blanco sobrantes.
   - (float)/(int): convierte el texto a número para evitar inyecciones. */
$filtros = [
    'q' => trim($_GET['q'] ?? ''),
    'zona' => trim($_GET['zona'] ?? ''),
    'operacion' => trim($_GET['operacion'] ?? ''),
    'min_precio' => (isset($_GET['min_precio']) && $_GET['min_precio'] !== '') ? (float) $_GET['min_precio'] : null,
    'max_precio' => (isset($_GET['max_precio']) && $_GET['max_precio'] !== '') ? (float) $_GET['max_precio'] : null,
    'habitaciones' => (isset($_GET['habitaciones']) && $_GET['habitaciones'] !== '') ? (int) $_GET['habitaciones'] : null,
    'banos' => (isset($_GET['banos']) && $_GET['banos'] !== '') ? (int) $_GET['banos'] : null,
    'orden' => $_GET['orden'] ?? 'recientes',
];

/* ══════ CONSTRUCCIÓN DINÁMICA DE LA CONSULTA SQL ═══════════════════════════════
   La consulta SQL se construye dinámicamente añadiendo condiciones WHERE
   solo cuando el usuario ha rellenado ese filtro. Así, si no pone zona,
   no se filtra por zona. Si pone precio mínimo, se añade esa condición. */

/* Condición base: solo propiedades de Valencia (foco de negocio). */
$where = ['ciudad = :ciudad'];
$params = ['ciudad' => 'Valencia'] + sql_iid_params();

/* Filtro multi-tenant: asegura que cada inmobiliaria solo vea sus datos. */
[$iid_cond, $iid_p] = sql_iid_pair();
if ($iid_cond) { $where[] = ltrim($iid_cond, ' AND '); }

/* ── FILTRO DE TEXTO LIBRE ────────────────────────────────────────────────
   Busca en el título, descripción, zona y ubicación.
   Usa LIKE con %texto% para encontrar coincidencias parciales
   (ej: buscar "centro" encontrará "Piso en el centro histórico"). */
if ($filtros['q'] !== '') {
    $where[] = '(titulo LIKE :q OR descripcion LIKE :q OR zona LIKE :q OR ubicacion LIKE :q)';
    $params['q'] = '%' . $filtros['q'] . '%';
}

/* ── FILTROS OPCIONALES ACUMULATIVOS ─────────────────────────────────────
   Cada filtro añade una condición AND a la consulta.
   Son "acumulativos" porque se suman: si pongo zona Y precio mínimo,
   filtra por ambos a la vez. */
if ($filtros['zona'] !== '') {
    $where[] = 'zona LIKE :zona';
    $params['zona'] = '%' . $filtros['zona'] . '%';
}

/* Filtro por tipo de operación: 'venta' o 'alquiler'. */
if ($filtros['operacion'] !== '') {
    $where[] = 'operacion = :operacion';
    $params['operacion'] = $filtros['operacion'];
}

/* Rango de precios: mínimo y máximo (ambos opcionales). */
if ($filtros['min_precio'] !== null) {
    $where[] = 'precio >= :min_precio';
    $params['min_precio'] = $filtros['min_precio'];
}

if ($filtros['max_precio'] !== null) {
    $where[] = 'precio <= :max_precio';
    $params['max_precio'] = $filtros['max_precio'];
}

/* Mínimo de habitaciones y baños: usa >= (mayor o igual que). */
if ($filtros['habitaciones'] !== null) {
    $where[] = 'habitaciones >= :habitaciones';
    $params['habitaciones'] = $filtros['habitaciones'];
}

if ($filtros['banos'] !== null) {
    $where[] = 'banos >= :banos';
    $params['banos'] = $filtros['banos'];
}

/* ── ORDEN DE RESULTADOS ────────────────────────────────────────────────
   El usuario puede elegir cómo ordenar los resultados:
   - recientes: las últimas añadidas primero (por fecha de scraping).
   - precio_asc/desc: de más barato a más caro, o viceversa.
   - metros_desc: las más grandes primero.
   Nota: 'precio IS NULL' pone los sin precio al final. */
$order = 'scraped_at DESC';
if ($filtros['orden'] === 'precio_asc') {
    $order = 'precio IS NULL, precio ASC';
} elseif ($filtros['orden'] === 'precio_desc') {
    $order = 'precio IS NULL, precio DESC';
} elseif ($filtros['orden'] === 'metros_desc') {
    $order = 'metros IS NULL, metros DESC';
}

/* ── EJECUCIÓN DE LA CONSULTA FINAL ────────────────────────────────────
   Unimos todas las condiciones WHERE con AND, añadimos el ORDER BY
   y limitamos a 100 resultados para no saturar el navegador.
   LIMIT 100 es una medida de protección: si hay miles de propiedades
   scrapeadas, solo mostramos las primeras 100. */
$sql = 'SELECT id, fuente, titulo, tipo, operacion, precio, moneda, ubicacion, zona, ciudad, habitaciones, banos, metros, descripcion, imagen_url, imagenes_json, ascensor, url, scraped_at
        FROM scraped_propiedades
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $order . '
        LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$propiedades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* Estadísticas rápidas: total de propiedades cacheadas y última actualización.
   Se muestran en la cabecera para que el usuario sepa cuántos datos hay
   y cuándo se hizo por última vez el scraping. */
$stmtStats = $pdo->prepare('SELECT COUNT(*) AS total, MAX(scraped_at) AS last_run FROM scraped_propiedades WHERE ciudad = :ciudad' . sql_iid());
$stmtStats->execute(['ciudad' => 'Valencia'] + sql_iid_params());
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'last_run' => null];
?>

<div class="encabezado_seccion">
    <div>
        <p class="texto_muted">Datos cacheados desde Habitaclia. Registros: <?php echo (int) ($stats['total'] ?? 0); ?><?php if (!empty($stats['last_run'])): ?> · Ultima sincronizacion: <?php echo e($stats['last_run']); ?><?php endif; ?></p>
    </div>
</div>

<form method="GET" class="barra_filtros filtros_avanzada">
    <input type="hidden" name="seccion" value="busqueda-avanzada">

    <div class="filtro_item" style="grid-column: 1 / -1;">
        <label for="q">Busqueda rapida</label>
        <input id="q" name="q" type="text" value="<?php echo e($filtros['q']); ?>" placeholder="Titulo, descripcion, zona...">
    </div>

    <div class="filtro_item">
        <label for="zona">Zona / barrio</label>
        <input id="zona" name="zona" type="text" value="<?php echo e($filtros['zona']); ?>" placeholder="Ruzafa, El Carmen...">
    </div>

    <div class="filtro_item">
        <label for="operacion">Operacion</label>
        <select id="operacion" name="operacion">
            <option value="">Cualquiera</option>
            <option value="venta" <?php echo $filtros['operacion'] === 'venta' ? 'selected' : ''; ?>>Venta</option>
            <option value="alquiler" <?php echo $filtros['operacion'] === 'alquiler' ? 'selected' : ''; ?>>Alquiler</option>
        </select>
    </div>

    <div class="filtro_item">
        <label for="min_precio">Precio min</label>
        <input id="min_precio" name="min_precio" type="number" min="0" step="500" value="<?php echo e((string) $filtros['min_precio']); ?>">
    </div>

    <div class="filtro_item">
        <label for="max_precio">Precio max</label>
        <input id="max_precio" name="max_precio" type="number" min="0" step="500" value="<?php echo e((string) $filtros['max_precio']); ?>">
    </div>

    <div class="filtro_item">
        <label for="habitaciones">Min. habitaciones</label>
        <input id="habitaciones" name="habitaciones" type="number" min="0" step="1" value="<?php echo e((string) $filtros['habitaciones']); ?>">
    </div>

    <div class="filtro_item">
        <label for="banos">Min. banos</label>
        <input id="banos" name="banos" type="number" min="0" step="1" value="<?php echo e((string) $filtros['banos']); ?>">
    </div>

    <div class="filtro_item">
        <label for="orden">Orden</label>
        <select id="orden" name="orden">
            <option value="recientes" <?php echo $filtros['orden'] === 'recientes' ? 'selected' : ''; ?>>Mas recientes</option>
            <option value="precio_asc" <?php echo $filtros['orden'] === 'precio_asc' ? 'selected' : ''; ?>>Precio ascendente</option>
            <option value="precio_desc" <?php echo $filtros['orden'] === 'precio_desc' ? 'selected' : ''; ?>>Precio descendente</option>
            <option value="metros_desc" <?php echo $filtros['orden'] === 'metros_desc' ? 'selected' : ''; ?>>M2 descendente</option>
        </select>
    </div>

    <div class="acciones_inline" style="align-self: end;">
        <button type="submit" class="btn_guardar">Buscar</button>
        <a class="btn_secundario" href="index.php?seccion=busqueda-avanzada">Limpiar</a>
    </div>
</form>

<?php if (empty($propiedades)): ?>
    <div class="tarjeta_info">
        <p>No hay resultados con los filtros actuales.</p>
        <p>Ejecuta el scraper en <strong>scripts/scrape_habitaclia.py</strong> para poblar el cache o ajusta los filtros.</p>
    </div>
<?php else: ?>
    <div class="grid_cards">
        <?php foreach ($propiedades as $propiedad): ?>
            <?php
                $imagenes = [];
                $operacionBadge = $propiedad['operacion'] ?? null;
                if (empty($operacionBadge) && !empty($propiedad['url'])) {
                    $u = strtolower((string) $propiedad['url']);
                    if (strpos($u, 'alquiler') !== false) {
                        $operacionBadge = 'alquiler';
                    } elseif (strpos($u, 'comprar') !== false || strpos($u, 'venta') !== false) {
                        $operacionBadge = 'venta';
                    }
                }
                if (!empty($propiedad['imagenes_json'])) {
                    $decoded = json_decode((string) $propiedad['imagenes_json'], true);
                    if (is_array($decoded)) {
                        $imagenes = array_values(array_filter($decoded, function ($u) {
                            return is_string($u) && trim($u) !== '';
                        }));
                    }
                }
                $img_url = $imagenes[0] ?? ((isset($propiedad['imagen_url']) && trim((string) $propiedad['imagen_url']) !== '') ? $propiedad['imagen_url'] : null);
                if (empty($imagenes) && $img_url) {
                    $imagenes = [$img_url];
                }
            ?>
            <article class="card_propiedad">
                <div class="card_media carousel" data-count="<?php echo count($imagenes); ?>">
                    <div class="carousel_track">
                        <?php if (!empty($imagenes)): ?>
                            <?php foreach ($imagenes as $idx => $img): ?>
                                <?php if (!empty($img)): ?>
                                    <div class="carousel_slide <?php echo $idx === 0 ? 'is-active' : ''; ?>">
                                        <img class="carousel_img" src="<?php echo e($img); ?>" alt="<?php echo e($propiedad['titulo']); ?>" loading="lazy" decoding="async">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (empty($imagenes)): ?>
                            <div class="carousel_slide is-active carousel_empty">Sin imagen</div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($imagenes) > 1): ?>
                        <button class="carousel_btn prev" aria-label="Anterior">‹</button>
                        <button class="carousel_btn next" aria-label="Siguiente">›</button>
                        <div class="carousel_dots">
                            <?php foreach ($imagenes as $idx => $_img): ?>
                                <span class="carousel_dot <?php echo $idx === 0 ? 'is-active' : ''; ?>" data-index="<?php echo $idx; ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($operacionBadge)): ?>
                        <span class="card_tag"><?php echo e($operacionBadge); ?></span>
                    <?php endif; ?>
                </div>

                <div class="card_body">
                    <h3><?php echo e($propiedad['titulo']); ?></h3>
                    <p class="card_meta">
                        <?php echo e($propiedad['zona'] ?: $propiedad['ubicacion'] ?: $propiedad['ciudad']); ?> ·
                        <?php echo e($propiedad['operacion'] ? ucfirst($propiedad['operacion']) : 'Operacion no informada'); ?> ·
                        <?php echo e($propiedad['tipo'] ?: 'Tipo no informado'); ?>
                    </p>
                    <p class="card_meta">
                        <?php echo e($propiedad['metros'] ? $propiedad['metros'] . ' m²' : 'Metros no informados'); ?> ·
                        <?php echo e($propiedad['habitaciones'] !== null ? ((string) $propiedad['habitaciones']) . ' hab' : 'Hab. no informadas'); ?> ·
                        <?php echo e($propiedad['banos'] !== null ? ((string) $propiedad['banos']) . ' baños' : 'Baños no informados'); ?> ·
                        Ascensor: <?php
                            if ($propiedad['ascensor'] === '0' || $propiedad['ascensor'] === 0) {
                                echo 'No';
                            } elseif ($propiedad['ascensor'] === '1' || $propiedad['ascensor'] === 1) {
                                echo 'Sí';
                            } else {
                                echo 'No informado';
                            }
                        ?>
                    </p>
                    <div class="card_precios">
                        <span class="card_precio">
                            <?php if (!empty($propiedad['precio'])): ?>
                                <?php echo e(format_price((float) $propiedad['precio'], $propiedad['moneda'], null)); ?>
                            <?php else: ?>
                                Precio no informado
                            <?php endif; ?>
                        </span>
                        <span class="badge_estado badge_estado--disponible">Fuente: <?php echo e($propiedad['fuente']); ?></span>
                    </div>
                    <p class="card_descripcion">
                        <?php echo e(substr((string) ($propiedad['descripcion'] ?? ''), 0, 180)); ?>
                    </p>
                </div>

                <div class="card_footer">
                    <div class="acciones_inline">
                        <a class="btn_ver_mas" href="<?php echo e($propiedad['url']); ?>" target="_blank" rel="noopener noreferrer">Ver en Habitaclia ➜</a>
                        <span class="texto_muted">Actualizado: <?php echo e($propiedad['scraped_at']); ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function() {
    const carousels = document.querySelectorAll('.carousel');
    carousels.forEach(function(root) {
        const slides = Array.from(root.querySelectorAll('.carousel_slide'));
        if (slides.length <= 1) return;

        let current = 0;
        const dots = Array.from(root.querySelectorAll('.carousel_dot'));
        const track = root.querySelector('.carousel_track');

        function show(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach((s, i) => s.classList.toggle('is-active', i === current));
            dots.forEach((d, i) => d.classList.toggle('is-active', i === current));
            track.style.transform = `translateX(-${current * 100}%)`;
        }

        const prevBtn = root.querySelector('.carousel_btn.prev');
        const nextBtn = root.querySelector('.carousel_btn.next');
        if (prevBtn) prevBtn.addEventListener('click', () => show(current - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => show(current + 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
        show(0);
    });
})();
</script>
