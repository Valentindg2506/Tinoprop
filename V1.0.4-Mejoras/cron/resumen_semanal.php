<?php
/**
 * Resumen semanal por email — ejecutar cada lunes
 * Cron: 0 8 * * 1 php /var/www/html/CRM/app/cron/resumen_semanal.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';

$db = getDB();

// Usuarios activos con email que tienen notif_email_semanal activada (default ON)
$usuarios = $db->query("
    SELECT u.id, u.nombre, u.apellidos, u.email, u.rol
    FROM usuarios u
    WHERE u.activo = 1 AND u.email != ''
      AND (
          SELECT COALESCE(ua.valor, '1')
          FROM usuario_ajustes ua
          WHERE ua.usuario_id = u.id AND ua.clave = 'notif_email_semanal'
          LIMIT 1
      ) = '1'
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($usuarios)) {
    echo date('Y-m-d H:i:s') . " — Sin usuarios para enviar resumen.\n";
    exit;
}

$esAdmin = function(string $rol): bool { return $rol === 'admin'; };

$semanaLabel = 'Semana del ' . date('d/m/Y', strtotime('last monday')) . ' al ' . date('d/m/Y', strtotime('last monday +6 days'));
$lunes = date('Y-m-d', strtotime('last monday'));
$domingo = date('Y-m-d', strtotime('last monday +6 days'));

foreach ($usuarios as $u) {
    $uid  = (int)$u['id'];
    $admin = $esAdmin($u['rol']);
    $filtroAgenteSql = $admin ? '' : ' AND agente_id = ?';
    $filtroAgenteParams = $admin ? [] : [$uid];
    $filtroAgenteTareasSql = $admin ? '' : ' AND asignado_a = ?';
    $filtroAgenteTareasParams = $admin ? [] : [$uid];
    $filtroAgenteHistorialSql = $admin ? '' : ' AND usuario_id = ?';
    $filtroAgenteHistorialParams = $admin ? [] : [$uid];

    // ── Contactos de la semana ─────────────────────────────────────────────
        $sql = "SELECT tipo, COUNT(*) as total
                        FROM historial_prospectos
                        WHERE DATE(COALESCE(fecha_evento, created_at)) BETWEEN ? AND ?
                        $filtroAgenteHistorialSql
                        GROUP BY tipo";
        $params = array_merge([$lunes, $domingo], $filtroAgenteHistorialParams);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contactosSemana = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalContactos = array_sum($contactosSemana);

    // ── Tareas pendientes ──────────────────────────────────────────────────
        $sql = "SELECT titulo, prioridad, fecha_vencimiento
                        FROM tareas
                        WHERE estado IN ('pendiente','en_progreso')
                        $filtroAgenteTareasSql
                        ORDER BY FIELD(prioridad,'urgente','alta','media','baja'), fecha_vencimiento ASC
                        LIMIT 20";
        $stmt = $db->prepare($sql);
        $stmt->execute($filtroAgenteTareasParams);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Prospectos urgentes (próximo contacto vencido o sin contactar > 7 días) ──
    $sql = "SELECT nombre, etapa, temperatura, fecha_proximo_contacto,
                   DATEDIFF(CURDATE(), COALESCE(fecha_proximo_contacto, updated_at)) as dias
            FROM prospectos
            WHERE activo = 1 AND etapa NOT IN ('captado','descartado')
              AND (fecha_proximo_contacto <= CURDATE()
                   OR DATEDIFF(CURDATE(), updated_at) > 7)
            $filtroAgenteSql
            ORDER BY
                CASE WHEN fecha_proximo_contacto <= CURDATE() THEN 0 ELSE 1 END,
                dias DESC
            LIMIT 15";
    $stmt = $db->prepare($sql);
    $stmt->execute($filtroAgenteParams);
    $prospectos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Visitas de la semana ───────────────────────────────────────────────
        $sql = "SELECT v.fecha, v.hora, p.titulo as propiedad, p.referencia,
                                     CONCAT(c.nombre,' ',COALESCE(c.apellidos,'')) as cliente
                        FROM visitas v
                        LEFT JOIN propiedades p ON v.propiedad_id = p.id
                        LEFT JOIN clientes c ON v.cliente_id = c.id
                        WHERE v.fecha BETWEEN ? AND ?
                        $filtroAgenteSql
                        ORDER BY v.fecha, v.hora
                        LIMIT 20";
        $params = array_merge([$lunes, $domingo], $filtroAgenteParams);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Llamadas de la semana (historial tipo llamada/whatsapp) ────────────
    $llamadas = ($contactosSemana['llamada'] ?? 0) + ($contactosSemana['whatsapp'] ?? 0);

    // ── Construir email ────────────────────────────────────────────────────
    $nombreCompleto = htmlspecialchars(trim($u['nombre'] . ' ' . $u['apellidos']));

    $html  = "<h2 style='color:#1e293b; margin-bottom:4px;'>Resumen semanal</h2>";
    $html .= "<p style='color:#64748b; margin-top:0;'>$semanaLabel</p>";
    $html .= "<p>Hola <strong>$nombreCompleto</strong>, aquí tienes tu resumen de la semana:</p>";

    // Bloque KPIs rápidos
    $html .= "
    <table style='width:100%; border-collapse:collapse; margin:16px 0;'>
        <tr>
            " . kpiBox('Contactos', $totalContactos, '#3b82f6') . "
            " . kpiBox('Llamadas / WA', $llamadas, '#10b981') . "
            " . kpiBox('Visitas', count($visitas), '#8b5cf6') . "
            " . kpiBox('Prospectos urgentes', count($prospectos), '#ef4444') . "
        </tr>
    </table>";

    // Contactos por tipo
    if ($totalContactos > 0) {
        $tiposLabel = ['llamada'=>'Llamadas','whatsapp'=>'WhatsApp','email'=>'Emails','visita'=>'Visitas','nota'=>'Notas','otro'=>'Otros'];
        $html .= seccionTitulo('Contactos realizados esta semana');
        $html .= "<table style='width:100%;border-collapse:collapse;font-size:13px;'>";
        foreach ($contactosSemana as $tipo => $n) {
            $label = $tiposLabel[$tipo] ?? ucfirst($tipo);
            $html .= "<tr><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$label</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;'>$n</td></tr>";
        }
        $html .= "</table>";
    }

    // Visitas
    if (!empty($visitas)) {
        $html .= seccionTitulo('Visitas esta semana', '#8b5cf6');
        $html .= "<table style='width:100%;border-collapse:collapse;font-size:13px;'>";
        $html .= "<tr style='background:#f8fafc;'><th style='padding:6px 8px;text-align:left;'>Fecha</th><th style='padding:6px 8px;text-align:left;'>Propiedad</th><th style='padding:6px 8px;text-align:left;'>Cliente</th></tr>";
        foreach ($visitas as $v) {
            $fecha = date('d/m', strtotime($v['fecha'])) . ' ' . substr($v['hora'] ?? '', 0, 5);
            $prop  = htmlspecialchars($v['referencia'] . ($v['propiedad'] ? ' · ' . mb_strimwidth($v['propiedad'], 0, 30, '…') : ''));
            $cli   = htmlspecialchars(trim($v['cliente']) ?: '-');
            $html .= "<tr><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$fecha</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$prop</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$cli</td></tr>";
        }
        $html .= "</table>";
    }

    // Prospectos urgentes
    if (!empty($prospectos)) {
        $html .= seccionTitulo('Prospectos urgentes a contactar', '#ef4444');
        $etapasLabel = ['nuevo_lead'=>'Nuevo Lead','contactado'=>'1er Contacto','seguimiento'=>'Seguimiento','visita_programada'=>'Visita','en_negociacion'=>'Negociando'];
        $html .= "<table style='width:100%;border-collapse:collapse;font-size:13px;'>";
        $html .= "<tr style='background:#f8fafc;'><th style='padding:6px 8px;text-align:left;'>Nombre</th><th style='padding:6px 8px;text-align:left;'>Etapa</th><th style='padding:6px 8px;text-align:left;'>Días s/c</th></tr>";
        foreach ($prospectos as $pr) {
            $nombre = htmlspecialchars($pr['nombre']);
            $etapa  = htmlspecialchars($etapasLabel[$pr['etapa']] ?? $pr['etapa']);
            $dias   = max(0, (int)$pr['dias']);
            $color  = $dias > 15 ? '#ef4444' : ($dias > 7 ? '#f59e0b' : '#10b981');
            $html .= "<tr><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$nombre</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$etapa</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;font-weight:700;color:$color;'>$dias días</td></tr>";
        }
        $html .= "</table>";
    }

    // Tareas pendientes
    if (!empty($tareas)) {
        $html .= seccionTitulo('Tareas pendientes', '#f59e0b');
        $prioLabel = ['urgente'=>'🔴 Urgente','alta'=>'🟠 Alta','media'=>'🟡 Media','baja'=>'🟢 Baja'];
        $html .= "<table style='width:100%;border-collapse:collapse;font-size:13px;'>";
        $html .= "<tr style='background:#f8fafc;'><th style='padding:6px 8px;text-align:left;'>Tarea</th><th style='padding:6px 8px;text-align:left;'>Prioridad</th><th style='padding:6px 8px;text-align:left;'>Vence</th></tr>";
        foreach ($tareas as $t) {
            $titulo  = htmlspecialchars(mb_strimwidth($t['titulo'], 0, 50, '…'));
            $prio    = $prioLabel[$t['prioridad']] ?? $t['prioridad'];
            $vence   = $t['fecha_vencimiento'] ? date('d/m/Y', strtotime($t['fecha_vencimiento'])) : '-';
            $vencido = $t['fecha_vencimiento'] && $t['fecha_vencimiento'] < date('Y-m-d H:i:s');
            $estiloFila = $vencido ? "background:#fef2f2;" : '';
            $html .= "<tr style='$estiloFila'><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$titulo</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;'>$prio</td><td style='padding:5px 8px;border-bottom:1px solid #f1f5f9;" . ($vencido ? "color:#ef4444;font-weight:700;" : '') . "'>$vence</td></tr>";
        }
        $html .= "</table>";
    }

    if ($totalContactos === 0 && empty($visitas) && empty($prospectos) && empty($tareas)) {
        $html .= "<p style='color:#64748b; text-align:center; padding:20px 0;'>Sin actividad registrada esta semana.</p>";
    }

    $html .= "<div style='margin-top:24px; text-align:center;'>
        <a href='" . APP_URL . "' style='background:#1e40af; color:#fff; padding:12px 28px; text-decoration:none; border-radius:6px; font-weight:600;'>Abrir el CRM</a>
    </div>";

    $asunto = '📋 Resumen semanal — ' . $semanaLabel;
    $ok = enviarEmailCron($u['email'], $asunto, $html);
    echo date('Y-m-d H:i:s') . ' — ' . ($ok ? 'OK' : 'ERROR') . " → {$u['email']}\n";
}

echo date('Y-m-d H:i:s') . " — Resumen semanal completado. " . count($usuarios) . " usuario(s) procesado(s).\n";

// ── Helpers locales ────────────────────────────────────────────────────────

function kpiBox(string $label, $valor, string $color): string {
    return "<td style='text-align:center; padding:12px; background:{$color}15; border-radius:8px; margin:4px;'>
        <div style='font-size:1.8rem; font-weight:800; color:$color;'>$valor</div>
        <div style='font-size:0.7rem; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px;'>$label</div>
    </td>";
}

function seccionTitulo(string $titulo, string $color = '#1e40af'): string {
    return "<h4 style='margin:20px 0 8px 0; padding:6px 10px; background:{$color}15; color:$color; border-left:3px solid $color; border-radius:0 4px 4px 0; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.5px;'>$titulo</h4>";
}

/**
 * Versión de enviarEmail para uso en CLI (sin sesión de usuario activa).
 */
function enviarEmailCron(string $destinatario, string $asunto, string $cuerpo): bool {
    try {
        $cfg = getEmailTransportConfig(null);
        if ($cfg['method'] === 'smtp' && !empty($cfg['host'])) {
            return enviarEmailSMTP($destinatario, $asunto, $cuerpo, true, $cfg);
        }
        return enviarEmailMail($destinatario, $asunto, $cuerpo, true, $cfg);
    } catch (Exception $e) {
        echo "  Error email: " . $e->getMessage() . "\n";
        return false;
    }
}
