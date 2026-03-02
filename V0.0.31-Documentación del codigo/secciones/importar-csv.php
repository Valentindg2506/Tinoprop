<?php
/**
 * ========================================================================================
 * ARCHIVO: importar-csv.php
 * SECCIÓN: Importación Masiva de Datos desde CSV
 * ========================================================================================
 *
 * PROPÓSITO:
 * Permite cargar datos de clientes y propiedades de forma masiva desde archivos CSV.
 * En vez de crear clientes o propiedades uno por uno, el agente puede preparar un
 * archivo CSV (un archivo de texto con datos separados por comas, compatible con
 * Excel y Google Sheets) con muchos registros y cargarlos todos de golpe.
 *
 * ¿QUÉ ES UN ARCHIVO CSV?
 * CSV significa "Comma Separated Values" (Valores Separados por Comas).
 * Es un formato de archivo de texto plano donde cada línea es un registro
 * y los campos se separan con comas. Ejemplo:
 *   nombre,apellido,telefono,email
 *   Juan,García,612345678,juan@email.com
 *
 * FUNCIONALIDAD:
 * - Importar clientes: nombre, apellido, teléfono, email, operación.
 *   Se puede elegir si son clientes vendedor o comprador.
 * - Importar propiedades: título, tipo, operación, precio, ubicación, etc.
 *   Se puede elegir el equipo (vendedor/comprador).
 * - Muestra un informe tras la importación: cuántos se importaron y cuáles fallaron.
 * - Ofrece plantillas CSV descargables para que el usuario sepa el formato esperado.
 *
 * SEGURIDAD:
 * - csrf_verify(): protección contra envíos de formularios falsificados.
 * - enctype="multipart/form-data": permite subir archivos al servidor.
 * - Las funciones importar_csv_clientes() e importar_csv_propiedades() validan
 *   cada fila internamente y registran errores individualmente.
 *
 * @version V0.0.31 (V0.0.28 original)
 */

/* Bootstrap: inicializa sesión, BD, funciones y autenticación. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Conexión a base de datos. */
$pdo = db();

/* Mensajes flash y variable para almacenar el resultado de la importación. */
$mensaje_exito = flash_get('success');
$mensaje_error = flash_get('error');
$resultado = null; // Contendrá el array con 'importados' y 'errores' tras importar.

/* ═════════════════════════════════════════════════════════════════════════════
   CONTROLADOR POST — Procesa la subida de archivos CSV.
   Solo se ejecuta cuando el usuario envía un formulario con un archivo.
   ═════════════════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Protección CSRF. */
    csrf_verify();
    /* ── IMPORTAR CLIENTES DESDE CSV ───────────────────────────────────────
       Verifica que: 1) se pulsó el botón de importar clientes y
                     2) se subió un archivo CSV correctamente.
       $_FILES['csv_file']['tmp_name'] contiene la ruta temporal del archivo subido. */
    if (isset($_POST['importar_clientes']) && !empty($_FILES['csv_file']['tmp_name'])) {
        /* El tipo de cliente (vendedor/comprador) se selecciona en el formulario. */
        $tipo = $_POST['tipo_cliente'] ?? 'vendedor';

        /* importar_csv_clientes() lee el CSV línea por línea, valida cada fila
           y la inserta en la tabla 'clientes'. Devuelve un array con:
           - 'importados': número de clientes insertados correctamente.
           - 'errores': array con mensajes de error por cada fila que falló. */
        $resultado = importar_csv_clientes($pdo, $_FILES['csv_file']['tmp_name'], $tipo);

        if ($resultado['importados'] > 0) {
            /* Registramos la importación en el log de actividad. */
            actividad_registrar($pdo, 'importar', 'cliente', 0, "Importación CSV: {$resultado['importados']} clientes ({$tipo})");
            flash_set('success', "Se importaron {$resultado['importados']} clientes correctamente.");
        }
        if (!empty($resultado['errores'])) {
            /* Mostramos máximo 5 errores para no saturar la pantalla. */
            flash_set('error', 'Errores: ' . implode(' | ', array_slice($resultado['errores'], 0, 5)));
        }
        /* No redirigimos (no hay header('Location:...')) para que el usuario
           pueda ver el informe del resultado en la misma página. */
        $mensaje_exito = flash_get('success');
        $mensaje_error = flash_get('error');
    }

    /* ── IMPORTAR PROPIEDADES DESDE CSV ────────────────────────────────────
       Funciona igual que importar clientes: lee el CSV, valida cada fila
       y la inserta en la tabla 'propiedades' asociada al equipo seleccionado. */
    if (isset($_POST['importar_propiedades']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $equipo = $_POST['equipo'] ?? 'vendedor';

        /* importar_csv_propiedades() procesa el archivo y devuelve
           el resultado con 'importados' y 'errores'. */
        $resultado = importar_csv_propiedades($pdo, $_FILES['csv_file']['tmp_name'], $equipo);

        if ($resultado['importados'] > 0) {
            actividad_registrar($pdo, 'importar', 'propiedad', 0, "Importación CSV: {$resultado['importados']} propiedades");
            flash_set('success', "Se importaron {$resultado['importados']} propiedades correctamente.");
        }
        if (!empty($resultado['errores'])) {
            flash_set('error', 'Errores: ' . implode(' | ', array_slice($resultado['errores'], 0, 5)));
        }
        $mensaje_exito = flash_get('success');
        $mensaje_error = flash_get('error');
    }
}
/* Fin del controlador POST. A partir de aquí solo hay HTML. */
?>

<!-- ======================================================================
     VISTA HTML — Interfaz para importar datos desde CSV.
     ====================================================================== -->

<!-- Encabezado de la sección. -->
<div class="seccion_header animate_fadeIn">
    <h2>📥 Importar Datos (CSV)</h2>
    <p class="seccion_subtitulo">Carga masiva de clientes y propiedades desde archivos CSV.</p>
</div>

<?php if ($mensaje_exito): ?><div class="alerta_exito animate_fadeIn"><?php echo e($mensaje_exito); ?></div><?php endif; ?>
<?php if ($mensaje_error): ?><div class="alerta_error animate_fadeIn"><?php echo e($mensaje_error); ?></div><?php endif; ?>

<!-- ── INFORME DE RESULTADOS ──────────────────────────────────────────────
     Se muestra solo tras una importación. Muestra cuántos registros
     se importaron correctamente y cuántos tuvieron errores.
     Los errores se pueden desplegar para ver el detalle por fila. -->
<?php if ($resultado): ?>
<div class="importar_resultado animate_fadeIn">
    <h3>📊 Resultado de la importación</h3>
    <div class="importar_stats">
        <div class="stat_box stat_box--ok"><span class="stat_num"><?php echo $resultado['importados']; ?></span><span class="stat_label">Importados</span></div>
        <div class="stat_box stat_box--err"><span class="stat_num"><?php echo count($resultado['errores']); ?></span><span class="stat_label">Errores</span></div>
    </div>
    <?php if (!empty($resultado['errores'])): ?>
    <details class="importar_errores_detail">
        <summary>Ver errores detallados</summary>
        <ul>
            <?php foreach ($resultado['errores'] as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── FORMULARIOS DE IMPORTACIÓN ───────────────────────────────────────────
     Dos tarjetas lado a lado: una para importar clientes y otra para
     importar propiedades. Cada una tiene su propio formulario con:
     - Un selector de tipo/equipo.
     - Un área para arrastrar o seleccionar el archivo CSV.
     - Un botón de importar.
     enctype="multipart/form-data" es OBLIGATORIO para poder subir archivos. -->
<div class="importar_grid animate_fadeIn">
    <!-- Tarjeta: Importar clientes -->
    <div class="importar_card">
        <div class="importar_card_header">
            <h3>👥 Importar Clientes</h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="form_grupo">
                <label>Tipo de cliente</label>
                <select name="tipo_cliente">
                    <option value="vendedor">Vendedor</option>
                    <option value="comprador">Comprador</option>
                </select>
            </div>
            <div class="form_grupo">
                <label>Archivo CSV</label>
                <div class="area_drop_csv" onclick="this.querySelector('input[type=file]').click()">
                    <p>📄 Haz clic o arrastra un archivo CSV</p>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required style="display:none" onchange="this.parentNode.querySelector('p').textContent = '✅ ' + this.files[0].name">
                </div>
            </div>
            <button type="submit" name="importar_clientes" class="btn_guardar">Importar clientes</button>
        </form>
        <div class="importar_ejemplo">
            <h4>Formato esperado:</h4>
            <code>nombre,apellido,telefono,email,operacion</code>
            <p>Ejemplo:</p>
            <pre>Juan,García,612345678,juan@email.com,vendedor
María,López,698765432,maria@email.com,comprador</pre>
        </div>
    </div>

    <!-- Tarjeta: Importar propiedades -->
    <div class="importar_card">
        <div class="importar_card_header">
            <h3>🏠 Importar Propiedades</h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?php /* Token CSRF obligatorio en todo formulario POST. */ ?>
            <?php echo csrf_field(); ?>
            <div class="form_grupo">
                <label>Equipo</label>
                <select name="equipo">
                    <option value="vendedor">Vendedor</option>
                    <option value="comprador">Comprador</option>
                </select>
            </div>
            <div class="form_grupo">
                <label>Archivo CSV</label>
                <div class="area_drop_csv" onclick="this.querySelector('input[type=file]').click()">
                    <p>📄 Haz clic o arrastra un archivo CSV</p>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required style="display:none" onchange="this.parentNode.querySelector('p').textContent = '✅ ' + this.files[0].name">
                </div>
            </div>
            <button type="submit" name="importar_propiedades" class="btn_guardar">Importar propiedades</button>
        </form>
        <div class="importar_ejemplo">
            <h4>Formato esperado:</h4>
            <code>titulo,tipo,operacion,precio,ubicacion,direccion,metros,habitaciones,banos</code>
            <p>Ejemplo:</p>
            <pre>Piso centro,Piso,venta,150000,Madrid Centro,C/ Gran Vía 10,80,3,2
Local comercial,Local,alquiler,800,Sevilla,Av. Constitución 5,120,,1</pre>
        </div>
    </div>
</div>

<!-- ── PLANTILLAS DESCARGABLES ──────────────────────────────────────────────
     Enlaces que generan archivos CSV de muestra directamente en el navegador
     usando data URIs (el contenido del CSV está codificado en la propia URL).
     Esto ahorra tener que crear archivos separados en el servidor. -->
<div class="importar_plantillas animate_fadeIn">
    <h3>📋 Descargar plantillas</h3>
    <div class="plantillas_btns">
        <a href="data:text/csv;charset=utf-8,nombre,apellido,telefono,email,operacion%0AJuan,García,612345678,juan@email.com,vendedor" download="plantilla_clientes.csv" class="btn_secundario">📥 Plantilla Clientes</a>
        <a href="data:text/csv;charset=utf-8,titulo,tipo,operacion,precio,ubicacion,direccion,metros,habitaciones,banos%0APiso ejemplo,Piso,venta,150000,Madrid,Calle 1,80,3,2" download="plantilla_propiedades.csv" class="btn_secundario">📥 Plantilla Propiedades</a>
    </div>
</div>
