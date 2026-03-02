<?php
/**
 * ============================================================================
 * HISTORIAL DE ACTIVIDAD - CRM TINOPROP
 * ============================================================================
 *
 * ¿Qué es este archivo?
 * ---------------------
 * Muestra un registro cronológico (log) de TODAS las acciones realizadas
 * en el CRM: quién hizo qué, cuándo y desde dónde (IP).
 *
 * Es como una "cámara de seguridad" digital que graba cada acción:
 * - ➕ Crear: cuando se añade un cliente, propiedad, etc.
 * - ✏️ Editar: cuando se modifica un registro existente
 * - 🗑️ Eliminar: cuando se borra algo
 * - ↔️ Mover: cuando se cambia un prospecto de estado (Kanban)
 * - 👁️ Ver: cuando se accede a una ficha
 * - 🔑 Login: cuando alguien inicia sesión
 * - 📥 Exportar: cuando se exportan datos a CSV
 *
 * ¿Por qué es importante?
 * - Trazabilidad: saber quién modificó un dato y cuándo
 * - Seguridad: detectar accesos no autorizados
 * - Cumplimiento RGPD: obligación de registrar accesos a datos personales
 * - Auditoría: los supervisores pueden revisar la actividad de su equipo
 *
 * Funcionalidades:
 * - Paginación: muestra 20 registros por página
 * - Cada registro muestra: fecha/hora, acción, entidad afectada,
 *   descripción y dirección IP del usuario
 *
 * Los registros son creados automáticamente por la función
 * actividad_registrar() que se llama desde otras secciones del CRM
 * cuando se realizan acciones relevantes.
 *
 * @archivo  secciones/actividad.php
 * @proyecto TinoProp - CRM Inmobiliario
 * @version  V0.0.31 Pre-Producción
 */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Obtenemos la conexión a la base de datos */
$pdo = db();

/*
 * ASEGURAR QUE EXISTE LA TABLA
 * actividad_asegurar_tabla() crea la tabla 'actividad' si no existe.
 * Es un patrón de "migración ligera": en lugar de ejecutar scripts SQL
 * manuales, la propia aplicación verifica y crea las tablas que necesita.
 * Esto facilita el despliegue en nuevos entornos.
 */
actividad_asegurar_tabla($pdo);

/*
 * PAGINACIÓN
 * Para no cargar miles de registros de golpe (sería muy lento),
 * dividimos los resultados en páginas de 20 registros.
 *
 * - $por_pagina: cuántos registros por página
 * - $total: total de registros en la tabla (para calcular número de páginas)
 * - $pagina_actual: la página que está viendo el usuario (?pagina=2)
 * - paginar(): función helper que calcula offset, páginas totales, etc.
 *
 * max(1, ...) se asegura de que la página sea al menos 1 (no puede ser 0 o negativa)
 */
$por_pagina = 20;
$total = actividad_contar($pdo);
$pagina_actual = max(1, (int) ($_GET['pagina'] ?? 1));
$paginacion = paginar($total, $por_pagina, $pagina_actual);

/* Obtenemos los registros de actividad de la página actual */
$registros = actividad_listar($pdo, $por_pagina, $paginacion['offset']);

/*
 * ICONOS POR TIPO DE ACCIÓN
 * Asignamos un icono a cada tipo de acción para que la tabla sea
 * más visual y fácil de escanear rápidamente.
 * Si la acción no está en la lista, se usa el icono 📋 por defecto.
 */
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
    <p class="subtitulo_seccion"><?php echo $total; ?> registros en total</p>
</div>

<!-- Si no hay registros, mostramos un mensaje amigable.
     Si sí hay, renderizamos una tabla HTML responsive con:
     - Fecha y hora de la acción
     - Tipo de acción con su icono
     - Entidad afectada (cliente, propiedad, etc.) con su ID
     - Descripción de lo que se hizo
     - IP del usuario (para trazabilidad de seguridad)

     e() escapa el HTML para prevenir ataques XSS:
     si alguien intenta guardar código malicioso como descripción,
     e() lo convierte en texto plano inofensivo.

     renderizar_paginacion() genera los botones "Anterior" / "Siguiente" / números.
-->
<?php if (empty($registros)): ?>
    <div class="sin_datos">
        <p>No hay actividad registrada todavía.</p>
    </div>
<?php else: ?>
    <div class="tabla_responsive">
        <table class="tabla_crm">
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
