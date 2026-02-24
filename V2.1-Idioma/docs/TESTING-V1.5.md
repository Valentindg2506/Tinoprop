# V1.5 Recordatorios - Guía de Prueba 🚀

## Checklist de Funcionalidades

### ✅ Instalación y Base de Datos
- [ ] Base de datos `recordatorios` creada automáticamente en primer acceso
- [ ] Tabla tiene campos: id, usuario_id, tipo, descripcion, fecha_recordatorio, hora_recordatorio, prospecto_id, estado
- [ ] Índices en usuario_id, fecha_recordatorio y estado

### ✅ Interfaz del Calendario
- [ ] Calendario visible al acceder a "?seccion=recordatorios"
- [ ] Botones ← y → navegan entre meses
- [ ] Mes y año se muestran correctamente
- [ ] Días vencidos deshabilitados o diferenciados
- [ ] Día actual marcado en verde (es_hoy)
- [ ] Día seleccionado resaltado en azul
- [ ] Selección de día actualiza la lista de recordatorios sin recargar

### ✅ Visualización de Recordatorios
- [ ] Panel derecho muestra "No hay recordatorios para esta fecha" si no hay
- [ ] Tarjetas muestran:
  - Tipo de recordatorio (badge)
  - Hora si existe
  - Estado (Pendiente/Completado/Cancelado)
  - Descripción
  - ID de prospecto (si existe)
- [ ] Colores diferenciados por estado:
  - Pendiente: naranja/amarillo
  - Completado: verde
  - Cancelado: rojo

### ✅ Crear Recordatorio
- [ ] Botón "+ Nuevo" visible en header del panel
- [ ] Clic en "+ Nuevo" muestra formulario
- [ ] Formulario completo con campos:
  - Tipo (dropdown): Llamada, Visita, Reunión, Nota importante, Seguimiento, Otro
  - Hora (time input, opcional)
  - Cliente/Prospecto (número, opcional)
  - Descripción (textarea, requerido)
  - Estado (dropdown)
- [ ] Botones "Guardar" y "Cancelar" funcionales
- [ ] Guardar crea recordatorio en BD y recarga lista

### ✅ Editar Recordatorio
- [ ] Clic en "✏️ Editar" abre formulario con datos precargados
- [ ] Modificar cualquier campo
- [ ] Clic "Guardar" actualiza en BD
- [ ] Lista se recarga mostrando cambios

### ✅ Eliminar Recordatorio
- [ ] Clic en "🗑️ Eliminar" muestra confirmación
- [ ] Confirmar borra de la BD
- [ ] Lista se recarga sin recordatorio

### ✅ API (recordatorios.php)
- [ ] Endpoint `api/recordatorios.php` accesible
- [ ] Acepta JSON body y parámetros GET/POST
- [ ] Acción `crear` retorna success + id
- [ ] Acción `obtener` retorna datos del recordatorio
- [ ] Acción `actualizar` retorna success
- [ ] Acción `eliminar` retorna success
- [ ] Errores retornan JSON con mensaje descriptivo

### ✅ Persistencia
- [ ] Recordatorios creados se guardan en BD
- [ ] Recargar página mantiene los recordatorios
- [ ] Cada usuario solo ve sus recordatorios
- [ ] No interfiere con otras versiones

### ✅ Responsividad
- [ ] En desktop: calendario izquierda, panel recordatorios derecha
- [ ] En tablet (< 1024px): calendario arriba, panel abajo
- [ ] Elementos se adaptan al ancho disponible

## Casos de Prueba Manuales

### Test 1: Crear recordatorio simple
1. Navegar a `?seccion=recordatorios`
2. Clic en cualquier día del calendario
3. Clic en "+ Nuevo"
4. Tipo: "Llamada"
5. Descripción: "Llamar a cliente XYZ"
6. Clic "Guardar"
7. ✅ Debe aparecer tarjeta roja "Llamada" en panel

### Test 2: Crear con todos los campos
1. Seleccionar día
2. "+ Nuevo"
3. Tipo: "Visita"
4. Hora: "14:30"
5. Cliente: "123"
6. Descripción: "Visita a propiedad de calle..."
7. Estado: "Pendiente"
8. Guardar
9. ✅ Tarjeta muestra todo: tipo, hora, descripción, prospecto ID

### Test 3: Editar recordatorio
1. Clic "✏️ Editar" en cualquier tarjeta
2. Cambiar Tipo a "Reunión"
3. Cambiar Descripción
4. Guardar
5. ✅ Tarjeta actualizada sin refrescar página

### Test 4: Cambiar estado
1. Editar un recordatorio
2. Cambiar Estado a "Completado"
3. Guardar
4. ✅ Tarjeta cambia a color verde

### Test 5: Eliminar
1. Clic "🗑️ Eliminar"
2. Confirmar en alert
3. ✅ Tarjeta desaparece inmediatamente

### Test 6: Navegación calendario
1. Ver mes actual
2. Clic derecha → próximo mes
3. Clic izquierda ← mes anterior
4. ✅ Navegación suave, días correctos

### Test 7: Múltiples recordatorios
1. Crear 3 recordatorios para el mismo día
2. Ver en calendario: número "3" en rojo
3. Cambiar de día
4. Cambiar nuevamente al primer día
5. ✅ Aparecen los 3 recordatorios

### Test 8: Badge en calendario
1. Crear recordatorio para día 15
2. Ver calendario
3. ✅ Día 15 tiene bolita roja con número de recordatorios

### Test 9: Persistencia
1. Crear recordatorio
2. Refrescar página F5
3. ✅ Recordatorio sigue ahí

### Test 10: Filtro por usuario
1. Ser usuario A, crear recordatorio
2. Cambiar sesión a usuario B (si es posible)
3. ✅ User B solo ve sus propios recordatorios

## Errores Esperados a Reportar

Si encuentras cualquiera de estos, **NO es normal**:
- [ ] Recordatorios de otros usuarios visibles
- [ ] Errores en consola (F12 → Console)
- [ ] Almacenamiento no persiste tras refrescar
- [ ] Formulario se envía a página blanca en lugar de actualizar lista
- [ ] Calendario desalineado o días incorrectos
- [ ] Botones "+ Nuevo", editar o eliminar sin respuesta

## Notas de Testing

- Probar en navegadores modernos: Chrome, Firefox, Safari, Edge
- Probar en móvil (viewport 375px)
- Probar con recordatorios sin hora (campo opcional)
- Probar sin prospecto_id (campo opcional)
- Crear múltiples recordatorios en distinto meses y navegarlos

## Contacto para Issues
Reportar cualquier problema a través de los canales habituales.
