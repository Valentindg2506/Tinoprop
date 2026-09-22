<?php
/**
 * Configuracion del sistema de reservas publicas (Booking)
 * Panel de administracion
 */

// POST handler antes de incluir header (para redirect)
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

$db = getDB();

// Procesar formulario de configuracion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_config'])) {
    verifyCsrf();

    $titulo = trim($_POST['titulo'] ?? 'Reservar Cita');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $duracion_minutos = intval($_POST['duracion_minutos'] ?? 30);
    $horario_inicio = trim($_POST['horario_inicio'] ?? '09:00');
    $horario_fin = trim($_POST['horario_fin'] ?? '18:00');
    $dias_disponibles = isset($_POST['dias_disponibles']) ? implode(',', $_POST['dias_disponibles']) : '1,2,3,4,5';
    $dias_anticipacion = intval($_POST['dias_anticipacion'] ?? 30);
    $color_primario = trim($_POST['color_primario'] ?? '#10b981');
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validaciones
    $errores = [];
    if (empty($titulo)) $errores[] = 'El titulo es obligatorio.';
    if ($duracion_minutos < 10 || $duracion_minutos > 120) $errores[] = 'La duracion debe estar entre 10 y 120 minutos.';
    if ($horario_inicio >= $horario_fin) $errores[] = 'El horario de inicio debe ser anterior al de fin.';
    if ($dias_anticipacion < 1 || $dias_anticipacion > 365) $errores[] = 'Los dias de anticipacion deben estar entre 1 y 365.';

    if (!empty($errores)) {
        setFlash('danger', implode(' ', $errores));
    } else {
        // Verificar si ya existe una configuracion
        $stmtExiste = $db->query("SELECT id FROM booking_config LIMIT 1");
        $configExistente = $stmtExiste->fetch(PDO::FETCH_ASSOC);

        if ($configExistente) {
            $stmt = $db->prepare("UPDATE booking_config SET titulo = ?, descripcion = ?, duracion_minutos = ?, horario_inicio = ?, horario_fin = ?, dias_disponibles = ?, dias_anticipacion = ?, color_primario = ?, activo = ? WHERE id = ?");
            $stmt->execute([$titulo, $descripcion, $duracion_minutos, $horario_inicio . ':00', $horario_fin . ':00', $dias_disponibles, $dias_anticipacion, $color_primario, $activo, $configExistente['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO booking_config (titulo, descripcion, duracion_minutos, horario_inicio, horario_fin, dias_disponibles, dias_anticipacion, agente_id, color_primario, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$titulo, $descripcion, $duracion_minutos, $horario_inicio . ':00', $horario_fin . ':00', $dias_disponibles, $dias_anticipacion, currentUserId(), $color_primario, $activo]);
        }

        setFlash('success', 'Configuracion de booking guardada correctamente.');
        header('Location: booking_config.php');
        exit;
    }
}

// Procesar cambio de estado de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    verifyCsrf();
    $reservaId = intval($_POST['reserva_id'] ?? 0);
    $nuevoEstado = $_POST['nuevo_estado'] ?? '';
    $estadosValidos = ['pendiente', 'confirmada', 'cancelada', 'completada'];

    if ($reservaId > 0 && in_array($nuevoEstado, $estadosValidos)) {
        $stmt = $db->prepare("UPDATE booking_reservas SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevoEstado, $reservaId]);
        setFlash('success', 'Estado de la reserva actualizado.');
    }
    header('Location: booking_config.php');
    exit;
}

// Ahora incluir header (que hace output)
$pageTitle = 'Configuracion Booking';
require_once __DIR__ . '/../../includes/header.php';

// Obtener configuracion existente
$stmtConfig = $db->query("SELECT * FROM booking_config LIMIT 1");
$config = $stmtConfig->fetch(PDO::FETCH_ASSOC);

$valores = [
    'titulo' => $config['titulo'] ?? 'Reservar Cita',
    'descripcion' => $config['descripcion'] ?? '',
    'duracion_minutos' => $config['duracion_minutos'] ?? 30,
    'horario_inicio' => substr($config['horario_inicio'] ?? '09:00:00', 0, 5),
    'horario_fin' => substr($config['horario_fin'] ?? '18:00:00', 0, 5),
    'dias_disponibles' => explode(',', $config['dias_disponibles'] ?? '1,2,3,4,5'),
    'dias_anticipacion' => $config['dias_anticipacion'] ?? 30,
    'color_primario' => $config['color_primario'] ?? '#10b981',
    'activo' => $config['activo'] ?? 1,
];

// Obtener reservas existentes
$stmtReservas = $db->query("
    SELECT br.*, bc.titulo as config_titulo
    FROM booking_reservas br
    LEFT JOIN booking_config bc ON br.booking_config_id = bc.id
    ORDER BY br.fecha DESC, br.hora DESC
    LIMIT 50
");
$reservas = $stmtReservas->fetchAll(PDO::FETCH_ASSOC);

$diasNombres = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo'];
$estadoClases = [
    'pendiente' => 'warning',
    'confirmada' => 'success',
    'cancelada' => 'danger',
    'completada' => 'info',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Calendario
        </a>
    </div>
    <a href="<?= APP_URL ?>/booking.php" target="_blank" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-box-arrow-up-right"></i> Ver Pagina Publica de Reservas
    </a>
</div>

<div class="row g-4">
    <!-- Configuracion -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-gear"></i> Configuracion del Sistema de Reservas</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="guardar_config" value="1">

                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Titulo <span class="text-danger">*</span></label>
                            <input type="text" name="titulo" class="form-control" required maxlength="200"
                                   value="<?= sanitize($valores['titulo']) ?>" placeholder="Reservar Cita">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color primario</label>
                            <div class="input-group">
                                <input type="color" name="color_primario" class="form-control form-control-color"
                                       value="<?= sanitize($valores['color_primario']) ?>" title="Color del tema">
                                <input type="text" class="form-control" value="<?= sanitize($valores['color_primario']) ?>" id="colorText" readonly style="max-width: 100px;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripcion</label>
                        <textarea name="descripcion" class="form-control" rows="2" maxlength="500"
                                  placeholder="Descripcion que se mostrara en la pagina publica..."><?= sanitize($valores['descripcion']) ?></textarea>
                    </div>

                    <hr>
                    <h6 class="mb-3"><i class="bi bi-clock"></i> Horarios</h6>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Duracion de la cita</label>
                            <select name="duracion_minutos" class="form-select">
                                <?php foreach ([15, 30, 45, 60] as $dur): ?>
                                <option value="<?= $dur ?>" <?= intval($valores['duracion_minutos']) === $dur ? 'selected' : '' ?>><?= $dur ?> minutos</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hora de inicio</label>
                            <input type="time" name="horario_inicio" class="form-control" value="<?= sanitize($valores['horario_inicio']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hora de fin</label>
                            <input type="time" name="horario_fin" class="form-control" value="<?= sanitize($valores['horario_fin']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Dias disponibles</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($diasNombres as $num => $nombre): ?>
                            <div class="form-check">
                                <input type="checkbox" name="dias_disponibles[]" value="<?= $num ?>" class="form-check-input" id="dia<?= $num ?>"
                                       <?= in_array($num, $valores['dias_disponibles']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="dia<?= $num ?>"><?= $nombre ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Dias de anticipacion maxima</label>
                            <div class="input-group">
                                <input type="number" name="dias_anticipacion" class="form-control" min="1" max="365"
                                       value="<?= intval($valores['dias_anticipacion']) ?>">
                                <span class="input-group-text">dias</span>
                            </div>
                            <small class="text-muted">Cuantos dias en el futuro se puede reservar.</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input type="checkbox" name="activo" class="form-check-input" id="activoSwitch" value="1"
                                       <?= $valores['activo'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activoSwitch">
                                    <strong>Sistema de reservas activo</strong>
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= APP_URL ?>/booking.php" target="_blank" class="text-decoration-none">
                            <i class="bi bi-link-45deg"></i> <?= APP_URL ?>/booking.php
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Configuracion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Vista previa -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h6 class="mb-0"><i class="bi bi-eye"></i> Vista Previa</h6>
            </div>
            <div class="card-body text-center p-4" id="previewCard" style="background: <?= sanitize($valores['color_primario']) ?>; color: #fff; border-radius: 0 0 8px 8px;">
                <i class="bi bi-calendar-check fs-1"></i>
                <h5 class="mt-2"><?= sanitize($valores['titulo']) ?></h5>
                <?php if (!empty($valores['descripcion'])): ?>
                <p class="small opacity-75"><?= sanitize($valores['descripcion']) ?></p>
                <?php endif; ?>
                <div class="mt-3 small opacity-75">
                    <i class="bi bi-clock"></i> <?= sanitize($valores['horario_inicio']) ?> - <?= sanitize($valores['horario_fin']) ?><br>
                    <i class="bi bi-hourglass-split"></i> <?= intval($valores['duracion_minutos']) ?> min por cita
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Resumen</h6>
                <?php
                $totalReservas = count($reservas);
                $pendientes = count(array_filter($reservas, function($r) { return $r['estado'] === 'pendiente'; }));
                $confirmadas = count(array_filter($reservas, function($r) { return $r['estado'] === 'confirmada'; }));
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total reservas</span>
                    <strong><?= $totalReservas ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Pendientes</span>
                    <span class="badge bg-warning"><?= $pendientes ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Confirmadas</span>
                    <span class="badge bg-success"><?= $confirmadas ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Reservas -->
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-check"></i> Reservas</h5>
        <span class="badge bg-secondary"><?= count($reservas) ?> registros</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reservas)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-calendar-x d-block fs-1 mb-2"></i>
            <p>No hay reservas registradas aun.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha / Hora</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>Estado</th>
                        <th>Notas</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservas as $reserva): ?>
                    <tr>
                        <td>
                            <strong><?= date('d/m/Y', strtotime($reserva['fecha'])) ?></strong><br>
                            <small class="text-muted"><?= date('H:i', strtotime($reserva['hora'])) ?>h</small>
                        </td>
                        <td><?= sanitize($reserva['nombre']) ?></td>
                        <td><a href="mailto:<?= sanitize($reserva['email']) ?>"><?= sanitize($reserva['email']) ?></a></td>
                        <td><?= sanitize($reserva['telefono'] ?? '-') ?></td>
                        <td>
                            <span class="badge bg-<?= $estadoClases[$reserva['estado']] ?? 'secondary' ?>">
                                <?= ucfirst($reserva['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($reserva['notas'])): ?>
                            <small class="text-muted" title="<?= sanitize($reserva['notas']) ?>"><?= sanitize(mb_strimwidth($reserva['notas'], 0, 40, '...')) ?></small>
                            <?php else: ?>
                            <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="cambiar_estado" value="1">
                                <input type="hidden" name="reserva_id" value="<?= intval($reserva['id']) ?>">
                                <select name="nuevo_estado" class="form-select form-select-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                    <option value="">Cambiar...</option>
                                    <?php foreach (['pendiente', 'confirmada', 'cancelada', 'completada'] as $estado): ?>
                                    <?php if ($estado !== $reserva['estado']): ?>
                                    <option value="<?= $estado ?>"><?= ucfirst($estado) ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sincronizar color picker con texto
    const colorInput = document.querySelector('input[name="color_primario"]');
    const colorText = document.getElementById('colorText');
    if (colorInput && colorText) {
        colorInput.addEventListener('input', function() {
            colorText.value = this.value;
            document.getElementById('previewCard').style.background = this.value;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
