<?php
// Responsable: A
// Usuarios del sistema. Roles: cliente | empleado | admin (columna rol).
// La columna admin se mantiene como espejo de rol = 'admin' porque la API REST la usa.
class User {

    public const ROLES = ['cliente', 'empleado', 'admin'];

    /** Estados que se pueden filtrar en Gestión de usuarios. */
    public const ESTADOS = ['activo', 'inactivo', 'sin_confirmar'];

    /** Horas de vigencia de cada tipo de enlace enviado por correo. */
    public const VIGENCIA_HORAS = ['confirmacion' => 24, 'recuperacion' => 1];

    /** Busca un usuario por email. Devuelve la fila o null si no existe. */
    public static function buscarPorEmail(string $email): ?array {
        $stmt = conectarDB()->prepare(
            'SELECT id, nombre, apellido, email, password, admin, rol, confirmado, activo
               FROM usuarios
              WHERE email = :email
              LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /** true si el email ya está registrado. */
    public static function emailExiste(string $email): bool {
        $stmt = conectarDB()->prepare('SELECT 1 FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crea un cliente sin confirmar. La contraseña llega en texto plano y se guarda hasheada (bcrypt).
     * El rol NUNCA se toma del formulario (evita escalar privilegios): siempre nace como 'cliente'
     * y solo un administrador puede cambiarlo desde Gestión de usuarios.
     * @return int id del usuario creado
     */
    public static function crear(array $d): int {
        $db = conectarDB();
        $stmt = $db->prepare(
            'INSERT INTO usuarios (nombre, apellido, email, password, telefono, admin, rol, confirmado)
             VALUES (:nombre, :apellido, :email, :password, :telefono, 0, \'cliente\', 0)'
        );
        $stmt->execute([
            ':nombre'   => $d['nombre'],
            ':apellido' => $d['apellido'],
            ':email'    => $d['email'],
            ':password' => password_hash($d['password'], PASSWORD_DEFAULT),
            ':telefono' => $d['telefono'],
        ]);
        return (int) $db->lastInsertId();
    }

    /**
     * Usuarios para el panel admin. Filtros opcionales:
     *   q      -> nombre, apellido, email o teléfono
     *   rol    -> cliente | empleado | admin
     *   estado -> activo | inactivo | sin_confirmar
     */
    public static function todos(array $filtros = []): array {
        $where  = [];
        $params = [];

        if (($filtros['q'] ?? '') !== '') {
            $patron  = '%' . addcslashes($filtros['q'], '%_\\') . '%';
            $where[] = "(CONCAT_WS(' ', nombre, apellido) LIKE :qNombre OR email LIKE :qEmail OR telefono LIKE :qTelefono)";
            $params += [':qNombre' => $patron, ':qEmail' => $patron, ':qTelefono' => $patron];
        }
        if (($filtros['rol'] ?? '') !== '') {
            $where[] = 'rol = :rol';
            $params[':rol'] = $filtros['rol'];
        }
        $condicionEstado = [
            'activo'        => 'activo = 1 AND confirmado = 1',
            'inactivo'      => 'activo = 0',
            'sin_confirmar' => 'activo = 1 AND confirmado = 0',
        ][$filtros['estado'] ?? ''] ?? null;
        if ($condicionEstado !== null) { $where[] = $condicionEstado; }

        $sql = 'SELECT id, nombre, apellido, email, telefono, admin, rol, confirmado, activo, created_at FROM usuarios'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY id DESC';
        $stmt = conectarDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Activa o desactiva un usuario (toggle). Los administradores están protegidos.
     */
    public static function toggleEstado(int $id): bool {
        $db = conectarDB();
        $query = "UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = :id AND rol <> 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Genera un enlace de un solo uso (reemplaza el anterior). En la BD solo queda el hash SHA-256.
     * La expiración la calcula MariaDB con el mismo reloj con el que luego se valida.
     * @return string token en claro, para enviarlo por correo
     */
    public static function generarToken(int $id, string $tipo): string {
        $horas = self::VIGENCIA_HORAS[$tipo];
        $token = bin2hex(random_bytes(32));
        conectarDB()->prepare(
            "UPDATE usuarios SET token = :hash, token_tipo = :tipo,
                    token_expira = DATE_ADD(NOW(), INTERVAL $horas HOUR)
              WHERE id = :id"
        )->execute([':hash' => hash('sha256', $token), ':tipo' => $tipo, ':id' => $id]);
        return $token;
    }

    /** Descarta el enlace pendiente (por ejemplo, si el correo no se pudo enviar). */
    public static function borrarToken(int $id): void {
        conectarDB()->prepare('UPDATE usuarios SET token = NULL, token_tipo = NULL, token_expira = NULL WHERE id = :id')
                    ->execute([':id' => $id]);
    }

    /** true si ya se generó un enlace de ese tipo hace menos de $minutos (evita enviar correos en ráfaga). */
    public static function tokenReciente(int $id, string $tipo, int $minutos = 2): bool {
        $horas = self::VIGENCIA_HORAS[$tipo];
        $stmt = conectarDB()->prepare(
            "SELECT 1 FROM usuarios WHERE id = :id AND token_tipo = :tipo
                AND token_expira > DATE_ADD(DATE_SUB(NOW(), INTERVAL $minutos MINUTE), INTERVAL $horas HOUR)"
        );
        $stmt->execute([':id' => $id, ':tipo' => $tipo]);
        return (bool) $stmt->fetchColumn();
    }

    /** Id del usuario activo dueño de un enlace vigente, o null. */
    public static function idPorToken(string $token, string $tipo): ?int {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return null; }
        $stmt = conectarDB()->prepare(
            'SELECT id FROM usuarios
              WHERE token = :hash AND token_tipo = :tipo AND token_expira > NOW() AND activo = 1'
        );
        $stmt->execute([':hash' => hash('sha256', $token), ':tipo' => $tipo]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Confirma la cuenta dueña del enlace y lo consume. */
    public static function confirmarPorToken(string $token): bool {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return false; }
        $stmt = conectarDB()->prepare(
            "UPDATE usuarios SET confirmado = 1, token = NULL, token_tipo = NULL, token_expira = NULL
              WHERE token = :hash AND token_tipo = 'confirmacion' AND token_expira > NOW() AND activo = 1"
        );
        $stmt->execute([':hash' => hash('sha256', $token)]);
        return $stmt->rowCount() === 1;
    }

    /** Confirmación manual desde el panel de administración. */
    public static function confirmarManual(int $id): bool {
        $stmt = conectarDB()->prepare(
            'UPDATE usuarios SET confirmado = 1, token = NULL, token_tipo = NULL, token_expira = NULL
              WHERE id = :id AND confirmado = 0'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() === 1;
    }

    /** Cambia la contraseña con un enlace de recuperación, lo consume y revoca los tokens de la API. */
    public static function restablecerPassword(string $token, string $password): bool {
        $id = self::idPorToken($token, 'recuperacion');
        if ($id === null) { return false; }

        $db = conectarDB();
        $db->beginTransaction();
        try {
            // La condición sobre el token se repite en el UPDATE: dos envíos simultáneos no lo usan dos veces
            $stmt = $db->prepare(
                "UPDATE usuarios SET password = :password, token = NULL, token_tipo = NULL, token_expira = NULL
                  WHERE id = :id AND token = :hash AND token_tipo = 'recuperacion' AND token_expira > NOW()"
            );
            $stmt->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id,
                            ':hash' => hash('sha256', $token)]);
            if ($stmt->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }
            $db->prepare('DELETE FROM api_tokens WHERE usuarioId = :id')->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable $ex) {
            $db->rollBack();
            throw $ex;
        }
    }

    /**
     * Asigna un rol y mantiene sincronizada la columna admin. Revoca los tokens de API del usuario
     * para que el cambio de permisos aplique de inmediato también en la API.
     */
    public static function cambiarRol(int $id, string $rol): bool {
        if (!in_array($rol, self::ROLES, true)) { return false; }
        $db = conectarDB();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE usuarios SET rol = :rol, admin = :admin WHERE id = :id');
            $stmt->execute([':rol' => $rol, ':admin' => $rol === 'admin' ? 1 : 0, ':id' => $id]);
            $db->prepare('DELETE FROM api_tokens WHERE usuarioId = :id')->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable $ex) {
            $db->rollBack();
            error_log('[User] No se pudo cambiar el rol: ' . $ex->getMessage());
            return false;
        }
    }
}
