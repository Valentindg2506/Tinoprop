<?php
/*
 * Archivo: api/imagenes.php
 * Rol: endpoint JSON para gestionar galería de propiedades.
 * Acciones soportadas: subir, obtener, marcar-principal, eliminar.
 * Entrada: action por query/body + ficheros en $_FILES.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

try {
    // Resolve de acción compatible con:
    // - query string (?action=...)
    // - formulario POST
    // - body JSON (fetch)
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    
    // Si no vino por GET/POST, intenta extraer action desde JSON body.
    if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = json_decode(file_get_contents('php://input'), true);
        $action = $json['action'] ?? null;
    }

    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
        exit;
    }

    // Protección CSRF para operaciones de escritura
    $acciones_escritura = ['subir', 'marcar-principal', 'eliminar'];
    if (in_array($action, $acciones_escritura) && !csrf_verify_api()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }

    // Router de acciones para galería.
    switch ($action) {
        case 'subir':
            // Subida de imagen(es) para una propiedad concreta.
            $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);

            if (!$propiedad_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de propiedad faltante']);
                exit;
            }

            // Admite dos contratos de subida: archivo único o array imagenes[].
            if (!isset($_FILES['archivo']) && !isset($_FILES['imagenes'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Archivo no especificado']);
                exit;
            }

            // Normaliza entrada de archivos para procesar de forma uniforme.
            $files = $_FILES['archivo'] ?? $_FILES['imagenes'] ?? null;
            $ids = [];
            $errores = [];

            // Si viene archivo único, lo convierte a colección homogénea.
            if (isset($files['name']) && !is_array($files['name'])) {
                $files = [$files];
            }

            // Rama principal: procesa múltiples archivos imagenes[].
            if (is_array($files['name'] ?? [])) {
                foreach ($files['name'] as $idx => $filename) {
                    if ($files['error'][$idx] !== UPLOAD_ERR_OK) {
                        $errores[] = "Error en: $filename";
                        continue;
                    }

                    $file = [
                        'name' => $files['name'][$idx],
                        'type' => $files['type'][$idx],
                        'tmp_name' => $files['tmp_name'][$idx],
                        'error' => $files['error'][$idx],
                        'size' => $files['size'][$idx],
                    ];

                    // Delega validación MIME + guardado físico + persistencia SQL al helper.
                    $id = imagen_subir($pdo, $propiedad_id, $file);
                    if ($id) {
                        $ids[] = $id;
                    } else {
                        $errores[] = "Error al procesar: $filename";
                    }
                }
            } else {
                // Rama fallback: archivo único en campo "archivo".
                $file = [
                    'name' => $_FILES['archivo']['name'] ?? '',
                    'type' => $_FILES['archivo']['type'] ?? '',
                    'tmp_name' => $_FILES['archivo']['tmp_name'] ?? '',
                    'error' => $_FILES['archivo']['error'] ?? 0,
                    'size' => $_FILES['archivo']['size'] ?? 0,
                ];
                
                $id = imagen_subir($pdo, $propiedad_id, $file);
                if ($id) {
                    $ids[] = $id;
                } else {
                    $errores[] = 'Error al procesar la imagen';
                }
            }

            if (count($ids) > 0) {
                // Respuesta parcial/total de éxito con detalle de fallos por archivo.
                echo json_encode([
                    'success' => true,
                    'ids' => $ids,
                    'message' => count($ids) . ' imagen(es) subida(s)',
                    'errors' => $errores
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se pudo procesar ninguna imagen',
                    'details' => $errores
                ]);
            }
            break;

        case 'obtener':
            // Devuelve lista de imágenes de una propiedad ordenadas por principal/fecha.
            $propiedad_id = (int) ($_GET['propiedad_id'] ?? 0);

            if (!$propiedad_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de propiedad faltante']);
                exit;
            }

            $imagenes = imagenes_obtener_propiedad($pdo, $propiedad_id);
            echo json_encode(['success' => true, 'data' => $imagenes]);
            break;

        case 'marcar-principal':
            // Marca una imagen como portada (y desmarca las demás de esa propiedad).
            // Acepta ID por JSON o POST.
            $json = json_decode(file_get_contents('php://input'), true);
            $imagen_id = (int) ($json['id'] ?? $_POST['imagen_id'] ?? 0);

            if (!$imagen_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de imagen faltante']);
                exit;
            }

            $resultado = imagen_marcar_principal($pdo, $imagen_id);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Imagen marcada como principal']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al marcar imagen']);
            }
            break;

        case 'eliminar':
            // Elimina imagen de base de datos y archivo físico asociado.
            // Acepta ID por JSON o POST.
            $json = json_decode(file_get_contents('php://input'), true);
            $imagen_id = (int) ($json['id'] ?? $_POST['imagen_id'] ?? 0);

            if (!$imagen_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de imagen faltante']);
                exit;
            }

            $resultado = imagen_eliminar($pdo, $imagen_id);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Imagen eliminada']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al eliminar imagen']);
            }
            break;

        default:
            // Acción no reconocida.
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (\Exception $e) {
    // Falla inesperada: reporta 500 para diagnóstico del cliente.
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}

