<?php
// Responsable: A  (entrega final, módulo de reportes)
// Todas las consultas filtran por período con c.fecha BETWEEN :ini AND :fin (usa idx_citas_fecha_estado)
// y excluyen los bloqueos administrativos: citas SIN servicios (las crea el personal en Disponibilidad).
//
// Definiciones de negocio:
//   - Ingresos realizados: citas 'completada'.
//   - Ingresos por cobrar:  citas 'pendiente' + 'confirmada'.
//   - Las citas 'cancelada' se cuentan, pero no suman dinero.
//   - Con filtro de servicio: solo cuentan las citas que incluyen ese servicio y el monto es el precio
//     de ESE servicio (no el total de la cita, que puede incluir otros servicios).
// Limitación conocida: citasServicios no guarda el precio histórico, así que el desglose por
// servicio usa el precio ACTUAL del servicio; el total por cita (citas.total) sí es histórico.

class Report {

    private const FILTRO_BASE = "c.fecha BETWEEN :ini AND :fin
                                 AND EXISTS (SELECT 1 FROM citasServicios cb WHERE cb.citaId = c.id)";

    /**
     * JOIN y expresión de monto según haya o no filtro de servicio.
     * @return array{0: string, 1: string, 2: array} [join, monto, params]
     */
    private static function porServicio(?int $servicio): array {
        if ($servicio === null) {
            return ['', 'c.total', []];
        }
        return [
            "INNER JOIN citasServicios csf ON csf.citaId = c.id AND csf.servicioId = :serv
             INNER JOIN servicios sf ON sf.id = csf.servicioId",
            'sf.precio',
            [':serv' => $servicio],
        ];
    }

    /** KPIs del período. */
    public static function resumen(string $ini, string $fin, ?int $servicio = null): array {
        [$join, $monto, $extra] = self::porServicio($servicio);
        $stmt = conectarDB()->prepare(
            "SELECT COUNT(*)                                                         AS total_citas,
                    SUM(c.estado = 'pendiente')                                      AS pendientes,
                    SUM(c.estado = 'confirmada')                                     AS confirmadas,
                    SUM(c.estado = 'completada')                                     AS completadas,
                    SUM(c.estado = 'cancelada')                                      AS canceladas,
                    COALESCE(SUM(CASE WHEN c.estado = 'completada' THEN $monto END), 0)                  AS ingresos_realizados,
                    COALESCE(SUM(CASE WHEN c.estado IN ('pendiente','confirmada') THEN $monto END), 0)   AS ingresos_por_cobrar,
                    COUNT(DISTINCT CASE WHEN c.estado <> 'cancelada' THEN c.usuarioId END)               AS clientes_unicos
               FROM citas c
               $join
              WHERE " . self::FILTRO_BASE
        );
        $stmt->execute([':ini' => $ini, ':fin' => $fin] + $extra);
        $r = array_map(fn($v) => $v === null ? 0 : $v, $stmt->fetch());

        $r['ticket_promedio'] = $r['completadas'] > 0 ? (float)$r['ingresos_realizados'] / (int)$r['completadas'] : 0.0;
        $r['tasa_cancelacion'] = $r['total_citas'] > 0 ? (int)$r['canceladas'] * 100 / (int)$r['total_citas'] : 0.0;
        return $r;
    }

    /** Detalle de citas del período (opcionalmente por estado y por servicio). */
    public static function citas(string $ini, string $fin, ?string $estado = null, ?int $servicio = null): array {
        $sql = "SELECT c.id, c.fecha, TIME_FORMAT(c.hora, '%H:%i') AS hora, c.estado, c.total,
                       CONCAT_WS(' ', u.nombre, u.apellido) AS cliente, u.email,
                       GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') AS servicios,
                       COALESCE(SUM(s.duracion), 0) AS duracion
                  FROM citas c
                  LEFT JOIN usuarios u        ON u.id = c.usuarioId
                  LEFT JOIN citasServicios cs ON cs.citaId = c.id
                  LEFT JOIN servicios s       ON s.id = cs.servicioId
                 WHERE " . self::FILTRO_BASE
             . ($estado ? " AND c.estado = :estado" : "")
             . ($servicio ? " AND EXISTS (SELECT 1 FROM citasServicios x WHERE x.citaId = c.id AND x.servicioId = :serv)" : "") . "
                 GROUP BY c.id
                 ORDER BY c.fecha, c.hora";
        $params = [':ini' => $ini, ':fin' => $fin];
        if ($estado)   { $params[':estado'] = $estado; }
        if ($servicio) { $params[':serv'] = $servicio; }

        $stmt = conectarDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Reservas e ingresos agrupados por servicio (o solo del servicio filtrado). */
    public static function ingresosPorServicio(string $ini, string $fin, ?int $servicio = null): array {
        $stmt = conectarDB()->prepare(
            "SELECT s.id, s.nombre,
                    COUNT(CASE WHEN c.estado <> 'cancelada' THEN 1 END)                      AS reservas,
                    COUNT(CASE WHEN c.estado = 'completada' THEN 1 END)                      AS realizados,
                    COALESCE(SUM(CASE WHEN c.estado = 'completada' THEN s.precio END), 0)    AS ingresos_realizados,
                    COALESCE(SUM(CASE WHEN c.estado IN ('pendiente','confirmada') THEN s.precio END), 0) AS ingresos_por_cobrar
               FROM citasServicios cs
               INNER JOIN citas c     ON c.id = cs.citaId
               INNER JOIN servicios s ON s.id = cs.servicioId
              WHERE " . self::FILTRO_BASE . ($servicio ? " AND s.id = :serv" : "") . "
              GROUP BY s.id, s.nombre
              ORDER BY ingresos_realizados DESC, reservas DESC"
        );
        $stmt->execute([':ini' => $ini, ':fin' => $fin] + ($servicio ? [':serv' => $servicio] : []));
        return $stmt->fetchAll();
    }

    /** Serie diaria continua (los días sin citas salen en 0) para la gráfica de tendencia. */
    public static function porDia(string $ini, string $fin, ?int $servicio = null): array {
        [$join, $monto, $extra] = self::porServicio($servicio);
        $stmt = conectarDB()->prepare(
            "SELECT c.fecha,
                    COUNT(CASE WHEN c.estado <> 'cancelada' THEN 1 END)                     AS citas,
                    COALESCE(SUM(CASE WHEN c.estado = 'completada' THEN $monto END), 0)     AS ingresos
               FROM citas c
               $join
              WHERE " . self::FILTRO_BASE . "
              GROUP BY c.fecha"
        );
        $stmt->execute([':ini' => $ini, ':fin' => $fin] + $extra);
        $datos = [];
        foreach ($stmt->fetchAll() as $f) { $datos[$f['fecha']] = $f; }

        $serie = [];
        $dia = new DateTimeImmutable($ini);
        $ult = new DateTimeImmutable($fin);
        for (; $dia <= $ult; $dia = $dia->modify('+1 day')) {
            $k = $dia->format('Y-m-d');
            $serie[] = ['fecha' => $k, 'citas' => (int)($datos[$k]['citas'] ?? 0), 'ingresos' => (float)($datos[$k]['ingresos'] ?? 0)];
        }
        return $serie;
    }
}
