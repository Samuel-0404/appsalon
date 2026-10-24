<?php
// Responsable: A  -- Contratos que usa B: e(), setAlerta(), requireLogin(), requireAdmin()

session_start();

/** Escapar salida (protección XSS) */
function e($texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

/** Alertas flash: tipo = success | danger | warning | info */
function setAlerta(string $tipo, string $mensaje): void {
    $_SESSION['alertas'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function getAlertas(): array {
    $a = $_SESSION['alertas'] ?? [];
    unset($_SESSION['alertas']);
    return $a;
}

function redirect(string $ruta): void {
    header('Location: ' . BASE_URL . '/index.php?url=' . $ruta);
    exit;
}

function estaLogueado(): bool { return isset($_SESSION['usuario_id']); }
function esAdmin(): bool { return !empty($_SESSION['admin']); }

function requireLogin(): void {
    if (!estaLogueado()) { setAlerta('warning', 'Debes iniciar sesión'); redirect('auth/login'); }
}

function requireAdmin(): void {
    requireLogin();
    if (!esAdmin()) { setAlerta('danger', 'No tienes permisos'); redirect('servicios/index'); }
}

/** CSRF */
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrfCampo(): string {
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}
function validarCsrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Token CSRF inválido');
    }
}

/** Cargar una vista dentro del layout */
function render(string $vista, array $datos = []): void {
    extract($datos);
    include __DIR__ . '/../views/layout/header.php';
    include __DIR__ . '/../views/' . $vista . '.php';
    include __DIR__ . '/../views/layout/footer.php';
}
