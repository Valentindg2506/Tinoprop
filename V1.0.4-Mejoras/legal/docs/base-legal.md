# Base Legal por Actividad de Tratamiento — Tinoprop
## Análisis conforme al Art. 6 y Art. 9 RGPD

**Fecha:** Abril 2026

---

## Marco normativo aplicable

- **RGPD** (Reglamento UE 2016/679): Artículos 6 (bases legales) y 9 (categorías especiales)
- **LOPDGDD** (Ley Orgánica 3/2018): Especificaciones nacionales españolas
- **LSSI** (Ley 34/2002): Comunicaciones comerciales electrónicas (Art. 21)
- **LSSICE / Directiva ePrivacy**: Cookies y comunicaciones electrónicas

---

## Tabla de bases legales por tratamiento

### Tinoprop como Responsable

| Tratamiento | Base legal RGPD | Fundamento específico | Notas |
|---|---|---|---|
| **Autenticación y gestión de cuenta de usuario** | Art. 6.1.b — Ejecución de contrato | El acceso al CRM es parte del servicio contratado | No requiere consentimiento adicional |
| **Facturación y gestión de cobros** | Art. 6.1.b + Art. 6.1.c | Contrato + obligación legal fiscal (Ley 58/2003 LGT) | Datos de facturación conservados 10 años |
| **Registro de actividad / audit log (IP, acciones)** | Art. 6.1.f — Interés legítimo | Seguridad de la plataforma; detectar accesos no autorizados; trazabilidad | Test de balance: el interés legítimo en seguridad supera la expectativa razonable del usuario profesional |
| **Soporte técnico al cliente** | Art. 6.1.b — Ejecución de contrato | El soporte es parte del servicio SaaS | Incluye datos de diagnóstico que el usuario comparte voluntariamente |
| **Comunicaciones sobre el servicio** (cambios, incidencias, mantenimientos) | Art. 6.1.b — Ejecución de contrato | Información necesaria para prestar el servicio | No requiere consentimiento; obligatorio para el funcionamiento del servicio |
| **Comunicaciones comerciales** (nuevas funciones, upsell) | Art. 6.1.a — Consentimiento **ó** Art. 6.1.f — Interés legítimo | Si el destinatario es cliente activo: interés legítimo (LSSI Art. 21.2 — productos propios similares) | Siempre incluir baja fácil; si es prospecto (no cliente): requiere consentimiento explícito |
| **Programa de afiliados — gestión** | Art. 6.1.b — Ejecución del acuerdo de afiliación | El tratamiento es necesario para ejecutar el programa | Incluye datos bancarios para pago de comisiones |
| **Cookie de afiliado (`ref_code`)** | Art. 6.1.a — Consentimiento | Cookie de seguimiento no esencial; requiere consentimiento previo al establecimiento | No se puede establecer sin banner de cookies |
| **Cookie de funnel (`funnel_visitor`)** | Art. 6.1.a — Consentimiento | Cookie de seguimiento analítico; requiere consentimiento | No se puede establecer sin banner de cookies |
| **Cookie de sesión (`PHPSESSID`)** | Art. 6.1.b — Ejecución de contrato | Cookie técnica estrictamente necesaria para el funcionamiento del servicio | Exenta de consentimiento (Directiva ePrivacy + LSSI) |

### Tinoprop como Encargado (datos de los clientes)

| Tratamiento | Base legal del Responsable (cliente) | Notas para el cliente |
|---|---|---|
| **Gestión de prospectos inmobiliarios** | Art. 6.1.b — precontractual o Art. 6.1.f — interés legítimo | El cliente debe evaluar y documentar su base legal. Típicamente: relación precontractual o interés legítimo comercial. |
| **Historial de comunicaciones WhatsApp** | Art. 6.1.b — ejecución de relación comercial | El cliente debe informar a sus contactos que sus mensajes se almacenan. |
| **Formularios públicos de captación** | Art. 6.1.a — Consentimiento | **Obligatorio:** el formulario público debe incluir casilla de consentimiento activo y texto informativo. Sin esto, el tratamiento es ilegal. |
| **Mensajes de email** | Art. 6.1.b o Art. 6.1.a | Según el tipo de comunicación: transaccional (b) o comercial (a). |
| **Conversaciones IA** (datos del CRM enviados a Anthropic/Groq) | Misma base que el tratamiento original | Los datos enviados a la IA mantienen la misma base legal. Informar en la política de privacidad propia. |
| **Visitas programadas a inmuebles** | Art. 6.1.b — relación precontractual | Tratamiento necesario para la gestión del servicio inmobiliario. |

---

## Análisis de interés legítimo (Art. 6.1.f) — Test de balance

### Audit log (registro de actividad)

**Interés perseguido por Tinoprop:**
- Detección de accesos no autorizados.
- Trazabilidad de acciones para resolución de disputas.
- Seguridad de la plataforma y de todos los usuarios.

**Necesidad del tratamiento:**
- No existe alternativa menos intrusiva para garantizar la seguridad.
- El dato mínimo necesario se limita a: ID usuario, acción, timestamp, IP.

**Ponderación con los derechos del interesado:**
- Los usuarios son profesionales (inmobiliarias/agentes) que acceden en el contexto de una relación contractual.
- La expectativa razonable de un usuario profesional en una plataforma SaaS incluye el registro de accesos.
- Los datos no se comparten con terceros ni se usan para perfilado.
- El período de retención está limitado a 12 meses.

**Conclusión:** El interés legítimo prevalece. Se debe informar en la Política de Privacidad (requisito de transparencia).

---

## Categorías especiales de datos (Art. 9 RGPD)

Tinoprop **no trata de forma estructurada ni intencionada** categorías especiales de datos (salud, origen étnico, opiniones políticas, datos biométricos, etc.).

**Riesgo residual:** los campos de notas libres en prospectos y clientes podrían contener información sensible introducida por los agentes (ej: "el propietario está enfermo", "tiene problemas económicos"). 

**Medida recomendada:** incluir en los Términos de Uso la prohibición expresa de introducir categorías especiales de datos en campos de texto libre, y añadir aviso en la interfaz de notas.

---

## Consentimiento: requisitos de validez (Art. 7 RGPD)

Para los tratamientos basados en consentimiento (cookies no esenciales, marketing, formularios de captación), el consentimiento debe ser:

| Requisito | Descripción | Estado en Tinoprop |
|---|---|---|
| **Libre** | Sin coacción; rechazar no debe tener consecuencias negativas | ⚠️ Verificar que el rechazo de cookies no bloquea el servicio |
| **Específico** | Para una finalidad concreta, no genérico | ❌ Pendiente de implementar categorías en banner |
| **Informado** | El interesado conoce quién trata, para qué y por cuánto tiempo | ❌ Falta en formularios públicos |
| **Inequívoco** | Acción afirmativa clara (checkbox marcado, no pre-marcado) | ❌ No implementado |
| **Revocable** | Debe poder retirarse con la misma facilidad | ❌ No implementado |
| **Documentado** | Debe poder demostrar que se obtuvo el consentimiento | ❌ Consent log no implementado |
