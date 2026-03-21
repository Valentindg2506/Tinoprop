# TinoProp — Referencia de API y Funciones

**Versión:** 1.0.0 — Producción  
**Última actualización:** 3 de marzo de 2026

---

## Parte I — Endpoints de API

Todos los endpoints están en `api/` y devuelven JSON (excepto `download` que envía el archivo).  
Requieren sesión activa. Las acciones de escritura verifican token CSRF.

---

### 1. `api/buscar.php` — Búsqueda Global

| Método | Parámetro | Tipo | Requerido | Descripción |
|--------|-----------|------|:---------:|-------------|
| GET | `q` | string | ✅ | Término de búsqueda (mín. 2 caracteres) |

**Respuesta exitosa:** `200`
```json
[
  {
    "tipo": "cliente",
    "id": 12,
    "nombre": "Juan",
    "apellidos": "Pérez",
    "url": "?seccion=clientes_vendedor&id=12"
  },
  {
    "tipo": "propiedad",
    "id": 5,
    "titulo": "Piso centro",
    "url": "?seccion=propiedades_vendedor&id=5"
  }
]
```

**Respuesta sin resultados / término corto:** `200` → `[]`

**Ámbito de búsqueda:**  
Busca en `clientes`, `propiedades` y `prospectos` — filtrado por `sql_iid` + `sql_uid`.

---

### 2. `api/documentacion.php` — Documentación de Entidades

Gestiona archivos (subidos y generados como PDF) vinculados a clientes o propiedades.

#### Acciones disponibles

| Acción | Método | CSRF | Descripción |
|--------|--------|:----:|-------------|
| `list_files` | GET | ❌ | Listar archivos de una entidad |
| `upload` | POST | ✅ | Subir documento (PDF, JPG, PNG, WEBP) |
| `save_pdf` | POST | ✅ | Guardar PDF generado desde plantilla |
| `download` | GET | ❌ | Descargar un archivo |

#### `list_files`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `entity_type` | string | ✅ | `"cliente"` o `"propiedad"` |
| `entity_id` | int | ✅ | ID de la entidad |

**Respuesta:** `200`
```json
{
  "success": true,
  "uploaded": [
    {
      "name": "20260301_contrato.pdf",
      "size": 204800,
      "size_label": "200 KB",
      "modified": "2026-03-01 14:30:00",
      "download_url": "api/documentacion.php?action=download&..."
    }
  ],
  "generated": [ ... ]
}
```

#### `upload`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `entity_type` | string (POST) | ✅ | `"cliente"` o `"propiedad"` |
| `entity_id` | int (POST) | ✅ | ID |
| `documento` | file (multipart) | ✅ | Archivo ≤ 20 MB |
| `csrf_token` | string (POST) | ✅ | Token CSRF |

**MIME permitidos:** `application/pdf`, `image/jpeg`, `image/png`, `image/webp`

**Respuesta:** `200`
```json
{ "success": true, "message": "Archivo subido correctamente", "file": "20260301_141500_contrato_a1b2c3.pdf" }
```

#### `save_pdf`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `entity_type` | string (JSON) | ✅ | `"cliente"` o `"propiedad"` |
| `entity_id` | int (JSON) | ✅ | ID |
| `template_key` | string (JSON) | ❌ | Clave de la plantilla |
| `template_name` | string (JSON) | ❌ | Nombre visible |
| `pdf_base64` | string (JSON) | ✅ | Contenido PDF en Base64 |
| `csrf_token` | string (JSON) | ✅ | Token CSRF |

#### `download`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `entity_type` | string (GET) | ✅ | `"cliente"` o `"propiedad"` |
| `entity_id` | int (GET) | ✅ | ID |
| `kind` | string (GET) | ✅ | `"uploaded"` o `"generated"` |
| `file` | string (GET) | ✅ | Nombre del archivo |

**Respuesta:** Stream del archivo con su MIME type correcto (inline).

**Seguridad:** Verificación de tenant — el usuario solo accede a documentos de su inmobiliaria.

---

### 3. `api/imagenes.php` — Galería de Propiedades

Gestiona imágenes asociadas a propiedades (subida, listado, portada, eliminación).

#### Acciones disponibles

| Acción | Método | CSRF | Descripción |
|--------|--------|:----:|-------------|
| `subir` | POST | ✅ | Subir una o varias imágenes |
| `obtener` | GET | ❌ | Listar imágenes de una propiedad |
| `marcar-principal` | POST | ✅ | Marcar imagen como portada |
| `eliminar` | POST | ✅ | Eliminar imagen (BD + archivo) |

#### `subir`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `propiedad_id` | int (POST) | ✅ | ID de propiedad |
| `archivo` o `imagenes[]` | file(s) | ✅ | Una o varias imágenes |
| `csrf_token` | string | ✅ | Token CSRF |

**Respuesta:** `200`
```json
{ "success": true, "ids": [1, 2, 3], "message": "3 imagen(es) subida(s)", "errors": [] }
```

#### `obtener`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `propiedad_id` | int (GET) | ✅ | ID de propiedad |

**Respuesta:** `200`
```json
{
  "success": true,
  "data": [
    { "id": 1, "url": "uploads/propiedades/5/img_abc123.jpg", "principal": 1 },
    { "id": 2, "url": "uploads/propiedades/5/img_def456.jpg", "principal": 0 }
  ]
}
```

#### `marcar-principal`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `id` (JSON) o `imagen_id` (POST) | int | ✅ | ID de la imagen |

#### `eliminar`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `id` (JSON) o `imagen_id` (POST) | int | ✅ | ID de la imagen |

---

### 4. `api/recordatorios.php` — CRUD de Recordatorios

Gestión completa de recordatorios con sincronización automática con la tabla de visitas.

#### Acciones disponibles

| Acción | Método | CSRF | Descripción |
|--------|--------|:----:|-------------|
| `crear` | POST/JSON | ✅ | Crear nuevo recordatorio |
| `obtener` | GET/JSON | ❌ | Obtener un recordatorio por ID |
| `actualizar` | POST/JSON | ✅ | Modificar un recordatorio |
| `eliminar` | POST/JSON | ✅ | Eliminar recordatorio (y visita vinculada) |

#### `crear`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `tipo` | string | ✅ | Tipo: Llamada, Visita, Seguimiento, etc. |
| `descripcion` | string | ✅ | Texto descriptivo |
| `fecha` | string | ✅ | Fecha `YYYY-MM-DD` |
| `hora` | string | ❌ | Hora `HH:MM` |
| `prospecto_id` | int | ❌ | ID de prospecto vinculado |

> Si el tipo es "Visita", se crea automáticamente una entrada en la tabla `visitas`.

**Respuesta:** `200`
```json
{ "success": true, "id": 42, "message": "Recordatorio creado" }
```

#### `actualizar`

Mismos campos que `crear` + `id` (obligatorio) + `estado` (opcional, default: `"pendiente"`).

#### `eliminar`

| Parámetro | Tipo | Requerido | Descripción |
|-----------|------|:---------:|-------------|
| `id` | int | ✅ | ID del recordatorio |

> Si tiene una visita vinculada, esta también se elimina automáticamente.

---

### Códigos de Error Comunes

| Código | Situación |
|--------|-----------|
| `400` | Parámetros faltantes o acción no especificada |
| `403` | Acceso denegado (tenant incorrecto, CSRF inválido) |
| `404` | Recurso no encontrado |
| `500` | Error interno del servidor |

---

## Parte II — Funciones de `helpers.php`

El archivo `inc/helpers.php` contiene **~100 funciones** organizadas en 14 grupos.

---

### Grupo 0 — Multi-Tenant: Roles y Permisos

| Función | Firma | Descripción |
|---------|-------|-------------|
| `roles_disponibles` | `(): array` | Devuelve mapa de roles con label y nivel |
| `usuario_rol` | `(): string` | Rol del usuario en sesión |
| `usuario_inmobiliaria_id` | `(): int` | ID de inmobiliaria del usuario actual |
| `usuario_inmobiliaria_nombre` | `(): string` | Nombre de la inmobiliaria |
| `es_superadmin` | `(): bool` | Verifica si el usuario es superadmin |
| `tiene_nivel` | `(string $rol_minimo): bool` | ¿Tiene al menos este nivel? |
| `puede_ver_vendedor` | `(): bool` | ¿Accede a secciones vendedor? |
| `puede_ver_comprador` | `(): bool` | ¿Accede a secciones comprador? |
| `puede_ver_sistema` | `(): bool` | ¿Accede a CSV/Historial? (supervisor+) |
| `puede_gestionar_usuarios` | `(): bool` | ¿Gestiona usuarios? (jefe+) |
| `puede_acceder_seccion` | `(string $seccion): bool` | Verifica acceso a sección concreta |
| `verificar_acceso` | `(string $seccion): void` | Redirige si no tiene acceso |

### Filtros SQL Multi-Tenant

| Función | Firma | Descripción |
|---------|-------|-------------|
| `sql_iid` | `(string $alias = ''): string` | Cláusula `AND inmobiliaria_id = :iid` |
| `sql_iid_params` | `(): array` | Parámetros para bind: `['iid' => id]` |
| `sql_iid_pair` | `(string $alias = ''): array` | Combinación `[sql, params]` |
| `sql_uid` | `(string $alias = ''): string` | Cláusula `AND usuario_id = :uid` (agentes) |
| `sql_uid_params` | `(): array` | Parámetros: `['uid' => id]` |
| `sql_uid_pair` | `(string $alias = ''): array` | Combinación `[sql, params]` |

---

### Grupo 0b — Gestión de Inmobiliarias

| Función | Firma | Descripción |
|---------|-------|-------------|
| `inmobiliarias_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla si no existe |
| `inmobiliaria_crear` | `(PDO $pdo, array $datos): ?int` | Alta de inmobiliaria |
| `inmobiliaria_actualizar` | `(PDO $pdo, int $id, array $datos): bool` | Editar datos |
| `inmobiliaria_toggle_activa` | `(PDO $pdo, int $id): bool` | Activar/desactivar |
| `inmobiliarias_listar` | `(PDO $pdo): array` | Listar todas |
| `inmobiliaria_obtener` | `(PDO $pdo, int $id): ?array` | Obtener por ID |
| `inmobiliaria_eliminar` | `(PDO $pdo, int $id): bool` | Eliminar con cascada |

---

### Grupo 0c — Gestión de Usuarios

| Función | Firma | Descripción |
|---------|-------|-------------|
| `usuarios_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla si no existe |
| `usuarios_listar_por_inmobiliaria` | `(PDO $pdo, int $inmobiliaria_id): array` | Lista usuarios de una inmobiliaria |
| `usuario_crear` | `(PDO $pdo, array $datos): array` | Crea usuario con password temporal |
| `generar_password_temporal` | `(): string` | Genera password aleatoria segura |
| `usuario_actualizar_rol` | `(PDO $pdo, int $id, string $rol, int $iid): bool` | Cambia rol |
| `usuario_toggle_activo` | `(PDO $pdo, int $id, int $iid): bool` | Activa/desactiva |
| `usuario_obtener` | `(PDO $pdo, int $id): ?array` | Obtiene usuario por ID |

---

### Grupo 1 — Utilidades de Salida y Formato

| Función | Firma | Descripción |
|---------|-------|-------------|
| `e` | `(string $value): string` | Escape HTML (prevención XSS) |
| `format_price` | `(float $precio, string $moneda, ?string $periodo): string` | Formatea precio con moneda |
| `map_estado_clase` | `(string $estado): string` | Devuelve clase CSS según estado |
| `obtener_origen_propiedad` | `(string $operacion, string $equipo): string` | Determina origen de propiedad |

---

### Grupo 2 — Flash Messages y Validaciones

| Función | Firma | Descripción |
|---------|-------|-------------|
| `flash_set` | `(string $key, string $message): void` | Guarda mensaje flash en sesión |
| `flash_get` | `(string $key): ?string` | Recupera y consume mensaje flash |
| `validar_requerido` | `(string $valor): bool` | Campo no vacío |
| `validar_email` | `(string $valor): bool` | Formato email válido |
| `validar_password_segura` | `(string $valor): bool` | ≥8 chars, mayúscula, símbolo |
| `validar_telefono` | `(string $valor): bool` | Formato telefónico |
| `validar_enum` | `(string $valor, array $permitidos): bool` | Valor en lista permitida |

---

### Grupo 3 — Preferencias de Usuario

| Función | Firma | Descripción |
|---------|-------|-------------|
| `preferencias_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla si no existe |
| `preferencias_usuario_get` | `(PDO $pdo, string $clave): ?string` | Lee preferencia |
| `preferencias_usuario_set` | `(PDO $pdo, string $clave, string $valor): void` | Guarda preferencia |
| `preferencias_usuario_delete` | `(PDO $pdo, string $clave): void` | Elimina preferencia |

---

### Grupo 4 — Recordatorios

| Función | Firma | Descripción |
|---------|-------|-------------|
| `recordatorios_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `recordatorio_crear` | `(PDO $pdo, tipo, desc, fecha, ?hora, ?prospecto_id): ?int` | Alta |
| `recordatorios_por_fecha` | `(PDO $pdo, string $fecha): array` | Listar por día |
| `recordatorios_por_mes` | `(PDO $pdo, int $mes, int $ano): array` | Listar por mes |
| `recordatorio_obtener` | `(PDO $pdo, int $id): ?array` | Obtener por ID |
| `recordatorio_actualizar` | `(PDO $pdo, id, tipo, desc, fecha, ?hora, ?prospecto_id, estado): bool` | Editar |
| `recordatorio_eliminar` | `(PDO $pdo, int $id): bool` | Eliminar |

---

### Grupo 5 — Imágenes de Propiedades

| Función | Firma | Descripción |
|---------|-------|-------------|
| `imagenes_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `imagen_subir` | `(PDO $pdo, int $propiedad_id, array $archivo): ?int` | Sube y persiste |
| `imagenes_obtener_propiedad` | `(PDO $pdo, int $propiedad_id): array` | Lista por propiedad |
| `imagen_obtener_principal` | `(PDO $pdo, int $propiedad_id): ?array` | Imagen portada |
| `imagen_eliminar` | `(PDO $pdo, int $id): bool` | Elimina (BD+archivo) |
| `imagen_marcar_principal` | `(PDO $pdo, int $imagen_id): bool` | Marca como portada |

---

### Grupo 6 — Propiedades Scrapeadas

| Función | Firma | Descripción |
|---------|-------|-------------|
| `scraped_propiedades_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla auxiliar |

---

### Grupo 7 — CSRF / Seguridad

| Función | Firma | Descripción |
|---------|-------|-------------|
| `csrf_token` | `(): string` | Genera o recupera token |
| `csrf_field` | `(): string` | HTML input hidden con token |
| `csrf_verify` | `(): void` | Valida token en POST (redirige si falla) |
| `csrf_verify_api` | `(): void` | Valida token para API (JSON 403 si falla) |

---

### Grupo 8 — Historial de Actividad

| Función | Firma | Descripción |
|---------|-------|-------------|
| `actividad_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `actividad_registrar` | `(PDO $pdo, accion, entidad, ?id, desc, datos_extra): void` | Registra acción |
| `actividad_listar` | `(PDO $pdo, int $limite, int $offset): array` | Lista con paginación |
| `actividad_contar` | `(PDO $pdo): int` | Total de registros |

---

### Grupo 9 — Notificaciones Inteligentes

| Función | Firma | Descripción |
|---------|-------|-------------|
| `notificaciones_generar` | `(PDO $pdo): array` | Genera alertas dashboard |

---

### Grupo 10 — Caché de Consultas

| Función | Firma | Descripción |
|---------|-------|-------------|
| `cache_get` | `(string $clave, int $ttl): mixed` | Lee caché por clave |
| `cache_set` | `(string $clave, mixed $valor): void` | Escribe en caché |
| `cache_flush` | `(): void` | Limpia toda la caché |

---

### Grupo 11 — Exportación CSV

| Función | Firma | Descripción |
|---------|-------|-------------|
| `exportar_csv` | `(array $datos, string $nombre): void` | Envía CSV como descarga |

---

### Grupo 12 — Breadcrumbs y Paginación

| Función | Firma | Descripción |
|---------|-------|-------------|
| `generar_breadcrumbs` | `(string $seccion): array` | Genera migas de pan |
| `renderizar_breadcrumbs` | `(string $seccion): string` | HTML de breadcrumbs |
| `paginar` | `(int $total, int $por_pagina, int $pagina_actual): array` | Cálculo de paginación |
| `renderizar_paginacion` | `(array $pag, string $base_url): string` | HTML de paginador |

---

### Grupo 13 — Búsqueda Global y Notas

| Función | Firma | Descripción |
|---------|-------|-------------|
| `busqueda_global` | `(PDO $pdo, string $termino, int $limite): array` | Busca en clientes+propiedades+prospectos |
| `nota_actualizar` | `(PDO $pdo, int $id, string $texto, string $tipo): bool` | Edita nota |
| `nota_eliminar` | `(PDO $pdo, int $id): bool` | Elimina nota |

---

### Grupo 14 — Etiquetas (Tags)

| Función | Firma | Descripción |
|---------|-------|-------------|
| `etiquetas_asegurar_tabla` | `(PDO $pdo): void` | Crea tablas de etiquetas |
| `etiqueta_crear` | `(PDO $pdo, string $nombre, string $color): ?int` | Crea etiqueta |
| `etiquetas_listar` | `(PDO $pdo): array` | Lista todas |
| `etiqueta_eliminar` | `(PDO $pdo, int $id): bool` | Elimina etiqueta |
| `entidad_asignar_etiqueta` | `(PDO $pdo, tipo, entidad_id, etiqueta_id): bool` | Asigna a entidad |
| `entidad_quitar_etiqueta` | `(PDO $pdo, tipo, entidad_id, etiqueta_id): bool` | Quita de entidad |
| `entidad_obtener_etiquetas` | `(PDO $pdo, tipo, entidad_id): array` | Lista etiquetas de entidad |

---

### Grupo 15 — Visitas

| Función | Firma | Descripción |
|---------|-------|-------------|
| `visitas_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `visita_crear` | `(PDO $pdo, equipo, ?prop_id, ?cli_id, fecha, ?hora, obs, ?crear_rec): ?int` | Alta con opción de recordatorio |
| `visita_crear_desde_recordatorio` | `(PDO $pdo, int $recordatorio_id, string $equipo): ?int` | Crea visita desde recordatorio |
| `visitas_listar` | `(PDO $pdo, string $equipo, string $filtro): array` | Lista con filtro de estado |
| `visita_actualizar_estado` | `(PDO $pdo, int $id, string $estado): bool` | Cambia estado |
| `visita_actualizar` | `(PDO $pdo, id, ?prop_id, ?cli_id, fecha, ?hora, estado, obs): bool` | Edición completa |
| `visita_eliminar` | `(PDO $pdo, int $id, bool $eliminar_rec): bool` | Elimina (y opcionalmente recordatorio) |

---

### Grupo 16 — Ofertas

| Función | Firma | Descripción |
|---------|-------|-------------|
| `ofertas_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `oferta_crear` | `(PDO $pdo, prop_id, ?cli_id, ?nombre, importe, fecha, notas): ?int` | Alta |
| `ofertas_listar` | `(PDO $pdo, string $filtro): array` | Lista con filtro de estado |
| `oferta_cambiar_estado` | `(PDO $pdo, id, estado, ?contraoferta): bool` | Cambia estado |
| `oferta_actualizar` | `(PDO $pdo, id, prop_id, ?cli_id, ?nombre, importe, fecha, estado, ?contr, notas): bool` | Edición completa |
| `oferta_eliminar` | `(PDO $pdo, int $id): bool` | Elimina |

---

### Grupo 17 — Importación CSV

| Función | Firma | Descripción |
|---------|-------|-------------|
| `importar_csv_clientes` | `(PDO $pdo, string $filepath, string $tipo): array` | Importa clientes desde CSV |
| `importar_csv_propiedades` | `(PDO $pdo, string $filepath, string $equipo): array` | Importa propiedades desde CSV |

---

### Grupo 18 — Filtros Guardados y Matching

| Función | Firma | Descripción |
|---------|-------|-------------|
| `filtros_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `filtro_guardar` | `(PDO $pdo, nombre, seccion, parametros): ?int` | Guarda filtro |
| `filtros_listar` | `(PDO $pdo, string $seccion): array` | Lista filtros de sección |
| `filtro_eliminar` | `(PDO $pdo, int $id): bool` | Elimina filtro |
| `matching_buscar` | `(PDO $pdo, int $limite): array` | Busca coincidencias comprador↔propiedad |
| `timeline_entidad` | `(PDO $pdo, entidad_tipo, entidad_id): array` | Historial cronológico de entidad |

---

### Grupo 19 — Proceso de Venta/Compra (Kanban)

| Función | Firma | Descripción |
|---------|-------|-------------|
| `proceso_propiedades_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla de proceso |
| `propiedad_duplicar` | `(PDO $pdo, int $id): ?int` | Duplica propiedad |

---

### Grupo 20 — Peticiones (Tickets)

| Función | Firma | Descripción |
|---------|-------|-------------|
| `peticiones_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla |
| `peticion_crear` | `(PDO $pdo, array $datos): ?int` | Crea petición |
| `peticiones_listar_todas` | `(PDO $pdo, ?string $filtro): array` | Lista todas (SuperAdmin) |
| `peticiones_listar_por_inmobiliaria` | `(PDO $pdo, int $iid): array` | Lista por inmobiliaria |
| `peticion_obtener` | `(PDO $pdo, int $id): ?array` | Obtiene por ID |
| `peticion_responder` | `(PDO $pdo, id, respuesta, nuevo_estado): bool` | Responde a petición |
| `peticion_cambiar_estado` | `(PDO $pdo, int $id, string $estado): bool` | Cambia estado |
| `peticiones_contar_por_estado` | `(PDO $pdo): array` | Conteo agrupado por estado |
| `sistema_obtener_estadisticas` | `(PDO $pdo): array` | KPIs globales del sistema |

---

### Grupo 21 — Legal y Rate Limiting

| Función | Firma | Descripción |
|---------|-------|-------------|
| `db_config` | `(): array` | Lee configuración de BD desde .env |
| `usuario_ha_aceptado_terminos` | `(): bool` | ¿Aceptó versión actual? |
| `usuario_aceptar_terminos` | `(PDO $pdo, int $id): void` | Registra aceptación |
| `requiere_aceptacion_terminos` | `(): bool` | ¿Necesita aceptar? |
| `login_intentos_asegurar_tabla` | `(PDO $pdo): void` | Crea tabla rate limit |
| `login_obtener_ip` | `(): string` | IP del solicitante |
| `login_intentos_bloqueado` | `(PDO $pdo, ip, email): int` | Segundos de bloqueo restantes |
| `login_intentos_registrar` | `(PDO $pdo, ip, email): void` | Registra intento fallido |
| `login_intentos_limpiar` | `(PDO $pdo, ip, email): void` | Limpia tras login exitoso |
| `login_intentos_purgar` | `(PDO $pdo): void` | Purga registros expirados |

---

## Parte III — Scripts Auxiliares

### `scripts/`

| Script | Descripción |
|--------|-------------|
| `insertar_datos_prueba.php` | Seed de datos de demo para testing |

### `inc/`

| Archivo | Descripción |
|---------|-------------|
| `bootstrap.php` | Arranque: session, .env, PDO, auto-crear tablas, verificar login |
| `helpers.php` | Todas las funciones de negocio (~3900 líneas) |

### `css/`

| Archivo | Descripción |
|---------|-------------|
| `estilo.css` | Hoja de estilos principal (~7200 líneas) con CSS variables para temas |

### `js/`

| Archivo | Descripción |
|---------|-------------|
| `app.js` | Lógica frontend: sidebar, modales, drag&drop, búsqueda, notificaciones |
| `pdf-generator.js` | Generación de PDFs desde plantillas en cliente |
