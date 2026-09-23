# Checklist de Cumplimiento RGPD — Tinoprop
## Auditoría Técnica y Legal — Abril 2026

**Leyenda:** ✅ Cumple · ⚠️ Parcial / Recomendado · ❌ No cumple / Pendiente

---

## 1. BASES LEGALES Y TRANSPARENCIA

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 1.1 | Política de Privacidad publicada y accesible | ⚠️ | Generada en `legal/privacidad.php`. **Publicar y enlazar desde el footer de la app.** Rellenar los marcadores [EMPRESA] / [CIF] / etc. |
| 1.2 | Aviso Legal publicado | ⚠️ | Generado en `legal/aviso-legal.php`. **Publicar.** Rellenar datos legales. |
| 1.3 | Términos y Condiciones publicados | ⚠️ | Generados en `legal/terminos.php`. **Publicar.** |
| 1.4 | Política de Cookies publicada | ⚠️ | Generada en `legal/cookies.php`. **Publicar.** |
| 1.5 | Bases legales documentadas por cada tratamiento | ✅ | Documentado en `registro-actividades.md` y `base-legal.md` |
| 1.6 | Información a interesados en el momento de recogida | ❌ | Formulario público (`formulario.php`) **no tiene texto informativo RGPD ni casilla de consentimiento**. Añadir obligatoriamente. |
| 1.7 | Consentimiento separado para marketing | ❌ | No implementado. Si se envían comunicaciones comerciales, requiere opt-in explícito. |

---

## 2. DERECHOS DE LOS INTERESADOS

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 2.1 | Canal habilitado para ejercicio de derechos ARCO+ | ⚠️ | Definir email de contacto privacidad y publicarlo en la Política de Privacidad. Crear procedimiento interno de respuesta (máx. 30 días). |
| 2.2 | Derecho de acceso: posibilidad de exportar datos del usuario | ❌ | **No implementado técnicamente.** Añadir funcionalidad de exportación de datos por usuario/cliente. Ver `requisitos-tecnicos.md`. |
| 2.3 | Derecho al olvido: eliminación de datos a petición | ❌ | **No implementado técnicamente.** Añadir funcionalidad de purga de datos de un cliente/prospecto. Ver `requisitos-tecnicos.md`. |
| 2.4 | Portabilidad: exportación en formato estructurado (CSV/JSON) | ⚠️ | Exportación básica de clientes existe. Falta exportación completa incluyendo mensajes, historial IA, actividad. |
| 2.5 | Procedimiento documentado para responder en ≤30 días | ❌ | Crear procedimiento interno. |

---

## 3. SEGURIDAD DEL TRATAMIENTO (Art. 32 RGPD)

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 3.1 | Contraseñas con hash seguro (bcrypt) | ✅ | Implementado. Factor de coste verificar ≥ 10. |
| 3.2 | Protección CSRF | ✅ | Implementado con tokens de sesión. |
| 3.3 | Control de acceso por roles | ✅ | Roles admin/agente implementados. |
| 3.4 | Bloqueo por intentos fallidos | ✅ | 5 intentos → bloqueo de cuenta. |
| 3.5 | HTTPS/TLS en todas las comunicaciones | ⚠️ | Verificar que el servidor tiene HTTPS activo y redirige HTTP → HTTPS. Añadir HSTS header. |
| 3.6 | Cookie de sesión segura (HttpOnly + Secure + SameSite) | ❌ | `PHPSESSID` no tiene atributos de seguridad configurados. Añadir en `php.ini` o `session_set_cookie_params()`. |
| 3.7 | Cookie `ref_code` segura | ❌ | Sin atributos HttpOnly/Secure/SameSite. Corregir en el código que la establece. |
| 3.8 | Cookie `funnel_visitor` segura | ❌ | Mismo problema que `ref_code`. |
| 3.9 | Regeneración de sesión post-login | ✅ | `session_regenerate_id(true)` implementado. |
| 3.10 | Registro de actividad (audit log) | ✅ | Tabla `actividad_log` con IP, usuario, acción. |
| 3.11 | Política de retención de logs | ⚠️ | Log se almacena pero no hay proceso de purga automática. Implementar limpieza mensual. |
| 3.12 | Copias de seguridad | ⚠️ | No evaluado en el código fuente. Verificar que existe backup automático del servidor/BD. |
| 3.13 | Cifrado de datos en reposo | ⚠️ | No implementado en capa de aplicación. Depende del cifrado a nivel disco del servidor. Verificar. |
| 3.14 | Plan de respuesta a incidentes | ❌ | No documentado. Crear procedimiento interno para brechas de seguridad. |

---

## 4. TRANSFERENCIAS INTERNACIONALES

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 4.1 | Identificadas todas las transferencias fuera del EEE | ✅ | Anthropic, Groq, Meta, Stripe (todos EE. UU.). |
| 4.2 | Mecanismo legal para cada transferencia | ⚠️ | Verificar y documentar los DPAs firmados con Anthropic, Groq, Meta y Stripe. Archivar copia de las CCT aplicables. |
| 4.3 | Información a interesados sobre transferencias internacionales | ✅ | Incluido en Política de Privacidad. |
| 4.4 | Análisis de riesgo de transferencias a EE. UU. | ⚠️ | Completar Evaluación de Impacto de Transferencia (TIA) para Anthropic y Groq (datos sensibles del CRM). Ver `evaluacion-riesgos.md`. |

---

## 5. CONTRATOS CON ENCARGADOS (Art. 28 RGPD)

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 5.1 | DPA con proveedor de hosting | ❌ | Firmar DPA con el proveedor de hosting. |
| 5.2 | DPA con Anthropic (Claude) | ⚠️ | Verificar si Anthropic ofrece DPA para el plan utilizado. Firmar/aceptar. |
| 5.3 | DPA con Groq | ⚠️ | Verificar disponibilidad de DPA con Groq y firmar. |
| 5.4 | DPA con Meta (WhatsApp Business) | ⚠️ | Meta incluye DPA en sus Términos de servicio para WhatsApp Business API. Verificar aceptación formal. |
| 5.5 | DPA con Stripe | ⚠️ | Stripe incluye DPA en sus Términos. Verificar aceptación y archivo. |
| 5.6 | DPA ofrecido a clientes SaaS (Tinoprop como Encargado) | ⚠️ | Plantilla generada en `contrato-encargado.md`. **Firmar con cada cliente activo.** |

---

## 6. COOKIES Y CONSENTIMIENTO

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 6.1 | Banner de consentimiento de cookies | ❌ | **NO implementado.** Obligatorio antes de activar cookies no esenciales. |
| 6.2 | Rechazo igual de fácil que aceptación | ❌ | Pendiente de implementar junto con el banner. |
| 6.3 | Registro de consentimientos (consent log) | ❌ | Pendiente. Tabla en BD para almacenar: usuario/IP, timestamp, versión de política, decisión. |
| 6.4 | Posibilidad de revocar consentimiento | ❌ | Pendiente de implementar. |
| 6.5 | Cookies no esenciales inactivas sin consentimiento | ❌ | `ref_code` y `funnel_visitor` se establecen sin consentimiento previo. Corregir. |

---

## 7. DELEGADO DE PROTECCIÓN DE DATOS (DPD)

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 7.1 | Análisis de obligatoriedad del DPD | ❌ | Como SaaS que procesa datos para múltiples clientes a gran escala, **puede ser obligatorio** (Art. 37.1.b RGPD). Consultar con asesor legal. |
| 7.2 | Nombramiento del DPD (si aplica) | ❌ | Pendiente de decisión. Si es obligatorio, designar y comunicar a la AEPD. |
| 7.3 | Publicación de datos de contacto del DPD | ❌ | Publicar en Política de Privacidad (si se designa). |

---

## 8. REGISTRO DE ACTIVIDADES DE TRATAMIENTO (Art. 30 RGPD)

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 8.1 | RAT documentado por escrito | ✅ | Generado en `registro-actividades.md`. Revisar y completar datos de empresa. |
| 8.2 | RAT actualizado ante cambios de tratamiento | ⚠️ | Establecer proceso de revisión anual o ante cualquier nuevo tratamiento. |

---

## 9. FORMULARIOS PÚBLICOS DE CAPTACIÓN

| # | Requisito | Estado | Acción necesaria |
|---|---|---|---|
| 9.1 | Texto informativo RGPD en `formulario.php` | ❌ | **Urgente:** El formulario público auto-crea clientes sin información RGPD ni consentimiento. Añadir texto y casilla obligatoria. Ver `clausulas-consentimiento.md`. |
| 9.2 | Registro de consentimiento con timestamp | ❌ | Añadir campo `consentimiento_rgpd` y `consentimiento_at` a la tabla `formulario_envios`. |
| 9.3 | Doble opt-in para formularios de captación | ⚠️ | Recomendado pero no obligatorio. Implementar si se usan para marketing. |

---

## RESUMEN EJECUTIVO

| Área | Cumplimiento | Riesgo |
|---|---|---|
| Documentación legal | 30% | 🔴 Alto |
| Derechos de interesados | 20% | 🔴 Alto |
| Seguridad técnica | 65% | 🟡 Medio |
| Transferencias internacionales | 50% | 🟡 Medio |
| Contratos con encargados | 25% | 🔴 Alto |
| Cookies y consentimiento | 0% | 🔴 Alto |
| DPD | 0% | 🟡 Medio |
| RAT | 70% | 🟢 Bajo |
| Formularios públicos | 0% | 🔴 Alto |

### Prioridades inmediatas (antes de continuar operando)
1. ❌ Añadir consentimiento RGPD al formulario público (`formulario.php`)
2. ❌ Implementar banner de cookies con consentimiento
3. ❌ Securizar cookies de sesión (`HttpOnly`, `Secure`, `SameSite`)
4. ⚠️ Rellenar y publicar los documentos legales generados
5. ⚠️ Firmar DPA con el proveedor de hosting
6. ❌ Evaluar obligatoriedad del DPD
