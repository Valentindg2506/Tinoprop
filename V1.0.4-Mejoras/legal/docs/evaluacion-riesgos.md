# Evaluación de Riesgos para la Protección de Datos — Tinoprop
## Análisis de riesgos conforme al Art. 32 RGPD

**Fecha:** Abril 2026  
**Metodología:** CNIL PIA v3 simplificada + ENISA Risk Assessment

---

## Escala de valoración

| Probabilidad/Impacto | 1 — Muy baja | 2 — Baja | 3 — Media | 4 — Alta | 5 — Muy alta |
|---|---|---|---|---|---|
| **Riesgo = P × I** | 1-4: Bajo 🟢 | 5-9: Medio 🟡 | 10-19: Alto 🔴 | 20-25: Crítico ⛔ | | |

---

## Mapa de riesgos

### R01 — Acceso no autorizado a la base de datos

| Elemento | Detalle |
|---|---|
| **Descripción** | Un atacante externo o usuario interno con exceso de privilegios accede a datos personales de prospectos, clientes y mensajes |
| **Datos afectados** | Todos los datos de la BD: prospectos, clientes, mensajes, usuarios, conversaciones IA |
| **Probabilidad** | 3 — Media (aplicación web expuesta a internet) |
| **Impacto** | 5 — Muy alto (datos comerciales + personales de miles de contactos) |
| **Riesgo residual** | 🔴 15 (Alto) |
| **Controles existentes** | Control de acceso por roles, autenticación con bloqueo, audit log, HTTPS |
| **Controles pendientes** | Cifrado de BD en reposo; WAF (Web Application Firewall); segmentación de red; penetration test anual |

### R02 — Robo o fuga de credenciales de usuarios

| Elemento | Detalle |
|---|---|
| **Descripción** | Las credenciales de un agente o admin son comprometidas (phishing, reutilización de contraseñas) |
| **Datos afectados** | Todos los datos accesibles por el rol del usuario comprometido |
| **Probabilidad** | 4 — Alta (vector de ataque muy común) |
| **Impacto** | 4 — Alto |
| **Riesgo residual** | 🔴 16 (Alto) |
| **Controles existentes** | Hash bcrypt, bloqueo 5 intentos, regeneración de sesión |
| **Controles pendientes** | Autenticación de doble factor (2FA/MFA); alertas de login desde nueva IP; contraseñas temporales de un solo uso |

### R03 — Inyección SQL / XSS / vulnerabilidades en la app

| Elemento | Detalle |
|---|---|
| **Descripción** | Vulnerabilidades en el código PHP permiten inyección de código malicioso |
| **Datos afectados** | Potencialmente todos los datos |
| **Probabilidad** | 3 — Media (aplicación con múltiples módulos) |
| **Impacto** | 5 — Muy alto |
| **Riesgo residual** | 🔴 15 (Alto) |
| **Controles existentes** | Prepared statements en la mayoría del código; `htmlspecialchars()` en salidas |
| **Controles pendientes** | Auditoría de código completa; Content Security Policy (CSP) headers; OWASP Top 10 review; bug bounty |

### R04 — Transferencia de datos sensibles a proveedores en EE. UU. (IA)

| Elemento | Detalle |
|---|---|
| **Descripción** | Al usar el asistente IA, datos del CRM (nombres, teléfonos, emails, notas de prospectos) se envían a Anthropic (EE. UU.) o Groq (EE. UU.) |
| **Datos afectados** | Datos del CRM consultados durante la sesión IA: prospectos, clientes, propiedades, historial |
| **Probabilidad** | 5 — Muy alta (ocurre en cada consulta IA) |
| **Impacto** | 3 — Medio (riesgo de transferencia sin SCCs verificadas) |
| **Riesgo residual** | 🔴 15 (Alto) |
| **Controles existentes** | HTTPS al comunicarse con la API |
| **Controles pendientes** | Firmar/verificar DPA con Anthropic y Groq; documentar CCT; informar explícitamente al usuario antes de activar la IA; evaluar si los datos de IA pueden ser anonimizados antes de enviar |

### R05 — Pérdida de datos (fallo del servidor / desastre)

| Elemento | Detalle |
|---|---|
| **Descripción** | Fallo del servidor de hosting que cause pérdida irreversible de datos de todos los clientes |
| **Datos afectados** | Todos los datos de la plataforma |
| **Probabilidad** | 2 — Baja (con hosting profesional) |
| **Impacto** | 5 — Muy alto (pérdida total de datos de múltiples clientes) |
| **Riesgo residual** | 🟡 10 (Medio) |
| **Controles existentes** | Sin evaluar desde el código fuente |
| **Controles pendientes** | Verificar política de backups del hosting; implementar backups automáticos diarios; backup offsite; RTO/RPO documentados |

### R06 — Exposición de datos por formulario público sin consentimiento

| Elemento | Detalle |
|---|---|
| **Descripción** | `formulario.php` crea automáticamente clientes/prospectos sin informar al visitante ni obtener su consentimiento |
| **Datos afectados** | Nombre, teléfono, email, mensaje del visitante |
| **Probabilidad** | 5 — Muy alta (ocurre en cada envío de formulario) |
| **Impacto** | 4 — Alto (infracción directa del Art. 13 RGPD; multa potencial AEPD) |
| **Riesgo residual** | ⛔ 20 (Crítico) |
| **Controles existentes** | Ninguno |
| **Controles pendientes** | **Acción inmediata:** Añadir texto RGPD y casilla de consentimiento obligatoria al formulario. Ver `clausulas-consentimiento.md`. |

### R07 — Ausencia de banner de cookies

| Elemento | Detalle |
|---|---|
| **Descripción** | Las cookies `ref_code` y `funnel_visitor` se establecen sin consentimiento previo |
| **Datos afectados** | Seguimiento de navegación/afiliados |
| **Probabilidad** | 5 — Muy alta (ocurre en cada visita) |
| **Impacto** | 3 — Medio (infracción LSSI; sanción AEPD de hasta 30.000 €) |
| **Riesgo residual** | 🔴 15 (Alto) |
| **Controles existentes** | Ninguno |
| **Controles pendientes** | Implementar banner de consentimiento antes de establecer cookies no esenciales |

### R08 — Brecha de seguridad en mensajes de WhatsApp

| Elemento | Detalle |
|---|---|
| **Descripción** | Los mensajes de WhatsApp (incluyendo los de clientes del CRM) están almacenados en texto plano en la BD |
| **Datos afectados** | Contenido de mensajes, números de teléfono |
| **Probabilidad** | 2 — Baja |
| **Impacto** | 4 — Alto (comunicaciones privadas expuestas) |
| **Riesgo residual** | 🟡 8 (Medio) |
| **Controles existentes** | Control de acceso, HTTPS |
| **Controles pendientes** | Cifrado a nivel de columna para `mensaje` en `whatsapp_mensajes`; acceso restringido a mensajes solo del agente propietario |

### R09 — Acceso excesivo a datos entre agentes

| Elemento | Detalle |
|---|---|
| **Descripción** | Un agente puede ver datos de prospectos y clientes que no son suyos |
| **Datos afectados** | Prospectos, clientes, mensajes |
| **Probabilidad** | 3 — Media |
| **Impacto** | 2 — Bajo (impacto interno, no externo) |
| **Riesgo residual** | 🟢 6 (Bajo) |
| **Controles existentes** | Roles diferenciados |
| **Controles pendientes** | Implementar filtro por `agente_id` en vistas de prospectos/clientes (data isolation entre agentes) |

---

## Resumen del mapa de riesgos

| ID | Riesgo | P | I | R | Estado |
|---|---|---|---|---|---|
| R01 | Acceso no autorizado a BD | 3 | 5 | 🔴 15 | Controles parciales |
| R02 | Robo de credenciales | 4 | 4 | 🔴 16 | Controles básicos. Falta 2FA |
| R03 | Vulnerabilidades app (SQLi/XSS) | 3 | 5 | 🔴 15 | Controles básicos. Falta auditoría |
| R04 | Transferencia IA a EE. UU. | 5 | 3 | 🔴 15 | Sin DPA verificado |
| R05 | Pérdida de datos (disaster) | 2 | 5 | 🟡 10 | Sin verificar backups |
| R06 | Formulario sin consentimiento | 5 | 4 | ⛔ 20 | **Sin controles — URGENTE** |
| R07 | Sin banner de cookies | 5 | 3 | 🔴 15 | **Sin controles** |
| R08 | Mensajes WhatsApp en texto plano | 2 | 4 | 🟡 8 | Controles de acceso |
| R09 | Acceso cruzado entre agentes | 3 | 2 | 🟢 6 | Roles implementados |

---

## ¿Es necesaria una EIPD (Evaluación de Impacto)?

Conforme al Art. 35 RGPD, una **Evaluación de Impacto en la Protección de Datos (EIPD/DPIA)** es obligatoria cuando el tratamiento "puede suponer un alto riesgo". Los criterios del EDPB son:

| Criterio | Tinoprop | ¿Aplica? |
|---|---|---|
| Evaluación o puntuación (scoring/profiling) | Calificación automática de prospectos por IA | ⚠️ Parcialmente |
| Decisiones automatizadas con efecto legal | No se toman decisiones automatizadas vinculantes | ❌ |
| Monitorización sistemática | Registro de actividad de usuarios | ⚠️ Parcialmente |
| Datos sensibles | No se tratan de forma estructurada | ❌ |
| Datos a gran escala | Depende del número de clientes SaaS activos | ⚠️ Evaluar umbral |
| Combinación/correlación de datasets | La IA combina múltiples fuentes del CRM | ⚠️ |
| Nuevas tecnologías (IA) | Asistente IA con tool use | ✅ |
| Impedimento de derechos | No aplica | ❌ |

**Recomendación:** Si Tinoprop supera ~500 clientes SaaS activos o las funcionalidades de IA se expanden (scoring automatizado, decisiones de negocio), se recomienda realizar una EIPD formal.
