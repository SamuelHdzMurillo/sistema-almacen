-- Migración: agregar columna "factura" en entradas
-- Ejecutar en la base de datos existente.
-- Es seguro ejecutar varias veces: solo agrega la columna si no existe.

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_agregar_factura_entradas_safe;
DELIMITER //
CREATE PROCEDURE migrar_agregar_factura_entradas_safe()
BEGIN
  IF (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'entradas'
      AND COLUMN_NAME = 'factura'
  ) = 0 THEN
    ALTER TABLE entradas
      ADD COLUMN factura VARCHAR(50) NULL
      COMMENT 'Folio de la factura / orden (proveedor)';
  END IF;
END//
DELIMITER ;

CALL migrar_agregar_factura_entradas_safe();
DROP PROCEDURE IF EXISTS migrar_agregar_factura_entradas_safe;

