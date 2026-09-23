<?php
/**
 * Exportacion de datos a CSV
 * Compatible con Excel (BOM UTF-8 + separador punto y coma)
 */

/**
 * Exportar array de datos a CSV y forzar descarga
 */
function exportCSV($filename, $headers, $rows, $separator = ';') {
    // Headers HTTP para descarga
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // BOM para que Excel reconozca UTF-8
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Cabeceras
    fputcsv($output, $headers, $separator);

    // Datos
    foreach ($rows as $row) {
        fputcsv($output, $row, $separator);
    }

    fclose($output);
    exit;
}

/**
 * Exportar propiedades
 */
function exportarPropiedades($propiedades) {
    $headers = ['Referencia', 'Titulo', 'Tipo', 'Operacion', 'Estado', 'Precio', 'Comunidad',
        'Sup. Construida', 'Sup. Util', 'Habitaciones', 'Banos', 'Planta',
        'Direccion', 'Localidad', 'Provincia', 'CP',
        'Ref. Catastral', 'Cert. Energetica',
        'Ascensor', 'Garaje', 'Trastero', 'Terraza', 'Piscina', 'A/C',
        'Agente', 'Fecha Captacion', 'Descripcion'];

    $rows = [];
    foreach ($propiedades as $p) {
        $rows[] = [
            $p['referencia'], $p['titulo'], $p['tipo'], $p['operacion'], $p['estado'],
            $p['precio'], $p['precio_comunidad'] ?? '',
            $p['superficie_construida'] ?? '', $p['superficie_util'] ?? '',
            $p['habitaciones'] ?? '', $p['banos'] ?? '', $p['planta'] ?? '',
            $p['direccion'] ?? '', $p['localidad'] ?? '', $p['provincia'] ?? '', $p['codigo_postal'] ?? '',
            $p['referencia_catastral'] ?? '', $p['certificacion_energetica'] ?? '',
            $p['ascensor'] ? 'Si' : 'No', $p['garaje_incluido'] ? 'Si' : 'No',
            $p['trastero_incluido'] ? 'Si' : 'No', $p['terraza'] ? 'Si' : 'No',
            $p['piscina'] ? 'Si' : 'No', $p['aire_acondicionado'] ? 'Si' : 'No',
            $p['agente_nombre'] ?? '', $p['fecha_captacion'] ?? '',
            strip_tags($p['descripcion'] ?? ''),
        ];
    }

    exportCSV('propiedades_' . date('Y-m-d') . '.csv', $headers, $rows);
}

/**
 * Exportar clientes
 */
function exportarClientes($clientes) {
    $headers = ['Nombre', 'Apellidos', 'Email', 'Telefono', 'Telefono 2', 'DNI/NIE/CIF',
        'Tipo', 'Origen', 'Direccion', 'Localidad', 'Provincia', 'CP',
        'Operacion Interes', 'Presupuesto Min', 'Presupuesto Max', 'Zona Interes',
        'Habitaciones Min', 'Superficie Min', 'Agente', 'Fecha Alta', 'Notas'];

    $rows = [];
    foreach ($clientes as $c) {
        $rows[] = [
            $c['nombre'], $c['apellidos'] ?? '', $c['email'] ?? '',
            $c['telefono'] ?? '', $c['telefono2'] ?? '', $c['dni_nie_cif'] ?? '',
            $c['tipo'], $c['origen'] ?? '',
            $c['direccion'] ?? '', $c['localidad'] ?? '', $c['provincia'] ?? '', $c['codigo_postal'] ?? '',
            $c['operacion_interes'] ?? '', $c['presupuesto_min'] ?? '', $c['presupuesto_max'] ?? '',
            $c['zona_interes'] ?? '', $c['habitaciones_min'] ?? '', $c['superficie_min'] ?? '',
            $c['agente_nombre'] ?? '', $c['created_at'] ?? '',
            strip_tags($c['notas'] ?? ''),
        ];
    }

    exportCSV('clientes_' . date('Y-m-d') . '.csv', $headers, $rows);
}

/**
 * Exportar prospectos
 */
function exportarProspectos($prospectos) {
    $headers = ['Referencia', 'Nombre', 'Telefono', 'Telefono 2', 'Email',
        'Etapa', 'Estado', 'Tipo Propiedad', 'Direccion', 'Barrio', 'Localidad', 'Provincia', 'CP',
        'Precio Estimado', 'Precio Propietario', 'Superficie m2', 'Habitaciones',
        'Enlace', 'Fecha Publicacion', 'Fecha Contacto', 'Hora Contacto', 'Mejor Horario Contacto', 'Prox. Contacto', 'Comision %', 'Exclusividad',
        'Agente', 'Notas', 'Reformas', 'Historial Contactos', 'Fecha Alta'];

    $rows = [];
    foreach ($prospectos as $p) {
        $rows[] = [
            $p['referencia'], $p['nombre'],
            $p['telefono'] ?? '', $p['telefono2'] ?? '', $p['email'] ?? '',
            $p['etapa'], $p['estado'],
            $p['tipo_propiedad'] ?? '', $p['direccion'] ?? '', $p['barrio'] ?? '',
            $p['localidad'] ?? '', $p['provincia'] ?? '', $p['codigo_postal'] ?? '',
            $p['precio_estimado'] ?? '', $p['precio_propietario'] ?? '',
            $p['superficie'] ?? '', $p['habitaciones'] ?? '',
            $p['enlace'] ?? '', $p['fecha_publicacion_propiedad'] ?? '', $p['fecha_contacto'] ?? '', $p['hora_contacto'] ?? '', $p['mejor_horario_contacto'] ?? '', $p['fecha_proximo_contacto'] ?? '',
            $p['comision'] ?? '', $p['exclusividad'] ? 'Si' : 'No',
            $p['agente_nombre'] ?? '',
            strip_tags($p['notas'] ?? ''), strip_tags($p['reformas'] ?? ''),
            strip_tags($p['historial_contactos'] ?? ''),
            $p['created_at'] ?? '',
        ];
    }

    exportCSV('prospectos_' . date('Y-m-d') . '.csv', $headers, $rows);
}

/**
 * Exportar visitas
 */
function exportarVisitas($visitas) {
    $headers = ['Fecha', 'Hora', 'Propiedad', 'Cliente', 'Agente', 'Estado', 'Valoracion', 'Comentarios'];

    $rows = [];
    foreach ($visitas as $v) {
        $rows[] = [
            $v['fecha'], substr($v['hora'], 0, 5),
            ($v['referencia'] ?? '') . ' - ' . ($v['propiedad'] ?? ''),
            ($v['cliente_nombre'] ?? '') . ' ' . ($v['cliente_apellidos'] ?? ''),
            $v['agente_nombre'] ?? '',
            $v['estado'],
            $v['valoracion'] ?? '',
            strip_tags($v['comentarios'] ?? ''),
        ];
    }

    exportCSV('visitas_' . date('Y-m-d') . '.csv', $headers, $rows);
}

/**
 * Exportar finanzas
 */
function exportarFinanzas($registros) {
    $headers = ['Fecha', 'Tipo', 'Concepto', 'Importe', 'IVA %', 'Total', 'Estado', 'N. Factura', 'Propiedad', 'Agente', 'Notas'];

    $rows = [];
    foreach ($registros as $r) {
        $rows[] = [
            $r['fecha'], $r['tipo'], $r['concepto'],
            $r['importe'], $r['iva'], $r['importe_total'],
            $r['estado'], $r['factura_numero'] ?? '',
            $r['prop_ref'] ?? '', $r['agente_nombre'] ?? '',
            strip_tags($r['notas'] ?? ''),
        ];
    }

    exportCSV('finanzas_' . date('Y-m-d') . '.csv', $headers, $rows);
}

/**
 * Exportar todos los datos de un cliente (RGPD - derecho de portabilidad)
 */
function exportarDatosClienteRGPD($clienteId) {
    $db = getDB();

    // Datos del cliente
    $cliente = $db->prepare("SELECT * FROM clientes WHERE id = ?");
    $cliente->execute([$clienteId]);
    $cliente = $cliente->fetch();

    if (!$cliente) return;

    // Visitas
    $visitas = $db->prepare("SELECT v.*, p.referencia, p.titulo as propiedad FROM visitas v LEFT JOIN propiedades p ON v.propiedad_id = p.id WHERE v.cliente_id = ?");
    $visitas->execute([$clienteId]);
    $visitas = $visitas->fetchAll();

    // Documentos
    $docs = $db->prepare("SELECT * FROM documentos WHERE cliente_id = ?");
    $docs->execute([$clienteId]);
    $docs = $docs->fetchAll();

    // Finanzas
    $finanzas = $db->prepare("SELECT * FROM finanzas WHERE cliente_id = ?");
    $finanzas->execute([$clienteId]);
    $finanzas = $finanzas->fetchAll();

    // Generar JSON con todos los datos
    $data = [
        'fecha_exportacion' => date('Y-m-d H:i:s'),
        'datos_personales' => $cliente,
        'visitas' => $visitas,
        'documentos' => array_map(function($d) {
            unset($d['archivo']); // No incluir ruta de archivo
            return $d;
        }, $docs),
        'datos_financieros' => $finanzas,
    ];

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="datos_cliente_' . $clienteId . '_' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
