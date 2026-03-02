/*
 * Archivo: js/script.js — V0.0.28 Mejoras
 * Rol: interacciones globales del frontend.
 * Incluye: favoritos de menú, modal de confirmación, drag&drop de kanban,
 *          orden de widgets del dashboard, sidebar colapsable, buscador global,
 *          ordenar columnas de tabla, validación de formularios, notificaciones.
 *
 * Índice de funciones principales:
 * - toggle_favorito(nombre): añade o quita elementos del listado de favoritos.
 * - actualizar_menu(): renderiza el menú de favoritos en el sidebar.
 * - cerrarModal()/abrirModal(mensaje): controlan el modal de confirmación global.
 * - limpiarZonasActivas()/resolverTarjetaArrastrada(e): utilidades del drag&drop kanban.
 * - actualizarModoKanban(): activa/desactiva modo edición del tablero kanban.
 * - inicializarOrdenDashboard(...): habilita reordenado de widgets en dashboard.
 * - obtenerItems()/guardarOrden()/aplicarOrdenGuardado(): persistencia y restauración del orden.
 * - limpiarDrag()/moverPlaceholder()/procesarFrame()/onMouseUp()/iniciarDrag(): ciclo completo de drag para widgets.
 * - mostrarEstadoOrden(mensaje, esError): feedback visual de guardado/reset de orden.
 * - estaEditandoDashboard()/actualizarModoDashboard(): estado de edición de dashboard.
 * - actualizarContadores(): recalcula contadores de tarjetas por columna en kanban.
 * - initSidebarToggle(): sidebar colapsable.
 * - initBuscadorGlobal(): buscador global con debounce y fetch.
 * - initOrdenarTablas(): ordenar columnas de tabla al hacer clic.
 * - initValidacionFormularios(): validación client-side en inputs required.
 * - initNotificaciones(): dropdown de notificaciones.
 */

let favoritos = [];
const STORAGE_KEY_FAVORITOS = 'tinoprop.menu.favoritos';

/* Helper global: obtiene el token CSRF desde el meta tag para peticiones fetch */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function normalizarFavorito(item) {
    // Soporta formato legado (string) y formato nuevo ({ nombre, url }).
    if (typeof item === 'string') {
        const nombre = item.trim();
        return nombre ? { nombre, url: '' } : null;
    }

    if (!item || typeof item !== 'object') {
        return null;
    }

    const nombre = String(item.nombre || '').trim();
    const url = String(item.url || '').trim();

    if (!nombre) {
        return null;
    }

    return { nombre, url };
}

function deduplicarFavoritos(items) {
    const salida = [];
    const vistos = new Set();

    items.forEach(item => {
        const normalizado = normalizarFavorito(item);
        if (!normalizado) {
            return;
        }

        // Clave estable por URL cuando exista; fallback por nombre para legacy.
        const key = normalizado.url ? `url:${normalizado.url}` : `name:${normalizado.nombre}`;
        if (vistos.has(key)) {
            return;
        }

        vistos.add(key);
        salida.push(normalizado);
    });

    return salida;
}

function guardarFavoritosEnStorage() {
    // Persistencia local para conservar favoritos entre recargas/sesiones del navegador.
    localStorage.setItem(STORAGE_KEY_FAVORITOS, JSON.stringify(favoritos));
}

function cargarFavoritosDesdeStorage() {
    // Carga inicial con tolerancia a datos corruptos y migración de formato antiguo.
    const raw = localStorage.getItem(STORAGE_KEY_FAVORITOS);
    if (!raw) {
        favoritos = [];
        return;
    }

    try {
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            favoritos = [];
            return;
        }

        favoritos = deduplicarFavoritos(parsed);

        // Reescribe storage ya normalizado para mantener consistencia futura.
        guardarFavoritosEnStorage();
    } catch (_error) {
        favoritos = [];
    }
}

function obtenerDatosFavoritoDesdeEvento(nombre) {
    const objetivo = window.event?.target;
    const itemMenu = objetivo?.closest?.('li');
    const enlace = itemMenu?.querySelector?.('a[href]');
    const url = enlace?.getAttribute('href') || '';
    const titulo = (nombre || enlace?.textContent || '').trim();

    return {
        nombre: titulo,
        url,
    };
}

function toggle_favorito(nombre, urlPreferida = '') {
    const candidato = obtenerDatosFavoritoDesdeEvento(nombre);
    if (urlPreferida && !candidato.url) {
        candidato.url = urlPreferida;
    }

    if (!candidato.nombre) {
        return;
    }

    // Clave primaria por URL para evitar colisiones entre secciones con mismo nombre.
    // Fallback por nombre cuando no exista URL (compatibilidad).
    const index = favoritos.findIndex(fav => {
        if (candidato.url && fav.url) {
            return fav.url === candidato.url;
        }
        return fav.nombre === candidato.nombre;
    });

    if (index === -1) {
        favoritos.push(candidato);
    } else {
        favoritos.splice(index, 1);
    }

    guardarFavoritosEnStorage();
    actualizar_menu();
}

function actualizar_menu() {
    let lista = document.getElementById("lista_favoritos_menu");
    lista.innerHTML = "";

    /* Mapa de iconos por nombre de sección para favoritos */
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

    if (favoritos.length === 0) {
        lista.innerHTML = '<li class="texto_vacio">Marca una estrella...</li>';
    } else {
        favoritos.forEach(fav => {
            const nombreSeguro = (fav.nombre || '').replace(/'/g, "\\'");
            const urlSeguro = (fav.url || '').replace(/'/g, "\\'");
            const destino = fav.url || '#';
            const icono = iconoMap[fav.nombre] || '⭐';

            lista.innerHTML += `
                <li>
                    <a href="${destino}" data-tooltip="${fav.nombre}"><span class="menu_icono">${icono}</span> <span class="sidebar_titulo">${fav.nombre}</span></a>
                    <span class="btn_eliminar" onclick="toggle_favorito('${nombreSeguro}', '${urlSeguro}')">✖</span>
                </li>
            `;
        });
    }
}

/* =========================================
   LOGICA KANBAN DRAG & DROP
   ========================================= */

document.addEventListener('DOMContentLoaded', () => {
    // Inicializa favoritos persistidos antes de cualquier interacción de menú.
    cargarFavoritosDesdeStorage();
    actualizar_menu();

    const modal = document.getElementById('modalConfirm');
    const modalMensaje = document.getElementById('modalConfirmMessage');
    const btnCancelar = document.getElementById('modalConfirmCancel');
    const btnAceptar = document.getElementById('modalConfirmAccept');
    let formPendiente = null;
    let submitterPendiente = null;

    const cerrarModal = () => {
        if (modal) {
            modal.classList.remove('activo');
            modal.setAttribute('aria-hidden', 'true');
        }
        formPendiente = null;
        submitterPendiente = null;
    };

    const abrirModal = mensaje => {
        if (!modal) {
            return false;
        }

        modalMensaje.textContent = mensaje;
        modal.classList.add('activo');
        modal.setAttribute('aria-hidden', 'false');
        return true;
    };

    if (btnCancelar) {
        btnCancelar.addEventListener('click', cerrarModal);
    }

    if (modal) {
        modal.addEventListener('click', event => {
            if (event.target === modal) {
                cerrarModal();
            }
        });
    }

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

    const formulariosConfirmacion = document.querySelectorAll('form');
    formulariosConfirmacion.forEach(form => {
        form.addEventListener('submit', event => {
            const submitter = event.submitter;
            const mensaje = submitter?.getAttribute('data-confirm') || form.getAttribute('data-confirm');

            if (!mensaje) {
                return;
            }

            event.preventDefault();
            formPendiente = form;
            submitterPendiente = submitter || null;

            if (!abrirModal(mensaje)) {
                if (window.confirm(mensaje)) {
                    form.submit();
                } else {
                    cerrarModal();
                }
            }
        });
    });

    
    const tarjetas = document.querySelectorAll('.tarjeta_prospecto, .tarjeta_proceso');
    const columnas = document.querySelectorAll('.kanban_columna');
    const btnEditarKanban = document.querySelector('.btn-editar-kanban') || document.querySelector('.btn-editar-kanban-proceso');
    let tarjetaArrastrada = null;
    let modoEdicionKanban = false;

    // 1) Configuración de drag para tarjetas de prospectos.
    // Solo se habilita cuando el usuario activa "Editar orden" en kanban.
    tarjetas.forEach(tarjeta => {
        
        tarjeta.addEventListener('dragstart', (event) => {
            if (!modoEdicionKanban) {
                event.preventDefault();
                return;
            }

            // Guarda referencia de la tarjeta en arrastre y su estado original.
            tarjetaArrastrada = tarjeta;
            tarjeta.classList.add('arrastrando');
            const estadoOrigen = tarjeta.closest('.kanban_columna')?.dataset.estado || '';
            tarjeta.dataset.estadoOrigen = estadoOrigen;

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', tarjeta.id || '');
            }
        });

        tarjeta.addEventListener('dragend', () => {
            tarjeta.classList.remove('arrastrando');
            tarjetaArrastrada = null;
        });
    });

    const limpiarZonasActivas = () => {
        // Limpia resaltado visual de columnas candidatas durante el drag.
        columnas.forEach(col => col.classList.remove('zona_activa'));
    };

    const resolverTarjetaArrastrada = (e) => {
        // Recupera tarjeta arrastrada por referencia directa o por dataTransfer id.
        let tarjetaDestino = tarjetaArrastrada || document.querySelector('.arrastrando');

        if (!tarjetaDestino && e.dataTransfer) {
            const tarjetaId = e.dataTransfer.getData('text/plain');
            if (tarjetaId) {
                tarjetaDestino = document.getElementById(tarjetaId);
            }
        }

        return tarjetaDestino;
    };

    const guardarCambioEstado = async (id, estado) => {
        // Persiste en backend el nuevo estado del prospecto/proceso tras mover tarjeta.
        const params = new URLSearchParams(window.location.search);
        const seccion = params.get('seccion') || '';
        const endpoint = `secciones/${seccion}.php`;

        const esProceso = seccion.startsWith('proceso-');
        const esProspectos = seccion.startsWith('prospectos-');

        if (!esProspectos && !esProceso) {
            throw new Error('Seccion no valida para guardar movimiento.');
        }

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

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': getCsrfToken()
            },
            body: body.toString()
        });

        const text = await response.text();
        let data = null;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('Respuesta invalida del servidor al guardar.');
        }

        // Se valida HTTP y contrato JSON de respuesta para mostrar errores claros.
        if (!response.ok) {
            throw new Error(data?.mensaje || 'No se pudo guardar el cambio de estado.');
        }

        if (!data.ok) {
            throw new Error(data.mensaje || 'No se pudo guardar el cambio de estado.');
        }
    };

    document.addEventListener('dragover', (e) => {
        // Permite drop sobre columnas y marca zona activa.
        if (!modoEdicionKanban) {
            return;
        }

        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        e.preventDefault();
        limpiarZonasActivas();
        columna.classList.add('zona_activa');
    });

    document.addEventListener('dragleave', (e) => {
        if (!modoEdicionKanban) {
            return;
        }

        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        if (!columna.contains(e.relatedTarget)) {
            columna.classList.remove('zona_activa');
        }
    });

    document.addEventListener('drop', async (e) => {
        // Al soltar: mueve visualmente la tarjeta y luego intenta persistir en servidor.
        // Si falla, revierte al estado/columna de origen.
        if (!modoEdicionKanban) {
            return;
        }

        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        limpiarZonasActivas();

        const cuerpoDestino = columna.querySelector('.kanban_body');
        const estadoDestino = columna.dataset.estado || cuerpoDestino?.dataset.estado || '';
        const tarjetaDestino = resolverTarjetaArrastrada(e);

        if (!cuerpoDestino || !estadoDestino || !tarjetaDestino || cuerpoDestino.contains(tarjetaDestino)) {
            return;
        }

        const cuerpoOrigen = tarjetaDestino.closest('.kanban_body');
        const estadoOrigen = tarjetaDestino.dataset.estadoOrigen || tarjetaDestino.closest('.kanban_columna')?.dataset.estado || '';
        const idTarjeta = parseInt(tarjetaDestino.dataset.id || '', 10);

        cuerpoDestino.appendChild(tarjetaDestino);
        actualizarContadores();

        if (!Number.isInteger(idTarjeta) || idTarjeta <= 0 || estadoOrigen === estadoDestino) {
            return;
        }

        try {
            await guardarCambioEstado(idTarjeta, estadoDestino);
            tarjetaDestino.dataset.estadoOrigen = estadoDestino;
        } catch (error) {
            if (cuerpoOrigen) {
                cuerpoOrigen.appendChild(tarjetaDestino);
                actualizarContadores();
            }
            alert(error.message || 'No se pudo actualizar el estado del prospecto.');
        }
    });

    const actualizarModoKanban = () => {
        // Activa/desactiva atributo draggable y sincroniza texto del botón.
        tarjetas.forEach(tarjeta => {
            tarjeta.setAttribute('draggable', modoEdicionKanban ? 'true' : 'false');
        });

        if (btnEditarKanban) {
            btnEditarKanban.textContent = modoEdicionKanban ? 'Listo' : 'Editar orden';
        }
    };

    if (btnEditarKanban) {
        btnEditarKanban.addEventListener('click', () => {
            modoEdicionKanban = !modoEdicionKanban;
            limpiarZonasActivas();
            actualizarModoKanban();
        });
    }

    actualizarModoKanban();

    // =========================================
    // TOGGLE FORMULARIOS DE AÑADIR
    // =========================================
    const botonesNuevo = document.querySelectorAll('a[href^="#nuevo"], a[href^="#nueva"]');
    botonesNuevo.forEach(boton => {
        boton.addEventListener('click', (e) => {
            e.preventDefault();
            const target = boton.getAttribute('href').substring(1); // Quitamos el '#'
            const formulario = document.getElementById(target);
            if (formulario) {
                formulario.classList.toggle('visible');
            }
        });
    });

    const guardarOrdenDashboardServidor = async (kpis, panels) => {
        // Guarda en backend el orden de tarjetas KPI y paneles para el usuario actual.
        const body = new URLSearchParams();
        body.append('dashboard_orden_accion', 'guardar');
        body.append('kpis', JSON.stringify(kpis));
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

    const resetOrdenDashboardServidor = async () => {
        // Elimina en backend preferencias de orden para volver al layout por defecto.
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

    const inicializarOrdenDashboard = (selectorContenedor, selectorItems, storageKey, estaEditando, ordenServidor) => {
        // Motor de drag por mouse para reordenar widgets dentro de un contenedor.
        // Soporta:
        // - carga orden servidor/localStorage
        // - placeholder visual durante arrastre
        // - persistencia de orden local
        const contenedor = document.querySelector(selectorContenedor);
        if (!contenedor) {
            return;
        }

        const obtenerItems = () => Array.from(contenedor.querySelectorAll(selectorItems));
        let itemArrastrado = null;
        let placeholder = null;
        let offsetX = 0;
        let offsetY = 0;
        let pointerX = 0;
        let pointerY = 0;
        let framePendiente = false;

        const guardarOrden = () => {
            // Serializa orden actual por data-dashboard-card.
            const orden = obtenerItems()
                .map(item => item.dataset.dashboardCard)
                .filter(Boolean);

            if (orden.length > 0) {
                localStorage.setItem(storageKey, JSON.stringify(orden));
            }

            return orden;
        };

        const aplicarOrden = ids => {
            // Reordena nodos según lista de ids recibida.
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

        const aplicarOrdenGuardado = () => {
            // Prioridad de carga de orden:
            // 1) servidor (preferencia persistida)
            // 2) localStorage (fallback local)
            if (Array.isArray(ordenServidor) && ordenServidor.length > 0) {
                aplicarOrden(ordenServidor);
                localStorage.setItem(storageKey, JSON.stringify(ordenServidor));
                return;
            }

            const ordenGuardado = localStorage.getItem(storageKey);
            if (!ordenGuardado) {
                return;
            }

            let ids = [];
            try {
                ids = JSON.parse(ordenGuardado);
            } catch (_error) {
                return;
            }

            aplicarOrden(ids);
        };

        const limpiarDrag = () => {
            // Cierra ciclo de drag: restaura estilos, sustituye placeholder y guarda orden.
            if (!itemArrastrado) {
                return;
            }

            itemArrastrado.classList.remove('dashboard_drag_flotante');
            itemArrastrado.style.left = '';
            itemArrastrado.style.top = '';
            itemArrastrado.style.width = '';
            itemArrastrado.style.height = '';

            if (placeholder && placeholder.parentNode) {
                placeholder.parentNode.replaceChild(itemArrastrado, placeholder);
            }

            document.body.classList.remove('dashboard_reordenando');

            itemArrastrado = null;
            placeholder = null;
            guardarOrden();
        };

        const moverPlaceholder = (clientX, clientY) => {
            // Decide posición del placeholder comparando cursor vs mitad del item objetivo.
            if (!placeholder) {
                return;
            }

            const elBajoPuntero = document.elementFromPoint(clientX, clientY);
            const itemObjetivo = elBajoPuntero ? elBajoPuntero.closest(selectorItems) : null;

            if (itemObjetivo && itemObjetivo !== itemArrastrado && itemObjetivo !== placeholder && contenedor.contains(itemObjetivo)) {
                const rect = itemObjetivo.getBoundingClientRect();
                const mitadY = rect.top + rect.height / 2;
                const mitadX = rect.left + rect.width / 2;
                const mismaFila = Math.abs(clientY - mitadY) <= rect.height * 0.35;
                const insertarAntes = mismaFila ? clientX < mitadX : clientY < mitadY;
                const referencia = insertarAntes ? itemObjetivo : itemObjetivo.nextSibling;

                if (referencia !== placeholder && placeholder.nextSibling !== referencia) {
                    contenedor.insertBefore(placeholder, referencia || null);
                }
                return;
            }

            const rectCont = contenedor.getBoundingClientRect();
            if (clientY < rectCont.top) {
                contenedor.insertBefore(placeholder, contenedor.firstChild);
            } else if (clientY > rectCont.bottom) {
                contenedor.appendChild(placeholder);
            }
        };

        const procesarFrame = () => {
            // Frame de animación: mueve item flotante + recalcula placeholder.
            framePendiente = false;

            if (!itemArrastrado) {
                return;
            }

            itemArrastrado.style.left = `${pointerX - offsetX}px`;
            itemArrastrado.style.top = `${pointerY - offsetY}px`;
            moverPlaceholder(pointerX, pointerY);
        };

        const onMouseMove = event => {
            if (!itemArrastrado) {
                return;
            }

            pointerX = event.clientX;
            pointerY = event.clientY;

            if (!framePendiente) {
                framePendiente = true;
                requestAnimationFrame(procesarFrame);
            }
        };

        const onMouseUp = () => {
            // Finaliza drag con mouseup global.
            if (!itemArrastrado) {
                return;
            }

            limpiarDrag();
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        const iniciarDrag = (item, event) => {
            // Inicializa drag manual: crea placeholder y mueve item al body para flotación.
            const rect = item.getBoundingClientRect();
            offsetX = event.clientX - rect.left;
            offsetY = event.clientY - rect.top;
            pointerX = event.clientX;
            pointerY = event.clientY;

            placeholder = document.createElement('div');
            placeholder.className = 'dashboard_drag_placeholder';
            placeholder.style.width = `${rect.width}px`;
            placeholder.style.height = `${rect.height}px`;

            itemArrastrado = item;

            item.parentNode.insertBefore(placeholder, item);
            document.body.appendChild(item);

            item.classList.add('dashboard_drag_flotante');
            item.style.width = `${rect.width}px`;
            item.style.height = `${rect.height}px`;
            item.style.left = `${event.clientX - offsetX}px`;
            item.style.top = `${event.clientY - offsetY}px`;
            document.body.classList.add('dashboard_reordenando');

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        };

        aplicarOrdenGuardado();

        contenedor.addEventListener('mousedown', event => {
            // Ignora drag cuando no está en modo edición o al interactuar con controles.
            if (!estaEditando()) {
                return;
            }

            const item = event.target.closest(selectorItems);
            if (!item || !contenedor.contains(item)) {
                return;
            }

            const tag = (event.target.tagName || '').toLowerCase();
            if (['a', 'button', 'input', 'select', 'textarea', 'label', 'summary'].includes(tag)) {
                return;
            }

            event.preventDefault();
            iniciarDrag(item, event);
        });
    };

    const btnEditarOrden = document.getElementById('btnEditarOrdenDashboard');
    const btnResetOrden = document.getElementById('btnResetOrdenDashboard');
    const estadoOrden = document.getElementById('estadoOrdenDashboard');
    let modoEdicionDashboard = false;
    let temporizadorEstadoOrden = null;

    const mostrarEstadoOrden = (mensaje, esError = false) => {
        // Mensajería breve en UI para confirmar guardado/reset de orden.
        if (!estadoOrden) {
            return;
        }

        if (temporizadorEstadoOrden) {
            clearTimeout(temporizadorEstadoOrden);
        }

        estadoOrden.textContent = mensaje;
        estadoOrden.style.color = esError ? '#c0392b' : '#27ae60';
        estadoOrden.classList.add('visible');

        temporizadorEstadoOrden = window.setTimeout(() => {
            estadoOrden.classList.remove('visible');
        }, 2200);
    };

    const estaEditandoDashboard = () => modoEdicionDashboard;

    const actualizarModoDashboard = () => {
        // Sincroniza clase editando en contenedores y etiqueta del botón principal.
        const contKpis = document.querySelector('.dashboard_kpis');
        const contPaneles = document.querySelector('.dashboard_grid');

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

    if (btnEditarOrden) {
        btnEditarOrden.addEventListener('click', () => {
            modoEdicionDashboard = !modoEdicionDashboard;
            actualizarModoDashboard();

            if (!modoEdicionDashboard) {
                // Al salir de modo edición: persiste orden actual en servidor.
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

    if (btnResetOrden) {
        btnResetOrden.addEventListener('click', async () => {
            // Limpia orden local/servidor y recarga para reconstruir layout base.
            localStorage.removeItem('tinoprop.dashboard.kpis.order');
            localStorage.removeItem('tinoprop.dashboard.panels.order');

            try {
                await resetOrdenDashboardServidor();
                mostrarEstadoOrden('Orden restablecido');
            } catch (_error) {
                mostrarEstadoOrden('Restablecido local', true);
            }

            window.location.reload();
        });
    }

    actualizarModoDashboard();
    const prefDashboard = window.tinoPrefDashboard || {};
    inicializarOrdenDashboard('.dashboard_kpis', '.kpi_card[data-dashboard-card]', 'tinoprop.dashboard.kpis.order', estaEditandoDashboard, prefDashboard.kpis || []);
    inicializarOrdenDashboard('.dashboard_grid', '.panel_panel[data-dashboard-card]', 'tinoprop.dashboard.panels.order', estaEditandoDashboard, prefDashboard.panels || []);
});

/* Función auxiliar para recalcular los números del encabezado */
function actualizarContadores() {
    // Buscamos todas las columnas
    const todasLasColumnas = document.querySelectorAll('.kanban_columna');

    todasLasColumnas.forEach(col => {
        const cuerpo = col.querySelector('.kanban_body');
        const contadorSpan = col.querySelector('.contador');
        
        // Contamos cuántas tarjetas hay dentro ahora mismo (prospectos y procesos)
        const cantidad = cuerpo.querySelectorAll('.tarjeta_prospecto, .tarjeta_proceso').length;
        
        // Actualizamos el número
        contadorSpan.textContent = cantidad;
    });
}


/* =============================================
   SIDEBAR COLAPSABLE
   ========================================= */
function initSidebarToggle() {
    const btn = document.getElementById('btnColapsarSidebar');
    const sidebar = document.getElementById('sidebar');
    if (!btn || !sidebar) return;

    /* Asignar data-tooltip a cada enlace del menú para los tooltips en modo colapsado */
    sidebar.querySelectorAll('.grupo_menu ul li a').forEach(link => {
        const span = link.querySelector('.sidebar_titulo');
        if (span && !link.hasAttribute('data-tooltip')) {
            link.setAttribute('data-tooltip', span.textContent.trim());
        }
    });

    const STORAGE_KEY = 'tinoprop.sidebar.colapsado';
    const guardado = localStorage.getItem(STORAGE_KEY);
    if (guardado === 'true') sidebar.classList.add('sidebar_colapsado');

    btn.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar_colapsado');
        const colapsado = sidebar.classList.contains('sidebar_colapsado');
        localStorage.setItem(STORAGE_KEY, colapsado);
    });
}


/* =============================================
   BUSCADOR GLOBAL
   ========================================= */
function initBuscadorGlobal() {
    const input = document.getElementById('buscadorGlobal');
    const contenedor = document.getElementById('resultadosBusqueda');
    if (!input || !contenedor) return;

    let debounceTimer = null;

    const cerrarResultados = () => {
        contenedor.classList.remove('activo');
        contenedor.innerHTML = '';
    };

    input.addEventListener('input', () => {
        const termino = input.value.trim();
        clearTimeout(debounceTimer);

        if (termino.length < 2) { cerrarResultados(); return; }

        debounceTimer = setTimeout(async () => {
            try {
                const resp = await fetch('api/buscar.php?q=' + encodeURIComponent(termino));
                const datos = await resp.json();

                if (!datos.length) {
                    contenedor.innerHTML = '<div class="resultado_vacio">Sin resultados</div>';
                    contenedor.classList.add('activo');
                    return;
                }

                const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

                contenedor.innerHTML = datos.map(r => `
                    <a href="${esc(r.url)}" class="resultado_item">
                        <span class="resultado_icono">${esc(r.icono)}</span>
                        <div class="resultado_info">
                            <div class="resultado_titulo">${esc(r.titulo)}</div>
                            <div class="resultado_detalle">${esc(r.detalle || '')}</div>
                        </div>
                    </a>
                `).join('');
                contenedor.classList.add('activo');
            } catch (err) {
                contenedor.innerHTML = '<div class="resultado_vacio">Error de búsqueda</div>';
                contenedor.classList.add('activo');
            }
        }, 300);
    });

    // Cerrar al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.buscador_global_wrap')) cerrarResultados();
    });
}


/* =============================================
   NOTIFICACIONES DROPDOWN
   ========================================= */
function initNotificaciones() {
    const btn = document.getElementById('btnNotificaciones');
    const dropdown = document.getElementById('dropdownNotificaciones');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('activo');
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.notificaciones_wrap')) {
            dropdown.classList.remove('activo');
        }
    });
}


/* =============================================
   ORDENAR COLUMNAS DE TABLA
   ========================================= */
function initOrdenarTablas() {
    document.querySelectorAll('.th_ordenable').forEach(th => {
        th.addEventListener('click', () => {
            const tabla = th.closest('table');
            const tbody = tabla.querySelector('tbody');
            if (!tbody) return;

            const colIndex = Array.from(th.parentElement.children).indexOf(th);
            const filas = Array.from(tbody.querySelectorAll('tr'));

            // Determinar dirección
            const esAsc = th.classList.contains('asc');
            // Limpiar otras columnas
            th.closest('tr').querySelectorAll('.th_ordenable').forEach(t => t.classList.remove('asc', 'desc'));

            if (esAsc) {
                th.classList.add('desc');
            } else {
                th.classList.add('asc');
            }

            const dir = th.classList.contains('asc') ? 1 : -1;

            filas.sort((a, b) => {
                const aVal = (a.children[colIndex]?.textContent || '').trim().toLowerCase();
                const bVal = (b.children[colIndex]?.textContent || '').trim().toLowerCase();

                // Intentar como número
                const aNum = parseFloat(aVal.replace(/[€$,.\s]/g, ''));
                const bNum = parseFloat(bVal.replace(/[€$,.\s]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) return (aNum - bNum) * dir;

                // Intentar como fecha dd/mm/yyyy
                const fechaA = parseFechaTabla(aVal);
                const fechaB = parseFechaTabla(bVal);
                if (fechaA && fechaB) return (fechaA - fechaB) * dir;

                return aVal.localeCompare(bVal, 'es') * dir;
            });

            filas.forEach(f => tbody.appendChild(f));
        });
    });
}

function parseFechaTabla(str) {
    // dd/mm/yyyy o yyyy-mm-dd
    let m = str.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) return new Date(m[3], m[2] - 1, m[1]);
    m = str.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (m) return new Date(m[1], m[2] - 1, m[3]);
    return null;
}


/* =============================================
   VALIDACIÓN DE FORMULARIOS
   ========================================= */
function initValidacionFormularios() {
    document.querySelectorAll('form[data-validar]').forEach(form => {
        form.setAttribute('novalidate', '');

        form.addEventListener('submit', (e) => {
            let valido = true;
            // Limpiar errores previos
            form.querySelectorAll('.error_campo').forEach(el => el.remove());
            form.querySelectorAll('.campo_invalido, .campo_valido').forEach(el => {
                el.classList.remove('campo_invalido', 'campo_valido');
            });

            form.querySelectorAll('input[required], select[required], textarea[required]').forEach(campo => {
                const valor = campo.value.trim();
                if (!valor) {
                    campo.classList.add('campo_invalido');
                    const msg = document.createElement('span');
                    msg.className = 'error_campo';
                    msg.textContent = 'Este campo es obligatorio';
                    campo.parentElement.appendChild(msg);
                    valido = false;
                } else {
                    // Validaciones específicas
                    if (campo.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
                        campo.classList.add('campo_invalido');
                        const msg = document.createElement('span');
                        msg.className = 'error_campo';
                        msg.textContent = 'Email no válido';
                        campo.parentElement.appendChild(msg);
                        valido = false;
                    } else if (campo.type === 'tel' && !/^[\d\s\+\-\(\)]{6,20}$/.test(valor)) {
                        campo.classList.add('campo_invalido');
                        const msg = document.createElement('span');
                        msg.className = 'error_campo';
                        msg.textContent = 'Teléfono no válido';
                        campo.parentElement.appendChild(msg);
                        valido = false;
                    } else {
                        campo.classList.add('campo_valido');
                    }
                }
            });

            if (!valido) {
                e.preventDefault();
                // Scroll al primer error
                const primerError = form.querySelector('.campo_invalido');
                if (primerError) primerError.focus();
            }
        });

        // Validación en vivo al salir del campo
        form.querySelectorAll('input[required], select[required], textarea[required]').forEach(campo => {
            campo.addEventListener('blur', () => {
                const msg = campo.parentElement.querySelector('.error_campo');
                if (msg) msg.remove();
                campo.classList.remove('campo_invalido', 'campo_valido');

                if (campo.value.trim()) {
                    campo.classList.add('campo_valido');
                }
            });
        });
    });
}


/* =============================================
   INIT TODOS LOS MÓDULOS V0.0.27
   ========================================= */
document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initBuscadorGlobal();
    initNotificaciones();
    initOrdenarTablas();
    initValidacionFormularios();
});
