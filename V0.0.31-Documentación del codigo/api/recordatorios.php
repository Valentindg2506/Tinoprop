<?php
/*
 * ==========================================================================
 * Archivo: api/recordatorios.php — API CRUD de Recordatorios de TinoProp
 * ==========================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo gestiona los recordatorios del CRM inmobiliario. Un recordatorio
 * es una tarea programada (ej: "Llamar al cliente García", "Visita al piso de
 * la calle Mayor", etc.) que el agente inmobiliario quiere recordar.
 *
 * OPERACIONES CRUD:
 * CRUD viene del inglés: Create (Crear), Read (Leer), Update (Actualizar),
 * Delete (Eliminar). Son las 4 operaciones básicas sobre cualquier dato.
 *
 * ACCIONES DISPONIBLES:
 * - "crear"      → Crea un nuevo recordatorio.
 * - "obtener"    → Lee los datos de un recordatorio existente por su ID.
 * - "actualizar" → Modifica los datos de un recordatorio existente.
 * - "eliminar"   → Borra un recordatorio (y su visita vinculada si existe).
 *
 * CARACTERÍSTICA ESPECIAL:
 * Si el tipo de recordatorio es "Visita", automáticamente se crea o elimina
 * un registro en la tabla de visitas. Esto mantiene sincronizados ambos
 * módulos sin que el usuario tenga que hacerlo manualmente.
 *
 * SEGURIDAD:
 * - Sesión obligatoria (verificada en bootstrap.php).
 * - CSRF verificado en operaciones de escritura (crear/actualizar/eliminar).
 * - Datos filtrados por inmobiliaria (multi-tenant con sql_iid()).
 *
 * FORMATO DE COMUNICACIÓN:
 * - Entrada: JSON body, POST o GET.
 * - Salida: JSON con campos "success", "message" y/o "data".
 */

// Cargamos bootstrap: sesión, BD, funciones helper, autenticación
require_once __DIR__ . '/../inc/bootstrap.php';

// Todas las respuestas de esta API serán en formato JSON
header('Content-Type: application/json');

try {
    // ────────────────────────────────────────────────────────────
    // PASO 1: LEER LA ACCIÓN Y LOS DATOS DE ENTRADA
    // ────────────────────────────────────────────────────────────
    // Primero intentamos leer el cuerpo de la petición como JSON.
    // file_get_contents('php://input') lee los datos "crudos" que envió el navegador.
    // json_decode(..., true) los convierte de JSON a un array PHP.
    // El ?? [] al final significa: si no hay JSON válido, usa un array vacío.
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // Buscamos la acción en 3 sitios posibles (de mayor a menor prioridad):
    // 1. En el JSON del cuerpo de la petición
    // 2. En los parámetros GET de la URL (?action=crear)
    // 3. En los datos POST de un formulario
    $action = $data['action'] ?? $_GET['action'] ?? $_POST['action'] ?? null;

    // Si no se especificó ninguna acción, devolvemos error 400 (petición inválida)
    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
        exit;
    }

    // ────────────────────────────────────────────────────────────
    // PASO 2: PROTECCIÓN CSRF PARA ESCRITURA
    // ────────────────────────────────────────────────────────────
    // Solo verificamos el token CSRF en acciones que MODIFICAN datos.
    // "obtener" es solo lectura, así que no necesita protección CSRF.
    $acciones_escritura = ['crear', 'actualizar', 'eliminar'];
    if (in_array($action, $acciones_escritura)) {
        csrf_verify_api(); // Lanza excepción si el token no es válido
    }

    // ────────────────────────────────────────────────────────────
    // PASO 3: ENRUTADOR DE ACCIONES CRUD
    // ────────────────────────────────────────────────────────────
    switch ($action) {

        // =============================================================
        // ACCIÓN: CREAR UN NUEVO RECORDATORIO
        // =============================================================
        case 'crear':
            // Recopilamos todos los campos necesarios para crear el recordatorio.
            // Buscamos en $data (JSON), $_POST y $_GET para máxima compatibilidad.
            $tipo = $data['tipo'] ?? $_POST['tipo'] ?? $_GET['tipo'] ?? null;
            $descripcion = $data['descripcion'] ?? $_POST['descripcion'] ?? $_GET['descripcion'] ?? null;
            $fecha = $data['fecha'] ?? $_POST['fecha'] ?? $_GET['fecha'] ?? null;
            $hora = $data['hora'] ?? $_POST['hora'] ?? $_GET['hora'] ?? null;
            // prospecto_id es opcional: permite vincular el recordatorio a un prospecto
            // (persona interesada en comprar/alquilar)
            $prospecto_id = $data['prospecto_id'] ?? $_POST['prospecto_id'] ?? $_GET['prospecto_id'] ?? null;

            // Validación: tipo, descripción y fecha son OBLIGATORIOS.
            // Sin estos datos mínimos no tiene sentido crear un recordatorio.
            if (!$tipo || !$descripcion || !$fecha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }

            // Insertamos el recordatorio en la base de datos.
            // La función recordatorio_crear() se encarga de asociarlo al usuario
            // logueado y a su inmobiliaria automáticamente (multi-tenant).
            // Devuelve el ID del nuevo registro si tuvo éxito, o null si falló.
            $id = recordatorio_crear($pdo, $tipo, $descripcion, $fecha, $hora ?: null, $prospecto_id ? (int) $prospecto_id : null);

            if ($id) {
                // SINCRONIZACIÓN CON VISITAS:
                // Si el tipo del recordatorio es "Visita", creamos automáticamente
                // un registro en la tabla de visitas. Así el módulo de visitas
                // refleja la cita sin que el usuario tenga que crearla por separado.
                if (strtolower($tipo) === 'visita') {
                    visita_crear_desde_recordatorio($pdo, $id, 'vendedor');
                }
                echo json_encode(['success' => true, 'id' => $id, 'message' => 'Recordatorio creado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al crear recordatorio']);
            }
            break;

        // =============================================================
        // ACCIÓN: OBTENER UN RECORDATORIO POR SU ID
        // =============================================================
        // Devuelve todos los datos de un recordatorio específico.
        // Se usa cuando el usuario abre el detalle de un recordatorio.
        case 'obtener':
            // Obtenemos el ID del recordatorio y lo convertimos a entero
            $id = (int) ($data['id'] ?? $_GET['id'] ?? $_POST['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no especificado']);
                exit;
            }

            // recordatorio_obtener() busca el recordatorio en la BD.
            // Internamente filtra por inmobiliaria (sql_iid) para que un usuario
            // no pueda ver recordatorios de otra inmobiliaria.
            $recordatorio = recordatorio_obtener($pdo, $id);

            if ($recordatorio) {
                echo json_encode(['success' => true, 'data' => $recordatorio]);
            } else {
                // 404 = Not Found (no se encontró el recurso solicitado)
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Recordatorio no encontrado']);
            }
            break;

        // =============================================================
        // ACCIÓN: ACTUALIZAR UN RECORDATORIO EXISTENTE
        // =============================================================
        // Modifica los datos de un recordatorio ya creado.
        case 'actualizar':
            // Recogemos todos los campos actualizables
            $id = (int) ($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
            $tipo = $data['tipo'] ?? $_POST['tipo'] ?? $_GET['tipo'] ?? null;
            $descripcion = $data['descripcion'] ?? $_POST['descripcion'] ?? $_GET['descripcion'] ?? null;
            $fecha = $data['fecha'] ?? $_POST['fecha'] ?? $_GET['fecha'] ?? null;
            $hora = $data['hora'] ?? $_POST['hora'] ?? $_GET['hora'] ?? null;
            $prospecto_id = $data['prospecto_id'] ?? $_POST['prospecto_id'] ?? $_GET['prospecto_id'] ?? null;
            // Estado puede ser: "pendiente", "completado", "cancelado", etc.
            $estado = $data['estado'] ?? $_POST['estado'] ?? $_GET['estado'] ?? 'pendiente';

            // Validación: necesitamos ID + los mismos campos obligatorios que al crear
            if (!$id || !$tipo || !$descripcion || !$fecha) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
                exit;
            }

            // recordatorio_actualizar() ejecuta un UPDATE en la BD.
            // Devuelve true si la actualización fue exitosa, false si falló.
            $resultado = recordatorio_actualizar($pdo, $id, $tipo, $descripcion, $fecha, $hora ?: null, $prospecto_id ? (int) $prospecto_id : null, $estado);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Recordatorio actualizado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al actualizar recordatorio']);
            }
            break;

        // =============================================================
        // ACCIÓN: ELIMINAR UN RECORDATORIO
        // =============================================================
        // Al eliminar un recordatorio, también se elimina la visita
        // vinculada (si existe) para mantener la coherencia de datos.
        case 'eliminar':
            $id = (int) ($data['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);

            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID no especificado']);
                exit;
            }

            // ── SINCRONIZACIÓN CON VISITAS (antes de eliminar) ──
            // Si este recordatorio tenía una visita vinculada, la eliminamos primero.
            // Esto evita que queden "visitas huérfanas" en la BD sin recordatorio.
            visitas_asegurar_tabla($pdo); // Crea la tabla visitas si no existe

            // Obtenemos el ID del usuario logueado desde la sesión
            $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);

            // Buscamos si existe una visita vinculada a este recordatorio.
            // sql_iid() añade el filtro de inmobiliaria (multi-tenant) a la consulta.
            // sql_iid_params() proporciona los parámetros de ese filtro.
            $stmtV = $pdo->prepare('SELECT id FROM visitas WHERE recordatorio_id = :rid AND usuario_id = :uid' . sql_iid());
            $stmtV->execute(['rid' => $id, 'uid' => $usuario_id] + sql_iid_params());
            $visita_vinculada = $stmtV->fetchColumn();

            // Si encontramos una visita vinculada, la eliminamos
            if ($visita_vinculada) {
                $pdo->prepare('DELETE FROM visitas WHERE id = :id')->execute(['id' => $visita_vinculada]);
            }

            // Ahora sí eliminamos el recordatorio en sí
            $resultado = recordatorio_eliminar($pdo, $id);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Recordatorio eliminado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al eliminar recordatorio']);
            }
            break;

        default:
            // Si la acción no coincide con ninguna conocida, informamos al frontend.
            // Esto ayuda a detectar errores de programación en el JavaScript.
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
} catch (\Exception $e) {
    // ── CAPTURA DE ERRORES INESPERADOS ──
    // Si algo falla (BD caída, error de programación, etc.), capturamos
    // la excepción y respondemos con error 500 sin revelar detalles
    // internos al navegador (por seguridad).
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
