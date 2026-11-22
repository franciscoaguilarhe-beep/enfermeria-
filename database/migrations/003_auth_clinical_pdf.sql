USE enfermeria;

-- Tercera iteración: autenticación, autorización y campos clínicos faltantes.
-- No elimina columnas legacy ni reescribe migraciones ya aplicadas.

CREATE TABLE IF NOT EXISTS roles (
  rol_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(40) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_roles_clave (clave)
) ENGINE=InnoDB;

INSERT INTO roles (clave, nombre, activo) VALUES
  ('ADMINISTRADOR', 'Administrador', 1),
  ('SUPERVISOR', 'Supervisor', 1),
  ('ENFERMERIA', 'Enfermería', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), activo=1;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER nombre,
  ADD COLUMN IF NOT EXISTS rol_id INT UNSIGNED NULL AFTER password_hash,
  ADD COLUMN IF NOT EXISTS unidad_id INT UNSIGNED NULL AFTER rol_id,
  ADD COLUMN IF NOT EXISTS consultorio_id INT UNSIGNED NULL AFTER unidad_id,
  ADD COLUMN IF NOT EXISTS es_personal_clinico TINYINT(1) NOT NULL DEFAULT 0 AFTER consultorio_id,
  ADD COLUMN IF NOT EXISTS debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1 AFTER es_personal_clinico,
  ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL AFTER debe_cambiar_password,
  ADD COLUMN IF NOT EXISTS intentos_fallidos SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER ultimo_acceso,
  ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL AFTER intentos_fallidos;
ALTER TABLE usuarios ADD KEY IF NOT EXISTS idx_usuarios_rol_activo (rol_id, activo);
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(rol_id);
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_unidad FOREIGN KEY (unidad_id) REFERENCES unidades(unidad_id);
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_consultorio FOREIGN KEY (consultorio_id) REFERENCES consultorios(consultorio_id);

ALTER TABLE atenciones
  ADD COLUMN IF NOT EXISTS attention_context_snapshot JSON NULL AFTER patient_snapshot,
  ADD COLUMN IF NOT EXISTS vitamina_a_fecha DATE NULL AFTER vitamina_a,
  ADD COLUMN IF NOT EXISTS desparasitacion_fecha DATE NULL AFTER desparasitacion,
  ADD COLUMN IF NOT EXISTS cepillado_dental_fecha DATE NULL AFTER desparasitacion_fecha,
  ADD COLUMN IF NOT EXISTS sulfato_ferroso_fecha DATE NULL AFTER cepillado_dental_fecha,
  ADD COLUMN IF NOT EXISTS fecha_envio_medico_familiar DATE NULL AFTER envio_medico_familiar,
  ADD COLUMN IF NOT EXISTS educacion_salud_legacy TINYINT(1) NULL AFTER educacion_salud,
  ADD COLUMN IF NOT EXISTS educacion_salud_texto TEXT NULL AFTER educacion_salud_legacy;
UPDATE atenciones SET educacion_salud_legacy=educacion_salud WHERE educacion_salud_legacy IS NULL AND educacion_salud IS NOT NULL;

CREATE TABLE IF NOT EXISTS temas_educacion_salud (
  tema_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(60) NOT NULL,
  nombre VARCHAR(180) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_temas_educacion_clave (clave)
) ENGINE=InnoDB;
INSERT INTO temas_educacion_salud (clave, nombre, activo) VALUES
  ('HIGIENE_SALUD_BUCAL', 'Higiene personal y salud bucal', 1),
  ('ALIMENTACION_SALUDABLE', 'Alimentación saludable', 1),
  ('ACTIVIDAD_FISICA', 'Actividad física', 1)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), activo=1;

CREATE TABLE IF NOT EXISTS atencion_educacion_salud (
  atencion_id INT UNSIGNED NOT NULL,
  tema_id INT UNSIGNED NOT NULL,
  observacion VARCHAR(500) NULL,
  PRIMARY KEY (atencion_id, tema_id),
  CONSTRAINT fk_atencion_educacion_atencion FOREIGN KEY (atencion_id) REFERENCES atenciones(atencion_id),
  CONSTRAINT fk_atencion_educacion_tema FOREIGN KEY (tema_id) REFERENCES temas_educacion_salud(tema_id)
) ENGINE=InnoDB;

ALTER TABLE auditoria
  ADD COLUMN IF NOT EXISTS resultado ENUM('OK','ERROR') NOT NULL DEFAULT 'OK' AFTER motivo,
  ADD COLUMN IF NOT EXISTS campos_modificados JSON NULL AFTER resultado;

-- El contexto histórico se captura al crear una atención; los registros previos
-- permanecen válidos y se reimprimen con sus columnas históricas disponibles.
