<?php
// Responsable: A  (entrega final)
// Reglas de negocio de una cita en un solo lugar. Hoy las usa la API; la idea es que
// AppointmentController también las use para que web y API validen exactamente igual.

class ValidadorCita {

    public const HORA_APERTURA = '09:00';
    public const HORA_CIERRE   = '19:00';   // la última cita debe TERMINAR a esta hora como máximo
    public const DURACION_SIN_SERVICIOS = 60; // minutos que ocupa un bloqueo administrativo (cita sin servicios)

    /**
     * Valida y normaliza los datos de una cita.
     *
     * @param array    $entrada        ['fecha' => 'Y-m-d', 'hora' => 'H:i', 'servicios' => int[]]
     * @param int|null $excluirCitaId  id de la cita que se está modificando (no choca consigo misma)
     * @return array{0: array<string,string>, 1: array}  [errores, datos normalizados]
     *         datos = ['fecha','hora','servicios' => int[], 'total' => float, 'duracion' => int, 'nombres' => string[]]
     */
    public static function validar(array $entrada, ?int $excluirCitaId = null): array {
        $errores = [];
        $datos   = [];

        // ---- Fecha: formato estricto, no pasada, no domingo ----
        $fechaTxt = is_string($entrada['fecha'] ?? null) ? trim($entrada['fecha']) : '';
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaTxt);
        if (!$fecha || $fecha->format('Y-m-d') !== $fechaTxt) {
            $errores['fecha'] = 'La fecha es obligatoria con formato AAAA-MM-DD.';
        } elseif ($fechaTxt < date('Y-m-d')) {
            $errores['fecha'] = 'No se permiten fechas pasadas.';
        } elseif ($fecha->format('w') === '0') {
            $errores['fecha'] = 'El salón no atiende los domingos.';
        } else {
            $datos['fecha'] = $fechaTxt;
        }

        // ---- Hora: HH:MM en punto, dentro del horario ----
        $horaTxt = is_string($entrada['hora'] ?? null) ? substr(trim($entrada['hora']), 0, 5) : '';
        if (!preg_match('/^([01]\d|2[0-3]):00$/', $horaTxt)) {
            $errores['hora'] = 'La hora es obligatoria con formato HH:00 (franjas de una hora).';
        } elseif ($horaTxt < self::HORA_APERTURA || $horaTxt >= self::HORA_CIERRE) {
            $errores['hora'] = 'El horario de atención es de ' . self::HORA_APERTURA . ' a ' . self::HORA_CIERRE . '.';
        } elseif (isset($datos['fecha']) && $datos['fecha'] === date('Y-m-d') && $horaTxt <= date('H:i')) {
            $errores['hora'] = 'Esa hora de hoy ya pasó.';
        } else {
            $datos['hora'] = $horaTxt;
        }

        // ---- Servicios: lista no vacía de ids existentes y activos ----
        $ids = $entrada['servicios'] ?? null;
        if (!is_array($ids) || $ids === []) {
            $errores['servicios'] = 'Debes enviar al menos un servicio.';
        } else {
            $limpios = [];
            foreach ($ids as $id) {
                $v = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($v === false) { $errores['servicios'] = 'Los servicios deben ser ids enteros positivos.'; break; }
                $limpios[$v] = $v; // elimina duplicados
            }
            if (!isset($errores['servicios'])) {
                $servicios = self::serviciosActivos(array_values($limpios));
                if (count($servicios) !== count($limpios)) {
                    $errores['servicios'] = 'Uno o más servicios no existen o no están disponibles.';
                } else {
                    // El total y la duración SIEMPRE se calculan en el servidor, nunca se reciben del cliente
                    $datos['servicios'] = array_map('intval', array_column($servicios, 'id'));
                    $datos['nombres']   = array_column($servicios, 'nombre');
                    $datos['total']     = array_sum(array_map('floatval', array_column($servicios, 'precio')));
                    $datos['duracion']  = array_sum(array_map('intval', array_column($servicios, 'duracion')));
                }
            }
        }

        // ---- Reglas que dependen de todo lo anterior ----
        if (!$errores) {
            $inicio = DateTimeImmutable::createFromFormat('Y-m-d H:i', $datos['fecha'] . ' ' . $datos['hora']);
            $fin    = $inicio->modify('+' . $datos['duracion'] . ' minutes');

            if ($fin->format('Y-m-d H:i') > $datos['fecha'] . ' ' . self::HORA_CIERRE) {
                $errores['hora'] = 'Los servicios duran ' . $datos['duracion'] . ' min y terminarían después del cierre ('
                                 . self::HORA_CIERRE . ').';
            } elseif (self::haySolapamiento($datos['fecha'], $inicio->format('H:i:s'), $fin->format('H:i:s'), $excluirCitaId)) {
                $errores['disponibilidad'] = 'El horario se cruza con otra cita o con un bloqueo del salón.';
            }
        }

        return [$errores, $datos];
    }

    /**
     * Intervalos semiabiertos [inicio, fin): dos citas chocan si A.inicio < B.fin y B.inicio < A.fin.
     * La duración de cada cita existente es la suma de sus servicios; una cita sin servicios
     * (bloqueo administrativo) ocupa DURACION_SIN_SERVICIOS minutos.
     */
    public static function haySolapamiento(string $fecha, string $inicio, string $fin, ?int $excluirCitaId = null): bool {
        $sql = "SELECT c.id
                  FROM citas c
                  LEFT JOIN citasServicios cs ON cs.citaId = c.id
                  LEFT JOIN servicios s       ON s.id = cs.servicioId
                 WHERE c.fecha = :fecha
                   AND c.estado <> 'cancelada'"
             . ($excluirCitaId !== null ? " AND c.id <> :excluir" : "") . "
                 GROUP BY c.id, c.hora
                HAVING c.hora < :fin
                   AND ADDTIME(c.hora, SEC_TO_TIME(COALESCE(SUM(s.duracion), :durDefecto) * 60)) > :inicio
                 LIMIT 1";
        $params = [':fecha' => $fecha, ':fin' => $fin, ':inicio' => $inicio, ':durDefecto' => self::DURACION_SIN_SERVICIOS];
        if ($excluirCitaId !== null) { $params[':excluir'] = $excluirCitaId; }

        $stmt = conectarDB()->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    /** Servicios activos cuyos ids están en la lista. */
    private static function serviciosActivos(array $ids): array {
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $stmt = conectarDB()->prepare(
            "SELECT id, nombre, precio, duracion FROM servicios WHERE activo = 1 AND id IN ($marcas) ORDER BY id"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll();
    }
}
