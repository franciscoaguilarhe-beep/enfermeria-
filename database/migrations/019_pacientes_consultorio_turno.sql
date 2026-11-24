-- ============================================================================
-- Migración 019: Consultorio y Turno fijos en la tabla pacientes
-- ============================================================================

ALTER TABLE pacientes
  ADD COLUMN IF NOT EXISTS consultorio_id INT UNSIGNED NULL DEFAULT NULL AFTER unidad_adscripcion_id,
  ADD COLUMN IF NOT EXISTS turno_id INT UNSIGNED NULL DEFAULT NULL AFTER consultorio_id;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pacientes' AND CONSTRAINT_NAME = 'fk_pacientes_consultorio'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE pacientes ADD CONSTRAINT fk_pacientes_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk; EXECUTE stmt_fk; DEALLOCATE PREPARE stmt_fk;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pacientes' AND CONSTRAINT_NAME = 'fk_pacientes_turno'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE pacientes ADD CONSTRAINT fk_pacientes_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk; EXECUTE stmt_fk; DEALLOCATE PREPARE stmt_fk;

-- Backfill: asignar consultorio_id y turno_id de la última atención activa (si existe)
UPDATE pacientes p
JOIN (
  SELECT a1.paciente_id, a1.consultorio_id, a1.turno_id
  FROM atenciones a1
  INNER JOIN (
    SELECT paciente_id, MAX(CONCAT(fecha, ' ', hora, '_', LPAD(atencion_id, 10, '0'))) AS max_key
    FROM atenciones
    WHERE estado = 'ACTIVA'
    GROUP BY paciente_id
  ) ult ON ult.paciente_id = a1.paciente_id
       AND CONCAT(a1.fecha, ' ', a1.hora, '_', LPAD(a1.atencion_id, 10, '0')) = ult.max_key
) last_att ON last_att.paciente_id = p.paciente_id
SET
  p.consultorio_id = COALESCE(p.consultorio_id, last_att.consultorio_id),
  p.turno_id = COALESCE(p.turno_id, last_att.turno_id)
WHERE p.consultorio_id IS NULL OR p.turno_id IS NULL;
