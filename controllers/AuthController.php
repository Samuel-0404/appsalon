<?php
// Responsable: A
class AuthController {
    public function login() {
        // TODO: GET -> mostrar formulario | POST -> validarCsrf(), validar, password_verify,
        // guardar $_SESSION['usuario_id'], $_SESSION['nombre'], $_SESSION['admin']
        render('auth/login');
    }
    public function registro() {
        // TODO: validar nombre, apellido, email (único y válido), password >= 6, teléfono
        render('auth/registro');
    }
    public function logout() {
        // TODO: session_destroy() completo
        $_SESSION = [];
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?url=auth/login');
        exit;
    }
}
