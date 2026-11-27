<?php
// Responsable: A  (front controller / router)
// Cada uno agrega SUS rutas en su bloque para evitar conflictos de merge.
require_once __DIR__ . '/../config/config.php';
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

    // --- Bloque Citas (Entrega Final) ---
    'citas/crear'          => ['AppointmentController', 'crear'],
    'citas/editar'         => ['AppointmentController', 'editar'],
    'citas/mis-citas'      => ['AppointmentController', 'misCitas'],
    'citas/cancelar'       => ['AppointmentController', 'cancelar'],
    'citas/horas-ocupadas' => ['AppointmentController', 'horasOcupadas'],
    'citas/ver-correo'     => ['AppointmentController', 'verCorreoCita'],
    'citas/festivos'       => ['AppointmentController', 'festivos'],

    // --- Bloque Admin Avanzado (Entrega Final) ---
    'admin/dashboard'      => ['AdminController', 'index'],
    'admin/cita-estado'    => ['AdminController', 'cambiarEstadoCita'],
    'admin/usuarios'       => ['AdminController', 'usuarios'],
    'admin/usuario-toggle' => ['AdminController', 'toggleUsuario'],
    'admin/usuario-rol'    => ['AdminController', 'cambiarRol'],
    'admin/horario'        => ['AdminController', 'horario'],
    'admin/disponibilidad' => ['AdminController', 'disponibilidad'],
    'admin/bloquear-hora'  => ['AdminController', 'bloquearHora'],
    'admin/liberar-hora'   => ['AdminController', 'liberarHora'],
    'admin/buzon'          => ['AdminController', 'buzon'],
    'admin/buzon-ver'      => ['AdminController', 'verCorreo'],

    // --- Bloque A: reportes (Entrega Final) --- la API REST vive en public/api.php
    'reportes/index' => ['ReportController', 'index'],
    'reportes/csv'   => ['ReportController', 'csv'],
    'reportes/pdf'   => ['ReportController', 'pdf'],
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
