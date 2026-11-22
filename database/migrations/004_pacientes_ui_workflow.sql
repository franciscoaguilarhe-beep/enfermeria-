USE enfermeria;

-- Cuarta iteración: flujo modal de Pacientes, acompañante histórico y
-- esquema configurable de vacunación. No elimina ni reescribe datos existentes.

ALTER TABLE atenciones
  ADD COLUMN IF NOT EXISTS sin_acompanante TINYINT(1) NOT NULL DEFAULT 0 AFTER turno_id;

ALTER TABLE acompanantes
  ADD COLUMN IF NOT EXISTS atencion_id INT UNSIGNED NULL AFTER acompanante_id,
  ADD COLUMN IF NOT EXISTS observaciones TEXT NULL AFTER parentesco;
ALTER TABLE acompanantes
  ADD KEY IF NOT EXISTS idx_acompanantes_atencion (atencion_id);
ALTER TABLE acompanantes
  ADD CONSTRAINT fk_acompanantes_atencion FOREIGN KEY (atencion_id) REFERENCES atenciones(atencion_id);

ALTER TABLE vacunas
  ADD COLUMN IF NOT EXISTS orden_visual SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER nombre;

ALTER TABLE vacunas_aplicadas
  ADD COLUMN IF NOT EXISTS public_id CHAR(32) NULL AFTER vacuna_id;
UPDATE vacunas_aplicadas
SET public_id = REPLACE(UUID(), '-', '')
WHERE public_id IS NULL OR public_id = '';
ALTER TABLE vacunas_aplicadas MODIFY public_id CHAR(32) NOT NULL;
ALTER TABLE vacunas_aplicadas ADD UNIQUE KEY IF NOT EXISTS uq_vacunas_aplicadas_public_id (public_id);
ALTER TABLE vacunas_aplicadas ADD KEY IF NOT EXISTS idx_vacunas_aplicadas_dosis (paciente_id, vacuna_id, dosis_id, estado);

CREATE TABLE IF NOT EXISTS vacuna_slots (
  slot_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vacuna_id INT UNSIGNED NOT NULL,
  dosis_id INT UNSIGNED NOT NULL,
  slot_codigo VARCHAR(20) NOT NULL,
  orden SMALLINT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_vacuna_slot (vacuna_id, dosis_id),
  KEY idx_vacuna_slots_orden (vacuna_id, activo, orden),
  CONSTRAINT fk_vacuna_slots_vacuna FOREIGN KEY (vacuna_id) REFERENCES vacunas(vacuna_id),
  CONSTRAINT fk_vacuna_slots_dosis FOREIGN KEY (dosis_id) REFERENCES dosis(dosis_id)
) ENGINE=InnoDB;

UPDATE vacunas SET orden_visual = CASE clave
  WHEN 'BCG' THEN 1 WHEN 'HEP_B' THEN 2 WHEN 'HEXA' THEN 3 WHEN 'DPT' THEN 4
  WHEN 'ROTA' THEN 5 WHEN 'NEUMO13' THEN 6 WHEN 'INFLUENZA' THEN 7 WHEN 'SRP' THEN 8
  WHEN 'VARICELA' THEN 9 WHEN 'HEP_A' THEN 10 WHEN 'VPH' THEN 11 WHEN 'SR' THEN 12
  WHEN 'TD' THEN 13 WHEN 'TDPA' THEN 14 WHEN 'NEUMO23' THEN 15 WHEN 'COVID19' THEN 16
  ELSE orden_visual END;

INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
SELECT v.vacuna_id, d.dosis_id, d.clave, d.orden, 1
FROM (
  SELECT 'BCG' clave, '1ER' dosis UNION ALL SELECT 'BCG', 'REF'
  UNION ALL SELECT 'HEP_B', '1ER' UNION ALL SELECT 'HEP_B', '2DA' UNION ALL SELECT 'HEP_B', '3ER'
  UNION ALL SELECT 'HEXA', '1ER' UNION ALL SELECT 'HEXA', '2DA' UNION ALL SELECT 'HEXA', '3ER' UNION ALL SELECT 'HEXA', 'REF'
  UNION ALL SELECT 'DPT', '1ER' UNION ALL SELECT 'DPT', '2DA' UNION ALL SELECT 'DPT', '3ER' UNION ALL SELECT 'DPT', 'REF'
  UNION ALL SELECT 'ROTA', '1ER' UNION ALL SELECT 'ROTA', '2DA' UNION ALL SELECT 'ROTA', '3ER'
  UNION ALL SELECT 'NEUMO13', '1ER' UNION ALL SELECT 'NEUMO13', '2DA' UNION ALL SELECT 'NEUMO13', 'REF'
  UNION ALL SELECT 'INFLUENZA', '1ER' UNION ALL SELECT 'INFLUENZA', '2DA' UNION ALL SELECT 'INFLUENZA', 'REF'
  UNION ALL SELECT 'SRP', '1ER' UNION ALL SELECT 'SRP', '2DA'
  UNION ALL SELECT 'VARICELA', '1ER'
  UNION ALL SELECT 'HEP_A', '1ER' UNION ALL SELECT 'HEP_A', '2DA'
  UNION ALL SELECT 'VPH', '1ER' UNION ALL SELECT 'VPH', '2DA'
  UNION ALL SELECT 'SR', '1ER' UNION ALL SELECT 'SR', '2DA'
  UNION ALL SELECT 'TD', '1ER' UNION ALL SELECT 'TD', 'REF'
  UNION ALL SELECT 'TDPA', '1ER'
  UNION ALL SELECT 'NEUMO23', '1ER' UNION ALL SELECT 'NEUMO23', 'REF'
  UNION ALL SELECT 'COVID19', '1ER' UNION ALL SELECT 'COVID19', '2DA' UNION ALL SELECT 'COVID19', '3ER' UNION ALL SELECT 'COVID19', 'REF'
) m
JOIN vacunas v ON v.clave = m.clave
JOIN dosis d ON d.clave = m.dosis
ON DUPLICATE KEY UPDATE activo = 1, orden = VALUES(orden), slot_codigo = VALUES(slot_codigo);

