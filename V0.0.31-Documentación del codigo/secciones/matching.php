<?php
/**
 * ============================================================================
 * MATCHING AUTOMÁTICO - CRM TINOPROP
 * ============================================================================
 *
 * ¿Qué es este archivo?
 * ---------------------
 * Muestra un listado de coincidencias automáticas entre COMPRADORES
 * y PROPIEDADES disponibles.
 *
 * ¿Cómo funciona el matching?
 * --------------------------
 * El sistema compara los criterios de búsqueda de cada comprador
 * (zona de interés y presupuesto máximo) con las propiedades disponibles.
 * Si una propiedad está en la zona que busca el comprador y su precio
 * está dentro (o cerca) de su presupuesto, se muestra como coincidencia.
 *
 * Ejemplo práctico:
 * - Comprador: Juan García busca piso en "Centro" con presupuesto 200.000€
 * - Propiedad: Piso en Centro, 180.000€ → ✅ Dentro del presupuesto (margen 11%)
 * - Propiedad: Piso en Centro, 220.000€ → ⚠️ Excede presupuesto (10%)
 *
 * ¿Por qué es útil?
 * ----------------
 * Ahorra tiempo al agente: en lugar de buscar manualmente qué
 * propiedades podrían interesar a cada comprador, el sistema
 * lo hace automáticamente. El agente solo tiene que revisar
 * las sugerencias y contactar al cliente.
 *
 * Vista:
 * - Se agrupan las coincidencias por comprador.
 * - Cada comprador muestra sus datos básicos y un enlace a su ficha.
 * - Debajo, una cuadrícula con las propiedades que coinciden,
 *   indicando si están dentro del presupuesto o lo exceden.
 *
 * @archivo  secciones/matching.php
 * @proyecto TinoProp - CRM Inmobiliario
 * @version  V0.0.31 Pre-Producción
 */
require_once __DIR__ . '/../inc/bootstrap.php';

/*
 * OBTENCIÓN DE DATOS DE MATCHING
 * matching_buscar() es una función helper que ejecuta la lógica de
 * comparación en la base de datos. El parámetro 30 es el límite
 * de compradores a evaluar (para evitar sobrecargar el servidor).
 *
 * Cada elemento de $matches contiene:
 * - 'comprador': datos del cliente comprador (nombre, zona, presupuesto)
 * - 'propiedades': array de propiedades que coinciden con sus criterios
 * - 'coincidencias': número total de propiedades coincidentes
 */
$pdo = db();
$matches = matching_buscar($pdo, 30);
$mensaje_exito = flash_get('success');

/* Contamos el total de coincidencias para mostrarlo en la cabecera */
$total_matches = 0;
foreach ($matches as $m) $total_matches += $m['coincidencias'];
?>

<div class="seccion_header animate_fadeIn">
    <div class="seccion_titulo_row">
        <h2>🔗 Matching Automático</h2>
        <span class="badge_estado badge_estado--activo"><?php echo count($matches); ?> comprador<?php echo count($matches) !== 1 ? 'es' : ''; ?> · <?php echo $total_matches; ?> coincidencia<?php echo $total_matches !== 1 ? 's' : ''; ?></span>
    </div>
    <p class="seccion_subtitulo">Compradores cuya zona y presupuesto coinciden con propiedades disponibles.</p>
</div>

<?php if ($mensaje_exito): ?><div class="alerta_exito animate_fadeIn"><?php echo e($mensaje_exito); ?></div><?php endif; ?>

<?php if (empty($matches)): ?>
    <div class="sin_datos animate_fadeIn">
        <p>🔍 No se encontraron coincidencias. Asegúrate de que los compradores tengan <strong>zona_interesada</strong> y <strong>presupuesto</strong>, y haya propiedades disponibles en venta.</p>
    </div>
<?php else: ?>
<div class="matching_lista animate_fadeIn">
    <?php foreach ($matches as $m):
        $comp = $m['comprador'];   // Datos del comprador
        $props = $m['propiedades']; // Propiedades que coinciden con este comprador
    ?>
    <div class="matching_grupo">
        <!-- Comprador -->
        <div class="matching_comprador">
            <div class="matching_comp_avatar">👤</div>
            <div class="matching_comp_info">
                <h3><?php echo e($comp['nombre'] . ' ' . $comp['apellido']); ?></h3>
                <div class="matching_comp_tags">
                    <span class="tag_mini tag_mini--zona">📍 <?php echo e($comp['zona_interesada']); ?></span>
                    <span class="tag_mini tag_mini--precio">💰 Hasta <?php echo number_format((float)($comp['presupuesto'] ?? 0), 0, ',', '.'); ?> €</span>
                    <?php if (!empty($comp['operacion'])): ?>
                        <span class="tag_mini tag_mini--op"><?php echo e(ucfirst($comp['operacion'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="matching_comp_acciones">
                <a href="?seccion=ver_cliente&id=<?php echo $comp['id']; ?>&origen=matching" class="btn_secundario btn_chico">Ver ficha ➜</a>
            </div>
        </div>

        <!-- Propiedades coincidentes con este comprador -->
        <!-- Para cada propiedad calculamos:
             - $diff: diferencia entre presupuesto y precio (positivo = dentro del presupuesto)
             - $pct: porcentaje de diferencia respecto al precio
             Esto permite mostrar "Margen 11%" (sobra dinero) o "Excede 10%" (falta dinero) -->
        <div class="matching_props_grid">
            <?php foreach ($props as $prop):
                // Calculamos cuánto le sobra o le falta al comprador para esta propiedad
                $diff = (float)($comp['presupuesto'] ?? 0) - (float)($prop['precio'] ?? 0);
                // Porcentaje de la diferencia respecto al precio de la propiedad
                $pct = ($prop['precio'] > 0) ? round(abs($diff) / $prop['precio'] * 100) : 0;
            ?>
            <div class="matching_prop_card">
                <div class="matching_prop_titulo">
                    <span>🏠 <?php echo e($prop['titulo']); ?></span>
                    <span class="matching_score_mini" title="<?php echo $diff >= 0 ? 'Dentro del presupuesto' : 'Excede presupuesto'; ?>">
                        <?php echo $diff >= 0 ? '✅' : '⚠️'; ?>
                    </span>
                </div>
                <ul class="matching_prop_datos">
                    <li>📍 <?php echo e($prop['ubicacion'] ?? '—'); ?></li>
                    <li>💰 <?php echo number_format((float)($prop['precio'] ?? 0), 0, ',', '.'); ?> €</li>
                    <?php if (!empty($prop['metros'])): ?><li>📐 <?php echo e($prop['metros']); ?> m²</li><?php endif; ?>
                    <?php if (!empty($prop['habitaciones'])): ?><li>🛏️ <?php echo e($prop['habitaciones']); ?> hab.</li><?php endif; ?>
                </ul>
                <div class="matching_prop_footer">
                    <span class="matching_diferencia matching_diferencia--<?php echo $diff >= 0 ? 'ok' : 'warn'; ?>">
                        <?php echo $diff >= 0 ? "Margen {$pct}%" : "Excede {$pct}%"; ?>
                    </span>
                    <a href="?seccion=ver_propiedad&id=<?php echo $prop['id']; ?>&origen=matching" class="btn_secundario btn_chico">Ver ➜</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
