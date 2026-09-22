<?php
/**
 * Pagina publica de reservas de citas
 * No requiere autenticacion
 */

require_once __DIR__ . '/config/database.php';

$db = getDB();

// Obtener configuracion activa
$stmtConfig = $db->query("SELECT * FROM booking_config WHERE activo = 1 LIMIT 1");
$config = $stmtConfig->fetch(PDO::FETCH_ASSOC);

$mensaje = '';
$tipoMensaje = '';

// Procesar formulario de reserva
if ($config && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha = trim($_POST['fecha'] ?? '');
    $hora = trim($_POST['hora'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    $errores = [];

    // Validaciones basicas
    if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es valido.';
    if (empty($telefono)) $errores[] = 'El telefono es obligatorio.';
    if (empty($fecha)) $errores[] = 'La fecha es obligatoria.';
    if (empty($hora)) $errores[] = 'Debe seleccionar una hora.';

    // Validar que la fecha no sea pasada
    if (!empty($fecha) && $fecha < date('Y-m-d')) {
        $errores[] = 'La fecha no puede ser en el pasado.';
    }

    // Validar fecha maxima (dias_anticipacion)
    if (!empty($fecha)) {
        $maxDate = date('Y-m-d', strtotime('+' . intval($config['dias_anticipacion']) . ' days'));
        if ($fecha > $maxDate) {
            $errores[] = 'La fecha excede el periodo de anticipacion permitido.';
        }
    }

    // Validar dia de la semana
    if (!empty($fecha)) {
        $diaSemana = date('N', strtotime($fecha)); // 1=lunes, 7=domingo
        $diasPermitidos = explode(',', $config['dias_disponibles']);
        if (!in_array($diaSemana, $diasPermitidos)) {
            $errores[] = 'El dia seleccionado no esta disponible para citas.';
        }
    }

    // Validar hora dentro del horario
    if (!empty($hora)) {
        $horaSeleccionada = $hora . ':00';
        if ($horaSeleccionada < $config['horario_inicio'] || $horaSeleccionada >= $config['horario_fin']) {
            $errores[] = 'La hora seleccionada esta fuera del horario disponible.';
        }
    }

    // Verificar que no exista reserva en la misma fecha y hora
    if (!empty($fecha) && !empty($hora) && empty($errores)) {
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM booking_reservas WHERE booking_config_id = ? AND fecha = ? AND hora = ? AND estado != 'cancelada'");
        $stmtCheck->execute([$config['id'], $fecha, $hora . ':00']);
        if ($stmtCheck->fetchColumn() > 0) {
            $errores[] = 'Ya existe una reserva en esa fecha y hora. Por favor, elija otro horario.';
        }
    }

    if (!empty($errores)) {
        $mensaje = implode('<br>', $errores);
        $tipoMensaje = 'danger';
    } else {
        $stmtInsert = $db->prepare("INSERT INTO booking_reservas (booking_config_id, nombre, email, telefono, fecha, hora, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtInsert->execute([$config['id'], $nombre, $email, $telefono, $fecha, $hora . ':00', $notas]);
        $mensaje = 'Su cita ha sido reservada correctamente. Le contactaremos para confirmar.';
        $tipoMensaje = 'success';

        // Limpiar datos del formulario tras exito
        $nombre = $email = $telefono = $fecha = $hora = $notas = '';
    }
}

// Generar slots de tiempo si hay config activa
$slots = [];
$bookedSlots = [];
$selectedDate = $_POST['fecha'] ?? '';

if ($config) {
    $inicio = strtotime($config['horario_inicio']);
    $fin = strtotime($config['horario_fin']);
    $duracion = intval($config['duracion_minutos']) * 60;

    for ($t = $inicio; $t < $fin; $t += $duracion) {
        $slots[] = date('H:i', $t);
    }

    // Obtener slots ocupados para la fecha seleccionada
    if (!empty($selectedDate)) {
        $stmtBooked = $db->prepare("SELECT TIME_FORMAT(hora, '%H:%i') as hora_slot FROM booking_reservas WHERE booking_config_id = ? AND fecha = ? AND estado != 'cancelada'");
        $stmtBooked->execute([$config['id'], $selectedDate]);
        $bookedSlots = $stmtBooked->fetchAll(PDO::FETCH_COLUMN);
    }
}

$colorPrimario = $config['color_primario'] ?? '#10b981';
$titulo = htmlspecialchars($config['titulo'] ?? 'Reservar Cita');
$descripcion = htmlspecialchars($config['descripcion'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: <?= $colorPrimario ?>;
        }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; min-height: 100vh; }
        .booking-header {
            background: linear-gradient(135deg, var(--color-primary), color-mix(in srgb, var(--color-primary) 70%, #000));
            color: #fff;
            padding: 3rem 0;
            text-align: center;
        }
        .booking-header h1 { font-weight: 700; margin-bottom: 0.5rem; }
        .booking-header p { opacity: 0.9; font-size: 1.1rem; margin: 0; }
        .booking-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            border: none;
            margin-top: -2rem;
        }
        .time-slot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .time-slot:hover:not(.booked) {
            border-color: var(--color-primary);
            background: color-mix(in srgb, var(--color-primary) 10%, #fff);
        }
        .time-slot.selected {
            border-color: var(--color-primary);
            background: var(--color-primary);
            color: #fff;
        }
        .time-slot.booked {
            background: #f1f5f9;
            color: #94a3b8;
            border-color: #e2e8f0;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        .time-slot input[type="radio"] { display: none; }
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.2rem color-mix(in srgb, var(--color-primary) 25%, transparent);
        }
        .btn-booking {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: #fff;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-booking:hover {
            background: color-mix(in srgb, var(--color-primary) 85%, #000);
            border-color: color-mix(in srgb, var(--color-primary) 85%, #000);
            color: #fff;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--color-primary);
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }
        .section-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: #1e293b;
        }
        .footer-text {
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
            padding: 2rem 0;
        }
        #timeSlots { min-height: 50px; }
    </style>
</head>
<body>

<?php if (!$config): ?>
    <div class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center">
            <i class="bi bi-calendar-x" style="font-size: 4rem; color: #94a3b8;"></i>
            <h3 class="mt-3 text-muted">No hay citas disponibles</h3>
            <p class="text-muted">El sistema de reservas no esta activo en este momento.</p>
        </div>
    </div>
<?php else: ?>

    <div class="booking-header">
        <div class="container">
            <h1><i class="bi bi-calendar-check"></i> <?= $titulo ?></h1>
            <?php if (!empty($descripcion)): ?>
            <p><?= $descripcion ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="container" style="max-width: 720px;">
        <div class="booking-card p-4 p-md-5 mb-5">

            <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipoMensaje ?> d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-<?= $tipoMensaje === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2 fs-5"></i>
                <div><?= $mensaje ?></div>
            </div>
            <?php endif; ?>

            <?php if ($tipoMensaje !== 'success'): ?>
            <form method="POST" id="bookingForm">

                <!-- Paso 1: Fecha -->
                <div class="mb-4">
                    <div class="section-title">
                        <span class="step-number">1</span> Seleccione una fecha
                    </div>
                    <input type="date" name="fecha" id="fechaInput" class="form-control form-control-lg"
                           min="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d', strtotime('+' . intval($config['dias_anticipacion']) . ' days')) ?>"
                           value="<?= htmlspecialchars($selectedDate) ?>"
                           required>
                    <small class="text-muted mt-1 d-block">
                        <?php
                        $diasNombres = [1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 7 => 'Dom'];
                        $diasDisp = explode(',', $config['dias_disponibles']);
                        $diasTexto = array_map(function($d) use ($diasNombres) { return $diasNombres[intval($d)] ?? ''; }, $diasDisp);
                        ?>
                        Dias disponibles: <?= implode(', ', array_filter($diasTexto)) ?>
                    </small>
                </div>

                <!-- Paso 2: Hora -->
                <div class="mb-4">
                    <div class="section-title">
                        <span class="step-number">2</span> Seleccione una hora
                    </div>
                    <div id="timeSlots">
                        <?php if (empty($selectedDate)): ?>
                            <p class="text-muted"><i class="bi bi-info-circle"></i> Seleccione primero una fecha para ver los horarios disponibles.</p>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): ?>
                                <?php $isBooked = in_array($slot, $bookedSlots); ?>
                                <label class="time-slot <?= $isBooked ? 'booked' : '' ?> <?= (isset($hora) && $hora === $slot) ? 'selected' : '' ?>">
                                    <input type="radio" name="hora" value="<?= $slot ?>" <?= $isBooked ? 'disabled' : '' ?> <?= (isset($hora) && $hora === $slot) ? 'checked' : '' ?> required>
                                    <?= $slot ?>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($slots)): ?>
                                <p class="text-muted">No hay horarios disponibles.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Paso 3: Datos personales -->
                <div class="mb-4">
                    <div class="section-title">
                        <span class="step-number">3</span> Sus datos de contacto
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required maxlength="100"
                                   value="<?= htmlspecialchars($nombre ?? '') ?>" placeholder="Ej: Juan Garcia">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required maxlength="150"
                                   value="<?= htmlspecialchars($email ?? '') ?>" placeholder="juan@email.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Telefono <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono" class="form-control" required maxlength="20"
                                   value="<?= htmlspecialchars($telefono ?? '') ?>" placeholder="+34 600 000 000">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Notas (opcional)</label>
                            <textarea name="notas" class="form-control" rows="3" maxlength="500"
                                      placeholder="Informacion adicional sobre su consulta..."><?= htmlspecialchars($notas ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-booking btn-lg">
                        <i class="bi bi-calendar-check me-2"></i> Confirmar Reserva
                    </button>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </div>

    <div class="footer-text">
        Powered by Tinoprop
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const fechaInput = document.getElementById('fechaInput');
        const timeSlotsContainer = document.getElementById('timeSlots');
        const diasDisponibles = [<?= $config['dias_disponibles'] ?>];

        // Datos de slots pre-generados desde PHP
        const allSlots = <?= json_encode($slots) ?>;
        const configId = <?= intval($config['id']) ?>;

        // Cuando cambia la fecha, reenviar formulario para actualizar slots
        // (enfoque simple sin AJAX extra)
        fechaInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (!selectedDate) return;

            // Validar dia de la semana (JS: 0=dom, 1=lun ... 6=sab -> convertir a ISO: 1=lun ... 7=dom)
            const d = new Date(selectedDate + 'T00:00:00');
            let dayOfWeek = d.getDay();
            dayOfWeek = dayOfWeek === 0 ? 7 : dayOfWeek;

            if (!diasDisponibles.includes(dayOfWeek)) {
                timeSlotsContainer.innerHTML = '<p class="text-danger"><i class="bi bi-exclamation-circle"></i> Este dia no esta disponible para citas.</p>';
                return;
            }

            // Reenviar con la fecha para que PHP regenere los slots
            const form = document.getElementById('bookingForm');
            const tempInput = document.createElement('input');
            tempInput.type = 'hidden';
            tempInput.name = 'cambio_fecha';
            tempInput.value = '1';
            form.appendChild(tempInput);

            // Usar GET para no procesar como reserva
            // En su lugar, simplemente recargar con la fecha como parametro
            window.location.href = window.location.pathname + '?fecha=' + selectedDate;
        });

        // Manejar seleccion de slots
        document.querySelectorAll('.time-slot:not(.booked)').forEach(function(slot) {
            slot.addEventListener('click', function() {
                document.querySelectorAll('.time-slot').forEach(function(s) {
                    s.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });

        // Si hay fecha en GET, ponerla en el input
        const urlParams = new URLSearchParams(window.location.search);
        const fechaParam = urlParams.get('fecha');
        if (fechaParam && !fechaInput.value) {
            fechaInput.value = fechaParam;
        }
    });
    </script>

<?php endif; ?>

</body>
</html>
