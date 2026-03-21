# README V2.1 - Idioma Inglés

## Objetivo
Permitir que cada usuario cambie la interfaz a inglés (o mantenga español) sin afectar el resto de preferencias.

## Implementación
- Nueva clave `ui.idioma` (es/en) almacenada en `preferencias_usuario` y en la sesión.
- `inc/i18n.php` provee helpers y un traductor por buffer que hace `str_replace` sobre textos comunes cuando el idioma es `en`.
- `inc/bootstrap.php` lee la preferencia, fija el idioma en sesión e inicia el buffer de traducción; se asegura de finalizarlo al cierre.
- `index.php` ajusta `lang` del documento según la preferencia.
- `secciones/configuracion.php` añade el selector de idioma y resetea la clave en el botón de restablecer.

## Cobertura de traducción
- Menú lateral y textos frecuentes en Configuración, botones comunes y labels clave.
- Para ampliar cobertura, agrega pares español→inglés en `inc/i18n.php` (función `i18n_traducciones`).

## Cómo probar
1) Inicia sesión y ve a `?seccion=configuracion#preferencias`.
2) Selecciona **English** en "Idioma de la interfaz" y guarda.
3) Navega por el menú: verás los textos traducidos; el `lang` de la página será `en`.
4) Usa el botón de reset de preferencias para volver a español (borra `ui.idioma` y demás filtros/estilos).

## Archivos modificados
- `inc/bootstrap.php` → carga i18n, fija idioma y buffer.
- `inc/i18n.php` → nuevo módulo de traducción.
- `index.php` → `lang` dinámico en `<html>`.
- `secciones/configuracion.php` → selector y guardado de idioma.
- `README.md`, `docs/README_V2.1.md` → documentación de versión.

## Notas
- El método de traducción es simple (reemplazo de texto). Evita strings ambiguas o agrega claves más específicas si notas colisiones.
- No se introducen dependencias externas ni cambios de base de datos.
