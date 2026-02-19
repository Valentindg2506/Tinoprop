<?php
/* Seccion: Detalle de Propiedad
   Descripcion: Vista moderna con galería y detalles de propiedad
*/
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$id_propiedad = (int) ($_GET['id'] ?? 0);
$origen = $_GET['origen'] ?? 'propiedades-vendedor';
$en_edicion = (int) ($_GET['editar'] ?? 0) === 1;
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

// Asegurar que tabla de imágenes existe
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

        if (!validar_requerido($titulo)) $errores[] = 'El título es obligatorio.';
        if (!validar_requerido($tipo)) $errores[] = 'El tipo es obligatorio.';
        if (!validar_enum($operacion, ['venta', 'alquiler'])) $errores[] = 'La operación no es válida.';
        if (!validar_enum($estado, ['Disponible', 'Reservado', 'Vendido'])) $errores[] = 'El estado no es válido.';
        if ($precio <= 0) $errores[] = 'El precio debe ser mayor a 0.';
        if (!validar_requerido($ubicacion)) $errores[] = 'La ubicación es obligatoria.';

        if (!empty($errores)) {
            flash_set('error', implode(' | ', $errores));
            header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
            exit;
        }

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
        $stmt->execute([
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
        ]);
        flash_set('success', 'Propiedad actualizada correctamente.');
        header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
        exit;
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
        header('Location: index.php?seccion=ver_propiedad&id=' . $id_propiedad . '&origen=' . urlencode($origen));
        exit;
    }

    if (isset($_POST['eliminar_propiedad'])) {
        $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'propiedad' AND entity_id = :id");
        $stmt->execute(['id' => $id_propiedad]);

        $stmt = $pdo->prepare('DELETE FROM imagenes_propiedades WHERE propiedad_id = :id');
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

// Cargar datos
$stmt = $pdo->prepare('SELECT * FROM propiedades WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id_propiedad]);
$propiedad = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$propiedad) {
    $propiedad = [
        'id' => 0,
        'titulo' => '',
        'tipo' => '',
        'precio' => 0,
        'operacion' => '',
        'estado' => '',
        'ubicacion' => '',
        'direccion' => '',
        'metros' => 0,
        'habitaciones' => 0,
        'banos' => 0,
        'referencia' => '',
        'descripcion' => '',
    ];
}

// Cargar imágenes
$imagenes = imagenes_obtener_propiedad($pdo, $id_propiedad);
$imagen_principal = imagenes_obtener_principal($pdo, $id_propiedad);

// Cargar notas
$stmt = $pdo->prepare(
    "SELECT tipo, texto, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS fecha
     FROM notas
     WHERE entity_type = 'propiedad' AND entity_id = :id
     ORDER BY created_at DESC"
);
$stmt->execute(['id' => $id_propiedad]);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="encabezado_detalle">
    <a href="index.php?seccion=<?php echo e($origen); ?>" class="btn_volver">⬅ Volver al listado</a>
    <h2>Detalle de Propiedad</h2>
</div>

<form action="" method="POST" enctype="multipart/form-data">
    <?php if ($mensaje_error): ?>
        <div class="alerta_error" style="margin-bottom: 20px;"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito" style="margin-bottom: 20px;"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>

    <!-- SECCIÓN DE GALERÍA DE IMÁGENES -->
    <div class="galeria_seccion">
        <div class="galeria_contenedor">
            <!-- Imagen Principal -->
            <div class="galeria_principal">
                <?php if ($imagen_principal): ?>
                    <img id="imagenPrincipal" src="<?php echo e($imagen_principal['ruta_archivo']); ?>" alt="<?php echo e($imagen_principal['nombre_original']); ?>">
                <?php else: ?>
                    <div class="imagen_placeholder">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        <p>Sin imagen</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Miniaturas -->
            <?php if (!empty($imagenes_propiedad)): ?>
                <div class="galeria_miniaturas">
                    <?php foreach ($imagenes_propiedad as $imagen): ?>
                        <div class="miniatura_item <?php echo $imagen['es_principal'] ? 'activa' : ''; ?>">
                            <img src="<?php echo e($imagen['ruta_archivo']); ?>" alt="<?php echo e($imagen['nombre_original']); ?>" onclick="cambiarImagenPrincipal(<?php echo $imagen['id']; ?>, '<?php echo e($imagen['ruta_archivo']); ?>')">
                            <div class="miniatura_toolbar">
                                <?php if (!$imagen['es_principal']): ?>
                                    <button type="button" class="miniatura_btn miniatura_star" title="Marcar como principal" onclick="marcarImagenPrincipal(<?php echo $imagen['id']; ?>, event)">★</button>
                                <?php else: ?>
                                    <span class="miniatura_principal">★ Actual</span>
                                <?php endif; ?>
                                <button type="button" class="miniatura_btn miniatura_delete" title="Eliminar" onclick="eliminarImagen(<?php echo $imagen['id']; ?>, event)">✕</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Carga de Imágenes -->
        <div class="carga_imagenes">
            <div class="area_drop" id="areaDrop">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <p>Arrastra imágenes aquí o haz clic para seleccionar</p>
                <input type="file" id="inputImagenes" name="imagenes[]" multiple accept="image/*" style="display: none;">
            </div>
            <div id="cargaProgreso" style="display: none;">
                <div class="progress_bar">
                    <div class="progress_fill"></div>
                </div>
                <p>Cargando...</p>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE DETALLES -->
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
// Activar edición de campos
function activarEdicion(idCampo) {
    let input = document.getElementById(idCampo);
    input.removeAttribute('readonly');
    input.focus();
    input.classList.add('editando');
}

// Drag & Drop para imágenes
const areaDrop = document.getElementById('areaDrop');
const inputImagenes = document.getElementById('inputImagenes');

areaDrop.addEventListener('click', () => inputImagenes.click());

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    areaDrop.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    areaDrop.addEventListener(eventName, () => areaDrop.classList.add('activa'), false);
});

['dragleave', 'drop'].forEach(eventName => {
    areaDrop.addEventListener(eventName, () => areaDrop.classList.remove('activa'), false);
});

areaDrop.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    inputImagenes.files = files;
    cargarImagenes(files);
}, false);

inputImagenes.addEventListener('change', (e) => {
    cargarImagenes(e.target.files);
});

function cargarImagenes(files) {
    if (files.length === 0) return;

    const formData = new FormData();
    formData.append('action', 'subir');
    formData.append('propiedad_id', <?php echo $id_propiedad; ?>);

    for (let file of files) {
        formData.append('archivo', file);
    }

    // Mostrar progreso
    document.getElementById('areaDrop').style.display = 'none';
    document.getElementById('cargaProgreso').style.display = 'block';

    fetch('api/imagenes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('cargaProgreso').style.display = 'none';
        document.getElementById('areaDrop').style.display = 'block';

        if (data.success) {
            // Recargar la página para mostrar las nuevas imágenes
            location.reload();
        } else {
            alert('Error al cargar las imágenes: ' + (data.error || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('cargaProgreso').style.display = 'none';
        document.getElementById('areaDrop').style.display = 'block';
        alert('Error al cargar las imágenes');
    });
}

function cambiarImagenPrincipal(imagenId, ruta) {
    document.getElementById('imagenPrincipal').src = ruta;
}

function marcarImagenPrincipal(imagenId, event) {
    event.preventDefault();
    
    fetch('api/imagenes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'marcar-principal',
            id: imagenId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo marcar la imagen'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al marcar la imagen');
    });
}

function eliminarImagen(imagenId, event) {
    event.preventDefault();
    
    if (!confirm('¿Eliminar esta imagen?')) return;

    fetch('api/imagenes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'eliminar',
            id: imagenId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo eliminar la imagen'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar la imagen');
    });
}
</script>
