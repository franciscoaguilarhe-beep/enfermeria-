USE enfermeria;

-- ============================================================================
-- Migración 015: Agregar columna prefijo / título académico a la tabla usuarios
-- ============================================================================

ALTER TABLE usuarios
    ADD COLUMN prefijo VARCHAR(25) NOT NULL DEFAULT 'L.E.' AFTER matricula;
