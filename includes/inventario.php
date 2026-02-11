<?php
require_once __DIR__ . '/../config/database.php';

function inventarioPorProducto(): array {
    $pdo = getDB();
    $sql = "
        SELECT p.id, p.codigo, p.nombre, p.unidad,
          (SELECT COALESCE(SUM(de.cantidad), 0) FROM detalle_entradas de
           JOIN entradas e ON e.id = de.entrada_id AND e.estado != 'cancelada'
           WHERE de.producto_id = p.id) -
          (SELECT COALESCE(SUM(ds.cantidad), 0) FROM detalle_salidas ds
           JOIN salidas s ON s.id = ds.salida_id AND s.estado != 'cancelada'
           WHERE ds.producto_id = p.id) AS stock
        FROM productos p
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function totalItems(): int {
    $pdo = getDB();
    $e = $pdo->query("SELECT COALESCE(SUM(de.cantidad), 0) AS t FROM detalle_entradas de JOIN entradas e ON e.id = de.entrada_id AND e.estado != 'cancelada'")->fetch();
    $s = $pdo->query("SELECT COALESCE(SUM(ds.cantidad), 0) AS t FROM detalle_salidas ds JOIN salidas s ON s.id = ds.salida_id AND s.estado != 'cancelada'")->fetch();
    return (int)($e['t'] - $s['t']);
}

function capacidadTotal(): array {
    $totalItems = totalItems();
    $pct = 0;
    return ['capacidad' => 0, 'items' => $totalItems, 'porcentaje' => $pct];
}
