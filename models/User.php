<?php
// Responsable: A
// Usuarios del sistema. Roles: cliente | empleado | admin (columna rol).
// La columna admin se mantiene como espejo de rol = 'admin' porque la API REST la usa.
class User {

    public const ROLES = ['cliente', 'empleado', 'admin'];

    /** Busca un usuario por email. Devuelve la fila o null si no existe. */
    public static function buscarPorEmail(string $email): ?array {
        $stmt = conectarDB()->prepare(
            'SELECT id, nombre, apellido, email, password, admin, rol, confirmado
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
     * Crea un cliente. La contraseña llega en texto plano y se guarda hasheada (bcrypt).
     * El rol NUNCA se toma del formulario (evita escalar privilegios): siempre nace como 'cliente'
     * y solo un administrador puede cambiarlo desde Gestión de usuarios.
     * @return int id del usuario creado
     */
    public static function crear(array $d): int {
        $db = conectarDB();
        $stmt = $db->prepare(
            'INSERT INTO usuarios (nombre, apellido, email, password, telefono, admin, rol, confirmado)
             VALUES (:nombre, :apellido, :email, :password, :telefono, 0, \'cliente\', 1)'
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
     * Devuelve todos los usuarios registrados (para el panel admin)
     */
    public static function todos(): array {
        $db = conectarDB();
        $query = "SELECT id, nombre, apellido, email, telefono, admin, rol, confirmado, created_at 
                  FROM usuarios 
                  ORDER BY id DESC";
        return $db->query($query)->fetchAll();
    }

    /**
     * Activa o desactiva un usuario (toggle). Los administradores están protegidos.
     */
    public static function toggleEstado(int $id): bool {
        $db = conectarDB();
        $query = "UPDATE usuarios SET confirmado = IF(confirmado = 1, 0, 1) WHERE id = :id AND rol <> 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
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
