<?php
// Entrega final (Semana 12-16)

class Appointment {

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
     * Intervalos ocupados de un día: cada cita activa ocupa [hora, hora + suma de duraciones).
     * Una cita sin servicios es un bloqueo administrativo y ocupa ValidadorCita::DURACION_SIN_SERVICIOS.
     * @return array<int, array{id:int, inicio:string, fin:string, bloqueo:bool, cliente:?string}>
     */
    public static function intervalos(string $fecha, ?int $excluirCitaId = null): array {
        $sql = "SELECT c.id, TIME_FORMAT(c.hora, '%H:%i') AS inicio,
                       TIME_FORMAT(ADDTIME(c.hora, SEC_TO_TIME(COALESCE(SUM(s.duracion), :durDefecto) * 60)), '%H:%i') AS fin,
                       COUNT(cs.id) = 0 AS bloqueo,
                       CONCAT_WS(' ', u.nombre, u.apellido) AS cliente
                  FROM citas c
                  LEFT JOIN citasServicios cs ON cs.citaId = c.id
                  LEFT JOIN servicios s       ON s.id = cs.servicioId
                  LEFT JOIN usuarios u        ON u.id = c.usuarioId
                 WHERE c.fecha = :fecha AND c.estado <> 'cancelada'"
             . ($excluirCitaId !== null ? " AND c.id <> :excluir" : "") . "
                 GROUP BY c.id, c.hora, u.nombre, u.apellido
                 ORDER BY c.hora";
        $params = [':fecha' => $fecha, ':durDefecto' => ValidadorCita::DURACION_SIN_SERVICIOS];
        if ($excluirCitaId !== null) { $params[':excluir'] = $excluirCitaId; }

        $stmt = conectarDB()->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($f) => [
            'id'      => (int)$f['id'],
            'inicio'  => $f['inicio'],
            'fin'     => $f['fin'],
            'bloqueo' => (bool)$f['bloqueo'],
            'cliente' => $f['cliente'] ?: null,
        ], $stmt->fetchAll());
    }

    /**
     * Agenda del salón entre dos fechas (incluidas), con datos del cliente y servicios.
     * Excluye los bloqueos administrativos (citas sin servicios): esos se gestionan en Disponibilidad.
     */
    public static function todas(?string $desde = null, ?string $hasta = null, ?string $estado = null): array {
        $db = conectarDB();
        $query = "SELECT c.id, c.fecha, c.hora, c.total, c.estado, c.created_at,
                         u.nombre AS cliente_nombre, u.apellido AS cliente_apellido,
                         u.email AS cliente_email, u.telefono AS cliente_telefono,
                         GROUP_CONCAT(s.nombre SEPARATOR ', ') AS servicios,
                         SUM(s.duracion) AS duracion_total
                  FROM citas c
                  LEFT JOIN usuarios u ON c.usuarioId = u.id
                  INNER JOIN citasServicios cs ON c.id = cs.citaId
                  INNER JOIN servicios s ON cs.servicioId = s.id
                  WHERE 1=1";

        $params = [];
        if (!empty($desde)) {
            $query .= " AND c.fecha >= :desde";
            $params[':desde'] = $desde;
        }
        if (!empty($hasta)) {
            $query .= " AND c.fecha <= :hasta";
            $params[':hasta'] = $hasta;
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
        $stmtHoy = $db->prepare("SELECT COUNT(*) FROM citas c WHERE c.fecha = :hoy AND c.estado != 'cancelada'
                                   AND EXISTS (SELECT 1 FROM citasServicios cs WHERE cs.citaId = c.id)");
        $stmtHoy->execute([':hoy' => $hoy]);
        $citasHoy = (int)$stmtHoy->fetchColumn();

        // 2. Ingresos del mes
        $stmtMes = $db->query("SELECT COALESCE(SUM(total), 0) FROM citas WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado != 'cancelada'");
        $ingresosMes = (float)$stmtMes->fetchColumn();

        // 3. Citas pendientes por atender
        $citasPendientes = (int)$db->query("SELECT COUNT(*) FROM citas WHERE estado = 'pendiente'")->fetchColumn();

        // 4. Clientes totales (solo rol cliente: no cuenta administradores ni empleados)
        $totalClientes = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'")->fetchColumn();

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
     * Estado de cada franja horaria (según el horario configurado) para un día:
     * 'libre', 'cita' (la cubre una cita de un cliente) o 'bloqueado' (bloqueo administrativo).
     * Una franja está ocupada si se cruza con [inicio, fin) de alguna cita, no solo si coincide la hora de inicio.
     */
    public static function estadoHorariosDia(string $fecha): array {
        $intervalos = self::intervalos($fecha);
        $resultado  = [];

        foreach (Horario::franjas($fecha) as $h) {
            $finFranja = sprintf('%02d:00', (int)substr($h, 0, 2) + 1);
            $ocupante  = null;
            foreach ($intervalos as $iv) {
                if ($iv['inicio'] < $finFranja && $h < $iv['fin']) { $ocupante = $iv; break; }
            }

            if ($ocupante === null) {
                $resultado[] = ['hora' => $h, 'estado' => 'libre', 'detalle' => 'Disponible', 'cita_id' => null];
            } elseif ($ocupante['bloqueo']) {
                $resultado[] = ['hora' => $h, 'estado' => 'bloqueado', 'detalle' => 'Bloqueo administrativo',
                                'cita_id' => $ocupante['id'], 'inicio' => $ocupante['inicio']];
            } else {
                $resultado[] = ['hora' => $h, 'estado' => 'cita',
                                'detalle' => ($ocupante['cliente'] ?? 'Cliente') . ' (' . $ocupante['inicio'] . '–' . $ocupante['fin'] . ')',
                                'cita_id' => $ocupante['id']];
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
     * Libera un bloqueo administrativo. Solo borra citas SIN servicios: nunca puede borrar la cita de un cliente.
     */
    public static function liberarHorario(string $fecha, string $hora): bool {
        $db = conectarDB();
        $query = "DELETE c FROM citas c
                  WHERE c.fecha = :fecha AND c.hora = :hora
                    AND NOT EXISTS (SELECT 1 FROM citasServicios cs WHERE cs.citaId = c.id)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':fecha' => $fecha,
            ':hora'  => $hora
        ]);
        return $stmt->rowCount() > 0;
    }

}
