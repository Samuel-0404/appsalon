<?php
// Responsable: A
class AuthController {

    /** GET: formulario | POST: valida credenciales y crea la sesión */
    public function login() {
        if (estaLogueado()) { redirect('servicios/index'); }

        $errores = [];
        $usuario = ['email' => ''];
        $sinConfirmar = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $usuario['email'] = mb_strtolower(trim($_POST['email'] ?? ''));
            $password         = $_POST['password'] ?? '';

            if (!filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
                $errores['email'] = 'Ingresa un email válido.';
            }
            if ($password === '') {
                $errores['password'] = 'La contraseña es obligatoria.';
            }

            if (empty($errores)) {
                $u = User::buscarPorEmail($usuario['email']);

                if (!$u || !password_verify($password, $u['password'])) {
                    // Mensaje genérico: no revela si el email existe (evita enumeración de usuarios)
                    $errores['general'] = 'Email o contraseña incorrectos.';
                } elseif (!$u['activo']) {
                    $errores['general'] = 'Tu cuenta está desactivada. Comunícate con el salón.';
                } elseif (!$u['confirmado']) {
                    $errores['general'] = 'Tu cuenta aún no ha sido confirmada. Revisa tu correo.';
                    $sinConfirmar = true; // la vista ofrece reenviar el enlace
                } else {
                    session_regenerate_id(true); // nuevo ID de sesión: evita fijación de sesión
                    $_SESSION['usuario_id'] = (int) $u['id'];
                    $_SESSION['nombre']     = $u['nombre'];
                    $_SESSION['email']      = $u['email'];
                    $_SESSION['rol']        = $u['rol'] ?? ($u['admin'] ? 'admin' : 'cliente');
                    $_SESSION['admin']      = $_SESSION['rol'] === 'admin';

                    setAlerta('success', '¡Bienvenido, ' . $u['nombre'] . '!');
                    // Personal del salón -> panel; clientes -> catálogo
                    redirect(esPersonal() ? 'admin/dashboard' : 'servicios/index');
                }
            }
        }

        render('auth/login', ['usuario' => $usuario, 'errores' => $errores, 'sinConfirmar' => $sinConfirmar]);
    }

    /** GET: formulario | POST: valida, crea el usuario y redirige al login (PRG) */
    public function registro() {
        if (estaLogueado()) { redirect('servicios/index'); }

        $errores = [];
        $usuario = ['nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $usuario['nombre']   = trim($_POST['nombre'] ?? '');
            $usuario['apellido'] = trim($_POST['apellido'] ?? '');
            $usuario['email']    = mb_strtolower(trim($_POST['email'] ?? ''));
            $usuario['telefono'] = preg_replace('/\D/', '', $_POST['telefono'] ?? ''); // quita espacios y guiones
            $password            = $_POST['password'] ?? '';
            $password2           = $_POST['password2'] ?? '';

            $errores = $this->validarRegistro($usuario, $password, $password2);

            if (empty($errores)) {
                try {
                    $id = User::crear($usuario + ['password' => $password]);
                    if ($this->enviarEnlace(['id' => $id] + $usuario, 'confirmacion')) {
                        setAlerta('success', 'Cuenta creada. Te enviamos un correo para confirmarla (revisa también Spam).');
                    } else {
                        setAlerta('warning', 'Cuenta creada, pero no pudimos enviar el correo de confirmación. '
                            . 'Intenta ingresar para reenviarlo o pide al salón que confirme tu cuenta.');
                    }
                    redirect('auth/login');
                } catch (PDOException $ex) {
                    // SQLSTATE 23000 = violación del UNIQUE(email): otro registro ganó la carrera
                    // entre emailExiste() y el INSERT. La BD es la última línea de defensa.
                    if ($ex->getCode() === '23000') {
                        $errores['email'] = 'Este email ya está registrado.';
                    } else {
                        throw $ex; // lo atrapa el try/catch global de public/index.php
                    }
                }
            }
        }

        render('auth/registro', ['usuario' => $usuario, 'errores' => $errores]);
    }

    /** GET ?url=auth/confirmar&token=...  Activa la cuenta con el enlace del correo. */
    public function confirmar() {
        header('Referrer-Policy: no-referrer');
        $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

        if (User::confirmarPorToken($token)) {
            setAlerta('success', 'Cuenta confirmada. Ya puedes iniciar sesión.');
        } else {
            setAlerta('danger', 'El enlace no es válido o ya venció. Intenta ingresar para pedir uno nuevo.');
        }
        redirect('auth/login');
    }

    /** POST: reenvía el enlace de confirmación. La respuesta no revela si la cuenta existe. */
    public function reenviar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();
            $u = $this->usuarioDelFormulario();
            if ($u && $u['activo'] && !$u['confirmado'] && !User::tokenReciente((int)$u['id'], 'confirmacion')) {
                $this->enviarEnlace($u, 'confirmacion');
            }
            setAlerta('info', 'Si la cuenta está pendiente de confirmación, te enviamos un enlace. '
                . 'Revisa también Spam; puedes pedir otro en 2 minutos.');
        }
        redirect('auth/login');
    }

    /** GET: formulario "olvidé mi contraseña" | POST: envía el enlace de recuperación. */
    public function olvide() {
        if (estaLogueado()) { redirect('servicios/index'); }
        $errores = [];
        $email = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();
            $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores['email'] = 'Ingresa un email válido.';
            } else {
                $u = User::buscarPorEmail($email);
                if ($u && $u['activo']) {
                    // Una cuenta sin confirmar recibe primero el enlace de confirmación
                    $tipo = $u['confirmado'] ? 'recuperacion' : 'confirmacion';
                    if (!User::tokenReciente((int)$u['id'], $tipo)) { $this->enviarEnlace($u, $tipo); }
                }
                // Mismo mensaje exista o no la cuenta (evita enumeración de usuarios)
                setAlerta('info', 'Si el correo está registrado, te enviamos un enlace. Revisa también Spam.');
                redirect('auth/login');
            }
        }

        render('auth/olvide', ['email' => $email, 'errores' => $errores]);
    }

    /** GET: formulario de nueva contraseña | POST: la guarda y consume el enlace. */
    public function restablecer() {
        header('Referrer-Policy: no-referrer');
        $origen = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        $token  = is_string($origen['token'] ?? null) ? $origen['token'] : '';
        $errores = [];

        if (User::idPorToken($token, 'recuperacion') === null) {
            setAlerta('danger', 'El enlace no es válido o ya venció. Pide uno nuevo.');
            redirect('auth/olvide');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();
            $password  = (string)($_POST['password'] ?? '');
            $errores   = $this->validarPassword($password, (string)($_POST['password2'] ?? ''));

            if (!$errores) {
                if (User::restablecerPassword($token, $password)) {
                    unset($_SESSION['usuario_id'], $_SESSION['rol'], $_SESSION['admin']);
                    session_regenerate_id(true);
                    setAlerta('success', 'Contraseña actualizada. Ingresa con la nueva.');
                    redirect('auth/login');
                }
                setAlerta('danger', 'El enlace ya no es válido. Pide uno nuevo.');
                redirect('auth/olvide');
            }
        }

        render('auth/restablecer', ['token' => $token, 'errores' => $errores]);
    }

    /** Usuario cuyo email llegó en el formulario, o null. */
    private function usuarioDelFormulario(): ?array {
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? User::buscarPorEmail($email) : null;
    }

    /** Genera el token y envía el correo. Si el envío falla, descarta el token para poder reintentar ya. */
    private function enviarEnlace(array $u, string $tipo): bool {
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
    private function validarPassword(string $password, string $password2): array {
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

    /** Cierra la sesión por completo: datos, cookie y archivo de sesión */
    public function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?url=auth/login');
        exit;
    }

    /** Reglas de validación del registro (lado servidor). Devuelve [campo => mensaje]. */
    private function validarRegistro(array $u, string $password, string $password2): array {
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

        return $errores + $this->validarPassword($password, $password2);
    }
}
