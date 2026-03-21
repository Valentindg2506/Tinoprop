<?php
/* Seccion: Busqueda Avanzada (scraping Habitaclia Valencia)
   Descripcion: Permite filtrar propiedades obtenidas via scraping y almacenadas en cache.
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
scraped_propiedades_asegurar_tabla($pdo);

$filtros = [
    'q' => trim($_GET['q'] ?? ''),
    'zona' => trim($_GET['zona'] ?? ''),
    'operacion' => trim($_GET['operacion'] ?? ''),
    'min_precio' => $_GET['min_precio'] !== '' ? (float) $_GET['min_precio'] : null,
    'max_precio' => $_GET['max_precio'] !== '' ? (float) $_GET['max_precio'] : null,
    'habitaciones' => $_GET['habitaciones'] !== '' ? (int) $_GET['habitaciones'] : null,
    'banos' => $_GET['banos'] !== '' ? (int) $_GET['banos'] : null,
    'orden' => $_GET['orden'] ?? 'recientes',
];

$where = ['ciudad = :ciudad'];
$params = ['ciudad' => 'Valencia'];

if ($filtros['q'] !== '') {
    $where[] = '(titulo LIKE :q OR descripcion LIKE :q OR zona LIKE :q OR ubicacion LIKE :q)';
    $params['q'] = '%' . $filtros['q'] . '%';
}

if ($filtros['zona'] !== '') {
    $where[] = 'zona LIKE :zona';
    $params['zona'] = '%' . $filtros['zona'] . '%';
}

if ($filtros['operacion'] !== '') {
    $where[] = 'operacion = :operacion';
    $params['operacion'] = $filtros['operacion'];
}

if ($filtros['min_precio'] !== null) {
    $where[] = 'precio >= :min_precio';
    $params['min_precio'] = $filtros['min_precio'];
}

if ($filtros['max_precio'] !== null) {
    $where[] = 'precio <= :max_precio';
    $params['max_precio'] = $filtros['max_precio'];
}

if ($filtros['habitaciones'] !== null) {
    $where[] = 'habitaciones >= :habitaciones';
    $params['habitaciones'] = $filtros['habitaciones'];
}

if ($filtros['banos'] !== null) {
    $where[] = 'banos >= :banos';
    $params['banos'] = $filtros['banos'];
}

$order = 'scraped_at DESC';
if ($filtros['orden'] === 'precio_asc') {
    $order = 'precio IS NULL, precio ASC';
} elseif ($filtros['orden'] === 'precio_desc') {
    $order = 'precio IS NULL, precio DESC';
} elseif ($filtros['orden'] === 'metros_desc') {
    $order = 'metros IS NULL, metros DESC';
}

$sql = 'SELECT id, fuente, titulo, tipo, operacion, precio, moneda, ubicacion, zona, ciudad, habitaciones, banos, metros, descripcion, url, scraped_at
        FROM scraped_propiedades
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $order . '
        LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$propiedades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmtStats = $pdo->prepare('SELECT COUNT(*) AS total, MAX(scraped_at) AS last_run FROM scraped_propiedades WHERE ciudad = :ciudad');
$stmtStats->execute(['ciudad' => 'Valencia']);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'last_run' => null];
?>

<div class="encabezado_seccion">
    <div>
        <h2>Busqueda avanzada (Valencia)</h2>
        <p class="texto_muted">Datos cacheados desde Habitaclia. Registros: <?php echo (int) ($stats['total'] ?? 0); ?><?php if (!empty($stats['last_run'])): ?> · Ultima sincronizacion: <?php echo e($stats['last_run']); ?><?php endif; ?></p>
    </div>
    <div class="acciones_inline">
        <a class="btn_nuevo_cliente" href="scripts/scrape_habitaclia.py" target="_blank">Ver script Python</a>
        <a class="btn_secundario" href="docs/README_V1.8.md" target="_blank">Guia V1.8</a>
    </div>
</div>

<div class="form_panel">
    <h3>Filtrar propiedades</h3>
    <form method="GET" class="form_grid">
        <input type="hidden" name="seccion" value="busqueda-avanzada">
        <div class="campo_input">
            <label for="q">Texto libre</label>
            <input id="q" name="q" type="text" value="<?php echo e($filtros['q']); ?>" placeholder="Titulo, descripcion, zona...">
        </div>
        <div class="campo_input">
            <label for="zona">Zona / barrio</label>
            <input id="zona" name="zona" type="text" value="<?php echo e($filtros['zona']); ?>" placeholder="Ruzafa, El Carmen...">
        </div>
        <div class="campo_input">
            <label for="operacion">Operacion</label>
            <select id="operacion" name="operacion">
                <option value="">Cualquiera</option>
                <option value="venta" <?php echo $filtros['operacion'] === 'venta' ? 'selected' : ''; ?>>Venta</option>
                <option value="alquiler" <?php echo $filtros['operacion'] === 'alquiler' ? 'selected' : ''; ?>>Alquiler</option>
            </select>
        </div>
        <div class="campo_input">
            <label for="min_precio">Precio min (EUR)</label>
            <input id="min_precio" name="min_precio" type="number" min="0" step="500" value="<?php echo e((string) $filtros['min_precio']); ?>">
        </div>
        <div class="campo_input">
            <label for="max_precio">Precio max (EUR)</label>
            <input id="max_precio" name="max_precio" type="number" min="0" step="500" value="<?php echo e((string) $filtros['max_precio']); ?>">
        </div>
        <div class="campo_input">
            <label for="habitaciones">Min. habitaciones</label>
            <input id="habitaciones" name="habitaciones" type="number" min="0" step="1" value="<?php echo e((string) $filtros['habitaciones']); ?>">
        </div>
        <div class="campo_input">
            <label for="banos">Min. banos</label>
            <input id="banos" name="banos" type="number" min="0" step="1" value="<?php echo e((string) $filtros['banos']); ?>">
        </div>
        <div class="campo_input">
            <label for="orden">Orden</label>
            <select id="orden" name="orden">
                <option value="recientes" <?php echo $filtros['orden'] === 'recientes' ? 'selected' : ''; ?>>Mas recientes</option>
                <option value="precio_asc" <?php echo $filtros['orden'] === 'precio_asc' ? 'selected' : ''; ?>>Precio ascendente</option>
                <option value="precio_desc" <?php echo $filtros['orden'] === 'precio_desc' ? 'selected' : ''; ?>>Precio descendente</option>
                <option value="metros_desc" <?php echo $filtros['orden'] === 'metros_desc' ? 'selected' : ''; ?>>M2 descendente</option>
            </select>
        </div>
        <div class="acciones_inline">
            <button type="submit" class="btn_guardar">Buscar</button>
            <a class="btn_secundario" href="index.php?seccion=busqueda-avanzada">Limpiar</a>
        </div>
    </form>
</div>

<?php if (empty($propiedades)): ?>
    <div class="tarjeta_info">
        <p>No hay resultados con los filtros actuales.</p>
        <p>Ejecuta el scraper en <strong>scripts/scrape_habitaclia.py</strong> para poblar el cache o ajusta los filtros.</p>
    </div>
<?php else: ?>
    <div class="grid_cards">
        <?php foreach ($propiedades as $propiedad): ?>
            <article class="card_propiedad">
                <div class="card_media">
                    <span class="card_tag"><?php echo e($propiedad['operacion'] ?: 'Scraping'); ?></span>
                    <div class="card_media_placeholder">
                        <?php echo e($propiedad['tipo'] ?: 'Propiedad'); ?>
                    </div>
                </div>

                <div class="card_body">
                    <h3><?php echo e($propiedad['titulo']); ?></h3>
                    <p class="card_meta">
                        <?php echo e($propiedad['zona'] ?: $propiedad['ubicacion'] ?: $propiedad['ciudad']); ?> ·
                        <?php echo e((string) ($propiedad['metros'] ?? '')); ?> m2 ·
                        <?php echo e((string) ($propiedad['habitaciones'] ?? 0)); ?> hab ·
                        <?php echo e((string) ($propiedad['banos'] ?? 0)); ?> banos
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
