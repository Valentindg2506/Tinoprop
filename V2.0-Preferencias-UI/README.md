# TinoProp V2.0 - Preferencias de UI

Versión que expande Configuración con tema claro/oscuro y densidad de tablas, aplicadas por usuario. Mantiene lo de V1.9 (perfil, password, preferencias de dashboard) y la búsqueda avanzada de V1.8.

## Novedades principales
- Tema de interfaz guardado por usuario (`Sistema`, `Claro`, `Oscuro`).
- Densidad de tablas configurable (`Media`, `Comoda`, `Compacta`).
- Reset de preferencias ahora borra tema y densidad además de los filtros de dashboard.
- El body aplica clases dinámicas según las preferencias guardadas.

## Pasos rápidos
1) BD: importa `database/tinoprop.sql` si aún no lo hiciste.
2) Entra a **Configuración** → elige tema y densidad, guarda.
3) Navega: se aplican automáticamente; dashboard sigue tomando preferencias de filtros si no hay parámetros en la URL.

## Estructura
- `api/`, `css/`, `js/`, `inc/`, `secciones/`, `database/`: código y assets de la app.
- `docs/`: guías (GUIA-RAPIDA, IMPLEMENTACION, README_GALERIA, README_V1.8, README_V1.9, README_V2.0).
- `storage/uploads/propiedades/`: imágenes de propiedades (crear si no existe).

## Notas de despliegue
1) Permisos de escritura en `storage/uploads/propiedades/`.
2) Si usas el tema oscuro, la UI ya viene con overrides básicos para tarjetas/tablas.
3) Preferencias se guardan por usuario en MySQL, sin dependencias nuevas.
