-- Migración: estado por línea en detalle_entradas (cancelar una línea dentro de una entrada)
-- Ejecutar en MySQL

USE sistema_almacen;

ALTER TABLE detalle_entradas
  ADD COLUMN estado ENUM('activa', 'cancelada') DEFAULT 'activa'
  COMMENT 'activa = cuenta en inventario; cancelada = línea anulada dentro de la entrada';
