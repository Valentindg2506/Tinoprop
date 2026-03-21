<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../inc/bootstrap.php';
$termino = trim($_GET['q'] ?? '');
if (strlen($termino) < 2) {
    echo json_encode([]);
    exit;
}
$pdo = db();
$resultados = busqueda_global($pdo, $termino, 10);
echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
