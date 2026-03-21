# TinoProp V2.1 - Idioma Inglés

Añade preferencia de idioma (Español/Inglés) a la app completa. Se apoya en traducción por buffer para textos comunes, manteniendo las preferencias de UI y dashboard de V2.0.

## Novedades principales
- Selector de idioma en Configuración (Español/English) guardado por usuario.
- Preferencia `ui.idioma` aplicada en sesión y persistida en BD.
- Traducción al inglés por sustitución en buffer para los textos más visibles (menú, configuración, botones comunes).
- `lang` del documento se ajusta dinámicamente.

## Pasos rápidos
1) BD: importa `database/tinoprop.sql` si aún no lo hiciste.
2) Entra a **Configuración** → elige idioma (English), guarda.
3) Navega: el menú y textos comunes se verán en inglés; el resto permanece en español donde no exista traducción mapeada.

## Estructura
- `api/`, `css/`, `js/`, `inc/`, `secciones/`, `database/`: código y assets de la app.
- `docs/`: guías (GUIA-RAPIDA, IMPLEMENTACION, README_GALERIA, README_V1.8, README_V1.9, README_V2.0, README_V2.1).
- `storage/uploads/propiedades/`: imágenes de propiedades (crear si no existe).

## Notas de despliegue
1) No hay dependencias nuevas. El cambio es solo PHP/HTML.
2) Si necesitas ampliar cobertura de traducciones, agrega pares español→inglés en `inc/idioma.php`.
3) El tema y densidad se mantienen como en V2.0; el idioma se gestiona de forma independiente.
