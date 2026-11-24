CREATE DATABASE IF NOT EXISTS enfermeria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE enfermeria;

CREATE TABLE IF NOT EXISTS usuarios (
  usuario_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  matricula VARCHAR(40) NOT NULL,
  prefijo VARCHAR(25) NOT NULL DEFAULT 'L.E.',
  nombre VARCHAR(180) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_matricula (matricula),
  KEY idx_usuarios_activo (activo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unidades (
  unidad_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(30) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unidades_clave (clave)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consultorios (
  consultorio_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidad_id INT UNSIGNED NOT NULL,
  clave VARCHAR(30) NOT NULL,
  nombre_descriptivo VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_consultorio_unidad_clave (unidad_id, clave),
  KEY idx_consultorios_unidad_activo (unidad_id, activo),
  CONSTRAINT fk_consultorios_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(unidad_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS turnos (
  turno_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(30) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  hora_inicio TIME NULL,
  hora_fin TIME NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_turnos_clave (clave)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unidad_consultorio_turno (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unidad_id INT UNSIGNED NOT NULL,
  consultorio_id INT UNSIGNED NOT NULL,
  turno_id INT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_matriz (unidad_id, consultorio_id, turno_id),
  KEY idx_matriz_activa (unidad_id, consultorio_id, turno_id, activo),
  CONSTRAINT fk_matriz_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(unidad_id),
  CONSTRAINT fk_matriz_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id),
  CONSTRAINT fk_matriz_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pacientes (
  paciente_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nss CHAR(10) NOT NULL,
  agregado CHAR(8) NOT NULL,
  consultorio_id INT UNSIGNED NULL DEFAULT NULL,
  turno_id INT UNSIGNED NULL DEFAULT NULL,
  apellido_paterno VARCHAR(100) NULL,
  apellido_materno VARCHAR(100) NULL,
  nombres VARCHAR(160) NOT NULL,
  fecha_nacimiento DATE NULL,
  telefono VARCHAR(30) NULL,
  otro_contacto VARCHAR(160) NULL,
  domicilio VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pacientes_identidad (nss, agregado),
  KEY idx_pacientes_nss (nss),
  KEY idx_pacientes_nombre (apellido_paterno, apellido_materno, nombres),
  KEY idx_pacientes_fecha_nacimiento (fecha_nacimiento),
  KEY fk_pacientes_consultorio (consultorio_id),
  KEY fk_pacientes_turno (turno_id),
  CONSTRAINT fk_pacientes_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL,
  CONSTRAINT fk_pacientes_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS atenciones (
  atencion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT UNSIGNED NOT NULL,
  unidad_id INT UNSIGNED NOT NULL,
  consultorio_id INT UNSIGNED NOT NULL,
  turno_id INT UNSIGNED NOT NULL,
  enfermera_id INT UNSIGNED NOT NULL,
  sin_acompanante TINYINT(1) NOT NULL DEFAULT 0,
  fecha DATE NOT NULL,
  hora TIME NOT NULL,
  datos_clinicos TEXT NULL,
  estado ENUM('ACTIVA','ANULADA') NOT NULL DEFAULT 'ACTIVA',
  motivo_anulacion VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_atenciones_fecha (fecha),
  KEY idx_atenciones_filtros (unidad_id, consultorio_id, turno_id, enfermera_id, fecha),
  CONSTRAINT fk_atenciones_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id),
  CONSTRAINT fk_atenciones_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(unidad_id),
  CONSTRAINT fk_atenciones_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id),
  CONSTRAINT fk_atenciones_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id),
  CONSTRAINT fk_atenciones_enfermera FOREIGN KEY (enfermera_id) REFERENCES usuarios(usuario_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vacunas_aplicadas (
  aplicacion_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(32) NOT NULL,
  vacuna_id INT UNSIGNED NOT NULL,
  dosis_id INT UNSIGNED NULL,
  paciente_id INT UNSIGNED NOT NULL,
  atencion_id INT UNSIGNED NULL,
  vacuna VARCHAR(160) NOT NULL,
  fecha_aplicacion DATE NOT NULL,
  lote VARCHAR(80) NULL,
  fecha_caducidad DATE NULL,
  enfermera_id INT UNSIGNED NULL,
  estado ENUM('ACTIVA','ANULADA') NOT NULL DEFAULT 'ACTIVA',
  observaciones VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vacunas_aplicadas_public_id (public_id),
  UNIQUE KEY uq_vacunas_paciente_vacuna_dosis (paciente_id, vacuna_id, dosis_id),
  KEY idx_vacunas_aplicadas_paciente_fecha (paciente_id, fecha_aplicacion),
  KEY idx_vacunas_aplicadas_dosis (paciente_id, vacuna_id, dosis_id, estado),
  CONSTRAINT fk_vacunas_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id),
  CONSTRAINT fk_vacunas_atencion FOREIGN KEY (atencion_id) REFERENCES atenciones(atencion_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auditoria (
  auditoria_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NULL,
  accion VARCHAR(80) NOT NULL,
  entidad VARCHAR(80) NOT NULL,
  entidad_id INT UNSIGNED NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  valor_anterior JSON NULL,
  valor_nuevo JSON NULL,
  motivo VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auditoria_fecha (created_at),
  KEY idx_auditoria_entidad (entidad, entidad_id),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(usuario_id)
) ENGINE=InnoDB;

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
  consultorio_id INT UNSIGNED NULL,
  turno_id INT UNSIGNED NULL,
  fecha DATE NOT NULL,
  grupo_riesgo ENUM('40_44_AHF', '45_74') NOT NULL DEFAULT '45_74' COMMENT '40_44_AHF = Hombres 40 a 44 años con antecedente heredofamiliar, 45_74 = Hombres 45 a 74 años',
  puntaje_total TINYINT UNSIGNED NOT NULL DEFAULT 0,
  valoracion VARCHAR(40) NOT NULL DEFAULT 'Normal',
  f_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dc_prostata_paciente_fecha (id_paciente, fecha),
  KEY fk_dc_prostata_usuario (id_usuario),
  KEY fk_dc_prostata_consultorio (consultorio_id),
  KEY fk_dc_prostata_turno (turno_id),
  CONSTRAINT fk_dc_prostata_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
  CONSTRAINT fk_dc_prostata_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(usuario_id),
  CONSTRAINT fk_dc_prostata_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL,
  CONSTRAINT fk_dc_prostata_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL
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
  consultorio_id INT UNSIGNED NULL,
  turno_id INT UNSIGNED NULL,
  fecha DATE NOT NULL,
  puntaje_phq4 TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Suma de preguntas 1 a 4 (0 a 12)',
  puntaje_ansiedad TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Suma P1 + P2 (0 a 6)',
  puntaje_depresion TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Suma P3 + P4 (0 a 6)',
  ataque_panico TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Respuesta pregunta 5: 1 = Sí, 0 = No',
  valoracion VARCHAR(160) NOT NULL DEFAULT 'SIN RIESGO DETECTADO / NORMAL',
  clave_up VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Claves UP activas (UP307, UP306, UP308)',
  sitio_envio VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Sitio de envío (ENVIO MF)',
  f_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  f_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_dc_sm_paciente_fecha (id_paciente, fecha),
  KEY fk_dc_sm_usuario (id_usuario),
  KEY fk_dc_sm_consultorio (consultorio_id),
  KEY fk_dc_sm_turno (turno_id),
  CONSTRAINT fk_dc_sm_paciente FOREIGN KEY (id_paciente) REFERENCES pacientes(paciente_id) ON DELETE CASCADE,
  CONSTRAINT fk_dc_sm_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(usuario_id),
  CONSTRAINT fk_dc_sm_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id) ON DELETE SET NULL,
  CONSTRAINT fk_dc_sm_turno FOREIGN KEY (turno_id) REFERENCES turnos(turno_id) ON DELETE SET NULL
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
