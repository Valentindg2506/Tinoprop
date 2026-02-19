<?php
/* Seccion: Kanban de Prospectos (Comprador)
    Descripcion: Tablero visual conectado a MySQL
*/
require_once __DIR__ . '/../inc/bootstrap.php';

// 1. Definimos las columnas del Kanban
$columnas_kanban = [
    "nuevo" => "Nuevo",
    "contactado" => "Contactado",
    "no_contesta" => "No Contesta",
    "realizado" => "Realizado",
    "descartado" => "Descartado"
];

$pdo = db();
$origen = 'prospectos-comprador';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mover_prospecto_drag'])) {
        header('Content-Type: application/json; charset=utf-8');

        $id_mover = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';

        if ($id_mover <= 0 || !validar_enum($estado, array_keys($columnas_kanban))) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'mensaje' => 'Datos de movimiento no validos.']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE prospectos SET estado = :estado WHERE id = :id AND tipo = :tipo');
        $stmt->execute([
            'estado' => $estado,
            'id' => $id_mover,
            'tipo' => 'comprador',
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    if (isset($_POST['crear_prospecto'])) {
        $errores = [];
        $nombre = trim($_POST['nombre'] ?? '');
        $interes = trim($_POST['interes'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado = $_POST['estado'] ?? 'nuevo';

        if (!validar_requerido($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (!validar_requerido($interes)) {
            $errores[] = 'El interes es obligatorio.';
        }
        if (!validar_telefono($telefono)) {
            $errores[] = 'El telefono no es valido.';
        }
        if (!validar_enum($estado, array_keys($columnas_kanban))) {
            $errores[] = 'El estado seleccionado no es valido.';
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-prospecto');
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO prospectos (tipo, nombre, interes, estado, telefono)
             VALUES (:tipo, :nombre, :interes, :estado, :telefono)'
        );
        $stmt->execute([
            'tipo' => 'comprador',
            'nombre' => $nombre,
            'interes' => $interes,
            'estado' => $estado,
            'telefono' => $telefono,
        ]);
        flash_set('success', 'Prospecto creado correctamente.');
    }

    if (isset($_POST['editar_prospecto'])) {
        $id_editar = (int) ($_POST['id'] ?? 0);
        if ($id_editar > 0) {
            $errores = [];
            $nombre = trim($_POST['nombre'] ?? '');
            $interes = trim($_POST['interes'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $estado = $_POST['estado'] ?? 'nuevo';

            if (!validar_requerido($nombre)) {
                $errores[] = 'El nombre es obligatorio.';
            }
            if (!validar_requerido($interes)) {
                $errores[] = 'El interes es obligatorio.';
            }
            if (!validar_telefono($telefono)) {
                $errores[] = 'El telefono no es valido.';
            }
            if (!validar_enum($estado, array_keys($columnas_kanban))) {
                $errores[] = 'El estado seleccionado no es valido.';
            }

            if (!empty($errores)) {
                flash_set('error', implode(' ', $errores));
                header('Location: index.php?seccion=' . $origen);
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE prospectos SET nombre = :nombre, interes = :interes, estado = :estado, telefono = :telefono WHERE id = :id'
            );
            $stmt->execute([
                'nombre' => $nombre,
                'interes' => $interes,
                'estado' => $estado,
                'telefono' => $telefono,
                'id' => $id_editar,
            ]);
            flash_set('success', 'Prospecto actualizado correctamente.');
        }
    }

    if (isset($_POST['eliminar_prospecto'])) {
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            $stmt = $pdo->prepare('DELETE FROM prospectos WHERE id = :id');
            $stmt->execute(['id' => $id_eliminar]);
        }
        flash_set('success', 'Prospecto eliminado correctamente.');
    }

    header('Location: index.php?seccion=' . $origen);
    exit;
}
$stmt = $pdo->prepare('SELECT id, nombre, interes, estado, telefono FROM prospectos WHERE tipo = :tipo ORDER BY id DESC');
$stmt->execute(['tipo' => 'comprador']);
$prospectos_db = $stmt->fetchAll();
?>

<div class="encabezado_seccion">
    <h2>Tablero de Prospectos</h2>
    <a href="#nuevo-prospecto" class="btn_nuevo_cliente">+ Nuevo Prospecto</a>
</div>

<div id="nuevo-prospecto" class="form_panel">
    <h3>Crear prospecto comprador</h3>
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid">
        <div class="campo_input">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required>
        </div>
        <div class="campo_input">
            <label for="interes">Interes</label>
            <input id="interes" name="interes" type="text" required>
        </div>
        <div class="campo_input">
            <label for="telefono">Telefono</label>
            <input id="telefono" name="telefono" type="text" required>
        </div>
        <div class="campo_input">
            <label for="estado">Estado</label>
            <select id="estado" name="estado">
                <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
                    <option value="<?php echo e($clave_estado); ?>"><?php echo e($titulo); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_prospecto" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>

<div class="kanban_contenedor">
    
    <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
        
        <div class="kanban_columna" data-estado="<?php echo e($clave_estado); ?>">
            
            <div class="kanban_header <?php echo $clave_estado; ?>">
                <h3><?php echo $titulo; ?></h3>
                <span class="contador">
                    <?php 
                    $total = count(array_filter($prospectos_db, function($p) use ($clave_estado) {
                        return $p['estado'] == $clave_estado;
                    }));
                    echo $total;
                    ?>
                </span>
            </div>

            <div class="kanban_body" data-estado="<?php echo e($clave_estado); ?>">
                <?php 
                // Filtramos y mostramos solo los de esta columna
                foreach ($prospectos_db as $prospecto): 
                    if($prospecto['estado'] == $clave_estado):
                ?>
                    <div class="tarjeta_prospecto" 
                         draggable="true" 
                        data-id="<?php echo $prospecto['id']; ?>"
                         id="prospecto_<?php echo $prospecto['id']; ?>">
                        
                        <h4><?php echo e($prospecto['nombre']); ?></h4>
                        <p class="interes">"<?php echo e($prospecto['interes']); ?>"</p>
                        <div class="datos_contacto">
                            <span>📞 <?php echo e($prospecto['telefono']); ?></span>
                        </div>
                        <div class="acciones_tarjeta">
                            <button title="Mover">➜</button>
                            <form method="POST" data-confirm="¿Eliminar este prospecto? Esta accion no se puede deshacer.">
                                <input type="hidden" name="id" value="<?php echo $prospecto['id']; ?>">
                                <button type="submit" name="eliminar_prospecto" class="btn_peligro">Eliminar</button>
                            </form>
                        </div>

                        <details class="detalle_inline">
                            <summary>Editar</summary>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?php echo $prospecto['id']; ?>">
                                <div class="campo_input">
                                    <label>Nombre</label>
                                    <input name="nombre" type="text" value="<?php echo e($prospecto['nombre']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Interes</label>
                                    <input name="interes" type="text" value="<?php echo e($prospecto['interes']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Telefono</label>
                                    <input name="telefono" type="text" value="<?php echo e($prospecto['telefono']); ?>" required>
                                </div>
                                <div class="campo_input">
                                    <label>Estado</label>
                                    <select name="estado">
                                        <?php foreach ($columnas_kanban as $clave_estado => $titulo): ?>
                                            <option value="<?php echo e($clave_estado); ?>" <?php echo $prospecto['estado'] === $clave_estado ? 'selected' : ''; ?>>
                                                <?php echo e($titulo); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="editar_prospecto" class="btn_guardar">Guardar cambios</button>
                            </form>
                        </details>
                    </div>
                <?php 
                    endif; 
                endforeach; 
                ?>
            </div>
            
        </div>

    <?php endforeach; ?>

</div>
