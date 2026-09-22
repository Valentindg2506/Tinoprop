<?php
$pageTitle      = 'Política de Privacidad';
$pageLastUpdate = '16/04/2026';
require_once __DIR__ . '/_layout.php';
?>

<div class="row">
<div class="col-lg-9">

<div class="legal-card">
<div class="info-box">
    <i class="bi bi-info-circle-fill text-success me-2"></i>
    <strong>Responsable del Tratamiento:</strong> <span class="placeholder">[NOMBRE EMPRESA]</span>,
    con CIF <span class="placeholder">[CIF]</span>, domicilio en <span class="placeholder">[DIRECCIÓN FISCAL COMPLETA]</span>.
    Contacto privacidad: <span class="placeholder">[EMAIL PRIVACIDAD, ej: privacidad@tinoprop.es]</span>
</div>

<h2>1. Objeto y ámbito de aplicación</h2>
<p>La presente Política de Privacidad regula el tratamiento de los datos personales que <span class="placeholder">[NOMBRE EMPRESA]</span> (en adelante, <strong>"Tinoprop"</strong> o el <strong>"Prestador"</strong>) realiza como consecuencia de la prestación del servicio de software CRM inmobiliario en modalidad SaaS.</p>
<p>Tinoprop actúa en una doble condición:</p>
<ul>
    <li><strong>Responsable del Tratamiento</strong> respecto a los datos de los propios usuarios/clientes del servicio SaaS (inmobiliarias y agentes que contratan la plataforma).</li>
    <li><strong>Encargado del Tratamiento</strong> respecto a los datos personales de terceros (prospectos, propietarios, contactos) que los clientes incorporan en la plataforma. En este caso, cada cliente actúa como Responsable.</li>
</ul>

<h2>2. Datos que tratamos como Responsable</h2>
<h3>2.1 Datos de clientes SaaS (inmobiliarias / agentes)</h3>
<table class="table table-bordered table-sm mb-3">
    <thead><tr><th>Dato</th><th>Finalidad</th><th>Base legal</th><th>Plazo</th></tr></thead>
    <tbody>
        <tr><td>Nombre y apellidos, email, teléfono</td><td>Gestión del contrato, soporte, comunicaciones de servicio</td><td>Art. 6.1.b RGPD — ejecución de contrato</td><td>Duración del contrato + 5 años</td></tr>
        <tr><td>Contraseña (hash bcrypt)</td><td>Autenticación y seguridad de la cuenta</td><td>Art. 6.1.b RGPD — ejecución de contrato</td><td>Duración del contrato</td></tr>
        <tr><td>Dirección IP y user-agent</td><td>Seguridad, detección de fraude, logs de acceso</td><td>Art. 6.1.f RGPD — interés legítimo</td><td>12 meses</td></tr>
        <tr><td>Historial de conversaciones con el asistente IA</td><td>Prestación del servicio IA del CRM</td><td>Art. 6.1.b RGPD — ejecución de contrato</td><td>Duración del contrato + 1 año</td></tr>
        <tr><td>Datos de facturación (vía Stripe)</td><td>Gestión de pagos y cumplimiento fiscal</td><td>Art. 6.1.b RGPD y art. 6.1.c — obligación legal</td><td>10 años (obligación fiscal)</td></tr>
    </tbody>
</table>

<h3>2.2 Datos de afiliados</h3>
<table class="table table-bordered table-sm mb-3">
    <thead><tr><th>Dato</th><th>Finalidad</th><th>Base legal</th><th>Plazo</th></tr></thead>
    <tbody>
        <tr><td>Nombre, email, código de afiliado</td><td>Gestión del programa de afiliación y pago de comisiones</td><td>Art. 6.1.b RGPD — ejecución de contrato</td><td>Duración del programa + 5 años</td></tr>
        <tr><td>Cookie de seguimiento (<code>ref_code</code>)</td><td>Atribución de conversiones a afiliados</td><td>Art. 6.1.a RGPD — consentimiento</td><td>30 días desde la primera visita</td></tr>
    </tbody>
</table>

<h2>3. Datos que tratamos como Encargado del Tratamiento</h2>
<p>Los datos personales introducidos por los clientes (prospectos, propietarios, contactos, mensajes de WhatsApp, emails, etc.) son tratados por Tinoprop únicamente para prestar el servicio contratado. El <strong>cliente es el Responsable del Tratamiento</strong> y es quien debe informar a sus propios contactos y disponer de la base legal adecuada.</p>
<p>Tinoprop garantiza:</p>
<ul>
    <li>Tratar los datos únicamente conforme a las instrucciones documentadas del Responsable.</li>
    <li>Adoptar las medidas de seguridad técnicas y organizativas descritas en la cláusula 8.</li>
    <li>No ceder los datos a terceros sin autorización expresa, salvo los subencargados indicados en la cláusula 5.</li>
    <li>Asistir al Responsable en el ejercicio de derechos de los interesados.</li>
    <li>Notificar las brechas de seguridad sin dilación indebida.</li>
    <li>Suprimir o devolver los datos al finalizar el contrato.</li>
</ul>
<p>El Contrato de Encargado de Tratamiento (DPA) completo está disponible para cada cliente en <code>legal/docs/contrato-encargado.md</code>.</p>

<h2>4. Transferencias internacionales de datos</h2>
<div class="warn-box">
    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
    <strong>Aviso importante:</strong> Tinoprop utiliza servicios de terceros cuyos servidores están ubicados fuera del Espacio Económico Europeo (EEE), principalmente en EE. UU.
</div>
<table class="table table-bordered table-sm">
    <thead><tr><th>Servicio</th><th>Proveedor</th><th>País</th><th>Datos transferidos</th><th>Garantía</th></tr></thead>
    <tbody>
        <tr><td>Asistente IA (Claude)</td><td>Anthropic, PBC</td><td>EE. UU.</td><td>Mensajes del chat IA y datos de CRM consultados durante la sesión</td><td>Cláusulas Contractuales Tipo (CCT) / DPA de Anthropic</td></tr>
        <tr><td>Asistente IA (Groq)</td><td>Groq, Inc.</td><td>EE. UU.</td><td>Mensajes del chat IA</td><td>CCT / DPA de Groq</td></tr>
        <tr><td>WhatsApp Business</td><td>Meta Platforms, Inc.</td><td>EE. UU.</td><td>Mensajes de WhatsApp y números de teléfono</td><td>Cláusulas Contractuales Tipo</td></tr>
        <tr><td>Pagos</td><td>Stripe, Inc.</td><td>EE. UU.</td><td>Datos de facturación y pago</td><td>CCT / Privacy Shield sucesor (DPF)</td></tr>
    </tbody>
</table>
<p>El interesado puede solicitar copia de las garantías aplicables dirigiéndose a <span class="placeholder">[EMAIL PRIVACIDAD]</span>.</p>

<h2>5. Destinatarios y subencargados</h2>
<p>Tinoprop no vende ni cede datos personales a terceros con fines propios. Los datos pueden ser comunicados a:</p>
<ul>
    <li><strong>Proveedores tecnológicos</strong> (hosting en España, los servicios IA y WhatsApp indicados en §4) únicamente para prestar el servicio.</li>
    <li><strong>Administraciones públicas</strong> cuando exista obligación legal.</li>
    <li><strong>Asesores jurídicos o contables</strong> sujetos a deber de confidencialidad.</li>
</ul>

<h2>6. Derechos de los interesados</h2>
<p>Cualquier persona puede ejercer ante Tinoprop los siguientes derechos:</p>
<ul>
    <li><strong>Acceso:</strong> obtener confirmación de si se tratan sus datos y una copia de los mismos.</li>
    <li><strong>Rectificación:</strong> corregir datos inexactos o incompletos.</li>
    <li><strong>Supresión ("derecho al olvido"):</strong> solicitar la eliminación de sus datos cuando, entre otros supuestos, ya no sean necesarios.</li>
    <li><strong>Limitación:</strong> solicitar que se restrinja el tratamiento en determinadas circunstancias.</li>
    <li><strong>Portabilidad:</strong> recibir sus datos en formato estructurado y de uso común.</li>
    <li><strong>Oposición:</strong> oponerse al tratamiento basado en interés legítimo.</li>
    <li><strong>No ser objeto de decisiones automatizadas</strong> con efectos significativos.</li>
</ul>
<p>Para ejercer sus derechos, envíe un email a <span class="placeholder">[EMAIL PRIVACIDAD]</span> con copia de su documento de identidad y descripción de la solicitud. Responderemos en el plazo máximo de <strong>30 días</strong>.</p>
<p>Si considera que el tratamiento infringe la normativa, puede presentar una reclamación ante la <strong>Agencia Española de Protección de Datos</strong> (AEPD): <a href="https://www.aepd.es" target="_blank">www.aepd.es</a>.</p>

<h2>7. Delegado de Protección de Datos (DPD)</h2>
<p>Tinoprop no ha designado formalmente un DPD. Dada la naturaleza del tratamiento como plataforma SaaS que procesa datos para múltiples clientes, <strong>se recomienda valorar la obligatoriedad de nombramiento</strong> conforme al art. 37 RGPD. Las consultas en materia de privacidad pueden dirigirse a <span class="placeholder">[EMAIL PRIVACIDAD]</span>.</p>

<h2>8. Medidas de seguridad</h2>
<p>Tinoprop implementa, entre otras, las siguientes medidas técnicas y organizativas:</p>
<ul>
    <li>Contraseñas almacenadas con hash <strong>bcrypt</strong> (factor de coste ≥ 10).</li>
    <li>Protección CSRF en todos los formularios con tokens de sesión.</li>
    <li>Bloqueo de cuenta tras 5 intentos fallidos de inicio de sesión.</li>
    <li>Regeneración de ID de sesión tras autenticación.</li>
    <li>Servidor ubicado en España (zona UE).</li>
    <li>Comunicaciones cifradas mediante HTTPS/TLS.</li>
    <li>Control de acceso basado en roles (admin / agente).</li>
    <li>Registro de actividad (<em>audit log</em>) de acciones sensibles.</li>
</ul>

<h2>9. Conservación de datos</h2>
<p>Los datos se conservan únicamente durante el tiempo necesario para la finalidad para la que fueron recogidos. Una vez cumplida dicha finalidad, se bloquearán y suprimirán conforme a los plazos indicados en §2, salvo obligación legal de conservación.</p>

<h2>10. Modificaciones</h2>
<p>Tinoprop podrá actualizar esta Política. Los cambios significativos se notificarán por email o mediante aviso en la plataforma con al menos 30 días de antelación.</p>
</div>

</div><!-- col -->
<div class="col-lg-3 d-none d-lg-block">
    <div class="legal-card p-3 sticky-top" style="top:1rem">
        <p class="fw-bold text-success mb-2"><i class="bi bi-list-ul me-1"></i> Índice</p>
        <ol class="small ps-3" style="line-height:2">
            <li><a href="#" class="text-decoration-none text-secondary">Objeto</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Datos como Responsable</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Datos como Encargado</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Transferencias internacionales</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Destinatarios</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Derechos ARCO+</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">DPD</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Seguridad</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Conservación</a></li>
            <li><a href="#" class="text-decoration-none text-secondary">Modificaciones</a></li>
        </ol>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
