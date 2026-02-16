-- Migración: Catálogos para salidas (quien entrega, plantel, persona que recibe)
-- Normaliza los datos para que siempre se use el mismo nombre desde el catálogo.
-- Ejecutar en la base de datos existente. Es seguro ejecutar una vez; si ya se aplicó, no hace nada.

USE sistema_almacen;

-- Catálogo: Quien entrega el material
CREATE TABLE IF NOT EXISTS catalogo_quien_entrega (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo: Plantel al que se entrega
CREATE TABLE IF NOT EXISTS catalogo_plantel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo: Persona que recibe el material
CREATE TABLE IF NOT EXISTS catalogo_receptor (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valores iniciales por defecto
INSERT IGNORE INTO catalogo_quien_entrega (nombre) VALUES ('No indicado');
INSERT IGNORE INTO catalogo_plantel (nombre) VALUES ('No especificado');
INSERT IGNORE INTO catalogo_receptor (nombre) VALUES ('No indicado');

-- Migrar nombres existentes en salidas a los catálogos (solo si salidas tiene columnas viejas)
DROP PROCEDURE IF EXISTS migrar_catalogos_ins;
DELIMITER //
CREATE PROCEDURE migrar_catalogos_ins()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_entrega') > 0 THEN
    INSERT IGNORE INTO catalogo_quien_entrega (nombre)
      SELECT DISTINCT TRIM(nombre_entrega) FROM salidas WHERE TRIM(IFNULL(nombre_entrega,'')) <> '';
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_receptor') > 0 THEN
    INSERT IGNORE INTO catalogo_receptor (nombre)
      SELECT DISTINCT TRIM(nombre_receptor) FROM salidas WHERE TRIM(IFNULL(nombre_receptor,'')) <> '';
  END IF;
END//
DELIMITER ;
CALL migrar_catalogos_ins();
DROP PROCEDURE IF EXISTS migrar_catalogos_ins;

DROP PROCEDURE IF EXISTS migrar_catalogos_salida_safe;
DELIMITER //
CREATE PROCEDURE migrar_catalogos_salida_safe()
BEGIN
  -- Añadir columnas FK en salidas si no existen
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'quien_entrega_id') = 0 THEN
    ALTER TABLE salidas ADD COLUMN quien_entrega_id INT UNSIGNED NULL AFTER fecha;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'plantel_id') = 0 THEN
    ALTER TABLE salidas ADD COLUMN plantel_id INT UNSIGNED NULL AFTER quien_entrega_id;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'receptor_id') = 0 THEN
    ALTER TABLE salidas ADD COLUMN receptor_id INT UNSIGNED NULL AFTER plantel_id;
  END IF;
END//
DELIMITER ;

CALL migrar_catalogos_salida_safe();
DROP PROCEDURE IF EXISTS migrar_catalogos_salida_safe;

-- Rellenar quien_entrega_id y receptor_id desde columnas antiguas (solo si existen esas columnas)
DROP PROCEDURE IF EXISTS migrar_catalogos_update;
DELIMITER //
CREATE PROCEDURE migrar_catalogos_update()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_entrega') > 0 THEN
    UPDATE salidas s INNER JOIN catalogo_quien_entrega c ON c.nombre = TRIM(s.nombre_entrega) SET s.quien_entrega_id = c.id WHERE s.quien_entrega_id IS NULL AND s.nombre_entrega IS NOT NULL;
  END IF;
  UPDATE salidas s SET s.quien_entrega_id = (SELECT id FROM catalogo_quien_entrega WHERE nombre = 'No indicado' LIMIT 1) WHERE s.quien_entrega_id IS NULL;

  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_receptor') > 0 THEN
    UPDATE salidas s INNER JOIN catalogo_receptor c ON c.nombre = TRIM(s.nombre_receptor) SET s.receptor_id = c.id WHERE s.receptor_id IS NULL AND s.nombre_receptor IS NOT NULL;
  END IF;
  UPDATE salidas s SET s.receptor_id = (SELECT id FROM catalogo_receptor WHERE nombre = 'No indicado' LIMIT 1) WHERE s.receptor_id IS NULL;
  UPDATE salidas s SET s.plantel_id = (SELECT id FROM catalogo_plantel WHERE nombre = 'No especificado' LIMIT 1) WHERE s.plantel_id IS NULL;
END//
DELIMITER ;
CALL migrar_catalogos_update();
DROP PROCEDURE IF EXISTS migrar_catalogos_update;

-- Eliminar columnas antiguas (solo si existen)
DROP PROCEDURE IF EXISTS migrar_catalogos_salida_drop;
DELIMITER //
CREATE PROCEDURE migrar_catalogos_salida_drop()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_entrega') > 0 THEN
    ALTER TABLE salidas DROP COLUMN nombre_entrega;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND COLUMN_NAME = 'nombre_receptor') > 0 THEN
    ALTER TABLE salidas DROP COLUMN nombre_receptor;
  END IF;
END//
DELIMITER ;

CALL migrar_catalogos_salida_drop();
DROP PROCEDURE IF EXISTS migrar_catalogos_salida_drop;

-- Claves foráneas y NOT NULL vía procedimiento
DROP PROCEDURE IF EXISTS migrar_catalogos_salida_fk;
DELIMITER //
CREATE PROCEDURE migrar_catalogos_salida_fk()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND CONSTRAINT_NAME = 'fk_salidas_quien_entrega') = 0 THEN
    ALTER TABLE salidas ADD CONSTRAINT fk_salidas_quien_entrega FOREIGN KEY (quien_entrega_id) REFERENCES catalogo_quien_entrega(id);
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND CONSTRAINT_NAME = 'fk_salidas_plantel') = 0 THEN
    ALTER TABLE salidas ADD CONSTRAINT fk_salidas_plantel FOREIGN KEY (plantel_id) REFERENCES catalogo_plantel(id);
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'salidas' AND CONSTRAINT_NAME = 'fk_salidas_receptor') = 0 THEN
    ALTER TABLE salidas ADD CONSTRAINT fk_salidas_receptor FOREIGN KEY (receptor_id) REFERENCES catalogo_receptor(id);
  END IF;
  ALTER TABLE salidas MODIFY COLUMN quien_entrega_id INT UNSIGNED NOT NULL;
  ALTER TABLE salidas MODIFY COLUMN plantel_id INT UNSIGNED NOT NULL;
  ALTER TABLE salidas MODIFY COLUMN receptor_id INT UNSIGNED NOT NULL;
END//
DELIMITER ;

CALL migrar_catalogos_salida_fk();
DROP PROCEDURE IF EXISTS migrar_catalogos_salida_fk;
