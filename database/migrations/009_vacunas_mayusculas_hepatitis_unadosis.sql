-- Migración 009: Vacunas en mayúsculas, nombres limpios (sin caracteres raros) y Hepatitis B a 1 sola dosis
UPDATE vacunas SET nombre = 'BCG' WHERE clave = 'BCG';
UPDATE vacunas SET nombre = 'HEPATITIS B' WHERE clave = 'HEP_B';
UPDATE vacunas SET nombre = 'HEXAVALENTE' WHERE clave = 'HEXA';
UPDATE vacunas SET nombre = 'DPT' WHERE clave = 'DPT';
UPDATE vacunas SET nombre = 'ROTAVIRUS' WHERE clave = 'ROTA';
UPDATE vacunas SET nombre = 'NEUMOCOCCICA 13' WHERE clave = 'NEUMO13';
UPDATE vacunas SET nombre = 'INFLUENZA' WHERE clave = 'INFLUENZA';
UPDATE vacunas SET nombre = 'SRP' WHERE clave = 'SRP';
UPDATE vacunas SET nombre = 'VARICELA' WHERE clave = 'VARICELA';
UPDATE vacunas SET nombre = 'HEPATITIS A' WHERE clave = 'HEP_A';
UPDATE vacunas SET nombre = 'VPH' WHERE clave = 'VPH';
UPDATE vacunas SET nombre = 'SR' WHERE clave = 'SR';
UPDATE vacunas SET nombre = 'TD' WHERE clave = 'TD';
UPDATE vacunas SET nombre = 'TDPA' WHERE clave = 'TDPA';
UPDATE vacunas SET nombre = 'NEUMOCOCCICA 20' WHERE clave = 'NEUMO23';
UPDATE vacunas SET nombre = 'COVID-19' WHERE clave = 'COVID19';

-- Hepatitis B solo lleva 1 dosis (1ER), desactivar 2DA y 3ER
UPDATE vacuna_slots SET activo = 0 WHERE vacuna_id = (SELECT vacuna_id FROM vacunas WHERE clave = 'HEP_B') AND dosis_id IN (2, 3);
