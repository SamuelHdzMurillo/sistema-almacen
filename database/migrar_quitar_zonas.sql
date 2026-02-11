-- Migración: quitar zonas (si ya tenías el esquema anterior)
-- Ejecutar solo si tu base de datos tiene las tablas con zona_id

USE sistema_almacen;

-- Crear tablas de detalle nuevas sin zona_id (MySQL no permite DROP COLUMN con FK fácil en todas versiones)
-- Opción: crear tablas temporales, copiar datos, reemplazar

ALTER TABLE detalle_entradas DROP FOREIGN KEY detalle_entradas_ibfk_3;
ALTER TABLE detalle_entradas DROP COLUMN zona_id;

ALTER TABLE detalle_salidas DROP FOREIGN KEY detalle_salidas_ibfk_3;
ALTER TABLE detalle_salidas DROP COLUMN zona_id;

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
