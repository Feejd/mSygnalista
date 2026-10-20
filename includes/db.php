<?php
// Jedno połączenie PDO na całe żądanie.
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/config.php';

        $pdo = new PDO(
            'mysql:host=' . $config['host'] . ';port=' . $config['port']
                . ';dbname=' . $config['database'] . ';charset=utf8mb4',
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}
