<?php
/**
 * ============================================================================
 * CONFIGURACIÓN DEL USUARIO - CRM TINOPROP
 * ============================================================================
 *
 * ¿Qué es este archivo?
 * ---------------------
 * Es la página donde cada usuario puede gestionar su cuenta personal.
 * Tiene tres secciones principales:
 *
 * 1. PERFIL: cambiar nombre y email de la cuenta.
 * 2. SEGURIDAD: cambiar la contraseña (requiere poner la actual primero).
 * 3. PREFERENCIAS: configurar los filtros por defecto del dashboard,
 *    el tema visual (claro/oscuro), la densidad de las tablas y el idioma.
 *
 * También incluye:
 * 4. ETIQUETAS: crear y eliminar etiquetas de colores que se pueden
 *    asignar a clientes y propiedades para organizarlos mejor.
 *
 * ¿Cómo funciona?
 * ---------------
 * Cada sección tiene su propio formulario HTML que envía datos por POST.
 * El campo oculto "accion" indica qué formulario se envió:
 * - 'actualizar_perfil'
 * - 'cambiar_password'
 * - 'guardar_preferencias'
 *
 * Tras procesar, redirige de vuelta a la misma página con un mensaje
 * de éxito o error usando "flash messages" (mensajes temporales que
 * solo se muestran una vez y luego desaparecen).
 *
 * Seguridad:
 * - csrf_verify() protege todos los formularios contra ataques CSRF
 * - Las contraseñas se almacenan con hash bcrypt (nunca en texto plano)
 * - password_verify() compara la contraseña sin desencriptar el hash
 * - Se valida unicidad del email para que no haya duplicados
 *
 * @archivo  secciones/configuracion.php
 * @proyecto TinoProp - CRM Inmobiliario
 * @version  V0.0.31 Pre-Producción
 */
require_once __DIR__ . '/../inc/bootstrap.php';

/*
 * DATOS INICIALES DE LA SESIÓN
 * Obtenemos el ID del usuario logueado desde la sesión de PHP.
 * flash_get() recupera mensajes temporales que se hayan guardado
 * en la petición anterior (ej: "Perfil actualizado correctamente").
 * Estos mensajes se muestran una sola vez y luego se borran automáticamente.
 */
$pdo = db();
$usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
$mensaje_error = flash_get('config_error');
$mensaje_exito = flash_get('config_success');

// Carga datos base del usuario autenticado para rellenar formularios.
$stmt = $pdo->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $usuario_id]);
$usuario = $stmt->fetch();

/*
 * CATÁLOGOS DE VALORES VÁLIDOS
 * Estas listas definen qué opciones puede elegir el usuario en cada desplegable.
 * Si alguien intenta enviar un valor que no está en estas listas
 * (por ejemplo, manipulando el HTML), el sistema lo rechazará.
 * Esto es una técnica de validación por "lista blanca" (whitelist).
 */
$equipos_validos = ['Todos', 'Vendedor', 'Comprador'];
$periodos_validos = ['Mes actual', 'Ultimos 90 dias', 'Ano'];
$operaciones_validas = ['Venta y Alquiler', 'Venta', 'Alquiler'];
$temas_validos = ['Sistema', 'Claro', 'Oscuro'];
$densidades_validas = ['Media', 'Comoda', 'Compacta'];
$idiomas_validos = ['es', 'en'];

/*
 * MAPAS DE TRADUCCIÓN INGLÉS → ESPAÑOL
 * Si el usuario tiene la interfaz en inglés, los formularios
 * envían valores en inglés ("All", "Dark", etc.).
 * Estos mapas convierten esos valores a sus equivalentes en español,
 * que es como se almacenan internamente en la base de datos.
 */
$map_equipo_en_es = [
    'All' => 'Todos',
    'Seller' => 'Vendedor',
    'Buyer' => 'Comprador',
];

$map_operacion_en_es = [
    'Sale and Rent' => 'Venta y Alquiler',
    'Sale y Rent' => 'Venta y Alquiler',
    'Sale' => 'Venta',
    'Rent' => 'Alquiler',
];

$map_periodo_en_es = [
    'Current month' => 'Mes actual',
    'Last 90 days' => 'Ultimos 90 dias',
    'Year' => 'Ano',
];

$map_tema_en_es = [
    'System' => 'Sistema',
    'Light' => 'Claro',
    'Dark' => 'Oscuro',
];

$map_densidad_en_es = [
    'Medium' => 'Media',
    'Comfortable' => 'Comoda',
    'Compact' => 'Compacta',
];

$map_idioma_label = [
    'English' => 'en',
    'Ingles' => 'en',
    'Inglés' => 'en',
    'Spanish' => 'es',
    'Español' => 'es',
    'Espanol' => 'es',
];

/*
 * ─────────────────────────────────────────────────────────────
 * CONTROLADOR POST PRINCIPAL
 * ─────────────────────────────────────────────────────────────
 * Este bloque se ejecuta SOLO cuando el usuario envía un formulario
 * (método POST). Según el campo 'accion', procesamos la acción
 * correspondiente.
 *
 * Patrón POST-Redirect-GET (PRG):
 * Después de procesar el formulario, SIEMPRE hacemos un redirect
 * con header('Location: ...'). Esto evita que si el usuario
 * refresca la página, se reenvíe el formulario accidentalmente.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(); // Protección contra CSRF: verifica que el token del formulario coincide con el de la sesión
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_perfil') {
        /*
         * ACCIÓN: ACTUALIZAR PERFIL
         * 1. Limpiamos espacios en blanco con trim()
         * 2. Validamos que el nombre no esté vacío y el email sea válido
         * 3. Comprobamos que no exista otro usuario con ese email
         * 4. Si todo OK, actualizamos en la base de datos y en la sesión
         */
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $errores = [];

        if (!validar_requerido($nombre)) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (!validar_email($email)) {
            $errores[] = 'El email no es valido.';
        }

        if (empty($errores)) {
            // Verifica unicidad de email para evitar colisiones con otros usuarios.
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id != :id LIMIT 1');
            $stmt->execute([
                'email' => $email,
                'id' => $usuario_id,
            ]);
            if ($stmt->fetch()) {
                $errores[] = 'El email ya esta en uso por otro usuario.';
            }
        }

        if (!empty($errores)) {
            flash_set('config_error', implode(' ', $errores));
            header('Location: index.php?seccion=configuracion#perfil');
            exit;
        }

        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = :nombre, email = :email WHERE id = :id');
        $stmt->execute([
            'nombre' => $nombre,
            'email' => $email,
            'id' => $usuario_id,
        ]);

        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['email'] = $email;

        flash_set('config_success', 'Perfil actualizado correctamente.');
        header('Location: index.php?seccion=configuracion#perfil');
        exit;
    }

    if ($accion === 'cambiar_password') {
        /*
         * ACCIÓN: CAMBIAR CONTRASEÑA
         * Es la acción más crítica de seguridad. Requiere:
         * 1. La contraseña actual (para verificar que realmente es el dueño)
         * 2. La nueva contraseña (que cumpla requisitos de seguridad)
         * 3. Repetir la nueva contraseña (para evitar errores de escritura)
         *
         * password_verify() compara la contraseña introducida con el hash
         * almacenado SIN necesidad de desencriptar (es irreversible).
         * password_hash() genera un nuevo hash bcrypt para la nueva contraseña.
         */
        $actual = $_POST['password_actual'] ?? '';
        $nueva = $_POST['password_nueva'] ?? '';
        $repetir = $_POST['password_repetir'] ?? '';
        $errores = [];

        if (!validar_requerido($actual) || !validar_requerido($nueva) || !validar_requerido($repetir)) {
            $errores[] = 'Completa todos los campos de password.';
        }
        if ($nueva !== $repetir) {
            $errores[] = 'La nueva password y la repeticion no coinciden.';
        }
        if (!validar_password_segura($nueva)) {
            $errores[] = 'La password debe tener al menos 8 caracteres, una mayuscula y un simbolo.';
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $usuario_id]);
        $hash = (string) $stmt->fetchColumn();

        if (!$hash || !password_verify($actual, $hash)) {
            $errores[] = 'La password actual no es correcta.';
        }

        if (!empty($errores)) {
            flash_set('config_error', implode(' ', $errores));
            header('Location: index.php?seccion=configuracion#seguridad');
            exit;
        }

        $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            'hash' => $nuevo_hash,
            'id' => $usuario_id,
        ]);

        flash_set('config_success', 'Password actualizada correctamente.');
        header('Location: index.php?seccion=configuracion#seguridad');
        exit;
    }

    if ($accion === 'guardar_preferencias') {
        /*
         * ACCIÓN: GUARDAR PREFERENCIAS
         * Guarda las configuraciones personales del usuario:
         * - Filtros del dashboard (equipo, periodo, operación)
         * - Tema visual (claro, oscuro, sistema)
         * - Densidad de tablas (cómoda, media, compacta)
         * - Idioma (español o inglés)
         *
         * Si el usuario pulsa "Restablecer valores por defecto",
         * se borran todas las preferencias guardadas y el sistema
         * volverá a usar los valores predeterminados.
         */
        $reset = isset($_POST['reset_preferencias']);

        if ($reset) {
            // Limpia preferencias persistidas para volver a valores por defecto.
            preferencias_usuario_delete($pdo, 'dashboard.filtro.equipo');
            preferencias_usuario_delete($pdo, 'dashboard.filtro.periodo');
            preferencias_usuario_delete($pdo, 'dashboard.filtro.operacion');
            preferencias_usuario_delete($pdo, 'ui.tema');
            preferencias_usuario_delete($pdo, 'ui.densidad');
            preferencias_usuario_delete($pdo, 'ui.idioma');
            flash_set('config_success', 'Preferencias restablecidas a los valores por defecto.');
            header('Location: index.php?seccion=configuracion#preferencias');
            exit;
        }

        $pref_equipo = $_POST['pref_equipo'] ?? 'Todos';
        $pref_periodo = $_POST['pref_periodo'] ?? 'Mes actual';
        $pref_operacion = $_POST['pref_operacion'] ?? 'Venta y Alquiler';
        $pref_tema = $_POST['pref_tema'] ?? 'Sistema';
        $pref_densidad = $_POST['pref_densidad'] ?? 'Media';
        $pref_idioma = $_POST['pref_idioma'] ?? 'es';

        // Normaliza valores recibidos en inglés hacia etiquetas internas en español.
        if (isset($map_equipo_en_es[$pref_equipo])) {
            $pref_equipo = $map_equipo_en_es[$pref_equipo];
        }
        if (isset($map_operacion_en_es[$pref_operacion])) {
            $pref_operacion = $map_operacion_en_es[$pref_operacion];
        }
        if (isset($map_periodo_en_es[$pref_periodo])) {
            $pref_periodo = $map_periodo_en_es[$pref_periodo];
        }
        if (isset($map_tema_en_es[$pref_tema])) {
            $pref_tema = $map_tema_en_es[$pref_tema];
        }
        if (isset($map_densidad_en_es[$pref_densidad])) {
            $pref_densidad = $map_densidad_en_es[$pref_densidad];
        }
        if (isset($map_idioma_label[$pref_idioma])) {
            $pref_idioma = $map_idioma_label[$pref_idioma];
        }

        // Valida que todo pertenezca a catálogos permitidos.
        $errores = [];

        if (!in_array($pref_equipo, $equipos_validos, true)) {
            $errores[] = 'Selecciona un equipo valido para el dashboard.';
        }
        if (!in_array($pref_periodo, $periodos_validos, true)) {
            $errores[] = 'Selecciona un periodo valido.';
        }
        if (!in_array($pref_operacion, $operaciones_validas, true)) {
            $errores[] = 'Selecciona una operacion valida.';
        }
        if (!in_array($pref_tema, $temas_validos, true)) {
            $errores[] = 'Selecciona un tema valido (claro/oscuro/sistema).';
        }
        if (!in_array($pref_densidad, $densidades_validas, true)) {
            $errores[] = 'Selecciona una densidad valida.';
        }
        if (!in_array($pref_idioma, $idiomas_validos, true)) {
            $errores[] = 'Selecciona un idioma valido.';
        }

        if (!empty($errores)) {
            flash_set('config_error', implode(' ', $errores));
            header('Location: index.php?seccion=configuracion#preferencias');
            exit;
        }

        preferencias_usuario_set($pdo, 'dashboard.filtro.equipo', $pref_equipo);
        preferencias_usuario_set($pdo, 'dashboard.filtro.periodo', $pref_periodo);
        preferencias_usuario_set($pdo, 'dashboard.filtro.operacion', $pref_operacion);
        preferencias_usuario_set($pdo, 'ui.tema', $pref_tema);
        preferencias_usuario_set($pdo, 'ui.densidad', $pref_densidad);
        preferencias_usuario_set($pdo, 'ui.idioma', $pref_idioma);

        // Aplica idioma inmediatamente en sesión para la respuesta actual.
        idioma_establecer($pref_idioma);

        flash_set('config_success', 'Preferencias guardadas y listas para aplicarse en el dashboard.');
        header('Location: index.php?seccion=configuracion#preferencias');
        exit;
    }
}

/*
 * ─────────────────────────────────────────────────────────────
 * CARGA DE PREFERENCIAS PARA EL RENDERIZADO HTML
 * ─────────────────────────────────────────────────────────────
 * A partir de aquí el código ya NO procesa formularios.
 * Esta sección carga las preferencias actuales del usuario
 * desde la base de datos para mostrarlas preseleccionadas
 * en los desplegables (<select>) del formulario HTML.
 *
 * Cada preferencia se carga con un fallback (valor por defecto)
 * usando el operador ?? de PHP ("nullish coalescing").
 */
$pref_equipo = preferencias_usuario_get($pdo, 'dashboard.filtro.equipo') ?? 'Todos';
if (!in_array($pref_equipo, $equipos_validos, true)) {
    $pref_equipo = 'Todos';
}
$pref_periodo = preferencias_usuario_get($pdo, 'dashboard.filtro.periodo') ?? 'Mes actual';
if (!in_array($pref_periodo, $periodos_validos, true)) {
    $pref_periodo = 'Mes actual';
}
$pref_operacion = preferencias_usuario_get($pdo, 'dashboard.filtro.operacion') ?? 'Venta y Alquiler';
if (!in_array($pref_operacion, $operaciones_validas, true)) {
    $pref_operacion = 'Venta y Alquiler';
}
$pref_tema = preferencias_usuario_get($pdo, 'ui.tema') ?? 'Sistema';
if (!in_array($pref_tema, $temas_validos, true)) {
    $pref_tema = 'Sistema';
}
$pref_densidad = preferencias_usuario_get($pdo, 'ui.densidad') ?? 'Media';
if (!in_array($pref_densidad, $densidades_validas, true)) {
    $pref_densidad = 'Media';
}
$pref_idioma = preferencias_usuario_get($pdo, 'ui.idioma') ?? 'es';
if (!in_array($pref_idioma, $idiomas_validos, true)) {
    $pref_idioma = 'es';
}
?>

<div class="encabezado_seccion config_intro">
    <p class="config_kicker">Personaliza la experiencia</p>
    <p>Actualiza tu perfil, refuerza la seguridad y define los valores iniciales del dashboard.</p>
</div>

<?php if ($mensaje_error): ?>
    <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
<?php endif; ?>
<?php if ($mensaje_exito): ?>
    <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
<?php endif; ?>

<?php if (!$usuario): ?>
    <div class="alerta_error">No se encontro el usuario de la sesion. Cierra sesion y vuelve a entrar.</div>
    <?php return; ?>
<?php endif; ?>

<div class="config_grid">
    <section id="perfil" class="form_panel visible config_panel">
        <h3>Perfil</h3>
        <p class="config_hint">Estos datos identifican tu cuenta y se usan en avisos y actividad.</p>
        <ul class="config_meta">
            <li>Usuario: <?php echo e($usuario['nombre']); ?> (Rol: <?php echo e($usuario['rol']); ?>)</li>
            <li>Email acceso: <?php echo e($usuario['email']); ?></li>
        </ul>
        <form method="POST" class="form_grid config_form_grid-2">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="actualizar_perfil">
            <div class="campo_input">
                <label for="nombre">Nombre visible</label>
                <input id="nombre" name="nombre" type="text" value="<?php echo e($usuario['nombre']); ?>" required>
            </div>
            <div class="campo_input">
                <label for="email">Email de acceso</label>
                <input id="email" name="email" type="email" value="<?php echo e($usuario['email']); ?>" required>
            </div>
            <div class="config_actions">
                <button class="btn_guardar" type="submit">Guardar perfil</button>
            </div>
        </form>
    </section>

    <section id="seguridad" class="form_panel visible config_panel">
        <h3>Seguridad</h3>
        <p class="config_hint">Cambia tu contraseña para mantener la cuenta protegida.</p>
        <form method="POST" class="form_grid config_form_grid-1">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="campo_input">
                <label for="password_actual">Contraseña actual</label>
                <input id="password_actual" name="password_actual" type="password" required>
            </div>
            <div class="campo_input">
                <label for="password_nueva">Contraseña nueva (min. 8)</label>
                <input id="password_nueva" name="password_nueva" type="password" required>
            </div>
            <div class="campo_input">
                <label for="password_repetir">Repetir contraseña nueva</label>
                <input id="password_repetir" name="password_repetir" type="password" required>
            </div>
            <div class="config_actions">
                <button class="btn_guardar" type="submit">Actualizar contraseña</button>
            </div>
        </form>
    </section>

    <section id="preferencias" class="form_panel visible config_panel">
        <h3>Preferencias del dashboard</h3>
        <p class="config_hint">Define con que filtros se abre el dashboard cuando ingresas sin seleccionar opciones.</p>
        <form method="POST" class="form_grid config_form_grid-2">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="guardar_preferencias">
            <div class="campo_input">
                <label for="pref_equipo">Equipo por defecto</label>
                <select id="pref_equipo" name="pref_equipo">
                    <?php foreach ($equipos_validos as $equipo): ?>
                        <option value="<?php echo e($equipo); ?>" <?php echo $equipo === $pref_equipo ? 'selected' : ''; ?>><?php echo e($equipo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo_input">
                <label for="pref_operacion">Operacion por defecto</label>
                <select id="pref_operacion" name="pref_operacion">
                    <?php foreach ($operaciones_validas as $operacion): ?>
                        <option value="<?php echo e($operacion); ?>" <?php echo $operacion === $pref_operacion ? 'selected' : ''; ?>><?php echo e($operacion); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo_input">
                <label for="pref_periodo">Periodo de referencia</label>
                <select id="pref_periodo" name="pref_periodo">
                    <?php foreach ($periodos_validos as $periodo): ?>
                        <option value="<?php echo e($periodo); ?>" <?php echo $periodo === $pref_periodo ? 'selected' : ''; ?>><?php echo e($periodo); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo_input">
                <label for="pref_tema">Tema de interfaz</label>
                <select id="pref_tema" name="pref_tema">
                    <?php foreach ($temas_validos as $tema): ?>
                        <option value="<?php echo e($tema); ?>" <?php echo $tema === $pref_tema ? 'selected' : ''; ?>><?php echo e($tema); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo_input">
                <label for="pref_densidad">Densidad de tablas</label>
                <select id="pref_densidad" name="pref_densidad">
                    <?php foreach ($densidades_validas as $densidad): ?>
                        <option value="<?php echo e($densidad); ?>" <?php echo $densidad === $pref_densidad ? 'selected' : ''; ?>><?php echo e($densidad); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo_input">
                <label for="pref_idioma">Idioma de la interfaz</label>
                <select id="pref_idioma" name="pref_idioma">
                    <option value="es" <?php echo $pref_idioma === 'es' ? 'selected' : ''; ?>>Español</option>
                    <option value="en" <?php echo $pref_idioma === 'en' ? 'selected' : ''; ?>>English</option>
                </select>
            </div>
            <div class="config_actions">
                <button class="btn_guardar" type="submit">Guardar preferencias</button>
                <button class="btn_secundario" type="submit" name="reset_preferencias" value="1">Restablecer valores por defecto</button>
            </div>
        </form>
        <p class="config_hint">Estos valores se aplican al abrir el dashboard si no hay filtros en la URL.</p>
    </section>

    <!-- GESTIÓN DE ETIQUETAS -->
    <!-- Las etiquetas son "tags" de colores que se pueden crear aquí
         y luego asignar a clientes y propiedades desde sus fichas.
         Ejemplo: "Urgente" (rojo), "VIP" (dorado), "Inversor" (verde).
         Cada inmobiliaria tiene sus propias etiquetas (aislamiento multi-tenant).
         
         Flujo:
         1. El usuario escribe un nombre y elige un color
         2. Se crea la etiqueta en la base de datos
         3. Desde las fichas de cliente/propiedad se pueden asignar
         4. Se pueden eliminar desde aquí (pide confirmación JavaScript)
    -->
    <section class="config_seccion">
        <h3>🏷️ Gestión de etiquetas</h3>
        <p class="config_hint">Las etiquetas se pueden asignar a clientes y propiedades desde sus fichas.</p>
        <?php
        etiquetas_asegurar_tabla($pdo);
        $todas_etiquetas = etiquetas_listar($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_etiqueta_config'])) {
            $tn = trim($_POST['tag_nombre'] ?? '');
            $tc = $_POST['tag_color'] ?? '#3b82f6';
            if ($tn) { etiqueta_crear($pdo, $tn, $tc); header('Location: index.php?seccion=configuracion'); exit; }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_etiqueta_config'])) {
            $tid = (int) ($_POST['tag_id'] ?? 0);
            if ($tid > 0) { etiqueta_eliminar($pdo, $tid); header('Location: index.php?seccion=configuracion'); exit; }
        }
        ?>
        <div class="tags_manager">
            <div class="tags_lista_config">
                <?php if (empty($todas_etiquetas)): ?>
                    <p class="sin_datos_mini">No hay etiquetas creadas.</p>
                <?php else: ?>
                    <?php foreach ($todas_etiquetas as $tag): ?>
                    <div class="tag_config_item">
                        <span class="tag_badge" style="background:<?php echo e($tag['color']); ?>"><?php echo e($tag['nombre']); ?></span>
                        <form method="POST" style="display:inline" data-confirm="¿Eliminar esta etiqueta?">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                            <button type="submit" name="eliminar_etiqueta_config" class="btn_icono_sm" title="Eliminar">🗑️</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form method="POST" class="tag_crear_form tag_crear_form--config">
                <?php echo csrf_field(); ?>
                <input type="text" name="tag_nombre" placeholder="Nombre etiqueta..." required class="tag_input">
                <input type="color" name="tag_color" value="#3b82f6" class="tag_color_pick">
                <button type="submit" name="crear_etiqueta_config" class="btn_guardar btn_chico">Crear etiqueta</button>
            </form>
        </div>
    </section>
</div>
