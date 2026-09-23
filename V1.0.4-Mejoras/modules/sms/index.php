<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'enviar') {
        $telefono = trim(post('telefono'));
        $clienteId = intval(post('cliente_id')) ?: null;
        $mensaje = trim(post('mensaje'));

        if ($clienteId && !$telefono) {
            $cl = $db->prepare("SELECT telefono, nombre FROM clientes WHERE id = ?");
            $cl->execute([$clienteId]);
            $clData = $cl->fetch();
            $telefono = $clData['telefono'] ?? '';
            $mensaje = str_replace('{{nombre}}', $clData['nombre'] ?? '', $mensaje);
        }

        if (empty($telefono) || empty($mensaje)) {
            setFlash('danger', 'Telefono y mensaje son obligatorios.');
        } else {
            $cfg = $db->query("SELECT * FROM sms_config WHERE id = 1")->fetch();
            $estado = 'pendiente';
            $provId = null;
            $error = null;

            if ($cfg && $cfg['activo'] && !empty($cfg['api_sid']) && !empty($cfg['api_token'])) {
                if ($cfg['proveedor'] === 'twilio') {
                    $url = "https://api.twilio.com/2010-04-01/Accounts/{$cfg['api_sid']}/Messages.json";
                    $data = ['To'=>$telefono, 'From'=>$cfg['telefono_remitente'], 'Body'=>$mensaje];
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data),
                        CURLOPT_USERPWD => $cfg['api_sid'].':'.$cfg['api_token'],
                        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15
                    ]);
                    $resp = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    $result = json_decode($resp, true);
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $estado = 'enviado';
                        $provId = $result['sid'] ?? null;
                    } else {
                        $estado = 'fallido';
                        $error = $result['message'] ?? 'Error HTTP ' . $httpCode;
                    }
                } elseif ($cfg['proveedor'] === 'vonage') {
                    $url = "https://rest.nexmo.com/sms/json";
                    $data = ['api_key'=>$cfg['api_sid'], 'api_secret'=>$cfg['api_token'], 'from'=>$cfg['telefono_remitente'], 'to'=>preg_replace('/[^0-9]/','',$telefono), 'text'=>$mensaje];
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($data), CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15]);
                    $resp = curl_exec($ch); curl_close($ch);
                    $result = json_decode($resp, true);
                    if (isset($result['messages'][0]['status']) && $result['messages'][0]['status'] == '0') {
                        $estado = 'enviado';
                        $provId = $result['messages'][0]['message-id'] ?? null;
                    } else {
                        $estado = 'fallido';
                        $error = $result['messages'][0]['error-text'] ?? 'Error desconocido';
                    }
                }
            } else {
                $error = 'SMS no configurado o inactivo';
                $estado = 'fallido';
            }

            $db->prepare("INSERT INTO sms_mensajes (cliente_id, telefono_destino, mensaje, estado, proveedor_id, error_mensaje) VALUES (?,?,?,?,?,?)")
                ->execute([$clienteId, $telefono, $mensaje, $estado, $provId, $error]);
            registrarActividad('enviar', 'sms', $db->lastInsertId(), 'A: ' . $telefono);
            setFlash($estado === 'enviado' ? 'success' : 'warning', $estado === 'enviado' ? 'SMS enviado.' : 'SMS: ' . ($error ?: $estado));
        }
    }
    header('Location: index.php');
    exit;
}

$pageTitle = 'SMS';
require_once __DIR__ . '/../../includes/header.php';

$stats = $db->query("SELECT COUNT(*) as total, SUM(estado='enviado') as enviados, SUM(estado='fallido') as fallidos, SUM(estado='pendiente') as pendientes FROM sms_mensajes")->fetch();
$mensajes = $db->query("SELECT sm.*, c.nombre as cli_nombre FROM sms_mensajes sm LEFT JOIN clientes c ON sm.cliente_id = c.id ORDER BY sm.created_at DESC LIMIT 50")->fetchAll();
$clientes = $db->query("SELECT id, nombre, apellidos, telefono FROM clientes WHERE activo = 1 AND telefono != '' ORDER BY nombre")->fetchAll();
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold"><?= $stats['total'] ?? 0 ?></div><small class="text-muted">Total</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-success"><?= $stats['enviados'] ?? 0 ?></div><small class="text-muted">Enviados</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-danger"><?= $stats['fallidos'] ?? 0 ?></div><small class="text-muted">Fallidos</small></div></div></div>
    <div class="col-6 col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center py-3"><div class="fs-4 fw-bold text-warning"><?= $stats['pendientes'] ?? 0 ?></div><small class="text-muted">Pendientes</small></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-send"></i> Enviar SMS</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="accion" value="enviar">
                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <select name="cliente_id" class="form-select" id="smsCliente" onchange="document.getElementById('smsTel').value=this.options[this.selectedIndex].dataset.tel||''">
                            <option value="">Seleccionar o escribir telefono</option>
                            <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" data-tel="<?= sanitize($c['telefono']) ?>"><?= sanitize($c['nombre'].' '.$c['apellidos']) ?> - <?= sanitize($c['telefono']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Telefono</label>
                        <input type="tel" name="telefono" id="smsTel" class="form-control" placeholder="+34600000000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje <small class="text-muted">(<span id="charCount">0</span>/160)</small></label>
                        <textarea name="mensaje" class="form-control" rows="3" maxlength="480" id="smsMsg" oninput="document.getElementById('charCount').textContent=this.value.length" placeholder="Usa {{nombre}} para el nombre del cliente"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> Enviar SMS</button>
                </form>
                <hr>
                <a href="config.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-gear"></i> Configurar proveedor SMS</a>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-list-ul"></i> Mensajes recientes</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Destinatario</th><th>Mensaje</th><th>Estado</th><th>Fecha</th></tr></thead>
                        <tbody>
                            <?php foreach ($mensajes as $m): ?>
                            <tr>
                                <td><?= sanitize($m['cli_nombre'] ?: $m['telefono_destino']) ?></td>
                                <td><small><?= sanitize(mb_strimwidth($m['mensaje'],0,60,'...')) ?></small></td>
                                <td><span class="badge bg-<?= match($m['estado']){'enviado'=>'success','fallido'=>'danger','entregado'=>'info',default=>'warning'} ?>"><?= ucfirst($m['estado']) ?></span></td>
                                <td><small><?= formatFechaHora($m['created_at']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($mensajes)): ?><tr><td colspan="4" class="text-center text-muted py-4">Sin mensajes</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
