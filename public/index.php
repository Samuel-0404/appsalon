<?php
// Responsable: A  (front controller / router)
// Cada uno agrega SUS rutas en su bloque para evitar conflictos de merge.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

spl_autoload_register(function ($clase) {
    foreach (['controllers', 'models'] as $dir) {
        $f = __DIR__ . "/../$dir/$clase.php";
        if (file_exists($f)) { require_once $f; return; }
    }
});

// 'ruta' => [Controlador, método]
$rutas = [
    // --- Bloque A: autenticación ---
    'auth/login'    => ['AuthController', 'login'],
    'auth/registro' => ['AuthController', 'registro'],
    'auth/logout'   => ['AuthController', 'logout'],

    // --- Bloque B: servicios ---
    'servicios/index'   => ['ServiceController', 'index'],
    'servicios/admin'   => ['ServiceController', 'admin'],
    'servicios/crear'   => ['ServiceController', 'crear'],
    'servicios/editar'  => ['ServiceController', 'editar'],
    'servicios/eliminar'=> ['ServiceController', 'eliminar'],
];

$url = $_GET['url'] ?? 'servicios/index';

try {
    if (!isset($rutas[$url])) {
        http_response_code(404);
        render('error', ['mensaje' => 'Página no encontrada']);
        exit;
    }
    [$controlador, $metodo] = $rutas[$url];
    (new $controlador())->$metodo();
} catch (Throwable $ex) {
    error_log($ex->getMessage());
    http_response_code(500);
    render('error', ['mensaje' => 'Ocurrió un error inesperado']);
}
