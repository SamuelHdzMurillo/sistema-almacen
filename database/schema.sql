-- Sistema de Almacén - Esquema de base de datos
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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla: Productos
CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  unidad VARCHAR(20) DEFAULT 'und',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró/creó el producto',
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla: Entradas (cabecera)
CREATE TABLE IF NOT EXISTS entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(50) NOT NULL UNIQUE,
  fecha DATE NOT NULL,
  responsable VARCHAR(150) NOT NULL,
  estado ENUM('completada','pendiente','cancelada') DEFAULT 'completada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró la entrada',
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla: Detalle de entradas (ítems por entrada)
CREATE TABLE IF NOT EXISTS detalle_entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrada_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró el detalle',
  FOREIGN KEY (entrada_id) REFERENCES entradas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id),
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Catálogos para salidas (datos normalizados)
CREATE TABLE IF NOT EXISTS catalogo_quien_entrega (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalogo_plantel (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalogo_receptor (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO catalogo_quien_entrega (nombre) VALUES ('No indicado');
INSERT IGNORE INTO catalogo_plantel (nombre) VALUES ('No especificado');
INSERT IGNORE INTO catalogo_receptor (nombre) VALUES ('No indicado');

-- Tabla: Salidas (cabecera)
CREATE TABLE IF NOT EXISTS salidas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(50) NOT NULL UNIQUE,
  fecha DATE NOT NULL,
  quien_entrega_id INT UNSIGNED NOT NULL COMMENT 'Catálogo: quien entrega el material',
  plantel_id INT UNSIGNED NOT NULL COMMENT 'Catálogo: plantel al que se entrega',
  receptor_id INT UNSIGNED NOT NULL COMMENT 'Catálogo: persona que recibe',
  estado ENUM('completada','pendiente','cancelada') DEFAULT 'completada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró la salida',
  FOREIGN KEY (quien_entrega_id) REFERENCES catalogo_quien_entrega(id),
  FOREIGN KEY (plantel_id) REFERENCES catalogo_plantel(id),
  FOREIGN KEY (receptor_id) REFERENCES catalogo_receptor(id),
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla: Detalle de salidas (ítems por salida)
CREATE TABLE IF NOT EXISTS detalle_salidas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  salida_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró el detalle',
  FOREIGN KEY (salida_id) REFERENCES salidas(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id),
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Índices (IF EXISTS evita error si ya existen; requiere MySQL 8.0.4+)
DROP INDEX IF EXISTS idx_entradas_fecha ON entradas;
CREATE INDEX idx_entradas_fecha ON entradas(fecha);
DROP INDEX IF EXISTS idx_entradas_estado ON entradas;
CREATE INDEX idx_entradas_estado ON entradas(estado);
DROP INDEX IF EXISTS idx_salidas_fecha ON salidas;
CREATE INDEX idx_salidas_fecha ON salidas(fecha);
DROP INDEX IF EXISTS idx_salidas_estado ON salidas;
CREATE INDEX idx_salidas_estado ON salidas(estado);
-- No crear/drop índices en entrada_id y salida_id: los exige la FK y no se pueden borrar

SET FOREIGN_KEY_CHECKS = 1;

-- Usuario inicial: usuario "admin", contraseña "password" (cambiar en producción)
INSERT INTO usuarios (usuario, clave, nombre) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');

-- Productos de ejemplo
INSERT INTO productos (codigo, nombre, descripcion, unidad) VALUES
('PROD-001', 'jabon en polvo', 'jabon en polvo para lavar la ropa', 'und');

