# Informe técnico de Tinoprop (carpeta por carpeta, archivo por archivo)

---

## 1) Resumen de arquitectura

Tinoprop está implementado como una aplicación **monolítica PHP + MySQL**.

- El punto de entrada es `index.php`.
- Las pantallas viven en `secciones/`.
- Los endpoints JSON viven en `api/`.
- Utilidades comunes de backend viven en `inc/`.
- Frontend global en `js/script.js` y `css/estilo.css`.
- Esquema inicial y datos base en `database/tinoprop.sql`.
- Scraping externo de inmuebles en `scripts/*.py`.

Además:
- Autenticación por sesión en `login.php` + `bootstrap.php`.
- Internacionalización por buffer de salida en `inc/idioma.php`.
- Persistencia de ficheros en `storage/` (imágenes/documentación).

---

## 2) Carpeta raíz (`V0.0.23-Documentacion/`)

### `README.md`
- **Qué hace:** documentación general de la versión.
- **Lenguaje:** Markdown.
- **Funciones:** no aplica.
- **Importancia:** explica stack, puesta en marcha y notas de internacionalización.

### `index.php`
- **Qué hace:** shell principal de la aplicación (estructura base + menú + router de secciones).
- **Lenguaje:** PHP + HTML.
- **Flujo clave:**
  - Carga bootstrap.
  - Lee preferencias de UI (`tema`, `densidad`) y las aplica en clases CSS.
  - Lee `?seccion=` y carga `secciones/<seccion>.php`.
  - Si no existe sección, muestra fallback.
- **Entradas/salidas:** GET (`seccion`), SESSION (`usuario`, idioma), salida HTML.

### `login.php`
- **Qué hace:** autenticación de usuario.
- **Lenguaje:** PHP + HTML.
- **Flujo clave:**
  - Si ya hay sesión activa, redirige a `index.php`.
  - Recibe POST `email/password`.
  - Consulta tabla `usuarios` por email.
  - Verifica hash con `password_verify`.
  - Si es válido, guarda `$_SESSION['usuario']` (id/nombre/email/rol).
- **Tablas:** `usuarios`.
- **Entradas/salidas:** POST, SESSION, redirecciones.

### `logout.php`
- **Qué hace:** cierre de sesión.
- **Lenguaje:** PHP.
- **Flujo clave:** destruye sesión y redirige a `login.php`.

---

## 3) Carpeta `inc/` (núcleo backend)

### `inc/bootstrap.php`
- **Qué hace:** bootstrap global de toda petición PHP.
- **Lenguaje:** PHP.
- **Responsabilidades:**
  - `session_start()`.
  - Carga `db.php`, `helpers.php`, `idioma.php`.
  - Aplica idioma preferido (`ui.idioma`) desde preferencias de usuario.
  - Inicia buffer de salida i18n.
  - Protege rutas privadas (si no hay sesión -> login).
  - Finaliza buffer traduciendo HTML al cerrar la petición.
- **Entradas/salidas:** SESSION, SERVER (`SCRIPT_NAME`), redirección.

### `inc/config.php`
- **Qué hace:** configuración de conexión a base de datos.
- **Lenguaje:** PHP.
- **Contenido:** host, dbname, user, pass, charset/collation.

### `inc/db.php`
- **Qué hace:** conexión PDO singleton.
- **Lenguaje:** PHP.
- **Función declarada:**
  - `db(): PDO`.
- **Detalles:** configura `ERRMODE_EXCEPTION` y fetch asociativo.

### `inc/helpers.php`
- **Qué hace:** utilidades comunes y operaciones de soporte de negocio.
- **Lenguaje:** PHP.

#### Bloque 1: utilidades generales
- `e(string $value): string` → escapa HTML (XSS).
- `format_price(float $precio, string $moneda, ?string $periodo): string` → formatea precio/periodo.
- `map_estado_clase(string $estado): string` → normaliza estado para CSS.
- `obtener_origen_propiedad(string $operacion, string $equipo): string` → decide URL de retorno según venta/alquiler y comprador/vendedor.

#### Bloque 2: mensajes flash y validación
- `flash_set(string $key, string $message): void` → guarda mensajes temporales en sesión.
- `flash_get(string $key): ?string` → recupera y consume mensajes flash.
- `validar_requerido(string $valor): bool`.
- `validar_email(string $valor): bool`.
- `validar_telefono(string $valor): bool`.
- `validar_enum(string $valor, array $permitidos): bool`.

#### Bloque 3: preferencias por usuario
- `preferencias_asegurar_tabla(PDO $pdo): void` → crea tabla `preferencias_usuario` si no existe.
- `preferencias_usuario_get(PDO $pdo, string $clave): ?string`.
- `preferencias_usuario_set(PDO $pdo, string $clave, string $valor): void` (upsert).
- `preferencias_usuario_delete(PDO $pdo, string $clave): void`.
- **Tablas:** `preferencias_usuario`.
- **Uso funcional:** tema, densidad, idioma, filtros y orden dashboard.

#### Bloque 4: recordatorios
- `recordatorios_asegurar_tabla(PDO $pdo): void`.
- `recordatorio_crear(PDO $pdo, string $tipo, string $descripcion, string $fecha, ?string $hora, ?int $prospecto_id): ?int`.
- `recordatorios_por_fecha(PDO $pdo, string $fecha): array`.
- `recordatorios_por_mes(PDO $pdo, int $mes, int $ano): array`.
- `recordatorio_obtener(PDO $pdo, int $id): ?array`.
- `recordatorio_actualizar(PDO $pdo, int $id, string $tipo, string $descripcion, string $fecha, ?string $hora, ?int $prospecto_id, string $estado): bool`.
- `recordatorio_eliminar(PDO $pdo, int $id): bool`.
- **Tablas:** `recordatorios`.
- **Nota:** todas las operaciones se filtran por `usuario_id` de sesión.

#### Bloque 5: imágenes de propiedades
- `imagenes_asegurar_tabla(PDO $pdo): void`.
- `imagen_subir(PDO $pdo, int $propiedad_id, array $archivo): ?int`.
- `imagenes_obtener_propiedad(PDO $pdo, int $propiedad_id): array`.
- `imagen_obtener_principal(PDO $pdo, int $propiedad_id): ?array`.
- `imagen_eliminar(PDO $pdo, int $id): bool`.
- `imagen_marcar_principal(PDO $pdo, int $imagen_id): bool`.
- **Tablas:** `imagenes_propiedades`.
- **Sistema de archivos:** guarda en `storage/uploads/propiedades`.

#### Bloque 6: soporte de tabla de scraping
- `scraped_propiedades_asegurar_tabla(PDO $pdo): void`.
- **Qué hace:** crea/ajusta tabla `scraped_propiedades` y columnas evolutivas (`imagen_url`, `imagenes_json`, `ascensor`).

### `inc/idioma.php`
- **Qué hace:** internacionalización ES/EN.
- **Lenguaje:** PHP.
- **Funciones declaradas:**
  - `idioma_actual(): string`.
  - `idioma_es_valido(string $lang): bool`.
  - `idioma_establecer(string $lang): void`.
  - `i18n_traducciones(): array` (diccionario ES→EN).
  - `i18n_traducir_buffer(string $html): string`.
  - `i18n_iniciar_buffer(): void`.
  - `i18n_finalizar_buffer(): void`.
- **Cómo funciona:** traduce el HTML final cuando idioma activo es `en`.

---

## 4) Carpeta `api/` (endpoints)

### `api/recordatorios.php`
- **Qué hace:** API JSON CRUD de recordatorios.
- **Lenguaje:** PHP.
- **Acciones (`action`):**
  - `crear`
  - `obtener`
  - `actualizar`
  - `eliminar`
- **Entradas:** GET/POST/JSON (`php://input`).
- **Salidas:** JSON con `success` y payload/error.
- **Tablas:** `recordatorios`.

### `api/imagenes.php`
- **Qué hace:** API JSON para galería de imágenes de propiedades.
- **Lenguaje:** PHP.
- **Acciones (`action`):**
  - `subir`
  - `obtener`
  - `marcar-principal`
  - `eliminar`
- **Entradas:** GET/POST/JSON y `$_FILES` (`archivo` o `imagenes[]`).
- **Salidas:** JSON.
- **Tablas:** `imagenes_propiedades`.
- **FS:** escritura/borrado en `storage/uploads/propiedades`.

### `api/documentacion.php`
- **Qué hace:** API para gestión documental por entidad.
- **Lenguaje:** PHP.
- **Funciones declaradas:**
  - `doc_json_response(int $code, array $payload): void`
  - `doc_normalizar_entidad(?string $entity_type): ?string`
  - `doc_base_dir(): string`
  - `doc_entity_dir(string $entity_type, int $entity_id, string $kind): string`
  - `doc_size_label(int $bytes): string`
  - `doc_list_files(string $path, string $entity_type, int $entity_id, string $kind): array`
- **Acciones (`action`):**
  - `download`
  - `list_files`
  - `upload`
  - `save_pdf`
- **Entradas:** GET/POST/JSON y `$_FILES['documento']`.
- **Salidas:** JSON o stream de descarga.
- **Persistencia:** filesystem en `storage/documentacion/...`.

---

## 5) Carpeta `secciones/` (módulos funcionales)

### `secciones/dashboard.php`
- **Qué hace:** tablero principal de métricas y actividad.
- **Lenguaje:** PHP (+ integración JS).
- **Funciones declaradas:** no define funciones globales propias.
- **Acciones principales:**
  - Lee filtros GET (`equipo`, `periodo`, `operacion`).
  - Aplica preferencias por defecto.
  - Calcula KPIs, embudo, actividad, alertas, recordatorios próximos.
  - Guarda/resetea orden visual de widgets por POST `dashboard_orden_accion=guardar|reset`.
- **Tablas usadas:** `clientes`, `prospectos`, `propiedades`, `notas`, `recordatorios`, `preferencias_usuario`.

### `secciones/clientes-vendedor.php`
- **Qué hace:** alta/listado/baja de clientes tipo vendedor.
- **Acciones POST:** `crear_cliente`, `eliminar_cliente`.
- **Tablas:** `clientes`, `notas`.

### `secciones/clientes-comprador.php`
- **Qué hace:** alta/listado/baja de clientes tipo comprador.
- **Acciones POST:** `crear_cliente`, `eliminar_cliente`.
- **Tablas:** `clientes`, `notas`.

### `secciones/ver_cliente.php`
- **Qué hace:** ficha detallada de cliente + edición + notas.
- **JS inline:** `activarEdicion(idCampo)`.
- **Acciones POST:** `guardar_cambios`, `guardar_nota`, `eliminar_cliente`.
- **Tablas:** `clientes`, `notas`.
- **Entradas:** GET `id`, `origen`.

### `secciones/prospectos-vendedor.php`
- **Qué hace:** kanban de prospectos vendedor.
- **Acciones POST:**
  - `mover_prospecto_drag`
  - `crear_prospecto`
  - `editar_prospecto`
  - `eliminar_prospecto`
- **Tablas:** `prospectos`.
- **Salida especial:** JSON para movimiento drag.

### `secciones/prospectos-comprador.php`
- **Qué hace:** kanban de prospectos comprador.
- **Acciones POST:** mismas que vendedor.
- **Tablas:** `prospectos`.

### `secciones/propiedades-vendedor.php`
- **Qué hace:** alta/listado/baja de propiedades de venta para vendedor.
- **Acciones POST:** `crear_propiedad`, `eliminar_propiedad`.
- **Tablas:** `propiedades`, `notas`.

### `secciones/propiedades-comprador.php`
- **Qué hace:** alta/listado/baja de propiedades de venta para comprador.
- **Acciones POST:** `crear_propiedad`, `eliminar_propiedad`.
- **Tablas:** `propiedades`, `notas`.

### `secciones/alquileres-vendedor.php`
- **Qué hace:** alta/listado/baja de alquileres para vendedor.
- **Acciones POST:** `crear_alquiler`, `eliminar_propiedad`.
- **Tablas:** `propiedades`, `notas`.

### `secciones/alquileres-comprador.php`
- **Qué hace:** alta/listado/baja de alquileres para comprador.
- **Acciones POST:** `crear_alquiler`, `eliminar_propiedad`.
- **Tablas:** `propiedades`, `notas`.

### `secciones/ver_propiedad.php`
- **Qué hace:** ficha de propiedad con edición, notas y galería de imágenes.
- **JS inline:**
  - `activarEdicion(idCampo)`
  - `subirImagenes(archivos)`
- **Acciones POST:** `guardar_cambios`, `guardar_nota`, `eliminar_propiedad`.
- **Tablas:** `propiedades`, `notas`, `imagenes_propiedades`.
- **Entradas:** GET `id`, `origen`.
- **Integración API:** subida de imágenes contra `/api/imagenes.php?action=subir`.

### `secciones/recordatorios.php`
- **Qué hace:** calendario mensual + CRUD de recordatorios desde interfaz.
- **Integración API:** `api/recordatorios.php`.
- **Entradas GET:** `mes`, `ano`, `fecha`.
- **Tablas:** `recordatorios`.

### `secciones/configuracion.php`
- **Qué hace:** perfil, contraseña y preferencias de usuario.
- **Acciones POST (`accion`):**
  - `actualizar_perfil`
  - `cambiar_password`
  - `guardar_preferencias`
  - (y reset de preferencias)
- **Tablas:** `usuarios`, `preferencias_usuario`.

### `secciones/documentacion.php`
- **Qué hace:** centro documental con subida/listado y generación de PDF por plantillas.
- **Funciones PHP declaradas:**
  - `doc_collect_recent_files(string $kind, int $limit = 10): array`
  - `doc_is_valid_date(?string $value): bool`
  - `doc_filter_recent_files(array $files, string $entity_filter, ?string $from_date, ?string $to_date): array`
  - `doc_paginate(array $files, int $page, int $per_page): array`
  - `doc_build_url(array $overrides = []): string`
- **Funciones JS inline principales:**
  - `abrirPanelSubidas`, `abrirPanelPlantillas`, `volverInicioDocumentacion`
  - `syncUploadEntityUI`, `entidadSeleccionadaUpload`, `cargarListados`
  - `syncTemplateEntityUI`, `plantillaActiva`, `renderCamposPlantilla`
  - `coordFirma`, `firmaStart`, `firmaMove`, `firmaEnd`, `tieneFirma`
  - `clienteActualData`, `propiedadActualData`, `sugerenciaPorCampo`, `autocompletarCamposPlantilla`
  - `construirDocumentoPdf`, `cerrarPreview`
- **Integración API:** `api/documentacion.php` (`list_files`, `upload`, `save_pdf`, `download`).
- **Tablas consultadas:** `clientes`, `propiedades`.

### `secciones/busqueda-avanzada.php`
- **Qué hace:** buscador de inmuebles scrapeados con filtros avanzados.
- **JS inline:** `show(index)` para carrusel de imágenes en resultados.
- **Entradas GET:** `q`, `zona`, `operacion`, `min_precio`, `max_precio`, `habitaciones`, `banos`, `orden`.
- **Tabla:** `scraped_propiedades`.

---

## 6) Carpeta `js/`

### `js/script.js`
- **Qué hace:** lógica global de interacción frontend.
- **Funciones detectadas:**
  - `toggle_favorito(nombre)`
  - `actualizar_menu()`
  - `actualizarContadores()`
  - + funciones internas para drag&drop, modal de confirmación y orden dashboard.
- **Comportamientos clave:**
  - favoritos de menú
  - abrir/cerrar y UX de paneles
  - modal de confirmación antes de acciones destructivas
  - drag&drop de prospectos (kanban)
  - orden de tarjetas dashboard y persistencia (localStorage + POST a backend)

---

## 7) Carpeta `css/`

### `css/estilo.css`
- **Qué hace:** estilos globales de toda la aplicación.
- **Incluye estilos para:**
  - layout general
  - login
  - dashboard
  - tablas/listados
  - kanban
  - modales
  - documentación
  - configuración
  - temas y densidad visual

---

## 8) Carpeta `database/`

### `database/tinoprop.sql`
- **Qué hace:** crea BD/tablas base e inserta datos iniciales.
- **Lenguaje:** SQL.
- **Operaciones principales:** `CREATE DATABASE`, `CREATE TABLE`, `INSERT`.
- **Tablas incluidas:**
  - `usuarios`
  - `clientes`
  - `prospectos`
  - `propiedades`
  - `notas`
  - `scraped_propiedades`
- **Índices/restricciones destacadas:**
  - `usuarios.email` único.
  - claves e índices para búsqueda y rendimiento (especialmente en `scraped_propiedades`).

---

## 9) Carpeta `scripts/`

### `scripts/requirements.txt`
- **Qué hace:** dependencias Python del scraping.
- **Paquetes:** `requests`, `beautifulsoup4`, `mysql-connector-python`.

### `scripts/scrape_habitaclia.py`
- **Qué hace:** scraper principal (HTTP + parseo + normalización + upsert en MySQL).
- **Lenguaje:** Python.
- **Funciones declaradas:**
  - `get_db_conn`
  - `ensure_table`
  - `build_page_url`
  - `fetch_soup`
  - `parse_price`
  - `parse_int`
  - `parse_from_ldjson`
  - `parse_from_cards`
  - `dedupe_listings` (con helpers internos)
  - `normalize_listing`
  - `fetch_images`
  - `normalize_image_url`
  - `pick_first_from_srcset`
  - `normalize_images`
  - `extract_meta`
  - `guess_operacion`
  - `guess_tipo`
  - `_dump_debug_html`
  - `ensure_absolute_url`
  - `upsert_listing`
  - `scrape`
  - `parse_args`
  - `main`
- **Tabla objetivo:** `scraped_propiedades`.
- **Entrada CLI:** `--base-url --pages --delay --run-tag --dry-run --verbose`.

### `scripts/scrape_habitaclia_playwright.py`
- **Qué hace:** scraper alternativo con Playwright para casos anti-bot/captcha.
- **Lenguaje:** Python.
- **Funciones declaradas:**
  - `is_block_page`
  - `_dump_debug_html`
  - `_stealth_init_script`
  - `_first_from_srcset`
  - `_dedupe_urls`
  - `_collect_ldjson_images`
  - `extract_images_from_detail_html`
  - `fetch_html`
  - `scrape_page`
  - `parse_from_ldjson_html`
  - `parse_from_cards_html`
  - `_wait_for_user`
  - `manual_solve_challenge`
  - `main_async`
  - `parse_args`
  - `main`
- **Tabla objetivo:** `scraped_propiedades`.
- **Entrada CLI adicional:** `--headful --user-data-dir --manual-once`.

### `scripts/__pycache__/scrape_habitaclia.cpython-312.pyc`
- **Qué hace:** bytecode cache generado por Python (no código fuente).

### `scripts/__pycache__/scrape_habitaclia_playwright.cpython-312.pyc`
- **Qué hace:** bytecode cache generado por Python (no código fuente).

---

## 10) Carpeta `storage/`

`storage/` es persistencia runtime (datos generados en ejecución).

- **`storage/uploads/propiedades/`**
  - destino físico de imágenes subidas de propiedades.

- **`storage/documentacion/`**
  - almacena documentos por entidad, típicamente:
    - `clientes/<id>/subidos`
    - `clientes/<id>/generados`
    - `propiedades/<id>/subidos`
    - `propiedades/<id>/generados`

No contiene lógica de negocio; solo almacenamiento de ficheros.

---

## 11) Mapa rápido de “archivo -> función principal”

- `login.php` / `logout.php` -> autenticación/sesión.
- `index.php` -> shell y enrutado de módulos.
- `inc/bootstrap.php` -> arranque común + protección.
- `inc/helpers.php` -> utilidades de negocio transversales.
- `inc/idioma.php` -> internacionalización.
- `api/*.php` -> endpoints JSON/documental.
- `secciones/*.php` -> funcionalidades de CRM por módulo.
- `database/tinoprop.sql` -> definición base de datos.
- `scripts/*.py` -> recolección de inmuebles externos.
- `js/script.js` -> interactividad global.
- `css/estilo.css` -> estilos globales.

