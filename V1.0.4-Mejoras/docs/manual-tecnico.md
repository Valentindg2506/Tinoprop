# Manual Técnico - Tinoprop

## 1. Arquitectura del Sistema

### 1.1 Stack Tecnológico
- **Backend:** PHP 7.4+ (sin frameworks)
- **Base de datos:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** Bootstrap 5.3, CSS3 con variables personalizadas, JavaScript vanilla
- **Hosting:** Compatible con Hostinger shared hosting
- **Sin dependencias externas:** No requiere Composer ni Node.js

### 1.2 Patrón de Arquitectura
El CRM utiliza un patrón **MVC simplificado** basado en archivos:
- Cada módulo tiene su propia carpeta en `modules/`
- Los archivos PHP actúan como controlador y vista combinados
- Los modelos son consultas directas PDO dentro de cada archivo
- Los includes compartidos (`header.php`, `footer.php`, `helpers.php`) proporcionan funcionalidad común

### 1.3 Flujo de una Petición
```
1. Usuario accede a /modules/clientes/index.php
2. Se incluye config/database.php (conexión PDO)
3. Se incluye includes/auth.php (verificación de sesión)
4. Se incluye includes/helpers.php (funciones auxiliares)
5. session_start() + requireLogin()
6. Si es POST: procesar formulario antes del header
7. require includes/header.php (carga whitelabel, genera HTML head + navbar)
8. Lógica de la página y HTML del body
9. require includes/footer.php (cierra HTML, scripts JS)
```

## 2. Estructura de Archivos

```
CRM/
├── assets/
│   ├── css/
│   │   └── style.css          # Estilos globales con dark mode
│   ├── js/
│   │   └── app.js             # JavaScript global (dark mode, sidebar, etc.)
│   └── uploads/               # Archivos subidos por usuarios
├── config/
│   └── database.php           # Conexión PDO y constantes
├── docs/                      # Documentación del proyecto
├── includes/
│   ├── auth.php               # Sistema de autenticación
│   ├── header.php             # Cabecera HTML con whitelabel y dark mode
│   ├── footer.php             # Pie de página y scripts
│   └── helpers.php            # Funciones auxiliares
├── install/
│   └── setup.php              # Instalador con creación de tablas
├── modules/
│   ├── ajustes/               # Configuración del sistema
│   │   ├── index.php          # Panel de ajustes
│   │   ├── whitelabel.php     # Personalización de marca
│   │   ├── usuarios.php       # Gestión de usuarios
│   │   ├── roles.php          # Roles y permisos
│   │   ├── api_keys.php       # Claves API
│   │   ├── integraciones.php  # Integraciones externas
│   │   ├── plantillas_email.php # Plantillas de correo
│   │   └── backup.php         # Respaldo de base de datos
│   ├── automatizaciones/      # Motor de automatizaciones
│   ├── blog/                  # Gestor de contenido/blog
│   ├── clientes/              # Gestión de clientes
│   ├── contratos/             # Contratos digitales
│   ├── conversaciones/        # Mensajería interna
│   ├── dashboard/             # Panel principal
│   ├── documentos/            # Gestor documental
│   ├── facturas/              # Facturación
│   ├── formularios/           # Formularios web
│   ├── funnels/               # Embudos de venta
│   ├── pipeline/              # Kanban de oportunidades
│   ├── presupuestos/          # Presupuestos
│   ├── propiedades/           # Catálogo de propiedades
│   ├── tareas/                # Gestión de tareas
│   └── visitas/               # Agenda de visitas
└── index.php                  # Punto de entrada (redirect a login/dashboard)
```

## 3. Base de Datos

### 3.1 Conexión
Definida en `config/database.php`:
```php
$db = new PDO("mysql:host=HOST;dbname=DB;charset=utf8mb4", USER, PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
```

### 3.2 Tablas Principales
| Tabla | Descripción |
|-------|-------------|
| `usuarios` | Usuarios del sistema con roles |
| `roles` | Definición de roles y permisos |
| `clientes` | Base de datos de clientes |
| `propiedades` | Catálogo inmobiliario |
| `visitas` | Agenda de visitas a propiedades |
| `tareas` | Tareas asignadas a usuarios |
| `pipeline_etapas` | Etapas del pipeline/kanban |
| `pipeline_oportunidades` | Oportunidades de venta |
| `automatizaciones` | Reglas de automatización |
| `automatizacion_acciones` | Acciones por automatización |
| `automatizacion_logs` | Registro de ejecuciones |
| `contratos` | Contratos con firma digital |
| `presupuestos` | Presupuestos para clientes |
| `facturas` | Facturación |
| `conversaciones` | Hilos de conversación |
| `mensajes` | Mensajes dentro de conversaciones |
| `documentos` | Archivos y documentos |
| `formularios` | Formularios web configurables |
| `formulario_campos` | Campos de cada formulario |
| `formulario_envios` | Envíos recibidos |
| `funnels` | Embudos de marketing |
| `funnel_pasos` | Pasos de cada embudo |
| `blog_posts` | Artículos del blog |
| `whitelabel_config` | Configuración de marca blanca |
| `ajustes` | Configuración general |
| `api_keys` | Claves de API |
| `integraciones` | Configuración de integraciones |
| `plantillas_email` | Plantillas de correo |

### 3.3 Relaciones Clave
- `clientes.asignado_a` → `usuarios.id`
- `propiedades.creado_por` → `usuarios.id`
- `visitas.cliente_id` → `clientes.id`
- `visitas.propiedad_id` → `propiedades.id`
- `pipeline_oportunidades.etapa_id` → `pipeline_etapas.id`
- `pipeline_oportunidades.cliente_id` → `clientes.id`
- `automatizacion_acciones.automatizacion_id` → `automatizaciones.id`
- `contratos.cliente_id` → `clientes.id`
- `facturas.cliente_id` → `clientes.id`

## 4. Sistema de Autenticación

### 4.1 Sesiones PHP
- Autenticación basada en `$_SESSION`
- `session_start()` al inicio de cada página
- `requireLogin()` redirige a login si no hay sesión

### 4.2 Protección CSRF
```php
// Generar campo oculto en formularios
csrfField();  // Genera <input type="hidden" name="csrf_token" value="...">

// Verificar token en POST handlers
verifyCsrf();  // Compara $_POST['csrf_token'] con $_SESSION['csrf_token']
```

### 4.3 Roles y Permisos
- Los roles se definen en la tabla `roles` con permisos JSON
- Cada usuario tiene un `rol_id`
- `tienePermiso('modulo.accion')` verifica acceso

## 5. Funciones Auxiliares (helpers.php)

| Función | Descripción |
|---------|-------------|
| `post($key, $default)` | Obtiene `$_POST[$key]` con `htmlspecialchars()` |
| `get($key, $default)` | Obtiene `$_GET[$key]` con `htmlspecialchars()` |
| `setFlash($type, $msg)` | Almacena mensaje flash en sesión |
| `getFlash()` | Recupera y limpia mensajes flash |
| `csrfField()` | Genera campo CSRF oculto |
| `verifyCsrf()` | Valida token CSRF |
| `requireLogin()` | Redirige si no autenticado |
| `tienePermiso($perm)` | Verifica permiso del usuario |
| `formatDate($date)` | Formatea fecha a formato español |
| `formatMoney($amount)` | Formatea cantidad monetaria con € |
| `slugify($text)` | Genera slug URL-friendly |
| `uploadFile($field, $dir)` | Sube archivo con validación |

**Nota importante:** `post()` aplica `htmlspecialchars()`. Para campos que contienen JSON o HTML (como `css_custom`, `contenido_html`, `campos_json`), usar `$_POST['campo'] ?? ''` directamente.

## 6. Sistema de Temas

### 6.1 Dark Mode
- Implementado con `data-bs-theme` de Bootstrap 5.3
- Variables CSS en `:root` (light) y `[data-bs-theme="dark"]`
- Toggle en el navbar superior (`#themeToggle`)
- Persistencia vía `localStorage.setItem('theme', 'dark'|'light')`
- Script inline en `<head>` previene flash de tema incorrecto (FOUC)

### 6.2 Whitelabel / Marca Blanca
- Configuración en tabla `whitelabel_config` (id=1)
- `header.php` carga la configuración y genera CSS inline con variables
- `hexToHsl()` computa variantes de color (hover, dark, light)
- Variables inyectadas: `--primary`, `--primary-hover`, `--primary-dark`, `--primary-light`, `--sidebar-active`

## 7. Motor de Automatizaciones

### 7.1 Estructura
- **Triggers:** Eventos que disparan la automatización
  - `nuevo_cliente`, `nueva_propiedad`, `nueva_visita`, `nuevo_formulario`
  - `etapa_cambiada`, `contrato_firmado`, `factura_pagada`, `presupuesto_aceptado`
- **Acciones:** Tareas a ejecutar cuando se dispara el trigger
  - `enviar_email`, `crear_tarea`, `cambiar_etapa`, `notificar`, `webhook`
- **Ejecución:** Registrada en `automatizacion_logs`

### 7.2 Flujo de Ejecución
```
1. Evento ocurre (ej: nuevo cliente creado)
2. Se buscan automatizaciones activas con trigger correspondiente
3. Para cada automatización encontrada:
   a. Se cargan sus acciones ordenadas
   b. Se ejecuta cada acción secuencialmente
   c. Se registra en logs (éxito o error)
```

## 8. Seguridad

### 8.1 Medidas Implementadas
- **SQL Injection:** Prepared statements con PDO en todas las consultas
- **XSS:** `htmlspecialchars()` en salidas de datos, `post()` helper sanitiza input
- **CSRF:** Tokens por sesión verificados en cada POST
- **Autenticación:** Sesiones PHP con `requireLogin()` obligatorio
- **Uploads:** Validación de tipo MIME y extensión, directorio fuera de ejecución
- **Contraseñas:** `password_hash()` con bcrypt

### 8.2 Consideraciones
- Los campos JSON y HTML deben usar `$_POST` directamente, no `post()`
- Las firmas digitales y contenido rico se escapan apropiadamente en la salida
- Los headers de email se sanitizan para prevenir inyección de cabeceras

## 9. Despliegue en Hostinger

### 9.1 Requisitos del Servidor
- PHP 7.4 o superior
- MySQL 5.7 o MariaDB 10.3
- Extensiones PHP: PDO, pdo_mysql, mbstring, json, gd
- Mínimo 100MB espacio en disco

### 9.2 Configuración
- Subir archivos vía File Manager o FTP
- Configurar `config/database.php` con credenciales
- Ejecutar `install/setup.php` desde el navegador
- Establecer permisos 755 en directorios, 644 en archivos
- `assets/uploads/` requiere permisos 775

## 10. Mantenimiento

### 10.1 Backups
- Módulo de backup en `modules/ajustes/backup.php`
- Exporta dump SQL de la base de datos
- Se recomienda backup diario vía cron de Hostinger

### 10.2 Logs
- Errores PHP en el log del servidor
- Ejecuciones de automatizaciones en `automatizacion_logs`
- Actividad de usuarios rastreable por timestamps en registros
