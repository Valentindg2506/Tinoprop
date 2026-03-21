# V1.5 - Recordatorios 📅

## Descripción
Esta versión agrega un sistema completo de recordatorios para agentes (vendedor/comprador) que incluye:
- **Calendario interactivo** con navegación mes a mes
- **Visualización de recordatorios** por día seleccionado  
- **Creación/Edición/Eliminación** de recordatorios con CRUD completo
- **Tipos de recordatorios** (Llamada, Visita, Reunión, Nota importante, Seguimiento, Otro)
- **Estados** (Pendiente, Completado, Cancelado)
- **Asignación opcional** a prospectos/clientes
- **Horarios** para cada recordatorio
- **Persistencia** en base de datos por usuario

## Cambios Realizados

### 1. Base de Datos
Se creó la tabla `recordatorios` con auto-creación en el primer acceso:
```sql
CREATE TABLE recordatorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50),
    descripcion TEXT,
    fecha_recordatorio DATE,
    hora_recordatorio TIME,
    prospecto_id INT,
    estado VARCHAR(20) DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_fecha (fecha_recordatorio),
    INDEX idx_estado (estado)
)
```

### 2. Helpers (inc/helpers.php)
Se agregaron funciones CRUD para recordatorios:
- `recordatorios_asegurar_tabla()` - Auto-crear tabla si no existe
- `recordatorio_crear()` - Crear un nuevo recordatorio
- `recordatorios_por_fecha()` - Obtener recordatorios de una fecha
- `recordatorios_por_mes()` - Obtener recordatorios del mes
- `recordatorio_obtener()` - Obtener un recordatorio por ID
- `recordatorio_actualizar()` - Actualizar recordatorio
- `recordatorio_eliminar()` - Eliminar recordatorio

### 3. Sección de Recordatorios (secciones/recordatorios.php)
Interfaz completa con:
- **Calendario**: Mes actual con navegación ← / →
  - Días con recordatorios destacados con número rojo
  - Día actual marcado en verde
  - Día seleccionado en azul
- **Panel de Recordatorios**: Muestra recordatorios del día seleccionado
  - Botón "+ Nuevo" para agregar recordatorios
  - Formulario en modal/desplegable para crear/editar
  - Tarjetas de recordatorios con acciones editar/eliminar
  - Filtrado por fecha automático

### 4. API (api/recordatorios.php)
Endpoint JSON que soporta acciones:
- `crear` - POST nuevos recordatorios
- `obtener` - GET un recordatorio por ID
- `actualizar` - POST actualizar existente
- `eliminar` - POST eliminar recordatorio

Acepta JSON body y parámetros GET/POST indistintamente.

### 5. Menú (index.php)
Se actualizó el menú lateral para incluir:
- Link "📅 Recordatorios" en Dashboard con favoriteable

### 6. Estilos (css/estilo.css)
Se agregaron aproximadamente 400 líneas de CSS con:
- Calendario responsivo con grid
- Tarjetas de recordatorios con estados visibles
- Formulario de creación/edición con validación visual
- Estilos para estados (pendiente/completado/cancelado)
- Responsive design (mobile-friendly en pantallas < 1024px)

## Características

### Interfaz
- ☐ **Calendario interactivo** con navegación mensual
- ☐ **Badges de cantidad** en días con recordatorios  
- ☐ **Resaltado visual** del día actual (verde) y día seleccionado (azul)
- ☐ **Formulario dinámico** para crear/editar recordatorios
- ☐ **Tarjetas informativas** con estado visual

### Funcionalidad
- ☐ Crear recordatorios con tipo, descripción, fecha, hora, prospecto_id
- ☐ Editar recordatorios existentes
- ☐ Eliminar recordatorios
- ☐ Cambiar estado (Pendiente → Completado/Cancelado)
- ☐ Filtrar por fecha automáticamente
- ☐ Ver resumen de recordatorios por mes

### Datos
- ☐ Persistencia en BD por usuario (usuario_id)
- ☐ Índices en Usuario, Fecha y Estado para performance
- ☐ Timestamps de creación y actualización

## Uso

1. **Acceder a Recordatorios**:
   - Menú lateral → Dashboard → 📅 Recordatorios
   - O directo: `?seccion=recordatorios`

2. **Crear Recordatorio**:
   - Seleccionar fecha en calendario (se resalta azul)
   - Clic en "+ Nuevo"
   - Llenar formulario (tipo, descripción obligatorios)
   - Clic "Guardar"

3. **Editar Recordatorio**:
   - Clic en "✏️ Editar" en tarjeta
   - Modificar datos
   - Clic "Guardar"

4. **Eliminar Recordatorio**:
   - Clic en "🗑️ Eliminar" en tarjeta
   - Confirmar eliminación

5. **Navegar Meses**:
   - Flechas ← / → para cambiar mes/año

## Notas Técnicas

- Todos los recordatorios están vinculados a `usuario_id` de sesión
- No hay conflicto con versiones anteriores (sin cambios en otras secciones)
- El calendario usa aritmética de fechas sin librerías externas
- JavaScript vanilla sin dependencias
- AJAX para todas las operaciones de DB sin reload de página

## Mejoras Futuras
- Notificaciones push/email para recordatorios próximos
- Búsqueda/filtrado de recordatorios por tipo
- Repetición de recordatorios (diario, semanal, etc)
- Compartir recordatorios entre usuarios del equipo
- Historial/logs de cambios en recordatorios
