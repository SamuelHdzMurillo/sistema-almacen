-- Sistema de Almacén - Instalación completa (un solo archivo)
--
-- Instalación nueva: ejecuta TODO este archivo una vez en phpMyAdmin o:
--   mysql -u root < database/schema.sql
--
-- Incluye tablas, catálogos, datos iniciales, auditoría (db_audit + triggers)
-- y transaccion_modificaciones. No hace falta ejecutar migrar_*.sql en BD nueva.
--
-- Si ya tenías una base antigua, usa los scripts en database/migrar_*.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS sistema_almacen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_almacen;

-- Tabla: Almacenes
CREATE TABLE IF NOT EXISTS almacenes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO almacenes (nombre) VALUES ('Principal');

-- Tabla: Usuarios (login)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  nombre VARCHAR(150),
  almacen_id INT UNSIGNED NULL COMMENT 'Almacén al que pertenece el usuario',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL
);

-- Tabla: Productos
CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  descripcion TEXT,
  unidad VARCHAR(20) DEFAULT 'und',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró/creó el producto',
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Catálogos para entradas
CREATE TABLE IF NOT EXISTS catalogo_proveedor (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalogo_quien_recibe_entrada (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL UNIQUE,
  activo TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO catalogo_proveedor (nombre) VALUES ('No indicado');
INSERT IGNORE INTO catalogo_quien_recibe_entrada (nombre) VALUES ('No indicado');

-- Tabla: Entradas (cabecera)
CREATE TABLE IF NOT EXISTS entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  referencia VARCHAR(50) NOT NULL UNIQUE,
  factura VARCHAR(50) NULL COMMENT 'Folio de la factura / orden (proveedor)',
  factura_doc VARCHAR(255) NULL COMMENT 'Ruta relativa al archivo de factura (imagen WebP o PDF)',
  fecha DATE NOT NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  quien_recibe_id INT UNSIGNED NOT NULL,
  almacen_id INT UNSIGNED NOT NULL,
  estado ENUM('completada','pendiente','cancelada') DEFAULT 'completada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró la entrada',
  FOREIGN KEY (proveedor_id) REFERENCES catalogo_proveedor(id),
  FOREIGN KEY (quien_recibe_id) REFERENCES catalogo_quien_recibe_entrada(id),
  FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
  FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla: Detalle de entradas (ítems por entrada)
CREATE TABLE IF NOT EXISTS detalle_entradas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entrada_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  estado ENUM('activa', 'cancelada') DEFAULT 'activa'
    COMMENT 'activa = cuenta en inventario; cancelada = línea anulada',
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
  almacen_id INT UNSIGNED NOT NULL,
  recibo_entrega_doc VARCHAR(255) NULL COMMENT 'Ruta relativa al recibo de entrega firmado (imagen WebP o PDF)',
  estado ENUM('completada','pendiente','cancelada') DEFAULT 'completada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT UNSIGNED NULL COMMENT 'Usuario que registró la salida',
  FOREIGN KEY (quien_entrega_id) REFERENCES catalogo_quien_entrega(id),
  FOREIGN KEY (plantel_id) REFERENCES catalogo_plantel(id),
  FOREIGN KEY (receptor_id) REFERENCES catalogo_receptor(id),
  FOREIGN KEY (almacen_id) REFERENCES almacenes(id),
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

-- Historial de ediciones en entradas/salidas (razón y diff JSON)
CREATE TABLE IF NOT EXISTS transaccion_modificaciones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('entrada','salida') NOT NULL,
  transaccion_id INT UNSIGNED NOT NULL,
  razon TEXT NOT NULL,
  cambios_json JSON NULL,
  request_id VARCHAR(64) NULL,
  usuario_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tipo_transaccion (tipo, transaccion_id),
  INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices (IF EXISTS evita error si ya existen; requiere MySQL 8.0.4+)
DROP INDEX IF EXISTS idx_entradas_fecha ON entradas;
CREATE INDEX idx_entradas_fecha ON entradas(fecha);
DROP INDEX IF EXISTS idx_entradas_estado ON entradas;
CREATE INDEX idx_entradas_estado ON entradas(estado);
DROP INDEX IF EXISTS idx_salidas_fecha ON salidas;
CREATE INDEX idx_salidas_fecha ON salidas(fecha);
DROP INDEX IF EXISTS idx_salidas_estado ON salidas;
CREATE INDEX idx_salidas_estado ON salidas(estado);
DROP INDEX IF EXISTS idx_entradas_almacen_id ON entradas;
CREATE INDEX idx_entradas_almacen_id ON entradas(almacen_id);
DROP INDEX IF EXISTS idx_salidas_almacen_id ON salidas;
CREATE INDEX idx_salidas_almacen_id ON salidas(almacen_id);
-- No crear/drop índices en entrada_id y salida_id: los exige la FK y no se pueden borrar

SET FOREIGN_KEY_CHECKS = 1;

-- Usuario inicial: usuario "admin", contraseña "password" (cambiar en producción)
INSERT IGNORE INTO usuarios (usuario, clave, nombre) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador');

-- Producto de ejemplo (opcional)
INSERT IGNORE INTO productos (codigo, nombre, descripcion, unidad) VALUES
('PROD-001', 'jabon en polvo', 'jabon en polvo para lavar la ropa', 'und');

-- ============================================================
-- Auditoría (db_audit + triggers)
-- ============================================================
CREATE TABLE IF NOT EXISTS db_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(64) NULL,
  usuario_id INT UNSIGNED NULL,
  accion ENUM('INSERT','UPDATE','DELETE') NOT NULL,
  tabla VARCHAR(64) NOT NULL,
  pk JSON NULL,
  old_data JSON NULL,
  new_data JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_request_id (request_id),
  INDEX idx_usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================
-- productos
-- ============================================================
DROP TRIGGER IF EXISTS audit_productos_ai;
DROP TRIGGER IF EXISTS audit_productos_au;
DROP TRIGGER IF EXISTS audit_productos_ad;

DELIMITER //
CREATE TRIGGER audit_productos_ai
AFTER INSERT ON productos
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'productos',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'codigo', NEW.codigo,
      'nombre', NEW.nombre,
      'descripcion', NEW.descripcion,
      'unidad', NEW.unidad,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_productos_au
AFTER UPDATE ON productos
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'productos',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'codigo', OLD.codigo,
      'nombre', OLD.nombre,
      'descripcion', OLD.descripcion,
      'unidad', OLD.unidad,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'codigo', NEW.codigo,
      'nombre', NEW.nombre,
      'descripcion', NEW.descripcion,
      'unidad', NEW.unidad,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_productos_ad
AFTER DELETE ON productos
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'productos',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'codigo', OLD.codigo,
      'nombre', OLD.nombre,
      'descripcion', OLD.descripcion,
      'unidad', OLD.unidad,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    NULL
  );
END//
DELIMITER ;

-- ============================================================
-- entradas (cabecera)
-- ============================================================
DROP TRIGGER IF EXISTS audit_entradas_ai;
DROP TRIGGER IF EXISTS audit_entradas_au;
DROP TRIGGER IF EXISTS audit_entradas_ad;

DELIMITER //
CREATE TRIGGER audit_entradas_ai
AFTER INSERT ON entradas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'entradas',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'referencia', NEW.referencia,
      'factura', NEW.factura,
      'factura_doc', NEW.factura_doc,
      'fecha', NEW.fecha,
      'proveedor_id', NEW.proveedor_id,
      'quien_recibe_id', NEW.quien_recibe_id,
      'almacen_id', NEW.almacen_id,
      'estado', NEW.estado,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_entradas_au
AFTER UPDATE ON entradas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'entradas',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'referencia', OLD.referencia,
      'factura', OLD.factura,
      'factura_doc', OLD.factura_doc,
      'fecha', OLD.fecha,
      'proveedor_id', OLD.proveedor_id,
      'quien_recibe_id', OLD.quien_recibe_id,
      'almacen_id', OLD.almacen_id,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'referencia', NEW.referencia,
      'factura', NEW.factura,
      'factura_doc', NEW.factura_doc,
      'fecha', NEW.fecha,
      'proveedor_id', NEW.proveedor_id,
      'quien_recibe_id', NEW.quien_recibe_id,
      'almacen_id', NEW.almacen_id,
      'estado', NEW.estado,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_entradas_ad
AFTER DELETE ON entradas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'entradas',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'referencia', OLD.referencia,
      'factura', OLD.factura,
      'factura_doc', OLD.factura_doc,
      'fecha', OLD.fecha,
      'proveedor_id', OLD.proveedor_id,
      'quien_recibe_id', OLD.quien_recibe_id,
      'almacen_id', OLD.almacen_id,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    NULL
  );
END//
DELIMITER ;

-- ============================================================
-- detalle_entradas
-- ============================================================
DROP TRIGGER IF EXISTS audit_detalle_entradas_ai;
DROP TRIGGER IF EXISTS audit_detalle_entradas_au;
DROP TRIGGER IF EXISTS audit_detalle_entradas_ad;

DELIMITER //
CREATE TRIGGER audit_detalle_entradas_ai
AFTER INSERT ON detalle_entradas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'detalle_entradas',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'entrada_id', NEW.entrada_id,
      'producto_id', NEW.producto_id,
      'cantidad', NEW.cantidad,
      'estado', NEW.estado,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_detalle_entradas_au
AFTER UPDATE ON detalle_entradas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'detalle_entradas',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'entrada_id', OLD.entrada_id,
      'producto_id', OLD.producto_id,
      'cantidad', OLD.cantidad,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'entrada_id', NEW.entrada_id,
      'producto_id', NEW.producto_id,
      'cantidad', NEW.cantidad,
      'estado', NEW.estado,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_detalle_entradas_ad
AFTER DELETE ON detalle_entradas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'detalle_entradas',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'entrada_id', OLD.entrada_id,
      'producto_id', OLD.producto_id,
      'cantidad', OLD.cantidad,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    NULL
  );
END//
DELIMITER ;

-- ============================================================
-- salidas (cabecera)
-- ============================================================
DROP TRIGGER IF EXISTS audit_salidas_ai;
DROP TRIGGER IF EXISTS audit_salidas_au;
DROP TRIGGER IF EXISTS audit_salidas_ad;

DELIMITER //
CREATE TRIGGER audit_salidas_ai
AFTER INSERT ON salidas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'salidas',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'referencia', NEW.referencia,
      'fecha', NEW.fecha,
      'quien_entrega_id', NEW.quien_entrega_id,
      'plantel_id', NEW.plantel_id,
      'receptor_id', NEW.receptor_id,
      'almacen_id', NEW.almacen_id,
      'recibo_entrega_doc', NEW.recibo_entrega_doc,
      'estado', NEW.estado,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_salidas_au
AFTER UPDATE ON salidas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'salidas',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'referencia', OLD.referencia,
      'fecha', OLD.fecha,
      'quien_entrega_id', OLD.quien_entrega_id,
      'plantel_id', OLD.plantel_id,
      'receptor_id', OLD.receptor_id,
      'almacen_id', OLD.almacen_id,
      'recibo_entrega_doc', OLD.recibo_entrega_doc,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'referencia', NEW.referencia,
      'fecha', NEW.fecha,
      'quien_entrega_id', NEW.quien_entrega_id,
      'plantel_id', NEW.plantel_id,
      'receptor_id', NEW.receptor_id,
      'almacen_id', NEW.almacen_id,
      'recibo_entrega_doc', NEW.recibo_entrega_doc,
      'estado', NEW.estado,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_salidas_ad
AFTER DELETE ON salidas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'salidas',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'referencia', OLD.referencia,
      'fecha', OLD.fecha,
      'quien_entrega_id', OLD.quien_entrega_id,
      'plantel_id', OLD.plantel_id,
      'receptor_id', OLD.receptor_id,
      'almacen_id', OLD.almacen_id,
      'recibo_entrega_doc', OLD.recibo_entrega_doc,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    NULL
  );
END//
DELIMITER ;

-- ============================================================
-- detalle_salidas
-- ============================================================
DROP TRIGGER IF EXISTS audit_detalle_salidas_ai;
DROP TRIGGER IF EXISTS audit_detalle_salidas_au;
DROP TRIGGER IF EXISTS audit_detalle_salidas_ad;

DELIMITER //
CREATE TRIGGER audit_detalle_salidas_ai
AFTER INSERT ON detalle_salidas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'detalle_salidas',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'salida_id', NEW.salida_id,
      'producto_id', NEW.producto_id,
      'cantidad', NEW.cantidad,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_detalle_salidas_au
AFTER UPDATE ON detalle_salidas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'detalle_salidas',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'salida_id', OLD.salida_id,
      'producto_id', OLD.producto_id,
      'cantidad', OLD.cantidad,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'salida_id', NEW.salida_id,
      'producto_id', NEW.producto_id,
      'cantidad', NEW.cantidad,
      'created_at', NEW.created_at,
      'updated_at', NEW.updated_at,
      'created_by', NEW.created_by
    )
  );
END//

CREATE TRIGGER audit_detalle_salidas_ad
AFTER DELETE ON detalle_salidas
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'detalle_salidas',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'salida_id', OLD.salida_id,
      'producto_id', OLD.producto_id,
      'cantidad', OLD.cantidad,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    NULL
  );
END//
DELIMITER ;

-- ============================================================
-- CatÃ¡logos: entradas/salidas
-- ============================================================
-- catalogo_proveedor
DROP TRIGGER IF EXISTS audit_catalogo_proveedor_ai;
DROP TRIGGER IF EXISTS audit_catalogo_proveedor_au;
DROP TRIGGER IF EXISTS audit_catalogo_proveedor_ad;

DELIMITER //
CREATE TRIGGER audit_catalogo_proveedor_ai
AFTER INSERT ON catalogo_proveedor
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'catalogo_proveedor',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_proveedor_au
AFTER UPDATE ON catalogo_proveedor
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'catalogo_proveedor',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_proveedor_ad
AFTER DELETE ON catalogo_proveedor
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'catalogo_proveedor',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    NULL
  );
END//
DELIMITER ;

-- catalogo_quien_recibe_entrada
DROP TRIGGER IF EXISTS audit_catalogo_quien_recibe_entrada_ai;
DROP TRIGGER IF EXISTS audit_catalogo_quien_recibe_entrada_au;
DROP TRIGGER IF EXISTS audit_catalogo_quien_recibe_entrada_ad;

DELIMITER //
CREATE TRIGGER audit_catalogo_quien_recibe_entrada_ai
AFTER INSERT ON catalogo_quien_recibe_entrada
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'catalogo_quien_recibe_entrada',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_quien_recibe_entrada_au
AFTER UPDATE ON catalogo_quien_recibe_entrada
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'catalogo_quien_recibe_entrada',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_quien_recibe_entrada_ad
AFTER DELETE ON catalogo_quien_recibe_entrada
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'catalogo_quien_recibe_entrada',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    NULL
  );
END//
DELIMITER ;

-- catalogo_quien_entrega
DROP TRIGGER IF EXISTS audit_catalogo_quien_entrega_ai;
DROP TRIGGER IF EXISTS audit_catalogo_quien_entrega_au;
DROP TRIGGER IF EXISTS audit_catalogo_quien_entrega_ad;

DELIMITER //
CREATE TRIGGER audit_catalogo_quien_entrega_ai
AFTER INSERT ON catalogo_quien_entrega
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'catalogo_quien_entrega',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_quien_entrega_au
AFTER UPDATE ON catalogo_quien_entrega
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'catalogo_quien_entrega',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_quien_entrega_ad
AFTER DELETE ON catalogo_quien_entrega
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'catalogo_quien_entrega',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    NULL
  );
END//
DELIMITER ;

-- catalogo_plantel
DROP TRIGGER IF EXISTS audit_catalogo_plantel_ai;
DROP TRIGGER IF EXISTS audit_catalogo_plantel_au;
DROP TRIGGER IF EXISTS audit_catalogo_plantel_ad;

DELIMITER //
CREATE TRIGGER audit_catalogo_plantel_ai
AFTER INSERT ON catalogo_plantel
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'catalogo_plantel',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_plantel_au
AFTER UPDATE ON catalogo_plantel
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'catalogo_plantel',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_plantel_ad
AFTER DELETE ON catalogo_plantel
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'catalogo_plantel',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    NULL
  );
END//
DELIMITER ;

-- catalogo_receptor
DROP TRIGGER IF EXISTS audit_catalogo_receptor_ai;
DROP TRIGGER IF EXISTS audit_catalogo_receptor_au;
DROP TRIGGER IF EXISTS audit_catalogo_receptor_ad;

DELIMITER //
CREATE TRIGGER audit_catalogo_receptor_ai
AFTER INSERT ON catalogo_receptor
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'INSERT',
    'catalogo_receptor',
    JSON_OBJECT('id', NEW.id),
    NULL,
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_receptor_au
AFTER UPDATE ON catalogo_receptor
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'UPDATE',
    'catalogo_receptor',
    JSON_OBJECT('id', NEW.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'nombre', NEW.nombre,
      'activo', NEW.activo,
      'created_at', NEW.created_at
    )
  );
END//

CREATE TRIGGER audit_catalogo_receptor_ad
AFTER DELETE ON catalogo_receptor
FOR EACH ROW
BEGIN
  INSERT INTO db_audit (request_id, usuario_id, accion, tabla, pk, old_data, new_data)
  VALUES (
    @app_request_id,
    @app_user_id,
    'DELETE',
    'catalogo_receptor',
    JSON_OBJECT('id', OLD.id),
    JSON_OBJECT(
      'id', OLD.id,
      'nombre', OLD.nombre,
      'activo', OLD.activo,
      'created_at', OLD.created_at
    ),
    NULL
  );
END//
DELIMITER ;

