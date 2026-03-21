<?php
/*
 * ==========================================================================
 * Archivo: api/imagenes.php — API de Gestión de Imágenes de Propiedades
 * ==========================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo es el endpoint (punto de acceso) que gestiona todas las
 * operaciones relacionadas con las imágenes de las propiedades inmobiliarias.
 * Funciona como un "controlador de tráfico" que recibe peticiones del
 * navegador y decide qué hacer según la acción solicitada.
 *
 * ACCIONES DISPONIBLES:
 * - "subir"           → Sube una o varias imágenes a una propiedad.
 * - "obtener"         → Devuelve la lista de imágenes de una propiedad.
 * - "marcar-principal" → Marca una imagen como la foto de portada.
 * - "eliminar"        → Borra una imagen (del disco y de la base de datos).
 *
 * ¿CÓMO SE COMUNICA CON EL NAVEGADOR?
 * Toda la comunicación es en formato JSON (JavaScript Object Notation).
 * El navegador envía la acción deseada y los datos necesarios, y este
 * archivo responde con un JSON indicando si fue exitoso o hubo error.
 *
 * SEGURIDAD:
 * - Las operaciones de escritura (subir/marcar/eliminar) requieren
 *   verificación CSRF (Cross-Site Request Forgery) para evitar que
 *   sitios maliciosos hagan peticiones en nombre del usuario.
 * - La sesión del usuario se verifica en bootstrap.php.
 * - Los archivos se validan (tipo MIME) antes de guardarse.
 *
 * EJEMPLO DE USO DESDE JAVASCRIPT:
 *   // Subir imagen:
 *   fetch('api/imagenes.php', { method: 'POST', body: formData })
 *
 *   // Obtener imágenes:
 *   fetch('api/imagenes.php?action=obtener&propiedad_id=42')
 */

// Cargamos el bootstrap: sesión, conexión BD, funciones auxiliares, autenticación
require_once __DIR__ . '/../inc/bootstrap.php';

// Indicamos al navegador que nuestra respuesta será siempre JSON
header('Content-Type: application/json');

try {
    // ────────────────────────────────────────────────────────────
    // PASO 1: DETERMINAR QUÉ ACCIÓN QUIERE REALIZAR EL USUARIO
    // ────────────────────────────────────────────────────────────
    // La acción puede venir de 3 formas diferentes (compatibilidad):
    // - Por URL (query string): ?action=subir
    // - Por formulario POST: campo "action" en el formulario
    // - Por JSON en el cuerpo de la petición (cuando se usa fetch() con JSON)
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    
    // Si no encontramos la acción por GET ni POST, intentamos leer el cuerpo
    // de la petición como JSON. Esto ocurre cuando JavaScript envía datos
    // con fetch() usando Content-Type: application/json.
    // php://input es un "flujo" especial de PHP que contiene los datos
    // crudos enviados por el navegador.
    if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = json_decode(file_get_contents('php://input'), true);
        $action = $json['action'] ?? null;
    }

    // Si después de todos los intentos no tenemos acción, respondemos con
    // error 400 (Bad Request = petición mal formada)
    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
        exit;
    }

    // ────────────────────────────────────────────────────────────
    // PASO 2: PROTECCIÓN CSRF PARA OPERACIONES DE ESCRITURA
    // ────────────────────────────────────────────────────────────
    // CSRF (Cross-Site Request Forgery) es un ataque donde un sitio malicioso
    // podría hacer que tu navegador envíe peticiones a TinoProp sin tu
    // consentimiento. csrf_verify_api() comprueba que la petición contiene
    // un token secreto válido que solo nuestra aplicación conoce.
    // Solo verificamos CSRF en operaciones que MODIFICAN datos (no en lecturas).
    $acciones_escritura = ['subir', 'marcar-principal', 'eliminar'];
    if (in_array($action, $acciones_escritura)) {
        csrf_verify_api();
    }

    // ────────────────────────────────────────────────────────────
    // PASO 3: ENRUTADOR DE ACCIONES (SWITCH)
    // ────────────────────────────────────────────────────────────
    // Un switch es como un "menú de opciones": según el valor de $action,
    // ejecutamos un bloque de código diferente.
    switch ($action) {

        // =============================================================
        // ACCIÓN: SUBIR IMÁGENES
        // =============================================================
        // Permite subir una o varias imágenes asociadas a una propiedad.
        // Las imágenes se envían desde un formulario HTML con enctype="multipart/form-data".
        case 'subir':
            // Obtenemos el ID de la propiedad a la que pertenecen las imágenes.
            // (int) fuerza la conversión a número entero por seguridad.
            $propiedad_id = (int) ($_POST['propiedad_id'] ?? 0);

            // Validación: sin ID de propiedad, no podemos saber dónde guardar las imágenes
            if (!$propiedad_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de propiedad faltante']);
                exit;
            }

            // Verificamos que se hayan enviado archivos.
            // $_FILES es una variable global de PHP que contiene los archivos subidos.
            // Aceptamos dos nombres de campo: "archivo" (singular) o "imagenes" (múltiple).
            if (!isset($_FILES['archivo']) && !isset($_FILES['imagenes'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Archivo no especificado']);
                exit;
            }

            // Normalizamos la entrada de archivos para procesarlos de forma uniforme.
            // Independientemente de si se envió como "archivo" o "imagenes",
            // los tratamos de la misma manera.
            $files = $_FILES['archivo'] ?? $_FILES['imagenes'] ?? null;
            $ids = [];      // Array donde guardaremos los IDs de las imágenes subidas con éxito
            $errores = [];  // Array donde guardaremos los mensajes de error por archivo

            // Si es un archivo único (no array), lo convertimos a array de un elemento
            // para poder procesarlo con el mismo bucle que los múltiples.
            if (isset($files['name']) && !is_array($files['name'])) {
                $files = [$files];
            }

            // Procesamiento de múltiples archivos (campo imagenes[] del formulario).
            // Cuando un input HTML tiene name="imagenes[]", PHP organiza $_FILES
            // con arrays paralelos: name[], type[], tmp_name[], error[], size[].
            if (is_array($files['name'] ?? [])) {
                foreach ($files['name'] as $idx => $filename) {
                    // UPLOAD_ERR_OK (valor 0) significa que el archivo se subió correctamente.
                    // Si hay error (archivo muy grande, subida parcial, etc.), lo registramos.
                    if ($files['error'][$idx] !== UPLOAD_ERR_OK) {
                        $errores[] = "Error en: $filename";
                        continue; // Saltamos este archivo y seguimos con el siguiente
                    }

                    // Construimos un array con la información del archivo individual
                    // para pasárselo a la función imagen_subir() de forma limpia.
                    $file = [
                        'name' => $files['name'][$idx],       // Nombre original del archivo
                        'type' => $files['type'][$idx],       // Tipo MIME (ej: image/jpeg)
                        'tmp_name' => $files['tmp_name'][$idx], // Ruta temporal donde PHP guardó el archivo
                        'error' => $files['error'][$idx],     // Código de error (0 = OK)
                        'size' => $files['size'][$idx],       // Tamaño en bytes
                    ];

                    // imagen_subir() es una función helper que:
                    // 1. Valida que el archivo sea realmente una imagen (tipo MIME)
                    // 2. Lo mueve a la carpeta de almacenamiento definitiva
                    // 3. Guarda un registro en la tabla de imágenes de la BD
                    // 4. Devuelve el ID del nuevo registro, o null si falló
                    $id = imagen_subir($pdo, $propiedad_id, $file);
                    if ($id) {
                        $ids[] = $id;
                    } else {
                        $errores[] = "Error al procesar: $filename";
                    }
                }
            } else {
                // Rama de respaldo: archivo único enviado con campo "archivo".
                // Este caso se da cuando el formulario usa un input simple:
                // <input type="file" name="archivo">
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

            // Respuesta al navegador: si al menos una imagen se subió con éxito,
            // respondemos con success=true e incluimos tanto los IDs exitosos
            // como los errores parciales (por si alguna falló).
            if (count($ids) > 0) {
                // Respuesta parcial/total de éxito con detalle de fallos por archivo.
                echo json_encode([
                    'success' => true,
                    'ids' => $ids,              // IDs de las imágenes guardadas en BD
                    'message' => count($ids) . ' imagen(es) subida(s)',
                    'errors' => $errores        // Errores parciales (si los hubo)
                ]);
            } else {
                // Si NINGUNA imagen se pudo procesar, respondemos con error 500
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se pudo procesar ninguna imagen',
                    'details' => $errores       // Detalle de qué falló en cada archivo
                ]);
            }
            break;

        // =============================================================
        // ACCIÓN: OBTENER IMÁGENES DE UNA PROPIEDAD
        // =============================================================
        // Devuelve la lista de imágenes de una propiedad, ordenadas
        // primero la imagen principal (portada) y luego por fecha.
        // Es una operación de LECTURA, no necesita CSRF.
        case 'obtener':
            // El ID viene por GET porque es una consulta (no modifica datos)
            $propiedad_id = (int) ($_GET['propiedad_id'] ?? 0);

            if (!$propiedad_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de propiedad faltante']);
                exit;
            }

            // imagenes_obtener_propiedad() consulta la BD y devuelve un array
            // con todas las imágenes de esa propiedad (URL, si es principal, etc.)
            $imagenes = imagenes_obtener_propiedad($pdo, $propiedad_id);
            echo json_encode(['success' => true, 'data' => $imagenes]);
            break;

        // =============================================================
        // ACCIÓN: MARCAR IMAGEN COMO PRINCIPAL (PORTADA)
        // =============================================================
        // Cuando el usuario elige una foto como "portada" de la propiedad,
        // esta acción la marca como principal y desmarca todas las demás
        // imágenes de esa misma propiedad.
        case 'marcar-principal':
            // Leemos el ID de la imagen desde JSON o POST
            $json = json_decode(file_get_contents('php://input'), true);
            $imagen_id = (int) ($json['id'] ?? $_POST['imagen_id'] ?? 0);

            if (!$imagen_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de imagen faltante']);
                exit;
            }

            // imagen_marcar_principal() hace dos cosas en la BD:
            // 1. Pone principal=0 en TODAS las imágenes de esa propiedad
            // 2. Pone principal=1 en la imagen seleccionada
            $resultado = imagen_marcar_principal($pdo, $imagen_id);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Imagen marcada como principal']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al marcar imagen']);
            }
            break;

        // =============================================================
        // ACCIÓN: ELIMINAR UNA IMAGEN
        // =============================================================
        // Borra una imagen tanto de la base de datos como del disco duro
        // del servidor (el archivo físico .jpg/.png/.webp).
        case 'eliminar':
            // Leemos el ID de la imagen a eliminar desde JSON o POST
            $json = json_decode(file_get_contents('php://input'), true);
            $imagen_id = (int) ($json['id'] ?? $_POST['imagen_id'] ?? 0);

            if (!$imagen_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de imagen faltante']);
                exit;
            }

            // imagen_eliminar() hace dos cosas:
            // 1. Borra el registro de la BD (tabla de imágenes)
            // 2. Elimina el archivo físico del disco del servidor
            $resultado = imagen_eliminar($pdo, $imagen_id);

            if ($resultado) {
                echo json_encode(['success' => true, 'message' => 'Imagen eliminada']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al eliminar imagen']);
            }
            break;

        default:
            // Si la acción no coincide con ninguna de las anteriores,
            // respondemos con error 400 para que el frontend sepa que
            // envió una acción inválida.
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (\Exception $e) {
    // ── MANEJO DE ERRORES INESPERADOS ──
    // Si algo falla de forma inesperada (ej: la BD se cae, un archivo
    // está corrupto, etc.), capturamos la excepción y respondemos con
    // un error 500 genérico. No mostramos detalles del error al usuario
    // por seguridad (podrían revelar información sensible del servidor).
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor.']);
}

