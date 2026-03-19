<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/almacenes.php';

function inventarioPorProducto(?int $almacenId = null): array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $sql = "
        SELECT p.id, p.codigo, p.nombre, p.unidad,
          (SELECT COALESCE(SUM(de.cantidad), 0) FROM detalle_entradas de
           JOIN entradas e ON e.id = de.entrada_id
           WHERE de.producto_id = p.id
             AND e.almacen_id = ?
             AND e.estado != 'cancelada'
             AND (de.estado = 'activa' OR de.estado IS NULL)) -
          (SELECT COALESCE(SUM(ds.cantidad), 0) FROM detalle_salidas ds
           JOIN salidas s ON s.id = ds.salida_id
           WHERE ds.producto_id = p.id
             AND s.almacen_id = ?
             AND s.estado != 'cancelada') AS stock
        FROM productos p
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$almacenId, $almacenId]);
    return $stmt->fetchAll();
}

function totalItems(?int $almacenId = null): int {
    $pdo = getDB();

    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();

    $e = $pdo->prepare("
        SELECT COALESCE(SUM(de.cantidad), 0) AS t
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        WHERE e.estado != 'cancelada'
          AND e.almacen_id = ?
          AND (de.estado = 'activa' OR de.estado IS NULL)
    ");
    $e->execute([$almacenId]);
    $eRow = $e->fetch();

    $s = $pdo->prepare("
        SELECT COALESCE(SUM(ds.cantidad), 0) AS t
        FROM detalle_salidas ds
        JOIN salidas s ON s.id = ds.salida_id
        WHERE s.estado != 'cancelada'
          AND s.almacen_id = ?
    ");
    $s->execute([$almacenId]);
    $sRow = $s->fetch();

    return (int)($eRow['t'] - $sRow['t']);
}

function capacidadTotal(?int $almacenId = null): array {
    $totalItems = totalItems($almacenId);
    $pct = 0;
    return ['capacidad' => 0, 'items' => $totalItems, 'porcentaje' => $pct];
}
