# README V1.9 - Configuración y preferencias

## Objetivo
Habilitar la sección de **Configuración** (menú lateral) para que cada usuario gestione su perfil, seguridad y preferencias iniciales del dashboard.

## Qué incluye
- **Perfil:** cambio de nombre y email con validación de email único.
- **Seguridad:** cambio de password verificando la actual (mínimo 8 caracteres).
- **Preferencias del dashboard:** equipo, operación y periodo por defecto. Se guardan en `preferencias_usuario` y se aplican cuando abres el dashboard sin filtros en la URL.
- **Dashboard:** usa las preferencias como fallback; GET sigue teniendo prioridad.

## Flujos
1) Ir a `?seccion=configuracion`.
2) Actualizar **Perfil** o **Seguridad** (Post/Redirect/Get con mensajes flash).
3) Ajustar **Preferencias del dashboard** y guardar o restablecer valores por defecto.
4) Entrar al dashboard; si no se pasan `equipo|periodo|operacion` en la URL, se aplican las preferencias guardadas.

## Detalles técnicos
- Tabla `preferencias_usuario` se crea on-demand vía `preferencias_asegurar_tabla()`.
- Claves usadas:
  - `dashboard.filtro.equipo`
  - `dashboard.filtro.periodo`
  - `dashboard.filtro.operacion`
- Validaciones: listas blancas para equipo/periodo/operación; password mínima 8 y confirmación; email único.
- Mensajes flash: `config_success` y `config_error`.

## Archivos tocados
- `index.php` → agrega el ítem de menú **Configuración**.
- `secciones/configuracion.php` → nueva sección.
- `secciones/dashboard.php` → aplica preferencias como valores iniciales.
- `README.md` y `docs/README_V1.9.md` → documentación actualizada.

## Notas de despliegue
- No hay nuevas dependencias. Solo asegurar permisos de escritura en `storage/uploads/propiedades/` y la BD `tinoprop` disponible.
- Las preferencias son por usuario; si no hay sesión, bootstrap redirige a login.
