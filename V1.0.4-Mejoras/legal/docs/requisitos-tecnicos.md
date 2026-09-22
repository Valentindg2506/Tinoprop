# Requisitos Técnicos de Cumplimiento RGPD — Tinoprop
## Implementaciones pendientes con código de referencia

**Fecha:** Abril 2026  
**Prioridad:** Alta — Implementar antes del siguiente ciclo de facturación

---

## RT-01: Securizar cookies de sesión ⚠️ URGENTE

**Archivo:** `config/database.php` o `includes/auth.php` (antes de cualquier `session_start()`)

**Código a añadir:**

```php
// Seguridad de sesión — añadir al inicio de auth.php o database.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,           // Cookie de sesión (muere al cerrar navegador)
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,        // No accesible desde JavaScript
        'samesite' => 'Lax',      // Protección CSRF
    ]);
    session_start();
}
```

**Verificar en `php.ini`:**
```ini
session.cookie_httponly = 1
session.cookie_secure   = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1
session.gc_maxlifetime  = 7200
```

---

## RT-02: Securizar cookies de afiliado/funnel ⚠️ URGENTE

**Búsqueda:** localizar en el código los `setcookie('ref_code', ...)` y `setcookie('funnel_visitor', ...)`

**Reemplazar toda llamada `setcookie()` con esta función helper:**

```php
/**
 * Establece una cookie con atributos de seguridad RGPD.
 * Usar en reemplazo de setcookie() en todo el proyecto.
 */
function setSecureCookie(string $name, string $value, int $ttl = 0, bool $httponly = true): void {
    setcookie($name, $value, [
        'expires'  => $ttl > 0 ? time() + $ttl : 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => $httponly,
        'samesite' => 'Lax',
    ]);
}

// Uso:
// Antes: setcookie('ref_code', $refCode, time() + 30 * 86400, '/');
// Después: setSecureCookie('ref_code', $refCode, 30 * 86400);
```

---

## RT-03: Consentimiento en formulario público ❌ CRÍTICO

**Archivo:** `formulario.php` (formulario de captación público)

**1. Migración de BD:**

```sql
-- Ejecutar una vez
ALTER TABLE formulario_envios 
    ADD COLUMN IF NOT EXISTS consentimiento_rgpd TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS consentimiento_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS consentimiento_ip VARCHAR(45) NULL;
```

**2. En el HTML del formulario, añadir antes del botón submit:**

Ver `clausulas-consentimiento.md` §1 para el texto completo.

**3. En el PHP handler:**

```php
// Validar consentimiento
if (empty($_POST['consentimiento_rgpd']) || $_POST['consentimiento_rgpd'] !== '1') {
    http_response_code(422);
    echo json_encode(['error' => 'El consentimiento RGPD es obligatorio.']);
    exit;
}

// En el INSERT, añadir:
// consentimiento_rgpd = 1
// consentimiento_at = NOW()
// consentimiento_ip = $_SERVER['REMOTE_ADDR']
```

---

## RT-04: Banner de cookies ❌ PENDIENTE

**Archivos a modificar:**
- El layout principal (include que carga el `</body>`) — añadir el banner
- Los archivos que establecen `ref_code` y `funnel_visitor` — condicionar al consentimiento
- Crear `api/cookie_consent.php` para registrar el consentimiento

**Archivo `api/cookie_consent.php`:**

```php
<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['necessary'])) {
    http_response_code(400);
    exit;
}

$db = getDB();
$db->prepare("
    INSERT INTO cookie_consents (ip, user_agent, decision)
    VALUES (?, ?, ?)
")->execute([
    $_SERVER['REMOTE_ADDR'] ?? '',
    $_SERVER['HTTP_USER_AGENT'] ?? '',
    json_encode([
        'necessary' => true,
        'analytics' => (bool)($data['analytics'] ?? false),
        'timestamp' => $data['timestamp'] ?? date('c'),
        'version'   => $data['version'] ?? '1.0',
    ]),
]);

echo json_encode(['ok' => true]);
```

**Crear tabla:**

```sql
CREATE TABLE IF NOT EXISTS cookie_consents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500),
    decision JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## RT-05: Exportación de datos (Derecho de portabilidad) ❌ PENDIENTE

**Archivo a crear:** `modules/ajustes/exportar_mis_datos.php` (para usuarios) y panel admin para exportar datos de un cliente.

**Ejemplo — exportar datos de un prospecto:**

```php
<?php
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin');

$prospectoId = (int)($_GET['id'] ?? 0);
$db = getDB();

// Recopilar todos los datos
$prospecto = $db->prepare("SELECT * FROM prospectos WHERE id = ?")->execute([$prospectoId]);
$mensajes  = $db->prepare("SELECT * FROM whatsapp_mensajes WHERE cliente_id = ?")->execute([$prospectoId]);

// Generar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="datos_prospecto_' . $prospectoId . '.csv"');
// ... volcar datos
```

---

## RT-06: Derecho al olvido (supresión) ❌ PENDIENTE

**Archivo a crear:** `modules/ajustes/derecho_olvido.php`

Ver pseudocódigo completo en `clausulas-consentimiento.md` §7.

Requiere:
- Panel admin para buscar y anonimizar prospectos/clientes.
- Log de solicitudes de supresión atendidas.
- Proceso para los mensajes de WhatsApp vinculados.
- Notificación automática al solicitante.

---

## RT-07: Validación de firma en webhook Meta ⚠️ RECOMENDADO

**Archivo:** `api/whatsapp_webhook.php`  
**Ver detalle en:** `medidas-seguridad.md` §2.6

Requiere añadir la variable de entorno `META_APP_SECRET` en el `.env`.

---

## RT-08: Purga automática de logs ⚠️ RECOMENDADO

**Archivo a crear:** `cron/purgar_logs.php`

```php
<?php
/**
 * Cron: purgar registros de actividad con más de 12 meses
 * Ejecutar mensualmente: 0 3 1 * * php /var/www/html/CRM/cron/purgar_logs.php
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$limit = date('Y-m-d H:i:s', strtotime('-12 months'));

$stmt = $db->prepare("DELETE FROM actividad_log WHERE created_at < ?");
$stmt->execute([$limit]);
echo "Actividad log: " . $stmt->rowCount() . " registros eliminados.\n";

$stmt = $db->prepare("DELETE FROM ia_acciones_log WHERE created_at < ?");
$stmt->execute([$limit]);
echo "IA acciones log: " . $stmt->rowCount() . " registros eliminados.\n";

$stmt = $db->prepare("DELETE FROM cookie_consents WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 YEAR)");
$stmt->execute();
echo "Cookie consents: " . $stmt->rowCount() . " registros eliminados.\n";
```

**Añadir al crontab del servidor:**
```bash
# Purgar logs RGPD — 1er día de cada mes a las 3:00
0 3 1 * * php /var/www/html/CRM/cron/purgar_logs.php >> /var/log/crm_rgpd_purge.log 2>&1
```

---

## RT-09: Headers de seguridad HTTP ⚠️ RECOMENDADO

**Archivo:** `.htaccess` (raíz del proyecto) o configuración de Nginx.

Ver configuración completa en `medidas-seguridad.md` §2.3.

---

## Orden de implementación sugerido

```
Semana 1:
  [x] RT-01: Securizar PHPSESSID         (30 min)
  [x] RT-02: Securizar ref_code/funnel   (1 hora)
  [x] RT-03: Consentimiento formulario   (3 horas)
  [x] RT-09: Headers HTTP                (1 hora)

Semana 2:
  [ ] RT-04: Banner de cookies           (1 día)
  [ ] RT-07: Firma webhook Meta          (2 horas)

Mes 1:
  [ ] RT-05: Exportación de datos        (1-2 días)
  [ ] RT-06: Derecho al olvido           (1-2 días)
  [ ] RT-08: Purga automática de logs    (2 horas)
```
