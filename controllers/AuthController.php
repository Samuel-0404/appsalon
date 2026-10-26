<?php
// Responsable: A
class AuthController {

    /** GET: formulario | POST: valida credenciales y crea la sesión */
    public function login() {
        if (estaLogueado()) { redirect('servicios/index'); }

        $errores = [];
        $usuario = ['email' => ''];

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
                } elseif (!$u['confirmado']) {
                    $errores['general'] = 'Tu cuenta aún no ha sido confirmada.';
                } else {
                    session_regenerate_id(true); // nuevo ID de sesión: evita fijación de sesión
                    $_SESSION['usuario_id'] = (int) $u['id'];
                    $_SESSION['nombre']     = $u['nombre'];
                    $_SESSION['admin']      = (bool) $u['admin'];

                    setAlerta('success', '¡Bienvenido, ' . $u['nombre'] . '!');
                    redirect($_SESSION['admin'] ? 'servicios/admin' : 'servicios/index');
                }
            }
        }

        render('auth/login', ['usuario' => $usuario, 'errores' => $errores]);
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
                    User::crear($usuario + ['password' => $password]);
                    setAlerta('success', 'Cuenta creada. Ya puedes iniciar sesión.');
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
}
