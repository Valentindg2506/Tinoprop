<?php
/*
 * ============================================================================
 * Archivo: inc/idioma.php
 * Aplicación: TinoProp — CRM inmobiliario
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo implementa el sistema de internacionalización (i18n) de TinoProp.
 * "Internacionalización" significa hacer que la aplicación pueda mostrarse
 * en varios idiomas. Actualmente soporta Español (es) e Inglés (en).
 *
 * ¿CÓMO FUNCIONA?
 * Usa un patrón llamado "output buffering + str_replace":
 *
 * 1. BUFFER DE SALIDA: PHP normalmente envía el HTML al navegador línea por línea.
 *    Con output buffering (ob_start), PHP guarda TODO el HTML en memoria (un "buffer")
 *    en vez de enviarlo inmediatamente.
 *
 * 2. DICCIONARIO: Un array asociativo (mapa) que relaciona cada texto en español
 *    con su traducción en inglés. Ejemplo: 'Guardar' => 'Save'
 *
 * 3. TRADUCCIÓN: Al final de la petición, se toma todo el HTML acumulado en el
 *    buffer y se reemplazan los textos en español por sus traducciones en inglés
 *    usando str_replace (para textos largos) y preg_replace (para textos cortos
 *    con protección de fronteras de palabra).
 *
 * 4. ENVÍO: El HTML ya traducido se envía al navegador del usuario.
 *
 * VENTAJA de este enfoque: no hay que modificar ningún archivo PHP/HTML.
 * Toda la traducción es automática y transparente.
 *
 * FLUJO DE EJECUCIÓN:
 *   bootstrap.php → i18n_iniciar_buffer() → [se genera todo el HTML] → i18n_finalizar_buffer()
 *
 * COMPONENTES:
 * - idioma_actual(): obtiene el idioma activo de la sesión
 * - idioma_es_valido(): verifica si un código de idioma es soportado
 * - idioma_establecer(): cambia el idioma del usuario
 * - i18n_traducciones(): diccionario español → inglés
 * - i18n_traducir_buffer(): aplica las traducciones al HTML
 * - i18n_iniciar_buffer(): inicia la captura del HTML
 * - i18n_finalizar_buffer(): traduce y envía el HTML final
 */

/*
 * idioma_actual: devuelve el código del idioma activo del usuario.
 *
 * Lee el idioma de la SESIÓN PHP ($_SESSION). Una sesión es un espacio de
 * almacenamiento temporal en el servidor que guarda datos del usuario mientras
 * navega por la aplicación (como su idioma, su nombre, etc.).
 *
 * El operador ?? (null coalescing) devuelve 'es' (español) si no hay ningún
 * idioma guardado en la sesión (por ejemplo, la primera vez que entra).
 *
 * El ': string' indica que esta función siempre devuelve un texto (string).
 *
 * @return string — Código de idioma ('es' o 'en')
 */
function idioma_actual(): string
{
    return $_SESSION['idioma'] ?? 'es';
}

/*
 * idioma_es_valido: comprueba si un código de idioma es uno de los soportados.
 *
 * in_array() busca un valor en un array. El parámetro 'true' (strict) hace que
 * la comparación sea estricta (no solo el valor, también el tipo de dato).
 * Esto evita que, por ejemplo, el número 0 se considere igual a la cadena 'es'.
 *
 * @param string $lang — Código de idioma a verificar
 * @return bool — true si es válido, false si no
 */
function idioma_es_valido(string $lang): bool
{
    return in_array($lang, ['es', 'en'], true);
}

/*
 * idioma_establecer: cambia el idioma del usuario en su sesión.
 *
 * Solo acepta idiomas válidos (es, en). Si alguien intenta poner un idioma
 * no soportado (por ejemplo, 'fr', 'de', o un texto malicioso), la función
 * simplemente no hace nada (return). Esto es una medida de seguridad.
 *
 * @param string $lang — Código del idioma a establecer
 */
function idioma_establecer(string $lang): void
{
    if (!idioma_es_valido($lang)) {
        return; // Idioma no soportado: ignoramos la petición
    }
    $_SESSION['idioma'] = $lang; // Guardamos en sesión
}

/*
 * i18n_traducciones: devuelve el diccionario de traducciones español → inglés.
 *
 * Este es el CORAZÓN del sistema de traducción. Es un array asociativo enorme
 * donde cada clave es un texto en español y su valor es la traducción al inglés.
 *
 * Ejemplo: 'Guardar' => 'Save'
 *
 * La variable 'static $map' usa el patrón de caché estática:
 * - La primera vez que se llama a esta función, se construye todo el array.
 * - Las siguientes veces, devuelve el mismo array sin reconstruirlo.
 * - Esto ahorra tiempo y memoria porque el diccionario puede tener cientos de entradas.
 *
 * El diccionario está organizado por categorías (comentadas dentro del array):
 * - Menú de navegación y secciones
 * - Formularios y labels comunes
 * - Dashboard y métricas
 * - Calendario / Recordatorios
 * - Propiedades / edición
 * - Panel Supervisor y Jefe
 *
 * IMPORTANTE: los textos deben coincidir EXACTAMENTE con lo que aparece en el HTML.
 * Si en el HTML dice "Guardar cambios" y aquí pone "Guardar Cambios", NO se traducirá
 * porque las mayúsculas son diferentes.
 *
 * @return array — Mapa de traducciones ['texto_es' => 'texto_en']
 */
function i18n_traducciones(): array
{
    /* Caché estática: null la primera vez, luego ya tiene el diccionario cargado */
    static $map = null;

    if ($map !== null) {
        return $map; // Ya construido, lo devolvemos directamente
    }

    /* ---- DICCIONARIO DE TRADUCCIONES ---- */
    $map = [
        /* --- Menú de navegación y secciones principales --- */
        'Inicio' => 'Home',
        'Recordatorios' => 'Reminders',
        'Documentación' => 'Documentation',
        'Gestión de Clientes' => 'Customer Management',
        'Clientes' => 'Clients',
        'Prospectos' => 'Leads',
        'Inmuebles' => 'Properties',
        'Propiedades' => 'Properties',
        'Alquileres' => 'Rentals',
        'Búsqueda Avanzada' => 'Advanced Search',
        'Sistema' => 'System',
        'Configuración' => 'Settings',
        'Cerrar Sesión' => 'Log out',
        'Gestión Clientes - Vendedor' => 'Customer Management - Seller',
        'Gestión Clientes - Comprador' => 'Customer Management - Buyer',
        'Inmuebles - Vendedor' => 'Properties - Seller',
        'Inmuebles - Comprador' => 'Properties - Buyer',
        'Perfil' => 'Profile',
        'Seguridad' => 'Security',
        'Preferencias del dashboard' => 'Dashboard preferences',
        'Guardar perfil' => 'Save profile',
        'Actualizar contraseña' => 'Update password',
        'Guardar preferencias' => 'Save preferences',
        'Restablecer valores por defecto' => 'Reset to defaults',
        'Equipo por defecto' => 'Default team',
        'Operacion por defecto' => 'Default operation',
        'Periodo de referencia' => 'Reference period',
        'Tema de interfaz' => 'Interface theme',
        'Densidad de tablas' => 'Table density',
        'Tema' => 'Theme',
        'Densidad' => 'Density',
        'Usuario' => 'User',
        'Email de acceso' => 'Login email',
        'Email acceso' => 'Login email',
        'Contraseña actual' => 'Current password',
        'Contraseña nueva (min. 8)' => 'New password (min. 8)',
        'Contraseña nueva' => 'New password',
        'Repetir contraseña nueva' => 'Repeat new password',
        'Cambio de idioma' => 'Language',
        'Idioma de la interfaz' => 'Interface language',
        'Estos valores se aplican al abrir el dashboard si no hay filtros en la URL.' => 'These values apply when opening the dashboard without URL filters.',
        'Define con que filtros se abre el dashboard cuando ingresas sin seleccionar opciones.' => 'Set which filters the dashboard uses when you enter without selecting options.',
        'Cambia tu contraseña para mantener la cuenta protegida.' => 'Change your password to keep the account secure.',
        'Estos datos identifican tu cuenta y se usan en avisos y actividad.' => 'These details identify your account and are used in notices and activity.',

        /* --- Formularios y etiquetas (labels) comunes en toda la app --- */
        'Nombre' => 'First name',
        'Apellidos' => 'Last name',
        'Telefono' => 'Phone',
        'Teléfono' => 'Phone',
        'Email' => 'Email',
        'Operación' => 'Operation',
        'Operacion' => 'Operation',
        'Venta' => 'Sale',
        'Compra' => 'Purchase',
        'Alquiler' => 'Rent',
        'Moneda' => 'Currency',
        'Precio' => 'Price',
        'Direccion' => 'Address',

                    /* --- Dashboard: métricas, filtros y embudo comercial --- */
                    'Clientes activos' => 'Active clients',
                    'Prospectos' => 'Leads',
                    'Propiedades venta' => 'Sale properties',
                    'Propiedades alquiler' => 'Rent properties',
                    'Embudo comercial' => 'Sales funnel',
                    'Alertas prioritarias' => 'Priority alerts',
                    'Follow-up del dia' => 'Day follow-up',
                    'Actividad reciente' => 'Recent activity',
                    'Propiedades destacadas' => 'Featured properties',
                    'Mes actual' => 'Current month',
                    'Sale y Rent' => 'Sale and Rent',
                    'Sale y rent' => 'Sale and Rent',
                    'Operacion: Sale' => 'Operation: Sale',
                    'Operation: Sale' => 'Operation: Sale',
                    'Operation: Rent' => 'Operation: Rent',
                    'Filter: Todos' => 'Filter: All',
                    'Period: Mes actual' => 'Period: Current month',
                    'TEAM' => 'TEAM',
                    'PERIOD' => 'PERIOD',
                    'OPERATION' => 'OPERATION',
                    'Apply filtros' => 'Apply filters',
                    'Edit orden' => 'Edit order',
                    'Reset orden' => 'Reset order',
                    'Apply filtros' => 'Apply filters',

                    /* --- Estados del embudo de ventas (Kanban) --- */
                    'Contacto' => 'Contact',
                    'Visita' => 'Visit',
                    'Oferta' => 'Offer',
                    'Cierre' => 'Close',
                    'Closed' => 'Closed',

                    /* --- Listados, tarjetas y widgets del dashboard --- */
                    'Team: Todos' => 'Team: All',
                    'Team: TODOS' => 'Team: All',
                    'Todos' => 'All',
                    'Ver detalle' => 'View detail',
                    'Ver detalles' => 'View details',
                    'Ver más' => 'View more',
                    'Ver mas' => 'View more',
                    'View más' => 'View more',
                    'visitas' => 'visits',
                    'ofertas' => 'offers',
                    'Top 3 performance' => 'Top 3 performance',

                    /* --- Calendario y recordatorios: meses y días de la semana --- */
                    'Enero' => 'January',
                    'Febrero' => 'February',
                    'Marzo' => 'March',
                    'Abril' => 'April',
                    'Mayo' => 'May',
                    'Junio' => 'June',
                    'Julio' => 'July',
                    'Agosto' => 'August',
                    'Septiembre' => 'September',
                    'Octubre' => 'October',
                    'Noviembre' => 'November',
                    'Diciembre' => 'December',
                    'Do' => 'Sun',
                    'Lu' => 'Mon',
                    'Ma' => 'Tue',
                    'Mi' => 'Wed',
                    'Ju' => 'Thu',
                    'Vi' => 'Fri',
                    'Sa' => 'Sat',
                    'Reminders para' => 'Reminders for',
                    'No hay recordatorios para esta fecha' => 'No reminders for this date',
                    'Seleccionado:' => 'Selected:',

        'Dirección' => 'Address',
        'Zona' => 'Area',
        'Metros' => 'Square meters',
        'Habitaciones' => 'Bedrooms',
        'Banos' => 'Bathrooms',
        'Baños' => 'Bathrooms',
        'Descripcion' => 'Description',
        'Descripción' => 'Description',
        'Estado' => 'Status',
        'Disponible' => 'Available',
        'Reservado' => 'Reserved',
        'Vendido' => 'Sold',
        'Nuevo' => 'New',
        'Contactado' => 'Contacted',
        'Visita' => 'Visit',
        'Oferta' => 'Offer',
        'Cierre' => 'Closed',
        'Pendiente' => 'Pending',
        'Completado' => 'Completed',
        'Cancelar' => 'Cancel',
        'Eliminar' => 'Delete',
        'Guardar' => 'Save',
        'Editar' => 'Edit',
        'Crear' => 'Create',
        'Buscar' => 'Search',
        'Filtrar' => 'Filter',
        'Limpiar filtros' => 'Clear filters',
        'Resultados' => 'Results',
        'Sin resultados' => 'No results',
        'Propiedad' => 'Property',
        'Cliente' => 'Client',
        'Prospecto' => 'Lead',
        'Aviso' => 'Notice',
        'Nota' => 'Note',
        'Notas' => 'Notes',
        'Acciones' => 'Actions',
        'Ver' => 'View',
        'Volver' => 'Back',
        'ID' => 'ID',
        'Fecha' => 'Date',
        'Hora' => 'Time',
        'Tipo' => 'Type',
        'Filtro' => 'Filter',
        'Filtros' => 'Filters',
        'Periodo' => 'Period',
        'Equipo' => 'Team',
        'Comprador' => 'Buyer',
        'Vendedor' => 'Seller',
        'Propietario' => 'Owner',
        'Inquilino' => 'Tenant',
        'Ver detalles' => 'View details',
        'Detalles' => 'Details',
        'Comentarios' => 'Comments',
        'Presupuesto' => 'Budget',
        'Referencia' => 'Reference',
        'Ubicacion' => 'Location',
        'Ubicación' => 'Location',
        'Ciudad' => 'City',
        'Provincia' => 'Province',
        'País' => 'Country',
        'Pais' => 'Country',
        'Subir' => 'Upload',
        'Imagen' => 'Image',
        'Imágenes' => 'Images',
        'Guardar cambios' => 'Save changes',
        'Crear cliente' => 'Create client',
        'Crear propiedad' => 'Create property',
        'Crear prospecto' => 'Create lead',
        'Crear recordatorio' => 'Create reminder',
        'Recordatorio' => 'Reminder',
        'Seguimiento' => 'Follow-up',
        'Reunión' => 'Meeting',
        'Llamada' => 'Call',
        'Visita' => 'Visit',
        'Estado del prospecto' => 'Lead status',
        'Estado del cliente' => 'Client status',
        'Estado de la propiedad' => 'Property status',
        'Galeria' => 'Gallery',
        'Galería' => 'Gallery',
        'Subido' => 'Uploaded',
        'Actualizado' => 'Updated',
        'Creado' => 'Created',
        'Operación' => 'Operation',
        'Operaciones' => 'Operations',
        'Vista previa' => 'Preview',
        'Aplicar' => 'Apply',
        'Restablecer' => 'Reset',
        'Confirmar accion' => 'Confirm action',
        'Esta accion no se puede deshacer.' => 'This action cannot be undone.',
        'Confirmar' => 'Confirm',
        'Aceptar' => 'Accept',
        'Accion' => 'Action',
        'Acción' => 'Action',
        'Filtro avanzado' => 'Advanced filter',
        'Precio minimo' => 'Min price',
        'Precio máximo' => 'Max price',
        'Metros mínimos' => 'Min sqm',
        'Metros maximos' => 'Max sqm',
        'Habitaciones minimas' => 'Min bedrooms',
        'Banos minimos' => 'Min bathrooms',
        'Resultados encontrados' => 'Results found',
        'Favoritos' => 'Favorites',
        'Marca una estrella...' => 'Star an item...',
        'Selecciona una opción del menú para comenzar a trabajar.' => 'Select an option from the menu to get started.',
        'Sistema listo para usar.' => 'System ready to use.',
        'Bienvenido a TinoProp' => 'Welcome to TinoProp',
        'Seccion' => 'Section',
        'Sección' => 'Section',
        'Dashboard' => 'Dashboard',

            /* --- Propiedades: campos de edición y formularios de inmuebles --- */
            'TITULO:' => 'TITLE:',
            'OPERACION:' => 'OPERATION:',
            'REFERENCIA:' => 'REFERENCE:',
            'Guardar Cambios' => 'Save changes',
            'Save Cambios' => 'Save changes',
            'Save cambios' => 'Save changes',
            'Contenido' => 'Content',
            'Notas y avisos' => 'Notes and notices',
            'FOLLOW-UP DEL INMUEBLE' => 'PROPERTY FOLLOW-UP',
            'Escribe una nota o aviso...' => 'Write a note or notice...',
            'Save nota' => 'Save note',
            'Tipo' => 'Type',
            'Tipo:' => 'Type:',
            'Tipo :' => 'Type:',
            'Arrastra imágenes aquí o haz clic' => 'Drag images here or click',
            'Arrastra imagenes aqui o haz clic' => 'Drag images here or click',
            'Arrastra imagenes aqui o haz clic' => 'Drag images here or click',
            'Sin imagen' => 'No image',
            'Back al listado' => 'Back to list',
            'Detalle de Property' => 'Property detail',

            /* --- Alquileres: tipos de inmueble y unidades --- */
            'Properties en Rent' => 'Properties for Rent',
            'PISO' => 'FLAT',
            'Piso' => 'Flat',
            'Piso Familiar' => 'Family flat',
            'Loft' => 'Loft',
            'Loft Urban' => 'Urban loft',
            'Ver en detalle' => 'View details',
            'View en detalle' => 'View details',
            'EUR/mes' => 'EUR/mo',
            'mes' => 'month',
            'hab' => 'bd',
            'banos' => 'ba',
            'baños' => 'ba',
            'm2' => 'sqm',

            /* --- Panel del Supervisor: rendimiento del equipo de agentes --- */
            'Rendimiento del equipo' => 'Team Performance',
            'Estadísticas por agente' => 'Agent Statistics',
            'Agente' => 'Agent',
            'Rol' => 'Role',
            'Visitas (periodo)' => 'Visits (period)',
            'Ofertas' => 'Offers',

            /* --- Panel del Jefe/Director: informe ejecutivo de la empresa --- */
            'Informe de empresa' => 'Company Report',
            'Resumen ejecutivo' => 'Executive Summary',
            'Usuarios activos' => 'Active Users',
            'Valor en cartera' => 'Portfolio Value',
            'Valor reservado' => 'Reserved Value',
            'Tasa conversión' => 'Conversion Rate',
            'Mejor agente' => 'Best Agent',
    ];

    return $map; // Devolvemos el diccionario completo
}

/*
 * i18n_traducir_buffer: aplica las traducciones al HTML completo de la página.
 *
 * Esta es la función que hace la "magia" de la traducción. Recibe todo el HTML
 * generado por PHP y reemplaza cada texto en español por su traducción en inglés.
 *
 * SOLO se traduce cuando el idioma activo es 'en' (inglés). Si el idioma es 'es'
 * (español), devuelve el HTML sin modificar porque ya está en español.
 *
 * Usa dos estrategias de reemplazo:
 *
 * 1. TEXTOS LARGOS (más de 3 caracteres): usa str_replace() que es rápido
 *    y suficiente porque los textos largos rara vez causan colisiones.
 *    Ejemplo: "Guardar cambios" solo aparecerá como texto completo.
 *
 * 2. TEXTOS CORTOS (3 caracteres o menos): usa preg_replace() con fronteras
 *    de palabra (\b). Esto evita colisiones como reemplazar "Vi" dentro de "View".
 *    \b marca el límite entre una letra y un no-letra.
 *    Ejemplo: \bVi\b solo coincide con "Vi" como palabra aislada.
 *
 * @param string $html — El HTML completo de la página
 * @return string — El HTML traducido al inglés (o sin cambios si el idioma es español)
 */
function i18n_traducir_buffer(string $html): string
{
    /* Si el idioma activo es español, no hay nada que traducir */
    if (idioma_actual() !== 'en') {
        return $html;
    }

    /* Obtenemos el diccionario de traducciones */
    $map = i18n_traducciones();

    /* Separamos los textos en dos grupos: largos y cortos */
    $largos = [];  // Textos de más de 3 caracteres
    $cortos = [];  // Textos de 3 caracteres o menos

    foreach ($map as $es => $en) {
        /* mb_strlen cuenta caracteres Unicode correctamente (ñ, é, etc.) */
        if (mb_strlen($es, 'UTF-8') <= 3) {
            $cortos[$es] = $en;
        } else {
            $largos[$es] = $en;
        }
    }

    /* Paso 1: reemplazo directo para frases largas (más rápido y suficiente).
     * str_replace puede recibir arrays: reemplaza cada clave por su valor */
    $html = str_replace(array_keys($largos), array_values($largos), $html);

    /* Paso 2: reemplazo con regex para tokens cortos (evita colisiones).
     * preg_quote() escapa caracteres especiales de regex en el texto español.
     * El flag /u activa el modo Unicode (necesario para textos en español). */
    foreach ($cortos as $es => $en) {
        $pattern = '/\b' . preg_quote($es, '/') . '\b/u';
        $html = preg_replace($pattern, $en, $html);
    }

    return $html; // Devolvemos el HTML con todos los textos traducidos
}

/*
 * i18n_iniciar_buffer: activa el "output buffering" de PHP.
 *
 * Normalmente, PHP envía el HTML al navegador línea por línea mientras
 * lo genera. ob_start() le dice a PHP: "no envíes nada todavía, guarda
 * todo en un buffer (memoria temporal)".
 *
 * Esto nos permite capturar TODO el HTML de la página para luego
 * traducirlo de una sola vez antes de enviarlo.
 *
 * Se llama al principio de la ejecución (en bootstrap.php).
 */
function i18n_iniciar_buffer(): void
{
    ob_start(); // Inicia la captura de toda la salida HTML
}

/*
 * i18n_finalizar_buffer: cierra el buffer, traduce el HTML y lo envía al navegador.
 *
 * Es la última función que se ejecuta en la petición. Hace lo siguiente:
 * 1. ob_get_level() comprueba si hay un buffer activo (por seguridad)
 * 2. ob_get_clean() obtiene todo el HTML acumulado Y cierra el buffer
 * 3. i18n_traducir_buffer() aplica las traducciones si el idioma es inglés
 * 4. echo envía el HTML final (traducido o no) al navegador del usuario
 *
 * Se llama al final de la ejecución (mediante un shutdown handler o al final de index.php).
 */
function i18n_finalizar_buffer(): void
{
    /* Verificamos que hay un buffer activo antes de intentar cerrarlo */
    if (ob_get_level() === 0) {
        return; // No hay buffer, no hacemos nada
    }

    /* Obtenemos todo el HTML acumulado y cerramos el buffer */
    $html = ob_get_clean();

    /* Traducimos el HTML (si el idioma es inglés) y lo enviamos al navegador */
    echo i18n_traducir_buffer($html);
}
