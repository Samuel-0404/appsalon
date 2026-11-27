USE appsalon_mvc_php;

CREATE TABLE IF NOT EXISTS festivos (
    fecha     DATE  PRIMARY KEY,
    nombre    VARCHAR(100) NOT NULL,
    fuente    VARCHAR(40) NOT NULL DEFAULT 'nager.date',
    sincronizado    TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;