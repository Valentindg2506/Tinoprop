<?php
require_once '_db.php';
casAuth();

$db     = casDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        casOk($db->query('SELECT * FROM propiedades ORDER BY id')->fetchAll());

    } elseif ($method === 'POST') {
        $b = casBody();
        if (empty($b['id'])) { casErr('Falta id'); exit; }
        $db->prepare(
            'INSERT INTO propiedades (id,name,sub,addr) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE name=VALUES(name),sub=VALUES(sub),addr=VALUES(addr)'
        )->execute([$b['id'], $b['name'] ?? '', $b['sub'] ?? '', $b['addr'] ?? '']);
        casOk(['id' => $b['id']]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) { casErr('Falta id'); exit; }
        $db->prepare('DELETE FROM propiedades WHERE id = ?')->execute([$id]);
        casOk(['deleted' => $id]);

    } else {
        casErr('Método no permitido', 405);
    }
} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
