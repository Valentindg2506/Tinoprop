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
    const columnas = document.querySelectorAll('.kanban_body');

    // 1. Eventos para las TARJETAS (lo que arrastras)
    tarjetas.forEach(tarjeta => {
        
        tarjeta.addEventListener('dragstart', () => {
            tarjeta.classList.add('arrastrando');
        });

        tarjeta.addEventListener('dragend', () => {
            tarjeta.classList.remove('arrastrando');
        });
    });

    // 2. Eventos para las COLUMNAS (donde sueltas)
    columnas.forEach(columna => {
        
        // Cuando pasas una tarjeta por encima
        columna.addEventListener('dragover', (e) => {
            e.preventDefault(); // Necesario para permitir soltar
            columna.classList.add('zona_activa'); // Efecto visual
        });

        // Cuando sales de la columna sin soltar
        columna.addEventListener('dragleave', () => {
            columna.classList.remove('zona_activa');
        });

        // Cuando SUELTAS la tarjeta
        columna.addEventListener('drop', (e) => {
            e.preventDefault();
            columna.classList.remove('zona_activa');

            // Buscamos la tarjeta que se está arrastrando
            const tarjetaArrastrada = document.querySelector('.arrastrando');
            
            if(tarjetaArrastrada) {
                // Movemos el elemento HTML a esta columna
                columna.appendChild(tarjetaArrastrada);
                
                // Actualizamos los numeritos de los contadores
                actualizarContadores();
            }
        });
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
