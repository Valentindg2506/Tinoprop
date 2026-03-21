<?php
/*
 * Sección: Admin Inmobiliarias (Solo SuperAdmin)
 * Rol: CRUD de inmobiliarias del sistema multi-tenant.
 */
require_once __DIR__ . '/../inc/bootstrap.php';
verificar_acceso('admin-inmobiliarias');

$pdo = db();
inmobiliarias_asegurar_tabla($pdo);
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

// --- POST: acciones CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['crear_inmobiliaria'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $cif = trim($_POST['cif'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $max_usuarios = (int) ($_POST['max_usuarios'] ?? 10);

        if ($nombre === '') {
            flash_set('error', 'El nombre de la inmobiliaria es obligatorio.');
        } else {
            inmobiliaria_crear($pdo, [
                'nombre' => $nombre, 'cif' => $cif, 'direccion' => $direccion,
                'telefono' => $telefono, 'email' => $email, 'max_usuarios' => $max_usuarios,
            ]);
            flash_set('success', 'Inmobiliaria creada correctamente.');
        }
        header('Location: index.php?seccion=admin-inmobiliarias');
        exit;
    }

    if (isset($_POST['editar_inmobiliaria'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($id > 0 && $nombre !== '') {
            inmobiliaria_actualizar($pdo, $id, [
                'nombre' => $nombre,
                'cif' => trim($_POST['cif'] ?? ''),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'max_usuarios' => (int) ($_POST['max_usuarios'] ?? 10),
            ]);
            flash_set('success', 'Inmobiliaria actualizada.');
        }
        header('Location: index.php?seccion=admin-inmobiliarias');
        exit;
    }

    if (isset($_POST['toggle_inmobiliaria'])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            inmobiliaria_toggle_activa($pdo, $id);
            flash_set('success', 'Estado de la inmobiliaria actualizado.');
        }
        header('Location: index.php?seccion=admin-inmobiliarias');
        exit;
    }
}

$inmobiliarias = inmobiliarias_listar($pdo);
$editando = null;
if (isset($_GET['editar'])) {
    $editando = inmobiliaria_obtener($pdo, (int) $_GET['editar']);
}
?>

<?php if ($mensaje_error): ?>
    <div class="alerta alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>

<div class="seccion_header animate_fadeIn">
    <div class="seccion_titulo_row">
        <h2><span class="menu_icono">🏢</span> Gestión de Inmobiliarias</h2>
    </div>
    <p class="seccion_subtitulo">Administra las agencias inmobiliarias del sistema.</p>
</div>

<!-- Formulario crear/editar -->
<div class="panel_panel admin_form_panel animate_fadeIn">
    <div class="panel_header">
        <h3><?php echo $editando ? '✏️ Editar Inmobiliaria' : '➕ Nueva Inmobiliaria'; ?></h3>
    </div>
    <form method="POST" class="admin_form_grid">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="<?php echo $editando ? 'editar_inmobiliaria' : 'crear_inmobiliaria'; ?>" value="1">
        <?php if ($editando): ?>
            <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
        <?php endif; ?>

        <div class="campo">
            <label>Nombre *</label>
            <input type="text" name="nombre" required value="<?php echo e($editando['nombre'] ?? ''); ?>" placeholder="Nombre de la inmobiliaria">
        </div>
        <div class="campo">
            <label>CIF / NIF</label>
            <input type="text" name="cif" value="<?php echo e($editando['cif'] ?? ''); ?>" placeholder="B12345678">
        </div>
        <div class="campo">
            <label>Dirección</label>
            <input type="text" name="direccion" value="<?php echo e($editando['direccion'] ?? ''); ?>" placeholder="Calle, número, ciudad">
        </div>
        <div class="campo">
            <label>Teléfono</label>
            <input type="text" name="telefono" value="<?php echo e($editando['telefono'] ?? ''); ?>" placeholder="600 123 456">
        </div>
        <div class="campo">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo e($editando['email'] ?? ''); ?>" placeholder="contacto@inmobiliaria.com">
        </div>
        <div class="campo">
            <label>Máx. usuarios</label>
            <input type="number" name="max_usuarios" min="1" max="100" value="<?php echo e((string) ($editando['max_usuarios'] ?? 10)); ?>">
        </div>

        <div class="campo campo_acciones">
            <button type="submit" class="btn_guardar"><?php echo $editando ? 'Guardar cambios' : 'Crear inmobiliaria'; ?></button>
            <?php if ($editando): ?>
                <a href="?seccion=admin-inmobiliarias" class="btn_secundario">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Lista de inmobiliarias -->
<div class="panel_panel animate_fadeIn">
    <div class="panel_header">
        <h3>📋 Inmobiliarias registradas (<?php echo count($inmobiliarias); ?>)</h3>
    </div>

    <?php if (empty($inmobiliarias)): ?>
        <p class="sin_datos_texto">No hay inmobiliarias registradas.</p>
    <?php else: ?>
    <div class="tabla_responsive">
        <table class="tabla_datos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>CIF</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inmobiliarias as $inmob): ?>
                <tr class="<?php echo empty($inmob['activa']) ? 'fila_inactiva' : ''; ?>">
                    <td><?php echo $inmob['id']; ?></td>
                    <td><strong><?php echo e($inmob['nombre']); ?></strong></td>
                    <td><?php echo e($inmob['cif'] ?? '-'); ?></td>
                    <td><?php echo e($inmob['email'] ?? '-'); ?></td>
                    <td><?php echo e($inmob['telefono'] ?? '-'); ?></td>
                    <td>
                        <span class="badge_usuarios"><?php echo (int) $inmob['total_usuarios']; ?> / <?php echo (int) $inmob['max_usuarios']; ?></span>
                    </td>
                    <td>
                        <span class="badge_estado badge_estado--<?php echo $inmob['activa'] ? 'disponible' : 'reservado'; ?>">
                            <?php echo $inmob['activa'] ? 'Activa' : 'Inactiva'; ?>
                        </span>
                    </td>
                    <td class="acciones_celda">
                        <a href="?seccion=admin-inmobiliarias&editar=<?php echo $inmob['id']; ?>" class="btn_editar_inline" title="Editar">✏️</a>
                        <form method="POST" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="toggle_inmobiliaria" value="1">
                            <input type="hidden" name="id" value="<?php echo $inmob['id']; ?>">
                            <button type="submit" class="btn_editar_inline" title="<?php echo $inmob['activa'] ? 'Desactivar' : 'Activar'; ?>">
                                <?php echo $inmob['activa'] ? '🔒' : '🔓'; ?>
                            </button>
                        </form>
                        <a href="?seccion=admin-usuarios&inmobiliaria_id=<?php echo $inmob['id']; ?>" class="btn_editar_inline" title="Ver usuarios">👥</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
