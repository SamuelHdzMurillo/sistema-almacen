-- Migración: agregar columna "quien entrega" en salidas
-- Ejecutar en la base de datos existente si ya tenías salidas sin este campo.
-- Es seguro ejecutar varias veces: solo agrega la columna si no existe.

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_agregar_quien_entrega_safe;
DELIMITER //
CREATE PROCEDURE migrar_agregar_quien_entrega_safe()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_entrega') = 0 THEN
    ALTER TABLE salidas
      ADD COLUMN nombre_entrega VARCHAR(150) NULL COMMENT 'Quien entrega el material' AFTER fecha;
  END IF;
END//
DELIMITER ;

CALL migrar_agregar_quien_entrega_safe();
DROP PROCEDURE IF EXISTS migrar_agregar_quien_entrega_safe;

-- Rellenar registros antiguos con un valor por defecto para que NOT NULL no falle después
UPDATE salidas SET nombre_entrega = 'No indicado' WHERE nombre_entrega IS NULL;

ALTER TABLE salidas
  MODIFY COLUMN nombre_entrega VARCHAR(150) NOT NULL COMMENT 'Quien entrega el material';
