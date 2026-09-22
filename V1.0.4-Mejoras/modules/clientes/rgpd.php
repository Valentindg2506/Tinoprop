<?php
$pageTitle = 'RGPD - Datos del Cliente';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/export.php';

$db = getDB();
$id = intval(get('id'));
$accion = get('accion');

$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();
if (!$cliente) { setFlash('danger', 'Cliente no encontrado.'); header('Location: index.php'); exit; }
if (!isAdmin() && intval($cliente['agente_id']) !== intval(currentUserId())) {
    setFlash('danger', 'No tienes permisos para gestionar RGPD de este cliente.');
    header('Location: index.php');
    exit;
}

// Acciones RGPD
if ($accion === 'exportar') {
    exportarDatosClienteRGPD($id);
    exit;
}

if ($accion === 'anonimizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Anonimizar datos personales (mantener registros para estadisticas)
    $db->prepare("UPDATE clientes SET
        nombre = 'ANONIMIZADO', apellidos = NULL, email = NULL,
        telefono = NULL, telefono2 = NULL, dni_nie_cif = NULL,
        direccion = NULL, codigo_postal = NULL, localidad = NULL,
        notas = 'Datos anonimizados por solicitud RGPD el " . date('d/m/Y') . "',
        activo = 0
        WHERE id = ?")->execute([$id]);

    registrarActividad('anonimizar_rgpd', 'cliente', $id, 'Solicitud de derecho al olvido');
    logError('RGPD: Client data anonymized', ['client_id' => $id, 'user_id' => currentUserId()]);

    setFlash('success', 'Datos del cliente anonimizados correctamente segun RGPD.');
    header('Location: index.php');
    exit;
}

if ($accion === 'eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Eliminar documentos fisicos del cliente
    $docs = $db->prepare("SELECT archivo FROM documentos WHERE cliente_id = ?");
    $docs->execute([$id]);
    foreach ($docs->fetchAll() as $doc) {
        deleteUpload($doc['archivo']);
    }

    // Eliminar todos los registros del cliente
    $db->prepare("DELETE FROM documentos WHERE cliente_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM visitas WHERE cliente_id = ?")->execute([$id]);
    $db->prepare("UPDATE finanzas SET cliente_id = NULL WHERE cliente_id = ?")->execute([$id]);
    $db->prepare("UPDATE propiedades SET propietario_id = NULL WHERE propietario_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM tareas WHERE cliente_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);

    registrarActividad('eliminar_rgpd', 'cliente', $id, 'Eliminacion completa por solicitud RGPD');
    logError('RGPD: Client fully deleted', ['client_id' => $id, 'user_id' => currentUserId()]);

    setFlash('success', 'Todos los datos del cliente han sido eliminados permanentemente.');
    header('Location: index.php');
    exit;
}

// Contar datos asociados
$numVisitas = getCount('visitas', 'cliente_id = ?', [$id]);
$numDocs = getCount('documentos', 'cliente_id = ?', [$id]);
$numFinanzas = getCount('finanzas', 'cliente_id = ?', [$id]);
$numTareas = getCount('tareas', 'cliente_id = ?', [$id]);
$numPropiedades = getCount('propiedades', 'propietario_id = ?', [$id]);
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-shield-lock"></i> Proteccion de Datos (RGPD/LOPD) - <?= sanitize($cliente['nombre'] . ' ' . $cliente['apellidos']) ?>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle"></i> Informacion sobre Proteccion de Datos</h6>
                    <p class="mb-1"><strong>Responsable:</strong> <?= RGPD_EMPRESA ?> (<?= RGPD_CIF ?>)</p>
                    <p class="mb-1"><strong>Direccion:</strong> <?= RGPD_DIRECCION ?></p>
                    <p class="mb-1"><strong>Finalidad:</strong> <?= RGPD_FINALIDAD ?></p>
                    <p class="mb-1"><strong>Base legal:</strong> <?= RGPD_BASE_LEGAL ?></p>
                    <p class="mb-0"><strong>Delegado de Proteccion de Datos:</strong> <?= RGPD_EMAIL_DPD ?></p>
                </div>

                <h5 class="mt-4">Datos almacenados de este cliente</h5>
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr><th style="width:30%">Campo</th><th>Valor almacenado</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Nombre</strong></td><td><?= sanitize($cliente['nombre'] ?? '') ?></td></tr>
                        <tr><td><strong>Apellidos</strong></td><td><?= sanitize($cliente['apellidos'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Email</strong></td><td><?= sanitize($cliente['email'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Teléfono</strong></td><td><?= sanitize($cliente['telefono'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Teléfono 2</strong></td><td><?= sanitize($cliente['telefono2'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>DNI/NIE/CIF</strong></td><td><?= sanitize($cliente['dni_nie_cif'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Dirección</strong></td><td><?= sanitize($cliente['direccion'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Código Postal</strong></td><td><?= sanitize($cliente['codigo_postal'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Localidad</strong></td><td><?= sanitize($cliente['localidad'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Provincia</strong></td><td><?= sanitize($cliente['provincia'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Notas</strong></td><td><?= sanitize($cliente['notas'] ?? '') ?: '<span class="text-muted">—</span>' ?></td></tr>
                        <tr><td><strong>Visitas registradas</strong></td><td><?= $numVisitas ?> visitas</td></tr>
                        <tr><td><strong>Documentos asociados</strong></td><td><?= $numDocs ?> documentos</td></tr>
                        <tr><td><strong>Registros financieros</strong></td><td><?= $numFinanzas ?> registros</td></tr>
                        <tr><td><strong>Tareas asociadas</strong></td><td><?= $numTareas ?> tareas</td></tr>
                        <tr><td><strong>Propiedades como propietario</strong></td><td><?= $numPropiedades ?> propiedades</td></tr>
                        <tr><td><strong>Fecha de alta</strong></td><td><?= formatFecha($cliente['created_at']) ?></td></tr>
                    </tbody>
                </table>

                <h5 class="mt-4">Derechos del interesado</h5>

                <div class="row g-3">
                    <!-- Derecho de acceso / portabilidad -->
                    <div class="col-md-4">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center">
                                <i class="bi bi-download fs-1 text-primary"></i>
                                <h6 class="mt-2">Derecho de Acceso y Portabilidad</h6>
                                <p class="text-muted small">Exportar todos los datos del cliente en formato JSON.</p>
                                <a href="rgpd.php?id=<?= $id ?>&accion=exportar" class="btn btn-primary">
                                    <i class="bi bi-download"></i> Exportar Datos
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Derecho al olvido (anonimizar) -->
                    <div class="col-md-4">
                        <div class="card h-100 border-warning">
                            <div class="card-body text-center">
                                <i class="bi bi-eye-slash fs-1 text-warning"></i>
                                <h6 class="mt-2">Derecho al Olvido (Anonimizar)</h6>
                                <p class="text-muted small">Anonimizar datos personales conservando registros estadisticos.</p>
                                <form method="POST" action="rgpd.php?id=<?= $id ?>&accion=anonimizar" onsubmit="return confirm('ATENCION: Se anonimizaran todos los datos personales de este cliente. Esta accion NO se puede deshacer. ¿Continuar?')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-eye-slash"></i> Anonimizar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Derecho de supresion (eliminar todo) -->
                    <div class="col-md-4">
                        <div class="card h-100 border-danger">
                            <div class="card-body text-center">
                                <i class="bi bi-trash fs-1 text-danger"></i>
                                <h6 class="mt-2">Derecho de Supresion Total</h6>
                                <p class="text-muted small">Eliminar permanentemente TODOS los datos y registros asociados.</p>
                                <form method="POST" action="rgpd.php?id=<?= $id ?>&accion=eliminar" onsubmit="return confirm('PELIGRO: Se eliminaran PERMANENTEMENTE todos los datos de este cliente, incluyendo visitas, documentos y tareas. Esta accion NO se puede deshacer. Escribe ELIMINAR para confirmar.') && prompt('Escribe ELIMINAR para confirmar') === 'ELIMINAR'">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-trash"></i> Eliminar Todo
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a ficha del cliente</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
