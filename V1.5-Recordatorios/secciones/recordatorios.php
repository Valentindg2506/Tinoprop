<?php
require_once __DIR__ . '/../inc/bootstrap.php';

$mes_actual = isset($_GET['mes']) ? (int) $_GET['mes'] : date('n');
$ano_actual = isset($_GET['ano']) ? (int) $_GET['ano'] : date('Y');

$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$recordatorios_mes = recordatorios_por_mes($pdo, $mes_actual, $ano_actual);

$recordatorios_por_dia = [];
foreach ($recordatorios_mes as $r) {
    $dia = date('d', strtotime($r['fecha_recordatorio']));
    if (!isset($recordatorios_por_dia[$dia])) {
        $recordatorios_por_dia[$dia] = 0;
    }
    $recordatorios_por_dia[$dia]++;
}

$recordatorios_hoy = recordatorios_por_fecha($pdo, $fecha_seleccionada);

$primer_dia_mes = date('w', strtotime("$ano_actual-$mes_actual-01"));
$dias_en_mes = (int) date('t', strtotime("$ano_actual-$mes_actual-01"));

$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
          7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

$tipos_recordatorio = ['Llamada', 'Visita', 'Reunión', 'Nota importante', 'Seguimiento', 'Otro'];
?>

<div class="seccion_recordatorios">
    <h1>📅 Recordatorios</h1>

    <div class="contenedor_recordatorios">
        <!-- CALENDARIO -->
        <aside class="panel_calendario">
            <div class="header_calendario">
                <a href="?seccion=recordatorios&mes=<?php echo $mes_actual - 1 < 1 ? 12 : $mes_actual - 1; ?>&ano=<?php echo $mes_actual - 1 < 1 ? $ano_actual - 1 : $ano_actual; ?>" class="btn_mes">←</a>
                <h3><?php echo $meses[$mes_actual] . ' ' . $ano_actual; ?></h3>
                <a href="?seccion=recordatorios&mes=<?php echo $mes_actual + 1 > 12 ? 1 : $mes_actual + 1; ?>&ano=<?php echo $mes_actual + 1 > 12 ? $ano_actual + 1 : $ano_actual; ?>" class="btn_mes">→</a>
            </div>

            <table class="tabla_calendario">
                <thead>
                    <tr>
                        <th>Do</th>
                        <th>Lu</th>
                        <th>Ma</th>
                        <th>Mi</th>
                        <th>Ju</th>
                        <th>Vi</th>
                        <th>Sa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dia_semana = ($primer_dia_mes === 0) ? 6 : $primer_dia_mes - 1;
                    $contador = 0;
                    
                    for ($i = 0; $i < 6; $i++) {
                        echo '<tr>';
                        for ($j = 0; $j < 7; $j++) {
                            if ($i === 0 && $j < $dia_semana) {
                                echo '<td class="vacia"></td>';
                            } elseif ($contador < $dias_en_mes) {
                                $contador++;
                                $fecha_dia = sprintf("%04d-%02d-%02d", $ano_actual, $mes_actual, $contador);
                                $hoy = date('Y-m-d');
                                $es_hoy = $fecha_dia === $hoy;
                                $es_seleccionada = $fecha_dia === $fecha_seleccionada;
                                $tiene_recordatorios = isset($recordatorios_por_dia[$contador]) && $recordatorios_por_dia[$contador] > 0;
                                
                                $clases = 'dia';
                                if ($es_hoy) $clases .= ' es_hoy';
                                if ($es_seleccionada) $clases .= ' seleccionada';
                                if ($tiene_recordatorios) $clases .= ' con_recordatorios';
                                
                                echo '<td class="' . $clases . '" data-fecha="' . $fecha_dia . '">';
                                echo '<div class="numero_dia">' . $contador . '</div>';
                                if ($tiene_recordatorios) {
                                    echo '<div class="badge_recordatorios">' . $recordatorios_por_dia[$contador] . '</div>';
                                }
                                echo '</td>';
                            } else {
                                echo '<td class="vacia"></td>';
                            }
                        }
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>

            <div class="info_seleccion">
                <p>Seleccionado: <strong><?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></strong></p>
            </div>
        </aside>

        <!-- PANEL DE RECORDATORIOS -->
        <main class="panel_recordatorios">
            <div class="header_panel">
                <h2>Recordatorios para <?php echo date('d/m/Y', strtotime($fecha_seleccionada)); ?></h2>
                <button class="btn btn_primario" id="btn_nuevo_recordatorio">+ Nuevo</button>
            </div>

            <!-- FORMULARIO NUEVO/EDITAR RECORDATORIO -->
            <div id="formulario_recordatorio" class="formulario_recordatorio oculto">
                <form id="form_recordatorio" class="form">
                    <input type="hidden" id="recordatorio_id" name="recordatorio_id" value="">
                    <input type="hidden" id="recordatorio_fecha" name="recordatorio_fecha" value="">

                    <div class="grupo_form">
                        <label for="tipo">Tipo de recordatorio:</label>
                        <select id="tipo" name="tipo" required>
                            <option value="">-- Selecciona un tipo --</option>
                            <?php foreach ($tipos_recordatorio as $tipo): ?>
                                <option value="<?php echo e($tipo); ?>"><?php echo e($tipo); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fila_form">
                        <div class="grupo_form col-50">
                            <label for="hora">Hora (opcional):</label>
                            <input type="time" id="hora" name="hora">
                        </div>
                        <div class="grupo_form col-50">
                            <label for="prospecto_id">Cliente/Prospecto:</label>
                            <input type="number" id="prospecto_id" name="prospecto_id" placeholder="ID opcional">
                        </div>
                    </div>

                    <div class="grupo_form">
                        <label for="descripcion">Descripción:</label>
                        <textarea id="descripcion" name="descripcion" required placeholder="¿Qué necesitas recordar?"></textarea>
                    </div>

                    <div class="grupo_form">
                        <label for="estado">Estado:</label>
                        <select id="estado" name="estado">
                            <option value="pendiente">Pendiente</option>
                            <option value="completado">Completado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>

                    <div class="botones_form">
                        <button type="submit" class="btn btn_primario">Guardar</button>
                        <button type="button" class="btn btn_secundario" id="btn_cancelar_form">Cancelar</button>
                    </div>
                </form>
            </div>

            <!-- LISTA DE RECORDATORIOS -->
            <div class="lista_recordatorios">
                <?php if (empty($recordatorios_hoy)): ?>
                    <p class="sin_recordatorios">No hay recordatorios para esta fecha</p>
                <?php else: ?>
                    <?php foreach ($recordatorios_hoy as $rec): ?>
                        <div class="tarjeta_recordatorio estado_<?php echo e($rec['estado']); ?>">
                            <div class="header_tarjeta">
                                <div class="tipo_y_hora">
                                    <span class="tipo_badge"><?php echo e($rec['tipo']); ?></span>
                                    <?php if ($rec['hora_recordatorio']): ?>
                                        <time class="hora"><?php echo date('H:i', strtotime($rec['hora_recordatorio'])); ?></time>
                                    <?php endif; ?>
                                </div>
                                <div class="estado_badge"><?php echo ucfirst(e($rec['estado'])); ?></div>
                            </div>

                            <div class="cuerpo_tarjeta">
                                <p><?php echo e($rec['descripcion']); ?></p>
                                <?php if ($rec['prospecto_id']): ?>
                                    <small class="prospecto_ref">Prospecto ID: <?php echo (int) $rec['prospecto_id']; ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="acciones_tarjeta">
                                <button class="btn btn_chico btn_editar" data-id="<?php echo (int) $rec['id']; ?>">✏️ Editar</button>
                                <button class="btn btn_chico btn_eliminar" data-id="<?php echo (int) $rec['id']; ?>">🗑️ Eliminar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendario = document.querySelector('.tabla_calendario');
    const formulario = document.getElementById('formulario_recordatorio');
    const btnNuevo = document.getElementById('btn_nuevo_recordatorio');
    const btnCancelar = document.getElementById('btn_cancelar_form');
    const formRecordatorio = document.getElementById('form_recordatorio');
    const infoSeleccion = document.querySelector('.info_seleccion');

    let fechaSeleccionada = '<?php echo $fecha_seleccionada; ?>';

    // Navegar a día en calendario
    document.querySelectorAll('.tabla_calendario .dia').forEach(td => {
        td.addEventListener('click', function () {
            const fecha = this.dataset.fecha;
            fechaSeleccionada = fecha;
            
            // Actualizar URL sin recargar
            const url = new URL(window.location);
            url.searchParams.set('fecha', fecha);
            window.history.pushState({}, '', url);

            // Actualizar visual
            document.querySelectorAll('.tabla_calendario .dia').forEach(d => d.classList.remove('seleccionada'));
            this.classList.add('seleccionada');

            // Actualizar info y recargar lista
            infoSeleccion.querySelector('strong').textContent = new Date(fecha).toLocaleDateString('es-ES');
            location.reload();
        });
    });

    // Abrir formulario nuevo
    btnNuevo.addEventListener('click', function () {
        document.getElementById('recordatorio_id').value = '';
        document.getElementById('recordatorio_fecha').value = fechaSeleccionada;
        document.getElementById('tipo').value = '';
        document.getElementById('hora').value = '';
        document.getElementById('prospecto_id').value = '';
        document.getElementById('descripcion').value = '';
        document.getElementById('estado').value = 'pendiente';
        formulario.classList.remove('oculto');
    });

    // Cerrar formulario
    btnCancelar.addEventListener('click', function () {
        formulario.classList.add('oculto');
    });

    // Enviar formulario
    formRecordatorio.addEventListener('submit', async function (e) {
        e.preventDefault();

        const id = document.getElementById('recordatorio_id').value;
        const tipo = document.getElementById('tipo').value;
        const descripcion = document.getElementById('descripcion').value;
        const fecha = document.getElementById('recordatorio_fecha').value;
        const hora = document.getElementById('hora').value || null;
        const prospecto_id = document.getElementById('prospecto_id').value || null;
        const estado = document.getElementById('estado').value;

        try {
            const response = await fetch('api/recordatorios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: id ? 'actualizar' : 'crear',
                    id: id || undefined,
                    tipo,
                    descripcion,
                    fecha,
                    hora,
                    prospecto_id,
                    estado
                })
            });

            const result = await response.json();
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + (result.message || 'Error desconocido'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al guardar recordatorio');
        }
    });

    // Editar recordatorio
    document.querySelectorAll('.btn_editar').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.id;
            try {
                const response = await fetch('api/recordatorios.php?action=obtener&id=' + id);
                const result = await response.json();
                if (result.success) {
                    const rec = result.data;
                    document.getElementById('recordatorio_id').value = rec.id;
                    document.getElementById('recordatorio_fecha').value = rec.fecha_recordatorio;
                    document.getElementById('tipo').value = rec.tipo;
                    document.getElementById('hora').value = rec.hora_recordatorio || '';
                    document.getElementById('prospecto_id').value = rec.prospecto_id || '';
                    document.getElementById('descripcion').value = rec.descripcion;
                    document.getElementById('estado').value = rec.estado;
                    formulario.classList.remove('oculto');
                    formulario.scrollIntoView({ behavior: 'smooth' });
                }
            } catch (error) {
                console.error('Error:', error);
            }
        });
    });

    // Eliminar recordatorio
    document.querySelectorAll('.btn_eliminar').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('¿Estás seguro de que quieres eliminar este recordatorio?')) return;

            const id = this.dataset.id;
            try {
                const response = await fetch('api/recordatorios.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'eliminar', id })
                });

                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al eliminar recordatorio');
            }
        });
    });
});
</script>
