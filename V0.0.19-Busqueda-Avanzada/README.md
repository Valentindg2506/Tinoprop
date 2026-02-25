# TinoProp V1.8 - Búsqueda avanzada + scraping

Incluye la nueva sección de búsqueda avanzada con propiedades cacheadas desde Habitaclia (Valencia) vía Python.

## Novedades principales
- Sección `busqueda-avanzada` con filtros (texto, zona, operación, rango de precio, m2, habitaciones y banos).
- Scraper Python `scripts/scrape_habitaclia.py` + `scripts/requirements.txt` para poblar la tabla `scraped_propiedades`.
- DDL y seeds en `database/tinoprop.sql` para la nueva tabla.
- Documentación específica en `docs/README_V1.8.md`.

## Pasos rápidos
1) Instala deps Python: `pip install -r scripts/requirements.txt`.
2) Ejecuta el scraper: `python3 scripts/scrape_habitaclia.py --pages 2` (usa `--verbose` para debug).
3) Accede al menú lateral → **Búsqueda Avanzada** y filtra resultados.

## Estructura
- `api/`, `css/`, `js/`, `inc/`, `secciones/`, `database/`: código y assets de la app.
- `docs/`: guías (GUIA-RAPIDA, IMPLEMENTACION, README_GALERIA, README_V1.8, etc.).
- `storage/uploads/propiedades/`: imágenes de propiedades (crear si no existe).

## Notas de despliegue
1) Permisos de escritura en `storage/uploads/propiedades/`.
2) Base de datos: importar `database/tinoprop.sql` o al menos crear `scraped_propiedades`.
3) Programa el scraper (cron) para mantener el cache fresco.
