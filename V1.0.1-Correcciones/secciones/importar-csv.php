<?php
/*
 * Sección: Importar CSV — V0.0.28
 * Rol: importación masiva de clientes y propiedades desde archivos CSV.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

$pdo = db();
$mensaje_exito = flash_get('success');
$mensaje_error = flash_get('error');
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['importar_clientes']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $tipo = in_array($_POST['tipo_cliente'] ?? '', ['vendedor', 'comprador'], true) ? $_POST['tipo_cliente'] : 'vendedor';
        $resultado = importar_csv_clientes($pdo, $_FILES['csv_file']['tmp_name'], $tipo);
        if ($resultado['importados'] > 0) {
            actividad_registrar($pdo, 'importar', 'cliente', 0, "Importación CSV: {$resultado['importados']} clientes ({$tipo})");
            flash_set('success', "Se importaron {$resultado['importados']} clientes correctamente.");
        }
        if (!empty($resultado['errores'])) {
            flash_set('error', 'Errores: ' . implode(' | ', array_slice($resultado['errores'], 0, 5)));
        }
        // No redirect so user sees results
        $mensaje_exito = flash_get('success');
        $mensaje_error = flash_get('error');
    }

    if (isset($_POST['importar_propiedades']) && !empty($_FILES['csv_file']['tmp_name'])) {
        $equipo = in_array($_POST['equipo'] ?? '', ['vendedor', 'comprador'], true) ? $_POST['equipo'] : 'vendedor';
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
?>

<div class="seccion_header animate_fadeIn">
    <h2>📥 Importar Datos (CSV)</h2>
    <p class="seccion_subtitulo">Carga masiva de clientes y propiedades desde archivos CSV.</p>
</div>

<?php if ($mensaje_exito): ?><div class="alerta_exito animate_fadeIn"><?php echo e($mensaje_exito); ?></div><?php endif; ?>
<?php if ($mensaje_error): ?><div class="alerta_error animate_fadeIn"><?php echo e($mensaje_error); ?></div><?php endif; ?>

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

<div class="importar_grid animate_fadeIn">
    <!-- Importar clientes -->
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

    <!-- Importar propiedades -->
    <div class="importar_card">
        <div class="importar_card_header">
            <h3>🏠 Importar Propiedades</h3>
        </div>
        <form method="POST" enctype="multipart/form-data">
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

<!-- Descargar plantilla -->
<div class="importar_plantillas animate_fadeIn">
    <h3>📋 Descargar plantillas</h3>
    <div class="plantillas_btns">
        <a href="data:text/csv;charset=utf-8,nombre,apellido,telefono,email,operacion%0AJuan,García,612345678,juan@email.com,vendedor" download="plantilla_clientes.csv" class="btn_secundario">📥 Plantilla Clientes</a>
        <a href="data:text/csv;charset=utf-8,titulo,tipo,operacion,precio,ubicacion,direccion,metros,habitaciones,banos%0APiso ejemplo,Piso,venta,150000,Madrid,Calle 1,80,3,2" download="plantilla_propiedades.csv" class="btn_secundario">📥 Plantilla Propiedades</a>
    </div>
</div>
