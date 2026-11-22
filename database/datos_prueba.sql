-- Responsable: A
-- Datos de prueba para la entrega final: 10 clientes y 32 citas (oct-dic 2026).
-- Requisito: haber importado database/appsalon.sql (servicios y usuarios base).
-- Uso:  mysql -u root appsalon_mvc_php < database/seeds/datos_prueba.sql
-- Es re-ejecutable: borra y vuelve a crear solo los clientes @example.com y sus citas.
--
-- Contraseña de todos los clientes: cliente123
-- Reglas que respeta (las mismas de ValidadorCita): sin domingos ni festivos (2-nov, 16-nov, 8-dic),
-- franjas en punto de 09:00 a 18:00, la cita termina a las 19:00 como máximo y no hay solapamientos.
-- Citas pasadas: completada/cancelada. Futuras (desde el 23-nov): pendiente/confirmada/cancelada.
-- felipe.arango@example.com está desactivado (confirmado = 0) para probar el panel de usuarios.
USE appsalon_mvc_php;
SET NAMES utf8mb4;

START TRANSACTION;

-- 1. Limpieza (citas antes que usuarios: la FK de citas es ON DELETE SET NULL, no CASCADE)
DELETE c FROM citas c INNER JOIN usuarios u ON u.id = c.usuarioId WHERE u.email LIKE '%@example.com';
DELETE FROM usuarios WHERE email LIKE '%@example.com';

-- 2. Clientes
INSERT INTO usuarios (nombre, apellido, email, password, telefono, admin, confirmado) VALUES
('Valentina', 'Restrepo', 'valentina.restrepo@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3104567812', 0, 1),
('Camila', 'Ospina', 'camila.ospina@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3157894561', 0, 1),
('Daniela', 'Cárdenas', 'daniela.cardenas@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3012345678', 0, 1),
('Mariana', 'Valencia', 'mariana.valencia@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3209876543', 0, 1),
('Laura', 'Henao', 'laura.henao@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3176543210', 0, 1),
('Sofía', 'Montoya', 'sofia.montoya@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3123456789', 0, 1),
('Andrés', 'Zapata', 'andres.zapata@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3145678901', 0, 1),
('Santiago', 'Muñoz', 'santiago.munoz@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3018765432', 0, 1),
('Natalia', 'Quintero', 'natalia.quintero@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3187654321', 0, 1),
('Felipe', 'Arango', 'felipe.arango@example.com', '$2y$10$BLINNud3.DNwzRIO9yRavOJn4YNOpKQe/3ga7vQsY6csT5tE/2lTG', '3051234567', 0, 0);

-- 3. Citas (total = 0 provisional; se calcula en el paso 5 desde los precios)
INSERT INTO citas (fecha, hora, usuarioId, total, estado, created_at) VALUES
('2026-10-05', '09:00:00', (SELECT id FROM usuarios WHERE email = 'valentina.restrepo@example.com'), 0, 'completada', '2026-09-30 10:00:00'),
('2026-10-05', '11:00:00', (SELECT id FROM usuarios WHERE email = 'camila.ospina@example.com'), 0, 'completada', '2026-09-30 10:00:00'),
('2026-10-06', '10:00:00', (SELECT id FROM usuarios WHERE email = 'daniela.cardenas@example.com'), 0, 'completada', '2026-10-01 10:00:00'),
('2026-10-09', '14:00:00', (SELECT id FROM usuarios WHERE email = 'mariana.valencia@example.com'), 0, 'completada', '2026-10-04 10:00:00'),
('2026-10-14', '09:00:00', (SELECT id FROM usuarios WHERE email = 'laura.henao@example.com'), 0, 'completada', '2026-10-09 10:00:00'),
('2026-10-17', '15:00:00', (SELECT id FROM usuarios WHERE email = 'sofia.montoya@example.com'), 0, 'completada', '2026-10-12 10:00:00'),
('2026-10-20', '10:00:00', (SELECT id FROM usuarios WHERE email = 'andres.zapata@example.com'), 0, 'cancelada', '2026-10-15 10:00:00'),
('2026-10-24', '09:00:00', (SELECT id FROM usuarios WHERE email = 'santiago.munoz@example.com'), 0, 'completada', '2026-10-19 10:00:00'),
('2026-10-28', '16:00:00', (SELECT id FROM usuarios WHERE email = 'natalia.quintero@example.com'), 0, 'completada', '2026-10-23 10:00:00'),
('2026-10-31', '10:00:00', (SELECT id FROM usuarios WHERE email = 'valentina.restrepo@example.com'), 0, 'completada', '2026-10-26 10:00:00'),
('2026-11-04', '11:00:00', (SELECT id FROM usuarios WHERE email = 'camila.ospina@example.com'), 0, 'completada', '2026-10-30 10:00:00'),
('2026-11-06', '09:00:00', (SELECT id FROM usuarios WHERE email = 'daniela.cardenas@example.com'), 0, 'completada', '2026-11-01 10:00:00'),
('2026-11-10', '13:00:00', (SELECT id FROM usuarios WHERE email = 'mariana.valencia@example.com'), 0, 'cancelada', '2026-11-05 10:00:00'),
('2026-11-12', '10:00:00', (SELECT id FROM usuarios WHERE email = 'laura.henao@example.com'), 0, 'completada', '2026-11-07 10:00:00'),
('2026-11-13', '17:00:00', (SELECT id FROM usuarios WHERE email = 'sofia.montoya@example.com'), 0, 'completada', '2026-11-08 10:00:00'),
('2026-11-14', '09:00:00', (SELECT id FROM usuarios WHERE email = 'natalia.quintero@example.com'), 0, 'completada', '2026-11-09 10:00:00'),
('2026-11-18', '15:00:00', (SELECT id FROM usuarios WHERE email = 'andres.zapata@example.com'), 0, 'completada', '2026-11-13 10:00:00'),
('2026-11-19', '10:00:00', (SELECT id FROM usuarios WHERE email = 'valentina.restrepo@example.com'), 0, 'completada', '2026-11-14 10:00:00'),
('2026-11-20', '14:00:00', (SELECT id FROM usuarios WHERE email = 'santiago.munoz@example.com'), 0, 'cancelada', '2026-11-15 10:00:00'),
('2026-11-21', '09:00:00', (SELECT id FROM usuarios WHERE email = 'felipe.arango@example.com'), 0, 'completada', '2026-11-16 10:00:00'),
('2026-11-21', '11:00:00', (SELECT id FROM usuarios WHERE email = 'camila.ospina@example.com'), 0, 'completada', '2026-11-16 10:00:00'),
('2026-11-23', '09:00:00', (SELECT id FROM usuarios WHERE email = 'daniela.cardenas@example.com'), 0, 'pendiente', '2026-11-18 10:00:00'),
('2026-11-23', '10:00:00', (SELECT id FROM usuarios WHERE email = 'laura.henao@example.com'), 0, 'pendiente', '2026-11-18 10:00:00'),
('2026-11-24', '15:00:00', (SELECT id FROM usuarios WHERE email = 'sofia.montoya@example.com'), 0, 'pendiente', '2026-11-19 10:00:00'),
('2026-11-26', '11:00:00', (SELECT id FROM usuarios WHERE email = 'mariana.valencia@example.com'), 0, 'confirmada', '2026-11-21 10:00:00'),
('2026-11-28', '09:00:00', (SELECT id FROM usuarios WHERE email = 'valentina.restrepo@example.com'), 0, 'confirmada', '2026-11-23 10:00:00'),
('2026-12-01', '14:00:00', (SELECT id FROM usuarios WHERE email = 'natalia.quintero@example.com'), 0, 'pendiente', '2026-11-26 10:00:00'),
('2026-12-03', '16:00:00', (SELECT id FROM usuarios WHERE email = 'andres.zapata@example.com'), 0, 'confirmada', '2026-11-28 10:00:00'),
('2026-12-05', '10:00:00', (SELECT id FROM usuarios WHERE email = 'camila.ospina@example.com'), 0, 'pendiente', '2026-11-30 10:00:00'),
('2026-12-11', '09:00:00', (SELECT id FROM usuarios WHERE email = 'santiago.munoz@example.com'), 0, 'cancelada', '2026-12-06 10:00:00'),
('2026-12-12', '11:00:00', (SELECT id FROM usuarios WHERE email = 'sofia.montoya@example.com'), 0, 'confirmada', '2026-12-07 10:00:00'),
('2026-12-18', '13:00:00', (SELECT id FROM usuarios WHERE email = 'laura.henao@example.com'), 0, 'pendiente', '2026-12-13 10:00:00');

-- 4. Servicios de cada cita (se ubica la cita por fecha + hora + email; el servicio por nombre)
INSERT INTO citasServicios (citaId, servicioId)
SELECT c.id, s.id
  FROM (
        SELECT '2026-10-05' AS fecha, '09:00:00' AS hora, 'valentina.restrepo@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-10-05' AS fecha, '09:00:00' AS hora, 'valentina.restrepo@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-10-05' AS fecha, '11:00:00' AS hora, 'camila.ospina@example.com' AS email, 'Tinte completo' AS servicio
        UNION ALL
        SELECT '2026-10-06' AS fecha, '10:00:00' AS hora, 'daniela.cardenas@example.com' AS email, 'Peinado' AS servicio
        UNION ALL
        SELECT '2026-10-09' AS fecha, '14:00:00' AS hora, 'mariana.valencia@example.com' AS email, 'Alisado' AS servicio
        UNION ALL
        SELECT '2026-10-14' AS fecha, '09:00:00' AS hora, 'laura.henao@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-10-14' AS fecha, '09:00:00' AS hora, 'laura.henao@example.com' AS email, 'Pedicure' AS servicio
        UNION ALL
        SELECT '2026-10-17' AS fecha, '15:00:00' AS hora, 'sofia.montoya@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-10-20' AS fecha, '10:00:00' AS hora, 'andres.zapata@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-10-24' AS fecha, '09:00:00' AS hora, 'santiago.munoz@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-10-28' AS fecha, '16:00:00' AS hora, 'natalia.quintero@example.com' AS email, 'Tinte completo' AS servicio
        UNION ALL
        SELECT '2026-10-31' AS fecha, '10:00:00' AS hora, 'valentina.restrepo@example.com' AS email, 'Peinado' AS servicio
        UNION ALL
        SELECT '2026-11-04' AS fecha, '11:00:00' AS hora, 'camila.ospina@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-11-06' AS fecha, '09:00:00' AS hora, 'daniela.cardenas@example.com' AS email, 'Tinte completo' AS servicio
        UNION ALL
        SELECT '2026-11-06' AS fecha, '09:00:00' AS hora, 'daniela.cardenas@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-11-10' AS fecha, '13:00:00' AS hora, 'mariana.valencia@example.com' AS email, 'Pedicure' AS servicio
        UNION ALL
        SELECT '2026-11-12' AS fecha, '10:00:00' AS hora, 'laura.henao@example.com' AS email, 'Alisado' AS servicio
        UNION ALL
        SELECT '2026-11-13' AS fecha, '17:00:00' AS hora, 'sofia.montoya@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-11-14' AS fecha, '09:00:00' AS hora, 'natalia.quintero@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-11-14' AS fecha, '09:00:00' AS hora, 'natalia.quintero@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-11-14' AS fecha, '09:00:00' AS hora, 'natalia.quintero@example.com' AS email, 'Pedicure' AS servicio
        UNION ALL
        SELECT '2026-11-18' AS fecha, '15:00:00' AS hora, 'andres.zapata@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-11-19' AS fecha, '10:00:00' AS hora, 'valentina.restrepo@example.com' AS email, 'Tinte completo' AS servicio
        UNION ALL
        SELECT '2026-11-20' AS fecha, '14:00:00' AS hora, 'santiago.munoz@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-11-21' AS fecha, '09:00:00' AS hora, 'felipe.arango@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-11-21' AS fecha, '11:00:00' AS hora, 'camila.ospina@example.com' AS email, 'Peinado' AS servicio
        UNION ALL
        SELECT '2026-11-21' AS fecha, '11:00:00' AS hora, 'camila.ospina@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-11-23' AS fecha, '09:00:00' AS hora, 'daniela.cardenas@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-11-23' AS fecha, '10:00:00' AS hora, 'laura.henao@example.com' AS email, 'Tinte completo' AS servicio
        UNION ALL
        SELECT '2026-11-24' AS fecha, '15:00:00' AS hora, 'sofia.montoya@example.com' AS email, 'Alisado' AS servicio
        UNION ALL
        SELECT '2026-11-26' AS fecha, '11:00:00' AS hora, 'mariana.valencia@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-11-26' AS fecha, '11:00:00' AS hora, 'mariana.valencia@example.com' AS email, 'Pedicure' AS servicio
        UNION ALL
        SELECT '2026-11-28' AS fecha, '09:00:00' AS hora, 'valentina.restrepo@example.com' AS email, 'Peinado' AS servicio
        UNION ALL
        SELECT '2026-12-01' AS fecha, '14:00:00' AS hora, 'natalia.quintero@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-12-01' AS fecha, '14:00:00' AS hora, 'natalia.quintero@example.com' AS email, 'Manicure' AS servicio
        UNION ALL
        SELECT '2026-12-03' AS fecha, '16:00:00' AS hora, 'andres.zapata@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-12-05' AS fecha, '10:00:00' AS hora, 'camila.ospina@example.com' AS email, 'Tinte completo' AS servicio
        UNION ALL
        SELECT '2026-12-05' AS fecha, '10:00:00' AS hora, 'camila.ospina@example.com' AS email, 'Peinado' AS servicio
        UNION ALL
        SELECT '2026-12-11' AS fecha, '09:00:00' AS hora, 'santiago.munoz@example.com' AS email, 'Corte de cabello' AS servicio
        UNION ALL
        SELECT '2026-12-12' AS fecha, '11:00:00' AS hora, 'sofia.montoya@example.com' AS email, 'Peinado' AS servicio
        UNION ALL
        SELECT '2026-12-18' AS fecha, '13:00:00' AS hora, 'laura.henao@example.com' AS email, 'Alisado' AS servicio
       ) d
  INNER JOIN usuarios u  ON u.email = d.email
  INNER JOIN citas c     ON c.usuarioId = u.id AND c.fecha = d.fecha AND c.hora = d.hora
  INNER JOIN servicios s ON s.nombre = d.servicio;

-- 5. Total de cada cita = suma de los precios de sus servicios
UPDATE citas c
  INNER JOIN (SELECT cs.citaId, SUM(s.precio) AS total
                FROM citasServicios cs INNER JOIN servicios s ON s.id = cs.servicioId
               GROUP BY cs.citaId) t ON t.citaId = c.id
  INNER JOIN usuarios u ON u.id = c.usuarioId
   SET c.total = t.total
 WHERE u.email LIKE '%@example.com';

COMMIT;

-- 6. Verificación rápida
SELECT estado, COUNT(*) AS citas, SUM(total) AS total
  FROM citas c INNER JOIN usuarios u ON u.id = c.usuarioId
 WHERE u.email LIKE '%@example.com'
 GROUP BY estado WITH ROLLUP;
