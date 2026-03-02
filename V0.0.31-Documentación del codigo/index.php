<?php
/*
 * ============================================================================
 * Archivo: index.php — V0.0.31 Pre-Producción
 * Aplicación: TinoProp — CRM inmobiliario (gestión de propiedades y clientes)
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este es el PUNTO DE ENTRADA PRINCIPAL de la aplicación. Cuando un usuario
 * abre TinoProp en su navegador, SIEMPRE llega a este archivo.
 *
 * ¿CÓMO FUNCIONA?
 * TinoProp usa una arquitectura de "página única" (SPA-like con PHP):
 * - Toda la estructura (sidebar, cabecera, modal) está en este archivo.
 * - El CONTENIDO cambia según el parámetro ?seccion= de la URL.
 *   Ejemplo: index.php?seccion=dashboard carga secciones/dashboard.php
 *            index.php?seccion=clientes-vendedor carga secciones/clientes-vendedor.php
 *
 * ESTRUCTURA DE LA PÁGINA:
 * ┌──────────────────────────────────────────────────────┐
 * │ [☰ Botón hamburguesa móvil]                        │
 * ┌───────────────┬──────────────────────────────────────┐
 * │  SIDEBAR        │  CONTENIDO PRINCIPAL (<main>)          │
 * │  ──────────── │                                      │
 * │  🏠 TinoProp    │  Se carga dinámicamente desde:          │
 * │  👤 Usuario    │  secciones/{seccion_actual}.php         │
 * │  🔍 Buscador   │                                      │
 * │  🔔 Alertas    │  Ejemplos:                            │
 * │  ★ Favoritos   │  - secciones/dashboard.php             │
 * │  ──────────── │  - secciones/clientes-vendedor.php    │
 * │  📊 Dashboard  │  - secciones/propiedades-vendedor.php  │
 * │  ⏰ Recordat.  │                                      │
 * │  🔗 Matching   │                                      │
 * │  ...          │                                      │
 * └───────────────┴──────────────────────────────────────┘
 * │  [MODAL DE CONFIRMACIÓN - oculto por defecto]              │
 * └──────────────────────────────────────────────────────┘
 *
 * SEGURIDAD Y CONTROL DE ACCESO:
 * - El menú se adapta según el ROL del usuario (SuperAdmin, Jefe, Agente, etc.)
 * - Usa funciones como es_superadmin(), puede_ver_vendedor(), puede_ver_comprador()
 *   para mostrar u ocultar secciones del menú.
 * - Arquitectura multi-tenant: cada inmobiliaria solo ve sus propios datos.
 * - Token CSRF para proteger formularios contra ataques.
 *
 * TEMAS VISUALES:
 * - Soporta tema Claro, Oscuro y Sistema (automático según el SO del usuario).
 * - La densidad de las tablas puede ser Media, Cómoda o Compacta.
 */

/*
 * require_once: incluye y ejecuta el archivo bootstrap.php.
 * Bootstrap es el archivo que arranca toda la aplicación:
 * - Inicia la sesión PHP (¿quién es el usuario?)
 * - Conecta con la base de datos
 * - Carga las funciones auxiliares
 * - Verifica que el usuario está autenticado
 * - Inicia el buffer de internacionalización (i18n)
 * __DIR__ es una constante de PHP que contiene la ruta del directorio actual.
 */
require_once __DIR__ . '/inc/bootstrap.php';

/*
 * CONFIGURACIÓN DEL TEMA VISUAL Y DENSIDAD DE TABLAS
 *
 * Se leen las preferencias del usuario desde la base de datos.
 * Si no tiene preferencias guardadas, se usan valores por defecto.
 *
 * Temas disponibles: 'Sistema' (auto), 'Claro' (fondo blanco), 'Oscuro' (fondo negro)
 * Densidades: 'Media' (normal), 'Comoda' (más espaciado), 'Compacta' (más ajustado)
 *
 * in_array() verifica que el valor es uno de los permitidos (seguridad).
 */
$tema_valido = ['Sistema', 'Claro', 'Oscuro'];
$densidad_valida = ['Media', 'Comoda', 'Compacta'];
$idiomas_validos = ['es', 'en'];

/* Leemos las preferencias del usuario de la base de datos.
 * Si no tiene, usamos 'Sistema' para tema y 'Media' para densidad. */
$tema = preferencias_usuario_get($pdo, 'ui.tema') ?? 'Sistema';
if (!in_array($tema, $tema_valido, true)) $tema = 'Sistema';
$densidad = preferencias_usuario_get($pdo, 'ui.densidad') ?? 'Media';
if (!in_array($densidad, $densidad_valida, true)) $densidad = 'Media';

/*
 * CONSTRUCCIÓN DE CLASES CSS PARA EL <body>
 *
 * Según las preferencias del usuario, añadimos clases CSS al <body>.
 * Estas clases activan las variables CSS (custom properties) correspondientes
 * en la hoja de estilos, cambiando colores, espaciados, etc.
 *
 * Ejemplo resultante: <body class="tema-oscuro densidad-compacta">
 */
$body_classes = [];
if ($tema === 'Oscuro') $body_classes[] = 'tema-oscuro';     // Activa tema oscuro
elseif ($tema === 'Claro') $body_classes[] = 'tema-claro';    // Activa tema claro
// Si es 'Sistema', no añadimos clase: el CSS usará prefers-color-scheme del SO
if ($densidad === 'Comoda') $body_classes[] = 'densidad-comoda';      // Tablas con más espacio
elseif ($densidad === 'Compacta') $body_classes[] = 'densidad-compacta'; // Tablas más comprimidas

/* Unimos las clases con espacios para el atributo class del <body> */
$body_class_attr = trim(implode(' ', $body_classes));
/* Idioma actual para el atributo lang del <html> (accesibilidad y SEO) */
$lang_attr = in_array(idioma_actual(), $idiomas_validos, true) ? idioma_actual() : 'es';

/*
 * ENRUTAMIENTO DE SECCIONES (qué contenido mostrar)
 *
 * La URL determina qué sección se carga. Por ejemplo:
 * - index.php?seccion=dashboard     → carga secciones/dashboard.php
 * - index.php?seccion=clientes-vendedor → carga secciones/clientes-vendedor.php
 *
 * SuperAdmins tienen su propio dashboard (admin-dashboard) como página por defecto.
 *
 * preg_replace elimina cualquier carácter que no sea letra, número, guión o guión bajo.
 * Esto es una medida de seguridad para evitar ataques de "directory traversal"
 * (donde alguien intenta acceder a archivos del servidor con rutas como "../../etc/passwd").
 */
$seccion_por_defecto = es_superadmin() ? 'admin-dashboard' : 'dashboard';
$seccion_actual = preg_replace('/[^a-z0-9_-]/', '', $_GET['seccion'] ?? $seccion_por_defecto);

/* Generamos las notificaciones (alertas) para el usuario.
 * Los SuperAdmins no reciben notificaciones normales (tienen su propio panel). */
$notificaciones = es_superadmin() ? [] : notificaciones_generar($pdo);
$num_notifs = count($notificaciones); // Cantidad para mostrar en el badge

/* CONTROL DE ACCESO: verificamos que el usuario tiene permiso para ver esta sección.
 * Si no tiene permiso, lo redirigimos a su sección por defecto (fallback seguro). */
if (!puede_acceder_seccion($seccion_actual)) {
    $seccion_actual = $seccion_por_defecto;
}

/* Datos del usuario para mostrar en el sidebar */
$rol_actual = usuario_rol();                                    // Rol: agente, jefe, admin, superadmin
$nombre_inmobiliaria = usuario_inmobiliaria_nombre();            // Nombre de la inmobiliaria (multi-tenant)
$nombre_usuario = $_SESSION['usuario']['nombre'] ?? 'Usuario';  // Nombre del usuario logueado
?>
<!-- ============================================================================
     INICIO DEL HTML - Estructura principal de la página
     ============================================================================ -->
<!DOCTYPE html>
<html lang="<?php echo e($lang_attr); ?>">
<head>
	<!-- Codificación UTF-8: soporta caracteres especiales como ñ, é, ü, etc. -->
	<meta charset="UTF-8">
	<!-- Meta viewport: hace la página responsive (adaptable a móviles) -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Título de la pestaña: muestra el nombre de la inmobiliaria del usuario -->
	<title>TinoProp — <?php echo e($nombre_inmobiliaria); ?></title>
	<!-- Hoja de estilos CSS principal (contiene todos los estilos de la app) -->
	<link rel="stylesheet" href="css/estilo.css">
	<!-- Chart.js: librería externa para crear gráficos en el dashboard.
	     'defer' hace que se cargue en segundo plano sin bloquear la página -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
	<!-- Token CSRF: código de seguridad que JavaScript necesita para hacer
	     peticiones POST al servidor (ver getCsrfToken() en script.js).
	     e() es una función que escapa caracteres especiales para prevenir ataques XSS. -->
	<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
	<!-- Meta description para SEO (motores de búsqueda) -->
	<meta name="description" content="Sistema de gestión inmobiliaria (CRM)">
	<!-- Favicon: el icono pequeño que aparece en la pestaña del navegador -->
	<link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<!-- El cuerpo de la página. Las clases CSS controlan el tema visual y la densidad.
     Ejemplo: class="tema-oscuro densidad-compacta" -->
<body<?php echo $body_class_attr ? ' class="' . e($body_class_attr) . '"' : ''; ?>>

	<!-- BOTÓN HAMBURGUESA: solo visible en móvil.
	     Al hacer clic, abre/cierra el sidebar (menú lateral).
	     aria-label mejora la accesibilidad para lectores de pantalla. -->
	<button type="button" class="btn_hamburguesa" id="btnHamburguesa" title="Abrir menú" aria-label="Abrir menú">☰</button>

	<!-- OVERLAY: fondo semitransparente oscuro que aparece detrás del sidebar en móvil.
	     Al hacer clic en él, se cierra el menú. -->
	<div class="sidebar_overlay" id="sidebarOverlay"></div>

	<!-- CONTENEDOR PRINCIPAL: engloba sidebar + contenido.
	     Usa CSS Grid o Flexbox para distribuir el espacio horizontalmente. -->
	<div class="contenedor_principal">

		<!-- ============================================================
		     SIDEBAR (MENÚ LATERAL)
		     El menú de navegación principal de la aplicación.
		     Se puede colapsar (mostrar solo iconos) o expandir.
		     Su contenido varía según el rol del usuario.
		     ============================================================ -->
		<nav class="menu_lateral" id="sidebar">

		<!-- Cabecera del sidebar: logo de la app y botón para colapsar/expandir.
		     El botón ◀ colapsa el sidebar a solo iconos (ahorra espacio). -->
		<div class="sidebar_header">
			<h2><span class="menu_icono">🏠️</span> <span class="sidebar_titulo">TinoProp</span></h2>
			<button type="button" class="btn_colapsar_sidebar" id="btnColapsarSidebar" title="Colapsar menú">◀</button>
		</div>

		<!-- INFORMACIÓN DEL USUARIO: muestra quién está conectado.
		     En TinoProp, cada usuario pertenece a una inmobiliaria (multi-tenant).
		     Aquí se muestra:
		     - Su nombre y rol (ej: "Juan Pérez" / "Agente")
		     - El nombre de su inmobiliaria (ej: "Inmobiliaria Sol")
		     e() escapa texto para prevenir XSS (inyección de código malicioso).
		     roles_disponibles() devuelve un array con la etiqueta legible de cada rol. -->
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

		<!-- BUSCADOR GLOBAL: campo de búsqueda rápida.
		     Permite buscar clientes, propiedades, etc. en toda la aplicación.
		     Los resultados aparecen en un dropdown debajo del campo.
		     autocomplete="off" evita sugerencias del navegador.
		     La lógica de búsqueda está en js/script.js (initBuscadorGlobal). -->
		<div class="buscador_global_wrap">
			<input type="search" id="buscadorGlobal" class="buscador_global_input" placeholder="🔍 Buscar..." autocomplete="off">
			<div class="buscador_global_resultados" id="resultadosBusqueda"></div>
		</div>

		<!-- NOTIFICACIONES: campana con badge (número de alertas pendientes).
		     Al hacer clic, se despliega un dropdown con las alertas.
		     Las notificaciones se generan en PHP (notificaciones_generar) basadas en:
		     - Recordatorios próximos a vencer
		     - Prospectos sin seguimiento
		     - Inmuebles que requieren atención
		     El badge (círculo rojo con número) solo aparece si hay alertas. -->
		<div class="notificaciones_wrap">
			<button type="button" class="btn_notificaciones" id="btnNotificaciones" title="Notificaciones">
				<span class="menu_icono">🔔</span> <span class="sidebar_titulo">Alertas</span>
				<?php if ($num_notifs > 0): /* Solo mostramos el badge si hay alertas */ ?>
					<span class="notif_badge"><?php echo $num_notifs; ?></span>
				<?php endif; ?>
			</button>
			<!-- Dropdown con el listado de notificaciones -->
			<div class="notificaciones_dropdown" id="dropdownNotificaciones">
				<?php if (empty($notificaciones)): /* Si no hay alertas */ ?>
					<p class="notif_vacio">Sin alertas pendientes ✓</p>
				<?php else: /* Si hay alertas, mostramos cada una como enlace */ ?>
					<?php foreach ($notificaciones as $notif): ?>
						<a href="<?php echo e($notif['enlace']); ?>" class="notif_item notif_<?php echo e($notif['tipo']); ?>">
							<span class="notif_icono"><?php echo $notif['icono']; ?></span>
							<span class="notif_texto"><?php echo e($notif['texto']); ?></span>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<!-- SECCIÓN DE FAVORITOS: acceso rápido a las secciones marcadas con estrella.
		     La lista se rellena dinámicamente con JavaScript (actualizar_menu en script.js).
		     Al principio muestra el mensaje "Marca una estrella..." hasta que el usuario
		     añada favoritos haciendo clic en las estrellas (☆) junto a cada sección del menú. -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion titulo_seccion--favoritos"><span class="menu_icono">★</span> <span class="sidebar_titulo">Favoritos</span></h3>
			<ul id="lista_favoritos_menu">
				<li class="texto_vacio">Marca una estrella...</li>
			</ul>
		</div>

		<!-- ============================================================
		     MENÚ BASADO EN ROL: el PHP muestra diferentes opciones según
		     el tipo de usuario. Hay dos sidebars completamente distintos:

		     1. SUPERADMIN: ve el panel de administración global
		        (gestionar todas las inmobiliarias, todos los usuarios, etc.)

		     2. USUARIOS NORMALES: ven las secciones según sus permisos
		        (dashboard, clientes, propiedades, etc.)

		     es_superadmin() devuelve true si el usuario tiene rol superadmin.
		     ============================================================ -->

		<?php if (es_superadmin()): ?>
		<!-- === SIDEBAR SUPERADMIN ===
		     El SuperAdmin es el administrador global de TinoProp.
		     Puede ver y gestionar TODAS las inmobiliarias y usuarios.
		     Su menú es más sencillo porque no trabaja con clientes/propiedades directamente.

		     aria-current="page" indica al navegador y lectores de pantalla
		     cuál es la sección activa actualmente (accesibilidad). -->
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
		<!-- === SIDEBAR USUARIOS NORMALES ===
		     Para usuarios que NO son SuperAdmin (agentes, jefes, supervisores).
		     Cada sección tiene una estrella (☆) junto al enlace que permite
		     añadirla a favoritos con toggle_favorito() de script.js.

		     Las secciones de INICIO son comunes para todos los usuarios normales:
		     - Dashboard: resumen visual con métricas y gráficos
		     - Recordatorios: calendario con citas y seguimientos
		     - Matching: emparejamiento automático cliente-propiedad
		     - Documentación: guías y manuales de la app -->
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

		<?php if (!es_superadmin() && puede_ver_vendedor()): ?>
		<!-- CLIENTES - VENDEDOR: gestión de clientes que VENDEN propiedades.
		     Incluye clientes confirmados y prospectos (potenciales clientes). -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Clientes - Vendedor</span></h3>
			<ul>
				<li><a href="?seccion=clientes-vendedor" <?php if($seccion_actual==='clientes-vendedor')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Clientes</span></a><span class="btn_star" onclick="toggle_favorito('Clientes Vend.')">☆</span></li>
				<li><a href="?seccion=prospectos-vendedor" <?php if($seccion_actual==='prospectos-vendedor')echo'aria-current="page"';?>><span class="menu_icono">🎯</span> <span class="sidebar_titulo">Prospectos</span></a><span class="btn_star" onclick="toggle_favorito('Prospectos Vend.')">☆</span></li>
			</ul>
		</div>
		<?php endif; ?>

		<?php if (!es_superadmin() && puede_ver_comprador()): ?>
		<!-- CLIENTES - COMPRADOR: gestión de clientes que COMPRAN propiedades.
		     Misma estructura que vendedor pero orientada a compradores. -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Clientes - Comprador</span></h3>
			<ul>
				<li><a href="?seccion=clientes-comprador" <?php if($seccion_actual==='clientes-comprador')echo'aria-current="page"';?>><span class="menu_icono">👥</span> <span class="sidebar_titulo">Clientes</span></a><span class="btn_star" onclick="toggle_favorito('Clientes Comp.')">☆</span></li>
				<li><a href="?seccion=prospectos-comprador" <?php if($seccion_actual==='prospectos-comprador')echo'aria-current="page"';?>><span class="menu_icono">🎯</span> <span class="sidebar_titulo">Prospectos</span></a><span class="btn_star" onclick="toggle_favorito('Prospectos Comp.')">☆</span></li>
			</ul>
		</div>
		<?php endif; ?>

		<?php if (!es_superadmin() && puede_ver_vendedor()): ?>
		<!-- INMUEBLES - VENDEDOR: gestión de propiedades en venta/alquiler.
		     Incluye: listado de propiedades, alquileres, búsqueda avanzada,
		     proceso de venta (tablero Kanban), visitas, ofertas y post-venta.
		     Esta es la sección más completa del CRM inmobiliario. -->
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
		<?php endif; ?>

		<?php if (!es_superadmin() && puede_ver_comprador()): ?>
		<!-- INMUEBLES - COMPRADOR: propiedades desde la perspectiva del comprador.
		     Similar a vendedor pero sin ofertas (el comprador recibe ofertas). -->
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
		<?php endif; ?>

		<?php if (!es_superadmin()): ?>
		<!-- SISTEMA: herramientas de administración para usuarios normales.
		     Se muestra a todos los no-superadmin, pero con contenido condicional:
		     - puede_ver_sistema(): permite ver Importar CSV e Historial
		     - puede_gestionar_usuarios(): permite gestionar Usuarios y Peticiones
		     - Configuración y Legal son accesibles para todos
		     - Cerrar Sesión siempre visible -->
		<div class="grupo_menu">
			<h3 class="titulo_seccion"><span class="sidebar_titulo">Sistema</span></h3>
			<ul>
				<?php if (puede_ver_sistema()): /* Solo usuarios con permiso de sistema */ ?>
				<li><a href="?seccion=importar-csv" <?php if($seccion_actual==='importar-csv')echo'aria-current="page"';?>><span class="menu_icono">📥</span> <span class="sidebar_titulo">Importar CSV</span></a><span class="btn_star" onclick="toggle_favorito('Importar CSV')">☆</span></li>
				<li><a href="?seccion=actividad" <?php if($seccion_actual==='actividad')echo'aria-current="page"';?>><span class="menu_icono">📜</span> <span class="sidebar_titulo">Historial</span></a><span class="btn_star" onclick="toggle_favorito('Historial')">☆</span></li>
				<?php endif; ?>
				<?php if (puede_gestionar_usuarios()): /* Solo jefes y admins pueden gestionar usuarios */ ?>
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

		<!-- ============================================================
		     ÁREA DE CONTENIDO PRINCIPAL
		     Aquí se carga dinámicamente la sección seleccionada del menú.
		     El PHP busca el archivo en la carpeta secciones/ y lo incluye.

		     Ejemplo: si $seccion_actual = 'dashboard'
		     → se incluye secciones/dashboard.php

		     Si el archivo no existe (URL manipulada o error), muestra
		     un mensaje de bienvenida como fallback.
		     ============================================================ -->
		<main class="contenido_derecha">
		<?php
			/* Construimos la ruta al archivo PHP de la sección */
			$archivo = "secciones/" . $seccion_actual . ".php";

			/* file_exists verifica que el archivo existe antes de incluirlo
			 * (seguridad: no intentar cargar archivos inexistentes) */
			if (file_exists($archivo)) {
				include $archivo; // Incluye y ejecuta el contenido de la sección
			} else {
				// Fallback: mensaje de bienvenida si la sección no existe
				echo '<h1>Bienvenido a TinoProp</h1>';
				echo '<p>Selecciona una opción del menú.</p>';
			}
		?>
		</main>
	</div>
	<!-- ============================================================
	     MODAL DE CONFIRMACIÓN GLOBAL
	     Ventana emergente que aparece cuando el usuario va a realizar
	     una acción peligrosa (eliminar un registro, cambiar un estado, etc.).

	     Estructura:
	     - modal_overlay: fondo oscuro semitransparente
	     - modal_contenido: la caja blanca centrada con el mensaje
	     - modal_acciones: botones Cancelar y Confirmar

	     Empieza oculto (aria-hidden="true"). JavaScript lo muestra
	     cuando un formulario tiene el atributo data-confirm.
	     Ver cerrarModal() y abrirModal() en script.js.

	     Atributos de accesibilidad:
	     - role="dialog": indica que es una ventana de diálogo
	     - aria-modal="true": indica que bloquea la interacción con el fondo
	     - aria-labelledby: conecta el título con el diálogo para lectores de pantalla
	     ============================================================ -->
	<div class="modal_overlay" id="modalConfirm" aria-hidden="true">
		<div class="modal_contenido" role="dialog" aria-modal="true" aria-labelledby="modalConfirmTitle">
			<div class="modal_header_dialog">
				<span class="modal_icono_dialog">⚠️</span>
				<h3 id="modalConfirmTitle">Confirmar acción</h3>
			</div>
			<!-- Mensaje dinámico: JavaScript cambia este texto según la acción -->
			<p id="modalConfirmMessage" class="modal_mensaje">Esta acción no se puede deshacer.</p>
			<div class="modal_acciones">
				<button type="button" class="btn_secundario" id="modalConfirmCancel">Cancelar</button>
				<button type="button" class="btn_peligro" id="modalConfirmAccept">Confirmar</button>
			</div>
		</div>
	</div>

	<!-- Cargamos el JavaScript principal al final del body para que el DOM
	     ya esté construido cuando el script se ejecute.
	     (Alternativa a usar DOMContentLoaded, aunque el script usa ambos métodos) -->
	<script src="js/script.js"></script>
</body>
</html>
