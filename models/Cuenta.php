<?php
// Reglas de cuenta compartidas por la web (AuthController) y la API (ApiController):
// validación del registro y envío de los enlaces por correo.
class Cuenta {

    /** Genera el token y envía el correo. Si el envío falla, descarta el token para poder reintentar ya. */
    public static function enviarEnlace(array $u, string $tipo): bool {
        require_once __DIR__ . '/../includes/Email.php';
        $token = User::generarToken((int)$u['id'], $tipo);
        $ruta  = $tipo === 'confirmacion' ? 'auth/confirmar' : 'auth/restablecer';
        // La URL base sale de la configuración, nunca de la cabecera Host (que controla quien hace la petición)
        $base  = defined('APP_URL') ? APP_URL : 'http://localhost' . BASE_URL;
        $url   = $base . '/index.php?url=' . $ruta . '&token=' . $token;

        $enviado = Email::enviarEnlaceCuenta($u['email'], $u['nombre'], $tipo, $url, User::VIGENCIA_HORAS[$tipo]);
        if (!$enviado) { User::borrarToken((int)$u['id']); }
        return $enviado;
    }

    /** Reglas de la contraseña. Devuelve [campo => mensaje]. */
    public static function validarPassword(string $password, string $password2): array {
        $errores = [];
        if (strlen($password) < 6) {
            $errores['password'] = 'La contraseña debe tener mínimo 6 caracteres.';
        } elseif (strlen($password) > 72) {
            $errores['password'] = 'La contraseña no puede superar 72 caracteres.'; // bcrypt trunca en 72 bytes
        }
        if ($password !== $password2) {
            $errores['password2'] = 'Las contraseñas no coinciden.';
        }
        return $errores;
    }

    /** Reglas de validación del registro (lado servidor). Devuelve [campo => mensaje]. */
    public static function validarRegistro(array $u, string $password, string $password2): array {
        $errores = [];
        $soloLetras = "/^[\p{L} '\-]+$/u"; // letras Unicode (tildes, ñ), espacios, apóstrofo y guion

        if ($u['nombre'] === '' || mb_strlen($u['nombre']) > 60 || !preg_match($soloLetras, $u['nombre'])) {
            $errores['nombre'] = 'El nombre es obligatorio, solo letras, máximo 60 caracteres.';
        }
        if ($u['apellido'] === '' || mb_strlen($u['apellido']) > 60 || !preg_match($soloLetras, $u['apellido'])) {
            $errores['apellido'] = 'El apellido es obligatorio, solo letras, máximo 60 caracteres.';
        }

        if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($u['email']) > 60) {
            $errores['email'] = 'Ingresa un email válido (máximo 60 caracteres).';
        } elseif (User::emailExiste($u['email'])) {
            $errores['email'] = 'Este email ya está registrado.';
        }

        if (!preg_match('/^\d{10}$/', $u['telefono'])) {
            $errores['telefono'] = 'El teléfono debe tener 10 dígitos.';
        }

        return $errores + self::validarPassword($password, $password2);
    }
}
