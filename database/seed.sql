USE enfermeria;

-- Roles base
INSERT INTO roles (rol_id, clave, nombre, activo) VALUES 
(1, 'ADMINISTRADOR', 'Administrador', 1),
(2, 'SUPERVISOR', 'Supervisor', 1),
(3, 'ENFERMERIA', 'Enfermera', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

-- Turnos base
INSERT INTO turnos (clave, nombre, hora_inicio, hora_fin, activo) VALUES
('MAT', 'Matutino', '07:00:00', '14:00:00', 1),
('VESP', 'Vespertino', '14:00:00', '21:00:00', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

-- Temas de Educacin (opcional, para catlogo)
INSERT INTO temas_educacion_salud (nombre, activo) VALUES 
('Lactancia Materna', 1),
('Nutricin', 1),
('Prevencin de Accidentes', 1)
ON DUPLICATE KEY UPDATE activo=1;

