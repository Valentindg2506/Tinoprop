<?php
/*
 * Sección: Post-Venta — V0.0.28
 * Rol: pipeline kanban para seguimiento post-venta de propiedades vendidas/alquiladas.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$mensaje_exito = flash_get('success');
$mensaje_error = flash_get('error');

// Columnas del pipeline post-venta
$columnas = [
    'pendiente_docs' => ['titulo' => '📄 Pendiente Docs', 'color' => '#f59e0b'],
    'firma_contrato' => ['titulo' => '✍️ Firma Contrato', 'color' => '#3b82f6'],
    'tramites' => ['titulo' => '🏛️ Trámites', 'color' => '#8b5cf6'],
    'entrega_llaves' => ['titulo' => '🔑 Entrega Llaves', 'color' => '#10b981'],
    'completado' => ['titulo' => '✅ Completado', 'color' => '#059669'],
];

// Asegurar tabla
$pdo->exec("CREATE TABLE IF NOT EXISTS post_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    propiedad_id INT NOT NULL,
    cliente_id INT DEFAULT NULL,
    usuario_id INT UNSIGNED NOT NULL DEFAULT 0,
    inmobiliaria_id INT UNSIGNED DEFAULT NULL,
    etapa VARCHAR(50) NOT NULL DEFAULT 'pendiente_docs',
    notas TEXT,
    fecha_venta DATE,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(propiedad_id), INDEX(etapa), INDEX(usuario_id), INDEX(inmobiliaria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");


$usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['crear_seguimiento'])) {
        $prop_id = (int) ($_POST['propiedad_id'] ?? 0);
        $cli_id = (int) ($_POST['cliente_id'] ?? 0) ?: null;
        $fecha_venta = $_POST['fecha_venta'] ?? date('Y-m-d');
        $notas = trim($_POST['notas'] ?? '');
        if ($prop_id > 0) {
            $stmt = $pdo->prepare('INSERT INTO post_venta (propiedad_id, cliente_id, usuario_id, inmobiliaria_id, etapa, notas, fecha_venta) VALUES (:pid, :cid, :uid, :iid, :etapa, :notas, :fv)');
            $stmt->execute(['pid' => $prop_id, 'cid' => $cli_id, 'uid' => $usuario_id, 'iid' => usuario_inmobiliaria_id(), 'etapa' => 'pendiente_docs', 'notas' => $notas, 'fv' => $fecha_venta]);
            actividad_registrar($pdo, 'crear', 'post_venta', (int)$pdo->lastInsertId(), 'Seguimiento post-venta creado');
            flash_set('success', 'Seguimiento post-venta creado.');
        }
    }

    if (isset($_POST['mover_etapa'])) {
        $pv_id = (int) ($_POST['pv_id'] ?? 0);
        $nueva_etapa = $_POST['nueva_etapa'] ?? '';
        if ($pv_id > 0 && array_key_exists($nueva_etapa, $columnas)) {
            $pdo->prepare('UPDATE post_venta SET etapa = :etapa WHERE id = :id AND usuario_id = :uid' . sql_iid())->execute(['etapa' => $nueva_etapa, 'id' => $pv_id, 'uid' => $usuario_id] + sql_iid_params());
            flash_set('success', 'Etapa actualizada.');
        }
    }

    if (isset($_POST['eliminar_seguimiento'])) {
        $pv_id = (int) ($_POST['pv_id'] ?? 0);
        if ($pv_id > 0 && tiene_nivel('supervisor')) {
            $pdo->prepare('DELETE FROM post_venta WHERE id = :id' . sql_iid())->execute(['id' => $pv_id] + sql_iid_params());
            flash_set('success', 'Seguimiento eliminado.');
        } elseif (!tiene_nivel('supervisor')) {
            flash_set('error', 'Solo supervisores o superiores pueden eliminar seguimientos.');
        }
    }

    header('Location: index.php?seccion=post-venta');
    exit;
}

// Datos (filtrados por usuario)
$stmt = $pdo->prepare("SELECT pv.*, p.titulo AS prop_titulo, p.precio AS prop_precio, p.ubicacion AS prop_ubicacion,
    CONCAT(c.nombre, ' ', c.apellido) AS cliente_nombre
    FROM post_venta pv
    LEFT JOIN propiedades p ON pv.propiedad_id = p.id
    LEFT JOIN clientes c ON pv.cliente_id = c.id
    WHERE pv.usuario_id = :uid" . sql_iid('pv') . "
    ORDER BY pv.fecha_actualizacion DESC");
$stmt->execute(['uid' => $usuario_id] + sql_iid_params());
$items = $stmt->fetchAll();

// Agrupar por etapa
$por_etapa = [];
foreach (array_keys($columnas) as $k) $por_etapa[$k] = [];
foreach ($items as $item) {
    $etapa = $item['etapa'] ?? 'pendiente_docs';
    if (!isset($por_etapa[$etapa])) $etapa = 'pendiente_docs';
    $por_etapa[$etapa][] = $item;
}

// Propiedades vendidas/reservadas para crear nuevo seguimiento
$stmt = $pdo->prepare("SELECT id, titulo, precio FROM propiedades WHERE estado IN ('Vendido','Reservado')" . sql_iid() . " ORDER BY titulo");
$stmt->execute(sql_iid_params());
$props_vendidas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, CONCAT(nombre, ' ', apellido) AS nombre_completo FROM clientes WHERE 1=1" . sql_iid() . " ORDER BY nombre");
$stmt->execute(sql_iid_params());
$clientes_lista = $stmt->fetchAll();
?>

<div class="seccion_header animate_fadeIn">
    <h2>🏡 Seguimiento Post-Venta</h2>
    <div class="seccion_acciones">
        <button type="button" class="btn_guardar" onclick="document.getElementById('modalPostVenta').classList.add('activo')">+ Nuevo seguimiento</button>
    </div>
</div>

<?php if ($mensaje_exito): ?><div class="alerta_exito animate_fadeIn"><?php echo e($mensaje_exito); ?></div><?php endif; ?>
<?php if ($mensaje_error): ?><div class="alerta_error animate_fadeIn"><?php echo e($mensaje_error); ?></div><?php endif; ?>

<div class="kanban_container animate_fadeIn">
    <?php foreach ($columnas as $clave => $col): ?>
    <div class="kanban_columna">
        <div class="kanban_columna_header" style="border-top: 3px solid <?php echo $col['color']; ?>">
            <h3><?php echo $col['titulo']; ?></h3>
            <span class="kanban_count"><?php echo count($por_etapa[$clave]); ?></span>
        </div>
        <div class="kanban_items">
            <?php foreach ($por_etapa[$clave] as $item): ?>
            <div class="kanban_tarjeta kanban_tarjeta--enriquecida">
                <div class="kanban_tarjeta_header">
                    <strong><?php echo e($item['prop_titulo'] ?? 'Propiedad #' . $item['propiedad_id']); ?></strong>
                </div>
                <?php if ($item['prop_precio']): ?>
                    <span class="kanban_precio"><?php echo number_format((float)$item['prop_precio'], 0, ',', '.'); ?> €</span>
                <?php endif; ?>
                <?php if ($item['prop_ubicacion']): ?>
                    <span class="kanban_ubicacion">📍 <?php echo e($item['prop_ubicacion']); ?></span>
                <?php endif; ?>
                <?php if ($item['cliente_nombre']): ?>
                    <span class="kanban_cliente">👤 <?php echo e($item['cliente_nombre']); ?></span>
                <?php endif; ?>
                <?php if ($item['fecha_venta']): ?>
                    <span class="kanban_fecha">📅 <?php echo date('d/m/Y', strtotime($item['fecha_venta'])); ?></span>
                <?php endif; ?>
                <?php if ($item['notas']): ?>
                    <p class="kanban_notas"><?php echo e(mb_substr($item['notas'], 0, 50)); ?></p>
                <?php endif; ?>
                <div class="kanban_tarjeta_acciones">
                    <?php
                    $claves = array_keys($columnas);
                    $idx = array_search($clave, $claves);
                    if ($idx < count($claves) -1):
                        $siguiente = $claves[$idx + 1];
                    ?>
                    <form method="POST" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="pv_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="nueva_etapa" value="<?php echo $siguiente; ?>">
                        <button type="submit" name="mover_etapa" class="btn_chico btn_guardar" title="Avanzar">▶ Avanzar</button>
                    </form>
                    <?php endif; ?>
                    <?php if (tiene_nivel('supervisor')): ?>
                    <form method="POST" style="display:inline" data-confirm="¿Eliminar este seguimiento?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="pv_id" value="<?php echo $item['id']; ?>">
                        <button type="submit" name="eliminar_seguimiento" class="btn_chico btn_peligro" title="Eliminar">🗑️</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal crear seguimiento -->
<div class="modal_seccion" id="modalPostVenta">
    <div class="modal_seccion_contenido">
        <div class="modal_seccion_header">
            <h3>Nuevo seguimiento post-venta</h3>
            <button type="button" class="modal_cerrar" onclick="document.getElementById('modalPostVenta').classList.remove('activo')">✕</button>
        </div>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <div class="form_grupo">
                <label>Propiedad</label>
                <select name="propiedad_id" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($props_vendidas as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo e($p['titulo']) . ' — ' . number_format((float)$p['precio'], 0, ',', '.') . ' €'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form_grupo">
                <label>Cliente (comprador)</label>
                <select name="cliente_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($clientes_lista as $cl): ?>
                        <option value="<?php echo $cl['id']; ?>"><?php echo e($cl['nombre_completo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form_grupo">
                <label>Fecha de venta</label>
                <input type="date" name="fecha_venta" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form_grupo">
                <label>Notas</label>
                <textarea name="notas" rows="3" placeholder="Observaciones..."></textarea>
            </div>
            <button type="submit" name="crear_seguimiento" class="btn_guardar">Crear seguimiento</button>
        </form>
    </div>
</div>
