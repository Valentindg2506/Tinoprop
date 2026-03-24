<?php

require_once __DIR__ . '/../inc/bootstrap.php';
header('Content-Type: application/json');
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input', false, null, 0, 1024 * 1024);
        $json = $rawInput ? (json_decode($rawInput, true) ?? []) : [];
        $action = $json['action'] ?? null;
    }
    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
        exit;
    }
    $acciones_escritura = ['subir', 'marcar-principal', 'eliminar'];
    if (in_array($action, $acciones_escritura)) {
        csrf_verify_api();
    }
    switch ($action) {
        case 'subir':
            $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);
            if (!$propiedad_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de propiedad faltante']);
                exit;
            }
            if (!isset($_FILES['archivo']) && !isset($_FILES['imagenes'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Archivo no especificado']);
                exit;
            }
            $files = $_FILES['archivo'] ?? $_FILES['imagenes'] ?? null;
            $ids = [];
            $errores = [];
            if (isset($files['name']) && !is_array($files['name'])) {
                $files = [$files];
            }
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
                    $id = imagen_subir($pdo, $propiedad_id, $file);
                    if ($id) {
                        $ids[] = $id;
                    } else {
                        $errores[] = "Error al procesar: $filename";
                    }
                }
            } else {
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
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}
