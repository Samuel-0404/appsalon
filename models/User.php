<?php
// Responsable: A
class User {
    // TODO: buscarPorEmail($email), crear($datos), emailExiste($email)
    // Usar conectarDB() y prepared statements. Guardar password con password_hash().
/** Busca un usuario por email. Devuelve la fila o null si no existe. */
    public static function buscarPorEmail(string $email): ?array {
        $stmt = conectarDB()->prepare(
            'SELECT id, nombre, apellido, email, password, admin, confirmado
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
     * `admin` va fijo en 0: el rol NUNCA se toma del formulario (evita escalar privilegios).
     * `confirmado` = 1 en la entrega intermedia; en la final será 0 + token + email.
     * @return int id del usuario creado
     */
    public static function crear(array $d): int {
        $db = conectarDB();
        $stmt = $db->prepare(
            'INSERT INTO usuarios (nombre, apellido, email, password, telefono, admin, confirmado)
             VALUES (:nombre, :apellido, :email, :password, :telefono, 0, 1)'
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
        $query = "SELECT id, nombre, apellido, email, telefono, admin, confirmado, created_at 
                  FROM usuarios 
                  ORDER BY id DESC";
        return $db->query($query)->fetchAll();
    }

    /**
     * Activa o desactiva un usuario (toggle)
     */
    public static function toggleEstado(int $id): bool {
        $db = conectarDB();
        $query = "UPDATE usuarios SET confirmado = IF(confirmado = 1, 0, 1) WHERE id = :id AND admin = 0";
        $stmt = $db->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}
