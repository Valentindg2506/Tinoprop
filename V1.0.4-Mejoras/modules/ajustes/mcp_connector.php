<?php
$pageTitle = 'Conector Claude (MCP)';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/ajustes_helper.php';

$db     = getDB();
$userId = currentUserId();

// ── Acciones POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'generar_token') {
        $token = bin2hex(random_bytes(32)); // 64 chars hex
        setUserSetting('mcp_token', $token);
        setFlash('success', 'Token MCP generado. Guárdalo en tu archivo .env local.');
        header('Location: mcp_connector.php');
        exit;
    }

    if ($accion === 'revocar_token') {
        setUserSetting('mcp_token', '');
        setFlash('warning', 'Token MCP revocado. El conector local dejará de funcionar hasta que generes uno nuevo.');
        header('Location: mcp_connector.php');
        exit;
    }
}

// ── Leer token actual ──────────────────────────────────────────────────────
$settings    = getUserSettings();
$mcpToken    = $settings['mcp_token'] ?? '';
$tieneToken  = !empty($mcpToken);
$appUrl      = rtrim(APP_URL, '/');

// Config snippets
$envContent = "CRM_URL={$appUrl}\nCRM_API_PATH=/api/mcp_api.php\nMODE=sse\nPORT=3001\nHOST=127.0.0.1";

$claudeDesktopConfig = json_encode([
    'mcpServers' => [
        'crm' => [
            'command' => 'node',
            'args'    => ['RUTA_LOCAL/mcp-connector/index.js'],
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$claudeCodeConfig = json_encode([
    'mcpServers' => [
        'crm' => [
            'type'    => 'stdio',
            'command' => 'node',
            'args'    => ['RUTA_LOCAL/mcp-connector/index.js'],
        ]
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

<style>
.step-circle {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--primary); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.code-block {
    background: #1e293b; color: #e2e8f0;
    border-radius: 8px; padding: 12px 16px;
    font-family: monospace; font-size: 0.82rem;
    white-space: pre-wrap; word-break: break-all;
    position: relative;
}
[data-bs-theme="dark"] .code-block { background: #0f172a; }
.copy-btn {
    position: absolute; top: 8px; right: 8px;
    padding: 2px 10px; font-size: 0.72rem; border-radius: 4px;
}
.tool-tag {
    display: inline-block; background: var(--primary-light,rgba(16,185,129,.1));
    color: var(--primary); border-radius: 4px;
    padding: 1px 8px; font-size: 0.75rem; font-family: monospace; margin: 2px;
}
</style>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Ajustes</a>
    <h5 class="mb-0"><i class="bi bi-robot"></i> Conector Claude (MCP)</h5>
</div>

<div class="alert alert-info d-flex gap-3 align-items-start">
    <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div>
        <strong>¿Qué es esto?</strong> MCP (Model Context Protocol) te permite conectar Claude con este CRM.
        Una vez configurado, podrás hablarle a Claude y pedirle cosas como <em>"muéstrame los prospectos calientes de hoy"</em>,
        <em>"crea un prospecto llamado Juan García con teléfono +34612345678"</em> o <em>"añade una nota a PR042 con el resultado de la llamada"</em>.
        El servidor MCP corre en <strong>tu PC local</strong> y se conecta al CRM con tu login del CRM o con tu token personal.
    </div>
</div>

<div class="row g-4">

    <!-- Columna izquierda: Token -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-key"></i> Tu Token MCP</div>
            <div class="card-body">
                <?php if ($tieneToken): ?>
                <div class="alert alert-success py-2 mb-3">
                    <i class="bi bi-check-circle-fill"></i> Token activo
                </div>
                <div class="mb-3">
                    <label class="form-label small text-muted">Token (trátalo como una contraseña)</label>
                    <div class="input-group">
                        <input type="password" class="form-control font-monospace" id="tokenField" value="<?= sanitize($mcpToken) ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleToken()">
                            <i class="bi bi-eye" id="tokenEyeIcon"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyToken()">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="accion" value="generar_token">
                        <button type="submit" class="btn btn-outline-warning btn-sm"
                                onclick="return confirm('Se generará un token nuevo y el actual dejará de funcionar. ¿Continuar?')">
                            <i class="bi bi-arrow-clockwise"></i> Regenerar
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="accion" value="revocar_token">
                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Se revocará el token y el conector dejará de funcionar. ¿Continuar?')">
                            <i class="bi bi-x-lg"></i> Revocar
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <p class="text-muted">Aún no tienes un token. Genéralo para poder configurar el conector.</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="generar_token">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key"></i> Generar Token MCP
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Herramientas disponibles -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-tools"></i> Herramientas disponibles</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Lo que Claude puede hacer en tu CRM:</p>
                <div>
                    <span class="tool-tag">resumen_dashboard</span>
                    <span class="tool-tag">listar_prospectos</span>
                    <span class="tool-tag">ver_prospecto</span>
                    <span class="tool-tag">crear_prospecto</span>
                    <span class="tool-tag">anadir_nota</span>
                    <span class="tool-tag">listar_clientes</span>
                    <span class="tool-tag">ver_cliente</span>
                    <span class="tool-tag">listar_tareas</span>
                </div>
                <hr class="my-3">
                <p class="small text-muted mb-1"><strong>Ejemplos de uso con Claude:</strong></p>
                <ul class="small text-muted mb-0">
                    <li>«¿Cuántos prospectos calientes tengo hoy?»</li>
                    <li>«Crea un prospecto para María López, tel +34611223344»</li>
                    <li>«Añade al prospecto PR007 que no contestó la llamada»</li>
                    <li>«Muéstrame los clientes compradores de Madrid»</li>
                    <li>«¿Qué tareas tengo pendientes para hoy?»</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Columna derecha: Instrucciones -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-terminal"></i> Configuración paso a paso</div>
            <div class="card-body">

                <!-- Paso 1 -->
                <div class="d-flex gap-3 mb-4">
                    <div class="step-circle">1</div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Instala Node.js en tu PC</h6>
                        <p class="text-muted small mb-2">
                            Descarga e instala Node.js 18 o superior desde
                            <a href="https://nodejs.org" target="_blank" rel="noopener">nodejs.org</a>.
                            Comprueba que está instalado abriendo una terminal:
                        </p>
                        <div class="code-block">node --version<button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                    </div>
                </div>

                <!-- Paso 2 -->
                <div class="d-flex gap-3 mb-4">
                    <div class="step-circle">2</div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Descarga el conector</h6>
                        <p class="text-muted small mb-2">
                            Copia la carpeta <code>mcp-connector/</code> de tu servidor a tu PC local
                            (vía FTP, SFTP, scp o como prefieras). Estará en la raíz del CRM.
                            Luego instala las dependencias:
                        </p>
                        <div class="code-block">cd mcp-connector
npm install<button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                    </div>
                </div>

                <!-- Paso 3 -->
                <div class="d-flex gap-3 mb-4">
                    <div class="step-circle">3</div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Crea el archivo <code>.env</code></h6>
                        <p class="text-muted small mb-2">
                            Dentro de la carpeta <code>mcp-connector/</code> crea un archivo llamado
                            <strong>.env</strong> con esta configuración base:
                        </p>
                        <div class="code-block" id="envBlock"><?= htmlspecialchars($envContent) ?><button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                        <div class="alert alert-info py-1 mt-2 small"><i class="bi bi-info-circle"></i> Para Claude.ai web puedes entrar con tu login del CRM; el token personal sigue disponible para Claude Desktop o uso manual.</div>
                    </div>
                </div>

                <!-- Paso 4a: Claude Desktop -->
                <div class="d-flex gap-3 mb-4">
                    <div class="step-circle">4</div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Conecta con <strong>Claude Desktop</strong></h6>
                        <p class="text-muted small mb-2">
                            Edita el archivo de configuración de Claude Desktop
                            (<code>claude_desktop_config.json</code>) y añade la sección <code>mcpServers</code>.
                            Sustituye <code>RUTA_LOCAL</code> por la ruta real donde guardaste la carpeta en tu PC:
                        </p>
                        <div class="mb-1" style="font-size:0.75rem; color:#94a3b8;">
                            📁 Mac: <code>~/Library/Application Support/Claude/claude_desktop_config.json</code><br>
                            📁 Win: <code>%APPDATA%\Claude\claude_desktop_config.json</code>
                        </div>
                        <div class="code-block"><?= htmlspecialchars($claudeDesktopConfig) ?><button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                        <p class="small text-muted mt-2">Reinicia Claude Desktop después de guardar el archivo.</p>
                    </div>
                </div>

                <!-- Paso 4b: Claude Code -->
                <div class="d-flex gap-3 mb-4">
                    <div class="step-circle d-flex align-items-center justify-content-center" style="font-size:0.7rem;">4b</div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">O bien conecta con <strong>Claude Code</strong> (CLI)</h6>
                        <p class="text-muted small mb-2">
                            Edita <code>~/.claude/settings.json</code> y añade:
                        </p>
                        <div class="code-block"><?= htmlspecialchars($claudeCodeConfig) ?><button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                        <p class="small text-muted mt-2">O ejecuta directamente desde terminal para probar:</p>
                        <div class="code-block">node RUTA_LOCAL/mcp-connector/index.js<button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                    </div>
                </div>

                <!-- Paso 5 -->
                <div class="d-flex gap-3">
                    <div class="step-circle">5</div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">¡Listo! Prueba en Claude</h6>
                        <p class="text-muted small mb-0">
                            Abre Claude Desktop o Claude Code. Si el conector aparece conectado,
                            escribe algo como:
                        </p>
                        <div class="code-block mt-2">Dame un resumen del CRM<button class="btn btn-sm btn-outline-light copy-btn" onclick="copyCode(this)">Copiar</button></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function toggleToken() {
    const f = document.getElementById('tokenField');
    const i = document.getElementById('tokenEyeIcon');
    if (f.type === 'password') {
        f.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        f.type = 'password';
        i.className = 'bi bi-eye';
    }
}

function copyToken() {
    const f = document.getElementById('tokenField');
    navigator.clipboard.writeText(f.value).then(() => {
        const btn = f.nextElementSibling.nextElementSibling;
        btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
        setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i>', 2000);
    });
}

function copyCode(btn) {
    const block = btn.closest('.code-block');
    const text = block.childNodes[0].textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = '✓ Copiado';
        btn.classList.replace('btn-outline-light', 'btn-success');
        setTimeout(() => {
            btn.textContent = 'Copiar';
            btn.classList.replace('btn-success', 'btn-outline-light');
        }, 2000);
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
