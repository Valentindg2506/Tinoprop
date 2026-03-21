<?php
/*
 * Sección: Ver propiedad — V0.0.28
 * Rol: ficha detallada con edición, notas editables, galería, tags, timeline, duplicar.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$id_propiedad = (int) ($_GET['id'] ?? 0);
$origen_raw = $_GET['origen'] ?? 'propiedades-vendedor';
$origenes_validos = ['propiedades-vendedor', 'alquileres-vendedor', 'propiedades-comprador', 'alquileres-comprador', 'busqueda-avanzada', 'favoritos', 'matching', 'proceso-vendedor', 'proceso-comprador', 'ofertas-vendedor', 'visitas-vendedor', 'visitas-comprador'];
$origen = in_array($origen_raw, $origenes_validos, true) ? $origen_raw : 'propiedades-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

imagenes_asegurar_tabla($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_propiedad > 0) {
    csrf_verify();
    if (isset($_POST['guardar_cambios'])) {
        $errores = [];
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $operacion = strtolower(trim($_POST['operacion'] ?? ''));
        $estado = trim($_POST['estado'] ?? '');
        $precio = $_POST['precio'] !== '' ? (float) $_POST['precio'] : 0;
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        $operaciones_validas = ['venta', 'alquiler'];
        $estados_validos = ['Disponible', 'Reservado', 'Vendido'];

        if (!validar_requerido($titulo)) $errores[] = 'El titulo es obligatorio.';
        if (!validar_requerido($tipo)) $errores[] = 'El tipo es obligatorio.';
        if (!validar_enum($operacion, $operaciones_validas)) $errores[] = 'La operacion debe ser Venta o Alquiler.';
        if (!validar_enum($estado, $estados_validos)) $errores[] = 'El estado seleccionado no es valido.';
        if ($precio <= 0) $errores[] = 'El precio debe ser mayor a 0.';
        if (!validar_requerido($ubicacion)) $errores[] = 'La ubicacion es obligatoria.';

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
            exit;
        }

        $datos_update = [
            'titulo' => $titulo, 'tipo' => $tipo, 'operacion' => $operacion,
            'estado' => $estado, 'precio' => $precio, 'ubicacion' => $ubicacion,
            'direccion' => trim($_POST['direccion'] ?? ''),
            'metros' => ($_POST['metros'] ?? '') !== '' ? (int) $_POST['metros'] : null,
            'habitaciones' => ($_POST['habitaciones'] ?? '') !== '' ? (int) $_POST['habitaciones'] : null,
            'banos' => ($_POST['banos'] ?? '') !== '' ? (int) $_POST['banos'] : null,
            'referencia' => trim($_POST['referencia'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'id' => $id_propiedad,
        ];
        $stmt = $pdo->prepare(
            'UPDATE propiedades SET titulo=:titulo, tipo=:tipo, operacion=:operacion, estado=:estado,
             precio=:precio, ubicacion=:ubicacion, direccion=:direccion, metros=:metros,
             habitaciones=:habitaciones, banos=:banos, referencia=:referencia, descripcion=:descripcion WHERE id=:id' . sql_iid()
        );
        $stmt->execute($datos_update + sql_iid_params());
        actividad_registrar($pdo, 'editar', 'propiedad', $id_propiedad, "Actualizada: $titulo");
        flash_set('success', 'Propiedad actualizada correctamente.');
    }

    if (isset($_POST['guardar_nota'])) {
        $nota_texto = trim($_POST['nota_nueva'] ?? '');
        $nota_tipo = $_POST['nota_tipo'] ?? 'Nota';
        if ($nota_texto !== '') {
            $pdo->prepare('INSERT INTO notas (entity_type, entity_id, tipo, texto, usuario_id, inmobiliaria_id) VALUES (:et, :eid, :tipo, :texto, :uid, :iid)')
                ->execute(['et' => 'propiedad', 'eid' => $id_propiedad, 'tipo' => $nota_tipo, 'texto' => $nota_texto, 'uid' => $_SESSION['usuario']['id'] ?? null, 'iid' => usuario_inmobiliaria_id()]);
        }
        flash_set('success', 'Nota guardada correctamente.');
    }

    if (isset($_POST['editar_nota'])) {
        $nid = (int) ($_POST['nota_id'] ?? 0);
        $ntxt = trim($_POST['nota_texto'] ?? '');
        $ntip = $_POST['nota_tipo_edit'] ?? 'Nota';
        if ($nid > 0 && $ntxt !== '') { nota_actualizar($pdo, $nid, $ntxt, $ntip); flash_set('success', 'Nota actualizada.'); }
    }

    if (isset($_POST['eliminar_nota'])) {
        $nid = (int) ($_POST['nota_id'] ?? 0);
        if ($nid > 0) { nota_eliminar($pdo, $nid); flash_set('success', 'Nota eliminada.'); }
    }

    if (isset($_POST['asignar_etiqueta'])) {
        $tid = (int) ($_POST['etiqueta_id'] ?? 0);
        if ($tid > 0) entidad_asignar_etiqueta($pdo, 'propiedad', $id_propiedad, $tid);
    }

    if (isset($_POST['quitar_etiqueta'])) {
        $tid = (int) ($_POST['etiqueta_id'] ?? 0);
        if ($tid > 0) entidad_quitar_etiqueta($pdo, 'propiedad', $id_propiedad, $tid);
    }

    if (isset($_POST['crear_etiqueta_rapida'])) {
        $tn = trim($_POST['tag_nombre'] ?? '');
        $tc = $_POST['tag_color'] ?? '#3b82f6';
        if ($tn) { $new_id = etiqueta_crear($pdo, $tn, $tc); if ($new_id) entidad_asignar_etiqueta($pdo, 'propiedad', $id_propiedad, $new_id); }
    }

    if (isset($_POST['duplicar_propiedad'])) {
        $nuevo_id = propiedad_duplicar($pdo, $id_propiedad);
        if ($nuevo_id) {
            actividad_registrar($pdo, 'crear', 'propiedad', $nuevo_id, 'Propiedad duplicada desde #' . $id_propiedad);
            flash_set('success', 'Propiedad duplicada correctamente. ID: ' . $nuevo_id);
            header('Location: index.php?seccion=ver_propiedad&id=' . $nuevo_id . '&origen=' . urlencode($origen));
            exit;
        }
        flash_set('error', 'No se pudo duplicar la propiedad.');
    }

    if (isset($_POST['eliminar_propiedad'])) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id" . sql_iid())->execute(['id' => $id_propiedad] + sql_iid_params());
            $pdo->prepare("DELETE FROM entidad_etiquetas WHERE entidad_tipo = 'propiedad' AND entidad_id = :id")->execute(['id' => $id_propiedad]);
            $pdo->prepare('DELETE FROM propiedades WHERE id = :id' . sql_iid())->execute(['id' => $id_propiedad] + sql_iid_params());
            $pdo->commit();
            actividad_registrar($pdo, 'eliminar', 'propiedad', $id_propiedad, 'Propiedad eliminada');
            flash_set('success', 'Propiedad eliminada correctamente.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Error al eliminar la propiedad. Inténtelo de nuevo.');
        }
        header('Location: index.php?seccion=' . urlencode($origen));
        exit;
    }

    header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM propiedades WHERE id = :id' . sql_iid() . ' LIMIT 1');
$stmt->execute(['id' => $id_propiedad] + sql_iid_params());
$datos_propiedad = $stmt->fetch();
if (!$datos_propiedad) {
    $datos_propiedad = array_fill_keys(['id','titulo','tipo','precio','operacion','estado','ubicacion','direccion','metros','habitaciones','banos','referencia','descripcion'], '');
    $datos_propiedad['id'] = $id_propiedad;
}

$stmt = $pdo->prepare("SELECT id, tipo, texto, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS fecha FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id" . sql_iid() . " ORDER BY created_at DESC");
$stmt->execute(['id' => $id_propiedad] + sql_iid_params());
$notas_propiedad = $stmt->fetchAll();

$imagenes_propiedad = imagenes_obtener_propiedad($pdo, $id_propiedad);
$imagen_principal = imagen_obtener_principal($pdo, $id_propiedad);
$tags_propiedad = entidad_obtener_etiquetas($pdo, 'propiedad', $id_propiedad);
$todas_tags = etiquetas_listar($pdo);
$timeline = timeline_entidad($pdo, 'propiedad', $id_propiedad);

// Datos del propietario asignado
$propietario = null;
if (!empty($datos_propiedad['cliente_id'])) {
    $stmt = $pdo->prepare('SELECT nombre, apellido, telefono, email FROM clientes WHERE id = :id' . sql_iid());
    $stmt->execute(['id' => $datos_propiedad['cliente_id']] + sql_iid_params());
    $propietario = $stmt->fetch();
}
$tel_prop = $propietario ? preg_replace('/[^0-9+]/', '', $propietario['telefono'] ?? '') : '';
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo e($origen); ?>" class="btn_volver">⬅ Volver al listado</a>
</div>

<?php if ($datos_propiedad['titulo']): ?>
<div class="barra_contacto_rapido animate_fadeIn">
    <div class="contacto_info">
        <div class="contacto_avatar contacto_avatar--prop">🏠</div>
        <div>
            <strong><?php echo e($datos_propiedad['titulo']); ?></strong>
            <span class="badge_estado badge_estado--<?php echo e(strtolower($datos_propiedad['estado'] ?? 'disponible')); ?>"><?php echo e($datos_propiedad['estado']); ?></span>
            <?php if ($datos_propiedad['precio']): ?>
                <span class="badge_precio"><?php echo number_format((float)$datos_propiedad['precio'], 0, ',', '.'); ?> €</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="contacto_acciones">
        <?php if ($propietario): ?>
            <?php if ($tel_prop): ?>
                <a href="https://wa.me/<?php echo e(ltrim($tel_prop, '+')); ?>" target="_blank" class="btn_contacto btn_contacto--wa" title="WhatsApp propietario">💬 Propietario</a>
            <?php endif; ?>
            <?php if (!empty($propietario['email'])): ?>
                <a href="mailto:<?php echo e($propietario['email']); ?>?subject=Información propiedad: <?php echo e($datos_propiedad['titulo']); ?>" class="btn_contacto btn_contacto--email" title="Email propietario">📧 Propietario</a>
            <?php endif; ?>
        <?php endif; ?>
        <form method="POST" style="display:inline">
            <?php echo csrf_field(); ?>
            <button type="submit" name="duplicar_propiedad" class="btn_contacto btn_contacto--dup" title="Duplicar propiedad">📋 Duplicar</button>
        </form>
    </div>
</div>

<div class="barra_tags animate_fadeIn">
    <?php foreach ($tags_propiedad as $tag): ?>
        <form method="POST" class="tag_form_inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="etiqueta_id" value="<?php echo $tag['id']; ?>">
            <span class="tag_badge" style="background:<?php echo e($tag['color']); ?>">
                <?php echo e($tag['nombre']); ?>
                <button type="submit" name="quitar_etiqueta" class="tag_remove" title="Quitar">×</button>
            </span>
        </form>
    <?php endforeach; ?>
    <details class="tag_agregar_detail">
        <summary class="btn_tag_add">+ Etiqueta</summary>
        <div class="tag_dropdown">
            <?php $tags_ids = array_column($tags_propiedad, 'id'); $tags_disp = array_filter($todas_tags, fn($t) => !in_array($t['id'], $tags_ids)); ?>
            <?php foreach ($tags_disp as $t): ?>
                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="etiqueta_id" value="<?php echo $t['id']; ?>"><button type="submit" name="asignar_etiqueta" class="tag_option" style="border-left:3px solid <?php echo e($t['color']); ?>"><?php echo e($t['nombre']); ?></button></form>
            <?php endforeach; ?>
            <form method="POST" class="tag_crear_form">
                <?php echo csrf_field(); ?>
                <input type="text" name="tag_nombre" placeholder="Nueva etiqueta..." required class="tag_input">
                <input type="color" name="tag_color" value="#3b82f6" class="tag_color_pick">
                <button type="submit" name="crear_etiqueta_rapida" class="btn_guardar btn_chico">+</button>
            </form>
        </div>
    </details>
</div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <!-- GALERÍA DE IMÁGENES -->
    <div class="galeria_seccion animate_fadeIn">
        <div class="galeria_contenedor">
            <div class="galeria_principal">
                <?php if ($imagen_principal): ?>
                    <img src="<?php echo e($imagen_principal['ruta_archivo']); ?>" alt="<?php echo e($imagen_principal['nombre_original']); ?>">
                <?php else: ?>
                    <div class="imagen_placeholder"><p>📷 Sin imagen</p></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($imagenes_propiedad)): ?>
            <div class="galeria_miniaturas">
                <?php foreach ($imagenes_propiedad as $img): ?>
                    <div class="miniatura_item <?php echo $img['es_principal'] ? 'activa' : ''; ?>">
                        <img src="<?php echo e($img['ruta_archivo']); ?>" alt="">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="carga_imagenes">
                <div class="area_drop" id="areaDrop">
                    <p>📤 Arrastra imágenes aquí o <a href="#" onclick="document.getElementById('inputImagenes').click(); return false;">haz clic</a></p>
                    <input type="file" id="inputImagenes" name="imagenes[]" multiple accept="image/*" style="display: none;">
                </div>
            </div>
        </div>
    </div>

    <div class="detalle_layout detalle_layout--tres">
        <div class="detalle_col detalle_col--info animate_fadeIn">
            <div class="tarjeta_ficha tarjeta_ficha--compacta">
                <?php if ($mensaje_error): ?><div class="alerta_error"><?php echo e($mensaje_error); ?></div><?php endif; ?>
                <?php if ($mensaje_exito): ?><div class="alerta_exito"><?php echo e($mensaje_exito); ?></div><?php endif; ?>
                <div class="grid_datos">
                    <?php
                    $campos = [
                        ['id'=>'titulo','label'=>'Titulo','type'=>'text'],
                        ['id'=>'tipo','label'=>'Tipo','type'=>'text'],
                        ['id'=>'operacion','label'=>'Operacion','type'=>'text'],
                        ['id'=>'estado','label'=>'Estado','type'=>'text'],
                        ['id'=>'precio','label'=>'Precio','type'=>'text'],
                        ['id'=>'ubicacion','label'=>'Ubicacion','type'=>'text'],
                        ['id'=>'direccion','label'=>'Direccion','type'=>'text','full'=>true],
                        ['id'=>'metros','label'=>'Metros','type'=>'text'],
                        ['id'=>'habitaciones','label'=>'Habitaciones','type'=>'text'],
                        ['id'=>'banos','label'=>'Baños','type'=>'text'],
                        ['id'=>'referencia','label'=>'Referencia','type'=>'text'],
                        ['id'=>'descripcion','label'=>'Descripcion','type'=>'text','full'=>true],
                    ];
                    foreach ($campos as $c): ?>
                    <div class="campo_dato <?php echo !empty($c['full']) ? 'full_width' : ''; ?>">
                        <label><?php echo $c['label']; ?>:</label>
                        <div class="input_con_lapiz">
                            <input type="<?php echo $c['type']; ?>" id="<?php echo $c['id']; ?>" name="<?php echo $c['id']; ?>" value="<?php echo e((string)($datos_propiedad[$c['id']] ?? '')); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('<?php echo $c['id']; ?>')">✎</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="area_botones acciones_inline">
                    <button type="submit" name="guardar_cambios" class="btn_guardar">Guardar Cambios</button>
                    <button type="submit" name="eliminar_propiedad" class="btn_peligro" data-confirm="¿Eliminar esta propiedad? Esta accion no se puede deshacer.">Eliminar</button>
                </div>
            </div>
        </div>

        <aside class="detalle_col detalle_col--notas animate_fadeIn" style="animation-delay:.1s">
            <div class="panel_notas">
                <div class="panel_header"><h3>Notas y avisos</h3></div>
                <ul class="lista_notas">
                    <?php foreach ($notas_propiedad as $nota): ?>
                    <li class="nota_item" id="nota-<?php echo $nota['id']; ?>">
                        <div class="nota_meta">
                            <span class="nota_tipo"><?php echo e($nota['tipo']); ?></span>
                            <span class="nota_fecha"><?php echo e($nota['fecha']); ?></span>
                            <button type="button" class="btn_icono_sm" onclick="editarNota(<?php echo $nota['id']; ?>)" title="Editar">✏️</button>
                        </div>
                        <p class="nota_texto_display"><?php echo e($nota['texto']); ?></p>
                        <div class="nota_edit_form" id="nota-edit-<?php echo $nota['id']; ?>" style="display:none">
                            <select name="nota_tipo_edit" form="fn-<?php echo $nota['id']; ?>"><option value="Nota" <?php echo $nota['tipo']==='Nota'?'selected':''; ?>>Nota</option><option value="Aviso" <?php echo $nota['tipo']==='Aviso'?'selected':''; ?>>Aviso</option></select>
                            <textarea name="nota_texto" form="fn-<?php echo $nota['id']; ?>" rows="2"><?php echo e($nota['texto']); ?></textarea>
                            <div class="nota_edit_btns">
                                <form method="POST" id="fn-<?php echo $nota['id']; ?>"><?php echo csrf_field(); ?><input type="hidden" name="nota_id" value="<?php echo $nota['id']; ?>"><button type="submit" name="editar_nota" class="btn_guardar btn_chico">Guardar</button></form>
                                <form method="POST" data-confirm="¿Eliminar esta nota?"><?php echo csrf_field(); ?><input type="hidden" name="nota_id" value="<?php echo $nota['id']; ?>"><button type="submit" name="eliminar_nota" class="btn_peligro btn_chico">🗑️</button></form>
                                <button type="button" class="btn_secundario btn_chico" onclick="cancelarEditNota(<?php echo $nota['id']; ?>)">Cancelar</button>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="campo_nota"><label for="nota_tipo">Tipo</label><select id="nota_tipo" name="nota_tipo"><option value="Nota">Nota</option><option value="Aviso">Aviso</option></select></div>
                <div class="campo_nota"><label for="nota_nueva">Contenido</label><textarea id="nota_nueva" name="nota_nueva" rows="3" placeholder="Escribe una nota o aviso..."></textarea></div>
                <div class="area_botones area_botones--notas"><button type="submit" name="guardar_nota" class="btn_guardar">Guardar nota</button></div>
            </div>
        </aside>

        <aside class="detalle_col detalle_col--timeline animate_fadeIn" style="animation-delay:.2s">
            <div class="panel_timeline">
                <div class="panel_header"><h3>📋 Timeline</h3></div>
                <?php if (empty($timeline)): ?>
                    <p class="sin_datos_mini">Sin actividad registrada</p>
                <?php else: ?>
                <ul class="timeline_lista">
                    <?php foreach (array_slice($timeline, 0, 25) as $ev): ?>
                    <li class="timeline_item timeline_item--<?php echo e($ev['origen']); ?>">
                        <span class="timeline_icono"><?php echo match($ev['origen']) { 'nota'=>'📝','actividad'=>'⚡','visita'=>'🚶','oferta'=>'💰',default=>'📋' }; ?></span>
                        <div class="timeline_contenido">
                            <span class="timeline_tipo"><?php echo e(ucfirst($ev['origen'])); ?> · <?php echo e($ev['subtipo']); ?></span>
                            <p><?php echo e(mb_substr($ev['descripcion'] ?? '', 0, 80)); ?></p>
                            <time class="timeline_fecha"><?php echo date('d/m/Y H:i', strtotime($ev['created_at'])); ?></time>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</form>

<script>
function activarEdicion(id) { let i = document.getElementById(id); i.removeAttribute('readonly'); i.focus(); i.classList.add('editando'); }
function editarNota(id) { document.getElementById('nota-edit-'+id).style.display='block'; document.querySelector('#nota-'+id+' .nota_texto_display').style.display='none'; }
function cancelarEditNota(id) { document.getElementById('nota-edit-'+id).style.display='none'; document.querySelector('#nota-'+id+' .nota_texto_display').style.display=''; }

// GALERÍA DE IMÁGENES
const areaDrop = document.getElementById('areaDrop');
if (areaDrop) {
    areaDrop.addEventListener('dragover', (e) => { e.preventDefault(); areaDrop.style.backgroundColor = '#f0f0f0'; });
    areaDrop.addEventListener('dragleave', () => { areaDrop.style.backgroundColor = ''; });
    areaDrop.addEventListener('drop', (e) => { e.preventDefault(); areaDrop.style.backgroundColor = ''; subirImagenes(e.dataTransfer.files); });
    const inputImagenes = document.getElementById('inputImagenes');
    if (inputImagenes) inputImagenes.addEventListener('change', (e) => { subirImagenes(e.target.files); });
}
document.querySelectorAll('.miniatura_item img').forEach(img => {
    img.addEventListener('click', () => { const p = document.querySelector('.galeria_principal img'); if (p) p.src = img.src; });
});

function subirImagenes(archivos) {
    const propId = <?php echo $id_propiedad; ?>;
    if (!propId) { alert('Propiedad inválida'); return; }
    const ad = document.getElementById('areaDrop');
    ad.innerHTML = '<p>⏳ Cargando...</p>';
    const fd = new FormData();
    fd.append('propiedad_id', propId);
    for (let a of archivos) { if (a.type.startsWith('image/')) fd.append('imagenes[]', a); }
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('api/imagenes.php?action=subir', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrfToken } })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else { alert('Error: ' + (d.message || 'Error desconocido')); ad.innerHTML = '<p>📤 Arrastra imágenes aquí o <a href="#" onclick="document.getElementById(\'inputImagenes\').click(); return false;">haz clic</a></p>'; } })
    .catch(err => { console.error(err); alert('Error al subir imágenes'); ad.innerHTML = '<p>📤 Arrastra imágenes aquí o <a href="#" onclick="document.getElementById(\'inputImagenes\').click(); return false;">haz clic</a></p>'; });
}
</script>
