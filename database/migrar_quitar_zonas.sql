-- Migración: quitar zonas (si ya tenías el esquema anterior)
-- Seguro: solo elimina FK/columnas si existen (no falla si ya migraste o nunca tuviste zonas)

USE sistema_almacen;

DROP PROCEDURE IF EXISTS migrar_quitar_zonas_safe;
DELIMITER //
CREATE PROCEDURE migrar_quitar_zonas_safe()
BEGIN
  -- detalle_entradas: quitar FK y columna zona_id solo si existen
  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_entradas'
        AND CONSTRAINT_NAME = 'detalle_entradas_ibfk_3' AND CONSTRAINT_TYPE = 'FOREIGN KEY') > 0 THEN
    ALTER TABLE detalle_entradas DROP FOREIGN KEY detalle_entradas_ibfk_3;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_entradas' AND COLUMN_NAME = 'zona_id') > 0 THEN
    ALTER TABLE detalle_entradas DROP COLUMN zona_id;
  END IF;

  -- detalle_salidas: quitar FK y columna zona_id solo si existen
  IF (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_salidas'
        AND CONSTRAINT_NAME = 'detalle_salidas_ibfk_3' AND CONSTRAINT_TYPE = 'FOREIGN KEY') > 0 THEN
    ALTER TABLE detalle_salidas DROP FOREIGN KEY detalle_salidas_ibfk_3;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'detalle_salidas' AND COLUMN_NAME = 'zona_id') > 0 THEN
    ALTER TABLE detalle_salidas DROP COLUMN zona_id;
  END IF;
END//
DELIMITER ;

CALL migrar_quitar_zonas_safe();
DROP PROCEDURE IF EXISTS migrar_quitar_zonas_safe;

DROP TABLE IF EXISTS zonas;

-- Crear tabla usuarios si no existe (para login)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  nombre VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO usuarios (usuario, clave, nombre) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');
