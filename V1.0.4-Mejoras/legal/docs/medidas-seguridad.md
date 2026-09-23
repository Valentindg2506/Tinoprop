# Medidas de Seguridad Técnicas y Organizativas — Tinoprop
## Conforme al Art. 32 RGPD

**Fecha:** Abril 2026

---

## 1. Medidas actualmente implementadas ✅

### 1.1 Autenticación y control de acceso
- **Hash de contraseñas:** bcrypt con factor de coste. Verificar que el factor es ≥ 10 en `includes/auth.php`.
- **Bloqueo de cuenta:** tras 5 intentos fallidos consecutivos, la cuenta se bloquea.
- **Regeneración de sesión:** `session_regenerate_id(true)` al autenticar → previene session fixation.
- **Control de acceso basado en roles (RBAC):** roles `admin` y `agente` con permisos diferenciados.
- **Validación IP de sesión:** control contra cambio de IP durante la sesión activa.
- **`requireLogin()`:** todas las páginas del módulo verifican autenticación.

### 1.2 Protección contra ataques web
- **CSRF:** tokens de sesión en formularios POST (`csrf_token`).
- **Prepared statements (PDO):** uso generalizado de consultas parametrizadas → previene SQL Injection.
- **`htmlspecialchars()`:** sanitización de salida en vistas para prevenir XSS.
- **Validación de entrada:** validación básica de tipo y formato en formularios.

### 1.3 Comunicaciones
- **HTTPS:** las comunicaciones con la API de Anthropic, Groq, Meta y Stripe se realizan por HTTPS.
- **Servidor España:** datos alojados en territorio UE/España.

### 1.4 Trazabilidad
- **Audit log:** tabla `actividad_log` registra: `usuario_id`, `modulo`, `accion`, `entidad_tipo`, `entidad_id`, `ip`, `user_agent`, `created_at`.
- **Log de acciones IA:** tabla `ia_acciones_log` registra tool calls del asistente.

---

## 2. Medidas pendientes de implementar ❌

### 2.1 URGENTE — Seguridad de cookies

**Problema:** Las cookies `PHPSESSID`, `ref_code` y `funnel_visitor` se establecen sin atributos de seguridad.

**Solución para `PHPSESSID`** — añadir en `config/database.php` o `includes/auth.php` antes de `session_start()`:

```php
// Configuración segura de sesión
ini_set('session.cookie_httponly', 1);  // Previene acceso JS (XSS)
ini_set('session.cookie_secure', 1);    // Solo enviar por HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Protección CSRF
ini_set('session.use_strict_mode', 1);  // IDs de sesión regenerados
ini_set('session.gc_maxlifetime', 7200); // 2h de inactividad
```

**Solución para cookies propias** — donde se establezcan `ref_code` y `funnel_visitor`:

```php
// Antes (inseguro):
setcookie('ref_code', $value, time() + 30 * 86400, '/');

// Después (seguro):
setcookie('ref_code', $value, [
    'expires'  => time() + 30 * 86400,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
```

### 2.2 Autenticación de doble factor (2FA)

Para cuentas admin especialmente, implementar TOTP (Google Authenticator / Authy):

```php
// Librerías PHP recomendadas:
// - spomky-labs/otphp (TOTP estándar)
// - robthree/twofactorauth
```

Flujo recomendado:
1. Login correcto → si tiene 2FA activado → solicitar código TOTP
2. Panel de configuración para activar/desactivar 2FA por usuario
3. Códigos de recuperación de un solo uso

### 2.3 Headers de seguridad HTTP

Añadir en la configuración del servidor (Apache `.htaccess` o Nginx):

```apache
# .htaccess
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self'"
```

### 2.4 Purga automática de logs

Implementar proceso periódico (cron) para eliminar registros de `actividad_log` con más de 12 meses:

```sql
-- Ejecutar mensualmente via cron
DELETE FROM actividad_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 12 MONTH);
DELETE FROM ia_acciones_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 12 MONTH);
```

### 2.5 Rate limiting en endpoints críticos

El webhook de WhatsApp y la API de IA no tienen rate limiting. Riesgo de abuso o DoS.

```php
// Implementar en api/whatsapp_webhook.php y modules/ia/api.php
// Opción A: tabla rate_limits en BD
// Opción B: usar Redis/APCu si disponible
// Opción C: configurar en Nginx/Apache (limit_req_zone)
```

### 2.6 Validación de firma en webhook Meta

El webhook POST de Meta debería verificar la firma `X-Hub-Signature-256` para autenticar que el mensaje viene de Meta:

```php
// Añadir en api/whatsapp_webhook.php
$appSecret = getenv('META_APP_SECRET') ?: '';
if ($appSecret) {
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected  = 'sha256=' . hash_hmac('sha256', $raw, $appSecret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(403);
        exit('Invalid signature');
    }
}
```

### 2.7 Cifrado de campos sensibles en BD

Para campos de alta sensibilidad (tokens de API, access tokens de WhatsApp), usar cifrado simétrico a nivel de columna:

```php
// Función de cifrado/descifrado con AES-256-GCM
function encryptField(string $value, string $key): string {
    $iv = random_bytes(12);
    $tag = '';
    $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $encrypted);
}
```

Campos prioritarios para cifrar:
- `whatsapp_config.access_token`
- `ia_config.api_key`
- `email_config.smtp_password` (si existe)

---

## 3. Medidas organizativas recomendadas

### 3.1 Control de acceso mínimo necesario
- Principio de mínimo privilegio: los agentes solo deben ver prospectos/clientes asignados a ellos.
- Revisión periódica (semestral) de los permisos de cada usuario.
- Baja inmediata de usuarios que dejen la empresa.

### 3.2 Formación en protección de datos
- Formación básica RGPD para todos los empleados que accedan al CRM.
- Protocolo específico para el manejo de datos de prospectos.
- Formación en phishing e ingeniería social.

### 3.3 Gestión de incidentes
Protocolo de respuesta a brechas de seguridad:

1. **Detección:** agente de seguridad o usuario detecta el incidente.
2. **Contención (0-4h):** aislar sistemas afectados, revocar accesos comprometidos.
3. **Evaluación (4-24h):** determinar alcance, datos afectados, origen.
4. **Notificación (≤72h):** si hay riesgo para los derechos de los interesados:
   - Notificar a la AEPD: https://sedeagpd.gob.es
   - Notificar a los clientes SaaS afectados.
5. **Notificación a interesados:** si el riesgo es alto, notificar directamente a los afectados.
6. **Remediación:** parchear vulnerabilidad, restablecer sistemas.
7. **Post-mortem:** documentar el incidente y las medidas adoptadas.

### 3.4 Política de contraseñas
Requisitos mínimos para contraseñas de usuarios:
- Mínimo 12 caracteres.
- Al menos 1 mayúscula, 1 minúscula, 1 número.
- No reutilizar las últimas 5 contraseñas.
- Caducidad recomendada: 12 meses.

### 3.5 Gestión de accesos a APIs externas
- Rotar las API keys de Anthropic, Groq y Meta periódicamente (cada 6 meses o al cambio de personal).
- Almacenar las keys en variables de entorno (`.env`), nunca en el código fuente.
- Auditar permisos de las apps de Meta regularmente.

### 3.6 Desarrollo seguro
- Revisión de código (code review) antes de desplegar cambios.
- No desplegar código sin testing mínimo.
- Mantener dependencias actualizadas (actualizaciones de seguridad).
- No usar versiones de PHP EOL (End of Life).

---

## 4. Plan de implementación priorizado

| Prioridad | Medida | Esfuerzo | Impacto |
|---|---|---|---|
| 🔴 Inmediata | Securizar cookies (§2.1) | Bajo (1-2h) | Alto |
| 🔴 Inmediata | Formulario público con RGPD | Bajo (2-3h) | Crítico |
| 🔴 Semana 1 | Headers de seguridad HTTP (§2.3) | Bajo (1h) | Alto |
| 🔴 Semana 1 | Validación firma webhook Meta (§2.6) | Bajo (1h) | Medio |
| 🟡 Semana 2 | Banner de cookies | Medio (1 día) | Alto |
| 🟡 Semana 2 | Cifrado de tokens en BD (§2.7) | Medio (4-8h) | Alto |
| 🟡 Mes 1 | Purga automática de logs (§2.4) | Bajo (2h) | Medio |
| 🟡 Mes 1 | DPAs con proveedores | Medio (legal) | Alto |
| 🟢 Mes 2-3 | 2FA para admins (§2.2) | Alto (3-5 días) | Alto |
| 🟢 Mes 2-3 | Rate limiting APIs (§2.5) | Medio (1-2 días) | Medio |
| 🟢 Mes 3-6 | Evaluación de obligatoriedad DPD | Legal | Alto |
