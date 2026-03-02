<?php
/**
 * ============================================================================
 * MARCO LEGAL Y NORMATIVO - CRM TINOPROP
 * ============================================================================
 *
 * ¿Qué es este archivo?
 * ---------------------
 * Contiene toda la documentación legal obligatoria para que la aplicación
 * cumpla con la legislación española y europea. Es la página que
 * cualquier usuario puede consultar para conocer sus derechos y
 * obligaciones al usar TinoProp.
 *
 * Pestañas (tabs):
 * 1. AVISO LEGAL: información sobre la titularidad de la aplicación
 *    (obligatorio por la Ley 34/2002 LSSI-CE).
 *
 * 2. TÉRMINOS Y CONDICIONES: reglas de uso del servicio, roles,
 *    responsabilidades de cada parte, propiedad de los datos, etc.
 *
 * 3. POLÍTICA DE PRIVACIDAD: cómo se tratan los datos personales
 *    (obligatorio por el RGPD y la LOPDGDD). Explica qué datos se
 *    recogen, para qué se usan, cuánto tiempo se conservan y
 *    qué derechos tienen los usuarios (acceso, rectificación,
 *    supresión, portabilidad, etc.).
 *
 * 4. POLÍTICA DE COOKIES: informa sobre las cookies que usa la app
 *    (solo PHPSESSID, que es estrictamente necesaria).
 *
 * Aspectos técnicos:
 * - NO usa base de datos: todo es HTML estático renderizado con PHP.
 * - La navegación entre pestañas se hace por URL (?legal_tab=aviso).
 * - Se valida que la pestaña solicitada exista para evitar errores.
 * - usuario_inmobiliaria_nombre() obtiene el nombre de la inmobiliaria
 *   para personalizar los textos legales.
 *
 * Marco legal aplicable:
 * - Reglamento (UE) 2016/679 (RGPD)
 * - Ley Orgánica 3/2018 (LOPDGDD)
 * - Ley 34/2002 (LSSI-CE)
 * - Ley 7/1998 Condiciones Generales de Contratación
 *
 * @archivo  secciones/legal.php
 * @proyecto TinoProp - CRM Inmobiliario
 * @version  V0.0.31 Pre-Producción
 */

/*
 * SISTEMA DE PESTAÑAS (TABS)
 * La URL lleva un parámetro ?legal_tab=aviso|terminos|privacidad|cookies
 * que indica qué pestaña mostrar. Si no se envía o el valor no es válido,
 * mostramos 'aviso' por defecto.
 *
 * Esto evita tener 4 archivos PHP separados y permite compartir
 * un enlace directo a una pestaña específica.
 */
$tab = $_GET['legal_tab'] ?? 'aviso'; // Pestaña solicitada (o 'aviso' por defecto)
$tabs_validos = ['aviso', 'terminos', 'privacidad', 'cookies'];
if (!in_array($tab, $tabs_validos, true)) {
    $tab = 'aviso'; // Si el valor no es válido, volvemos al aviso legal
}

$nombre_app = 'TinoProp';
$empresa = usuario_inmobiliaria_nombre(); // Obtiene el nombre de la inmobiliaria del usuario logueado
?>

<div class="encabezado_seccion">
    <h1>📜 Marco Legal y Normativo</h1>
    <p class="texto_sec">Documentación legal de <?php echo e($nombre_app); ?> conforme a la legislación española y europea.</p>
</div>

<div class="legal_tabs">
    <a href="?seccion=legal&legal_tab=aviso" class="legal_tab <?php echo $tab === 'aviso' ? 'legal_tab_activo' : ''; ?>">Aviso Legal</a>
    <a href="?seccion=legal&legal_tab=terminos" class="legal_tab <?php echo $tab === 'terminos' ? 'legal_tab_activo' : ''; ?>">Términos y Condiciones</a>
    <a href="?seccion=legal&legal_tab=privacidad" class="legal_tab <?php echo $tab === 'privacidad' ? 'legal_tab_activo' : ''; ?>">Política de Privacidad</a>
    <a href="?seccion=legal&legal_tab=cookies" class="legal_tab <?php echo $tab === 'cookies' ? 'legal_tab_activo' : ''; ?>">Política de Cookies</a>
</div>

<?php if ($tab === 'aviso'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--                        AVISO LEGAL                             -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<article class="legal_contenido animate_fadeIn">
    <h2>Aviso Legal</h2>
    <p class="legal_fecha">Última actualización: 2 de marzo de 2026</p>

    <section class="legal_seccion">
        <h3>1. Datos identificativos</h3>
        <p>En cumplimiento del artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y de Comercio Electrónico (LSSI-CE), se informa al usuario que la titularidad de esta aplicación web (<strong><?php echo e($nombre_app); ?></strong>) corresponde a la inmobiliaria que ha contratado el servicio.</p>
        <p>Cada inmobiliaria registrada en la plataforma opera bajo su propia razón social, CIF y domicilio fiscal, siendo responsable del cumplimiento de la normativa aplicable a su actividad de intermediación inmobiliaria.</p>
    </section>

    <section class="legal_seccion">
        <h3>2. Objeto</h3>
        <p><?php echo e($nombre_app); ?> es una aplicación de gestión inmobiliaria (CRM) de tipo SaaS (Software as a Service) diseñada para la gestión integral de la actividad de agentes inmobiliarios en España: clientes, propiedades, ofertas, visitas, documentación, procesos de compraventa y alquiler.</p>
    </section>

    <section class="legal_seccion">
        <h3>3. Propiedad intelectual e industrial</h3>
        <p>El código fuente, diseño, estructura de navegación, bases de datos y demás elementos de <?php echo e($nombre_app); ?> están protegidos por las leyes de propiedad intelectual e industrial vigentes en España y la Unión Europea.</p>
        <p>Queda prohibida la reproducción total o parcial, distribución, comunicación pública, transformación o cualquier otro uso no expresamente autorizado por escrito.</p>
    </section>

    <section class="legal_seccion">
        <h3>4. Legislación aplicable y jurisdicción</h3>
        <p>Las presentes condiciones se rigen por la legislación española. Para la resolución de cualquier controversia, las partes se someten a los Juzgados y Tribunales de la localidad del domicilio social de la empresa titular, salvo que la normativa de protección de consumidores establezca otro fuero imperativo.</p>
    </section>

    <section class="legal_seccion">
        <h3>5. Limitación de responsabilidad</h3>
        <p><?php echo e($nombre_app); ?> no se responsabiliza del uso que hagan los usuarios de la información y datos gestionados a través de la plataforma. Cada inmobiliaria es responsable directa del tratamiento de datos personales de sus clientes.</p>
        <p>La plataforma no ofrece asesoramiento legal, fiscal ni financiero. Los documentos generados por las plantillas tienen carácter orientativo y deben ser revisados por un profesional cualificado antes de su uso formal.</p>
    </section>
</article>

<?php elseif ($tab === 'terminos'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--                  TÉRMINOS Y CONDICIONES                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<article class="legal_contenido animate_fadeIn">
    <h2>Términos y Condiciones de Uso</h2>
    <p class="legal_fecha">Última actualización: 2 de marzo de 2026</p>

    <section class="legal_seccion">
        <h3>1. Aceptación de los términos</h3>
        <p>El acceso y uso de <?php echo e($nombre_app); ?> implica la aceptación plena y sin reservas de los presentes Términos y Condiciones. Si no está de acuerdo con alguno de ellos, le rogamos que no utilice la plataforma.</p>
        <p>Al iniciar sesión por primera vez o tras una actualización sustancial de estos términos, se requerirá la aceptación expresa mediante una acción afirmativa.</p>
    </section>

    <section class="legal_seccion">
        <h3>2. Descripción del servicio</h3>
        <p><?php echo e($nombre_app); ?> proporciona a las agencias inmobiliarias una plataforma de gestión integral que incluye:</p>
        <ul class="legal_lista">
            <li>Gestión de cartera de clientes (compradores y vendedores)</li>
            <li>Gestión de inmuebles y propiedades</li>
            <li>Control de prospectos y pipeline comercial (Kanban)</li>
            <li>Generación de documentación inmobiliaria conforme a la legislación española</li>
            <li>Gestión de visitas, ofertas y contraofertas</li>
            <li>Procesos de compraventa y alquiler</li>
            <li>Seguimiento post-venta</li>
            <li>Sistema de recordatorios y notificaciones</li>
            <li>Búsqueda avanzada y matching cliente-propiedad</li>
            <li>Importación/exportación de datos (CSV)</li>
            <li>Panel de administración multi-inmobiliaria</li>
        </ul>
    </section>

    <section class="legal_seccion">
        <h3>3. Registro y cuentas de usuario</h3>
        <p><strong>3.1.</strong> El acceso a la plataforma se realiza mediante credenciales facilitadas por el administrador de la inmobiliaria (rol "Jefe") o por el SuperAdministrador del sistema.</p>
        <p><strong>3.2.</strong> No existe registro autónomo. Las cuentas se crean con una contraseña temporal que el usuario deberá cambiar en su primer acceso.</p>
        <p><strong>3.3.</strong> Cada usuario es responsable de mantener la confidencialidad de sus credenciales y de todas las acciones realizadas bajo su cuenta.</p>
        <p><strong>3.4.</strong> La contraseña debe cumplir los requisitos de seguridad: entre 8 y 16 caracteres, al menos una letra mayúscula y un carácter especial.</p>
        <p><strong>3.5.</strong> La cuenta podrá ser desactivada por el administrador sin previo aviso en caso de incumplimiento de estos términos.</p>
    </section>

    <section class="legal_seccion">
        <h3>4. Roles y niveles de acceso</h3>
        <p>La plataforma implementa un sistema de roles jerárquico:</p>
        <ul class="legal_lista">
            <li><strong>Agente Comprador / Agente Vendedor (Nivel 1):</strong> Acceso limitado a su equipo de trabajo.</li>
            <li><strong>Agente (Nivel 2):</strong> Acceso a ambos equipos (comprador y vendedor).</li>
            <li><strong>Marketing (Nivel 3):</strong> Acceso a datos de marketing y estadísticas.</li>
            <li><strong>Supervisor (Nivel 4):</strong> Visión general del equipo y módulos de sistema.</li>
            <li><strong>Jefe (Nivel 5):</strong> Gestión completa de la inmobiliaria, incluida la administración de usuarios.</li>
            <li><strong>SuperAdmin (Nivel 99):</strong> Administración global de todas las inmobiliarias de la plataforma.</li>
        </ul>
        <p>Cada usuario solo puede acceder a los datos de su propia inmobiliaria (aislamiento multi-tenant), salvo el SuperAdmin que gestiona la plataforma.</p>
    </section>

    <section class="legal_seccion">
        <h3>5. Uso aceptable</h3>
        <p>El usuario se compromete a:</p>
        <ul class="legal_lista">
            <li>Utilizar la plataforma exclusivamente para fines profesionales de intermediación inmobiliaria.</li>
            <li>Introducir datos veraces, exactos y actualizados.</li>
            <li>No intentar acceder a datos de otras inmobiliarias o usuarios no autorizados.</li>
            <li>No utilizar la plataforma para actividades ilícitas, fraudulentas o contrarias a la buena fe.</li>
            <li>No intentar realizar ingeniería inversa, descompilar o extraer código fuente.</li>
            <li>No introducir virus, malware o cualquier otro código dañino.</li>
            <li>Respetar la normativa de protección de datos personales en relación con los datos de clientes e interesados.</li>
        </ul>
    </section>

    <section class="legal_seccion">
        <h3>6. Protección de datos de clientes</h3>
        <p><strong>6.1.</strong> La inmobiliaria actúa como <strong>Responsable del Tratamiento</strong> de los datos personales de sus clientes (compradores, vendedores, arrendadores, arrendatarios, interesados).</p>
        <p><strong>6.2.</strong> <?php echo e($nombre_app); ?> actúa como <strong>Encargado del Tratamiento</strong> conforme al artículo 28 del RGPD.</p>
        <p><strong>6.3.</strong> Cada inmobiliaria es responsable de obtener el consentimiento legítimo de sus clientes para el tratamiento de datos, así como de informarles de sus derechos.</p>
        <p><strong>6.4.</strong> Las plantillas de documentación incluyen modelos de consentimiento RGPD y autorización para el tratamiento de datos personales que la inmobiliaria debe adaptar a su contexto específico.</p>
    </section>

    <section class="legal_seccion">
        <h3>7. Documentación generada</h3>
        <p><strong>7.1.</strong> Las plantillas de documentación incluidas en la plataforma (contratos de arras, encargos de venta, contratos de alquiler, etc.) tienen carácter orientativo y se proporcionan como herramienta de apoyo.</p>
        <p><strong>7.2.</strong> Dichos documentos <strong>no constituyen asesoramiento jurídico</strong>. Se recomienda encarecidamente que sean revisados por un profesional del derecho antes de su firma y uso.</p>
        <p><strong>7.3.</strong> <?php echo e($nombre_app); ?> no se responsabiliza de las consecuencias derivadas del uso de la documentación generada sin revisión profesional.</p>
    </section>

    <section class="legal_seccion">
        <h3>8. Propiedad de los datos</h3>
        <p><strong>8.1.</strong> Los datos introducidos por cada inmobiliaria son propiedad exclusiva de dicha inmobiliaria.</p>
        <p><strong>8.2.</strong> <?php echo e($nombre_app); ?> no accederá a los datos de negocio de las inmobiliarias salvo por razones técnicas de mantenimiento o previa solicitud explícita.</p>
        <p><strong>8.3.</strong> En caso de cancelación del servicio, la inmobiliaria tendrá derecho a solicitar la exportación completa de sus datos en un formato estándar (CSV) dentro de los 30 días siguientes.</p>
    </section>

    <section class="legal_seccion">
        <h3>9. Disponibilidad y nivel de servicio</h3>
        <p><strong>9.1.</strong> Se realizarán los mejores esfuerzos para garantizar la disponibilidad del servicio, sin que esto suponga una obligación de resultado.</p>
        <p><strong>9.2.</strong> Se podrán realizar interrupciones programadas para mantenimiento, que se comunicarán con antelación razonable.</p>
        <p><strong>9.3.</strong> <?php echo e($nombre_app); ?> no será responsable de interrupciones causadas por fuerza mayor, fallos de terceros proveedores de infraestructura, o acciones del usuario.</p>
    </section>

    <section class="legal_seccion">
        <h3>10. Limitación de responsabilidad</h3>
        <p><strong>10.1.</strong> En ningún caso la responsabilidad total de <?php echo e($nombre_app); ?> excederá el importe abonado por la inmobiliaria en los últimos 12 meses de servicio.</p>
        <p><strong>10.2.</strong> No se asume responsabilidad por daños indirectos, pérdida de beneficios, pérdida de datos (más allá de las obligaciones de respaldo comprometidas), ni por el uso inadecuado de la plataforma.</p>
    </section>

    <section class="legal_seccion">
        <h3>11. Modificaciones de los términos</h3>
        <p>Estos términos podrán ser actualizados periódicamente. Los cambios sustanciales se notificarán a los usuarios con al menos 15 días de antelación, requiriéndose nueva aceptación expresa para continuar usando el servicio.</p>
    </section>

    <section class="legal_seccion">
        <h3>12. Resolución y cancelación</h3>
        <p><strong>12.1.</strong> El servicio se puede resolver por cualquiera de las partes con un preaviso de 30 días.</p>
        <p><strong>12.2.</strong> El incumplimiento grave de estos términos faculta a la suspensión inmediata del acceso.</p>
        <p><strong>12.3.</strong> La resolución del servicio no exime del cumplimiento de las obligaciones de protección de datos adquiridas durante la vigencia.</p>
    </section>

    <section class="legal_seccion">
        <h3>13. Legislación aplicable y fuero</h3>
        <p>Los presentes Términos se rigen por la legislación española. Para la resolución de cualquier controversia, ambas partes se someterán a la jurisdicción de los Juzgados y Tribunales del domicilio del titular de la plataforma, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, salvo que la normativa aplicable disponga otro fuero imperativo.</p>
    </section>
</article>

<?php elseif ($tab === 'privacidad'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--                  POLÍTICA DE PRIVACIDAD                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<article class="legal_contenido animate_fadeIn">
    <h2>Política de Privacidad</h2>
    <p class="legal_fecha">Última actualización: 2 de marzo de 2026</p>
    <p class="legal_intro">En cumplimiento del Reglamento (UE) 2016/679 del Parlamento Europeo y del Consejo, de 27 de abril de 2016 (RGPD) y la Ley Orgánica 3/2018, de 5 de diciembre, de Protección de Datos Personales y garantía de los derechos digitales (LOPDGDD), se proporciona la siguiente información sobre el tratamiento de datos personales.</p>

    <section class="legal_seccion">
        <h3>1. Responsable del tratamiento</h3>
        <div class="legal_tabla">
            <div class="legal_tabla_fila">
                <span class="legal_tabla_clave">Plataforma:</span>
                <span class="legal_tabla_valor"><?php echo e($nombre_app); ?> (Software de gestión inmobiliaria)</span>
            </div>
            <div class="legal_tabla_fila">
                <span class="legal_tabla_clave">Rol:</span>
                <span class="legal_tabla_valor">Encargado del tratamiento (art. 28 RGPD)</span>
            </div>
            <div class="legal_tabla_fila">
                <span class="legal_tabla_clave">Responsable del tratamiento:</span>
                <span class="legal_tabla_valor">Cada inmobiliaria registrada en la plataforma</span>
            </div>
        </div>
        <p>Cada inmobiliaria que utiliza <?php echo e($nombre_app); ?> es la <strong>Responsable del Tratamiento</strong> de los datos personales de sus clientes. <?php echo e($nombre_app); ?> actúa como <strong>Encargado del Tratamiento</strong>, procesando datos únicamente según las instrucciones de cada inmobiliaria.</p>
    </section>

    <section class="legal_seccion">
        <h3>2. Datos personales que se tratan</h3>
        <h4>2.1. Datos de usuarios de la plataforma (empleados de la inmobiliaria)</h4>
        <ul class="legal_lista">
            <li>Nombre completo</li>
            <li>Dirección de correo electrónico corporativo</li>
            <li>Contraseña (almacenada con hash bcrypt, nunca en texto plano)</li>
            <li>Rol dentro de la organización</li>
            <li>Inmobiliaria a la que pertenece</li>
            <li>Fecha de registro y último acceso</li>
            <li>Preferencias de interfaz (tema, idioma, densidad)</li>
        </ul>

        <h4>2.2. Datos de clientes de las inmobiliarias</h4>
        <ul class="legal_lista">
            <li>Nombre y apellidos</li>
            <li>Email y teléfono</li>
            <li>Dirección postal</li>
            <li>DNI/NIE (cuando se incluye en documentos)</li>
            <li>Preferencias inmobiliarias (zona, presupuesto, tipo de operación)</li>
            <li>Historial de interacciones (visitas, ofertas, notas)</li>
        </ul>

        <h4>2.3. Datos de propiedades</h4>
        <ul class="legal_lista">
            <li>Dirección y ubicación del inmueble</li>
            <li>Características (superficie, habitaciones, estado)</li>
            <li>Referencia catastral</li>
            <li>Fotografías del inmueble</li>
            <li>Precio y condiciones económicas</li>
        </ul>
    </section>

    <section class="legal_seccion">
        <h3>3. Finalidad del tratamiento</h3>
        <table class="legal_tabla_formal">
            <thead>
                <tr>
                    <th>Finalidad</th>
                    <th>Base jurídica</th>
                    <th>Plazo de conservación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gestión de la cuenta de usuario</td>
                    <td>Ejecución del contrato (art. 6.1.b RGPD)</td>
                    <td>Duración de la relación contractual + 5 años</td>
                </tr>
                <tr>
                    <td>Intermediación inmobiliaria</td>
                    <td>Interés legítimo (art. 6.1.f RGPD) / Consentimiento</td>
                    <td>Duración de la gestión + 5 años por prescripción</td>
                </tr>
                <tr>
                    <td>Generación de documentación</td>
                    <td>Ejecución del contrato (art. 6.1.b RGPD)</td>
                    <td>10 años (obligaciones fiscales y mercantiles)</td>
                </tr>
                <tr>
                    <td>Registro de actividad del sistema</td>
                    <td>Interés legítimo (seguridad) (art. 6.1.f RGPD)</td>
                    <td>1 año</td>
                </tr>
                <tr>
                    <td>Comunicaciones de servicio</td>
                    <td>Interés legítimo (art. 6.1.f RGPD)</td>
                    <td>Duración de la relación contractual</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="legal_seccion">
        <h3>4. Destinatarios de los datos</h3>
        <p>Los datos personales no serán cedidos a terceros salvo:</p>
        <ul class="legal_lista">
            <li>Obligación legal (requerimiento judicial, fiscal o administrativo).</li>
            <li>Proveedores de infraestructura y hosting necesarios para la prestación del servicio, con los correspondientes contratos de encargo de tratamiento (art. 28 RGPD).</li>
            <li>No se realizan transferencias internacionales de datos fuera del Espacio Económico Europeo.</li>
        </ul>
    </section>

    <section class="legal_seccion">
        <h3>5. Derechos del interesado</h3>
        <p>Conforme a los artículos 15 a 22 del RGPD y los artículos 12 a 18 de la LOPDGDD, los interesados pueden ejercer los siguientes derechos:</p>
        <div class="legal_derechos_grid">
            <div class="legal_derecho">
                <strong>📋 Acceso</strong>
                <p>Conocer qué datos personales están siendo tratados.</p>
            </div>
            <div class="legal_derecho">
                <strong>✏️ Rectificación</strong>
                <p>Solicitar la corrección de datos inexactos o incompletos.</p>
            </div>
            <div class="legal_derecho">
                <strong>🗑️ Supresión</strong>
                <p>Solicitar la eliminación de los datos cuando ya no sean necesarios.</p>
            </div>
            <div class="legal_derecho">
                <strong>⏸️ Limitación</strong>
                <p>Solicitar la limitación del tratamiento en determinadas circunstancias.</p>
            </div>
            <div class="legal_derecho">
                <strong>📦 Portabilidad</strong>
                <p>Recibir los datos en formato estructurado y legible por máquina.</p>
            </div>
            <div class="legal_derecho">
                <strong>🚫 Oposición</strong>
                <p>Oponerse al tratamiento de sus datos por motivos legítimos.</p>
            </div>
        </div>
        <p>Para ejercer estos derechos, el interesado deberá dirigirse directamente a la inmobiliaria que gestiona sus datos (Responsable del Tratamiento) mediante comunicación escrita, acompañada de copia de su documento de identidad.</p>
        <p>Igualmente, tiene derecho a presentar una reclamación ante la <strong>Agencia Española de Protección de Datos (AEPD)</strong> — <a href="https://www.aepd.es" target="_blank" rel="noopener noreferrer">www.aepd.es</a></p>
    </section>

    <section class="legal_seccion">
        <h3>6. Medidas de seguridad</h3>
        <p><?php echo e($nombre_app); ?> implementa las medidas de seguridad técnicas y organizativas adecuadas conforme al artículo 32 del RGPD:</p>
        <ul class="legal_lista">
            <li><strong>Autenticación segura:</strong> Contraseñas almacenadas con hash bcrypt (costo adaptativo). Contraseñas temporales con cambio obligatorio en primer acceso.</li>
            <li><strong>Control de acceso:</strong> Sistema de roles jerárquico con principio de mínimo privilegio. Aislamiento multi-tenant por inmobiliaria.</li>
            <li><strong>Protección CSRF:</strong> Tokens de verificación en todos los formularios y endpoints de escritura.</li>
            <li><strong>Protección XSS:</strong> Escape de salida con <code>htmlspecialchars()</code> en todas las vistas.</li>
            <li><strong>Protección SQL Injection:</strong> Consultas preparadas con parámetros enlazados (PDO).</li>
            <li><strong>Gestión de sesiones:</strong> Regeneración de ID tras autenticación. Invalidación completa en logout. Cookies de sesión con flags seguros.</li>
            <li><strong>Registro de actividad:</strong> Log de acciones críticas (creación, modificación, eliminación de datos).</li>
            <li><strong>Copias de seguridad:</strong> Procedimientos de backup periódico de bases de datos y archivos.</li>
        </ul>
    </section>

    <section class="legal_seccion">
        <h3>7. Conservación y supresión de datos</h3>
        <p>Los datos se conservarán durante el tiempo necesario para cumplir la finalidad para la que fueron recogidos y para determinar las posibles responsabilidades derivadas de dicha finalidad y del tratamiento de los datos.</p>
        <p>Al finalizar la relación contractual, los datos se bloquearán durante los plazos legales de prescripción, tras lo cual serán eliminados de forma segura.</p>
    </section>

    <section class="legal_seccion">
        <h3>8. Encargo de tratamiento</h3>
        <p>La relación entre <?php echo e($nombre_app); ?> (encargado) y cada inmobiliaria (responsable) se rige por un contrato de encargo de tratamiento conforme al artículo 28 del RGPD, que establece:</p>
        <ul class="legal_lista">
            <li>Los datos se tratan únicamente según instrucciones documentadas del responsable.</li>
            <li>Existe compromiso de confidencialidad de todas las personas autorizadas para tratar datos.</li>
            <li>Se aplican las medidas de seguridad del artículo 32 del RGPD.</li>
            <li>Se prestará asistencia al responsable en el ejercicio de derechos de los interesados.</li>
            <li>Al término de la relación, los datos serán devueltos o eliminados, según elija el responsable.</li>
        </ul>
    </section>
</article>

<?php elseif ($tab === 'cookies'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--                    POLÍTICA DE COOKIES                         -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<article class="legal_contenido animate_fadeIn">
    <h2>Política de Cookies</h2>
    <p class="legal_fecha">Última actualización: 2 de marzo de 2026</p>

    <section class="legal_seccion">
        <h3>1. ¿Qué son las cookies?</h3>
        <p>Las cookies son pequeños archivos de texto que los sitios web almacenan en el dispositivo del usuario. Contienen información que permite recordar preferencias o mantener sesiones de usuario activas.</p>
    </section>

    <section class="legal_seccion">
        <h3>2. Cookies utilizadas por <?php echo e($nombre_app); ?></h3>
        <table class="legal_tabla_formal">
            <thead>
                <tr>
                    <th>Cookie</th>
                    <th>Tipo</th>
                    <th>Finalidad</th>
                    <th>Duración</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>PHPSESSID</code></td>
                    <td>Técnica (estrictamente necesaria)</td>
                    <td>Mantener la sesión del usuario autenticado. Sin esta cookie el servicio no funciona.</td>
                    <td>Duración de la sesión</td>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="legal_seccion">
        <h3>3. Cookies de terceros</h3>
        <p><?php echo e($nombre_app); ?> únicamente carga recursos externos necesarios para su funcionamiento:</p>
        <ul class="legal_lista">
            <li><strong>Chart.js</strong> (cdn.jsdelivr.net): Biblioteca de gráficos para el dashboard. No instala cookies.</li>
            <li><strong>jsPDF</strong> (cdnjs.cloudflare.com): Generación de documentos PDF en el navegador. No instala cookies.</li>
            <li><strong>Font Awesome</strong> (cdnjs.cloudflare.com): Iconos de interfaz. No instala cookies.</li>
            <li><strong>Google Fonts</strong> (fonts.googleapis.com): Fuente tipográfica Inter. Puede registrar accesos según la política de Google.</li>
        </ul>
        <p>No se utilizan cookies de analítica, publicidad ni seguimiento de ningún tipo.</p>
    </section>

    <section class="legal_seccion">
        <h3>4. Base jurídica</h3>
        <p>Las cookies utilizadas son <strong>estrictamente necesarias</strong> para la prestación del servicio solicitado por el usuario (art. 22.2 LSSI-CE), por lo que están exentas del requisito de consentimiento previo.</p>
    </section>

    <section class="legal_seccion">
        <h3>5. Gestión de cookies</h3>
        <p>El usuario puede configurar su navegador para rechazar cookies. Sin embargo, al ser cookies estrictamente necesarias, el rechazo impedirá el uso de la plataforma.</p>
    </section>
</article>
<?php endif; ?>
