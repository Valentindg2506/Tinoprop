<?php
/*
 * Sección: Ofertas (Vendedor)
 * Rol: tabla para control de ofertas recibidas sobre propiedades del equipo vendedor.
 * Cada oferta se asocia a una propiedad y opcionalmente a un cliente existente.
 * Estados: pendiente → aceptada / rechazada / contraoferta.
 * Acciones: crear_oferta, editar_oferta, cambiar_estado, eliminar_oferta.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();

if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    ofertas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM ofertas WHERE usuario_id = :uid' . sql_iid() . ' ORDER BY id DESC');
    $stmt->execute(['uid' => (int)($_SESSION['usuario']['id'] ?? 0)] + sql_iid_params());
    exportar_csv($stmt->fetchAll(PDO::FETCH_ASSOC), 'ofertas_vendedor.csv');
}

$origen = 'ofertas-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

$estados_oferta = ['pendiente', 'aceptada', 'rechazada', 'contraoferta'];

// Controlador POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['crear_oferta'])) {
        $errores = [];
        $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
        $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
        $nombre_ofertante = trim($_POST['nombre_ofertante'] ?? '');
        $importe = $_POST['importe'] !== '' ? (float) $_POST['importe'] : 0;
        $fecha = trim($_POST['fecha_oferta'] ?? '');
        $notas = trim($_POST['notas'] ?? '');

        if ($propiedad_id <= 0) {
            $errores[] = 'Debes seleccionar una propiedad.';
        }
        if ($importe <= 0) {
            $errores[] = 'El importe debe ser mayor a 0.';
        }
        if (!$fecha) {
            $errores[] = 'La fecha es obligatoria.';
        }
        if ($cliente_id <= 0 && !$nombre_ofertante) {
            $errores[] = 'Indica un cliente o un nombre de ofertante.';
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nueva-oferta');
            exit;
        }

        $id = oferta_crear($pdo, $propiedad_id, $cliente_id ?: null, $nombre_ofertante ?: null, $importe, $fecha, $notas);

        if ($id) {
            actividad_registrar($pdo, 'crear', 'oferta', $id);
            flash_set('success', 'Oferta registrada correctamente.');
        } else {
            flash_set('error', 'Error al registrar la oferta.');
        }
    }

    if (isset($_POST['editar_oferta'])) {
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
            $cliente_id = (int) ($_POST['cliente_id'] ?? 0);
            $nombre_ofertante = trim($_POST['nombre_ofertante'] ?? '');
            $importe = (float) ($_POST['importe'] ?? 0);
            $fecha = trim($_POST['fecha_oferta'] ?? '');
            $estado = trim($_POST['estado'] ?? 'pendiente');
            $contraoferta = $_POST['contraoferta_importe'] !== '' ? (float) $_POST['contraoferta_importe'] : null;
            $notas = trim($_POST['notas'] ?? '');

            if ($propiedad_id > 0 && $importe > 0 && $fecha && validar_enum($estado, $estados_oferta)) {
                $ok = oferta_actualizar($pdo, $id_editar, $propiedad_id, $cliente_id ?: null, $nombre_ofertante ?: null, $importe, $fecha, $estado, $contraoferta, $notas);
                flash_set($ok ? 'success' : 'error', $ok ? 'Oferta actualizada correctamente.' : 'Error al actualizar.');
            } else {
                flash_set('error', 'Datos no válidos.');
            }
        }
    }

    if (isset($_POST['cambiar_estado'])) {
        $id_cambiar = (int) ($_POST['id'] ?? 0);
        $nuevo_estado = trim($_POST['estado'] ?? '');
        $contraoferta_importe = $_POST['contraoferta_importe'] !== '' ? (float) $_POST['contraoferta_importe'] : null;
        if ($id_cambiar > 0 && validar_enum($nuevo_estado, $estados_oferta)) {
            $ok = oferta_cambiar_estado($pdo, $id_cambiar, $nuevo_estado, $contraoferta_importe);
            flash_set($ok ? 'success' : 'error', $ok ? 'Estado actualizado.' : 'Error al cambiar estado.');
        }
    }

    if (isset($_POST['eliminar_oferta'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $ok = oferta_eliminar($pdo, $id_eliminar);
            if ($ok) {
                actividad_registrar($pdo, 'eliminar', 'oferta', $id_eliminar);
            }
            flash_set($ok ? 'success' : 'error', $ok ? 'Oferta eliminada correctamente.' : 'Error al eliminar.');
        }
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}

// Filtro de estado desde GET
$filtro = $_GET['estado'] ?? 'todos';
if (!in_array($filtro, ['todos', 'pendiente', 'aceptada', 'rechazada', 'contraoferta'])) {
    $filtro = 'todos';
}

// Listado de ofertas
$ofertas = ofertas_listar($pdo, $filtro);

// Propiedades del equipo vendedor para el select
$stmt = $pdo->prepare('SELECT id, titulo, referencia, precio FROM propiedades WHERE equipo = :equipo' . sql_iid() . ' ORDER BY titulo ASC');
$stmt->execute(['equipo' => 'vendedor'] + sql_iid_params());
$propiedades_list = $stmt->fetchAll();

// Clientes para el select (todos, porque el ofertante puede ser comprador)
$stmt = $pdo->prepare('SELECT id, nombre, apellido FROM clientes WHERE 1=1' . sql_iid() . ' ORDER BY nombre ASC');
$stmt->execute(sql_iid_params());
$clientes_list = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <div class="acciones_dashboard">
        <a href="#nueva-oferta" class="btn_nuevo_cliente">+ Nueva Oferta</a>
    </div>
</div>

<?php if ($mensaje_error): ?>
    <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>

<!-- KPIs resumen -->
<?php
$total_ofertas = count($ofertas);
$pendientes = 0;
$aceptadas = 0;
$rechazadas = 0;
$sum_pendientes = 0;
$sum_aceptadas = 0;

// Si el filtro no es "todos", necesitamos datos completos para las KPIs
$todas_para_kpis = ($filtro === 'todos') ? $ofertas : ofertas_listar($pdo, 'todos');
foreach ($todas_para_kpis as $o) {
    if ($o['estado'] === 'pendiente') { $pendientes++; $sum_pendientes += $o['importe']; }
    if ($o['estado'] === 'aceptada') { $aceptadas++; $sum_aceptadas += $o['importe']; }
    if ($o['estado'] === 'rechazada') { $rechazadas++; }
}
?>
<div class="dashboard_kpis ofertas_kpis">
    <div class="kpi_card">
        <div class="kpi_header_row">
            <span class="kpi_icon">🕐</span>
            <span class="kpi_title">Pendientes</span>
        </div>
        <div class="kpi_value"><?php echo $pendientes; ?></div>
        <div class="kpi_detail"><?php echo number_format($sum_pendientes, 0, ',', '.'); ?> €</div>
    </div>
    <div class="kpi_card">
        <div class="kpi_header_row">
            <span class="kpi_icon">✅</span>
            <span class="kpi_title">Aceptadas</span>
        </div>
        <div class="kpi_value"><?php echo $aceptadas; ?></div>
        <div class="kpi_detail"><?php echo number_format($sum_aceptadas, 0, ',', '.'); ?> €</div>
    </div>
    <div class="kpi_card">
        <div class="kpi_header_row">
            <span class="kpi_icon">❌</span>
            <span class="kpi_title">Rechazadas</span>
        </div>
        <div class="kpi_value"><?php echo $rechazadas; ?></div>
    </div>
    <div class="kpi_card">
        <div class="kpi_header_row">
            <span class="kpi_icon">📊</span>
            <span class="kpi_title">Total ofertas</span>
        </div>
        <div class="kpi_value"><?php echo count($todas_para_kpis); ?></div>
    </div>
</div>

<!-- Formulario nueva oferta -->
<div id="nueva-oferta" class="form_panel">
    <h3>Registrar nueva oferta</h3>
    <form method="POST" class="form_grid" data-validar>
        <?php echo csrf_field(); ?>
        <div class="campo_input">
            <label for="propiedad_id">Propiedad *</label>
            <select id="propiedad_id" name="propiedad_id" required>
                <option value="">-- Seleccionar propiedad --</option>
                <?php foreach ($propiedades_list as $prop): ?>
                    <option value="<?php echo $prop['id']; ?>">
                        <?php echo e($prop['referencia'] ? $prop['referencia'] . ' - ' : ''); ?><?php echo e($prop['titulo']); ?>
                        <?php echo $prop['precio'] ? ' (' . number_format($prop['precio'], 0, ',', '.') . ' €)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo_input">
            <label for="cliente_id">Cliente existente</label>
            <select id="cliente_id" name="cliente_id">
                <option value="">-- Sin cliente --</option>
                <?php foreach ($clientes_list as $cli): ?>
                    <option value="<?php echo $cli['id']; ?>">
                        <?php echo e($cli['nombre'] . ' ' . $cli['apellido']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo_input">
            <label for="nombre_ofertante">O nombre del ofertante</label>
            <input id="nombre_ofertante" name="nombre_ofertante" type="text" placeholder="Si no es cliente registrado...">
        </div>
        <div class="campo_input">
            <label for="importe">Importe ofertado (€) *</label>
            <input id="importe" name="importe" type="number" step="0.01" min="1" required placeholder="150000">
        </div>
        <div class="campo_input">
            <label for="fecha_oferta">Fecha oferta *</label>
            <input id="fecha_oferta" name="fecha_oferta" type="date" required value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="campo_input">
            <label for="notas">Notas</label>
            <input id="notas" name="notas" type="text" placeholder="Condiciones, observaciones...">
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_oferta" class="btn_guardar">Registrar Oferta</button>
        </div>
    </form>
</div>

<!-- Filtros -->
<div class="filtros_visitas">
    <a href="?seccion=<?php echo $origen; ?>&estado=todos" class="filtro_btn <?php echo $filtro === 'todos' ? 'activo' : ''; ?>">Todas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=pendiente" class="filtro_btn <?php echo $filtro === 'pendiente' ? 'activo' : ''; ?>">🕐 Pendientes</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=aceptada" class="filtro_btn <?php echo $filtro === 'aceptada' ? 'activo' : ''; ?>">✅ Aceptadas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=rechazada" class="filtro_btn <?php echo $filtro === 'rechazada' ? 'activo' : ''; ?>">❌ Rechazadas</a>
    <a href="?seccion=<?php echo $origen; ?>&estado=contraoferta" class="filtro_btn <?php echo $filtro === 'contraoferta' ? 'activo' : ''; ?>">🔄 Contraofertas</a>
</div>

<!-- Tabla de ofertas -->
<div class="barra_acciones_tabla">
    <span>Ofertas</span>
    <a href="index.php?seccion=ofertas-vendedor&exportar=csv" class="btn_exportar">📥 Exportar CSV</a>
</div>
<div class="contenedor_tabla">
    <?php if (empty($ofertas)): ?>
        <p class="sin_recordatorios">No hay ofertas <?php echo $filtro !== 'todos' ? 'con estado "' . $filtro . '"' : 'registradas'; ?>.</p>
    <?php else: ?>
    <table class="tabla_datos">
        <thead>
            <tr>
                <th class="th_ordenable">Fecha</th>
                <th class="th_ordenable">Propiedad</th>
                <th class="th_ordenable">Ofertante</th>
                <th class="th_ordenable">Precio piso</th>
                <th class="th_ordenable">Oferta</th>
                <th class="th_ordenable">Diferencia</th>
                <th class="th_ordenable">Estado</th>
                <th class="th_ordenable">Notas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ofertas as $o): ?>
            <?php
                $diferencia = null;
                $pct = null;
                if ($o['propiedad_precio'] && $o['importe']) {
                    $diferencia = $o['importe'] - $o['propiedad_precio'];
                    $pct = ($diferencia / $o['propiedad_precio']) * 100;
                }
            ?>
            <tr class="fila_oferta estado_oferta_<?php echo e($o['estado']); ?>">
                <td>
                    <strong><?php echo date('d/m/Y', strtotime($o['fecha_oferta'])); ?></strong>
                </td>
                <td>
                    <?php if ($o['propiedad_titulo']): ?>
                        <a href="index.php?seccion=ver_propiedad&id=<?php echo $o['propiedad_id']; ?>&origen=<?php echo $origen; ?>">
                            <?php echo e($o['propiedad_titulo']); ?>
                        </a>
                        <?php if ($o['propiedad_ref']): ?>
                            <br><small><?php echo e($o['propiedad_ref']); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="texto_muted">Propiedad eliminada</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($o['cliente_nombre']): ?>
                        <?php echo e($o['cliente_nombre'] . ' ' . $o['cliente_apellido']); ?>
                        <?php if ($o['cliente_telefono']): ?>
                            <br><small>📞 <?php echo e($o['cliente_telefono']); ?></small>
                        <?php endif; ?>
                    <?php elseif ($o['nombre_ofertante']): ?>
                        <?php echo e($o['nombre_ofertante']); ?>
                    <?php else: ?>
                        <span class="texto_muted">Sin identificar</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($o['propiedad_precio']): ?>
                        <strong><?php echo number_format($o['propiedad_precio'], 0, ',', '.'); ?> €</strong>
                    <?php else: ?>
                        <span class="texto_muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong class="importe_oferta"><?php echo number_format($o['importe'], 0, ',', '.'); ?> €</strong>
                    <?php if ($o['estado'] === 'contraoferta' && $o['contraoferta_importe']): ?>
                        <br><small class="texto_contraoferta">Contra: <?php echo number_format($o['contraoferta_importe'], 0, ',', '.'); ?> €</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($diferencia !== null): ?>
                        <span class="badge_diferencia <?php echo $diferencia >= 0 ? 'positiva' : 'negativa'; ?>">
                            <?php echo ($diferencia >= 0 ? '+' : '') . number_format($diferencia, 0, ',', '.'); ?> €
                            <small>(<?php echo ($pct >= 0 ? '+' : '') . number_format($pct, 1, ',', '.'); ?>%)</small>
                        </span>
                    <?php else: ?>
                        <span class="texto_muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" class="form_inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                        <input type="hidden" name="contraoferta_importe" value="">
                        <select name="estado" onchange="this.form.submit()" class="select_estado select_estado_<?php echo e($o['estado']); ?>">
                            <?php foreach ($estados_oferta as $est): ?>
                                <option value="<?php echo e($est); ?>" <?php echo $o['estado'] === $est ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(e($est)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="cambiar_estado" value="1">
                    </form>
                </td>
                <td>
                    <span class="texto_observaciones"><?php echo e($o['notas'] ?: '-'); ?></span>
                </td>
                <td>
                    <div class="acciones_inline">
                        <details class="detalle_inline_visita">
                            <summary class="btn_icono btn_icono_editar" title="Editar">✏️</summary>
                            <div class="form_editar_visita">
                                <form method="POST" class="form_grid">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                                    <div class="campo_input">
                                        <label>Propiedad</label>
                                        <select name="propiedad_id" required>
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach ($propiedades_list as $prop): ?>
                                                <option value="<?php echo $prop['id']; ?>" <?php echo ($o['propiedad_id'] == $prop['id']) ? 'selected' : ''; ?>>
                                                    <?php echo e($prop['referencia'] ? $prop['referencia'] . ' - ' : ''); ?><?php echo e($prop['titulo']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo_input">
                                        <label>Cliente</label>
                                        <select name="cliente_id">
                                            <option value="">-- Sin cliente --</option>
                                            <?php foreach ($clientes_list as $cli): ?>
                                                <option value="<?php echo $cli['id']; ?>" <?php echo ($o['cliente_id'] == $cli['id']) ? 'selected' : ''; ?>>
                                                    <?php echo e($cli['nombre'] . ' ' . $cli['apellido']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo_input">
                                        <label>Nombre ofertante</label>
                                        <input name="nombre_ofertante" type="text" value="<?php echo e($o['nombre_ofertante'] ?? ''); ?>">
                                    </div>
                                    <div class="campo_input">
                                        <label>Importe (€)</label>
                                        <input name="importe" type="number" step="0.01" value="<?php echo e($o['importe']); ?>" required>
                                    </div>
                                    <div class="campo_input">
                                        <label>Fecha</label>
                                        <input name="fecha_oferta" type="date" value="<?php echo e($o['fecha_oferta']); ?>" required>
                                    </div>
                                    <div class="campo_input">
                                        <label>Estado</label>
                                        <select name="estado">
                                            <?php foreach ($estados_oferta as $est): ?>
                                                <option value="<?php echo e($est); ?>" <?php echo $o['estado'] === $est ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(e($est)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo_input">
                                        <label>Contraoferta (€)</label>
                                        <input name="contraoferta_importe" type="number" step="0.01" value="<?php echo e($o['contraoferta_importe'] ?? ''); ?>" placeholder="Solo si aplica">
                                    </div>
                                    <div class="campo_input">
                                        <label>Notas</label>
                                        <input name="notas" type="text" value="<?php echo e($o['notas'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" name="editar_oferta" class="btn_guardar">Guardar</button>
                                </form>
                            </div>
                        </details>
                        <form method="POST" data-confirm="¿Eliminar esta oferta? Esta acción no se puede deshacer.">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $o['id']; ?>">
                            <button type="submit" name="eliminar_oferta" class="btn_icono btn_icono_eliminar" title="Eliminar">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
