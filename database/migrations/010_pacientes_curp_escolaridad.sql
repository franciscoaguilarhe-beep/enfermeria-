-- Migración 010: Agregar CURP y Escolaridad a la tabla pacientes
ALTER TABLE pacientes
  ADD COLUMN curp VARCHAR(18) NULL AFTER agregado,
  ADD COLUMN escolaridad VARCHAR(100) NULL AFTER curp,
  ADD KEY idx_pacientes_curp (curp);
