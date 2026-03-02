<?php
/*
 * Archivo: index.php
 * Rol: contenedor principal de la aplicación (layout + menú lateral + enrutado por sección).
 * Flujo: recibe ?seccion=..., verifica si existe el archivo en /secciones y lo incluye.
 * Dependencias: inc/bootstrap.php, inc/helpers.php (vía bootstrap), js/script.js, css/estilo.css.
 */
require_once __DIR__ . '/inc/bootstrap.php';

// Catálogos de preferencias válidas para evitar valores inconsistentes en UI.
$tema_valido = ['Sistema', 'Claro', 'Oscuro'];
$densidad_valida = ['Media', 'Comoda', 'Compacta'];
$idiomas_validos = ['es', 'en'];

// Carga tema desde preferencias del usuario con fallback seguro.
$tema = preferencias_usuario_get($pdo, 'ui.tema') ?? 'Sistema';
if (!in_array($tema, $tema_valido, true)) {
	$tema = 'Sistema';
}

// Carga densidad visual desde preferencias del usuario con fallback seguro.
$densidad = preferencias_usuario_get($pdo, 'ui.densidad') ?? 'Media';
if (!in_array($densidad, $densidad_valida, true)) {
	$densidad = 'Media';
}

// Traduce preferencias de tema/densidad a clases CSS aplicadas sobre <body>.
$body_classes = [];
if ($tema === 'Oscuro') {
	$body_classes[] = 'tema-oscuro';
} elseif ($tema === 'Claro') {
	$body_classes[] = 'tema-claro';
}

if ($densidad === 'Comoda') {
	$body_classes[] = 'densidad-comoda';
} elseif ($densidad === 'Compacta') {
	$body_classes[] = 'densidad-compacta';
}

// Idioma HTML de la página (sincronizado con idioma de sesión si es válido).
$body_class_attr = trim(implode(' ', $body_classes));
$lang_attr = in_array(idioma_actual(), $idiomas_validos, true) ? idioma_actual() : 'es';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang_attr, ENT_QUOTES, 'UTF-8'); ?>">
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
<body<?php echo $body_class_attr ? ' class="' . htmlspecialchars($body_class_attr, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
	<div class="contenedor_principal">
		<nav class="menu_lateral">
		<h2>🏠️ TinoProp</h2>
		
		<div class="grupo_menu">
			<h3 class="titulo_seccion titulo_seccion--favoritos">★ Favoritos</h3>
			<ul id="lista_favoritos_menu">
				<li class="texto_vacio">Marca una estrella...</li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion">Inicio</h3>
			<ul>
				<li>
					<a href="?seccion=dashboard">Dashboard</a>
					<span class="btn_star" onclick="toggle_favorito('Dashboard')">☆</span>
				</li>
				<li>
					<a href="?seccion=recordatorios">Recordatorios</a>
					<span class="btn_star" onclick="toggle_favorito('Recordatorios')">☆</span>
				</li>
				<li>
					<a href="?seccion=documentacion">Documentación</a>
					<span class="btn_star" onclick="toggle_favorito('Documentación')">☆</span>
				</li>
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
					<a href="?seccion=alquileres-vendedor">Alquileres</a>
					<span class="btn_star" onclick="toggle_favorito('Alquileres')">☆</span>
				</li>
				<li>
					<a href="?seccion=busqueda-avanzada">Búsqueda Avanzada</a>
					<span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span>
				</li>
				<li>
					<a href="?seccion=proceso-vendedor">Proceso</a>
					<span class="btn_star" onclick="toggle_favorito('Proceso Vendedor')">☆</span>
				</li>
				<li>
					<a href="?seccion=visitas-vendedor">Visitas</a>
					<span class="btn_star" onclick="toggle_favorito('Visitas Vendedor')">☆</span>
				</li>
				<li>
					<a href="?seccion=ofertas-vendedor">Ofertas</a>
					<span class="btn_star" onclick="toggle_favorito('Ofertas Vendedor')">☆</span>
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
					<a href="?seccion=alquileres-comprador">Alquileres</a>
					<span class="btn_star" onclick="toggle_favorito('Alquileres')">☆</span>
				</li>
				<li>
					<a href="?seccion=busqueda-avanzada">Búsqueda Avanzada</a>
					<span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span>
				</li>
				<li>
					<a href="?seccion=proceso-comprador">Proceso</a>
					<span class="btn_star" onclick="toggle_favorito('Proceso Comprador')">☆</span>
				</li>
				<li>
					<a href="?seccion=visitas-comprador">Visitas</a>
					<span class="btn_star" onclick="toggle_favorito('Visitas Comprador')">☆</span>
				</li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion">Sistema</h3>
			<ul>
				<li>
					<a href="?seccion=configuracion">Configuración</a>
					<span class="btn_star" onclick="toggle_favorito('Configuración')">☆</span>
				</li>
				<li><a href="logout.php">Cerrar Sesión</a></li>
			</ul>
		</div>
		
		</nav>
		<main class="contenido_derecha">
		<?php
			// Router principal de secciones:
			// - si no llega parámetro, abre dashboard por defecto.
			$seccion = isset($_GET['seccion']) ? $_GET['seccion'] : 'dashboard';

			// Construye ruta del archivo de sección.
			$archivo = "secciones/" . $seccion . ".php";

			// Incluye la sección si existe; en caso contrario muestra fallback de bienvenida.
			if (file_exists($archivo)) {
				include $archivo;
			} else {
				echo '<h1>Bienvenido a TinoProp</h1>';
				echo '<p>Selecciona una opción del menú para comenzar a trabajar.</p>';
				echo '<div class="tarjeta_info"><p>Sistema listo para usar.</p></div>';
			}
		?>
		</main>
		</div>
	</div>
	<div class="modal_overlay" id="modalConfirm" aria-hidden="true">
		<div class="modal_contenido" role="dialog" aria-modal="true" aria-labelledby="modalConfirmTitle">
			<h3 id="modalConfirmTitle">Confirmar accion</h3>
			<p id="modalConfirmMessage">Esta accion no se puede deshacer.</p>
			<div class="modal_acciones">
				<button type="button" class="btn_secundario" id="modalConfirmCancel">Cancelar</button>
				<button type="button" class="btn_peligro" id="modalConfirmAccept">Eliminar</button>
			</div>
		</div>
	</div>
	<script src="js/script.js"></script>
</body>
</html>
