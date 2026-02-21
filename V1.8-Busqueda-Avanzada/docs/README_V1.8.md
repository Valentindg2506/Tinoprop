# V1.8 - Busqueda avanzada con scraping

Objetivo: permitir que los usuarios consulten propiedades de Valencia obtenidas de Habitaclia mediante un scraping programable en Python y filtrarlas desde la app.

## Componentes nuevos
- scripts/scrape_habitaclia.py: scraper en Python (Habitaclia -> tabla `scraped_propiedades`).
- scripts/requirements.txt: dependencias para el scraper.
- secciones/busqueda-avanzada.php: UI de filtros y resultados cacheados.
- Tabla nueva: `scraped_propiedades` (DDL en database/tinoprop.sql).

## Como ejecutar el scraper
1. Instala deps: `pip install -r scripts/requirements.txt`.
2. Configura la conexion (variables de entorno opcionales):
   - TP_DB_HOST (default: localhost)
   - TP_DB_NAME (default: tinoprop)
   - TP_DB_USER (default: valentin)
   - TP_DB_PASS (default: 759234)
3. Ejecuta ejemplo basico:
   - `python3 scripts/scrape_habitaclia.py --pages 2`
   - Usa `--verbose` para ver logs y `--dry-run` para no escribir en BD.
4. Cron sugerido (cada 6h):
   - `0 */6 * * * cd /var/www/html/GitHub/Tinoprop/V1.8-Busqueda-Avanzada && /usr/bin/python3 scripts/scrape_habitaclia.py --pages 3 --delay 2`

Notas:
- El scraper usa User-Agent de navegador y agrega `-2.htm`, `-3.htm`, etc. para paginar (patron de Habitaclia). Ajusta `--base-url` si cambian las rutas.
- Se evita duplicar avisos via UNIQUE en `url` y hash `raw_hash`.

## Uso en la app
- Menu lateral -> "Búsqueda Avanzada".
- Filtros: texto libre, zona/barrio, operacion (venta/alquiler), rango de precio, min habitaciones, min banos, orden por precio/m2/recientes.
- Los resultados se leen de `scraped_propiedades` (ciudad = Valencia) y se limitan a 100 registros.
- Si la tabla esta vacia, se muestra un aviso para ejecutar el scraper.

## Despliegue
- Importa database/tinoprop.sql o agrega solo la tabla nueva si ya tienes datos.
- Asegura que el usuario de la BD tenga permisos de CREATE/INSERT/UPDATE sobre `scraped_propiedades`.
- Ejecuta el scraper al menos una vez antes de mostrar la seccion a usuarios finales.
