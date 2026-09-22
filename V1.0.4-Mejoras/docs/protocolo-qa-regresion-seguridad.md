# Protocolo QA de Regresion y Seguridad (Operativo)

Fecha: 2026-04-01
Alcance: Validar estabilidad funcional, seguridad y consistencia de datos tras aplicar correcciones, sin alterar el dashboard en su comportamiento visual o de negocio.

## 1) Preparacion

Objetivo: Ejecutar pruebas repetibles con trazabilidad.

1. Entorno de prueba
- Base de datos de staging con copia reciente de estructura.
- Datos anonimizados o ficticios controlados para pruebas.
- Usuario Administrador: qa_admin@example.com
- Usuario Comercial A: qa_a@example.com
- Usuario Comercial B: qa_b@example.com

2. Reglas de ejecucion
- Registrar evidencia por caso: captura, respuesta HTTP o extracto de log.
- No cerrar un caso como aprobado sin evidencia.
- Si falla un caso critico, detener release.

3. Criterio de salida
- 100% de casos criticos aprobados.
- 0 bloqueantes abiertos.
- Riesgos medios documentados con plan y fecha.

## 2) Matriz de Casos Ejecutables

### Bloque A: Acceso, sesion y permisos

Caso QA-A01
- Prioridad: Critica
- Objetivo: Verificar login valido.
- Precondicion: Usuario qa_admin activo.
- Datos de prueba: email y password validos.
- Pasos:
1. Abrir pantalla de login.
2. Ingresar credenciales validas.
3. Enviar formulario.
- Resultado esperado:
1. Redireccion al inicio autenticado.
2. Sesion creada.
- Evidencia requerida: Captura de pantalla y cookie/sesion activa.

Caso QA-A02
- Prioridad: Alta
- Objetivo: Bloqueo por intentos fallidos.
- Precondicion: Usuario existente.
- Datos de prueba: password incorrecta repetida.
- Pasos:
1. Fallar login consecutivamente hasta limite.
2. Reintentar antes del tiempo de desbloqueo.
- Resultado esperado:
1. Mensaje de bloqueo temporal.
2. No permite acceso hasta expirar bloqueo.
- Evidencia requerida: Captura del mensaje de bloqueo.

Caso QA-A03
- Prioridad: Critica
- Objetivo: Restriccion de secciones admin.
- Precondicion: Usuario qa_a no admin.
- Datos de prueba: URL de modulo admin.
- Pasos:
1. Iniciar sesion con qa_a.
2. Acceder directo a rutas de admin.
- Resultado esperado:
1. Acceso denegado o redireccion segura.
- Evidencia requerida: Captura y codigo de estado.

### Bloque B: Acciones destructivas y CSRF

Caso QA-B01
- Prioridad: Critica
- Objetivo: Impedir borrado por GET.
- Precondicion: Registro de prueba existente (cliente/prospecto/tarea).
- Datos de prueba: URL con parametros id y token en query.
- Pasos:
1. Ejecutar borrado via URL directa.
- Resultado esperado:
1. Operacion rechazada.
2. Registro permanece en base.
- Evidencia requerida: Captura y consulta posterior.

Caso QA-B02
- Prioridad: Critica
- Objetivo: Permitir borrado solo por POST con CSRF valido.
- Precondicion: Sesion activa y token vigente.
- Datos de prueba: formulario legitimo.
- Pasos:
1. Ejecutar borrado desde UI autorizada.
- Resultado esperado:
1. Borrado exitoso.
2. Log de actividad generado.
- Evidencia requerida: Captura + registro en actividad.

Caso QA-B03
- Prioridad: Critica
- Objetivo: Bloquear POST sin token o con token invalido.
- Precondicion: Sesion activa.
- Datos de prueba: peticion modificada.
- Pasos:
1. Reenviar solicitud con token vacio/invalido.
- Resultado esperado:
1. Rechazo por seguridad.
2. Sin cambios en BD.
- Evidencia requerida: Respuesta HTTP y verificacion de datos.

### Bloque C: API Prospectos (autorizacion por recurso)

Caso QA-C01
- Prioridad: Critica
- Objetivo: Evitar edicion de recurso ajeno.
- Precondicion: Prospecto asignado a usuario A.
- Datos de prueba: Usuario B con id de prospecto de A.
- Pasos:
1. Iniciar sesion con qa_b.
2. Invocar endpoint de edicion con id de A.
- Resultado esperado:
1. Rechazo por permisos.
2. Campo no modificado.
- Evidencia requerida: Respuesta API y lectura del valor final.

Caso QA-C02
- Prioridad: Alta
- Objetivo: Evitar lectura de historial ajeno.
- Precondicion: Historial existente de prospecto de A.
- Datos de prueba: qa_b consultando ese id.
- Pasos:
1. Invocar endpoint de historial con qa_b.
- Resultado esperado:
1. Rechazo o respuesta vacia segun politica definida.
- Evidencia requerida: Payload de respuesta.

Caso QA-C03
- Prioridad: Alta
- Objetivo: Mantener funcionalidad legitima.
- Precondicion: Prospecto propio de qa_a.
- Datos de prueba: actualizacion de un campo permitido.
- Pasos:
1. qa_a actualiza campo valido.
- Resultado esperado:
1. Exito y persistencia del cambio.
- Evidencia requerida: Respuesta API y consulta en BD.

### Bloque D: Chat publico

Caso QA-D01
- Prioridad: Critica
- Objetivo: Crear conversacion legitima desde widget.
- Precondicion: Chat activo.
- Datos de prueba: visitante nuevo.
- Pasos:
1. Abrir widget.
2. Iniciar conversacion.
3. Enviar mensaje.
- Resultado esperado:
1. Conversacion creada.
2. Mensaje persistido.
- Evidencia requerida: Captura UI y registro en tabla.

Caso QA-D02
- Prioridad: Critica
- Objetivo: Bloquear abuso por identificador arbitrario.
- Precondicion: Endpoint accesible publico.
- Datos de prueba: script externo con visitor_id manipulado.
- Pasos:
1. Simular peticiones con visitor_id inventado.
2. Simular lectura de mensajes de id no propio.
- Resultado esperado:
1. Rechazo o mitigacion segun hardening aplicado.
- Evidencia requerida: Respuesta API y logs de seguridad.

Caso QA-D03
- Prioridad: Alta
- Objetivo: Validar mitigacion de rafagas.
- Precondicion: Control de rate limit habilitado.
- Datos de prueba: multiples mensajes en corto intervalo.
- Pasos:
1. Enviar rafaga de solicitudes.
- Resultado esperado:
1. Limitacion aplicada.
2. No degradacion critica.
- Evidencia requerida: tiempos y codigos de respuesta.

### Bloque E: Cron de backup

Caso QA-E01
- Prioridad: Critica
- Objetivo: Denegar ejecucion sin clave.
- Precondicion: Endpoint de cron publicado.
- Datos de prueba: request sin key y key incorrecta.
- Pasos:
1. Llamar endpoint sin key.
2. Llamar con key invalida.
- Resultado esperado:
1. Rechazo en ambos casos.
- Evidencia requerida: respuesta HTTP y log.

Caso QA-E02
- Prioridad: Critica
- Objetivo: Permitir ejecucion con secreto correcto.
- Precondicion: secreto configurado por entorno.
- Datos de prueba: key valida.
- Pasos:
1. Ejecutar endpoint con key valida.
- Resultado esperado:
1. Backup creado.
2. Rotacion de backups aplicada.
- Evidencia requerida: nombre de backup y tamano.

### Bloque F: Integridad de datos y regresion funcional

Caso QA-F01
- Prioridad: Alta
- Objetivo: Flujo comercial minimo.
- Precondicion: datos base cargados.
- Datos de prueba: cliente y prospecto de prueba.
- Pasos:
1. Crear prospecto.
2. Editar campos clave.
3. Convertir o vincular a cliente.
4. Crear tarea y seguimiento.
- Resultado esperado:
1. Flujo completo sin errores.
2. Datos consistentes entre modulos.
- Evidencia requerida: capturas por hito.

Caso QA-F02
- Prioridad: Alta
- Objetivo: Validar listados, filtros y paginacion.
- Precondicion: volumen minimo de registros.
- Datos de prueba: busquedas por nombre/email/telefono.
- Pasos:
1. Aplicar filtros.
2. Navegar paginacion.
3. Limpiar filtros.
- Resultado esperado:
1. Resultados coherentes y estables.
- Evidencia requerida: capturas antes/despues.

Caso QA-F03
- Prioridad: Media
- Objetivo: Revisar errores de navegador y backend.
- Precondicion: consola y logs habilitados.
- Datos de prueba: navegacion por modulos principales.
- Pasos:
1. Abrir modulos clave.
2. Revisar consola.
3. Revisar logs de aplicacion.
- Resultado esperado:
1. Sin errores JS/PHP bloqueantes.
- Evidencia requerida: captura de consola limpia y extracto de logs.

## 3) Plantilla de Ejecucion por Caso

Copiar este bloque por cada caso ejecutado:

- Caso ID:
- Build/Version:
- Entorno:
- Tester:
- Fecha/Hora:
- Estado: Aprobado | Fallido | Bloqueado
- Evidencia: enlace a captura/log
- Observaciones:
- Defecto asociado (si aplica):

## 4) Regla de Severidad de Defectos

- Critica: Riesgo de seguridad o perdida de datos. Bloquea release.
- Alta: Rompe proceso principal sin workaround aceptable.
- Media: Afecta productividad o calidad, con workaround.
- Baja: detalle cosmetico o menor sin impacto operativo.

## 5) Gate de Release

Liberar solo si se cumple todo:

1. Todos los casos criticos en estado Aprobado.
2. Sin defectos criticos o altos abiertos.
3. Evidencia completa en repositorio de QA.
4. Aprobacion final de responsable tecnico.
