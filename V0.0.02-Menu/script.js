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
