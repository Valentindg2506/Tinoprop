<?php
/*
 * Sección: Ver cliente — V0.0.28
 * Rol: ficha completa del cliente con edición, notas editables, tags, timeline, email/WhatsApp.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$id_cliente = (int) ($_GET['id'] ?? 0);
$origen = $_GET['origen'] ?? 'clientes-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_cliente > 0) {
    csrf_verify();
    if (isset($_POST['guardar_cambios'])) {
        $errores = [];
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $operacion = trim($_POST['operacion'] ?? '');

        if (!validar_requerido($nombre)) $errores[] = 'El nombre es obligatorio.';
        if (!validar_requerido($apellido)) $errores[] = 'Los apellidos son obligatorios.';
        if (!validar_telefono($telefono)) $errores[] = 'El telefono no es valido.';
        if ($email && !validar_email($email)) $errores[] = 'El email no es valido.';
        if (!validar_requerido($operacion)) $errores[] = 'La operacion es obligatoria.';

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=ver_cliente&id=' . $id_cliente . '&origen=' . urlencode($origen));
            exit;
        }

        $datos_update = [
            'nombre' => $nombre, 'apellido' => $apellido, 'telefono' => $telefono,
            'email' => $email, 'operacion' => $operacion,
            'direccion' => trim($_POST['direccion'] ?? ''),
            'genero' => trim($_POST['genero'] ?? ''),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
            'presupuesto' => ($_POST['presupuesto'] ?? '') !== '' ? (float) $_POST['presupuesto'] : null,
            'zona_interesada' => trim($_POST['zona_interesada'] ?? ''),
            'comentarios' => trim($_POST['comentarios'] ?? ''),
            'id' => $id_cliente,
        ];

        $stmt = $pdo->prepare(
            'UPDATE clientes SET nombre = :nombre, apellido = :apellido, telefono = :telefono,
                email = :email, operacion = :operacion, direccion = :direccion, genero = :genero,
                fecha_nacimiento = :fecha_nacimiento, presupuesto = :presupuesto,
                zona_interesada = :zona_interesada, comentarios = :comentarios WHERE id = :id' . sql_iid()
        );
        $stmt->execute($datos_update + sql_iid_params());
        actividad_registrar($pdo, 'editar', 'cliente', $id_cliente, "Actualizado: $nombre $apellido");
        flash_set('success', 'Cliente actualizado correctamente.');
    }

    if (isset($_POST['guardar_nota'])) {
        $nota_texto = trim($_POST['nota_nueva'] ?? '');
        $nota_tipo = $_POST['nota_tipo'] ?? 'Nota';
        if ($nota_texto !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO notas (entity_type, entity_id, tipo, texto, usuario_id, inmobiliaria_id)
                 VALUES (:etype, :eid, :tipo, :texto, :uid, :iid)'
            );
            $stmt->execute(['etype' => 'cliente', 'eid' => $id_cliente, 'tipo' => $nota_tipo, 'texto' => $nota_texto, 'uid' => $_SESSION['usuario']['id'] ?? null, 'iid' => usuario_inmobiliaria_id()]);
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
        if ($tid > 0) entidad_asignar_etiqueta($pdo, 'cliente', $id_cliente, $tid);
    }

    if (isset($_POST['quitar_etiqueta'])) {
        $tid = (int) ($_POST['etiqueta_id'] ?? 0);
        if ($tid > 0) entidad_quitar_etiqueta($pdo, 'cliente', $id_cliente, $tid);
    }

    if (isset($_POST['crear_etiqueta_rapida'])) {
        $tn = trim($_POST['tag_nombre'] ?? '');
        $tc = $_POST['tag_color'] ?? '#3b82f6';
        if ($tn) { $new_id = etiqueta_crear($pdo, $tn, $tc); if ($new_id) entidad_asignar_etiqueta($pdo, 'cliente', $id_cliente, $new_id); }
    }

    if (isset($_POST['eliminar_cliente'])) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id" . sql_iid())->execute(['id' => $id_cliente] + sql_iid_params());
            $pdo->prepare("DELETE FROM entidad_etiquetas WHERE entidad_tipo = 'cliente' AND entidad_id = :id")->execute(['id' => $id_cliente]);
            $pdo->prepare('DELETE FROM clientes WHERE id = :id' . sql_iid())->execute(['id' => $id_cliente] + sql_iid_params());
            $pdo->commit();
            actividad_registrar($pdo, 'eliminar', 'cliente', $id_cliente, 'Cliente eliminado');
            flash_set('success', 'Cliente eliminado correctamente.');
        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', 'Error al eliminar el cliente. Inténtelo de nuevo.');
        }
        header('Location: index.php?seccion=' . urlencode($origen));
        exit;
    }

    header('Location: index.php?seccion=ver_cliente&id=' . $id_cliente . '&origen=' . urlencode($origen));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id' . sql_iid() . ' LIMIT 1');
$stmt->execute(['id' => $id_cliente] + sql_iid_params());
$datos_cliente = $stmt->fetch();

if (!$datos_cliente) {
    $datos_cliente = array_fill_keys(['id','nombre','apellido','telefono','email','operacion','direccion','genero','fecha_nacimiento','presupuesto','zona_interesada','comentarios'], '');
    $datos_cliente['id'] = $id_cliente;
}

$stmt = $pdo->prepare("SELECT id, tipo, texto, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS fecha FROM notas WHERE entity_type = 'cliente' AND entity_id = :id" . sql_iid() . " ORDER BY created_at DESC");
$stmt->execute(['id' => $id_cliente] + sql_iid_params());
$notas_cliente = $stmt->fetchAll();

$tags_cliente = entidad_obtener_etiquetas($pdo, 'cliente', $id_cliente);
$todas_tags = etiquetas_listar($pdo);
$timeline = timeline_entidad($pdo, 'cliente', $id_cliente);
$tel_limpio = preg_replace('/[^0-9+]/', '', $datos_cliente['telefono']);
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo e($origen); ?>" class="btn_volver">⬅ Volver al listado</a>
</div>

<?php if ($datos_cliente['nombre']): ?>
<div class="barra_contacto_rapido animate_fadeIn">
    <div class="contacto_info">
        <div class="contacto_avatar"><?php echo mb_strtoupper(mb_substr($datos_cliente['nombre'], 0, 1) . mb_substr($datos_cliente['apellido'], 0, 1)); ?></div>
        <div>
            <strong><?php echo e($datos_cliente['nombre'] . ' ' . $datos_cliente['apellido']); ?></strong>
            <span class="badge_estado badge_estado--<?php echo e(map_estado_clase($datos_cliente['operacion'])); ?>"><?php echo e($datos_cliente['operacion']); ?></span>
        </div>
    </div>
    <div class="contacto_acciones">
        <?php if ($tel_limpio): ?>
            <a href="tel:<?php echo e($tel_limpio); ?>" class="btn_contacto btn_contacto--tel" title="Llamar">📞 Llamar</a>
            <a href="https://wa.me/<?php echo e(ltrim($tel_limpio, '+')); ?>" target="_blank" class="btn_contacto btn_contacto--wa" title="WhatsApp">💬 WhatsApp</a>
        <?php endif; ?>
        <?php if ($datos_cliente['email']): ?>
            <a href="mailto:<?php echo e($datos_cliente['email']); ?>?subject=Información inmobiliaria" class="btn_contacto btn_contacto--email" title="Email">📧 Email</a>
        <?php endif; ?>
    </div>
</div>

<div class="barra_tags animate_fadeIn">
    <?php foreach ($tags_cliente as $tag): ?>
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
            <?php $tags_ids = array_column($tags_cliente, 'id'); $tags_disp = array_filter($todas_tags, fn($t) => !in_array($t['id'], $tags_ids)); ?>
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

<form action="" method="POST">
    <?php echo csrf_field(); ?>
    <div class="detalle_layout detalle_layout--tres">
        <div class="detalle_col detalle_col--info animate_fadeIn">
            <div class="tarjeta_ficha tarjeta_ficha--compacta">
                <?php if ($mensaje_error): ?><div class="alerta_error"><?php echo e($mensaje_error); ?></div><?php endif; ?>
                <?php if ($mensaje_exito): ?><div class="alerta_exito"><?php echo e($mensaje_exito); ?></div><?php endif; ?>
                <div class="grid_datos">
                    <?php
                    $campos = [
                        ['id'=>'nombre','label'=>'Nombre','type'=>'text'],
                        ['id'=>'apellido','label'=>'Apellidos','type'=>'text'],
                        ['id'=>'telefono','label'=>'Teléfono','type'=>'tel'],
                        ['id'=>'operacion','label'=>'Operación','type'=>'text'],
                        ['id'=>'email','label'=>'Email','type'=>'email'],
                        ['id'=>'direccion','label'=>'Dirección','type'=>'text','full'=>true],
                        ['id'=>'genero','label'=>'Género','type'=>'text'],
                        ['id'=>'fecha_nacimiento','label'=>'Nacimiento','type'=>'date'],
                        ['id'=>'presupuesto','label'=>'Presupuesto','type'=>'number'],
                        ['id'=>'zona_interesada','label'=>'Zona interesada','type'=>'text'],
                        ['id'=>'comentarios','label'=>'Comentarios','type'=>'text','full'=>true],
                    ];
                    foreach ($campos as $c): ?>
                    <div class="campo_dato <?php echo !empty($c['full']) ? 'full_width' : ''; ?>">
                        <label><?php echo $c['label']; ?>:</label>
                        <div class="input_con_lapiz"><input type="<?php echo $c['type']; ?>" id="<?php echo $c['id']; ?>" name="<?php echo $c['id']; ?>" value="<?php echo e((string)($datos_cliente[$c['id']]??'')); ?>" readonly><span class="lapiz_editar" onclick="activarEdicion('<?php echo $c['id']; ?>')">✎</span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="area_botones acciones_inline">
                    <button type="submit" name="guardar_cambios" class="btn_guardar">Guardar Cambios</button>
                    <button type="submit" name="eliminar_cliente" class="btn_peligro" data-confirm="¿Eliminar este cliente?">Eliminar</button>
                </div>
            </div>
        </div>

        <aside class="detalle_col detalle_col--notas animate_fadeIn" style="animation-delay:.1s">
            <div class="panel_notas">
                <div class="panel_header"><h3>Notas y avisos</h3></div>
                <ul class="lista_notas">
                    <?php foreach ($notas_cliente as $nota): ?>
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
</script>
