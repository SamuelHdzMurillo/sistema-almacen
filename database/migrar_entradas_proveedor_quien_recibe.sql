-- Migración: Entradas con Proveedor y Quien recibe (catálogos)
-- Sustituye "responsable" por proveedor_id y quien_recibe_id.
-- Ejecutar en la base de datos existente. Es seguro ejecutar una vez.

USE sistema_almacen;

-- Catálogo de proveedores (se alimenta al ir agregando entradas)
CREATE TABLE IF NOT EXISTS catalogo_proveedor (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de quien recibe en almacén (en cada entrada)
CREATE TABLE IF NOT EXISTS catalogo_quien_recibe_entrada (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO catalogo_proveedor (nombre) VALUES ('No indicado');
INSERT IGNORE INTO catalogo_quien_recibe_entrada (nombre) VALUES ('No indicado');

-- Añadir columnas a entradas si no existen
DROP PROCEDURE IF EXISTS migrar_entradas_prov_safe;
DELIMITER //
CREATE PROCEDURE migrar_entradas_prov_safe()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'proveedor_id') = 0 THEN
    ALTER TABLE entradas ADD COLUMN proveedor_id INT UNSIGNED NULL COMMENT 'Catálogo: proveedor' AFTER fecha;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'quien_recibe_id') = 0 THEN
    ALTER TABLE entradas ADD COLUMN quien_recibe_id INT UNSIGNED NULL COMMENT 'Catálogo: quien recibe en almacén' AFTER proveedor_id;
  END IF;
END//
DELIMITER ;
CALL migrar_entradas_prov_safe();
DROP PROCEDURE IF EXISTS migrar_entradas_prov_safe;

-- Migrar datos: si existe columna responsable, pasar a catálogos (en procedimiento)
DROP PROCEDURE IF EXISTS migrar_entradas_prov_datos;
DELIMITER //
CREATE PROCEDURE migrar_entradas_prov_datos()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'responsable') > 0 THEN
    INSERT IGNORE INTO catalogo_proveedor (nombre)
    SELECT DISTINCT UPPER(TRIM(responsable)) FROM entradas WHERE responsable IS NOT NULL AND TRIM(responsable) != '';

    UPDATE entradas e
    SET e.proveedor_id = (SELECT id FROM catalogo_proveedor p WHERE p.nombre = UPPER(TRIM(e.responsable)) LIMIT 1),
        e.quien_recibe_id = (SELECT id FROM catalogo_quien_recibe_entrada WHERE nombre = 'No indicado' LIMIT 1)
    WHERE e.responsable IS NOT NULL AND e.proveedor_id IS NULL;
  END IF;

  UPDATE entradas e
  SET e.proveedor_id = (SELECT id FROM catalogo_proveedor WHERE nombre = 'No indicado' LIMIT 1),
      e.quien_recibe_id = (SELECT id FROM catalogo_quien_recibe_entrada WHERE nombre = 'No indicado' LIMIT 1)
  WHERE e.proveedor_id IS NULL OR e.quien_recibe_id IS NULL;
END//
DELIMITER ;
CALL migrar_entradas_prov_datos();
DROP PROCEDURE IF EXISTS migrar_entradas_prov_datos;

-- Quitar columna responsable y poner NOT NULL + FK
DROP PROCEDURE IF EXISTS migrar_entradas_prov_fin;
DELIMITER //
CREATE PROCEDURE migrar_entradas_prov_fin()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'responsable') > 0 THEN
    ALTER TABLE entradas DROP COLUMN responsable;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'proveedor_id') > 0 THEN
    ALTER TABLE entradas MODIFY proveedor_id INT UNSIGNED NOT NULL;
    IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND CONSTRAINT_NAME = 'fk_entradas_proveedor') = 0 THEN
      ALTER TABLE entradas ADD CONSTRAINT fk_entradas_proveedor FOREIGN KEY (proveedor_id) REFERENCES catalogo_proveedor(id);
    END IF;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND COLUMN_NAME = 'quien_recibe_id') > 0 THEN
    ALTER TABLE entradas MODIFY quien_recibe_id INT UNSIGNED NOT NULL;
    IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'entradas' AND CONSTRAINT_NAME = 'fk_entradas_quien_recibe') = 0 THEN
      ALTER TABLE entradas ADD CONSTRAINT fk_entradas_quien_recibe FOREIGN KEY (quien_recibe_id) REFERENCES catalogo_quien_recibe_entrada(id);
    END IF;
  END IF;
END//
DELIMITER ;
CALL migrar_entradas_prov_fin();
DROP PROCEDURE IF EXISTS migrar_entradas_prov_fin;
