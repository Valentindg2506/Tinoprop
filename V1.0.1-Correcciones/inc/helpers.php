<?php
/*
 * Archivo: inc/helpers.php — V0.0.31 Pre-Producción
 * Rol: utilidades transversales del backend.
 * Grupos de funciones:
 * 0) Sistema multi-tenant: roles, permisos, inmobiliarias.
 * 1) Utilidades de salida/formato (escape HTML, precio, clases de estado).
 * 2) Mensajería flash y validaciones de formulario.
 * 3) Preferencias por usuario (persistidas en BD).
 * 4) CRUD de recordatorios.
 * 5) Gestión de imágenes de propiedades.
 * 6) Creación/ajuste de la tabla de propiedades scrapeadas.
 * 7) CSRF tokens.
 * 8) Historial de actividad (log).
 * 9) Notificaciones inteligentes.
 * 10) Caché de consultas.
 * 11) Exportación CSV.
 * 12) Breadcrumbs.
 * 13) Sistema legal y aceptación de términos.
 * 14) Rate limiting de login.
 */

/** Versión vigente de los Términos y Condiciones. Cuando cambie, se requerirá nueva aceptación. */
define('TERMINOS_VERSION', '2026-03-02');

/** Máximo de intentos de login fallidos antes de bloqueo temporal. */
define('LOGIN_MAX_INTENTOS', 5);

/** Ventana de tiempo en segundos para contar intentos fallidos (15 min). */
define('LOGIN_VENTANA_SEGUNDOS', 900);

/** Segundos de bloqueo tras superar el máximo de intentos (15 min). */
define('LOGIN_BLOQUEO_SEGUNDOS', 900);

/* =============================================
   0. SISTEMA MULTI-TENANT: ROLES Y PERMISOS
   ============================================= */

/*
 * ¿Qué es "multi-tenant"?
 * En esta aplicación, varias inmobiliarias comparten el mismo sistema.
 * Cada inmobiliaria es un "tenant" (inquilino). El sistema necesita saber
 * a qué inmobiliaria pertenece cada usuario y qué puede hacer dentro de ella.
 *
 * ¿Qué son los "roles"?
 * Un rol es como un puesto de trabajo dentro de la inmobiliaria.
 * Cada usuario tiene asignado un rol que determina qué pantallas puede ver
 * y qué acciones puede realizar. Por ejemplo, un agente solo ve sus propios
 * datos, mientras que un jefe puede ver todo lo de su inmobiliaria.
 */

/*
 * Esta función devuelve todos los roles que existen en la aplicación.
 * Cada rol tiene un nombre visible (label) y un nivel numérico que indica
 * cuánto poder tiene dentro del sistema. A mayor número, más permisos.
 *
 * La jerarquía funciona así (de menor a mayor poder):
 *   - Nivel 1: "Agente Comprador" o "Agente Vendedor" → solo ven su área específica.
 *   - Nivel 2: "Agente" → ve tanto la parte de comprador como la de vendedor.
 *   - Nivel 3: "Marketing" → acceso a herramientas de difusión y publicidad.
 *   - Nivel 4: "Supervisor" → puede supervisar el trabajo de los agentes.
 *   - Nivel 5: "Jefe" → control total de su inmobiliaria.
 *   - Nivel 99: "Super Admin" → control absoluto de TODO el sistema (todas las inmobiliarias).
 */

/**
 * Roles válidos del sistema y su jerarquía (mayor número = más poder).
 */
function roles_disponibles(): array
{
    return [
        'agente_comprador' => ['label' => 'Agente Comprador', 'nivel' => 1],
        'agente_vendedor'  => ['label' => 'Agente Vendedor',  'nivel' => 1],
        'agente'           => ['label' => 'Agente',           'nivel' => 2],
        'marketing'        => ['label' => 'Marketing',        'nivel' => 3],
        'supervisor'       => ['label' => 'Supervisor',       'nivel' => 4],
        'jefe'             => ['label' => 'Jefe',             'nivel' => 5],
        'superadmin'       => ['label' => 'Super Admin',      'nivel' => 99],
    ];
}

/*
 * ¿Qué es la "sesión"?
 * Cuando un usuario inicia sesión (login), el sistema guarda sus datos
 * en una variable especial llamada $_SESSION. Es como una "memoria temporal"
 * que el servidor mantiene mientras el usuario navega por la web.
 * Gracias a la sesión, el sistema sabe quién eres sin pedirte la contraseña
 * en cada clic.
 *
 * Esta función consulta esa memoria temporal para saber qué rol tiene
 * el usuario que está usando la aplicación en este momento.
 * Si por alguna razón no encuentra el rol (por ejemplo, datos corruptos),
 * asume que es un "agente" como medida de seguridad (el rol con menos poder).
 */

/** Devuelve el rol actual del usuario en sesión. */
function usuario_rol(): string
{
    return $_SESSION['usuario']['rol'] ?? 'agente';
}

/*
 * Cada inmobiliaria tiene un número identificador único (ID) en la base de datos.
 * Esta función obtiene ese número para el usuario actual.
 * Se usa constantemente para filtrar datos: "muéstrame solo las propiedades
 * de MI inmobiliaria" en lugar de las de todas.
 * Si no tiene inmobiliaria asignada, devuelve 0 (sin inmobiliaria).
 */

/** Devuelve el ID de la inmobiliaria del usuario en sesión. */
function usuario_inmobiliaria_id(): int
{
    return (int) ($_SESSION['usuario']['inmobiliaria_id'] ?? 0);
}

/*
 * Similar a la anterior, pero en vez del número identificador, devuelve
 * el nombre legible de la inmobiliaria (por ejemplo: "Inmobiliaria García").
 * Se usa para mostrarlo en pantalla, por ejemplo en la cabecera del dashboard.
 */

/** Devuelve el nombre de la inmobiliaria del usuario en sesión. */
function usuario_inmobiliaria_nombre(): string
{
    return $_SESSION['usuario']['inmobiliaria_nombre'] ?? 'Sin asignar';
}

/*
 * El "superadmin" es el administrador supremo de toda la plataforma.
 * No pertenece a ninguna inmobiliaria en concreto: puede ver y gestionar
 * TODAS las inmobiliarias, todos los usuarios y todos los datos.
 * Esta función es un atajo rápido para comprobar si el usuario actual
 * tiene ese poder absoluto. Se usa mucho para mostrar u ocultar
 * opciones de administración global (como crear inmobiliarias nuevas).
 */

/** ¿El usuario en sesión es superadmin? */
function es_superadmin(): bool
{
    return usuario_rol() === 'superadmin';
}

/*
 * ¿Qué es la "jerarquía de roles"?
 * Imagina una escalera de poder. Cada peldaño es un nivel numérico.
 * Un usuario con nivel 5 (jefe) automáticamente puede hacer todo lo que
 * hace alguien de nivel 4 (supervisor), nivel 3 (marketing), etc.
 *
 * Esta función compara: "¿Mi nivel es igual o superior al nivel mínimo
 * que se necesita para esta acción?" Si la respuesta es sí, se permite.
 *
 * Ejemplo práctico:
 *   - Para borrar una propiedad se requiere nivel "supervisor" (nivel 4).
 *   - Un "jefe" (nivel 5) SÍ puede, porque 5 >= 4.
 *   - Un "agente" (nivel 2) NO puede, porque 2 < 4.
 */

/** ¿El usuario tiene nivel >= al rol indicado? */
function tiene_nivel(string $rol_minimo): bool
{
    $roles = roles_disponibles();
    $nivel_actual = $roles[usuario_rol()]['nivel'] ?? 0;
    $nivel_requerido = $roles[$rol_minimo]['nivel'] ?? 0;
    return $nivel_actual >= $nivel_requerido;
}

/*
 * La aplicación tiene dos grandes áreas de trabajo:
 *   1) VENDEDOR: gestionar propiedades en venta/alquiler, captar viviendas,
 *      contactar propietarios, publicar anuncios, etc.
 *   2) COMPRADOR: gestionar compradores/inquilinos que buscan vivienda,
 *      organizar visitas, hacer seguimiento del proceso de compra, etc.
 *
 * Algunos agentes solo trabajan en un área. Un "agente_comprador" solo
 * busca compradores; un "agente_vendedor" solo capta propiedades.
 * Los roles superiores (agente completo, marketing, supervisor, jefe, superadmin)
 * pueden ver AMBAS áreas.
 *
 * Esta función comprueba si el usuario actual tiene permiso para acceder
 * a las secciones del lado VENDEDOR (propiedades, prospectos vendedor, etc.).
 * Si el rol del usuario está en la lista permitida, devuelve verdadero (true).
 */

/** ¿El usuario puede ver secciones de vendedor? */
function puede_ver_vendedor(): bool
{
    $rol = usuario_rol();
    return in_array($rol, ['agente_vendedor', 'agente', 'marketing', 'supervisor', 'jefe', 'superadmin'], true);
}

/*
 * Igual que la función anterior, pero para el lado COMPRADOR.
 * Controla el acceso a secciones como: búsqueda de propiedades para clientes,
 * organización de visitas, seguimiento del proceso de compra, matching
 * (encontrar la propiedad ideal para cada comprador), etc.
 *
 * Nota: el "agente_vendedor" NO aparece en esta lista, por lo que
 * no podrá acceder a las pantallas de gestión de compradores.
 * Esto permite especializar a los agentes en un área concreta.
 */

/** ¿El usuario puede ver secciones de comprador? */
function puede_ver_comprador(): bool
{
    $rol = usuario_rol();
    return in_array($rol, ['agente_comprador', 'agente', 'marketing', 'supervisor', 'jefe', 'superadmin'], true);
}

/*
 * ¿Puede el usuario acceder a las secciones de SISTEMA?
 * Las secciones de sistema son aquellas que afectan al funcionamiento
 * general de la aplicación: importar datos desde archivos CSV,
 * ver el registro de actividad de todos los usuarios, etc.
 *
 * Solo pueden acceder usuarios con nivel de "supervisor" o superior
 * (es decir, supervisores, jefes y superadmins). Los agentes normales
 * y el marketing no pueden acceder a estas herramientas porque podrían
 * alterar datos de forma masiva o ver información confidencial.
 *
 * Internamente usa tiene_nivel('supervisor'), que compara el nivel
 * numérico del usuario actual con el nivel mínimo requerido.
 */
/** ¿El usuario puede acceder a secciones de sistema (importar, configuración, etc.)? */
function puede_ver_sistema(): bool
{
    return tiene_nivel('supervisor');
}

/*
 * ¿Puede el usuario gestionar (crear, editar, desactivar) a otros usuarios
 * dentro de su inmobiliaria?
 *
 * Esta es una acción delicada: dar de alta agentes, cambiar roles,
 * desactivar cuentas… Solo los "jefes" (nivel 5) y el "superadmin"
 * tienen este poder. Ni siquiera los supervisores pueden hacerlo.
 *
 * Ejemplo: El jefe de "Inmobiliaria García" puede crear un nuevo
 * agente para su oficina de Valencia, pero un supervisor no puede.
 */
/** ¿El usuario puede gestionar usuarios de su inmobiliaria? */
function puede_gestionar_usuarios(): bool
{
    return tiene_nivel('jefe');
}

/*
 * =====================================================================
 *  MAPA MAESTRO DE PERMISOS DE SECCIÓN
 * =====================================================================
 *
 * Esta es la función "guardián" de toda la aplicación. Cada vez que
 * un usuario intenta acceder a una pantalla (sección), se consulta
 * esta función para saber si tiene permiso.
 *
 * ¿Cómo funciona? La función recibe el nombre de la sección (por ejemplo
 * "propiedades-vendedor") y sigue estas reglas en orden:
 *
 *   1. SUPERADMIN: Es un caso especial. El superadmin administra la
 *      PLATAFORMA completa, no una inmobiliaria concreta. Por eso,
 *      solo puede entrar a secciones administrativas globales
 *      (admin-dashboard, admin-inmobiliarias, admin-usuarios, peticiones,
 *      configuración y legal). NO puede ver las pantallas de trabajo
 *      diario como propiedades o clientes porque no pertenece a
 *      ninguna inmobiliaria.
 *
 *   2. SECCIONES UNIVERSALES: Algunas pantallas están abiertas para
 *      TODOS los usuarios sin importar su rol: el dashboard principal,
 *      los recordatorios, el matching, la documentación, la configuración
 *      personal, la búsqueda avanzada, el post-venta y las páginas legales.
 *
 *   3. SECCIONES DE VENDEDOR: Las pantallas relacionadas con captación
 *      de propiedades y gestión de propietarios (clientes-vendedor,
 *      prospectos-vendedor, propiedades-vendedor, etc.) solo son
 *      visibles para roles que incluyan la función de vendedor.
 *
 *   4. SECCIONES DE COMPRADOR: Las pantallas relacionadas con compradores
 *      e inquilinos (clientes-comprador, propiedades-comprador, visitas,
 *      etc.) solo son visibles para roles con función de comprador.
 *
 *   5. SECCIONES DE SISTEMA: Importar datos CSV y ver la actividad
 *      requieren ser supervisor o superior.
 *
 *   6. GESTIÓN DE USUARIOS: Crear/editar/eliminar usuarios dentro de
 *      la inmobiliaria requiere ser jefe.
 *
 *   7. SECCIONES DE SUPERADMIN: admin-inmobiliarias y admin-dashboard
 *      son exclusivas del superadmin.
 *
 *   8. PETICIONES: Solo jefes y superadmin pueden gestionar peticiones.
 *
 *   9. VER DETALLE: Ver la ficha de un cliente o propiedad individual
 *      está permitido a todos (el filtrado de qué datos puede ver
 *      se hace en otro nivel, con sql_iid y sql_uid).
 *
 *  Si ninguna regla coincide, se deniega el acceso (devuelve false).
 * =====================================================================
 */
/**
 * Mapa de secciones → permisos. Devuelve true si el usuario puede acceder a la sección.
 */
function puede_acceder_seccion(string $seccion): bool
{
    // SuperAdmin SOLO accede a secciones administrativas
    if (es_superadmin()) {
        $secciones_admin = ['admin-dashboard', 'admin-inmobiliarias', 'admin-usuarios', 'peticiones', 'configuracion', 'legal'];
        return in_array($seccion, $secciones_admin, true);
    }

    // Secciones universales (todos los roles)
    $universales = ['dashboard', 'recordatorios', 'matching', 'documentacion', 'configuracion', 'busqueda-avanzada', 'post-venta', 'legal'];
    if (in_array($seccion, $universales, true)) return true;

    // Secciones de vendedor
    $secciones_vendedor = [
        'clientes-vendedor', 'prospectos-vendedor', 'propiedades-vendedor',
        'alquileres-vendedor', 'proceso-vendedor', 'visitas-vendedor', 'ofertas-vendedor',
    ];
    if (in_array($seccion, $secciones_vendedor, true)) return puede_ver_vendedor();

    // Secciones de comprador
    $secciones_comprador = [
        'clientes-comprador', 'prospectos-comprador', 'propiedades-comprador',
        'alquileres-comprador', 'proceso-comprador', 'visitas-comprador',
    ];
    if (in_array($seccion, $secciones_comprador, true)) return puede_ver_comprador();

    // Secciones de sistema
    $secciones_sistema = ['importar-csv', 'actividad'];
    if (in_array($seccion, $secciones_sistema, true)) return puede_ver_sistema();

    // Gestión de usuarios: requiere nivel jefe
    if ($seccion === 'admin-usuarios') return puede_gestionar_usuarios();

    // SuperAdmin exclusivas
    if (in_array($seccion, ['admin-inmobiliarias', 'admin-dashboard'], true)) return es_superadmin();

    // Peticiones: Jefe y SuperAdmin
    if ($seccion === 'peticiones') return tiene_nivel('jefe');

    // Ver detalle de entidad: depende del contexto
    if (in_array($seccion, ['ver_cliente', 'ver_propiedad'], true)) return true;

    return false;
}

/*
 * =====================================================================
 *  VERIFICACIÓN DE ACCESO CON BLOQUEO
 * =====================================================================
 *
 * Esta función es la "puerta cerrada con llave" de cada sección.
 * Se llama al PRINCIPIO de cada página protegida. Lo que hace:
 *
 *   1. Pregunta a puede_acceder_seccion() si el usuario tiene permiso.
 *   2. Si SÍ tiene permiso → no hace nada, la página continúa cargando.
 *   3. Si NO tiene permiso → DETIENE todo inmediatamente:
 *      a) Envía al navegador un código HTTP 403 ("Prohibido"),
 *         que es la forma estándar de decir "no tienes autorización".
 *      b) Muestra un mensaje visual de "Acceso denegado" con un
 *         botón para volver al Dashboard.
 *      c) Ejecuta exit; que CORTA la ejecución del programa por
 *         completo. Nada de código posterior se ejecutará.
 *         Esto es crucial: sin el exit, el resto de la página
 *         seguiría cargándose y el usuario vería los datos.
 *
 * Ejemplo de uso en una sección:
 *   verificar_acceso('importar-csv');
 *   // Si llego aquí, el usuario SÍ tiene permiso
 *   // ... código de la sección de importación ...
 *
 * ¿Qué pasa si un agente intenta acceder escribiendo la URL?
 *   Si un "agente_comprador" escribe manualmente en la barra de
 *   direcciones ?seccion=importar-csv, esta función le bloqueará
 *   porque su nivel es inferior a "supervisor".
 * =====================================================================
 */
/**
 * Aborta con 403 si el usuario no puede acceder a la sección.
 */
function verificar_acceso(string $seccion): void
{
    if (!puede_acceder_seccion($seccion)) {
        http_response_code(403);
        echo '<div class="error_acceso"><h2>⛔ Acceso denegado</h2><p>No tienes permisos para acceder a esta sección.</p><a href="?seccion=dashboard" class="btn_guardar">Volver al Dashboard</a></div>';
        exit;
    }
}

/*
 * =====================================================================
 *  FILTROS SQL MULTI-TENANT (Aislamiento por inmobiliaria)
 * =====================================================================
 *
 * ¿Qué es "multi-tenant"?
 * La aplicación Tinoprop es usada simultáneamente por MUCHAS inmobiliarias
 * diferentes. Todas comparten la misma base de datos, pero cada una
 * solo debe ver SUS propios datos. A esto se le llama "multi-tenant"
 * (múltiples inquilinos compartiendo un mismo edificio, pero cada uno
 * en su piso, sin poder entrar al piso del vecino).
 *
 * Cuando un usuario de la inmobiliaria "Inmuebles Madrid" busca clientes,
 * estas funciones añaden automáticamente un filtro SQL para que SOLO vea
 * los clientes de su inmobiliaria, no los de otras. Es como si cada
 * inmobiliaria tuviera su propia base de datos separada, aunque en
 * realidad comparten una sola.
 *
 * ¿Cómo funciona en la práctica?
 * Imagina esta consulta SQL sin filtro:
 *   SELECT * FROM clientes WHERE nombre LIKE '%García%'
 *   → Devolvería TODOS los clientes García de TODAS las inmobiliarias.
 *
 * Con el filtro sql_iid() se convierte en:
 *   SELECT * FROM clientes WHERE nombre LIKE '%García%' AND inmobiliaria_id = 5
 *   → Solo devuelve los clientes García de la inmobiliaria nº 5.
 *
 * Caso especial - SuperAdmin:
 *   El superadmin administra la plataforma completa, así que estas
 *   funciones le devuelven un filtro vacío (sin restricción),
 *   permitiéndole ver datos de TODAS las inmobiliarias.
 *
 * Las tres funciones trabajan juntas:
 *   - sql_iid():        genera el texto del filtro SQL (" AND inmobiliaria_id = :iid")
 *   - sql_iid_params(): genera el valor del parámetro (['iid' => 5])
 *   - sql_iid_pair():   devuelve ambos juntos para mayor comodidad
 *
 * El parámetro $alias se usa cuando la consulta involucra varias tablas
 * y hay que especificar a cuál pertenece la columna (por ejemplo: "c.inmobiliaria_id"
 * en vez de solo "inmobiliaria_id", donde "c" es el alias de la tabla clientes).
 * =====================================================================
 */
/* ---- Filtros SQL multi-tenant ---- */

/**
 * Devuelve la condición SQL para filtrar por inmobiliaria_id.
 * SuperAdmin NO recibe filtro (ve todo). Puede prefijarse con alias de tabla.
 *
 * Ejemplo de uso:
 *   $sql = "SELECT * FROM clientes WHERE 1=1" . sql_iid();
 *   // Resultado para un agente de inmobiliaria 5:
 *   //   "SELECT * FROM clientes WHERE 1=1 AND inmobiliaria_id = :iid"
 *   // Resultado para superadmin:
 *   //   "SELECT * FROM clientes WHERE 1=1" (sin filtro adicional)
 */
function sql_iid(string $alias = ''): string
{
    if (es_superadmin()) return '';
    $col = $alias ? "{$alias}.inmobiliaria_id" : 'inmobiliaria_id';
    return " AND {$col} = :iid";
}

/**
 * Devuelve el array de parámetros a mezclar con los existentes.
 * SuperAdmin devuelve array vacío (no se aplica ningún filtro).
 *
 * Ejemplo de uso:
 *   $params = array_merge(['nombre' => '%García%'], sql_iid_params());
 *   // Para agente de inmobiliaria 5: ['nombre' => '%García%', 'iid' => 5]
 *   // Para superadmin:               ['nombre' => '%García%'] (sin iid)
 */
function sql_iid_params(): array
{
    if (es_superadmin()) return [];
    return ['iid' => usuario_inmobiliaria_id()];
}

/**
 * Devuelve ambos (condición + params) como una tupla [string, array].
 * Es un atajo cómodo para no tener que llamar a sql_iid() y sql_iid_params()
 * por separado. Se usa así:
 *
 *   [$filtro_iid, $params_iid] = sql_iid_pair();
 *   $sql = "SELECT * FROM clientes WHERE 1=1" . $filtro_iid;
 *   $stmt->execute(array_merge($otros_params, $params_iid));
 */
function sql_iid_pair(string $alias = ''): array
{
    return [sql_iid($alias), sql_iid_params()];
}

/*
 * =====================================================================
 *  FILTROS SQL POR USUARIO (Visibilidad de datos según rol)
 * =====================================================================
 *
 * Además del filtro por inmobiliaria (sql_iid), existe un segundo
 * nivel de filtrado: el filtro por usuario.
 *
 * ¿Por qué hace falta otro filtro?
 * Dentro de una misma inmobiliaria puede haber 20 agentes. Si todos
 * vieran los clientes de todos, sería un caos. Así que los agentes
 * (niveles bajos: agente_comprador, agente_vendedor, agente) solo
 * ven SUS propios registros: los clientes que ellos captaron, las
 * propiedades que ellos dieron de alta, las visitas que ellos organizaron.
 *
 * Sin embargo, un supervisor o jefe necesita ver el trabajo de TODO
 * su equipo para poder coordinarlo. Por eso, a partir del nivel de
 * "marketing" (nivel 3) el filtro desaparece y se ven todos los
 * registros de la inmobiliaria.
 *
 * Resumen de visibilidad:
 *   - Agente comprador (nivel 1) → solo sus datos personales
 *   - Agente vendedor  (nivel 1) → solo sus datos personales
 *   - Agente completo  (nivel 2) → solo sus datos personales
 *   - Marketing        (nivel 3) → ve todos los datos de la inmobiliaria
 *   - Supervisor       (nivel 4) → ve todos los datos de la inmobiliaria
 *   - Jefe             (nivel 5) → ve todos los datos de la inmobiliaria
 *   - SuperAdmin       (nivel 99)→ ve absolutamente todo
 *
 * Ejemplo práctico combinando ambos filtros:
 *   Consulta base: SELECT * FROM clientes WHERE 1=1
 *   + sql_iid() →  AND inmobiliaria_id = :iid    (solo mi inmobiliaria)
 *   + sql_uid() →  AND usuario_id = :uid          (solo mis clientes)
 *
 *   Un agente de "Inmuebles Madrid" solo ve SUS clientes de ESA inmobiliaria.
 *   Un supervisor de "Inmuebles Madrid" ve TODOS los clientes de la inmobiliaria.
 *   Un superadmin ve TODOS los clientes de TODAS las inmobiliarias.
 *
 * Funciona igual que sql_iid con tres variantes:
 *   - sql_uid():        genera el texto del filtro SQL
 *   - sql_uid_params(): genera el valor del parámetro
 *   - sql_uid_pair():   devuelve ambos juntos
 * =====================================================================
 */
/* ---- Filtros SQL por usuario (visibilidad de datos según rol) ---- */

/**
 * Devuelve condición SQL para filtrar registros por usuario_id.
 * Agentes (nivel < 3) solo ven sus propios datos.
 * Marketing, supervisores, jefe y superadmin ven todos los de la inmobiliaria.
 *
 * Ejemplo:
 *   Para un agente con id=42:
 *     sql_uid() → " AND usuario_id = :uid"
 *   Para un supervisor:
 *     sql_uid() → "" (cadena vacía, sin filtro → ve todo)
 */
function sql_uid(string $alias = ''): string
{
    if (tiene_nivel('marketing')) return '';
    $col = $alias ? "{$alias}.usuario_id" : 'usuario_id';
    return " AND {$col} = :uid";
}

/**
 * Parámetros para sql_uid(). Devuelve array vacío si el rol ve todo.
 *
 * Ejemplo:
 *   Para un agente con id=42:
 *     sql_uid_params() → ['uid' => 42]
 *   Para un supervisor:
 *     sql_uid_params() → [] (vacío, porque no necesita filtro)
 */
function sql_uid_params(): array
{
    if (tiene_nivel('marketing')) return [];
    return ['uid' => (int) ($_SESSION['usuario']['id'] ?? 0)];
}

/**
 * Tupla [condición, params] de sql_uid.
 * Atajo cómodo que combina sql_uid() y sql_uid_params() en una sola llamada.
 *
 *   [$filtro_uid, $params_uid] = sql_uid_pair();
 *   $sql .= $filtro_uid;
 *   $params = array_merge($params, $params_uid);
 */
function sql_uid_pair(string $alias = ''): array
{
    return [sql_uid($alias), sql_uid_params()];
}

/*
 * =====================================================================
 *  CRUD DE INMOBILIARIAS (Crear, Leer, Actualizar, Desactivar)
 * =====================================================================
 *
 * Las siguientes funciones gestionan la tabla "inmobiliarias" en la
 * base de datos. Cada inmobiliaria registrada en la plataforma tiene
 * una fila en esta tabla con sus datos básicos: nombre, CIF, dirección,
 * teléfono, email, logotipo y cuántos usuarios puede tener como máximo.
 * =====================================================================
 */
/* ---- Inmobiliarias CRUD ---- */

/*
 * ¿Qué es "asegurar tabla"?
 * Este patrón se repite en toda la app: antes de usar una tabla,
 * comprueba si existe y si no, la crea automáticamente. Esto hace que
 * la aplicación sea "auto-instalable": no necesitas ejecutar scripts
 * SQL manualmente, la propia app crea las tablas cuando las necesita.
 *
 * ¿Cómo funciona "static $ok"?
 * La palabra clave "static" hace que la variable $ok conserve su valor
 * entre llamadas a la función. La primera vez que se ejecuta, $ok es
 * false, así que ejecuta el CREATE TABLE. Luego pone $ok = true.
 * Si la función se llama otra vez (por ejemplo, al crear y luego
 * listar inmobiliarias en la misma página), $ok ya es true y la
 * función sale inmediatamente sin volver a ejecutar el CREATE TABLE.
 * Usa 'static $ok' para no repetir la comprobación en cada llamada.
 * Esto mejora el rendimiento: la comprobación de la tabla solo se
 * hace UNA vez por cada carga de página.
 *
 * "CREATE TABLE IF NOT EXISTS" es una instrucción SQL que dice:
 * "Crea esta tabla, pero solo si no existe ya". Si la tabla ya existe,
 * no hace nada y no da error.
 *
 * Campos de la tabla inmobiliarias:
 *   - id: Número único auto-generado (1, 2, 3...)
 *   - nombre: Nombre de la inmobiliaria (obligatorio)
 *   - cif: Código fiscal de la empresa (opcional)
 *   - direccion: Dirección postal de la oficina (opcional)
 *   - telefono: Teléfono de contacto (opcional)
 *   - email: Correo electrónico (opcional)
 *   - logo_url: Ruta al logotipo de la inmobiliaria (opcional)
 *   - activa: 1 = activa, 0 = desactivada (se puede "apagar" sin borrar)
 *   - max_usuarios: Máximo de usuarios permitidos (por defecto 10)
 *   - created_at: Fecha/hora de creación (automática)
 *   - updated_at: Fecha/hora de última modificación (automática)
 */
function inmobiliarias_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inmobiliarias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            cif VARCHAR(20) DEFAULT NULL,
            direccion VARCHAR(250) DEFAULT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            email VARCHAR(150) DEFAULT NULL,
            logo_url VARCHAR(300) DEFAULT NULL,
            activa TINYINT(1) NOT NULL DEFAULT 1,
            max_usuarios INT UNSIGNED NOT NULL DEFAULT 10,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_inmob_activa (activa)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

/*
 * Crea una inmobiliaria nueva en la base de datos.
 *
 * Recibe un array $datos con los campos del formulario (nombre, cif,
 * dirección, teléfono, email, max_usuarios). Los campos opcionales
 * usan el operador ?? para poner null si no se proporcionan.
 *
 * Antes de insertar, llama a inmobiliarias_asegurar_tabla() para
 * garantizar que la tabla existe.
 *
 * Devuelve el ID numérico de la nueva inmobiliaria (por ejemplo, 7),
 * que se obtiene con $pdo->lastInsertId() (el último ID auto-generado).
 *
 * Usa "prepare" + "execute" en vez de ejecutar SQL directamente.
 * Esto se llama "consulta preparada" y protege contra ataques de
 * inyección SQL (un hacker no puede introducir código malicioso
 * a través de los campos del formulario).
 */
function inmobiliaria_crear(PDO $pdo, array $datos): ?int
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO inmobiliarias (nombre, cif, direccion, telefono, email, max_usuarios)
         VALUES (:nombre, :cif, :direccion, :telefono, :email, :max_usuarios)'
    );
    $stmt->execute([
        'nombre'       => $datos['nombre'],
        'cif'          => $datos['cif'] ?? null,
        'direccion'    => $datos['direccion'] ?? null,
        'telefono'     => $datos['telefono'] ?? null,
        'email'        => $datos['email'] ?? null,
        'max_usuarios' => (int) ($datos['max_usuarios'] ?? 10),
    ]);
    return (int) $pdo->lastInsertId();
}

/*
 * Actualiza los datos de una inmobiliaria existente.
 *
 * Recibe el $id de la inmobiliaria a modificar y un array $datos con
 * los nuevos valores. Ejecuta un UPDATE SQL que reemplaza todos los
 * campos editables.
 *
 * La cláusula "WHERE id = :id" es fundamental: sin ella, se
 * actualizarían TODAS las inmobiliarias de la tabla, lo cual sería
 * un desastre.
 *
 * Devuelve true si se modificó al menos una fila (es decir, si la
 * inmobiliaria existía y los datos cambiaron), o false si no hubo
 * cambios (por ejemplo, si se enviaron los mismos datos que ya tenía).
 * Esto se comprueba con $stmt->rowCount() que cuenta las filas afectadas.
 */
function inmobiliaria_actualizar(PDO $pdo, int $id, array $datos): bool
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'UPDATE inmobiliarias SET nombre = :nombre, cif = :cif, direccion = :direccion,
         telefono = :telefono, email = :email, max_usuarios = :max_usuarios WHERE id = :id'
    );
    $stmt->execute([
        'nombre'       => $datos['nombre'],
        'cif'          => $datos['cif'] ?? null,
        'direccion'    => $datos['direccion'] ?? null,
        'telefono'     => $datos['telefono'] ?? null,
        'email'        => $datos['email'] ?? null,
        'max_usuarios' => (int) ($datos['max_usuarios'] ?? 10),
        'id'           => $id,
    ]);
    return $stmt->rowCount() > 0;
}

/*
 * Activa o desactiva una inmobiliaria ("toggle" = interruptor).
 *
 * En vez de borrar una inmobiliaria (lo cual eliminaría sus datos),
 * se puede "desactivar": sigue existiendo en la base de datos pero
 * sus usuarios no pueden iniciar sesión ni operar.
 *
 * El truco SQL es elegante: "SET activa = NOT activa".
 *   - Si activa era 1 (encendida), NOT 1 = 0 → se desactiva.
 *   - Si activa era 0 (apagada),   NOT 0 = 1 → se reactiva.
 * Con una sola instrucción se consigue el efecto de "interruptor".
 *
 * Devuelve true si se cambió el estado, false si la inmobiliaria
 * no existía (ninguna fila fue afectada).
 */
function inmobiliaria_toggle_activa(PDO $pdo, int $id): bool
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('UPDATE inmobiliarias SET activa = NOT activa WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() > 0;
}

/*
 * =====================================================================
 *  LISTAR TODAS LAS INMOBILIARIAS
 * =====================================================================
 *
 * Esta función obtiene la lista completa de inmobiliarias registradas
 * en la plataforma, ordenadas alfabéticamente por nombre.
 *
 * Además de traer todos los datos de cada inmobiliaria (nombre, CIF,
 * dirección, etc.), incluye un dato extra muy útil: "total_usuarios",
 * que es la cantidad de usuarios que tiene cada inmobiliaria.
 *
 * ¿Cómo calcula el total de usuarios?
 * Usa una "subconsulta" SQL (una consulta dentro de otra):
 *   (SELECT COUNT(*) FROM usuarios u WHERE u.inmobiliaria_id = i.id)
 * Esto cuenta cuántos registros hay en la tabla "usuarios" cuyo
 * campo inmobiliaria_id coincida con el id de cada inmobiliaria.
 * Por ejemplo, si la inmobiliaria "Pisos Madrid" tiene id=3 y hay
 * 7 usuarios con inmobiliaria_id=3, el resultado será total_usuarios=7.
 *
 * Se asegura de que la tabla exista antes de consultarla llamando a
 * inmobiliarias_asegurar_tabla().
 *
 * fetchAll() devuelve TODOS los resultados como un array de arrays.
 * Cada elemento del array contiene los datos de una inmobiliaria.
 *
 * Devuelve: Un array con todas las inmobiliarias y su conteo de usuarios.
 *           Si no hay ninguna, devuelve un array vacío [].
 * =====================================================================
 */
function inmobiliarias_listar(PDO $pdo): array
{
    inmobiliarias_asegurar_tabla($pdo);
    return $pdo->query('SELECT i.*, (SELECT COUNT(*) FROM usuarios u WHERE u.inmobiliaria_id = i.id) AS total_usuarios FROM inmobiliarias i ORDER BY i.nombre')->fetchAll();
}

/*
 * =====================================================================
 *  OBTENER UNA INMOBILIARIA POR SU ID
 * =====================================================================
 *
 * Esta función busca una inmobiliaria concreta usando su número de
 * identificación (id). Es como buscar una ficha en un fichero por
 * su número.
 *
 * Ejemplo: inmobiliaria_obtener($pdo, 5) buscaría la inmobiliaria
 * con id=5 y devolvería todos sus datos (nombre, CIF, teléfono...).
 *
 * Usa una consulta preparada (prepare + execute) para protegerse
 * contra inyección SQL. El :id es un "marcador de posición" que
 * se reemplaza de forma segura por el valor real.
 *
 * fetch() devuelve UNA sola fila como array asociativo.
 * Si no encuentra la inmobiliaria, fetch() devuelve false,
 * y el operador ?: convierte ese false en null.
 *
 * El tipo de retorno "?array" significa que puede devolver:
 *   - Un array con los datos de la inmobiliaria (si la encontró)
 *   - null (si no existe ninguna inmobiliaria con ese id)
 * =====================================================================
 */
function inmobiliaria_obtener(PDO $pdo, int $id): ?array
{
    inmobiliarias_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM inmobiliarias WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

/*
 * =====================================================================
 *  ELIMINAR UNA INMOBILIARIA Y TODOS SUS DATOS ASOCIADOS
 * =====================================================================
 *
 * Esta función elimina completamente una inmobiliaria y TODOS los datos
 * vinculados a ella en todas las tablas del sistema. Es una operación
 * irreversible que borra en cascada:
 *
 *   1. Imágenes de propiedades (tabla imagenes_propiedades)
 *   2. Proceso de propiedades / Kanban (tabla proceso_propiedades)
 *   3. Etiquetas de entidades (tabla entidad_etiquetas)
 *   4. Filtros guardados (tabla filtros_guardados)
 *   5. Preferencias de usuario (tabla preferencias_usuario)
 *   6. Recordatorios (tabla recordatorios)
 *   7. Visitas (tabla visitas)
 *   8. Ofertas (tabla ofertas)
 *   9. Notas (tabla notas)
 *  10. Peticiones / tickets (tabla peticiones)
 *  11. Actividad / log de auditoría (tabla actividad_log)
 *  12. Propiedades (tabla propiedades)
 *  13. Clientes (tabla clientes)
 *  14. Usuarios (tabla usuarios)
 *  15. La propia inmobiliaria (tabla inmobiliarias)
 *
 * El orden de borrado es importante: primero se eliminan las tablas
 * que dependen de otras (por ejemplo, imágenes antes que propiedades,
 * visitas antes que clientes). Si se borrara al revés, las referencias
 * quedarían huérfanas o MySQL daría error por claves foráneas.
 *
 * Se usa una transacción (beginTransaction / commit / rollback) para
 * garantizar que O SE BORRA TODO O NO SE BORRA NADA. Si falla algún
 * DELETE intermedio, el rollback deshace todos los cambios anteriores.
 *
 * Devuelve true si la eliminación fue exitosa, false si hubo error.
 * =====================================================================
 */
function inmobiliaria_eliminar(PDO $pdo, int $id): bool
{
    try {
        $pdo->beginTransaction();

        // Obtener IDs de usuarios de esta inmobiliaria para limpiar tablas que usan usuario_id
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE inmobiliaria_id = :iid');
        $stmt->execute(['iid' => $id]);
        $usuario_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Obtener IDs de propiedades para limpiar imágenes
        $stmt = $pdo->prepare('SELECT id FROM propiedades WHERE inmobiliaria_id = :iid');
        $stmt->execute(['iid' => $id]);
        $propiedad_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 1. Imágenes de propiedades
        if (!empty($propiedad_ids)) {
            $placeholders = implode(',', array_fill(0, count($propiedad_ids), '?'));
            $pdo->prepare("DELETE FROM imagenes_propiedades WHERE propiedad_id IN ($placeholders)")->execute($propiedad_ids);
        }

        // 2-14. Tablas con columna inmobiliaria_id (borrado directo)
        $tablas_con_iid = [
            'proceso_propiedades', 'recordatorios', 'visitas', 'ofertas',
            'peticiones', 'actividad_log', 'propiedades', 'clientes'
        ];
        foreach ($tablas_con_iid as $tabla) {
            try {
                $pdo->prepare("DELETE FROM {$tabla} WHERE inmobiliaria_id = ?")->execute([$id]);
            } catch (PDOException $e) { /* La tabla puede no existir aún */ }
        }

        // Tablas que dependen de usuario_id (sin inmobiliaria_id directa)
        if (!empty($usuario_ids)) {
            $placeholders = implode(',', array_fill(0, count($usuario_ids), '?'));
            $tablas_por_uid = ['preferencias_usuario', 'filtros_guardados', 'entidad_etiquetas', 'notas'];
            foreach ($tablas_por_uid as $tabla) {
                try {
                    $pdo->prepare("DELETE FROM {$tabla} WHERE usuario_id IN ($placeholders)")->execute($usuario_ids);
                } catch (PDOException $e) { /* La tabla puede no existir aún */ }
            }
        }

        // 14. Usuarios de la inmobiliaria
        $pdo->prepare('DELETE FROM usuarios WHERE inmobiliaria_id = ?')->execute([$id]);

        // 15. La propia inmobiliaria
        $pdo->prepare('DELETE FROM inmobiliarias WHERE id = ?')->execute([$id]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

/* ---- Usuarios multi-tenant ---- */

/*
 * =====================================================================
 *  ASEGURAR QUE LA TABLA USUARIOS TIENE TODAS LAS COLUMNAS NECESARIAS
 * =====================================================================
 *
 * Esta función es un "patrón de migración" automática. A diferencia de
 * inmobiliarias_asegurar_tabla() que crea la tabla desde cero, aquí
 * la tabla "usuarios" ya existe (fue creada al instalar la app),
 * pero con el tiempo se han añadido nuevas funcionalidades que
 * requieren columnas adicionales que no existían originalmente.
 *
 * ¿Qué es ALTER TABLE?
 *   Es la instrucción SQL para MODIFICAR una tabla existente. En este
 *   caso, se usa "ALTER TABLE usuarios ADD COLUMN" para añadir nuevas
 *   columnas a la tabla de usuarios sin perder los datos existentes.
 *   Es como añadir una nueva columna a una hoja de Excel que ya tiene
 *   datos: los datos anteriores no se tocan.
 *
 * ¿Por qué se usa try/catch?
 *   Si la columna YA EXISTE, MySQL lanza un error al intentar crearla
 *   otra vez. El bloque try/catch "atrapa" ese error y lo ignora
 *   silenciosamente (el catch está vacío a propósito). Así la función
 *   funciona tanto en bases de datos nuevas (añade las columnas) como
 *   en bases de datos ya actualizadas (no hace nada, sin errores).
 *
 *   try { ... } = "Intenta ejecutar esto"
 *   catch { ... } = "Si falla, haz esto otro (en este caso, nada)"
 *
 * Columnas que se añaden mediante migración:
 *   - inmobiliaria_id:    Vincula al usuario con su inmobiliaria
 *   - avatar_url:         Ruta a la foto de perfil del usuario
 *   - activo:             1 = usuario activo, 0 = desactivado
 *   - password_temporal:  1 = debe cambiar contraseña, 0 = contraseña definitiva
 *   - terminos_aceptados: Fecha en que aceptó términos y condiciones
 *
 * "AFTER id", "AFTER rol", etc: indica DÓNDE colocar la nueva columna
 * dentro de la tabla (después de qué columna existente).
 *
 * Usa "static $ok" para ejecutar la migración solo UNA vez por
 * carga de página, igual que inmobiliarias_asegurar_tabla().
 * =====================================================================
 */
function usuarios_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;

    // Migración: añadir columnas si no existen
    $columnas = ['inmobiliaria_id', 'avatar_url', 'activo', 'password_temporal', 'terminos_aceptados'];
    foreach ($columnas as $col) {
        try {
            if ($col === 'inmobiliaria_id') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER id');
            } elseif ($col === 'avatar_url') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN avatar_url VARCHAR(300) DEFAULT NULL AFTER rol');
            } elseif ($col === 'activo') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_url');
            } elseif ($col === 'password_temporal') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN password_temporal TINYINT(1) NOT NULL DEFAULT 0 AFTER activo');
            } elseif ($col === 'terminos_aceptados') {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN terminos_aceptados VARCHAR(20) DEFAULT NULL AFTER password_temporal');
            }
        } catch (\PDOException $e) {
            // La columna ya existe en la base de datos → no hay que hacer nada.
            // Este catch vacío es INTENCIONAL: simplemente ignora el error
            // "Duplicate column name" que MySQL lanza cuando la columna ya existe.
        }
    }
    $ok = true;
}

/*
 * =====================================================================
 *  LISTAR USUARIOS DE UNA INMOBILIARIA CONCRETA
 * =====================================================================
 *
 * Obtiene todos los usuarios que pertenecen a una inmobiliaria específica.
 * Esta función la usa el jefe o supervisor de una inmobiliaria para
 * ver a todos los miembros de su equipo.
 *
 * Recibe el $inmobiliaria_id (número identificador de la inmobiliaria)
 * y busca en la tabla "usuarios" todos los que tengan ese número
 * en su campo inmobiliaria_id.
 *
 * Solo trae los campos necesarios para la lista (id, nombre, email,
 * rol, activo, fecha de creación), NO trae datos sensibles como
 * la contraseña. Esto es una buena práctica de seguridad.
 *
 * Los resultados se ordenan alfabéticamente por nombre (ORDER BY nombre)
 * para que la lista sea fácil de leer.
 *
 * Devuelve un array con todos los usuarios encontrados.
 * Si la inmobiliaria no tiene usuarios, devuelve un array vacío [].
 * =====================================================================
 */
function usuarios_listar_por_inmobiliaria(PDO $pdo, int $inmobiliaria_id): array
{
    usuarios_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, rol, activo, created_at FROM usuarios WHERE inmobiliaria_id = :iid ORDER BY nombre'
    );
    $stmt->execute(['iid' => $inmobiliaria_id]);
    return $stmt->fetchAll();
}

/*
 * =====================================================================
 *  CREAR UN NUEVO USUARIO
 * =====================================================================
 *
 * Esta función registra un nuevo usuario en la base de datos.
 * Puede ser llamada cuando un jefe da de alta a un miembro de su
 * equipo desde el panel de administración.
 *
 * CONTRASEÑAS TEMPORALES:
 *   Cuando un jefe crea un empleado nuevo, no necesita inventarle
 *   una contraseña. Marca la opción "contraseña temporal" y el sistema
 *   genera una automáticamente (por ejemplo: "kR3$xmLp").
 *   Esta contraseña temporal se muestra UNA sola vez al jefe para
 *   que se la dé al empleado. En el primer login, el sistema
 *   obligará al empleado a cambiarla por una propia.
 *
 * HASHING DE CONTRASEÑA (Seguridad crítica):
 *   Nunca se guarda la contraseña en texto plano. password_hash()
 *   la convierte en un código irreversible (hash). Cuando el usuario
 *   hace login, se compara el hash, no la contraseña original.
 *
 *   Ejemplo: la contraseña "MiClave123!" se convierte en algo como:
 *   "$2y$10$xJ8kQ2pR5mN3vL7wY9uZoe.abc123xyz..."
 *   Es IMPOSIBLE recuperar "MiClave123!" a partir del hash.
 *   Si un hacker roba la base de datos, solo verá hashes inútiles.
 *
 *   PASSWORD_DEFAULT usa el algoritmo bcrypt, que es uno de los más
 *   seguros disponibles. Además, añade automáticamente un "salt"
 *   (dato aleatorio) para que dos usuarios con la misma contraseña
 *   tengan hashes DIFERENTES.
 *
 * Parámetros del array $datos:
 *   - inmobiliaria_id: A qué inmobiliaria pertenece el nuevo usuario
 *   - nombre: Nombre completo del usuario
 *   - email: Correo electrónico (será su nombre de usuario para login)
 *   - password: Contraseña elegida (puede estar vacía si es temporal)
 *   - rol: Nivel de acceso (agente_comprador, supervisor, jefe, etc.)
 *   - password_temporal: Si es true, se genera contraseña automática
 *
 * Devuelve un array con:
 *   - 'id': El ID numérico del usuario recién creado
 *   - 'password': La contraseña temporal generada (solo si fue temporal),
 *                 o null si el usuario eligió su propia contraseña.
 *                 IMPORTANTE: Esta es la única oportunidad de ver la
 *                 contraseña temporal, porque en la BD solo está el hash.
 * =====================================================================
 */
function usuario_crear(PDO $pdo, array $datos): array
{
    usuarios_asegurar_tabla($pdo);

    // Si no se provee contraseña, generar una temporal aleatoria
    $password_plano = $datos['password'] ?? '';
    $es_temporal = !empty($datos['password_temporal']);
    if ($es_temporal && $password_plano === '') {
        $password_plano = generar_password_temporal();
    }

    // Nunca se guarda la contraseña en texto plano.
    // password_hash() la convierte en un código irreversible (hash).
    // Cuando el usuario hace login, se compara el hash, no la contraseña original.
    $hash = password_hash($password_plano, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (inmobiliaria_id, nombre, email, password_hash, rol, activo, password_temporal)
         VALUES (:iid, :nombre, :email, :hash, :rol, 1, :temporal)'
    );
    $stmt->execute([
        'iid'      => $datos['inmobiliaria_id'],
        'nombre'   => $datos['nombre'],
        'email'    => $datos['email'],
        'hash'     => $hash,
        'rol'      => $datos['rol'],
        'temporal' => $es_temporal ? 1 : 0,
    ]);
    return [
        'id'       => (int) $pdo->lastInsertId(),
        'password' => $es_temporal ? $password_plano : null,
    ];
}

/*
 * =====================================================================
 *  GENERAR UNA CONTRASEÑA TEMPORAL ALEATORIA
 * =====================================================================
 *
 * Crea una contraseña aleatoria de 8 caracteres que cumple con la
 * política de seguridad de la aplicación:
 *   - Mínimo 8 caracteres (aquí genera exactamente 8)
 *   - Al menos 1 letra MAYÚSCULA (A-Z)
 *   - Al menos 1 símbolo especial (!@#$%&*?)
 *   - El resto son letras minúsculas y números
 *
 * ¿Cómo funciona paso a paso?
 *   1. Define 4 grupos de caracteres: mayúsculas, minúsculas, números y símbolos.
 *   2. GARANTIZA que haya al menos 1 mayúscula y 1 símbolo
 *      eligiendo uno de cada grupo al principio.
 *   3. Rellena los 6 caracteres restantes con minúsculas y números
 *      elegidos al azar.
 *   4. Mezcla todos los caracteres aleatoriamente con str_shuffle().
 *
 * ¿Por qué str_shuffle() al final?
 *   Sin la mezcla, la contraseña siempre empezaría con una mayúscula
 *   seguida de un símbolo (porque se añaden primero). str_shuffle()
 *   reordena los caracteres aleatoriamente para que la mayúscula y
 *   el símbolo puedan aparecer en cualquier posición.
 *   Ejemplo antes: "A!k3m8px" → después de shuffle: "3kA!p8mx"
 *
 * random_int() es criptográficamente seguro (a diferencia de rand()),
 * lo que significa que genera números verdaderamente aleatorios
 * adecuados para contraseñas.
 *
 * Devuelve: Una cadena de 8 caracteres como "kR3$xmLp"
 * =====================================================================
 */
/**
 * Genera una contraseña temporal que cumple la política (8-16 chars, 1 MAY, 1 símbolo).
 */
function generar_password_temporal(): string
{
    $upper  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower  = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $symbols = '!@#$%&*?';

    // Paso 1: Garantizar al menos 1 mayúscula y 1 símbolo
    $pwd  = $upper[random_int(0, strlen($upper) - 1)];
    $pwd .= $symbols[random_int(0, strlen($symbols) - 1)];

    // Paso 2: Rellenar los 6 caracteres restantes con minúsculas y números
    for ($i = 0; $i < 6; $i++) {
        $all = $lower . $digits;
        $pwd .= $all[random_int(0, strlen($all) - 1)];
    }

    // Paso 3: Mezclar aleatoriamente para que la mayúscula y el símbolo
    // no estén siempre al principio
    return str_shuffle($pwd);
}

/*
 * =====================================================================
 *  CAMBIAR EL ROL DE UN USUARIO
 * =====================================================================
 *
 * Actualiza el rol (nivel de acceso) de un usuario dentro de su
 * inmobiliaria. Por ejemplo, puede ascender a un "agente_comprador"
 * a "supervisor".
 *
 * Validación de seguridad:
 *   Antes de hacer nada, comprueba que el rol solicitado sea uno
 *   de los válidos (agente_comprador, agente_vendedor, agente,
 *   marketing, supervisor, jefe). Si alguien intenta poner un rol
 *   inventado como "dios" o inyectar código malicioso, la función
 *   devuelve false inmediatamente sin tocar la base de datos.
 *
 * El filtro "AND inmobiliaria_id = :iid" asegura que un jefe solo
 * pueda cambiar el rol de usuarios de SU inmobiliaria, no de otras.
 * Sin este filtro, un jefe malintencionado podría cambiar roles
 * de empleados de la competencia.
 *
 * Devuelve true si el cambio se realizó, false si no (usuario
 * no encontrado, mismo rol, o inmobiliaria incorrecta).
 * =====================================================================
 */
function usuario_actualizar_rol(PDO $pdo, int $id, string $rol, int $inmobiliaria_id): bool
{
    $roles_validos = array_keys(roles_disponibles());
    if (!in_array($rol, $roles_validos, true)) return false;

    $stmt = $pdo->prepare('UPDATE usuarios SET rol = :rol WHERE id = :id AND inmobiliaria_id = :iid');
    $stmt->execute(['rol' => $rol, 'id' => $id, 'iid' => $inmobiliaria_id]);
    return $stmt->rowCount() > 0;
}

/*
 * =====================================================================
 *  ACTIVAR O DESACTIVAR UN USUARIO (Interruptor on/off)
 * =====================================================================
 *
 * Funciona exactamente igual que inmobiliaria_toggle_activa() pero
 * para usuarios individuales. Alterna el estado de un usuario
 * entre activo (1) e inactivo (0).
 *
 * Un usuario desactivado no puede iniciar sesión pero sus datos
 * (clientes, propiedades, visitas) se conservan. Es útil cuando
 * un empleado se va de vacaciones o deja la empresa temporalmente.
 *
 * "SET activo = NOT activo" invierte el valor actual:
 *   - Si estaba activo (1) → pasa a inactivo (0)
 *   - Si estaba inactivo (0) → pasa a activo (1)
 *
 * El filtro por inmobiliaria_id garantiza que solo puedas activar
 * o desactivar usuarios de TU propia inmobiliaria.
 *
 * Devuelve true si se cambió el estado, false si el usuario no
 * existe o no pertenece a la inmobiliaria indicada.
 * =====================================================================
 */
function usuario_toggle_activo(PDO $pdo, int $id, int $inmobiliaria_id): bool
{
    $stmt = $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id = :id AND inmobiliaria_id = :iid');
    $stmt->execute(['id' => $id, 'iid' => $inmobiliaria_id]);
    return $stmt->rowCount() > 0;
}

/*
 * =====================================================================
 *  OBTENER LOS DATOS DE UN USUARIO POR SU ID
 * =====================================================================
 *
 * Busca un usuario específico por su número de identificación.
 * Devuelve sus datos básicos: id, inmobiliaria, nombre, email,
 * rol, si está activo y cuándo se creó la cuenta.
 *
 * NOTA DE SEGURIDAD: intencionalmente NO devuelve el campo
 * password_hash. Nunca hay razón legítima para mostrar el hash
 * de la contraseña en una interfaz de usuario.
 *
 * Devuelve un array con los datos del usuario, o null si no
 * se encuentra ningún usuario con ese id.
 * =====================================================================
 */
function usuario_obtener(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, inmobiliaria_id, nombre, email, rol, activo, created_at FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

/*
 * =====================================================================
 *  e() — ESCAPAR TEXTO PARA HTML (Protección contra XSS)
 * =====================================================================
 *
 * Esta pequeña función es una de las más IMPORTANTES de toda la
 * aplicación en términos de seguridad. Protege contra ataques XSS
 * (Cross-Site Scripting = Inyección de código en sitios web).
 *
 * ¿Qué es un ataque XSS?
 *   Si un usuario escribe <script>alert('hacked')</script> en un
 *   campo, esta función convierte los < y > en texto visible,
 *   evitando que se ejecute código malicioso en el navegador.
 *
 * Ejemplo práctico:
 *   Entrada: <script>alert('hacked')</script>
 *   Salida:  &lt;script&gt;alert(&#039;hacked&#039;)&lt;/script&gt;
 *
 *   El navegador muestra literalmente "<script>alert('hacked')</script>"
 *   como texto, en vez de ejecutarlo como código JavaScript.
 *
 * ¿Por qué se llama "e()"?
 *   Es una abreviatura de "escape". Se usa en TODAS partes donde
 *   se muestra un dato del usuario en HTML:
 *   <p><?= e($cliente['nombre']) ?></p>
 *
 * htmlspecialchars() es la función PHP que hace la conversión:
 *   - ENT_QUOTES: convierte AMBOS tipos de comillas (' y ")
 *   - 'UTF-8': indica la codificación de caracteres del texto
 *
 * REGLA DE ORO: NUNCA mostrar datos del usuario sin pasar por e().
 * =====================================================================
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
 * =====================================================================
 *  FORMATEAR UN PRECIO PARA MOSTRARLO EN PANTALLA
 * =====================================================================
 *
 * Convierte un número de precio crudo (ej: 285000.00) en un texto
 * legible para humanos (ej: "285.000 €").
 *
 * ¿Qué hace number_format()?
 *   - Primer parámetro: el número a formatear (285000)
 *   - 0: sin decimales (no muestra céntimos)
 *   - ',': separador decimal (estándar español)
 *   - '.': separador de miles (punto, estándar español)
 *   Resultado: "285.000"
 *
 * Luego añade la moneda (normalmente "€") y, si es un alquiler,
 * el periodo de pago.
 *
 * Ejemplos:
 *   format_price(285000, '€', null) → "285.000 €"
 *   format_price(1200, '€', 'mes')  → "1.200 €/mes"
 *   format_price(800, '€', 'semana') → "800 €/semana"
 *
 * El periodo solo se añade si no está vacío (para ventas no aplica).
 * =====================================================================
 */
function format_price(float $precio, string $moneda, ?string $periodo): string
{
    $formato = number_format($precio, 0, ',', '.');
    $texto = $formato . ' ' . $moneda;

    if (!empty($periodo)) {
        $texto .= '/' . $periodo;
    }

    return $texto;
}

/*
 * =====================================================================
 *  CONVERTIR ESTADO EN CLASE CSS
 * =====================================================================
 *
 * Convierte el nombre de un estado (ej: "En Negociación") en un
 * texto válido para usarlo como clase CSS (ej: "en_negociación").
 *
 * Las clases CSS no pueden tener espacios ni mayúsculas por
 * convención. Esta función:
 *   - strtolower(): convierte todo a minúsculas
 *   - str_replace(' ', '_', ...): reemplaza espacios por guiones bajos
 *
 * Ejemplo de uso en HTML:
 *   <span class="estado estado_<?= map_estado_clase($estado) ?>">
 * Resultado: <span class="estado estado_en_negociación">
 *
 * Esto permite aplicar colores diferentes a cada estado usando CSS:
 *   .estado_disponible { background: green; }
 *   .estado_vendido { background: red; }
 *   .estado_en_negociación { background: orange; }
 * =====================================================================
 */
function map_estado_clase(string $estado): string
{
    return strtolower(str_replace(' ', '_', $estado));
}

/*
 * =====================================================================
 *  DETERMINAR LA SECCIÓN DE ORIGEN DE UNA PROPIEDAD
 * =====================================================================
 *
 * Cuando un usuario está viendo los detalles de una propiedad y
 * pulsa "Volver", necesitamos saber a qué listado devolverlo.
 * La sección correcta depende de dos factores:
 *
 *   1. Tipo de operación: ¿es un alquiler o una venta/compra?
 *   2. Equipo del usuario: ¿es del equipo comprador o vendedor?
 *
 * Combinaciones posibles:
 *   - Alquiler + comprador → "alquileres-comprador"
 *   - Alquiler + vendedor  → "alquileres-vendedor"
 *   - Venta + comprador    → "propiedades-comprador"
 *   - Venta + vendedor     → "propiedades-vendedor"
 *
 * Esto permite construir enlaces de retorno correctos como:
 *   <a href="?seccion=<?= obtener_origen_propiedad('alquiler','comprador') ?>">
 *   → <a href="?seccion=alquileres-comprador">
 * =====================================================================
 */
function obtener_origen_propiedad(string $operacion, string $equipo): string
{
    if ($operacion === 'alquiler') {
        return $equipo === 'comprador' ? 'alquileres-comprador' : 'alquileres-vendedor';
    }

    return $equipo === 'comprador' ? 'propiedades-comprador' : 'propiedades-vendedor';
}

/*
 * =====================================================================
 *  MENSAJES FLASH — AVISOS TEMPORALES DE UNA SOLA LECTURA
 * =====================================================================
 *
 * Los mensajes flash son avisos temporales que se muestran UNA sola
 * vez. Por ejemplo: "Cliente guardado correctamente". Se guardan en
 * la sesión y se borran al leerlos.
 *
 * ¿Por qué son necesarios?
 *   Cuando guardas un formulario, el servidor procesa los datos y
 *   luego REDIRIGE al usuario a otra página (por ejemplo, al listado).
 *   El problema es: ¿cómo le dices al usuario "se guardó bien" si
 *   ya estás en otra página? La respuesta: guardas el mensaje en la
 *   sesión ANTES de redirigir, y lo lees DESPUÉS de redirigir.
 *
 * Flujo típico:
 *   1. Usuario envía formulario de "Nuevo Cliente"
 *   2. Servidor guarda el cliente en la BD
 *   3. Servidor ejecuta: flash_set('exito', 'Cliente guardado correctamente')
 *   4. Servidor redirige a: ?seccion=clientes-vendedor
 *   5. La página del listado ejecuta: $msg = flash_get('exito')
 *   6. Muestra el banner verde "Cliente guardado correctamente"
 *   7. El mensaje se BORRA automáticamente de la sesión
 *   8. Si el usuario recarga la página, el banner ya NO aparece
 *
 * $_SESSION['flash'] es un array dentro de la sesión del usuario
 * donde se almacenan estos mensajes temporalmente.
 * =====================================================================
 */

/*
 * flash_set() — GUARDAR UN MENSAJE FLASH
 *
 * Guarda un mensaje en la sesión para mostrarlo más tarde.
 * $key = tipo de mensaje ('exito', 'error', 'aviso', etc.)
 * $message = texto del mensaje ('Cliente guardado correctamente')
 *
 * Si el array $_SESSION['flash'] no existe todavía (primera vez
 * que se usa), lo crea como array vacío antes de añadir el mensaje.
 */
function flash_set(string $key, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $message;
}

/*
 * flash_get() — LEER Y BORRAR UN MENSAJE FLASH
 *
 * Lee un mensaje flash de la sesión y lo ELIMINA inmediatamente.
 * Esta es la clave del sistema: el mensaje solo se puede leer UNA vez.
 *
 * ¿Cómo funciona?
 *   1. Comprueba si existe un mensaje con la clave $key
 *   2. Si NO existe → devuelve null (no hay mensaje que mostrar)
 *   3. Si SÍ existe:
 *      a) Guarda el texto del mensaje en $mensaje
 *      b) Lo borra de la sesión con unset() (para que no vuelva a aparecer)
 *      c) Devuelve el texto del mensaje
 *
 * Ejemplo de uso en una vista:
 *   $exito = flash_get('exito');
 *   if ($exito) {
 *       echo "<div class='alerta_exito'>$exito</div>";
 *   }
 */
function flash_get(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $mensaje = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $mensaje;
}

/*
 * =====================================================================
 *  FUNCIONES DE VALIDACIÓN DE DATOS
 * =====================================================================
 *
 * Las siguientes funciones comprueban que los datos introducidos por
 * el usuario cumplan ciertas reglas antes de guardarlos en la base
 * de datos. Es como un "control de calidad" que rechaza datos
 * incorrectos o peligrosos.
 *
 * Todas devuelven true (válido) o false (inválido).
 * Se usan antes de insertar o actualizar registros.
 * =====================================================================
 */

/*
 * validar_requerido() — COMPROBAR QUE UN CAMPO NO ESTÉ VACÍO
 *
 * Verifica que el usuario haya escrito algo en un campo obligatorio.
 * trim() elimina los espacios en blanco al principio y al final,
 * así que un campo con solo espacios "   " se considera vacío.
 *
 * Ejemplo:
 *   validar_requerido('Juan')   → true  (✓ tiene contenido)
 *   validar_requerido('')       → false (✗ está vacío)
 *   validar_requerido('  ')     → false (✗ solo espacios = vacío)
 */
function validar_requerido(string $valor): bool
{
    return trim($valor) !== '';
}

/*
 * validar_email() — COMPROBAR QUE UN EMAIL TIENE FORMATO VÁLIDO
 *
 * Usa la función integrada de PHP filter_var() con el filtro
 * FILTER_VALIDATE_EMAIL para verificar que el texto tiene forma
 * de dirección de correo electrónico.
 *
 * Comprueba cosas como: que tenga @, que tenga un dominio válido,
 * que no tenga caracteres prohibidos, etc.
 *
 * Ejemplo:
 *   validar_email('juan@ejemplo.com')  → true  (✓ formato correcto)
 *   validar_email('juan@ejemplo')      → false (✗ falta extensión)
 *   validar_email('juanejemplo.com')    → false (✗ falta @)
 *   validar_email('')                   → false (✗ vacío)
 *
 * NOTA: Esto solo valida el FORMATO, no verifica que el email
 * exista realmente o que el buzón esté activo.
 */
function validar_email(string $valor): bool
{
    return filter_var($valor, FILTER_VALIDATE_EMAIL) !== false;
}

/*
 * =====================================================================
 *  validar_password_segura() — VERIFICAR POLÍTICA DE CONTRASEÑA
 * =====================================================================
 *
 * Comprueba que una contraseña cumple los requisitos mínimos de
 * seguridad establecidos para la aplicación:
 *
 *   1. Mínimo 8 caracteres de longitud
 *   2. Al menos 1 letra MAYÚSCULA (A-Z)
 *   3. Al menos 1 símbolo especial (!, @, #, $, etc.) o guión bajo (_)
 *
 * ¿Cómo funciona la expresión regular (regex)?
 *   /^(?=.*[A-Z])(?=.*[\W_]).{8,}$/
 *
 *   Desglose para no programadores:
 *   - ^           = "desde el principio de la contraseña"
 *   - (?=.*[A-Z]) = "en algún punto debe haber una mayúscula"
 *   - (?=.*[\W_]) = "en algún punto debe haber un símbolo"
 *   - .{8,}       = "debe tener al menos 8 caracteres en total"
 *   - $           = "hasta el final de la contraseña"
 *
 * Nota sobre bcrypt: no se pone límite máximo artificial, pero
 * el algoritmo bcrypt (usado en password_hash) trunca internamente
 * a 72 bytes. Contraseñas más largas funcionan, pero los caracteres
 * extra después del byte 72 no añaden seguridad adicional.
 *
 * Ejemplos:
 *   validar_password_segura('MiClave1!')  → true  (✓ cumple todo)
 *   validar_password_segura('miclave1!')  → false (✗ falta mayúscula)
 *   validar_password_segura('MiClave12')  → false (✗ falta símbolo)
 *   validar_password_segura('Mi!1')       → false (✗ menos de 8 chars)
 * =====================================================================
 */
function validar_password_segura(string $valor): bool
{
    return preg_match('/^(?=.*[A-Z])(?=.*[\W_]).{8,}$/', $valor) === 1;
}

/*
 * validar_telefono() — COMPROBAR FORMATO DE TELÉFONO
 *
 * Verifica que un número de teléfono tenga un formato razonable.
 * Es bastante flexible para aceptar formatos internacionales:
 *
 * Caracteres permitidos: dígitos (0-9), signo + (para prefijo
 * internacional), paréntesis (), espacios y guiones.
 * Longitud: entre 6 y 20 caracteres.
 *
 * Ejemplos válidos:
 *   "612345678"        → true (✓ móvil español)
 *   "+34 612 345 678"  → true (✓ con prefijo y espacios)
 *   "(+34) 91-123-4567" → true (✓ con paréntesis y guiones)
 *
 * Ejemplos inválidos:
 *   "12345"            → false (✗ muy corto, menos de 6 chars)
 *   "hola"             → false (✗ contiene letras)
 *   ""                 → false (✗ vacío)
 */
function validar_telefono(string $valor): bool
{
    return (bool) preg_match('/^[0-9+()\s-]{6,20}$/', $valor);
}

/*
 * validar_enum() — COMPROBAR QUE UN VALOR ESTÁ EN UNA LISTA PERMITIDA
 *
 * Verifica que el valor proporcionado sea uno de los valores
 * permitidos. "Enum" viene de "enumeración" (lista cerrada de opciones).
 *
 * ¿Para qué sirve?
 *   Cuando un formulario tiene un desplegable (select) con opciones
 *   fijas como "venta", "alquiler", "traspaso", esta función verifica
 *   que el valor recibido sea realmente una de esas opciones.
 *   Un hacker podría modificar el HTML del formulario para enviar
 *   un valor no permitido como "hackeo". Esta función lo detecta.
 *
 * in_array() busca un valor dentro de un array.
 * El tercer parámetro 'true' activa la comparación estricta:
 * compara tanto el valor como el tipo de dato (por ejemplo,
 * el número 1 NO es igual al texto "1").
 *
 * Ejemplo:
 *   validar_enum('venta', ['venta','alquiler','traspaso']) → true
 *   validar_enum('hackeo', ['venta','alquiler','traspaso']) → false
 */
function validar_enum(string $valor, array $permitidos): bool
{
    return in_array($valor, $permitidos, true);
}

/*
 * =====================================================================
 *  SISTEMA DE PREFERENCIAS DE USUARIO
 * =====================================================================
 *
 * Las preferencias permiten que cada usuario guarde sus ajustes
 * personales: tema visual (claro/oscuro), número de filas por página,
 * columnas visibles en las tablas, filtros guardados, etc.
 *
 * Se almacenan como pares "clave/valor" en la tabla preferencias_usuario.
 * Cada usuario tiene sus propias preferencias independientes.
 *
 * Ejemplo de datos en la tabla:
 *   | usuario_id | clave          | valor    |
 *   |------------|----------------|----------|
 *   | 42         | tema           | oscuro   |
 *   | 42         | filas_por_pag  | 25       |
 *   | 15         | tema           | claro    |
 *
 * Las cuatro funciones trabajan juntas:
 *   - preferencias_asegurar_tabla(): Crea la tabla si no existe
 *   - preferencias_usuario_get():    Lee una preferencia
 *   - preferencias_usuario_set():    Guarda o actualiza una preferencia
 *   - preferencias_usuario_delete(): Elimina una preferencia
 * =====================================================================
 */

/*
 * preferencias_asegurar_tabla() — CREAR LA TABLA SI NO EXISTE
 *
 * Igual que inmobiliarias_asegurar_tabla(), crea la tabla de
 * preferencias automáticamente si aún no existe en la base de datos.
 *
 * Usa "static $tabla_lista" para ejecutar el CREATE TABLE solo
 * UNA vez por carga de página (mismo patrón que las demás).
 *
 * Campos de la tabla:
 *   - id: Identificador único auto-incrementable
 *   - usuario_id: A qué usuario pertenece esta preferencia
 *   - clave: Nombre de la preferencia (ej: "tema", "idioma")
 *   - valor: Valor de la preferencia (ej: "oscuro", "es")
 *   - updated_at: Cuándo se modificó por última vez
 *
 * UNIQUE KEY (usuario_id, clave): Esta restricción asegura que
 * un usuario NO pueda tener dos veces la misma clave. Si el
 * usuario 42 ya tiene "tema", no puede crear otro "tema".
 * Esto es esencial para que el ON DUPLICATE KEY UPDATE funcione
 * correctamente en preferencias_usuario_set().
 */
function preferencias_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS preferencias_usuario (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            clave VARCHAR(120) NOT NULL,
            valor LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_usuario_clave (usuario_id, clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

/*
 * preferencias_usuario_get() — LEER UNA PREFERENCIA DEL USUARIO ACTUAL
 *
 * Busca y devuelve el valor de una preferencia específica del
 * usuario que tiene la sesión iniciada.
 *
 * Ejemplo:
 *   $tema = preferencias_usuario_get($pdo, 'tema');
 *   // Devuelve "oscuro" si el usuario tenía esa preferencia,
 *   // o null si nunca la configuró.
 *
 * Pasos internos:
 *   1. Obtiene el ID del usuario desde la sesión ($_SESSION)
 *   2. Si no hay usuario logueado (id <= 0), devuelve null
 *   3. Se asegura de que la tabla existe
 *   4. Busca en la tabla por usuario_id + clave
 *   5. fetchColumn() devuelve solo el valor (no toda la fila)
 *   6. Si no encuentra la preferencia, devuelve null
 *
 * "LIMIT 1" añade una pequeña optimización: le dice a MySQL que
 * deje de buscar en cuanto encuentre el primer resultado (aunque
 * con la restricción UNIQUE nunca habrá más de uno).
 */
function preferencias_usuario_get(PDO $pdo, string $clave): ?string
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('SELECT valor FROM preferencias_usuario WHERE usuario_id = :usuario_id AND clave = :clave LIMIT 1');
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
    ]);

    $valor = $stmt->fetchColumn();
    if ($valor === false) {
        return null;
    }

    return (string) $valor;
}

/*
 * preferencias_usuario_set() — GUARDAR O ACTUALIZAR UNA PREFERENCIA
 *
 * Guarda una preferencia para el usuario actual. Si la preferencia
 * ya existía, la ACTUALIZA con el nuevo valor. Si no existía, la CREA.
 *
 * Ejemplo:
 *   preferencias_usuario_set($pdo, 'tema', 'oscuro');
 *   // Si ya tenía tema="claro", ahora tiene tema="oscuro"
 *   // Si no tenía preferencia de tema, se crea nueva
 *
 * ¿Cómo funciona "ON DUPLICATE KEY UPDATE"?
 *   Esta cláusula SQL es muy inteligente. Intenta hacer un INSERT
 *   (crear nuevo registro). Si el registro ya existe (misma
 *   combinación de usuario_id + clave, que es UNIQUE), en vez de
 *   dar error, ejecuta un UPDATE (actualiza el valor existente).
 *
 *   Es como decir: "Guarda esto. Si ya existía, sobreescríbelo."
 *   Esto evita tener que hacer primero un SELECT para comprobar
 *   si existe, y luego decidir si hacer INSERT o UPDATE.
 *
 * VALUES(valor) dentro del ON DUPLICATE KEY hace referencia al
 * valor que se intentó insertar, es decir, el nuevo valor.
 */
function preferencias_usuario_set(PDO $pdo, string $clave, string $valor): void
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO preferencias_usuario (usuario_id, clave, valor)
         VALUES (:usuario_id, :clave, :valor)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
    );
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
        'valor' => $valor,
    ]);
}

/*
 * preferencias_usuario_delete() — ELIMINAR UNA PREFERENCIA
 *
 * Borra una preferencia específica del usuario actual.
 * Después de borrarla, preferencias_usuario_get() para esa clave
 * devolverá null (como si nunca se hubiera configurado).
 *
 * Ejemplo:
 *   preferencias_usuario_delete($pdo, 'tema');
 *   // El usuario volverá a usar el tema por defecto de la app
 *
 * Se usa típicamente cuando el usuario quiere "restablecer" una
 * configuración a su valor por defecto, o cuando se limpia el
 * perfil del usuario.
 *
 * La consulta DELETE ... WHERE filtra por usuario_id Y clave,
 * asegurando que solo se borra ESA preferencia concreta de ESE
 * usuario. No afecta a las preferencias de otros usuarios, ni
 * a otras preferencias del mismo usuario.
 */
function preferencias_usuario_delete(PDO $pdo, string $clave): void
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return;
    }

    preferencias_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM preferencias_usuario WHERE usuario_id = :usuario_id AND clave = :clave');
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'clave' => $clave,
    ]);
}

/* Crea la tabla de recordatorios si no existe. */
function recordatorios_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS recordatorios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            descripcion TEXT NOT NULL,
            fecha_recordatorio DATE NOT NULL,
            hora_recordatorio TIME,
            prospecto_id INT,
            estado VARCHAR(20) DEFAULT "pendiente",
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_usuario_id (usuario_id),
            INDEX idx_fecha (fecha_recordatorio),
            INDEX idx_estado (estado),
            INDEX idx_recordatorios_inmob (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

function recordatorio_crear(PDO $pdo, string $tipo, string $descripcion, string $fecha, ?string $hora, ?int $prospecto_id): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO recordatorios (usuario_id, tipo, descripcion, fecha_recordatorio, hora_recordatorio, prospecto_id, estado, inmobiliaria_id)
         VALUES (:usuario_id, :tipo, :descripcion, :fecha, :hora, :prospecto_id, "pendiente", :iid)'
    );
    
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'fecha' => $fecha,
        'hora' => $hora,
        'prospecto_id' => $prospecto_id,
        'iid' => usuario_inmobiliaria_id(),
    ]);

    return (int) $pdo->lastInsertId();
}

/* Devuelve todos los recordatorios del usuario para una fecha concreta. */
function recordatorios_por_fecha(PDO $pdo, string $fecha): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM recordatorios 
         WHERE usuario_id = :usuario_id AND fecha_recordatorio = :fecha
         ORDER BY hora_recordatorio ASC'
    );
    
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'fecha' => $fecha,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function recordatorios_por_mes(PDO $pdo, int $mes, int $ano): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM recordatorios 
         WHERE usuario_id = :usuario_id 
         AND YEAR(fecha_recordatorio) = :ano
         AND MONTH(fecha_recordatorio) = :mes
         ORDER BY fecha_recordatorio ASC, hora_recordatorio ASC'
    );
    
    $stmt->execute([
        'usuario_id' => $usuario_id,
        'mes' => $mes,
        'ano' => $ano,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Recupera un recordatorio por ID validando que pertenezca al usuario de sesión. */
function recordatorio_obtener(PDO $pdo, int $id): ?array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('SELECT * FROM recordatorios WHERE id = :id AND usuario_id = :usuario_id');
    $stmt->execute([
        'id' => $id,
        'usuario_id' => $usuario_id,
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ?: null;
}

function recordatorio_actualizar(PDO $pdo, int $id, string $tipo, string $descripcion, string $fecha, ?string $hora, ?int $prospecto_id, string $estado): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'UPDATE recordatorios 
         SET tipo = :tipo, descripcion = :descripcion, fecha_recordatorio = :fecha, 
             hora_recordatorio = :hora, prospecto_id = :prospecto_id, estado = :estado
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    
    $resultado = $stmt->execute([
        'id' => $id,
        'usuario_id' => $usuario_id,
        'tipo' => $tipo,
        'descripcion' => $descripcion,
        'fecha' => $fecha,
        'hora' => $hora,
        'prospecto_id' => $prospecto_id,
        'estado' => $estado,
    ]);

    return $resultado && $stmt->rowCount() > 0;
}

/* Elimina un recordatorio por ID para el usuario autenticado. */
function recordatorio_eliminar(PDO $pdo, int $id): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    recordatorios_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM recordatorios WHERE id = :id AND usuario_id = :usuario_id');
    $resultado = $stmt->execute([
        'id' => $id,
        'usuario_id' => $usuario_id,
    ]);

    return $resultado && $stmt->rowCount() > 0;
}

/* ===== FUNCIONES PARA IMÁGENES DE PROPIEDADES ===== */

/* Crea la tabla de imágenes de propiedades y su relación con propiedades. */
function imagenes_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS imagenes_propiedades (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                propiedad_id INT UNSIGNED NOT NULL,
                nombre_archivo VARCHAR(255) NOT NULL,
                nombre_original VARCHAR(255),
                ruta_archivo VARCHAR(500) NOT NULL,
                es_principal BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_propiedad_id (propiedad_id),
                FOREIGN KEY (propiedad_id) REFERENCES propiedades(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $tabla_lista = true;
    } catch (PDOException $e) {
        // La tabla probablemente ya existe, ignorar error
        $tabla_lista = true;
    }
}

function imagen_subir(PDO $pdo, int $propiedad_id, array $archivo): ?int
{
    imagenes_asegurar_tabla($pdo);

    // Validar que el archivo sea una imagen con MIME real (no el del cliente)
    $mimes_a_ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_real = $finfo->file($archivo['tmp_name'] ?? '');
    if (!isset($mimes_a_ext[$mime_real])) {
        return null;
    }
    // Forzar extensión segura basada en MIME real (ignora la del cliente)
    $extension = $mimes_a_ext[$mime_real];

    // Limitar tamaño (10 MB)
    if (($archivo['size'] ?? 0) > 10 * 1024 * 1024) {
        return null;
    }

    // Crear directorio si no existe
    $dir_imagenes = __DIR__ . '/../storage/uploads/propiedades';
    if (!is_dir($dir_imagenes)) {
        mkdir($dir_imagenes, 0755, true);
    }

    // Generar nombre único para el archivo
    $nombre_archivo = uniqid('img_' . $propiedad_id . '_') . '.' . $extension;
    $ruta_destino = $dir_imagenes . '/' . $nombre_archivo;

    // Mover archivo subido
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        return null;
    }

    // Guardar en BD
    $stmt = $pdo->prepare(
        'INSERT INTO imagenes_propiedades (propiedad_id, nombre_archivo, nombre_original, ruta_archivo)
         VALUES (:propiedad_id, :nombre_archivo, :nombre_original, :ruta_archivo)'
    );

    $resultado = $stmt->execute([
        'propiedad_id' => $propiedad_id,
        'nombre_archivo' => $nombre_archivo,
        'nombre_original' => $archivo['name'] ?? '',
        'ruta_archivo' => '/storage/uploads/propiedades/' . $nombre_archivo,
    ]);

    return $resultado ? (int) $pdo->lastInsertId() : null;
}

/* Devuelve todas las imágenes de una propiedad priorizando la principal. */
function imagenes_obtener_propiedad(PDO $pdo, int $propiedad_id): array
{
    imagenes_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, ruta_archivo, nombre_original, es_principal
         FROM imagenes_propiedades
         WHERE propiedad_id = :propiedad_id
         ORDER BY es_principal DESC, created_at ASC'
    );

    $stmt->execute(['propiedad_id' => $propiedad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function imagen_obtener_principal(PDO $pdo, int $propiedad_id): ?array
{
    imagenes_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'SELECT ruta_archivo, nombre_original FROM imagenes_propiedades
         WHERE propiedad_id = :propiedad_id
         ORDER BY es_principal DESC, created_at ASC
         LIMIT 1'
    );

    $stmt->execute(['propiedad_id' => $propiedad_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ?: null;
}

/* Elimina imagen en BD y, si existe, su archivo físico en disco. Verifica tenant. */
function imagen_eliminar(PDO $pdo, int $id): bool
{
    imagenes_asegurar_tabla($pdo);

    // Obtener archivo verificando que pertenece a la inmobiliaria del usuario
    $sql = 'SELECT ip.ruta_archivo FROM imagenes_propiedades ip
            INNER JOIN propiedades p ON ip.propiedad_id = p.id
            WHERE ip.id = :id' . sql_iid('p');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id] + sql_iid_params());
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$imagen) {
        return false;
    }

    // Eliminar archivo físico
    $archivo = __DIR__ . '/..' . $imagen['ruta_archivo'];
    if (file_exists($archivo)) {
        unlink($archivo);
    }

    // Eliminar de BD
    $stmt = $pdo->prepare('DELETE FROM imagenes_propiedades WHERE id = :id');
    $resultado = $stmt->execute(['id' => $id]);

    return $resultado && $stmt->rowCount() > 0;
}

function imagen_marcar_principal(PDO $pdo, int $imagen_id): bool
{
    imagenes_asegurar_tabla($pdo);

    // Obtener propiedad_id verificando que pertenece a la inmobiliaria del usuario
    $sql = 'SELECT ip.propiedad_id FROM imagenes_propiedades ip
            INNER JOIN propiedades p ON ip.propiedad_id = p.id
            WHERE ip.id = :id' . sql_iid('p');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $imagen_id] + sql_iid_params());
    $imagen = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$imagen) {
        return false;
    }

    // Desmarcar todas las imágenes de esta propiedad
    $stmt = $pdo->prepare('UPDATE imagenes_propiedades SET es_principal = FALSE WHERE propiedad_id = :propiedad_id');
    $stmt->execute(['propiedad_id' => $imagen['propiedad_id']]);

    // Marcar esta como principal
    $stmt = $pdo->prepare('UPDATE imagenes_propiedades SET es_principal = TRUE WHERE id = :id');
    $resultado = $stmt->execute(['id' => $imagen_id]);

    return $resultado && $stmt->rowCount() > 0;
}

/* Crea/actualiza estructura de la tabla cacheada de propiedades scrapeadas. */
function scraped_propiedades_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS scraped_propiedades (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            fuente VARCHAR(50) NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            tipo VARCHAR(100) DEFAULT NULL,
            operacion VARCHAR(50) DEFAULT NULL,
            precio DECIMAL(12,2) DEFAULT NULL,
            moneda VARCHAR(10) DEFAULT "EUR",
            ubicacion VARCHAR(200) DEFAULT NULL,
            zona VARCHAR(120) DEFAULT NULL,
            ciudad VARCHAR(80) DEFAULT NULL,
            provincia VARCHAR(80) DEFAULT NULL,
            direccion VARCHAR(255) DEFAULT NULL,
            habitaciones TINYINT DEFAULT NULL,
            banos TINYINT DEFAULT NULL,
            metros INT DEFAULT NULL,
            descripcion TEXT DEFAULT NULL,
            imagen_url VARCHAR(500) DEFAULT NULL,
            imagenes_json LONGTEXT DEFAULT NULL,
            ascensor TINYINT DEFAULT NULL,
            url VARCHAR(400) NOT NULL,
            raw_hash CHAR(64) NOT NULL,
            scrape_run VARCHAR(80) DEFAULT NULL,
            scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_url (url),
            KEY idx_ciudad (ciudad),
            KEY idx_precio (precio),
            KEY idx_zona (zona),
            KEY idx_operacion (operacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    // Las columnas imagen_url, imagenes_json y ascensor ya están incluidas en el CREATE TABLE.
    // No se necesitan ALTER TABLE adicionales.

    $tabla_lista = true;
}

/* ===== FUNCIONES PARA VISITAS ===== */

/* Crea la tabla de visitas si no existe. */
function visitas_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS visitas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            equipo ENUM("vendedor","comprador") NOT NULL,
            propiedad_id INT UNSIGNED DEFAULT NULL,
            cliente_id INT UNSIGNED DEFAULT NULL,
            fecha_visita DATE NOT NULL,
            hora_visita TIME DEFAULT NULL,
            estado ENUM("pendiente","realizada","cancelada") NOT NULL DEFAULT "pendiente",
            observaciones TEXT DEFAULT NULL,
            recordatorio_id INT DEFAULT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visitas_equipo (equipo),
            INDEX idx_visitas_fecha (fecha_visita),
            INDEX idx_visitas_estado (estado),
            INDEX idx_visitas_recordatorio (recordatorio_id),
            INDEX idx_visitas_inmob (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $tabla_lista = true;
}

/* Crea una visita y opcionalmente sincroniza creando un recordatorio asociado. */
function visita_crear(PDO $pdo, string $equipo, ?int $propiedad_id, ?int $cliente_id, string $fecha, ?string $hora, string $observaciones, bool $crear_recordatorio = true): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    visitas_asegurar_tabla($pdo);

    $recordatorio_id = null;

    // Sincronización: crear recordatorio de tipo "Visita" automáticamente.
    if ($crear_recordatorio) {
        $desc_recordatorio = 'Visita programada';
        if ($propiedad_id) {
            $stmt = $pdo->prepare('SELECT titulo FROM propiedades WHERE id = :id');
            $stmt->execute(['id' => $propiedad_id]);
            $prop = $stmt->fetchColumn();
            if ($prop) {
                $desc_recordatorio = 'Visita: ' . $prop;
            }
        }
        if ($observaciones) {
            $desc_recordatorio .= ' - ' . $observaciones;
        }

        recordatorios_asegurar_tabla($pdo);
        $recordatorio_id = recordatorio_crear($pdo, 'Visita', $desc_recordatorio, $fecha, $hora, null);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO visitas (equipo, propiedad_id, cliente_id, fecha_visita, hora_visita, estado, observaciones, recordatorio_id, usuario_id, inmobiliaria_id)
         VALUES (:equipo, :propiedad_id, :cliente_id, :fecha, :hora, "pendiente", :observaciones, :recordatorio_id, :usuario_id, :iid)'
    );
    $stmt->execute([
        'equipo'          => $equipo,
        'propiedad_id'    => $propiedad_id ?: null,
        'cliente_id'      => $cliente_id ?: null,
        'fecha'           => $fecha,
        'hora'            => $hora ?: null,
        'observaciones'   => $observaciones,
        'recordatorio_id' => $recordatorio_id,
        'usuario_id'      => $usuario_id,
        'iid'             => usuario_inmobiliaria_id(),
    ]);

    return (int) $pdo->lastInsertId();
}

/* Crea una visita desde un recordatorio de tipo "Visita" (sincronización inversa). */
function visita_crear_desde_recordatorio(PDO $pdo, int $recordatorio_id, string $equipo = 'vendedor'): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    visitas_asegurar_tabla($pdo);

    // Verificar que no exista ya una visita vinculada a este recordatorio.
    $check = $pdo->prepare('SELECT id FROM visitas WHERE recordatorio_id = :rid');
    $check->execute(['rid' => $recordatorio_id]);
    if ($check->fetch()) {
        return null; // Ya existe
    }

    // Obtener datos del recordatorio.
    $rec = recordatorio_obtener($pdo, $recordatorio_id);
    if (!$rec) {
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO visitas (equipo, propiedad_id, cliente_id, fecha_visita, hora_visita, estado, observaciones, recordatorio_id, usuario_id, inmobiliaria_id)
         VALUES (:equipo, NULL, NULL, :fecha, :hora, "pendiente", :observaciones, :recordatorio_id, :usuario_id, :iid)'
    );
    $stmt->execute([
        'equipo'          => $equipo,
        'fecha'           => $rec['fecha_recordatorio'],
        'hora'            => $rec['hora_recordatorio'],
        'observaciones'   => $rec['descripcion'],
        'recordatorio_id' => $recordatorio_id,
        'usuario_id'      => $usuario_id,
        'iid'             => usuario_inmobiliaria_id(),
    ]);

    return (int) $pdo->lastInsertId();
}

/* Obtiene visitas para un equipo con datos de propiedad y cliente. */
function visitas_listar(PDO $pdo, string $equipo, string $filtro_estado = 'todos'): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    visitas_asegurar_tabla($pdo);

    $sql = 'SELECT v.*, 
                   p.titulo AS propiedad_titulo, p.referencia AS propiedad_ref, p.ubicacion AS propiedad_ubicacion,
                   c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.telefono AS cliente_telefono
            FROM visitas v
            LEFT JOIN propiedades p ON v.propiedad_id = p.id
            LEFT JOIN clientes c ON v.cliente_id = c.id
            WHERE v.equipo = :equipo AND v.usuario_id = :usuario_id' . sql_iid('v');

    $params = ['equipo' => $equipo, 'usuario_id' => $usuario_id] + sql_iid_params();

    if ($filtro_estado !== 'todos') {
        $sql .= ' AND v.estado = :estado';
        $params['estado'] = $filtro_estado;
    }

    $sql .= ' ORDER BY v.fecha_visita ASC, v.hora_visita ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Actualiza estado de una visita y sincroniza con su recordatorio vinculado. */
function visita_actualizar_estado(PDO $pdo, int $id, string $estado): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    visitas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('UPDATE visitas SET estado = :estado WHERE id = :id AND usuario_id = :usuario_id' . sql_iid());
    $stmt->execute(['estado' => $estado, 'id' => $id, 'usuario_id' => $usuario_id] + sql_iid_params());
    $ok = $stmt->rowCount() > 0;

    // Sincronizar estado con recordatorio vinculado
    if ($ok) {
        $stmt2 = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id');
        $stmt2->execute(['id' => $id]);
        $rid = $stmt2->fetchColumn();

        if ($rid) {
            $estado_rec = 'pendiente';
            if ($estado === 'realizada') $estado_rec = 'completado';
            if ($estado === 'cancelada') $estado_rec = 'cancelado';

            $pdo->prepare('UPDATE recordatorios SET estado = :estado WHERE id = :id AND usuario_id = :uid')
                ->execute(['estado' => $estado_rec, 'id' => $rid, 'uid' => $usuario_id]);
        }
    }

    return $ok;
}

/* Actualiza todos los campos de una visita. */
function visita_actualizar(PDO $pdo, int $id, ?int $propiedad_id, ?int $cliente_id, string $fecha, ?string $hora, string $estado, string $observaciones): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    visitas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'UPDATE visitas 
         SET propiedad_id = :propiedad_id, cliente_id = :cliente_id, fecha_visita = :fecha, 
             hora_visita = :hora, estado = :estado, observaciones = :observaciones
         WHERE id = :id AND usuario_id = :usuario_id' . sql_iid()
    );
    $resultado = $stmt->execute([
        'propiedad_id'  => $propiedad_id ?: null,
        'cliente_id'    => $cliente_id ?: null,
        'fecha'         => $fecha,
        'hora'          => $hora ?: null,
        'estado'        => $estado,
        'observaciones' => $observaciones,
        'id'            => $id,
        'usuario_id'    => $usuario_id,
    ] + sql_iid_params());

    $ok = $resultado && $stmt->rowCount() > 0;

    // Sincronizar cambios con recordatorio vinculado
    if ($ok) {
        $stmt2 = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id');
        $stmt2->execute(['id' => $id]);
        $rid = $stmt2->fetchColumn();

        if ($rid) {
            $estado_rec = 'pendiente';
            if ($estado === 'realizada') $estado_rec = 'completado';
            if ($estado === 'cancelada') $estado_rec = 'cancelado';

            $desc = 'Visita programada';
            if ($propiedad_id) {
                $stmtP = $pdo->prepare('SELECT titulo FROM propiedades WHERE id = :id');
                $stmtP->execute(['id' => $propiedad_id]);
                $titulo = $stmtP->fetchColumn();
                if ($titulo) $desc = 'Visita: ' . $titulo;
            }
            if ($observaciones) $desc .= ' - ' . $observaciones;

            $pdo->prepare(
                'UPDATE recordatorios SET tipo = "Visita", descripcion = :desc, fecha_recordatorio = :fecha, 
                 hora_recordatorio = :hora, estado = :estado WHERE id = :id AND usuario_id = :uid'
            )->execute([
                'desc'   => $desc,
                'fecha'  => $fecha,
                'hora'   => $hora ?: null,
                'estado' => $estado_rec,
                'id'     => $rid,
                'uid'    => $usuario_id,
            ]);
        }
    }

    return $ok;
}

/* Elimina una visita y opcionalmente su recordatorio vinculado. */
function visita_eliminar(PDO $pdo, int $id, bool $eliminar_recordatorio = true): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    visitas_asegurar_tabla($pdo);

    // Obtener recordatorio vinculado antes de eliminar
    $rid = null;
    if ($eliminar_recordatorio) {
        $stmt = $pdo->prepare('SELECT recordatorio_id FROM visitas WHERE id = :id AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['id' => $id, 'uid' => $usuario_id] + sql_iid_params());
        $rid = $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare('DELETE FROM visitas WHERE id = :id AND usuario_id = :usuario_id' . sql_iid());
    $resultado = $stmt->execute(['id' => $id, 'usuario_id' => $usuario_id] + sql_iid_params());
    $ok = $resultado && $stmt->rowCount() > 0;

    // Limpiar recordatorio vinculado
    if ($ok && $rid) {
        recordatorio_eliminar($pdo, (int) $rid);
    }

    return $ok;
}


/* =============================================
   OFERTAS — CRUD para gestión de ofertas por propiedad
   ============================================= */

/* Crea la tabla de ofertas si no existe. */
function ofertas_asegurar_tabla(PDO $pdo): void
{
    static $tabla_lista = false;

    if ($tabla_lista) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ofertas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            propiedad_id INT UNSIGNED NOT NULL,
            cliente_id INT UNSIGNED DEFAULT NULL,
            nombre_ofertante VARCHAR(200) DEFAULT NULL,
            importe DECIMAL(12,2) NOT NULL,
            fecha_oferta DATE NOT NULL,
            estado ENUM("pendiente","aceptada","rechazada","contraoferta") NOT NULL DEFAULT "pendiente",
            contraoferta_importe DECIMAL(12,2) DEFAULT NULL,
            notas TEXT DEFAULT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ofertas_propiedad (propiedad_id),
            INDEX idx_ofertas_estado (estado),
            INDEX idx_ofertas_fecha (fecha_oferta),
            INDEX idx_ofertas_inmobiliaria (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    try { $pdo->exec('ALTER TABLE ofertas ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER usuario_id'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE ofertas ADD INDEX idx_ofertas_inmobiliaria (inmobiliaria_id)'); } catch (PDOException $e) {}

    $tabla_lista = true;
}

/* Crea una nueva oferta. */
function oferta_crear(PDO $pdo, int $propiedad_id, ?int $cliente_id, ?string $nombre_ofertante, float $importe, string $fecha, string $notas = ''): ?int
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return null;
    }

    ofertas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO ofertas (propiedad_id, cliente_id, nombre_ofertante, importe, fecha_oferta, estado, notas, usuario_id, inmobiliaria_id)
         VALUES (:propiedad_id, :cliente_id, :nombre_ofertante, :importe, :fecha, "pendiente", :notas, :usuario_id, :iid)'
    );
    $stmt->execute([
        'propiedad_id'     => $propiedad_id,
        'cliente_id'       => $cliente_id ?: null,
        'nombre_ofertante' => $nombre_ofertante ?: null,
        'importe'          => $importe,
        'fecha'            => $fecha,
        'notas'            => $notas,
        'usuario_id'       => $usuario_id,
        'iid'              => usuario_inmobiliaria_id(),
    ]);

    return (int) $pdo->lastInsertId();
}

/* Lista ofertas con datos de propiedad y cliente. */
function ofertas_listar(PDO $pdo, string $filtro_estado = 'todos'): array
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return [];
    }

    ofertas_asegurar_tabla($pdo);

    $sql = 'SELECT o.*,
                   p.titulo AS propiedad_titulo, p.referencia AS propiedad_ref, p.precio AS propiedad_precio, p.ubicacion AS propiedad_ubicacion,
                   c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, c.telefono AS cliente_telefono
            FROM ofertas o
            LEFT JOIN propiedades p ON o.propiedad_id = p.id
            LEFT JOIN clientes c ON o.cliente_id = c.id
            WHERE o.usuario_id = :usuario_id' . sql_iid('o');

    $params = ['usuario_id' => $usuario_id] + sql_iid_params();

    if ($filtro_estado !== 'todos') {
        $sql .= ' AND o.estado = :estado';
        $params['estado'] = $filtro_estado;
    }

    $sql .= ' ORDER BY o.fecha_oferta DESC, o.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/* Actualiza estado de una oferta (y opcionalmente la contraoferta). */
function oferta_cambiar_estado(PDO $pdo, int $id, string $estado, ?float $contraoferta_importe = null): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    ofertas_asegurar_tabla($pdo);

    if ($estado === 'contraoferta' && $contraoferta_importe !== null) {
        $stmt = $pdo->prepare('UPDATE ofertas SET estado = :estado, contraoferta_importe = :contra WHERE id = :id AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['estado' => $estado, 'contra' => $contraoferta_importe, 'id' => $id, 'uid' => $usuario_id] + sql_iid_params());
    } else {
        $stmt = $pdo->prepare('UPDATE ofertas SET estado = :estado WHERE id = :id AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['estado' => $estado, 'id' => $id, 'uid' => $usuario_id] + sql_iid_params());
    }

    return $stmt->rowCount() > 0;
}

/* Actualiza todos los campos de una oferta. */
function oferta_actualizar(PDO $pdo, int $id, int $propiedad_id, ?int $cliente_id, ?string $nombre_ofertante, float $importe, string $fecha, string $estado, ?float $contraoferta_importe, string $notas): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    ofertas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare(
        'UPDATE ofertas
         SET propiedad_id = :propiedad_id, cliente_id = :cliente_id, nombre_ofertante = :nombre,
             importe = :importe, fecha_oferta = :fecha, estado = :estado,
             contraoferta_importe = :contra, notas = :notas
         WHERE id = :id AND usuario_id = :uid' . sql_iid()
    );
    $resultado = $stmt->execute([
        'propiedad_id' => $propiedad_id,
        'cliente_id'   => $cliente_id ?: null,
        'nombre'       => $nombre_ofertante ?: null,
        'importe'      => $importe,
        'fecha'        => $fecha,
        'estado'       => $estado,
        'contra'       => $contraoferta_importe,
        'notas'        => $notas,
        'id'           => $id,
        'uid'          => $usuario_id,
    ] + sql_iid_params());

    return $resultado && $stmt->rowCount() > 0;
}

/* Elimina una oferta. */
function oferta_eliminar(PDO $pdo, int $id): bool
{
    $usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($usuario_id <= 0) {
        return false;
    }

    ofertas_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM ofertas WHERE id = :id AND usuario_id = :uid' . sql_iid());
    $resultado = $stmt->execute(['id' => $id, 'uid' => $usuario_id] + sql_iid_params());

    return $resultado && $stmt->rowCount() > 0;
}


/* =============================================
   7. CSRF TOKENS — Protección contra ataques CSRF
   ============================================= */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        http_response_code(403);
        echo '<p style="color:red;text-align:center;margin-top:3rem;">⛔ Token CSRF inválido. Recarga la página e intenta de nuevo.</p>';
        exit;
    }
}

/* Verifica CSRF desde APIs (header X-CSRF-Token, JSON body o POST). */
function csrf_verify_api(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token) {
        $json = json_decode(file_get_contents('php://input'), true);
        $token = $json['_csrf_token'] ?? $_POST['_csrf_token'] ?? $_GET['_csrf_token'] ?? '';
    }
    if (empty($token) || empty($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
        exit;
    }
}


/* =============================================
   8. HISTORIAL DE ACTIVIDAD
   ============================================= */

function actividad_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS actividad_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            usuario_nombre VARCHAR(100) DEFAULT NULL,
            accion VARCHAR(50) NOT NULL,
            entidad VARCHAR(50) NOT NULL,
            entidad_id INT UNSIGNED DEFAULT NULL,
            descripcion TEXT DEFAULT NULL,
            datos_extra JSON DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_act_usr (usuario_id),
            INDEX idx_act_ent (entidad, entidad_id),
            INDEX idx_act_fecha (created_at),
            INDEX idx_act_inmobiliaria (inmobiliaria_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    try { $pdo->exec('ALTER TABLE actividad_log ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER ip'); } catch (PDOException $e) {}
    try { $pdo->exec('ALTER TABLE actividad_log ADD INDEX idx_act_inmobiliaria (inmobiliaria_id)'); } catch (PDOException $e) {}
    $ok = true;
}

function actividad_registrar(PDO $pdo, string $accion, string $entidad, ?int $entidad_id = null, string $descripcion = '', array $datos_extra = []): void
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    $nombre = $_SESSION['usuario']['nombre'] ?? 'Sistema';
    if ($uid <= 0) return;
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO actividad_log (usuario_id, usuario_nombre, accion, entidad, entidad_id, descripcion, datos_extra, ip, inmobiliaria_id)
         VALUES (:uid, :nombre, :accion, :entidad, :eid, :desc, :datos, :ip, :iid)'
    );
    $stmt->execute([
        'uid' => $uid, 'nombre' => $nombre, 'accion' => $accion,
        'entidad' => $entidad, 'eid' => $entidad_id, 'desc' => $descripcion,
        'datos' => !empty($datos_extra) ? json_encode($datos_extra) : null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'iid' => usuario_inmobiliaria_id(),
    ]);
}

function actividad_listar(PDO $pdo, int $limite = 20, int $offset = 0): array
{
    actividad_asegurar_tabla($pdo);
    [$iid_cond, $iid_p] = sql_iid_pair();
    $sql = 'SELECT * FROM actividad_log WHERE 1=1' . $iid_cond . ' ORDER BY created_at DESC LIMIT :lim OFFSET :off';
    $stmt = $pdo->prepare($sql);
    foreach ($iid_p as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function actividad_contar(PDO $pdo): int
{
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM actividad_log WHERE 1=1' . sql_iid());
    $stmt->execute(sql_iid_params());
    return (int) $stmt->fetchColumn();
}


/* =============================================
   9. NOTIFICACIONES INTELIGENTES
   ============================================= */

function notificaciones_generar(PDO $pdo): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];
    $notifs = [];

    try {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM visitas WHERE fecha_visita = CURDATE() AND estado = "pendiente" AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['uid' => $uid] + sql_iid_params());
        $n = (int) $stmt->fetchColumn();
        // El link de visitas apunta al equipo que puede ver el usuario actual.
        $seccion_visitas = puede_ver_vendedor() ? 'visitas-vendedor' : 'visitas-comprador';
        if ($n > 0) $notifs[] = ['tipo' => 'info', 'icono' => '📅', 'texto' => "Tienes {$n} visita(s) hoy", 'enlace' => '?seccion=' . $seccion_visitas];
    } catch (PDOException $e) {}

    try {
        ofertas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ofertas WHERE estado = "pendiente" AND fecha_oferta < DATE_SUB(CURDATE(), INTERVAL 5 DAY) AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['uid' => $uid] + sql_iid_params());
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'aviso', 'icono' => '⚠️', 'texto' => "{$n} oferta(s) pendiente(s) hace +5 días", 'enlace' => '?seccion=ofertas-vendedor'];
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM prospectos WHERE estado = "nuevo" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY) AND usuario_id = :uid' . sql_iid());
        $stmt->execute(['uid' => $uid] + sql_iid_params());
        $n = (int) $stmt->fetchColumn();
        $seccion_prospectos = puede_ver_vendedor() ? 'prospectos-vendedor' : 'prospectos-comprador';
        if ($n > 0) $notifs[] = ['tipo' => 'aviso', 'icono' => '👤', 'texto' => "{$n} prospecto(s) sin contactar hace +2 semanas", 'enlace' => '?seccion=' . $seccion_prospectos];
    } catch (PDOException $e) {}

    try {
        recordatorios_asegurar_tabla($pdo);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE fecha_recordatorio = CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'info', 'icono' => '🔔', 'texto' => "{$n} recordatorio(s) para hoy", 'enlace' => '?seccion=recordatorios'];
    } catch (PDOException $e) {}

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM recordatorios WHERE fecha_recordatorio < CURDATE() AND estado = "pendiente" AND usuario_id = :uid');
        $stmt->execute(['uid' => $uid]);
        $n = (int) $stmt->fetchColumn();
        if ($n > 0) $notifs[] = ['tipo' => 'peligro', 'icono' => '🚨', 'texto' => "{$n} recordatorio(s) atrasado(s)", 'enlace' => '?seccion=recordatorios'];
    } catch (PDOException $e) {}

    return $notifs;
}


/* =============================================
   10. CACHÉ DE CONSULTAS
   ============================================= */

function cache_get(string $clave, int $ttl = 60): mixed
{
    if (!isset($_SESSION['_cache'][$clave])) return null;
    $e = $_SESSION['_cache'][$clave];
    if (time() - $e['ts'] > $ttl) { unset($_SESSION['_cache'][$clave]); return null; }
    return $e['valor'];
}

function cache_set(string $clave, mixed $valor): void
{
    $_SESSION['_cache'][$clave] = ['valor' => $valor, 'ts' => time()];
}

function cache_flush(): void { $_SESSION['_cache'] = []; }


/* =============================================
   11. EXPORTACIÓN CSV
   ============================================= */

function exportar_csv(array $datos, string $nombre = 'export.csv'): void
{
    if (empty($datos)) { header('Content-Type: text/plain'); echo 'Sin datos.'; exit; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, array_keys($datos[0]), ';');
    foreach ($datos as $fila) fputcsv($out, $fila, ';');
    fclose($out);
    exit;
}


/* =============================================
   12. BREADCRUMBS
   ============================================= */

function generar_breadcrumbs(string $seccion): array
{
    $migas = [['titulo' => 'Inicio', 'url' => '?seccion=dashboard']];
    $mapa = [
        'dashboard'             => [['titulo' => 'Dashboard']],
        'recordatorios'         => [['titulo' => 'Recordatorios']],
        'documentacion'         => [['titulo' => 'Documentación']],
        'configuracion'         => [['titulo' => 'Sistema'], ['titulo' => 'Configuración']],
        'clientes-vendedor'     => [['titulo' => 'Vendedor'], ['titulo' => 'Clientes']],
        'clientes-comprador'    => [['titulo' => 'Comprador'], ['titulo' => 'Clientes']],
        'prospectos-vendedor'   => [['titulo' => 'Vendedor'], ['titulo' => 'Prospectos']],
        'prospectos-comprador'  => [['titulo' => 'Comprador'], ['titulo' => 'Prospectos']],
        'propiedades-vendedor'  => [['titulo' => 'Vendedor'], ['titulo' => 'Propiedades']],
        'propiedades-comprador' => [['titulo' => 'Comprador'], ['titulo' => 'Propiedades']],
        'alquileres-vendedor'   => [['titulo' => 'Vendedor'], ['titulo' => 'Alquileres']],
        'alquileres-comprador'  => [['titulo' => 'Comprador'], ['titulo' => 'Alquileres']],
        'busqueda-avanzada'     => [['titulo' => 'Búsqueda Avanzada']],
        'proceso-vendedor'      => [['titulo' => 'Vendedor'], ['titulo' => 'Proceso']],
        'proceso-comprador'     => [['titulo' => 'Comprador'], ['titulo' => 'Proceso']],
        'visitas-vendedor'      => [['titulo' => 'Vendedor'], ['titulo' => 'Visitas']],
        'visitas-comprador'     => [['titulo' => 'Comprador'], ['titulo' => 'Visitas']],
        'ofertas-vendedor'      => [['titulo' => 'Vendedor'], ['titulo' => 'Ofertas']],
        'matching'              => [['titulo' => 'Matching']],
        'post-venta'            => [['titulo' => 'Post-Venta']],
        'importar-csv'          => [['titulo' => 'Sistema'], ['titulo' => 'Importar CSV']],
        'ver_propiedad'         => [['titulo' => 'Propiedades'], ['titulo' => 'Detalle']],
        'ver_cliente'           => [['titulo' => 'Clientes'], ['titulo' => 'Detalle']],
        'actividad'             => [['titulo' => 'Sistema'], ['titulo' => 'Historial']],
        'admin-dashboard'       => [['titulo' => 'Admin'], ['titulo' => 'Panel de Control']],
        'admin-inmobiliarias'   => [['titulo' => 'Admin'], ['titulo' => 'Inmobiliarias']],
        'admin-usuarios'        => [['titulo' => 'Admin'], ['titulo' => 'Usuarios']],
        'peticiones'            => [['titulo' => 'Sistema'], ['titulo' => 'Peticiones']],
        'legal'                 => [['titulo' => 'Marco Legal']],
    ];
    if (isset($mapa[$seccion])) foreach ($mapa[$seccion] as $m) $migas[] = $m;
    return $migas;
}

function renderizar_breadcrumbs(string $seccion): string
{
    $migas = generar_breadcrumbs($seccion);
    $html = '<nav class="breadcrumbs" aria-label="Navegación"><ol>';
    $total = count($migas);
    foreach ($migas as $i => $m) {
        if ($i === $total - 1) {
            $html .= '<li class="breadcrumb_actual" aria-current="page">' . e($m['titulo']) . '</li>';
        } elseif (!empty($m['url'])) {
            $html .= '<li><a href="' . e($m['url']) . '">' . e($m['titulo']) . '</a></li>';
        } else {
            $html .= '<li>' . e($m['titulo']) . '</li>';
        }
    }
    $html .= '</ol></nav>';
    return $html;
}


/* =============================================
   13. PAGINACIÓN
   ============================================= */

function paginar(int $total, int $por_pagina = 15, int $pagina_actual = 1): array
{
    $total_pag = max(1, (int) ceil($total / $por_pagina));
    $pagina_actual = max(1, min($pagina_actual, $total_pag));
    return [
        'total' => $total, 'por_pagina' => $por_pagina,
        'pagina_actual' => $pagina_actual, 'total_paginas' => $total_pag,
        'offset' => ($pagina_actual - 1) * $por_pagina,
        'tiene_anterior' => $pagina_actual > 1,
        'tiene_siguiente' => $pagina_actual < $total_pag,
    ];
}

function renderizar_paginacion(array $pag, string $base_url): string
{
    if ($pag['total_paginas'] <= 1) return '';
    $sep = str_contains($base_url, '?') ? '&' : '?';
    $html = '<nav class="paginacion"><ul>';
    if ($pag['tiene_anterior']) {
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=' . ($pag['pagina_actual'] - 1)) . '" class="pag_btn">← Anterior</a></li>';
    } else {
        $html .= '<li><span class="pag_btn pag_disabled">← Anterior</span></li>';
    }
    $rango = 2;
    $inicio = max(1, $pag['pagina_actual'] - $rango);
    $fin = min($pag['total_paginas'], $pag['pagina_actual'] + $rango);
    if ($inicio > 1) {
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=1') . '" class="pag_btn">1</a></li>';
        if ($inicio > 2) $html .= '<li><span class="pag_dots">…</span></li>';
    }
    for ($p = $inicio; $p <= $fin; $p++) {
        $html .= ($p === $pag['pagina_actual'])
            ? '<li><span class="pag_btn pag_activa">' . $p . '</span></li>'
            : '<li><a href="' . e($base_url . $sep . 'pagina=' . $p) . '" class="pag_btn">' . $p . '</a></li>';
    }
    if ($fin < $pag['total_paginas']) {
        if ($fin < $pag['total_paginas'] - 1) $html .= '<li><span class="pag_dots">…</span></li>';
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=' . $pag['total_paginas']) . '" class="pag_btn">' . $pag['total_paginas'] . '</a></li>';
    }
    if ($pag['tiene_siguiente']) {
        $html .= '<li><a href="' . e($base_url . $sep . 'pagina=' . ($pag['pagina_actual'] + 1)) . '" class="pag_btn">Siguiente →</a></li>';
    } else {
        $html .= '<li><span class="pag_btn pag_disabled">Siguiente →</span></li>';
    }
    $html .= '</ul></nav>';
    $html .= '<p class="pag_info">Página ' . $pag['pagina_actual'] . ' de ' . $pag['total_paginas'] . ' (' . $pag['total'] . ' registros)</p>';
    return $html;
}


/* =============================================
   14. BÚSQUEDA GLOBAL
   ============================================= */

function busqueda_global(PDO $pdo, string $termino, int $limite = 10): array
{
    $resultados = [];
    $like = '%' . $termino . '%';

    $stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email, tipo FROM clientes WHERE (nombre LIKE :q OR apellido LIKE :q2 OR email LIKE :q3 OR telefono LIKE :q4)' . sql_iid() . ' LIMIT :lim');
    $stmt->bindValue('q', $like); $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like); $stmt->bindValue('q4', $like);
    foreach (sql_iid_params() as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $resultados[] = ['tipo' => 'cliente', 'icono' => '👤', 'titulo' => $r['nombre'] . ' ' . $r['apellido'],
            'detalle' => $r['email'] . ' · ' . $r['telefono'],
            'url' => 'index.php?seccion=ver_cliente&id=' . $r['id'] . '&origen=clientes-' . $r['tipo']];
    }

    $stmt = $pdo->prepare('SELECT id, titulo, ubicacion, referencia, operacion, equipo FROM propiedades WHERE (titulo LIKE :q OR ubicacion LIKE :q2 OR referencia LIKE :q3 OR direccion LIKE :q4)' . sql_iid() . ' LIMIT :lim');
    $stmt->bindValue('q', $like); $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like); $stmt->bindValue('q4', $like);
    foreach (sql_iid_params() as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
    $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $r) {
        $origen = obtener_origen_propiedad($r['operacion'], $r['equipo']);
        $resultados[] = ['tipo' => 'propiedad', 'icono' => '🏠', 'titulo' => $r['titulo'],
            'detalle' => $r['ubicacion'] . ($r['referencia'] ? ' · ' . $r['referencia'] : ''),
            'url' => 'index.php?seccion=ver_propiedad&id=' . $r['id'] . '&origen=' . $origen];
    }

    try {
        $stmt = $pdo->prepare('SELECT id, nombre, telefono, tipo FROM prospectos WHERE (nombre LIKE :q OR telefono LIKE :q2)' . sql_iid() . ' LIMIT :lim');
        $stmt->bindValue('q', $like); $stmt->bindValue('q2', $like);
        foreach (sql_iid_params() as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $r) {
            $resultados[] = ['tipo' => 'prospecto', 'icono' => '📊', 'titulo' => $r['nombre'],
                'detalle' => $r['telefono'] ?? '', 'url' => 'index.php?seccion=prospectos-' . $r['tipo']];
        }
    } catch (PDOException $e) {}

    return $resultados;
}


/* =============================================
   15. EDITAR Y ELIMINAR NOTAS
   ============================================= */

function nota_actualizar(PDO $pdo, int $id, string $texto, string $tipo): bool
{
    [$cond, $params] = sql_iid_pair();
    $stmt = $pdo->prepare('UPDATE notas SET texto = :texto, tipo = :tipo WHERE id = :id' . $cond);
    $stmt->execute(['texto' => $texto, 'tipo' => $tipo, 'id' => $id] + $params);
    return $stmt->rowCount() > 0;
}

function nota_eliminar(PDO $pdo, int $id): bool
{
    [$cond, $params] = sql_iid_pair();
    $stmt = $pdo->prepare('DELETE FROM notas WHERE id = :id' . $cond);
    $stmt->execute(['id' => $id] + $params);
    return $stmt->rowCount() > 0;
}


/* =============================================
   16. SISTEMA DE ETIQUETAS/TAGS
   ============================================= */

function etiquetas_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS etiquetas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT "#3b82f6",
            usuario_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uniq_nombre_usr (nombre, usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS entidad_etiquetas (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entidad_tipo VARCHAR(30) NOT NULL,
            entidad_id INT UNSIGNED NOT NULL,
            etiqueta_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uniq_ent_tag (entidad_tipo, entidad_id, etiqueta_id),
            INDEX idx_entidad (entidad_tipo, entidad_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

function etiqueta_crear(PDO $pdo, string $nombre, string $color): ?int
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return null;
    etiquetas_asegurar_tabla($pdo);
    try {
        $stmt = $pdo->prepare('INSERT INTO etiquetas (nombre, color, usuario_id) VALUES (:nombre, :color, :uid)');
        $stmt->execute(['nombre' => $nombre, 'color' => $color, 'uid' => $uid]);
        return (int) $pdo->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

function etiquetas_listar(PDO $pdo): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];
    etiquetas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM etiquetas WHERE usuario_id = :uid ORDER BY nombre');
    $stmt->execute(['uid' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function etiqueta_eliminar(PDO $pdo, int $id): bool
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    etiquetas_asegurar_tabla($pdo);
    $pdo->prepare('DELETE FROM entidad_etiquetas WHERE etiqueta_id = :id')->execute(['id' => $id]);
    $stmt = $pdo->prepare('DELETE FROM etiquetas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    return $stmt->rowCount() > 0;
}

function entidad_asignar_etiqueta(PDO $pdo, string $tipo, int $entidad_id, int $etiqueta_id): bool
{
    etiquetas_asegurar_tabla($pdo);
    try {
        $stmt = $pdo->prepare('INSERT INTO entidad_etiquetas (entidad_tipo, entidad_id, etiqueta_id) VALUES (:tipo, :eid, :tid)');
        $stmt->execute(['tipo' => $tipo, 'eid' => $entidad_id, 'tid' => $etiqueta_id]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function entidad_quitar_etiqueta(PDO $pdo, string $tipo, int $entidad_id, int $etiqueta_id): bool
{
    etiquetas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('DELETE FROM entidad_etiquetas WHERE entidad_tipo = :tipo AND entidad_id = :eid AND etiqueta_id = :tid');
    $stmt->execute(['tipo' => $tipo, 'eid' => $entidad_id, 'tid' => $etiqueta_id]);
    return $stmt->rowCount() > 0;
}

function entidad_obtener_etiquetas(PDO $pdo, string $tipo, int $entidad_id): array
{
    etiquetas_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT e.* FROM etiquetas e
         INNER JOIN entidad_etiquetas ee ON e.id = ee.etiqueta_id
         WHERE ee.entidad_tipo = :tipo AND ee.entidad_id = :eid
         ORDER BY e.nombre'
    );
    $stmt->execute(['tipo' => $tipo, 'eid' => $entidad_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}


/* =============================================
   17. DUPLICAR PROPIEDAD
   ============================================= */

function propiedad_duplicar(PDO $pdo, int $id): ?int
{
    $stmt = $pdo->prepare('SELECT * FROM propiedades WHERE id = :id' . sql_iid());
    $stmt->execute(['id' => $id] + sql_iid_params());
    $prop = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prop) return null;

    unset($prop['id'], $prop['created_at'], $prop['updated_at']);
    $prop['titulo'] = $prop['titulo'] . ' (copia)';
    $prop['referencia'] = $prop['referencia'] ? $prop['referencia'] . '-COPIA' : '';
    $prop['estado'] = 'Disponible';

    $cols = implode(', ', array_keys($prop));
    $placeholders = ':' . implode(', :', array_keys($prop));
    $stmt = $pdo->prepare("INSERT INTO propiedades ($cols) VALUES ($placeholders)");
    $stmt->execute($prop);
    return (int) $pdo->lastInsertId();
}


/* =============================================
   18. IMPORTACIÓN MASIVA CSV
   ============================================= */

function importar_csv_clientes(PDO $pdo, string $filepath, string $tipo): array
{
    $resultado = ['importados' => 0, 'errores' => [], 'lineas_error' => []];
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        $resultado['errores'][] = 'No se pudo abrir el archivo.';
        return $resultado;
    }

    // Detectar BOM UTF-8
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $cabeceras = fgetcsv($handle, 0, ';');
    if (!$cabeceras) {
        $resultado['errores'][] = 'No se pudieron leer las cabeceras.';
        fclose($handle);
        return $resultado;
    }
    $cabeceras = array_map('strtolower', array_map('trim', $cabeceras));

    $campos_requeridos = ['nombre', 'apellido'];
    foreach ($campos_requeridos as $campo) {
        if (!in_array($campo, $cabeceras)) {
            $resultado['errores'][] = "Falta columna obligatoria: $campo";
            fclose($handle);
            return $resultado;
        }
    }

    $linea = 1;
    $stmt = $pdo->prepare(
        'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, direccion, zona_interesada, presupuesto, comentarios, inmobiliaria_id, usuario_id)
         VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :direccion, :zona, :presupuesto, :comentarios, :iid, :uid)'
    );

    while (($fila = fgetcsv($handle, 0, ';')) !== false) {
        $linea++;
        if (count($fila) < count($cabeceras)) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        $datos = array_combine($cabeceras, $fila);
        $nombre = trim($datos['nombre'] ?? '');
        $apellido = trim($datos['apellido'] ?? '');
        if (!$nombre || !$apellido) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        try {
            $stmt->execute([
                'tipo' => $tipo,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'telefono' => trim($datos['telefono'] ?? ''),
                'email' => trim($datos['email'] ?? ''),
                'operacion' => trim($datos['operacion'] ?? 'Venta'),
                'direccion' => trim($datos['direccion'] ?? ''),
                'zona' => trim($datos['zona_interesada'] ?? $datos['zona'] ?? ''),
                'presupuesto' => is_numeric($datos['presupuesto'] ?? '') ? (float)$datos['presupuesto'] : null,
                'comentarios' => trim($datos['comentarios'] ?? ''),
                'iid' => usuario_inmobiliaria_id(),
                'uid' => $_SESSION['usuario']['id'] ?? null,
            ]);
            $resultado['importados']++;
        } catch (PDOException $e) {
            $resultado['lineas_error'][] = $linea;
        }
    }
    fclose($handle);
    return $resultado;
}

function importar_csv_propiedades(PDO $pdo, string $filepath, string $equipo): array
{
    $resultado = ['importados' => 0, 'errores' => [], 'lineas_error' => []];
    $handle = fopen($filepath, 'r');
    if (!$handle) {
        $resultado['errores'][] = 'No se pudo abrir el archivo.';
        return $resultado;
    }

    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    $cabeceras = fgetcsv($handle, 0, ';');
    if (!$cabeceras) {
        $resultado['errores'][] = 'No se pudieron leer las cabeceras.';
        fclose($handle);
        return $resultado;
    }
    $cabeceras = array_map('strtolower', array_map('trim', $cabeceras));

    if (!in_array('titulo', $cabeceras)) {
        $resultado['errores'][] = 'Falta columna obligatoria: titulo';
        fclose($handle);
        return $resultado;
    }

    $linea = 1;
    $stmt = $pdo->prepare(
        'INSERT INTO propiedades (equipo, titulo, tipo, ubicacion, direccion, metros, habitaciones, banos, precio, moneda, operacion, estado, referencia, descripcion, inmobiliaria_id, usuario_id)
         VALUES (:equipo, :titulo, :tipo, :ubicacion, :direccion, :metros, :hab, :banos, :precio, :moneda, :operacion, :estado, :ref, :desc, :iid, :uid)'
    );

    while (($fila = fgetcsv($handle, 0, ';')) !== false) {
        $linea++;
        if (count($fila) < count($cabeceras)) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        $datos = array_combine($cabeceras, $fila);
        $titulo = trim($datos['titulo'] ?? '');
        if (!$titulo) {
            $resultado['lineas_error'][] = $linea;
            continue;
        }
        try {
            $stmt->execute([
                'equipo' => $equipo,
                'titulo' => $titulo,
                'tipo' => trim($datos['tipo'] ?? 'Piso'),
                'ubicacion' => trim($datos['ubicacion'] ?? ''),
                'direccion' => trim($datos['direccion'] ?? ''),
                'metros' => is_numeric($datos['metros'] ?? '') ? (int)$datos['metros'] : null,
                'hab' => is_numeric($datos['habitaciones'] ?? '') ? (int)$datos['habitaciones'] : null,
                'banos' => is_numeric($datos['banos'] ?? $datos['baños'] ?? '') ? (int)($datos['banos'] ?? $datos['baños'] ?? 0) : null,
                'precio' => is_numeric($datos['precio'] ?? '') ? (float)$datos['precio'] : 0,
                'moneda' => trim($datos['moneda'] ?? 'EUR'),
                'operacion' => strtolower(trim($datos['operacion'] ?? 'venta')),
                'estado' => trim($datos['estado'] ?? 'Disponible'),
                'ref' => trim($datos['referencia'] ?? ''),
                'desc' => trim($datos['descripcion'] ?? ''),
                'iid' => usuario_inmobiliaria_id(),
                'uid' => $_SESSION['usuario']['id'] ?? null,
            ]);
            $resultado['importados']++;
        } catch (PDOException $e) {
            $resultado['lineas_error'][] = $linea;
        }
    }
    fclose($handle);
    return $resultado;
}


/* =============================================
   19. FILTROS GUARDADOS
   ============================================= */

function filtros_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS filtros_guardados (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT UNSIGNED NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            seccion VARCHAR(50) NOT NULL,
            parametros JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_filtro_usr (usuario_id, seccion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

function filtro_guardar(PDO $pdo, string $nombre, string $seccion, array $parametros): ?int
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return null;
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('INSERT INTO filtros_guardados (usuario_id, nombre, seccion, parametros) VALUES (:uid, :nombre, :seccion, :params)');
    $stmt->execute(['uid' => $uid, 'nombre' => $nombre, 'seccion' => $seccion, 'params' => json_encode($parametros)]);
    return (int) $pdo->lastInsertId();
}

function filtros_listar(PDO $pdo, string $seccion): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('SELECT * FROM filtros_guardados WHERE usuario_id = :uid AND seccion = :seccion ORDER BY nombre');
    $stmt->execute(['uid' => $uid, 'seccion' => $seccion]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function filtro_eliminar(PDO $pdo, int $id): bool
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    filtros_asegurar_tabla($pdo);
    $stmt = $pdo->prepare('DELETE FROM filtros_guardados WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    return $stmt->rowCount() > 0;
}


/* =============================================
   20. MATCHING AUTOMÁTICO COMPRADOR-PROPIEDAD
   ============================================= */

function matching_buscar(PDO $pdo, int $limite = 20): array
{
    $uid = (int) ($_SESSION['usuario']['id'] ?? 0);
    if ($uid <= 0) return [];

    $compradores = $pdo->prepare(
        "SELECT id, nombre, apellido, zona_interesada, presupuesto, operacion
         FROM clientes WHERE tipo = 'comprador' AND zona_interesada IS NOT NULL AND zona_interesada != ''" . sql_iid() . "
         ORDER BY id DESC LIMIT 50"
    );
    $compradores->execute(sql_iid_params());
    $compradores = $compradores->fetchAll(PDO::FETCH_ASSOC);

    $matches = [];
    foreach ($compradores as $comp) {
        $sql = "SELECT id, titulo, ubicacion, precio, moneda, operacion, estado, metros, habitaciones
                FROM propiedades WHERE estado = 'Disponible'" . sql_iid();
        $params = [] + sql_iid_params();

        if ($comp['zona_interesada']) {
            $sql .= ' AND (ubicacion LIKE :zona OR direccion LIKE :zona2)';
            $params['zona'] = '%' . $comp['zona_interesada'] . '%';
            $params['zona2'] = '%' . $comp['zona_interesada'] . '%';
        }
        if ($comp['presupuesto'] && $comp['presupuesto'] > 0) {
            $sql .= ' AND precio <= :max_precio';
            $params['max_precio'] = $comp['presupuesto'] * 1.15; // 15% tolerancia
        }

        $sql .= ' ORDER BY precio ASC LIMIT 5';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $props = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($props)) {
            $matches[] = [
                'comprador' => $comp,
                'propiedades' => $props,
                'coincidencias' => count($props),
            ];
        }
    }

    usort($matches, fn($a, $b) => $b['coincidencias'] <=> $a['coincidencias']);
    return array_slice($matches, 0, $limite);
}


/* =============================================
   21. TIMELINE DE ACTIVIDAD POR ENTIDAD
   ============================================= */

function timeline_entidad(PDO $pdo, string $entidad_tipo, int $entidad_id): array
{
    $timeline = [];

    // Notas
    $stmt = $pdo->prepare(
        "SELECT 'nota' AS origen, tipo AS subtipo, texto AS descripcion, created_at
         FROM notas WHERE entity_type = :tipo AND entity_id = :id" . sql_iid() . " ORDER BY created_at DESC"
    );
    $stmt->execute(['tipo' => $entidad_tipo, 'id' => $entidad_id] + sql_iid_params());
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Actividad log
    actividad_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        "SELECT 'actividad' AS origen, accion AS subtipo, descripcion, created_at
         FROM actividad_log WHERE entidad = :tipo AND entidad_id = :id" . sql_iid() . " ORDER BY created_at DESC"
    );
    $stmt->execute(['tipo' => $entidad_tipo, 'id' => $entidad_id] + sql_iid_params());
    $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // Visitas (solo para clientes o propiedades)
    if ($entidad_tipo === 'cliente') {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'visita' AS origen, estado AS subtipo, observaciones AS descripcion, fecha_visita AS created_at
             FROM visitas WHERE cliente_id = :id" . sql_iid() . " ORDER BY fecha_visita DESC"
        );
        $stmt->execute(['id' => $entidad_id] + sql_iid_params());
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if ($entidad_tipo === 'propiedad') {
        visitas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'visita' AS origen, estado AS subtipo, observaciones AS descripcion, fecha_visita AS created_at
             FROM visitas WHERE propiedad_id = :id" . sql_iid() . " ORDER BY fecha_visita DESC"
        );
        $stmt->execute(['id' => $entidad_id] + sql_iid_params());
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));

        ofertas_asegurar_tabla($pdo);
        $stmt = $pdo->prepare(
            "SELECT 'oferta' AS origen, estado AS subtipo, CONCAT('Oferta: ', FORMAT(importe, 0)) AS descripcion, fecha_oferta AS created_at
             FROM ofertas WHERE propiedad_id = :id" . sql_iid() . " ORDER BY fecha_oferta DESC"
        );
        $stmt->execute(['id' => $entidad_id] + sql_iid_params());
        $timeline = array_merge($timeline, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Ordenar cronológicamente (más reciente primero)
    usort($timeline, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
    return $timeline;
}

/* =============================================
   22. PROCESO PROPIEDADES – ASEGURAR TABLA
   ============================================= */

function proceso_propiedades_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS proceso_propiedades (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            propiedad_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL DEFAULT 0,
            inmobiliaria_id INT UNSIGNED DEFAULT NULL,
            equipo ENUM("vendedor","comprador") NOT NULL,
            etapa VARCHAR(50) NOT NULL DEFAULT "captacion",
            notas TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_proceso_propiedad (propiedad_id),
            INDEX idx_proceso_equipo (equipo),
            INDEX idx_proceso_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    /* Migración: añadir usuario_id si la tabla ya existía sin ella */
    try {
        $pdo->exec('ALTER TABLE proceso_propiedades ADD COLUMN usuario_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER propiedad_id');
    } catch (\PDOException $e) {
        /* columna ya existe – ignorar */
    }
    try {
        $pdo->exec('ALTER TABLE proceso_propiedades ADD COLUMN inmobiliaria_id INT UNSIGNED DEFAULT NULL AFTER usuario_id');
    } catch (\PDOException $e) {}

    $ok = true;
}

/* ---- Peticiones / Tickets (Jefe → SuperAdmin) ---- */

function peticiones_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS peticiones (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            inmobiliaria_id INT UNSIGNED NOT NULL,
            usuario_id INT UNSIGNED NOT NULL,
            tipo ENUM("error","mejora","consulta","urgente") NOT NULL DEFAULT "error",
            prioridad ENUM("baja","media","alta","critica") NOT NULL DEFAULT "media",
            estado ENUM("abierta","en_progreso","resuelta","cerrada") NOT NULL DEFAULT "abierta",
            asunto VARCHAR(200) NOT NULL,
            descripcion TEXT NOT NULL,
            respuesta_admin TEXT DEFAULT NULL,
            seccion_afectada VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resuelto_at DATETIME DEFAULT NULL,
            INDEX idx_pet_inmob (inmobiliaria_id),
            INDEX idx_pet_estado (estado),
            INDEX idx_pet_tipo (tipo),
            INDEX idx_pet_prioridad (prioridad)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

function peticion_crear(PDO $pdo, array $datos): ?int
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO peticiones (inmobiliaria_id, usuario_id, tipo, prioridad, asunto, descripcion, seccion_afectada)
         VALUES (:iid, :uid, :tipo, :prioridad, :asunto, :descripcion, :seccion)'
    );
    $stmt->execute([
        'iid'         => $datos['inmobiliaria_id'],
        'uid'         => $datos['usuario_id'],
        'tipo'        => $datos['tipo'] ?? 'error',
        'prioridad'   => $datos['prioridad'] ?? 'media',
        'asunto'      => $datos['asunto'],
        'descripcion' => $datos['descripcion'],
        'seccion'     => $datos['seccion_afectada'] ?? null,
    ]);
    return (int) $pdo->lastInsertId() ?: null;
}

function peticiones_listar_todas(PDO $pdo, ?string $filtro_estado = null): array
{
    peticiones_asegurar_tabla($pdo);
    $sql = 'SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email, i.nombre AS inmobiliaria_nombre
            FROM peticiones p
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN inmobiliarias i ON i.id = p.inmobiliaria_id';
    $params = [];
    if ($filtro_estado && $filtro_estado !== 'todas') {
        $sql .= ' WHERE p.estado = :estado';
        $params['estado'] = $filtro_estado;
    }
    $sql .= ' ORDER BY FIELD(p.prioridad,"critica","alta","media","baja"), p.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function peticiones_listar_por_inmobiliaria(PDO $pdo, int $inmobiliaria_id): array
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT * FROM peticiones WHERE inmobiliaria_id = :iid ORDER BY created_at DESC'
    );
    $stmt->execute(['iid' => $inmobiliaria_id]);
    return $stmt->fetchAll();
}

function peticion_obtener(PDO $pdo, int $id): ?array
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->prepare(
        'SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email, i.nombre AS inmobiliaria_nombre
         FROM peticiones p
         LEFT JOIN usuarios u ON u.id = p.usuario_id
         LEFT JOIN inmobiliarias i ON i.id = p.inmobiliaria_id
         WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function peticion_responder(PDO $pdo, int $id, string $respuesta, string $nuevo_estado): bool
{
    peticiones_asegurar_tabla($pdo);
    $resuelto = in_array($nuevo_estado, ['resuelta', 'cerrada'], true) ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare(
        'UPDATE peticiones SET respuesta_admin = :resp, estado = :estado, resuelto_at = :resuelto WHERE id = :id'
    );
    return $stmt->execute(['resp' => $respuesta, 'estado' => $nuevo_estado, 'resuelto' => $resuelto, 'id' => $id]);
}

function peticion_cambiar_estado(PDO $pdo, int $id, string $estado): bool
{
    peticiones_asegurar_tabla($pdo);
    $resuelto = in_array($estado, ['resuelta', 'cerrada'], true) ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare('UPDATE peticiones SET estado = :estado, resuelto_at = :resuelto WHERE id = :id');
    return $stmt->execute(['estado' => $estado, 'resuelto' => $resuelto, 'id' => $id]);
}

function peticiones_contar_por_estado(PDO $pdo): array
{
    peticiones_asegurar_tabla($pdo);
    $stmt = $pdo->query(
        'SELECT estado, COUNT(*) as total FROM peticiones GROUP BY estado'
    );
    $conteo = ['abierta' => 0, 'en_progreso' => 0, 'resuelta' => 0, 'cerrada' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $conteo[$row['estado']] = (int) $row['total'];
    }
    return $conteo;
}

/**
 * Obtiene estadísticas del sistema para el panel SuperAdmin.
 */
function sistema_obtener_estadisticas(PDO $pdo): array
{
    $stats = [];

    // Total de inmobiliarias
    try {
        $stats['inmobiliarias_total'] = (int) $pdo->query('SELECT COUNT(*) FROM inmobiliarias')->fetchColumn();
        $stats['inmobiliarias_activas'] = (int) $pdo->query('SELECT COUNT(*) FROM inmobiliarias WHERE activa = 1')->fetchColumn();
    } catch (\PDOException $e) { $stats['inmobiliarias_total'] = 0; $stats['inmobiliarias_activas'] = 0; }

    // Total de usuarios
    try {
        $stats['usuarios_total'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $stats['usuarios_activos'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn();
        $stats['usuarios_pwd_temporal'] = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE password_temporal = 1')->fetchColumn();
    } catch (\PDOException $e) { $stats['usuarios_total'] = 0; $stats['usuarios_activos'] = 0; $stats['usuarios_pwd_temporal'] = 0; }

    // Usuarios por rol
    try {
        $stmt = $pdo->query('SELECT rol, COUNT(*) as total FROM usuarios WHERE activo = 1 GROUP BY rol ORDER BY total DESC');
        $stats['usuarios_por_rol'] = $stmt->fetchAll();
    } catch (\PDOException $e) { $stats['usuarios_por_rol'] = []; }

    // Tablas principales — recuentos
    $tablas_contar = ['clientes', 'prospectos', 'propiedades', 'visitas', 'notas', 'proceso_propiedades'];
    foreach ($tablas_contar as $tabla) {
        try {
            $stats['total_' . $tabla] = (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
        } catch (\PDOException $e) { $stats['total_' . $tabla] = 0; }
    }

    // Últimos 7 días: nuevos registros
    try {
        $stats['clientes_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $stats['propiedades_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM propiedades WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        $stats['visitas_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM visitas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    } catch (\PDOException $e) { $stats['clientes_7d'] = 0; $stats['propiedades_7d'] = 0; $stats['visitas_7d'] = 0; }

    // Tamaño de la BD
    try {
        $db_name = db_config()['name'] ?? 'tinoprop';
        $stmt = $pdo->prepare(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
             FROM information_schema.tables WHERE table_schema = :db"
        );
        $stmt->execute(['db' => $db_name]);
        $stats['db_size_mb'] = (float) ($stmt->fetchColumn() ?: 0);
    } catch (\PDOException $e) { $stats['db_size_mb'] = 0; }

    // Tablas existentes
    try {
        $stmt = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024, 1) AS size_kb
                             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_ROWS DESC");
        $stats['tablas'] = $stmt->fetchAll();
    } catch (\PDOException $e) { $stats['tablas'] = []; }

    // PHP / MySQL info
    $stats['php_version'] = phpversion();
    try {
        $stats['mysql_version'] = $pdo->query("SELECT VERSION()")->fetchColumn();
    } catch (\PDOException $e) { $stats['mysql_version'] = 'N/A'; }
    $stats['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
    $stats['memory_limit'] = ini_get('memory_limit');
    $stats['max_upload'] = ini_get('upload_max_filesize');
    $stats['timezone'] = date_default_timezone_get();

    // Peticiones pendientes
    $stats['peticiones'] = peticiones_contar_por_estado($pdo);

    return $stats;
}

/**
 * Devuelve la configuración DB (nombre, host, etc.).
 */
function db_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = (require __DIR__ . '/config.php')['db'] ?? [];
    }
    return $cfg;
}

/* =============================================
   SISTEMA LEGAL Y ACEPTACIÓN DE TÉRMINOS
   ============================================= */

/**
 * Comprueba si el usuario ha aceptado la versión vigente de los términos.
 */
function usuario_ha_aceptado_terminos(): bool
{
    $v = $_SESSION['usuario']['terminos_aceptados'] ?? null;
    return $v === TERMINOS_VERSION;
}

/**
 * Registra la aceptación de los términos en BD y sesión.
 */
function usuario_aceptar_terminos(PDO $pdo, int $usuario_id): void
{
    $stmt = $pdo->prepare('UPDATE usuarios SET terminos_aceptados = :ver WHERE id = :id');
    $stmt->execute(['ver' => TERMINOS_VERSION, 'id' => $usuario_id]);
    $_SESSION['usuario']['terminos_aceptados'] = TERMINOS_VERSION;
}

/**
 * Devuelve true si la ruta actual requiere aceptación de términos.
 * No aplica en: login, logout, cambiar-password, aceptar-terminos.
 */
function requiere_aceptacion_terminos(): bool
{
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $excluidos = ['login.php', 'logout.php', 'cambiar-password.php', 'aceptar-terminos.php'];
    return !in_array($script, $excluidos, true);
}

/* =============================================
   24. RATE LIMITING DE LOGIN
   ============================================= */

/**
 * Crea la tabla login_intentos si no existe.
 */
function login_intentos_asegurar_tabla(PDO $pdo): void
{
    static $ok = false;
    if ($ok) return;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_intentos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            email VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_li_ip (ip),
            INDEX idx_li_email (email),
            INDEX idx_li_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $ok = true;
}

/**
 * Obtiene la IP real del cliente, considerando solo proxies de confianza.
 * Solo lee X-Forwarded-For si REMOTE_ADDR está en la lista TRUSTED_PROXIES.
 */
function login_obtener_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Solo confiar en X-Forwarded-For si la petición viene de un proxy conocido.
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $trusted_raw = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: '';
        $trusted = array_filter(array_map('trim', explode(',', $trusted_raw)));
        if (!empty($trusted) && in_array($remote, $trusted, true)) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip  = $ips[0];
            // Validar que sea una IP real.
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $remote;
}

/**
 * Comprueba si la IP o el email están bloqueados por exceso de intentos fallidos.
 * Devuelve 0 si puede intentar login, o los segundos restantes de bloqueo.
 */
function login_intentos_bloqueado(PDO $pdo, string $ip, string $email): int
{
    login_intentos_asegurar_tabla($pdo);

    $limite = date('Y-m-d H:i:s', time() - LOGIN_VENTANA_SEGUNDOS);

    // Contamos intentos fallidos por IP o por email en la ventana de tiempo
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_intentos
         WHERE (ip = :ip OR email = :email) AND created_at >= :limite'
    );
    $stmt->execute(['ip' => $ip, 'email' => $email, 'limite' => $limite]);
    $count = (int) $stmt->fetchColumn();

    if ($count < LOGIN_MAX_INTENTOS) {
        return 0;
    }

    // Obtener el timestamp del último intento para calcular cuándo expira el bloqueo
    $stmt = $pdo->prepare(
        'SELECT MAX(created_at) FROM login_intentos
         WHERE (ip = :ip OR email = :email) AND created_at >= :limite'
    );
    $stmt->execute(['ip' => $ip, 'email' => $email, 'limite' => $limite]);
    $ultimo = $stmt->fetchColumn();

    if (!$ultimo) return 0;

    $expira_en = strtotime($ultimo) + LOGIN_BLOQUEO_SEGUNDOS - time();
    return max(0, $expira_en);
}

/**
 * Registra un intento fallido de login.
 */
function login_intentos_registrar(PDO $pdo, string $ip, string $email): void
{
    login_intentos_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('INSERT INTO login_intentos (ip, email) VALUES (:ip, :email)');
    $stmt->execute(['ip' => $ip, 'email' => $email]);
}

/**
 * Limpia los intentos fallidos para una IP/email tras un login exitoso.
 */
function login_intentos_limpiar(PDO $pdo, string $ip, string $email): void
{
    login_intentos_asegurar_tabla($pdo);

    $stmt = $pdo->prepare('DELETE FROM login_intentos WHERE ip = :ip AND email = :email');
    $stmt->execute(['ip' => $ip, 'email' => $email]);
}

/**
 * Limpia intentos antiguos (más de 24h) para no acumular basura.
 * Llamar ocasionalmente (p.ej. desde un cron o tras login exitoso).
 */
function login_intentos_purgar(PDO $pdo): void
{
    login_intentos_asegurar_tabla($pdo);
    $pdo->exec("DELETE FROM login_intentos WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
}

/* FIN de helpers.php */
