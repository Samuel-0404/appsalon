<?php
// Responsable: A  (entrega final, módulo de reportes)
// Todas las consultas filtran por período con c.fecha BETWEEN :ini AND :fin (usa idx_citas_fecha_estado)
// y excluyen los bloqueos administrativos: citas cuyo usuario es admin.
//
// Definiciones de negocio:
//   - Ingresos realizados: citas 'completada'.
//   - Ingresos por cobrar:  citas 'pendiente' + 'confirmada'.
//   - Las citas 'cancelada' se cuentan, pero no suman dinero.
// Limitación conocida: citasServicios no guarda el precio histórico, así que el desglose por
// servicio usa el precio ACTUAL del servicio; el total por cita (citas.total) sí es histórico.

class Report {

    private const FILTRO_BASE = "c.fecha BETWEEN :ini AND :fin AND (u.id IS NULL OR u.admin = 0)";

    /** KPIs del período. */
    public static function resumen(string $ini, string $fin): array {
        $stmt = conectarDB()->prepare(
            "SELECT COUNT(*)                                                         AS total_citas,
                    SUM(c.estado = 'pendiente')                                      AS pendientes,
                    SUM(c.estado = 'confirmada')                                     AS confirmadas,
                    SUM(c.estado = 'completada')                                     AS completadas,
                    SUM(c.estado = 'cancelada')                                      AS canceladas,
                    COALESCE(SUM(CASE WHEN c.estado = 'completada' THEN c.total END), 0)                  AS ingresos_realizados,
                    COALESCE(SUM(CASE WHEN c.estado IN ('pendiente','confirmada') THEN c.total END), 0)   AS ingresos_por_cobrar,
                    COUNT(DISTINCT CASE WHEN c.estado <> 'cancelada' THEN c.usuarioId END)                AS clientes_unicos
               FROM citas c
               LEFT JOIN usuarios u ON u.id = c.usuarioId
              WHERE " . self::FILTRO_BASE
        );
        $stmt->execute([':ini' => $ini, ':fin' => $fin]);
        $r = array_map(fn($v) => $v === null ? 0 : $v, $stmt->fetch());

        $r['ticket_promedio'] = $r['completadas'] > 0 ? (float)$r['ingresos_realizados'] / (int)$r['completadas'] : 0.0;
        $r['tasa_cancelacion'] = $r['total_citas'] > 0 ? (int)$r['canceladas'] * 100 / (int)$r['total_citas'] : 0.0;
        return $r;
    }

    /** Detalle de citas del período (opcionalmente por estado). */
    public static function citas(string $ini, string $fin, ?string $estado = null): array {
        $sql = "SELECT c.id, c.fecha, TIME_FORMAT(c.hora, '%H:%i') AS hora, c.estado, c.total,
                       CONCAT_WS(' ', u.nombre, u.apellido) AS cliente, u.email,
                       GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') AS servicios,
                       COALESCE(SUM(s.duracion), 0) AS duracion
                  FROM citas c
                  LEFT JOIN usuarios u        ON u.id = c.usuarioId
                  LEFT JOIN citasServicios cs ON cs.citaId = c.id
                  LEFT JOIN servicios s       ON s.id = cs.servicioId
                 WHERE " . self::FILTRO_BASE
             . ($estado ? " AND c.estado = :estado" : "") . "
                 GROUP BY c.id
                 ORDER BY c.fecha, c.hora";
        $params = [':ini' => $ini, ':fin' => $fin];
        if ($estado) { $params[':estado'] = $estado; }

        $stmt = conectarDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Reservas e ingresos agrupados por servicio. */
    public static function ingresosPorServicio(string $ini, string $fin): array {
        $stmt = conectarDB()->prepare(
            "SELECT s.id, s.nombre,
                    COUNT(CASE WHEN c.estado <> 'cancelada' THEN 1 END)                      AS reservas,
                    COUNT(CASE WHEN c.estado = 'completada' THEN 1 END)                      AS realizados,
                    COALESCE(SUM(CASE WHEN c.estado = 'completada' THEN s.precio END), 0)    AS ingresos_realizados,
                    COALESCE(SUM(CASE WHEN c.estado IN ('pendiente','confirmada') THEN s.precio END), 0) AS ingresos_por_cobrar
               FROM citasServicios cs
               INNER JOIN citas c     ON c.id = cs.citaId
               INNER JOIN servicios s ON s.id = cs.servicioId
               LEFT JOIN usuarios u   ON u.id = c.usuarioId
              WHERE " . self::FILTRO_BASE . "
              GROUP BY s.id, s.nombre
              ORDER BY ingresos_realizados DESC, reservas DESC"
        );
        $stmt->execute([':ini' => $ini, ':fin' => $fin]);
        return $stmt->fetchAll();
    }

    /** Serie diaria continua (los días sin citas salen en 0) para la gráfica de tendencia. */
    public static function porDia(string $ini, string $fin): array {
        $stmt = conectarDB()->prepare(
            "SELECT c.fecha,
                    COUNT(CASE WHEN c.estado <> 'cancelada' THEN 1 END)                     AS citas,
                    COALESCE(SUM(CASE WHEN c.estado = 'completada' THEN c.total END), 0)    AS ingresos
               FROM citas c
               LEFT JOIN usuarios u ON u.id = c.usuarioId
              WHERE " . self::FILTRO_BASE . "
              GROUP BY c.fecha"
        );
        $stmt->execute([':ini' => $ini, ':fin' => $fin]);
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
