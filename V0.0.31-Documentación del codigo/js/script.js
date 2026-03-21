/*
 * ============================================================================
 * Archivo: js/script.js — V0.0.31 Pre-Producción
 * Aplicación: TinoProp — CRM inmobiliario (gestión de propiedades y clientes)
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este es el archivo JavaScript principal de la aplicación. JavaScript es el
 * lenguaje que permite que una página web sea interactiva (responder a clics,
 * arrastrar elementos, mostrar ventanas emergentes, etc.).
 *
 * Este archivo controla TODAS las interacciones del usuario en la interfaz:
 *
 * 1. FAVORITOS DE MENÚ: El usuario puede marcar secciones con una estrella (☆)
 *    para tener acceso rápido desde el menú lateral. Se guardan en el navegador
 *    (localStorage) para que persistan al recargar la página.
 *
 * 2. MODAL DE CONFIRMACIÓN: Cuando el usuario va a hacer algo importante
 *    (como eliminar un registro), aparece una ventana emergente que pide
 *    confirmación antes de ejecutar la acción.
 *
 * 3. DRAG & DROP EN KANBAN: Los tableros Kanban muestran tarjetas que se pueden
 *    arrastrar entre columnas (ej: de "Contacto" a "Visita"). Esto actualiza
 *    el estado del prospecto/proceso tanto visualmente como en el servidor.
 *
 * 4. REORDENAR DASHBOARD: Los widgets (tarjetas de métricas y paneles) del
 *    dashboard se pueden reorganizar arrastrándolos. El orden se guarda en
 *    el servidor y en localStorage.
 *
 * 5. SIDEBAR COLAPSABLE: El menú lateral se puede comprimir para ganar espacio.
 *    En móvil se abre/cierra con un botón hamburguesa (☰).
 *
 * 6. BUSCADOR GLOBAL: Campo de búsqueda que consulta al servidor mientras
 *    el usuario escribe (con un pequeño retardo llamado "debounce" para no
 *    sobrecargar el servidor con cada tecla).
 *
 * 7. ORDENAR TABLAS: Al hacer clic en las cabeceras de las tablas, las filas
 *    se reordenan alfabéticamente, numéricamente o por fecha.
 *
 * 8. VALIDACIÓN DE FORMULARIOS: Antes de enviar un formulario, se verifica
 *    que los campos obligatorios estén completados y tengan el formato correcto
 *    (email, teléfono, etc.).
 *
 * 9. NOTIFICACIONES: Un desplegable (dropdown) que muestra alertas pendientes.
 *
 * CONCEPTOS CLAVE PARA PRINCIPIANTES:
 * - DOM (Document Object Model): es la representación en memoria de la página HTML.
 *   JavaScript puede modificar el DOM para cambiar lo que el usuario ve.
 * - addEventListener: conecta un evento (clic, tecleo, arrastrar...) con una función.
 * - fetch: permite hacer peticiones HTTP al servidor sin recargar la página (AJAX).
 * - localStorage: almacenamiento del navegador que persiste entre sesiones.
 * - async/await: forma moderna de manejar operaciones asíncronas (que tardan en
 *   completarse, como pedir datos al servidor).
 *
 * ÍNDICE DE FUNCIONES:
 * - toggle_favorito(nombre): añade o quita elementos del listado de favoritos.
 * - actualizar_menu(): renderiza el menú de favoritos en el sidebar.
 * - cerrarModal()/abrirModal(mensaje): controlan el modal de confirmación global.
 * - limpiarZonasActivas()/resolverTarjetaArrastrada(e): utilidades del drag&drop kanban.
 * - actualizarModoKanban(): activa/desactiva modo edición del tablero kanban.
 * - inicializarOrdenDashboard(...): habilita reordenado de widgets en dashboard.
 * - obtenerItems()/guardarOrden()/aplicarOrdenGuardado(): persistencia y restauración del orden.
 * - limpiarDrag()/moverPlaceholder()/procesarFrame()/onMouseUp()/iniciarDrag(): ciclo de drag para widgets.
 * - mostrarEstadoOrden(mensaje, esError): feedback visual de guardado/reset de orden.
 * - estaEditandoDashboard()/actualizarModoDashboard(): estado de edición de dashboard.
 * - actualizarContadores(): recalcula contadores de tarjetas por columna en kanban.
 * - initSidebarToggle(): sidebar colapsable.
 * - initBuscadorGlobal(): buscador global con debounce y fetch.
 * - initOrdenarTablas(): ordenar columnas de tabla al hacer clic.
 * - initValidacionFormularios(): validación client-side en inputs required.
 * - initNotificaciones(): dropdown de notificaciones.
 */

/*
 * =============================================
 * SECCIÓN 1: SISTEMA DE FAVORITOS DEL MENÚ
 * =============================================
 * Permite al usuario "marcar" secciones del menú con una estrella para
 * tenerlas siempre a mano en la parte superior del sidebar.
 * Los favoritos se guardan en localStorage (almacenamiento del navegador)
 * para que no se pierdan al cerrar la página.
 */

/* Array (lista) que almacena los favoritos del usuario en memoria.
 * Cada elemento es un objeto con { nombre, url }.
 * Ejemplo: [{ nombre: 'Dashboard', url: '?seccion=dashboard' }] */
let favoritos = [];

/* Clave (key) usada para guardar/leer los favoritos en localStorage.
 * localStorage funciona como un diccionario: clave -> valor (texto). */
const STORAGE_KEY_FAVORITOS = 'tinoprop.menu.favoritos';

/*
 * Función auxiliar (helper) global: obtiene el token CSRF desde un meta tag del HTML.
 *
 * ¿Qué es CSRF? (Cross-Site Request Forgery — Falsificación de peticiones)
 * Es un ataque donde un sitio malicioso puede hacer que tu navegador envíe
 * peticiones a nuestra app sin tu consentimiento. El token CSRF es un código
 * secreto que el servidor genera y que debemos incluir en cada petición POST
 * para demostrar que la petición es legítima y viene de nuestra propia página.
 *
 * document.querySelector busca en el HTML un elemento <meta name="csrf-token">.
 * Si lo encuentra, devuelve el valor de su atributo "content" (el token).
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/*
 * normalizarFavorito: convierte un favorito a formato estándar { nombre, url }.
 *
 * ¿Por qué normalizar? Antes los favoritos se guardaban como texto simple ("Dashboard").
 * Ahora se guardan como objetos con nombre y URL. Esta función acepta ambos formatos
 * para no perder los favoritos de usuarios que actualicen desde versiones anteriores.
 *
 * @param {string|object} item — El favorito a normalizar (puede ser texto o un objeto)
 * @returns {object|null} — Objeto { nombre, url } normalizado, o null si es inválido
 */
function normalizarFavorito(item) {
    /* Si el favorito es un texto simple (formato antiguo), lo convertimos a objeto */
    if (typeof item === 'string') {
        const nombre = item.trim(); // trim() elimina espacios al inicio/final
        return nombre ? { nombre, url: '' } : null;
    }

    /* Si no es ni texto ni objeto válido, lo descartamos */
    if (!item || typeof item !== 'object') {
        return null;
    }

    /* Extraemos nombre y url, convirtiendo a texto y limpiando espacios */
    const nombre = String(item.nombre || '').trim();
    const url = String(item.url || '').trim();

    /* Un favorito sin nombre no tiene sentido, lo descartamos */
    if (!nombre) {
        return null;
    }

    return { nombre, url };
}

/*
 * deduplicarFavoritos: elimina favoritos duplicados de una lista.
 *
 * Usa un Set (conjunto) para recordar qué favoritos ya hemos visto.
 * Un Set es como una lista que no permite elementos repetidos.
 * La clave de comparación es la URL si existe, o el nombre si no.
 *
 * @param {Array} items — Lista de favoritos (pueden tener formatos mixtos)
 * @returns {Array} — Lista limpia sin duplicados, con formato normalizado
 */
function deduplicarFavoritos(items) {
    const salida = [];          // Lista resultado sin duplicados
    const vistos = new Set();   // Conjunto para rastrear los que ya procesamos

    /* Recorremos cada favorito de la lista */
    items.forEach(item => {
        const normalizado = normalizarFavorito(item);
        if (!normalizado) {
            return; // Si no es válido, lo saltamos
        }

        /* Generamos una clave única: preferimos la URL (más precisa),
         * pero si no hay URL, usamos el nombre (compatibilidad) */
        const key = normalizado.url ? `url:${normalizado.url}` : `name:${normalizado.nombre}`;
        if (vistos.has(key)) {
            return; // Ya existe este favorito, no lo añadimos otra vez
        }

        vistos.add(key);          // Lo marcamos como visto
        salida.push(normalizado); // Lo añadimos a la lista limpia
    });

    return salida;
}

/*
 * guardarFavoritosEnStorage: guarda la lista de favoritos en el navegador.
 *
 * localStorage.setItem(clave, valor) almacena datos que persisten incluso
 * al cerrar el navegador. Solo acepta texto, por eso usamos JSON.stringify()
 * para convertir el array de objetos JavaScript en una cadena de texto JSON.
 * Ejemplo: [{ nombre: "Dashboard", url: "?seccion=dashboard" }] se convierte
 * en el texto '[{"nombre":"Dashboard","url":"?seccion=dashboard"}]'
 */
function guardarFavoritosEnStorage() {
    localStorage.setItem(STORAGE_KEY_FAVORITOS, JSON.stringify(favoritos));
}

/*
 * cargarFavoritosDesdeStorage: lee los favoritos guardados en el navegador al abrir la página.
 *
 * Esta función se ejecuta al cargar la app. Lee los datos de localStorage,
 * los convierte de texto JSON a objeto JavaScript (JSON.parse), los normaliza
 * y los deduplicada. Si los datos están corruptos (por ejemplo, alguien editó
 * manualmente el localStorage), simplemente empieza con una lista vacía.
 *
 * El bloque try/catch captura errores: si JSON.parse falla (datos corruptos),
 * el catch nos permite continuar sin que la app se rompa.
 */
function cargarFavoritosDesdeStorage() {
    /* Leemos el texto guardado en localStorage */
    const raw = localStorage.getItem(STORAGE_KEY_FAVORITOS);
    if (!raw) {
        favoritos = []; // No había nada guardado
        return;
    }

    try {
        /* JSON.parse convierte texto JSON de vuelta a un objeto JavaScript */
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            favoritos = []; // Los datos no son una lista válida
            return;
        }

        /* Normalizamos y eliminamos duplicados */
        favoritos = deduplicarFavoritos(parsed);

        /* Reescribimos en localStorage con el formato limpio */
        guardarFavoritosEnStorage();
    } catch (_error) {
        /* Si algo falla al parsear, empezamos con lista vacía */
        favoritos = [];
    }
}

/*
 * obtenerDatosFavoritoDesdeEvento: extrae el nombre y URL del elemento del menú
 * que el usuario acaba de marcar como favorito.
 *
 * ¿Cómo funciona? Cuando el usuario hace clic en la estrella (☆) junto a un
 * elemento del menú, esta función "sube" por el árbol DOM (de hijo a padre)
 * hasta encontrar el <li> que contiene el enlace, y de ahí extrae la URL y el texto.
 *
 * - window.event?.target: el elemento HTML donde se hizo clic
 * - closest('li'): busca el <li> padre más cercano
 * - querySelector('a[href]'): busca el enlace dentro de ese <li>
 */
function obtenerDatosFavoritoDesdeEvento(nombre) {
    const objetivo = window.event?.target; // El elemento que recibió el clic
    const itemMenu = objetivo?.closest?.('li'); // El <li> contenedor
    const enlace = itemMenu?.querySelector?.('a[href]'); // El enlace <a> dentro
    const url = enlace?.getAttribute('href') || ''; // La URL del enlace
    const titulo = (nombre || enlace?.textContent || '').trim(); // El texto visible

    return {
        nombre: titulo,
        url,
    };
}

/*
 * toggle_favorito: añade o quita un favorito de la lista.
 *
 * "Toggle" significa alternar: si el favorito ya existe, lo quita;
 * si no existe, lo añade. Es como un interruptor de luz ON/OFF.
 *
 * usa findIndex para buscar si ya existe en el array:
 * - Si devuelve -1 (no encontrado) ⇒ lo añadimos con push()
 * - Si devuelve un índice válido ⇒ lo quitamos con splice()
 *
 * Después de cambiar la lista, se guarda en localStorage y se
 * redibuja el menú de favoritos en la interfaz.
 *
 * @param {string} nombre — Nombre de la sección a marcar/desmarcar
 * @param {string} urlPreferida — URL alternativa si no se puede detectar del DOM
 */
function toggle_favorito(nombre, urlPreferida = '') {
    /* Obtenemos los datos del elemento del menú usando el DOM */
    const candidato = obtenerDatosFavoritoDesdeEvento(nombre);
    if (urlPreferida && !candidato.url) {
        candidato.url = urlPreferida;
    }

    if (!candidato.nombre) {
        return; // Sin nombre no podemos hacer nada
    }

    /* Buscamos si este favorito ya está en la lista.
     * findIndex recorre el array y devuelve la posición del primer elemento
     * que cumpla la condición, o -1 si no encuentra ninguno. */
    const index = favoritos.findIndex(fav => {
        /* Comparamos primero por URL (más preciso), luego por nombre */
        if (candidato.url && fav.url) {
            return fav.url === candidato.url;
        }
        return fav.nombre === candidato.nombre;
    });

    if (index === -1) {
        /* No existe → lo añadimos al final de la lista */
        favoritos.push(candidato);
    } else {
        /* Ya existe → lo eliminamos de la lista.
         * splice(posición, cantidad) elimina 'cantidad' elementos desde 'posición' */
        favoritos.splice(index, 1);
    }

    /* Guardamos los cambios y actualizamos la interfaz */
    guardarFavoritosEnStorage();
    actualizar_menu();
}

/*
 * actualizar_menu: redibuja la lista de favoritos en el sidebar (menú lateral).
 *
 * Esta función manipula el DOM (la estructura HTML de la página).
 * Primero vacía la lista actual, y luego crea nuevos elementos HTML
 * (<li>, <a>, <span>) para cada favorito guardado.
 *
 * Conceptos clave de manipulación DOM usados aquí:
 * - document.getElementById(): busca un elemento por su atributo id
 * - document.createElement(): crea un nuevo elemento HTML en memoria
 * - element.appendChild(): añade un hijo al final de un elemento
 * - element.addEventListener(): conecta un evento (clic) con una función
 * - element.innerHTML = '': vacía todo el contenido de un elemento
 */
function actualizar_menu() {
    /* Obtenemos la lista <ul> donde se mostrarán los favoritos */
    let lista = document.getElementById("lista_favoritos_menu");
    lista.innerHTML = ""; // Vaciamos la lista para reconstruirla

    /* Mapa de iconos (emojis) para cada sección del menú.
     * Un objeto JavaScript funciona como un diccionario: clave → valor */
    const iconoMap = {
        'Dashboard': '📊', 'Agenda': '📅', 'Recordatorios': '⏰',
        'Matching': '🔗', 'Documentación': '📖', 'Clientes Vend.': '👥',
        'Prospectos Vend.': '🎯', 'Clientes Comp.': '👥', 'Prospectos Comp.': '🎯',
        'Propiedades': '🏠', 'Alquileres': '🔑', 'Búsqueda Avanzada': '🔎',
        'Proceso Vendedor': '📋', 'Proceso Comprador': '📋',
        'Visitas Vendedor': '🚶', 'Visitas Comprador': '🚶',
        'Ofertas Vendedor': '💰', 'Post-Venta': '🏡',
        'Importar CSV': '📥', 'Historial': '📜', 'Configuración': '⚙️',
    };

    /* Si no hay favoritos, mostramos un mensaje indicativo */
    if (favoritos.length === 0) {
        const li = document.createElement('li');
        li.className = 'texto_vacio';  // Clase CSS para estilo de texto gris
        li.textContent = 'Marca una estrella...';
        lista.appendChild(li);
    } else {
        /* Recorremos cada favorito y creamos su elemento HTML */
        favoritos.forEach(fav => {
            const nombre = fav.nombre || '';
            const url = fav.url || '#';
            const icono = iconoMap[nombre] || '⭐'; // Icono por defecto si no hay mapeo

            /* Creamos la estructura HTML para cada favorito:
             * <li>
             *   <a href="url">
             *     <span class="menu_icono">📊</span>
             *     <span class="sidebar_titulo">Dashboard</span>
             *   </a>
             *   <span class="btn_eliminar">✖</span>  ← botón para quitar
             * </li>
             */
            const li = document.createElement('li');

            /* Creamos el enlace <a> que llevará a la sección */
            const a = document.createElement('a');
            a.href = url;
            a.setAttribute('data-tooltip', nombre); // Tooltip al pasar el ratón

            /* Span para el icono emoji */
            const spanIcono = document.createElement('span');
            spanIcono.className = 'menu_icono';
            spanIcono.textContent = icono;

            /* Span para el título de la sección */
            const spanTitulo = document.createElement('span');
            spanTitulo.className = 'sidebar_titulo';
            spanTitulo.textContent = nombre;

            /* Ensamblamos el enlace: icono + espacio + título */
            a.appendChild(spanIcono);
            a.append(' ');
            a.appendChild(spanTitulo);

            /* Botón ✖ para eliminar el favorito.
             * Al hacer clic, llama a toggle_favorito que lo quitará de la lista */
            const btnElim = document.createElement('span');
            btnElim.className = 'btn_eliminar';
            btnElim.textContent = '✖';
            btnElim.addEventListener('click', () => toggle_favorito(nombre, url));

            li.appendChild(a);
            li.appendChild(btnElim);
            lista.appendChild(li);
        });
    }
}

/* =========================================
   SECCIÓN 2: LÓGICA KANBAN DRAG & DROP
   =========================================
   Un tablero Kanban es una herramienta visual con columnas que representan
   estados (ej: "Contacto", "Visita", "Oferta", "Cierre"). Las tarjetas
   (prospectos/procesos) se arrastran entre columnas para cambiar su estado.

   El "Drag & Drop" (arrastrar y soltar) usa eventos nativos del navegador:
   - dragstart: cuando el usuario empieza a arrastrar
   - dragover: cuando arrastra sobre una zona válida
   - dragleave: cuando sale de una zona válida
   - drop: cuando suelta la tarjeta
   - dragend: cuando termina el arrastre (suelte donde suelte)
   ========================================= */

/*
 * DOMContentLoaded: este evento se dispara cuando el navegador ha terminado
 * de construir el DOM (la estructura HTML de la página). Es el momento seguro
 * para empezar a buscar elementos y añadir comportamientos.
 *
 * Todo el código dentro de este bloque se ejecuta UNA vez al cargar la página.
 */
document.addEventListener('DOMContentLoaded', () => {
    /* Lo primero: cargar y mostrar los favoritos que el usuario tenía guardados */
    cargarFavoritosDesdeStorage();
    actualizar_menu();

    /* ---- MODAL DE CONFIRMACIÓN ----
     * Es una ventana emergente que pregunta "¿Estás seguro?" antes de acciones
     * peligrosas como eliminar un registro. Buscamos los elementos del modal
     * en el DOM para poder controlarlos. */
    const modal = document.getElementById('modalConfirm');
    const modalMensaje = document.getElementById('modalConfirmMessage');
    const btnCancelar = document.getElementById('modalConfirmCancel');
    const btnAceptar = document.getElementById('modalConfirmAccept');
    let formPendiente = null;      // El formulario que espera confirmación
    let submitterPendiente = null;  // El botón específico que se pulsó

    /*
     * cerrarModal: oculta el modal de confirmación.
     * - Quita la clase CSS 'activo' para ocultarlo con una animación.
     * - Establece aria-hidden="true" para accesibilidad (lectores de pantalla).
     * - Limpia referencias al formulario pendiente.
     */
    const cerrarModal = () => {
        if (modal) {
            modal.classList.remove('activo');
            modal.setAttribute('aria-hidden', 'true');
        }
        formPendiente = null;
        submitterPendiente = null;
    };

    /*
     * abrirModal: muestra el modal con un mensaje personalizado.
     * - Pone el texto del mensaje en el párrafo del modal.
     * - Añade la clase 'activo' que activa la CSS que lo hace visible.
     * - Devuelve true si el modal existe, false si no (fallback a confirm nativo).
     */
    const abrirModal = mensaje => {
        if (!modal) {
            return false; // Si no hay modal en el HTML, devolvemos false
        }

        modalMensaje.textContent = mensaje;
        modal.classList.add('activo');
        modal.setAttribute('aria-hidden', 'false');
        return true;
    };

    /* Al hacer clic en "Cancelar" en el modal, lo cerramos sin hacer nada */
    if (btnCancelar) {
        btnCancelar.addEventListener('click', cerrarModal);
    }

    /* También se cierra al hacer clic fuera del contenido del modal (en el fondo oscuro) */
    if (modal) {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                cerrarModal();
            }
        });
    }

    /* Al hacer clic en "Confirmar" en el modal, enviamos el formulario pendiente.
     * requestSubmit() es preferible a submit() porque preserva la validación HTML5
     * y el submitter original (el botón que se pulsó). */
    if (btnAceptar) {
        btnAceptar.addEventListener('click', () => {
            if (formPendiente) {
                if (submitterPendiente && formPendiente.requestSubmit) {
                    formPendiente.requestSubmit(submitterPendiente);
                } else {
                    formPendiente.submit();
                }
            }
            cerrarModal();
        });
    }

    /*
     * INTERCEPTAR ENVÍO DE FORMULARIOS CON CONFIRMACIÓN
     *
     * Buscamos TODOS los formularios de la página. Si un formulario o su botón
     * tienen el atributo data-confirm="mensaje", interceptamos el envío:
     * 1) Paramos el envío normal (preventDefault)
     * 2) Mostramos el modal con el mensaje de confirmación
     * 3) Solo enviamos si el usuario pulsa "Confirmar"
     *
     * Si el modal no existe en el HTML, usamos window.confirm() como alternativa
     * (la ventana nativa del navegador que dice "Aceptar / Cancelar").
     */
    const formulariosConfirmacion = document.querySelectorAll('form');
    formulariosConfirmacion.forEach(form => {
        form.addEventListener('submit', event => {
            /* event.submitter es el botón específico que se pulsó para enviar */
            const submitter = event.submitter;
            const mensaje = submitter?.getAttribute('data-confirm') || form.getAttribute('data-confirm');

            /* Si no hay mensaje de confirmación, dejamos que el formulario se envíe normal */
            if (!mensaje) {
                return;
            }

            /* Paramos el envío y guardamos referencia al formulario */
            event.preventDefault();
            formPendiente = form;
            submitterPendiente = submitter || null;

            /* Intentamos abrir nuestro modal bonito; si no existe, usamos el nativo */
            if (!abrirModal(mensaje)) {
                if (window.confirm(mensaje)) {
                    form.submit();
                } else {
                    cerrarModal();
                }
            }
        });
    });

    
    /*
     * CONFIGURACIÓN INICIAL DEL KANBAN
     *
     * Seleccionamos todas las tarjetas (.tarjeta_prospecto, .tarjeta_proceso)
     * y todas las columnas (.kanban_columna) del tablero.
     * El modo de edición Kanban empieza desactivado: las tarjetas solo se pueden
     * arrastrar cuando el usuario pulsa el botón "Editar orden".
     */
    const tarjetas = document.querySelectorAll('.tarjeta_prospecto, .tarjeta_proceso');
    const columnas = document.querySelectorAll('.kanban_columna');
    const btnEditarKanban = document.querySelector('.btn-editar-kanban') || document.querySelector('.btn-editar-kanban-proceso');
    let tarjetaArrastrada = null;     // Referencia a la tarjeta que se está arrastrando
    let modoEdicionKanban = false;   // ¿Está activado el modo de edición (arrastre)?

    /*
     * Configuramos los eventos de drag para CADA tarjeta del Kanban.
     * Solo se permite arrastrar cuando modoEdicionKanban es true.
     */
    tarjetas.forEach(tarjeta => {
        
        /* dragstart: se dispara cuando el usuario empieza a arrastrar la tarjeta */
        tarjeta.addEventListener('dragstart', (event) => {
            /* Si no estamos en modo edición, bloqueamos el arrastre */
            if (!modoEdicionKanban) {
                event.preventDefault();
                return;
            }

            /* Guardamos referencia a la tarjeta y marcamos su columna de origen */
            tarjetaArrastrada = tarjeta;
            tarjeta.classList.add('arrastrando'); // Clase CSS para efecto visual
            const estadoOrigen = tarjeta.closest('.kanban_columna')?.dataset.estado || '';
            tarjeta.dataset.estadoOrigen = estadoOrigen;

            /* dataTransfer es el objeto que transporta información durante el drag */
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', tarjeta.id || '');
            }
        });

        /* dragend: se dispara al terminar el arrastre (suelte donde suelte) */
        tarjeta.addEventListener('dragend', () => {
            tarjeta.classList.remove('arrastrando');
            tarjetaArrastrada = null;
        });
    });

    /*
     * limpiarZonasActivas: quita el resaltado visual de TODAS las columnas.
     * Cuando arrastramos una tarjeta, la columna sobre la que pasamos se
     * resalta con la clase 'zona_activa'. Esta función limpia ese resaltado.
     */
    const limpiarZonasActivas = () => {
        columnas.forEach(col => col.classList.remove('zona_activa'));
    };

    /*
     * resolverTarjetaArrastrada: identifica cuál es la tarjeta que se está arrastrando.
     * Primero intenta usar la referencia directa (tarjetaArrastrada), luego busca
     * por clase CSS (.arrastrando), y como último recurso busca por el ID almacenado
     * en dataTransfer (el mecanismo nativo de transporte de datos del drag).
     */
    const resolverTarjetaArrastrada = (e) => {
        let tarjetaDestino = tarjetaArrastrada || document.querySelector('.arrastrando');

        if (!tarjetaDestino && e.dataTransfer) {
            const tarjetaId = e.dataTransfer.getData('text/plain');
            if (tarjetaId) {
                tarjetaDestino = document.getElementById(tarjetaId);
            }
        }

        return tarjetaDestino;
    };

    /*
     * guardarCambioEstado: envía al servidor el nuevo estado de una tarjeta.
     *
     * Cuando el usuario arrastra una tarjeta de una columna a otra, necesitamos
     * guardar ese cambio en la base de datos. Para eso usamos fetch() que
     * hace una petición HTTP POST al servidor sin recargar la página.
     *
     * Es una función "async" (asíncrona) porque la petición al servidor tarda
     * un tiempo y no queremos bloquear la interfaz mientras esperamos.
     * "await" pausa la ejecución hasta que la petición termine.
     *
     * @param {number} id     — ID del prospecto/proceso en la base de datos
     * @param {string} estado — Nuevo estado (ej: "Contacto", "Visita", "Oferta")
     */
    const guardarCambioEstado = async (id, estado) => {
        /* Obtenemos la sección actual de la URL para saber qué endpoint usar */
        const params = new URLSearchParams(window.location.search);
        const seccion = params.get('seccion') || '';
        const endpoint = `secciones/${seccion}.php`;

        /* Determinamos si es una sección de "proceso" o de "prospectos" */
        const esProceso = seccion.startsWith('proceso-');
        const esProspectos = seccion.startsWith('prospectos-');

        if (!esProspectos && !esProceso) {
            throw new Error('Seccion no valida para guardar movimiento.');
        }

        /* Construimos los parámetros que enviaremos al servidor.
         * URLSearchParams formatea los datos como "clave=valor&clave2=valor2" */
        const body = new URLSearchParams();
        if (esProceso) {
            body.append('mover_proceso_drag', '1');
            body.append('id', String(id));
            body.append('etapa', estado);
        } else {
            body.append('mover_prospecto_drag', '1');
            body.append('id', String(id));
            body.append('estado', estado);
        }

        /* fetch() hace la petición HTTP al servidor.
         * Incluimos el token CSRF en las cabeceras por seguridad.
         * X-Requested-With indica que es una petición AJAX (no una navegación normal). */
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': getCsrfToken()
            },
            body: body.toString()
        });

        /* Procesamos la respuesta del servidor */
        const text = await response.text();
        let data = null;

        try {
            /* Intentamos interpretar la respuesta como JSON */
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('Respuesta invalida del servidor al guardar.');
        }

        /* Verificamos que la petición fue exitosa tanto a nivel HTTP como de lógica */
        if (!response.ok) {
            throw new Error(data?.mensaje || 'No se pudo guardar el cambio de estado.');
        }

        if (!data.ok) {
            throw new Error(data.mensaje || 'No se pudo guardar el cambio de estado.');
        }
    };

    /*
     * EVENTO DRAGOVER: se dispara continuamente mientras arrastramos sobre un elemento.
     * Debemos llamar a e.preventDefault() para indicar al navegador que esta zona
     * ACEPTA recibir elementos soltados (drop). Sin esto, el drop no funcionaría.
     * También resaltamos visualmente la columna de destino.
     */
    document.addEventListener('dragover', (e) => {
        if (!modoEdicionKanban) {
            return;
        }

        /* Buscamos la columna kanban más cercana al punto del cursor */
        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        e.preventDefault(); // FUNDAMENTAL: permite el drop
        limpiarZonasActivas();
        columna.classList.add('zona_activa'); // Resaltado visual
    });

    /*
     * EVENTO DRAGLEAVE: se dispara cuando el cursor sale de una columna.
     * Quitamos el resaltado visual. Verificamos con contains() que realmente
     * hemos salido de la columna (no solo movido a un hijo interno).
     */
    document.addEventListener('dragleave', (e) => {
        if (!modoEdicionKanban) {
            return;
        }

        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        /* Solo quitamos el resaltado si realmente salimos de la columna */
        if (!columna.contains(e.relatedTarget)) {
            columna.classList.remove('zona_activa');
        }
    });

    /*
     * EVENTO DROP: se dispara cuando el usuario SUELTA la tarjeta sobre una columna.
     *
     * Flujo:
     * 1. Identificamos la columna de destino y la tarjeta arrastrada
     * 2. Movemos visualmente la tarjeta a la nueva columna (inmediato, sin esperar)
     * 3. Actualizamos los contadores numéricos de cada columna
     * 4. Enviamos el cambio al servidor (async)
     * 5. Si el servidor rechaza el cambio, devolvemos la tarjeta a su columna original
     *
     * Esto se llama "optimistic UI": primero actualizamos la interfaz y luego
     * verificamos con el servidor, revirtiendo si hay error.
     */
    document.addEventListener('drop', async (e) => {
        if (!modoEdicionKanban) {
            return;
        }

        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        e.preventDefault();
        e.stopPropagation(); // Evita que el evento suba a elementos padre
        limpiarZonasActivas();

        /* Identificamos el cuerpo de la columna destino y el nuevo estado */
        const cuerpoDestino = columna.querySelector('.kanban_body');
        const estadoDestino = columna.dataset.estado || cuerpoDestino?.dataset.estado || '';
        const tarjetaDestino = resolverTarjetaArrastrada(e);

        /* Validamos que todo existe y que no estamos soltando en la misma columna */
        if (!cuerpoDestino || !estadoDestino || !tarjetaDestino || cuerpoDestino.contains(tarjetaDestino)) {
            return;
        }

        /* Guardamos referencia a la columna de origen (para poder revertir) */
        const cuerpoOrigen = tarjetaDestino.closest('.kanban_body');
        const estadoOrigen = tarjetaDestino.dataset.estadoOrigen || tarjetaDestino.closest('.kanban_columna')?.dataset.estado || '';
        const idTarjeta = parseInt(tarjetaDestino.dataset.id || '', 10);

        /* Paso 1: Mover visualmente la tarjeta a la nueva columna */
        cuerpoDestino.appendChild(tarjetaDestino);
        actualizarContadores();

        /* Si el ID no es válido o no cambió de columna, no necesitamos guardar */
        if (!Number.isInteger(idTarjeta) || idTarjeta <= 0 || estadoOrigen === estadoDestino) {
            return;
        }

        /* Paso 2: Intentar guardar en el servidor */
        try {
            await guardarCambioEstado(idTarjeta, estadoDestino);
            tarjetaDestino.dataset.estadoOrigen = estadoDestino; // Actualizamos el origen
        } catch (error) {
            /* Si falla, revertimos: devolvemos la tarjeta a su columna original */
            if (cuerpoOrigen) {
                cuerpoOrigen.appendChild(tarjetaDestino);
                actualizarContadores();
            }
            alert(error.message || 'No se pudo actualizar el estado del prospecto.');
        }
    });

    /*
     * actualizarModoKanban: sincroniza la interfaz con el estado de edición.
     * - Activa/desactiva el atributo HTML "draggable" en cada tarjeta.
     *   (draggable="true" permite arrastrar, "false" lo impide)
     * - Cambia el texto del botón entre "Editar orden" y "Listo".
     */
    const actualizarModoKanban = () => {
        tarjetas.forEach(tarjeta => {
            tarjeta.setAttribute('draggable', modoEdicionKanban ? 'true' : 'false');
        });

        if (btnEditarKanban) {
            btnEditarKanban.textContent = modoEdicionKanban ? 'Listo' : 'Editar orden';
        }
    };

    /* Al hacer clic en el botón de edición, alternamos el modo y actualizamos la UI */
    if (btnEditarKanban) {
        btnEditarKanban.addEventListener('click', () => {
            modoEdicionKanban = !modoEdicionKanban; // Invertir: true→false o false→true
            limpiarZonasActivas();
            actualizarModoKanban();
        });
    }

    actualizarModoKanban(); // Aplicar estado inicial (siempre desactivado)

    // =========================================
    // TOGGLE FORMULARIOS DE AÑADIR
    // =========================================
    // Cuando hay botones tipo "+ Nuevo" que enlazan a #nuevo..., al hacer clic
    // mostramos/ocultamos el formulario correspondiente dentro de la misma página.
    // classList.toggle('visible') añade la clase si no está, o la quita si ya está.
    const botonesNuevo = document.querySelectorAll('a[href^="#nuevo"], a[href^="#nueva"]');
    botonesNuevo.forEach(boton => {
        boton.addEventListener('click', (e) => {
            e.preventDefault(); // Evitamos que el navegador haga scroll al ancla
            const target = boton.getAttribute('href').substring(1); // Quitamos el '#'
            const formulario = document.getElementById(target);
            if (formulario) {
                formulario.classList.toggle('visible'); // Mostrar/ocultar
            }
        });
    });

    /* =========================================
     * SECCIÓN 3: GUARDAR/RESETEAR ORDEN DEL DASHBOARD EN SERVIDOR
     * =========================================
     * El dashboard tiene tarjetas KPI (métricas rápidas) y paneles (gráficos, tablas).
     * El usuario puede reordenarlos arrastrándolos. El nuevo orden se guarda en el
     * servidor para que al volver a entrar, vea el dashboard como lo dejó.
     */

    /*
     * guardarOrdenDashboardServidor: envía al servidor los IDs de KPIs y paneles
     * en el orden actual. Usa fetch() con POST para enviar los datos.
     *
     * @param {Array} kpis   — Lista ordenada de IDs de tarjetas KPI
     * @param {Array} panels — Lista ordenada de IDs de paneles
     */
    const guardarOrdenDashboardServidor = async (kpis, panels) => {
        const body = new URLSearchParams();
        body.append('dashboard_orden_accion', 'guardar');
        body.append('kpis', JSON.stringify(kpis));    // Convertimos array a texto JSON
        body.append('panels', JSON.stringify(panels));

        const response = await fetch('secciones/dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': getCsrfToken()
            },
            body: body.toString()
        });

        if (!response.ok) {
            throw new Error('No se pudo guardar el orden en servidor.');
        }
    };

    /*
     * resetOrdenDashboardServidor: elimina las preferencias de orden guardadas
     * en el servidor, volviendo al layout por defecto del dashboard.
     */
    const resetOrdenDashboardServidor = async () => {
        const body = new URLSearchParams();
        body.append('dashboard_orden_accion', 'reset');

        const response = await fetch('secciones/dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': getCsrfToken()
            },
            body: body.toString()
        });

        if (!response.ok) {
            throw new Error('No se pudo restablecer el orden en servidor.');
        }
    };

    /*
     * inicializarOrdenDashboard: motor de arrastre personalizado (drag manual con mouse)
     * para reordenar los widgets (KPIs y paneles) del dashboard.
     *
     * A diferencia del Kanban que usa el drag&drop nativo del navegador,
     * aquí implementamos el arrastre manualmente con eventos de mouse (mousedown,
     * mousemove, mouseup) para tener más control sobre la experiencia.
     *
     * Funcionalidades:
     * - Carga el orden guardado (servidor o localStorage) al iniciar
     * - Crea un "placeholder" (hueco visual) donde irá el widget soltado
     * - Hace que el widget "flote" siguiendo al cursor
     * - Guarda el nuevo orden al soltar
     *
     * @param {string} selectorContenedor — Selector CSS del contenedor (ej: '.dashboard_kpis')
     * @param {string} selectorItems      — Selector CSS de cada widget (ej: '.kpi_card')
     * @param {string} storageKey         — Clave para localStorage
     * @param {function} estaEditando     — Función que devuelve true si estamos en modo edición
     * @param {Array} ordenServidor       — Orden guardado en el servidor (puede estar vacío)
     */
    const inicializarOrdenDashboard = (selectorContenedor, selectorItems, storageKey, estaEditando, ordenServidor) => {
        const contenedor = document.querySelector(selectorContenedor);
        if (!contenedor) {
            return; // Si no existe el contenedor en esta página, no hacemos nada
        }

        /* Función auxiliar: obtiene todos los widgets actuales del contenedor */
        const obtenerItems = () => Array.from(contenedor.querySelectorAll(selectorItems));

        /* Variables de estado del drag manual */
        let itemArrastrado = null;   // El widget que estamos arrastrando
        let placeholder = null;      // El hueco visual que ocupa su lugar
        let offsetX = 0;             // Distancia horizontal del cursor al borde del widget
        let offsetY = 0;             // Distancia vertical del cursor al borde del widget
        let pointerX = 0;            // Posición X actual del cursor
        let pointerY = 0;            // Posición Y actual del cursor
        let framePendiente = false;  // Control para requestAnimationFrame

        /*
         * guardarOrden: lee el orden actual de los widgets del DOM y lo guarda
         * en localStorage. Lee el atributo data-dashboard-card de cada widget
         * para obtener su identificador único.
         */
        const guardarOrden = () => {
            const orden = obtenerItems()
                .map(item => item.dataset.dashboardCard) // Extraemos el ID de cada widget
                .filter(Boolean); // Filtramos los que no tienen ID

            if (orden.length > 0) {
                localStorage.setItem(storageKey, JSON.stringify(orden));
            }

            return orden;
        };

        /*
         * aplicarOrden: reordena los widgets del DOM según una lista de IDs.
         * Usa un Map (diccionario) para encontrar rápidamente cada widget por su ID
         * y lo mueve al final del contenedor en el orden correcto.
         * appendChild mueve el nodo al final (no lo duplica).
         */
        const aplicarOrden = ids => {
            if (!Array.isArray(ids)) {
                return;
            }

            const items = obtenerItems();
            const mapa = new Map(items.map(item => [item.dataset.dashboardCard, item]));

            ids.forEach(id => {
                const el = mapa.get(id);
                if (el) {
                    contenedor.appendChild(el);
                }
            });
        };

        /*
         * aplicarOrdenGuardado: carga y aplica el orden guardado previamente.
         *
         * Prioridad de carga:
         * 1º) Servidor (preferencias del usuario en base de datos) — más fiable
         * 2º) localStorage (fallback local en el navegador)
         * Si no hay nada guardado, se usa el orden por defecto del HTML.
         */
        const aplicarOrdenGuardado = () => {
            /* Prioridad 1: orden del servidor */
            if (Array.isArray(ordenServidor) && ordenServidor.length > 0) {
                aplicarOrden(ordenServidor);
                localStorage.setItem(storageKey, JSON.stringify(ordenServidor));
                return;
            }

            /* Prioridad 2: orden de localStorage */
            const ordenGuardado = localStorage.getItem(storageKey);
            if (!ordenGuardado) {
                return; // No hay nada guardado, usamos el orden del HTML
            }

            let ids = [];
            try {
                ids = JSON.parse(ordenGuardado);
            } catch (_error) {
                return; // Datos corruptos, los ignoramos
            }

            aplicarOrden(ids);
        };

        /*
         * limpiarDrag: finaliza el ciclo de arrastre.
         * - Restaura los estilos del widget (quita la clase de "flotante")
         * - Sustituye el placeholder por el widget real en su nueva posición
         * - Quita la clase del body que indica que estamos reordenando
         * - Guarda el nuevo orden en localStorage
         */
        const limpiarDrag = () => {
            if (!itemArrastrado) {
                return;
            }

            /* Restaurar estilos originales del widget */
            itemArrastrado.classList.remove('dashboard_drag_flotante');
            itemArrastrado.style.left = '';
            itemArrastrado.style.top = '';
            itemArrastrado.style.width = '';
            itemArrastrado.style.height = '';

            /* Poner el widget real donde estaba el placeholder */
            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.replaceChild(itemArrastrado, placeholder);
            }

            /* Quitar indicador visual de "reordenando" */
            document.body.classList.remove('dashboard_reordenando');

            itemArrastrado = null;
            placeholder = null;
            guardarOrden(); // Persistir el nuevo orden
        };

        /*
         * moverPlaceholder: decide dónde colocar el hueco (placeholder) mientras arrastramos.
         *
         * Usa document.elementFromPoint() para saber qué elemento hay bajo el cursor.
         * Luego calcula si debe insertar el placeholder ANTES o DESPUÉS del widget
         * objetivo, comparando la posición del cursor con el punto medio del widget.
         */
        const moverPlaceholder = (clientX, clientY) => {
            if (!placeholder) {
                return;
            }

            /* Detectamos qué elemento hay justo bajo el cursor */
            const elBajoPuntero = document.elementFromPoint(clientX, clientY);
            const itemObjetivo = elBajoPuntero ? elBajoPuntero.closest(selectorItems) : null;

            /* Si hay un widget bajo el cursor (que no sea el que arrastramos ni el placeholder) */
            if (itemObjetivo && itemObjetivo !== itemArrastrado && itemObjetivo !== placeholder && contenedor.contains(itemObjetivo)) {
                const rect = itemObjetivo.getBoundingClientRect();
                /* Calculamos el centro del widget objetivo */
                const mitadY = rect.top + rect.height / 2;
                const mitadX = rect.left + rect.width / 2;
                /* Si estamos en la misma fila, comparamos X; si no, comparamos Y */
                const mismaFila = Math.abs(clientY - mitadY) <= rect.height * 0.35;
                const insertarAntes = mismaFila ? clientX < mitadX : clientY < mitadY;
                const referencia = insertarAntes ? itemObjetivo : itemObjetivo.nextSibling;

                if (referencia !== placeholder && placeholder.nextSibling !== referencia) {
                    contenedor.insertBefore(placeholder, referencia || null);
                }
                return;
            }

            /* Si estamos fuera de los widgets, ponemos el placeholder al inicio o final */
            const rectCont = contenedor.getBoundingClientRect();
            if (clientY < rectCont.top) {
                contenedor.insertBefore(placeholder, contenedor.firstChild);
            } else if (clientY > rectCont.bottom) {
                contenedor.appendChild(placeholder);
            }
        };

        /*
         * procesarFrame: actualiza la posición visual del widget flotante.
         *
         * Usa requestAnimationFrame para sincronizarse con el refresco de pantalla
         * (~60 veces por segundo). Esto hace que el movimiento sea suave y no
         * sobrecargue el procesador moviendo el elemento en cada evento mousemove.
         */
        const procesarFrame = () => {
            framePendiente = false;

            if (!itemArrastrado) {
                return;
            }

            /* Posicionamos el widget flotante bajo el cursor */
            itemArrastrado.style.left = `${pointerX - offsetX}px`;
            itemArrastrado.style.top = `${pointerY - offsetY}px`;
            /* Recalculamos dónde debe ir el placeholder */
            moverPlaceholder(pointerX, pointerY);
        };

        /*
         * onMouseMove: se ejecuta cada vez que el cursor se mueve mientras arrastramos.
         * Guarda la posición actual del cursor y solicita un frame de animación
         * (solo si no hay uno pendiente, para evitar saturar el navegador).
         */
        const onMouseMove = event => {
            if (!itemArrastrado) {
                return;
            }

            pointerX = event.clientX; // Posición X del cursor
            pointerY = event.clientY; // Posición Y del cursor

            if (!framePendiente) {
                framePendiente = true;
                requestAnimationFrame(procesarFrame); // Solicitar frame de animación
            }
        };

        /*
         * onMouseUp: se ejecuta cuando el usuario suelta el botón del mouse.
         * Finaliza el arrastre y limpia los event listeners temporales.
         */
        const onMouseUp = () => {
            if (!itemArrastrado) {
                return;
            }

            limpiarDrag(); // Finalizar arrastre y guardar orden
            /* Quitamos los listeners temporales del documento */
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        /*
         * iniciarDrag: comienza el arrastre manual de un widget.
         *
         * 1. Calcula el offset (distancia del cursor al borde del widget)
         *    para que el widget no "salte" al empezar a arrastrarlo.
         * 2. Crea un placeholder (div vacío con las mismas dimensiones)
         *    que ocupa el espacio que deja el widget.
         * 3. Mueve el widget al <body> para que pueda "flotar" sobre todo.
         * 4. Añade listeners temporales de mousemove y mouseup al document.
         */
        const iniciarDrag = (item, event) => {
            /* getBoundingClientRect() devuelve posición y tamaño del widget */
            const rect = item.getBoundingClientRect();
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            pointerX = event.clientX;
            pointerY = event.clientY;

            /* Creamos el placeholder: un div vacío del mismo tamaño */
            placeholder = document.createElement('div');
            placeholder.className = 'dashboard_drag_placeholder';
            placeholder.style.width = `${rect.width}px`;
            placeholder.style.height = `${rect.height}px`;

            itemArrastrado = item;

            /* Insertamos el placeholder donde estaba el widget */
            item.parentNode.insertBefore(placeholder, item);
            /* Movemos el widget al body para que flote sobre toda la página */
            document.body.appendChild(item);

            /* Aplicamos estilos de flotación (posición absoluta, tamaño fijo) */
            item.classList.add('dashboard_drag_flotante');
            item.style.width = `${rect.width}px`;
            item.style.height = `${rect.height}px`;
            item.style.left = `${event.clientX - offsetX}px`;
            item.style.top = `${event.clientY - offsetY}px`;
            /* Clase en el body para cambiar el cursor y prevenir selección de texto */
            document.body.classList.add('dashboard_reordenando');

            /* Listeners temporales: se quitan cuando se suelta el mouse */
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        };

        /* Aplicamos el orden guardado al inicializar */
        aplicarOrdenGuardado();

        /*
         * Listener en el contenedor: al hacer clic y arrastrar un widget.
         * Verificamos que estamos en modo edición y que el clic fue sobre un widget
         * (no sobre un botón, enlace, input u otro control interactivo).
         */
        contenedor.addEventListener('mousedown', event => {
            if (!estaEditando()) {
                return; // Solo arrastramos en modo edición
            }

            const item = event.target.closest(selectorItems);
            if (!item || !contenedor.contains(item)) {
                return; // No hicimos clic sobre un widget
            }

            /* Ignoramos clics sobre controles interactivos para no interferir */
            const tag = (event.target.tagName || '').toLowerCase();
            if (['a', 'button', 'input', 'select', 'textarea', 'label', 'summary'].includes(tag)) {
                return;
            }

            event.preventDefault();
            iniciarDrag(item, event); // Comenzar el arrastre
        });
    };

    /* ---- CONTROLES DE EDICIÓN DEL DASHBOARD ---- */
    const btnEditarOrden = document.getElementById('btnEditarOrdenDashboard');
    const btnResetOrden = document.getElementById('btnResetOrdenDashboard');
    const estadoOrden = document.getElementById('estadoOrdenDashboard');
    let modoEdicionDashboard = false;       // ¿Estamos en modo edición del dashboard?
    let temporizadorEstadoOrden = null;     // Timer para ocultar el mensaje de estado

    /*
     * mostrarEstadoOrden: muestra un mensaje breve de confirmación o error.
     * El mensaje aparece durante 2.2 segundos y luego se oculta automáticamente.
     * setTimeout programa la desaparición del mensaje tras el retardo indicado.
     */
    const mostrarEstadoOrden = (mensaje, esError = false) => {
        if (!estadoOrden) {
            return;
        }

        /* Si hay un temporizador previo, lo cancelamos */
        if (temporizadorEstadoOrden) {
            clearTimeout(temporizadorEstadoOrden);
        }

        estadoOrden.textContent = mensaje;
        estadoOrden.style.color = esError ? '#c0392b' : '#27ae60'; // Rojo si error, verde si ok
        estadoOrden.classList.add('visible');

        /* Ocultar automáticamente después de 2.2 segundos */
        temporizadorEstadoOrden = window.setTimeout(() => {
            estadoOrden.classList.remove('visible');
        }, 2200);
    };

    /* Función que devuelve si estamos en modo edición (usada por inicializarOrdenDashboard) */
    const estaEditandoDashboard = () => modoEdicionDashboard;

    /*
     * actualizarModoDashboard: sincroniza la interfaz con el estado de edición.
     * - Añade/quita la clase 'editando' en los contenedores de KPIs y paneles
     *   (la CSS usa esta clase para mostrar cursores de arrastre, bordes, etc.)
     * - Cambia el texto del botón entre "Editar orden" y "Listo"
     */
    const actualizarModoDashboard = () => {
        const contKpis = document.querySelector('.dashboard_kpis');
        const contPaneles = document.querySelector('.dashboard_grid');

        /* classList.toggle(clase, condición) añade la clase si condición es true, la quita si es false */
        if (contKpis) {
            contKpis.classList.toggle('editando', modoEdicionDashboard);
        }
        if (contPaneles) {
            contPaneles.classList.toggle('editando', modoEdicionDashboard);
        }

        if (btnEditarOrden) {
            btnEditarOrden.textContent = modoEdicionDashboard ? 'Listo' : 'Editar orden';
        }
    };

    /* Botón "Editar orden" / "Listo": alterna modo de edición del dashboard.
     * Al SALIR del modo edición, guardamos el orden actual en el servidor.
     * .then() se ejecuta si la petición tiene éxito.
     * .catch() se ejecuta si hay error (muestra "guardado local" como fallback). */
    if (btnEditarOrden) {
        btnEditarOrden.addEventListener('click', () => {
            modoEdicionDashboard = !modoEdicionDashboard;
            actualizarModoDashboard();

            /* Al salir de modo edición: persiste orden actual en servidor */
            if (!modoEdicionDashboard) {
                /* Leemos los IDs de KPIs y paneles en su orden actual */
                const ordenKpis = Array.from(document.querySelectorAll('.dashboard_kpis .kpi_card[data-dashboard-card]')).map(el => el.dataset.dashboardCard);
                const ordenPanels = Array.from(document.querySelectorAll('.dashboard_grid .panel_panel[data-dashboard-card]')).map(el => el.dataset.dashboardCard);

                guardarOrdenDashboardServidor(ordenKpis, ordenPanels)
                    .then(() => {
                        mostrarEstadoOrden('Orden guardado');
                    })
                    .catch(() => {
                        mostrarEstadoOrden('Guardado local aplicado', true);
                    });
            }
        });
    }

    /* Botón "Reset orden": elimina las preferencias de orden y recarga la página.
     * Primero limpia localStorage, luego el servidor, y finalmente recarga
     * para que el dashboard se muestre con el layout por defecto. */
    if (btnResetOrden) {
        btnResetOrden.addEventListener('click', async () => {
            /* Limpiamos las preferencias locales */
            localStorage.removeItem('tinoprop.dashboard.kpis.order');
            localStorage.removeItem('tinoprop.dashboard.panels.order');

            try {
                /* Limpiamos en el servidor también */
                await resetOrdenDashboardServidor();
                mostrarEstadoOrden('Orden restablecido');
            } catch (_error) {
                mostrarEstadoOrden('Restablecido local', true);
            }

            /* Recargamos la página para que se aplique el orden por defecto */
            window.location.reload();
        });
    }

    actualizarModoDashboard(); // Aplicar estado inicial del dashboard

    /* Cargamos las preferencias de orden del dashboard (si existen en el servidor).
     * window.tinoPrefDashboard es un objeto que el PHP inyecta en el HTML
     * con el orden guardado del usuario en la base de datos. */
    const prefDashboard = window.tinoPrefDashboard || {};

    /* Inicializamos el reordenamiento para las tarjetas KPI (métricas rápidas) */
    inicializarOrdenDashboard('.dashboard_kpis', '.kpi_card[data-dashboard-card]', 'tinoprop.dashboard.kpis.order', estaEditandoDashboard, prefDashboard.kpis || []);

    /* Inicializamos el reordenamiento para los paneles (gráficos, tablas, etc.) */
    inicializarOrdenDashboard('.dashboard_grid', '.panel_panel[data-dashboard-card]', 'tinoprop.dashboard.panels.order', estaEditandoDashboard, prefDashboard.panels || []);
});

/*
 * actualizarContadores: recalcula y actualiza los números que aparecen en la
 * cabecera de cada columna del Kanban (ej: "Contacto (3)", "Visita (1)").
 *
 * Se llama cada vez que una tarjeta se mueve entre columnas para que los
 * números siempre reflejen la cantidad real de tarjetas en cada estado.
 */
function actualizarContadores() {
    /* Buscamos TODAS las columnas del Kanban */
    const todasLasColumnas = document.querySelectorAll('.kanban_columna');

    todasLasColumnas.forEach(col => {
        const cuerpo = col.querySelector('.kanban_body');
        const contadorSpan = col.querySelector('.contador');
        
        /* Contamos cuántas tarjetas hay dentro de esta columna ahora mismo */
        const cantidad = cuerpo.querySelectorAll('.tarjeta_prospecto, .tarjeta_proceso').length;
        
        /* Actualizamos el texto del <span class="contador"> con el nuevo número */
        if (contadorSpan) contadorSpan.textContent = cantidad;
    });
}


/* =============================================
   SECCIÓN 4: SIDEBAR COLAPSABLE
   =============================================
   El sidebar (menú lateral) puede colapsar para mostrar solo iconos
   y ahorrar espacio horizontal. El estado se guarda en localStorage
   para que se mantenga al recargar la página.
   =============================================  */
function initSidebarToggle() {
    const btn = document.getElementById('btnColapsarSidebar');
    const sidebar = document.getElementById('sidebar');
    if (!btn || !sidebar) return; // Si no existen estos elementos, no hacemos nada

    /* Asignamos tooltips (texto emergente) a cada enlace del menú.
     * Cuando el sidebar está colapsado y solo se ven iconos, el tooltip
     * muestra el nombre de la sección al pasar el cursor por encima. */
    sidebar.querySelectorAll('.grupo_menu ul li a').forEach(link => {
        const span = link.querySelector('.sidebar_titulo');
        if (span && !link.hasAttribute('data-tooltip')) {
            link.setAttribute('data-tooltip', span.textContent.trim());
        }
    });

    /* Comprobamos si el usuario tenía el sidebar colapsado en su última visita */
    const STORAGE_KEY = 'tinoprop.sidebar.colapsado';
    const guardado = localStorage.getItem(STORAGE_KEY);
    if (guardado === 'true') sidebar.classList.add('sidebar_colapsado');

    /* Al hacer clic en el botón ◀, alternamos el estado colapsado */
    btn.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar_colapsado');
        const colapsado = sidebar.classList.contains('sidebar_colapsado');
        localStorage.setItem(STORAGE_KEY, colapsado); // Guardamos preferencia
    });
}

/* =============================================
   SECCIÓN 5: MENÚ HAMBURGUESA EN MÓVIL
   =============================================
   En pantallas pequeñas (móviles/tablets), el sidebar se oculta por defecto.
   Un botón con el símbolo ☰ (hamburguesa) lo abre/cierra como panel lateral.
   Un overlay (fondo semitransparente oscuro) cubre el contenido y al hacer
   clic en él se cierra el menú.
   ============================================= */
function initHamburguesaMovil() {
    const btn = document.getElementById('btnHamburguesa');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar) return;

    /* toggleMovil: alterna visibilidad del sidebar en móvil */
    const toggleMovil = () => {
        sidebar.classList.toggle('sidebar_movil_abierto');
        if (overlay) overlay.classList.toggle('activo'); // Mostrar/ocultar fondo oscuro
    };

    /* cerrarMovil: cierra el sidebar móvil de forma explícita */
    const cerrarMovil = () => {
        sidebar.classList.remove('sidebar_movil_abierto');
        if (overlay) overlay.classList.remove('activo');
    };

    /* Eventos: botón hamburguesa abre/cierra, overlay cierra */
    btn.addEventListener('click', toggleMovil);
    if (overlay) overlay.addEventListener('click', cerrarMovil);

    /* Al navegar a una sección (clic en enlace), cerramos el menú móvil */
    sidebar.querySelectorAll('a[href]').forEach(link => {
        link.addEventListener('click', cerrarMovil);
    });
}


/* =============================================
   SECCIÓN 6: BUSCADOR GLOBAL
   =============================================
   Campo de búsqueda en el sidebar que busca clientes, propiedades, etc.
   en tiempo real mientras el usuario escribe.

   Usa "debounce": espera 300ms después de la última tecla antes de
   hacer la petición al servidor. Así, si el usuario escribe rápido
   (ej: "pedro"), no se hacen 5 peticiones (p, pe, ped, pedr, pedro),
   sino solo 1 cuando para de escribir.

   fetch() consulta al endpoint api/buscar.php con el término de búsqueda
   y recibe los resultados en formato JSON.
   ============================================= */
function initBuscadorGlobal() {
    const input = document.getElementById('buscadorGlobal');
    const contenedor = document.getElementById('resultadosBusqueda');
    if (!input || !contenedor) return;

    let debounceTimer = null; // Temporizador del debounce

    /* Cierra el panel de resultados y lo vacía */
    const cerrarResultados = () => {
        contenedor.classList.remove('activo');
        contenedor.innerHTML = '';
    };

    /* Evento 'input': se dispara cada vez que cambia el texto del campo */
    input.addEventListener('input', () => {
        const termino = input.value.trim();
        clearTimeout(debounceTimer); // Cancelamos el timer anterior

        /* Si el término tiene menos de 2 caracteres, cerramos resultados */
        if (termino.length < 2) { cerrarResultados(); return; }

        /* Esperamos 300ms sin que el usuario escriba más para buscar */
        debounceTimer = setTimeout(async () => {
            try {
                /* Hacemos la petición GET al API de búsqueda.
                 * encodeURIComponent codifica caracteres especiales para la URL */
                const resp = await fetch('api/buscar.php?q=' + encodeURIComponent(termino));
                const datos = await resp.json(); // Convertimos la respuesta a JSON

                /* Si no hay resultados, mostramos un mensaje */
                if (!datos.length) {
                    contenedor.innerHTML = '<div class="resultado_vacio">Sin resultados</div>';
                    contenedor.classList.add('activo');
                    return;
                }

                /* Función auxiliar para escapar HTML y prevenir inyección de código (XSS).
                 * Crea un div temporal y usa textContent (que escapa automáticamente). */
                const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

                /* Construimos el HTML de resultados usando template literals (``) */
                contenedor.innerHTML = datos.map(r => `
                    <a href="${esc(r.url)}" class="resultado_item">
                        <span class="resultado_icono">${esc(r.icono)}</span>
                        <div class="resultado_info">
                            <div class="resultado_titulo">${esc(r.titulo)}</div>
                            <div class="resultado_detalle">${esc(r.detalle || '')}</div>
                        </div>
                    </a>
                `).join(''); // join('') une todos los HTML de resultado en un solo string
                contenedor.classList.add('activo'); // Mostramos el panel de resultados
            } catch (err) {
                /* Si hay un error de red o del servidor, mostramos un mensaje de error */
                contenedor.innerHTML = '<div class="resultado_vacio">Error de búsqueda</div>';
                contenedor.classList.add('activo');
            }
        }, 300); // 300 milisegundos de espera (debounce)
    });

    /* Cerramos los resultados al hacer clic fuera del buscador.
     * closest() comprueba si el clic fue dentro del contenedor del buscador. */
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.buscador_global_wrap')) cerrarResultados();
    });
}


/* =============================================
   SECCIÓN 7: NOTIFICACIONES DROPDOWN
   =============================================
   Un botón de campana (🔔) que al hacer clic muestra u oculta
   un panel desplegable con las alertas pendientes del usuario.
   Se cierra al hacer clic en cualquier parte fuera del panel.
   ============================================= */
function initNotificaciones() {
    const btn = document.getElementById('btnNotificaciones');
    const dropdown = document.getElementById('dropdownNotificaciones');
    if (!btn || !dropdown) return;

    /* Al hacer clic en la campana, alternamos visibilidad del dropdown.
     * stopPropagation() evita que el clic "suba" al document (lo que lo cerraría). */
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('activo');
    });

    /* Cerramos el dropdown al hacer clic en cualquier lugar fuera de él */
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.notificaciones_wrap')) {
            dropdown.classList.remove('activo');
        }
    });
}


/* =============================================
   SECCIÓN 8: ORDENAR COLUMNAS DE TABLA
   =============================================
   Cuando las cabeceras de una tabla tienen la clase 'th_ordenable',
   al hacer clic se reordenan las filas por esa columna.
   Soporta tres tipos de datos:
   - Números (detecta decimales, monedas con € $ , . )
   - Fechas (formatos dd/mm/yyyy y yyyy-mm-dd)
   - Texto (ordenación alfabética con localeCompare en español)

   Alterna entre ascendente (↑) y descendente (↓) en cada clic.
   ============================================= */
function initOrdenarTablas() {
    /* Buscamos todos los <th> con clase th_ordenable */
    document.querySelectorAll('.th_ordenable').forEach(th => {
        th.addEventListener('click', () => {
            const tabla = th.closest('table');       // La tabla que contiene este <th>
            const tbody = tabla.querySelector('tbody'); // El cuerpo de la tabla
            if (!tbody) return;

            /* Averiguamos qué número de columna es (0, 1, 2...) */
            const colIndex = Array.from(th.parentElement.children).indexOf(th);
            /* Obtenemos todas las filas del cuerpo de la tabla */
            const filas = Array.from(tbody.querySelectorAll('tr'));

            /* Determinamos la dirección: si ya está ascendente, cambiamos a descendente */
            const esAsc = th.classList.contains('asc');
            /* Limpiamos el estado de ordenación de TODAS las columnas */
            th.closest('tr').querySelectorAll('.th_ordenable').forEach(t => t.classList.remove('asc', 'desc'));

            /* Aplicamos la nueva dirección a esta columna */
            if (esAsc) {
                th.classList.add('desc'); // Si estaba ascendente, ahora descendente
            } else {
                th.classList.add('asc');  // Si no, ascendente
            }

            const dir = th.classList.contains('asc') ? 1 : -1; // 1 = asc, -1 = desc

            /* Ordenamos las filas comparando los valores de la columna seleccionada */
            filas.sort((a, b) => {
                /* Obtenemos el texto de la celda en esta columna para cada fila */
                const aVal = (a.children[colIndex]?.textContent || '').trim().toLowerCase();
                const bVal = (b.children[colIndex]?.textContent || '').trim().toLowerCase();

                /* Intento 1: comparar como números (quitando símbolos de moneda) */
                const aNum = parseFloat(aVal.replace(/[€$,.\s]/g, ''));
                const bNum = parseFloat(bVal.replace(/[€$,.\s]/g, ''));
                if (!isNaN(aNum) && !isNaN(bNum)) return (aNum - bNum) * dir;

                /* Intento 2: comparar como fechas */
                const fechaA = parseFechaTabla(aVal);
                const fechaB = parseFechaTabla(bVal);
                if (fechaA && fechaB) return (fechaA - fechaB) * dir;

                /* Intento 3: comparar como texto (con soporte de é, ñ, etc.) */
                return aVal.localeCompare(bVal, 'es') * dir;
            });

            /* Reinsertamos las filas en el DOM en su nuevo orden.
             * appendChild mueve (no duplica) el nodo, así se reordena la tabla. */
            filas.forEach(f => tbody.appendChild(f));
        });
    });
}

/*
 * parseFechaTabla: convierte un texto de fecha en un objeto Date de JavaScript.
 *
 * Soporta dos formatos comunes:
 * - dd/mm/yyyy (formato europeo/español, ej: 25/12/2025)
 * - yyyy-mm-dd (formato ISO/base de datos, ej: 2025-12-25)
 *
 * Usa expresiones regulares (regex) para detectar el formato:
 * - \d{2} = exactamente 2 dígitos
 * - \d{4} = exactamente 4 dígitos
 * - m[1], m[2], m[3] son los grupos capturados (entre paréntesis)
 *
 * Devuelve null si el texto no coincide con ningún formato reconocido.
 * Nota: en Date(), los meses van de 0 a 11, por eso restamos 1.
 */
function parseFechaTabla(str) {
    /* Intentar formato dd/mm/yyyy */
    let m = str.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) return new Date(m[3], m[2] - 1, m[1]); // año, mes-1, día
    /* Intentar formato yyyy-mm-dd */
    m = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m) return new Date(m[1], m[2] - 1, m[3]); // año, mes-1, día
    return null; // No se reconoció el formato
}


/* =============================================
   SECCIÓN 9: VALIDACIÓN DE FORMULARIOS
   =============================================
   Valida los campos obligatorios de los formularios ANTES de enviarlos
   al servidor. Esto mejora la experiencia del usuario porque ve los
   errores inmediatamente sin esperar una respuesta del servidor.

   Solo se activa en formularios con el atributo data-validar.
   Valida:
   - Campos vacíos (required)
   - Formato de email (expresión regular básica)
   - Formato de teléfono (6-20 caracteres, dígitos, espacios, +, -, (, ))

   También valida "en vivo" (al salir de cada campo) para dar feedback
   inmediato al usuario mientras completa el formulario.
   ============================================= */
function initValidacionFormularios() {
    /* Buscamos todos los formularios con el atributo data-validar */
    document.querySelectorAll('form[data-validar]').forEach(form => {
        /* Desactivamos la validación nativa del navegador para usar la nuestra */
        form.setAttribute('novalidate', '');

        /* Evento submit: se dispara al intentar enviar el formulario */
        form.addEventListener('submit', (e) => {
            let valido = true;

            /* Limpiamos mensajes de error de validaciones anteriores */
            form.querySelectorAll('.error_campo').forEach(el => el.remove());
            form.querySelectorAll('.campo_invalido, .campo_valido').forEach(el => {
                el.classList.remove('campo_invalido', 'campo_valido');
            });

            /* Recorremos todos los campos obligatorios (required) */
            form.querySelectorAll('input[required], select[required], textarea[required]').forEach(campo => {
                const valor = campo.value.trim();

                if (!valor) {
                    /* Campo vacío: marcamos como inválido y mostramos mensaje */
                    campo.classList.add('campo_invalido');
                    const msg = document.createElement('span');
                    msg.className = 'error_campo';
                    msg.textContent = 'Este campo es obligatorio';
                    campo.parentElement.appendChild(msg);
                    valido = false;
                } else {
                    /* Validaciones específicas según el tipo de campo */
                    if (campo.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
                        /* Email inválido: no cumple el formato x@x.x */
                        campo.classList.add('campo_invalido');
                        const msg = document.createElement('span');
                        msg.className = 'error_campo';
                        msg.textContent = 'Email no válido';
                        campo.parentElement.appendChild(msg);
                        valido = false;
                    } else if (campo.type === 'tel' && !/^[\d\s\+\-\(\)]{6,20}$/.test(valor)) {
                        /* Teléfono inválido: debe tener entre 6 y 20 caracteres válidos */
                        campo.classList.add('campo_invalido');
                        const msg = document.createElement('span');
                        msg.className = 'error_campo';
                        msg.textContent = 'Teléfono no válido';
                        campo.parentElement.appendChild(msg);
                        valido = false;
                    } else {
                        /* Campo válido: marcamos con borde verde */
                        campo.classList.add('campo_valido');
                    }
                }
            });

            /* Si hay errores, no enviamos el formulario */
            if (!valido) {
                e.preventDefault(); // Bloqueamos el envío
                /* Hacemos scroll al primer campo con error y le damos foco */
                const primerError = form.querySelector('.campo_invalido');
                if (primerError) primerError.focus();
            }
        });

        /*
         * Validación en vivo: al salir de un campo (evento 'blur'),
         * limpiamos el mensaje de error si el usuario ya escribió algo.
         * Esto da feedback inmediato sin esperar al submit.
         */
        form.querySelectorAll('input[required], select[required], textarea[required]').forEach(campo => {
            campo.addEventListener('blur', () => {
                const msg = campo.parentElement.querySelector('.error_campo');
                if (msg) msg.remove(); // Quitamos el mensaje de error
                campo.classList.remove('campo_invalido', 'campo_valido');

                if (campo.value.trim()) {
                    campo.classList.add('campo_valido'); // Marcamos como válido si tiene valor
                }
            });
        });
    });
}


/* =============================================
   SECCIÓN 10: INICIALIZACIÓN DE TODOS LOS MÓDULOS
   =============================================
   Este segundo listener de DOMContentLoaded inicializa todos los módulos
   auxiliares de la interfaz. Cada función init* configura una
   funcionalidad específica de la aplicación.

   Se ejecuta cuando el DOM está listo (igual que el primer bloque
   de arriba que maneja el Kanban y el Dashboard).
   ============================================= */
document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();          // Sidebar colapsable con botón ◀
    initHamburguesaMovil();       // Menú hamburguesa para móvil
    initBuscadorGlobal();         // Buscador con debounce y fetch
    initNotificaciones();         // Dropdown de notificaciones
    initOrdenarTablas();          // Ordenar columnas de tabla al clic
    initValidacionFormularios();  // Validación de formularios client-side
});
