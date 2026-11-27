-- Responsable: A
-- Migración incremental (entrega final): roles de usuario y horario configurable del salón.
-- Aplicar sobre una BD existente (XAMPP):
--   /opt/lampp/bin/mysql -u root appsalon_mvc_php < database/migraciones/004_roles_horario.sql
-- Es idempotente (MariaDB): se puede ejecutar más de una vez sin error.
USE appsalon_mvc_php;
SET NAMES utf8mb4;

-- 1. Rol del usuario. La columna admin se conserva como espejo (admin = 1 <=> rol = 'admin')
--    porque la API REST y los tokens la usan.
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS rol ENUM('cliente','empleado','admin') NOT NULL DEFAULT 'cliente' AFTER admin;
UPDATE usuarios SET rol = 'admin' WHERE admin = 1 AND rol <> 'admin';

-- Usuario empleado de prueba (contraseña: empleado123)
INSERT IGNORE INTO usuarios (nombre, apellido, email, password, telefono, admin, rol, confirmado) VALUES
('Empleado', 'Salón', 'empleado@appsalon.com', '$2y$10$J2a.OVZK7nWUsg0jLZOofeCfosR3ZOEzflAxsMEdSpeTL8ivYpXPu', '3222222222', 0, 'empleado', 1);

-- 2. Horario de atención por día (0 = domingo ... 6 = sábado). "cierre" = hora a la que debe terminar la última cita.
CREATE TABLE IF NOT EXISTS horario_salon (
  dia_semana TINYINT PRIMARY KEY,
  abierto    TINYINT(1) NOT NULL DEFAULT 1,
  apertura   TIME NOT NULL DEFAULT '09:00:00',
  cierre     TIME NOT NULL DEFAULT '19:00:00',
  CONSTRAINT chk_horario_dia   CHECK (dia_semana BETWEEN 0 AND 6),
  CONSTRAINT chk_horario_rango CHECK (apertura < cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO horario_salon (dia_semana, abierto, apertura, cierre) VALUES
(0, 0, '09:00', '19:00'), (1, 1, '09:00', '19:00'), (2, 1, '09:00', '19:00'), (3, 1, '09:00', '19:00'),
(4, 1, '09:00', '19:00'), (5, 1, '09:00', '19:00'), (6, 1, '09:00', '19:00');
