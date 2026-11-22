ALTER TABLE consultorios DROP FOREIGN KEY fk_consultorios_unidad;
ALTER TABLE consultorios DROP KEY uq_consultorio_unidad_clave;
ALTER TABLE consultorios DROP KEY idx_consultorios_unidad_activo;
ALTER TABLE consultorios DROP COLUMN unidad_id;
ALTER TABLE consultorios ADD UNIQUE KEY uq_consultorios_clave (clave);
