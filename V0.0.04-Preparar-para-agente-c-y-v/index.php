<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>TinoProp</title>
	<link rel="stylesheet" href="css/estilo.css">
	
	<meta name="description" content="Sistema de gestión inmobiliaria (CRM) para administración de clientes, propiedades y alquileres en Valencia.">
    <meta name="keywords" content="inmobiliaria, crm, valencia, gestión, propiedades, alquileres, tinoprop">
    <meta name="author" content="Valentín Antonio De Gennaro">
    <meta name="robots" content="index, follow"> <meta property="og:title" content="TinoProp - Tu Gestión Inmobiliaria">
    <meta property="og:description" content="Plataforma integral para la gestión de activos inmobiliarios y cartera de clientes.">
    <meta property="og:image" content="img/preview-social.jpg"> <meta property="og:url" content="https://tinoprop.com">
    <meta property="og:type" content="website">
    
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32x32.png">
    <link rel="apple-touch-icon" href="img/apple-touch-icon.png">
</head>
<body>
	<div class="contenedor_principal">
		<nav class="menu_lateral">
		<h2>🏠️ TinoProp</h2>
		
		<div class="grupo_menu">
            <h3 class="titulo_seccion" style="color: gold;">★ Favoritos</h3>
            <ul id="lista_favoritos_menu">
                <li class="texto_vacio">Marca una estrella...</li>
            </ul>
        </div>
        
		<div class="grupo_menu">
			<h3 class="titulo_seccion">Gestión Clientes - Vendedor</h3>
			<ul>
				<li>
					<a href="?seccion=clientes-vendedor">Clientes</a>
					<span class="btn_star" onclick="toggle_favorito('Clientes')">☆</span>
				</li>
				<li>
					<a href="?seccion=prospectos-vendedor">Prospectos</a>
					<span class="btn_star" onclick="toggle_favorito('Prospectos')">☆</span>
				</li>
			</ul>
		</div>
		
		<div class="grupo_menu">
			<h3 class="titulo_seccion">Gestión Clientes - Comprador</h3>
			<ul>
				<li>
					<a href="?seccion=clientes-comprador">Clientes</a>
					<span class="btn_star" onclick="toggle_favorito('Clientes')">☆</span>
				</li>
				<li>
					<a href="?seccion=prospectos-comprador">Prospectos</a>
					<span class="btn_star" onclick="toggle_favorito('Prospectos')">☆</span>
				</li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion">Inmuebles - Vendedor</h3>
			<ul>
				<li>
					<a href="?seccion=propiedades-vendedor">Propiedades</a>
					<span class="btn_star" onclick="toggle_favorito('Propiedades')">☆</span>
				</li>
				<li>
					<a href="#">Alquileres</a>
					<span class="btn_star" onclick="toggle_favorito('Alquileres')">☆</span>
				</li>
				<li>
					<a href="#">Búsqueda Avanzada</a>
					<span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span>
				</li>
			</ul>
		</div>
		
		<div class="grupo_menu">
			<h3 class="titulo_seccion">Inmuebles - Comprador</h3>
			<ul>
				<li>
					<a href="?seccion=propiedades-comprador">Propiedades</a>
					<span class="btn_star" onclick="toggle_favorito('Propiedades')">☆</span>
				</li>
				<li>
					<a href="#">Alquileres</a>
					<span class="btn_star" onclick="toggle_favorito('Alquileres')">☆</span>
				</li>
				<li>
					<a href="#">Búsqueda Avanzada</a>
					<span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span>
				</li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion">Sistema</h3>
			<ul>
				<li><a href="#">Configuración</a></li>
				<li><a href="#">Cerrar Sesión</a></li>
			</ul>
		</div>
		
		</nav>
		<main class="contenido_derecha">
		<?php
			// Capturamos qué sección quiere ver el usuario
			// Si no hay ninguna, mostramos 'inicio' (puedes crear inicio.php con la bienvenida)
			$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'bienvenida';

			// Definimos la ruta del archivo
			$archivo = "secciones/" . $seccion . ".php";

			// Si el archivo existe, lo mostramos. Si no, mostramos la Bienvenida por defecto
			if (file_exists($archivo)) {
				include $archivo;
			} else {
			// Aquí puedes dejar tu HTML de bienvenida original
				echo '<h1>Bienvenido a TinoProp</h1>';
				echo '<p>Selecciona una opción del menú para comenzar a trabajar.</p>';
				echo '<div class="tarjeta_info"><p>Sistema listo para usar.</p></div>';
			}
		?>
		</main>
		</div>
	</div>
	<script src="js/script.js"></script>
</body>
</html>
