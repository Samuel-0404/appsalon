-- Migración incremental: confirmación de cuenta y recuperación de contraseña.
-- Aplicar sobre una BD existente (XAMPP):
--   /opt/lampp/bin/mysql -u root appsalon_mvc_php < database/migraciones/005_cuentas.sql
-- Es idempotente (MariaDB): se puede ejecutar más de una vez sin error.
USE appsalon_mvc_php;
SET NAMES utf8mb4;

-- Antes de esta migración, confirmado = 0 significaba "desactivado". Hay que saber si "activo" ya existía
-- para trasladar ese dato UNA sola vez (después, confirmado = 0 significa "sin confirmar").
SET @ya_migrada := (SELECT COUNT(*) FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'activo');

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1 AFTER confirmado,
  MODIFY COLUMN token VARCHAR(64),
  ADD COLUMN IF NOT EXISTS token_tipo ENUM('confirmacion','recuperacion') AFTER token,
  ADD COLUMN IF NOT EXISTS token_expira DATETIME AFTER token_tipo,
  ADD INDEX IF NOT EXISTS idx_usuarios_token (token);

UPDATE usuarios SET activo = 0, confirmado = 1 WHERE confirmado = 0 AND @ya_migrada = 0;
