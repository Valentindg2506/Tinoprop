<?php
$pageTitle      = 'Política de Cookies';
$pageLastUpdate = '16/04/2026';
require_once __DIR__ . '/_layout.php';
?>

<div class="row">
<div class="col-lg-9">

<div class="legal-card">

<div class="warn-box">
    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
    <strong>Acción requerida:</strong> Esta plataforma utiliza cookies. Es obligatorio implementar un banner de consentimiento de cookies antes de su activación. Ver §5 para los requisitos técnicos.
</div>

<h2>1. ¿Qué son las cookies?</h2>
<p>Las cookies son pequeños archivos de texto que un sitio web almacena en el dispositivo del usuario cuando lo visita. Permiten al sitio recordar información sobre la visita, como el idioma preferido y otras opciones, para hacer más fácil la próxima visita y que el sitio resulte más útil.</p>

<h2>2. Cookies utilizadas por Tinoprop</h2>

<h3>2.1 Cookies estrictamente necesarias (no requieren consentimiento)</h3>
<table class="table table-bordered table-sm">
    <thead><tr><th>Nombre</th><th>Tipo</th><th>Duración</th><th>Finalidad</th></tr></thead>
    <tbody>
        <tr>
            <td><code>PHPSESSID</code></td>
            <td>Sesión HTTP</td>
            <td>Sesión del navegador</td>
            <td>Gestión de la sesión de usuario autenticado en la plataforma CRM. Estrictamente necesaria para el funcionamiento del servicio.</td>
        </tr>
    </tbody>
</table>

<h3>2.2 Cookies funcionales / preferencias (requieren consentimiento)</h3>
<table class="table table-bordered table-sm">
    <thead><tr><th>Nombre</th><th>Tipo</th><th>Duración</th><th>Finalidad</th></tr></thead>
    <tbody>
        <tr>
            <td><code>ref_code</code></td>
            <td>Cookie propia</td>
            <td>30 días</td>
            <td>Almacena el código de referido/afiliado para atribución de conversiones en el programa de afiliación.</td>
        </tr>
        <tr>
            <td><code>funnel_visitor</code></td>
            <td>Cookie propia</td>
            <td>Sesión / variable</td>
            <td>Seguimiento del visitante a través de funnels y landing pages para medir conversiones.</td>
        </tr>
    </tbody>
</table>

<h3>2.3 Cookies de terceros potenciales</h3>
<table class="table table-bordered table-sm">
    <thead><tr><th>Proveedor</th><th>Finalidad</th><th>Política del tercero</th></tr></thead>
    <tbody>
        <tr>
            <td>Bootstrap CDN (jsDelivr)</td>
            <td>Carga de recursos CSS/JS. Puede registrar IP de acceso.</td>
            <td><a href="https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net" target="_blank">Ver política</a></td>
        </tr>
        <tr>
            <td>Meta (Facebook Pixel)</td>
            <td>Si se activan los anuncios/leads de Meta, Meta puede establecer cookies de seguimiento.</td>
            <td><a href="https://www.facebook.com/privacy/policy/" target="_blank">Ver política</a></td>
        </tr>
    </tbody>
</table>

<h2>3. Base legal para el uso de cookies</h2>
<ul>
    <li><strong>Cookies estrictamente necesarias:</strong> Art. 6.1.b RGPD (ejecución del contrato de servicio). No requieren consentimiento.</li>
    <li><strong>Cookies funcionales y de seguimiento (<code>ref_code</code>, <code>funnel_visitor</code>):</strong> Art. 6.1.a RGPD — <strong>consentimiento explícito previo</strong> del usuario. Estas cookies <strong>no deben activarse</strong> hasta obtener dicho consentimiento.</li>
</ul>

<h2>4. Cómo gestionar o desactivar las cookies</h2>
<p>El usuario puede configurar su navegador para bloquear o eliminar cookies. A continuación se indica cómo hacerlo en los navegadores más comunes:</p>
<ul>
    <li><strong>Chrome:</strong> Configuración → Privacidad y seguridad → Cookies y otros datos de sitios</li>
    <li><strong>Firefox:</strong> Opciones → Privacidad y seguridad → Cookies y datos del sitio</li>
    <li><strong>Safari:</strong> Preferencias → Privacidad → Gestionar datos del sitio</li>
    <li><strong>Edge:</strong> Configuración → Privacidad, búsqueda y servicios → Cookies</li>
</ul>
<p class="text-muted small">Nota: deshabilitar las cookies necesarias puede impedir el correcto funcionamiento de la plataforma CRM.</p>

<h2>5. Requisito legal: Banner de consentimiento</h2>
<div class="warn-box">
    <strong>OBLIGATORIO según la LSSI y RGPD:</strong> Antes de almacenar cookies no esenciales, debe mostrarse al usuario un banner de consentimiento que cumpla los siguientes requisitos:
    <ul class="mt-2 mb-0">
        <li>Información clara sobre las cookies usadas y su finalidad.</li>
        <li>Opciones para <strong>aceptar</strong>, <strong>rechazar</strong> o <strong>configurar</strong> por categorías.</li>
        <li>El rechazo debe ser igual de fácil que la aceptación (sin dark patterns).</li>
        <li>Registro del consentimiento otorgado (consent log) con timestamp y versión de política.</li>
        <li>Las cookies no esenciales NO deben cargarse antes del consentimiento.</li>
        <li>Posibilidad de revocar el consentimiento en cualquier momento.</li>
    </ul>
</div>
<p>Se recomienda implementar una solución como <strong>Cookiebot</strong>, <strong>Axeptio</strong>, <strong>Complianz</strong> o desarrollar un banner propio que cumpla los requisitos anteriores.</p>
<p>El archivo de implementación técnica sugerida se encuentra en <code>legal/docs/requisitos-tecnicos.md</code>.</p>

<h2>6. Cookies de sesión — Medidas de seguridad</h2>
<p>La cookie de sesión <code>PHPSESSID</code> actualmente se establece sin los atributos de seguridad recomendados. Se recomienda configurar en <code>php.ini</code> o en el código:</p>
<pre class="bg-light p-2 rounded small"><code>session.cookie_httponly = 1   // Previene acceso desde JavaScript (XSS)
session.cookie_secure   = 1   // Solo enviar por HTTPS
session.cookie_samesite = Lax // Protección CSRF básica</code></pre>
<p>Del mismo modo, las cookies propias <code>ref_code</code> y <code>funnel_visitor</code> deben crearse con los atributos <code>HttpOnly</code>, <code>Secure</code> y <code>SameSite=Lax</code>.</p>

<h2>7. Actualizaciones de la Política de Cookies</h2>
<p>Esta política puede actualizarse cuando se añadan nuevas cookies o cambie la normativa aplicable. Se notificará al usuario mediante el banner de consentimiento.</p>

</div>
</div><!-- col -->
<div class="col-lg-3 d-none d-lg-block">
    <div class="legal-card p-3 sticky-top" style="top:1rem">
        <p class="fw-bold text-success mb-2"><i class="bi bi-list-ul me-1"></i> Índice</p>
        <ol class="small ps-3" style="line-height:2">
            <li><a href="#" class="text-decoration-none text-secondary">¿Qué son las cookies?</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Cookies utilizadas</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Base legal</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Cómo desactivarlas</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Banner de consentimiento</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Seguridad de sesión</a></li>
        </ol>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
