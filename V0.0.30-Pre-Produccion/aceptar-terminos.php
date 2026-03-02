<?php
/*
 * Archivo: aceptar-terminos.php — V0.0.30
 * Rol: pantalla de aceptación obligatoria de Términos y Condiciones y Política de Privacidad.
 * Flujo: el usuario que no ha aceptado la versión vigente es redirigido aquí desde bootstrap.php.
 *        Debe marcar la casilla y confirmar para continuar.
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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceptar Términos — TinoProp</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="terminos_container">
        <h1>📜 Términos y Condiciones</h1>
        <p class="terminos_subtitle">Antes de continuar, debes leer y aceptar los Términos y Condiciones de uso y la Política de Privacidad.</p>

        <div class="terminos_scroll">
            <h3>1. Aceptación</h3>
            <p>El acceso y uso de TinoProp implica la aceptación plena de estos Términos y Condiciones y la Política de Privacidad.</p>

            <h3>2. Descripción del servicio</h3>
            <p>TinoProp es una aplicación de gestión inmobiliaria (CRM) para la gestión integral de clientes, propiedades, documentación, visitas, ofertas y procesos de compraventa y alquiler.</p>

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

            <h3>8. Medidas de seguridad</h3>
            <p>Contraseñas con hash bcrypt, sistema de roles jerárquico, aislamiento multi-tenant, protección CSRF y XSS, consultas preparadas (SQL injection), registro de actividad.</p>

            <h3>9. Legislación aplicable</h3>
            <p>Estos términos se rigen por la legislación española. Las partes se someten a los Juzgados y Tribunales correspondientes.</p>

            <p><strong>Puede consultar el texto completo en la sección "Legal" de la aplicación.</strong></p>
        </div>

        <form method="POST" class="terminos_actions">
            <?php echo csrf_field(); ?>

            <?php if ($mensaje): ?>
                <p class="error-text"><?php echo e($mensaje); ?></p>
            <?php endif; ?>

            <label class="legal_accept_wrap">
                <input type="checkbox" name="acepto_terminos" value="1">
                He leído y acepto los <a href="?seccion=legal&legal_tab=terminos" target="_blank">Términos y Condiciones</a>
                y la <a href="?seccion=legal&legal_tab=privacidad" target="_blank">Política de Privacidad</a>
                de TinoProp conforme al RGPD y la LOPDGDD.
            </label>

            <button type="submit">Aceptar y Continuar</button>
        </form>

        <p class="terminos_version">Versión de los términos: <?php echo e(TERMINOS_VERSION); ?></p>
    </div>
</body>
</html>
