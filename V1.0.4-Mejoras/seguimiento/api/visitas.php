<?php
require_once '_db.php';
casAuth();

$db     = casDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        casOk($db->query('SELECT * FROM visitas ORDER BY fecha DESC, hora ASC')->fetchAll());

    } elseif ($method === 'POST') {
        $b = casBody();
        if (empty($b['id'])) { casErr('Falta id'); exit; }
        $db->prepare(
            'INSERT INTO visitas (id,proId,proNombre,proTel,propId,fecha,hora,duracion,notas,estado)
             VALUES (?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               proId=VALUES(proId),proNombre=VALUES(proNombre),proTel=VALUES(proTel),
               propId=VALUES(propId),fecha=VALUES(fecha),hora=VALUES(hora),
               duracion=VALUES(duracion),notas=VALUES(notas),estado=VALUES(estado)'
        )->execute([
            $b['id'], $b['proId'] ?? null, $b['proNombre'] ?? '', $b['proTel'] ?? '',
            $b['propId'] ?? null, $b['fecha'] ?? '', $b['hora'] ?? '',
            $b['duracion'] ?? 60, $b['notas'] ?? '', $b['estado'] ?? 'Pendiente'
        ]);
        casOk(['id' => $b['id']]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) { casErr('Falta id'); exit; }
        $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([$id]);
        casOk(['deleted' => $id]);

    } else {
        casErr('Método no permitido', 405);
    }
} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
