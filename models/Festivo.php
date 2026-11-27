<?php
// Festivos de Colombia. La API Nager.Date solo se usa para POBLAR la tabla `festivos`;
// las validaciones siempre consultan la BD (si la API se cae, el sistema sigue validando).

class Festivo {

    private const API_URL = 'https://date.nager.at/api/v3/PublicHolidays/%d/CO';
    private const FUENTE  = 'nager.date';

    /** Devuelve el nombre del festivo o null si la fecha es laboral. */
    public static function buscar(string $fecha): ?string {
        self::asegurarAnio((int)substr($fecha, 0, 4));
        $stmt = conectarDB()->prepare('SELECT nombre FROM festivos WHERE fecha = ?');
        $stmt->execute([$fecha]);
        $nombre = $stmt->fetchColumn();
        return $nombre === false ? null : $nombre;
    }

    /** Festivos de un año: [['fecha' => 'Y-m-d', 'nombre' => '...'], ...] */
    public static function porAnio(int $anio): array {
        self::asegurarAnio($anio);
        $stmt = conectarDB()->prepare(
            'SELECT fecha, nombre FROM festivos WHERE fecha BETWEEN ? AND ? ORDER BY fecha'
        );
        $stmt->execute(["$anio-01-01", "$anio-12-31"]);
        return $stmt->fetchAll();
    }

    /**
     * Descarga los festivos del año desde la API y los guarda (UPSERT en una transacción).
     * @return int cantidad de festivos guardados
     * @throws RuntimeException si la API falla o responde algo inválido
     */
    public static function sincronizar(int $anio): int {
        $festivos = self::consultarApi($anio);

        $db   = conectarDB();
        $stmt = $db->prepare(
            'INSERT INTO festivos (fecha, nombre, fuente) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), fuente = VALUES(fuente)'
        );
        $db->beginTransaction();
        try {
            foreach ($festivos as $f) {
                $stmt->execute([$f['fecha'], $f['nombre'], self::FUENTE]);
            }
            $db->commit();
        } catch (Throwable $ex) {
            $db->rollBack();     // todo o nada: nunca queda un año a medias
            throw $ex;
        }
        return count($festivos);
    }

    /** Carga perezosa: si el año no tiene festivos en BD, los trae una vez de la API. */
    private static function asegurarAnio(int $anio): void {
        static $revisados = [];                       // caché por petición: 1 COUNT por año
        if ($anio < 2000 || $anio > 2100 || isset($revisados[$anio])) { return; }
        $revisados[$anio] = true;

        $stmt = conectarDB()->prepare('SELECT COUNT(*) FROM festivos WHERE fecha BETWEEN ? AND ?');
        $stmt->execute(["$anio-01-01", "$anio-12-31"]);
        if ((int)$stmt->fetchColumn() > 0) { return; }

        try {
            self::sincronizar($anio);
        } catch (RuntimeException $ex) {
            // No tumbamos la app: se registra y se reintenta en la próxima petición
            error_log('[Festivos] ' . $ex->getMessage());
        }
    }

    /** Consumo de la API: request -> status -> parseo -> validación de cada item. */
    private static function consultarApi(int $anio): array {
        $ch = curl_init(sprintf(self::API_URL, $anio));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,              // devolver el cuerpo como string en vez de imprimirlo
            CURLOPT_CONNECTTIMEOUT => 3,                 // máx. para abrir la conexión TCP/TLS
            CURLOPT_TIMEOUT        => 5,                 // máx. para toda la petición
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'AppSalon/1.0',
            CURLOPT_SSL_VERIFYPEER => true,              // nunca desactivar la verificación TLS
        ]);

        $cuerpo = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);

        if ($cuerpo === false) {
            throw new RuntimeException("Error de red consultando festivos $anio: $error");
        }
        if ($status !== 200) {
            throw new RuntimeException("La API de festivos respondió HTTP $status para $anio");
        }

        try {
            $json = json_decode($cuerpo, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            throw new RuntimeException('La API de festivos devolvió JSON inválido: ' . $ex->getMessage());
        }
        if (!is_array($json)) {
            throw new RuntimeException('Formato inesperado en la respuesta de festivos');
        }

        // Nunca confíes en datos externos: valida cada campo antes de guardarlo
        $festivos = [];
        foreach ($json as $item) {
            $fecha = is_array($item) ? ($item['date'] ?? '') : '';
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if (!$d || $d->format('Y-m-d') !== $fecha || (int)$d->format('Y') !== $anio) { continue; }

            $nombre = trim((string)($item['localName'] ?? $item['name'] ?? 'Festivo'));
            $festivos[] = ['fecha' => $fecha, 'nombre' => mb_substr($nombre, 0, 100)];
        }
        if (!$festivos) {
            throw new RuntimeException("La API no devolvió festivos válidos para $anio");
        }
        return $festivos;
    }
}