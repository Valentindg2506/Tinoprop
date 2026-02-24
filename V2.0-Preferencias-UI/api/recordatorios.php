<?php
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

try {
    // Obtener datos de JSON body o query/post params
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? $_GET['action'] ?? $_POST['action'] ?? null;

    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
        exit;
    }

    switch ($action) {
        case 'crear':
            $tipo = $data['tipo'] ?? $_POST['tipo'] ?? $_GET['tipo'] ?? null;
            $descripcion = $data['descripcion'] ?? $_POST['descripcion'] ?? $_GET['descripcion'] ?? null;
            $fecha = $data['fecha'] ?? $_POST['fecha'] ?? $_GET['fecha'] ?? null;
            $hora = $data['hora'] ?? $_POST['hora'] ?? $_GET['hora'] ?? null;
            $prospecto_id = $data['prospecto_id'] ?? $_POST['prospecto_id'] ?? $_GET['prospecto_id'] ?? null;

            if (!$tipo || !$descripcion || !$fecha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }

            $id = recordatorio_crear($pdo, $tipo, $descripcion, $fecha, $hora ?: null, $prospecto_id ? (int) $prospecto_id : null);

            if ($id) {
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Recordatorio creado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al crear recordatorio']);
            }
            break;

        case 'obtener':
            $id = (int) ($data['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no especificado']);
                exit;
            }

            $recordatorio = recordatorio_obtener($pdo, $id);

            if ($recordatorio) {
                echo json_encode(['success' => true, 'data' => $recordatorio]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Recordatorio no encontrado']);
            }
            break;

        case 'actualizar':
            $id = (int) ($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
            $tipo = $data['tipo'] ?? $_POST['tipo'] ?? $_GET['tipo'] ?? null;
            $descripcion = $data['descripcion'] ?? $_POST['descripcion'] ?? $_GET['descripcion'] ?? null;
            $fecha = $data['fecha'] ?? $_POST['fecha'] ?? $_GET['fecha'] ?? null;
            $hora = $data['hora'] ?? $_POST['hora'] ?? $_GET['hora'] ?? null;
            $prospecto_id = $data['prospecto_id'] ?? $_POST['prospecto_id'] ?? $_GET['prospecto_id'] ?? null;
            $estado = $data['estado'] ?? $_POST['estado'] ?? $_GET['estado'] ?? 'pendiente';

            if (!$id || !$tipo || !$descripcion || !$fecha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }

            $resultado = recordatorio_actualizar($pdo, $id, $tipo, $descripcion, $fecha, $hora ?: null, $prospecto_id ? (int) $prospecto_id : null, $estado);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al actualizar recordatorio']);
            }
            break;

        case 'eliminar':
            $id = (int) ($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no especificado']);
                exit;
            }

            $resultado = recordatorio_eliminar($pdo, $id);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Recordatorio eliminado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al eliminar recordatorio']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
