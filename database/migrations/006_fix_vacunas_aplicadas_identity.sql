USE enfermeria;

-- Corrige el conflicto histórico donde vacuna_id era el folio de la aplicación.
-- El folio pasa a aplicacion_id y vacuna_id queda como referencia al catálogo.
ALTER TABLE vacunas_aplicadas DROP FOREIGN KEY fk_vacunas_catalogo;
ALTER TABLE vacunas_aplicadas CHANGE COLUMN vacuna_id aplicacion_id INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST;
ALTER TABLE vacunas_aplicadas ADD COLUMN vacuna_id INT UNSIGNED NULL AFTER public_id;

UPDATE vacunas_aplicadas va
JOIN vacunas v ON v.clave = va.vacuna OR v.nombre = va.vacuna
SET va.vacuna_id = v.vacuna_id
WHERE va.vacuna_id IS NULL;

ALTER TABLE vacunas_aplicadas MODIFY vacuna_id INT UNSIGNED NOT NULL;
ALTER TABLE vacunas_aplicadas DROP INDEX idx_vacunas_aplicadas_dosis;
ALTER TABLE vacunas_aplicadas ADD KEY idx_vacunas_aplicadas_dosis (paciente_id, vacuna_id, dosis_id, estado);
ALTER TABLE vacunas_aplicadas ADD CONSTRAINT fk_vacunas_catalogo FOREIGN KEY (vacuna_id) REFERENCES vacunas(vacuna_id);
