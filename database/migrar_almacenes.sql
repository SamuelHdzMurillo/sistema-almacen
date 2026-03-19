-- Migración: multi-almacén
-- Crea `almacenes` y agrega `almacen_id` a `usuarios`, `entradas` y `salidas`.
-- Ejecutar en la base de datos existente. Es seguro ejecutar varias veces.

USE sistema_almacen;

-- Tabla de almacenes
CREATE TABLE IF NOT EXISTS almacenes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Almacén por defecto para migrar datos previos
INSERT IGNORE INTO almacenes (nombre) VALUES ('Principal');

-- Agregar columnas si no existen
DROP PROCEDURE IF EXISTS migrar_almacenes_safe;
DELIMITER //
CREATE PROCEDURE migrar_almacenes_safe()
BEGIN
  -- usuarios: almacen_id
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'almacen_id') = 0 THEN
    ALTER TABLE usuarios
      ADD COLUMN almacen_id INT UNSIGNED NULL COMMENT 'Almacén al que pertenece el usuario' AFTER nombre;
  END IF;

  -- entradas: almacen_id
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'almacen_id') = 0 THEN
    ALTER TABLE entradas
      ADD COLUMN almacen_id INT UNSIGNED NULL COMMENT 'Almacén del movimiento (entrada)' AFTER quien_recibe_id;
  END IF;

  -- salidas: almacen_id
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'almacen_id') = 0 THEN
    ALTER TABLE salidas
      ADD COLUMN almacen_id INT UNSIGNED NULL COMMENT 'Almacén del movimiento (salida)' AFTER receptor_id;
  END IF;
END//
DELIMITER ;

CALL migrar_almacenes_safe();
DROP PROCEDURE IF EXISTS migrar_almacenes_safe;

-- Migrar datos existentes a 'Principal'
UPDATE usuarios
SET almacen_id = (SELECT id FROM almacenes WHERE nombre = 'Principal' LIMIT 1)
WHERE almacen_id IS NULL;

UPDATE entradas
SET almacen_id = (SELECT id FROM almacenes WHERE nombre = 'Principal' LIMIT 1)
WHERE almacen_id IS NULL;

UPDATE salidas
SET almacen_id = (SELECT id FROM almacenes WHERE nombre = 'Principal' LIMIT 1)
WHERE almacen_id IS NULL;

-- Índices para acelerar filtros por almacén
DROP INDEX IF EXISTS idx_usuarios_almacen_id ON usuarios;
CREATE INDEX idx_usuarios_almacen_id ON usuarios(almacen_id);

DROP INDEX IF EXISTS idx_entradas_almacen_id ON entradas;
CREATE INDEX idx_entradas_almacen_id ON entradas(almacen_id);

DROP INDEX IF EXISTS idx_salidas_almacen_id ON salidas;
CREATE INDEX idx_salidas_almacen_id ON salidas(almacen_id);

-- FKs (si ya existen con otro nombre, esto podría duplicar; por eso solo lo hacemos si el nombre no existe)
DROP PROCEDURE IF EXISTS migrar_almacenes_fk_safe;
DELIMITER //
CREATE PROCEDURE migrar_almacenes_fk_safe()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND CONSTRAINT_NAME = 'fk_usuarios_almacen_id') = 0 THEN
    ALTER TABLE usuarios
      ADD CONSTRAINT fk_usuarios_almacen_id
      FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL;
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND CONSTRAINT_NAME = 'fk_entradas_almacen_id') = 0 THEN
    ALTER TABLE entradas
      ADD CONSTRAINT fk_entradas_almacen_id
      FOREIGN KEY (almacen_id) REFERENCES almacenes(id);
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND CONSTRAINT_NAME = 'fk_salidas_almacen_id') = 0 THEN
    ALTER TABLE salidas
      ADD CONSTRAINT fk_salidas_almacen_id
      FOREIGN KEY (almacen_id) REFERENCES almacenes(id);
  END IF;
END//
DELIMITER ;

CALL migrar_almacenes_fk_safe();
DROP PROCEDURE IF EXISTS migrar_almacenes_fk_safe;

