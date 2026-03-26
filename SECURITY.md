# Política de Seguridad

## Versiones soportadas

| Versión | Soportada |
|---------|:---------:|
| V1.0.3  | ✅ Activa |
| V1.0.2  | ❌ |
| V1.0.1  | ❌ |
| V1.0.0  | ❌ |
| < V1.0.0 | ❌ |

Solo la última versión de producción (`V1.0.3-Arreglo de errores`) recibe correcciones de seguridad.

---

## Reportar una vulnerabilidad

Si descubres una vulnerabilidad de seguridad en TinoProp, **no la publiques como Issue público**. Hacerlo podría exponer a los usuarios del sistema antes de que se publique un parche.

### Proceso de reporte responsable (Responsible Disclosure)

1. **Contacta directamente** al autor a través de los datos de contacto disponibles en su perfil de GitHub ([@Valentindg2506](https://github.com/Valentindg2506)).
2. Incluye en tu reporte:
   - Descripción clara de la vulnerabilidad
   - Pasos para reproducirla
   - Impacto potencial estimado
   - Si es posible, una prueba de concepto (PoC)
3. **Tiempo de respuesta:** Se intentará responder en un plazo de 72 horas.
4. **Reconocimiento:** Los reportes válidos serán reconocidos públicamente (si el investigador lo desea) en las notas de la versión que incluya el parche.

---

## Medidas de seguridad implementadas

TinoProp implementa las siguientes protecciones en su versión actual:

| Protección | Implementación |
|-----------|---------------|
| Autenticación | bcrypt (`password_hash` / `password_verify`) |
| SQL Injection | PDO con prepared statements nativos (`EMULATE_PREPARES=false`) |
| XSS | Función `e()` — `htmlspecialchars` en toda la salida |
| CSRF | Tokens por sesión con `hash_equals()` (timing-safe) |
| Fuerza bruta | Rate limiting: 5 intentos / 15 min → bloqueo 15 min |
| Clickjacking | `X-Frame-Options: DENY` |
| HTTPS | Redirect 301 + `Strict-Transport-Security` (HSTS, max-age=1 año) |
| Uploads | Validación MIME con `finfo`, límite 10 MB, nombres aleatorios |
| Sesiones | `httponly`, `SameSite=Strict`, `Secure` en HTTPS |
| Archivos sensibles | `.htaccess` bloquea `.env`, `.sql`, `.log`, `.py` |

---

**Valentín Antonio De Gennaro** — Valencia, España — 2025/2026
