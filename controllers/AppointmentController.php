<?php
// Entrega final: reservas del cliente (crear, reprogramar, cancelar, historial y correo)

class AppointmentController {

    /**
     * Formulario y procesamiento de una nueva cita.
     * Las reglas (horario del día, festivos, servicios activos, solapamiento por duración) están en
     * ValidadorCita, el mismo validador de la API: web y API aceptan exactamente las mismas citas.
     */
    public function crear() {
        requireLogin();

        $servicios = Service::todos();
        $errores = [];

        // Si viene un servicio preseleccionado desde el catálogo público
        $servicioIdParam = filter_var($_GET['servicio_id'] ?? null, FILTER_VALIDATE_INT);

        $cita = [
            'fecha'     => '',
            'hora'      => '',
            'servicios' => $servicioIdParam ? [$servicioIdParam] : []
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();
            $cita = self::datosDelFormulario($cita);

            // El bloqueo por fecha serializa las reservas del mismo día: dos clientes no pueden
            // validar y guardar la misma franja al mismo tiempo.
            $citaId = self::conBloqueoDeFecha($cita['fecha'], function () use ($cita, &$errores, &$datos) {
                [$errores, $datos] = ValidadorCita::validar($cita);
                if ($errores) { return null; }
                return Appointment::crear((int)$_SESSION['usuario_id'], $datos['fecha'], $datos['hora'],
                                          $datos['total'], $datos['servicios']);
            });

            if ($citaId) {
                // Notificación por correo al email con el que se registró el cliente
                require_once __DIR__ . '/../includes/Email.php';
                $enviado = Email::enviarConfirmacionCita(
                    $_SESSION['email'] ?? '',
                    $_SESSION['nombre'] ?? 'Cliente',
                    $datos['fecha'],
                    $datos['hora'],
                    $datos['nombres'],
                    $datos['total'],
                    $citaId
                );

                setAlerta('success', '¡Tu cita #' . $citaId . ' fue registrada con éxito!'
                    . ($enviado ? ' Te enviamos un correo con el resumen.' : ''));
                redirect('citas/mis-citas');
            } elseif (!$errores) {
                setAlerta('danger', 'Ocurrió un error al guardar tu cita. Intenta de nuevo.');
            }
        }

        render('appointments/crear', [
            'servicios' => $servicios,
            'cita'      => $cita,
            'errores'   => $errores
        ]);
    }

    /**
     * Modificar / reprogramar una cita existente (solo el dueño y solo si está pendiente)
     */
    public function editar() {
        requireLogin();

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            setAlerta('danger', 'Cita no válida.');
            redirect('citas/mis-citas');
        }

        $citaExistente = Appointment::buscar($id);
        if (!$citaExistente || (int)$citaExistente['usuarioId'] !== (int)$_SESSION['usuario_id']) {
            setAlerta('danger', 'No tienes permiso para modificar esta cita.');
            redirect('citas/mis-citas');
        }

        if ($citaExistente['estado'] !== 'pendiente') {
            setAlerta('warning', 'Solo puedes modificar citas que estén en estado pendiente.');
            redirect('citas/mis-citas');
        }

        $servicios = Service::todos();
        $errores = [];

        // Precargar datos actuales de la cita
        $cita = [
            'id'        => $citaExistente['id'],
            'fecha'     => $citaExistente['fecha'],
            'hora'      => substr($citaExistente['hora'], 0, 5),
            'servicios' => Appointment::obtenerServiciosIds($id)
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();
            $cita = self::datosDelFormulario($cita);

            // excluirCitaId = $id: la cita no choca consigo misma al reprogramarla
            $ok = self::conBloqueoDeFecha($cita['fecha'], function () use ($cita, $id, &$errores) {
                [$errores, $datos] = ValidadorCita::validar($cita, $id);
                if ($errores) { return false; }
                return Appointment::actualizar($id, (int)$_SESSION['usuario_id'], $datos['fecha'], $datos['hora'],
                                               $datos['total'], $datos['servicios']);
            });

            if ($ok) {
                setAlerta('success', '¡Tu cita #' . $id . ' fue reprogramada con éxito!');
                redirect('citas/mis-citas');
            } elseif (!$errores) {
                setAlerta('danger', 'Ocurrió un error al reprogramar la cita.');
            }
        }

        render('appointments/editar', [
            'servicios' => $servicios,
            'cita'      => $cita,
            'errores'   => $errores
        ]);
    }

    /** Lee fecha, hora y servicios del POST (sin validar: eso lo hace ValidadorCita). */
    private static function datosDelFormulario(array $cita): array {
        $cita['fecha'] = trim((string)($_POST['fecha'] ?? ''));
        $cita['hora']  = trim((string)($_POST['hora'] ?? ''));
        $servicios     = $_POST['servicios'] ?? [];
        $cita['servicios'] = is_array($servicios) ? $servicios : [];
        return $cita;
    }

    /**
     * Ejecuta $fn con un bloqueo con nombre de MariaDB por fecha (GET_LOCK), igual que la API.
     * Si no consigue el bloqueo en 5 s, no ejecuta nada y devuelve null.
     */
    private static function conBloqueoDeFecha(string $fecha, callable $fn) {
        $db = conectarDB();
        $nombre = 'appsalon_citas_' . preg_replace('/[^0-9-]/', '', $fecha);
        $stmt = $db->prepare('SELECT GET_LOCK(:n, 5)');
        $stmt->execute([':n' => $nombre]);
        if ((int)$stmt->fetchColumn() !== 1) {
            setAlerta('warning', 'El sistema está ocupado en este momento, intenta de nuevo.');
            return null;
        }
        try {
            return $fn();
        } finally {
            $db->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $nombre]);
        }
    }

    /**
     * Historial de citas del cliente logueado
     */
    public function misCitas() {
        requireLogin();

        $citas = Appointment::porUsuario((int)$_SESSION['usuario_id']);
        render('appointments/mis_citas', [
            'citas' => $citas
        ]);
    }

    /**
     * Cancelar una cita
     */
    public function cancelar() {
        requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                $cancelada = Appointment::cancelar($id, (int)$_SESSION['usuario_id']);
                if ($cancelada) {
                    setAlerta('success', 'La cita fue cancelada correctamente.');
                } else {
                    setAlerta('danger', 'No se pudo cancelar la cita.');
                }
            }
        }

        redirect('citas/mis-citas');
    }

    /**
     * Permite al cliente ver el correo de confirmación de su cita
     */
    public function verCorreoCita() {
        requireLogin();

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            redirect('citas/mis-citas');
        }

        $cita = Appointment::buscar($id);
        if (!$cita || ((int)$cita['usuarioId'] !== (int)$_SESSION['usuario_id'] && !esAdmin())) {
            setAlerta('danger', 'No tienes permiso para ver este correo.');
            redirect('citas/mis-citas');
        }

        // Buscar el archivo del correo generado
        $dir = __DIR__ . '/../docs/correos_enviados';
        $archivos = is_dir($dir) ? glob($dir . '/cita_' . $id . '_*.html') : [];

        if (!empty($archivos)) {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($archivos[0]);
            exit;
        }

        // Si no existía el archivo previo, regenerarlo para que el cliente siempre lo vea
        require_once __DIR__ . '/../includes/Email.php';
        $nombresServicios = [];
        $serviciosIds = Appointment::obtenerServiciosIds($id);
        foreach ($serviciosIds as $sId) {
            $s = Service::buscar($sId);
            if ($s) {
                $nombresServicios[] = $s['nombre'];
            }
        }

        Email::enviarConfirmacionCita(
            $_SESSION['email'] ?? 'cliente@appsalon.com',
            $_SESSION['nombre'] ?? 'Cliente',
            $cita['fecha'],
            $cita['hora'],
            $nombresServicios,
            (float)$cita['total'],
            $id
        );

        $archivos = glob($dir . '/cita_' . $id . '_*.html');
        if (!empty($archivos)) {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($archivos[0]);
            exit;
        }

        setAlerta('warning', 'No se encontró el correo de confirmación.');
        redirect('citas/mis-citas');
    }

    /**
     * Endpoint interno (JSON) con la disponibilidad de un día para el calendario de reservas:
     *   GET ?url=citas/horas-ocupadas&fecha=2026-12-10[&excluir_id=5]
     *   { "abierto": true, "apertura": "09:00", "cierre": "19:00", "franjas": ["09:00", ...],
     *     "intervalos": [{"inicio": "10:00", "fin": "12:00"}], "ocupadas": ["10:00", "11:00"] }
     * "intervalos" permite al navegador descartar las horas donde la duración elegida no cabe.
     */
    public function horasOcupadas() {
        header('Content-Type: application/json; charset=utf-8');

        $fecha = (string)($_GET['fecha'] ?? date('Y-m-d'));
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            http_response_code(400);
            echo json_encode(['error' => 'Fecha inválida (AAAA-MM-DD)']);
            exit;
        }
        $excluirId = filter_var($_GET['excluir_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        $horario    = Horario::delDia($fecha);
        $franjas    = Horario::franjas($fecha);
        $intervalos = array_map(fn($iv) => ['inicio' => $iv['inicio'], 'fin' => $iv['fin']],
                                Appointment::intervalos($fecha, $excluirId));

        // Compatibilidad: franjas que se cruzan con alguna cita (aunque no empiecen a esa hora)
        $ocupadas = array_values(array_filter($franjas, function ($h) use ($intervalos) {
            $finFranja = sprintf('%02d:00', (int)substr($h, 0, 2) + 1);
            foreach ($intervalos as $iv) {
                if ($iv['inicio'] < $finFranja && $h < $iv['fin']) { return true; }
            }
            return false;
        }));

        echo json_encode([
            'abierto'    => $horario !== null,
            'apertura'   => $horario['apertura'] ?? null,
            'cierre'     => $horario['cierre'] ?? null,
            'franjas'    => $franjas,
            'intervalos' => $intervalos,
            'ocupadas'   => $ocupadas,
        ]);
        exit;
    }

    /** GET ?url=citas/festivos&anio=2026  →  {"anio":2026,"festivos":[{"fecha":"...","nombre":"..."}]} */
    public function festivos() {
        header('Content-Type: application/json; charset=utf-8');

        $anio = filter_var($_GET['anio'] ?? date('Y'), FILTER_VALIDATE_INT,
                           ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
        if ($anio === false) {
            http_response_code(400);                               // error del cliente
            echo json_encode(['error' => 'El parámetro anio debe estar entre 2000 y 2100']);
            exit;
        }

        echo json_encode(['anio' => $anio, 'festivos' => Festivo::porAnio($anio)], JSON_UNESCAPED_UNICODE);
        exit;
    }

}