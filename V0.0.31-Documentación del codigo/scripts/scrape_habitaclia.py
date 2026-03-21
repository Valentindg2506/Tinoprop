#!/usr/bin/env python3
"""
==========================================================================
Archivo: scripts/scrape_habitaclia.py — Scraper de Habitaclia para TinoProp
==========================================================================

¿QUÉ HACE ESTE ARCHIVO?
Este script de Python "scrapea" (extrae datos automáticamente) anuncios de
viviendas del portal inmobiliario Habitaclia (habitaclia.com) y los guarda
en la base de datos de TinoProp.

¿PARA QUÉ SIRVE?
Alimenta la búsqueda avanzada del CRM: los agentes inmobiliarios pueden ver
propiedades publicadas en Habitaclia directamente desde TinoProp, sin tener
que visitar la web manualmente.

¿CÓMO FUNCIONA PASO A PASO?
1. Conecta a la base de datos MySQL de TinoProp.
2. Crea la tabla 'scraped_propiedades' si no existe.
3. Para cada página del listado de Habitaclia:
   a. Descarga el HTML de la página.
   b. Extrae datos de dos formas: JSON-LD (datos estructurados) y tarjetas HTML.
   c. Combina duplicados y normaliza los datos.
   d. Para cada propiedad, intenta obtener más datos de su página de detalle.
   e. Guarda/actualiza el registro en la BD (UPSERT por URL única).
4. Espera entre páginas para no sobrecargar el servidor de Habitaclia.

¿QUÉ ES WEB SCRAPING?
Es una técnica para extraer datos de páginas web automáticamente. El script
"lee" el HTML de la web como lo haría un navegador, pero en vez de mostrar
la página, extrae la información útil (título, precio, metros, etc.).

USO DESDE LA TERMINAL:
  python3 scrape_habitaclia.py                          # Scraping básico (2 páginas)
  python3 scrape_habitaclia.py --pages 5 --verbose      # 5 páginas con logs detallados
  python3 scrape_habitaclia.py --dry-run                # Solo simula, no escribe en BD

PENSADO PARA EJECUTARSE CON CRON:
  Cron es un programador de tareas de Linux que permite ejecutar scripts
  automáticamente a intervalos regulares (ej: cada noche a las 2:00 AM).

REQUISITOS:
  pip install -r scripts/requirements.txt
  (Instala: requests, beautifulsoup4, mysql-connector-python)

Índice de funciones:
- get_db_conn: abre conexión MySQL con variables de entorno TP_DB_*.
- ensure_table: crea/actualiza tabla scraped_propiedades.
- build_page_url: construye URL paginada a partir de la URL base.
- fetch_soup: descarga HTML y devuelve BeautifulSoup.
- parse_price / parse_int: normalizan números desde texto libre.
- parse_from_ldjson: extrae inmuebles desde scripts JSON-LD.
- parse_from_cards: extrae inmuebles desde tarjetas HTML visibles.
- dedupe_listings: unifica duplicados y combina campos complementarios.
- normalize_listing: homogeneiza un registro al formato final de persistencia.
- fetch_images: intenta obtener imágenes de detalle del anuncio.
- normalize_image_url / pick_first_from_srcset / normalize_images: normalización de URLs de imágenes.
- extract_meta: infiere habitaciones/baños/metros desde textos del anuncio.
- guess_operacion / guess_tipo: deduce operación y tipo de inmueble.
- _dump_debug_html: guarda HTML de depuración en /tmp.
- ensure_absolute_url: convierte rutas relativas a URL absoluta.
- upsert_listing: inserta/actualiza en scraped_propiedades por URL única.
- scrape: orquesta el scraping completo por páginas.
- parse_args: define argumentos CLI.
- main: punto de entrada del script.
"""
# ── IMPORTACIONES ──
# Cada 'import' trae funcionalidades externas que necesitamos:
import argparse         # Para leer argumentos de la línea de comandos (--pages, --verbose, etc.)
import hashlib          # Para generar hashes SHA256 (resúmenes únicos de datos)
import json             # Para leer/escribir datos en formato JSON
import logging          # Para escribir mensajes de log (información, advertencias, errores)
import os               # Para interactuar con el sistema operativo (variables de entorno, archivos)
import re               # Para expresiones regulares (búsqueda de patrones en texto)
import time             # Para hacer pausas entre peticiones (time.sleep)
from datetime import datetime, timezone  # Para fechas y zonas horarias
from typing import Dict, List, Optional  # Para anotaciones de tipo (documentación del código)
from urllib.parse import urljoin         # Para combinar URLs relativas con absolutas

import mysql.connector   # Conector para base de datos MySQL
import requests          # Librería para hacer peticiones HTTP (descargar páginas web)
from bs4 import BeautifulSoup  # "Beautiful Soup": parsea HTML y permite navegar por sus elementos

# URL por defecto para scrapear: listado de viviendas en Valencia
DEFAULT_BASE_URL = "https://www.habitaclia.com/viviendas-en-valencia.htm"

# User-Agent: nos "disfrazamos" como un navegador Chrome normal.
# Sin esto, muchos sitios web bloquean la petición porque detectan
# que viene de un script automatizado en lugar de un usuario real.
USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"


def get_db_conn():
    """
    Crea la conexión a la base de datos MySQL de TinoProp.

    Usa variables de entorno (TP_DB_HOST, TP_DB_NAME, etc.) para obtener
    las credenciales. Si no están definidas, usa valores por defecto.

    ¿Por qué variables de entorno?
    Es una buena práctica de seguridad: las contraseñas no se escriben
    directamente en el código fuente (que podría subirse a GitHub).
    Se definen en el servidor de producción de forma segura.
    """
    return mysql.connector.connect(
        host=os.getenv("TP_DB_HOST", "localhost"),  # Servidor de BD (por defecto: local)
        database=os.getenv("TP_DB_NAME", "tinoprop"), # Nombre de la base de datos
        user=os.getenv("TP_DB_USER", "valentin"),     # Usuario de MySQL
        password=os.getenv("TP_DB_PASS", "759234"),   # Contraseña de MySQL
        charset="utf8mb4",   # Juego de caracteres que soporta emojis y caracteres especiales
        use_unicode=True,    # Activamos soporte Unicode
    )


def ensure_table(conn) -> None:
    """
    Crea la tabla 'scraped_propiedades' si no existe, y añade columnas nuevas
    si faltan (para actualizaciones del esquema).

    Esta tabla almacena todas las propiedades extraídas de Habitaclia.
    Cada propiedad tiene una URL única que sirve como clave para evitar
    duplicados (si ya existe, se actualiza en lugar de insertar otra vez).
    """
    # DDL = Data Definition Language: sentencias SQL que definen la estructura
    # de las tablas (CREATE TABLE, ALTER TABLE, etc.)
    ddl = """
    CREATE TABLE IF NOT EXISTS scraped_propiedades (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fuente VARCHAR(50) NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        tipo VARCHAR(100) DEFAULT NULL,
        operacion VARCHAR(50) DEFAULT NULL,
        precio DECIMAL(12,2) DEFAULT NULL,
        moneda VARCHAR(10) DEFAULT 'EUR',
        ubicacion VARCHAR(200) DEFAULT NULL,
        zona VARCHAR(120) DEFAULT NULL,
        ciudad VARCHAR(80) DEFAULT NULL,
        provincia VARCHAR(80) DEFAULT NULL,
        direccion VARCHAR(255) DEFAULT NULL,
        habitaciones TINYINT DEFAULT NULL,
        banos TINYINT DEFAULT NULL,
        metros INT DEFAULT NULL,
        descripcion TEXT DEFAULT NULL,
        imagen_url VARCHAR(500) DEFAULT NULL,
        imagenes_json LONGTEXT DEFAULT NULL,
        ascensor TINYINT DEFAULT NULL,
        url VARCHAR(400) NOT NULL,
        raw_hash CHAR(64) NOT NULL,
        scrape_run VARCHAR(80) DEFAULT NULL,
        scraped_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_url (url),
        KEY idx_ciudad (ciudad),
        KEY idx_precio (precio),
        KEY idx_zona (zona),
        KEY idx_operacion (operacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    """
    with conn.cursor() as cur:
        # Creamos la tabla si no existe (CREATE TABLE IF NOT EXISTS)
        cur.execute(ddl)

        # Intentamos añadir columnas nuevas que se agregaron en versiones posteriores.
        # Si la columna ya existe, MySQL lanza error 1060 que ignoramos.
        # Esto permite actualizar el esquema sin romper instalaciones existentes.
        try:
            cur.execute("ALTER TABLE scraped_propiedades ADD COLUMN imagen_url VARCHAR(500) DEFAULT NULL")
        except mysql.connector.Error as exc:
            # Column may already exist; ignore duplicate-column errors
            if exc.errno not in (1060, 1061):
                raise
        try:
            cur.execute("ALTER TABLE scraped_propiedades ADD COLUMN imagenes_json LONGTEXT DEFAULT NULL")
        except mysql.connector.Error as exc:
            if exc.errno not in (1060, 1061):
                raise
        try:
            cur.execute("ALTER TABLE scraped_propiedades ADD COLUMN ascensor TINYINT DEFAULT NULL")
        except mysql.connector.Error as exc:
            if exc.errno not in (1060, 1061):
                raise
    conn.commit()


def build_page_url(base_url: str, page: int) -> str:
    """
    Construye la URL de una página específica del listado.

    Habitaclia usa dos formas de paginación según el tipo de URL:
    - URLs que terminan en .htm: la página se añade como sufijo (ej: viviendas-2.htm)
    - Otras URLs: se añade como parámetro GET (ej: ?page=2)
    """
    # La primera página no necesita modificación
    if page <= 1:
        return base_url

    if base_url.endswith(".htm"):
        base, ext = base_url.rsplit(".", 1)
        return f"{base}-{page}.{ext}"

    joiner = "&" if "?" in base_url else "?"
    return f"{base_url}{joiner}page={page}"


def fetch_soup(url: str, timeout: int = 20) -> Optional[BeautifulSoup]:
    """
    Descarga el HTML de una URL y lo convierte en un objeto BeautifulSoup.

    BeautifulSoup es una librería que "parsea" (analiza) HTML y permite
    navegar por sus elementos como si fuera un árbol. Por ejemplo:
    soup.find('div', class_='precio') busca un <div> con clase "precio".

    Enviamos cabeceras que imitan un navegador real para evitar que
    el servidor de Habitaclia nos bloquee como bot.

    Retorna None si la descarga falla.
    """
    # Cabeceras HTTP que imitan las de un navegador Chrome real
    headers = {
        "User-Agent": USER_AGENT,
        "Accept-Language": "es-ES,es;q=0.9",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
        "Referer": "https://www.google.com/",  # Simulamos que venimos de Google
    }
    # requests.get() descarga la página web, como haría un navegador
    resp = requests.get(url, headers=headers, timeout=timeout)

    # Si el servidor no responde con 200 (OK), algo falló
    if resp.status_code != 200:
        logging.warning("GET %s devolvio %s", url, resp.status_code)
        _dump_debug_html(resp.text, url, suffix="status")  # Guardamos el HTML para depuración
        return None

    # Convertimos el HTML crudo en un objeto BeautifulSoup navegable
    soup = BeautifulSoup(resp.text, "html.parser")

    # Diagnóstico: si no encontramos contenedores de listado esperados,
    # puede que la web haya cambiado su estructura o nos esté bloqueando.
    # Guardamos el HTML para inspección manual posterior.
    if not soup.find("article") and not soup.find("div", class_=re.compile("list-item", re.I)):
        _dump_debug_html(resp.text, url, suffix="nolisting")
    return soup


def parse_price(text: str) -> Optional[float]:
    """
    Extrae un precio numérico desde texto libre.

    Ejemplos de texto que puede recibir:
    - "125.000 €"  → 125000.0
    - "1.200,50€"  → 1200.50
    - "Consultar"  → None (no hay precio)

    El sistema de puntos/comas en España es al revés que en EEUU:
    España: 1.000,50 = mil con cincuenta céntimos
    EEUU:   1,000.50 = lo mismo
    """
    if not text:
        return None
    clean = text.replace(".", "").replace("€", "").replace(",", ".")
    match = re.findall(r"\d+\.?\d*", clean)
    if not match:
        return None
    try:
        return float(match[0])
    except ValueError:
        return None


def parse_int(text: str) -> Optional[int]:
    """
    Extrae el primer número entero encontrado en un texto.

    Ejemplos:
    - "3 habitaciones" → 3
    - "120 m2"         → 120
    - "No informado"   → None
    """
    match = re.search(r"(\d+)", text or "")
    if not match:
        return None
    try:
        return int(match.group(1))
    except ValueError:
        return None


def parse_from_ldjson(soup: BeautifulSoup) -> List[Dict]:
    """
    Extrae propiedades desde datos estructurados JSON-LD del HTML.

    ¿Qué es JSON-LD?
    Es un formato que las webs insertan dentro de etiquetas <script> para
    que buscadores como Google entiendan el contenido de la página.
    Contiene datos limpios y estructurados (título, precio, dirección, etc.)
    que son más fiables que los extraídos del HTML visual.

    Esta es la FUENTE PREFERIDA de datos porque es más limpia y completa.
    """
    listings: List[Dict] = []
    # Buscamos todas las etiquetas <script type="application/ld+json"> del HTML
    for script in soup.find_all("script", {"type": "application/ld+json"}):
        try:
            data = json.loads(script.string or "")
        except Exception:
            continue

        # Formato ItemList: las webs inmobiliarias suelen usar este tipo de
        # JSON-LD para listados, donde cada elemento es un inmueble.
        if isinstance(data, dict) and data.get("@type") == "ItemList":
            for element in data.get("itemListElement", []):
                # Cada element puede contener un "item" con los datos de la propiedad
                item = element.get("item", {}) if isinstance(element, dict) else {}
                if not item:
                    continue
                listing_url = ensure_absolute_url(item.get("url"))
                image_field = item.get("image")
                images = normalize_images(image_field, listing_url)
                image_url = images[0] if images else None
                if not images and image_url:
                    images = [image_url]

                listings.append(
                    {
                        "titulo": item.get("name"),
                        "descripcion": item.get("description"),
                        "url": listing_url,
                        "precio": parse_price(str(item.get("offers", {}).get("price", ""))),
                        "moneda": (item.get("offers", {}).get("priceCurrency") or "EUR"),
                        "ubicacion": (item.get("address", {}).get("streetAddress") if isinstance(item.get("address"), dict) else None),
                        "ciudad": (item.get("address", {}).get("addressLocality") if isinstance(item.get("address"), dict) else None),
                        "provincia": (item.get("address", {}).get("addressRegion") if isinstance(item.get("address"), dict) else None),
                        "habitaciones": parse_int(str(item.get("numberOfRooms"))),
                        "banos": parse_int(str(item.get("numberOfBathroomsTotal"))),
                        "metros": parse_int(str(item.get("floorSize", {}).get("value"))) if isinstance(item.get("floorSize"), dict) else None,
                        "tipo": item.get("@type"),
                        "operacion": None,
                        "zona": None,
                        "imagen_url": image_url,
                        "imagenes": images,
                    }
                )
    return listings


def parse_from_cards(soup: BeautifulSoup) -> List[Dict]:
    """
    Extrae propiedades directamente desde las tarjetas HTML del listado.

    Esta es la FUENTE DE RESPALDO: cuando no hay datos JSON-LD o estos
    están incompletos, parseamos directamente el HTML visual de la página.

    El HTML es menos fiable porque las webs cambian su diseño frecuentemente,
    pero nos permite obtener datos aunque el JSON-LD no esté disponible.

    Probamos varios selectores CSS porque Habitaclia ha cambiado su HTML
    varias veces y queremos ser compatibles con múltiples versiones.
    """
    listings: List[Dict] = []
    # Probamos múltiples selectores CSS porque la web puede usar diferentes
    # clases/etiquetas según la versión o el dispositivo.
    selectors = [
        "div.list-item",             # Selector clásico de Habitaclia
        "article",                   # Etiqueta semántica HTML5
        "div[class*=\"list-item\"]",  # Cualquier div con "list-item" en la clase
        "li[class*=\"list-item\"]",   # Lo mismo pero como elemento de lista
        "div[class*=\"ListItem\"]",   # Versión con mayúsculas (React/Next.js)
    ]
    for selector in selectors:
        for card in soup.select(selector):
            link = card.select_one("a")
            title_el = card.select_one(".list-item-title") or card.select_one("h3")
            price_el = card.select_one(".list-item-price") or card.select_one(".price")
            desc_el = card.select_one(".description") or card.select_one("p")
            img_el = card.select_one("img") or card.select_one("picture img") or card.select_one("source")
            if not link or not title_el:
                continue
            href = ensure_absolute_url(link.get("href"))
            if not href or "habitaclia.com" not in href:
                continue
            img_candidate = None
            if img_el:
                img_candidate = img_el.get("src") or img_el.get("data-src") or pick_first_from_srcset(img_el.get("srcset"))
            if not img_candidate:
                style_bg = img_el.get("style") if img_el else None
                if style_bg and "url(" in style_bg:
                    m = re.search(r"url\(['\"]?(.*?)['\"]?\)", style_bg)
                    if m:
                        img_candidate = m.group(1)
            img_url = normalize_image_url(img_candidate, href)
            images = [img_url] if img_url else []
            # Extrae metadatos de m2/hab/baños desde texto visible de tarjeta.
            details_text = " ".join([
                (card.get_text(" ", strip=True) or ""),
            ])
            meta = extract_meta(details_text)
            operacion_guess = guess_operacion(href, title_el.get_text(strip=True))
            tipo_guess = guess_tipo(title_el.get_text(strip=True))
            listings.append(
                {
                    "titulo": title_el.get_text(strip=True),
                    "descripcion": (desc_el.get_text(strip=True) if desc_el else None),
                    "url": href if href.startswith("http") else f"https:{href}" if href.startswith("//") else href,
                    "precio": parse_price(price_el.get_text(strip=True) if price_el else ""),
                    "moneda": "EUR",
                    "ubicacion": None,
                    "ciudad": None,
                    "provincia": None,
                    "habitaciones": meta.get("habitaciones"),
                    "banos": meta.get("banos"),
                    "metros": meta.get("metros"),
                    "tipo": tipo_guess,
                    "operacion": operacion_guess,
                    "zona": None,
                    "imagen_url": img_url,
                    "imagenes": images,
                }
            )
    return listings


def dedupe_listings(listings: List[Dict]) -> List[Dict]:
    """
    Una y combina propiedades duplicadas por URL.

    ¿Por qué hay duplicados?
    Una misma propiedad puede aparecer tanto en los datos JSON-LD como
    en las tarjetas HTML. Esta función los combina inteligentemente:
    - Si un campo está vacío en una fuente pero no en la otra, se queda con el que tiene datos.
    - Para texto, prefiere el más largo (probablemente más informativo).
    - Para números, descarta valores absurdos (ej: 0 habitaciones, 50000 m2).
    - Combina las listas de imágenes sin repetir URLs.
    """
    # Función interna para determinar si un valor está "vacío" o es genérico
    def is_empty(value) -> bool:
        if value is None:
            return True
        if isinstance(value, str):
            txt = value.strip().lower()
            return txt in ("", "sin titulo", "tipo no informado", "operacion no informada", "no informado")
        return False

    def merge_images(base: Dict, extra: Dict) -> None:
        """
        Combina listas de imágenes de dos registros sin repetir URLs.
        Mantiene el orden original para que la portada sea consistente.
        """
        imgs = []
        for source in (
            base.get("imagenes") or [],
            extra.get("imagenes") or [],
            [base.get("imagen_url")] if base.get("imagen_url") else [],
            [extra.get("imagen_url")] if extra.get("imagen_url") else [],
        ):
            for img in source:
                if img and img not in imgs:
                    imgs.append(img)
        base["imagenes"] = imgs
        if imgs:
            base["imagen_url"] = imgs[0]

    def merge_two(base: Dict, extra: Dict) -> Dict:
        """Combina dos registros de la misma propiedad, priorizando datos más completos."""
        merged = dict(base)

        # Campos de texto: quedarse con el no-vacio y más informativo
        for field in ["titulo", "tipo", "operacion", "ubicacion", "zona", "ciudad", "provincia", "direccion", "descripcion", "moneda"]:
            current = merged.get(field)
            incoming = extra.get(field)
            if is_empty(current) and not is_empty(incoming):
                merged[field] = incoming
            elif field in ("titulo", "descripcion") and not is_empty(incoming) and isinstance(incoming, str):
                if is_empty(current) or len(incoming.strip()) > len(str(current).strip()):
                    merged[field] = incoming

        # Numéricos: preferir no nulos y corregir outliers con rangos plausibles.
        for field in ["precio", "habitaciones", "banos", "metros"]:
            current = merged.get(field)
            incoming = extra.get(field)
            if current is None and incoming is not None:
                merged[field] = incoming
            elif field == "metros" and incoming is not None and current is not None:
                # Si el actual es claramente irreal y el nuevo parece válido
                if (not isinstance(current, int) or current < 15 or current > 1500) and isinstance(incoming, int) and 15 <= incoming <= 1500:
                    merged[field] = incoming
            elif field == "habitaciones" and incoming is not None and current is not None:
                if (not isinstance(current, int) or current < 0 or current > 10) and isinstance(incoming, int) and 0 <= incoming <= 10:
                    merged[field] = incoming
            elif field == "banos" and incoming is not None and current is not None:
                if (not isinstance(current, int) or current < 0 or current > 8) and isinstance(incoming, int) and 0 <= incoming <= 8:
                    merged[field] = incoming

        if merged.get("ascensor") is None and extra.get("ascensor") is not None:
            merged["ascensor"] = extra.get("ascensor")

        merge_images(merged, extra)
        return merged

    merged_by_url: Dict[str, Dict] = {}
    order: List[str] = []
    for item in listings:
        key = item.get("url")
        if not key:
            continue
        if key not in merged_by_url:
            merged_by_url[key] = dict(item)
            order.append(key)
        else:
            merged_by_url[key] = merge_two(merged_by_url[key], item)

    return [merged_by_url[url] for url in order]


def normalize_listing(raw: Dict, run_tag: str) -> Dict:
    """
    Estandariza un registro al esquema final de la base de datos.

    Toma los datos "crudos" extraídos del HTML y los transforma al formato
    exacto que espera la tabla scraped_propiedades. También calcula un
    hash SHA256 que sirve para detectar si los datos han cambiado
    entre una ejecución del scraper y la siguiente.
    """
    titulo = (raw.get("titulo") or "").strip()
    url = (raw.get("url") or "").strip()
    base_hash = f"{url}-{raw.get('precio')}-{titulo}".encode("utf-8", errors="ignore")
    return {
        "fuente": "habitaclia",
        "titulo": titulo or "Sin titulo",
        "tipo": raw.get("tipo"),
        "operacion": raw.get("operacion"),
        "precio": raw.get("precio"),
        "moneda": raw.get("moneda") or "EUR",
        "ubicacion": raw.get("ubicacion"),
        "zona": raw.get("zona"),
        "ciudad": raw.get("ciudad") or "Valencia",
        "provincia": raw.get("provincia") or "Valencia",
        "direccion": raw.get("direccion"),
        "habitaciones": raw.get("habitaciones"),
        "banos": raw.get("banos"),
        "metros": raw.get("metros"),
        "descripcion": raw.get("descripcion"),
        "imagen_url": raw.get("imagen_url"),
        "imagenes": raw.get("imagenes") or [],
        "ascensor": raw.get("ascensor"),
        "url": url,
        "raw_hash": hashlib.sha256(base_hash).hexdigest(),
        "scrape_run": run_tag,
    }


def fetch_images(url: str, max_items: int = 8) -> List[str]:
    """
    Descarga la página de detalle de una propiedad y extrae URLs de imágenes.

    Las páginas de listado suelen mostrar solo 1 imagen por propiedad.
    La página de detalle contiene la galería completa, así que visitamos
    cada propiedad individualmente para obtener más fotos.

    Prioridad de fuentes:
    1. Meta tags sociales (og:image, twitter:image) → Imagen principal de alta calidad
    2. JSON-LD del detalle → Imágenes estructuradas
    3. Etiquetas <img> del DOM → Último recurso
    """
    if not url:
        return []
    images: List[str] = []
    try:
        soup = fetch_soup(url, timeout=25)
        if not soup:
            return []

        # Prioridad 1: meta tags sociales (og/twitter).
        metas = [
            soup.find("meta", property="og:image"),
            soup.find("meta", property="og:image:secure_url"),
            soup.find("meta", attrs={"name": "twitter:image"}),
        ]
        for tag in metas:
            if tag and tag.get("content"):
                images.append(normalize_image_url(tag.get("content"), url))

        # Prioridad 2: JSON-LD del detalle.
        for script in soup.find_all("script", {"type": "application/ld+json"}):
            try:
                data = json.loads(script.string or "")
            except Exception:
                continue
            imgs = normalize_images(data.get("image"), url)
            images.extend(imgs)

        # Prioridad 3: imágenes del DOM (img/source/srcset).
        for img in soup.select("img[src], img[data-src], picture source[srcset]"):
            candidate = img.get("src") or img.get("data-src") or pick_first_from_srcset(img.get("srcset"))
            images.append(normalize_image_url(candidate, url))

    except Exception as exc:
        logging.debug("No se pudieron obtener imagenes para %s: %s", url, exc)

    # Deduplica preservando orden para mantener una portada consistente.
    seen = set()
    final = []
    for im in images:
        if not im or im in seen:
            continue
        seen.add(im)
        final.append(im)
        if len(final) >= max_items:
            break
    return final


def normalize_image_url(image_url: Optional[str], base_url: Optional[str]) -> Optional[str]:
    """
    Normaliza una URL de imagen para que sea absoluta y completa.

    Las URLs pueden venir en varios formatos:
    - Absoluta: https://example.com/img.jpg (ya está bien)
    - Protocol-relative: //example.com/img.jpg (le falta https:)
    - Relativa: /img/foto.jpg (necesita el dominio base)
    """
    if not image_url:
        return None
    image_url = image_url.strip()
    if image_url.startswith("//"):
        return f"https:{image_url}"
    if image_url.startswith("http://") or image_url.startswith("https://"):
        return image_url
    if base_url:
        return urljoin(base_url, image_url)
    return image_url


def pick_first_from_srcset(srcset: Optional[str]) -> Optional[str]:
    """
    Extrae la primera URL de un atributo srcset de HTML.

    srcset es un atributo de <img> que lista varias versiones de una imagen
    a diferentes resoluciones. Ejemplo:
    srcset="foto-800w.jpg 800w, foto-400w.jpg 400w"
    Nosotros solo necesitamos una, así que tomamos la primera.
    """
    if not srcset:
        return None
    # srcset example: "https://... 800w, https://... 400w"
    first_part = srcset.split(",")[0].strip()
    return first_part.split()[0] if first_part else None


def normalize_images(value, base_url: Optional[str]) -> List[str]:
    """
    Normaliza el campo "image" del JSON-LD que puede ser string, lista o None.
    Devuelve siempre una lista de URLs absolutas.
    """
    imgs: List[str] = []
    if isinstance(value, list):
        imgs = [normalize_image_url(v, base_url) for v in value]
    elif isinstance(value, str):
        imgs = [normalize_image_url(value, base_url)]
    else:
        imgs = []
    return [img for img in imgs if img]


def extract_meta(text: str) -> Dict[str, Optional[int]]:
    """
    Extrae metros cuadrados, habitaciones y baños desde texto libre.

    Usa expresiones regulares (regex) para encontrar patrones comunes:
    - "85 m2" o "85 m²" → metros = 85
    - "3 habitaciones" o "3 hab" → habitaciones = 3
    - "2 baños" o "2 banos" → baños = 2

    Los regex son tolerantes a variaciones de escritura (con/sin tilde,
    abreviaciones, etc.) porque los textos de los anuncios no son uniformes.
    """
    meta = {"metros": None, "habitaciones": None, "banos": None}
    if not text:
        return meta

    def pick_first_int(values, max_value: Optional[int] = None) -> Optional[int]:
        for val in values:
            try:
                num = int(val)
            except (ValueError, TypeError):
                continue
            if max_value is not None and num > max_value:
                continue
            return num
        return None

    # Regex tolerantes a variaciones de escritura (m2/m², baños con n/ñ, etc.).
    metros_matches = re.findall(r"(\d+)\s*(?:m2|m²)", text, flags=re.IGNORECASE)
    hab_matches = re.findall(r"(\d+)\s*(?:hab(?:itaciones?)?)", text, flags=re.IGNORECASE)
    banos_matches = re.findall(r"(\d+)\s*ba(?:n|ñ)os?", text, flags=re.IGNORECASE)

    meta["metros"] = pick_first_int(metros_matches)
    # Habitaciones: descarta outliers (p.ej., 20) quedandose con valores razonables
    meta["habitaciones"] = pick_first_int(hab_matches, max_value=10)
    meta["banos"] = pick_first_int(banos_matches, max_value=8)
    return meta


def guess_operacion(url: str, title: str) -> Optional[str]:
    """
    Deduce si la propiedad es de VENTA o ALQUILER a partir de la URL y el título.
    Los portales inmobiliarios suelen incluir la operación en la URL
    (ej: /alquiler-piso-...) o en el título del anuncio.
    """
    txt = f"{url} {title}".lower()
    if "alquiler" in txt or "/alquiler" in txt:
        return "alquiler"
    if "venta" in txt or "comprar" in txt:
        return "venta"
    return None


def guess_tipo(title: str) -> Optional[str]:
    """
    Deduce el tipo de inmueble (piso, chalet, ático, etc.) desde el título.
    Busca palabras clave comunes del mercado inmobiliario español.
    """
    t = title.lower()
    for kw in ["atico", "ático", "duplex", "dúplex", "chalet", "casa", "adosado", "piso", "apartamento", "estudio", "local", "garaje"]:
        if kw in t:
            return kw
    return None


def _dump_debug_html(html: str, url: str, suffix: str = "") -> None:
    """
    Guarda el HTML descargado en /tmp para depuración posterior.

    Cuando el scraper no encuentra datos, guardamos el HTML que descargó
    para poder inspeccionarlo manualmente y entender qué falló
    (quizá la web cambió su estructura, o nos está bloqueando).
    """
    try:
        os.makedirs("/tmp", exist_ok=True)
        safe_name = re.sub(r"[^a-zA-Z0-9]+", "_", url)[:80]
        fname = f"/tmp/habitaclia_debug_{safe_name}_{suffix}.html"
        with open(fname, "w", encoding="utf-8") as f:
            f.write(html)
        logging.info("Dump HTML debug -> %s", fname)
    except Exception as exc:
        logging.debug("No se pudo volcar HTML debug: %s", exc)


def ensure_absolute_url(url: Optional[str]) -> Optional[str]:
    """
    Convierte cualquier URL relativa a absoluta usando el dominio de Habitaclia.
    Las URLs relativas (ej: /piso-valencia.htm) necesitan el dominio completo
    para poder hacer peticiones HTTP a ellas.
    """
    if not url:
        return None
    url = url.strip()
    if url.startswith("//"):
        return f"https:{url}"
    if url.startswith("http://") or url.startswith("https://"):
        return url
    return urljoin(DEFAULT_BASE_URL, url)


def upsert_listing(conn, listing: Dict) -> None:
    """
    Inserta o actualiza una propiedad en la base de datos (UPSERT).

    UPSERT = UPdate + inSERT:
    - Si la URL no existe en la BD → INSERT (nueva fila)
    - Si la URL ya existe → UPDATE (actualiza los datos existentes)

    Esto se logra con la cláusula SQL 'ON DUPLICATE KEY UPDATE' de MySQL,
    que detecta automáticamente si hay conflicto con la clave única (URL)
    y actualiza en lugar de fallar.

    Para imágenes, usamos COALESCE para no perder imágenes existentes
    si el nuevo scraping no encontró nuevas.
    """
    payload = dict(listing)
    payload["imagenes_json"] = json.dumps(listing.get("imagenes") or [])
    # Remove non-SQL fields that would break connector conversion
    payload.pop("imagenes", None)
    # Normaliza bool de ascensor a tinyint para MySQL.
    if "ascensor" in payload:
        if payload["ascensor"] is True:
            payload["ascensor"] = 1
        elif payload["ascensor"] is False:
            payload["ascensor"] = 0
    sql = """
    INSERT INTO scraped_propiedades
    (fuente, titulo, tipo, operacion, precio, moneda, ubicacion, zona, ciudad, provincia, direccion,
     habitaciones, banos, metros, descripcion, imagen_url, imagenes_json, ascensor, url, raw_hash, scrape_run, scraped_at)
    VALUES
    (%(fuente)s, %(titulo)s, %(tipo)s, %(operacion)s, %(precio)s, %(moneda)s, %(ubicacion)s, %(zona)s,
     %(ciudad)s, %(provincia)s, %(direccion)s, %(habitaciones)s, %(banos)s, %(metros)s, %(descripcion)s,
     %(imagen_url)s, %(imagenes_json)s, %(ascensor)s, %(url)s, %(raw_hash)s, %(scrape_run)s, NOW())
    ON DUPLICATE KEY UPDATE
        titulo = VALUES(titulo),
        tipo = VALUES(tipo),
        operacion = VALUES(operacion),
        precio = VALUES(precio),
        moneda = VALUES(moneda),
        ubicacion = VALUES(ubicacion),
        zona = VALUES(zona),
        ciudad = VALUES(ciudad),
        provincia = VALUES(provincia),
        direccion = VALUES(direccion),
        habitaciones = VALUES(habitaciones),
        banos = VALUES(banos),
        metros = VALUES(metros),
        descripcion = VALUES(descripcion),
        imagen_url = COALESCE(VALUES(imagen_url), imagen_url),
        imagenes_json = CASE WHEN VALUES(imagenes_json) IS NOT NULL AND VALUES(imagenes_json) != '[]' THEN VALUES(imagenes_json) ELSE imagenes_json END,
        ascensor = COALESCE(VALUES(ascensor), ascensor),
        raw_hash = VALUES(raw_hash),
        scrape_run = VALUES(scrape_run),
        updated_at = NOW();
    """
    with conn.cursor() as cur:
        cur.execute(sql, payload)


def scrape(base_url: str, pages: int, delay: float, run_tag: str, dry_run: bool = False) -> None:
    """
    Función principal que orquesta todo el proceso de scraping.

    Recorre las páginas del listado de Habitaclia, extrae propiedades,
    las normaliza y las guarda en la base de datos.

    Args:
        base_url: URL del listado de Habitaclia a scrapear.
        pages:    Número de páginas a recorrer.
        delay:    Segundos de espera entre páginas (para no saturar el servidor).
        run_tag:  Etiqueta única que identifica esta ejecución del scraper.
        dry_run:  Si es True, no escribe en BD (solo simula para pruebas).
    """
    # Bucle principal: descarga página, parsea, deduplica, enriquece imágenes y persiste
    logging.info("Iniciando scraping base_url=%s pages=%s", base_url, pages)
    conn = get_db_conn()
    ensure_table(conn)

    total = 0
    for page in range(1, pages + 1):
        # Construimos la URL de la página actual y la descargamos
        page_url = build_page_url(base_url, page)
        logging.info("Scrape pagina %s -> %s", page, page_url)
        soup = fetch_soup(page_url)
        if not soup:
            continue

        # Combinamos resultados de JSON-LD + tarjetas HTML y deduplicamos
        listings = parse_from_ldjson(soup) + parse_from_cards(soup)
        listings = dedupe_listings(listings)

        if not listings:
            # Fallback a versión móvil cuando desktop devuelve DOM no parseable.
            alt_url = page_url.replace("www.", "m.", 1)
            logging.warning("Pagina %s sin listados parseados, probando version movil %s", page_url, alt_url)
            soup_alt = fetch_soup(alt_url)
            if soup_alt:
                listings = dedupe_listings(parse_from_ldjson(soup_alt) + parse_from_cards(soup_alt))
        if not listings:
            logging.warning("Pagina %s sin listados parseados (tras fallback)", page_url)

        for raw in listings:
            # Normalizamos cada propiedad al formato de la BD
            normalized = normalize_listing(raw, run_tag)
            if not normalized.get("url"):
                continue
            # Si no hubo imágenes en listado, intenta extraerlas desde detalle.
            if not normalized.get("imagenes"):
                images = fetch_images(normalized.get("url"))
                normalized["imagenes"] = images
                if images and not normalized.get("imagen_url"):
                    normalized["imagen_url"] = images[0]
            if not normalized.get("imagen_url") and normalized.get("imagenes"):
                normalized["imagen_url"] = normalized["imagenes"][0]
            # DRY-RUN: modo prueba que valida todo el pipeline sin escribir en BD.
            # Útil para verificar que el scraper funciona antes de ejecutarlo "en serio".
            if dry_run:
                logging.debug("[DRY-RUN] %s", normalized["titulo"])
                continue
            # Guardamos/actualizamos la propiedad en la base de datos
            upsert_listing(conn, normalized)
            total += 1

        # Hacemos commit (confirmar cambios) en la BD después de cada página
        conn.commit()
        # Esperamos antes de la siguiente página para no saturar el servidor
        time.sleep(delay)

    logging.info("Finalizado: %s registros procesados", total)
    conn.close()


def parse_args() -> argparse.Namespace:
    """
    Define los argumentos que el script acepta desde la línea de comandos.

    argparse es una librería estándar de Python que facilita la creación
    de interfaces de línea de comandos. Genera automáticamente ayuda
    con --help y valida los tipos de datos.
    """
    parser = argparse.ArgumentParser(description="Scraper Habitaclia -> TinoProp")
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="URL de listado en Habitaclia")
    parser.add_argument("--pages", type=int, default=2, help="Numero de paginas a recorrer")
    parser.add_argument("--delay", type=float, default=1.5, help="Segundos de espera entre paginas")
    parser.add_argument("--run-tag", default=datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S"), help="Etiqueta identificadora de la corrida")
    parser.add_argument("--dry-run", action="store_true", help="No escribe en BD, solo muestra logs")
    parser.add_argument("--verbose", action="store_true", help="Nivel debug")
    return parser.parse_args()


def main() -> None:
    """
    Punto de entrada del script. Se ejecuta cuando haces:
    python3 scrape_habitaclia.py

    Configura el sistema de logging (mensajes informativos) y
    lanza el scraping con los argumentos proporcionados.
    """
    args = parse_args()
    # Configuramos el logging: si --verbose, mostramos TODOS los mensajes;
    # si no, solo INFO y superiores (WARNING, ERROR)
    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
    )
    # Lanzamos el scraping con todos los parámetros de la línea de comandos
    scrape(args.base_url, args.pages, args.delay, args.run_tag, dry_run=args.dry_run)


# ¿Qué es if __name__ == "__main__"?
# Esta condición se cumple SOLO cuando ejecutas el archivo directamente.
# Si otro archivo hace "import scrape_habitaclia", NO se ejecuta main().
# Esto permite reutilizar las funciones de este archivo sin ejecutar el scraper.
if __name__ == "__main__":
    main()
