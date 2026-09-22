<?php
/**
 * IA Asistente — Dashboard principal
 * Chat inteligente con acceso completo al CRM via Anthropic Claude + Tool Use
 */
$pageTitle = 'IA Asistente';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$config = $db->query("SELECT * FROM ia_config WHERE id=1")->fetch(PDO::FETCH_ASSOC);
if (!$config) {
    $config = ['proveedor'=>'anthropic','api_key'=>'','modelo'=>'claude-sonnet-4-20250514','prompt_sistema'=>'','activo'=>0,'max_tokens'=>4096,'temperatura'=>0.4,'tools_activos'=>1,'max_tool_iterations'=>8];
}
$isConfigured = $config['activo'] && $config['api_key'];
$isAdm = isAdmin();
?>

<style>
/* IA Module — Premium Chat UI */
.ia-container {
    display: flex;
    height: calc(100vh - var(--topbar-height) - 56px);
    gap: 0;
    margin: -28px;
    overflow: hidden;
}

/* Sidebar de conversaciones */
.ia-sidebar {
    width: 280px;
    min-width: 280px;
    background: var(--bg-card);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ia-sidebar-header {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.ia-sidebar-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}

.ia-conv-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.15s;
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid transparent;
    margin-bottom: 2px;
}
.ia-conv-item:hover {
    background: var(--primary-light, rgba(16,185,129,0.06));
    color: var(--text-primary);
}
.ia-conv-item.active {
    background: var(--primary-light, rgba(16,185,129,0.1));
    border-color: var(--primary);
    color: var(--primary);
}
.ia-conv-title {
    font-size: 0.84rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}
.ia-conv-date {
    font-size: 0.7rem;
    color: var(--text-muted);
    white-space: nowrap;
}
.ia-conv-delete {
    opacity: 0;
    transition: opacity 0.15s;
    border: none;
    background: none;
    color: var(--text-muted);
    padding: 2px;
    cursor: pointer;
    font-size: 0.85rem;
}
.ia-conv-item:hover .ia-conv-delete { opacity: 1; }
.ia-conv-delete:hover { color: #ef4444; }

/* Chat principal */
.ia-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg-page);
}

.ia-chat-header {
    padding: 12px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: var(--bg-card);
}

.ia-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    scroll-behavior: smooth;
}

.ia-chat-input-area {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--bg-card);
    flex-shrink: 0;
}

/* Mensajes */
.ia-msg {
    max-width: 800px;
    margin: 0 auto 20px auto;
    display: flex;
    gap: 14px;
    animation: iaFadeIn 0.3s ease;
}

@keyframes iaFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}

.ia-msg-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    font-weight: 700;
}
.ia-msg-user .ia-msg-avatar {
    background: var(--primary);
    color: #fff;
}
.ia-msg-assistant .ia-msg-avatar {
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    color: #fff;
}
.ia-msg-body {
    flex: 1;
    min-width: 0;
}
.ia-msg-name {
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 4px;
    color: var(--text-primary);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.ia-msg-content {
    font-size: 0.92rem;
    line-height: 1.65;
    color: var(--text-secondary);
    word-wrap: break-word;
    overflow-wrap: break-word;
}
.ia-msg-content p { margin-bottom: 8px; }
.ia-msg-content p:last-child { margin-bottom: 0; }
.ia-msg-content ul, .ia-msg-content ol { padding-left: 20px; margin-bottom: 8px; }
.ia-msg-content li { margin-bottom: 4px; }
.ia-msg-content strong { color: var(--text-primary); }
.ia-msg-content code {
    background: rgba(0,0,0,0.06);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.85em;
}
.ia-msg-content pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 14px;
    border-radius: var(--radius-sm);
    overflow-x: auto;
    margin: 8px 0;
    font-size: 0.83rem;
}
.ia-msg-content pre code {
    background: transparent;
    padding: 0;
    color: inherit;
}
.ia-msg-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 8px 0;
    font-size: 0.85rem;
}
.ia-msg-content table th, .ia-msg-content table td {
    padding: 8px 12px;
    border: 1px solid var(--border);
    text-align: left;
}
.ia-msg-content table th {
    background: var(--bg-page);
    font-weight: 600;
    color: var(--text-primary);
}
.ia-msg-content h1, .ia-msg-content h2, .ia-msg-content h3 {
    color: var(--text-primary);
    margin: 12px 0 6px 0;
    font-weight: 700;
}
.ia-msg-content h1 { font-size: 1.2rem; }
.ia-msg-content h2 { font-size: 1.1rem; }
.ia-msg-content h3 { font-size: 1rem; }

/* Tool call chips */
.ia-tool-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.76rem;
    font-weight: 600;
    margin: 4px 4px 4px 0;
    background: rgba(139,92,246,0.1);
    color: #8b5cf6;
    border: 1px solid rgba(139,92,246,0.2);
}
.ia-tool-chip i { font-size: 0.8rem; }

[data-bs-theme="dark"] .ia-tool-chip {
    background: rgba(139,92,246,0.15);
    border-color: rgba(139,92,246,0.3);
}

/* Empty state */
.ia-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}
.ia-empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
    background: linear-gradient(135deg, #8b5cf6, #06b6d4);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.ia-empty h4 {
    color: var(--text-primary);
    font-weight: 700;
    margin-bottom: 8px;
}

/* Quick actions */
.ia-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 20px;
    justify-content: center;
    max-width: 600px;
}
.ia-quick-btn {
    padding: 8px 16px;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ia-quick-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light, rgba(16,185,129,0.06));
    transform: translateY(-1px);
}

/* Input area */
.ia-input-group {
    max-width: 800px;
    margin: 0 auto;
    display: flex;
    gap: 10px;
    align-items: flex-end;
}
.ia-input-group textarea {
    flex: 1;
    resize: none;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.92rem;
    min-height: 48px;
    max-height: 150px;
    line-height: 1.5;
    border: 1px solid var(--border);
    background: var(--bg-page);
    color: var(--text-primary);
    transition: border-color 0.2s;
}
.ia-input-group textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(16,185,129,0.12);
    outline: none;
}
.ia-send-btn {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    border: none;
    background: var(--primary);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
    font-size: 1.1rem;
}
.ia-send-btn:hover { filter: brightness(0.9); transform: translateY(-1px); }
.ia-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

/* Thinking indicator */
.ia-thinking {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: rgba(139,92,246,0.08);
    border-radius: var(--radius-sm);
    font-size: 0.84rem;
    color: #8b5cf6;
    font-weight: 500;
    animation: iaPulse 1.5s infinite;
}
@keyframes iaPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Token counter */
.ia-tokens {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .ia-sidebar { display: none; }
    .ia-chat-messages { padding: 16px; }
    .ia-chat-input-area { padding: 12px 16px; }
}

/* Dark mode adjustments */
[data-bs-theme="dark"] .ia-msg-content code {
    background: rgba(255,255,255,0.08);
}
[data-bs-theme="dark"] .ia-msg-content table th {
    background: #0f172a;
}
[data-bs-theme="dark"] .ia-quick-btn {
    background: var(--bg-card);
}
</style>

<!-- Not configured banner -->
<?php if (!$isConfigured): ?>
<div class="alert alert-warning d-flex align-items-center gap-3 mb-0" style="margin: -28px -28px 0 -28px; border-radius: 0; border-left: 0; border-right: 0;">
    <i class="bi bi-exclamation-triangle fs-4"></i>
    <div>
        <strong>IA no configurada.</strong>
        <?php if ($isAdm): ?>
            Configura tu API key de Anthropic y activa el asistente.
            <button class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#configModal">
                <i class="bi bi-gear"></i> Configurar
            </button>
        <?php else: ?>
            Contacta al administrador para configurar la IA.
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="ia-container">
    <!-- Sidebar de conversaciones -->
    <div class="ia-sidebar">
        <div class="ia-sidebar-header">
            <button class="btn btn-primary btn-sm w-100" id="btnNewConv">
                <i class="bi bi-plus-lg me-1"></i> Nueva conversación
            </button>
        </div>
        <div class="ia-sidebar-list" id="convList">
            <div class="text-center text-muted py-4" style="font-size:.82rem;">
                <i class="bi bi-clock-history d-block fs-4 mb-2"></i>
                Cargando historial...
            </div>
        </div>
        <?php if ($isAdm): ?>
        <div style="padding:12px; border-top:1px solid var(--border); flex-shrink:0;">
            <button class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#configModal">
                <i class="bi bi-gear me-1"></i> Configuración
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Chat principal -->
    <div class="ia-chat">
        <div class="ia-chat-header">
            <div class="d-flex align-items-center gap-2">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#6366f1);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.9rem;">
                    <i class="bi bi-cpu"></i>
                </div>
                <div>
                    <div style="font-weight:700; color:var(--text-primary); font-size:0.92rem;" id="chatTitle">Tino — IA Asistente</div>
                    <div style="font-size:0.72rem; color:var(--text-muted);" id="chatSubtitle">
                        <?= htmlspecialchars($config['modelo'] ?: 'Claude Sonnet') ?> · Tools <?= $config['tools_activos'] ? 'ON' : 'OFF' ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="ia-tokens" id="tokenCounter"></span>
                <?php if ($isAdm): ?>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#configModal" title="Configuración">
                    <i class="bi bi-gear"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="ia-chat-messages" id="chatMessages">
            <!-- Empty state -->
            <div class="ia-empty" id="emptyState">
                <div class="ia-empty-icon"><i class="bi bi-cpu"></i></div>
                <h4>Hola, soy Tino 👋</h4>
                <p style="max-width:480px; font-size:0.9rem;">
                    Tu asistente IA con acceso completo al CRM. Puedo consultar prospectos, analizar pipelines,
                    calificar leads, enviar emails y WhatsApp, crear tareas, y diagnosticar problemas de la app.
                </p>
                <div class="ia-quick-actions">
                    <button class="ia-quick-btn" data-prompt="¿Qué prospectos debo contactar hoy?">
                        <i class="bi bi-telephone"></i> Contactos de hoy
                    </button>
                    <button class="ia-quick-btn" data-prompt="Analiza el estado de todos los pipelines y dame un resumen ejecutivo">
                        <i class="bi bi-kanban"></i> Analizar pipelines
                    </button>
                    <button class="ia-quick-btn" data-prompt="Dame los KPIs de este mes: prospectos, propiedades, finanzas y tareas">
                        <i class="bi bi-graph-up"></i> KPIs del mes
                    </button>
                    <button class="ia-quick-btn" data-prompt="Califica todos los prospectos nuevos según su potencial de cierre">
                        <i class="bi bi-star"></i> Calificar prospectos
                    </button>
                    <button class="ia-quick-btn" data-prompt="¿Qué tareas tengo pendientes y cuáles están vencidas?">
                        <i class="bi bi-check2-square"></i> Tareas pendientes
                    </button>
                    <button class="ia-quick-btn" data-prompt="Dame un resumen financiero del trimestre con ingresos, gastos y pendiente de cobro">
                        <i class="bi bi-cash-stack"></i> Resumen financiero
                    </button>
                    <button class="ia-quick-btn" data-prompt="Diagnostica el estado de la aplicación: verifica tablas, configuraciones y errores">
                        <i class="bi bi-wrench-adjustable"></i> Diagnosticar app
                    </button>
                </div>
            </div>
        </div>

        <div class="ia-chat-input-area">
            <div class="ia-input-group">
                <textarea id="chatInput" rows="1" placeholder="Pregunta algo sobre el CRM..." <?= !$isConfigured ? 'disabled' : '' ?>></textarea>
                <button class="ia-send-btn" id="btnSend" <?= !$isConfigured ? 'disabled' : '' ?>>
                    <i class="bi bi-send-fill"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Config Modal -->
<?php if ($isAdm): ?>
<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-gear me-2" style="color:var(--primary);"></i>Configuración IA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="configForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Proveedor</label>
                            <select name="proveedor" class="form-select">
                                <option value="anthropic" <?= $config['proveedor']==='anthropic'?'selected':'' ?>>Anthropic (Claude)</option>
                                <option value="openai" <?= $config['proveedor']==='openai'?'selected':'' ?>>OpenAI</option>
                                <option value="groq" <?= $config['proveedor']==='groq'?'selected':'' ?>>Groq</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control" value="<?= sanitize($config['modelo']) ?>">
                            <small class="text-muted" id="modeloHint">Recomendado: claude-sonnet-4-20250514</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">API Key</label>
                            <input type="password" name="api_key" class="form-control" placeholder="<?= $config['api_key']?'••••••• Configurada':'No configurada' ?>">
                            <small class="text-muted">Déjala vacía para mantener la actual</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Prompt del sistema</label>
                            <textarea name="prompt_sistema" class="form-control" rows="6"><?= sanitize($config['prompt_sistema']) ?></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max tokens</label>
                            <input type="number" name="max_tokens" class="form-control" value="<?= $config['max_tokens'] ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Temperatura</label>
                            <input type="number" name="temperatura" class="form-control" value="<?= $config['temperatura'] ?>" step="0.1" min="0" max="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tools activos</label>
                            <select name="tools_activos" class="form-select">
                                <option value="1" <?= $config['tools_activos']?'selected':'' ?>>Sí</option>
                                <option value="0" <?= !$config['tools_activos']?'selected':'' ?>>No</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Activo</label>
                            <select name="activo" class="form-select">
                                <option value="1" <?= $config['activo']?'selected':'' ?>>Sí</option>
                                <option value="0" <?= !$config['activo']?'selected':'' ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ================================================================
// IA Chat Engine
// ================================================================
const API_URL = '<?= APP_URL ?>/modules/ia/api.php';
const CSRF = '<?= csrfToken() ?>';
const userName = '<?= sanitize(currentUserName()) ?>';

let currentConvId = null;
let totalTokensIn = 0;
let totalTokensOut = 0;
let isSending = false;

const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const btnSend = document.getElementById('btnSend');
const emptyState = document.getElementById('emptyState');
const convList = document.getElementById('convList');
const tokenCounter = document.getElementById('tokenCounter');

// Auto-resize textarea
chatInput?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

// Send on Enter (Shift+Enter for newline)
chatInput?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

btnSend?.addEventListener('click', sendMessage);

// Quick action buttons
document.querySelectorAll('.ia-quick-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        chatInput.value = btn.dataset.prompt;
        sendMessage();
    });
});

// New conversation
document.getElementById('btnNewConv')?.addEventListener('click', () => {
    currentConvId = null;
    chatMessages.innerHTML = '';
    if (emptyState) chatMessages.appendChild(emptyState);
    emptyState.style.display = '';
    document.querySelectorAll('.ia-conv-item').forEach(el => el.classList.remove('active'));
    document.getElementById('chatTitle').textContent = 'Tino — IA Asistente';
    totalTokensIn = 0; totalTokensOut = 0;
    updateTokenCounter();
});

// Load history on page load
loadConversations();

// ================================================================
// Core Functions
// ================================================================

async function sendMessage() {
    if (isSending) return;
    const msg = chatInput.value.trim();
    if (!msg) return;

    isSending = true;
    btnSend.disabled = true;
    chatInput.value = '';
    chatInput.style.height = 'auto';

    // Hide empty state
    if (emptyState) emptyState.style.display = 'none';

    // Render user message
    appendMessage('user', msg, userName);

    // Show thinking indicator
    const thinkingEl = document.createElement('div');
    thinkingEl.id = 'thinking';
    thinkingEl.className = 'ia-msg ia-msg-assistant';
    thinkingEl.innerHTML = `
        <div class="ia-msg-avatar"><i class="bi bi-cpu"></i></div>
        <div class="ia-msg-body">
            <div class="ia-thinking">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span id="thinkingText">Pensando...</span>
            </div>
        </div>`;
    chatMessages.appendChild(thinkingEl);
    scrollToBottom();

    try {
        const formData = new FormData();
        formData.append('accion', 'chat');
        formData.append('mensaje', msg);
        formData.append('csrf_token', CSRF);
        if (currentConvId) formData.append('conversacion_id', currentConvId);

        const resp = await fetch(API_URL, { method: 'POST', body: formData });
        const data = await resp.json();

        thinkingEl.remove();

        if (data.error) {
            appendMessage('assistant', '❌ ' + data.error, 'Tino');
        } else {
            // Update conversation ID
            if (data.conversacion_id) currentConvId = data.conversacion_id;

            // Show tool calls if any
            let toolHtml = '';
            if (data.tool_calls && data.tool_calls.length > 0) {
                toolHtml = '<div style="margin-bottom:8px;">';
                const toolIcons = {
                    'consultar_prospectos': 'bi-person-plus',
                    'consultar_propiedades': 'bi-house-door',
                    'consultar_clientes': 'bi-people',
                    'consultar_pipelines': 'bi-kanban',
                    'consultar_finanzas': 'bi-cash-stack',
                    'consultar_tareas': 'bi-check2-square',
                    'consultar_actividad': 'bi-clock-history',
                    'analizar_kpis': 'bi-graph-up',
                    'calificar_prospecto': 'bi-star',
                    'crear_tarea': 'bi-plus-circle',
                    'crear_notificacion': 'bi-bell',
                    'actualizar_prospecto': 'bi-pencil-square',
                    'enviar_email': 'bi-envelope-at',
                    'enviar_whatsapp': 'bi-whatsapp',
                    'diagnosticar_app': 'bi-wrench-adjustable',
                };
                data.tool_calls.forEach(tc => {
                    const icon = toolIcons[tc.tool] || 'bi-tools';
                    const label = tc.tool.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    toolHtml += `<span class="ia-tool-chip"><i class="bi ${icon}"></i> ${escHtml(label)}</span>`;
                });
                toolHtml += '</div>';
            }

            // Render response with markdown
            appendMessage('assistant', data.reply, 'Tino', toolHtml, data.tokens);

            // Update token counter
            if (data.tokens) {
                totalTokensIn += data.tokens.in || 0;
                totalTokensOut += data.tokens.out || 0;
                updateTokenCounter();
            }

            // Refresh conversation list
            loadConversations();
        }
    } catch (err) {
        thinkingEl.remove();
        appendMessage('assistant', '❌ Error de conexión: ' + err.message, 'Tino');
    }

    isSending = false;
    btnSend.disabled = false;
    chatInput.focus();
}

function appendMessage(role, content, name, toolHtml = '', tokens = null) {
    const div = document.createElement('div');
    div.className = `ia-msg ia-msg-${role}`;

    const avatarContent = role === 'user'
        ? (userName ? userName.charAt(0).toUpperCase() : 'U')
        : '<i class="bi bi-cpu"></i>';

    let renderedContent = content;
    if (role === 'assistant') {
        renderedContent = renderMarkdown(content);
    } else {
        renderedContent = escHtml(content).replace(/\n/g, '<br>');
    }

    let tokenInfo = '';
    if (tokens && (tokens.in || tokens.out)) {
        tokenInfo = `<div class="ia-tokens">${tokens.in + tokens.out} tokens</div>`;
    }

    div.innerHTML = `
        <div class="ia-msg-avatar">${avatarContent}</div>
        <div class="ia-msg-body">
            <div class="ia-msg-name">${escHtml(name)}</div>
            ${toolHtml}
            <div class="ia-msg-content">${renderedContent}</div>
            ${tokenInfo}
        </div>`;

    chatMessages.appendChild(div);
    scrollToBottom();
}

function renderMarkdown(text) {
    if (!text) return '';

    // Escape HTML first
    let html = escHtml(text);

    // Code blocks
    html = html.replace(/```(\w*)\n([\s\S]*?)```/g, (_, lang, code) => {
        return `<pre><code class="language-${lang}">${code.trim()}</code></pre>`;
    });
    // Inline code
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    // Bold
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // Italic
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
    // Headers
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
    // Tables
    html = html.replace(/^\|(.+)\|$/gm, (match) => {
        const cells = match.split('|').filter(c => c.trim());
        if (cells.every(c => /^[\s-:]+$/.test(c))) return ''; // separator row
        const tag = 'td';
        const row = cells.map(c => `<${tag}>${c.trim()}</${tag}>`).join('');
        return `<tr>${row}</tr>`;
    });
    html = html.replace(/((?:<tr>.*<\/tr>\s*)+)/g, '<table>$1</table>');
    // Unordered lists
    html = html.replace(/^[-*] (.+)$/gm, '<li>$1</li>');
    html = html.replace(/((?:<li>.*<\/li>\s*)+)/g, '<ul>$1</ul>');
    // Ordered lists
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
    // Paragraphs (double newline)
    html = html.replace(/\n\n/g, '</p><p>');
    // Single newlines to <br>
    html = html.replace(/\n/g, '<br>');
    // Wrap in paragraph
    html = '<p>' + html + '</p>';
    // Clean empty paragraphs
    html = html.replace(/<p>\s*<\/p>/g, '');
    html = html.replace(/<p>\s*(<h[1-3]>)/g, '$1');
    html = html.replace(/(<\/h[1-3]>)\s*<\/p>/g, '$1');
    html = html.replace(/<p>\s*(<ul>)/g, '$1');
    html = html.replace(/(<\/ul>)\s*<\/p>/g, '$1');
    html = html.replace(/<p>\s*(<table>)/g, '$1');
    html = html.replace(/(<\/table>)\s*<\/p>/g, '$1');
    html = html.replace(/<p>\s*(<pre>)/g, '$1');
    html = html.replace(/(<\/pre>)\s*<\/p>/g, '$1');

    return html;
}

function scrollToBottom() {
    requestAnimationFrame(() => {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
}

function updateTokenCounter() {
    if (tokenCounter) {
        const total = totalTokensIn + totalTokensOut;
        tokenCounter.textContent = total > 0 ? `${total.toLocaleString()} tokens` : '';
    }
}

// ================================================================
// Conversation Management
// ================================================================

async function loadConversations() {
    try {
        const resp = await fetch(API_URL + '?accion=historial');
        const data = await resp.json();

        if (!data.conversaciones || data.conversaciones.length === 0) {
            convList.innerHTML = `<div class="text-center text-muted py-4" style="font-size:.82rem;">
                <i class="bi bi-chat-dots d-block fs-4 mb-2"></i>Aún no hay conversaciones</div>`;
            return;
        }

        convList.innerHTML = '';
        data.conversaciones.forEach(conv => {
            const div = document.createElement('div');
            div.className = `ia-conv-item${conv.id == currentConvId ? ' active' : ''}`;
            div.dataset.id = conv.id;

            const date = new Date(conv.updated_at || conv.created_at);
            const dateStr = date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' });

            div.innerHTML = `
                <i class="bi bi-chat-dots" style="flex-shrink:0; opacity:.5;"></i>
                <span class="ia-conv-title">${escHtml(conv.titulo)}</span>
                <span class="ia-conv-date">${dateStr}</span>
                <button class="ia-conv-delete" title="Eliminar" onclick="event.stopPropagation(); deleteConv(${conv.id})">
                    <i class="bi bi-x-lg"></i>
                </button>`;

            div.addEventListener('click', () => loadConversation(conv.id));
            convList.appendChild(div);
        });
    } catch (err) {
        convList.innerHTML = `<div class="text-center text-muted py-3 small">Error cargando historial</div>`;
    }
}

async function loadConversation(convId) {
    try {
        const formData = new FormData();
        formData.append('accion', 'cargar');
        formData.append('conversacion_id', convId);
        formData.append('csrf_token', CSRF);

        const resp = await fetch(API_URL, { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.error) { alert(data.error); return; }

        currentConvId = convId;
        chatMessages.innerHTML = '';
        if (emptyState) emptyState.style.display = 'none';

        document.getElementById('chatTitle').textContent = data.conversacion?.titulo || 'Conversación';

        totalTokensIn = 0;
        totalTokensOut = 0;

        (data.mensajes || []).forEach(msg => {
            let toolHtml = '';
            if (msg.tool_calls) {
                try {
                    const tc = JSON.parse(msg.tool_calls);
                    if (tc && tc.length > 0) {
                        toolHtml = '<div style="margin-bottom:8px;">';
                        tc.forEach(t => {
                            const label = t.tool.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            toolHtml += `<span class="ia-tool-chip"><i class="bi bi-tools"></i> ${escHtml(label)}</span>`;
                        });
                        toolHtml += '</div>';
                    }
                } catch(e) {}
            }

            const tokens = { in: msg.tokens_in || 0, out: msg.tokens_out || 0 };
            totalTokensIn += tokens.in;
            totalTokensOut += tokens.out;

            appendMessage(msg.role, msg.content, msg.role === 'user' ? userName : 'Tino', toolHtml, msg.role === 'assistant' ? tokens : null);
        });

        updateTokenCounter();

        // Update active state in sidebar
        document.querySelectorAll('.ia-conv-item').forEach(el => {
            el.classList.toggle('active', el.dataset.id == convId);
        });

        scrollToBottom();
    } catch (err) {
        alert('Error al cargar conversación.');
    }
}

async function deleteConv(convId) {
    if (!confirm('¿Eliminar esta conversación?')) return;

    const formData = new FormData();
    formData.append('accion', 'eliminar');
    formData.append('conversacion_id', convId);
    formData.append('csrf_token', CSRF);

    await fetch(API_URL, { method: 'POST', body: formData });

    if (currentConvId == convId) {
        currentConvId = null;
        chatMessages.innerHTML = '';
        if (emptyState) { chatMessages.appendChild(emptyState); emptyState.style.display = ''; }
        document.getElementById('chatTitle').textContent = 'Tino — IA Asistente';
    }
    loadConversations();
}

// ================================================================
// Config form
// ================================================================
document.getElementById('configForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('accion', 'config');
    formData.append('csrf_token', CSRF);

    try {
        const resp = await fetch(API_URL, { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('configModal')).hide();
            location.reload();
        } else {
            alert(data.error || 'Error al guardar.');
        }
    } catch(e) { alert('Error de conexión.'); }
});

// ── Hint de modelo según proveedor ───────────────────────────────────────────
const modelHints = {
    anthropic: 'Recomendado: claude-sonnet-4-20250514',
    openai:    'Recomendado: gpt-4o',
    groq:      'Recomendado: llama-3.3-70b-versatile  |  Otros: llama-3.1-8b-instant, mixtral-8x7b-32768',
};
document.querySelector('select[name="proveedor"]')?.addEventListener('change', function() {
    const hint = document.getElementById('modeloHint');
    if (hint) hint.textContent = modelHints[this.value] || '';
});

// ================================================================
// Helpers
// ================================================================
function escHtml(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
