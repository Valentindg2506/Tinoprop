<?php
$pageTitle = 'Importar Prospectos';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $resultado = ['tipo' => 'danger', 'mensaje' => 'Error al subir el archivo.'];
    } else {
        $file = $_FILES['csv_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $resultado = ['tipo' => 'danger', 'mensaje' => 'El archivo debe ser CSV.'];
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $resultado = ['tipo' => 'danger', 'mensaje' => 'No se pudo leer el archivo.'];
            } else {
                // Detectar BOM UTF-8
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                // Leer encabezados
                $headerLine = fgets($handle);
                // Detectar separador (coma o punto y coma)
                $separator = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
                rewind($handle);
                // Skip BOM again if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);

                $headers = fgetcsv($handle, 0, $separator);
                $headers = array_map('trim', $headers);
                $headers = array_map('mb_strtolower', $headers);

                // Mapeo de columnas CSV a campos de la tabla
                $map = [
                    'nombre' => ['nombre', 'name', 'propietario'],
                    'telefono' => ['telefono', 'tel', 'telefono1', 'phone', 'tel.'],
                    'telefono2' => ['telefono2', 'tel2', 'phone2'],
                    'email' => ['email', 'correo', 'e-mail', 'mail'],
                    'etapa' => ['etapa', 'stage', 'pipeline'],
                    'tipo_propiedad' => ['tipo_propiedad', 'tipo', 'type', 'inmueble'],
                    'direccion' => ['direccion', 'dirección', 'address', 'dir'],
                    'barrio' => ['barrio', 'zona', 'neighborhood'],
                    'localidad' => ['localidad', 'ciudad', 'city'],
                    'provincia' => ['provincia', 'province'],
                    'codigo_postal' => ['codigo_postal', 'cp', 'postal'],
                    'precio_estimado' => ['precio_estimado', 'precio', 'price', 'precio est.'],
                    'precio_propietario' => ['precio_propietario', 'precio prop.'],
                    'superficie' => ['superficie', 'm2', 'm²', 'metros', 'sup'],
                    'habitaciones' => ['habitaciones', 'hab', 'rooms', 'hab.'],
                    'enlace' => ['enlace', 'link', 'url'],
                    'fecha_contacto' => ['fecha_contacto', 'fecha contacto', 'first contact'],
                    'hora_contacto' => ['hora_contacto', 'hora contacto', 'contact time'],
                    'mejor_horario_contacto' => ['mejor_horario_contacto', 'mejor horario', 'best contact time'],
                    'fecha_publicacion_propiedad' => ['fecha_publicacion_propiedad', 'fecha publicacion', 'publication date'],
                    'comision' => ['comision', 'comisión', 'commission'],
                    'notas' => ['notas', 'notes', 'observaciones'],
                    'reformas' => ['reformas', 'reforms'],
                    'historial_contactos' => ['historial_contactos', 'historial', 'contactos'],
                ];

                // Determinar que columnas del CSV coinciden
                $columnMap = [];
                foreach ($map as $dbField => $aliases) {
                    foreach ($headers as $idx => $header) {
                        if (in_array($header, $aliases)) {
                            $columnMap[$dbField] = $idx;
                            break;
                        }
                    }
                }

                if (!isset($columnMap['nombre'])) {
                    $resultado = ['tipo' => 'danger', 'mensaje' => 'El CSV debe contener al menos una columna "Nombre". Columnas detectadas: ' . implode(', ', $headers)];
                } else {
                    $importados = 0;
                    $errores = 0;
                    $linea = 1;
                    $erroresList = [];

                    // Obtener max referencia
                    $stmtRef = $db->query("SELECT MAX(CAST(SUBSTRING(referencia, 3) AS UNSIGNED)) as max_ref FROM prospectos WHERE referencia LIKE 'PR%'");
                    $maxRef = $stmtRef->fetch()['max_ref'] ?? 0;

                    $etapasValidas = ['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado'];

                    while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                        $linea++;
                        // Skip empty rows
                        if (empty(array_filter($row))) continue;

                        $nombre = isset($columnMap['nombre']) ? trim($row[$columnMap['nombre']] ?? '') : '';
                        if (empty($nombre)) {
                            $errores++;
                            $erroresList[] = "Linea $linea: Nombre vacio, saltando.";
                            continue;
                        }

                        $maxRef++;
                        $referencia = 'PR' . str_pad($maxRef, 3, '0', STR_PAD_LEFT);

                        $data = [
                            'referencia' => $referencia,
                            'nombre' => $nombre,
                            'telefono' => isset($columnMap['telefono']) ? trim($row[$columnMap['telefono']] ?? '') ?: null : null,
                            'telefono2' => isset($columnMap['telefono2']) ? trim($row[$columnMap['telefono2']] ?? '') ?: null : null,
                            'email' => isset($columnMap['email']) ? trim($row[$columnMap['email']] ?? '') ?: null : null,
                            'tipo_propiedad' => isset($columnMap['tipo_propiedad']) ? trim($row[$columnMap['tipo_propiedad']] ?? '') ?: null : null,
                            'direccion' => isset($columnMap['direccion']) ? trim($row[$columnMap['direccion']] ?? '') ?: null : null,
                            'barrio' => isset($columnMap['barrio']) ? trim($row[$columnMap['barrio']] ?? '') ?: null : null,
                            'localidad' => isset($columnMap['localidad']) ? trim($row[$columnMap['localidad']] ?? '') ?: null : null,
                            'provincia' => isset($columnMap['provincia']) ? trim($row[$columnMap['provincia']] ?? '') ?: null : null,
                            'codigo_postal' => isset($columnMap['codigo_postal']) ? trim($row[$columnMap['codigo_postal']] ?? '') ?: null : null,
                            'enlace' => isset($columnMap['enlace']) ? trim($row[$columnMap['enlace']] ?? '') ?: null : null,
                            'notas' => isset($columnMap['notas']) ? trim($row[$columnMap['notas']] ?? '') ?: null : null,
                            'reformas' => isset($columnMap['reformas']) ? trim($row[$columnMap['reformas']] ?? '') ?: null : null,
                            'historial_contactos' => isset($columnMap['historial_contactos']) ? trim($row[$columnMap['historial_contactos']] ?? '') ?: null : null,
                            'agente_id' => currentUserId(),
                            'activo' => 1,
                            'etapa' => 'contactado',
                            'estado' => 'nuevo_lead',
                        ];

                        // Parse numeric fields
                        if (isset($columnMap['precio_estimado'])) {
                            $val = trim($row[$columnMap['precio_estimado']] ?? '');
                            $val = preg_replace('/[^\d.,]/', '', $val);
                            $val = str_replace(',', '.', $val);
                            $data['precio_estimado'] = $val ? floatval($val) : null;
                        }
                        if (isset($columnMap['precio_propietario'])) {
                            $val = trim($row[$columnMap['precio_propietario']] ?? '');
                            $val = preg_replace('/[^\d.,]/', '', $val);
                            $val = str_replace(',', '.', $val);
                            $data['precio_propietario'] = $val ? floatval($val) : null;
                        }
                        if (isset($columnMap['superficie'])) {
                            $val = trim($row[$columnMap['superficie']] ?? '');
                            $val = preg_replace('/[^\d.,]/', '', $val);
                            $val = str_replace(',', '.', $val);
                            $data['superficie'] = $val ? floatval($val) : null;
                        }
                        if (isset($columnMap['habitaciones'])) {
                            $val = trim($row[$columnMap['habitaciones']] ?? '');
                            $data['habitaciones'] = is_numeric($val) ? intval($val) : null;
                        }
                        if (isset($columnMap['comision'])) {
                            $val = trim($row[$columnMap['comision']] ?? '');
                            $val = preg_replace('/[^\d.,]/', '', $val);
                            $val = str_replace(',', '.', $val);
                            $data['comision'] = $val ? floatval($val) : null;
                        }
                        if (isset($columnMap['fecha_contacto'])) {
                            $val = trim($row[$columnMap['fecha_contacto']] ?? '');
                            if ($val) {
                                // Try different date formats
                                $dt = date_create_from_format('d/m/Y', $val) ?: date_create_from_format('Y-m-d', $val) ?: date_create_from_format('d-m-Y', $val);
                                $data['fecha_contacto'] = $dt ? $dt->format('Y-m-d') : null;
                            }
                        }
                        if (isset($columnMap['fecha_publicacion_propiedad'])) {
                            $val = trim($row[$columnMap['fecha_publicacion_propiedad']] ?? '');
                            if ($val) {
                                $dt = date_create_from_format('d/m/Y', $val) ?: date_create_from_format('Y-m-d', $val) ?: date_create_from_format('d-m-Y', $val);
                                $data['fecha_publicacion_propiedad'] = $dt ? $dt->format('Y-m-d') : null;
                            }
                        }
                        if (isset($columnMap['hora_contacto'])) {
                            $val = trim($row[$columnMap['hora_contacto']] ?? '');
                            if ($val && preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $val)) {
                                $data['hora_contacto'] = strlen($val) === 5 ? $val . ':00' : $val;
                            }
                        }
                        if (isset($columnMap['mejor_horario_contacto'])) {
                            $val = trim($row[$columnMap['mejor_horario_contacto']] ?? '');
                            $data['mejor_horario_contacto'] = $val ?: null;
                        }
                        if (isset($columnMap['etapa'])) {
                            $val = trim(mb_strtolower($row[$columnMap['etapa']] ?? ''));
                            if (in_array($val, $etapasValidas)) {
                                $data['etapa'] = $val;
                            }
                        }

                        try {
                            $fields = array_keys($data);
                            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                            $db->prepare("INSERT INTO prospectos (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                            $importados++;
                        } catch (Exception $e) {
                            $errores++;
                            $erroresList[] = "Linea $linea: " . $e->getMessage();
                        }
                    }

                    fclose($handle);
                    registrarActividad('importar', 'prospecto', 0, "Importacion CSV: $importados importados, $errores errores");

                    $msg = "<strong>$importados prospectos importados correctamente.</strong>";
                    if ($errores > 0) $msg .= "<br>$errores lineas con errores.";
                    if (!empty($erroresList)) $msg .= '<br><small>' . implode('<br>', array_slice($erroresList, 0, 10)) . '</small>';

                    $resultado = ['tipo' => $errores > 0 ? 'warning' : 'success', 'mensaje' => $msg];
                }
            }
        }
    }
}
?>

<?php if ($resultado): ?>
<div class="alert alert-<?= $resultado['tipo'] ?>"><?= $resultado['mensaje'] ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-upload"></i> Importar Prospectos desde CSV</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <div class="mb-4">
                        <label class="form-label">Archivo CSV</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <div class="form-text">Selecciona un archivo CSV con los datos de los prospectos. Separador: coma (,) o punto y coma (;).</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Importar</button>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">Cancelar</a>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle"></i> Formato del CSV</div>
            <div class="card-body">
                <p>El archivo debe tener al menos la columna <strong>Nombre</strong>. Las columnas se detectan automáticamente por su nombre:</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Columnas aceptadas</th>
                                <th>Ejemplo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Nombre *</strong></td><td>nombre, name, propietario</td><td>Juan García</td></tr>
                            <tr><td>Teléfono</td><td>telefono, tel, phone</td><td>612345678</td></tr>
                            <tr><td>Email</td><td>email, correo, mail</td><td>juan@email.com</td></tr>
                            <tr><td>Tipo Propiedad</td><td>tipo_propiedad, tipo, inmueble</td><td>Piso</td></tr>
                            <tr><td>Dirección</td><td>direccion, dirección, address</td><td>Calle Mayor 1</td></tr>
                            <tr><td>Barrio</td><td>barrio, zona</td><td>Centro</td></tr>
                            <tr><td>Localidad</td><td>localidad, ciudad, city</td><td>Valencia</td></tr>
                            <tr><td>Provincia</td><td>provincia, province</td><td>Valencia</td></tr>
                            <tr><td>Precio Estimado</td><td>precio_estimado, precio, price</td><td>250000</td></tr>
                            <tr><td>Superficie (m²)</td><td>superficie, m2, metros</td><td>85</td></tr>
                            <tr><td>Habitaciones</td><td>habitaciones, hab, rooms</td><td>3</td></tr>
                            <tr><td>Enlace</td><td>enlace, link, url</td><td>https://...</td></tr>
                            <tr><td>Fecha Contacto</td><td>fecha_contacto</td><td>15/03/2026</td></tr>
                            <tr><td>Fecha Publicación</td><td>fecha_publicacion_propiedad</td><td>01/03/2026</td></tr>
                            <tr><td>Hora Contacto</td><td>hora_contacto</td><td>10:30</td></tr>
                            <tr><td>Mejor Horario Contacto</td><td>mejor_horario_contacto</td><td>10:00 - 13:00</td></tr>
                            <tr><td>Comisión</td><td>comision, comisión</td><td>3.5</td></tr>
                            <tr><td>Notas</td><td>notas, notes</td><td>Interesado en vender</td></tr>
                            <tr><td>Reformas</td><td>reformas</td><td>Necesita baño</td></tr>
                            <tr><td>Historial</td><td>historial_contactos, historial</td><td>Llamada 15/3</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-lightbulb"></i> <strong>Consejo:</strong> Las referencias (PR001, PR002...) se generan automáticamente. La etapa se establece como "Contactado" y el estado como "Nuevo lead" por defecto.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
