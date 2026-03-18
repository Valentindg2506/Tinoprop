<?php
require_once __DIR__ . '/../inc/bootstrap.php';
$pdo = db();
actividad_asegurar_tabla($pdo);
$por_pagina = 20;
$total = actividad_contar($pdo);
$pagina_actual = max(1, (int) ($_GET['pagina'] ?? 1));
$paginacion = paginar($total, $por_pagina, $pagina_actual);
$registros = actividad_listar($pdo, $por_pagina, $paginacion['offset']);
$iconos_accion = [
    'crear'    => '➕',
    'editar'   => '✏️',
    'eliminar' => '🗑️',
    'mover'    => '↔️',
    'ver'      => '👁️',
    'login'    => '🔑',
    'exportar' => '📥',
];
?>
<div class="encabezado_seccion">
    <h1>📜 Historial de Actividad</h1>
    <p class="subtitulo_seccion"><?php echo $total; ?> registros en total</p>
</div>
<?php if (empty($registros)): ?>
    <div class="sin_datos">
        <p>No hay actividad registrada todavía.</p>
    </div>
<?php else: ?>
    <div class="vista_contenido vista_tabla">
        <table class="tabla_datos">
            <thead>
                <tr>
                    <th class="th_ordenable">Fecha</th>
                    <th class="th_ordenable">Acción</th>
                    <th class="th_ordenable">Entidad</th>
                    <th>Descripción</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $reg): ?>
                    <tr>
                        <td>
                            <span class="actividad_meta">
                                <?php echo date('d/m/Y H:i', strtotime($reg['created_at'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="actividad_icono <?php echo e($reg['accion']); ?>">
                                <?php echo $iconos_accion[$reg['accion']] ?? '📋'; ?>
                            </span>
                            <?php echo e(ucfirst($reg['accion'])); ?>
                        </td>
                        <td>
                            <strong><?php echo e(ucfirst($reg['entidad'])); ?></strong>
                            <?php if ($reg['entidad_id']): ?>
                                <small>#<?php echo e((string)$reg['entidad_id']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($reg['descripcion'] ?: '—'); ?></td>
                        <td><small><?php echo e($reg['ip']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo renderizar_paginacion($paginacion, 'index.php?seccion=actividad'); ?>
<?php endif; ?>
