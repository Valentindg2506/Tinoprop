<?php
require_once '_db.php';
casAuth();

$db     = casDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        casOk($db->query('SELECT * FROM plantillas ORDER BY id')->fetchAll());

    } elseif ($method === 'POST') {
        $b = casBody();
        if (empty($b['id'])) { casErr('Falta id'); exit; }
        $db->prepare(
            'INSERT INTO plantillas (id,n,t,txt) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE n=VALUES(n),t=VALUES(t),txt=VALUES(txt)'
        )->execute([$b['id'], $b['n'] ?? '', $b['t'] ?? '', $b['txt'] ?? '']);
        casOk(['id' => $b['id']]);

    } else {
        casErr('Método no permitido', 405);
    }
} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
