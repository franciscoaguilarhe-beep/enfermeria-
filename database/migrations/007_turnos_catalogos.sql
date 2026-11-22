USE enfermeria;

-- Migración 007: Turnos adicionales para el catálogo de consultorios
-- y ampliación de longitud de siglas de unidades.

ALTER TABLE unidades
  MODIFY COLUMN clave VARCHAR(50) NOT NULL;

INSERT INTO turnos (clave, nombre, hora_inicio, hora_fin, activo) VALUES
  ('NOC', 'Nocturno', '21:00:00', '07:00:00', 1),
  ('JA', 'Fin de semana', '07:00:00', '19:00:00', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1;
