-- Responsable: A
-- Cambios vs. el enunciado: email VARCHAR(60) y precio DECIMAL(10,2) (pesos colombianos)
CREATE DATABASE IF NOT EXISTS appsalon_mvc_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE appsalon_mvc_php;
-- Sin esto, un cliente mysql en latin1 (p. ej. el de Docker) guarda las tildes con doble codificación
SET NAMES utf8mb4;

DROP TABLE IF EXISTS horario_salon;
DROP TABLE IF EXISTS festivos;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS citasServicios;
DROP TABLE IF EXISTS citas;
DROP TABLE IF EXISTS servicios;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  apellido VARCHAR(60) NOT NULL,
  email VARCHAR(60) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  telefono VARCHAR(10),
  admin TINYINT(1) DEFAULT 0,             -- espejo de rol = 'admin' (lo usa la API); se mantiene sincronizado
  rol ENUM('cliente','empleado','admin') NOT NULL DEFAULT 'cliente',
  confirmado TINYINT(1) DEFAULT 0,         -- 1 = verificó su correo
  activo TINYINT(1) NOT NULL DEFAULT 1,     -- 0 = desactivado por un administrador
  token VARCHAR(64),                        -- hash SHA-256 del enlace de confirmación o recuperación
  token_tipo ENUM('confirmacion','recuperacion'),
  token_expira DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_usuarios_token (token)
);

CREATE TABLE servicios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  descripcion TEXT,
  duracion INT DEFAULT 60,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE citas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  usuarioId INT,
  total DECIMAL(10,2),
  estado ENUM('pendiente','confirmada','completada','cancelada') DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuarioId) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_citas_fecha_estado (fecha, estado)
);

CREATE TABLE citasServicios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citaId INT,
  servicioId INT,
  FOREIGN KEY (citaId) REFERENCES citas(id) ON DELETE CASCADE,
  FOREIGN KEY (servicioId) REFERENCES servicios(id) ON DELETE CASCADE
);

-- Entrega final: tokens de la API REST (solo se guarda el hash SHA-256 del token)
CREATE TABLE api_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  usuarioId INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_api_tokens_hash (token_hash),
  KEY idx_api_tokens_usuario_expira (usuarioId, expira),
  CONSTRAINT fk_api_tokens_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Entrega final: festivos de Colombia (los llena models/Festivo.php desde la API Nager.Date)
CREATE TABLE festivos (
  fecha        DATE         PRIMARY KEY,
  nombre       VARCHAR(100) NOT NULL,
  fuente       VARCHAR(40)  NOT NULL DEFAULT 'nager.date',
  sincronizado TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horario de atención por día de la semana (0 = domingo ... 6 = sábado, igual que date('w') en PHP).
-- Lo edita el administrador en admin/horario. "cierre" = hora máxima a la que debe TERMINAR la última cita.
CREATE TABLE horario_salon (
  dia_semana TINYINT PRIMARY KEY,
  abierto    TINYINT(1) NOT NULL DEFAULT 1,
  apertura   TIME NOT NULL DEFAULT '09:00:00',
  cierre     TIME NOT NULL DEFAULT '19:00:00',
  CONSTRAINT chk_horario_dia   CHECK (dia_semana BETWEEN 0 AND 6),
  CONSTRAINT chk_horario_rango CHECK (apertura < cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO horario_salon (dia_semana, abierto, apertura, cierre) VALUES
(0, 0, '09:00', '19:00'), (1, 1, '09:00', '19:00'), (2, 1, '09:00', '19:00'), (3, 1, '09:00', '19:00'),
(4, 1, '09:00', '19:00'), (5, 1, '09:00', '19:00'), (6, 1, '09:00', '19:00');

-- Datos de prueba (admin123 / cliente123 / empleado123)
INSERT INTO usuarios (nombre, apellido, email, password, telefono, admin, rol, confirmado) VALUES
('Admin', 'AppSalon', 'admin@appsalon.com', '$2y$10$v2EF1nug76nQBpCLJChdZee.F5NSCHFTdgsjixEUfAPnVdN3BNxvW', '3000000000', 1, 'admin', 1),
('Cliente', 'Prueba', 'cliente@appsalon.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3111111111', 0, 'cliente', 1),
('Empleado', 'Salón', 'empleado@appsalon.com', '$2y$10$J2a.OVZK7nWUsg0jLZOofeCfosR3ZOEzflAxsMEdSpeTL8ivYpXPu', '3222222222', 0, 'empleado', 1);

INSERT INTO servicios (nombre, precio, descripcion, duracion) VALUES
('Corte de cabello', 25000, 'Corte para dama o caballero', 45),
('Tinte completo', 90000, 'Coloración de todo el cabello', 120),
('Manicure', 20000, 'Manicure con esmalte tradicional', 40),
('Pedicure', 25000, 'Pedicure con esmalte tradicional', 50),
('Peinado', 40000, 'Peinado para evento', 60),
('Alisado', 150000, 'Tratamiento de alisado', 150);
