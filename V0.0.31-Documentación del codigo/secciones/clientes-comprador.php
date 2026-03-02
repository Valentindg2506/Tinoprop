<?php
/**
 * ============================================================================
 * ARCHIVO: clientes-comprador.php
 * SECCIÓN: Gestión de Clientes del equipo Comprador
 * ============================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo gestiona los clientes del equipo de compradores dentro
 * del CRM inmobiliario TinoProp. Permite:
 *   - CREAR nuevos clientes compradores (rellenando un formulario)
 *   - LISTAR todos los clientes compradores en una tabla
 *   - ELIMINAR clientes existentes (con confirmación)
 *   - EXPORTAR la lista completa a un archivo CSV (compatible con Excel)
 *   - PAGINAR los resultados (mostrar de 15 en 15)
 *
 * DIFERENCIA CON clientes-vendedor.php:
 * La estructura es idéntica, pero este archivo filtra por tipo='comprador'.
 * Los compradores son personas interesadas en adquirir inmuebles.
 * Los vendedores son propietarios que quieren vender sus inmuebles.
 *
 * SEGURIDAD:
 * - csrf_verify() protege contra ataques CSRF
 * - e() escapa texto HTML para prevenir ataques XSS
 * - sql_iid() filtra por inmobiliaria (aislamiento multi-tenant)
 * - sql_uid() filtra por usuario según el rol
 * - Sentencias preparadas PDO contra inyección SQL
 */

/* Se carga el archivo bootstrap.php que inicializa todo el sistema:
   sesión del usuario, conexión a base de datos, funciones auxiliares, etc. */
require_once __DIR__ . '/../inc/bootstrap.php';

/* Conexión a la base de datos. $pdo permite ejecutar consultas SQL seguras. */
$pdo = db();

/* Identificador de esta sección, usado para las redirecciones tras POST. */
$origen = 'clientes-comprador';

/* Mensajes flash: se muestran una sola vez tras una operación (crear/eliminar). */
$mensaje_error = flash_get('error');
$mensaje_exito = flash_get('success');

/* ========================================================================
   EXPORTAR A CSV
   ========================================================================
   Genera un archivo descargable con todos los clientes compradores.
   Útil para que los agentes trabajen con los datos en Excel. */
// Exportar CSV
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    $stmt = $pdo->prepare('SELECT nombre, apellido, telefono, email, operacion, created_at FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC');
    $stmt->execute(['tipo' => 'comprador'] + sql_iid_params() + sql_uid_params());
    exportar_csv($stmt->fetchAll(PDO::FETCH_ASSOC), 'clientes_comprador.csv');
}

/* ========================================================================
   CONTROLADOR POST — Procesar formularios enviados por el usuario
   ========================================================================
   Detecta si la petición es POST (envío de formulario) y ejecuta
   la acción correspondiente: crear o eliminar un cliente comprador.
   Sigue el patrón PRG (Post-Redirect-Get) para evitar reenvíos. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Verificación CSRF: asegura que el formulario fue enviado
       desde nuestro propio sitio web, no desde uno externo. */
    csrf_verify();

    /* ----------------------------------------------------------------
       ACCIÓN: CREAR UN NUEVO CLIENTE COMPRADOR
       ---------------------------------------------------------------- */
    if (isset($_POST['crear_cliente'])) {
        /* Se recogen y limpian los datos del formulario.
           trim() elimina espacios sobrantes; ?? '' pone valor por defecto. */
        $errores = [];
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $operacion = trim($_POST['operacion'] ?? '');
        $operaciones_validas = ['Venta', 'Compra', 'Alquiler'];

        /* VALIDACIÓN: se comprueba que todos los campos obligatorios estén
           correctos antes de guardar en la base de datos. */
        if (!validar_requerido($nombre)) $errores[] = 'El nombre es obligatorio.';
        if (!validar_requerido($apellido)) $errores[] = 'Los apellidos son obligatorios.';
        if (!validar_telefono($telefono)) $errores[] = 'El telefono no es valido.';
        if (!validar_email($email)) $errores[] = 'El email no es valido.';
        if (!validar_enum($operacion, $operaciones_validas)) $errores[] = 'La operacion seleccionada no es valida.';

        /* Si hay errores, se guardan en un flash message y se redirige
           al formulario para que el usuario corrija los datos. */
        if (!empty($errores)) {
            flash_set('error', implode(' ', $errores));
            header('Location: index.php?seccion=' . $origen . '#nuevo-cliente');
            exit;
        }

        /* INSERCIÓN EN BD: sentencia preparada con parámetros con nombre
           para prevenir inyección SQL. El tipo se fija como 'comprador'.
           inmobiliaria_id y usuario_id vinculan al cliente con su agencia y agente. */
        $stmt = $pdo->prepare(
            'INSERT INTO clientes (tipo, nombre, apellido, telefono, email, operacion, inmobiliaria_id, usuario_id) VALUES (:tipo, :nombre, :apellido, :telefono, :email, :operacion, :iid, :uid)'
        );
        $stmt->execute([
            'tipo' => 'comprador', 'nombre' => $nombre, 'apellido' => $apellido,
            'telefono' => $telefono, 'email' => $email, 'operacion' => $operacion,
            'iid' => usuario_inmobiliaria_id(),
            'uid' => $_SESSION['usuario']['id'] ?? null,
        ]);
        /* Se registra la creación en el log de actividad del sistema. */
        actividad_registrar($pdo, 'crear', 'cliente', (int)$pdo->lastInsertId(), "Nuevo cliente comprador: $nombre $apellido");
        flash_set('success', 'Cliente creado correctamente.');
    }

    /* ----------------------------------------------------------------
       ACCIÓN: ELIMINAR UN CLIENTE COMPRADOR
       ----------------------------------------------------------------
       Borra el cliente y sus notas asociadas dentro de una transacción.
       Si algo falla, rollBack() deshace los cambios para no dejar
       datos inconsistentes. */
    if (isset($_POST['eliminar_cliente'])) {
        /* Se convierte a entero para seguridad. */
        $id_eliminar = (int) ($_POST['id'] ?? 0);
        if ($id_eliminar > 0) {
            try {
                /* TRANSACCIÓN: operaciones atómicas — o se ejecutan todas o ninguna */
                $pdo->beginTransaction();
                /* Primero eliminamos las notas asociadas al cliente */
                $stmt = $pdo->prepare("DELETE FROM notas WHERE entity_type = 'cliente' AND entity_id = :id" . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                /* Después eliminamos el registro del cliente */
                $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = :id' . sql_iid());
                $stmt->execute(['id' => $id_eliminar] + sql_iid_params());
                $pdo->commit();
                actividad_registrar($pdo, 'eliminar', 'cliente', $id_eliminar, 'Cliente comprador eliminado');
                flash_set('success', 'Cliente eliminado correctamente.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Error al eliminar el cliente. Inténtelo de nuevo.');
            }
        }
    }

    /* Patrón PRG: redirigir después de POST para evitar reenvíos accidentales. */
    header('Location: index.php?seccion=' . $origen);
    exit;
}

/* ========================================================================
   PAGINACIÓN — Dividir la lista en páginas de 15 clientes
   ========================================================================
   Se cuenta el total, se calcula la página actual y se obtienen
   solo los registros correspondientes a esa página. */
$stmt_total = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid());
$stmt_total->execute(['tipo' => 'comprador'] + sql_iid_params() + sql_uid_params());
$total_clientes = (int)$stmt_total->fetchColumn();
$pagina_actual = max(1, (int)($_GET['pagina'] ?? 1));
$paginacion = paginar($total_clientes, 15, $pagina_actual);

/* CONSULTA PRINCIPAL: obtiene los clientes compradores de la página actual.
   LIMIT :lim = cuántos mostrar, OFFSET :off = desde qué posición.
   ORDER BY id DESC = los más recientes primero. */
$stmt = $pdo->prepare('SELECT id, nombre, apellido, telefono, email FROM clientes WHERE tipo = :tipo' . sql_iid() . sql_uid() . ' ORDER BY id DESC LIMIT :lim OFFSET :off');
$stmt->bindValue('tipo', 'comprador');
foreach (sql_iid_params() as $k => $v) { $stmt->bindValue($k, $v); }
foreach (sql_uid_params() as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue('lim', $paginacion['por_pagina'], PDO::PARAM_INT);
$stmt->bindValue('off', $paginacion['offset'], PDO::PARAM_INT);
$stmt->execute();
$clientes_db = $stmt->fetchAll();
?>

<!-- =====================================================================
     PARTE VISUAL (HTML) — Interfaz de usuario
     ===================================================================== -->

<!-- Botón para mostrar el formulario de alta de nuevo cliente comprador -->
<div class="encabezado_seccion">
    <a href="#nuevo-cliente" class="btn_nuevo_cliente">+ Nuevo Cliente</a>
</div>

<!-- =====================================================================
     FORMULARIO DE ALTA — Crear un nuevo cliente comprador
     =====================================================================
     method="POST" asegura que los datos se envíen de forma segura. -->
<div id="nuevo-cliente" class="form_panel">
    <h3>Crear cliente comprador</h3>
    <!-- Mensajes flash de error y éxito de la operación anterior -->
    <?php if ($mensaje_error): ?>
        <div class="alerta_error"><?php echo e($mensaje_error); ?></div>
    <?php endif; ?>
    <?php if ($mensaje_exito): ?>
        <div class="alerta_exito"><?php echo e($mensaje_exito); ?></div>
    <?php endif; ?>
    <form method="POST" class="form_grid" data-validar>
        <!-- Token CSRF oculto para protección de seguridad -->
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

<!-- Barra de información con total de clientes y botón de exportar CSV -->
<div class="barra_acciones_tabla">
    <span><?php echo $total_clientes; ?> clientes</span>
    <a href="index.php?seccion=<?php echo $origen; ?>&exportar=csv" class="btn_exportar">📥 Exportar CSV</a>
</div>

<!-- =====================================================================
     TABLA DE CLIENTES COMPRADORES
     =====================================================================
     Muestra cada cliente en una fila con sus datos y botones de acción.
     e() escapa todo el texto para evitar ataques XSS. -->
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
            <!-- Se recorre cada cliente para generar una fila en la tabla -->
            <?php foreach ($clientes_db as $cliente): ?>
            <tr>
                <td><strong><?php echo e($cliente['nombre']); ?></strong></td>
                <td><?php echo e($cliente['apellido']); ?></td>
                <td><?php echo e($cliente['telefono']); ?></td>
                <td><?php echo e($cliente['email']); ?></td>
                <td>
                    <div class="acciones_inline">
                        <!-- Enlace a la ficha detallada del cliente -->
                        <a href="index.php?seccion=ver_cliente&id=<?php echo $cliente['id']; ?>&origen=clientes-comprador" class="btn_ver_mas">Ver más ➜</a>
                        <!-- Formulario de eliminación con confirmación JavaScript -->
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

<!-- Controles de paginación (Anterior, números de página, Siguiente) -->
<?php echo renderizar_paginacion($paginacion, 'index.php?seccion=' . $origen); ?>
