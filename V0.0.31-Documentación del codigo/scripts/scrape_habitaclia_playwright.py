#!/usr/bin/env python3
"""
==========================================================================
Archivo: scripts/scrape_habitaclia_playwright.py — Scraper Alternativo con Playwright
==========================================================================

¿QUÉ HACE ESTE ARCHIVO?
Este es un scraper ALTERNATIVO al principal (scrape_habitaclia.py) que usa
Playwright en lugar de requests. ¿Por qué necesitamos dos scrapers?

¿QUÉ ES PLAYWRIGHT?
Playwright es una herramienta que controla un navegador REAL (Chrome/Chromium)
de forma programática. Es como tener un robot que abre Chrome, navega a
una web, espera a que cargue, y lee el contenido.

¿POR QUÉ UN SEGUNDO SCRAPER?
El scraper básico (requests) solo descarga el HTML estático. Pero muchas
webs modernas (incluida Habitaclia) tienen protecciones anti-bot:
- CAPTCHA: "Demuestra que no eres un robot"
- JavaScript challenge: la página requiere ejecutar JS para ver el contenido
- Rate limiting: bloqueo tras muchas peticiones seguidas

Playwright supera estas barreras porque ES un navegador real que ejecuta
JavaScript, puede resolver challenges simples, y mantiene cookies/sesión.

CARACTERÍSTICAS ESPECIALES:
- Modo headful (--headful): abre Chrome visible para resolver captchas manualmente.
- Perfil persistente: guarda cookies/sesión para no resolver el captcha cada vez.
- Doble contexto desktop/móvil: si desktop falla, prueba la versión móvil.
- Reutiliza toda la lógica de parseo y BD del scraper principal.

USO:
  python3 scrape_habitaclia_playwright.py                          # Headless (sin ventana)
  python3 scrape_habitaclia_playwright.py --headful --manual-once   # Con ventana, resolución manual
  python3 scrape_habitaclia_playwright.py --dry-run                 # Prueba sin escribir en BD

REQUISITOS:
  pip install playwright && playwright install chromium

Índice de funciones:
- is_block_page: detecta señales de bloqueo/captcha en HTML.
- _dump_debug_html: guarda HTML de depuración en /tmp.
- _stealth_init_script: define script anti-bot básico para el contexto del navegador.
- _first_from_srcset / _dedupe_urls: utilidades de normalización de URLs de imagen.
- _collect_ldjson_images: recorre estructuras JSON-LD para extraer imágenes.
- extract_images_from_detail_html: extrae imágenes desde la página de detalle.
- fetch_html: navega con Playwright y devuelve HTML con reintentos.
- scrape_page: scrapea una página de listados y persiste resultados.
- parse_from_ldjson_html / parse_from_cards_html: wrappers de parseo.
- _wait_for_user: espera interacción manual en modo resolución asistida.
- manual_solve_challenge: abre flujo manual para resolver bloqueos/captcha.
- main_async: orquesta todo el scraping asíncrono.
- parse_args: define argumentos CLI específicos de Playwright.
- main: punto de entrada del script.
"""
# ── IMPORTACIONES ──
import argparse
import asyncio          # Para programación asíncrona (ejecutar tareas en paralelo)
import json
import logging
import os
import re
import time
from datetime import datetime, timezone
from urllib.parse import urljoin

# Playwright: controla un navegador Chromium de forma programática.
# async_playwright permite usarlo con asyncio (asíncrono).
from playwright.async_api import async_playwright
from bs4 import BeautifulSoup

# Reutilizamos TODAS las funciones del scraper principal.
# ¿Por qué? Para no duplicar código. Si corregimos un bug en el parseo,
# se corrige en ambos scrapers automáticamente.
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


# User-Agents para simular dos tipos de dispositivo:
# Desktop: navegador de escritorio (Chrome en Linux)
# Mobile: navegador móvil (Chrome en Android)
# Algunos sitios muestran contenido diferente según el dispositivo.
DESKTOP_UA = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)
MOBILE_UA = (
    "Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36"
)


def is_block_page(html: str) -> bool:
    """
    Detecta si una página contiene señales de bloqueo/captcha.

    Los servicios anti-bot como Imperva/Incapsula muestran páginas
    con mensajes característicos cuando detectan tráfico automatizado.
    Esta función busca esos mensajes para saber si nos han bloqueado.
    """
    txt = (html or "").lower()
    # Lista de textos que aparecen en páginas de bloqueo conocidas
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
    """Guarda el HTML en /tmp para poder inspeccionar manualmente qué devuelve la web."""
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
    """
    Genera un script JavaScript que se inyecta en cada página que abre Playwright.

    ¿Para qué sirve? Los sitios web pueden detectar que estás usando automatización
    comprobando cosas como:
    - navigator.webdriver = true (Playwright lo pone a true por defecto)
    - navigator.languages vacío (los navegadores reales siempre tienen idiomas)
    - navigator.plugins vacío (los navegadores reales tienen plugins)

    Este script "arregla" esos indicadores para que el navegador controlado
    por Playwright parezca un navegador normal.
    """
    return """
// webdriver
Object.defineProperty(navigator, 'webdriver', {get: () => undefined});
// languages
Object.defineProperty(navigator, 'languages', {get: () => ['es-ES', 'es', 'en-US', 'en']});
// plugins
Object.defineProperty(navigator, 'plugins', {get: () => [1,2,3,4,5]});
"""


def _first_from_srcset(srcset: str) -> str:
    """Extrae la primera URL de un atributo srcset HTML."""
    if not srcset:
        return ""
    first = srcset.split(",")[0].strip()
    return first.split()[0].strip() if first else ""


def _dedupe_urls(urls):
    """Elimina URLs duplicadas manteniendo el orden original."""
    seen = set()
    result = []
    for url in urls:
        if not url or url in seen:
            continue
        seen.add(url)
        result.append(url)
    return result


def _collect_ldjson_images(node, base_url: str, out):
    """
    Recorre recursivamente una estructura JSON-LD (diccionarios y listas)
    buscando cualquier campo "image" y añade las URLs encontradas a 'out'.

    JSON-LD puede tener estructuras anidadas complejas, por eso recorremos
    recursivamente todos los niveles.
    """
    if isinstance(node, dict):
        if "image" in node:
            out.extend(normalize_images(node.get("image"), base_url))
        for value in node.values():
            _collect_ldjson_images(value, base_url, out)
    elif isinstance(node, list):
        for item in node:
            _collect_ldjson_images(item, base_url, out)


def extract_images_from_detail_html(html: str, base_url: str, max_items: int = 12):
    """
    Extrae URLs de imágenes desde el HTML de la página de detalle de una propiedad.

    La página de detalle suele tener una galería completa de fotos.
    Buscamos imágenes en 3 fuentes diferentes por orden de prioridad:
    1. Meta tags sociales (og:image) → Imagen principal de alta calidad
    2. JSON-LD del detalle → Imágenes estructuradas en datos semánticos
    3. Etiquetas HTML <img> → Último recurso, recorre el DOM directamente
    """
    soup = BeautifulSoup(html or "", "html.parser")
    images = []

    # 1) Prioridad alta: imágenes de meta tags OpenGraph (og:image) y Twitter.
    # Estas suelen ser la imagen principal del anuncio en alta resolución.
    for tag in soup.select("meta[property='og:image'], meta[property='og:image:secure_url'], meta[name='twitter:image']"):
        content = (tag.get("content") or "").strip()
        if content:
            images.append(normalize_image_url(content, base_url))

    # 2) Extrae imágenes embebidas en JSON-LD del detalle.
    for script in soup.find_all("script", {"type": "application/ld+json"}):
        raw = (script.string or "").strip()
        if not raw:
            continue
        try:
            data = json.loads(raw)
        except Exception:
            continue
        _collect_ldjson_images(data, base_url, images)

    # 3) Fallback DOM: recorremos todas las etiquetas <img> y <source> del HTML
    # buscando imágenes en sus atributos src, data-src, srcset, etc.
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

    # Limpieza final: eliminamos duplicados, filtramos iconos/logos/data-URIs
    # y limitamos la cantidad de imágenes al máximo establecido.
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
    """
    Navega a una URL con Playwright y devuelve el HTML resultante.

    ¿Por qué es 'async'?
    Playwright funciona de forma asíncrona: mientras espera que la página
    cargue, puede hacer otras cosas. 'await' indica "espera a que esto
    termine antes de continuar".

    Incluye lógica de reintentos porque a veces el primer intento falla
    por challenges anti-bot que se resuelven solos tras unos segundos.
    """
    page = await context.new_page()  # Abrimos una nueva pestaña del navegador
    try:
        # Navegamos a la URL y esperamos a que el DOM esté listo.
        # Usamos 'domcontentloaded' en vez de 'networkidle' porque
        # las páginas con challenges JS nunca llegan a networkidle.
        await page.goto(url, wait_until="domcontentloaded", timeout=45000)
        await page.wait_for_timeout(wait)  # Espera adicional para contenido dinámico

        # Bucle de reintento: si detectamos bloqueo, esperamos y recargamos.
        # A veces el challenge JS se resuelve solo tras unos segundos.
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
            html = await page.content()  # Obtenemos el HTML completo de la página
            title = ""
            try:
                title = await page.title()
            except Exception:
                pass

            # Caso exitoso: el HTML no tiene señales de bloqueo
            if not is_block_page(html) and title.lower() != "pardon our interruption":
                return html  # Devolvemos el HTML limpio

            logging.debug("Posible anti-bot (%s) intento %s/%s -> %s", title or "sin titulo", attempt + 1, retries + 1, url)
            if attempt < retries:
                await page.wait_for_timeout(6000)
                try:
                    await page.reload(wait_until="domcontentloaded", timeout=45000)
                except Exception:
                    await page.goto(url, wait_until="domcontentloaded", timeout=45000)
                await page.wait_for_timeout(wait)

        # Si agotamos los reintentos, devolvemos el último HTML obtenido.
        # El código que llama a esta función decidirá qué hacer con él.
        return html
    finally:
        await page.close()  # Siempre cerramos la pestaña al terminar


async def scrape_page(desktop_ctx, mobile_ctx, url: str, run_tag: str, conn, delay: float, dry_run: bool) -> int:
    """
    Scrapea una página de listados: descarga HTML, parsea propiedades,
    enriquece con datos de detalle y guarda en BD.

    Si no encuentra listados en la versión desktop, prueba con la
    versión móvil (m.habitaclia.com) como fallback.
    """
    total = 0
    # Intentamos primero con el contexto desktop
    html = await fetch_html(desktop_ctx, url)
    # Parseamos de ambas fuentes (JSON-LD + tarjetas HTML) y deduplicamos
    listings = dedupe_listings(parse_from_ldjson_html(html) + parse_from_cards_html(html))

    if not listings:
        # Fallback móvil: algunos sitios muestran contenido diferente
        # en la versión móvil que puede ser más fácil de parsear.
        logging.warning("Pagina sin listados parseados, probando version movil %s", url)
        html_m = await fetch_html(mobile_ctx, url.replace("www.", "m."))
        listings = dedupe_listings(parse_from_ldjson_html(html_m) + parse_from_cards_html(html_m))

    if not listings:
        # Dump HTML for diagnostics
        _dump_debug_html(html, url, "nolist")
        if 'html_m' in locals():
            _dump_debug_html(html_m, url.replace('www.', 'm.'), 'nolist_mobile')
        return 0

    # Abrimos una pestaña reutilizable para visitar las páginas de detalle.
    # Usar la misma sesión/contexto conserva cookies y cualquier challenge resuelto.
    detail_page = await desktop_ctx.new_page()

    for raw in listings:
        # Normalizamos cada propiedad al formato de la BD
        normalized = normalize_listing(raw, run_tag)
        if not normalized.get("url"):
            continue

        # ENRIQUECIMIENTO POR DETALLE:
        # Visitamos la página individual de cada propiedad para obtener
        # datos que no están disponibles en la tarjeta del listado
        # (metros cuadrados, baños, galería de fotos completa, etc.)
        try:
            await detail_page.goto(normalized.get("url"), wait_until="domcontentloaded", timeout=45000)
            await detail_page.wait_for_timeout(1500)
            try:
                await detail_page.wait_for_load_state("networkidle", timeout=15000)
            except Exception:
                pass
            detail_html = await detail_page.content()
            if not is_block_page(detail_html):
                # Extraemos metadatos del HTML del detalle
                detail_meta = extract_meta(detail_html)
                if detail_meta.get("metros"):
                    normalized["metros"] = detail_meta.get("metros")
                if detail_meta.get("habitaciones"):
                    normalized["habitaciones"] = detail_meta.get("habitaciones")
                if detail_meta.get("banos"):
                    normalized["banos"] = detail_meta.get("banos")
                low = detail_html.lower()

                # Detección de ascensor mediante búsqueda de texto:
                # Si el texto dice "sin ascensor" → False
                # Si solo dice "ascensor" → True (lo tiene)
                if "sin ascensor" in low:
                    normalized["ascensor"] = False
                elif "ascensor" in low:
                    normalized["ascensor"] = True

                # Las imágenes del detalle son mejores (más y de mayor calidad)
                # que las del listado, así que las priorizamos.
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

        # Fallback final de imágenes (scraper base) si no se obtuvieron en detalle/listado.
        if not normalized.get("imagenes"):
            images = fetch_images(normalized.get("url"))
            normalized["imagenes"] = images
            if images and not normalized.get("imagen_url"):
                normalized["imagen_url"] = images[0]
        if not normalized.get("imagen_url") and normalized.get("imagenes"):
            normalized["imagen_url"] = normalized["imagenes"][0]
        # En modo dry-run no persiste en BD, solo recorre y valida pipeline.
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
    """
    Wrapper que convierte HTML crudo en BeautifulSoup y llama al parser JSON-LD
    del scraper principal. Así reutilizamos la lógica sin duplicar código.
    """
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(html, "html.parser")
    return parse_from_ldjson(soup)


def parse_from_cards_html(html: str):
    """
    Wrapper que convierte HTML crudo en BeautifulSoup y llama al parser de
    tarjetas del scraper principal. Misma lógica de reutilización.
    """
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(html, "html.parser")
    return parse_from_cards(soup)


async def _wait_for_user(prompt: str) -> None:
    """Espera a que el usuario pulse ENTER en la terminal (comunicación asíncrona)."""
    await asyncio.to_thread(input, prompt)


async def manual_solve_challenge(context, url: str) -> bool:
    """
    Abre una pestaña visible para que el usuario resuelva el captcha manualmente.

    Este flujo se usa con --headful --manual-once:
    1. El script detecta que la web muestra un captcha/challenge.
    2. Abre la pestaña visible para que el usuario lo vea.
    3. Le pide que lo resuelva (marcar casilla, seleccionar imágenes, etc.).
    4. El usuario pulsa ENTER en la terminal cuando haya terminado.
    5. El script verifica si se resolvió correctamente.
    6. Si se resolvió, las cookies quedan guardadas en el perfil persistente
       y las siguientes ejecuciones (headless) no tendrán el problema.

    Retorna True si tras la espera la página ya no está bloqueada.
    """
    page = await context.new_page()
    try:
        await page.goto(url, wait_until="domcontentloaded", timeout=45000)
        await page.wait_for_timeout(1500)

        html = await page.content()
        if not is_block_page(html) and (await page.title()).lower() != "pardon our interruption":
            return True

        logging.warning("Se detecto bloqueo/captcha en %s", url)
        logging.warning("Resuelve el captcha en la ventana del navegador abierta.")

        # Permite hasta 5 ciclos de confirmación manual antes de continuar.
        # El usuario resuelve el captcha y pulsa ENTER; verificamos si funcionó.
        for attempt in range(1, 6):
            await _wait_for_user(f"Pulsa ENTER cuando lo hayas resuelto (chequeo {attempt}/5)... ")
            try:
                await page.wait_for_load_state("networkidle", timeout=15000)
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
                logging.info("Challenge resuelto, continuando scraping.")
                return True

        logging.warning("Sigue pareciendo bloqueado tras los intentos. Se continuara igualmente, pero es probable que de 0.")
        return False
    finally:
        await page.close()


async def main_async(
    base_url: str,
    pages: int,
    delay: float,
    run_tag: str,
    dry_run: bool,
    headful: bool,
    user_data_dir: str,
    manual_once: bool,
) -> None:
    """
    Orquestador principal del scraping con Playwright.

    Gestiona todo el ciclo de vida:
    1. Conecta a la base de datos
    2. Lanza Playwright con un perfil persistente (para guardar cookies)
    3. Opcionalmente permite resolver captchas manualmente
    4. Recorre las páginas del listado
    5. Cierra todo al terminar

    El perfil persistente es clave: si resuelves el captcha una vez en
    modo headful, las cookies se guardan y las siguientes ejecuciones
    headless ya no tendrán el captcha.
    """
    logging.info("Iniciando scraping (Playwright) base_url=%s pages=%s", base_url, pages)
    conn = get_db_conn()
    ensure_table(conn)
    total = 0

    async with async_playwright() as play:
        # PERFIL PERSISTENTE:
        # launch_persistent_context guarda cookies, localStorage y sesión en disco.
        # Si resolvemos un captcha una vez, las cookies se guardan y las
        # siguientes ejecuciones no necesitarán resolver el captcha de nuevo.
        persistent_ctx = await play.chromium.launch_persistent_context(
            user_data_dir,
            headless=not headful,  # headless=True = sin ventana; headful = con ventana
            args=[
                "--disable-blink-features=AutomationControlled",  # Oculta que es automatizado
                "--no-sandbox",                                   # Necesario en algunos servidores
                "--disable-dev-shm-usage",                        # Evita errores de memoria compartida
            ],
            locale="es-ES",                    # Idioma español
            timezone_id="Europe/Madrid",        # Zona horaria de España
            viewport={"width": 1366, "height": 768},  # Resolución típica de portátil
        )
        # Inyectamos el script de camuflaje en cada página
        await persistent_ctx.add_init_script(_stealth_init_script())

        # Usamos el mismo contexto para desktop y móvil.
        # Aunque el User-Agent sea de desktop, la URL de m.habitaclia.com
        # puede devolver un HTML diferente más fácil de parsear.
        desktop_ctx = persistent_ctx
        mobile_ctx = persistent_ctx

        try:
            # RESOLUCIÓN MANUAL DE CAPTCHA (opcional, con --manual-once):
            # Primero probamos si la URL base está bloqueada.
            # Si lo está, ofrecemos al usuario resolverlo manualmente.
            if manual_once:
                probe_html = await fetch_html(desktop_ctx, base_url, retries=0)
                if is_block_page(probe_html):
                    logging.warning("Bloqueo/captcha detectado en el listado.")
                    if not headful:
                        logging.warning("Ejecuta con --headful --manual-once para resolver el captcha una vez y guardar sesion en %s", user_data_dir)
                    else:
                        await manual_solve_challenge(desktop_ctx, base_url)

            # Bucle principal: recorremos cada página del listado
            for page in range(1, pages + 1):
                page_url = build_page_url(base_url, page)
                logging.info("Scrape pagina %s -> %s", page, page_url)
                # scrape_page gestiona todo: descargar, parsear, enriquecer y guardar
                total += await scrape_page(desktop_ctx, mobile_ctx, page_url, run_tag, conn, delay, dry_run)
        finally:
            await persistent_ctx.close()

    logging.info("Finalizado (Playwright): %s registros procesados", total)
    conn.close()


def parse_args() -> argparse.Namespace:
    """
    Define los argumentos de línea de comandos específicos del scraper Playwright.
    Incluye opciones adicionales como --headful, --manual-once y --user-data-dir
    que no existen en el scraper básico.
    """
    parser = argparse.ArgumentParser(description="Scraper Habitaclia (Playwright) -> TinoProp")
    parser.add_argument("--base-url", default="https://www.habitaclia.com/viviendas-valencia.htm?filtro_periodo=2", help="URL de listado")
    parser.add_argument("--pages", type=int, default=2, help="Numero de paginas a recorrer")
    parser.add_argument("--delay", type=float, default=1.5, help="Segundos entre paginas")
    parser.add_argument("--run-tag", default=datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S"), help="Etiqueta de corrida")
    parser.add_argument("--dry-run", action="store_true", help="No escribe en BD")
    parser.add_argument("--verbose", action="store_true", help="Nivel debug")
    parser.add_argument("--headful", action="store_true", help="Abre navegador visible (para resolver captcha/challenge)")
    parser.add_argument(
        "--user-data-dir",
        default=os.path.join("/tmp", "tinoprop_pw_profile"),
        help="Directorio donde se guarda el perfil del navegador (cookies, sesión, etc.)",
    )
    parser.add_argument(
        "--manual-once",
        action="store_true",
        help="Si hay captcha, espera a que lo resuelvas manualmente. Usar con --headful.",
    )
    return parser.parse_args()


def main() -> None:
    """
    Punto de entrada del script Playwright.
    Configura logging y lanza el scraping asíncrono con asyncio.run().

    asyncio.run() es la forma estándar de ejecutar funciones asíncronas
    desde código síncrono (el "puente" entre el mundo sync y async).
    """
    args = parse_args()
    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
    )
    # asyncio.run() ejecuta la función asíncrona principal
    asyncio.run(
        main_async(
            args.base_url,
            args.pages,
            args.delay,
            args.run_tag,
            args.dry_run,
            args.headful,
            args.user_data_dir,
            args.manual_once,
        )
    )


# Punto de entrada: se ejecuta solo cuando lanzas el script directamente
# (no cuando lo importas desde otro archivo)
if __name__ == "__main__":
    main()
