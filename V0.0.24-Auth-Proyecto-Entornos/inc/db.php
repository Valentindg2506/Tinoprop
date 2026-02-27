<?php
/*
 * Archivo: inc/db.php
 * Rol: exponer una conexión PDO única (singleton) para reutilizarla en toda la app.
 * Flujo: carga config -> crea PDO con atributos -> reutiliza la misma instancia.
 */

/* Devuelve la instancia PDO compartida para ejecutar consultas SQL. */
function db(): PDO
{
    // Singleton: mantiene una única conexión abierta por request PHP.
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];

    // DSN de conexión MySQL con charset explícito para evitar problemas de encoding.
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO(
        $dsn,
        $db['user'],
        $db['pass'],
        [
            // Lanza excepciones en errores SQL para control centralizado.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Formato de fetch por defecto: arrays asociativos por nombre de columna.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}
