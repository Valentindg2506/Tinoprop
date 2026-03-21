<?php
/*
 * =============================================================================
 * Archivo: login.php — V0.0.31 Pre-Producción
 * =============================================================================
 * Rol: pantalla de acceso al CRM TinoProp.
 *
 * ¿Cómo funciona el flujo de autenticación?
 * ------------------------------------------
 * 1. El usuario llega a esta página y ve el formulario de email + contraseña.
 * 2. Envía el formulario por POST.
 * 3. El servidor verifica el token CSRF (protección contra ataques CSRF).
 * 4. Se validan los campos (no vacíos, email con formato válido).
 * 5. Se comprueba si la IP está bloqueada por demasiados intentos (rate limiting).
 * 6. Se busca el usuario en la base de datos por email.
 * 7. Se verifica la contraseña con password_verify() (compara contra hash bcrypt).
 * 8. Se comprueba que el usuario y su inmobiliaria estén activos.
 * 9. Si todo es correcto:
 *    a) Se regenera el ID de sesión (seguridad contra session fixation).
 *    b) Se guardan los datos del usuario en $_SESSION.
 *    c) Se redirige según el estado:
 *       - password temporal → cambiar-password.php
 *       - términos no aceptados → aceptar-terminos.php  
 *       - todo OK → index.php (panel principal)
 *
 * NOTA: TinoProp NO tiene registro público. Los usuarios los crea
 * el SuperAdmin o el Jefe de cada inmobiliaria desde el panel de admin.
 * =============================================================================
 */

/* Cargamos el bootstrap: conexión a BD, funciones auxiliares, sesión PHP, etc. */
require_once __DIR__ . '/inc/bootstrap.php';

/* Variable para almacenar mensajes de error que se mostrarán en el formulario */
$mensaje = '';

/*
 * PASO 1: Comprobar si ya hay una sesión activa.
 * Si el usuario ya inició sesión, no tiene sentido mostrar el login.
 * header('Location: ...') envía una cabecera HTTP 302 al navegador
 * que le dice "ve a esta otra página". exit detiene el script.
 */
if (!empty($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

/*
 * PASO 2: Procesar el formulario cuando se envía por POST.
 * $_SERVER['REQUEST_METHOD'] === 'POST' comprueba que el usuario
 * ha enviado el formulario (no que simplemente ha cargado la página).
 * La primera vez que entras en login.php, el método es GET.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /*
     * csrf_verify() comprueba el token CSRF enviado con el formulario.
     * ¿Qué es CSRF? Cross-Site Request Forgery: un ataque donde un sitio
     * malicioso envía una petición a TinoProp aprovechando tu sesión.
     * El token CSRF es un valor secreto único que solo conoce nuestro formulario.
     */
    csrf_verify();

    /* trim() elimina espacios en blanco al inicio/final del email.
     * El operador ?? (null coalescing) devuelve '' si no existe $_POST['email']. */
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /* PASO 3: Validaciones básicas antes de consultar la BD */
    if ($email === '' || $password === '') {
        $mensaje = 'Completa email y contraseña.';
    } elseif (!validar_email($email)) {
        /* validar_email() comprueba el formato con filter_var() de PHP */
        $mensaje = 'El correo no tiene un formato válido.';
    } else {
        /* Conexión a la base de datos mediante PDO (PHP Data Objects) */
        $pdo = db();
        /* Asegura que la tabla 'usuarios' exista con las columnas necesarias */
        usuarios_asegurar_tabla($pdo);

        /*
         * PASO 4: Rate limiting (limitación de intentos).
         * Protege contra ataques de fuerza bruta: si alguien prueba miles de
         * contraseñas, se bloquea la IP + email durante unos minutos.
         */
        $ip_cliente = login_obtener_ip();
        $segundos_bloqueado = login_intentos_bloqueado($pdo, $ip_cliente, $email);

        if ($segundos_bloqueado > 0) {
            /* Usuario bloqueado: le decimos cuántos minutos debe esperar */
            $minutos = (int) ceil($segundos_bloqueado / 60);
            $mensaje = "Demasiados intentos fallidos. Inténtalo de nuevo en {$minutos} minuto" . ($minutos > 1 ? 's' : '') . '.';
        } else {
            /*
             * PASO 5: Buscar al usuario en la BD.
             * La consulta JOIN une la tabla 'usuarios' con 'inmobiliarias'
             * para obtener también el nombre de la inmobiliaria y si está activa.
             * Se usa una consulta preparada (:email) para prevenir SQL injection.
             */
            $stmt = $pdo->prepare(
                'SELECT u.id, u.nombre, u.email, u.password_hash, u.rol, u.inmobiliaria_id, u.activo, u.password_temporal,
                        u.terminos_aceptados,
                        COALESCE(i.nombre, "Sin asignar") AS inmobiliaria_nombre,
                        COALESCE(i.activa, 1) AS inmobiliaria_activa
                 FROM usuarios u
                 LEFT JOIN inmobiliarias i ON u.inmobiliaria_id = i.id
                 WHERE u.email = :email LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $usuario = $stmt->fetch();

            /*
             * PASO 6: Verificar credenciales.
             * password_verify() compara la contraseña en texto plano con
             * el hash bcrypt almacenado en la BD. NUNCA se compara directamente.
             */
            if ($usuario && password_verify($password, $usuario['password_hash'])) {
                /* Comprobaciones adicionales: usuario activo e inmobiliaria activa */
                if (empty($usuario['activo'])) {
                    $mensaje = 'Tu cuenta está desactivada. Contacta con tu administrador.';
                } elseif (empty($usuario['inmobiliaria_activa']) && $usuario['rol'] !== 'superadmin') {
                    /* El superadmin no pertenece a ninguna inmobiliaria, así que no se comprueba */
                    $mensaje = 'Tu inmobiliaria está desactivada. Contacta con el administrador.';
                } else {
                    /*
                     * PASO 7: Login exitoso.
                     * Limpiamos el registro de intentos fallidos y purgamos los antiguos.
                     */
                    login_intentos_limpiar($pdo, $ip_cliente, $email);
                    login_intentos_purgar($pdo);

                    /*
                     * session_regenerate_id(true): genera un nuevo ID de sesión
                     * y elimina el anterior. Previene ataques de "session fixation"
                     * (donde un atacante fija un ID de sesión antes del login).
                     */
                    session_regenerate_id(true);

                    /*
                     * Guardamos los datos del usuario en la sesión PHP.
                     * $_SESSION persiste entre peticiones hasta que se cierre la sesión.
                     * Estos datos se usan en todo el CRM para:
                     * - Mostrar el nombre del usuario.
                     * - Verificar permisos según el rol (superadmin, jefe, agente...).
                     * - Filtrar datos por inmobiliaria (aislamiento multi-tenant).
                     */
                    $_SESSION['usuario'] = [
                        'id' => $usuario['id'],
                        'nombre' => $usuario['nombre'],
                        'email' => $usuario['email'],
                        'rol' => $usuario['rol'],
                        'inmobiliaria_id' => (int) $usuario['inmobiliaria_id'],
                        'inmobiliaria_nombre' => $usuario['inmobiliaria_nombre'],
                        'password_temporal' => (bool) $usuario['password_temporal'],
                        'terminos_aceptados' => $usuario['terminos_aceptados'] ?? null,
                    ];
                    /* Registramos la actividad de login en el historial */
                    actividad_registrar($pdo, 'login', 'usuario', $usuario['id'], 'Inicio de sesión: ' . $usuario['nombre']);

                    /*
                     * PASO 8: Redirección post-login.
                     * El flujo de acceso tiene 3 paradas posibles:
                     * 1. Si la contraseña es temporal → cambiar-password.php (obligatorio)
                     * 2. Si no ha aceptado los términos legales → aceptar-terminos.php
                     * 3. Si todo está OK → index.php (dashboard principal del CRM)
                     */
                    if ($usuario['password_temporal']) {
                        header('Location: cambiar-password.php');
                    } elseif (!usuario_ha_aceptado_terminos()) {
                        header('Location: aceptar-terminos.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }
            } else {
                /*
                 * PASO 9: Credenciales inválidas.
                 * Registramos el intento fallido para el rate limiting.
                 * IMPORTANTE: el mensaje es genérico ("Credenciales inválidas")
                 * y NO dice si el email existe o no, para evitar enumeración de usuarios.
                 */
                login_intentos_registrar($pdo, $ip_cliente, $email);
                $mensaje = 'Credenciales inválidas.';
            }
        }
    }
}
?>
<!--
  =============================================================================
  SECCIÓN HTML: Interfaz visual del login
  =============================================================================
  Aquí comienza el HTML que el usuario ve en su navegador.
  Todo el código PHP anterior se ejecuta en el servidor ANTES de enviar
  este HTML al navegador. El navegador NUNCA ve el código PHP.
  =============================================================================
-->
<!DOCTYPE html>
<!-- lang="es" indica al navegador y buscadores que la página está en español -->
<html lang="es">
<head>
    <!-- charset UTF-8: permite usar tildes, eñes y emojis sin problemas -->
    <meta charset="UTF-8">
    <!-- viewport: hace que la página se adapte al ancho del dispositivo (responsive) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — TinoProp</title>

    <!-- estilo.css: hoja de estilos principal del CRM (variables CSS compartidas) -->
    <link rel="stylesheet" href="css/estilo.css">
    <!-- login-proyecto-entornos.css: estilos específicos para esta página de login -->
    <link rel="stylesheet" href="css/login-proyecto-entornos.css">
    <!-- Font Awesome: librería de iconos (candado, sobre, etc.) cargada desde CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<!--
  class="login-page": aplica los estilos del fondo degradado
  definidos en login-proyecto-entornos.css (body.login-page { ... })
-->
<body class="login-page">
    <!--
      Estructura de la tarjeta de login:
      .container.login-solo: tarjeta estrecha (480px) porque TinoProp
      no tiene registro público, solo login.
      id="container": usado por posible JavaScript futuro.
    -->
    <div class="container login-solo" id="container">
        <!-- .form-container: contenedor del formulario con transición CSS -->
        <!-- style="width:100%": sobreescribe el 50% por defecto ya que no hay panel de registro -->
        <div class="form-container sign-in-container" style="width:100%;">
            <!--
              El formulario envía los datos por POST a login.php (esta misma página).
              method="POST": envía los datos en el cuerpo de la petición HTTP
              (no en la URL, que sería GET y dejaría la contraseña visible).
            -->
            <form method="POST" action="login.php">
                <!-- Título con emoji de casa: identidad visual de TinoProp -->
                <h1>🏠 TinoProp</h1>
                <p class="login-subtitulo">Gestión Inmobiliaria</p>

                <!--
                  Campo de email:
                  - type="email": el navegador valida automáticamente el formato.
                  - name="email": clave con la que PHP recibe el dato ($_POST['email']).
                  - required: el navegador impide enviar el form si está vacío.
                  - value="...e(...)": rellena el campo con el email que el usuario
                    escribió antes si hubo algún error (para no tener que reescribirlo).
                    e() es una función de escape para prevenir XSS.
                  - autocomplete="email": ayuda al navegador a autocompletar.
                -->
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" required
                           value="<?php echo e($_POST['email'] ?? ''); ?>" autocomplete="email">
                    <!-- Icono de sobre (Font Awesome) posicionado a la izquierda del campo -->
                    <i class="fa-solid fa-envelope"></i>
                </div>

                <!--
                  Campo de contraseña:
                  - type="password": oculta el texto con puntos/asteriscos.
                  - autocomplete="current-password": indica al navegador que es
                    una contraseña existente (no nueva), para el gestor de contraseñas.
                  - NOTA: a diferencia del email, NO se rellena con el valor anterior
                    por seguridad (la contraseña no debe viajar de vuelta al HTML).
                -->
                <div class="input-group">
                    <input type="password" name="password" placeholder="Contraseña" required
                           autocomplete="current-password">
                    <!-- Icono de candado -->
                    <i class="fa-solid fa-lock"></i>
                </div>

                <!--
                  Mensaje de error (solo se muestra si $mensaje no está vacío).
                  e() escapa caracteres HTML para prevenir ataques XSS.
                  Ejemplos de mensajes: "Credenciales inválidas", "Cuenta desactivada"...
                -->
                <?php if ($mensaje): ?>
                    <p class="error-text"><?php echo e($mensaje); ?></p>
                <?php endif; ?>

                <!--
                  csrf_field() genera un campo oculto <input type="hidden"> con
                  un token aleatorio. Al enviar el formulario, el servidor compara
                  este token con el que guardó en la sesión. Si no coinciden,
                  la petición se rechaza (protección CSRF).
                -->
                <?php echo csrf_field(); ?>

                <!-- Botón de envío del formulario -->
                <button type="submit">Iniciar Sesión</button>

                <!--
                  Texto de ayuda: informa al usuario que no existe registro público.
                  Solo un administrador puede crear cuentas en TinoProp.
                -->
                <p class="login-ayuda">Contacta con tu administrador si no tienes cuenta.</p>
            </form>
        </div>
    </div>
</body>
</html>
