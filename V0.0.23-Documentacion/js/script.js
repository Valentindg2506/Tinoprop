let favoritos = [];

function toggle_favorito(nombre) {
    // Buscamos si ya existe
    let index = favoritos.indexOf(nombre);

    if (index === -1) {
        favoritos.push(nombre); // Agregamos
    } else {
        favoritos.splice(index, 1); // Quitamos
    }

    actualizar_menu();
}

function actualizar_menu() {
    let lista = document.getElementById("lista_favoritos_menu");
    lista.innerHTML = "";

    if (favoritos.length === 0) {
        lista.innerHTML = '<li class="texto_vacio">Marca una estrella...</li>';
    } else {
        favoritos.forEach(fav => {
            // Agregamos el enlace y un botón X para eliminar
            lista.innerHTML += `
                <li>
                    <a href="#">${fav}</a>
                    <span class="btn_eliminar" onclick="toggle_favorito('${fav}')">✖</span>
                </li>
            `;
        });
    }
}

/* =========================================
   LOGICA KANBAN DRAG & DROP
   ========================================= */

document.addEventListener('DOMContentLoaded', () => {
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

    
    const tarjetas = document.querySelectorAll('.tarjeta_prospecto');
    const columnas = document.querySelectorAll('.kanban_columna');
    const btnEditarKanban = document.querySelector('.btn-editar-kanban');
    let tarjetaArrastrada = null;
    let modoEdicionKanban = false;

    // 1. Eventos para las TARJETAS (lo que arrastras)
    tarjetas.forEach(tarjeta => {
        
        tarjeta.addEventListener('dragstart', (event) => {
            if (!modoEdicionKanban) {
                event.preventDefault();
                return;
            }

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
        columnas.forEach(col => col.classList.remove('zona_activa'));
    };

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

    const guardarCambioEstado = async (id, estado) => {
        const params = new URLSearchParams(window.location.search);
        const seccion = params.get('seccion') || '';
        const endpoint = `secciones/${seccion}.php`;

        if (!seccion.startsWith('prospectos-')) {
            throw new Error('Seccion no valida para guardar movimiento.');
        }

        const body = new URLSearchParams();
        body.append('mover_prospecto_drag', '1');
        body.append('id', String(id));
        body.append('estado', estado);

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
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

        if (!response.ok) {
            throw new Error(data?.mensaje || 'No se pudo guardar el cambio de estado.');
        }

        if (!data.ok) {
            throw new Error(data.mensaje || 'No se pudo guardar el cambio de estado.');
        }
    };

    document.addEventListener('dragover', (e) => {
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
    const botonesNuevo = document.querySelectorAll('a[href^="#nuevo"]');
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
        const body = new URLSearchParams();
        body.append('dashboard_orden_accion', 'guardar');
        body.append('kpis', JSON.stringify(kpis));
        body.append('panels', JSON.stringify(panels));

        const response = await fetch('secciones/dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        });

        if (!response.ok) {
            throw new Error('No se pudo guardar el orden en servidor.');
        }
    };

    const resetOrdenDashboardServidor = async () => {
        const body = new URLSearchParams();
        body.append('dashboard_orden_accion', 'reset');

        const response = await fetch('secciones/dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body.toString()
        });

        if (!response.ok) {
            throw new Error('No se pudo restablecer el orden en servidor.');
        }
    };

    const inicializarOrdenDashboard = (selectorContenedor, selectorItems, storageKey, estaEditando, ordenServidor) => {
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
            const orden = obtenerItems()
                .map(item => item.dataset.dashboardCard)
                .filter(Boolean);

            if (orden.length > 0) {
                localStorage.setItem(storageKey, JSON.stringify(orden));
            }

            return orden;
        };

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

        const aplicarOrdenGuardado = () => {
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
            if (!itemArrastrado) {
                return;
            }

            limpiarDrag();
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        const iniciarDrag = (item, event) => {
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
        
        // Contamos cuántas tarjetas hay dentro ahora mismo
        const cantidad = cuerpo.querySelectorAll('.tarjeta_prospecto').length;
        
        // Actualizamos el número
        contadorSpan.textContent = cantidad;
    });
}
