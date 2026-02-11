-- Migración: agregar en todas las tablas hora de transacción (updated_at) y quién la hace (created_by)
-- Ejecutar en la base de datos existente. Es seguro ejecutar varias veces (comprueba si las columnas existen).

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_horas_y_usuario_safe;
DELIMITER //
CREATE PROCEDURE migrar_horas_y_usuario_safe()
BEGIN
  -- usuarios: updated_at
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'updated_at') = 0 THEN
    ALTER TABLE usuarios ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
  END IF;

  -- productos: updated_at, created_by
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'updated_at') = 0 THEN
    ALTER TABLE productos ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos' AND COLUMN_NAME = 'created_by') = 0 THEN
    ALTER TABLE productos ADD COLUMN created_by INT UNSIGNED NULL COMMENT 'Usuario que registró/creó el producto' AFTER updated_at;
    ALTER TABLE productos ADD CONSTRAINT fk_productos_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;
  END IF;

  -- entradas: updated_at, created_by
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'updated_at') = 0 THEN
    ALTER TABLE entradas ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'created_by') = 0 THEN
    ALTER TABLE entradas ADD COLUMN created_by INT UNSIGNED NULL COMMENT 'Usuario que registró la entrada' AFTER updated_at;
    ALTER TABLE entradas ADD CONSTRAINT fk_entradas_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;
  END IF;

  -- detalle_entradas: updated_at, created_by
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_entradas' AND COLUMN_NAME = 'updated_at') = 0 THEN
    ALTER TABLE detalle_entradas ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_entradas' AND COLUMN_NAME = 'created_by') = 0 THEN
    ALTER TABLE detalle_entradas ADD COLUMN created_by INT UNSIGNED NULL COMMENT 'Usuario que registró el detalle' AFTER updated_at;
    ALTER TABLE detalle_entradas ADD CONSTRAINT fk_detalle_entradas_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;
  END IF;

  -- salidas: updated_at, created_by
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'updated_at') = 0 THEN
    ALTER TABLE salidas ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'created_by') = 0 THEN
    ALTER TABLE salidas ADD COLUMN created_by INT UNSIGNED NULL COMMENT 'Usuario que registró la salida' AFTER updated_at;
    ALTER TABLE salidas ADD CONSTRAINT fk_salidas_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;
  END IF;

  -- detalle_salidas: updated_at, created_by
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_salidas' AND COLUMN_NAME = 'updated_at') = 0 THEN
    ALTER TABLE detalle_salidas ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_salidas' AND COLUMN_NAME = 'created_by') = 0 THEN
    ALTER TABLE detalle_salidas ADD COLUMN created_by INT UNSIGNED NULL COMMENT 'Usuario que registró el detalle' AFTER updated_at;
    ALTER TABLE detalle_salidas ADD CONSTRAINT fk_detalle_salidas_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL;
  END IF;
END//
DELIMITER ;

CALL migrar_horas_y_usuario_safe();
DROP PROCEDURE IF EXISTS migrar_horas_y_usuario_safe;
