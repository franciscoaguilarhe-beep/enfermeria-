-- Migración 011: Agregar ocupación y estado civil a la tabla pacientes
ALTER TABLE pacientes
    ADD COLUMN ocupacion VARCHAR(150) NULL AFTER escolaridad,
    ADD COLUMN estado_civil VARCHAR(50) NULL AFTER ocupacion;
