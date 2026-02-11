-- Migración: agregar columna "quien entrega" en salidas
-- Ejecutar en la base de datos existente si ya tenías salidas sin este campo

USE sistema_almacen;

-- Si la columna no existe, la agregamos (MySQL 5.7 no tiene IF NOT EXISTS para columnas)
ALTER TABLE salidas
  ADD COLUMN nombre_entrega VARCHAR(150) NULL COMMENT 'Quien entrega el material' AFTER fecha;

-- Rellenar registros antiguos con un valor por defecto para que NOT NULL no falle después
UPDATE salidas SET nombre_entrega = 'No indicado' WHERE nombre_entrega IS NULL;

ALTER TABLE salidas
  MODIFY COLUMN nombre_entrega VARCHAR(150) NOT NULL COMMENT 'Quien entrega el material';
