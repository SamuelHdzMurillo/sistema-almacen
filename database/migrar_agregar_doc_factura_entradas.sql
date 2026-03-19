-- Migración: agregar columna "factura_doc" en entradas
-- Guarda la ruta relativa del archivo de factura subido (imagen o PDF).
-- Es seguro ejecutar varias veces: solo agrega la columna si no existe.

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_agregar_doc_factura_safe;
DELIMITER //
CREATE PROCEDURE migrar_agregar_doc_factura_safe()
BEGIN
  IF (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entradas'
      AND COLUMN_NAME = 'factura_doc'
  ) = 0 THEN
    ALTER TABLE entradas
      ADD COLUMN factura_doc VARCHAR(255) NULL
      COMMENT 'Ruta relativa al archivo de factura subido (imagen WebP o PDF)';
  END IF;
END//
DELIMITER ;

CALL migrar_agregar_doc_factura_safe();
DROP PROCEDURE IF EXISTS migrar_agregar_doc_factura_safe;
