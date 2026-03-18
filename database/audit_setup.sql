-- Sistema de Almacén - Auditoría (antes/después + request_id/usuario)
-- Ejecutar en MySQL para habilitar triggers de auditoría.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS sistema_almacen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_almacen;

-- Tabla de auditoría (log de cambios)
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
      'fecha', NEW.fecha,
      'proveedor_id', NEW.proveedor_id,
      'quien_recibe_id', NEW.quien_recibe_id,
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
      'fecha', OLD.fecha,
      'proveedor_id', OLD.proveedor_id,
      'quien_recibe_id', OLD.quien_recibe_id,
      'estado', OLD.estado,
      'created_at', OLD.created_at,
      'updated_at', OLD.updated_at,
      'created_by', OLD.created_by
    ),
    JSON_OBJECT(
      'id', NEW.id,
      'referencia', NEW.referencia,
      'fecha', NEW.fecha,
      'proveedor_id', NEW.proveedor_id,
      'quien_recibe_id', NEW.quien_recibe_id,
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
      'fecha', OLD.fecha,
      'proveedor_id', OLD.proveedor_id,
      'quien_recibe_id', OLD.quien_recibe_id,
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
-- Catálogos: entradas/salidas
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

