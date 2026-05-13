-- Ejecutar en MySQL/MariaDB si la tabla personas aún no tiene tipo de documento.
-- Si la columna ya existe, omitir o comentar la siguiente línea.

ALTER TABLE personas
  ADD COLUMN codi_iden VARCHAR(20) NULL DEFAULT NULL
  COMMENT 'Código tipo documento (CC, CE, TI, PA, etc.)'
  AFTER iden_pers;
