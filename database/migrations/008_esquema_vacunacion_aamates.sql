-- ============================================================================
-- Migración 008: Esquema de Vacunación AAMATES según matriz Excel del usuario
-- ============================================================================

INSERT IGNORE INTO dosis (dosis_id, clave, nombre, orden, activo, created_at, updated_at) 
VALUES (7, 'ADIC2', 'Adicional 2', 7, 1, NOW(), NOW());

-- Desactivar todos los slots
UPDATE vacuna_slots SET activo = 0;

-- Reactivar o insertar los slots exactos de acuerdo a la matriz Excel
-- 1. BCG: 1ER
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
SELECT 1, 1, '1ER', 1, 1
ON DUPLICATE KEY UPDATE slot_codigo = '1ER', orden = 1, activo = 1;

-- 2. HEPATITIS B: 1ER, 2DA, 3ER
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (2, 1, '1ER', 1, 1), (2, 2, '2DA', 2, 1), (2, 3, '3ER', 3, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 3. HEXAVALENTE: 1ER, 2DA, 3ER, 4TA
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (3, 1, '1ER', 1, 1), (3, 2, '2DA', 2, 1), (3, 3, '3ER', 3, 1), (3, 4, '4TA', 4, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 4. DPT: 1ER
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (4, 1, '1ER', 1, 1)
ON DUPLICATE KEY UPDATE slot_codigo = '1ER', orden = 1, activo = 1;

-- 5. ROTAVIRUS: 1ER, 2DA
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (5, 1, '1ER', 1, 1), (5, 2, '2DA', 2, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 6. NEUMOCOCCICA 13: 1ER, 2DA, 3ER
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (6, 1, '1ER', 1, 1), (6, 2, '2DA', 2, 1), (6, 3, '3ER', 3, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 7. ANTIINFLUENZA (Influenza): 1ER, 2DA, 3ER, 4TA, REF, ADIC, ADIC2
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (7, 1, '1ER', 1, 1), (7, 2, '2DA', 2, 1), (7, 3, '3ER', 3, 1), (7, 4, '4TA', 4, 1), (7, 5, 'REF', 5, 1), (7, 6, 'ADIC', 6, 1), (7, 7, 'ADIC2', 7, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 8. SRP: 1ER, 2DA
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (8, 1, '1ER', 1, 1), (8, 2, '2DA', 2, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 9. VARICELA: 1ER, 2DA
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (9, 1, '1ER', 1, 1), (9, 2, '2DA', 2, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 10. HEPATITIS A: 1ER, 2DA
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (10, 1, '1ER', 1, 1), (10, 2, '2DA', 2, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 11. VPH: 1ER
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (11, 1, '1ER', 1, 1)
ON DUPLICATE KEY UPDATE slot_codigo = '1ER', orden = 1, activo = 1;

-- 12. SR: 1ER, 2DA, 3ER, 4TA, REF, ADIC, ADIC2
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (12, 1, '1ER', 1, 1), (12, 2, '2DA', 2, 1), (12, 3, '3ER', 3, 1), (12, 4, '4TA', 4, 1), (12, 5, 'REF', 5, 1), (12, 6, 'ADIC', 6, 1), (12, 7, 'ADIC2', 7, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 13. TD.: 1ER, 2DA, 3ER, 4TA, REF, ADIC, ADIC2
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (13, 1, '1ER', 1, 1), (13, 2, '2DA', 2, 1), (13, 3, '3ER', 3, 1), (13, 4, '4TA', 4, 1), (13, 5, 'REF', 5, 1), (13, 6, 'ADIC', 6, 1), (13, 7, 'ADIC2', 7, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 14. TDPA: 1ER
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (14, 1, '1ER', 1, 1)
ON DUPLICATE KEY UPDATE slot_codigo = '1ER', orden = 1, activo = 1;

-- 15. NEUMOCOCCICA 20: 1ER, 2DA, 3ER, 4TA, REF, ADIC, ADIC2
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (15, 1, '1ER', 1, 1), (15, 2, '2DA', 2, 1), (15, 3, '3ER', 3, 1), (15, 4, '4TA', 4, 1), (15, 5, 'REF', 5, 1), (15, 6, 'ADIC', 6, 1), (15, 7, 'ADIC2', 7, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;

-- 16. COVID 19: 1ER, 2DA, 3ER, 4TA, REF, ADIC, ADIC2
INSERT INTO vacuna_slots (vacuna_id, dosis_id, slot_codigo, orden, activo)
VALUES (16, 1, '1ER', 1, 1), (16, 2, '2DA', 2, 1), (16, 3, '3ER', 3, 1), (16, 4, '4TA', 4, 1), (16, 5, 'REF', 5, 1), (16, 6, 'ADIC', 6, 1), (16, 7, 'ADIC2', 7, 1)
ON DUPLICATE KEY UPDATE slot_codigo = VALUES(slot_codigo), orden = VALUES(orden), activo = 1;
