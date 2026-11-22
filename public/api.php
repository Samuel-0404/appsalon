<?php
// Responsable: A  (front controller de la API REST)
// URL con .htaccess:   /appsalon/public/api/servicios
// URL sin rewrite:     /appsalon/public/api.php/servicios   (PATH_INFO)
// No carga includes/functions.php a propósito: la API es stateless (sin sesión ni cookies).

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../config/database.php';

spl_autoload_register(function ($clase) {
    foreach (['controllers', 'models'] as $dir) {
        $f = __DIR__ . "/../$dir/$clase.php";
        if (file_exists($f)) { require_once $f; return; }
    }
});
require_once __DIR__ . '/../controllers/ApiController.php'; // define también ApiException

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// 'MÉTODO patrón' => método del controlador. {id} = entero positivo.
$rutas = [
    'POST /auth/login'          => 'login',
    'POST /auth/logout'         => 'logout',
    'GET /servicios'            => 'servicios',
    'GET /servicios/{id}'       => 'servicio',
    'GET /citas/usuario/{id}'   => 'citasDeUsuario',
    'GET /citas/{id}'           => 'cita',
    'POST /citas'               => 'crearCita',
    'PUT /citas/{id}'           => 'actualizarCita',
];

$ruta   = '/' . trim($_GET['ruta'] ?? ($_SERVER['PATH_INFO'] ?? ''), '/');
$metodo = strtoupper($_SERVER['REQUEST_METHOD']);

try {
    $permitidos = [];
    foreach ($rutas as $firma => $accion) {
        [$m, $patron] = explode(' ', $firma, 2);
        $regex = '#^' . str_replace('\{id\}', '([1-9]\d{0,9})', preg_quote($patron, '#')) . '$#';
        if (!preg_match($regex, $ruta, $coincide)) { continue; }

        $permitidos[] = $m;
        if ($m !== $metodo) { continue; }

        $args = array_map('intval', array_slice($coincide, 1));
        (new ApiController())->$accion(...$args);
        exit; // las acciones terminan con responder(); esto es por seguridad
    }

    if ($permitidos) {
        throw new ApiException(405, 'Método no permitido', [], ['Allow' => implode(', ', array_unique($permitidos))]);
    }
    throw new ApiException(404, 'Recurso no encontrado');

} catch (ApiException $ex) {
    ApiController::responderError($ex);
} catch (Throwable $ex) {
    error_log('API: ' . $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
    ApiController::responderError(new ApiException(500, 'Error interno del servidor'));
}
