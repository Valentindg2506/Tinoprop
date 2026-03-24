# 🏠 TinoProp — CRM Inmobiliario Multi-Tenant

<div align="center">

**Sistema de gestión inmobiliaria (CRM) completo, multi-tenant y multi-rol**

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/Licencia-Ver_LICENSE-lightgrey?style=for-the-badge)

</div>

---

## 📖 Descripción

**TinoProp** es una aplicación web de tipo **CRM (Customer Relationship Management)** diseñada para la gestión integral de agencias inmobiliarias. Desarrollado como proyecto intermodular del Grado Superior en **Desarrollo de Aplicaciones Multiplataforma (DAM)**.

El sistema es **multi-tenant**: múltiples inmobiliarias comparten la misma instalación, cada una viendo exclusivamente sus datos. Incluye un completo sistema de **roles y permisos jerárquico** con 7 niveles, paneles diferenciados para vendedores y compradores, gestión documental, sistema de tickets y mucho más.

---

## 🚀 Funcionalidades

### Módulos Principales (19 módulos)

| Módulo | Descripción |
|--------|-------------|
| 📊 **Dashboard** | Panel de control con métricas, gráficos y KPIs personalizables |
| 👥 **Clientes Vendedor/Comprador** | Gestión de clientes separada por rol con fichas detalladas |
| 🎯 **Prospectos (Pipeline Kanban)** | Seguimiento de leads con drag & drop por etapas |
| 🏠 **Propiedades Vendedor/Comprador** | Inventario de inmuebles (venta/alquiler) con galería de imágenes |
| 🔑 **Alquileres Vendedor/Comprador** | Gestión específica de propiedades en alquiler |
| 📋 **Proceso (Kanban)** | Flujo de trabajo: captación → publicada → visitas → negociación → documentación → cerrada |
| 🚶 **Visitas** | Programación y seguimiento de visitas con estados y recordatorios |
| 💰 **Ofertas** | Gestión de ofertas con estados (pendiente, aceptada, rechazada, contraoferta) |
| 🏡 **Post-Venta** | Seguimiento posterior al cierre de operaciones |
| 🔗 **Matching** | Cruce inteligente entre demandas de compradores y propiedades disponibles |
| 🔎 **Búsqueda Avanzada** | Buscador con filtros multicriteria |
| ⏰ **Recordatorios** | Alertas por fecha/hora vinculados a prospectos y visitas |
| 📖 **Documentación** | Gestión documental por entidad (subida, generación PDF, descarga) |
| 📥 **Importar CSV** | Carga masiva de datos desde archivos CSV |
| 📜 **Historial de Actividad** | Log completo de auditoría de todas las acciones |
| 🔔 **Notificaciones** | Alertas automáticas sobre visitas pendientes, recordatorios, etc. |
| 📩 **Peticiones / Tickets** | Sistema de soporte interno entre niveles jerárquicos |
| ⚙️ **Configuración** | Perfil, contraseña, preferencias de dashboard, tema, densidad, idioma |
| 📜 **Legal** | Términos y Condiciones con versionado y aceptación obligatoria |

### Panel SuperAdmin

| Módulo | Descripción |
|--------|-------------|
| 🛠️ **Panel Admin** | Dashboard global de la plataforma |
| 🏢 **Inmobiliarias** | Gestión de inmobiliarias (tenants): alta, baja, configuración, límites |
| 👥 **Usuarios** | Gestión global de usuarios de todas las inmobiliarias |
| 📩 **Peticiones** | Gestión de tickets de soporte recibidos |

### Características Transversales

- 🔍 **Buscador global** en el sidebar (clientes, propiedades y prospectos)
- ⭐ **Favoritos** — secciones marcadas para acceso rápido
- 🌐 **Internacionalización (i18n)** — Español / Inglés
- 🎨 **Temas**: Sistema, Claro, Oscuro
- 📐 **Densidad de tablas**: Media, Cómoda, Compacta
- 🏷️ **Etiquetas personalizadas** con colores
- 💾 **Filtros guardados** por sección y usuario
- 📊 **Exportación CSV** de datos

---

## 👥 Sistema de Roles y Permisos

Jerarquía de **7 roles** con permisos acumulativos:

| Nivel | Rol | Alcance |
|:-----:|-----|---------|
| 99 | **Super Admin** | Control absoluto de toda la plataforma (inmobiliarias, usuarios, tickets) |
| 5 | **Jefe** | Director de inmobiliaria. Control total de su tenant |
| 4 | **Supervisor** | Supervisa agentes. Acceso a herramientas de sistema |
| 3 | **Marketing** | Herramientas de difusión. Ve ambos lados (vendedor y comprador) |
| 2 | **Agente** | Agente completo. Ve ambos lados (vendedor y comprador) |
| 1 | **Agente Vendedor** | Solo secciones del lado vendedor |
| 1 | **Agente Comprador** | Solo secciones del lado comprador |

---

## 🛠️ Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| **Backend** | PHP 8.x (vanilla, sin frameworks) |
| **Base de datos** | MySQL / MariaDB con PDO |
| **Frontend** | HTML5, CSS3 puro, JavaScript ES6+ vanilla |
| **Gráficos** | Chart.js |
| **Iconos** | Font Awesome + Emojis nativos |
| **Servidor** | Apache |

---

## 🔒 Seguridad

- Contraseñas hasheadas con **bcrypt**
- Contraseñas temporales con cambio obligatorio en primer login
- Protección **CSRF** con tokens por formulario
- Escape **XSS** en toda la salida HTML
- **PDO** con prepared statements nativos (prevención SQL Injection)
- **Rate limiting** contra fuerza bruta
- Cabeceras HTTP de seguridad (X-Frame-Options, X-Content-Type-Options, HSTS, etc.)
- Redirección forzada a **HTTPS** + cabecera **HSTS** (max-age=1 año)
- Protección de directorios sensibles y archivos de configuración
- Términos y Condiciones versionados con aceptación obligatoria

---

## 🗄️ Base de Datos

- **18 tablas** con relaciones, foreign keys e índices optimizados
- Motor **InnoDB** con soporte transaccional
- Charset **utf8mb4** / `utf8mb4_unicode_ci`
- Arquitectura multi-tenant con aislamiento de datos por inmobiliaria
- Log de auditoría completo con registro de IP y datos JSON

---

## 📌 Historial de Versiones

El proyecto ha evolucionado a través de **32 versiones**:

| Versión | Hito |
|---------|------|
| V0.0.01 | Web inicial |
| V0.0.03 | Módulo de clientes |
| V0.0.07 | Módulo de inmuebles |
| V0.0.08 | Dashboard |
| V0.0.09 | Base de datos MySQL |
| V0.0.13 | Drag & Drop (Kanban) |
| V0.0.16 | Recordatorios |
| V0.0.17 | Galería de imágenes |
| V0.0.19 | Búsqueda avanzada |
| V0.0.20 | Configuración |
| V0.0.21 | Preferencias UI (temas) |
| V0.0.22 | Internacionalización |
| V0.0.23 | Documentación |
| V0.0.24 | Autenticación |
| V0.0.25 | Proceso de propiedades (Kanban) |
| V0.0.26 | Visitas |
| V0.0.29 | Roles multi-tenant |
| V0.0.30 | Pre-producción |
| V0.0.31 | Documentación del código |
| **V1.0.0** | **🚀 Producción** |
| V1.0.1 | Correcciones post-producción |
| V1.0.2 | Arreglo de errores |
| **V1.0.3** | **Arreglo de errores + correcciones de seguridad** |

---

## 👤 Autor

**Valentín Antonio De Gennaro**  
Estudiante de Desarrollo de Aplicaciones Multiplataforma (DAM)  
Valencia, España

---

<div align="center">
<sub>Desarrollado con 💻 en Valencia, España — 2025/2026</sub>
</div>
