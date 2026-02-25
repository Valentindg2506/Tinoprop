# README V2.0 - Preferencias de UI

## Objetivo
Sumar personalización visual por usuario (tema claro/oscuro y densidad de tablas) sin romper los flujos previos.

## Qué cambia
- Nuevos campos en Configuración → Preferencias: `Tema de interfaz` (Sistema/Claro/Oscuro) y `Densidad de tablas` (Media/Cómoda/Compacta).
- Reset de preferencias también borra `ui.tema` y `ui.densidad` además de los filtros de dashboard.
- `index.php` aplica clases en `<body>` según las preferencias guardadas.
- `estilo.css` añade estilos para tema oscuro y densidades.

## Claves en preferencias_usuario
- `ui.tema`
- `ui.densidad`
- (Se mantienen) `dashboard.filtro.equipo`, `dashboard.filtro.periodo`, `dashboard.filtro.operacion`.

## Cómo probar
1) Inicia sesión y ve a `?seccion=configuracion#preferencias`.
2) Elige tema y densidad, guarda. Se realiza PRG y se muestran mensajes flash.
3) Revisa cualquier sección: el body debe tener clases `tema-oscuro`/`tema-claro` y `densidad-*` si no está en modo Sistema/Media.
4) Usa el botón "Restablecer valores por defecto" para limpiar filtros + tema + densidad.

## Archivos tocados
- `index.php` → aplica clases de tema/densidad.
- `secciones/configuracion.php` → añade selects y validaciones; resetea nuevas claves.
- `css/estilo.css` → estilos para tema oscuro y densidad cómoda/compacta.
- `README.md`, `docs/README_V2.0.md` → documentación de la versión.

## Notas
- Tema "Sistema" simplemente no aplica clase (usa estilos claros por defecto).
- No se agregan dependencias ni cambios de base de datos.
