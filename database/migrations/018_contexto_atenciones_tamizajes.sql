-- ============================================================================
-- Migración 018: Contexto clínico (consultorio y turno) en tamizajes
-- y tabla atenciones_tamizajes para registros independientes (Colon y Recto, etc.)
-- ============================================================================

-- 1. Agregar consultorio_id y turno_id a dc_prostata
ALTER TABLE dc_prostata
  ADD COLUMN IF NOT EXISTS consultorio_id INT UNSIGNED NULL AFTER id_usuario,
  ADD COLUMN IF NOT EXISTS turno_id INT UNSIGNED NULL AFTER consultorio_id;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'dc_prostata' AND CONSTRAINT_NAME = 'fk_dc_prostata_consultorio'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE dc_prostata ADD CONSTRAINT fk_dc_prostata_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk; EXECUTE stmt_fk; DEALLOCATE PREPARE stmt_fk;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'dc_prostata' AND CONSTRAINT_NAME = 'fk_dc_prostata_turno'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE dc_prostata ADD CONSTRAINT fk_dc_prostata_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk; EXECUTE stmt_fk; DEALLOCATE PREPARE stmt_fk;

-- Backfill registros existentes de dc_prostata
UPDATE dc_prostata d
LEFT JOIN usuarios u ON u.usuario_id = d.id_usuario
SET
  d.consultorio_id = COALESCE(d.consultorio_id, u.consultorio_id),
  d.turno_id = COALESCE(d.turno_id, 1)
WHERE d.consultorio_id IS NULL OR d.turno_id IS NULL;

-- 2. Agregar consultorio_id y turno_id a dc_salud_mental
ALTER TABLE dc_salud_mental
  ADD COLUMN IF NOT EXISTS consultorio_id INT UNSIGNED NULL AFTER id_usuario,
  ADD COLUMN IF NOT EXISTS turno_id INT UNSIGNED NULL AFTER consultorio_id;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'dc_salud_mental' AND CONSTRAINT_NAME = 'fk_dc_sm_consultorio'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE dc_salud_mental ADD CONSTRAINT fk_dc_sm_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk; EXECUTE stmt_fk; DEALLOCATE PREPARE stmt_fk;

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'dc_salud_mental' AND CONSTRAINT_NAME = 'fk_dc_sm_turno'
);
SET @sql_fk := IF(@fk_exists = 0,
  'ALTER TABLE dc_salud_mental ADD CONSTRAINT fk_dc_sm_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk; EXECUTE stmt_fk; DEALLOCATE PREPARE stmt_fk;

-- Backfill registros existentes de dc_salud_mental
UPDATE dc_salud_mental d
LEFT JOIN usuarios u ON u.usuario_id = d.id_usuario
SET
  d.consultorio_id = COALESCE(d.consultorio_id, u.consultorio_id),
  d.turno_id = COALESCE(d.turno_id, 1)
WHERE d.consultorio_id IS NULL OR d.turno_id IS NULL;

-- 3. Crear tabla atenciones_tamizajes para tamizajes rápidos (Detección de Cáncer de Colon y Recto, etc.)
CREATE TABLE IF NOT EXISTS atenciones_tamizajes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  consultorio_id INT UNSIGNED NULL,
  turno_id INT UNSIGNED NULL,
  tipo_tamizaje VARCHAR(50) NOT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tamizajes_paciente_fecha (paciente_id, fecha_hora),
  KEY idx_tamizajes_tipo (tipo_tamizaje),
  KEY fk_tamizajes_usuario (usuario_id),
  KEY fk_tamizajes_consultorio (consultorio_id),
  KEY fk_tamizajes_turno (turno_id),
  CONSTRAINT fk_tamizajes_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
  CONSTRAINT fk_tamizajes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id),
  CONSTRAINT fk_tamizajes_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL,
  CONSTRAINT fk_tamizajes_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
