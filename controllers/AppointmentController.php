<?php


class AppointmentController {

    /**
     * Formulario y procesamiento de una nueva cita
     */
    public function crear() {
        requireLogin();

        $servicios = Service::todos();
        $errores = [];
        
        // Si viene un servicio preseleccionado desde el catálogo público
        $servicioIdParam = filter_var($_GET['servicio_id'] ?? null, FILTER_VALIDATE_INT);
        $serviciosIniciales = $servicioIdParam ? [$servicioIdParam] : [];

        $cita = [
            'fecha' => '',
            'hora' => '',
            'servicios' => $serviciosIniciales
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $cita['fecha'] = trim($_POST['fecha'] ?? '');
            $cita['hora'] = trim($_POST['hora'] ?? '');
            $serviciosSeleccionados = $_POST['servicios'] ?? [];
            $cita['servicios'] = is_array($serviciosSeleccionados) ? $serviciosSeleccionados : [];

            // 1. Validar servicios
            if (empty($cita['servicios'])) {
                $errores['servicios'] = 'Debes seleccionar al menos un servicio.';
            }

            // 2. Validar fecha (no vacía, no pasada, no domingo, no festivo)
            $hoy = date('Y-m-d');
            if (empty($cita['fecha'])) {
                $errores['fecha'] = 'Debes seleccionar una fecha.';
            } elseif ($cita['fecha'] < $hoy) {
                $errores['fecha'] = 'No puedes seleccionar una fecha en el pasado.';
            } elseif (date('w', strtotime($cita['fecha'])) === '0') {
                $errores['fecha'] = 'El salón no atiende los domingos.';
            } elseif (($festivo = Festivo::buscar($cita['fecha'])) !== null) {
                $errores['fecha'] = 'El ' . e($cita['fecha']) . ' es festivo (' . e($festivo) . '): el salón no atiende.';
            }

            // 3. Validar hora (entre 09:00 y 18:00)
            if (empty($cita['hora'])) {
                $errores['hora'] = 'Debes seleccionar una hora.';
            } else {
                $horaInt = (int)substr($cita['hora'], 0, 2);
                if ($horaInt < 9 || $horaInt >= 19) {
                    $errores['hora'] = 'El horario de atención es de 09:00 a 19:00.';
                }
            }

            // 4. Validar disponibilidad (sin sobreposición de citas)
            if (empty($errores)) {
                if (!Appointment::disponible($cita['fecha'], $cita['hora'])) {
                    $errores['disponibilidad'] = 'El horario seleccionado (' . e($cita['hora']) . ' el ' . e($cita['fecha']) . ') ya está ocupado. Por favor elige otra hora o fecha.';
                }
            }

            // 5. Si todo es válido: calcular total real en servidor y guardar
            if (empty($errores)) {
                $total = 0.0;
                $serviciosValidos = [];

                foreach ($cita['servicios'] as $servId) {
                    $servicioBD = Service::buscar((int)$servId);
                    if ($servicioBD) {
                        $total += (float)$servicioBD['precio'];
                        $serviciosValidos[] = (int)$servicioBD['id'];
                    }
                }

                if (!empty($serviciosValidos)) {
                    $citaId = Appointment::crear(
                        (int)$_SESSION['usuario_id'],
                        $cita['fecha'],
                        $cita['hora'],
                        $total,
                        $serviciosValidos
                    );

                    if ($citaId) {
                        // 1. Obtener nombres de los servicios para el correo
                        $nombresServicios = [];
                        foreach ($serviciosValidos as $sId) {
                            $serv = Service::buscar($sId);
                            if ($serv) {
                                $nombresServicios[] = $serv['nombre'];
                            }
                        }

                        // 2. Enviar notificación por correo (Módulo 6 - Criterio Email)
                        require_once __DIR__ . '/../includes/Email.php';
                        $emailCliente = $_SESSION['email'] ?? 'cliente@appsalon.com';
                        $nombreCliente = $_SESSION['nombre'] ?? 'Cliente';

                        Email::enviarConfirmacionCita(
                            $emailCliente,
                            $nombreCliente,
                            $cita['fecha'],
                            $cita['hora'],
                            $nombresServicios,
                            $total,
                            $citaId
                        );

                        setAlerta('success', '¡Tu cita #' . $citaId . ' fue confirmada con éxito! Te hemos enviado un correo con el resumen.');
                        redirect('citas/mis-citas');
                    } else {
                        setAlerta('danger', 'Ocurrió un error al guardar tu cita. Intenta de nuevo.');
                    }
                }
            }
        }

        render('appointments/crear', [
            'servicios' => $servicios,
            'cita'      => $cita,
            'errores'   => $errores
        ]);
    }

        /**
     * Modificar / reprogramar una cita existente
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

            $cita['fecha'] = trim($_POST['fecha'] ?? '');
            $cita['hora']  = trim($_POST['hora'] ?? '');
            $serviciosSeleccionados = $_POST['servicios'] ?? [];
            $cita['servicios'] = is_array($serviciosSeleccionados) ? $serviciosSeleccionados : [];

            // 1. Validar servicios
            if (empty($cita['servicios'])) {
                $errores['servicios'] = 'Debes seleccionar al menos un servicio.';
            }

            // 2. Validar fecha (no vacía, no pasada, no domingo, no festivo)
            $hoy = date('Y-m-d');
            if (empty($cita['fecha'])) {
                $errores['fecha'] = 'Debes seleccionar una fecha.';
            } elseif ($cita['fecha'] < $hoy) {
                $errores['fecha'] = 'No puedes seleccionar una fecha en el pasado.';
            } elseif (date('w', strtotime($cita['fecha'])) === '0') {
                $errores['fecha'] = 'El salón no atiende los domingos.';
            } elseif (($festivo = Festivo::buscar($cita['fecha'])) !== null) {
                $errores['fecha'] = 'El ' . e($cita['fecha']) . ' es festivo (' . e($festivo) . '): el salón no atiende.';
            }

            // 3. Validar hora
            if (empty($cita['hora'])) {
                $errores['hora'] = 'Debes seleccionar una hora.';
            } else {
                $horaInt = (int)substr($cita['hora'], 0, 2);
                if ($horaInt < 9 || $horaInt >= 19) {
                    $errores['hora'] = 'El horario de atención es de 09:00 a 19:00.';
                }
            }

            // 4. Validar disponibilidad (excluyendo esta misma cita)
            if (empty($errores)) {
                if (!Appointment::disponible($cita['fecha'], $cita['hora'], $id)) {
                    $errores['disponibilidad'] = 'El horario seleccionado (' . e($cita['hora']) . ') ya está ocupado por otra cita. Por favor elige otro horario.';
                }
            }

            // 5. Calcular total en servidor y guardar actualización
            if (empty($errores)) {
                $total = 0.0;
                $serviciosValidos = [];

                foreach ($cita['servicios'] as $servId) {
                    $servicioBD = Service::buscar((int)$servId);
                    if ($servicioBD) {
                        $total += (float)$servicioBD['precio'];
                        $serviciosValidos[] = (int)$servicioBD['id'];
                    }
                }

                if (!empty($serviciosValidos)) {
                    $ok = Appointment::actualizar(
                        $id,
                        (int)$_SESSION['usuario_id'],
                        $cita['fecha'],
                        $cita['hora'],
                        $total,
                        $serviciosValidos
                    );

                    if ($ok) {
                        setAlerta('success', '¡Tu cita #' . $id . ' fue reprogramada con éxito!');
                        redirect('citas/mis-citas');
                    } else {
                        setAlerta('danger', 'Ocurrió un error al reprogramar la cita.');
                    }
                }
            }
        }

        render('appointments/editar', [
            'servicios' => $servicios,
            'cita'      => $cita,
            'errores'   => $errores
        ]);
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
     * Endpoint interno para consultar horas ocupadas de una fecha vía JSON
     */
    public function horasOcupadas() {
        header('Content-Type: application/json');
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $excluirId = filter_var($_GET['excluir_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        $horas = Appointment::horasOcupadas($fecha, $excluirId);
        // Formatear a HH:MM (ej. "09:00")
        $horasFormateadas = array_map(fn($h) => substr($h, 0, 5), $horas);

        echo json_encode(['ocupadas' => $horasFormateadas]);
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