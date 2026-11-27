<?php
// Responsable: A  (entrega final, API REST)
// Todas las respuestas son JSON con un sobre uniforme:
//   éxito: {"ok": true,  "data": ...}
//   error: {"ok": false, "error": {"codigo": 422, "mensaje": "...", "detalles": {...}}}
// La autenticación es por token Bearer (ver models/ApiToken.php). La API no usa sesiones ni CSRF:
// al no depender de cookies, un sitio ajeno no puede hacer peticiones autenticadas en nombre del usuario.

class ApiException extends RuntimeException {
    public function __construct(int $status, string $mensaje, public readonly array $detalles = [], public readonly array $headers = []) {
        parent::__construct($mensaje, $status);
    }
}

class ApiController {

    private const ESTADOS = ['pendiente', 'confirmada', 'completada', 'cancelada'];

    /* =========================================================
     *  Autenticación
     * ========================================================= */

    /**
     * POST /auth/register  {nombre, apellido, email, telefono, password, password_confirmation}  -> 201
     * Siempre crea un cliente sin confirmar y no entrega token: primero debe abrir el enlace del correo.
     */
    public function registro(): void {
        $body = self::cuerpoJson();
        $u = [
            'nombre'   => trim(self::texto($body, 'nombre')),
            'apellido' => trim(self::texto($body, 'apellido')),
            'email'    => mb_strtolower(trim(self::texto($body, 'email'))),
            'telefono' => preg_replace('/\D/', '', self::texto($body, 'telefono')),
        ];
        $password = self::texto($body, 'password');
        self::lanzarSiErrores(self::erroresDeCuenta(
            Cuenta::validarRegistro($u, $password, self::texto($body, 'password_confirmation'))));

        try {
            $id = User::crear($u + ['password' => $password]);
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') { throw $ex; }
            throw new ApiException(422, 'Datos inválidos', ['email' => 'Este email ya está registrado.']);
        }
        $enviado = Cuenta::enviarEnlace(['id' => $id] + $u, 'confirmacion');

        self::responder(201, [
            'usuario'             => self::perfilPublico(User::buscar($id)),
            'confirmacionEnviada' => $enviado,
        ]);
    }

    /** POST /auth/login  {email, password}  -> 200 {token, tipo, expira, usuario} */
    public function login(): void {
        $body     = self::cuerpoJson();
        $email    = mb_strtolower(trim((string)($body['email'] ?? '')));
        $password = (string)($body['password'] ?? '');

        $errores = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errores['email'] = 'Email inválido.'; }
        if ($password === '')                            { $errores['password'] = 'La contraseña es obligatoria.'; }
        if ($errores) { throw new ApiException(422, 'Datos inválidos', $errores); }

        $u = User::buscarPorEmail($email);
        if (!$u || !password_verify($password, $u['password'])) {
            // Mensaje genérico: no revela si el email existe
            throw new ApiException(401, 'Credenciales incorrectas');
        }
        if (!$u['confirmado'] || !$u['activo']) {
            throw new ApiException(403, 'La cuenta no está confirmada o fue desactivada');
        }

        $t = ApiToken::emitir((int)$u['id']);
        self::responder(200, [
            'token'   => $t['token'],
            'tipo'    => 'Bearer',
            'expira'  => $t['expira'],
            'usuario' => self::usuarioPublico($u),
        ]);
    }

    /** POST /auth/logout  -> 204 (revoca el token enviado) */
    public function logout(): void {
        self::autenticar();
        ApiToken::revocar(self::tokenDeCabecera());
        self::responder(204);
    }

    /** POST /auth/logout-all  -> 204 (revoca todos los tokens del usuario) */
    public function logoutTodos(): void {
        $yo = self::autenticar();
        ApiToken::revocarTodos((int)$yo['id']);
        self::responder(204);
    }

    /* =========================================================
     *  Perfil (requiere token)
     * ========================================================= */

    /** GET /auth/profile */
    public function perfil(): void {
        $yo = self::autenticar();
        self::responder(200, self::perfilPublico(User::buscar((int)$yo['id'])));
    }

    /**
     * PUT /auth/profile  {nombre?, apellido?, telefono?, email?, password?, password_confirmation?, password_actual?}
     * Solo cambian los campos enviados. Cambiar email o contraseña exige password_actual.
     */
    public function actualizarPerfil(): void {
        $yo     = self::autenticar();
        $body   = self::cuerpoJson();
        $id     = (int)$yo['id'];
        $actual = User::buscar($id);

        if (!array_intersect_key($body, array_flip(['nombre', 'apellido', 'telefono', 'email', 'password']))) {
            throw new ApiException(422, 'Nada que actualizar', ['body' => 'Envía nombre, apellido, telefono, email o password.']);
        }

        $d = [];
        foreach (['nombre', 'apellido', 'telefono', 'email'] as $campo) {
            $d[$campo] = array_key_exists($campo, $body) ? trim(self::texto($body, $campo)) : (string)$actual[$campo];
        }
        $d['email'] = mb_strtolower($d['email']);
        if (array_key_exists('telefono', $body)) { $d['telefono'] = preg_replace('/\D/', '', $d['telefono']); }

        $cambiaEmail    = $d['email'] !== mb_strtolower($actual['email']);
        $cambiaPassword = array_key_exists('password', $body);
        $password       = self::texto($body, 'password');

        // Se valida con las mismas reglas del registro, pero solo se reportan los campos que se envían.
        $errores = self::erroresDeCuenta(Cuenta::validarRegistro(
            $d, $password, self::texto($body, 'password_confirmation')));
        if (!$cambiaEmail) { unset($errores['email']); } // su propio correo no cuenta como "ya registrado"
        $errores = array_intersect_key($errores, $body + ($cambiaPassword ? ['password_confirmation' => true] : []));

        if (($cambiaEmail || $cambiaPassword)
            && !password_verify(self::texto($body, 'password_actual'), $actual['password'])) {
            $errores['password_actual'] = 'Ingresa tu contraseña actual correcta.';
        }
        self::lanzarSiErrores($errores);

        if ($cambiaPassword) { $d['password'] = $password; }
        try {
            User::actualizarPerfil($id, $d, $cambiaEmail);
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') { throw $ex; }
            throw new ApiException(422, 'Datos inválidos', ['email' => 'Este email ya está registrado.']);
        }

        $enviado = null;
        if ($cambiaEmail) {
            // La cuenta queda sin confirmar: se cierran todas las sesiones de la API, incluida esta.
            ApiToken::revocarTodos($id);
            $enviado = Cuenta::enviarEnlace(['id' => $id] + $d, 'confirmacion');
        } elseif ($cambiaPassword) {
            ApiToken::revocarTodos($id, self::tokenDeCabecera());
        }

        $respuesta = ['usuario' => self::perfilPublico(User::buscar($id))];
        if ($cambiaEmail) { $respuesta['confirmacionEnviada'] = $enviado; }
        self::responder(200, $respuesta);
    }

    /* =========================================================
     *  Gestión de tokens (cada usuario, solo los suyos)
     * ========================================================= */

    /** GET /tokens  -> 200 [{id, creado, expira, actual}] */
    public function tokens(): void {
        $yo = self::autenticar();
        self::responder(200, ApiToken::deUsuario((int)$yo['id'], self::tokenDeCabecera()));
    }

    /** DELETE /tokens/{id}  -> 204.  Un token ajeno responde 404. */
    public function revocarToken(int $id): void {
        $yo = self::autenticar();
        if (!ApiToken::revocarPorId($id, (int)$yo['id'])) { throw new ApiException(404, 'Token no encontrado'); }
        self::responder(204);
    }

    /* =========================================================
     *  Servicios (requieren token)
     * ========================================================= */

    /** GET /servicios  -> 200 [servicio] (solo activos) */
    public function servicios(): void {
        self::autenticar();
        $filas = conectarDB()
            ->query("SELECT id, nombre, descripcion, precio, duracion FROM servicios WHERE activo = 1 ORDER BY nombre")
            ->fetchAll();
        self::responder(200, array_map([self::class, 'servicioPublico'], $filas));
    }

    /** GET /servicios/{id} */
    public function servicio(int $id): void {
        self::autenticar();
        $s = Service::buscar($id);
        if (!$s || !(int)$s['activo']) { throw new ApiException(404, 'Servicio no encontrado'); }
        self::responder(200, self::servicioPublico($s));
    }

    /* =========================================================
     *  Citas (requieren token)
     * ========================================================= */

    /** GET /citas/usuario/{id}  -> 200 [cita]   (dueño o admin) */
    public function citasDeUsuario(int $usuarioId): void {
        $yo = self::autenticar();
        if (!$yo['admin'] && (int)$yo['id'] !== $usuarioId) {
            throw new ApiException(403, 'Solo puedes consultar tus propias citas');
        }

        $estado = $_GET['estado'] ?? null;
        if ($estado !== null && !in_array($estado, self::ESTADOS, true)) {
            throw new ApiException(422, 'Filtro inválido', ['estado' => 'Valores permitidos: ' . implode(', ', self::ESTADOS)]);
        }

        $sql = "SELECT id FROM citas WHERE usuarioId = :u" . ($estado ? " AND estado = :e" : "") . " ORDER BY fecha DESC, hora DESC";
        $stmt = conectarDB()->prepare($sql);
        $stmt->execute($estado ? [':u' => $usuarioId, ':e' => $estado] : [':u' => $usuarioId]);

        self::responder(200, self::representarCitas($stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** GET /citas/{id}  -> 200 cita   (dueño o admin) */
    public function cita(int $id): void {
        $yo = self::autenticar();
        $c  = self::citaAutorizada($id, $yo);
        self::responder(200, self::representarCitas([(int)$c['id']])[0]);
    }

    /** POST /citas  {fecha, hora, servicios[]}  -> 201 cita + Location */
    public function crearCita(): void {
        $yo   = self::autenticar();
        $body = self::cuerpoJson();

        // El cliente reserva para sí mismo; el admin puede reservar a nombre de un cliente con "usuarioId"
        $usuarioId = (int)$yo['id'];
        if ($yo['admin'] && isset($body['usuarioId'])) {
            $usuarioId = self::usuarioClienteValido($body['usuarioId']);
        }

        $citaId = self::conBloqueoDeFecha((string)($body['fecha'] ?? ''), function () use ($body, $usuarioId) {
            [$errores, $d] = ValidadorCita::validar($body);
            self::lanzarSiErrores($errores);

            $id = Appointment::crear($usuarioId, $d['fecha'], $d['hora'] . ':00', $d['total'], $d['servicios']);
            if (!$id) { throw new ApiException(500, 'No se pudo guardar la cita'); }
            return [$id, $d];
        });
        [$id, $d] = $citaId;

        self::notificar($usuarioId, $id, $d);
        self::responder(201, self::representarCitas([$id])[0], ['Location' => self::urlBase() . '/citas/' . $id]);
    }

    /**
     * PUT /citas/{id}
     *   Reprogramar: {fecha, hora, servicios[]}  (los tres son obligatorios: PUT reemplaza la reserva)
     *   Cambiar estado: {estado}  cliente: solo "cancelada"; admin: cualquier estado válido
     *   Se pueden enviar ambos en la misma petición.
     */
    public function actualizarCita(int $id): void {
        $yo   = self::autenticar();
        $c    = self::citaAutorizada($id, $yo);
        $body = self::cuerpoJson();

        $reprogramar = array_key_exists('fecha', $body) || array_key_exists('hora', $body) || array_key_exists('servicios', $body);
        $nuevoEstado = $body['estado'] ?? null;

        if (!$reprogramar && $nuevoEstado === null) {
            throw new ApiException(422, 'Nada que actualizar', ['body' => 'Envía {fecha, hora, servicios} y/o {estado}.']);
        }

        if ($nuevoEstado !== null) {
            if (!in_array($nuevoEstado, self::ESTADOS, true)) {
                throw new ApiException(422, 'Estado inválido', ['estado' => 'Valores permitidos: ' . implode(', ', self::ESTADOS)]);
            }
            if (!$yo['admin'] && $nuevoEstado !== 'cancelada') {
                throw new ApiException(403, 'Un cliente solo puede cancelar sus citas');
            }
        }

        if ($reprogramar) {
            if ($c['estado'] !== 'pendiente') {
                throw new ApiException(409, 'Solo se pueden reprogramar citas pendientes', ['estado' => $c['estado']]);
            }
            foreach (['fecha', 'hora', 'servicios'] as $campo) {
                if (!array_key_exists($campo, $body)) {
                    throw new ApiException(422, 'Para reprogramar envía fecha, hora y servicios', [$campo => 'Campo obligatorio.']);
                }
            }
            self::conBloqueoDeFecha((string)$body['fecha'], function () use ($body, $c, $id) {
                [$errores, $d] = ValidadorCita::validar($body, $id);
                self::lanzarSiErrores($errores);
                if (!Appointment::actualizar($id, (int)$c['usuarioId'], $d['fecha'], $d['hora'] . ':00', $d['total'], $d['servicios'])) {
                    throw new ApiException(500, 'No se pudo actualizar la cita');
                }
                return null;
            });
        }

        if ($nuevoEstado !== null && $nuevoEstado !== $c['estado']) {
            if (!$yo['admin'] && $c['estado'] !== 'pendiente') {
                throw new ApiException(409, 'Solo se pueden cancelar citas pendientes', ['estado' => $c['estado']]);
            }
            if (in_array($c['estado'], ['cancelada', 'completada'], true)) {
                throw new ApiException(409, 'La cita ya está ' . $c['estado'] . ' y no admite cambios de estado');
            }
            if (!Appointment::cambiarEstado($id, $nuevoEstado)) {
                throw new ApiException(500, 'No se pudo cambiar el estado');
            }
        }

        self::responder(200, self::representarCitas([$id])[0]);
    }

    /* =========================================================
     *  Infraestructura HTTP
     * ========================================================= */

    public static function responder(int $status, $data = null, array $headers = []): never {
        http_response_code($status);
        foreach ($headers as $k => $v) { header("$k: $v"); }
        if ($status !== 204) {
            echo json_encode(['ok' => $status < 400, 'data' => $data],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        exit;
    }

    public static function responderError(ApiException $ex): never {
        http_response_code($ex->getCode());
        foreach ($ex->headers as $k => $v) { header("$k: $v"); }
        $error = ['codigo' => $ex->getCode(), 'mensaje' => $ex->getMessage()];
        if ($ex->detalles) { $error['detalles'] = $ex->detalles; }
        echo json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Lee y decodifica el cuerpo JSON. 415 si no es JSON, 400 si está mal formado. */
    private static function cuerpoJson(): array {
        $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($tipo, 'application/json') !== 0) {
            throw new ApiException(415, 'El cuerpo debe enviarse como application/json');
        }
        $crudo = file_get_contents('php://input', false, null, 0, 65536); // 64 KB máximo
        try {
            $datos = json_decode($crudo === '' ? '{}' : $crudo, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(400, 'JSON mal formado');
        }
        if (!is_array($datos) || array_is_list($datos) && $datos !== []) {
            throw new ApiException(400, 'Se esperaba un objeto JSON');
        }
        return $datos;
    }

    private static function tokenDeCabecera(): string {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($h === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) { $h = $v; break; }
            }
        }
        return preg_match('/^Bearer\s+(\S+)$/i', trim($h), $m) ? $m[1] : '';
    }

    /** Devuelve el usuario autenticado o lanza 401/403. */
    private static function autenticar(): array {
        $token = self::tokenDeCabecera();
        $auth  = ['WWW-Authenticate' => 'Bearer realm="appsalon"'];
        if ($token === '') {
            throw new ApiException(401, 'Falta el token. Usa la cabecera Authorization: Bearer <token>', [], $auth);
        }
        $u = ApiToken::usuarioDeToken($token);
        if (!$u) {
            throw new ApiException(401, 'Token inválido o vencido', [], ['WWW-Authenticate' => 'Bearer realm="appsalon", error="invalid_token"']);
        }
        if (!$u['confirmado'] || !$u['activo']) {
            throw new ApiException(403, 'La cuenta no está confirmada o fue desactivada');
        }
        $u['admin'] = (bool)$u['admin'];
        return $u;
    }

    /** Carga la cita y verifica que el usuario sea su dueño o admin. 404 si no existe o no es visible. */
    private static function citaAutorizada(int $id, array $yo): array {
        $c = Appointment::buscar($id);
        // Para un cliente, una cita ajena responde 404 (no 403): no se confirma que el id exista
        if (!$c || (!$yo['admin'] && (int)$c['usuarioId'] !== (int)$yo['id'])) {
            throw new ApiException(404, 'Cita no encontrada');
        }
        return $c;
    }

    private static function usuarioClienteValido($valor): int {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $stmt = conectarDB()->prepare("SELECT id FROM usuarios WHERE id = :id AND admin = 0 AND confirmado = 1 AND activo = 1");
        $stmt->execute([':id' => $id ?: 0]);
        if (!$stmt->fetchColumn()) {
            throw new ApiException(422, 'Datos inválidos', ['usuarioId' => 'El cliente no existe o está inactivo.']);
        }
        return (int)$id;
    }

    /**
     * Serializa validación + escritura por fecha con un candado de MySQL (GET_LOCK).
     * Sin esto, dos peticiones simultáneas pueden pasar la validación de solapamiento
     * antes de que cualquiera de las dos inserte (condición de carrera check-then-act).
     */
    private static function conBloqueoDeFecha(string $fecha, callable $fn) {
        $db = conectarDB();
        $nombre = 'appsalon_citas_' . preg_replace('/[^0-9-]/', '', $fecha);
        $stmt = $db->prepare('SELECT GET_LOCK(:n, 5)');
        $stmt->execute([':n' => $nombre]);
        if ((int)$stmt->fetchColumn() !== 1) {
            throw new ApiException(503, 'El sistema está ocupado, intenta de nuevo', [], ['Retry-After' => '2']);
        }
        try {
            return $fn();
        } finally {
            $db->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $nombre]);
        }
    }

    private static function lanzarSiErrores(array $errores): void {
        if (!$errores) { return; }
        if (isset($errores['disponibilidad'])) {
            throw new ApiException(409, 'Horario no disponible', $errores);
        }
        throw new ApiException(422, 'Datos inválidos', $errores);
    }

    /** Representación pública de una lista de citas, con sus servicios como arreglo. */
    private static function representarCitas(array $ids): array {
        if (!$ids) { return []; }
        $ids = array_map('intval', $ids);
        $db  = conectarDB();
        $in  = implode(',', array_fill(0, count($ids), '?'));

        $citas = $db->prepare("SELECT id, usuarioId, fecha, hora, total, estado, created_at FROM citas WHERE id IN ($in)");
        $citas->execute($ids);
        $porId = [];
        foreach ($citas->fetchAll() as $c) {
            $porId[(int)$c['id']] = [
                'id'        => (int)$c['id'],
                'usuarioId' => $c['usuarioId'] !== null ? (int)$c['usuarioId'] : null,
                'fecha'     => $c['fecha'],
                'hora'      => substr($c['hora'], 0, 5),
                'estado'    => $c['estado'],
                'total'     => (float)$c['total'],
                'duracion'  => 0,
                'servicios' => [],
                'creada'    => $c['created_at'],
            ];
        }

        $serv = $db->prepare(
            "SELECT cs.citaId, s.id, s.nombre, s.precio, s.duracion
               FROM citasServicios cs INNER JOIN servicios s ON s.id = cs.servicioId
              WHERE cs.citaId IN ($in) ORDER BY s.nombre"
        );
        $serv->execute($ids);
        foreach ($serv->fetchAll() as $s) {
            $cid = (int)$s['citaId'];
            $porId[$cid]['servicios'][] = ['id' => (int)$s['id'], 'nombre' => $s['nombre'], 'precio' => (float)$s['precio'], 'duracion' => (int)$s['duracion']];
            $porId[$cid]['duracion'] += (int)$s['duracion'];
        }

        // Conserva el orden de $ids (el ORDER BY de quien llamó)
        return array_values(array_filter(array_map(fn($id) => $porId[$id] ?? null, $ids)));
    }

    private static function servicioPublico(array $s): array {
        return [
            'id'          => (int)$s['id'],
            'nombre'      => $s['nombre'],
            'descripcion' => $s['descripcion'],
            'precio'      => (float)$s['precio'],
            'duracion'    => (int)$s['duracion'],
        ];
    }

    /** Valor de texto del cuerpo; cualquier otro tipo cuenta como vacío. */
    private static function texto(array $body, string $campo): string {
        return is_string($body[$campo] ?? null) ? $body[$campo] : '';
    }

    /** La web llama "password2" a la confirmación; en la API es "password_confirmation". */
    private static function erroresDeCuenta(array $errores): array {
        if (isset($errores['password2'])) {
            $errores['password_confirmation'] = $errores['password2'];
            unset($errores['password2']);
        }
        return $errores;
    }

    private static function perfilPublico(array $u): array {
        return self::usuarioPublico($u) + [
            'telefono'   => $u['telefono'],
            'rol'        => $u['rol'],
            'confirmado' => (bool)$u['confirmado'],
        ];
    }

    private static function usuarioPublico(array $u): array {
        return ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'apellido' => $u['apellido'] ?? null,
                'email' => $u['email'], 'admin' => (bool)$u['admin']];
    }

    /** Correo de confirmación: misma plantilla que la web. Un fallo del correo no tumba la reserva. */
    private static function notificar(int $usuarioId, int $citaId, array $d): void {
        try {
            $stmt = conectarDB()->prepare("SELECT nombre, email FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $usuarioId]);
            $u = $stmt->fetch();
            if ($u) {
                require_once __DIR__ . '/../includes/Email.php';
                Email::enviarConfirmacionCita($u['email'], $u['nombre'], $d['fecha'], $d['hora'], $d['nombres'], $d['total'], $citaId);
            }
        } catch (Throwable $ex) {
            error_log('API: no se pudo enviar el correo de la cita ' . $citaId . ': ' . $ex->getMessage());
        }
    }

    private static function urlBase(): string {
        return BASE_URL . '/api';
    }
}
