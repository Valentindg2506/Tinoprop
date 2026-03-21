<?php
/* Seccion: Detalle de Cliente
   Descripcion: Ficha editable con datos de MySQL
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$id_cliente = (int) ($_GET['id'] ?? 0);
$origen = $_GET['origen'] ?? 'clientes-vendedor';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_cliente > 0) {
    if (isset($_POST['guardar_cambios'])) {
        $datos_update = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'operacion' => trim($_POST['operacion'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'genero' => trim($_POST['genero'] ?? ''),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
            'presupuesto' => $_POST['presupuesto'] !== '' ? (float) $_POST['presupuesto'] : null,
            'zona_interesada' => trim($_POST['zona_interesada'] ?? ''),
            'comentarios' => trim($_POST['comentarios'] ?? ''),
            'id' => $id_cliente,
        ];

        $stmt = $pdo->prepare(
            'UPDATE clientes SET
                nombre = :nombre,
                apellido = :apellido,
                telefono = :telefono,
                email = :email,
                operacion = :operacion,
                direccion = :direccion,
                genero = :genero,
                fecha_nacimiento = :fecha_nacimiento,
                presupuesto = :presupuesto,
                zona_interesada = :zona_interesada,
                comentarios = :comentarios
             WHERE id = :id'
        );
        $stmt->execute($datos_update);
    }

    if (isset($_POST['guardar_nota'])) {
        $nota_texto = trim($_POST['nota_nueva'] ?? '');
        $nota_tipo = $_POST['nota_tipo'] ?? 'Nota';

        if ($nota_texto !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO notas (entity_type, entity_id, tipo, texto, usuario_id)
                 VALUES (:entity_type, :entity_id, :tipo, :texto, :usuario_id)'
            );
            $stmt->execute([
                'entity_type' => 'cliente',
                'entity_id' => $id_cliente,
                'tipo' => $nota_tipo,
                'texto' => $nota_texto,
                'usuario_id' => $_SESSION['usuario']['id'] ?? null,
            ]);
        }
    }

    if (isset($_POST['eliminar_cliente'])) {
        $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id");
        $stmt->execute(['id' => $id_cliente]);

        $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id');
        $stmt->execute(['id' => $id_cliente]);

        header('Location: index.php?seccion=' . urlencode($origen));
        exit;
    }

    header('Location: index.php?seccion=ver_cliente&id=' . $id_cliente . '&origen=' . urlencode($origen));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id_cliente]);
$datos_cliente = $stmt->fetch();

if (!$datos_cliente) {
    $datos_cliente = [
        'id' => $id_cliente,
        'nombre' => '',
        'apellido' => '',
        'telefono' => '',
        'email' => '',
        'operacion' => '',
        'direccion' => '',
        'genero' => '',
        'fecha_nacimiento' => '',
        'presupuesto' => '',
        'zona_interesada' => '',
        'comentarios' => '',
    ];
}

$stmt = $pdo->prepare(
    "SELECT tipo, texto, DATE_FORMAT(created_at, '%Y-%m-%d') AS fecha
     FROM notas
     WHERE entity_type = 'cliente' AND entity_id = :id
     ORDER BY created_at DESC"
);
$stmt->execute(['id' => $id_cliente]);
$notas_cliente = $stmt->fetchAll();
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo e($origen); ?>" class="btn_volver">⬅ Volver al listado</a>
    <h2>Ficha del Cliente</h2>
</div>

<form action="" method="POST">
    <div class="detalle_layout">
        <div class="detalle_col detalle_col--info">
            <div class="tarjeta_ficha tarjeta_ficha--compacta">
                <div class="grid_datos">
                    <div class="campo_dato">
                        <label>Nombre:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="nombre" name="nombre" value="<?php echo e($datos_cliente['nombre']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('nombre')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Apellidos:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="apellido" name="apellido" value="<?php echo e($datos_cliente['apellido']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('apellido')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Telefono:</label>
                        <div class="input_con_lapiz">
                            <input type="tel" id="telefono" name="telefono" value="<?php echo e($datos_cliente['telefono']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('telefono')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Tipo de operacion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="operacion" name="operacion" value="<?php echo e($datos_cliente['operacion']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('operacion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Email:</label>
                        <div class="input_con_lapiz">
                            <input type="email" id="email" name="email" value="<?php echo e($datos_cliente['email']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('email')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato full_width">
                        <label>Direccion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="direccion" name="direccion" value="<?php echo e((string) $datos_cliente['direccion']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('direccion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Genero:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="genero" name="genero" value="<?php echo e((string) $datos_cliente['genero']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('genero')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Fecha de nacimiento:</label>
                        <div class="input_con_lapiz">
                            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo e((string) $datos_cliente['fecha_nacimiento']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('fecha_nacimiento')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Presupuesto:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="presupuesto" name="presupuesto" value="<?php echo e((string) $datos_cliente['presupuesto']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('presupuesto')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Zona interesada:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="zona_interesada" name="zona_interesada" value="<?php echo e((string) $datos_cliente['zona_interesada']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('zona_interesada')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato full_width">
                        <label>Comentarios:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="comentarios" name="comentarios" value="<?php echo e((string) $datos_cliente['comentarios']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('comentarios')">✎</span>
                        </div>
                    </div>
                </div>

                <div class="area_botones acciones_inline">
                    <button type="submit" name="guardar_cambios" class="btn_guardar">Guardar Cambios</button>
                    <button type="submit" name="eliminar_cliente" class="btn_peligro">Eliminar</button>
                </div>
            </div>
        </div>

        <aside class="detalle_col detalle_col--notas">
            <div class="panel_notas">
                <div class="panel_header">
                    <h3>Notas y avisos</h3>
                    <span class="panel_hint">Visibles para todo el equipo</span>
                </div>

                <ul class="lista_notas">
                    <?php foreach ($notas_cliente as $nota): ?>
                        <li class="nota_item">
                            <div class="nota_meta">
                                <span class="nota_tipo"><?php echo e($nota['tipo']); ?></span>
                                <span class="nota_fecha"><?php echo e($nota['fecha']); ?></span>
                            </div>
                            <p><?php echo e($nota['texto']); ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="campo_nota">
                    <label for="nota_tipo">Tipo</label>
                    <select id="nota_tipo" name="nota_tipo">
                        <option value="Nota">Nota</option>
                        <option value="Aviso">Aviso</option>
                    </select>
                </div>

                <div class="campo_nota">
                    <label for="nota_nueva">Contenido</label>
                    <textarea id="nota_nueva" name="nota_nueva" rows="4" placeholder="Escribe una nota o aviso..."></textarea>
                </div>

                <div class="area_botones area_botones--notas">
                    <button type="submit" name="guardar_nota" class="btn_guardar">Guardar nota</button>
                </div>
            </div>
        </aside>
    </div>
</form>

<script>
function activarEdicion(idCampo) {
    let input = document.getElementById(idCampo);

    // Habilita edicion y enfoca el campo seleccionado.
    input.removeAttribute('readonly');
    input.focus();

    // Refuerzo visual mientras se edita.
    input.classList.add('editando');
}
</script>
