<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

function ensurePdfTemplateColumns(PDO $db): void {
    try {
        $c1 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'tipo'");
        if (!$c1->fetch()) {
            $db->exec("ALTER TABLE contrato_plantillas ADD COLUMN tipo ENUM('texto','pdf') NOT NULL DEFAULT 'texto' AFTER nombre");
        }
        $c2 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'archivo_path'");
        if (!$c2->fetch()) {
            $db->exec("ALTER TABLE contrato_plantillas ADD COLUMN archivo_path VARCHAR(255) DEFAULT NULL AFTER contenido");
        }
        $c3 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'archivo_nombre'");
        if (!$c3->fetch()) {
            $db->exec("ALTER TABLE contrato_plantillas ADD COLUMN archivo_nombre VARCHAR(255) DEFAULT NULL AFTER archivo_path");
        }
    } catch (Throwable $e) {
        // Si falla la migracion, el modulo seguira en modo texto.
    }
}

function pdfTemplatesEnabled(PDO $db): bool {
    try {
        $c1 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'tipo'");
        $c2 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'archivo_path'");
        $c3 = $db->query("SHOW COLUMNS FROM contrato_plantillas LIKE 'archivo_nombre'");
        return (bool)$c1->fetch() && (bool)$c2->fetch() && (bool)$c3->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

ensurePdfTemplateColumns($db);
$pdfEnabled = pdfTemplatesEnabled($db);

function plantillaPlainText($value) {
    $text = (string) $value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\s*\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'guardar') {
        $id = intval(post('pid'));
        $tipo = ($pdfEnabled && post('tipo') === 'pdf') ? 'pdf' : 'texto';
        $nombre = trim(post('nombre'));
        $categoria = trim(post('categoria'));
        $contenido = plantillaPlainText($_POST['contenido'] ?? '');

        $current = null;
        if ($id) {
            $stCurr = $db->prepare("SELECT * FROM contrato_plantillas WHERE id = ? LIMIT 1");
            $stCurr->execute([$id]);
            $current = $stCurr->fetch();
        }

        $archivoPath = $current['archivo_path'] ?? null;
        $archivoNombre = $current['archivo_nombre'] ?? null;

        if ($tipo === 'pdf') {
            $upload = $_FILES['archivo_pdf'] ?? null;
            $hasFile = $upload && isset($upload['error']) && $upload['error'] === UPLOAD_ERR_OK;

            if (!$hasFile && !$id) {
                setFlash('danger', 'Debes subir un archivo PDF para crear una plantilla PDF.');
                header('Location: plantillas.php');
                exit;
            }

            if ($hasFile) {
                $maxBytes = 10 * 1024 * 1024;
                if (($upload['size'] ?? 0) > $maxBytes) {
                    setFlash('danger', 'El PDF supera el limite de 10MB.');
                    header('Location: plantillas.php');
                    exit;
                }

                $ext = strtolower(pathinfo($upload['name'] ?? '', PATHINFO_EXTENSION));
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                $mime = $finfo ? finfo_file($finfo, $upload['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                $allowedMimes = ['application/pdf', 'application/x-pdf', 'application/octet-stream'];
                if ($ext !== 'pdf' || ($mime && !in_array($mime, $allowedMimes, true))) {
                    setFlash('danger', 'Solo se permiten archivos PDF validos.');
                    header('Location: plantillas.php');
                    exit;
                }

                $dirAbs = __DIR__ . '/../../assets/uploads/contratos_plantillas';
                if (!is_dir($dirAbs)) {
                    @mkdir($dirAbs, 0775, true);
                }
                if (!is_dir($dirAbs)) {
                    setFlash('danger', 'No se pudo crear el directorio de plantillas PDF.');
                    header('Location: plantillas.php');
                    exit;
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', pathinfo($upload['name'], PATHINFO_FILENAME));
                $safeBase = trim($safeBase, '_') ?: 'plantilla';
                $newFile = 'tpl_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.pdf';
                $targetAbs = $dirAbs . '/' . $newFile;

                if (!move_uploaded_file($upload['tmp_name'], $targetAbs)) {
                    setFlash('danger', 'No se pudo guardar el archivo PDF.');
                    header('Location: plantillas.php');
                    exit;
                }

                if (!empty($archivoPath)) {
                    $oldAbs = __DIR__ . '/../../' . ltrim($archivoPath, '/');
                    if (is_file($oldAbs)) {
                        @unlink($oldAbs);
                    }
                }

                $archivoPath = 'assets/uploads/contratos_plantillas/' . $newFile;
                $archivoNombre = $upload['name'] ?? $newFile;
            }

            if ($id) {
                $db->prepare("UPDATE contrato_plantillas SET nombre=?, tipo='pdf', contenido='', archivo_path=?, archivo_nombre=?, categoria=? WHERE id=?")
                    ->execute([$nombre, $archivoPath, $archivoNombre, $categoria, $id]);
            } else {
                $db->prepare("INSERT INTO contrato_plantillas (nombre, tipo, contenido, archivo_path, archivo_nombre, categoria) VALUES (?,?, '', ?, ?, ?)")
                    ->execute([$nombre, 'pdf', $archivoPath, $archivoNombre, $categoria]);
            }
        } else {
            if ($id && !empty($current['tipo']) && $current['tipo'] === 'pdf' && !empty($current['archivo_path'])) {
                $oldAbs = __DIR__ . '/../../' . ltrim($current['archivo_path'], '/');
                if (is_file($oldAbs)) {
                    @unlink($oldAbs);
                }
            }

            if ($id) {
                if ($pdfEnabled) {
                    $db->prepare("UPDATE contrato_plantillas SET nombre=?, tipo='texto', contenido=?, archivo_path=NULL, archivo_nombre=NULL, categoria=? WHERE id=?")
                        ->execute([$nombre, $contenido, $categoria, $id]);
                } else {
                    $db->prepare("UPDATE contrato_plantillas SET nombre=?, contenido=?, categoria=? WHERE id=?")
                        ->execute([$nombre, $contenido, $categoria, $id]);
                }
            } else {
                if ($pdfEnabled) {
                    $db->prepare("INSERT INTO contrato_plantillas (nombre, tipo, contenido, categoria) VALUES (?,?,?,?)")
                        ->execute([$nombre, 'texto', $contenido, $categoria]);
                } else {
                    $db->prepare("INSERT INTO contrato_plantillas (nombre, contenido, categoria) VALUES (?,?,?)")
                        ->execute([$nombre, $contenido, $categoria]);
                }
            }
        }

        setFlash('success','Plantilla guardada.');
    }
    if ($a === 'eliminar') {
        $pid = intval(post('pid'));
        if ($pdfEnabled) {
            $st = $db->prepare("SELECT archivo_path FROM contrato_plantillas WHERE id = ? LIMIT 1");
            $st->execute([$pid]);
            $oldPath = $st->fetchColumn();
            if ($oldPath) {
                $oldAbs = __DIR__ . '/../../' . ltrim($oldPath, '/');
                if (is_file($oldAbs)) {
                    @unlink($oldAbs);
                }
            }
        }
        $db->prepare("DELETE FROM contrato_plantillas WHERE id=?")->execute([$pid]);
        setFlash('success','Eliminada.');
    }
    header('Location: plantillas.php'); exit;
}

$pageTitle = 'Plantillas de Contratos';
require_once __DIR__ . '/../../includes/header.php';
$plantillas = $db->query("SELECT * FROM contrato_plantillas ORDER BY categoria, nombre")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Contratos</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPlantilla"><i class="bi bi-plus-lg"></i> Nueva Plantilla</button>
</div>

<div class="row g-3">
    <?php foreach ($plantillas as $pl): ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold"><?= sanitize($pl['nombre']) ?></h6>
                <span class="badge bg-light text-dark"><?= sanitize($pl['categoria']) ?></span>
                <?php $tipo = $pl['tipo'] ?? 'texto'; ?>
                <span class="badge <?= $tipo === 'pdf' ? 'bg-danger' : 'bg-primary' ?> ms-1"><?= $tipo === 'pdf' ? 'PDF' : 'Texto' ?></span>
                <?php if ($tipo === 'pdf'): ?>
                    <div class="small text-muted mt-2">Archivo: <?= sanitize($pl['archivo_nombre'] ?? 'plantilla.pdf') ?></div>
                <?php else: ?>
                    <div class="small text-muted mt-2"><?= sanitize(mb_strimwidth($pl['contenido'],0,120,'...')) ?></div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white border-0 d-flex justify-content-between">
                <div class="d-flex gap-1">
                    <?php if (($pl['tipo'] ?? 'texto') === 'pdf' && !empty($pl['archivo_path'])): ?>
                    <a class="btn btn-sm btn-outline-danger" href="descargar_pdf.php?id=<?= intval($pl['id']) ?>" target="_blank"><i class="bi bi-filetype-pdf"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-primary" onclick='editPlantilla(<?= htmlspecialchars(json_encode($pl), ENT_QUOTES) ?>)'><i class="bi bi-pencil"></i> Editar</button>
                </div>
                <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="pid" value="<?= $pl['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="modalPlantilla" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="pid" id="pl_id" value="0">
    <div class="modal-header"><h5 class="modal-title" id="plTitle">Nueva Plantilla</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" name="nombre" id="pl_nombre" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Categoria</label><input type="text" name="categoria" id="pl_cat" class="form-control" value="general"></div>
        </div>
        <?php if ($pdfEnabled): ?>
        <div class="mt-3">
            <label class="form-label">Tipo de plantilla</label>
            <select name="tipo" id="pl_tipo" class="form-select">
                <option value="texto">Texto</option>
                <option value="pdf">PDF</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="mt-3" id="bloque_texto"><label class="form-label">Contenido (texto normal)</label><textarea name="contenido" id="pl_contenido" class="form-control" rows="15"></textarea>
        <small class="text-muted">Variables: {{cliente_nombre}}, {{cliente_dni}}, {{propiedad_direccion}}, {{propiedad_referencia}}, {{propiedad_precio}}, {{empresa_nombre}}, {{empresa_cif}}, {{fecha}}, {{ciudad}}</small></div>
        <?php if ($pdfEnabled): ?>
        <div class="mt-3 d-none" id="bloque_pdf">
            <label class="form-label">Archivo PDF</label>
            <input type="file" class="form-control" name="archivo_pdf" id="pl_archivo_pdf" accept="application/pdf,.pdf">
            <small class="text-muted">Tamano maximo: 10MB. Solo PDF.</small>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
</form></div></div></div>

<script>
function toggleTipoPlantilla() {
    var tipo = document.getElementById('pl_tipo');
    var bloqueTexto = document.getElementById('bloque_texto');
    var bloquePdf = document.getElementById('bloque_pdf');
    if (!tipo || !bloqueTexto || !bloquePdf) return;
    var isPdf = tipo.value === 'pdf';
    bloqueTexto.classList.toggle('d-none', isPdf);
    bloquePdf.classList.toggle('d-none', !isPdf);
}

function editPlantilla(p) {
    document.getElementById('pl_id').value = p.id;
    document.getElementById('pl_nombre').value = p.nombre;
    document.getElementById('pl_cat').value = p.categoria;
    document.getElementById('pl_contenido').value = p.contenido || '';
    var tipo = document.getElementById('pl_tipo');
    if (tipo) {
        tipo.value = p.tipo || 'texto';
        toggleTipoPlantilla();
    }
    document.getElementById('plTitle').textContent = 'Editar Plantilla';
    new bootstrap.Modal(document.getElementById('modalPlantilla')).show();
}

document.addEventListener('DOMContentLoaded', function () {
    var tipo = document.getElementById('pl_tipo');
    if (tipo) {
        tipo.addEventListener('change', toggleTipoPlantilla);
        toggleTipoPlantilla();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
