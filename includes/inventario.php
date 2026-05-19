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

/**
 * Matriz de stock: cada producto con cantidad por almacén y total general.
 * Solo para vista administrativa global.
 */
function inventarioGlobalMatriz(): array {
    $pdo = getDB();
    $almacenes = listarAlmacenes();
    $productos = $pdo->query('SELECT id, codigo, nombre, unidad FROM productos ORDER BY nombre')->fetchAll();

    $stock = [];
    foreach ($productos as $p) {
        $pid = (int) $p['id'];
        $stock[$pid] = [];
        foreach ($almacenes as $a) {
            $stock[$pid][(int) $a['id']] = 0;
        }
    }

    $sqlEntradas = "
        SELECT de.producto_id, e.almacen_id, COALESCE(SUM(de.cantidad), 0) AS cant
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        WHERE e.estado != 'cancelada'
          AND (de.estado = 'activa' OR de.estado IS NULL)
        GROUP BY de.producto_id, e.almacen_id
    ";
    foreach ($pdo->query($sqlEntradas) as $row) {
        $pid = (int) $row['producto_id'];
        $aid = (int) $row['almacen_id'];
        if (isset($stock[$pid][$aid])) {
            $stock[$pid][$aid] += (int) $row['cant'];
        }
    }

    $sqlSalidas = "
        SELECT ds.producto_id, s.almacen_id, COALESCE(SUM(ds.cantidad), 0) AS cant
        FROM detalle_salidas ds
        JOIN salidas s ON s.id = ds.salida_id
        WHERE s.estado != 'cancelada'
        GROUP BY ds.producto_id, s.almacen_id
    ";
    foreach ($pdo->query($sqlSalidas) as $row) {
        $pid = (int) $row['producto_id'];
        $aid = (int) $row['almacen_id'];
        if (isset($stock[$pid][$aid])) {
            $stock[$pid][$aid] -= (int) $row['cant'];
        }
    }

    $totalesAlmacen = [];
    foreach ($almacenes as $a) {
        $totalesAlmacen[(int) $a['id']] = 0;
    }

    $filas = [];
    $totalGeneral = 0;
    foreach ($productos as $p) {
        $pid = (int) $p['id'];
        $porAlmacen = [];
        $totalFila = 0;
        foreach ($almacenes as $a) {
            $aid = (int) $a['id'];
            $qty = $stock[$pid][$aid] ?? 0;
            $porAlmacen[$aid] = $qty;
            $totalFila += $qty;
            $totalesAlmacen[$aid] += $qty;
        }
        $totalGeneral += $totalFila;
        $filas[] = [
            'id' => $pid,
            'codigo' => $p['codigo'],
            'nombre' => $p['nombre'],
            'unidad' => $p['unidad'] ?? 'und',
            'por_almacen' => $porAlmacen,
            'total' => $totalFila,
        ];
    }

    return [
        'almacenes' => $almacenes,
        'filas' => $filas,
        'totales_almacen' => $totalesAlmacen,
        'total_general' => $totalGeneral,
    ];
}
