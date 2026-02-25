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
