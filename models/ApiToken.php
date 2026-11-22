<?php
// Responsable: A  (entrega final, API REST)
// Tokens tipo "Bearer" opacos: 32 bytes aleatorios (256 bits) en hexadecimal.
// En la BD solo se guarda hash('sha256', token): el token en claro nunca se persiste.

class ApiToken {

    /** Vigencia del token en horas */
    public const HORAS_VIGENCIA = 8;

    /**
     * Emite un token nuevo para el usuario y limpia sus tokens vencidos.
     * @return array{token: string, expira: string}
     */
    public static function emitir(int $usuarioId): array {
        $db = conectarDB();

        $db->prepare("DELETE FROM api_tokens WHERE usuarioId = :u AND expira < NOW()")
           ->execute([':u' => $usuarioId]);

        $token = bin2hex(random_bytes(32));

        // La expiración la calcula MySQL con el mismo reloj (NOW()) con el que luego se valida
        $db->prepare("INSERT INTO api_tokens (usuarioId, token_hash, expira)
                      VALUES (:u, :h, DATE_ADD(NOW(), INTERVAL " . self::HORAS_VIGENCIA . " HOUR))")
           ->execute([':u' => $usuarioId, ':h' => hash('sha256', $token)]);

        $stmt = $db->prepare("SELECT expira FROM api_tokens WHERE id = :id");
        $stmt->execute([':id' => (int)$db->lastInsertId()]);
        $expira = $stmt->fetchColumn();

        return ['token' => $token, 'expira' => $expira];
    }

    /**
     * Devuelve el usuario dueño de un token vigente, o null.
     * La búsqueda es por el hash (índice UNIQUE), así que no hay comparación de cadenas en PHP
     * que pueda filtrar información por tiempo de respuesta.
     */
    public static function usuarioDeToken(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $db = conectarDB();
        $stmt = $db->prepare(
            "SELECT u.id, u.nombre, u.apellido, u.email, u.admin, u.confirmado
             FROM api_tokens t
             INNER JOIN usuarios u ON u.id = t.usuarioId
             WHERE t.token_hash = :h AND t.expira > NOW()
             LIMIT 1"
        );
        $stmt->execute([':h' => hash('sha256', $token)]);
        $usuario = $stmt->fetch();
        return $usuario ?: null;
    }

    /** Revoca (borra) un token. Devuelve true si existía. */
    public static function revocar(string $token): bool {
        $stmt = conectarDB()->prepare("DELETE FROM api_tokens WHERE token_hash = :h");
        $stmt->execute([':h' => hash('sha256', $token)]);
        return $stmt->rowCount() > 0;
    }
}
