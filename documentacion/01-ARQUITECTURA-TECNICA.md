# TinoProp — Documentación Técnica de Arquitectura

**Versión:** 1.0.3
**Última actualización:** 24 de marzo de 2026

---

## 1. Visión General

TinoProp es un **CRM inmobiliario multi-tenant** desarrollado en PHP vanilla (sin frameworks). Está diseñado para que múltiples inmobiliarias operen de forma aislada sobre la misma instancia, cada una con sus propios usuarios, propiedades, clientes y datos.

### Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| **Backend** | PHP 8.x (vanilla, sin framework) |
| **Base de datos** | MySQL / MariaDB con utf8mb4 |
| **Frontend** | HTML5, CSS3 (custom properties), JavaScript vanilla |
| **Servidor** | Apache 2.x con mod_rewrite y mod_headers |
| **Autenticación** | Sesiones PHP con `password_hash()` / `password_verify()` (bcrypt) |
| **Scraping** (opcional) | Python 3 + Playwright |

---

## 2. Estructura de Archivos

```
V1.0.3-Arreglo de errores/
├── .env                    ← Variables de entorno (no versionado)
├── .env.example            ← Plantilla de variables de entorno
├── .htaccess               ← Seguridad HTTP y bloqueo de archivos sensibles
├── index.php               ← Punto de entrada principal (sidebar + carga de secciones)
├── login.php               ← Autenticación con rate limiting
├── logout.php              ← Cierre de sesión
├── cambiar-password.php    ← Cambio obligatorio de contraseña temporal
├── aceptar-terminos.php    ← Aceptación obligatoria de términos legales
│
├── inc/                    ← Lógica de negocio
│   ├── bootstrap.php       ← Inicialización: sesiones, seguridad, .env, idioma
│   ├── helpers.php         ← ~100 funciones: CRUD, roles, validación, utilidades (2263 líneas)
│   ├── db.php              ← Conexión PDO a MySQL
│   └── idioma.php          ← Sistema de internacionalización (i18n)
│
├── secciones/              ← Módulos del CRM (cargados dinámicamente)
│   ├── dashboard.php             ← Panel principal con KPIs y gráficos
│   ├── clientes-vendedor.php     ← Gestión de clientes (lado venta)
│   ├── clientes-comprador.php    ← Gestión de clientes (lado compra)
│   ├── prospectos-vendedor.php   ← Pipeline comercial (vendedor)
│   ├── prospectos-comprador.php  ← Pipeline comercial (comprador)
│   ├── propiedades-vendedor.php  ← Inventario de propiedades (vendedor)
│   ├── propiedades-comprador.php ← Inventario de propiedades (comprador)
│   ├── alquileres-vendedor.php   ← Alquileres (lado captación)
│   ├── alquileres-comprador.php  ← Alquileres (lado demanda)
│   ├── busqueda-avanzada.php     ← Buscador con múltiples filtros
│   ├── proceso-vendedor.php      ← Kanban del proceso de venta
│   ├── proceso-comprador.php     ← Kanban del proceso de compra
│   ├── visitas-vendedor.php      ← Programación de visitas (vendedor)
│   ├── visitas-comprador.php     ← Programación de visitas (comprador)
│   ├── ofertas-vendedor.php      ← Gestión de ofertas económicas
│   ├── post-venta.php            ← Pipeline post-venta (5 etapas)
│   ├── matching.php              ← Matching automático comprador↔propiedad
│   ├── recordatorios.php         ← Agenda y recordatorios
│   ├── documentacion.php         ← Gestión de documentos y PDFs
│   ├── importar-csv.php          ← Importación masiva CSV
│   ├── configuracion.php         ← Perfil, contraseña y preferencias UI
│   ├── actividad.php             ← Log de auditoría
│   ├── legal.php                 ← Términos legales y RGPD
│   ├── peticiones.php            ← Tickets jefe→superadmin
│   ├── admin-dashboard.php       ← Panel de administación (superadmin)
│   ├── admin-inmobiliarias.php   ← Gestión de inmobiliarias (superadmin)
│   ├── admin-usuarios.php        ← Gestión de usuarios (superadmin/jefe)
│   ├── ver_cliente.php           ← Ficha detallada de un cliente
│   └── ver_propiedad.php         ← Ficha detallada de una propiedad
│
├── api/                    ← Endpoints JSON (AJAX)
│   ├── buscar.php          ← Búsqueda global (clientes, propiedades, prospectos)
│   ├── documentacion.php   ← CRUD de archivos y generación de PDFs
│   ├── imagenes.php        ← Galería de fotos de propiedades
│   └── recordatorios.php   ← CRUD de recordatorios (calendario)
│
├── css/
│   ├── estilo.css          ← Hoja de estilos principal (~7200 líneas)
│   └── login-proyecto-entornos.css ← Estilos del login
│
├── js/
│   └── script.js           ← JavaScript principal (sidebar, drag&drop, modales)
│
├── database/               ← Scripts SQL
│   ├── tinoprop_v031_completa.sql  ← Esquema completo unificado
│   ├── tinoprop.sql                ← Esquema original base
│   ├── migracion_v029.sql          ← Migración multi-tenant
│   ├── migracion_v030.sql          ← Migración marco legal
│   └── migracion_v031_roles.sql    ← Migración visibilidad por usuario
│
├── sql/
│   └── migracion_v031.sql          ← Tabla login_intentos (rate limiting)
│
├── scripts/                ← Utilidades externas
│   ├── scrape_habitaclia.py             ← Scraping con requests
│   ├── scrape_habitaclia_playwright.py  ← Scraping con Playwright
│   └── requirements.txt                ← Dependencias Python
│
├── storage/                ← Almacenamiento de archivos (no versionado)
│   ├── uploads/            ← Imágenes de propiedades
│   └── documentacion/      ← Documentos generados y subidos
│
└── logs/                   ← Logs de errores PHP (no versionado)
```

---

## 3. Flujo de una Petición

```
[Navegador] → [Apache + .htaccess]
                    ↓
              [index.php]
                    ↓
            [inc/bootstrap.php]
            ┌────────────────────────┐
            │ 1. Carga .env          │
            │ 2. Conexión BD (PDO)   │
            │ 3. Helpers + i18n      │
            │ 4. Sesión + seguridad  │
            │ 5. Guardia de acceso   │
            │    - ¿Sesión activa?   │
            │    - ¿Password temp?   │
            │    - ¿Términos acepta? │
            └────────────────────────┘
                    ↓
            [Verificar acceso a sección]
            puede_acceder_seccion($seccion)
                    ↓
            [secciones/{seccion}.php]
                    ↓
            [HTML renderizado + i18n]
                    ↓
              [Navegador]
```

### Guardias de bootstrap (en orden)

1. **Autenticación**: si no hay sesión activa y no es una URL pública → redirige a `login.php`
2. **Contraseña temporal**: si `$_SESSION['usuario']['password_temporal'] == 1` → redirige a `cambiar-password.php`
3. **Aceptación de términos**: si no ha aceptado la versión vigente → redirige a `aceptar-terminos.php`
4. **Permisos de sección**: `verificar_acceso()` comprueba rol vs sección solicitada

---

## 4. Arquitectura Multi-Tenant

El sistema es **multi-tenant por fila** (shared database, shared schema). Cada registro de datos incluye un campo `inmobiliaria_id` que determina a qué empresa pertenece.

### Aislamiento de datos

```php
// Filtro por inmobiliaria (toda consulta de negocio lo incluye):
sql_iid()       → " AND inmobiliaria_id = :iid"
sql_iid_params() → ['iid' => 3]

// Filtro por usuario (agentes solo ven sus datos):
sql_uid()       → " AND usuario_id = :uid"
sql_uid_params() → ['uid' => 42]
```

- **SuperAdmin (nivel 99)**: `sql_iid()` devuelve `""` → ve TODAS las inmobiliarias
- **Marketing+ (nivel ≥ 3)**: `sql_uid()` devuelve `""` → ve todos los datos de su inmobiliaria
- **Agentes (nivel 1-2)**: ambos filtros activos → solo ven SUS datos dentro de SU inmobiliaria

### Tablas con aislamiento multi-tenant

Todas las tablas de negocio contienen `inmobiliaria_id`:
`clientes`, `prospectos`, `propiedades`, `notas`, `proceso_propiedades`, `visitas`, `recordatorios`, `ofertas`, `imagenes_propiedades`, `preferencias_usuario`, `actividad_log`, `etiquetas`, `entidad_etiquetas`, `filtros_guardados`, `post_venta`

---

## 5. Sistema de Temas (Dark/Light Mode)

El CSS usa **custom properties (variables CSS)** definidas en tres niveles:

```css
:root { }                          /* Tema claro (por defecto) */
body.tema-oscuro { }               /* Tema oscuro (clase explícita) */
@media (prefers-color-scheme: dark) {
    body:not(.tema-claro):not(.tema-oscuro) { }  /* Tema según SO */
}
```

### Variables principales

| Variable | Claro | Oscuro |
|----------|-------|--------|
| `--fondo` | `#f1f5f9` | `#0c1222` |
| `--fondo-tarjeta` | `#ffffff` | `#131c2e` |
| `--texto` | `#0f172a` | `#e2e8f0` |
| `--primario` | `#3b82f6` | `#60a5fa` |
| `--borde` | `#e2e8f0` | `#1e293b` |

Se incluyen **alias de compatibilidad** (`--bg-card`, `--text-secondary`, etc.) para que todos los componentes funcionen correctamente en ambos temas.

---

## 6. Seguridad

### Cabeceras HTTP (.htaccess + bootstrap.php)
- `X-Frame-Options: DENY` — previene clickjacking
- `X-Content-Type-Options: nosniff` — previene MIME sniffing
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `X-XSS-Protection: 1; mode=block`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` — fuerza HTTPS durante 1 año
- Redirección 301 de HTTP a HTTPS (`.htaccess` mod_rewrite)

### Protecciones implementadas

| Protección | Implementación |
|-----------|---------------|
| **SQL Injection** | PDO con sentencias preparadas nativas (`EMULATE_PREPARES=false`) |
| **XSS** | Función `e()` (escape HTML) en toda salida |
| **CSRF** | Token por sesión con `csrf_token()` / `csrf_verify()` |
| **Brute Force** | Rate limiting: max 5 intentos en 15 min, bloqueo 15 min |
| **Passwords** | bcrypt via `password_hash()` / `password_verify()` |
| **Sesiones** | `session_regenerate_id(true)` en login, cookies httponly + SameSite |
| **Archivos sensibles** | `.htaccess` bloquea `.env`, `.log`, `.sql`, `.md`, `.py` |

### Validación de contraseñas
- Mínimo 8 caracteres
- Al menos 1 mayúscula
- Al menos 1 símbolo especial

---

## 7. Internacionalización (i18n)

El sistema soporta traducción automática del HTML:

1. `inc/idioma.php` carga el idioma preferido del usuario (configuración)
2. `ob_start()` captura todo el HTML de salida
3. `register_shutdown_function('i18n_finalizar_buffer')` traduce cadenas antes de enviar
4. Idiomas disponibles: **Español** (por defecto), **Inglés**

---

## 8. Sistema de Caché

```php
cache_get($clave, $ttl)   // Obtiene valor si no ha expirado
cache_set($clave, $valor)  // Almacena en $_SESSION['_cache']
cache_flush()              // Limpia toda la caché
```

Caché en memoria de sesión para consultas frecuentes, con TTL configurable.
