-- Migración: productos.nombre UNIQUE
-- Ejecutar en MySQL. Es seguro ejecutar varias veces (verifica existencia del índice).

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_productos_nombre_unique_safe;
DELIMITER //
CREATE PROCEDURE migrar_productos_nombre_unique_safe()
BEGIN
  -- Si ya existe un índice/constraint único por nombre, no hace nada.
  IF (SELECT COUNT(*)
      FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'productos'
        AND INDEX_NAME = 'ux_productos_nombre') = 0
  THEN
    ALTER TABLE productos
      ADD CONSTRAINT ux_productos_nombre UNIQUE (nombre);
  END IF;
END//
DELIMITER ;

CALL migrar_productos_nombre_unique_safe();
DROP PROCEDURE IF EXISTS migrar_productos_nombre_unique_safe;

