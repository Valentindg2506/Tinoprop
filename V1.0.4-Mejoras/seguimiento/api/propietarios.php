<?php
require_once '_db.php';
casAuth();

$db     = casDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $rows = $db->query('SELECT * FROM propietarios ORDER BY createdAt DESC')->fetchAll();
        foreach ($rows as &$r) {
            $r['sentMsgs']  = json_decode($r['sentMsgs']  ?? '[]', true) ?: [];
            $r['reactions'] = json_decode($r['reactions'] ?? '{}', true) ?: (object)[];
        }
        casOk($rows);

    } elseif ($method === 'POST') {
        $b = casBody();
        if (empty($b['id'])) { casErr('Falta id'); exit; }
        $sentMsgs  = json_encode($b['sentMsgs']  ?? [], JSON_UNESCAPED_UNICODE);
        $reactions = json_encode($b['reactions'] ?? new stdClass(), JSON_UNESCAPED_UNICODE);
        $db->prepare(
            'INSERT INTO propietarios
               (id,nombre,telefono,zona,route,sentMsgs,lastSentDate,status,createdAt,notes,reactions)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               nombre=VALUES(nombre),telefono=VALUES(telefono),zona=VALUES(zona),
               route=VALUES(route),sentMsgs=VALUES(sentMsgs),lastSentDate=VALUES(lastSentDate),
               status=VALUES(status),notes=VALUES(notes),reactions=VALUES(reactions)'
        )->execute([
            $b['id'], $b['nombre'] ?? '', $b['telefono'] ?? '', $b['zona'] ?? '',
            $b['route'] ?? 'A', $sentMsgs,
            $b['lastSentDate'] ?: null,
            $b['status'] ?? 'activo',
            $b['createdAt'] ?? date('Y-m-d'),
            $b['notes'] ?? '', $reactions
        ]);
        casOk(['id' => $b['id']]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) { casErr('Falta id'); exit; }
        $db->prepare('DELETE FROM propietarios WHERE id = ?')->execute([$id]);
        casOk(['deleted' => $id]);

    } else {
        casErr('Método no permitido', 405);
    }
} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
