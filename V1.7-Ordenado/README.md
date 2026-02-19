# TinoProp V1.7 (Ordenado)

Estructura reorganizada con galería de imágenes y almacenamiento centralizado.

## Novedades principales
- Carpeta `docs/` con toda la documentación histórica.
- README único con enlaces a guías clave.
- Uploads movidos a `storage/uploads/propiedades/`.
- Código base tomado de V1.6-Galeria.

## Estructura
- `api/`, `css/`, `js/`, `inc/`, `secciones/`, `database/`: código y assets de la app.
- `docs/`: guías (GUIA-RAPIDA, IMPLEMENTACION, README_GALERIA, etc.).
- `storage/uploads/propiedades/`: imágenes de propiedades (crear si no existe).

## Enlaces rápidos
- docs/GUIA-RAPIDA.md
- docs/IMPLEMENTACION.md
- docs/README_GALERIA.md
- docs/TESTING-V1.5.md

## Notas de despliegue
1) Asegura permisos de escritura en `storage/uploads/propiedades/`.
2) Base de datos: usa los mismos esquemas de V1.6 (incluida `imagenes_propiedades`).
