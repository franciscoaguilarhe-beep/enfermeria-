USE enfermeria;

-- Contacto secundario solicitado por la vista del módulo Pacientes.
ALTER TABLE pacientes
  ADD COLUMN IF NOT EXISTS otro_contacto VARCHAR(160) NULL AFTER telefono;
