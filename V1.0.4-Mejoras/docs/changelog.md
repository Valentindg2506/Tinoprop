# Changelog - Tinoprop

Todos los cambios notables del proyecto se documentan en este archivo.

---

## [1.2.0] - 2026-03-30

### Añadido
- **Modo oscuro completo** con toggle en la barra superior
  - Variables CSS para tema oscuro en todos los componentes
  - Persistencia del tema en localStorage
  - Script anti-FOUC (Flash of Unstyled Content) en el head
  - Icono dinámico sol/luna en el botón de toggle
  - Soporte para Bootstrap 5.3 `data-bs-theme`
- **Inyección dinámica de colores whitelabel**
  - Los colores configurados en marca blanca ahora se aplican realmente a la interfaz
  - Función `hexToHsl()` para generar variantes de color
  - Variables CSS `--primary`, `--primary-hover`, `--primary-dark`, `--primary-light`
  - Favicon y logo dinámicos desde whitelabel
- **Rediseño del módulo de automatizaciones**
  - Vista en tarjetas en lugar de tabla
  - Tarjetas de estadísticas: total, activas, ejecuciones, acciones
  - Función de duplicar automatización (incluye acciones)
  - 4 nuevos tipos de trigger: nuevo_formulario, contrato_firmado, factura_pagada, presupuesto_aceptado
  - Iconos con código de colores por tipo de trigger
  - Sección de plantillas rápidas para automatizaciones comunes
- **Documentación completa del proyecto**
  - Manual de usuario
  - Manual técnico
  - Especificaciones del sistema
  - Guía de instalación
  - Changelog

### Mejorado
- **Diseño general de la interfaz**
  - Navbar superior usa variables CSS en lugar de colores hardcodeados
  - Componentes Kanban usan variables CSS
  - Mejor contraste y legibilidad en ambos temas
  - Sidebar con gradiente dinámico según tema
- **style.css** completamente reorganizado con variables CSS semánticas
- **app.js** refactorizado con soporte de temas

### Corregido
- Colores de whitelabel se guardaban en BD pero nunca se aplicaban a la UI

---

## [1.1.0] - 2026-03-29

### Corregido
- **Bug sistémico de corrupción JSON** en 13+ archivos
  - `post()` aplicaba `htmlspecialchars()` corrompiendo datos JSON y HTML
  - Campos afectados: `campos_json`, `contenido_html`, `pasos`, `configuracion`, `firma_imagen`, etc.
  - Solución: usar `$_POST` directamente para campos JSON/HTML
  - Archivos corregidos: automatizaciones, contratos, presupuestos, facturas, formularios, funnels, blog, conversaciones, plantillas_email, pipeline, integraciones, whitelabel
- **Columnas inexistentes en factura pagar.php**
  - `nombre_empresa` → `empresa_nombre`
  - `email_empresa` → `empresa_email`
  - `telefono_empresa` → `empresa_telefono`
- **División por cero** en funnel.php cuando no hay pasos
- **Vulnerabilidad XSS** en módulo de conversaciones
- **Inyección de headers de email** en conversaciones
- **Firma digital** no se guardaba correctamente (datos base64 corrompidos por `post()`)
- **Filtro de contactos** usaba `WHERE activo=1` en lugar de `WHERE estado != 'retirado'`
  - Corregido en: contratos/form.php, presupuestos/form.php, blog/editor.php
- **Campo incorrecto en funnels** editor: `activo` → `activa`

---

## [1.0.0] - 2026-03-28

### Lanzamiento Inicial
- **Dashboard** con métricas y resumen de actividad
- **Gestión de clientes** con CRUD completo y asignación a agentes
- **Catálogo de propiedades** con galería de imágenes y filtros
- **Agenda de visitas** con calendario y estados
- **Gestión de tareas** con asignación y prioridades
- **Pipeline/Kanban** con etapas personalizables y drag & drop
- **Automatizaciones** con triggers y acciones configurables
- **Contratos digitales** con firma integrada
- **Presupuestos** con líneas de detalle y conversión a factura
- **Facturación** con cálculo de IVA y estados de pago
- **Conversaciones** internas entre usuarios
- **Funnels** de marketing con editor visual
- **Formularios web** personalizables con campos dinámicos
- **Blog/CMS** con editor de artículos
- **Gestión documental** con subida de archivos
- **Panel de ajustes** completo:
  - Configuración general
  - Marca blanca (whitelabel)
  - Gestión de usuarios y roles
  - Permisos granulares
  - Claves API
  - Integraciones
  - Plantillas de email
  - Backup de base de datos
- **Seguridad:** CSRF, prepared statements, bcrypt, XSS protection
- **Responsive:** Bootstrap 5.3 mobile-first
