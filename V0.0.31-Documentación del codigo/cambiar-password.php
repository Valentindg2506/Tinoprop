<?php
/*
 * =============================================================================
 * Archivo: cambiar-password.php — V0.0.31 Pre-Producción
 * =============================================================================
 * Rol: pantalla de cambio obligatorio de contraseña temporal.
 *
 * ¿Cuándo se llega aquí?
 * ----------------------
 * Cuando un SuperAdmin o Jefe crea un usuario nuevo, le asigna una
 * contraseña temporal (password_temporal = 1 en la BD). Tras el primer
 * login exitoso, el sistema redirige automáticamente a esta página
 * para que el usuario elija su propia contraseña segura.
 *
 * Flujo:
 *   1. login.php detecta password_temporal = true → redirige aquí.
 *   2. El usuario escribe su nueva contraseña (2 veces para confirmar).
 *   3. Se valida la política de seguridad: mín. 8 chars + 1 mayúscula + 1 símbolo.
 *   4. Se guarda el hash bcrypt en la BD y se marca password_temporal = 0.
 *   5. Redirección a index.php (o aceptar-terminos.php si falta).
 *
 * Seguridad:
 *   - La contraseña NUNCA se almacena en texto plano: se usa password_hash().
 *   - La validación se hace tanto en el navegador (HTML5 pattern) como en
 *     el servidor (PHP), porque la validación del cliente se puede saltar.
 * =============================================================================
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Si no hay sesión, redirigir al login.
if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

// Si no tiene password temporal, no necesita estar aquí.
if (empty($_SESSION['usuario']['password_temporal'])) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nueva = $_POST['nueva_password'] ?? '';
    $repetir = $_POST['repetir_password'] ?? '';

    if ($nueva === '' || $repetir === '') {
        $mensaje = 'Completa ambos campos de contraseña.';
        $mensaje_tipo = 'error';
    } elseif (!validar_password_segura($nueva)) {
        $mensaje = 'La contraseña debe tener al menos 8 caracteres, 1 mayúscula y 1 símbolo.';
        $mensaje_tipo = 'error';
    } elseif ($nueva !== $repetir) {
        $mensaje = 'Las contraseñas no coinciden.';
        $mensaje_tipo = 'error';
    } else {
        $pdo = db();
        $uid = (int) $_SESSION['usuario']['id'];
        $hash = password_hash($nueva, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash, password_temporal = 0 WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $uid]);

        // Quitar la marca de la sesión
        $_SESSION['usuario']['password_temporal'] = false;

        actividad_registrar($pdo, 'editar', 'usuario', $uid, 'Contraseña temporal cambiada por el usuario');

        header('Location: index.php');
        exit;
    }
}

/*
 * e() es una función de escape que convierte caracteres especiales HTML
 * (como < > " &) en entidades seguras. Previene ataques XSS:
 * si el nombre del usuario contuviera <script>, se mostraría como texto,
 * no se ejecutaría como código JavaScript.
 */
$nombre = e($_SESSION['usuario']['nombre'] ?? 'Usuario');
?>
<!--
  =============================================================================
  SECCIÓN HTML: Formulario de cambio de contraseña
  =============================================================================
  Usa la misma estructura visual que login.php (tarjeta centrada con fondo
  degradado) para mantener coherencia en la experiencia pre-login.
  =============================================================================
-->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña — TinoProp</title>
    <!-- Se cargan los mismos CSS que en login.php para mantener el mismo aspecto -->
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="container login-solo" id="container">
        <div class="form-container sign-in-container" style="width:100%;">
            <!--
              El formulario envía a cambiar-password.php (esta misma página).
              El código PHP de arriba procesa el POST y, si todo va bien,
              redirige a index.php. Si hay error, vuelve a mostrar este HTML
              con el mensaje de error.
            -->
            <form method="POST" action="cambiar-password.php">
                <h1>🔑 Nueva Contraseña</h1>
                <!--
                  Mensaje personalizado con el nombre del usuario.
                  Le explica POR QUÉ está aquí: su contraseña es temporal
                  y debe cambiarla antes de usar el CRM.
                -->
                <p class="login-subtitulo">Hola <?php echo $nombre; ?>, tu cuenta tiene una contraseña temporal.<br>Debes cambiarla antes de continuar.</p>

                <!--
                  Campo de nueva contraseña:
                  - minlength="8": validación HTML5 (mínimo 8 caracteres).
                  - pattern: expresión regular que exige al menos 1 mayúscula
                    y 1 símbolo especial. El navegador muestra el "title" como
                    tooltip de ayuda si no cumple el patrón.
                    (?=.*[A-Z])  → al menos una mayúscula
                    (?=.*[\W_])  → al menos un símbolo (no-alfanumérico)
                    .{8,}        → mínimo 8 caracteres de cualquier tipo
                  - autocomplete="new-password": le dice al navegador que es
                    una contraseña NUEVA (para su gestor de contraseñas).
                  IMPORTANTE: esta validación es del CLIENTE (navegador).
                  Se puede saltar con las DevTools, por eso también se valida
                  en el servidor con validar_password_segura().
                -->
                <div class="input-group">
                    <input type="password" name="nueva_password" placeholder="Nueva contraseña" required
                           minlength="8"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}"
                           title="Mínimo 8 caracteres, al menos 1 mayúscula y 1 símbolo."
                           autocomplete="new-password">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <!--
                  Campo de repetición de contraseña:
                  El mismo patrón de validación. En el servidor se comprueba
                  que $nueva === $repetir para asegurar que no haya erratas.
                  NOTA: la comparación de igualdad se hace en PHP (servidor),
                  no con JavaScript, para mayor seguridad.
                -->
                <div class="input-group">
                    <input type="password" name="repetir_password" placeholder="Repetir contraseña" required
                           minlength="8"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}"
                           title="Mínimo 8 caracteres, al menos 1 mayúscula y 1 símbolo."
                           autocomplete="new-password">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <!-- Mensaje de error: se muestra solo si la validación del servidor falló -->
                <?php if ($mensaje): ?>
                    <p class="error-text"><?php echo e($mensaje); ?></p>
                <?php endif; ?>

                <!-- Token CSRF oculto para protección contra ataques cross-site -->
                <?php echo csrf_field(); ?>
                <button type="submit">Establecer Contraseña</button>

                <!--
                  Texto informativo con los requisitos de contraseña.
                  Se muestra siempre (no solo cuando hay error) para que
                  el usuario sepa qué se espera ANTES de escribir.
                -->
                <p class="login-ayuda">
                    Requisitos: mínimo 8 caracteres, al menos 1 mayúscula y 1 símbolo.
                </p>
            </form>
        </div>
    </div>
</body>
</html>
