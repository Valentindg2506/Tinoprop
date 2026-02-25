<?php
/* Seccion: Detalle de Propiedad
   Descripcion: Ficha editable con notas del inmueble
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$id_propiedad = (int) ($_GET['id'] ?? 0);
$origen = $_GET['origen'] ?? 'propiedades-vendedor';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_propiedad > 0) {
    if (isset($_POST['guardar_cambios'])) {
        $datos_update = [
            'titulo' => trim($_POST['titulo'] ?? ''),
            'tipo' => trim($_POST['tipo'] ?? ''),
            'operacion' => strtolower(trim($_POST['operacion'] ?? '')),
            'estado' => trim($_POST['estado'] ?? ''),
            'precio' => $_POST['precio'] !== '' ? (float) $_POST['precio'] : 0,
            'ubicacion' => trim($_POST['ubicacion'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'metros' => $_POST['metros'] !== '' ? (int) $_POST['metros'] : null,
            'habitaciones' => $_POST['habitaciones'] !== '' ? (int) $_POST['habitaciones'] : null,
            'banos' => $_POST['banos'] !== '' ? (int) $_POST['banos'] : null,
            'referencia' => trim($_POST['referencia'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'id' => $id_propiedad,
        ];

        $stmt = $pdo->prepare(
            'UPDATE propiedades SET
                titulo = :titulo,
                tipo = :tipo,
                operacion = :operacion,
                estado = :estado,
                precio = :precio,
                ubicacion = :ubicacion,
                direccion = :direccion,
                metros = :metros,
                habitaciones = :habitaciones,
                banos = :banos,
                referencia = :referencia,
                descripcion = :descripcion
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
                'entity_type' => 'propiedad',
                'entity_id' => $id_propiedad,
                'tipo' => $nota_tipo,
                'texto' => $nota_texto,
                'usuario_id' => $_SESSION['usuario']['id'] ?? null,
            ]);
        }
    }

    header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM propiedades WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id_propiedad]);
$datos_propiedad = $stmt->fetch();

if (!$datos_propiedad) {
    $datos_propiedad = [
        'id' => $id_propiedad,
        'titulo' => '',
        'tipo' => '',
        'precio' => '',
        'operacion' => '',
        'estado' => '',
        'ubicacion' => '',
        'direccion' => '',
        'metros' => '',
        'habitaciones' => '',
        'banos' => '',
        'referencia' => '',
        'descripcion' => '',
    ];
}

$stmt = $pdo->prepare(
    "SELECT tipo, texto, DATE_FORMAT(created_at, '%Y-%m-%d') AS fecha
     FROM notas
     WHERE entity_type = 'propiedad' AND entity_id = :id
     ORDER BY created_at DESC"
);
$stmt->execute(['id' => $id_propiedad]);
$notas_propiedad = $stmt->fetchAll();
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo e($origen); ?>" class="btn_volver">⬅ Volver al listado</a>
    <h2>Detalle de Propiedad</h2>
</div>

<form action="" method="POST">
    <div class="detalle_layout">
        <div class="detalle_col detalle_col--info">
            <div class="tarjeta_ficha tarjeta_ficha--compacta">
                <div class="grid_datos">
                    <div class="campo_dato">
                        <label>Titulo:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="titulo" name="titulo" value="<?php echo e($datos_propiedad['titulo']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('titulo')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Tipo:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="tipo" name="tipo" value="<?php echo e($datos_propiedad['tipo']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('tipo')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Operacion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="operacion" name="operacion" value="<?php echo e(ucfirst((string) $datos_propiedad['operacion'])); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('operacion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Estado:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="estado" name="estado" value="<?php echo e($datos_propiedad['estado']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('estado')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Precio:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="precio" name="precio" value="<?php echo e((string) $datos_propiedad['precio']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('precio')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Ubicacion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="ubicacion" name="ubicacion" value="<?php echo e((string) $datos_propiedad['ubicacion']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('ubicacion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato full_width">
                        <label>Direccion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="direccion" name="direccion" value="<?php echo e((string) $datos_propiedad['direccion']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('direccion')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Metros:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="metros" name="metros" value="<?php echo e((string) $datos_propiedad['metros']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('metros')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Habitaciones:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="habitaciones" name="habitaciones" value="<?php echo e((string) $datos_propiedad['habitaciones']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('habitaciones')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Banos:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="banos" name="banos" value="<?php echo e((string) $datos_propiedad['banos']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('banos')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato">
                        <label>Referencia:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="referencia" name="referencia" value="<?php echo e((string) $datos_propiedad['referencia']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('referencia')">✎</span>
                        </div>
                    </div>

                    <div class="campo_dato full_width">
                        <label>Descripcion:</label>
                        <div class="input_con_lapiz">
                            <input type="text" id="descripcion" name="descripcion" value="<?php echo e((string) $datos_propiedad['descripcion']); ?>" readonly>
                            <span class="lapiz_editar" onclick="activarEdicion('descripcion')">✎</span>
                        </div>
                    </div>
                </div>

                <div class="area_botones">
                    <button type="submit" name="guardar_cambios" class="btn_guardar">Guardar Cambios</button>
                </div>
            </div>
        </div>

        <aside class="detalle_col detalle_col--notas">
            <div class="panel_notas">
                <div class="panel_header">
                    <h3>Notas y avisos</h3>
                    <span class="panel_hint">Seguimiento del inmueble</span>
                </div>

                <ul class="lista_notas">
                    <?php foreach ($notas_propiedad as $nota): ?>
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

    input.removeAttribute('readonly');
    input.focus();
    input.classList.add('editando');
}
</script>
