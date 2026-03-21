<?php
/*
 * Archivo: inc/idioma.php
 * Rol: internacionalización ES/EN mediante traducción del buffer HTML al final de la petición.
 * Componentes: selección de idioma en sesión, validación de idioma, diccionario y traductor.
 * Flujo: bootstrap inicia buffer -> i18n_traducir_buffer aplica diccionario -> respuesta final traducida.
 */

/* Devuelve el idioma actual almacenado en sesión (por defecto: es). */
function idioma_actual(): string
{
    return $_SESSION['idioma'] ?? 'es';
}

function idioma_es_valido(string $lang): bool
{
    // Idiomas soportados actualmente por la interfaz.
    return in_array($lang, ['es', 'en'], true);
}

/* Guarda el idioma elegido en sesión solo si está permitido. */
function idioma_establecer(string $lang): void
{
    if (!idioma_es_valido($lang)) {
        return;
    }
    $_SESSION['idioma'] = $lang;
}

function i18n_traducciones(): array
{
    // Cache estática para no reconstruir el diccionario en cada llamada.
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $map = [
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

        // Formularios y labels comunes
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

                    // Dashboard y métricas
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

                    // Embudo estados
                    'Contacto' => 'Contact',
                    'Visita' => 'Visit',
                    'Oferta' => 'Offer',
                    'Cierre' => 'Close',
                    'Closed' => 'Closed',

                    // Listados y tarjetas
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

                    // Calendario / Recordatorios
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
        'Operación' => 'Operation',
            'Operaciones' => 'Operations',

            // Propiedades / edición
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

            // Propiedades / Alquiler
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
            'm2' => 'sqm'
    ];

    return $map;
}

/* Traduce el HTML final cuando el idioma activo es inglés. */
function i18n_traducir_buffer(string $html): string
{
    // Solo se traduce cuando el idioma activo es inglés.
    if (idioma_actual() !== 'en') {
        return $html;
    }

    $map = i18n_traducciones();

    $largos = [];
    $cortos = [];

    foreach ($map as $es => $en) {
        if (mb_strlen($es, 'UTF-8') <= 3) {
            $cortos[$es] = $en;
        } else {
            $largos[$es] = $en;
        }
    }

    // Reemplazo directo para frases largas (más rápido y suficiente).
    $html = str_replace(array_keys($largos), array_values($largos), $html);

    // Reemplazos con frontera de palabra para tokens cortos.
    // Evita colisiones como "Vi" dentro de "View".
    foreach ($cortos as $es => $en) {
        $pattern = '/\b' . preg_quote($es, '/') . '\b/u';
        $html = preg_replace($pattern, $en, $html);
    }

    return $html;
}

/* Inicia el output buffering para poder traducir la salida final. */
function i18n_iniciar_buffer(): void
{
    // Captura salida para poder traducir el HTML final completo.
    ob_start();
}

/* Cierra el buffer, traduce el HTML y lo imprime en la respuesta. */
function i18n_finalizar_buffer(): void
{
    if (ob_get_level() === 0) {
        return;
    }

    $html = ob_get_clean();
    echo i18n_traducir_buffer($html);
}
