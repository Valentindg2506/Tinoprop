<?php
/*
 * Sección: Matching automático — V0.0.28
 * Rol: muestra coincidencias entre compradores y propiedades disponibles.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$matches = matching_buscar($pdo, 30);
$mensaje_exito = flash_get('success');
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
        $comp = $m['comprador'];
        $props = $m['propiedades'];
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

        <!-- Propiedades coincidentes -->
        <div class="matching_props_grid">
            <?php foreach ($props as $prop):
                $diff = (float)($comp['presupuesto'] ?? 0) - (float)($prop['precio'] ?? 0);
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
