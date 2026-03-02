<?php
/*
 * =============================================================================
 * Archivo: aceptar-terminos.php — V0.0.31 Pre-Producción
 * =============================================================================
 * Rol: pantalla de aceptación obligatoria de Términos y Condiciones
 *      y Política de Privacidad.
 *
 * ¿Por qué existe esta página?
 * ----------------------------
 * TinoProp maneja datos personales de clientes (nombres, teléfonos, DNI...),
 * por lo que debe cumplir la normativa europea de protección de datos:
 *   - RGPD (Reglamento General de Protección de Datos) — UE 2016/679
 *   - LOPDGDD (Ley Orgánica de Protección de Datos) — España, LO 3/2018
 *
 * Cada usuario debe aceptar los términos antes de usar la aplicación.
 * Si se actualizan los términos (nueva TERMINOS_VERSION), el usuario
 * deberá aceptar de nuevo la versión actualizada.
 *
 * Flujo:
 *   1. El usuario hace login exitoso en login.php.
 *   2. bootstrap.php comprueba si ha aceptado la versión vigente.
 *   3. Si no la ha aceptado → redirige aquí.
 *   4. El usuario lee los términos, marca la casilla y envía.
 *   5. Se registra la aceptación con timestamp en la BD.
 *   6. Redirección a index.php (dashboard del CRM).
 * =============================================================================
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Si no hay sesión, redirigir al login.
if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Si ya aceptó la versión vigente, no necesita estar aquí.
if (usuario_ha_aceptado_terminos()) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (empty($_POST['acepto_terminos'])) {
        $mensaje = 'Debes aceptar los Términos y Condiciones y la Política de Privacidad para continuar.';
        $mensaje_tipo = 'error';
    } else {
        $pdo = db();
        usuario_aceptar_terminos($pdo, (int) $_SESSION['usuario']['id']);

        // Registrar en actividad
        actividad_registrar($pdo, 'aceptar_terminos', 'usuario', (int) $_SESSION['usuario']['id'], 'Aceptó Términos y Condiciones v' . TERMINOS_VERSION);

        header('Location: index.php');
        exit;
    }
}
?>
<!--
  =============================================================================
  SECCIÓN HTML: Página de aceptación de términos legales
  =============================================================================
  Esta página muestra un resumen de los Términos y Condiciones y la
  Política de Privacidad. El usuario debe marcar un checkbox y confirmar.
  
  Es una página "standalone" (independiente del CRM): no tiene sidebar
  ni header, solo la tarjeta centrada sobre fondo degradado.
  =============================================================================
-->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceptar Términos — TinoProp</title>
    <!-- Mismos CSS que el login para mantener coherencia visual -->
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <!--
      .terminos_container: contenedor principal más ancho que el login (700px)
      para acomodar el texto legal. Estilos definidos en estilo.css (L6950+).
    -->
    <div class="terminos_container">
        <h1>📜 Términos y Condiciones</h1>
        <p class="terminos_subtitle">Antes de continuar, debes leer y aceptar los Términos y Condiciones de uso y la Política de Privacidad.</p>

        <!--
          .terminos_scroll: contenedor con scroll vertical (max-height: 350px)
          para que el texto legal no ocupe toda la pantalla.
          El usuario debe hacer scroll para leer todo el contenido.
          
          NOTA LEGAL: Este es un RESUMEN de los términos. El texto completo
          está disponible en la sección "Legal" del CRM una vez dentro.
          
          Cada sección (h3) aborda un aspecto legal diferente:
          1. Aceptación general
          2. Descripción del servicio CRM
          3. Protección de datos (RGPD/LOPDGDD) — la más importante
          4. Responsabilidad sobre credenciales
          5. Uso aceptable de la plataforma
          6. Carácter orientativo de la documentación
          7. Propiedad de los datos de cada inmobiliaria
          8. Medidas de seguridad implementadas
          9. Legislación aplicable (española)
        -->
        <div class="terminos_scroll">
            <h3>1. Aceptación</h3>
            <p>El acceso y uso de TinoProp implica la aceptación plena de estos Términos y Condiciones y la Política de Privacidad.</p>

            <h3>2. Descripción del servicio</h3>
            <p>TinoProp es una aplicación de gestión inmobiliaria (CRM) para la gestión integral de clientes, propiedades, documentación, visitas, ofertas y procesos de compraventa y alquiler.</p>

            <!--
              SECCIÓN 3: RGPD — La sección más importante legalmente.
              Define los roles de Responsable y Encargado del Tratamiento:
              - Responsable: la inmobiliaria (decide qué datos recoger y para qué).
              - Encargado: TinoProp como software (procesa datos por cuenta de la inmobiliaria).
              También menciona los derechos ARCO del ciudadano:
              Acceso, Rectificación, Cancelación y Oposición.
            -->
            <h3>3. Protección de datos (RGPD/LOPDGDD)</h3>
            <p>Los datos personales se tratan conforme al Reglamento (UE) 2016/679 (RGPD) y la Ley Orgánica 3/2018 (LOPDGDD):</p>
            <ul>
                <li>La inmobiliaria actúa como <strong>Responsable del Tratamiento</strong> de los datos de sus clientes.</li>
                <li>TinoProp actúa como <strong>Encargado del Tratamiento</strong> (art. 28 RGPD).</li>
                <li>Los datos se tratan para la gestión de la intermediación inmobiliaria.</li>
                <li>Se implementan medidas de seguridad técnicas y organizativas adecuadas.</li>
                <li>Los interesados pueden ejercer sus derechos ARCO (acceso, rectificación, cancelación, oposición) dirigiéndose a su inmobiliaria.</li>
            </ul>

            <h3>4. Usuarios y credenciales</h3>
            <p>Cada usuario es responsable de la confidencialidad de sus credenciales y de las acciones realizadas bajo su cuenta. Las cuentas se crean por administradores con contraseña temporal de cambio obligatorio.</p>

            <h3>5. Uso aceptable</h3>
            <p>La plataforma se utilizará exclusivamente para fines profesionales de intermediación inmobiliaria. Está prohibido introducir datos falsos, acceder a datos no autorizados o realizar actividades ilícitas.</p>

            <h3>6. Documentación generada</h3>
            <p>Las plantillas de documentación tienen carácter orientativo. No constituyen asesoramiento jurídico. Deben ser revisadas por un profesional del derecho antes de su uso formal.</p>

            <h3>7. Propiedad de los datos</h3>
            <p>Los datos introducidos por cada inmobiliaria son propiedad exclusiva de dicha inmobiliaria. En caso de cancelación, se garantiza la exportación de datos en formato estándar.</p>

            <!--
              SECCIÓN 8: Enumera las medidas técnicas de seguridad de TinoProp.
              Esto es obligatorio por el art. 32 del RGPD (seguridad del tratamiento).
            -->
            <h3>8. Medidas de seguridad</h3>
            <p>Contraseñas con hash bcrypt, sistema de roles jerárquico, aislamiento multi-tenant, protección CSRF y XSS, consultas preparadas (SQL injection), registro de actividad.</p>

            <h3>9. Legislación aplicable</h3>
            <p>Estos términos se rigen por la legislación española. Las partes se someten a los Juzgados y Tribunales correspondientes.</p>

            <p><strong>Puede consultar el texto completo en la sección "Legal" de la aplicación.</strong></p>
        </div>

        <!--
          FORMULARIO DE ACEPTACIÓN
          Es un formulario POST simple con:
          - Un checkbox obligatorio (acepto_terminos)
          - Un botón de envío
          - Token CSRF oculto para seguridad
          
          El checkbox NO tiene "required" en HTML porque la validación se hace
          en PHP (servidor) para mostrar un mensaje personalizado en español
          en vez del mensaje genérico del navegador.
        -->
        <form method="POST" class="terminos_actions">
            <!-- Token CSRF: protección contra envíos fraudulentos -->
            <?php echo csrf_field(); ?>

            <!-- Mensaje de error si el usuario intentó enviar sin marcar la casilla -->
            <?php if ($mensaje): ?>
                <p class="error-text"><?php echo e($mensaje); ?></p>
            <?php endif; ?>

            <!--
              Casilla de aceptación con enlaces a los textos legales completos.
              - target="_blank": abre en pestaña nueva para no perder esta página.
              - rel="noopener": seguridad, evita que la página abierta pueda
                manipular window.opener (la página original).
              - El value="1" se envía como $_POST['acepto_terminos'] = '1'
                solo si el checkbox está marcado. Si no, no se envía nada.
            -->
            <label class="legal_accept_wrap">
                <input type="checkbox" name="acepto_terminos" value="1">
                He leído y acepto los <a href="index.php?seccion=legal&legal_tab=terminos" target="_blank" rel="noopener">Términos y Condiciones</a>
                y la <a href="index.php?seccion=legal&legal_tab=privacidad" target="_blank" rel="noopener">Política de Privacidad</a>
                de TinoProp conforme al RGPD y la LOPDGDD.
            </label>

            <button type="submit">Aceptar y Continuar</button>
        </form>

        <!--
          Muestra la versión actual de los términos (constante TERMINOS_VERSION).
          Si se actualiza esta constante, todos los usuarios deberán aceptar
          de nuevo los términos la próxima vez que inicien sesión.
        -->
        <p class="terminos_version">Versión de los términos: <?php echo e(TERMINOS_VERSION); ?></p>
    </div>
</body>
</html>
