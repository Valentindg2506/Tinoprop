# 📅 Widget de Recordatorios en Dashboard

## Cambios Realizados

Se agregó un **panel de próximos recordatorios** al dashboard que muestra los recordatorios de los próximos 7 días.

### 1. Backend (dashboard.php)

Se agregó código para consultar la BD y obtener los próximos recordatorios:

```php
// Obtener próximos recordatorios (próximos 7 días)
$recordatorios_asegurar_tabla($pdo);
$usuario_id = (int) ($_SESSION['usuario']['id'] ?? 0);
$fecha_hoy = date('Y-m-d');
$fecha_fin = date('Y-m-d', strtotime('+7 days'));

$stmt = $pdo->prepare(
    'SELECT id, tipo, descripcion, fecha_recordatorio, hora_recordatorio, estado
     FROM recordatorios
     WHERE usuario_id = :usuario_id
     AND fecha_recordatorio >= :fecha_hoy
     AND fecha_recordatorio <= :fecha_fin
     AND estado != "cancelado"
     ORDER BY fecha_recordatorio ASC, hora_recordatorio ASC
     LIMIT 5'
);
```

**Variable disponible**: `$proximos_recordatorios` (array de recordatorios)

### 2. Frontend (HTML)

Panel nuevo en la grid del dashboard:

```html
<section class="panel_panel" data-dashboard-card="panel-recordatorios" draggable="false">
    <div class="panel_header">
        <h3>📅 Próximos recordatorios</h3>
        <span class="panel_hint">Próximos 7 días</span>
    </div>
    
    <!-- Lista de recordatorios o mensaje vacío -->
    <ul class="lista_recordatorios_dash">
        <!-- Items de recordatorio -->
    </ul>
    
    <!-- Botón para ver todos -->
    <a href="?seccion=recordatorios" class="btn_ver_todos">Ver todos →</a>
</section>
```

**Características**:
- ✅ Muestra máximo 5 recordatorios próximos
- ✅ Filtrado por fecha (hoy + 7 días)
- ✅ Excluye recordatorios cancelados
- ✅ Ordena por fecha y hora
- ✅ Enlace directo a cada recordatorio
- ✅ Botón para ver calendario completo

### 3. Estilos CSS (estilo.css)

Se agregaron ~120 líneas de CSS para:

- `.lista_recordatorios_dash` - Contenedor de lista
- `.rec_item` - Item individual
- `.rec_estado_*` - Estados visuales (pendiente, completado)
- `.rec_fecha_hora` - Fecha y hora
- `.rec_contenido` - Tipo y descripción
- `.btn_ir` - Botón enlace
- `.btn_ver_todos` - Botón ver todos
- `.sin_datos` - Estado vacío

## Apariencia

### Vista con Recordatorios

```
┌─ 📅 Próximos recordatorios ─────────┐
│ Próximos 7 días                     │
├─────────────────────────────────────┤
│ 22/02  Llamada               → │
│ 14:30  Llamar cliente XYZ          │
│                                     │
│ 24/02  Visita               → │
│        Visita a propiedad...       │
│                                     │
│ [Ver todos →]                      │
└─────────────────────────────────────┘
```

### Vista Vacía

```
┌─ 📅 Próximos recordatorios ─────────┐
│ Próximos 7 días                     │
├─────────────────────────────────────┤
│                                     │
│ No hay recordatorios próximos       │
│                                     │
│ [Ver calendario]                    │
└─────────────────────────────────────┘
```

## Funcionalidades

- **Últimos 7 días**: Muestra recordatorios de hoy hasta 7 días después
- **5 máximo**: Solo muestra los 5 más próximos (evita saturación)
- **Estados visuales**: Colores diferentes por estado
  - 🟦 Pendiente (naranja/azul)
  - 🟩 Completado (verde, opaco)
- **Links interactivos**: Clic en el recordatorio → Va a la fecha en el calendario
- **Responsive**: Se adapta a todos los tamaños

## Integración

El panel es parte de la **grid del dashboard** y es:
- ✅ Draggable (se puede reordenar como otros paneles)
- ✅ Personalizable (se guarda orden del usuario)
- ✅ Responsive (se adapta a mobile)
- ✅ Sincronizado con BD (datos en tiempo real)

## Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `secciones/dashboard.php` | +30 líneas (query + HTML) |
| `css/estilo.css` | +120 líneas (estilos) |

## Testing

- [ ] Ver panel en dashboard
- [ ] Crear recordatorio
- [ ] Verificar que aparece en panel
- [ ] Clic en recordatorio
- [ ] Navegar a sección de recordatorios con fecha correcta
- [ ] Completar recordatorio
- [ ] Verificar cambio de color
- [ ] Cancelar recordatorio
- [ ] Verificar que desaparece

## Mejoras Futuras

- Notificación badge con cantidad de recordatorios
- Integración con alertas (avisos)
- Colores por tipo de recordatorio
- Búsqueda/filtrado en panel
