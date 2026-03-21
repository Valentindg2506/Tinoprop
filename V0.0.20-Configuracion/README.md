# TinoProp V1.9 - Configuración y preferencias

Versión centrada en habilitar la sección de **Configuración** (menú lateral) con perfil, seguridad y preferencias del dashboard. Se mantiene la búsqueda avanzada con scraping de Habitaclia incluida en V1.8.

## Novedades principales
- Nueva sección `configuracion` accesible desde el menú lateral.
- Edición de perfil: actualizar nombre y email con validación de unicidad.
- Seguridad: cambio de password con verificación de la actual y mínimo 8 caracteres.
- Preferencias por usuario: equipo, operación y periodo por defecto para el dashboard (persisten en BD).
- El dashboard usa esas preferencias como valores iniciales si no hay filtros en la URL.
- Se conservan la búsqueda avanzada y el scraper Python de Habitaclia.

## Pasos rápidos
1) Base de datos: importa `database/tinoprop.sql` (incluye scraping + tabla de preferencias si no existe).
2) Accede a **Configuración** y define perfil, password y preferencias de dashboard.
3) Entra al dashboard: si no pasas filtros en la URL, se aplican tus preferencias guardadas.

## Estructura
- `api/`, `css/`, `js/`, `inc/`, `secciones/`, `database/`: código y assets de la app.
- `docs/`: guías (GUIA-RAPIDA, IMPLEMENTACION, README_GALERIA, README_V1.8, README_V1.9).
- `storage/uploads/propiedades/`: imágenes de propiedades (crear si no existe).

## Notas de despliegue
1) Permisos de escritura en `storage/uploads/propiedades/`.
2) Programa el scraper (`scripts/scrape_habitaclia.py`) si quieres refrescar datos cacheados.
3) Las preferencias y el perfil se guardan en MySQL, no se requiere storage adicional.
