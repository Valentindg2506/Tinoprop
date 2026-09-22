# Registro de Actividades de Tratamiento (RAT)
## Artículo 30 RGPD — Tinoprop (CRM SaaS Inmobiliario)

**Responsable:** [NOMBRE EMPRESA]  
**CIF:** [CIF]  
**Contacto privacidad:** [EMAIL PRIVACIDAD]  
**Fecha de actualización:** 16/04/2026  
**Versión:** 1.0

---

> **Nota:** Como empresa SaaS, Tinoprop mantiene este RAT en su doble condición de **Responsable** (para datos de sus propios clientes/usuarios) y **Encargado** (para datos que sus clientes introducen en la plataforma).

---

## PARTE A — Tinoprop como RESPONSABLE del Tratamiento

### A1. Gestión de usuarios y clientes SaaS

| Campo | Detalle |
|---|---|
| **Denominación** | Gestión de cuentas de clientes SaaS (inmobiliarias y agentes) |
| **Responsable** | [NOMBRE EMPRESA] |
| **Finalidad** | Alta, autenticación, gestión de la cuenta, soporte técnico y facturación del servicio CRM |
| **Base legal** | Art. 6.1.b RGPD — ejecución del contrato de servicio |
| **Categorías de interesados** | Representantes y empleados de las inmobiliarias clientes; agentes inmobiliarios usuarios de la plataforma |
| **Categorías de datos** | Nombre, apellidos, email profesional, teléfono, contraseña (hash bcrypt), rol, dirección IP, historial de accesos, configuraciones de usuario |
| **Destinatarios** | Equipo técnico Tinoprop; proveedor de hosting (España) |
| **Transferencias internacionales** | No aplica (datos en servidor España) |
| **Plazo de conservación** | Duración del contrato + 5 años (obligación mercantil) |
| **Medidas de seguridad** | Hash bcrypt, HTTPS, CSRF, bloqueo por intentos, audit log |

### A2. Facturación y pagos

| Campo | Detalle |
|---|---|
| **Denominación** | Gestión de facturación y cobros de suscripciones |
| **Responsable** | [NOMBRE EMPRESA] |
| **Finalidad** | Emisión de facturas, gestión de cobros recurrentes, cumplimiento fiscal |
| **Base legal** | Art. 6.1.b RGPD — ejecución de contrato; Art. 6.1.c — obligación legal fiscal |
| **Categorías de interesados** | Clientes SaaS (personas físicas o representantes de personas jurídicas) |
| **Categorías de datos** | Nombre/razón social, CIF, dirección fiscal, email de facturación, datos de pago (procesados por Stripe; Tinoprop no almacena números de tarjeta) |
| **Destinatarios** | Stripe, Inc. (EE. UU.) — procesador de pagos; asesoría fiscal |
| **Transferencias internacionales** | Stripe, EE. UU. — CCT / DPF |
| **Plazo de conservación** | 10 años (Ley 58/2003 General Tributaria) |
| **Medidas de seguridad** | Stripe PCI-DSS, comunicaciones cifradas TLS |

### A3. Programa de afiliados

| Campo | Detalle |
|---|---|
| **Denominación** | Gestión del programa de referidos/afiliados |
| **Responsable** | [NOMBRE EMPRESA] |
| **Finalidad** | Alta de afiliados, seguimiento de conversiones, cálculo y pago de comisiones |
| **Base legal** | Art. 6.1.b RGPD — ejecución del acuerdo de afiliación; Art. 6.1.a — consentimiento (cookie de seguimiento) |
| **Categorías de interesados** | Personas físicas o representantes de empresas afiliadas |
| **Categorías de datos** | Nombre, email, código de afiliado, datos bancarios (para pago de comisiones), cookie `ref_code` |
| **Destinatarios** | Procesador de pagos; equipo interno |
| **Transferencias internacionales** | No aplica |
| **Plazo de conservación** | Duración del acuerdo + 5 años; cookie: 30 días |
| **Medidas de seguridad** | HTTPS; pendiente: atributos HttpOnly/Secure en cookie ref_code |

### A4. Registro de actividad del sistema (audit log)

| Campo | Detalle |
|---|---|
| **Denominación** | Logs de seguridad y actividad de la plataforma |
| **Responsable** | [NOMBRE EMPRESA] |
| **Finalidad** | Detección de accesos no autorizados, trazabilidad de acciones, soporte técnico, seguridad informática |
| **Base legal** | Art. 6.1.f RGPD — interés legítimo (seguridad de la plataforma) |
| **Categorías de interesados** | Usuarios de la plataforma |
| **Categorías de datos** | ID usuario, acción realizada, dirección IP, user-agent, timestamp, módulo afectado, ID de entidad modificada |
| **Destinatarios** | Equipo técnico Tinoprop |
| **Transferencias internacionales** | No aplica |
| **Plazo de conservación** | 12 meses |
| **Medidas de seguridad** | Acceso restringido a admin; logs no modificables por usuarios |

### A5. Comunicaciones de marketing y servicio

| Campo | Detalle |
|---|---|
| **Denominación** | Envío de comunicaciones sobre el servicio, actualizaciones y marketing |
| **Responsable** | [NOMBRE EMPRESA] |
| **Finalidad** | Notificaciones de servicio (cambios, incidencias); comunicaciones comerciales de productos propios |
| **Base legal** | Servicio: Art. 6.1.b RGPD; Marketing: Art. 6.1.a (consentimiento) o Art. 6.1.f (interés legítimo para clientes activos, conforme LSSI Art. 21.2) |
| **Categorías de interesados** | Clientes SaaS y leads |
| **Categorías de datos** | Email, nombre, historial de comunicaciones |
| **Destinatarios** | Proveedor de email transaccional ([PROVEEDOR EMAIL, ej: Vonage/Mailgun]) |
| **Transferencias internacionales** | Según proveedor — verificar SCCs si es USA |
| **Plazo de conservación** | Hasta baja del servicio o revocación del consentimiento |
| **Medidas de seguridad** | Enlace de baja en cada comunicación; doble opt-in recomendado |

---

## PARTE B — Tinoprop como ENCARGADO del Tratamiento

> Los datos gestionados por los clientes dentro de la plataforma son responsabilidad de cada cliente. Tinoprop los trata exclusivamente como encargado, conforme al DPA firmado con cada cliente.

### B1. Datos de prospectos inmobiliarios

| Campo | Detalle |
|---|---|
| **Denominación** | Gestión de prospectos (propietarios captados) en el CRM |
| **Responsable** | Cada cliente SaaS (inmobiliaria) |
| **Encargado** | [NOMBRE EMPRESA] (Tinoprop) |
| **Finalidad** | Almacenamiento y gestión de contactos prospectados para captación inmobiliaria |
| **Categorías de datos** | Nombre, apellidos, teléfono, email, dirección del inmueble, notas comerciales, etapa del pipeline, temperatura, historial de contactos |
| **Subencargados** | Hosting España; Anthropic/Groq (si cliente usa IA) |
| **Transferencias internacionales** | Anthropic/Groq EE. UU. (si se usa IA) — CCT |
| **Plazo de conservación** | Según instrucciones del Responsable; supresión al finalizar contrato (30 días) |

### B2. Mensajes de WhatsApp

| Campo | Detalle |
|---|---|
| **Denominación** | Almacenamiento de conversaciones WhatsApp Business |
| **Responsable** | Cada cliente SaaS |
| **Encargado** | [NOMBRE EMPRESA] (Tinoprop) |
| **Finalidad** | Historial de conversaciones para gestión comercial |
| **Categorías de datos** | Número de teléfono, contenido del mensaje, tipo (texto/imagen/audio), estado de lectura, timestamp |
| **Subencargados** | Meta Platforms (envío/recepción), hosting España |
| **Transferencias internacionales** | Meta EE. UU. — CCT |
| **Plazo de conservación** | Según instrucciones del Responsable |

### B3. Formularios de captación (landing pages)

| Campo | Detalle |
|---|---|
| **Denominación** | Datos enviados por visitantes a través de formularios web del cliente |
| **Responsable** | Cada cliente SaaS |
| **Encargado** | [NOMBRE EMPRESA] (Tinoprop) |
| **Finalidad** | Captación de leads, creación automática de clientes/prospectos en el CRM |
| **Categorías de datos** | Nombre, teléfono, email, mensaje libre, IP del visitante, timestamp |
| **Subencargados** | Hosting España |
| **Transferencias internacionales** | No aplica |
| **Nota de cumplimiento** | ⚠️ El cliente debe incluir información RGPD y casilla de consentimiento en sus formularios públicos |

### B4. Conversaciones con el Asistente IA

| Campo | Detalle |
|---|---|
| **Denominación** | Historial de consultas al asistente IA del CRM |
| **Responsable** | Cada cliente SaaS |
| **Encargado** | [NOMBRE EMPRESA] (Tinoprop) |
| **Finalidad** | Prestación del servicio IA integrado en el CRM; historial de consultas |
| **Categorías de datos** | Mensajes del usuario, respuestas de la IA, registros de herramientas ejecutadas, datos del CRM consultados durante la sesión |
| **Subencargados** | Anthropic, PBC (EE. UU.); Groq, Inc. (EE. UU.) |
| **Transferencias internacionales** | EE. UU. — CCT (Anthropic y Groq) |
| **Nota de cumplimiento** | ⚠️ Los datos del CRM consultados por la IA se envían a servidores en EE. UU. Informar a los usuarios |

---

## Historial de versiones

| Versión | Fecha | Cambios |
|---|---|---|
| 1.0 | 16/04/2026 | Creación inicial del RAT |
