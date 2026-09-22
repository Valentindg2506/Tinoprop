<?php
require_once '_db.php';
casAuth();

$db     = casDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $rows   = $db->query('SELECT clave, valor FROM seguimiento_meta')->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            $result[$r['clave']] = json_decode($r['valor'], true);
        }
        casOk($result);

    } elseif ($method === 'POST') {
        $b = casBody();
        if (!isset($b['clave']) || !array_key_exists('valor', $b)) {
            casErr('Faltan campos clave/valor'); exit;
        }
        $valorJson = json_encode($b['valor'], JSON_UNESCAPED_UNICODE);
        $db->prepare(
            'INSERT INTO seguimiento_meta (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        )->execute([$b['clave'], $valorJson]);
        casOk(['clave' => $b['clave']]);

    } else {
        casErr('Método no permitido', 405);
    }
} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
