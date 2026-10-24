<?php
// Responsable: B
class Service {
    // TODO: todos(), buscar($id), crear($datos), actualizar($id, $datos), eliminar($id)
    // Usar conectarDB() y prepared statements.
    /**
     * Obtener todos los servicios activos
     * @return array
     */
    public static function todos(): array {
        $db = conectarDB();
        $query = "SELECT * FROM servicios ORDER BY id ASC";
        $stmt = $db->query($query);
        return $stmt->fetchAll();
    }
    /**
     * Buscar un servicio por su ID
     * @param int|string $id
     * @return array|null
     */
    public static function buscar($id): ?array {
        $db = conectarDB();
        $query = "SELECT * FROM servicios WHERE id = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id]);
        $servicio = $stmt->fetch();
        return $servicio ?: null;
    }
    /**
     * Crear un nuevo servicio
     * @param array $datos ['nombre', 'precio', 'descripcion', 'duracion']
     * @return bool
     */
    public static function crear(array $datos): bool {
        $db = conectarDB();
        $query = "INSERT INTO servicios (nombre, precio, descripcion, duracion) 
                  VALUES (:nombre, :precio, :descripcion, :duracion)";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':nombre'      => trim($datos['nombre']),
            ':precio'      => (float)$datos['precio'],
            ':descripcion' => trim($datos['descripcion'] ?? ''),
            ':duracion'    => !empty($datos['duracion']) ? (int)$datos['duracion'] : 60
        ]);
    }
    /**
     * Actualizar un servicio existente
     * @param int|string $id
     * @param array $datos ['nombre', 'precio', 'descripcion', 'duracion']
     * @return bool
     */
    public static function actualizar($id, array $datos): bool {
        $db = conectarDB();
        $query = "UPDATE servicios 
                  SET nombre = :nombre, precio = :precio, descripcion = :descripcion, duracion = :duracion 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':nombre'      => trim($datos['nombre']),
            ':precio'      => (float)$datos['precio'],
            ':descripcion' => trim($datos['descripcion'] ?? ''),
            ':duracion'    => !empty($datos['duracion']) ? (int)$datos['duracion'] : 60,
            ':id'          => $id
        ]);
    }
    /**
     * Eliminar un servicio por su ID
     * @param int|string $id
     * @return bool
     */
    public static function eliminar($id): bool {
        $db = conectarDB();
        $query = "DELETE FROM servicios WHERE id = :id";
        $stmt = $db->prepare($query);
        return $stmt->execute([':id' => $id]);
    }
}
