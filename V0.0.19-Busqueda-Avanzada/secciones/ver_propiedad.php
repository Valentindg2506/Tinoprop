<?php
/* Seccion: Detalle de Propiedad
   Descripcion: Ficha editable con notas del inmueble
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$id_propiedad = (int) ($_GET['id'] ?? 0);
$origen = $_GET['origen'] ?? 'propiedades-vendedor';
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

// Inicializar tabla de imágenes
imagenes_asegurar_tabla($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_propiedad > 0) {
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

        if (!validar_requerido($titulo)) {
            $errores[] = 'El titulo es obligatorio.';
        }
        if (!validar_requerido($tipo)) {
            $errores[] = 'El tipo es obligatorio.';
        }
        if (!validar_enum($operacion, $operaciones_validas)) {
            $errores[] = 'La operacion debe ser Venta o Alquiler.';
        }
        if (!validar_enum($estado, $estados_validos)) {
            $errores[] = 'El estado seleccionado no es valido.';
        }
        if ($precio <= 0) {
            $errores[] = 'El precio debe ser mayor a 0.';
        }
        if (!validar_requerido($ubicacion)) {
            $errores[] = 'La ubicacion es obligatoria.';
        }

        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
            exit;
        }

        $datos_update = [
            'titulo' => $titulo,
            'tipo' => $tipo,
            'operacion' => $operacion,
            'estado' => $estado,
            'precio' => $precio,
            'ubicacion' => $ubicacion,
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
        flash_set('success', 'Propiedad actualizada correctamente.');
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
        flash_set('success', 'Nota guardada correctamente.');
    }

    if (isset($_POST['eliminar_propiedad'])) {
        $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id");
        $stmt->execute(['id' => $id_propiedad]);

        $stmt = $pdo->prepare('DELETE FROM propiedades WHERE id = :id');
        $stmt->execute(['id' => $id_propiedad]);

        flash_set('success', 'Propiedad eliminada correctamente.');
        header('Location: index.php?seccion=' . urlencode($origen));
        exit;
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

// Cargar imágenes
$imagenes_propiedad = imagenes_obtener_propiedad($pdo, $id_propiedad);
$imagen_principal = imagen_obtener_principal($pdo, $id_propiedad);
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo e($origen); ?>" class="btn_volver">⬅ Volver al listado</a>
    <h2>Detalle de Propiedad</h2>
</div>

<form action="" method="POST" enctype="multipart/form-data">
    <!-- GALERÍA DE IMÁGENES -->
    <div class="galeria_seccion">
        <div class="galeria_contenedor">
            <!-- Imagen Principal -->
            <div class="galeria_principal">
                <?php if ($imagen_principal): ?>
                    <img src="<?php echo e($imagen_principal['ruta_archivo']); ?>" alt="<?php echo e($imagen_principal['nombre_original']); ?>">
                <?php else: ?>
                    <div class="imagen_placeholder">
                        <p>📷 Sin imagen</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Miniaturas -->
            <?php if (!empty($imagenes_propiedad)): ?>
                <div class="galeria_miniaturas">
                    <?php foreach ($imagenes_propiedad as $img): ?>
                        <div class="miniatura_item <?php echo $img['es_principal'] ? 'activa' : ''; ?>">
                            <img src="<?php echo e($img['ruta_archivo']); ?>" alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Zona de carga -->
            <div class="carga_imagenes">
                <div class="area_drop" id="areaDrop">
                    <p>📤 Arrastra imágenes aquí o <a href="#" onclick="document.getElementById('inputImagenes').click(); return false;">haz clic</a></p>
                    <input type="file" id="inputImagenes" name="imagenes[]" multiple accept="image/*" style="display: none;">
                </div>
            </div>
        </div>
    </div>

    <div class="detalle_layout">
        <div class="detalle_col detalle_col--info">
            <div class="tarjeta_ficha tarjeta_ficha--compacta">
                <?php if ($mensaje_error): ?>
                    <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
                <?php endif; ?>
                <?php if ($mensaje_exito): ?>
                    <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
                <?php endif; ?>
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

                <div class="area_botones acciones_inline">
                    <button type="submit" name="guardar_cambios" class="btn_guardar">Guardar Cambios</button>
                    <button type="submit" name="eliminar_propiedad" class="btn_peligro" data-confirm="¿Eliminar esta propiedad? Esta accion no se puede deshacer.">Eliminar</button>
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

// GALERÍA DE IMÁGENES
const areaDrop = document.getElementById('areaDrop');
if (areaDrop) {
    // Eventos drag-drop
    areaDrop.addEventListener('dragover', (e) => {
        e.preventDefault();
        areaDrop.style.backgroundColor = '#f0f0f0';
    });

    areaDrop.addEventListener('dragleave', () => {
        areaDrop.style.backgroundColor = '';
    });

    areaDrop.addEventListener('drop', (e) => {
        e.preventDefault();
        areaDrop.style.backgroundColor = '';
        const archivos = e.dataTransfer.files;
        subirImagenes(archivos);
    });

    // Evento del input file
    const inputImagenes = document.getElementById('inputImagenes');
    if (inputImagenes) {
        inputImagenes.addEventListener('change', (e) => {
            subirImagenes(e.target.files);
        });
    }
}

// Miniaturas clickeables
const miniaturas = document.querySelectorAll('.miniatura_item img');
miniaturas.forEach((img, index) => {
    img.addEventListener('click', () => {
        console.log('Click en miniatura', index);
        // Cambiar imagen principal
        const principal = document.querySelector('.galeria_principal img');
        if (principal) {
            principal.src = img.src;
        }
    });
});

// Función para subir imágenes
function subirImagenes(archivos) {
    const propiedadId = <?php echo (int) $_GET['propiedad'] ?? 0; ?>;
    
    if (!propiedadId) {
        alert('Propiedad inválida');
        return;
    }

    // Mostrar carga
    const areaDrop = document.getElementById('areaDrop');
    areaDrop.innerHTML = '<p>⏳ Cargando...</p>';

    const formData = new FormData();
    formData.append('propiedad_id', propiedadId);
    
    for (let archivo of archivos) {
        if (archivo.type.startsWith('image/')) {
            formData.append('imagenes[]', archivo);
        }
    }

    fetch('/api/imagenes.php?action=subir', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.exito) {
            // Recargar la página
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Error desconocido'));
            areaDrop.innerHTML = '<p>📤 Arrastra imágenes aquí o <a href="#" onclick="document.getElementById(\'inputImagenes\').click(); return false;">haz clic</a></p>';
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error al subir imágenes');
        areaDrop.innerHTML = '<p>📤 Arrastra imágenes aquí o <a href="#" onclick="document.getElementById(\'inputImagenes\').click(); return false;">haz clic</a></p>';
    });
}
</script>
