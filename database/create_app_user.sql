CREATE USER IF NOT EXISTS 'enfermeria_app'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTA_CLAVE';
GRANT SELECT, INSERT, UPDATE, DELETE ON enfermeria.* TO 'enfermeria_app'@'localhost';
FLUSH PRIVILEGES;
