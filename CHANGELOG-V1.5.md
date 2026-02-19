# 📝 CHANGELOG - Tinoprop Versiones

## V1.5-Recordatorios (NUEVA) ✨
**Lanzamiento**: 2024  
**Cambios principales**: Sistema completo de recordatorios con calendario

### Agregar
- 📅 Sección de recordatorios con calendario interactivo
  - Navegación mensual (← y →)
  - Visualización de días con recordatorios (badges rojos)
  - Resaltado de día actual (verde) y seleccionado (azul)
  - Grid de 7×6 para visualización completa del mes

- 📝 CRUD de recordatorios
  - Crear: formulario dinámico con tipo, hora, descripción, estado, prospecto_id
  - Leer: listado por fecha con tarjetas visuales
  - Actualizar: edición inline con cambio de estado
  - Eliminar: con confirmación

- 🗄️ Tabla de base de datos `recordatorios`
  - Auto-creación en primer acceso
  - Índices en usuario_id, fecha_recordatorio, estado
  - Campos de auditoría (created_at, updated_at)

- 🔗 API JSON `/api/recordatorios.php`
  - Acciones: crear, obtener, actualizar, eliminar
  - Acepta JSON body y parámetros GET/POST
  - Validación de datos en servidor

- 🎨 Estilos responsive
  - Calendario responsive
  - Tarjetas con estados visuales
  - Formulario dinámico
  - Diseño mobile-first

### Archivos Nuevos
- `secciones/recordatorios.php` - Interfaz principal
- `api/recordatorios.php` - Endpoint API
- `inc/helpers.php` - Funciones CRUD (+ 220 líneas)
- `README-V1.5.md` - Documentación
- `TESTING-V1.5.md` - Guía de pruebas
- `IMPLEMENTACION.md` - Resumen técnico

### Cambios Existentes
- `index.php` - +1 línea: link a recordatorios en menú
- `css/estilo.css` - +400 líneas: estilos recordatorios
- `inc/helpers.php` - +220 líneas: funciones recordatorio_*

### No Cambios
- ✅ Dashboard mantiene funciones de personalización
- ✅ Kanban mantiene drag-and-drop
- ✅ Todas las secciones previas intactas

---

## V1.4-Mejoras-orden (Previa)
**Cambios**: Persistencia de orden en BD + reset + edición visual

---

## V1.3-Personalizar-inicio (Previa)  
**Cambios**: Reordenamiento de dashboard con drag-drop

---

## V1.2-Drag-and-Drop (Previa)
**Cambios**: Drag-drop en kanban con persistencia

---

## V1.1 (Base)
**Cambios**: Sistema CRM base con clientes, prospectos, propiedades
