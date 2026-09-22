<?php
$pageTitle = 'Nueva Tarea';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/email.php';

$db = getDB();
$id = intval(get('id'));
$tarea = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM tareas WHERE id = ?");
    $stmt->execute([$id]);
    $tarea = $stmt->fetch();
    if (!$tarea) { setFlash('danger', 'Tarea no encontrada.'); header('Location: index.php'); exit; }
    if (!isAdmin() && intval($tarea['creado_por']) !== intval(currentUserId()) && intval($tarea['asignado_a']) !== intval(currentUserId())) {
        setFlash('danger', 'No tienes permisos para editar esta tarea.');
        header('Location: index.php');
        exit;
    }
    $pageTitle = 'Editar Tarea';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'titulo' => post('titulo'),
        'descripcion' => $_POST['descripcion'] ?? null,
        'tipo' => post('tipo', 'otro'),
        'prioridad' => post('prioridad', 'media'),
        'estado' => post('estado', 'pendiente'),
        'fecha_vencimiento' => post('fecha_vencimiento') ? post('fecha_vencimiento') . ' ' . post('hora_vencimiento', '09:00') . ':00' : null,
        'asignado_a' => isAdmin()
            ? (post('asignado_a') ?: ($tarea['asignado_a'] ?? currentUserId()))
            : ($tarea['asignado_a'] ?? currentUserId()),
        'creado_por' => $id ? ($tarea['creado_por']) : currentUserId(),
        'propiedad_id' => post('propiedad_id') ?: null,
        'cliente_id' => post('cliente_id') ?: null,
    ];

    if ($data['estado'] === 'completada' && !$tarea) {
        $data['fecha_completada'] = date('Y-m-d H:i:s');
    } elseif ($data['estado'] === 'completada' && $tarea && $tarea['estado'] !== 'completada') {
        $data['fecha_completada'] = date('Y-m-d H:i:s');
    }

    if (empty($data['titulo'])) {
        $error = 'El titulo es obligatorio.';
    } else {
        try {
            if ($id) {
                $fields = []; $values = [];
                foreach ($data as $k => $v) { $fields[] = "`$k` = ?"; $values[] = $v; }
                $values[] = $id;
                $db->prepare("UPDATE tareas SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);
                registrarActividad('editar', 'tarea', $id, $data['titulo']);

                // Sincronizar con calendario
                try {
                    if ($data['fecha_vencimiento']) {
                        $fechaFin = date('Y-m-d H:i:s', strtotime($data['fecha_vencimiento'] . ' +1 hour'));
                        $tituloEvento = 'Tarea: ' . $data['titulo'];
                        $colorTarea = $data['prioridad'] === 'urgente' ? '#ef4444' : ($data['prioridad'] === 'alta' ? '#f59e0b' : '#8b5cf6');

                        // Buscar evento existente vinculado a esta tarea
                        $existeCal = $db->prepare("SELECT id FROM calendario_eventos WHERE titulo LIKE ? AND tipo = 'tarea' AND usuario_id = ? AND DATE(fecha_inicio) = DATE(?)");
                        $existeCal->execute(['Tarea: %' . $data['titulo'] . '%', $data['asignado_a'], $data['fecha_vencimiento']]);
                        // Si tarea completada/cancelada, no sincronizar
                        if (in_array($data['estado'], ['completada', 'cancelada'])) {
                            $db->prepare("DELETE FROM calendario_eventos WHERE titulo = ? AND tipo = 'tarea' AND usuario_id = ?")
                               ->execute([$tituloEvento, $data['asignado_a']]);
                        }
                    }
                } catch (Exception $e) { logError('Calendar sync error (edit tarea): ' . $e->getMessage()); }
            } else {
                $fields = array_keys($data);
                $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                $db->prepare("INSERT INTO tareas (`" . implode('`,`', $fields) . "`) VALUES ($placeholders)")->execute(array_values($data));
                $newTareaId = $db->lastInsertId();
                registrarActividad('crear', 'tarea', $newTareaId, $data['titulo']);

                // Crear evento en calendario automáticamente
                try {
                    if ($data['fecha_vencimiento']) {
                        $fechaFin = date('Y-m-d H:i:s', strtotime($data['fecha_vencimiento'] . ' +1 hour'));
                        $tituloEvento = 'Tarea: ' . $data['titulo'];
                        $colorTarea = $data['prioridad'] === 'urgente' ? '#ef4444' : ($data['prioridad'] === 'alta' ? '#f59e0b' : '#8b5cf6');

                        $db->prepare("INSERT INTO calendario_eventos (titulo, tipo, color, fecha_inicio, fecha_fin, propiedad_id, cliente_id, usuario_id) VALUES (?, 'tarea', ?, ?, ?, ?, ?, ?)")
                           ->execute([$tituloEvento, $colorTarea, $data['fecha_vencimiento'], $fechaFin, $data['propiedad_id'], $data['cliente_id'], $data['asignado_a']]);
                    }
                } catch (Exception $e) { logError('Calendar sync error (new tarea): ' . $e->getMessage()); }

                // Notificar por email al asignado
                try {
                    $s = $db->prepare("SELECT * FROM tareas WHERE id = ?"); $s->execute([$newTareaId]); $tData = $s->fetch();
                    $s = $db->prepare("SELECT * FROM usuarios WHERE id = ?"); $s->execute([$data['asignado_a']]); $aData = $s->fetch();
                    if ($tData && $aData) { notificarTareaAsignada($tData, $aData); }
                } catch (Exception $e) { logError('Email tarea error: ' . $e->getMessage()); }
            }
            setFlash('success', $tarea ? 'Tarea actualizada.' : 'Tarea creada.');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$propiedades = $db->query("SELECT id, CONCAT(referencia, ' - ', titulo) as nombre FROM propiedades ORDER BY referencia")->fetchAll();
$clientes = $db->query("SELECT id, CONCAT(nombre, ' ', COALESCE(apellidos,'')) as nombre_completo FROM clientes WHERE activo = 1 ORDER BY nombre")->fetchAll();
$agentes = $db->query("SELECT id, CONCAT(nombre, ' ', apellidos) as nombre_completo FROM usuarios WHERE activo = 1 ORDER BY nombre")->fetchAll();

$t = $tarea ?? [];
$fechaVenc = '';
$horaVenc = '09:00';
if (!empty($t['fecha_vencimiento'])) {
    $dt = new DateTime($t['fecha_vencimiento']);
    $fechaVenc = $dt->format('Y-m-d');
    $horaVenc = $dt->format('H:i');
}
?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>

    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-check2-square"></i> Datos de la Tarea</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Titulo *</label>
                    <input type="text" name="titulo" class="form-control" value="<?= sanitize($t['titulo'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select">
                        <?php foreach (['llamada'=>'Llamada','email'=>'Email','reunion'=>'Reunion','visita'=>'Visita','gestion'=>'Gestion','documentacion'=>'Documentacion','otro'=>'Otro'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($t['tipo'] ?? 'otro') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Prioridad</label>
                    <select name="prioridad" class="form-select">
                        <option value="baja" <?= ($t['prioridad'] ?? 'media') === 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="media" <?= ($t['prioridad'] ?? 'media') === 'media' ? 'selected' : '' ?>>Media</option>
                        <option value="alta" <?= ($t['prioridad'] ?? '') === 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgente" <?= ($t['prioridad'] ?? '') === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha vencimiento</label>
                    <input type="date" name="fecha_vencimiento" class="form-control" value="<?= $fechaVenc ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hora</label>
                    <input type="time" name="hora_vencimiento" class="form-control" value="<?= $horaVenc ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="pendiente" <?= ($t['estado'] ?? 'pendiente') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="en_progreso" <?= ($t['estado'] ?? '') === 'en_progreso' ? 'selected' : '' ?>>En progreso</option>
                        <option value="completada" <?= ($t['estado'] ?? '') === 'completada' ? 'selected' : '' ?>>Completada</option>
                        <option value="cancelada" <?= ($t['estado'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Asignado a</label>
                    <select name="asignado_a" class="form-select">
                        <?php foreach ($agentes as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= ($t['asignado_a'] ?? currentUserId()) == $ag['id'] ? 'selected' : '' ?>><?= sanitize($ag['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Propiedad relacionada</label>
                    <select name="propiedad_id" class="form-select">
                        <option value="">Ninguna</option>
                        <?php foreach ($propiedades as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= ($t['propiedad_id'] ?? get('propiedad_id')) == $pr['id'] ? 'selected' : '' ?>><?= sanitize($pr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cliente relacionado</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">Ninguno</option>
                        <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($t['cliente_id'] ?? get('cliente_id')) == $cl['id'] ? 'selected' : '' ?>><?= sanitize($cl['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripcion</label>
                    <textarea name="descripcion" class="form-control" rows="4"><?= sanitize($t['descripcion'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Tarea</button>
        <a href="index.php" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
