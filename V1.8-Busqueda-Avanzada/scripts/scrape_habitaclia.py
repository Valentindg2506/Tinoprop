#!/usr/bin/env python3
"""
Scraper de Habitaclia para TinoProp V1.8.

- Obtiene listados de viviendas en Valencia (o la URL especificada)
- Inserta/actualiza en la tabla `scraped_propiedades`
- Pensado para ejecutarse de forma programada (cron) y alimentar la búsqueda avanzada

Requisitos: pip install -r scripts/requirements.txt
"""
import argparse
import hashlib
import json
import logging
import os
import re
import time
from datetime import datetime
from typing import Dict, List, Optional

import mysql.connector
import requests
from bs4 import BeautifulSoup

DEFAULT_BASE_URL = "https://www.habitaclia.com/viviendas-en-valencia.htm"
USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"


def get_db_conn():
    """Crea la conexion a MySQL usando variables de entorno o valores por defecto."""
    return mysql.connector.connect(
        host=os.getenv("TP_DB_HOST", "localhost"),
        database=os.getenv("TP_DB_NAME", "tinoprop"),
        user=os.getenv("TP_DB_USER", "valentin"),
        password=os.getenv("TP_DB_PASS", "759234"),
        charset="utf8mb4",
        use_unicode=True,
    )


def ensure_table(conn) -> None:
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
        cur.execute(ddl)
    conn.commit()


def build_page_url(base_url: str, page: int) -> str:
    if page <= 1:
        return base_url

    if base_url.endswith(".htm"):
        base, ext = base_url.rsplit(".", 1)
        return f"{base}-{page}.{ext}"

    joiner = "&" if "?" in base_url else "?"
    return f"{base_url}{joiner}page={page}"


def fetch_soup(url: str, timeout: int = 20) -> Optional[BeautifulSoup]:
    headers = {"User-Agent": USER_AGENT, "Accept-Language": "es-ES,es;q=0.9"}
    resp = requests.get(url, headers=headers, timeout=timeout)
    if resp.status_code != 200:
        logging.warning("GET %s devolvio %s", url, resp.status_code)
        return None
    return BeautifulSoup(resp.text, "html.parser")


def parse_price(text: str) -> Optional[float]:
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
    match = re.search(r"(\d+)", text or "")
    if not match:
        return None
    try:
        return int(match.group(1))
    except ValueError:
        return None


def parse_from_ldjson(soup: BeautifulSoup) -> List[Dict]:
    listings: List[Dict] = []
    for script in soup.find_all("script", {"type": "application/ld+json"}):
        try:
            data = json.loads(script.string or "")
        except Exception:
            continue

        # ItemList format
        if isinstance(data, dict) and data.get("@type") == "ItemList":
            for element in data.get("itemListElement", []):
                item = element.get("item", {}) if isinstance(element, dict) else {}
                if not item:
                    continue
                listings.append(
                    {
                        "titulo": item.get("name"),
                        "descripcion": item.get("description"),
                        "url": item.get("url"),
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
                    }
                )
    return listings


def parse_from_cards(soup: BeautifulSoup) -> List[Dict]:
    listings: List[Dict] = []
    selectors = ["div.list-item", "article", "div[class*=\"list-item\"]"]
    for selector in selectors:
        for card in soup.select(selector):
            link = card.select_one("a")
            title_el = card.select_one(".list-item-title") or card.select_one("h3")
            price_el = card.select_one(".list-item-price") or card.select_one(".price")
            desc_el = card.select_one(".description") or card.select_one("p")
            if not link or not title_el:
                continue
            href = link.get("href")
            if not href or "habitaclia.com" not in href:
                continue
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
                    "habitaciones": None,
                    "banos": None,
                    "metros": None,
                    "tipo": None,
                    "operacion": None,
                    "zona": None,
                }
            )
    return listings


def dedupe_listings(listings: List[Dict]) -> List[Dict]:
    seen = set()
    final: List[Dict] = []
    for item in listings:
        key = item.get("url")
        if not key or key in seen:
            continue
        seen.add(key)
        final.append(item)
    return final


def normalize_listing(raw: Dict, run_tag: str) -> Dict:
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
        "url": url,
        "raw_hash": hashlib.sha256(base_hash).hexdigest(),
        "scrape_run": run_tag,
    }


def upsert_listing(conn, listing: Dict) -> None:
    sql = """
    INSERT INTO scraped_propiedades
    (fuente, titulo, tipo, operacion, precio, moneda, ubicacion, zona, ciudad, provincia, direccion,
     habitaciones, banos, metros, descripcion, url, raw_hash, scrape_run, scraped_at)
    VALUES
    (%(fuente)s, %(titulo)s, %(tipo)s, %(operacion)s, %(precio)s, %(moneda)s, %(ubicacion)s, %(zona)s,
     %(ciudad)s, %(provincia)s, %(direccion)s, %(habitaciones)s, %(banos)s, %(metros)s, %(descripcion)s,
     %(url)s, %(raw_hash)s, %(scrape_run)s, NOW())
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
        raw_hash = VALUES(raw_hash),
        scrape_run = VALUES(scrape_run),
        updated_at = NOW();
    """
    with conn.cursor() as cur:
        cur.execute(sql, listing)


def scrape(base_url: str, pages: int, delay: float, run_tag: str, dry_run: bool = False) -> None:
    logging.info("Iniciando scraping base_url=%s pages=%s", base_url, pages)
    conn = get_db_conn()
    ensure_table(conn)

    total = 0
    for page in range(1, pages + 1):
        page_url = build_page_url(base_url, page)
        logging.info("Scrape pagina %s -> %s", page, page_url)
        soup = fetch_soup(page_url)
        if not soup:
            continue

        listings = parse_from_ldjson(soup) + parse_from_cards(soup)
        listings = dedupe_listings(listings)

        for raw in listings:
            normalized = normalize_listing(raw, run_tag)
            if not normalized.get("url"):
                continue
            if dry_run:
                logging.debug("[DRY-RUN] %s", normalized["titulo"])
                continue
            upsert_listing(conn, normalized)
            total += 1

        conn.commit()
        time.sleep(delay)

    logging.info("Finalizado: %s registros procesados", total)
    conn.close()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Scraper Habitaclia -> TinoProp")
    parser.add_argument("--base-url", default=DEFAULT_BASE_URL, help="URL de listado en Habitaclia")
    parser.add_argument("--pages", type=int, default=2, help="Numero de paginas a recorrer")
    parser.add_argument("--delay", type=float, default=1.5, help="Segundos de espera entre paginas")
    parser.add_argument("--run-tag", default=datetime.utcnow().strftime("%Y%m%d%H%M%S"), help="Etiqueta identificadora de la corrida")
    parser.add_argument("--dry-run", action="store_true", help="No escribe en BD, solo muestra logs")
    parser.add_argument("--verbose", action="store_true", help="Nivel debug")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
    )
    scrape(args.base_url, args.pages, args.delay, args.run_tag, dry_run=args.dry_run)


if __name__ == "__main__":
    main()
