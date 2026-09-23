<?php
$pageTitle = 'Redactar Email';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();

// Obtener cuenta de email del usuario actual
$stmtCuenta = $db->prepare("SELECT * FROM email_cuentas WHERE usuario_id = ? AND activo = 1 LIMIT 1");
$stmtCuenta->execute([currentUserId()]);
$cuenta = $stmtCuenta->fetch();

if (!$cuenta) {
    setFlash('danger', 'No tienes una cuenta de email configurada. Configura tu cuenta primero.');
    header('Location: ' . APP_URL . '/modules/email/config.php');
    exit;
}

// Pre-rellenar campos si viene de respuesta
$prefillTo = get('para');
$prefillSubject = get('asunto');
$prefillBody = get('cuerpo');
$prefillClienteId = get('cliente_id');
$prefillPropiedadId = get('propiedad_id');

// Enviar email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $accion = post('accion');
    $paraEmail = post('para_email');
    $cc = post('cc');
    $asunto = post('asunto');
    $cuerpo = $_POST['cuerpo'] ?? ''; // No sanitizar HTML del cuerpo
    $clienteId = post('cliente_id') ?: null;
    $propiedadId = post('propiedad_id') ?: null;

    if (empty($paraEmail) || empty($asunto)) {
        setFlash('danger', 'El destinatario y el asunto son obligatorios.');
    } else {
        $destinatarios = [trim($paraEmail)];
        if (!empty($cc)) {
            $ccList = array_filter(array_map('trim', explode(',', $cc)));
            $destinatarios = array_merge($destinatarios, $ccList);
        }

        $destinatarios = array_values(array_unique($destinatarios));

        foreach ($destinatarios as $dest) {
            if (!filter_var($dest, FILTER_VALIDATE_EMAIL)) {
                setFlash('danger', 'Hay un email invalido en Para/CC: ' . sanitize($dest));
                $destinatarios = [];
                break;
            }
        }

        if ($accion === 'enviar' && !empty($destinatarios)) {
            $envioOk = true;
            foreach ($destinatarios as $dest) {
                if (!enviarEmail($dest, $asunto, $cuerpo, true)) {
                    $envioOk = false;
                    break;
                }
            }

            if (!$envioOk) {
                $ultimoError = function_exists('getLastEmailError') ? trim((string)getLastEmailError()) : '';
                $mensaje = 'No se pudo enviar el email. Revisa la configuracion SMTP y los logs del sistema.';
                if ($ultimoError !== '') {
                    $mensaje .= ' Detalle: ' . mb_strimwidth($ultimoError, 0, 220, '...');
                }
                setFlash('danger', $mensaje);
                goto render_form;
            }
        }

        $carpeta = ($accion === 'borrador') ? 'draft' : 'sent';
        $direccion = 'saliente';

        $stmt = $db->prepare("
            INSERT INTO email_mensajes (cuenta_id, message_id, direccion, de_email, para_email, cc, asunto, cuerpo, cuerpo_html, cliente_id, propiedad_id, leido, carpeta, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
        ");
        $messageId = '<' . uniqid('crm_') . '@' . parse_url(APP_URL, PHP_URL_HOST) . '>';
        $stmt->execute([
            $cuenta['id'],
            $messageId,
            $direccion,
            $cuenta['email'],
            $paraEmail,
            $cc ?: null,
            $asunto,
            strip_tags($cuerpo),
            $cuerpo,
            $clienteId,
            $propiedadId,
            $carpeta,
        ]);

        $emailId = $db->lastInsertId();
        registrarActividad('enviar', 'email', $emailId, ($accion === 'borrador' ? 'Borrador guardado' : 'Email enviado') . ': ' . $asunto);

        if ($accion === 'borrador') {
            setFlash('success', 'Borrador guardado correctamente.');
            header('Location: ' . APP_URL . '/modules/email/index.php?carpeta=draft');
        } else {
            setFlash('success', 'Email enviado correctamente.');
            header('Location: ' . APP_URL . '/modules/email/index.php?carpeta=sent');
        }
        exit;
    }
}

render_form:

// Obtener clientes para autocompletado
$clientes = $db->query("SELECT id, nombre, apellidos, email FROM clientes WHERE email IS NOT NULL AND email != '' ORDER BY nombre ASC")->fetchAll();

// Obtener propiedades
$propiedades = $db->query("SELECT id, referencia, titulo FROM propiedades ORDER BY titulo ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= APP_URL ?>/modules/email/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Email
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Redactar Email</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">De</label>
                        <input type="text" class="form-control bg-light" value="<?= sanitize($cuenta['nombre_display'] ?? $cuenta['email']) ?> <<?= sanitize($cuenta['email']) ?>>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Para <span class="text-danger">*</span></label>
                        <input type="email" name="para_email" class="form-control" required placeholder="email@ejemplo.com" value="<?= sanitize($prefillTo) ?>" list="clienteEmails">
                        <datalist id="clienteEmails">
                            <?php foreach ($clientes as $cli): ?>
                                <option value="<?= sanitize($cli['email']) ?>"><?= sanitize($cli['nombre'] . ' ' . $cli['apellidos']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">CC</label>
                        <input type="text" name="cc" class="form-control" placeholder="email1@ejemplo.com, email2@ejemplo.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Asunto <span class="text-danger">*</span></label>
                        <input type="text" name="asunto" class="form-control" required maxlength="500" placeholder="Asunto del email" value="<?= sanitize($prefillSubject) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mensaje</label>
                        <textarea name="cuerpo" class="form-control" rows="12" placeholder="Escribe tu mensaje aqui..."><?= sanitize($prefillBody) ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Vincular a Cliente (opcional)</label>
                            <select name="cliente_id" class="form-select">
                                <option value="">-- Sin vincular --</option>
                                <?php foreach ($clientes as $cli): ?>
                                    <option value="<?= $cli['id'] ?>" <?= $prefillClienteId == $cli['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($cli['nombre'] . ' ' . $cli['apellidos']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vincular a Propiedad (opcional)</label>
                            <select name="propiedad_id" class="form-select">
                                <option value="">-- Sin vincular --</option>
                                <?php foreach ($propiedades as $prop): ?>
                                    <option value="<?= $prop['id'] ?>" <?= $prefillPropiedadId == $prop['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($prop['referencia'] . ' - ' . $prop['titulo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" name="accion" value="borrador" class="btn btn-outline-secondary">
                            <i class="bi bi-file-earmark"></i> Guardar Borrador
                        </button>
                        <button type="submit" name="accion" value="enviar" class="btn btn-primary">
                            <i class="bi bi-send"></i> Enviar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
