<?php
$pageTitle = 'Evento';
require_once __DIR__ . '/../../includes/header.php';

$db = getDB();
$id = intval(get('id'));
$evento = null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM calendario_eventos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$id, currentUserId()]);
    $evento = $stmt->fetch();
    if (!$evento) {
        setFlash('danger', 'Evento no encontrado.');
        header('Location: index.php');
        exit;
    }
    $pageTitle = 'Editar Evento';
}

// Guardar evento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'titulo' => post('titulo'),
        'descripcion' => post('descripcion'),
        'tipo' => post('tipo'),
        'color' => post('color'),
        'fecha_inicio' => post('fecha_inicio'),
        'fecha_fin' => post('fecha_fin'),
        'todo_dia' => isset($_POST['todo_dia']) ? 1 : 0,
        'ubicacion' => post('ubicacion'),
        'propiedad_id' => post('propiedad_id') ?: null,
        'cliente_id' => post('cliente_id') ?: null,
        'recordatorio_minutos' => post('recordatorio_minutos') ?: null,
        'recurrente' => post('recurrente'),
    ];

    $errores = [];
    if (empty($data['titulo'])) $errores[] = 'El titulo es obligatorio.';
    if (empty($data['fecha_inicio'])) $errores[] = 'La fecha de inicio es obligatoria.';
    if (empty($data['fecha_fin'])) $errores[] = 'La fecha de fin es obligatoria.';
    if (!empty($data['fecha_inicio']) && !empty($data['fecha_fin']) && $data['fecha_fin'] < $data['fecha_inicio']) {
        $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
    }

    if (!empty($errores)) {
        setFlash('danger', implode('<br>', $errores));
    } else {
        if ($id) {
            $stmt = $db->prepare("
                UPDATE calendario_eventos SET
                    titulo = ?, descripcion = ?, tipo = ?, color = ?,
                    fecha_inicio = ?, fecha_fin = ?, todo_dia = ?,
                    ubicacion = ?, propiedad_id = ?, cliente_id = ?,
                    recordatorio_minutos = ?, recurrente = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([
                $data['titulo'], $data['descripcion'], $data['tipo'], $data['color'],
                $data['fecha_inicio'], $data['fecha_fin'], $data['todo_dia'],
                $data['ubicacion'], $data['propiedad_id'], $data['cliente_id'],
                $data['recordatorio_minutos'], $data['recurrente'],
                $id, currentUserId()
            ]);
            registrarActividad('actualizar', 'calendario_evento', $id, 'Evento actualizado: ' . $data['titulo']);
            setFlash('success', 'Evento actualizado correctamente.');
        } else {
            $stmt = $db->prepare("
                INSERT INTO calendario_eventos (titulo, descripcion, tipo, color, fecha_inicio, fecha_fin, todo_dia, ubicacion, propiedad_id, cliente_id, recordatorio_minutos, recurrente, usuario_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['titulo'], $data['descripcion'], $data['tipo'], $data['color'],
                $data['fecha_inicio'], $data['fecha_fin'], $data['todo_dia'],
                $data['ubicacion'], $data['propiedad_id'], $data['cliente_id'],
                $data['recordatorio_minutos'], $data['recurrente'], currentUserId()
            ]);
            $id = $db->lastInsertId();
            registrarActividad('crear', 'calendario_evento', $id, 'Evento creado: ' . $data['titulo']);
            setFlash('success', 'Evento creado correctamente.');
        }

        $mesRedir = date('n', strtotime($data['fecha_inicio']));
        $anioRedir = date('Y', strtotime($data['fecha_inicio']));
        header("Location: index.php?mes=$mesRedir&anio=$anioRedir");
        exit;
    }
}

// Datos para selects
$clientes = $db->query("SELECT id, nombre, apellidos FROM clientes ORDER BY nombre ASC")->fetchAll();
$propiedades = $db->query("SELECT id, referencia, titulo FROM propiedades ORDER BY titulo ASC")->fetchAll();

// Valores por defecto
$fechaDefault = get('fecha', date('Y-m-d'));
$valores = [
    'titulo' => $evento['titulo'] ?? '',
    'descripcion' => $evento['descripcion'] ?? '',
    'tipo' => $evento['tipo'] ?? 'otro',
    'color' => $evento['color'] ?? '#10b981',
    'fecha_inicio' => $evento ? date('Y-m-d\TH:i', strtotime($evento['fecha_inicio'])) : $fechaDefault . 'T09:00',
    'fecha_fin' => $evento ? date('Y-m-d\TH:i', strtotime($evento['fecha_fin'])) : $fechaDefault . 'T10:00',
    'todo_dia' => $evento['todo_dia'] ?? 0,
    'ubicacion' => $evento['ubicacion'] ?? '',
    'propiedad_id' => $evento['propiedad_id'] ?? '',
    'cliente_id' => $evento['cliente_id'] ?? get('cliente_id', ''),
    'recordatorio_minutos' => $evento['recordatorio_minutos'] ?? '',
    'recurrente' => $evento['recurrente'] ?? 'ninguno',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver al Calendario
    </a>
    <?php if ($id): ?>
    <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Seguro que deseas eliminar este evento?')">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= intval($id) ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Eliminar</button>
    </form>
    <?php endif; ?>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-event"></i> <?= $id ? 'Editar' : 'Nuevo' ?> Evento
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Titulo <span class="text-danger">*</span></label>
                        <input type="text" name="titulo" class="form-control" required maxlength="255" value="<?= sanitize($valores['titulo']) ?>" placeholder="Nombre del evento">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select">
                                <option value="visita" <?= $valores['tipo'] === 'visita' ? 'selected' : '' ?>>Visita</option>
                                <option value="reunion" <?= $valores['tipo'] === 'reunion' ? 'selected' : '' ?>>Reunion</option>
                                <option value="llamada" <?= $valores['tipo'] === 'llamada' ? 'selected' : '' ?>>Llamada</option>
                                <option value="tarea" <?= $valores['tipo'] === 'tarea' ? 'selected' : '' ?>>Tarea</option>
                                <option value="personal" <?= $valores['tipo'] === 'personal' ? 'selected' : '' ?>>Personal</option>
                                <option value="otro" <?= $valores['tipo'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="<?= sanitize($valores['color']) ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-5">
                            <label class="form-label">Fecha inicio <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_inicio" class="form-control" required value="<?= sanitize($valores['fecha_inicio']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Fecha fin <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="fecha_fin" class="form-control" required value="<?= sanitize($valores['fecha_fin']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="todo_dia" class="form-check-input" id="todoDia" value="1" <?= $valores['todo_dia'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="todoDia">Todo el dia</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripcion</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles del evento..."><?= sanitize($valores['descripcion']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ubicacion</label>
                        <input type="text" name="ubicacion" class="form-control" maxlength="255" value="<?= sanitize($valores['ubicacion']) ?>" placeholder="Direccion o lugar">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cliente (opcional)</label>
                            <select name="cliente_id" class="form-select">
                                <option value="">-- Sin vincular --</option>
                                <?php foreach ($clientes as $cli): ?>
                                <option value="<?= $cli['id'] ?>" <?= $valores['cliente_id'] == $cli['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($cli['nombre'] . ' ' . $cli['apellidos']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Propiedad (opcional)</label>
                            <select name="propiedad_id" class="form-select">
                                <option value="">-- Sin vincular --</option>
                                <?php foreach ($propiedades as $prop): ?>
                                <option value="<?= $prop['id'] ?>" <?= $valores['propiedad_id'] == $prop['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($prop['referencia'] . ' - ' . $prop['titulo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Recordatorio</label>
                            <select name="recordatorio_minutos" class="form-select">
                                <option value="">Sin recordatorio</option>
                                <option value="15" <?= $valores['recordatorio_minutos'] == 15 ? 'selected' : '' ?>>15 minutos antes</option>
                                <option value="30" <?= $valores['recordatorio_minutos'] == 30 ? 'selected' : '' ?>>30 minutos antes</option>
                                <option value="60" <?= $valores['recordatorio_minutos'] == 60 ? 'selected' : '' ?>>1 hora antes</option>
                                <option value="1440" <?= $valores['recordatorio_minutos'] == 1440 ? 'selected' : '' ?>>1 dia antes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recurrencia</label>
                            <select name="recurrente" class="form-select">
                                <option value="ninguno" <?= $valores['recurrente'] === 'ninguno' ? 'selected' : '' ?>>No se repite</option>
                                <option value="diario" <?= $valores['recurrente'] === 'diario' ? 'selected' : '' ?>>Diario</option>
                                <option value="semanal" <?= $valores['recurrente'] === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                                <option value="mensual" <?= $valores['recurrente'] === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <?= $id ? 'Actualizar' : 'Crear' ?> Evento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
