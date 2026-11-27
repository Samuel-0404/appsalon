<?php
// Entrega final: panel del salón.
//   Personal (admin + empleado): dashboard/agenda, cambio de estado de citas y disponibilidad.
//   Solo admin: usuarios y roles, horario del salón y buzón de correos.

class AdminController {

    private const ESTADOS = ['pendiente', 'confirmada', 'completada', 'cancelada'];

    /**
     * Dashboard: métricas + agenda por día o por semana (lunes a domingo), con filtro de estado.
     *   ?url=admin/dashboard&vista=dia|semana&fecha=2026-12-10&estado=pendiente&q=texto
     * Con texto de búsqueda (q) se consultan todas las fechas.
     */
    public function index() {
        requirePersonal();

        $vista  = ($_GET['vista'] ?? 'dia') === 'semana' ? 'semana' : 'dia';
        $estado = in_array($_GET['estado'] ?? '', self::ESTADOS, true) ? $_GET['estado'] : '';
        $fecha  = self::fechaValida($_GET['fecha'] ?? '') ?? date('Y-m-d');

        $buscar = self::textoBusqueda($_GET['q'] ?? '');

        $base = new DateTimeImmutable($fecha);
        if ($vista === 'semana') {
            $desde    = $base->modify('monday this week');
            $hasta    = $desde->modify('+6 days');
            $anterior = $desde->modify('-7 days');
            $siguiente = $desde->modify('+7 days');
        } else {
            $desde = $hasta = $base;
            $anterior  = $base->modify('-1 day');
            $siguiente = $base->modify('+1 day');
        }

        render('admin/index', [
            'metricas'     => Appointment::metricas(),
            'citas'        => $buscar !== ''
                ? Appointment::todas(null, null, $estado ?: null, $buscar)
                : Appointment::todas($desde->format('Y-m-d'), $hasta->format('Y-m-d'), $estado ?: null),
            'buscar'       => $buscar,
            'modo'         => $vista,      // ojo: no llamarla 'vista', render() usa $vista para el archivo
            'filtroFecha'  => $fecha,
            'filtroEstado' => $estado,
            'desde'        => $desde->format('Y-m-d'),
            'hasta'        => $hasta->format('Y-m-d'),
            'anterior'     => $anterior->format('Y-m-d'),
            'siguiente'    => $siguiente->format('Y-m-d'),
        ]);
    }

    /**
     * Cambiar el estado de una cita (Confirmar, Completar, Cancelar)
     */
    public function cambiarEstadoCita() {
        requirePersonal();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $estado = trim($_POST['estado'] ?? '');

            if ($id && in_array($estado, self::ESTADOS, true)) {
                $actualizado = Appointment::cambiarEstado($id, $estado);
                if ($actualizado) {
                    setAlerta('success', 'El estado de la cita #' . $id . ' se actualizó a "' . ucfirst($estado) . '".');
                } else {
                    setAlerta('danger', 'No se pudo actualizar el estado de la cita.');
                }
            }
        }

        // Volver a la misma vista de agenda desde la que se hizo el cambio
        $retorno = array_filter([
            'vista'  => ($_POST['retorno_vista'] ?? '') === 'semana' ? 'semana' : null,
            'fecha'  => self::fechaValida($_POST['retorno_fecha'] ?? ''),
            'estado' => in_array($_POST['retorno_estado'] ?? '', self::ESTADOS, true) ? $_POST['retorno_estado'] : null,
            'q'      => self::textoBusqueda($_POST['retorno_q'] ?? ''),
        ], fn($v) => $v !== null && $v !== '');
        redirect('admin/dashboard' . ($retorno ? '&' . http_build_query($retorno) : ''));
    }

    /**
     * Gestión de Usuarios del sistema (solo administradores)
     */
    public function usuarios() {
        requireAdmin();
        $filtros = self::filtrosUsuarios($_GET);
        render('admin/usuarios', [
            'usuarios' => User::todos($filtros),
            'roles'    => User::ROLES,
            'filtros'  => $filtros,
        ]);
    }

    /** Texto de búsqueda limpio (máximo 60 caracteres). Ignora valores que no sean texto. */
    private static function textoBusqueda($valor): string {
        return is_string($valor) ? mb_substr(trim($valor), 0, 60) : '';
    }

    /** Filtros válidos de la pantalla de usuarios: q, rol y estado. */
    private static function filtrosUsuarios(array $entrada): array {
        $rol    = $entrada['rol'] ?? '';
        $estado = $entrada['estado'] ?? '';
        return [
            'q'      => self::textoBusqueda($entrada['q'] ?? ''),
            'rol'    => in_array($rol, User::ROLES, true) ? $rol : '',
            'estado' => in_array($estado, User::ESTADOS, true) ? $estado : '',
        ];
    }

    /** Vuelve a la lista de usuarios conservando los filtros que venían en el formulario. */
    private static function volverAUsuarios(): void {
        parse_str(is_string($_POST['retorno'] ?? null) ? $_POST['retorno'] : '', $previos);
        $filtros = array_filter(self::filtrosUsuarios($previos), fn($v) => $v !== '');
        redirect('admin/usuarios' . ($filtros ? '&' . http_build_query($filtros) : ''));
    }

    /**
     * Activar o desactivar cuenta de usuario
     */
    public function toggleUsuario() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id && User::toggleEstado($id)) {
                setAlerta('success', 'Estado del usuario actualizado correctamente.');
            } elseif ($id) {
                setAlerta('warning', 'Las cuentas de administrador no se pueden desactivar.');
            }
        }

        self::volverAUsuarios();
    }

    /** Confirmar a mano una cuenta (respaldo si el correo de confirmación no llega). */
    public function confirmarUsuario() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id && User::confirmarManual($id)) {
                setAlerta('success', 'Cuenta confirmada.');
            } elseif ($id) {
                setAlerta('warning', 'La cuenta ya estaba confirmada o no existe.');
            }
        }

        self::volverAUsuarios();
    }

    /**
     * Asignar rol (cliente, empleado, admin). Un administrador no puede cambiar su propio rol,
     * así el sistema nunca se queda sin administradores por accidente.
     */
    public function cambiarRol() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $rol = (string)($_POST['rol'] ?? '');

            if (!$id || !in_array($rol, User::ROLES, true)) {
                setAlerta('danger', 'Datos de rol inválidos.');
            } elseif ($id === (int)$_SESSION['usuario_id']) {
                setAlerta('warning', 'No puedes cambiar tu propio rol.');
            } elseif (User::cambiarRol($id, $rol)) {
                setAlerta('success', 'Rol actualizado a "' . ucfirst($rol) . '". Aplica en su próximo inicio de sesión.');
            } else {
                setAlerta('danger', 'No se pudo actualizar el rol.');
            }
        }

        self::volverAUsuarios();
    }

    /**
     * Horario de atención del salón por día de la semana (solo administradores)
     */
    public function horario() {
        requireAdmin();
        $errores = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();
            $dias = $_POST['dias'] ?? [];
            $errores = Horario::guardar(is_array($dias) ? $dias : []);
            if (!$errores) {
                setAlerta('success', 'Horario del salón actualizado.');
                redirect('admin/horario');
            }
        }

        render('admin/horario', [
            'semana'  => Horario::semana(),
            'errores' => $errores,
        ]);
    }

    /**
     * Pantalla de Gestión de Disponibilidad Horaria (franjas del horario configurado)
     */
    public function disponibilidad() {
        requirePersonal();

        $fecha = self::fechaValida($_GET['fecha'] ?? '') ?? date('Y-m-d');

        render('admin/disponibilidad', [
            'fecha'    => $fecha,
            'horario'  => Horario::delDia($fecha),
            'festivo'  => Festivo::buscar($fecha),
            'horarios' => Appointment::estadoHorariosDia($fecha)
        ]);
    }

    /**
     * Bloquear una hora administrativamente (cita sin servicios de 60 min).
     * Solo si la franja completa está libre: usa la misma regla de solapamiento que las reservas.
     */
    public function bloquearHora() {
        requirePersonal();

        $fecha = self::fechaValida($_POST['fecha'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $hora = trim($_POST['hora'] ?? '');
            if ($fecha && in_array($hora, Horario::franjas($fecha), true)) {
                $fin = sprintf('%02d:00:00', (int)substr($hora, 0, 2) + 1);
                if (!ValidadorCita::haySolapamiento($fecha, $hora . ':00', $fin)) {
                    Appointment::bloquearHorario((int)$_SESSION['usuario_id'], $fecha, $hora);
                    setAlerta('success', 'El horario de las ' . $hora . ' fue bloqueado para el día ' . $fecha . '.');
                } else {
                    setAlerta('danger', 'Ese horario ya se encuentra ocupado.');
                }
            } else {
                setAlerta('danger', 'Fecha u hora fuera del horario del salón.');
            }
        }

        redirect('admin/disponibilidad&fecha=' . urlencode($fecha ?? date('Y-m-d')));
    }

    /**
     * Desbloquear / liberar una hora (solo borra bloqueos, nunca citas de clientes)
     */
    public function liberarHora() {
        requirePersonal();

        $fecha = self::fechaValida($_POST['fecha'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $hora = trim($_POST['hora'] ?? '');
            if ($fecha && preg_match('/^\d{2}:\d{2}$/', $hora) && Appointment::liberarHorario($fecha, $hora)) {
                setAlerta('success', 'El horario de las ' . $hora . ' fue liberado exitosamente.');
            } else {
                setAlerta('warning', 'No había un bloqueo en ese horario.');
            }
        }

        redirect('admin/disponibilidad&fecha=' . urlencode($fecha ?? date('Y-m-d')));
    }

    /** 'Y-m-d' válida o null. */
    private static function fechaValida(string $valor): ?string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($d && $d->format('Y-m-d') === $valor) ? $valor : null;
    }

    /**
     * Buzón simulador de correos enviados por la aplicación
     */
    public function buzon() {
        requireAdmin();

        $dir = __DIR__ . '/../docs/correos_enviados';
        $correos = [];

        if (is_dir($dir)) {
            $archivos = glob($dir . '/*.html');
            // Ordenar los correos más recientes primero
            usort($archivos, fn($a, $b) => filemtime($b) - filemtime($a));

            foreach ($archivos as $archivo) {
                $nombreArchivo = basename($archivo);
                $fechaEnvio = date('d/m/Y h:i A', filemtime($archivo));
                
                // Extraer el ID de cita del nombre (ej: cita_5_20261120_023000.html)
                preg_match('/cita_(\d+)_/', $nombreArchivo, $matches);
                $citaId = $matches[1] ?? 'General';

                $correos[] = [
                    'archivo' => $nombreArchivo,
                    'fecha'   => $fechaEnvio,
                    'cita_id' => $citaId,
                    'asunto'  => "Confirmación de Cita #" . $citaId . " - AppSalon"
                ];
            }
        }

        render('admin/buzon', [
            'correos' => $correos
        ]);
    }

    /**
     * Muestra el contenido gráfico del correo dentro del visor
     */
    public function verCorreo() {
        requireAdmin();

        $archivo = basename($_GET['archivo'] ?? '');
        $ruta = __DIR__ . '/../docs/correos_enviados/' . $archivo;

        if (!empty($archivo) && file_exists($ruta)) {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($ruta);
            exit;
        }

        http_response_code(404);
        echo "<p style='font-family:sans-serif; text-align:center; padding:50px;'>Correo no encontrado.</p>";
        exit;
    }
}