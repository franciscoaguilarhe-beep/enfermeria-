USE enfermeria;

-- ============================================================================
-- Migración 013: Soporte de actualización (UPSERT) en vacunas_aplicadas y
-- prevención de fechas inválidas (0000-00-00).
-- ============================================================================

-- 1. Limpiar posibles fechas de caducidad inválidas ('0000-00-00') a NULL
UPDATE vacunas_aplicadas
SET fecha_caducidad = NULL
WHERE CAST(fecha_caducidad AS CHAR) = '0000-00-00';

-- 2. Eliminar duplicados activos previos (si existieran) conservando el más reciente
DELETE va1 FROM vacunas_aplicadas va1
INNER JOIN vacunas_aplicadas va2
  ON va1.paciente_id = va2.paciente_id
 AND va1.vacuna_id = va2.vacuna_id
 AND va1.dosis_id = va2.dosis_id
 AND va1.aplicacion_id < va2.aplicacion_id;

-- 3. Asegurar índice único por (paciente_id, vacuna_id, dosis_id) para soportar
--    INSERT ... ON DUPLICATE KEY UPDATE de forma nativa.
ALTER TABLE vacunas_aplicadas
  ADD UNIQUE KEY IF NOT EXISTS uq_vacunas_paciente_vacuna_dosis (paciente_id, vacuna_id, dosis_id);
