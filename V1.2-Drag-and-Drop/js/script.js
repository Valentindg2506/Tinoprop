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
    let tarjetaArrastrada = null;

    // 1. Eventos para las TARJETAS (lo que arrastras)
    tarjetas.forEach(tarjeta => {
        
        tarjeta.addEventListener('dragstart', (event) => {
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
        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        e.preventDefault();
        limpiarZonasActivas();
        columna.classList.add('zona_activa');
    });

    document.addEventListener('dragleave', (e) => {
        const columna = e.target.closest('.kanban_columna');
        if (!columna) {
            return;
        }

        if (!columna.contains(e.relatedTarget)) {
            columna.classList.remove('zona_activa');
        }
    });

    document.addEventListener('drop', async (e) => {
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
