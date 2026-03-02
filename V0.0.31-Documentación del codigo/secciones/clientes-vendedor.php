<?php
/**
 * ============================================================================
 * ARCHIVO: clientes-vendedor.php
 * SECCIÓN: Gestión de Clientes del equipo Vendedor
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo gestiona los clientes del equipo de ventas (vendedores) dentro
 * del CRM inmobiliario TinoProp. Permite:
 *   - CREAR nuevos clientes vendedores (rellenando un formulario)
 *   - LISTAR todos los clientes vendedores en una tabla o en tarjetas
 *   - ELIMINAR clientes existentes (con confirmación)
 *   - EXPORTAR la lista completa a un archivo CSV (Excel)
 *   - PAGINAR los resultados (mostrar de 15 en 15)
 *
 * ¿CÓMO ESTÁ ORGANIZADO?
 * 1. Primero se carga la configuración base del sistema (bootstrap.php)
 * 2. Si el usuario pide exportar CSV, se genera y descarga el archivo
 * 3. Si el usuario envía un formulario (POST), se procesa la acción:
 *    - crear_cliente: inserta un nuevo cliente en la base de datos
 *    - eliminar_cliente: borra un cliente y sus notas asociadas
 * 4. Se consultan los clientes de la base de datos con paginación
 * 5. Se muestra el HTML: formulario de alta, tabla de datos y tarjetas
 *
 * SEGURIDAD:
 * - csrf_verify() protege contra ataques CSRF (evita que formularios
 *   externos envíen datos maliciosos a nuestra aplicación)
 * - e() escapa texto HTML para prevenir ataques XSS (inyección de código)
 * - sql_iid() filtra por inmobiliaria para que cada agencia solo vea sus datos
 * - sql_uid() filtra por usuario cuando el rol lo requiere
 * - Se usan sentencias preparadas (PDO) para evitar inyección SQL
 *
 * MULTI-TENANT:
 * Cada inmobiliaria tiene su propio espacio aislado. El filtro
 * sql_iid() se añade a TODAS las consultas SQL para garantizar
 * que una agencia nunca pueda ver ni modificar datos de otra.
 */

/* Se carga el archivo bootstrap.php que inicializa todo el sistema:
   sesión del usuario, conexión a base de datos, funciones auxiliares, etc. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Conexión a la base de datos usando PDO (PHP Data Objects).
   $pdo es el objeto que nos permite ejecutar consultas SQL de forma segura. */
$pdo = db();

/* Identificador de esta sección. Se usa para redirigir al usuario
   de vuelta a esta página después de crear o eliminar un cliente. */
$origen = 'clientes-vendedor';

/* Los "flash messages" son mensajes temporales que se muestran UNA sola vez.
   Por ejemplo, después de crear un cliente, se muestra "Cliente creado correctamente"
   y al recargar la página, el mensaje desaparece. */
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ========================================================================
   EXPORTAR A CSV
   ========================================================================
   Si el usuario hace clic en "Exportar CSV", se genera un archivo descargable
   con todos los clientes vendedores. CSV es un formato que se puede abrir
   directamente en Excel o Google Sheets. */
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    $stmt = $pdo->prepare('SELECT nombre, apellido, telefono, email, operacion, created_at FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
    $stmt->execute(['tipo' => 'vendedor'] + sql_iid_params() + sql_uid_params());
    exportar_csv($stmt->fetchAll(PDO::FETCH_ASSOC), 'clientes_vendedor.csv');
}

/* ========================================================================
   CONTROLADOR POST — Procesar formularios enviados por el usuario
   ========================================================================
   Cuando el usuario pulsa "Guardar" o "Eliminar", el navegador envía los
   datos del formulario mediante el método HTTP POST. Aquí los procesamos.
   
   El patrón POST-Redirect-GET (PRG) evita que al recargar la página se
   vuelva a enviar el formulario accidentalmente. Por eso, al final de
   cada acción se hace un header('Location:...') para redirigir. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Verificación CSRF: comprueba que el formulario fue enviado desde
       nuestra propia web y no desde un sitio externo malicioso.
       Si falla, detiene la ejecución por seguridad. */
    csrf_verify();

    /* ----------------------------------------------------------------
       ACCIÓN: CREAR UN NUEVO CLIENTE VENDEDOR
       ----------------------------------------------------------------
       Se ejecuta cuando el usuario rellena el formulario de alta y
       pulsa el botón "Guardar". */
    if (isset($_POST['crear_cliente'])) {
        /* Se recogen los datos del formulario. trim() elimina espacios
           en blanco al inicio y final del texto. El operador ?? '' pone
           un texto vacío si el campo no existe (evita errores). */
        $errores = [];  // Array donde se acumulan mensajes de error
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $operacion = trim($_POST['operacion'] ?? '');
        $operaciones_validas = ['Venta', 'Compra', 'Alquiler'];

        /* VALIDACIÓN: se comprueba que cada campo cumple las reglas.
           Si algún campo no es válido, se añade un mensaje al array $errores.
           Esto evita guardar datos incompletos o incorrectos en la BD. */
        if (!validar_requerido($nombre)) $errores[] = 'El nombre es obligatorio.';
        if (!validar_requerido($apellido)) $errores[] = 'Los apellidos son obligatorios.';
        if (!validar_telefono($telefono)) $errores[] = 'El telefono no es valido.';
        if (!validar_email($email)) $errores[] = 'El email no es valido.';
        if (!validar_enum($operacion, $operaciones_validas)) $errores[] = 'La operacion seleccionada no es valida.';

        /* Si hay errores de validación, se guardan en un mensaje flash
           y se redirige al formulario (#nuevo-cliente) para que el usuario
           los vea y corrija. exit detiene la ejecución del script. */
        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-cliente');
            exit;
        }

        /* INSERCIÓN EN BASE DE DATOS: se usa una sentencia preparada (prepare)
           con parámetros con nombre (:nombre, :apellido, etc.) para evitar
           inyección SQL. Los valores se pasan por separado en execute().
           inmobiliaria_id y usuario_id vinculan al cliente con la agencia
           y el agente que lo creó. */
        $stmt = $pdo->prepare(
            'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, inmobiliaria_id, usuario_id) VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :iid, :uid)'
        );
        $stmt->execute([
            'tipo' => 'vendedor', 'nombre' => $nombre, 'apellido' => $apellido,
            'telefono' => $telefono, 'email' => $email, 'operacion' => $operacion,
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        /* Se registra la acción en el log de actividad para tener un
           historial de quién creó qué y cuándo. lastInsertId() devuelve
           el ID auto-generado del cliente recién insertado. */
        actividad_registrar($pdo, 'crear', 'cliente', (int)$pdo->lastInsertId(), "Nuevo cliente vendedor: $nombre $apellido");
        flash_set('success', 'Cliente creado correctamente.');
    }

    /* ----------------------------------------------------------------
       ACCIÓN: ELIMINAR UN CLIENTE VENDEDOR
       ----------------------------------------------------------------
       Se ejecuta cuando el usuario pulsa "Eliminar" en la tabla.
       Usa una transacción SQL para borrar primero las notas asociadas
       y luego el cliente. Si algo falla, rollBack() deshace todo. */
    if (isset($_POST['eliminar_cliente'])) {
        /* Se convierte el ID a entero para seguridad (evita inyección). */
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            try {
                /* TRANSACCIÓN: agrupa varias operaciones SQL en una sola.
                   Si todas van bien, se confirman con commit().
                   Si alguna falla, catch() ejecuta rollBack() y deshace todo.
                   Esto evita dejar datos huérfanos (notas sin cliente). */
                $pdo->beginTransaction();
                /* Primero borramos las notas asociadas a este cliente */
                $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id" . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                /* Luego borramos el cliente en sí */
                $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id' . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                $pdo->commit();
                actividad_registrar($pdo, 'eliminar', 'cliente', $id_eliminar, 'Cliente vendedor eliminado');
                flash_set('success', 'Cliente eliminado correctamente.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Error al eliminar el cliente. Inténtelo de nuevo.');
            }
        }
    }

    /* Patrón PRG (Post-Redirect-Get): después de procesar el formulario,
       redirigimos al usuario a la misma página con GET para evitar que
       al pulsar F5 se reenvíe el formulario accidentalmente. */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ========================================================================
   PAGINACIÓN — Dividir la lista en páginas de 15 clientes
   ========================================================================
   Primero contamos cuántos clientes vendedores hay en total.
   Después calculamos en qué página estamos y cuántos registros saltar. */
$stmt_total = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid());
$stmt_total->execute(['tipo' => 'vendedor'] + sql_iid_params() + sql_uid_params());
$total_clientes = (int)$stmt_total->fetchColumn();
/* Se asegura que la página sea al menos 1 (evita páginas negativas).
   paginar() calcula: total de páginas, offset (desde qué registro empezar)
   y por_pagina (cuántos mostrar, aquí 15). */
$pagina_actual = max(1, (int)($_GET['pagina'] ?? 1));
$paginacion = paginar($total_clientes, 15, $pagina_actual);

/* CONSULTA PRINCIPAL: obtiene los clientes de la página actual.
   LIMIT limita cuántos resultados devolver (15 por página).
   OFFSET indica desde qué posición empezar (para la página 2, sería 15).
   ORDER BY id DESC muestra los más recientes primero. */
$stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email, operacion FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC LIMIT :lim OFFSET :off');
$stmt->bindValue('tipo', 'vendedor');
foreach (sql_iid_params() as $k => $v) { $stmt->bindValue($k, $v); }
foreach (sql_uid_params() as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue('lim', $paginacion['por_pagina'], PDO::PARAM_INT);
$stmt->bindValue('off', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
/* fetchAll() devuelve todos los resultados como un array de arrays.
   Cada elemento contiene los datos de un cliente (id, nombre, etc.). */
$clientes_db = $stmt->fetchAll();
?>

<!-- =====================================================================
     PARTE VISUAL (HTML) — Lo que el usuario ve en pantalla
     =====================================================================
     A partir de aquí se mezcla PHP con HTML para generar la interfaz.
     PHP genera el contenido dinámico y HTML define la estructura visual. -->

<!-- Botón para mostrar el formulario de crear un nuevo cliente -->
<div class="encabezado_seccion">
    <a href="#nuevo-cliente" class="btn_nuevo_cliente">+ Nuevo Cliente</a>
</div>

<!-- =====================================================================
     FORMULARIO DE ALTA — Crear un nuevo cliente vendedor
     =====================================================================
     Este panel se muestra cuando el usuario hace clic en "+ Nuevo Cliente".
     method="POST" envía los datos al servidor de forma segura.
     data-validar activa la validación JavaScript en el lado del cliente. -->
<div id="nuevo-cliente" class="form_panel">
    <h3>Crear cliente vendedor</h3>
    <!-- Mensajes flash: se muestran si hubo un error o éxito en la operación anterior -->
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid" data-validar>
        <!-- csrf_field() genera un campo oculto con un token de seguridad
             que protege contra ataques CSRF (falsificación de peticiones) -->
        <?php echo csrf_field(); ?>
        <div class="campo_input">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" type="text" required>
        </div>
        <div class="campo_input">
            <label for="apellido">Apellidos</label>
            <input id="apellido" name="apellido" type="text" required>
        </div>
        <div class="campo_input">
            <label for="telefono">Telefono</label>
            <input id="telefono" name="telefono" type="tel" required>
        </div>
        <div class="campo_input">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required>
        </div>
        <div class="campo_input">
            <label for="operacion">Operacion</label>
            <select id="operacion" name="operacion">
                <option value="Venta">Venta</option>
                <option value="Compra">Compra</option>
                <option value="Alquiler">Alquiler</option>
            </select>
        </div>
        <div class="acciones_inline">
            <button type="submit" name="crear_cliente" class="btn_guardar">Guardar</button>
        </div>
    </form>
</div>

<!-- Barra de información: muestra el total de clientes y el botón de exportar -->
<div class="barra_acciones_tabla">
    <span><?php echo $total_clientes; ?> clientes</span>
    <a href="index.php?seccion=<?php echo $origen; ?>&exportar=csv" class="btn_exportar">📥 Exportar CSV</a>
</div>

<!-- Botones para alternar entre vista de tabla y vista de tarjetas.
     La preferencia se guarda en localStorage del navegador. -->
<div class="vista_toggle">
    <button type="button" class="btn_toggle_vista active" data-vista="tabla" onclick="cambiarVista('tabla')">📋 Tabla</button>
    <button type="button" class="btn_toggle_vista" data-vista="tarjetas" onclick="cambiarVista('tarjetas')">🃏 Tarjetas</button>
</div>

<!-- =====================================================================
     VISTA DE TABLA — Lista de clientes en formato tabla
     =====================================================================
     Cada fila muestra los datos de un cliente con botones de acción.
     e() escapa los datos para que no se ejecute código HTML/JS malicioso. -->
<div class="vista_contenido vista_tabla">
<div class="contenedor_tabla">
    <table class="tabla_datos">
        <thead>
            <tr>
                <th class="th_ordenable">Nombre</th>
                <th class="th_ordenable">Apellidos</th>
                <th class="th_ordenable">Teléfono</th>
                <th class="th_ordenable">Email</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <!-- Se recorre el array de clientes con foreach para crear una fila por cada uno -->
            <?php foreach ($clientes_db as $cliente): ?>
            <tr>
                <!-- e() escapa el texto para seguridad XSS -->
                <td><strong><?php echo e($cliente['nombre']); ?></strong></td>
                <td><?php echo e($cliente['apellido']); ?></td>
                <td><?php echo e($cliente['telefono']); ?></td>
                <td><?php echo e($cliente['email']); ?></td>
                <td>
                    <div class="acciones_inline">
                        <!-- Enlace a la ficha detallada del cliente -->
                        <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-vendedor" class="btn_ver_mas">Ver más ➜</a>
                        <!-- Mini-formulario de eliminación. data-confirm muestra
                             un diálogo de confirmación antes de enviar -->
                        <form method="POST" data-confirm="¿Eliminar este cliente? Esta accion no se puede deshacer.">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                            <button type="submit" name="eliminar_cliente" class="btn_peligro">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<!-- =====================================================================
     VISTA DE TARJETAS — Mismos datos pero en formato visual de cards
     =====================================================================
     Oculta por defecto (display:none). Se activa con el botón de toggle.
     Muestra avatar con iniciales, badge de operación, teléfono y email. -->
<div class="vista_contenido vista_tarjetas" style="display:none">
    <div class="tarjetas_grid">
        <?php foreach ($clientes_db as $cliente): ?>
        <div class="tarjeta_cliente_card">
            <div class="tarjeta_cliente_header">
                <div class="tarjeta_avatar"><?php echo mb_strtoupper(mb_substr($cliente['nombre'], 0, 1) . mb_substr($cliente['apellido'] ?? '', 0, 1)); ?></div>
                <div>
                    <strong><?php echo e($cliente['nombre'] . ' ' . ($cliente['apellido'] ?? '')); ?></strong>
                    <span class="badge_estado"><?php echo e($cliente['operacion'] ?? ''); ?></span>
                </div>
            </div>
            <div class="tarjeta_cliente_body">
                <?php if (!empty($cliente['telefono'])): ?><p>📞 <?php echo e($cliente['telefono']); ?></p><?php endif; ?>
                <?php if (!empty($cliente['email'])): ?><p>📧 <?php echo e($cliente['email']); ?></p><?php endif; ?>
            </div>
            <div class="tarjeta_cliente_footer">
                <a href="?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-vendedor" class="btn_secundario btn_chico">Ver más</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Renderiza los controles de paginación (Anterior, 1, 2, 3..., Siguiente) -->
<?php echo renderizar_paginacion($paginacion, 'index.php?seccion=' . $origen); ?>

<!-- =====================================================================
     JAVASCRIPT — Cambiar entre vista tabla y tarjetas
     =====================================================================
     Guarda la preferencia del usuario en localStorage (almacenamiento local
     del navegador) para que se mantenga al volver a la página. -->
<script>
function cambiarVista(vista) {
    document.querySelectorAll('.vista_contenido').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.btn_toggle_vista').forEach(el => el.classList.remove('active'));
    if (vista === 'tabla') {
        document.querySelector('.vista_tabla').style.display = '';
        document.querySelector('[data-vista="tabla"]').classList.add('active');
    } else {
        document.querySelector('.vista_tarjetas').style.display = '';
        document.querySelector('[data-vista="tarjetas"]').classList.add('active');
    }
    localStorage.setItem('tinoprop_vista_clientes', vista);
}
// Restore preference
document.addEventListener('DOMContentLoaded', () => {
    const pref = localStorage.getItem('tinoprop_vista_clientes');
    if (pref === 'tarjetas') cambiarVista('tarjetas');
});
</script>
