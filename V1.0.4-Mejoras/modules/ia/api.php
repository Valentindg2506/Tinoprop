<?php
/**
 * IA API Endpoint — Chat con Tool Use Loop (Anthropic Claude)
 *
 * Maneja:
 *   - accion=chat         → Enviar mensaje y obtener respuesta con tool use
 *   - accion=historial    → Listar conversaciones
 *   - accion=cargar       → Cargar una conversación
 *   - accion=nueva        → Crear nueva conversación
 *   - accion=eliminar     → Eliminar conversación
 *   - accion=config       → Guardar configuración
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/encryption.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/tools.php';

if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$userId = currentUserId();

// Parse input
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
}

try {
    switch ($accion) {
        case 'chat':
            echo json_encode(handleChat($db, $userId));
            break;
        case 'historial':
            echo json_encode(handleHistorial($db, $userId));
            break;
        case 'cargar':
            echo json_encode(handleCargar($db, $userId));
            break;
        case 'nueva':
            echo json_encode(handleNueva($db, $userId));
            break;
        case 'eliminar':
            echo json_encode(handleEliminar($db, $userId));
            break;
        case 'config':
            echo json_encode(handleConfig($db));
            break;
        default:
            echo json_encode(['error' => 'Acción no válida.']);
    }
} catch (Throwable $e) {
    logError('IA API Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
exit;

// ================================================================
// HANDLERS
// ================================================================

function handleChat($db, $userId) {
    $mensaje = trim($_POST['mensaje'] ?? '');
    $convId  = (int)($_POST['conversacion_id'] ?? 0);

    if (!$mensaje) return ['error' => 'Mensaje vacío.'];

    // Load config
    $config = $db->query("SELECT * FROM ia_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if (!$config || !$config['activo']) return ['error' => 'IA no activada. Configura la API key y activa el asistente desde Configuración.'];
    if (!$config['api_key']) return ['error' => 'API key no configurada.'];
    // Descifrar la API key antes de usarla
    $config['api_key'] = decryptField($config['api_key']);

    // Create or verify conversation
    if ($convId <= 0) {
        $title = mb_substr($mensaje, 0, 80);
        $db->prepare("INSERT INTO ia_conversaciones (usuario_id, titulo) VALUES (?, ?)")->execute([$userId, $title]);
        $convId = (int)$db->lastInsertId();
    } else {
        $stmt = $db->prepare("SELECT id FROM ia_conversaciones WHERE id=? AND usuario_id=?");
        $stmt->execute([$convId, $userId]);
        if (!$stmt->fetch()) return ['error' => 'Conversación no encontrada.'];
    }

    // Save user message
    $db->prepare("INSERT INTO ia_mensajes (conversacion_id, role, content) VALUES (?, 'user', ?)")->execute([$convId, $mensaje]);

    // Load conversation history (last 20 messages for context window)
    $stmtH = $db->prepare("SELECT role, content FROM ia_mensajes WHERE conversacion_id = ? ORDER BY id DESC LIMIT 20");
    $stmtH->execute([$convId]);
    $historial = array_reverse($stmtH->fetchAll(PDO::FETCH_ASSOC));
    // Remove the user message we just added (it's already the last)

    // Build messages for Anthropic
    $anthropicMsgs = [];
    foreach ($historial as $h) {
        $anthropicMsgs[] = ['role' => $h['role'], 'content' => $h['content']];
    }

    // Build tools if enabled
    $tools = [];
    if ($config['tools_activos']) {
        $tools = getToolDefinitions();
    }

    // Anthropic API call with tool use loop
    $maxIterations = (int)($config['max_tool_iterations'] ?: 8);
    $finalReply = '';
    $allToolCalls = [];
    $totalIn = 0;
    $totalOut = 0;

    for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
        $apiResult = callAI($config, $anthropicMsgs, $tools);

        if (isset($apiResult['error'])) {
            $finalReply = "❌ Error de API: " . $apiResult['error'];
            break;
        }

        $totalIn += (int)($apiResult['usage']['input_tokens'] ?? 0);
        $totalOut += (int)($apiResult['usage']['output_tokens'] ?? 0);

        $stopReason = $apiResult['stop_reason'] ?? 'end_turn';
        $contentBlocks = $apiResult['content'] ?? [];

        // Process content blocks
        $textParts = [];
        $toolUseBlocks = [];

        foreach ($contentBlocks as $block) {
            if ($block['type'] === 'text') {
                $textParts[] = $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolUseBlocks[] = $block;
            }
        }

        if (!empty($textParts)) {
            $finalReply .= implode("\n", $textParts);
        }

        // If no tool use, we're done
        if (empty($toolUseBlocks) || $stopReason !== 'tool_use') {
            break;
        }

        // Execute tools and build tool_result messages
        // First, add the assistant response with tool_use to messages
        $anthropicMsgs[] = ['role' => 'assistant', 'content' => $contentBlocks];

        $toolResults = [];
        foreach ($toolUseBlocks as $toolBlock) {
            $toolName = $toolBlock['name'];
            $toolInput = $toolBlock['input'] ?? [];
            $toolId = $toolBlock['id'];

            // Execute the tool
            $result = executeTool($toolName, $toolInput, $userId);

            // Log the tool call
            $entidadTipo = null;
            $entidadId = null;
            if (isset($toolInput['prospecto_id'])) { $entidadTipo = 'prospecto'; $entidadId = (int)$toolInput['prospecto_id']; }
            elseif (isset($toolInput['pipeline_id'])) { $entidadTipo = 'pipeline'; $entidadId = (int)$toolInput['pipeline_id']; }

            try {
                $db->prepare("INSERT INTO ia_acciones_log (conversacion_id, usuario_id, tool_name, tool_input, tool_result, entidad_tipo, entidad_id) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$convId, $userId, $toolName, json_encode($toolInput, JSON_UNESCAPED_UNICODE), json_encode($result, JSON_UNESCAPED_UNICODE), $entidadTipo, $entidadId]);
            } catch (Throwable $e) {
                logError('IA log error: ' . $e->getMessage());
            }

            $allToolCalls[] = ['tool' => $toolName, 'input' => $toolInput, 'result_summary' => isset($result['error']) ? $result['error'] : 'OK'];

            $toolResults[] = [
                'type' => 'tool_result',
                'tool_use_id' => $toolId,
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }

        // Add tool results to messages
        $anthropicMsgs[] = ['role' => 'user', 'content' => $toolResults];
    }

    // Save assistant response
    $db->prepare("INSERT INTO ia_mensajes (conversacion_id, role, content, tool_calls, tokens_in, tokens_out) VALUES (?, 'assistant', ?, ?, ?, ?)")
       ->execute([$convId, $finalReply, json_encode($allToolCalls, JSON_UNESCAPED_UNICODE), $totalIn, $totalOut]);

    // Update conversation timestamp
    $db->prepare("UPDATE ia_conversaciones SET updated_at = NOW() WHERE id = ?")->execute([$convId]);

    return [
        'reply' => $finalReply,
        'conversacion_id' => $convId,
        'tool_calls' => $allToolCalls,
        'tokens' => ['in' => $totalIn, 'out' => $totalOut],
    ];
}

function callAnthropic($config, $messages, $tools) {
    $apiKey = $config['api_key'];
    $model = $config['modelo'] ?: 'claude-sonnet-4-20250514';
    $maxTokens = (int)($config['max_tokens'] ?: 4096);
    $temperature = (float)($config['temperatura'] ?: 0.4);
    $systemPrompt = $config['prompt_sistema'] ?: '';

    $body = [
        'model' => $model,
        'max_tokens' => $maxTokens,
        'system' => $systemPrompt,
        'messages' => $messages,
    ];

    if ($temperature > 0) {
        $body['temperature'] = $temperature;
    }

    if (!empty($tools)) {
        $body['tools'] = $tools;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => "Error de conexión: $curlError"];
    }

    $data = json_decode($response, true);
    if (!$data) {
        return ['error' => "Respuesta inválida de la API (HTTP $httpCode)"];
    }

    if (isset($data['error'])) {
        return ['error' => $data['error']['message'] ?? 'Error desconocido de API'];
    }

    return $data;
}

// ── Dispatcher: elige el proveedor correcto ───────────────────────────────────
function callAI($config, $messages, $tools) {
    $proveedor = $config['proveedor'] ?? 'anthropic';
    if ($proveedor === 'groq') {
        return callGroq($config, $messages, $tools);
    }
    return callAnthropic($config, $messages, $tools);
}

// ── Groq (OpenAI-compatible API) ──────────────────────────────────────────────
function callGroq($config, $messages, $tools) {
    $apiKey      = $config['api_key'];
    $model       = $config['modelo'] ?: 'llama-3.3-70b-versatile';
    $maxTokens   = (int)($config['max_tokens']  ?: 4096);
    $temperature = (float)($config['temperatura'] ?: 0.4);
    $systemPrompt = $config['prompt_sistema'] ?: '';

    // ── Convertir mensajes de formato Anthropic → OpenAI ─────────────────────
    $openAiMessages = [];
    if ($systemPrompt) {
        $openAiMessages[] = ['role' => 'system', 'content' => $systemPrompt];
    }

    foreach ($messages as $msg) {
        $role    = $msg['role'];
        $content = $msg['content'];

        // tool_result blocks (user role con array) → mensajes role=tool
        if ($role === 'user' && is_array($content)) {
            foreach ($content as $item) {
                if (($item['type'] ?? '') === 'tool_result') {
                    $openAiMessages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $item['tool_use_id'] ?? '',
                        'content'      => is_string($item['content'])
                            ? $item['content']
                            : json_encode($item['content'], JSON_UNESCAPED_UNICODE),
                    ];
                }
            }
            continue;
        }

        // Bloques de contenido del assistant (text + tool_use) → OpenAI
        if ($role === 'assistant' && is_array($content)) {
            $textParts = [];
            $toolCalls = [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $textParts[] = $block['text'];
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = [
                        'id'       => $block['id'],
                        'type'     => 'function',
                        'function' => [
                            'name'      => $block['name'],
                            'arguments' => json_encode($block['input'] ?? [], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
            }
            $oaMsg = ['role' => 'assistant', 'content' => implode("\n", $textParts) ?: null];
            if ($toolCalls) $oaMsg['tool_calls'] = $toolCalls;
            $openAiMessages[] = $oaMsg;
            continue;
        }

        // Mensaje normal (string)
        $openAiMessages[] = [
            'role'    => $role,
            'content' => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content,
        ];
    }

    // ── Convertir tools de formato Anthropic → OpenAI ─────────────────────────
    $openAiTools = [];
    foreach ($tools as $tool) {
        $openAiTools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters'  => $tool['input_schema'] ?? ['type' => 'object', 'properties' => []],
            ],
        ];
    }

    $body = [
        'model'       => $model,
        'messages'    => $openAiMessages,
        'max_tokens'  => $maxTokens,
        'temperature' => $temperature,
    ];
    if (!empty($openAiTools)) {
        $body['tools'] = $openAiTools;
    }

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);

    $response  = curl_exec($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) return ['error' => 'Error de conexión con Groq: ' . $curlError];

    $data = json_decode($response, true);
    if (!$data) return ['error' => 'Respuesta inválida de Groq (HTTP ' . $httpCode . ')'];
    if (isset($data['error'])) return ['error' => $data['error']['message'] ?? 'Error desconocido de Groq'];

    // ── Normalizar respuesta Groq → formato Anthropic ─────────────────────────
    $choice       = $data['choices'][0] ?? [];
    $msg          = $choice['message']      ?? [];
    $finishReason = $choice['finish_reason'] ?? 'stop';

    $contentBlocks = [];

    if (!empty($msg['content'])) {
        $contentBlocks[] = ['type' => 'text', 'text' => $msg['content']];
    }

    foreach ($msg['tool_calls'] ?? [] as $toolCall) {
        $contentBlocks[] = [
            'type'  => 'tool_use',
            'id'    => $toolCall['id'],
            'name'  => $toolCall['function']['name'],
            'input' => json_decode($toolCall['function']['arguments'] ?? '{}', true) ?? [],
        ];
    }

    $stopReason = 'end_turn';
    if ($finishReason === 'tool_calls') $stopReason = 'tool_use';
    elseif ($finishReason === 'length')  $stopReason = 'max_tokens';

    return [
        'content'     => $contentBlocks,
        'stop_reason' => $stopReason,
        'usage'       => [
            'input_tokens'  => $data['usage']['prompt_tokens']     ?? 0,
            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
        ],
    ];
}

function handleHistorial($db, $userId) {
    $stmt = $db->prepare("SELECT c.id, c.titulo, c.created_at, c.updated_at,
        (SELECT COUNT(*) FROM ia_mensajes WHERE conversacion_id = c.id) AS msg_count
        FROM ia_conversaciones c WHERE c.usuario_id = ? ORDER BY c.updated_at DESC LIMIT 50");
    $stmt->execute([$userId]);
    return ['conversaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function handleCargar($db, $userId) {
    $convId = (int)($_POST['conversacion_id'] ?? $_GET['conversacion_id'] ?? 0);
    if ($convId <= 0) return ['error' => 'ID de conversación requerido.'];

    $stmt = $db->prepare("SELECT id, titulo FROM ia_conversaciones WHERE id=? AND usuario_id=?");
    $stmt->execute([$convId, $userId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv) return ['error' => 'Conversación no encontrada.'];

    $stmtM = $db->prepare("SELECT id, role, content, tool_calls, tokens_in, tokens_out, created_at FROM ia_mensajes WHERE conversacion_id = ? ORDER BY id ASC");
    $stmtM->execute([$convId]);

    return ['conversacion' => $conv, 'mensajes' => $stmtM->fetchAll(PDO::FETCH_ASSOC)];
}

function handleNueva($db, $userId) {
    $db->prepare("INSERT INTO ia_conversaciones (usuario_id, titulo) VALUES (?, 'Nueva conversación')")->execute([$userId]);
    $id = (int)$db->lastInsertId();
    return ['conversacion_id' => $id];
}

function handleEliminar($db, $userId) {
    $convId = (int)($_POST['conversacion_id'] ?? 0);
    if ($convId <= 0) return ['error' => 'ID requerido.'];

    $db->prepare("DELETE FROM ia_conversaciones WHERE id=? AND usuario_id=?")->execute([$convId, $userId]);
    return ['ok' => true];
}

function handleConfig($db) {
    if (!isAdmin()) return ['error' => 'Solo administradores pueden cambiar la configuración.'];

    // Cifrar la API key antes de almacenarla
    $newApiKey = trim($_POST['api_key'] ?? '');
    $encryptedKey = $newApiKey !== '' ? encryptField($newApiKey) : '';

    $db->prepare("UPDATE ia_config SET proveedor=?, api_key=CASE WHEN ?='' THEN api_key ELSE ? END, modelo=?, prompt_sistema=?, activo=?, max_tokens=?, temperatura=?, tools_activos=?, max_tool_iterations=? WHERE id=1")
        ->execute([
            $_POST['proveedor'] ?? 'anthropic',
            $encryptedKey, $encryptedKey,
            $_POST['modelo'] ?? 'claude-sonnet-4-20250514',
            $_POST['prompt_sistema'] ?? '',
            (int)($_POST['activo'] ?? 0),
            (int)($_POST['max_tokens'] ?? 4096),
            (float)($_POST['temperatura'] ?? 0.4),
            (int)($_POST['tools_activos'] ?? 1),
            (int)($_POST['max_tool_iterations'] ?? 8),
        ]);

    return ['ok' => true, 'mensaje' => 'Configuración guardada.'];
}
