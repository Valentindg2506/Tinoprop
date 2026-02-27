<?php
/*
 * Archivo: api/recordatorios.php
 * Rol: endpoint JSON para CRUD de recordatorios.
 * Acciones soportadas: crear, obtener, actualizar, eliminar.
 * Entrada: GET/POST/JSON body; Salida: JSON con success/error.
 */
require_once __DIR__ . '/../inc/bootstrap.php';

header('Content-Type: application/json');

try {
    // Entrada unificada:
    // 1) intenta leer payload JSON (fetch con body JSON)
    // 2) permite fallback por GET/POST tradicional
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? $_GET['action'] ?? $_POST['action'] ?? null;

    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
        exit;
    }

    // Router de acciones CRUD del endpoint.
    switch ($action) {
        case 'crear':
            // Alta de recordatorio: recopila campos desde JSON, POST o GET.
            $tipo = $data['tipo'] ?? $_POST['tipo'] ?? $_GET['tipo'] ?? null;
            $descripcion = $data['descripcion'] ?? $_POST['descripcion'] ?? $_GET['descripcion'] ?? null;
            $fecha = $data['fecha'] ?? $_POST['fecha'] ?? $_GET['fecha'] ?? null;
            $hora = $data['hora'] ?? $_POST['hora'] ?? $_GET['hora'] ?? null;
            $prospecto_id = $data['prospecto_id'] ?? $_POST['prospecto_id'] ?? $_GET['prospecto_id'] ?? null;

            // Validación mínima de negocio para crear un recordatorio.
            if (!$tipo || !$descripcion || !$fecha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }

            // Crea registro asociado al usuario autenticado (controlado en helper).
            $id = recordatorio_crear($pdo, $tipo, $descripcion, $fecha, $hora ?: null, $prospecto_id ? (int) $prospecto_id : null);

            if ($id) {
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Recordatorio creado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al crear recordatorio']);
            }
            break;

        case 'obtener':
            // Obtiene un recordatorio concreto por ID.
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
            // Actualización completa de campos del recordatorio.
            $id = (int) ($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
            $tipo = $data['tipo'] ?? $_POST['tipo'] ?? $_GET['tipo'] ?? null;
            $descripcion = $data['descripcion'] ?? $_POST['descripcion'] ?? $_GET['descripcion'] ?? null;
            $fecha = $data['fecha'] ?? $_POST['fecha'] ?? $_GET['fecha'] ?? null;
            $hora = $data['hora'] ?? $_POST['hora'] ?? $_GET['hora'] ?? null;
            $prospecto_id = $data['prospecto_id'] ?? $_POST['prospecto_id'] ?? $_GET['prospecto_id'] ?? null;
            $estado = $data['estado'] ?? $_POST['estado'] ?? $_GET['estado'] ?? 'pendiente';

            // Mismos mínimos que crear, añadiendo ID obligatorio.
            if (!$id || !$tipo || !$descripcion || !$fecha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }

            // El helper devuelve bool según éxito real del UPDATE.
            $resultado = recordatorio_actualizar($pdo, $id, $tipo, $descripcion, $fecha, $hora ?: null, $prospecto_id ? (int) $prospecto_id : null, $estado);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al actualizar recordatorio']);
            }
            break;

        case 'eliminar':
            // Baja lógica/física del recordatorio por ID.
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
            // Acción desconocida: devuelve 400 para que frontend corrija request.
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (\Exception $e) {
    // Error no controlado: respuesta JSON 500 para trazabilidad en frontend.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
