# Especificaciones del Sistema - Tinoprop

## 1. Descripción General

**Tinoprop** es un sistema de gestión de relaciones con clientes (CRM) especializado para el sector inmobiliario en España. Desarrollado como aplicación web PHP pura, está diseñado para funcionar en hosting compartido sin dependencias de frameworks externos.

## 2. Requisitos Funcionales

### 2.1 Gestión de Clientes
- RF-01: Crear, editar, eliminar y listar clientes
- RF-02: Asignar clientes a agentes/usuarios
- RF-03: Búsqueda y filtrado de clientes por múltiples campos
- RF-04: Historial de interacciones por cliente
- RF-05: Importación/exportación de clientes

### 2.2 Gestión de Propiedades
- RF-06: CRUD completo de propiedades inmobiliarias
- RF-07: Galería de imágenes por propiedad
- RF-08: Campos específicos: precio, superficie, habitaciones, tipo, estado
- RF-09: Filtrado avanzado por características
- RF-10: Vinculación propiedad-cliente (interesados)

### 2.3 Agenda de Visitas
- RF-11: Programar visitas a propiedades con clientes
- RF-12: Calendario visual de visitas
- RF-13: Notificaciones de visitas próximas
- RF-14: Estados: programada, realizada, cancelada

### 2.4 Pipeline de Ventas
- RF-15: Vista Kanban de oportunidades
- RF-16: Etapas personalizables del pipeline
- RF-17: Drag & drop para mover entre etapas
- RF-18: Valor económico por oportunidad
- RF-19: Estadísticas de conversión por etapa

### 2.5 Automatizaciones
- RF-20: Definir reglas trigger → acciones
- RF-21: Triggers: nuevo cliente, nueva propiedad, cambio de etapa, nuevo formulario, contrato firmado, factura pagada, presupuesto aceptado
- RF-22: Acciones: enviar email, crear tarea, cambiar etapa, notificar, webhook
- RF-23: Activar/desactivar automatizaciones
- RF-24: Duplicar automatizaciones existentes
- RF-25: Log de ejecuciones con estado éxito/error
- RF-26: Plantillas rápidas para automatizaciones comunes

### 2.6 Contratos
- RF-27: Crear contratos desde plantillas
- RF-28: Firma digital integrada (canvas)
- RF-29: Estados: borrador, enviado, firmado, cancelado
- RF-30: Vinculación con cliente y propiedad

### 2.7 Presupuestos y Facturación
- RF-31: Generar presupuestos con líneas de detalle
- RF-32: Convertir presupuesto a factura
- RF-33: Facturación con cálculo automático de IVA
- RF-34: Estados de pago: pendiente, pagado, vencido
- RF-35: Vista de pagos y cobros

### 2.8 Conversaciones
- RF-36: Sistema de mensajería interno
- RF-37: Hilos de conversación entre usuarios
- RF-38: Notificación de mensajes nuevos
- RF-39: Adjuntar archivos en mensajes

### 2.9 Funnels y Formularios
- RF-40: Constructor de embudos de marketing
- RF-41: Formularios web personalizables
- RF-42: Campos dinámicos por formulario
- RF-43: Recepción y listado de envíos

### 2.10 Blog / Contenidos
- RF-44: Editor de artículos con HTML
- RF-45: Categorías y etiquetas
- RF-46: Estados: borrador, publicado
- RF-47: SEO básico (slug, meta description)

### 2.11 Documentos
- RF-48: Subida y gestión de documentos
- RF-49: Organización por carpetas/categorías
- RF-50: Vinculación con clientes y propiedades

### 2.12 Panel de Control (Dashboard)
- RF-51: Resumen de métricas clave
- RF-52: Gráficos de actividad reciente
- RF-53: Accesos directos a acciones frecuentes
- RF-54: Widget de tareas pendientes

### 2.13 Ajustes y Administración
- RF-55: Gestión de usuarios y roles
- RF-56: Permisos granulares por módulo
- RF-57: Configuración de marca blanca (whitelabel)
- RF-58: Plantillas de email configurables
- RF-59: Gestión de claves API
- RF-60: Integraciones con servicios externos
- RF-61: Backup de base de datos
- RF-62: Modo oscuro / claro con persistencia

## 3. Requisitos No Funcionales

### 3.1 Rendimiento
- RNF-01: Tiempo de carga de página < 2 segundos
- RNF-02: Soporte para al menos 10,000 registros por tabla sin degradación
- RNF-03: Compatible con hosting compartido (recursos limitados)

### 3.2 Seguridad
- RNF-04: Protección contra SQL Injection (prepared statements PDO)
- RNF-05: Protección contra XSS (htmlspecialchars en salidas)
- RNF-06: Protección CSRF con tokens por sesión
- RNF-07: Contraseñas hasheadas con bcrypt (password_hash)
- RNF-08: Validación de uploads (tipo MIME, extensión, tamaño)
- RNF-09: Sanitización de headers de email

### 3.3 Usabilidad
- RNF-10: Interfaz responsive (mobile-first con Bootstrap 5.3)
- RNF-11: Modo oscuro completo con toggle
- RNF-12: Personalización visual vía whitelabel
- RNF-13: Mensajes flash de confirmación/error
- RNF-14: Navegación consistente con sidebar y breadcrumbs

### 3.4 Compatibilidad
- RNF-15: Navegadores soportados: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- RNF-16: PHP 7.4 o superior
- RNF-17: MySQL 5.7+ o MariaDB 10.3+
- RNF-18: Sin dependencia de Composer o Node.js

### 3.5 Mantenibilidad
- RNF-19: Código organizado por módulos independientes
- RNF-20: Funciones auxiliares reutilizables en helpers.php
- RNF-21: Configuración centralizada en config/database.php
- RNF-22: Documentación completa del sistema

## 4. Stack Tecnológico

| Componente | Tecnología | Versión |
|-----------|------------|---------|
| Backend | PHP | 7.4+ |
| Base de datos | MySQL / MariaDB | 5.7+ / 10.3+ |
| Frontend CSS | Bootstrap | 5.3 |
| Iconos | Bootstrap Icons | 1.11+ |
| JavaScript | Vanilla JS | ES6+ |
| Fuentes | Google Fonts (Inter) | - |
| Tema oscuro | Bootstrap data-bs-theme | 5.3 |

## 5. Diagrama de Módulos

```
┌─────────────────────────────────────────────┐
│                 Dashboard                     │
├────────┬────────┬────────┬────────┬──────────┤
│Clientes│Propieda│Visitas │ Tareas │ Pipeline │
│        │  des   │        │        │ (Kanban) │
├────────┴────────┴────────┴────────┴──────────┤
│         Automatizaciones                      │
├────────┬────────┬────────┬───────────────────┤
│Contrat.│Presup. │Facturas│ Conversaciones    │
├────────┴────────┴────────┴───────────────────┤
│  Funnels  │  Formularios  │  Blog  │  Docs   │
├───────────┴───────────────┴────────┴─────────┤
│              Ajustes / Admin                  │
│  Whitelabel│Usuarios│Roles│API│Email│Backup   │
└──────────────────────────────────────────────┘
```

## 6. Restricciones

- No se utilizan frameworks PHP (Laravel, Symfony, etc.)
- No se requiere Composer para gestión de dependencias
- No se requiere Node.js ni herramientas de build frontend
- Debe funcionar en hosting compartido de Hostinger
- Archivos estáticos servidos directamente por Apache/LiteSpeed
- Sin requisito de acceso SSH para instalación
