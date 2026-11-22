-- Responsable: A
-- Migración incremental para la entrega final: API REST (tokens) y reportes (índices).
-- Aplicar sobre una BD que ya tiene el esquema de database/appsalon.sql:
--   mysql -u root appsalon_mvc_php < database/migraciones/002_api_reportes.sql
-- Es idempotente: se puede ejecutar más de una vez sin error.
USE appsalon_mvc_php;

-- Tokens de acceso para la API.
-- Se guarda SOLO el hash SHA-256 del token (CHAR(64)); el token en claro se entrega
-- una única vez al cliente. Si la tabla se filtra, los tokens no son reutilizables.
CREATE TABLE IF NOT EXISTS api_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  usuarioId INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_api_tokens_hash (token_hash),
  KEY idx_api_tokens_usuario_expira (usuarioId, expira),
  CONSTRAINT fk_api_tokens_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice compuesto para los reportes por período y la agenda (WHERE fecha BETWEEN ... AND estado ...).
SET @existe := (SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = 'citas' AND index_name = 'idx_citas_fecha_estado');
SET @sql := IF(@existe = 0, 'CREATE INDEX idx_citas_fecha_estado ON citas (fecha, estado)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
