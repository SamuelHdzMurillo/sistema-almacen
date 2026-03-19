-- Migración: agregar columna "recibo_entrega_doc" en salidas
-- Guarda la ruta relativa del archivo de recibo de entrega firmado (imagen o PDF).
-- Es seguro ejecutar varias veces: solo agrega la columna si no existe.

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_agregar_recibo_salidas_safe;
DELIMITER //
CREATE PROCEDURE migrar_agregar_recibo_salidas_safe()
BEGIN
  IF (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'salidas'
      AND COLUMN_NAME = 'recibo_entrega_doc'
  ) = 0 THEN
    ALTER TABLE salidas
      ADD COLUMN recibo_entrega_doc VARCHAR(255) NULL
      COMMENT 'Ruta relativa al archivo de recibo de entrega firmado (imagen WebP o PDF)';
  END IF;
END//
DELIMITER ;

CALL migrar_agregar_recibo_salidas_safe();
DROP PROCEDURE IF EXISTS migrar_agregar_recibo_salidas_safe;
