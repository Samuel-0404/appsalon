<?php
// Entrega final (Semana 12-16)

class Appointment {

    /**
     * Valida si un horario está libre (no permitir sobreposición de citas).
     * @param string $fecha Formato YYYY-MM-DD
     * @param string $hora  Formato HH:MM:SS o HH:MM
     * @param int|null $excluirCitaId Opcional al modificar
     * @return bool true si está disponible, false si ya está ocupado
     */
    public static function disponible(string $fecha, string $hora, ?int $excluirCitaId = null): bool {
        $db = conectarDB();
        $query = "SELECT COUNT(*) FROM citas 
                  WHERE fecha = :fecha 
                    AND hora = :hora 
                    AND estado != 'cancelada'";

        if ($excluirCitaId !== null) {
            $query .= " AND id != :excluirCitaId";
        }

        $stmt = $db->prepare($query);
        $params = [
            ':fecha' => $fecha,
            ':hora'  => $hora
        ];
        if ($excluirCitaId !== null) {
            $params[':excluirCitaId'] = $excluirCitaId;
        }

        $stmt->execute($params);
        return ((int)$stmt->fetchColumn()) === 0;
    }

    /**
     * Crea una cita y asocia múltiples servicios usando una transacción.
     * @param int $usuarioId
     * @param string $fecha
     * @param string $hora
     * @param float $total
     * @param array $serviciosIds Lista de IDs de servicios seleccionados
     * @return int|null Retorna el ID de la cita creada o null si falla
     */
    public static function crear(int $usuarioId, string $fecha, string $hora, float $total, array $serviciosIds): ?int {
        $db = conectarDB();

        try {
            $db->beginTransaction();

            // 1. Insertar en tabla citas
            $queryCita = "INSERT INTO citas (fecha, hora, usuarioId, total, estado) 
                          VALUES (:fecha, :hora, :usuarioId, :total, 'pendiente')";
            $stmtCita = $db->prepare($queryCita);
            $stmtCita->execute([
                ':fecha'     => $fecha,
                ':hora'      => $hora,
                ':usuarioId' => $usuarioId,
                ':total'     => $total
            ]);

            $citaId = (int)$db->lastInsertId();

            // 2. Insertar cada servicio en la tabla intermedia citasServicios
            $queryServicio = "INSERT INTO citasServicios (citaId, servicioId) VALUES (:citaId, :servicioId)";
            $stmtServicio = $db->prepare($queryServicio);

            foreach ($serviciosIds as $servicioId) {
                $stmtServicio->execute([
                    ':citaId'     => $citaId,
                    ':servicioId' => (int)$servicioId
                ]);
            }

            $db->commit();
            return $citaId;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al crear cita: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene el listado de citas de un cliente con sus servicios agrupados.
     * @param int $usuarioId
     * @return array
     */
    public static function porUsuario(int $usuarioId): array {
        $db = conectarDB();
        $query = "SELECT c.id, c.fecha, c.hora, c.total, c.estado, c.created_at,
                         GROUP_CONCAT(s.nombre SEPARATOR ', ') AS servicios,
                         SUM(s.duracion) AS duracion_total
                  FROM citas c
                  LEFT JOIN citasServicios cs ON c.id = cs.citaId
                  LEFT JOIN servicios s ON cs.servicioId = s.id
                  WHERE c.usuarioId = :usuarioId
                  GROUP BY c.id
                  ORDER BY c.fecha DESC, c.hora DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':usuarioId' => $usuarioId]);
        return $stmt->fetchAll();
    }

    /**
     * Busca una cita específica por su ID.
     */
    public static function buscar(int $id): ?array {
        $db = conectarDB();
        $query = "SELECT * FROM citas WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id]);
        $cita = $stmt->fetch();
        return $cita ?: null;
    }

    /**
     * Cancela una cita de un usuario si está en estado 'pendiente'.
     */
    public static function cancelar(int $citaId, int $usuarioId): bool {
        $db = conectarDB();
        $query = "UPDATE citas 
                  SET estado = 'cancelada' 
                  WHERE id = :citaId AND usuarioId = :usuarioId AND estado = 'pendiente'";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':citaId'    => $citaId,
            ':usuarioId' => $usuarioId
        ]);
        return $stmt->rowCount() > 0;
    }

        /**
     * Obtiene solo los IDs de los servicios vinculados a una cita
     * @param int $citaId
     * @return array Lista de IDs enteros
     */
    public static function obtenerServiciosIds(int $citaId): array {
        $db = conectarDB();
        $query = "SELECT servicioId FROM citasServicios WHERE citaId = :citaId";
        $stmt = $db->prepare($query);
        $stmt->execute([':citaId' => $citaId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Actualiza / reprograma una cita existente usando una transacción.
     */
    public static function actualizar(int $citaId, int $usuarioId, string $fecha, string $hora, float $total, array $serviciosIds): bool {
        $db = conectarDB();

        try {
            $db->beginTransaction();

            // 1. Actualizar fecha, hora y total en la tabla citas
            $queryCita = "UPDATE citas 
                          SET fecha = :fecha, hora = :hora, total = :total 
                          WHERE id = :citaId AND usuarioId = :usuarioId AND estado = 'pendiente'";
            $stmtCita = $db->prepare($queryCita);
            $stmtCita->execute([
                ':fecha'     => $fecha,
                ':hora'      => $hora,
                ':total'     => $total,
                ':citaId'    => $citaId,
                ':usuarioId' => $usuarioId
            ]);

            // 2. Eliminar servicios anteriores asociados a esta cita
            $queryDelete = "DELETE FROM citasServicios WHERE citaId = :citaId";
            $stmtDelete = $db->prepare($queryDelete);
            $stmtDelete->execute([':citaId' => $citaId]);

            // 3. Insertar los nuevos servicios seleccionados
            $queryInsert = "INSERT INTO citasServicios (citaId, servicioId) VALUES (:citaId, :servicioId)";
            $stmtInsert = $db->prepare($queryInsert);
            foreach ($serviciosIds as $servicioId) {
                $stmtInsert->execute([
                    ':citaId'     => $citaId,
                    ':servicioId' => (int)$servicioId
                ]);
            }

            $db->commit();
            return true;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error al actualizar cita: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Devuelve la lista de horas que ya están reservadas para una fecha específica.
     */
    public static function horasOcupadas(string $fecha, ?int $excluirCitaId = null): array {
        $db = conectarDB();
        $query = "SELECT hora FROM citas WHERE fecha = :fecha AND estado != 'cancelada'";
        if ($excluirCitaId !== null) {
            $query .= " AND id != :excluirCitaId";
        }
        $stmt = $db->prepare($query);
        $params = [':fecha' => $fecha];
        if ($excluirCitaId !== null) {
            $params[':excluirCitaId'] = $excluirCitaId;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Obtiene todas las citas del salón con datos del cliente y servicios (con filtros opcionales).
     */
    public static function todas(?string $fecha = null, ?string $estado = null): array {
        $db = conectarDB();
        $query = "SELECT c.id, c.fecha, c.hora, c.total, c.estado, c.created_at,
                         u.nombre AS cliente_nombre, u.apellido AS cliente_apellido,
                         u.email AS cliente_email, u.telefono AS cliente_telefono,
                         GROUP_CONCAT(s.nombre SEPARATOR ', ') AS servicios,
                         SUM(s.duracion) AS duracion_total
                  FROM citas c
                  LEFT JOIN usuarios u ON c.usuarioId = u.id
                  LEFT JOIN citasServicios cs ON c.id = cs.citaId
                  LEFT JOIN servicios s ON cs.servicioId = s.id
                  WHERE 1=1";

        $params = [];
        if (!empty($fecha)) {
            $query .= " AND c.fecha = :fecha";
            $params[':fecha'] = $fecha;
        }
        if (!empty($estado)) {
            $query .= " AND c.estado = :estado";
            $params[':estado'] = $estado;
        }

        $query .= " GROUP BY c.id ORDER BY c.fecha ASC, c.hora ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve las métricas para el Dashboard del Administrador
     */
    public static function metricas(): array {
        $db = conectarDB();
        $hoy = date('Y-m-d');

        // 1. Citas de hoy
        $stmtHoy = $db->prepare("SELECT COUNT(*) FROM citas WHERE fecha = :hoy AND estado != 'cancelada'");
        $stmtHoy->execute([':hoy' => $hoy]);
        $citasHoy = (int)$stmtHoy->fetchColumn();

        // 2. Ingresos del mes
        $stmtMes = $db->query("SELECT COALESCE(SUM(total), 0) FROM citas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado != 'cancelada'");
        $ingresosMes = (float)$stmtMes->fetchColumn();

        // 3. Citas pendientes por atender
        $citasPendientes = (int)$db->query("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente'")->fetchColumn();

        // 4. Clientes totales
        $totalClientes = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE admin = 0")->fetchColumn();

        return [
            'citas_hoy'        => $citasHoy,
            'ingresos_mes'     => $ingresosMes,
            'citas_pendientes' => $citasPendientes,
            'total_clientes'   => $totalClientes
        ];
    }

    /**
     * Cambia el estado de una cita (confirmada, completada, cancelada)
     */
    public static function cambiarEstado(int $citaId, string $nuevoEstado): bool {
        $db = conectarDB();
        $query = "UPDATE citas SET estado = :estado WHERE id = :id";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':estado' => $nuevoEstado,
            ':id'     => $citaId
        ]);
    }

    /**
     * Devuelve el estado de cada una de las 10 franjas horarias para un día específico
     */
    public static function estadoHorariosDia(string $fecha): array {
        $db = conectarDB();
        $horasSalon = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];

        // Consultar citas activas del día
        $query = "SELECT c.id, c.hora, c.total, c.estado, c.usuarioId,
                         u.nombre AS cliente_nombre, u.apellido AS cliente_apellido
                  FROM citas c
                  LEFT JOIN usuarios u ON c.usuarioId = u.id
                  WHERE c.fecha = :fecha AND c.estado != 'cancelada'";
        $stmt = $db->prepare($query);
        $stmt->execute([':fecha' => $fecha]);
        $citasDia = $stmt->fetchAll();

        // Mapear por hora HH:MM
        $citasPorHora = [];
        foreach ($citasDia as $c) {
            $h = substr($c['hora'], 0, 5);
            $citasPorHora[$h] = $c;
        }

        $resultado = [];
        foreach ($horasSalon as $h) {
            if (isset($citasPorHora[$h])) {
                $cita = $citasPorHora[$h];
                $esBloqueoAdmin = ((float)$cita['total'] == 0.0 && empty($cita['cliente_nombre']));
                $resultado[] = [
                    'hora'    => $h,
                    'estado'  => $esBloqueoAdmin ? 'bloqueado' : 'cita',
                    'detalle' => $esBloqueoAdmin ? 'Bloqueo administrativo' : ($cita['cliente_nombre'] . ' ' . $cita['cliente_apellido']),
                    'cita_id' => $cita['id']
                ];
            } else {
                $resultado[] = [
                    'hora'    => $h,
                    'estado'  => 'libre',
                    'detalle' => 'Disponible',
                    'cita_id' => null
                ];
            }
        }
        return $resultado;
    }

    /**
     * Bloquea una hora administrativamente para que ningún cliente pueda agendar
     */
    public static function bloquearHorario(int $adminId, string $fecha, string $hora): bool {
        $db = conectarDB();
        $query = "INSERT INTO citas (fecha, hora, usuarioId, total, estado) 
                  VALUES (:fecha, :hora, :adminId, 0.00, 'confirmada')";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':fecha'   => $fecha,
            ':hora'    => $hora,
            ':adminId' => $adminId
        ]);
    }

    /**
     * Desbloquea / libera un horario previamente bloqueado por la administración
     */
    public static function liberarHorario(string $fecha, string $hora): bool {
        $db = conectarDB();
        $query = "DELETE FROM citas 
                  WHERE fecha = :fecha AND hora = :hora AND total = 0.00";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':fecha' => $fecha,
            ':hora'  => $hora
        ]);
    }

}