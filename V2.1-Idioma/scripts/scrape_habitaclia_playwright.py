#!/usr/bin/env python3
"""
Scraper Habitaclia usando Playwright para evadir el bloqueo JS/captcha.
- Reutiliza la logica de parseo y upsert de scrape_habitaclia.
- Necesita playwright instalado: pip install playwright && playwright install chromium
"""
import argparse
import asyncio
import json
import logging
import os
import re
import time
from datetime import datetime, timezone
from urllib.parse import urljoin

from playwright.async_api import async_playwright
from bs4 import BeautifulSoup

from scrape_habitaclia import (
    build_page_url,
    dedupe_listings,
    ensure_table,
    fetch_images,
    get_db_conn,
    normalize_image_url,
    normalize_images,
    normalize_listing,
    parse_from_cards,
    parse_from_ldjson,
    extract_meta,
    upsert_listing,
)


DESKTOP_UA = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)
MOBILE_UA = (
    "Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
)


def is_block_page(html: str) -> bool:
    txt = (html or "").lower()
    block_markers = [
        "pardon our interruption",
        "access denied",
        "captcha",
        "imperva",
        "verify you are human",
        "incapsula",
    ]
    return any(marker in txt for marker in block_markers)


def _dump_debug_html(html: str, url: str, suffix: str) -> None:
    try:
        os.makedirs("/tmp", exist_ok=True)
        safe = re.sub(r"[^a-zA-Z0-9]+", "_", url)[:80]
        path = f"/tmp/habitaclia_playwright_{safe}_{suffix}.html"
        with open(path, "w", encoding="utf-8") as f:
            f.write(html or "")
        logging.info("Dump HTML debug -> %s", path)
    except Exception as exc:
        logging.debug("No se pudo volcar HTML debug: %s", exc)


def _stealth_init_script() -> str:
    # Minimal anti-bot hardening (no external deps)
    return """
// webdriver
Object.defineProperty(navigator, 'webdriver', {get: () => undefined});
// languages
Object.defineProperty(navigator, 'languages', {get: () => ['es-ES', 'es', 'en-US', 'en']});
// plugins
Object.defineProperty(navigator, 'plugins', {get: () => [1,2,3,4,5]});
"""


def _first_from_srcset(srcset: str) -> str:
    if not srcset:
        return ""
    first = srcset.split(",")[0].strip()
    return first.split()[0].strip() if first else ""


def _dedupe_urls(urls):
    seen = set()
    result = []
    for url in urls:
        if not url or url in seen:
            continue
        seen.add(url)
        result.append(url)
    return result


def _collect_ldjson_images(node, base_url: str, out):
    if isinstance(node, dict):
        if "image" in node:
            out.extend(normalize_images(node.get("image"), base_url))
        for value in node.values():
            _collect_ldjson_images(value, base_url, out)
    elif isinstance(node, list):
        for item in node:
            _collect_ldjson_images(item, base_url, out)


def extract_images_from_detail_html(html: str, base_url: str, max_items: int = 12):
    soup = BeautifulSoup(html or "", "html.parser")
    images = []

    for tag in soup.select("meta[property='og:image'], meta[property='og:image:secure_url'], meta[name='twitter:image']"):
        content = (tag.get("content") or "").strip()
        if content:
            images.append(normalize_image_url(content, base_url))

    for script in soup.find_all("script", {"type": "application/ld+json"}):
        raw = (script.string or "").strip()
        if not raw:
            continue
        try:
            data = json.loads(raw)
        except Exception:
            continue
        _collect_ldjson_images(data, base_url, images)

    for el in soup.select("img[src], img[data-src], img[data-original], source[srcset], source[data-srcset]"):
        candidate = (
            el.get("src")
            or el.get("data-src")
            or el.get("data-original")
            or _first_from_srcset(el.get("srcset") or "")
            or _first_from_srcset(el.get("data-srcset") or "")
        )
        candidate = (candidate or "").strip()
        if not candidate:
            continue
        normalized = normalize_image_url(candidate, base_url)
        if normalized:
            images.append(normalized)

    filtered = []
    for url in _dedupe_urls(images):
        low = url.lower()
        if low.endswith(".svg"):
            continue
        if "logo" in low or "avatar" in low or "icon" in low:
            continue
        if low.startswith("data:"):
            continue
        if low.startswith("//"):
            low = f"https:{low}"
        if low.startswith("/"):
            low = urljoin(base_url, low)
        filtered.append(low)
        if len(filtered) >= max_items:
            break

    return filtered


async def fetch_html(context, url: str, wait: int = 3000, retries: int = 2) -> str:
    page = await context.new_page()
    try:
        # First load: be tolerant; some anti-bot pages never reach networkidle
        await page.goto(url, wait_until="domcontentloaded", timeout=45000)
        await page.wait_for_timeout(wait)

        # Retry loop: sometimes the JS challenge auto-resolves after a few seconds
        for attempt in range(retries + 1):
            try:
                await page.wait_for_load_state("networkidle", timeout=15000)
            except Exception:
                pass

            try:
                await page.evaluate("window.scrollTo(0, document.body.scrollHeight)")
            except Exception:
                pass
            await page.wait_for_timeout(800)
            html = await page.content()
            title = ""
            try:
                title = await page.title()
            except Exception:
                pass

            if not is_block_page(html) and title.lower() != "pardon our interruption":
                return html

            logging.debug("Posible anti-bot (%s) intento %s/%s -> %s", title or "sin titulo", attempt + 1, retries + 1, url)
            if attempt < retries:
                await page.wait_for_timeout(6000)
                try:
                    await page.reload(wait_until="domcontentloaded", timeout=45000)
                except Exception:
                    await page.goto(url, wait_until="domcontentloaded", timeout=45000)
                await page.wait_for_timeout(wait)

        # Return last HTML even if blocked (caller will decide)
        return html
    finally:
        await page.close()


async def scrape_page(desktop_ctx, mobile_ctx, url: str, run_tag: str, conn, delay: float, dry_run: bool) -> int:
    total = 0
    html = await fetch_html(desktop_ctx, url)
    listings = dedupe_listings(parse_from_ldjson_html(html) + parse_from_cards_html(html))

    if not listings:
        logging.warning("Pagina sin listados parseados, probando version movil %s", url)
        html_m = await fetch_html(mobile_ctx, url.replace("www.", "m."))
        listings = dedupe_listings(parse_from_ldjson_html(html_m) + parse_from_cards_html(html_m))

    if not listings:
        # Dump HTML for diagnostics
        _dump_debug_html(html, url, "nolist")
        if 'html_m' in locals():
            _dump_debug_html(html_m, url.replace('www.', 'm.'), 'nolist_mobile')
        return 0

    # Detail page uses same desktop context so cookies/session persist
    detail_page = await desktop_ctx.new_page()

    for raw in listings:
        normalized = normalize_listing(raw, run_tag)
        if not normalized.get("url"):
            continue

        # Fetch detail page to fill missing meta/ascensor
        try:
            await detail_page.goto(normalized.get("url"), wait_until="domcontentloaded", timeout=45000)
            await detail_page.wait_for_timeout(1500)
            try:
                await detail_page.wait_for_load_state("networkidle", timeout=15000)
            except Exception:
                pass
            detail_html = await detail_page.content()
            if not is_block_page(detail_html):
                detail_meta = extract_meta(detail_html)
                if detail_meta.get("metros"):
                    normalized["metros"] = detail_meta.get("metros")
                if detail_meta.get("habitaciones"):
                    normalized["habitaciones"] = detail_meta.get("habitaciones")
                if detail_meta.get("banos"):
                    normalized["banos"] = detail_meta.get("banos")
                low = detail_html.lower()
                if "sin ascensor" in low:
                    normalized["ascensor"] = False
                elif "ascensor" in low:
                    normalized["ascensor"] = True

                detail_images = extract_images_from_detail_html(detail_html, normalized.get("url"))
                if detail_images:
                    merged = detail_images + (normalized.get("imagenes") or [])
                    merged = _dedupe_urls(merged)
                    normalized["imagenes"] = merged
                    normalized["imagen_url"] = merged[0]
            else:
                logging.debug("Detalle bloqueado por anti-bot, se mantienen datos de tarjeta: %s", normalized.get("url"))
        except Exception as exc:
            logging.debug("No se pudo leer detalle %s: %s", normalized.get("url"), exc)

        if not normalized.get("imagenes"):
            images = fetch_images(normalized.get("url"))
            normalized["imagenes"] = images
            if images and not normalized.get("imagen_url"):
                normalized["imagen_url"] = images[0]
        if not normalized.get("imagen_url") and normalized.get("imagenes"):
            normalized["imagen_url"] = normalized["imagenes"][0]
        if dry_run:
            logging.debug("[DRY-RUN] %s", normalized["titulo"])
            continue
        upsert_listing(conn, normalized)
        total += 1

    await detail_page.close()

    conn.commit()
    time.sleep(delay)
    return total


def parse_from_ldjson_html(html: str):
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(html, "html.parser")
    return parse_from_ldjson(soup)


def parse_from_cards_html(html: str):
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(html, "html.parser")
    return parse_from_cards(soup)


async def main_async(base_url: str, pages: int, delay: float, run_tag: str, dry_run: bool) -> None:
    logging.info("Iniciando scraping (Playwright) base_url=%s pages=%s", base_url, pages)
    conn = get_db_conn()
    ensure_table(conn)
    total = 0

    async with async_playwright() as play:
        browser = await play.chromium.launch(
            headless=True,
            args=[
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-dev-shm-usage",
            ],
        )

        desktop_ctx = await browser.new_context(
            user_agent=DESKTOP_UA,
            locale="es-ES",
            timezone_id="Europe/Madrid",
            viewport={"width": 1366, "height": 768},
        )
        mobile_ctx = await browser.new_context(
            user_agent=MOBILE_UA,
            locale="es-ES",
            timezone_id="Europe/Madrid",
            viewport={"width": 412, "height": 915},
            is_mobile=True,
            has_touch=True,
        )
        await desktop_ctx.add_init_script(_stealth_init_script())
        await mobile_ctx.add_init_script(_stealth_init_script())

        try:
            for page in range(1, pages + 1):
                page_url = build_page_url(base_url, page)
                logging.info("Scrape pagina %s -> %s", page, page_url)
                total += await scrape_page(desktop_ctx, mobile_ctx, page_url, run_tag, conn, delay, dry_run)
        finally:
            await desktop_ctx.close()
            await mobile_ctx.close()
            await browser.close()

    logging.info("Finalizado (Playwright): %s registros procesados", total)
    conn.close()


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Scraper Habitaclia (Playwright) -> TinoProp")
    parser.add_argument("--base-url", default="https://www.habitaclia.com/viviendas-en-valencia.htm", help="URL de listado")
    parser.add_argument("--pages", type=int, default=2, help="Numero de paginas a recorrer")
    parser.add_argument("--delay", type=float, default=1.5, help="Segundos entre paginas")
    parser.add_argument("--run-tag", default=datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S"), help="Etiqueta de corrida")
    parser.add_argument("--dry-run", action="store_true", help="No escribe en BD")
    parser.add_argument("--verbose", action="store_true", help="Nivel debug")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
    )
    asyncio.run(main_async(args.base_url, args.pages, args.delay, args.run_tag, args.dry_run))


if __name__ == "__main__":
    main()
