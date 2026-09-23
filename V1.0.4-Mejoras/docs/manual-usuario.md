# Manual de Usuario - Tinoprop

**Version:** 1.2.0
**Ultima actualizacion:** Marzo 2026

---

## Tabla de Contenidos

1. [Introduccion](#introduccion)
2. [Acceso al Sistema](#acceso-al-sistema)
3. [Dashboard](#dashboard)
4. [Modulo de Clientes](#modulo-de-clientes)
5. [Modulo de Propiedades](#modulo-de-propiedades)
6. [Modulo de Visitas](#modulo-de-visitas)
7. [Modulo de Tareas](#modulo-de-tareas)
8. [Pipeline / Kanban](#pipeline--kanban)
9. [Automatizaciones](#automatizaciones)
10. [Contratos](#contratos)
11. [Presupuestos](#presupuestos)
12. [Facturas](#facturas)
13. [Blog](#blog)
14. [Conversaciones](#conversaciones)
15. [Funnels](#funnels)
16. [Formularios](#formularios)
17. [Documentos](#documentos)
18. [Ajustes](#ajustes)
19. [Modo Oscuro](#modo-oscuro)
20. [Preguntas Frecuentes](#preguntas-frecuentes)

---

## Introduccion

Tinoprop es un sistema de gestion de relaciones con clientes (CRM) diseñado especificamente para el sector inmobiliario español. Permite gestionar propiedades, clientes, visitas, contratos, facturacion y mucho mas desde una interfaz web moderna y responsive.

El sistema esta optimizado para agencias inmobiliarias de cualquier tamaño y cumple con la normativa RGPD/LOPD vigente en España.

---

## Acceso al Sistema

### Inicio de Sesion

1. Acceda a la URL de su instalacion (ej: `https://sudominio.com/login.php`).
2. Introduzca su **correo electronico** y **contraseña**.
3. Pulse el boton **Iniciar Sesion**.

### Seguridad del Login

- Tras **5 intentos fallidos**, la cuenta se bloquea durante **15 minutos** por seguridad.
- Las sesiones expiran automaticamente tras **8 horas** de inactividad.
- Si detecta un acceso no autorizado, contacte con el administrador inmediatamente.

### Cierre de Sesion

Haga clic en su nombre de usuario en la esquina superior derecha y seleccione **Cerrar Sesion**. Esto destruira la sesion de forma segura.

---

## Dashboard

El panel principal muestra un resumen de la actividad del CRM:

### Tarjetas de Resumen
- **Total de clientes** registrados en el sistema.
- **Propiedades activas** disponibles para venta o alquiler.
- **Visitas programadas** pendientes para los proximos dias.
- **Tareas pendientes** asignadas al usuario actual.
- **Facturas por cobrar** y su importe total.
- **Ingresos del mes** y comparativa con meses anteriores.

### Graficos y Estadisticas
- Grafico de ventas/alquileres por mes.
- Distribucion de propiedades por tipo (piso, casa, chalet, etc.).
- Pipeline de oportunidades con estados.
- Actividad reciente del equipo.

### Accesos Rapidos
Desde el dashboard puede acceder rapidamente a:
- Crear un nuevo cliente.
- Añadir una nueva propiedad.
- Programar una visita.
- Ver tareas urgentes.

---

## Modulo de Clientes

### Listado de Clientes
Acceda desde el menu lateral: **Clientes**. Vera una tabla con todos los clientes registrados, incluyendo nombre, telefono, email, tipo de operacion que busca y fecha de registro.

### Busqueda y Filtros
- Use la barra de busqueda para buscar por nombre, email o telefono.
- Filtre por tipo de cliente (comprador, vendedor, inquilino, propietario).
- Filtre por provincia, rango de presupuesto o estado.

### Crear un Nuevo Cliente
1. Pulse **+ Nuevo Cliente**.
2. Rellene los campos obligatorios: nombre, apellidos, email, telefono.
3. Seleccione el tipo de cliente y la operacion que busca.
4. Añada notas internas si lo desea.
5. Marque la casilla de consentimiento RGPD si el cliente lo ha dado.
6. Pulse **Guardar**.

### Ficha del Cliente
Al hacer clic en un cliente se accede a su ficha completa con:
- **Datos personales** y de contacto.
- **Propiedades asociadas** (como propietario o como interesado).
- **Historial de visitas** realizadas.
- **Conversaciones** y notas.
- **Documentos** adjuntos.
- **Actividad** reciente.

### Editar y Eliminar
- Pulse el icono de lapiz para editar los datos.
- Pulse el icono de papelera para eliminar (requiere confirmacion).

---

## Modulo de Propiedades

### Listado de Propiedades
Acceda desde el menu lateral: **Propiedades**. Vera todas las propiedades con su referencia, titulo, tipo, operacion, precio y estado.

### Crear una Nueva Propiedad
1. Pulse **+ Nueva Propiedad**.
2. Se generara una referencia unica automaticamente (ej: `INM-A3B2C1`).
3. Rellene los datos basicos: titulo, tipo de inmueble, operacion (venta/alquiler), precio.
4. Complete los detalles: superficie (construida, util, parcela), habitaciones, baños, planta.
5. Indique la ubicacion: direccion, poblacion, provincia, codigo postal.
6. Añada caracteristicas: ascensor, garaje, piscina, terraza, aire acondicionado, etc.
7. Suba fotografias (formatos JPG, PNG, WebP; maximo 10MB por imagen).
8. Redacte la descripcion del inmueble.
9. Asigne un agente responsable.
10. Pulse **Guardar**.

### Estados de Propiedad
- **Disponible:** En activo para venta o alquiler.
- **Reservado:** Señalizada por un comprador/inquilino.
- **Vendido/Alquilado:** Operacion cerrada.
- **Retirado:** Retirada del mercado temporalmente.

### Galeria de Imagenes
- Suba multiples imagenes desde la ficha de la propiedad.
- Arrastre para reordenar las imagenes.
- La primera imagen sera la foto principal.
- Las imagenes se redimensionan automaticamente a un maximo de 1920x1080px.

### Tipos de Propiedad Soportados
Piso, Casa, Chalet, Adosado, Atico, Duplex, Estudio, Local Comercial, Oficina, Nave Industrial, Terreno, Garaje, Trastero, Edificio y Otros.

---

## Modulo de Visitas

### Programar una Visita
1. Acceda a **Visitas > + Nueva Visita**.
2. Seleccione el **cliente** que realizara la visita.
3. Seleccione la **propiedad** a visitar.
4. Establezca la **fecha y hora**.
5. Asigne el **agente** que acompañara.
6. Añada notas internas si lo desea.
7. Pulse **Guardar**.

### Calendario de Visitas
El sistema muestra las visitas en formato calendario (dia, semana, mes). Haga clic en cualquier dia para ver el detalle de las visitas programadas.

### Estados de Visita
- **Programada:** Visita pendiente de realizarse.
- **Realizada:** Visita completada.
- **Cancelada:** Visita cancelada por el cliente o el agente.
- **No presentado:** El cliente no acudio a la cita.

### Valoracion Post-Visita
Tras realizar una visita, el agente puede registrar la valoracion del cliente sobre el inmueble, añadir comentarios y marcar el nivel de interes.

---

## Modulo de Tareas

### Crear una Tarea
1. Acceda a **Tareas > + Nueva Tarea**.
2. Escriba el titulo y descripcion de la tarea.
3. Seleccione la prioridad (baja, media, alta, urgente).
4. Establezca la fecha de vencimiento.
5. Asigne la tarea a un usuario del equipo.
6. Opcionalmente, vincule la tarea a un cliente o propiedad.
7. Pulse **Guardar**.

### Vista de Tareas
- **Lista:** Vista clasica en formato tabla.
- **Kanban:** Arrastre las tareas entre columnas (Pendiente, En Progreso, Completada).

### Notificaciones de Tareas
El sistema notifica automaticamente cuando:
- Se le asigna una nueva tarea.
- Una tarea esta proxima a vencer.
- Una tarea ha sido completada o actualizada.

---

## Pipeline / Kanban

### Funcionamiento
El pipeline muestra las oportunidades de negocio en formato Kanban con columnas personalizables. Cada tarjeta representa una oportunidad con cliente, propiedad e importe asociados.

### Columnas por Defecto
1. **Nuevo Lead:** Contacto inicial recibido.
2. **Contactado:** Se ha establecido comunicacion.
3. **Visita Programada:** Se ha agendado una visita.
4. **Negociacion:** En proceso de negociacion de precio/condiciones.
5. **Propuesta Enviada:** Se ha enviado presupuesto o propuesta.
6. **Cerrado Ganado:** Operacion completada con exito.
7. **Cerrado Perdido:** Oportunidad descartada.

### Mover Oportunidades
Arrastre las tarjetas entre columnas para actualizar su estado. El cambio se guarda automaticamente.

### Informacion de la Tarjeta
Cada tarjeta muestra: nombre del cliente, propiedad de interes, importe estimado, agente asignado y dias en esa etapa.

---

## Automatizaciones

### Descripcion General
Las automatizaciones permiten ejecutar acciones de forma automatica cuando se cumplen determinadas condiciones, ahorrando tiempo en tareas repetitivas.

### Crear una Automatizacion
1. Acceda a **Automatizaciones > + Nueva Automatizacion**.
2. Defina el **trigger** (evento disparador):
   - Nuevo cliente registrado.
   - Propiedad creada o actualizada.
   - Visita completada.
   - Tarea vencida.
   - Cambio de etapa en pipeline.
3. Establezca las **condiciones** (filtros opcionales).
4. Seleccione la **accion** a ejecutar:
   - Enviar email automatico.
   - Crear tarea.
   - Mover en pipeline.
   - Enviar notificacion.
   - Asignar agente.
5. Pulse **Guardar y Activar**.

### Ejemplos de Automatizaciones Utiles
- Enviar email de bienvenida cuando se registra un nuevo cliente.
- Crear tarea de seguimiento 3 dias despues de una visita.
- Notificar al jefe de equipo cuando una oportunidad supera los 500.000 EUR.
- Enviar recordatorio 24h antes de una visita programada.

---

## Contratos

### Tipos de Contrato
- Contrato de compraventa.
- Contrato de arrendamiento.
- Contrato de arras.
- Mandato de venta (encargo de gestion).
- Contrato de exclusividad.

### Crear un Contrato
1. Acceda a **Contratos > + Nuevo Contrato**.
2. Seleccione el tipo de contrato.
3. Vincule el cliente y la propiedad.
4. Rellene los datos especificos (precio, duracion, condiciones).
5. Suba el documento firmado en formato PDF si lo tiene.
6. Pulse **Guardar**.

### Estados del Contrato
- **Borrador:** En preparacion.
- **Enviado:** Remitido al cliente para firma.
- **Firmado:** Contrato en vigor.
- **Vencido:** Contrato finalizado por plazo.
- **Cancelado:** Contrato rescindido.

---

## Presupuestos

### Crear un Presupuesto
1. Acceda a **Presupuestos > + Nuevo Presupuesto**.
2. Seleccione el cliente destinatario.
3. Añada las lineas del presupuesto (concepto, cantidad, precio unitario, IVA).
4. Establezca la fecha de validez.
5. Añada notas o condiciones especiales.
6. Pulse **Guardar**.

### Enviar Presupuesto
Desde la ficha del presupuesto, pulse **Enviar por Email** para remitirlo directamente al cliente. Se genera un enlace publico donde el cliente puede ver y aceptar el presupuesto.

### Convertir a Factura
Cuando un presupuesto es aceptado, pulse **Convertir a Factura** para generar automaticamente una factura con los mismos datos.

---

## Facturas

### Crear una Factura
1. Acceda a **Facturas > + Nueva Factura** (o convierta un presupuesto).
2. Seleccione el cliente.
3. Añada los conceptos facturados con cantidad, precio e IVA.
4. Establezca la fecha de emision y la fecha de vencimiento.
5. Pulse **Guardar**.

### Estados de Factura
- **Borrador:** En preparacion.
- **Enviada:** Remitida al cliente.
- **Pagada:** Cobro confirmado.
- **Vencida:** No pagada en plazo.
- **Cancelada:** Factura anulada.

### Descargar e Imprimir
Pulse **Descargar PDF** para obtener la factura en formato PDF con el diseño corporativo de su agencia.

---

## Blog

### Publicar un Articulo
1. Acceda a **Blog > + Nuevo Articulo**.
2. Escriba el titulo y el contenido del articulo.
3. Seleccione la categoria y añada etiquetas.
4. Suba una imagen destacada.
5. Elija si publicar inmediatamente o programar la publicacion.
6. Pulse **Publicar**.

### Gestion de Categorias
Desde **Blog > Categorias** puede crear, editar y eliminar categorias para organizar sus articulos.

### SEO
Cada articulo permite configurar el titulo SEO, la meta-descripcion y el slug personalizado de la URL.

---

## Conversaciones

### Descripcion
El modulo de conversaciones centraliza todas las comunicaciones con clientes: emails, notas internas y mensajes.

### Iniciar una Conversacion
1. Acceda a la ficha de un cliente.
2. Pulse la pestaña **Conversaciones**.
3. Escriba su mensaje o nota.
4. Seleccione si es una **nota interna** (solo visible para el equipo) o un **mensaje al cliente**.
5. Pulse **Enviar**.

### Historial
Todas las conversaciones quedan registradas cronologicamente, permitiendo a cualquier miembro del equipo conocer el historial de comunicaciones con cada cliente.

---

## Funnels

### Descripcion
Los funnels (embudos de captacion) permiten diseñar flujos de captacion de leads con paginas secuenciales orientadas a la conversion.

### Crear un Funnel
1. Acceda a **Funnels > + Nuevo Funnel**.
2. Defina el nombre y el objetivo del funnel.
3. Añada las paginas o pasos del embudo.
4. Configure las acciones de conversion (formulario de contacto, descarga, etc.).
5. Publique el funnel y obtenga la URL publica.

### Metricas
El sistema registra las metricas de cada paso: visitas, conversiones y tasa de abandono.

---

## Formularios

### Crear un Formulario
1. Acceda a **Formularios > + Nuevo Formulario**.
2. Añada los campos deseados (nombre, email, telefono, mensaje, etc.).
3. Configure la accion al enviar (crear cliente, enviar notificacion, asignar a agente).
4. Copie el codigo de incrustacion para insertarlo en su web.
5. Pulse **Guardar**.

### Formularios Embebidos
Los formularios generan un codigo HTML que puede pegarse en cualquier pagina web externa. Los envios se registran automaticamente en el CRM.

---

## Documentos

### Subir Documentos
1. Acceda a **Documentos > + Subir Documento**.
2. Seleccione el archivo (PDF, DOC, DOCX, JPG, PNG, WebP; maximo 10MB).
3. Asocie el documento a un cliente, propiedad o contrato.
4. Añada una descripcion.
5. Pulse **Subir**.

### Organizacion
Los documentos se pueden filtrar por tipo, cliente o propiedad asociada. Use la barra de busqueda para localizar documentos rapidamente.

---

## Ajustes

### Acceso
Solo los usuarios con rol **Administrador** pueden acceder a la seccion de Ajustes.

### Whitelabel (Marca Blanca)
Personalice la apariencia del CRM:
- **Nombre de la aplicacion:** Cambie "Tinoprop" por el nombre de su agencia.
- **Logo:** Suba su logotipo (aparecera en el menu lateral y en el login).
- **Favicon:** Suba el icono que aparece en la pestaña del navegador.
- **Color primario:** Cambie el color principal de la interfaz (botones, enlaces, menu activo).
- **Color secundario:** Color del sidebar y elementos secundarios.
- **Color de acento:** Color para resaltar elementos importantes.
- **CSS personalizado:** Añada reglas CSS adicionales para personalizacion avanzada.

Los cambios de colores se aplican dinamicamente en toda la interfaz mediante inyeccion de variables CSS.

### Usuarios
- Crear, editar y desactivar cuentas de usuario.
- Asignar roles: **Administrador** (acceso total) o **Agente** (acceso limitado a sus datos).
- Resetear contraseñas.

### Roles y Permisos
- **Admin:** Acceso completo a todos los modulos y ajustes.
- **Agente:** Acceso a clientes, propiedades, visitas y tareas asignadas. Sin acceso a ajustes del sistema.

### API Keys
Genere y gestione claves API para integrar el CRM con sistemas externos. Cada clave tiene permisos configurables.

### Integraciones
Configure integraciones con servicios de terceros como portales inmobiliarios, pasarelas de pago y herramientas de marketing.

### Plantillas de Email
Cree y edite plantillas de email reutilizables para comunicaciones automaticas y manuales con clientes.

### Backup
- Genere copias de seguridad de la base de datos desde el panel.
- Las copias se almacenan en la carpeta `backups/`.
- Descargue o restaure copias anteriores.

---

## Modo Oscuro

### Activar/Desactivar
Haga clic en el icono de **sol/luna** en la barra superior para alternar entre el modo claro y el modo oscuro. La preferencia se guarda en el navegador.

### Funcionamiento
El modo oscuro cambia la paleta de colores de fondo y texto para reducir la fatiga visual en condiciones de poca luz. Los colores corporativos (whitelabel) se adaptan automaticamente al modo oscuro.

---

## Preguntas Frecuentes

### No puedo iniciar sesion
- Verifique que esta usando el email correcto.
- Compruebe que no tiene activado el bloqueo Mayusculas.
- Si ha superado 5 intentos fallidos, espere 15 minutos.
- Contacte al administrador para restablecer su contraseña.

### No veo todos los modulos
Su cuenta puede tener rol de **Agente**, que tiene acceso limitado. Solicite al administrador que revise sus permisos.

### Las imagenes no se suben
- Verifique que el archivo es JPG, PNG o WebP.
- Compruebe que no supera los 10MB.
- Si el problema persiste, contacte al administrador para verificar los permisos de la carpeta `assets/uploads/`.

### Como exportar datos
Desde los listados de clientes, propiedades y otros modulos, busque el boton **Exportar** para descargar los datos en formato CSV o Excel.

### Como contactar con el soporte tecnico
Contacte con el administrador del sistema o con el equipo de soporte tecnico a traves del email configurado en los ajustes.

---

*Tinoprop v1.2.0 - Todos los derechos reservados.*
