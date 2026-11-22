USE enfermeria;

-- ============================================================================
-- Migración 017:
-- 1) Actualizar nombre de vacuna "NEUMOCOCCICA 23" a "NEUMOCOCCICA 20"
--    conservando su id y relaciones históricas.
-- 2) Ampliar tabla dc_salud_mental con puntajes desglosados (ansiedad P1+P2,
--    depresión P3+P4), clave_up (UP307/UP306/UP308) y sitio_envio (ENVIO MF).
-- ============================================================================

UPDATE vacunas
SET nombre = 'NEUMOCOCCICA 20'
WHERE clave IN ('NEUMO23', 'NEUMO20')
   OR UPPER(nombre) IN ('NEUMOCOCCICA 23', 'NEUMOCÓCCICA 23', 'NEUMOCOCICA 23');

UPDATE vacunas_aplicadas
SET vacuna = 'NEUMOCOCCICA 20'
WHERE vacuna_id IN (SELECT vacuna_id FROM vacunas WHERE clave IN ('NEUMO23', 'NEUMO20'))
   OR UPPER(vacuna) IN ('NEUMOCOCCICA 23', 'NEUMOCÓCCICA 23', 'NEUMOCOCICA 23');

ALTER TABLE dc_salud_mental
  MODIFY COLUMN valoracion VARCHAR(160) NOT NULL DEFAULT 'SIN RIESGO DETECTADO / NORMAL',
  ADD COLUMN IF NOT EXISTS puntaje_ansiedad TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Suma P1 + P2 (0 a 6)' AFTER puntaje_phq4,
  ADD COLUMN IF NOT EXISTS puntaje_depresion TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Suma P3 + P4 (0 a 6)' AFTER puntaje_ansiedad,
  ADD COLUMN IF NOT EXISTS clave_up VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Claves UP activas (UP307, UP306, UP308)' AFTER valoracion,
  ADD COLUMN IF NOT EXISTS sitio_envio VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Sitio de envío (ENVIO MF)' AFTER clave_up;
