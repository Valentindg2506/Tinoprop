-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 25-03-2026 a las 09:08:09
-- Versión del servidor: 8.0.45-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tinoprop`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividad_log`
--

CREATE TABLE `actividad_log` (
  `id` int UNSIGNED NOT NULL,
  `usuario_id` int UNSIGNED NOT NULL,
  `usuario_nombre` varchar(100) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `entidad` varchar(50) NOT NULL,
  `entidad_id` int UNSIGNED DEFAULT NULL,
  `descripcion` text,
  `datos_extra` json DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `inmobiliaria_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `tipo` enum('vendedor','comprador') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operacion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genero` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `presupuesto` decimal(12,2) DEFAULT NULL,
  `zona_interesada` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comentarios` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entidad_etiquetas`
--

CREATE TABLE `entidad_etiquetas` (
  `id` int UNSIGNED NOT NULL,
  `entidad_tipo` varchar(30) NOT NULL,
  `entidad_id` int UNSIGNED NOT NULL,
  `etiqueta_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `etiquetas`
--

CREATE TABLE `etiquetas` (
  `id` int UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#3b82f6',
  `usuario_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `imagenes_propiedades`
--

CREATE TABLE `imagenes_propiedades` (
  `id` int UNSIGNED NOT NULL,
  `propiedad_id` int UNSIGNED NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `nombre_original` varchar(255) DEFAULT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `es_principal` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inmobiliarias`
--

CREATE TABLE `inmobiliarias` (
  `id` int UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `cif` varchar(20) DEFAULT NULL,
  `direccion` varchar(250) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `logo_url` varchar(300) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `max_usuarios` int UNSIGNED NOT NULL DEFAULT '10',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_intentos`
--

CREATE TABLE `login_intentos` (
  `id` bigint UNSIGNED NOT NULL,
  `ip` varchar(45) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notas`
--

CREATE TABLE `notas` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `entity_type` enum('cliente','propiedad') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int UNSIGNED NOT NULL,
  `tipo` enum('Nota','Aviso') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nota',
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ofertas`
--

CREATE TABLE `ofertas` (
  `id` int UNSIGNED NOT NULL,
  `propiedad_id` int UNSIGNED NOT NULL,
  `cliente_id` int UNSIGNED DEFAULT NULL,
  `nombre_ofertante` varchar(200) DEFAULT NULL,
  `importe` decimal(12,2) NOT NULL,
  `fecha_oferta` date NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada','contraoferta') NOT NULL DEFAULT 'pendiente',
  `contraoferta_importe` decimal(12,2) DEFAULT NULL,
  `notas` text,
  `usuario_id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `peticiones`
--

CREATE TABLE `peticiones` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL,
  `usuario_id` int UNSIGNED NOT NULL,
  `tipo` enum('error','mejora','consulta','urgente') NOT NULL DEFAULT 'error',
  `prioridad` enum('baja','media','alta','critica') NOT NULL DEFAULT 'media',
  `estado` enum('abierta','en_progreso','resuelta','cerrada') NOT NULL DEFAULT 'abierta',
  `asunto` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `respuesta_admin` text,
  `seccion_afectada` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `resuelto_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `post_venta`
--

CREATE TABLE `post_venta` (
  `id` int NOT NULL,
  `propiedad_id` int NOT NULL,
  `cliente_id` int DEFAULT NULL,
  `usuario_id` int UNSIGNED NOT NULL DEFAULT '0',
  `inmobiliaria_id` int UNSIGNED DEFAULT NULL,
  `etapa` varchar(50) NOT NULL DEFAULT 'pendiente_docs',
  `notas` text,
  `fecha_venta` date DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preferencias_usuario`
--

CREATE TABLE `preferencias_usuario` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `clave` varchar(120) NOT NULL,
  `valor` longtext NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proceso_propiedades`
--

CREATE TABLE `proceso_propiedades` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `propiedad_id` int UNSIGNED NOT NULL,
  `usuario_id` int UNSIGNED NOT NULL DEFAULT '0',
  `equipo` enum('vendedor','comprador') NOT NULL,
  `etapa` varchar(50) NOT NULL DEFAULT 'captacion',
  `notas` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propiedades`
--

CREATE TABLE `propiedades` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `equipo` enum('vendedor','comprador') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubicacion` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metros` int DEFAULT NULL,
  `habitaciones` int DEFAULT NULL,
  `banos` int DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL,
  `moneda` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `periodo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operacion` enum('venta','alquiler') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `visitas` int NOT NULL DEFAULT '0',
  `ofertas` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prospectos`
--

CREATE TABLE `prospectos` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `tipo` enum('vendedor','comprador') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interes` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recordatorios`
--

CREATE TABLE `recordatorios` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_recordatorio` date NOT NULL,
  `hora_recordatorio` time DEFAULT NULL,
  `prospecto_id` int DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'pendiente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `inmobiliaria_id` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `scraped_propiedades`
--

CREATE TABLE `scraped_propiedades` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `fuente` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `tipo` varchar(100) DEFAULT NULL,
  `operacion` varchar(50) DEFAULT NULL,
  `precio` decimal(12,2) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT 'EUR',
  `ubicacion` varchar(200) DEFAULT NULL,
  `zona` varchar(120) DEFAULT NULL,
  `ciudad` varchar(80) DEFAULT NULL,
  `provincia` varchar(80) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `habitaciones` tinyint DEFAULT NULL,
  `banos` tinyint DEFAULT NULL,
  `metros` int DEFAULT NULL,
  `descripcion` text,
  `url` varchar(400) NOT NULL,
  `raw_hash` char(64) NOT NULL,
  `scrape_run` varchar(80) DEFAULT NULL,
  `scraped_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `imagen_url` varchar(500) DEFAULT NULL,
  `imagenes_json` longtext,
  `ascensor` tinyint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `avatar_url` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `password_temporal` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `terminos_aceptados` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Version de Terminos y Condiciones aceptada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas`
--

CREATE TABLE `visitas` (
  `id` int UNSIGNED NOT NULL,
  `inmobiliaria_id` int UNSIGNED NOT NULL DEFAULT '1',
  `equipo` enum('vendedor','comprador') NOT NULL,
  `propiedad_id` int UNSIGNED DEFAULT NULL,
  `cliente_id` int UNSIGNED DEFAULT NULL,
  `fecha_visita` date NOT NULL,
  `hora_visita` time DEFAULT NULL,
  `estado` enum('pendiente','realizada','cancelada') NOT NULL DEFAULT 'pendiente',
  `observaciones` text,
  `recordatorio_id` int DEFAULT NULL,
  `usuario_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividad_log`
--
ALTER TABLE `actividad_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_act_usr` (`usuario_id`),
  ADD KEY `idx_act_ent` (`entidad`,`entidad_id`),
  ADD KEY `idx_act_fecha` (`created_at`),
  ADD KEY `idx_act_inmobiliaria` (`inmobiliaria_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cli_inmob` (`inmobiliaria_id`),
  ADD KEY `idx_clientes_usuario` (`usuario_id`);

--
-- Indices de la tabla `entidad_etiquetas`
--
ALTER TABLE `entidad_etiquetas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ent_tag` (`entidad_tipo`,`entidad_id`,`etiqueta_id`),
  ADD KEY `idx_entidad` (`entidad_tipo`,`entidad_id`);

--
-- Indices de la tabla `etiquetas`
--
ALTER TABLE `etiquetas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_nombre_usr` (`nombre`,`usuario_id`);

--
-- Indices de la tabla `imagenes_propiedades`
--
ALTER TABLE `imagenes_propiedades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_propiedad_id` (`propiedad_id`);

--
-- Indices de la tabla `inmobiliarias`
--
ALTER TABLE `inmobiliarias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inmob_activa` (`activa`);

--
-- Indices de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_li_ip` (`ip`),
  ADD KEY `idx_li_email` (`email`),
  ADD KEY `idx_li_created` (`created_at`);

--
-- Indices de la tabla `notas`
--
ALTER TABLE `notas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notas_entity` (`entity_type`,`entity_id`),
  ADD KEY `fk_notas_usuario` (`usuario_id`),
  ADD KEY `idx_notas_inmob` (`inmobiliaria_id`);

--
-- Indices de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ofertas_propiedad` (`propiedad_id`),
  ADD KEY `idx_ofertas_estado` (`estado`),
  ADD KEY `idx_ofertas_fecha` (`fecha_oferta`),
  ADD KEY `idx_ofertas_inmobiliaria` (`inmobiliaria_id`);

--
-- Indices de la tabla `peticiones`
--
ALTER TABLE `peticiones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pet_inmob` (`inmobiliaria_id`),
  ADD KEY `idx_pet_estado` (`estado`),
  ADD KEY `idx_pet_tipo` (`tipo`),
  ADD KEY `idx_pet_prioridad` (`prioridad`);

--
-- Indices de la tabla `post_venta`
--
ALTER TABLE `post_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `propiedad_id` (`propiedad_id`),
  ADD KEY `etapa` (`etapa`),
  ADD KEY `idx_pv_usuario` (`usuario_id`),
  ADD KEY `idx_pv_inmobiliaria` (`inmobiliaria_id`);

--
-- Indices de la tabla `preferencias_usuario`
--
ALTER TABLE `preferencias_usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_usuario_clave` (`usuario_id`,`clave`);

--
-- Indices de la tabla `proceso_propiedades`
--
ALTER TABLE `proceso_propiedades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_proceso_propiedad` (`propiedad_id`),
  ADD KEY `idx_proceso_equipo` (`equipo`),
  ADD KEY `idx_proceso_etapa` (`etapa`),
  ADD KEY `idx_proc_inmob` (`inmobiliaria_id`);

--
-- Indices de la tabla `propiedades`
--
ALTER TABLE `propiedades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prop_inmob` (`inmobiliaria_id`),
  ADD KEY `idx_propiedades_usuario` (`usuario_id`);

--
-- Indices de la tabla `prospectos`
--
ALTER TABLE `prospectos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prosp_inmob` (`inmobiliaria_id`),
  ADD KEY `idx_prospectos_usuario` (`usuario_id`);

--
-- Indices de la tabla `recordatorios`
--
ALTER TABLE `recordatorios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_fecha` (`fecha_recordatorio`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_recordatorios_inmob` (`inmobiliaria_id`);

--
-- Indices de la tabla `scraped_propiedades`
--
ALTER TABLE `scraped_propiedades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_url` (`url`),
  ADD KEY `idx_ciudad` (`ciudad`),
  ADD KEY `idx_precio` (`precio`),
  ADD KEY `idx_zona` (`zona`),
  ADD KEY `idx_operacion` (`operacion`),
  ADD KEY `idx_scrap_inmob` (`inmobiliaria_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visitas_equipo` (`equipo`),
  ADD KEY `idx_visitas_fecha` (`fecha_visita`),
  ADD KEY `idx_visitas_estado` (`estado`),
  ADD KEY `idx_visitas_recordatorio` (`recordatorio_id`),
  ADD KEY `idx_vis_inmob` (`inmobiliaria_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividad_log`
--
ALTER TABLE `actividad_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `entidad_etiquetas`
--
ALTER TABLE `entidad_etiquetas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `etiquetas`
--
ALTER TABLE `etiquetas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `imagenes_propiedades`
--
ALTER TABLE `imagenes_propiedades`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `inmobiliarias`
--
ALTER TABLE `inmobiliarias`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notas`
--
ALTER TABLE `notas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ofertas`
--
ALTER TABLE `ofertas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `peticiones`
--
ALTER TABLE `peticiones`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `post_venta`
--
ALTER TABLE `post_venta`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `preferencias_usuario`
--
ALTER TABLE `preferencias_usuario`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proceso_propiedades`
--
ALTER TABLE `proceso_propiedades`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `propiedades`
--
ALTER TABLE `propiedades`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prospectos`
--
ALTER TABLE `prospectos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recordatorios`
--
ALTER TABLE `recordatorios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `scraped_propiedades`
--
ALTER TABLE `scraped_propiedades`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `visitas`
--
ALTER TABLE `visitas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `imagenes_propiedades`
--
ALTER TABLE `imagenes_propiedades`
  ADD CONSTRAINT `imagenes_propiedades_ibfk_1` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notas`
--
ALTER TABLE `notas`
  ADD CONSTRAINT `fk_notas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `proceso_propiedades`
--
ALTER TABLE `proceso_propiedades`
  ADD CONSTRAINT `fk_proceso_propiedad` FOREIGN KEY (`propiedad_id`) REFERENCES `propiedades` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
