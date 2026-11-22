USE enfermeria;

-- Segunda iteracion SRAE. Ejecutar despues de schema.sql.
-- Las columnas nuevas son compatibles con los registros existentes.

ALTER TABLE pacientes
  ADD COLUMN IF NOT EXISTS public_id CHAR(32) NULL AFTER paciente_id,
  ADD COLUMN IF NOT EXISTS dv CHAR(1) NULL AFTER nss,
  ADD COLUMN IF NOT EXISTS unidad_adscripcion_id INT UNSIGNED NULL AFTER agregado,
  ADD COLUMN IF NOT EXISTS lugar_nacimiento VARCHAR(160) NULL AFTER fecha_nacimiento,
  ADD COLUMN IF NOT EXISTS sexo VARCHAR(30) NULL AFTER lugar_nacimiento,
  ADD COLUMN IF NOT EXISTS indigena TINYINT(1) NULL AFTER sexo,
  ADD COLUMN IF NOT EXISTS afromexicano TINYINT(1) NULL AFTER indigena,
  ADD COLUMN IF NOT EXISTS discapacidad TINYINT(1) NULL AFTER afromexicano,
  ADD COLUMN IF NOT EXISTS pais_residencia VARCHAR(80) NULL AFTER discapacidad,
  ADD COLUMN IF NOT EXISTS entidad_residencia VARCHAR(120) NULL AFTER pais_residencia,
  ADD COLUMN IF NOT EXISTS municipio VARCHAR(120) NULL AFTER entidad_residencia,
  ADD COLUMN IF NOT EXISTS colonia VARCHAR(160) NULL AFTER municipio,
  ADD COLUMN IF NOT EXISTS calle VARCHAR(180) NULL AFTER colonia,
  ADD COLUMN IF NOT EXISTS numero_exterior VARCHAR(30) NULL AFTER calle,
  ADD COLUMN IF NOT EXISTS numero_interior VARCHAR(30) NULL AFTER numero_exterior,
  ADD COLUMN IF NOT EXISTS codigo_postal CHAR(5) NULL AFTER numero_interior;

UPDATE pacientes SET public_id = REPLACE(UUID(), '-', '') WHERE public_id IS NULL OR public_id = '';
ALTER TABLE pacientes MODIFY public_id CHAR(32) NOT NULL;
ALTER TABLE pacientes MODIFY agregado CHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
ALTER TABLE pacientes ADD UNIQUE KEY IF NOT EXISTS uq_pacientes_public_id (public_id);
ALTER TABLE pacientes ADD KEY IF NOT EXISTS idx_pacientes_dv (dv);
ALTER TABLE pacientes ADD CONSTRAINT fk_pacientes_unidad_adscripcion FOREIGN KEY (unidad_adscripcion_id) REFERENCES unidades(unidad_id);
ALTER TABLE pacientes
  ADD CONSTRAINT chk_pacientes_nss CHECK (nss REGEXP '^[0-9]{10}$'),
  ADD CONSTRAINT chk_pacientes_dv CHECK (dv IS NULL OR dv REGEXP '^[0-9]$'),
  ADD CONSTRAINT chk_pacientes_agregado CHECK (agregado REGEXP '^[0-9A-Z]{8}$');

ALTER TABLE atenciones
  ADD COLUMN IF NOT EXISTS public_id CHAR(32) NULL AFTER atencion_id,
  ADD COLUMN IF NOT EXISTS escolaridad VARCHAR(120) NULL AFTER hora,
  ADD COLUMN IF NOT EXISTS ocupacion VARCHAR(160) NULL AFTER escolaridad,
  ADD COLUMN IF NOT EXISTS estado_civil VARCHAR(80) NULL AFTER ocupacion,
  ADD COLUMN IF NOT EXISTS temperatura DECIMAL(4,1) NULL AFTER estado_civil,
  ADD COLUMN IF NOT EXISTS frecuencia_cardiaca SMALLINT UNSIGNED NULL AFTER temperatura,
  ADD COLUMN IF NOT EXISTS frecuencia_respiratoria SMALLINT UNSIGNED NULL AFTER frecuencia_cardiaca,
  ADD COLUMN IF NOT EXISTS presion_sistolica SMALLINT UNSIGNED NULL AFTER frecuencia_respiratoria,
  ADD COLUMN IF NOT EXISTS presion_diastolica SMALLINT UNSIGNED NULL AFTER presion_sistolica,
  ADD COLUMN IF NOT EXISTS peso DECIMAL(6,2) NULL AFTER presion_diastolica,
  ADD COLUMN IF NOT EXISTS talla DECIMAL(6,2) NULL AFTER peso,
  ADD COLUMN IF NOT EXISTS perimetro_cefalico DECIMAL(6,2) NULL AFTER talla,
  ADD COLUMN IF NOT EXISTS perimetro_toracico DECIMAL(6,2) NULL AFTER perimetro_cefalico,
  ADD COLUMN IF NOT EXISTS perimetro_abdominal DECIMAL(6,2) NULL AFTER perimetro_toracico,
  ADD COLUMN IF NOT EXISTS agudeza_visual_od VARCHAR(30) NULL AFTER perimetro_abdominal,
  ADD COLUMN IF NOT EXISTS agudeza_visual_oi VARCHAR(30) NULL AFTER agudeza_visual_od,
  ADD COLUMN IF NOT EXISTS exploracion_fisica TEXT NULL AFTER agudeza_visual_oi,
  ADD COLUMN IF NOT EXISTS signos_alarma TEXT NULL AFTER exploracion_fisica,
  ADD COLUMN IF NOT EXISTS envio_medico_familiar TINYINT(1) NULL AFTER signos_alarma,
  ADD COLUMN IF NOT EXISTS vitamina_a TINYINT(1) NULL AFTER envio_medico_familiar,
  ADD COLUMN IF NOT EXISTS desparasitacion TINYINT(1) NULL AFTER vitamina_a,
  ADD COLUMN IF NOT EXISTS cepillado_dental TINYINT(1) NULL AFTER desparasitacion,
  ADD COLUMN IF NOT EXISTS educacion_salud TINYINT(1) NULL AFTER cepillado_dental,
  ADD COLUMN IF NOT EXISTS acciones_preventivas TEXT NULL AFTER educacion_salud,
  ADD COLUMN IF NOT EXISTS nota_enfermeria TEXT NULL AFTER acciones_preventivas,
  ADD COLUMN IF NOT EXISTS observaciones TEXT NULL AFTER nota_enfermeria,
  ADD COLUMN IF NOT EXISTS patient_snapshot JSON NULL AFTER observaciones,
  ADD COLUMN IF NOT EXISTS companion_snapshot JSON NULL AFTER patient_snapshot,
  ADD COLUMN IF NOT EXISTS snapshot_version SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER companion_snapshot,
  ADD COLUMN IF NOT EXISTS edad_anios SMALLINT UNSIGNED NULL AFTER snapshot_version,
  ADD COLUMN IF NOT EXISTS edad_meses SMALLINT UNSIGNED NULL AFTER edad_anios,
  ADD COLUMN IF NOT EXISTS edad_dias SMALLINT UNSIGNED NULL AFTER edad_meses,
  ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1 AFTER edad_dias;

UPDATE atenciones SET public_id = REPLACE(UUID(), '-', '') WHERE public_id IS NULL OR public_id = '';
ALTER TABLE atenciones MODIFY public_id CHAR(32) NOT NULL;
ALTER TABLE atenciones ADD UNIQUE KEY IF NOT EXISTS uq_atenciones_public_id (public_id);

CREATE TABLE IF NOT EXISTS catalogos (
  catalogo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  clave VARCHAR(40) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_catalogos_tipo_clave (tipo, clave),
  KEY idx_catalogos_tipo_activo (tipo, activo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS acompanantes (
  acompanante_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT UNSIGNED NOT NULL,
  apellido_paterno VARCHAR(100) NULL,
  apellido_materno VARCHAR(100) NULL,
  nombres VARCHAR(160) NOT NULL,
  fecha_nacimiento DATE NULL,
  lugar_nacimiento VARCHAR(160) NULL,
  sexo VARCHAR(30) NULL,
  parentesco VARCHAR(100) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_acompanantes_paciente (paciente_id, activo),
  CONSTRAINT fk_acompanantes_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vacunas (
  vacuna_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(40) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vacunas_clave (clave)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dosis (
  dosis_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(40) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  orden SMALLINT UNSIGNED NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dosis_clave (clave)
) ENGINE=InnoDB;

ALTER TABLE vacunas_aplicadas
  ADD COLUMN IF NOT EXISTS vacuna_id INT UNSIGNED NULL AFTER paciente_id,
  ADD COLUMN IF NOT EXISTS dosis_id INT UNSIGNED NULL AFTER vacuna_id,
  ADD COLUMN IF NOT EXISTS lote VARCHAR(80) NULL AFTER fecha_aplicacion,
  ADD COLUMN IF NOT EXISTS fecha_caducidad DATE NULL AFTER lote,
  ADD COLUMN IF NOT EXISTS enfermera_id INT UNSIGNED NULL AFTER fecha_caducidad,
  ADD COLUMN IF NOT EXISTS estado ENUM('ACTIVA','ANULADA') NOT NULL DEFAULT 'ACTIVA' AFTER enfermera_id,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE vacunas_aplicadas ADD KEY IF NOT EXISTS idx_vacunas_aplicadas_paciente_fecha (paciente_id, fecha_aplicacion);
ALTER TABLE vacunas_aplicadas ADD CONSTRAINT fk_vacunas_catalogo FOREIGN KEY (vacuna_id) REFERENCES vacunas(vacuna_id);
ALTER TABLE vacunas_aplicadas ADD CONSTRAINT fk_vacunas_dosis FOREIGN KEY (dosis_id) REFERENCES dosis(dosis_id);
ALTER TABLE vacunas_aplicadas ADD CONSTRAINT fk_vacunas_enfermera FOREIGN KEY (enfermera_id) REFERENCES usuarios(usuario_id);

INSERT INTO catalogos (tipo, clave, nombre) VALUES
 ('sexo','F','Femenino'), ('sexo','M','Masculino'), ('sexo','NO_ESPECIFICADO','No especificado'),
 ('regimen','OR','Ordinario'), ('tipo_beneficiario','3','Asegurado / beneficiario'),
 ('parentesco','MADRE','Madre'), ('parentesco','PADRE','Padre'), ('parentesco','TUTOR','Tutor'),
 ('escolaridad','PRIMARIA','Primaria'), ('escolaridad','SECUNDARIA','Secundaria'),
 ('estado_civil','SOLTERO','Soltero'), ('estado_civil','CASADO','Casado')
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), activo=1;

INSERT INTO vacunas (clave, nombre) VALUES
 ('BCG','BCG'),('HEP_B','Hepatitis B'),('HEXA','Hexavalente'),('DPT','DPT'),('ROTA','Rotavirus'),
 ('NEUMO13','Neumocócica 13'),('INFLUENZA','Influenza'),('SRP','SRP'),('VARICELA','Varicela'),
 ('HEP_A','Hepatitis A'),('VPH','VPH'),('SR','SR'),('TD','Td'),('TDPA','Tdpa'),
 ('NEUMO23','NEUMOCOCCICA 20'),('COVID19','COVID-19')
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), activo=1;

INSERT INTO dosis (clave, nombre, orden) VALUES
 ('1ER','1ª dosis',1),('2DA','2ª dosis',2),('3ER','3ª dosis',3),('4TA','4ª dosis',4),('REF','Refuerzo',5),('ADIC','Adicional',6)
 ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden), activo=1;
