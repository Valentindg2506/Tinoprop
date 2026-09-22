<?php
/**
 * MCP Server para Claude.ai web — OAuth 2.1 + Streamable HTTP
 * Desplegado en: https://tinoprop.es/mcp
 * Subir este archivo a Hostinger: /public_html/app/mcp.php
 * Subir el .htaccess a: /public_html/mcp/.htaccess
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/email.php';

// ── CORS ─────────────────────────────────────────────────────────────────
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('MCP_ALLOWED_ORIGINS') ?: '')));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: ' . APP_URL);
}
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Mcp-Session-Id');
header('Access-Control-Expose-Headers: Mcp-Session-Id');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$db      = getDB();
$baseUrl = 'https://tinoprop.es/mcp';
$path    = trim($_GET['path'] ?? '', '/');
$method  = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body    = $rawBody ? (json_decode($rawBody, true) ?? []) : [];

// ── Helpers ───────────────────────────────────────────────────────────────
function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function mcpOut(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function rpc(mixed $id, mixed $result): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

function getMcpUser(PDO $db): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) return null;
    $token = trim($m[1]);
    if (strlen($token) < 32) return null;
    $stmt = $db->prepare("SELECT u.* FROM usuarios u JOIN usuario_ajustes ua ON ua.usuario_id = u.id WHERE ua.clave = 'mcp_token' AND ua.valor = ? AND u.activo = 1 LIMIT 1");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── OAuth Discovery ───────────────────────────────────────────────────────
if ($path === '.well-known/oauth-protected-resource') {
    jsonOut([
        'resource'                 => "$baseUrl/mcp",
        'authorization_servers'    => [$baseUrl],
        'bearer_methods_supported' => ['header'],
        'scopes_supported'         => ['mcp', 'claudeai'],
    ]);
}

if ($path === '.well-known/oauth-authorization-server') {
    jsonOut([
        'issuer'                                    => $baseUrl,
        'authorization_endpoint'                    => "$baseUrl/authorize",
        'token_endpoint'                            => "$baseUrl/token",
        'registration_endpoint'                     => "$baseUrl/register",
        'scopes_supported'                          => ['mcp', 'claudeai'],
        'response_types_supported'                  => ['code'],
        'grant_types_supported'                     => ['authorization_code'],
        'code_challenge_methods_supported'          => ['S256'],
        'token_endpoint_auth_methods_supported'     => ['none', 'client_secret_post'],
    ]);
}

// ── Dynamic Client Registration ───────────────────────────────────────────
if ($path === 'register' && $method === 'POST') {
    $clientId     = bin2hex(random_bytes(16));
    $clientSecret = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO usuario_ajustes (usuario_id, clave, valor) VALUES (0, ?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
       ->execute(["oauth_client_$clientId", json_encode(['secret' => $clientSecret, 'data' => $body, 'created' => time()])]);
    jsonOut([
        'client_id'               => $clientId,
        'client_secret'           => $clientSecret,
        'client_id_issued_at'     => time(),
        'client_secret_expires_at' => 0,
        'redirect_uris'           => $body['redirect_uris'] ?? [],
        'grant_types'             => ['authorization_code'],
        'response_types'          => ['code'],
        'token_endpoint_auth_method' => 'none',
        'client_name'             => $body['client_name'] ?? 'MCP Client',
    ], 201);
}

// ── Authorization Form ────────────────────────────────────────────────────
if ($path === 'authorize' && $method === 'GET') {
    // Pasar OAuth params al form SIN el parámetro "path"
    $queryParams = array_filter($_GET, fn($k) => $k !== 'path', ARRAY_FILTER_USE_KEY);
    $queryStr    = http_build_query($queryParams);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>Conectar CRM</title>
<style>
  *{box-sizing:border-box}body{font-family:system-ui,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f0f2f5}
  .card{background:#fff;padding:2.5rem;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.1);max-width:440px;width:100%;margin:1rem}
  h2{margin:0 0 .5rem;color:#111;font-size:1.4rem}p{color:#555;font-size:.95rem;margin:.5rem 0 1rem}
  label{font-size:.85rem;color:#333;font-weight:600;display:block;margin-bottom:4px}
  input{width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-family:monospace;font-size:.88rem}
  input:focus{outline:none;border-color:#0066cc}
  button{width:100%;padding:12px;background:#0066cc;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:700;margin-top:1rem}
  button:hover{background:#0055aa}.hint{font-size:.78rem;color:#888;margin-top:12px;text-align:center}
</style></head><body>
<div class="card">
  <h2>Conectar CRM a Claude</h2>
  <p>Introduce tu token personal del CRM.<br>
     Lo encuentras en <strong>Ajustes → Conector Claude (MCP)</strong>.</p>
  <form method="POST" action="/mcp/authorize?{$queryStr}">
    <label for="tok">Token personal (64 caracteres)</label>
    <input id="tok" type="text" name="crm_token" placeholder="a1b2c3d4e5f6..." autocomplete="off" required minlength="32">
    <button type="submit">Conectar</button>
  </form>
  <p class="hint">Cada usuario del CRM tiene su propio token.</p>
</div></body></html>
HTML;
    exit;
}

if ($path === 'authorize' && $method === 'POST') {
    $redirectUri = $_GET['redirect_uri'] ?? $_POST['redirect_uri'] ?? '';
    $state       = $_GET['state']        ?? $_POST['state']        ?? '';
    $challenge   = $_GET['code_challenge']        ?? '';
    $challengeMethod = $_GET['code_challenge_method'] ?? 'S256';
    $crmToken    = trim($_POST['crm_token'] ?? '');

    if (!$redirectUri || !$crmToken) { http_response_code(400); echo 'Faltan parámetros'; exit; }

    $code = bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO usuario_ajustes (usuario_id, clave, valor) VALUES (0, ?, ?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
       ->execute(["oauth_code_$code", json_encode([
           'token'     => $crmToken,
           'challenge' => $challenge,
           'method'    => $challengeMethod,
           'expires'   => time() + 300,
       ])]);

    $callbackUrl = $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?')
        . 'code=' . $code . ($state ? '&state=' . urlencode($state) : '');
    header("Location: $callbackUrl");
    exit;
}

// ── Token Endpoint ────────────────────────────────────────────────────────
if ($path === 'token' && $method === 'POST') {
    header('Cache-Control: no-store');
    header('Pragma: no-cache');

    $input = $_POST ?: $body;
    $code  = $input['code'] ?? '';
    if (!$code) { jsonOut(['error' => 'invalid_grant'], 400); }

    $stmt = $db->prepare("SELECT valor FROM usuario_ajustes WHERE clave = ? AND usuario_id = 0 LIMIT 1");
    $stmt->execute(["oauth_code_$code"]);
    $row = $stmt->fetchColumn();
    if (!$row) { jsonOut(['error' => 'invalid_grant', 'error_description' => 'Código inválido o expirado'], 400); }

    $data = json_decode($row, true);
    $db->prepare("DELETE FROM usuario_ajustes WHERE clave = ? AND usuario_id = 0")->execute(["oauth_code_$code"]);

    if ($data['expires'] < time()) { jsonOut(['error' => 'invalid_grant', 'error_description' => 'Código expirado'], 400); }

    if (!empty($data['challenge'])) {
        $verifier = $input['code_verifier'] ?? '';
        if (!$verifier) { jsonOut(['error' => 'invalid_grant', 'error_description' => 'code_verifier requerido'], 400); }
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if ($expected !== $data['challenge']) {
            jsonOut(['error' => 'invalid_grant', 'error_description' => 'code_verifier inválido'], 400);
        }
    }

    jsonOut([
        'access_token'  => $data['token'],
        'token_type'    => 'Bearer',
        'expires_in'    => 31536000,
        'scope'         => 'mcp',
        'refresh_token' => bin2hex(random_bytes(32)),
    ]);
}

// ── MCP Endpoint ──────────────────────────────────────────────────────────
if ($path === '' || $path === 'mcp') {

    $user = getMcpUser($db);
    if (!$user) {
        header("WWW-Authenticate: Bearer realm=\"$baseUrl/mcp\", resource_metadata=\"$baseUrl/.well-known/oauth-protected-resource\"");
        jsonOut(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32000, 'message' => 'Unauthorized']], 401);
    }

    $isAdmin = ($user['rol'] ?? '') === 'admin';
    $userId  = (int)$user['id'];

    if ($method === 'GET') { http_response_code(405); header('Allow: POST'); exit; }
    if ($method !== 'POST') { http_response_code(405); exit; }

    $rpcMethod = $body['method'] ?? '';
    $rpcId     = $body['id'] ?? null;
    $params    = $body['params'] ?? [];

    switch ($rpcMethod) {

        case 'initialize':
            header('Mcp-Session-Id: ' . bin2hex(random_bytes(16)));
            mcpOut(rpc($rpcId, [
                'protocolVersion' => '2024-11-05',
                'capabilities'    => ['tools' => ['listChanged' => true]],
                'serverInfo'      => ['name' => 'crm-tinoprop', 'version' => '3.0.0'],
            ]));

        case 'notifications/initialized':
            http_response_code(202); exit;

        case 'ping':
            mcpOut(rpc($rpcId, new stdClass));

        case 'tools/list':
            mcpOut(rpc($rpcId, ['tools' => getMcpTools()]));

        case 'tools/call':
            $toolName = $params['name'] ?? '';
            $args     = $params['arguments'] ?? [];
            try {
                $result = callMcpTool($db, $isAdmin, $userId, $toolName, $args);
                mcpOut(rpc($rpcId, ['content' => [['type' => 'text', 'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]]]));
            } catch (Throwable $e) {
                mcpOut(rpc($rpcId, ['content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]], 'isError' => true]));
            }

        default:
            mcpOut(['jsonrpc' => '2.0', 'id' => $rpcId, 'error' => ['code' => -32601, 'message' => "Método desconocido: $rpcMethod"]]);
    }
}

http_response_code(404);
echo json_encode(['error' => 'Not found', 'path' => $path]);
exit;

// ══════════════════════════════════════════════════════════════════════════
// TOOLS
// ══════════════════════════════════════════════════════════════════════════

function getMcpTools(): array {
    return [
        ['name'=>'resumen_dashboard','description'=>'Resumen del CRM: prospectos por etapa, clientes, tareas y contactos de hoy.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        ['name'=>'estadisticas','description'=>'Estadísticas de ventas por período (hoy/semana/mes/trimestre/anio).','inputSchema'=>['type'=>'object','properties'=>['periodo'=>['type'=>'string','enum'=>['hoy','semana','mes','trimestre','anio']]]]],
        ['name'=>'buscar','description'=>'Búsqueda global en prospectos, clientes y propiedades.','inputSchema'=>['type'=>'object','properties'=>['q'=>['type'=>'string']],'required'=>['q']]],
        ['name'=>'listar_prospectos','description'=>'Lista prospectos con filtros opcionales.','inputSchema'=>['type'=>'object','properties'=>['busqueda'=>['type'=>'string'],'etapa'=>['type'=>'string'],'temperatura'=>['type'=>'string'],'contactar_hoy'=>['type'=>'boolean'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_prospecto','description'=>'Datos completos e historial de un prospecto.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_prospecto','description'=>'Crea un nuevo prospecto.','inputSchema'=>['type'=>'object','properties'=>['nombre'=>['type'=>'string'],'telefono'=>['type'=>'string'],'email'=>['type'=>'string'],'tipo_propiedad'=>['type'=>'string'],'precio_estimado'=>['type'=>'number'],'localidad'=>['type'=>'string'],'provincia'=>['type'=>'string'],'notas'=>['type'=>'string'],'etapa'=>['type'=>'string'],'temperatura'=>['type'=>'string']],'required'=>['nombre']]],
        ['name'=>'actualizar_prospecto','description'=>'Actualiza etapa, temperatura, notas o fecha de contacto.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'etapa'=>['type'=>'string'],'temperatura'=>['type'=>'string'],'notas_internas'=>['type'=>'string'],'fecha_proximo_contacto'=>['type'=>'string'],'proxima_accion'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'programar_contacto','description'=>'Cambia fecha próximo contacto (acepta id o ids separados por coma).','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'ids'=>['type'=>'string'],'fecha'=>['type'=>'string'],'proxima_accion'=>['type'=>'string'],'temperatura'=>['type'=>'string'],'etapa'=>['type'=>'string']]]],
        ['name'=>'mover_etapas','description'=>'Mueve prospectos a una etapa del pipeline.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'ids'=>['type'=>'string'],'etapa'=>['type'=>'string','enum'=>['nuevo_lead','contactado','seguimiento','visita_programada','en_negociacion','captado','descartado']]],'required'=>['etapa']]],
        ['name'=>'anadir_nota','description'=>'Añade una nota al historial de un prospecto.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'contenido'=>['type'=>'string'],'tipo'=>['type'=>'string']],'required'=>['prospecto_id','contenido']]],
        ['name'=>'convertir_a_cliente','description'=>'Convierte un prospecto en cliente.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'tipo'=>['type'=>'string']],'required'=>['prospecto_id']]],
        ['name'=>'informe_prospectos','description'=>'Informe del embudo de captación.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        ['name'=>'listar_clientes','description'=>'Lista clientes.','inputSchema'=>['type'=>'object','properties'=>['busqueda'=>['type'=>'string'],'tipo'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_cliente','description'=>'Datos completos de un cliente.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_cliente','description'=>'Crea un nuevo cliente.','inputSchema'=>['type'=>'object','properties'=>['nombre'=>['type'=>'string'],'apellidos'=>['type'=>'string'],'tipo'=>['type'=>'string'],'email'=>['type'=>'string'],'telefono'=>['type'=>'string'],'localidad'=>['type'=>'string'],'notas'=>['type'=>'string']],'required'=>['nombre']]],
        ['name'=>'actualizar_cliente','description'=>'Actualiza datos de un cliente.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'nombre'=>['type'=>'string'],'apellidos'=>['type'=>'string'],'tipo'=>['type'=>'string'],'email'=>['type'=>'string'],'telefono'=>['type'=>'string'],'notas'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'anadir_nota_cliente','description'=>'Añade una nota a un cliente.','inputSchema'=>['type'=>'object','properties'=>['cliente_id'=>['type'=>'number'],'contenido'=>['type'=>'string'],'tipo'=>['type'=>'string']],'required'=>['cliente_id','contenido']]],
        ['name'=>'listar_tareas','description'=>'Lista tareas del usuario.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'solo_hoy'=>['type'=>'boolean']]]],
        ['name'=>'crear_tarea','description'=>'Crea una nueva tarea.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'tipo'=>['type'=>'string'],'fecha_vencimiento'=>['type'=>'string'],'descripcion'=>['type'=>'string'],'prioridad'=>['type'=>'string'],'cliente_id'=>['type'=>'number']],'required'=>['titulo','tipo']]],
        ['name'=>'completar_tarea','description'=>'Marca una tarea como completada.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'actualizar_tarea','description'=>'Actualiza estado o fecha de una tarea.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'titulo'=>['type'=>'string'],'estado'=>['type'=>'string'],'prioridad'=>['type'=>'string'],'fecha_vencimiento'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'cancelar_tarea','description'=>'Cancela una tarea.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'listar_propiedades','description'=>'Busca propiedades en el catálogo.','inputSchema'=>['type'=>'object','properties'=>['busqueda'=>['type'=>'string'],'estado'=>['type'=>'string'],'precio_maximo'=>['type'=>'number'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_propiedad','description'=>'Datos completos de una propiedad.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_propiedad','description'=>'Da de alta una propiedad en el catálogo.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'tipo'=>['type'=>'string'],'estado'=>['type'=>'string'],'precio'=>['type'=>'number'],'localidad'=>['type'=>'string'],'descripcion'=>['type'=>'string'],'habitaciones'=>['type'=>'number'],'banos'=>['type'=>'number'],'metros'=>['type'=>'number']],'required'=>['titulo']]],
        ['name'=>'actualizar_propiedad','description'=>'Actualiza estado o precio de una propiedad.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'estado'=>['type'=>'string'],'precio'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['id']]],
        ['name'=>'listar_visitas','description'=>'Lista visitas programadas.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'solo_hoy'=>['type'=>'boolean'],'limite'=>['type'=>'number']]]],
        ['name'=>'crear_visita','description'=>'Programa una visita (fecha: YYYY-MM-DD HH:MM).','inputSchema'=>['type'=>'object','properties'=>['propiedad_id'=>['type'=>'number'],'cliente_id'=>['type'=>'number'],'fecha'=>['type'=>'string'],'duracion_min'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['propiedad_id','fecha']]],
        ['name'=>'actualizar_visita','description'=>'Actualiza estado de una visita.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number'],'estado'=>['type'=>'string','enum'=>['programada','realizada','cancelada','no_presentado']]],'required'=>['id','estado']]],
        ['name'=>'listar_presupuestos','description'=>'Lista presupuestos.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_presupuesto','description'=>'Detalle de un presupuesto.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'crear_presupuesto','description'=>'Crea un presupuesto.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'cliente_id'=>['type'=>'number'],'total'=>['type'=>'number'],'detalles'=>['type'=>'string'],'validez_dias'=>['type'=>'number']],'required'=>['total']]],
        ['name'=>'listar_facturas','description'=>'Lista facturas.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_factura','description'=>'Detalle de una factura.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'listar_contratos','description'=>'Lista contratos.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'ver_contrato','description'=>'Detalle de un contrato.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'enviar_contrato','description'=>'Envía un contrato por email para firma.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'enviar_whatsapp','description'=>'Envía un WhatsApp a un prospecto.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'mensaje'=>['type'=>'string']],'required'=>['prospecto_id','mensaje']]],
        ['name'=>'enviar_email','description'=>'Envía un email a un prospecto.','inputSchema'=>['type'=>'object','properties'=>['prospecto_id'=>['type'=>'number'],'asunto'=>['type'=>'string'],'cuerpo_html'=>['type'=>'string']],'required'=>['prospecto_id','asunto','cuerpo_html']]],
        ['name'=>'listar_finanzas','description'=>'Lista comisiones y registros financieros.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'tipo'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'registrar_comision','description'=>'Crea un registro de comisión u honorario.','inputSchema'=>['type'=>'object','properties'=>['concepto'=>['type'=>'string'],'importe'=>['type'=>'number'],'iva'=>['type'=>'number'],'tipo'=>['type'=>'string'],'estado'=>['type'=>'string'],'cliente_id'=>['type'=>'number'],'propiedad_id'=>['type'=>'number'],'notas'=>['type'=>'string']],'required'=>['concepto','importe']]],
        ['name'=>'marcar_cobrado','description'=>'Marca una comisión como cobrada.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'number']],'required'=>['id']]],
        ['name'=>'informe_finanzas','description'=>'Informe financiero de los últimos 6 meses.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        ['name'=>'listar_automatizaciones','description'=>'Lista automatizaciones disponibles.','inputSchema'=>['type'=>'object','properties'=>new stdClass]],
        ['name'=>'listar_eventos','description'=>'Lista eventos del calendario.','inputSchema'=>['type'=>'object','properties'=>['desde'=>['type'=>'string'],'hasta'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'crear_evento','description'=>'Crea un evento en el calendario.','inputSchema'=>['type'=>'object','properties'=>['titulo'=>['type'=>'string'],'tipo'=>['type'=>'string'],'fecha_inicio'=>['type'=>'string'],'fecha_fin'=>['type'=>'string'],'ubicacion'=>['type'=>'string'],'cliente_id'=>['type'=>'number'],'propiedad_id'=>['type'=>'number']],'required'=>['titulo','fecha_inicio']]],
        ['name'=>'listar_campanas','description'=>'Lista campañas de marketing.','inputSchema'=>['type'=>'object','properties'=>['estado'=>['type'=>'string'],'limite'=>['type'=>'number']]]],
        ['name'=>'pipeline_kanban','description'=>'Vista Kanban de pipelines de ventas.','inputSchema'=>['type'=>'object','properties'=>['pipeline_id'=>['type'=>'number']]]],
    ];
}

// ══════════════════════════════════════════════════════════════════════════
// TOOL DISPATCH → mcp_api.php
// ══════════════════════════════════════════════════════════════════════════

function callMcpTool(PDO $db, bool $isAdmin, int $userId, string $tool, array $args): mixed {
    $map = [
        'resumen_dashboard'       => ['resumen',               'GET',  []],
        'estadisticas'            => ['estadisticas',          'GET',  ['periodo' => $args['periodo'] ?? 'mes']],
        'buscar'                  => ['buscar',                'GET',  ['q' => $args['q'] ?? '']],
        'listar_prospectos'       => ['prospectos',            'GET',  ['q' => $args['busqueda'] ?? '', 'etapa' => $args['etapa'] ?? '', 'temperatura' => $args['temperatura'] ?? '', 'contactar_hoy' => ($args['contactar_hoy'] ?? false) ? '1' : '', 'limit' => $args['limite'] ?? 20]],
        'ver_prospecto'           => ['prospecto',             'GET',  ['id' => $args['id'] ?? 0]],
        'crear_prospecto'         => ['crear_prospecto',       'POST', []],
        'actualizar_prospecto'    => ['actualizar_prospecto',  'POST', []],
        'programar_contacto'      => ['programar_contacto',    'POST', []],
        'mover_etapas'            => ['mover_etapas',          'POST', []],
        'anadir_nota'             => ['anadir_nota',           'POST', []],
        'convertir_a_cliente'     => ['convertir_cliente',     'POST', []],
        'informe_prospectos'      => ['informe_prospectos',    'GET',  []],
        'listar_clientes'         => ['clientes',              'GET',  ['q' => $args['busqueda'] ?? '', 'tipo' => $args['tipo'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_cliente'             => ['cliente',               'GET',  ['id' => $args['id'] ?? 0]],
        'crear_cliente'           => ['crear_cliente',         'POST', []],
        'actualizar_cliente'      => ['actualizar_cliente',    'POST', []],
        'anadir_nota_cliente'     => ['anadir_nota_cliente',   'POST', []],
        'listar_tareas'           => ['tareas',                'GET',  ['estado' => $args['estado'] ?? '', 'solo_hoy' => ($args['solo_hoy'] ?? false) ? '1' : '0']],
        'crear_tarea'             => ['crear_tarea',           'POST', []],
        'completar_tarea'         => ['completar_tarea',       'POST', []],
        'actualizar_tarea'        => ['actualizar_tarea',      'POST', []],
        'cancelar_tarea'          => ['cancelar_tarea',        'POST', []],
        'listar_propiedades'      => ['propiedades',           'GET',  ['q' => $args['busqueda'] ?? '', 'estado' => $args['estado'] ?? '', 'max' => $args['precio_maximo'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_propiedad'           => ['propiedad',             'GET',  ['id' => $args['id'] ?? 0]],
        'crear_propiedad'         => ['crear_propiedad',       'POST', []],
        'actualizar_propiedad'    => ['actualizar_propiedad',  'POST', []],
        'listar_visitas'          => ['visitas',               'GET',  ['estado' => $args['estado'] ?? '', 'solo_hoy' => ($args['solo_hoy'] ?? false) ? '1' : '0', 'limit' => $args['limite'] ?? 20]],
        'crear_visita'            => ['crear_visita',          'POST', []],
        'actualizar_visita'       => ['actualizar_visita',     'POST', []],
        'listar_presupuestos'     => ['presupuestos',          'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_presupuesto'         => ['presupuesto',           'GET',  ['id' => $args['id'] ?? 0]],
        'crear_presupuesto'       => ['crear_presupuesto',     'POST', []],
        'listar_facturas'         => ['facturas',              'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_factura'             => ['factura',               'GET',  ['id' => $args['id'] ?? 0]],
        'listar_contratos'        => ['contratos',             'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'ver_contrato'            => ['contrato',              'GET',  ['id' => $args['id'] ?? 0]],
        'enviar_contrato'         => ['enviar_contrato',       'POST', []],
        'enviar_whatsapp'         => ['enviar_whatsapp',       'POST', []],
        'enviar_email'            => ['enviar_email',          'POST', []],
        'listar_finanzas'         => ['finanzas',              'GET',  ['estado' => $args['estado'] ?? '', 'tipo' => $args['tipo'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'registrar_comision'      => ['crear_finanza',         'POST', []],
        'marcar_cobrado'          => ['marcar_cobrado',        'POST', []],
        'informe_finanzas'        => ['informe_finanzas',      'GET',  []],
        'listar_automatizaciones' => ['automatizaciones',      'GET',  []],
        'listar_eventos'          => ['calendario',            'GET',  ['desde' => $args['desde'] ?? '', 'hasta' => $args['hasta'] ?? '', 'limit' => $args['limite'] ?? 50]],
        'crear_evento'            => ['crear_evento',          'POST', []],
        'listar_campanas'         => ['campanas',              'GET',  ['estado' => $args['estado'] ?? '', 'limit' => $args['limite'] ?? 20]],
        'pipeline_kanban'         => ['pipeline_kanban',       'GET',  ['pipeline_id' => $args['pipeline_id'] ?? 0]],
    ];

    if (!isset($map[$tool])) throw new Exception("Herramienta desconocida: $tool");
    [$action, $httpMethod, $getParams] = $map[$tool];

    // Llamar a mcp_api.php via HTTP interno
    $apiUrl = 'https://tinoprop.es/api/mcp_api.php';
    // Obtener el token del usuario actual desde el header de la petición original
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    $token = trim(preg_replace('/^Bearer\s+/i', '', $authHeader));

    $url = $apiUrl . '?action=' . urlencode($action);
    foreach ($getParams as $k => $v) {
        if ($v !== '' && $v !== null) $url .= '&' . urlencode($k) . '=' . urlencode((string)$v);
    }

    $opts = [
        'http' => [
            'method'  => $httpMethod,
            'header'  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
            'timeout' => 15,
        ]
    ];
    if ($httpMethod === 'POST' && !empty($args)) {
        $opts['http']['content'] = json_encode($args);
    }

    $ctx      = stream_context_create($opts);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) throw new Exception("No se pudo conectar a la API del CRM");

    $data = json_decode($response, true);
    if ($data === null) throw new Exception("Respuesta inválida: " . substr($response, 0, 200));
    if (isset($data['error'])) throw new Exception($data['error']);
    return $data;
}
