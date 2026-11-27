<?php
// Responsable: A  (entrega final)
// Horario de atención del salón por día de la semana (tabla horario_salon).
// Es la única fuente de verdad de "cuándo se atiende": la usan ValidadorCita, el calendario,
// la agenda de disponibilidad y la API. Los festivos se manejan aparte (models/Festivo.php).

class Horario {

    public const DIAS        = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    public const DIAS_PLURAL = ['domingos', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábados'];

    // Valores por defecto si la migración 004 aún no se aplicó (la app sigue funcionando como antes)
    private const POR_DEFECTO = ['abierto' => 1, 'apertura' => '09:00', 'cierre' => '19:00'];

    private static ?array $cache = null;

    /** Los 7 días: [0..6 => ['dia_semana','nombre','abierto','apertura'(H:i),'cierre'(H:i)]] */
    public static function semana(): array {
        if (self::$cache !== null) { return self::$cache; }

        $filas = [];
        try {
            $stmt = conectarDB()->query(
                "SELECT dia_semana, abierto, TIME_FORMAT(apertura, '%H:%i') AS apertura, TIME_FORMAT(cierre, '%H:%i') AS cierre
                   FROM horario_salon"
            );
            foreach ($stmt->fetchAll() as $f) { $filas[(int)$f['dia_semana']] = $f; }
        } catch (PDOException $ex) {
            error_log('[Horario] Tabla horario_salon no disponible, se usa el horario por defecto: ' . $ex->getMessage());
        }

        $semana = [];
        foreach (self::DIAS as $n => $nombre) {
            $f = $filas[$n] ?? ['abierto' => $n === 0 ? 0 : 1] + self::POR_DEFECTO;   // domingo cerrado por defecto
            $semana[$n] = [
                'dia_semana' => $n,
                'nombre'     => $nombre,
                'abierto'    => (bool)$f['abierto'],
                'apertura'   => $f['apertura'],
                'cierre'     => $f['cierre'],
            ];
        }
        return self::$cache = $semana;
    }

    /** Horario de una fecha 'Y-m-d', o null si ese día el salón no abre. */
    public static function delDia(string $fecha): ?array {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$d) { return null; }
        $h = self::semana()[(int)$d->format('w')];
        return $h['abierto'] ? $h : null;
    }

    /**
     * Franjas de inicio (cada hora en punto) en las que se puede empezar una cita ese día.
     * Ej.: 09:00–19:00 => ['09:00', ..., '18:00'].
     */
    public static function franjas(string $fecha): array {
        $h = self::delDia($fecha);
        return $h ? self::franjasEntre($h['apertura'], $h['cierre']) : [];
    }

    /** Unión de las franjas de todos los días abiertos (para listas desplegables sin fecha fija). */
    public static function franjasSemana(): array {
        $todas = [];
        foreach (self::semana() as $h) {
            if ($h['abierto']) { $todas = array_merge($todas, self::franjasEntre($h['apertura'], $h['cierre'])); }
        }
        $todas = array_values(array_unique($todas));
        sort($todas);
        return $todas;
    }

    /**
     * Guarda el horario semanal. $datos[dia] = ['abierto' => bool, 'apertura' => 'H:i', 'cierre' => 'H:i'].
     * @return array<int,string> errores por día (vacío si se guardó)
     */
    public static function guardar(array $datos): array {
        $errores = [];
        $limpios = [];
        foreach (self::DIAS as $n => $nombre) {
            $d        = $datos[$n] ?? [];
            $abierto  = !empty($d['abierto']);
            $apertura = (string)($d['apertura'] ?? '');
            $cierre   = (string)($d['cierre'] ?? '');

            if (!preg_match('/^([01]\d|2[0-3]):00$/', $apertura) || !preg_match('/^([01]\d|2[0-3]):00$/', $cierre)) {
                $errores[$n] = "$nombre: las horas deben ser en punto (HH:00).";
            } elseif ($apertura >= $cierre) {
                $errores[$n] = "$nombre: la apertura debe ser anterior al cierre.";
            }
            $limpios[$n] = [$abierto ? 1 : 0, $apertura, $cierre];
        }
        if ($errores) { return $errores; }

        $db   = conectarDB();
        $stmt = $db->prepare(
            'INSERT INTO horario_salon (dia_semana, abierto, apertura, cierre) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE abierto = VALUES(abierto), apertura = VALUES(apertura), cierre = VALUES(cierre)'
        );
        $db->beginTransaction();
        try {
            foreach ($limpios as $n => [$abierto, $apertura, $cierre]) {
                $stmt->execute([$n, $abierto, $apertura, $cierre]);
            }
            $db->commit();
        } catch (Throwable $ex) {
            $db->rollBack();
            throw $ex;
        }
        self::$cache = null;
        return [];
    }

    /** Horas en punto desde la apertura hasta una hora antes del cierre. */
    private static function franjasEntre(string $apertura, string $cierre): array {
        $franjas = [];
        for ($h = (int)substr($apertura, 0, 2); sprintf('%02d:00', $h + 1) <= $cierre; $h++) {
            $franjas[] = sprintf('%02d:00', $h);
        }
        return $franjas;
    }
}
