# Cláusulas de Consentimiento e Información — Tinoprop
## Textos listos para implementar en la aplicación

**Fecha:** Abril 2026  
**Conforme a:** Art. 13 RGPD, Art. 11 LOPDGDD, LSSI Art. 21

---

> **Instrucciones de uso:** Copiar los bloques de texto siguientes en los puntos indicados de la aplicación. Los marcadores `[EMPRESA]`, `[EMAIL]`, etc., deben reemplazarse por los datos reales de la empresa.

---

## 1. Formulario público de captación (`formulario.php`)

### Texto informativo (añadir antes del botón de envío):

```html
<div class="mt-3 p-3 bg-light border rounded small text-muted">
    <strong>Información sobre protección de datos:</strong><br>
    En cumplimiento del RGPD (UE) 2016/679 y la LOPDGDD 3/2018, le informamos de que los datos 
    personales que nos facilita serán tratados por <strong>[NOMBRE EMPRESA]</strong> 
    (CIF: [CIF]), con domicilio en [DIRECCIÓN], con la finalidad de gestionar su solicitud 
    y contactarle sobre el inmueble de su interés. La base legal es su consentimiento. 
    Los datos no se cederán a terceros salvo obligación legal. Puede ejercer sus derechos de 
    acceso, rectificación, supresión y demás dirigiéndose a <a href="mailto:[EMAIL PRIVACIDAD]">[EMAIL PRIVACIDAD]</a>. 
    Para más información consulte nuestra 
    <a href="/legal/privacidad.php" target="_blank">Política de Privacidad</a>.
</div>

<div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" name="consentimiento_rgpd" 
           id="consentimientoRgpd" required value="1">
    <label class="form-check-label" for="consentimientoRgpd">
        He leído y acepto la 
        <a href="/legal/privacidad.php" target="_blank">Política de Privacidad</a> 
        y consiento el tratamiento de mis datos para gestionar mi solicitud. *
    </label>
</div>
```

### Código PHP para registrar el consentimiento (en el handler del formulario):

```php
// Verificar consentimiento RGPD obligatorio
if (empty($_POST['consentimiento_rgpd'])) {
    // Error: consentimiento no dado
    $errors[] = 'Debe aceptar la Política de Privacidad para enviar el formulario.';
}

// Al guardar en BD, registrar:
$consentimientoRgpd = !empty($_POST['consentimiento_rgpd']) ? 1 : 0;
$consentimientoAt   = date('Y-m-d H:i:s');
// Incluir en INSERT: consentimiento_rgpd = ?, consentimiento_at = ?
```

### Migración de BD necesaria:

```sql
ALTER TABLE formulario_envios 
    ADD COLUMN consentimiento_rgpd TINYINT(1) NOT NULL DEFAULT 0 
        COMMENT 'Consentimiento RGPD explícito',
    ADD COLUMN consentimiento_at DATETIME NULL 
        COMMENT 'Timestamp del consentimiento RGPD';

-- Para prospectos creados automáticamente desde formulario:
ALTER TABLE prospectos 
    ADD COLUMN consentimiento_rgpd TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN consentimiento_at DATETIME NULL,
    ADD COLUMN consentimiento_ip VARCHAR(45) NULL;
```

---

## 2. Página de login / registro de usuarios

### Texto en el formulario de alta de nuevo agente/usuario:

```html
<p class="text-muted small mt-2">
    Al crear tu cuenta aceptas nuestros 
    <a href="/legal/terminos.php" target="_blank">Términos y Condiciones</a> y 
    nuestra <a href="/legal/privacidad.php" target="_blank">Política de Privacidad</a>. 
    Tus datos serán tratados por [NOMBRE EMPRESA] para gestionar tu acceso al CRM 
    conforme a nuestra política de privacidad.
</p>
```

---

## 3. Formulario de contacto / soporte

```html
<p class="text-muted small">
    Los datos facilitados serán tratados por <strong>[NOMBRE EMPRESA]</strong> para 
    responder a su consulta, con base en su consentimiento (Art. 6.1.a RGPD). 
    Puede ejercer sus derechos escribiendo a 
    <a href="mailto:[EMAIL PRIVACIDAD]">[EMAIL PRIVACIDAD]</a>. 
    <a href="/legal/privacidad.php" target="_blank">Más información</a>.
</p>
```

---

## 4. Comunicaciones comerciales por email

### Pie de email obligatorio (para todos los emails salientes):

```
────────────────────────────────────────────
Este mensaje ha sido enviado por [NOMBRE EMPRESA], CIF [CIF].
Ha recibido este mensaje porque es cliente o ha solicitado información previamente.

Para dejar de recibir comunicaciones comerciales: [ENLACE DE BAJA]
Para ejercer sus derechos RGPD: [EMAIL PRIVACIDAD]
Política de Privacidad: [URL]/legal/privacidad.php

[NOMBRE EMPRESA] · [DIRECCIÓN] · [TELÉFONO]
```

### Double opt-in (recomendado para nuevos suscriptores):

```php
// 1. Guardar email con estado 'pendiente_confirmacion'
// 2. Enviar email con enlace de confirmación único
// 3. Al confirmar, actualizar a 'confirmado' y registrar timestamp
// 4. Solo enviar emails comerciales a 'confirmado'

// Tabla sugerida:
CREATE TABLE email_suscriptores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(200) NOT NULL UNIQUE,
    estado ENUM('pendiente','confirmado','baja') DEFAULT 'pendiente',
    token_confirmacion VARCHAR(64) NULL,
    suscrito_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    confirmado_at DATETIME NULL,
    baja_at DATETIME NULL
);
```

---

## 5. Banner de consentimiento de cookies

### HTML/JS para implementar (solución propia — sin librerías externas):

```html
<!-- Añadir antes del </body> en el layout principal -->
<div id="cookie-banner" class="position-fixed bottom-0 start-0 end-0 bg-dark text-white p-3 shadow-lg" 
     style="z-index:9999; display:none;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <p class="mb-1 small">
                    Utilizamos cookies propias necesarias para el funcionamiento del servicio, 
                    y cookies opcionales para el seguimiento de afiliados y análisis de conversiones. 
                    <a href="/legal/cookies.php" class="text-info" target="_blank">Más información</a>.
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                <button id="btn-rechazar-cookies" class="btn btn-outline-light btn-sm me-2">
                    Solo necesarias
                </button>
                <button id="btn-aceptar-cookies" class="btn btn-success btn-sm">
                    Aceptar todas
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var cookieConsent = getCookieConsent();
    
    function getCookieConsent() {
        var match = document.cookie.match(/cookie_consent=([^;]+)/);
        return match ? JSON.parse(decodeURIComponent(match[1])) : null;
    }
    
    function setCookieConsent(analytics) {
        var consent = {
            necessary: true,
            analytics: analytics,
            timestamp: new Date().toISOString(),
            version: '1.0'
        };
        document.cookie = 'cookie_consent=' + encodeURIComponent(JSON.stringify(consent)) 
            + '; max-age=' + (365 * 86400) + '; path=/; SameSite=Lax'
            + (location.protocol === 'https:' ? '; Secure' : '');
        
        // Registrar en el servidor
        fetch('/api/cookie_consent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(consent)
        });
    }
    
    // Mostrar banner si no hay consentimiento registrado
    if (!cookieConsent) {
        document.getElementById('cookie-banner').style.display = 'block';
        // NO establecer cookies no esenciales hasta que el usuario acepte
    } else if (cookieConsent.analytics) {
        // Cargar cookies de analytics/afiliados
        activateAnalyticsCookies();
    }
    
    document.getElementById('btn-aceptar-cookies').addEventListener('click', function() {
        setCookieConsent(true);
        document.getElementById('cookie-banner').style.display = 'none';
        activateAnalyticsCookies();
    });
    
    document.getElementById('btn-rechazar-cookies').addEventListener('click', function() {
        setCookieConsent(false);
        document.getElementById('cookie-banner').style.display = 'none';
    });
    
    function activateAnalyticsCookies() {
        // Aquí establecer ref_code y funnel_visitor si existen en la URL
        // (mover la lógica actual de establecimiento de estas cookies a aquí)
    }
})();
</script>
```

### Tabla para registro de consentimientos (consent log):

```sql
CREATE TABLE IF NOT EXISTS cookie_consents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500),
    decision JSON NOT NULL COMMENT '{"necessary":true,"analytics":bool,"timestamp":"...","version":"..."}',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. Cláusula en contratos con clientes SaaS (añadir a los T&C)

```
PROTECCIÓN DE DATOS — El Cliente, en su condición de Responsable del Tratamiento 
conforme al RGPD (UE) 2016/679, declara:

(a) Disponer de base legal adecuada para introducir y tratar datos personales de 
terceros en la Plataforma.

(b) Haber informado a dichos terceros sobre el tratamiento de sus datos personales, 
incluyendo la cesión al prestador como encargado del tratamiento.

(c) Conocer y aceptar las transferencias internacionales de datos derivadas del uso 
de los servicios de IA (Anthropic, Groq) y WhatsApp (Meta) integrados en la Plataforma, 
que implican la transferencia de datos a EE. UU. bajo las garantías indicadas en el 
Contrato de Encargado de Tratamiento.

(d) Incluir en sus propias políticas de privacidad la mención a Tinoprop como 
encargado del tratamiento.
```

---

## 7. Derecho al olvido — Procedimiento interno

Cuando un interesado solicite la supresión de sus datos:

```
1. Recibir solicitud por email: [EMAIL PRIVACIDAD]
2. Verificar identidad del solicitante (DNI)
3. Identificar todos los registros asociados:
   - clientes / prospectos (por email o teléfono)
   - whatsapp_mensajes (por teléfono)
   - actividad_log (por usuario_id)
   - ia_conversaciones / ia_mensajes (por usuario_id)
   - formulario_envios (por email)
4. Ejecutar supresión (o seudonimización si hay obligación de conservación)
5. Confirmar por escrito al solicitante en ≤30 días
```

### Script PHP de apoyo (herramienta admin):

```php
// Pseudoanonimización de un prospecto bajo derecho al olvido:
function anonymizeProspecto(PDO $db, int $prospectoId): void {
    $db->prepare("UPDATE prospectos SET 
        nombre = 'Anonimizado', 
        apellido = 'RGPD', 
        email = NULL, 
        telefono = NULL, 
        notas = '[Datos suprimidos por solicitud RGPD - ' . date('Y-m-d') . ']',
        updated_at = NOW()
        WHERE id = ?")->execute([$prospectoId]);
    
    $db->prepare("DELETE FROM whatsapp_mensajes WHERE 
        telefono = (SELECT telefono FROM prospectos WHERE id = ?)")
        ->execute([$prospectoId]);
}
```
