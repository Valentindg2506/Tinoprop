<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
requireLogin();
$db = getDB();

// Marcar contratos vencidos automaticamente (sin tocar firmados/rechazados).
$db->exec("UPDATE contratos SET estado='expirado' WHERE fecha_expiracion IS NOT NULL AND fecha_expiracion < CURDATE() AND estado IN ('borrador','enviado','visto')");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $a = post('accion');
    if ($a === 'eliminar') { $db->prepare("DELETE FROM contratos WHERE id=?")->execute([intval(post('cid'))]); setFlash('success','Eliminado.'); }
    if ($a === 'enviar') {
        $cid = intval(post('cid'));
        $c = $db->prepare("SELECT co.*, cl.email FROM contratos co LEFT JOIN clientes cl ON co.cliente_id=cl.id WHERE co.id=?"); $c->execute([$cid]); $c=$c->fetch();
        if ($c && !empty($c['email']) && $c['estado'] === 'borrador') {
            $db->prepare("UPDATE contratos SET estado='enviado' WHERE id=?")->execute([$cid]);
            $link = APP_URL . '/contrato.php?token=' . $c['token'];
            $body = "Se le ha enviado un contrato: <a href='{$link}'>Ver y firmar contrato</a>";
            @mail($c['email'], 'Contrato: '.$c['titulo'], $body, "Content-type: text/html; charset=UTF-8\r\n");
            setFlash('success','Contrato enviado.');
        } elseif ($c && empty($c['email'])) {
            setFlash('danger','No se puede enviar: el cliente no tiene email.');
        } elseif ($c && $c['estado'] !== 'borrador') {
            setFlash('warning','Solo se pueden enviar contratos en borrador.');
        } else {
            setFlash('danger','Contrato no encontrado.');
        }
    }
    header('Location: index.php'); exit;
}

$pageTitle = 'Contratos';
require_once __DIR__ . '/../../includes/header.php';

$contratos = $db->query("SELECT co.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos FROM contratos co LEFT JOIN clientes c ON co.cliente_id=c.id ORDER BY co.created_at DESC")->fetchAll();
$estadoClases = ['borrador'=>'secondary','enviado'=>'primary','visto'=>'info','firmado'=>'success','rechazado'=>'danger','expirado'=>'warning'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($contratos) ?> contrato<?= count($contratos)!==1?'s':'' ?></span>
    <div class="d-flex gap-2">
        <a href="plantillas.php" class="btn btn-outline-secondary"><i class="bi bi-file-text"></i> Plantillas</a>
        <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Contrato</a>
    </div>
</div>

<?php if (empty($contratos)): ?>
<div class="text-center text-muted py-5"><i class="bi bi-pen fs-1 d-block mb-3"></i><h5>No hay contratos</h5><p>Crea contratos con firma electronica.</p></div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Titulo</th><th>Cliente</th><th>Estado</th><th>Firmado</th><th>Creado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach($contratos as $co): ?>
            <tr>
                <td class="fw-semibold"><?= sanitize($co['titulo']) ?></td>
                <td><?= sanitize(($co['cli_nombre']??'').' '.($co['cli_apellidos']??'')) ?></td>
                <td><span class="badge bg-<?= $estadoClases[$co['estado']] ?>"><?= ucfirst($co['estado']) ?></span></td>
                <td><?= $co['firmado_at']?formatFechaHora($co['firmado_at']):'<span class="text-muted">-</span>' ?></td>
                <td class="small"><?= formatFecha($co['created_at']) ?></td>
                <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end">
                        <a href="form.php?id=<?= $co['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="bi bi-pencil"></i></a>
                        <a href="<?= APP_URL ?>/contrato.php?token=<?= urlencode($co['token']) ?>" target="_blank" class="btn btn-xs btn-outline-dark" title="Ver contrato"><i class="bi bi-eye"></i></a>
                        <a href="<?= APP_URL ?>/contrato.php?token=<?= urlencode($co['token']) ?>&pdf=1" target="_blank" class="btn btn-xs btn-outline-secondary" title="Exportar PDF"><i class="bi bi-filetype-pdf"></i></a>
                        <button type="button" class="btn btn-xs btn-outline-info" title="Copiar enlace" onclick="copyContratoLink('<?= APP_URL ?>/contrato.php?token=<?= $co['token'] ?>')"><i class="bi bi-link"></i></button>
                        <?php if ($co['estado']==='borrador'): ?>
                        <form method="POST"><?= csrfField() ?><input type="hidden" name="accion" value="enviar"><input type="hidden" name="cid" value="<?= $co['id'] ?>"><button class="btn btn-xs btn-outline-success"><i class="bi bi-send"></i></button></form>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('Eliminar?')"><?= csrfField() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="cid" value="<?= $co['id'] ?>"><button class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<style>.btn-xs{padding:2px 6px;font-size:.7rem}</style>

<script>
function copyContratoLink(link) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(function () {
            alert('Enlace copiado al portapapeles');
        }).catch(function () {
            window.prompt('Copia este enlace:', link);
        });
    } else {
        window.prompt('Copia este enlace:', link);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
