<?php
/*
 * Archivo: index.php — V0.0.28 Mejoras
 * Incluye: sidebar colapsable, buscador global, breadcrumbs, notificaciones, CSRF, iconos menú.
 */
require_once __DIR__ . '/inc/bootstrap.php';

$tema_valido = ['Sistema', 'Claro', 'Oscuro'];
$densidad_valida = ['Media', 'Comoda', 'Compacta'];
$idiomas_validos = ['es', 'en'];

$tema = preferencias_usuario_get($pdo, 'ui.tema') ?? 'Sistema';
if (!in_array($tema, $tema_valido, true)) $tema = 'Sistema';
$densidad = preferencias_usuario_get($pdo, 'ui.densidad') ?? 'Media';
if (!in_array($densidad, $densidad_valida, true)) $densidad = 'Media';

$body_classes = [];
if ($tema === 'Oscuro') $body_classes[] = 'tema-oscuro';
elseif ($tema === 'Claro') $body_classes[] = 'tema-claro';
if ($densidad === 'Comoda') $body_classes[] = 'densidad-comoda';
elseif ($densidad === 'Compacta') $body_classes[] = 'densidad-compacta';

$body_class_attr = trim(implode(' ', $body_classes));
$lang_attr = in_array(idioma_actual(), $idiomas_validos, true) ? idioma_actual() : 'es';

$seccion_actual = $_GET['seccion'] ?? 'dashboard';
$notificaciones = notificaciones_generar($pdo);
$num_notifs = count($notificaciones);
?>
<!DOCTYPE html>
<html lang="<?php echo e($lang_attr); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>TinoProp</title>
	<link rel="stylesheet" href="css/estilo.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
	<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
	<meta name="description" content="Sistema de gestión inmobiliaria (CRM)">
	<link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body<?php echo $body_class_attr ? ' class="' . e($body_class_attr) . '"' : ''; ?>>
	<div class="contenedor_principal">
		<nav class="menu_lateral" id="sidebar">

		<div class="sidebar_header">
			<h2><span class="menu_icono">🏠️</span> <span class="sidebar_titulo">TinoProp</span></h2>
			<button type="button" class="btn_colapsar_sidebar" id="btnColapsarSidebar" title="Colapsar menú">◀</button>
		</div>

		<div class="buscador_global_wrap">
			<input type="search" id="buscadorGlobal" class="buscador_global_input" placeholder="🔍 Buscar..." autocomplete="off">
			<div class="buscador_global_resultados" id="resultadosBusqueda"></div>
		</div>

		<div class="notificaciones_wrap">
			<button type="button" class="btn_notificaciones" id="btnNotificaciones" title="Notificaciones">
				<span class="menu_icono">🔔</span> <span class="sidebar_titulo">Alertas</span>
				<?php if ($num_notifs > 0): ?>
					<span class="notif_badge"><?php echo $num_notifs; ?></span>
				<?php endif; ?>
			</button>
			<div class="notificaciones_dropdown" id="dropdownNotificaciones">
				<?php if (empty($notificaciones)): ?>
					<p class="notif_vacio">Sin alertas pendientes ✓</p>
				<?php else: ?>
					<?php foreach ($notificaciones as $notif): ?>
						<a href="<?php echo e($notif['enlace']); ?>" class="notif_item notif_<?php echo e($notif['tipo']); ?>">
							<span class="notif_icono"><?php echo $notif['icono']; ?></span>
							<span class="notif_texto"><?php echo e($notif['texto']); ?></span>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion titulo_seccion--favoritos"><span class="menu_icono">★</span> <span class="sidebar_titulo">Favoritos</span></h3>
			<ul id="lista_favoritos_menu">
				<li class="texto_vacio">Marca una estrella...</li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Inicio</span></h3>
			<ul>
				<li><a href="?seccion=dashboard" <?php if($seccion_actual==='dashboard')echo'aria-current="page"';?>><span class="menu_icono">📊</span> <span class="sidebar_titulo">Dashboard</span></a><span class="btn_star" onclick="toggle_favorito('Dashboard')">☆</span></li>
				<li><a href="?seccion=recordatorios" <?php if($seccion_actual==='recordatorios')echo'aria-current="page"';?>><span class="menu_icono">⏰</span> <span class="sidebar_titulo">Recordatorios</span></a><span class="btn_star" onclick="toggle_favorito('Recordatorios')">☆</span></li>
				<li><a href="?seccion=matching" <?php if($seccion_actual==='matching')echo'aria-current="page"';?>><span class="menu_icono">🔗</span> <span class="sidebar_titulo">Matching</span></a><span class="btn_star" onclick="toggle_favorito('Matching')">☆</span></li>
				<li><a href="?seccion=documentacion" <?php if($seccion_actual==='documentacion')echo'aria-current="page"';?>><span class="menu_icono">📖</span> <span class="sidebar_titulo">Documentación</span></a><span class="btn_star" onclick="toggle_favorito('Documentación')">☆</span></li>
			</ul>
		</div>
        
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Clientes - Vendedor</span></h3>
			<ul>
				<li><a href="?seccion=clientes-vendedor" <?php if($seccion_actual==='clientes-vendedor')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Clientes</span></a><span class="btn_star" onclick="toggle_favorito('Clientes Vend.')">☆</span></li>
				<li><a href="?seccion=prospectos-vendedor" <?php if($seccion_actual==='prospectos-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🎯</span> <span class="sidebar_titulo">Prospectos</span></a><span class="btn_star" onclick="toggle_favorito('Prospectos Vend.')">☆</span></li>
			</ul>
		</div>
		
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Clientes - Comprador</span></h3>
			<ul>
				<li><a href="?seccion=clientes-comprador" <?php if($seccion_actual==='clientes-comprador')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Clientes</span></a><span class="btn_star" onclick="toggle_favorito('Clientes Comp.')">☆</span></li>
				<li><a href="?seccion=prospectos-comprador" <?php if($seccion_actual==='prospectos-comprador')echo'aria-current="page"';?>><span class="menu_icono">🎯</span> <span class="sidebar_titulo">Prospectos</span></a><span class="btn_star" onclick="toggle_favorito('Prospectos Comp.')">☆</span></li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Inmuebles - Vendedor</span></h3>
			<ul>
				<li><a href="?seccion=propiedades-vendedor" <?php if($seccion_actual==='propiedades-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🏠</span> <span class="sidebar_titulo">Propiedades</span></a><span class="btn_star" onclick="toggle_favorito('Propiedades')">☆</span></li>
				<li><a href="?seccion=alquileres-vendedor" <?php if($seccion_actual==='alquileres-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🔑</span> <span class="sidebar_titulo">Alquileres</span></a><span class="btn_star" onclick="toggle_favorito('Alquileres')">☆</span></li>
				<li><a href="?seccion=busqueda-avanzada" <?php if($seccion_actual==='busqueda-avanzada')echo'aria-current="page"';?>><span class="menu_icono">🔎</span> <span class="sidebar_titulo">Búsqueda Avanzada</span></a><span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span></li>
				<li><a href="?seccion=proceso-vendedor" <?php if($seccion_actual==='proceso-vendedor')echo'aria-current="page"';?>><span class="menu_icono">📋</span> <span class="sidebar_titulo">Proceso</span></a><span class="btn_star" onclick="toggle_favorito('Proceso Vendedor')">☆</span></li>
				<li><a href="?seccion=visitas-vendedor" <?php if($seccion_actual==='visitas-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🚶</span> <span class="sidebar_titulo">Visitas</span></a><span class="btn_star" onclick="toggle_favorito('Visitas Vendedor')">☆</span></li>
				<li><a href="?seccion=ofertas-vendedor" <?php if($seccion_actual==='ofertas-vendedor')echo'aria-current="page"';?>><span class="menu_icono">💰</span> <span class="sidebar_titulo">Ofertas</span></a><span class="btn_star" onclick="toggle_favorito('Ofertas Vendedor')">☆</span></li>
				<li><a href="?seccion=post-venta" <?php if($seccion_actual==='post-venta')echo'aria-current="page"';?>><span class="menu_icono">🏡</span> <span class="sidebar_titulo">Post-Venta</span></a><span class="btn_star" onclick="toggle_favorito('Post-Venta')">☆</span></li>
			</ul>
		</div>
		
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Inmuebles - Comprador</span></h3>
			<ul>
				<li><a href="?seccion=propiedades-comprador" <?php if($seccion_actual==='propiedades-comprador')echo'aria-current="page"';?>><span class="menu_icono">🏠</span> <span class="sidebar_titulo">Propiedades</span></a><span class="btn_star" onclick="toggle_favorito('Propiedades')">☆</span></li>
				<li><a href="?seccion=alquileres-comprador" <?php if($seccion_actual==='alquileres-comprador')echo'aria-current="page"';?>><span class="menu_icono">🔑</span> <span class="sidebar_titulo">Alquileres</span></a><span class="btn_star" onclick="toggle_favorito('Alquileres')">☆</span></li>
				<li><a href="?seccion=busqueda-avanzada" <?php if($seccion_actual==='busqueda-avanzada')echo'aria-current="page"';?>><span class="menu_icono">🔎</span> <span class="sidebar_titulo">Búsqueda Avanzada</span></a><span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span></li>
				<li><a href="?seccion=proceso-comprador" <?php if($seccion_actual==='proceso-comprador')echo'aria-current="page"';?>><span class="menu_icono">📋</span> <span class="sidebar_titulo">Proceso</span></a><span class="btn_star" onclick="toggle_favorito('Proceso Comprador')">☆</span></li>
				<li><a href="?seccion=visitas-comprador" <?php if($seccion_actual==='visitas-comprador')echo'aria-current="page"';?>><span class="menu_icono">🚶</span> <span class="sidebar_titulo">Visitas</span></a><span class="btn_star" onclick="toggle_favorito('Visitas Comprador')">☆</span></li>
				<li><a href="?seccion=post-venta" <?php if($seccion_actual==='post-venta')echo'aria-current="page"';?>><span class="menu_icono">🏡</span> <span class="sidebar_titulo">Post-Venta</span></a><span class="btn_star" onclick="toggle_favorito('Post-Venta')">☆</span></li>
			</ul>
		</div>

		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Sistema</span></h3>
			<ul>
				<li><a href="?seccion=importar-csv" <?php if($seccion_actual==='importar-csv')echo'aria-current="page"';?>><span class="menu_icono">📥</span> <span class="sidebar_titulo">Importar CSV</span></a><span class="btn_star" onclick="toggle_favorito('Importar CSV')">☆</span></li>
				<li><a href="?seccion=actividad" <?php if($seccion_actual==='actividad')echo'aria-current="page"';?>><span class="menu_icono">📜</span> <span class="sidebar_titulo">Historial</span></a><span class="btn_star" onclick="toggle_favorito('Historial')">☆</span></li>
				<li><a href="?seccion=configuracion" <?php if($seccion_actual==='configuracion')echo'aria-current="page"';?>><span class="menu_icono">⚙️</span> <span class="sidebar_titulo">Configuración</span></a><span class="btn_star" onclick="toggle_favorito('Configuración')">☆</span></li>
				<li><a href="logout.php"><span class="menu_icono">🚪</span> <span class="sidebar_titulo">Cerrar Sesión</span></a></li>
			</ul>
		</div>
		
		</nav>
		<main class="contenido_derecha">
		<?php
			$archivo = "secciones/" . $seccion_actual . ".php";
			if (file_exists($archivo)) {
				include $archivo;
			} else {
				echo '<h1>Bienvenido a TinoProp</h1>';
				echo '<p>Selecciona una opción del menú.</p>';
			}
		?>
		</main>
		</div>
	</div>
	<div class="modal_overlay" id="modalConfirm" aria-hidden="true">
		<div class="modal_contenido" role="dialog" aria-modal="true" aria-labelledby="modalConfirmTitle">
			<div class="modal_header_dialog">
				<span class="modal_icono_dialog">⚠️</span>
				<h3 id="modalConfirmTitle">Confirmar acción</h3>
			</div>
			<p id="modalConfirmMessage" class="modal_mensaje">Esta acción no se puede deshacer.</p>
			<div class="modal_acciones">
				<button type="button" class="btn_secundario" id="modalConfirmCancel">Cancelar</button>
				<button type="button" class="btn_peligro" id="modalConfirmAccept">Confirmar</button>
			</div>
		</div>
	</div>
	<script src="js/script.js"></script>
</body>
</html>
