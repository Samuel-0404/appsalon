-- Responsable: A
-- Cambios vs. el enunciado: email VARCHAR(60) y precio DECIMAL(10,2) (pesos colombianos)
CREATE DATABASE IF NOT EXISTS appsalon_mvc_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE appsalon_mvc_php;

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
  admin TINYINT(1) DEFAULT 0,
  confirmado TINYINT(1) DEFAULT 0,
  token VARCHAR(15),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
  FOREIGN KEY (usuarioId) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE citasServicios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  citaId INT,
  servicioId INT,
  FOREIGN KEY (citaId) REFERENCES citas(id) ON DELETE CASCADE,
  FOREIGN KEY (servicioId) REFERENCES servicios(id) ON DELETE CASCADE
);

-- Datos de prueba (admin123 / cliente123)
INSERT INTO usuarios (nombre, apellido, email, password, telefono, admin, confirmado) VALUES
('Admin', 'AppSalon', 'admin@appsalon.com', '$2y$10$v2EF1nug76nQBpCLJChdZee.F5NSCHFTdgsjixEUfAPnVdN3BNxvW', '3000000000', 1, 1),
('Cliente', 'Prueba', 'cliente@appsalon.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3111111111', 0, 1);

INSERT INTO servicios (nombre, precio, descripcion, duracion) VALUES
('Corte de cabello', 25000, 'Corte para dama o caballero', 45),
('Tinte completo', 90000, 'Coloración de todo el cabello', 120),
('Manicure', 20000, 'Manicure con esmalte tradicional', 40),
('Pedicure', 25000, 'Pedicure con esmalte tradicional', 50),
('Peinado', 40000, 'Peinado para evento', 60),
('Alisado', 150000, 'Tratamiento de alisado', 150);
