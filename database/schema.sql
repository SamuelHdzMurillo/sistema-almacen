-- Sistema de Almacén Cecyte 11 - Esquema de base de datos
-- Ejecutar en MySQL (XAMPP)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS sistema_almacen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_almacen;

-- Tabla: Usuarios (login)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  nombre VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla: Productos
CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  unidad VARCHAR(20) DEFAULT 'und',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla: Entradas (cabecera)
CREATE TABLE IF NOT EXISTS entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(50) NOT NULL UNIQUE,
  fecha DATE NOT NULL,
  responsable VARCHAR(150) NOT NULL,
  estado ENUM('completada','pendiente','cancelada') DEFAULT 'completada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla: Detalle de entradas (ítems por entrada)
CREATE TABLE IF NOT EXISTS detalle_entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrada_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (entrada_id) REFERENCES entradas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Tabla: Salidas (cabecera)
CREATE TABLE IF NOT EXISTS salidas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(50) NOT NULL UNIQUE,
  fecha DATE NOT NULL,
  nombre_receptor VARCHAR(150) NOT NULL,
  estado ENUM('completada','pendiente','cancelada') DEFAULT 'completada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla: Detalle de salidas (ítems por salida)
CREATE TABLE IF NOT EXISTS detalle_salidas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  salida_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (salida_id) REFERENCES salidas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
);

CREATE INDEX idx_entradas_fecha ON entradas(fecha);
CREATE INDEX idx_entradas_estado ON entradas(estado);
CREATE INDEX idx_salidas_fecha ON salidas(fecha);
CREATE INDEX idx_salidas_estado ON salidas(estado);
CREATE INDEX idx_detalle_entradas_entrada ON detalle_entradas(entrada_id);
CREATE INDEX idx_detalle_salidas_salida ON detalle_salidas(salida_id);

SET FOREIGN_KEY_CHECKS = 1;

-- Usuario inicial: usuario "admin", contraseña "password" (cambiar en producción)
INSERT INTO usuarios (usuario, clave, nombre) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');

-- Productos de ejemplo
INSERT INTO productos (codigo, nombre, descripcion, unidad) VALUES
('PROD-001', 'Tuberías de acero 6m', 'Tubería de acero 6 metros', 'und'),
('PROD-002', 'Bloques de hormigón', 'Bloques para construcción', 'und'),
('PROD-003', 'Cables eléctricos 100m', 'Rollos de cable 100m', 'rollo'),
('PROD-004', 'Bidones de pintura', 'Bidón 20L', 'und'),
('PROD-005', 'Azulejos cerámicos', 'Caja 1m²', 'caja');
