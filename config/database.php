<?php
// Responsable: A
// Devuelve la conexión PDO (usar siempre prepared statements).
require_once __DIR__ . '/../includes/config.php';

// Zona horaria única para PHP y MySQL (Colombia, UTC-5 sin horario de verano).
// Sin esto, XAMPP usa Europe/Berlin en PHP y la hora del sistema en MySQL: las validaciones de
// "fecha/hora pasada" y la expiración de tokens comparaban relojes distintos.
date_default_timezone_set('America/Bogota');

function conectarDB(): PDO {
    static $db = null;
    if ($db === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $db = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'",
        ]);
    }
    return $db;
}
