# V1.6 - Galería de Imágenes para Propiedades

## 📸 Nuevas Características

### Galería de Imágenes Mejorada
- **Imagen Principal**: Visualización grande (400x altura responsive) de la propiedad
- **Miniaturas**: Grid responsive que muestra todas las imágenes subidas
- **Imagen Principal**: Marcador visual para la imagen principal (asterisco verde)
- **Interactividad**: 
  - Haz clic en miniatura para cambiar imagen principal
  - Estrella (★) para marcar como principal
  - Equis (✕) para eliminar imagen

### Carga de Imágenes Moderno
- **Drag & Drop**: Arrastra imágenes directamente al área de carga
- **Clic para Seleccionar**: O haz clic en el área para seleccionar archivos
- **Múltiples Imágenes**: Carga varios archivos a la vez
- **Validación**: Solo acepta formatos de imagen (JPEG, PNG, WebP)
- **Retroalimentación Visual**: Indicador de progreso durante carga

## 📁 Estructura de Archivos

```
V1.6-Galeria/
├── secciones/
│   └── ver_propiedad.php          [MODIFICADO] - Agregada galería + upload
├── api/
│   └── imagenes.php               [MODIFICADO] - API de gestión de imágenes
├── inc/
│   └── helpers.php                [MODIFICADO] - Funciones de imagen
├── css/
│   └── estilo.css                 [MODIFICADO] - Estilos para galería
├── uploads/
│   └── propiedades/               [NUEVO] - Almacén de imágenes
└── README_GALERIA.md              [NUEVO] - Este archivo
```

## 🗄️ Base de Datos

### Tabla: `imagenes_propiedades`
Se crea automáticamente con estructura:
```sql
CREATE TABLE imagenes_propiedades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    propiedad_id INT NOT NULL,
    nombre_archivo VARCHAR(255),
    nombre_original VARCHAR(255),
    ruta_archivo VARCHAR(255),
    es_principal TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (propiedad_id) REFERENCES propiedades(id)
)
```

## 🔌 API REST - `/api/imagenes.php`

### 1. Subir Imágenes
**Endpoint**: `POST /api/imagenes.php`
```json
{
    "action": "subir",
    "propiedad_id": 1
}
```
Con archivos en `FormData` con clave `imagenes[]`

**Respuesta Exitosa**:
```json
{
    "success": true,
    "ids": [1, 2, 3],
    "message": "3 imagen(es) subida(s)"
}
```

### 2. Obtener Imágenes
**Endpoint**: `GET /api/imagenes.php?action=obtener&propiedad_id=1`

**Respuesta**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "propiedad_id": 1,
            "nombre_original": "foto.jpg",
            "ruta_archivo": "/uploads/propiedades/img_1_xxx.jpg",
            "es_principal": 1,
            "created_at": "2025-02-19 13:47:00"
        }
    ]
}
```

### 3. Marcar Como Principal
**Endpoint**: `POST /api/imagenes.php`
```json
{
    "action": "marcar-principal",
    "id": 1
}
```

### 4. Eliminar Imagen
**Endpoint**: `POST /api/imagenes.php`
```json
{
    "action": "eliminar",
    "id": 1
}
```

## 🎨 Estilos CSS Nuevos

Clases añadidas a `estilo.css`:
- `.galeria_seccion` - Contenedor principal
- `.galeria_contenedor` - Área de imagen + miniaturas
- `.galeria_principal` - Imagen grande (400px height)
- `.galeria_miniaturas` - Grid responsive de miniaturas
- `.miniatura_item` - Cada miniatura (80x80px)
- `.miniatura_toolbar` - Botones al pasar mouse
- `.carga_imagenes` - Área de drop/upload
- `.area_drop` - Zona drag&drop
- `.progress_bar` - Indicador de progreso

## 🚀 Funciones Helper Nuevas

En `/inc/helpers.php`:

```php
// Asegurar que tabla imagenes existe
imagenes_asegurar_tabla(PDO $pdo): void

// Subir imagen a servidor
imagen_subir(PDO $pdo, int $propiedad_id, array $archivo): ?int

// Obtener todas las imágenes de propiedad
imagenes_obtener_propiedad(PDO $pdo, int $propiedad_id): array

// Obtener imagen principal
imagen_obtener_principal(PDO $pdo, int $propiedad_id): ?array

// Eliminar imagen
imagen_eliminar(PDO $pdo, int $id): bool

// Marcar como imagen principal
imagen_marcar_principal(PDO $pdo, int $imagen_id): bool
```

## 📱 Responsive Design

- **Desktop** (1024px+): Imagen principal 400px de alto
- **Tablet** (768-1024px): Imagen principal 300px de alto, miniaturas 70px
- **Mobile** (< 768px): Imagen principal 250px de alto, miniaturas 60px

## ✅ Testing

1. Ir a cualquier propiedad: `index.php?seccion=ver_propiedad&id=1`
2. En la sección superior verá:
   - Imagen principal (placeholder si no hay imágenes)
   - Grid de miniaturas (si hay imágenes)
3. Arrastra imágenes o haz clic para cargar
4. Haz clic en miniaturas para cambiar imagen principal
5. Usa ★ para marcar como principal
6. Usa ✕ para eliminar

## 🔒 Seguridad

- **Validación MIME**: Solo JPEG, PNG, WebP
- **Nombres Únicos**: `img_{propiedad_id}_{uniqid}.ext`
- **Rutas Relativas**: Almacenadas como `/uploads/propiedades/{archivo}`
- **Permisos DB**: Foreign key a propiedades
- **Session Required**: Requiere usuario autenticado

## 📝 Notas

- Las imágenes se almacenan en `/uploads/propiedades/`
- Se mantiene funcionalidad de notas/comentarios
- Se mantienen todos los campos de propiedad originales
- Layout responsivo compatible con V1.5

## 🐛 Troubleshooting

### Las imágenes no se guardan
1. Verifica permisos: `chmod -R 777 /uploads`
2. Revisa logs del servidor
3. Confirma que imagenes_propiedades tabla existe

### Drag & Drop no funciona
1. Verifica navegador soporta HTML5 (todos modernos)
2. Revisa consola del navegador (F12) para errores JavaScript
3. Intenta clic tradicional en lugar de drag

### Imágenes no se muestran
1. Revisa ruta en DB vs directorio real
2. Confirma permisos de lectura en `/uploads`
3. Valida formato de imagen (JPEG/PNG/WebP)

## 📦 Migración desde V1.5

V1.6 mantiene compatibilidad total con V1.5:
- Todos los campos de propiedad sin cambios
- Sistema de notas íntacto
- Solo se agregó nueva tabla `imagenes_propiedades`
- No hay cambios en DB para propiedades existentes
