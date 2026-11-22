USE enfermeria;

-- ============================================================================
-- Migración 014: Cuestionario de cálculo de riesgo para la detección de
-- Cáncer de Próstata (PREVENIMSS / CHKT en línea)
-- ============================================================================

CREATE TABLE IF NOT EXISTS cat_preguntas_prostata (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  orden TINYINT UNSIGNED NOT NULL,
  pregunta TEXT NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  actualizado_por INT UNSIGNED NULL,
  f_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_preguntas_prostata_orden (orden),
  KEY idx_cat_preguntas_prostata_activo (activo, orden),
  CONSTRAINT fk_cat_preg_prostata_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(usuario_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cat_preguntas_prostata (orden, pregunta, activo) VALUES
  (1, 'Durante el último mes ¿Con qué frecuencia tuvo la sensación de no haber vaciado completamente la vejiga después de orinar?', 1),
  (2, 'Durante el último mes ¿Con qué frecuencia debió orinar nuevamente en menos de dos horas después de haber terminado de orinar?', 1),
  (3, 'Durante el último mes ¿Con qué frecuencia descubrió que al orinar se detenía y comenzaba a orinar nuevamente?', 1),
  (4, 'Durante el último mes ¿Cuántas veces le resultó difícil demorar la micción?', 1),
  (5, 'Durante el último mes ¿Cuántas veces ha tenido un chorro urinario débil?', 1),
  (6, 'Durante el último mes ¿Cuántas veces tuvo que esforzarse para comenzar a orinar?', 1),
  (7, 'Durante el último mes ¿Cuántas veces se ha levantado para orinar desde que se acostó por la noche hasta que se levantó en la mañana?', 1)
ON DUPLICATE KEY UPDATE
  pregunta = VALUES(pregunta),
  activo = VALUES(activo);

CREATE TABLE IF NOT EXISTS dc_prostata (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_paciente INT UNSIGNED NOT NULL,
  id_usuario INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  grupo_riesgo ENUM('40_44_AHF', '45_74') NOT NULL DEFAULT '45_74' COMMENT '40_44_AHF = Hombres 40 a 44 años con antecedente heredofamiliar, 45_74 = Hombres 45 a 74 años',
  puntaje_total TINYINT UNSIGNED NOT NULL DEFAULT 0,
  valoracion VARCHAR(40) NOT NULL DEFAULT 'Normal',
  f_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dc_prostata_paciente_fecha (id_paciente, fecha),
  KEY fk_dc_prostata_usuario (id_usuario),
  CONSTRAINT fk_dc_prostata_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
  CONSTRAINT fk_dc_prostata_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dc_prostata_respuestas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_dc_prostata INT UNSIGNED NOT NULL,
  id_cp INT UNSIGNED NOT NULL,
  valor TINYINT UNSIGNED NOT NULL COMMENT 'Valores de 0 a 5',
  UNIQUE KEY uq_prostata_pregunta (id_dc_prostata, id_cp),
  KEY fk_dc_prostata_resp_pregunta (id_cp),
  CONSTRAINT fk_dc_prostata_resp_encabezado FOREIGN KEY (id_dc_prostata) REFERENCES dc_prostata(id) ON DELETE CASCADE,
  CONSTRAINT fk_dc_prostata_resp_pregunta FOREIGN KEY (id_cp) REFERENCES cat_preguntas_prostata(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
