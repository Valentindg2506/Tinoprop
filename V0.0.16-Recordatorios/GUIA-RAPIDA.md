# 🚀 V1.5-Recordatorios: Guía Rápida de Inicio

## ¿Qué Puedo Hacer Ahora?

### 1️⃣ **Ver Recordatorios**
```
URL: ?seccion=recordatorios
Acceso: Menú Lateral → Dashboard → 📅 Recordatorios
```

### 2️⃣ **Crear Recordatorio**
```
Paso 1: Seleccionar fecha en calendario (resaltará azul)
Paso 2: Clic en "+ Nuevo"
Paso 3: Llenar formulario
        - Tipo: Llamada / Visita / Reunión / Nota importante / Seguimiento / Otro
        - Hora: Opcional (ej: 14:30)
        - Cliente/Prospecto: Opcional (número ID)
        - Descripción: Requerido (¿Qué recordar?)
        - Estado: Pendiente / Completado / Cancelado
Paso 4: Clic "Guardar"
Resul.: Tarjeta aparece en panel derecho
```

### 3️⃣ **Editar Recordatorio**
```
Paso 1: En cualquier tarjeta, clic "✏️ Editar"
Paso 2: Modificar los datos que desees
Paso 3: Clic "Guardar"
Resul.: Tarjeta actualizada al instante
```

### 4️⃣ **Cambiar Estado**
```
Ejemplo: Marcar como "Completado"
Paso 1: Clic "✏️ Editar" en tarjeta
Paso 2: Cambiar Estado a "Completado"
Paso 3: Guardar
Resul.: Tarjeta cambia a verde ✅
```

### 5️⃣ **Eliminar Recordatorio**
```
Paso 1: Clic "🗑️ Eliminar" en tarjeta
Paso 2: Confirmar en popup
Resul.: Tarjeta desaparece
```

### 6️⃣ **Navegar Meses**
```
Flechas del calendario:
← Mes anterior
→ Mes siguiente

Header muestra: "Mes Año" (ej: Enero 2024)
```

### 7️⃣ **Ver Recordatorios por Día**
```
Clic en cualquier día del calendario
↓
Panel derecho muestra recordatorios de ese día
Calendario resalta el día en azul
```

---

## 👀 Visual de Interfaz

```
┌─────────────────────────────────────────────────────────────────┐
│  📅 Recordatorios                                               │
├──────────────────────┬──────────────────────────────────────────┤
│   CALENDARIO         │      RECORDATORIOS DEL DÍA              │
│ ← Enero 2024 →       │   Seleccionado: 25/01/2024              │
│                      │                                          │
│  Do Lu Ma Mi Ju Vi Sa │   + Nuevo  [Botón Azul]                 │
│              1  2  3  │                                          │
│  4  5  6  7  8  9 10  │   ┌────────────────────────┐            │
│ 11 12 13 14 15 16 17  │   │ Llamada    14:30       │            │
│ 18 19 20[21]22 23 24  │   │ Pendiente              │            │
│ 25 26 27 28 29 30 31  │   │ Llamar a cliente XYZ   │            │
│                      │   │ ✏️ Editar  🗑️ Eliminar │            │
│ Seleccionado:        │   └────────────────────────┘            │
│ **25/01/2024**       │                                          │
└──────────────────────┴──────────────────────────────────────────┘

Colores:
🟢 Verde = Hoy
🔵 Azul = Seleccionado
🔴 Rojo = Día con recordatorios (número)
```

---

## 🎨 Tarjeta de Recordatorio

```
┌─ Llamada        14:30        ✅ Completado ─┐
│                                             │
│  Llamar a cliente XYZ para confirmar       │
│  visita a propiedad de calle Principal 42  │
│                                             │
│  Prospecto ID: 123                         │
│                                             │
│  [✏️ Editar]  [🗑️ Eliminar]                │
└─────────────────────────────────────────────┘

Estados por Color:
- Pendiente: Naranja/Amarillo
- Completado: Verde
- Cancelado: Rojo/Gris
```

---

## ⌨️ Formulario

```
Tipo de recordatorio:
[▼ Llamada --------]

┌─────────────────────┬─────────────────────┐
│ Hora (opcional):    │ Cliente/Prospecto:  │
│ [⏰ 14:30 ------]   │ [123 --------]      │
└─────────────────────┴─────────────────────┘

Descripción:
[▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬]
[¿Qué necesitas recordar?]
[▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬]

Estado:
[▼ Pendiente ----]

[💾 Guardar]  [❌ Cancelar]
```

---

## 📊 Tipos de Recordatorio

| Icono | Tipo | Uso |
|-------|------|-----|
| 📞 | Llamada | Contactar cliente por teléfono |
| 🏠 | Visita | Visitar propiedad o cliente |
| 🤝 | Reunión | Junta o videollamada |
| 📌 | Nota importante | Información crítica a recordar |
| 🔄 | Seguimiento | Follow-up con cliente |
| 📝 | Otro | Algo que no encaja en las otras |

---

## 🔍 Badges en Calendario

```
Si hay 3 recordatorios en el día 15:

┌────────┐
│   15   │
│   🔴3  │
└────────┘

El número rojo indica:
- Presencia de recordatorios ese día
- Cantidad total de recordatorios
```

---

## ❓ Preguntas Frecuentes

**P: ¿Los recordatorios se guardan?**
R: Sí, se guardan en base de datos. Persisten aunque cierres la sesión.

**P: ¿Puedo crear recordatorios sin hora?**
R: Sí, la hora es opcional. La descripción es lo único requerido.

**P: ¿Veo los recordatorios de otros usuarios?**
R: No, solo ves los tuyos. Los datos están separados por usuario_id.

**P: ¿Puedo cambiar la fecha de un recordatorio?**
R: No desde editar. Deberías crear uno nuevo en la fecha correcta.
*(Mejora futura)*

**P: ¿Qué pasa al eliminar?**
R: Se borra del calendario y de la base de datos definitivamente.

---

## 🛠️ Datos Técnicos

- **Base de datos**: tabla `recordatorios`
- **API**: `api/recordatorios.php`
- **Frontend**: `secciones/recordatorios.php`
- **Estilos**: CSS integrado en `estilo.css`
- **Persistencia**: 100% en BD (PDO MySQL)
- **JavaScript**: Vanilla (sin dependencias)

---

## 🚀 Tabla de Referencia API

| Método | Acción | URL |
|--------|--------|-----|
| POST | Crear | `api/recordatorios.php` |
| GET | Obtener | `api/recordatorios.php?action=obtener&id=1` |
| POST | Actualizar | `api/recordatorios.php` |
| POST | Eliminar | `api/recordatorios.php` |

```json
// Ejemplo crear
POST api/recordatorios.php
{
  "action": "crear",
  "tipo": "Llamada",
  "descripcion": "Contactar cliente",
  "fecha": "2024-01-25",
  "hora": "14:30",
  "prospecto_id": 123,
  "estado": "pendiente"
}

// Respuesta
{
  "success": true,
  "id": 42,
  "message": "Recordatorio creado"
}
```

---

## ✨ Características Destacadas

✅ **Calendario interactivo** - Navega meses sin recargar página  
✅ **CRUD completo** - Crear, leer, actualizar, eliminar  
✅ **Estados visuales** - Verde (hecho), naranja (pendiente), rojo (cancelado)  
✅ **Responsive** - Funciona en desktop, tablet, móvil  
✅ **Seguro** - Datos separados por usuario  
✅ **Rápido** - AJAX sin refresco de página  
✅ **User-friendly** - Interfaz intuitiva y clara  

---

**¡Listo! Comienza a crear tus recordatorios ahora. 🎉**
