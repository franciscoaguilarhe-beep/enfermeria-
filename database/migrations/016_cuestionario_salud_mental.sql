USE enfermeria;

-- ============================================================================
-- Migración 016: Cuestionario de Salud Mental. Detección de ansiedad,
-- depresión y trastornos de pánico (PHQ-4 + PHQ-TP)
-- ============================================================================

CREATE TABLE IF NOT EXISTS cat_preguntas_salud_mental (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orden TINYINT UNSIGNED NOT NULL,
  seccion ENUM('PHQ4', 'PHQTP') NOT NULL DEFAULT 'PHQ4',
  pregunta TEXT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  actualizado_por INT UNSIGNED NULL,
  f_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_preguntas_sm_orden (orden),
  KEY idx_cat_preguntas_sm_activo (activo, orden),
  CONSTRAINT fk_cat_preg_sm_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(usuario_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cat_preguntas_salud_mental (orden, seccion, pregunta, activo) VALUES
  (1, 'PHQ4', '1. Sentirse nervioso, angustiado o muy tenso', 1),
  (2, 'PHQ4', '2. Ser incapaz de controlar las preocupaciones', 1),
  (3, 'PHQ4', '3. Tener poco interés o alegría por hacer cosas', 1),
  (4, 'PHQ4', '4. Sentirse decaído, deprimido o sin esperanza', 1),
  (5, 'PHQTP', '5. En las últimas 4 semanas, ¿ha tenido algún ataque de ansiedad o sensación repentina de pánico o miedo?', 1)
ON DUPLICATE KEY UPDATE
  seccion = VALUES(seccion),
  pregunta = VALUES(pregunta),
  activo = VALUES(activo);

CREATE TABLE IF NOT EXISTS dc_salud_mental (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_paciente INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  puntaje_phq4 TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Suma de preguntas 1 a 4 (0 a 12)',
  ataque_panico TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Respuesta pregunta 5: 1 = Sí, 0 = No',
  valoracion VARCHAR(60) NOT NULL DEFAULT 'Normal',
  f_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dc_sm_paciente_fecha (id_paciente, fecha),
  KEY fk_dc_sm_usuario (id_usuario),
  CONSTRAINT fk_dc_sm_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
  CONSTRAINT fk_dc_sm_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dc_salud_mental_respuestas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_dc_sm INT UNSIGNED NOT NULL,
  id_pregunta INT UNSIGNED NOT NULL,
  valor TINYINT UNSIGNED NOT NULL COMMENT 'Para PHQ4: 0..3 | Para PHQTP (preg 5): 1=Sí, 0=No',
  UNIQUE KEY uq_sm_pregunta (id_dc_sm, id_pregunta),
  KEY fk_dc_sm_resp_pregunta (id_pregunta),
  CONSTRAINT fk_dc_sm_resp_encabezado FOREIGN KEY (id_dc_sm) REFERENCES dc_salud_mental(id) ON DELETE CASCADE,
  CONSTRAINT fk_dc_sm_resp_pregunta FOREIGN KEY (id_pregunta) REFERENCES cat_preguntas_salud_mental(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
