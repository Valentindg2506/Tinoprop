# Contrato de Encargado del Tratamiento (DPA)
## Tinoprop — Cláusula de Protección de Datos

**Versión:** 1.0 — Abril 2026  
**Conforme a:** Art. 28 RGPD (UE) 2016/679 y LOPDGDD 3/2018

---

## PARTES

**RESPONSABLE DEL TRATAMIENTO (Cliente):**
- Denominación: _________________________________
- CIF/NIF: _________________________________
- Dirección: _________________________________
- Representante: _________________________________
- Email DPO/contacto privacidad: _________________________________

**ENCARGADO DEL TRATAMIENTO:**
- Denominación: [NOMBRE EMPRESA] (Tinoprop)
- CIF: [CIF]
- Dirección: [DIRECCIÓN FISCAL]
- Email: [EMAIL PRIVACIDAD]

---

## 1. OBJETO

El presente contrato regula las condiciones bajo las cuales el Encargado tratará los datos personales por cuenta del Responsable, en el contexto de la prestación del servicio de CRM inmobiliario en modalidad SaaS.

## 2. NATURALEZA Y FINALIDAD DEL TRATAMIENTO

| Elemento | Detalle |
|---|---|
| **Finalidad** | Prestación del servicio CRM: almacenamiento, gestión y operativa de datos de prospectos, clientes, propiedades y comunicaciones. |
| **Duración** | Durante la vigencia del contrato de servicio. |
| **Naturaleza** | Alojamiento y procesamiento de datos en plataforma SaaS. |
| **Tipo de datos personales** | Nombre, apellidos, email, teléfono, dirección, notas comerciales, mensajes de WhatsApp/email, historial de actividad, datos económicos. |
| **Categorías de interesados** | Prospectos (propietarios que quieren vender), clientes de la inmobiliaria, contactos comerciales. |
| **Categorías especiales** | **No se tratan** categorías especiales (salud, origen racial, etc.) salvo instrucción expresa del Responsable. |

## 3. OBLIGACIONES DEL ENCARGADO

El Encargado (Tinoprop) se obliga a:

### 3.1 Tratamiento conforme a instrucciones
Tratar los datos personales únicamente siguiendo las instrucciones documentadas del Responsable. Si considera que una instrucción infringe el RGPD, informará al Responsable.

### 3.2 Confidencialidad
Garantizar que las personas autorizadas para tratar los datos personales se hayan comprometido a respetar la confidencialidad o estén sujetas a obligación legal de confidencialidad.

### 3.3 Medidas de seguridad (Art. 32 RGPD)
Aplicar medidas técnicas y organizativas apropiadas que garanticen un nivel de seguridad adecuado al riesgo:

- Contraseñas almacenadas con hash bcrypt (factor ≥ 10).
- Protección CSRF en todos los formularios.
- Control de acceso basado en roles (RBAC).
- Bloqueo de cuenta tras 5 intentos fallidos.
- Comunicaciones cifradas mediante HTTPS/TLS.
- Servidor ubicado en España (UE).
- Audit log de acciones sensibles.
- Regeneración de ID de sesión tras autenticación.

### 3.4 Subencargados
El Encargado utiliza los siguientes subencargados, aprobados por el Responsable mediante la aceptación del presente contrato:

| Subencargado | País | Finalidad | Garantía transferencia |
|---|---|---|---|
| Anthropic, PBC (Claude) | EE. UU. | Procesamiento IA del chat asistente | CCT / DPA Anthropic |
| Groq, Inc. | EE. UU. | Procesamiento IA alternativo | CCT / DPA Groq |
| Meta Platforms (WhatsApp) | EE. UU. | Envío/recepción de mensajes WhatsApp | Cláusulas Contractuales Tipo |
| Stripe, Inc. | EE. UU. | Procesamiento de pagos | CCT / DPF |
| Proveedor de hosting | España (UE) | Alojamiento de la plataforma y BD | Dentro del EEE |

El Encargado notificará al Responsable cualquier cambio en los subencargados con al menos **30 días** de antelación, permitiendo al Responsable oponerse.

### 3.5 Asistencia al Responsable
Asistir al Responsable, teniendo en cuenta la naturaleza del tratamiento, mediante medidas técnicas y organizativas apropiadas para que pueda cumplir con:

- Solicitudes de ejercicio de derechos de los interesados (Arts. 12-22 RGPD).
- Obligaciones relativas a seguridad del tratamiento (Art. 32 RGPD).
- Notificación de brechas de seguridad a la autoridad de control (Art. 33 RGPD).
- Comunicación de brechas al interesado (Art. 34 RGPD).
- Evaluaciones de impacto relativas a la protección de datos (Art. 35 RGPD).

### 3.6 Notificación de brechas de seguridad
Notificar al Responsable, **sin dilación indebida** y en todo caso en un plazo máximo de **48 horas** tras tener constancia, cualquier violación de la seguridad de los datos personales.

La notificación incluirá, como mínimo:
- Descripción de la naturaleza de la violación.
- Categorías y número aproximado de interesados afectados.
- Categorías y número aproximado de registros afectados.
- Posibles consecuencias y medidas adoptadas o propuestas.

### 3.7 Supresión o devolución de datos
A elección del Responsable, eliminar o devolver todos los datos personales al finalizar la prestación de servicios, y suprimirá las copias existentes, salvo que el Derecho de la Unión o de los Estados miembros exija la conservación de datos.

**Plazo para exportación:** el Responsable dispondrá de **30 días** desde la baja del servicio para solicitar la exportación en formato CSV. Transcurrido dicho plazo, los datos se eliminarán de forma segura e irrecuperable.

### 3.8 Auditorías
Poner a disposición del Responsable toda la información necesaria para demostrar el cumplimiento de las obligaciones establecidas en el Art. 28 RGPD, y permitir y contribuir a la realización de auditorías, incluidas inspecciones, por parte del Responsable o de otro auditor autorizado por este.

## 4. OBLIGACIONES DEL RESPONSABLE

El Responsable se obliga a:

- Disponer de base legal adecuada para tratar los datos personales antes de introducirlos en la Plataforma.
- Informar a los interesados sobre el tratamiento de sus datos (transparencia).
- Facilitar al Encargado únicamente los datos necesarios para la finalidad del servicio.
- Notificar al Encargado cualquier instrucción específica sobre el tratamiento.
- Responder a las solicitudes de ejercicio de derechos que los interesados dirijan al Responsable.

## 5. TRANSFERENCIAS INTERNACIONALES

Las transferencias de datos a países fuera del EEE (EE. UU.) realizadas por los subencargados listados en §3.4 se amparan en:
- **Cláusulas Contractuales Tipo (CCT)** adoptadas por la Comisión Europea (Decisión 2021/914).
- **Marco de Privacidad de Datos UE-EE.UU. (DPF)** para los proveedores adheridos.

El Encargado mantendrá actualizados los instrumentos de transferencia y notificará al Responsable cualquier cambio.

## 6. DURACIÓN Y TERMINACIÓN

Este contrato está vigente mientras esté activo el contrato de servicio SaaS. Su terminación no exime a las partes de las obligaciones de supresión/devolución de datos establecidas en §3.7.

## 7. RESPONSABILIDAD

Cada parte es responsable del cumplimiento de sus propias obligaciones en materia de protección de datos. En caso de incumplimiento por parte del Encargado que cause una sanción impuesta al Responsable, el Encargado responderá en la medida en que sea imputable a dicho incumplimiento, con el límite establecido en los Términos y Condiciones de Uso.

---

**FIRMAS**

| Responsable del Tratamiento | Encargado del Tratamiento (Tinoprop) |
|---|---|
| Nombre: _________________ | Nombre: _________________ |
| Cargo: _________________ | Cargo: _________________ |
| Fecha: _________________ | Fecha: _________________ |
| Firma: _________________ | Firma: _________________ |

---
*Documento conforme al Anexo 1 de las Directrices 07/2020 del EDPB sobre el concepto de encargado del tratamiento.*
