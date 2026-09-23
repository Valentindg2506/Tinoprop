<?php
$pageTitle = 'Nueva Visita';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/automatizaciones_engine.php';

$db = getDB();
$id = intval(get('id'));
$visita = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM visitas WHERE id = ?");
    $stmt->execute([$id]);
    $visita = $stmt->fetch();
    if (!$visita) { setFlash('danger', 'Visita no encontrada.'); header('Location: index.php'); exit; }
    if (!isAdmin() && intval($visita['agente_id']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar esta visita.');
        header('Location: index.php');
        exit;
    }
    $pageTitle = 'Editar Visita';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $estadoAnterior = $visita['estado'] ?? null;

    $data = [
        'propiedad_id' => intval(post('propiedad_id')),
        'cliente_id' => intval(post('cliente_id')),
        'agente_id' => isAdmin()
            ? (intval(post('agente_id')) ?: ($visita['agente_id'] ?? currentUserId()))
            : ($visita['agente_id'] ?? currentUserId()),
        'fecha' => post('fecha'),
        'hora' => post('hora'),
        'duracion_minutos' => intval(post('duracion_minutos')) ?: 30,
        'estado' => post('estado', 'programada'),
        'valoracion' => post('valoracion') ?: null,
        'comentarios' => $_POST['comentarios'] ?? null,
    ];

    if (!$data['propiedad_id'] || !$data['cliente_id'] || !$data['fecha'] || !$data['hora']) {
        $error = 'Propiedad, cliente, fecha y hora son obligatorios.';
    } else {
        try {
            if ($id) {
                $fields = []; $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }
                $values[] = $id;
                $db->prepare("UPDATE visitas SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'visita', $id);

                // Actualizar evento del calendario vinculado
                try {
                    $fechaIni = $data['fecha'] . ' ' . $data['hora'];
                    $fechaFin = $data['fecha'] . ' ' . date('H:i:s', strtotime($data['hora'] . ' +' . $data['duracion_minutos'] . ' minutes'));
                    $sProp = $db->prepare("SELECT referencia, titulo FROM propiedades WHERE id = ?"); $sProp->execute([$data['propiedad_id']]); $propInfo = $sProp->fetch();
                    $sCli = $db->prepare("SELECT nombre FROM clientes WHERE id = ?"); $sCli->execute([$data['cliente_id']]); $cliInfo = $sCli->fetch();
                    $tituloEvento = 'Visita: ' . ($propInfo['referencia'] ?? '') . ' - ' . ($cliInfo['nombre'] ?? '');

                    $existeCal = $db->prepare("SELECT id FROM calendario_eventos WHERE visita_id = ? AND usuario_id = ?");
                    $existeCal->execute([$id, $data['agente_id']]);
                    if ($existeCal->fetch()) {
                        $db->prepare("UPDATE calendario_eventos SET titulo = ?, fecha_inicio = ?, fecha_fin = ?, propiedad_id = ?, cliente_id = ? WHERE visita_id = ? AND usuario_id = ?")
                           ->execute([$tituloEvento, $fechaIni, $fechaFin, $data['propiedad_id'], $data['cliente_id'], $id, $data['agente_id']]);
                    }
                } catch (Exception $e) { logError('Calendar sync error (edit visita): ' . $e->getMessage()); }

                try {
                    if (($data['estado'] ?? '') === 'realizada' && $estadoAnterior !== 'realizada') {
                        automatizacionesEjecutarTrigger('visita_realizada', [
                            'entidad_tipo' => 'visita',
                            'entidad_id' => intval($id),
                            'visita_id' => intval($id),
                            'cliente_id' => intval($data['cliente_id'] ?? 0),
                            'propiedad_id' => intval($data['propiedad_id'] ?? 0),
                            'agente_id' => intval($data['agente_id'] ?? 0),
                            'actor_user_id' => intval(currentUserId()),
                            'owner_user_id' => intval(currentUserId()),
                        ]);
                    }
                } catch (Throwable $e) {
                    if (function_exists('logError')) {
                        logError('Error trigger visita_realizada (edit): ' . $e->getMessage());
                    }
                }
            } else {
                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO visitas (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                $id = $db->lastInsertId();

                // Incrementar contador de visitas
                $db->prepare("UPDATE propiedades SET visitas_count = visitas_count + 1 WHERE id = ?")->execute([$data['propiedad_id']]);
                registrarActividad('crear', 'visita', $id);

                // Crear evento en el calendario automáticamente
                try {
                    $fechaIni = $data['fecha'] . ' ' . $data['hora'];
                    $fechaFin = $data['fecha'] . ' ' . date('H:i:s', strtotime($data['hora'] . ' +' . $data['duracion_minutos'] . ' minutes'));
                    $sProp = $db->prepare("SELECT referencia, titulo FROM propiedades WHERE id = ?"); $sProp->execute([$data['propiedad_id']]); $propInfo = $sProp->fetch();
                    $sCli = $db->prepare("SELECT nombre FROM clientes WHERE id = ?"); $sCli->execute([$data['cliente_id']]); $cliInfo = $sCli->fetch();
                    $tituloEvento = 'Visita: ' . ($propInfo['referencia'] ?? '') . ' - ' . ($cliInfo['nombre'] ?? '');

                    $db->prepare("INSERT INTO calendario_eventos (titulo, tipo, color, fecha_inicio, fecha_fin, propiedad_id, cliente_id, visita_id, usuario_id) VALUES (?, 'visita', '#10b981', ?, ?, ?, ?, ?, ?)")
                       ->execute([$tituloEvento, $fechaIni, $fechaFin, $data['propiedad_id'], $data['cliente_id'], $id, $data['agente_id']]);
                } catch (Exception $e) { logError('Calendar sync error (new visita): ' . $e->getMessage()); }

                // Notificar por email
                try {
                    $s = $db->prepare("SELECT * FROM visitas WHERE id = ?"); $s->execute([$id]); $vData = $s->fetch();
                    $s = $db->prepare("SELECT * FROM propiedades WHERE id = ?"); $s->execute([$data['propiedad_id']]); $pData = $s->fetch();
                    $s = $db->prepare("SELECT * FROM clientes WHERE id = ?"); $s->execute([$data['cliente_id']]); $cData = $s->fetch();
                    $s = $db->prepare("SELECT * FROM usuarios WHERE id = ?"); $s->execute([$data['agente_id']]); $aData = $s->fetch();
                    if ($vData && $pData && $cData && $aData) {
                        notificarNuevaVisita($vData, $pData, $cData, $aData);
                    }
                } catch (Exception $e) { logError('Email visita error: ' . $e->getMessage()); }

                try {
                    automatizacionesEjecutarTrigger('nueva_visita', [
                        'entidad_tipo' => 'visita',
                        'entidad_id' => intval($id),
                        'visita_id' => intval($id),
                        'cliente_id' => intval($data['cliente_id'] ?? 0),
                        'propiedad_id' => intval($data['propiedad_id'] ?? 0),
                        'agente_id' => intval($data['agente_id'] ?? 0),
                        'actor_user_id' => intval(currentUserId()),
                        'owner_user_id' => intval(currentUserId()),
                    ]);

                    if (($data['estado'] ?? '') === 'realizada') {
                        automatizacionesEjecutarTrigger('visita_realizada', [
                            'entidad_tipo' => 'visita',
                            'entidad_id' => intval($id),
                            'visita_id' => intval($id),
                            'cliente_id' => intval($data['cliente_id'] ?? 0),
                            'propiedad_id' => intval($data['propiedad_id'] ?? 0),
                            'agente_id' => intval($data['agente_id'] ?? 0),
                            'actor_user_id' => intval(currentUserId()),
                            'owner_user_id' => intval(currentUserId()),
                        ]);
                    }
                } catch (Throwable $e) {
                    if (function_exists('logError')) {
                        logError('Error trigger visita (create): ' . $e->getMessage());
                    }
                }
            }
            setFlash('success', $visita ? 'Visita actualizada.' : 'Visita programada correctamente.');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$propiedades = $db->query("SELECT id, CONCAT(referencia, ' - ', titulo) as nombre FROM propiedades WHERE estado = 'disponible' ORDER BY referencia")->fetchAll();
$clientes = $db->query("SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre_completo FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();
$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();

$v = $visita ?? [];
$preselProp = intval(get('propiedad_id'));
$preselCli = intval(get('cliente_id'));
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-calendar-event"></i> Datos de la Visita</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Propiedad *</label>
                    <select name="propiedad_id" class="form-select" required>
                        <option value="">Seleccionar propiedad...</option>
                        <?php foreach ($propiedades as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= ($v['propiedad_id'] ?? $preselProp) == $pr['id'] ? 'selected' : '' ?>><?= sanitize($pr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cliente *</label>
                    <select name="cliente_id" class="form-select" required>
                        <option value="">Seleccionar cliente...</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($v['cliente_id'] ?? $preselCli) == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" value="<?= sanitize($v['fecha'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hora *</label>
                    <input type="time" name="hora" class="form-control" value="<?= sanitize(isset($v['hora']) ? substr($v['hora'], 0, 5) : '10:00') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Duracion (min)</label>
                    <input type="number" name="duracion_minutos" class="form-control" value="<?= sanitize($v['duracion_minutos'] ?? 30) ?>" min="15" step="15">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agente</label>
                    <select name="agente_id" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($v['agente_id'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="programada" <?= ($v['estado'] ?? 'programada') === 'programada' ? 'selected' : '' ?>>Programada</option>
                        <option value="realizada" <?= ($v['estado'] ?? '') === 'realizada' ? 'selected' : '' ?>>Realizada</option>
                        <option value="cancelada" <?= ($v['estado'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        <option value="no_presentado" <?= ($v['estado'] ?? '') === 'no_presentado' ? 'selected' : '' ?>>No presentado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Valoracion (1-5)</label>
                    <select name="valoracion" class="form-select">
                        <option value="">-</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= ($v['valoracion'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?> &#9733;</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Comentarios</label>
                    <textarea name="comentarios" class="form-control" rows="3"><?= sanitize($v['comentarios'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Programar' ?> Visita</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
