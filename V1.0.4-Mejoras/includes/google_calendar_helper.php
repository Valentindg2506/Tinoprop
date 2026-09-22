<?php
/**
 * Google Calendar Integration Helper
 * OAuth 2.0 + Calendar API v3 — sin SDK, sólo cURL
 */

if (!defined('APP_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

define('GCAL_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth');
define('GCAL_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GCAL_REVOKE_URL','https://oauth2.googleapis.com/revoke');
define('GCAL_API_BASE',  'https://www.googleapis.com/calendar/v3');
define('GCAL_SCOPE',     'https://www.googleapis.com/auth/calendar.events openid email');

// ─── Credenciales ───────────────────────────────────────

function gcalClientId(): string    { return (string)(getenv('GOOGLE_CLIENT_ID')     ?: ''); }
function gcalClientSecret(): string { return (string)(getenv('GOOGLE_CLIENT_SECRET') ?: ''); }
function gcalRedirectUri(): string  { return APP_URL . '/modules/ajustes/google_calendar_callback.php'; }
function gcalIsConfigured(): bool   { return gcalClientId() !== '' && gcalClientSecret() !== ''; }

// ─── OAuth ─────────────────────────────────────────────

function gcalGetAuthUrl(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $state = bin2hex(random_bytes(16));
    $_SESSION['gcal_oauth_state'] = $state;
    return GCAL_AUTH_URL . '?' . http_build_query([
        'client_id'     => gcalClientId(),
        'redirect_uri'  => gcalRedirectUri(),
        'response_type' => 'code',
        'scope'         => GCAL_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ]);
}

function gcalVerifyState(string $state): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $expected = $_SESSION['gcal_oauth_state'] ?? '';
    unset($_SESSION['gcal_oauth_state']);
    return $expected !== '' && hash_equals($expected, $state);
}

// ─── HTTP ───────────────────────────────────────────────

function gcalHttpPost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($body ?: '{}', true) ?? []];
}

function gcalHttpRequest(string $method, string $url, string $accessToken, ?array $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp ?: '{}', true) ?? []];
}

// ─── Token exchange / refresh ───────────────────────────

function gcalExchangeCode(string $code): ?array {
    $r = gcalHttpPost(GCAL_TOKEN_URL, [
        'code'          => $code,
        'client_id'     => gcalClientId(),
        'client_secret' => gcalClientSecret(),
        'redirect_uri'  => gcalRedirectUri(),
        'grant_type'    => 'authorization_code',
    ]);
    return ($r['code'] === 200 && !empty($r['body']['access_token'])) ? $r['body'] : null;
}

function gcalRefreshAccessToken(string $refreshToken): ?array {
    $r = gcalHttpPost(GCAL_TOKEN_URL, [
        'refresh_token' => $refreshToken,
        'client_id'     => gcalClientId(),
        'client_secret' => gcalClientSecret(),
        'grant_type'    => 'refresh_token',
    ]);
    return ($r['code'] === 200 && !empty($r['body']['access_token'])) ? $r['body'] : null;
}

function gcalRevokeToken(string $token): void {
    $ch = curl_init(GCAL_REVOKE_URL . '?token=' . urlencode($token));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch);
    curl_close($ch);
}

function gcalGetUserEmail(string $accessToken): string {
    $r = gcalHttpRequest('GET', 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json', $accessToken);
    return $r['body']['email'] ?? '';
}

// ─── DB: tokens ─────────────────────────────────────────

function gcalGetToken(PDO $db, int $userId): ?array {
    try {
        $stmt = $db->prepare("SELECT * FROM google_calendar_tokens WHERE usuario_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) { return null; }
}

function gcalSaveToken(PDO $db, int $userId, array $tokenData, string $googleEmail = '', string $calendarId = 'primary'): void {
    $db->prepare("
        INSERT INTO google_calendar_tokens
            (usuario_id, access_token, refresh_token, expires_at, google_email, google_calendar_id)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            access_token      = VALUES(access_token),
            refresh_token     = COALESCE(VALUES(refresh_token), refresh_token),
            expires_at        = VALUES(expires_at),
            google_email      = COALESCE(NULLIF(VALUES(google_email),''), google_email),
            updated_at        = NOW()
    ")->execute([
        $userId,
        $tokenData['access_token'],
        $tokenData['refresh_token'] ?? null,
        time() + intval($tokenData['expires_in'] ?? 3600),
        $googleEmail ?: null,
        $calendarId,
    ]);
}

function gcalDeleteToken(PDO $db, int $userId): void {
    try {
        $db->prepare("DELETE FROM google_calendar_tokens WHERE usuario_id = ?")->execute([$userId]);
        $db->prepare("DELETE FROM google_calendar_event_map WHERE usuario_id = ?")->execute([$userId]);
    } catch (Exception $e) {}
}

function gcalGetValidAccessToken(PDO $db, int $userId): ?string {
    $token = gcalGetToken($db, $userId);
    if (!$token) return null;

    // Válido con 60s de margen
    if (intval($token['expires_at']) > time() + 60) {
        return $token['access_token'];
    }

    // Refrescar
    if (empty($token['refresh_token'])) return null;
    $new = gcalRefreshAccessToken($token['refresh_token']);
    if (!$new) return null;

    gcalSaveToken($db, $userId, $new);
    return $new['access_token'];
}

// ─── DB: event map ──────────────────────────────────────

function gcalGetMappedEventId(PDO $db, int $userId, string $tipo, int $entidadId): ?string {
    try {
        $stmt = $db->prepare("SELECT google_event_id FROM google_calendar_event_map WHERE usuario_id = ? AND entidad_tipo = ? AND entidad_id = ?");
        $stmt->execute([$userId, $tipo, $entidadId]);
        return $stmt->fetchColumn() ?: null;
    } catch (Exception $e) { return null; }
}

function gcalSaveEventMap(PDO $db, int $userId, string $tipo, int $entidadId, string $googleEventId, string $calendarId): void {
    try {
        $db->prepare("
            INSERT INTO google_calendar_event_map (usuario_id, entidad_tipo, entidad_id, google_event_id, google_calendar_id)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE google_event_id = VALUES(google_event_id), updated_at = NOW()
        ")->execute([$userId, $tipo, $entidadId, $googleEventId, $calendarId]);
    } catch (Exception $e) {}
}

function gcalDeleteEventMap(PDO $db, int $userId, string $tipo, int $entidadId): void {
    try {
        $db->prepare("DELETE FROM google_calendar_event_map WHERE usuario_id = ? AND entidad_tipo = ? AND entidad_id = ?")->execute([$userId, $tipo, $entidadId]);
    } catch (Exception $e) {}
}

// ─── Builders de eventos ────────────────────────────────

function gcalBuildEventFromTarea(array $t): array {
    $fv    = $t['fecha_vencimiento'] ?? date('Y-m-d') . ' 09:00:00';
    $start = date('Y-m-d\TH:i:s', strtotime($fv));
    $end   = date('Y-m-d\TH:i:s', strtotime($fv) + 3600);

    $desc = '';
    if (!empty($t['descripcion']))     $desc .= $t['descripcion'] . "\n\n";
    if (!empty($t['cliente_nombre']))  $desc .= 'Cliente: ' . $t['cliente_nombre'] . "\n";
    if (!empty($t['propiedad_titulo'])) $desc .= 'Propiedad: ' . $t['propiedad_titulo'] . "\n";
    $desc .= APP_URL . '/modules/tareas/form.php?id=' . $t['id'];

    $colorId = match($t['prioridad'] ?? '') { 'urgente' => '11', 'alta' => '5', default => '3' };

    return [
        'summary'     => '📋 Tarea: ' . ($t['titulo'] ?? 'Sin título'),
        'description' => trim($desc),
        'start'       => ['dateTime' => $start, 'timeZone' => APP_TIMEZONE],
        'end'         => ['dateTime' => $end,   'timeZone' => APP_TIMEZONE],
        'colorId'     => $colorId,
    ];
}

function gcalBuildEventFromVisita(array $v): array {
    $fecha = $v['fecha'] ?? date('Y-m-d');
    $hora  = !empty($v['hora']) ? substr($v['hora'], 0, 5) : '09:00';
    $start = $fecha . 'T' . $hora . ':00';
    $end   = date('Y-m-d\TH:i', strtotime($start) + 3600);

    $nombre = trim(($v['cliente_nombre'] ?? '') . ' ' . ($v['cliente_apellidos'] ?? '')) ?: 'Sin cliente';
    $desc   = '';
    if (!empty($v['notas']))            $desc .= $v['notas'] . "\n\n";
    if (!empty($v['propiedad_titulo'])) $desc .= 'Propiedad: ' . $v['propiedad_titulo'] . "\n";
    if (!empty($v['propiedad_ref']))    $desc .= 'Ref: ' . $v['propiedad_ref'] . "\n";
    $desc .= APP_URL . '/modules/visitas/form.php?id=' . $v['id'];

    return [
        'summary'     => '🏠 Visita: ' . $nombre . (!empty($v['propiedad_ref']) ? ' — ' . $v['propiedad_ref'] : ''),
        'description' => trim($desc),
        'start'       => ['dateTime' => $start, 'timeZone' => APP_TIMEZONE],
        'end'         => ['dateTime' => $end,   'timeZone' => APP_TIMEZONE],
        'colorId'     => '2',
    ];
}

function gcalBuildEventFromProspecto(array $p): array {
    $fecha = $p['fecha_proximo_contacto'];
    $start = $fecha . 'T09:00:00';
    $end   = $fecha . 'T09:30:00';

    $desc  = '';
    if (!empty($p['telefono'])) $desc .= 'Tel: ' . $p['telefono'] . "\n";
    if (!empty($p['notas']))    $desc .= $p['notas'] . "\n\n";
    $desc .= APP_URL . '/modules/prospectos/ver.php?id=' . $p['id'];

    return [
        'summary'     => '📞 Contactar: ' . $p['nombre'],
        'description' => trim($desc),
        'start'       => ['dateTime' => $start, 'timeZone' => APP_TIMEZONE],
        'end'         => ['dateTime' => $end,   'timeZone' => APP_TIMEZONE],
        'colorId'     => '5',
    ];
}

function gcalBuildEventFromCalendario(array $c): array {
    $start   = $c['fecha_inicio'];
    $end     = !empty($c['fecha_fin']) ? $c['fecha_fin'] : date('Y-m-d H:i:s', strtotime($start) + 3600);
    $allDay  = !empty($c['todo_dia']);

    $desc  = '';
    if (!empty($c['descripcion'])) $desc .= $c['descripcion'] . "\n\n";
    $desc .= APP_URL . '/modules/calendario/form.php?id=' . $c['id'];

    $event = [
        'summary'     => $c['titulo'] ?? 'Evento',
        'description' => trim($desc),
    ];

    if ($allDay) {
        $event['start'] = ['date' => substr($start, 0, 10)];
        $event['end']   = ['date' => substr($end,   0, 10)];
    } else {
        $event['start'] = ['dateTime' => str_replace(' ', 'T', substr($start, 0, 16)) . ':00', 'timeZone' => APP_TIMEZONE];
        $event['end']   = ['dateTime' => str_replace(' ', 'T', substr($end,   0, 16)) . ':00', 'timeZone' => APP_TIMEZONE];
    }

    return $event;
}

// ─── Upsert helper ──────────────────────────────────────

function gcalUpsertEvent(PDO $db, int $userId, string $accessToken, string $calendarId, string $tipo, int $entidadId, array $eventData): string {
    $existingId = gcalGetMappedEventId($db, $userId, $tipo, $entidadId);

    if ($existingId) {
        $url = GCAL_API_BASE . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($existingId);
        $r   = gcalHttpRequest('PUT', $url, $accessToken, $eventData);
        if ($r['code'] === 200) return 'actualizados';
        if ($r['code'] !== 404) return 'errores';
        // El evento fue borrado de Google, lo recreamos
        gcalDeleteEventMap($db, $userId, $tipo, $entidadId);
    }

    $url = GCAL_API_BASE . '/calendars/' . urlencode($calendarId) . '/events';
    $r   = gcalHttpRequest('POST', $url, $accessToken, $eventData);
    if (in_array($r['code'], [200, 201]) && !empty($r['body']['id'])) {
        gcalSaveEventMap($db, $userId, $tipo, $entidadId, $r['body']['id'], $calendarId);
        return 'creados';
    }
    return 'errores';
}

// ─── Sincronización principal ───────────────────────────

function gcalSyncForUser(PDO $db, int $userId): array {
    $accessToken = gcalGetValidAccessToken($db, $userId);
    if (!$accessToken) return ['error' => 'Token inválido o expirado. Reconecta tu cuenta de Google.'];

    $token      = gcalGetToken($db, $userId);
    $calendarId = $token['google_calendar_id'] ?? 'primary';

    $desde = date('Y-m-d');
    $hasta = date('Y-m-d', strtotime('+60 days'));
    $stats = ['creados' => 0, 'actualizados' => 0, 'errores' => 0];

    // 1. Tareas pendientes
    try {
        $stmt = $db->prepare("
            SELECT t.id, t.titulo, t.descripcion, t.prioridad, t.fecha_vencimiento,
                   c.nombre as cliente_nombre, p.titulo as propiedad_titulo
            FROM tareas t
            LEFT JOIN clientes c  ON t.cliente_id    = c.id
            LEFT JOIN propiedades p ON t.propiedad_id = p.id
            WHERE (t.asignado_a = ? OR t.creado_por = ?)
              AND t.estado IN ('pendiente','en_progreso')
              AND DATE(t.fecha_vencimiento) BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $userId, $desde, $hasta]);
        foreach ($stmt->fetchAll() as $row) {
            $res = gcalUpsertEvent($db, $userId, $accessToken, $calendarId, 'tarea', $row['id'], gcalBuildEventFromTarea($row));
            $stats[$res]++;
        }
    } catch (Exception $e) { $stats['errores']++; }

    // 2. Visitas programadas
    try {
        $stmt = $db->prepare("
            SELECT v.id, v.fecha, v.hora, v.notas,
                   c.nombre as cliente_nombre, c.apellidos as cliente_apellidos,
                   p.titulo as propiedad_titulo, p.referencia as propiedad_ref
            FROM visitas v
            LEFT JOIN clientes c    ON v.cliente_id    = c.id
            LEFT JOIN propiedades p ON v.propiedad_id  = p.id
            WHERE v.agente_id = ? AND v.fecha BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $desde, $hasta]);
        foreach ($stmt->fetchAll() as $row) {
            $res = gcalUpsertEvent($db, $userId, $accessToken, $calendarId, 'visita', $row['id'], gcalBuildEventFromVisita($row));
            $stats[$res]++;
        }
    } catch (Exception $e) { $stats['errores']++; }

    // 3. Próximo contacto de prospectos
    try {
        $stmt = $db->prepare("
            SELECT id, nombre, telefono, notas, fecha_proximo_contacto
            FROM prospectos
            WHERE agente_id = ? AND activo = 1
              AND fecha_proximo_contacto BETWEEN ? AND ?
              AND etapa NOT IN ('captado','descartado')
        ");
        $stmt->execute([$userId, $desde, $hasta]);
        foreach ($stmt->fetchAll() as $row) {
            $res = gcalUpsertEvent($db, $userId, $accessToken, $calendarId, 'prospecto', $row['id'], gcalBuildEventFromProspecto($row));
            $stats[$res]++;
        }
    } catch (Exception $e) { $stats['errores']++; }

    // 4. Eventos de calendario
    try {
        $stmt = $db->prepare("
            SELECT id, titulo, descripcion, tipo, todo_dia, fecha_inicio, fecha_fin
            FROM calendario_eventos
            WHERE usuario_id = ?
              AND DATE(fecha_inicio) BETWEEN ? AND ?
        ");
        $stmt->execute([$userId, $desde, $hasta]);
        foreach ($stmt->fetchAll() as $row) {
            $res = gcalUpsertEvent($db, $userId, $accessToken, $calendarId, 'calendario', $row['id'], gcalBuildEventFromCalendario($row));
            $stats[$res]++;
        }
    } catch (Exception $e) { $stats['errores']++; }

    return $stats;
}
