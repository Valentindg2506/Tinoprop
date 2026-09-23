<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();

if (!in_array(currentUserRole(), ['admin', 'agente'], true)) {
    setFlash('danger', 'No tienes permisos para acceder a esta seccion.');
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$db = getDB();

// POST handlers antes del header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $accion = post('accion');

    if ($accion === 'crear') {
        $nombre = trim(post('nombre'));
        $entidad = post('entidad', 'cliente');
        $tipo = post('tipo', 'texto');
        $opciones = trim(post('opciones'));
        $obligatorio = post('obligatorio') ? 1 : 0;

        if (empty($nombre)) {
            setFlash('danger', 'El nombre del campo es obligatorio.');
        } else {
            $slug = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', mb_strtolower($nombre)));
            $slug = substr($slug, 0, 100);

            $orden = $db->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM custom_fields WHERE entidad = " . $db->quote($entidad))->fetchColumn();

            $stmt = $db->prepare("INSERT INTO custom_fields (entidad, nombre, slug, tipo, opciones, obligatorio, orden) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$entidad, $nombre, $slug, $tipo, $opciones, $obligatorio, $orden]);
            registrarActividad('crear', 'custom_field', $db->lastInsertId(), 'Campo: ' . $nombre);
            setFlash('success', 'Campo personalizado creado.');
        }
        header('Location: custom_fields.php');
        exit;
    }

    if ($accion === 'eliminar') {
        $id = intval(post('id'));
        $stmt = $db->prepare("DELETE FROM custom_fields WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Campo eliminado.');
        header('Location: custom_fields.php');
        exit;
    }

    if ($accion === 'toggle') {
        $id = intval(post('id'));
        $stmt = $db->prepare("UPDATE custom_fields SET activo = NOT activo WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: custom_fields.php');
        exit;
    }

    if ($accion === 'reordenar') {
        $orden = json_decode($_POST['orden'] ?? '[]', true);
        if (is_array($orden)) {
            $stmt = $db->prepare("UPDATE custom_fields SET orden = ? WHERE id = ?");
            foreach ($orden as $pos => $id) {
                $stmt->execute([$pos, intval($id)]);
            }
        }
        header('Location: custom_fields.php');
        exit;
    }
}

$pageTitle = 'Campos Personalizados';
require_once __DIR__ . '/../../includes/header.php';

$entidadFiltro = get('entidad', 'cliente');
$campos = $db->prepare("SELECT * FROM custom_fields WHERE entidad = ? ORDER BY orden ASC");
$campos->execute([$entidadFiltro]);
$campos = $campos->fetchAll();

$tipoLabels = [
    'texto' => 'Texto corto',
    'numero' => 'Numero',
    'fecha' => 'Fecha',
    'select' => 'Desplegable',
    'checkbox' => 'Casilla Si/No',
    'textarea' => 'Texto largo',
    'email' => 'Email',
    'telefono' => 'Telefono',
];

$entidadLabels = [
    'cliente' => 'Clientes',
    'propiedad' => 'Propiedades',
    'visita' => 'Visitas',
    'prospecto' => 'Prospectos',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= APP_URL ?>/modules/ajustes/index.php" class="btn btn-outline-secondary btn-sm me-2">
            <i class="bi bi-arrow-left"></i> Volver a Ajustes
        </a>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrear">
        <i class="bi bi-plus-lg"></i> Nuevo Campo
    </button>
</div>

<!-- Tabs por entidad -->
<ul class="nav nav-tabs mb-4">
    <?php foreach ($entidadLabels as $key => $label): ?>
    <li class="nav-item">
        <a class="nav-link <?= $entidadFiltro === $key ? 'active' : '' ?>" href="?entidad=<?= $key ?>">
            <?= $label ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($campos)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-ui-checks-grid fs-1 d-block mb-3"></i>
    <h5>No hay campos personalizados para <?= mb_strtolower($entidadLabels[$entidadFiltro]) ?></h5>
    <p>Crea campos personalizados para almacenar informacion adicional.</p>
</div>
<?php else: ?>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Tipo</th>
                    <th>Obligatorio</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campos as $campo): ?>
                <tr>
                    <td class="fw-semibold"><?= sanitize($campo['nombre']) ?></td>
                    <td><code><?= sanitize($campo['slug']) ?></code></td>
                    <td>
                        <span class="badge bg-secondary"><?= $tipoLabels[$campo['tipo']] ?? $campo['tipo'] ?></span>
                        <?php if ($campo['tipo'] === 'select' && $campo['opciones']): ?>
                            <br><small class="text-muted"><?= sanitize(mb_strimwidth($campo['opciones'], 0, 50, '...')) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $campo['obligatorio'] ? '<i class="bi bi-check-circle text-success"></i> Si' : '<span class="text-muted">No</span>' ?></td>
                    <td>
                        <?php if ($campo['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <form method="POST" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= $campo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $campo['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="bi bi-toggle-<?= $campo['activo'] ? 'on' : 'off' ?>"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Eliminar este campo y todos sus valores?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $campo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal Crear Campo -->
<div class="modal fade" id="modalCrear" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="crear">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-ui-checks-grid"></i> Nuevo Campo Personalizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Entidad <span class="text-danger">*</span></label>
                        <select name="entidad" class="form-select">
                            <?php foreach ($entidadLabels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $entidadFiltro === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100" placeholder="Ej: Fecha de cumpleanos">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de campo</label>
                        <select name="tipo" class="form-select" id="fieldType" onchange="toggleOpciones()">
                            <?php foreach ($tipoLabels as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="opcionesGroup" style="display:none;">
                        <label class="form-label">Opciones <small class="text-muted">(separadas por coma)</small></label>
                        <input type="text" name="opciones" class="form-control" placeholder="Opcion 1, Opcion 2, Opcion 3">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="obligatorio" class="form-check-input" id="obligatorio" value="1">
                        <label class="form-check-label" for="obligatorio">Campo obligatorio</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Crear Campo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleOpciones() {
    document.getElementById('opcionesGroup').style.display =
        document.getElementById('fieldType').value === 'select' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
