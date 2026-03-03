<?php
/*
 * Archivo: index.php — V0.0.31 Pre-Producción
 * Incluye: sidebar colapsable, buscador global, breadcrumbs, notificaciones, CSRF, iconos menú,
 *          menú dinámico basado en rol de usuario, multi-tenant, verificación de acceso.
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

// SuperAdmin: su dashboard por defecto es admin-dashboard
$seccion_por_defecto = es_superadmin() ? 'admin-dashboard' : 'dashboard';
$seccion_actual = preg_replace('/[^a-z0-9_-]/', '', $_GET['seccion'] ?? $seccion_por_defecto);
$notificaciones = es_superadmin() ? [] : notificaciones_generar($pdo);
$num_notifs = count($notificaciones);

// Verificar acceso a la sección solicitada
if (!puede_acceder_seccion($seccion_actual)) {
    $seccion_actual = $seccion_por_defecto; // Fallback seguro
}

$rol_actual = usuario_rol();
$nombre_inmobiliaria = usuario_inmobiliaria_nombre();
$nombre_usuario = $_SESSION['usuario']['nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="<?php echo e($lang_attr); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>TinoProp — <?php echo e($nombre_inmobiliaria); ?></title>
	<link rel="stylesheet" href="css/estilo.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
	<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
	<meta name="description" content="Sistema de gestión inmobiliaria (CRM)">
	<link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body<?php echo $body_class_attr ? ' class="' . e($body_class_attr) . '"' : ''; ?>>
	<button type="button" class="btn_hamburguesa" id="btnHamburguesa" title="Abrir menú" aria-label="Abrir menú">☰</button>
	<div class="sidebar_overlay" id="sidebarOverlay"></div>
	<div class="contenedor_principal">
		<nav class="menu_lateral" id="sidebar">

		<div class="sidebar_header">
			<h2><span class="menu_icono">🏠️</span> <span class="sidebar_titulo">TinoProp</span></h2>
			<button type="button" class="btn_colapsar_sidebar" id="btnColapsarSidebar" title="Colapsar menú">◀</button>
		</div>

		<!-- Info usuario/inmobiliaria -->
		<div class="sidebar_user_info">
			<div class="user_info_nombre" title="<?php echo e($nombre_usuario); ?>">
				<span class="user_info_avatar">👤</span>
				<span class="sidebar_titulo">
					<strong><?php echo e($nombre_usuario); ?></strong>
					<small><?php echo e(roles_disponibles()[$rol_actual]['label'] ?? $rol_actual); ?></small>
				</span>
			</div>
			<div class="user_info_inmob" title="<?php echo e($nombre_inmobiliaria); ?>">
				<span class="menu_icono">🏢</span>
				<span class="sidebar_titulo"><?php echo e($nombre_inmobiliaria); ?></span>
			</div>
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

		<?php if (es_superadmin()): ?>
		<!-- === SIDEBAR SUPERADMIN === -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Administración</span></h3>
			<ul>
				<li><a href="?seccion=admin-dashboard" <?php if($seccion_actual==='admin-dashboard')echo'aria-current="page"';?>><span class="menu_icono">🛠️</span> <span class="sidebar_titulo">Panel Admin</span></a></li>
				<li><a href="?seccion=admin-inmobiliarias" <?php if($seccion_actual==='admin-inmobiliarias')echo'aria-current="page"';?>><span class="menu_icono">🏢</span> <span class="sidebar_titulo">Inmobiliarias</span></a></li>
				<li><a href="?seccion=admin-usuarios" <?php if($seccion_actual==='admin-usuarios')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Usuarios</span></a></li>
				<li><a href="?seccion=peticiones" <?php if($seccion_actual==='peticiones')echo'aria-current="page"';?>><span class="menu_icono">📩</span> <span class="sidebar_titulo">Peticiones</span></a></li>
			</ul>
		</div>
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Sistema</span></h3>
			<ul>
				<li><a href="?seccion=configuracion" <?php if($seccion_actual==='configuracion')echo'aria-current="page"';?>><span class="menu_icono">⚙️</span> <span class="sidebar_titulo">Configuración</span></a></li>
				<li><a href="?seccion=legal" <?php if($seccion_actual==='legal')echo'aria-current="page"';?>><span class="menu_icono">📜</span> <span class="sidebar_titulo">Legal</span></a></li>
				<li><a href="logout.php"><span class="menu_icono">🚪</span> <span class="sidebar_titulo">Cerrar Sesión</span></a></li>
			</ul>
		</div>
		<?php else: ?>
		<!-- === SIDEBAR USUARIOS NORMALES === -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Inicio</span></h3>
			<ul>
				<li><a href="?seccion=dashboard" <?php if($seccion_actual==='dashboard')echo'aria-current="page"';?>><span class="menu_icono">📊</span> <span class="sidebar_titulo">Dashboard</span></a><span class="btn_star" onclick="toggle_favorito('Dashboard')">☆</span></li>
				<li><a href="?seccion=recordatorios" <?php if($seccion_actual==='recordatorios')echo'aria-current="page"';?>><span class="menu_icono">⏰</span> <span class="sidebar_titulo">Recordatorios</span></a><span class="btn_star" onclick="toggle_favorito('Recordatorios')">☆</span></li>
				<li><a href="?seccion=matching" <?php if($seccion_actual==='matching')echo'aria-current="page"';?>><span class="menu_icono">🔗</span> <span class="sidebar_titulo">Matching</span></a><span class="btn_star" onclick="toggle_favorito('Matching')">☆</span></li>
				<li><a href="?seccion=documentacion" <?php if($seccion_actual==='documentacion')echo'aria-current="page"';?>><span class="menu_icono">📖</span> <span class="sidebar_titulo">Documentación</span></a><span class="btn_star" onclick="toggle_favorito('Documentación')">☆</span></li>
			</ul>
		</div>
		<?php endif; ?>

		<?php
		/* ---------- Menú unificado Vendedor / Comprador ----------
		   Si el usuario ve ambos lados, se añade sufijo " Venta" / " Compra"
		   para distinguir. Si solo ve uno, el menú queda limpio sin sufijo.
		   Búsqueda Avanzada y Post-Venta se muestran UNA sola vez (son compartidos). */
		$_ve_v  = !es_superadmin() && puede_ver_vendedor();
		$_ve_c  = !es_superadmin() && puede_ver_comprador();
		$_ambos = $_ve_v && $_ve_c;
		$_sv    = $_ambos ? ' Venta'  : '';
		$_sc    = $_ambos ? ' Compra' : '';
		?>

		<?php if ($_ve_v || $_ve_c): ?>
		<!-- === CLIENTES (unificado) === -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Clientes</span></h3>
			<ul>
				<?php if ($_ve_v): ?>
				<li><a href="?seccion=clientes-vendedor" <?php if($seccion_actual==='clientes-vendedor')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Clientes<?= $_sv ?></span></a><span class="btn_star" onclick="toggle_favorito('Clientes Vend.')">☆</span></li>
				<li><a href="?seccion=prospectos-vendedor" <?php if($seccion_actual==='prospectos-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🎯</span> <span class="sidebar_titulo">Prospectos<?= $_sv ?></span></a><span class="btn_star" onclick="toggle_favorito('Prospectos Vend.')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_c): ?>
				<li><a href="?seccion=clientes-comprador" <?php if($seccion_actual==='clientes-comprador')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Clientes<?= $_sc ?></span></a><span class="btn_star" onclick="toggle_favorito('Clientes Comp.')">☆</span></li>
				<li><a href="?seccion=prospectos-comprador" <?php if($seccion_actual==='prospectos-comprador')echo'aria-current="page"';?>><span class="menu_icono">🎯</span> <span class="sidebar_titulo">Prospectos<?= $_sc ?></span></a><span class="btn_star" onclick="toggle_favorito('Prospectos Comp.')">☆</span></li>
				<?php endif; ?>
			</ul>
		</div>

		<!-- === INMUEBLES (unificado) === -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Inmuebles</span></h3>
			<ul>
				<?php if ($_ve_v): ?>
				<li><a href="?seccion=propiedades-vendedor" <?php if($seccion_actual==='propiedades-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🏠</span> <span class="sidebar_titulo">Propiedades<?= $_sv ?></span></a><span class="btn_star" onclick="toggle_favorito('Propiedades Vend.')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_c): ?>
				<li><a href="?seccion=propiedades-comprador" <?php if($seccion_actual==='propiedades-comprador')echo'aria-current="page"';?>><span class="menu_icono">🏠</span> <span class="sidebar_titulo">Propiedades<?= $_sc ?></span></a><span class="btn_star" onclick="toggle_favorito('Propiedades Comp.')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_v): ?>
				<li><a href="?seccion=alquileres-vendedor" <?php if($seccion_actual==='alquileres-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🔑</span> <span class="sidebar_titulo">Alquileres<?= $_sv ?></span></a><span class="btn_star" onclick="toggle_favorito('Alquileres Vend.')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_c): ?>
				<li><a href="?seccion=alquileres-comprador" <?php if($seccion_actual==='alquileres-comprador')echo'aria-current="page"';?>><span class="menu_icono">🔑</span> <span class="sidebar_titulo">Alquileres<?= $_sc ?></span></a><span class="btn_star" onclick="toggle_favorito('Alquileres Comp.')">☆</span></li>
				<?php endif; ?>
				<li><a href="?seccion=busqueda-avanzada" <?php if($seccion_actual==='busqueda-avanzada')echo'aria-current="page"';?>><span class="menu_icono">🔎</span> <span class="sidebar_titulo">Búsqueda Avanzada</span></a><span class="btn_star" onclick="toggle_favorito('Búsqueda Avanzada')">☆</span></li>
				<?php if ($_ve_v): ?>
				<li><a href="?seccion=proceso-vendedor" <?php if($seccion_actual==='proceso-vendedor')echo'aria-current="page"';?>><span class="menu_icono">📋</span> <span class="sidebar_titulo">Proceso<?= $_sv ?></span></a><span class="btn_star" onclick="toggle_favorito('Proceso Vendedor')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_c): ?>
				<li><a href="?seccion=proceso-comprador" <?php if($seccion_actual==='proceso-comprador')echo'aria-current="page"';?>><span class="menu_icono">📋</span> <span class="sidebar_titulo">Proceso<?= $_sc ?></span></a><span class="btn_star" onclick="toggle_favorito('Proceso Comprador')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_v): ?>
				<li><a href="?seccion=visitas-vendedor" <?php if($seccion_actual==='visitas-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🚶</span> <span class="sidebar_titulo">Visitas<?= $_sv ?></span></a><span class="btn_star" onclick="toggle_favorito('Visitas Vendedor')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_c): ?>
				<li><a href="?seccion=visitas-comprador" <?php if($seccion_actual==='visitas-comprador')echo'aria-current="page"';?>><span class="menu_icono">🚶</span> <span class="sidebar_titulo">Visitas<?= $_sc ?></span></a><span class="btn_star" onclick="toggle_favorito('Visitas Comprador')">☆</span></li>
				<?php endif; ?>
				<?php if ($_ve_v): ?>
				<li><a href="?seccion=ofertas-vendedor" <?php if($seccion_actual==='ofertas-vendedor')echo'aria-current="page"';?>><span class="menu_icono">💰</span> <span class="sidebar_titulo">Ofertas</span></a><span class="btn_star" onclick="toggle_favorito('Ofertas Vendedor')">☆</span></li>
				<?php endif; ?>
				<li><a href="?seccion=post-venta" <?php if($seccion_actual==='post-venta')echo'aria-current="page"';?>><span class="menu_icono">🏡</span> <span class="sidebar_titulo">Post-Venta</span></a><span class="btn_star" onclick="toggle_favorito('Post-Venta')">☆</span></li>
			</ul>
		</div>
		<?php endif; ?>

		<?php if (!es_superadmin()): ?>
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Sistema</span></h3>
			<ul>
				<?php if (puede_ver_sistema()): ?>
				<li><a href="?seccion=importar-csv" <?php if($seccion_actual==='importar-csv')echo'aria-current="page"';?>><span class="menu_icono">📥</span> <span class="sidebar_titulo">Importar CSV</span></a><span class="btn_star" onclick="toggle_favorito('Importar CSV')">☆</span></li>
				<li><a href="?seccion=actividad" <?php if($seccion_actual==='actividad')echo'aria-current="page"';?>><span class="menu_icono">📜</span> <span class="sidebar_titulo">Historial</span></a><span class="btn_star" onclick="toggle_favorito('Historial')">☆</span></li>
				<?php endif; ?>
				<?php if (puede_gestionar_usuarios()): ?>
				<li><a href="?seccion=admin-usuarios" <?php if($seccion_actual==='admin-usuarios')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Usuarios</span></a><span class="btn_star" onclick="toggle_favorito('Usuarios')">☆</span></li>
				<li><a href="?seccion=peticiones" <?php if($seccion_actual==='peticiones')echo'aria-current="page"';?>><span class="menu_icono">📩</span> <span class="sidebar_titulo">Peticiones</span></a><span class="btn_star" onclick="toggle_favorito('Peticiones')">☆</span></li>
				<?php endif; ?>
				<li><a href="?seccion=configuracion" <?php if($seccion_actual==='configuracion')echo'aria-current="page"';?>><span class="menu_icono">⚙️</span> <span class="sidebar_titulo">Configuración</span></a><span class="btn_star" onclick="toggle_favorito('Configuración')">☆</span></li>
				<li><a href="?seccion=legal" <?php if($seccion_actual==='legal')echo'aria-current="page"';?>><span class="menu_icono">📜</span> <span class="sidebar_titulo">Legal</span></a></li>
				<li><a href="logout.php"><span class="menu_icono">🚪</span> <span class="sidebar_titulo">Cerrar Sesión</span></a></li>
			</ul>
		</div>
		<?php endif; ?>
		
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
