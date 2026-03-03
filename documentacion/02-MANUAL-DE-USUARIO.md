# TinoProp — Manual de Usuario

**Versión:** 1.0.0 — Producción  
**Última actualización:** 3 de marzo de 2026

---

## 1. Acceso al Sistema

### 1.1 Iniciar sesión

1. Abre el navegador y accede a la URL de tu instancia de TinoProp
2. Introduce tu **email** y **contraseña**
3. Haz clic en **Iniciar Sesión**

> **Nota:** Tras 5 intentos fallidos en 15 minutos, la cuenta se bloquea temporalmente durante 15 minutos.

### 1.2 Primer acceso (contraseña temporal)

Si tu administrador acaba de crear tu cuenta:

1. Inicia sesión con la contraseña temporal que te proporcionaron
2. El sistema te obligará a **cambiar la contraseña** inmediatamente
3. La nueva contraseña debe cumplir:
   - Mínimo 8 caracteres
   - Al menos 1 letra mayúscula
   - Al menos 1 símbolo especial (`!@#$%^&*` etc.)
4. Escribe la nueva contraseña dos veces y confirma

### 1.3 Aceptación de términos

Tras cambiar la contraseña (o cuando se actualicen los términos legales), se te pedirá aceptar los **Términos de Servicio** y la **Política de Privacidad**. Lee el documento, marca la casilla de aceptación y pulsa **Aceptar**.

### 1.4 Cerrar sesión

En el menú lateral, sección **Sistema**, haz clic en **🚪 Cerrar Sesión**.

---

## 2. Interfaz Principal

### 2.1 Menú lateral (Sidebar)

El menú lateral izquierdo es tu herramienta de navegación principal. Se organiza en secciones:

| Sección | Contenido |
|---------|-----------|
| **Inicio** | Dashboard, Recordatorios, Matching, Documentación |
| **Clientes** | Clientes y Prospectos (Venta / Compra) |
| **Inmuebles** | Propiedades, Alquileres, Búsqueda Avanzada, Proceso, Visitas, Ofertas, Post-Venta |
| **Sistema** | Configuración, Legal, Cerrar Sesión |

- **Colapsar menú:** pulsa el botón ◀ en la cabecera del sidebar para minimizarlo
- **Favoritos:** marca la estrella ☆ junto a cualquier sección para añadirla a **Favoritos** (acceso rápido)
- **Buscador global:** en la parte superior del sidebar, escribe para buscar clientes, propiedades o prospectos al instante

### 2.2 Notificaciones

El icono 🔔 en el sidebar muestra alertas inteligentes:
- Propiedades sin imágenes
- Prospectos sin contactar
- Recordatorios pendientes
- Otros avisos relevantes

### 2.3 Preferencias de interfaz

En **Configuración** puedes ajustar:

| Preferencia | Opciones |
|------------|----------|
| **Tema** | Sistema (sigue tu SO), Claro, Oscuro |
| **Densidad** | Media, Cómoda, Compacta |
| **Idioma** | Español, English |

---

## 3. Dashboard

El panel principal muestra un resumen de tu actividad:

### 3.1 KPIs (indicadores clave)

Tarjetas con métricas principales:
- **Clientes activos** — total de clientes bajo tu gestión
- **Prospectos** — pipeline comercial activo
- **Propiedades en venta** — inventario de venta
- **Propiedades en alquiler** — inventario de alquiler
- **Visitas hoy** — visitas programadas para hoy
- **Reservadas** — propiedades en proceso de cierre

Cada KPI muestra el **% de cambio** respecto al periodo anterior (↑ verde = sube, ↓ rojo = baja).

> 💡 Puedes **arrastrar y reorganizar** las tarjetas KPI a tu gusto. El orden se guarda automáticamente.

### 3.2 Filtros del Dashboard

Tres filtros en la parte superior:

| Filtro | Opciones |
|--------|----------|
| **Equipo** | Todos, Vendedor, Comprador |
| **Periodo** | Mes actual, Últimos 90 días, Año |
| **Operación** | Venta y Alquiler, Solo Venta, Solo Alquiler |

### 3.3 Paneles informativos

- **Embudo comercial** — prospectos por etapa (Nuevo → Contacto → Cerrado / Descartado)
- **Últimos avisos** — notas de seguimiento urgentes
- **Actividad reciente** — últimas notas y acciones
- **Propiedades destacadas** — las más visitadas
- **Próximos recordatorios** — agenda de los próximos 7 días
- **Visitas de hoy** — detalle de visitas programadas

> Si eres **Supervisor** o superior, también verás el panel de **Rendimiento por agente**.

---

## 4. Gestión de Clientes

### 4.1 Listado de clientes

Accede desde el menú lateral → **Clientes Venta** o **Clientes Compra**.

- **Vista tabla** o **vista tarjetas** (cambia con el toggle superior)
- Filtros por nombre, estado, operación
- Exportar a CSV

### 4.2 Crear un cliente

1. Pulsa el botón **+ Nuevo Cliente**
2. Rellena los campos obligatorios: nombre, apellido, teléfono, email, operación
3. Campos opcionales: dirección, género, fecha de nacimiento, presupuesto, zona de interés, comentarios
4. Pulsa **Guardar**

### 4.3 Ficha del cliente

Haz clic en el nombre de un cliente para ver su **ficha completa**:

- **Datos personales** — información de contacto y perfil
- **Notas** — añade notas de seguimiento o avisos
- **Etiquetas** — clasifica con tags personalizados de colores
- **Timeline** — historial cronológico de interacciones
- **Propiedades vinculadas** — propiedades asignadas al cliente

### 4.4 Prospectos

Los prospectos son clientes potenciales en tu **pipeline comercial**. Estados posibles:
- **Nuevo** — acaba de entrar
- **Contactado** — ya has hablado con él
- **No contesta** — pendiente de respuesta
- **Realizado** — convertido a cliente
- **Descartado** — no interesado

---

## 5. Gestión de Propiedades

### 5.1 Listado de propiedades

Accede desde **Propiedades Venta** o **Propiedades Compra**.

- Vista tabla / tarjetas
- Filtros por tipo, operación, estado, rango de precio
- Cada tarjeta muestra imagen principal, precio, ubicación y estado

### 5.2 Crear una propiedad

1. Pulsa **+ Nueva Propiedad**
2. Campos obligatorios: título, tipo (Piso, Casa, Local...), ubicación, precio, operación (venta/alquiler)
3. Campos opcionales: referencia, descripción, superficie, habitaciones, baños, características
4. Pulsa **Guardar**

### 5.3 Ficha de propiedad

- **Galería de imágenes** — sube fotos, marca una como principal
- **Datos técnicos** — características completas
- **Notas** — seguimiento interno
- **Etiquetas** — clasificación por tags
- **Timeline** — historial de acciones
- **Visitas programadas** — visitas vinculadas

### 5.4 Estados de una propiedad

| Estado | Significado |
|--------|------------|
| **Disponible** | En el mercado, activa |
| **Reservado** | Oferta aceptada, pendiente de cierre |
| **Vendido** | Operación cerrada (venta) |
| **Alquilado** | Operación cerrada (alquiler) |
| **Retirado** | Temporalmente fuera del mercado |

---

## 6. Búsqueda Avanzada

Sección con filtros combinados para encontrar propiedades:

- Tipo de inmueble
- Rango de precio (mínimo — máximo)
- Ubicación / zona
- Operación (venta / alquiler)
- Estado
- Número de habitaciones / baños
- Superficie

Los resultados se muestran en tiempo real conforme ajustas los filtros.

---

## 7. Proceso de Inmuebles (Kanban)

Un **tablero kanban** para seguir el ciclo de vida de cada propiedad:

### 7.1 Proceso Vendedor — Etapas:
1. **Captación** — contacto inicial con propietario
2. **Valoración** — tasación y análisis de mercado
3. **Documentación** — recopilación de papeles
4. **Publicación** — anuncio en portales
5. **Negociación** — gestión de ofertas
6. **Cierre** — firma y entrega

### 7.2 Proceso Comprador — Etapas:
1. **Búsqueda** — identificar necesidades
2. **Preselección** — filtrar opciones
3. **Visitas** — agendar y realizar visitas
4. **Negociación** — presentar ofertas
5. **Cierre** — firma y entrega

> Arrastra las tarjetas entre columnas para cambiar de etapa.

---

## 8. Visitas

### 8.1 Programar una visita

1. Accede a **Visitas** (Venta o Compra)
2. Pulsa **+ Nueva Visita**
3. Selecciona: propiedad, cliente, fecha, hora
4. Añade observaciones si lo deseas
5. Pulsa **Guardar**

### 8.2 Estados de visita

| Estado | Significado |
|--------|------------|
| **Pendiente** | Programada, sin realizar |
| **Realizada** | Ya se llevó a cabo |
| **Cancelada** | Se anuló |
| **Reprogramada** | Se ha cambiado la fecha |

---

## 9. Ofertas

Gestión de ofertas económicas sobre propiedades:

1. Accede a **Ofertas** (disponible en el lado vendedor)
2. Pulsa **+ Nueva Oferta**
3. Vincula: propiedad, cliente, importe ofertado
4. Estados posibles:
   - **Pendiente** — en espera de respuesta
   - **Aceptada** — oferta aceptada
   - **Rechazada** — no procede
   - **Contraoferta** — nueva propuesta

---

## 10. Post-Venta

Pipeline kanban para el seguimiento posterior a la venta/alquiler:

| Etapa | Descripción |
|-------|------------|
| 📄 **Pendiente Docs** | Recopilar documentación necesaria |
| ✍️ **Firma Contrato** | Preparar y firmar el contrato |
| 🏛️ **Trámites** | Gestiones administrativas y notariales |
| 🔑 **Entrega Llaves** | Entrega física del inmueble |
| ✅ **Completado** | Operación finalizada |

> Arrastra las tarjetas entre etapas para actualizar el progreso.  
> Solo **Supervisores** y superiores pueden eliminar registros de post-venta.

---

## 11. Matching

El sistema de **matching automático** cruza compradores con propiedades disponibles por:

- **Zona de interés** del comprador ↔ ubicación de la propiedad
- **Presupuesto** del comprador ↔ precio de la propiedad

Muestra una lista agrupada por comprador con las propiedades que coinciden.

---

## 12. Recordatorios y Agenda

### 12.1 Crear un recordatorio

1. Accede a **Recordatorios**
2. Pulsa en un día del calendario o en **+ Nuevo**
3. Rellena: tipo, descripción, fecha, hora (opcional)
4. Pulsa **Guardar**

### 12.2 Tipos de recordatorio

- Llamada
- Visita
- Reunión
- Seguimiento
- Documentación
- Otro

### 12.3 Vista calendario

El calendario mensual muestra los recordatorios con colores según tipo. Haz clic en un día para ver el detalle.

---

## 13. Documentación y Archivos

### 13.1 Subir documentos

1. Accede a **Documentación**
2. Selecciona el cliente o propiedad al que vincular el archivo
3. Arrastra o selecciona el archivo
4. El archivo se almacena de forma segura vinculado a la entidad

### 13.2 Generar PDFs

1. Selecciona una **plantilla** (contrato, ficha, informe...)
2. Elige el cliente y/o propiedad
3. El sistema genera el PDF con los datos rellenados automáticamente
4. Descarga directa o almacenamiento en la ficha

---

## 14. Importar CSV

Importación masiva de datos desde archivos CSV:

1. Accede a **Importar CSV** (requiere nivel Supervisor+)
2. Selecciona el tipo: **Clientes** o **Propiedades**
3. Para clientes, selecciona el tipo: **Vendedor** o **Comprador**
4. Sube el archivo CSV
5. El sistema procesa los registros y muestra un resumen con errores (si los hay)

---

## 15. Configuración

### 15.1 Datos personales

- Cambiar nombre y email
- Visualizar tu rol y nivel

### 15.2 Cambiar contraseña

- Introduce la contraseña actual
- Escribe la nueva (mínimo 8 chars, 1 mayúscula, 1 símbolo)
- Confirma la nueva contraseña

### 15.3 Preferencias de interfaz

- **Tema:** Sistema / Claro / Oscuro
- **Densidad:** Media / Cómoda / Compacta
- **Idioma:** Español / English
- **Restablecer:** vuelve a los valores por defecto

---

## 16. Sección Legal

Acceso a los documentos legales:
- **Términos de Servicio** — condiciones de uso del CRM
- **Política de Privacidad** — tratamiento de datos personales (RGPD)
- **Información de sesión** — datos de la sesión activa

---

## 17. Etiquetas (Tags)

Sistema transversal de clasificación:

1. En la ficha de cualquier cliente o propiedad, busca la sección **Etiquetas**
2. Selecciona una etiqueta existente o **crea una nueva** (con nombre y color)
3. Las etiquetas son visibles en los listados y ayudan a filtrar

---

## 18. Atajos y Consejos

| Acción | Cómo |
|--------|------|
| Buscar rápido | Usa el buscador del sidebar (mín. 2 caracteres) |
| Acceso rápido | Marca secciones como favoritas con ☆ |
| Navegación | Los breadcrumbs muestran la ruta actual |
| Vista rápida | Alterna entre tabla y tarjetas con el toggle |
| Reordenar KPIs | Arrastra las tarjetas del dashboard |
| Modo oscuro | Configuración → Tema → Oscuro |
