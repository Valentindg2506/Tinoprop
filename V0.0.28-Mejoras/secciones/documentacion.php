<?php
/*
 * Sección: Documentación
 * Rol: gestionar archivos y generar PDFs por plantillas para clientes/propiedades.
 * Incluye filtros de listados, paginación y formularios dinámicos con firma.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$clientes_stmt = $pdo->query("SELECT id, CONCAT(nombre, ' ', apellido) AS etiqueta FROM clientes ORDER BY nombre, apellido");
$clientes = $clientes_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$propiedades_stmt = $pdo->query("SELECT id, titulo FROM propiedades ORDER BY titulo");
$propiedades = $propiedades_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$clientes_data_stmt = $pdo->query("SELECT id, nombre, apellido, email, telefono, direccion, operacion, zona_interesada, presupuesto FROM clientes ORDER BY id");
$clientes_data_raw = $clientes_data_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$clientes_data = [];
foreach ($clientes_data_raw as $cliente_row) {
    $cliente_row['full_name'] = trim(($cliente_row['nombre'] ?? '') . ' ' . ($cliente_row['apellido'] ?? ''));
    $clientes_data[] = $cliente_row;
}

$propiedades_data_stmt = $pdo->query("SELECT id, titulo, tipo, ubicacion, direccion, precio, operacion, estado, referencia FROM propiedades ORDER BY id");
$propiedades_data = $propiedades_data_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* Recorre storage/documentacion y devuelve los últimos archivos por tipo (subidos/generados). */
function doc_collect_recent_files(string $kind, int $limit = 10): array
{
    $base = __DIR__ . '/../storage/documentacion';
    $kind_folder = $kind === 'generated' ? 'generados' : 'subidos';
    $entity_map = [
        'clientes' => 'cliente',
        'propiedades' => 'propiedad',
    ];

    $files = [];

    foreach ($entity_map as $folder => $entity_type) {
        $entity_root = $base . '/' . $folder;
        if (!is_dir($entity_root)) {
            continue;
        }

        $entity_ids = scandir($entity_root) ?: [];
        foreach ($entity_ids as $entity_id) {
            if ($entity_id === '.' || $entity_id === '..' || !ctype_digit((string) $entity_id)) {
                continue;
            }

            $kind_path = $entity_root . '/' . $entity_id . '/' . $kind_folder;
            if (!is_dir($kind_path)) {
                continue;
            }

            $entries = scandir($kind_path) ?: [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $full = $kind_path . '/' . $entry;
                if (!is_file($full)) {
                    continue;
                }

                if ($kind === 'generated' && str_ends_with(strtolower($entry), '.json')) {
                    continue;
                }

                $files[] = [
                    'name' => $entry,
                    'entity_type' => $entity_type,
                    'entity_id' => (int) $entity_id,
                    'modified' => (int) filemtime($full),
                    'size' => (int) filesize($full),
                    'download_url' => 'api/documentacion.php?action=download&entity_type=' . urlencode($entity_type) . '&entity_id=' . (int) $entity_id . '&kind=' . urlencode($kind) . '&file=' . urlencode($entry),
                ];
            }
        }
    }

    usort($files, static function (array $a, array $b): int {
        return $b['modified'] <=> $a['modified'];
    });

    return array_slice($files, 0, $limit);
}

/* Valida una fecha estricta en formato YYYY-MM-DD. */
function doc_is_valid_date(?string $value): bool
{
    if (!$value) {
        return false;
    }

    $d = DateTime::createFromFormat('Y-m-d', $value);
    return $d instanceof DateTime && $d->format('Y-m-d') === $value;
}

/* Aplica filtros por entidad y rango de fecha sobre una lista de archivos recientes. */
function doc_filter_recent_files(array $files, string $entity_filter, ?string $from_date, ?string $to_date): array
{
    $from_ts = $from_date ? strtotime($from_date . ' 00:00:00') : null;
    $to_ts = $to_date ? strtotime($to_date . ' 23:59:59') : null;

    return array_values(array_filter($files, static function (array $item) use ($entity_filter, $from_ts, $to_ts): bool {
        if ($entity_filter !== 'todos' && $item['entity_type'] !== $entity_filter) {
            return false;
        }

        if ($from_ts !== null && $item['modified'] < $from_ts) {
            return false;
        }

        if ($to_ts !== null && $item['modified'] > $to_ts) {
            return false;
        }

        return true;
    }));
}

/* Pagina una lista de archivos y devuelve metadatos de paginación para la UI. */
function doc_paginate(array $files, int $page, int $per_page): array
{
    $total_items = count($files);
    $total_pages = max(1, (int) ceil($total_items / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    return [
        'items' => array_slice($files, $offset, $per_page),
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'page' => $page,
    ];
}

/* Construye URLs conservando parámetros actuales y aplicando overrides. */
function doc_build_url(array $overrides = []): string
{
    $params = $_GET;
    $params['seccion'] = 'documentacion';
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
            continue;
        }
        $params[$k] = $v;
    }

    return '?' . http_build_query($params);
}

$doc_entity_filter = $_GET['doc_entity'] ?? 'todos';
if (!in_array($doc_entity_filter, ['todos', 'cliente', 'propiedad'], true)) {
    $doc_entity_filter = 'todos';
}

$doc_q = trim((string) ($_GET['doc_q'] ?? ''));

$doc_from_raw = $_GET['doc_from'] ?? '';
$doc_to_raw = $_GET['doc_to'] ?? '';
$doc_from = doc_is_valid_date($doc_from_raw) ? $doc_from_raw : '';
$doc_to = doc_is_valid_date($doc_to_raw) ? $doc_to_raw : '';

$doc_page_gen = max(1, (int) ($_GET['doc_pg_gen'] ?? 1));
$doc_page_up = max(1, (int) ($_GET['doc_pg_up'] ?? 1));
$doc_per_page = 6;

$ultimos_generados_all = doc_collect_recent_files('generated', 200);
$ultimos_guardados_all = doc_collect_recent_files('uploaded', 200);

$ultimos_generados_filtered = doc_filter_recent_files($ultimos_generados_all, $doc_entity_filter, $doc_from ?: null, $doc_to ?: null);
$ultimos_guardados_filtered = doc_filter_recent_files($ultimos_guardados_all, $doc_entity_filter, $doc_from ?: null, $doc_to ?: null);

if ($doc_q !== '') {
    $needle = mb_strtolower($doc_q);

    $ultimos_generados_filtered = array_values(array_filter($ultimos_generados_filtered, static function (array $item) use ($needle): bool {
        return str_contains(mb_strtolower((string) $item['name']), $needle);
    }));

    $ultimos_guardados_filtered = array_values(array_filter($ultimos_guardados_filtered, static function (array $item) use ($needle): bool {
        return str_contains(mb_strtolower((string) $item['name']), $needle);
    }));
}

$ultimos_generados_pag = doc_paginate($ultimos_generados_filtered, $doc_page_gen, $doc_per_page);
$ultimos_guardados_pag = doc_paginate($ultimos_guardados_filtered, $doc_page_up, $doc_per_page);

$ultimos_generados = $ultimos_generados_pag['items'];
$ultimos_guardados = $ultimos_guardados_pag['items'];

$plantillas = [
    [
        'key' => 'encargo_venta_exclusiva',
        'nombre' => 'Encargo de venta exclusiva',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'agencia', 'label' => 'Agencia'],
            ['name' => 'agente', 'label' => 'Agente responsable'],
            ['name' => 'propietario', 'label' => 'Propietario'],
            ['name' => 'dni_propietario', 'label' => 'DNI propietario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'precio_salida', 'label' => 'Precio de salida'],
            ['name' => 'duracion_meses', 'label' => 'Duración (meses)'],
        ],
    ],
    [
        'key' => 'encargo_venta_no_exclusiva',
        'nombre' => 'Encargo de venta no exclusiva',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'agencia', 'label' => 'Agencia'],
            ['name' => 'propietario', 'label' => 'Propietario'],
            ['name' => 'dni_propietario', 'label' => 'DNI propietario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'precio_salida', 'label' => 'Precio de salida'],
        ],
    ],
    [
        'key' => 'encargo_alquiler',
        'nombre' => 'Encargo de alquiler',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'arrendador', 'label' => 'Arrendador'],
            ['name' => 'dni_arrendador', 'label' => 'DNI arrendador'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'renta_mensual', 'label' => 'Renta mensual'],
            ['name' => 'fianza', 'label' => 'Fianza'],
        ],
    ],
    [
        'key' => 'contrato_arras',
        'nombre' => 'Contrato de arras',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'vendedor', 'label' => 'Vendedor'],
            ['name' => 'comprador', 'label' => 'Comprador'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'precio_total', 'label' => 'Precio total'],
            ['name' => 'importe_arras', 'label' => 'Importe arras'],
            ['name' => 'fecha_limite_escritura', 'label' => 'Fecha límite escritura'],
        ],
    ],
    [
        'key' => 'reserva_compra',
        'nombre' => 'Documento de reserva de compra',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'comprador', 'label' => 'Comprador'],
            ['name' => 'vendedor', 'label' => 'Vendedor'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'importe_reserva', 'label' => 'Importe reserva'],
            ['name' => 'fecha_vigencia', 'label' => 'Fecha de vigencia'],
        ],
    ],
    [
        'key' => 'oferta_compra',
        'nombre' => 'Oferta de compra',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'oferente', 'label' => 'Oferente'],
            ['name' => 'precio_ofertado', 'label' => 'Precio ofertado'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'validez_oferta', 'label' => 'Validez de la oferta'],
        ],
    ],
    [
        'key' => 'contrato_alquiler_habitual',
        'nombre' => 'Contrato alquiler vivienda habitual',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'arrendador', 'label' => 'Arrendador'],
            ['name' => 'arrendatario', 'label' => 'Arrendatario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'renta_mensual', 'label' => 'Renta mensual'],
            ['name' => 'duracion_contrato', 'label' => 'Duración contrato'],
            ['name' => 'fecha_inicio', 'label' => 'Fecha inicio'],
        ],
    ],
    [
        'key' => 'contrato_alquiler_temporal',
        'nombre' => 'Contrato alquiler temporal',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'arrendador', 'label' => 'Arrendador'],
            ['name' => 'arrendatario', 'label' => 'Arrendatario'],
            ['name' => 'motivo_temporalidad', 'label' => 'Motivo de temporalidad'],
            ['name' => 'fecha_inicio', 'label' => 'Fecha inicio'],
            ['name' => 'fecha_fin', 'label' => 'Fecha fin'],
        ],
    ],
    [
        'key' => 'inventario_anexo',
        'nombre' => 'Inventario y anexo de mobiliario',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'estado_general', 'label' => 'Estado general'],
            ['name' => 'electrodomesticos', 'label' => 'Electrodomésticos'],
            ['name' => 'observaciones', 'label' => 'Observaciones'],
        ],
    ],
    [
        'key' => 'parte_visita',
        'nombre' => 'Parte de visita',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'agente', 'label' => 'Agente'],
            ['name' => 'cliente', 'label' => 'Cliente'],
            ['name' => 'direccion_inmueble', 'label' => 'Inmueble visitado'],
            ['name' => 'fecha_visita', 'label' => 'Fecha de visita'],
            ['name' => 'hora_visita', 'label' => 'Hora de visita'],
        ],
    ],
    [
        'key' => 'autorizacion_visitas',
        'nombre' => 'Autorización de visitas',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'propietario', 'label' => 'Propietario'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'franja_horaria', 'label' => 'Franja horaria permitida'],
        ],
    ],
    [
        'key' => 'consentimiento_rgpd',
        'nombre' => 'Consentimiento RGPD',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'interesado', 'label' => 'Interesado'],
            ['name' => 'dni', 'label' => 'DNI'],
            ['name' => 'finalidad', 'label' => 'Finalidad del tratamiento'],
        ],
    ],
    [
        'key' => 'encargo_busqueda_comprador',
        'nombre' => 'Encargo de búsqueda de comprador (Personal Shopper)',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'cliente', 'label' => 'Cliente'],
            ['name' => 'presupuesto_maximo', 'label' => 'Presupuesto máximo'],
            ['name' => 'zonas', 'label' => 'Zonas de interés'],
            ['name' => 'honorarios', 'label' => 'Honorarios'],
        ],
    ],
    [
        'key' => 'acta_entrega_llaves',
        'nombre' => 'Acta de entrega de llaves',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'entrega_a', 'label' => 'Entrega a'],
            ['name' => 'entrega_de', 'label' => 'Entrega de'],
            ['name' => 'direccion_inmueble', 'label' => 'Dirección inmueble'],
            ['name' => 'numero_juegos', 'label' => 'Número de juegos de llaves'],
            ['name' => 'fecha_entrega', 'label' => 'Fecha entrega'],
        ],
    ],
    [
        'key' => 'mandato_sepa',
        'nombre' => 'Mandato SEPA',
        'requiere_firma' => true,
        'campos' => [
            ['name' => 'titular_cuenta', 'label' => 'Titular cuenta'],
            ['name' => 'iban', 'label' => 'IBAN'],
            ['name' => 'acreedor', 'label' => 'Acreedor'],
            ['name' => 'referencia_mandato', 'label' => 'Referencia mandato'],
        ],
    ],
];
?>

<div class="encabezado_seccion">
</div>

<div class="doc_intro" id="doc_intro">
    <article class="doc_mode_card">
        <div class="doc_mode_icon">📄</div>
        <h3>Generar documentación</h3>
        <p>Usa plantillas del sector inmobiliario en España, completa datos, firma y descarga PDF. Siempre se guarda copia.</p>
        <button type="button" class="btn_guardar" id="btn_ir_generar">Ir a generar</button>
    </article>

    <article class="doc_mode_card">
        <div class="doc_mode_icon">📎</div>
        <h3>Subir documentación</h3>
        <p>Sube PDF o fotos a cliente o propiedad, con separación estricta por carpetas para que no se mezclen.</p>
        <button type="button" class="btn_guardar" id="btn_ir_subir">Ir a subir</button>
    </article>
</div>

<article class="tarjeta_info doc_recent_card" id="doc_recentes">
    <h3>Últimos documentos</h3>
    <p>Resumen rápido de la actividad reciente en documentación.</p>

    <form method="GET" class="doc_filter_bar">
        <input type="hidden" name="seccion" value="documentacion">
        <input type="hidden" name="doc_pg_gen" value="1">
        <input type="hidden" name="doc_pg_up" value="1">

        <div class="doc_filter_item">
            <label for="doc_entity">Entidad</label>
            <select id="doc_entity" name="doc_entity">
                <option value="todos" <?php echo $doc_entity_filter === 'todos' ? 'selected' : ''; ?>>Todas</option>
                <option value="cliente" <?php echo $doc_entity_filter === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                <option value="propiedad" <?php echo $doc_entity_filter === 'propiedad' ? 'selected' : ''; ?>>Propiedad</option>
            </select>
        </div>

        <div class="doc_filter_item">
            <label for="doc_from">Desde</label>
            <input type="date" id="doc_from" name="doc_from" value="<?php echo e($doc_from); ?>">
        </div>

        <div class="doc_filter_item">
            <label for="doc_to">Hasta</label>
            <input type="date" id="doc_to" name="doc_to" value="<?php echo e($doc_to); ?>">
        </div>

        <div class="doc_filter_item doc_filter_search">
            <label for="doc_q">Buscar archivo (tiempo real)</label>
            <input type="text" id="doc_q" name="doc_q" value="<?php echo e($doc_q); ?>" placeholder="Ej: contrato, sepa, arras...">
        </div>

        <div class="doc_filter_actions">
            <button type="submit" class="btn_guardar">Filtrar</button>
            <a class="btn_secundario" href="<?php echo e(doc_build_url(['doc_entity' => null, 'doc_from' => null, 'doc_to' => null, 'doc_q' => null, 'doc_pg_gen' => 1, 'doc_pg_up' => 1])); ?>">Limpiar</a>
        </div>
    </form>

    <div class="doc_recent_grid">
        <div>
            <h4>Últimos generados</h4>
            <div class="doc_table_wrap">
                <table class="doc_table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Entidad</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ultimos_generados): ?>
                            <tr>
                                <td colspan="3">Sin documentos generados aún.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimos_generados as $item): ?>
                                <tr class="doc_row_item" data-file-name="<?php echo e(mb_strtolower($item['name'])); ?>">
                                    <td><?php echo e(date('d/m/Y H:i', $item['modified'])); ?></td>
                                    <td><?php echo e(ucfirst($item['entity_type']) . ' #' . $item['entity_id']); ?></td>
                                    <td>
                                        <a href="<?php echo e($item['download_url']); ?>" target="_blank"><?php echo e($item['name']); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="doc_empty_state oculto">
                            <td colspan="3">No hay resultados para la búsqueda actual.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="doc_pagination">
                <span>Página <?php echo (int) $ultimos_generados_pag['page']; ?> de <?php echo (int) $ultimos_generados_pag['total_pages']; ?> · <?php echo (int) $ultimos_generados_pag['total_items']; ?> docs</span>
                <div class="doc_pagination_actions">
                    <?php $prev_gen = max(1, (int) $ultimos_generados_pag['page'] - 1); ?>
                    <?php $next_gen = min((int) $ultimos_generados_pag['total_pages'], (int) $ultimos_generados_pag['page'] + 1); ?>
                    <a class="btn_secundario <?php echo $ultimos_generados_pag['page'] <= 1 ? 'disabled' : ''; ?>" href="<?php echo e(doc_build_url(['doc_pg_gen' => $prev_gen])); ?>">Anterior</a>
                    <a class="btn_secundario <?php echo $ultimos_generados_pag['page'] >= $ultimos_generados_pag['total_pages'] ? 'disabled' : ''; ?>" href="<?php echo e(doc_build_url(['doc_pg_gen' => $next_gen])); ?>">Siguiente</a>
                </div>
            </div>
        </div>

        <div>
            <h4>Últimos guardados (subidos)</h4>
            <div class="doc_table_wrap">
                <table class="doc_table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Entidad</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ultimos_guardados): ?>
                            <tr>
                                <td colspan="3">Sin documentos guardados aún.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimos_guardados as $item): ?>
                                <tr class="doc_row_item" data-file-name="<?php echo e(mb_strtolower($item['name'])); ?>">
                                    <td><?php echo e(date('d/m/Y H:i', $item['modified'])); ?></td>
                                    <td><?php echo e(ucfirst($item['entity_type']) . ' #' . $item['entity_id']); ?></td>
                                    <td>
                                        <a href="<?php echo e($item['download_url']); ?>" target="_blank"><?php echo e($item['name']); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="doc_empty_state oculto">
                            <td colspan="3">No hay resultados para la búsqueda actual.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="doc_pagination">
                <span>Página <?php echo (int) $ultimos_guardados_pag['page']; ?> de <?php echo (int) $ultimos_guardados_pag['total_pages']; ?> · <?php echo (int) $ultimos_guardados_pag['total_items']; ?> docs</span>
                <div class="doc_pagination_actions">
                    <?php $prev_up = max(1, (int) $ultimos_guardados_pag['page'] - 1); ?>
                    <?php $next_up = min((int) $ultimos_guardados_pag['total_pages'], (int) $ultimos_guardados_pag['page'] + 1); ?>
                    <a class="btn_secundario <?php echo $ultimos_guardados_pag['page'] <= 1 ? 'disabled' : ''; ?>" href="<?php echo e(doc_build_url(['doc_pg_up' => $prev_up])); ?>">Anterior</a>
                    <a class="btn_secundario <?php echo $ultimos_guardados_pag['page'] >= $ultimos_guardados_pag['total_pages'] ? 'disabled' : ''; ?>" href="<?php echo e(doc_build_url(['doc_pg_up' => $next_up])); ?>">Siguiente</a>
                </div>
            </div>
        </div>
    </div>
</article>

<section class="doc_panel oculto" id="panel-subidas">
    <div class="doc_panel_head">
        <h3>Subir documentación</h3>
        <button type="button" class="btn_secundario" id="btn_volver_subidas">← Volver</button>
    </div>
    <div class="doc_grid">
        <article class="tarjeta_info doc_card">
            <h3>Subir documentación por entidad</h3>
            <p>Los archivos se guardan por separado en carpetas de clientes o propiedades para evitar mezcla.</p>

            <form id="formSubida" enctype="multipart/form-data" class="doc_form">
                <div class="doc_row">
                    <label for="upload_entity_type">Tipo de entidad</label>
                    <select id="upload_entity_type" name="entity_type">
                        <option value="cliente">Cliente</option>
                        <option value="propiedad">Propiedad</option>
                    </select>
                </div>

                <div class="doc_row" id="wrap_upload_cliente">
                    <label for="upload_cliente_id">Cliente</label>
                    <select id="upload_cliente_id" name="cliente_id">
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo (int) $cliente['id']; ?>"><?php echo e($cliente['etiqueta']); ?> (ID <?php echo (int) $cliente['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="doc_row oculto" id="wrap_upload_propiedad">
                    <label for="upload_propiedad_id">Propiedad</label>
                    <select id="upload_propiedad_id" name="propiedad_id">
                        <?php foreach ($propiedades as $propiedad): ?>
                            <option value="<?php echo (int) $propiedad['id']; ?>"><?php echo e($propiedad['titulo']); ?> (ID <?php echo (int) $propiedad['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="doc_row">
                    <label for="documento">Archivo (PDF o imagen)</label>
                    <input type="file" id="documento" name="documento" accept="application/pdf,image/*" required>
                </div>

                <button type="submit" class="btn_guardar">Subir archivo</button>
                <p class="doc_feedback" id="upload_feedback"></p>
            </form>
        </article>

        <article class="tarjeta_info doc_card">
            <h3>Archivos guardados</h3>
            <p>Se listan los ficheros subidos y los PDF generados para la entidad seleccionada.</p>
            <button type="button" class="btn_nuevo_cliente" id="btn_refrescar_listado">Refrescar</button>
            <div class="doc_listados">
                <h4>Subidos</h4>
                <ul id="lista_subidos" class="doc_lista"></ul>
                <h4>Generados</h4>
                <ul id="lista_generados" class="doc_lista"></ul>
            </div>
        </article>
    </div>
</section>

<section class="doc_panel oculto" id="panel-plantillas">
    <div class="doc_panel_head">
        <h3>Generar documentación</h3>
        <button type="button" class="btn_secundario" id="btn_volver_plantillas">← Volver</button>
    </div>
    <article class="tarjeta_info doc_card">
        <h3>Plantillas inmobiliarias España</h3>
        <p>Completa los campos, firma cuando aplique y genera el PDF. Se descarga y además se guarda una copia automática.</p>

        <form id="formPlantilla" class="doc_form">
            <div class="doc_row">
                <label for="tpl_entity_type">Tipo de entidad vinculada</label>
                <select id="tpl_entity_type">
                    <option value="cliente">Cliente</option>
                    <option value="propiedad">Propiedad</option>
                </select>
            </div>

            <div class="doc_row" id="wrap_tpl_cliente">
                <label for="tpl_cliente_id">Cliente</label>
                <select id="tpl_cliente_id">
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?php echo (int) $cliente['id']; ?>"><?php echo e($cliente['etiqueta']); ?> (ID <?php echo (int) $cliente['id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="doc_row oculto" id="wrap_tpl_propiedad">
                <label for="tpl_propiedad_id">Propiedad</label>
                <select id="tpl_propiedad_id">
                    <?php foreach ($propiedades as $propiedad): ?>
                        <option value="<?php echo (int) $propiedad['id']; ?>"><?php echo e($propiedad['titulo']); ?> (ID <?php echo (int) $propiedad['id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="doc_row">
                <label for="template_key">Plantilla</label>
                <select id="template_key"></select>
            </div>

            <div id="template_fields" class="doc_fields"></div>

            <div class="doc_actions_inline">
                <button type="button" class="btn_nuevo_cliente" id="btn_autocompletar">Autocompletar desde entidad</button>
                <button type="button" class="btn_secundario" id="btn_preview_pdf">Vista previa PDF</button>
            </div>

            <div class="doc_signature" id="firma_wrap">
                <label>Firma (si aplica)</label>
                <canvas id="firma_canvas" width="560" height="180"></canvas>
                <div class="doc_signature_actions">
                    <button type="button" class="btn_secundario" id="firma_limpiar">Limpiar firma</button>
                </div>
            </div>

            <button type="submit" class="btn_guardar">Generar y guardar PDF</button>
            <p class="doc_feedback" id="pdf_feedback"></p>
        </form>
    </article>
</section>

<div class="modal_overlay" id="docPreviewModal" aria-hidden="true">
    <div class="modal_contenido doc_preview_modal" role="dialog" aria-modal="true" aria-labelledby="docPreviewTitle">
        <h3 id="docPreviewTitle">Vista previa del PDF</h3>
        <p>Revisa el documento antes de guardarlo y descargarlo.</p>
        <iframe id="docPreviewFrame" title="Vista previa PDF"></iframe>
        <div class="modal_acciones">
            <button type="button" class="btn_secundario" id="docPreviewCancelar">Cancelar</button>
            <button type="button" class="btn_guardar" id="docPreviewConfirmar">Guardar y descargar</button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// Índice JS del módulo:
// abrirPanelSubidas/abrirPanelPlantillas/volverInicioDocumentacion -> navegación de paneles.
// syncUploadEntityUI/entidadSeleccionadaUpload/cargarListados -> gestión de listado de archivos.
// syncTemplateEntityUI/plantillaActiva/renderCamposPlantilla -> flujo de plantillas PDF.
// coordFirma/firmaStart/firmaMove/firmaEnd/tieneFirma -> captura y validación de firma.
// clienteActualData/propiedadActualData/sugerenciaPorCampo/autocompletarCamposPlantilla -> autocompletado.
// construirDocumentoPdf/cerrarPreview -> preview y cierre de modal PDF.

const plantillas = <?php echo json_encode($plantillas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const clientesData = <?php echo json_encode($clientes_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const propiedadesData = <?php echo json_encode($propiedades_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

const docIntro = document.getElementById('doc_intro');
const panelSubidas = document.getElementById('panel-subidas');
const panelPlantillas = document.getElementById('panel-plantillas');

// Muestra el panel de subida de archivos y recarga los listados del contexto actual.
function abrirPanelSubidas() {
    docIntro.classList.add('oculto');
    panelPlantillas.classList.add('oculto');
    panelSubidas.classList.remove('oculto');
    cargarListados();
}

// Muestra el panel de generación de documentos por plantilla.
function abrirPanelPlantillas() {
    docIntro.classList.add('oculto');
    panelSubidas.classList.add('oculto');
    panelPlantillas.classList.remove('oculto');
}

// Vuelve al bloque inicial con las dos acciones principales del módulo.
function volverInicioDocumentacion() {
    panelSubidas.classList.add('oculto');
    panelPlantillas.classList.add('oculto');
    docIntro.classList.remove('oculto');
}

document.getElementById('btn_ir_subir').addEventListener('click', abrirPanelSubidas);
document.getElementById('btn_ir_generar').addEventListener('click', abrirPanelPlantillas);
document.getElementById('btn_volver_subidas').addEventListener('click', volverInicioDocumentacion);
document.getElementById('btn_volver_plantillas').addEventListener('click', volverInicioDocumentacion);

const uploadEntityType = document.getElementById('upload_entity_type');
const wrapUploadCliente = document.getElementById('wrap_upload_cliente');
const wrapUploadPropiedad = document.getElementById('wrap_upload_propiedad');

// Alterna selectores de cliente/propiedad según entidad elegida para subida.
function syncUploadEntityUI() {
    const isCliente = uploadEntityType.value === 'cliente';
    wrapUploadCliente.classList.toggle('oculto', !isCliente);
    wrapUploadPropiedad.classList.toggle('oculto', isCliente);
}

uploadEntityType.addEventListener('change', () => {
    syncUploadEntityUI();
    cargarListados();
});
syncUploadEntityUI();

// Devuelve entidad e id actualmente seleccionados en el formulario de subida.
function entidadSeleccionadaUpload() {
    const entityType = uploadEntityType.value;
    const entityId = entityType === 'cliente'
        ? document.getElementById('upload_cliente_id').value
        : document.getElementById('upload_propiedad_id').value;
    return { entityType, entityId };
}

document.getElementById('upload_cliente_id').addEventListener('change', cargarListados);
document.getElementById('upload_propiedad_id').addEventListener('change', cargarListados);
document.getElementById('btn_refrescar_listado').addEventListener('click', cargarListados);

// Consulta la API de documentación y renderiza listas de subidos y generados.
async function cargarListados() {
    const { entityType, entityId } = entidadSeleccionadaUpload();
    const url = `api/documentacion.php?action=list_files&entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`;

    const res = await fetch(url);
    const data = await res.json();

    const subidos = document.getElementById('lista_subidos');
    const generados = document.getElementById('lista_generados');
    subidos.innerHTML = '';
    generados.innerHTML = '';

    if (!data.success) {
        subidos.innerHTML = '<li>Error cargando archivos.</li>';
        generados.innerHTML = '<li>Error cargando archivos.</li>';
        return;
    }

    const renderLista = (lista, nodo) => {
        if (!lista.length) {
            nodo.innerHTML = '<li>Sin archivos.</li>';
            return;
        }

        lista.forEach((item) => {
            const li = document.createElement('li');
            li.innerHTML = `<a href="${item.download_url}" target="_blank">${item.name}</a> <span>(${item.size_label})</span>`;
            nodo.appendChild(li);
        });
    };

    renderLista(data.uploaded, subidos);
    renderLista(data.generated, generados);
}

document.getElementById('formSubida').addEventListener('submit', async (event) => {
    event.preventDefault();

    const feedback = document.getElementById('upload_feedback');
    feedback.textContent = 'Subiendo...';

    const { entityType, entityId } = entidadSeleccionadaUpload();
    const formData = new FormData(event.target);
    formData.append('entity_type', entityType);
    formData.append('entity_id', entityId);

    const res = await fetch('api/documentacion.php?action=upload&_csrf_token=<?php echo csrf_token(); ?>', {
        method: 'POST',
        body: formData,
    });

    const data = await res.json();
    feedback.textContent = data.message || 'Operación completada.';

    if (data.success) {
        event.target.reset();
        syncUploadEntityUI();
        await cargarListados();
    }
});

const tplEntityType = document.getElementById('tpl_entity_type');
const wrapTplCliente = document.getElementById('wrap_tpl_cliente');
const wrapTplPropiedad = document.getElementById('wrap_tpl_propiedad');
const templateSelect = document.getElementById('template_key');
const templateFields = document.getElementById('template_fields');
const firmaWrap = document.getElementById('firma_wrap');
const btnPreviewPdf = document.getElementById('btn_preview_pdf');
const docPreviewModal = document.getElementById('docPreviewModal');
const docPreviewFrame = document.getElementById('docPreviewFrame');
const docPreviewCancelar = document.getElementById('docPreviewCancelar');
const docPreviewConfirmar = document.getElementById('docPreviewConfirmar');

let pendingPdfDataUri = null;
let pendingTpl = null;
let pendingEntity = null;
let pendingValues = null;

// Alterna selectores de entidad para la sección de plantillas.
function syncTemplateEntityUI() {
    const isCliente = tplEntityType.value === 'cliente';
    wrapTplCliente.classList.toggle('oculto', !isCliente);
    wrapTplPropiedad.classList.toggle('oculto', isCliente);
}

tplEntityType.addEventListener('change', syncTemplateEntityUI);
syncTemplateEntityUI();

plantillas.forEach((tpl) => {
    const option = document.createElement('option');
    option.value = tpl.key;
    option.textContent = tpl.nombre;
    templateSelect.appendChild(option);
});

// Devuelve la plantilla actualmente seleccionada en el selector.
function plantillaActiva() {
    return plantillas.find((tpl) => tpl.key === templateSelect.value) || plantillas[0];
}

// Dibuja dinámicamente inputs de la plantilla activa y prepara firma/autocompletado.
function renderCamposPlantilla() {
    const tpl = plantillaActiva();
    templateFields.innerHTML = '';

    tpl.campos.forEach((campo) => {
        const row = document.createElement('div');
        row.className = 'doc_row';
        row.innerHTML = `
            <label for="campo_${campo.name}">${campo.label}</label>
            <input type="text" id="campo_${campo.name}" name="${campo.name}" required>
        `;
        templateFields.appendChild(row);
    });

    firmaWrap.classList.toggle('oculto', !tpl.requiere_firma);

    autocompletarCamposPlantilla();
}

templateSelect.addEventListener('change', renderCamposPlantilla);
renderCamposPlantilla();

const firmaCanvas = document.getElementById('firma_canvas');
const firmaCtx = firmaCanvas.getContext('2d');
let firmando = false;

// Convierte coordenadas de mouse/touch al sistema de coordenadas del canvas de firma.
function coordFirma(event) {
    const rect = firmaCanvas.getBoundingClientRect();
    const touch = event.touches ? event.touches[0] : event;
    return {
        x: touch.clientX - rect.left,
        y: touch.clientY - rect.top,
    };
}

// Inicia trazo de firma (mousedown/touchstart).
function firmaStart(event) {
    firmando = true;
    const p = coordFirma(event);
    firmaCtx.beginPath();
    firmaCtx.moveTo(p.x, p.y);
}

// Continúa el trazo de firma mientras el usuario arrastra.
function firmaMove(event) {
    if (!firmando) return;
    event.preventDefault();
    const p = coordFirma(event);
    firmaCtx.lineWidth = 2;
    firmaCtx.lineCap = 'round';
    firmaCtx.strokeStyle = '#1f2937';
    firmaCtx.lineTo(p.x, p.y);
    firmaCtx.stroke();
}

// Finaliza la firma (mouseup/touchend).
function firmaEnd() {
    firmando = false;
}

firmaCanvas.addEventListener('mousedown', firmaStart);
firmaCanvas.addEventListener('mousemove', firmaMove);
window.addEventListener('mouseup', firmaEnd);
firmaCanvas.addEventListener('touchstart', firmaStart, { passive: false });
firmaCanvas.addEventListener('touchmove', firmaMove, { passive: false });
window.addEventListener('touchend', firmaEnd);

document.getElementById('firma_limpiar').addEventListener('click', () => {
    firmaCtx.clearRect(0, 0, firmaCanvas.width, firmaCanvas.height);
});

// Devuelve entidad e id seleccionados para la generación de plantilla.
function entidadSeleccionadaPlantilla() {
    const entityType = tplEntityType.value;
    const entityId = entityType === 'cliente'
        ? document.getElementById('tpl_cliente_id').value
        : document.getElementById('tpl_propiedad_id').value;
    return { entityType, entityId };
}

// Obtiene el objeto de datos del cliente seleccionado para autocompletar campos.
function clienteActualData() {
    const id = Number(document.getElementById('tpl_cliente_id').value || 0);
    return clientesData.find((item) => Number(item.id) === id) || null;
}

// Obtiene el objeto de datos de la propiedad seleccionada para autocompletar campos.
function propiedadActualData() {
    const id = Number(document.getElementById('tpl_propiedad_id').value || 0);
    return propiedadesData.find((item) => Number(item.id) === id) || null;
}

// Genera sugerencias por nombre de campo usando datos de cliente/propiedad/contexto.
function sugerenciaPorCampo(nombreCampo, cliente, propiedad) {
    const campo = nombreCampo.toLowerCase();

    if (campo.includes('direccion_inmueble') || campo.includes('inmueble')) {
        return (propiedad?.direccion || propiedad?.ubicacion || cliente?.direccion || '').toString();
    }

    if (campo.includes('precio') || campo.includes('renta') || campo.includes('presupuesto') || campo.includes('importe')) {
        if (propiedad?.precio) {
            return String(propiedad.precio);
        }
        if (cliente?.presupuesto) {
            return String(cliente.presupuesto);
        }
    }

    if (campo.includes('propietario') || campo.includes('vendedor') || campo.includes('arrendador') || campo.includes('entrega_de')) {
        return (cliente?.full_name || '').toString();
    }

    if (campo.includes('comprador') || campo.includes('cliente') || campo.includes('arrendatario') || campo.includes('interesado') || campo.includes('oferente') || campo.includes('entrega_a')) {
        return (cliente?.full_name || '').toString();
    }

    if (campo.includes('agente')) {
        return '<?php echo e($_SESSION['usuario']['nombre'] ?? 'Agente'); ?>';
    }

    if (campo.includes('agencia')) {
        return 'TinoProp';
    }

    if (campo.includes('operacion')) {
        return (propiedad?.operacion || cliente?.operacion || '').toString();
    }

    if (campo.includes('referencia')) {
        return (propiedad?.referencia || '').toString();
    }

    if (campo.includes('zona')) {
        return (cliente?.zona_interesada || propiedad?.ubicacion || '').toString();
    }

    return '';
}

// Rellena automáticamente los campos vacíos con sugerencias contextuales.
function autocompletarCamposPlantilla() {
    const { entityType } = entidadSeleccionadaPlantilla();
    const cliente = entityType === 'cliente' ? clienteActualData() : null;
    const propiedad = entityType === 'propiedad' ? propiedadActualData() : null;

    const inputs = templateFields.querySelectorAll('input[name]');
    inputs.forEach((input) => {
        if (input.value.trim() !== '') {
            return;
        }

        const sugerencia = sugerenciaPorCampo(input.name, cliente, propiedad);
        if (sugerencia) {
            input.value = sugerencia;
        }
    });
}

document.getElementById('btn_autocompletar').addEventListener('click', autocompletarCamposPlantilla);
document.getElementById('tpl_cliente_id').addEventListener('change', autocompletarCamposPlantilla);
document.getElementById('tpl_propiedad_id').addEventListener('change', autocompletarCamposPlantilla);
tplEntityType.addEventListener('change', autocompletarCamposPlantilla);

// Comprueba si el canvas contiene trazo (firma) revisando canal alpha.
function tieneFirma() {
    const pixels = firmaCtx.getImageData(0, 0, firmaCanvas.width, firmaCanvas.height).data;
    for (let i = 3; i < pixels.length; i += 4) {
        if (pixels[i] !== 0) return true;
    }
    return false;
}

// Construye el PDF en memoria validando campos requeridos y firma cuando aplica.
function construirDocumentoPdf() {
    const tpl = plantillaActiva();
    const form = document.getElementById('formPlantilla');
    const formData = new FormData(form);
    const { entityType, entityId } = entidadSeleccionadaPlantilla();
    const values = {};

    tpl.campos.forEach((campo) => {
        values[campo.name] = (formData.get(campo.name) || '').toString().trim();
    });

    for (const campo of tpl.campos) {
        if (!values[campo.name]) {
            return { ok: false, message: `Completa el campo: ${campo.label}` };
        }
    }

    if (tpl.requiere_firma && !tieneFirma()) {
        return { ok: false, message: 'La plantilla requiere firma.' };
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const fecha = new Date().toLocaleDateString('es-ES');

    doc.setFontSize(16);
    doc.text(tpl.nombre, 14, 20);
    doc.setFontSize(10);
    doc.text(`Fecha: ${fecha}`, 14, 28);
    doc.text(`Entidad: ${entityType} #${entityId}`, 14, 34);

    let y = 46;
    doc.setFontSize(12);
    tpl.campos.forEach((campo) => {
        const value = values[campo.name] || '-';
        const line = `${campo.label}: ${value}`;
        const lines = doc.splitTextToSize(line, 180);
        if (y > 260) {
            doc.addPage();
            y = 20;
        }
        doc.text(lines, 14, y);
        y += (lines.length * 7) + 3;
    });

    if (tpl.requiere_firma && tieneFirma()) {
        const firmaData = firmaCanvas.toDataURL('image/png');
        if (y > 220) {
            doc.addPage();
            y = 20;
        }
        doc.text('Firma:', 14, y);
        y += 4;
        doc.addImage(firmaData, 'PNG', 14, y, 70, 25);
    }

    const pdfDataUri = doc.output('datauristring');
    return {
        ok: true,
        tpl,
        entityType,
        entityId,
        values,
        pdfDataUri,
        doc,
    };
}

btnPreviewPdf.addEventListener('click', () => {
    const feedback = document.getElementById('pdf_feedback');
    const built = construirDocumentoPdf();
    if (!built.ok) {
        feedback.textContent = built.message;
        return;
    }

    pendingPdfDataUri = built.pdfDataUri;
    pendingTpl = built.tpl;
    pendingEntity = { entityType: built.entityType, entityId: built.entityId };
    pendingValues = built.values;

    docPreviewFrame.src = pendingPdfDataUri;
    docPreviewModal.classList.add('modal_visible');
    docPreviewModal.setAttribute('aria-hidden', 'false');
    feedback.textContent = 'Vista previa generada.';
});

// Cierra el modal de vista previa y limpia el iframe.
function cerrarPreview() {
    docPreviewModal.classList.remove('modal_visible');
    docPreviewModal.setAttribute('aria-hidden', 'true');
    docPreviewFrame.src = 'about:blank';
}

docPreviewCancelar.addEventListener('click', cerrarPreview);
docPreviewModal.addEventListener('click', (event) => {
    if (event.target === docPreviewModal) {
        cerrarPreview();
    }
});

const docSearchInput = document.getElementById('doc_q');
if (docSearchInput) {
    const aplicarBusquedaTiempoReal = () => {
        const value = docSearchInput.value.trim().toLowerCase();
        const tables = document.querySelectorAll('.doc_table');

        tables.forEach((table) => {
            const rows = table.querySelectorAll('tbody tr.doc_row_item');
            const emptyRow = table.querySelector('tbody tr.doc_empty_state');
            let visibles = 0;

            rows.forEach((row) => {
                const name = (row.getAttribute('data-file-name') || '').toLowerCase();
                const visible = value === '' || name.includes(value);
                row.classList.toggle('oculto', !visible);
                if (visible) {
                    visibles += 1;
                }
            });

            if (emptyRow) {
                emptyRow.classList.toggle('oculto', visibles > 0);
            }
        });
    };

    docSearchInput.addEventListener('input', aplicarBusquedaTiempoReal);
    aplicarBusquedaTiempoReal();
}

docPreviewConfirmar.addEventListener('click', async () => {
    const feedback = document.getElementById('pdf_feedback');

    if (!pendingPdfDataUri || !pendingTpl || !pendingEntity || !pendingValues) {
        feedback.textContent = 'No hay vista previa preparada.';
        return;
    }

    feedback.textContent = 'Guardando PDF...';

    const saveRes = await fetch('api/documentacion.php?action=save_pdf', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo csrf_token(); ?>' },
        body: JSON.stringify({
            entity_type: pendingEntity.entityType,
            entity_id: pendingEntity.entityId,
            template_key: pendingTpl.key,
            template_name: pendingTpl.nombre,
            fields: pendingValues,
            pdf_base64: pendingPdfDataUri,
        }),
    });

    const saveData = await saveRes.json();
    if (!saveData.success) {
        feedback.textContent = saveData.message || 'Error guardando copia.';
        return;
    }

    const link = document.createElement('a');
    link.href = pendingPdfDataUri;
    link.download = saveData.download_name || `${pendingTpl.key}.pdf`;
    document.body.appendChild(link);
    link.click();
    link.remove();

    cerrarPreview();
    feedback.textContent = 'PDF guardado y descargado correctamente.';
});

document.getElementById('formPlantilla').addEventListener('submit', async (event) => {
    event.preventDefault();
    const feedback = document.getElementById('pdf_feedback');
    feedback.textContent = 'Usa “Vista previa PDF” y confirma para guardar/descargar.';
});

</script>
