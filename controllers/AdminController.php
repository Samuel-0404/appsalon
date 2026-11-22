<?php
// Entrega final (Semana 12-16)

class AdminController {

    /**
     * Dashboard principal: Estadísticas y Agenda de Citas con filtros
     */
    public function index() {
        requireAdmin();

        $filtroFecha = trim($_GET['fecha'] ?? '');
        $filtroEstado = trim($_GET['estado'] ?? '');

        $metricas = Appointment::metricas();
        $citas = Appointment::todas($filtroFecha ?: null, $filtroEstado ?: null);

        render('admin/index', [
            'metricas'     => $metricas,
            'citas'        => $citas,
            'filtroFecha'  => $filtroFecha,
            'filtroEstado' => $filtroEstado
        ]);
    }

    /**
     * Cambiar el estado de una cita (Confirmar, Completar, Cancelar)
     */
    public function cambiarEstadoCita() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $estado = trim($_POST['estado'] ?? '');
            $estadosValidos = ['pendiente', 'confirmada', 'completada', 'cancelada'];

            if ($id && in_array($estado, $estadosValidos)) {
                $actualizado = Appointment::cambiarEstado($id, $estado);
                if ($actualizado) {
                    setAlerta('success', 'El estado de la cita #' . $id . ' se actualizó a "' . ucfirst($estado) . '".');
                } else {
                    setAlerta('danger', 'No se pudo actualizar el estado de la cita.');
                }
            }
        }

        $redireccion = 'admin/dashboard';
        if (!empty($_POST['retorno_fecha'])) {
            $redireccion .= '&fecha=' . urlencode($_POST['retorno_fecha']);
        }
        redirect($redireccion);
    }

    /**
     * Gestión de Usuarios del sistema
     */
    public function usuarios() {
        requireAdmin();
        $usuarios = User::todos();
        render('admin/usuarios', [
            'usuarios' => $usuarios
        ]);
    }

    /**
     * Activar o desactivar cuenta de usuario
     */
    public function toggleUsuario() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                User::toggleEstado($id);
                setAlerta('success', 'Estado del usuario actualizado correctamente.');
            }
        }

        redirect('admin/usuarios');
    }

    /**
     * Pantalla de Gestión de Disponibilidad Horaria
     */
    public function disponibilidad() {
        requireAdmin();

        $fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
        $horarios = Appointment::estadoHorariosDia($fecha);

        render('admin/disponibilidad', [
            'fecha'    => $fecha,
            'horarios' => $horarios
        ]);
    }

    /**
     * Bloquear una hora administrativamente
     */
    public function bloquearHora() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $fecha = trim($_POST['fecha'] ?? '');
            $hora  = trim($_POST['hora'] ?? '');

            if ($fecha && $hora) {
                if (Appointment::disponible($fecha, $hora)) {
                    Appointment::bloquearHorario((int)$_SESSION['usuario_id'], $fecha, $hora);
                    setAlerta('success', 'El horario de las ' . $hora . ' fue bloqueado para el día ' . $fecha . '.');
                } else {
                    setAlerta('danger', 'Ese horario ya se encuentra ocupado.');
                }
            }
        }

        redirect('admin/disponibilidad&fecha=' . urlencode($_POST['fecha'] ?? date('Y-m-d')));
    }

    /**
     * Desbloquear / liberar una hora
     */
    public function liberarHora() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $fecha = trim($_POST['fecha'] ?? '');
            $hora  = trim($_POST['hora'] ?? '');

            if ($fecha && $hora) {
                Appointment::liberarHorario($fecha, $hora);
                setAlerta('success', 'El horario de las ' . $hora . ' fue liberado exitosamente.');
            }
        }

        redirect('admin/disponibilidad&fecha=' . urlencode($_POST['fecha'] ?? date('Y-m-d')));
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