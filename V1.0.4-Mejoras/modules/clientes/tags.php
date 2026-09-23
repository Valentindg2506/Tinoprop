<?php
/**
 * AJAX endpoint para gestionar tags de clientes
 * Retorna respuestas JSON - No incluye header/footer
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticacion
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

// Verificar CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de seguridad invalido']);
    exit;
}

$db = getDB();
$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {
        case 'listar_tags':
            $stmt = $db->query("SELECT id, nombre, color FROM tags ORDER BY nombre ASC");
            $tags = $stmt->fetchAll();
            echo json_encode(['success' => true, 'tags' => $tags]);
            break;

        case 'agregar_tag':
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $tagId = intval($_POST['tag_id'] ?? 0);

            if (!$clienteId || !$tagId) {
                echo json_encode(['error' => 'Faltan parametros requeridos']);
                exit;
            }

            // Verificar que el cliente existe
            $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ?");
            $stmt->execute([$clienteId]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Cliente no encontrado']);
                exit;
            }

            // Verificar que el tag existe
            $stmt = $db->prepare("SELECT id, nombre, color FROM tags WHERE id = ?");
            $stmt->execute([$tagId]);
            $tag = $stmt->fetch();
            if (!$tag) {
                echo json_encode(['error' => 'Tag no encontrado']);
                exit;
            }

            // Insertar relacion (ignorar si ya existe)
            $stmt = $db->prepare("INSERT IGNORE INTO cliente_tags (cliente_id, tag_id) VALUES (?, ?)");
            $stmt->execute([$clienteId, $tagId]);

            registrarActividad('agregar_tag', 'cliente', $clienteId, 'Tag: ' . $tag['nombre']);

            echo json_encode([
                'success' => true,
                'message' => 'Tag agregado correctamente',
                'tag' => $tag
            ]);
            break;

        case 'quitar_tag':
            $clienteId = intval($_POST['cliente_id'] ?? 0);
            $tagId = intval($_POST['tag_id'] ?? 0);

            if (!$clienteId || !$tagId) {
                echo json_encode(['error' => 'Faltan parametros requeridos']);
                exit;
            }

            // Obtener nombre del tag para log
            $stmt = $db->prepare("SELECT nombre FROM tags WHERE id = ?");
            $stmt->execute([$tagId]);
            $tagNombre = $stmt->fetchColumn();

            $stmt = $db->prepare("DELETE FROM cliente_tags WHERE cliente_id = ? AND tag_id = ?");
            $stmt->execute([$clienteId, $tagId]);

            registrarActividad('quitar_tag', 'cliente', $clienteId, 'Tag: ' . ($tagNombre ?: 'desconocido'));

            echo json_encode([
                'success' => true,
                'message' => 'Tag eliminado correctamente'
            ]);
            break;

        case 'crear_tag':
            $nombre = trim($_POST['nombre'] ?? '');
            $color = trim($_POST['color'] ?? '#6b7280');

            if (empty($nombre)) {
                echo json_encode(['error' => 'El nombre del tag es obligatorio']);
                exit;
            }

            if (mb_strlen($nombre) > 50) {
                echo json_encode(['error' => 'El nombre no puede superar los 50 caracteres']);
                exit;
            }

            // Validar formato de color hex
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#6b7280';
            }

            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id, nombre, color FROM tags WHERE nombre = ?");
            $stmt->execute([$nombre]);
            $existente = $stmt->fetch();

            if ($existente) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Tag ya existente',
                    'tag' => $existente
                ]);
                exit;
            }

            $stmt = $db->prepare("INSERT INTO tags (nombre, color) VALUES (?, ?)");
            $stmt->execute([$nombre, $color]);
            $nuevoId = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Tag creado correctamente',
                'tag' => [
                    'id' => intval($nuevoId),
                    'nombre' => htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
                    'color' => $color
                ]
            ]);
            break;

        default:
            echo json_encode(['error' => 'Accion no reconocida']);
            break;
    }
} catch (PDOException $e) {
    logError('Tags error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
