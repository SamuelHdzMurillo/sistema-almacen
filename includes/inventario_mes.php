<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Lista entradas completadas de un mes/año (cabecera + detalle agrupado).
 */
function listarEntradasPorMes(int $anio, int $mes): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT e.id, e.referencia, e.fecha, e.responsable, e.estado, e.created_at,
               u.nombre AS created_by_nombre
        FROM entradas e
        LEFT JOIN usuarios u ON u.id = e.created_by
        WHERE e.estado = 'completada'
          AND YEAR(e.fecha) = ?
          AND MONTH(e.fecha) = ?
        ORDER BY e.fecha ASC, e.id ASC
    ");
    $stmt->execute([$anio, $mes]);
    $entradas = $stmt->fetchAll();
    foreach ($entradas as &$e) {
        $st = $pdo->prepare("
            SELECT de.cantidad, p.nombre AS producto_nombre, p.unidad
            FROM detalle_entradas de
            JOIN productos p ON p.id = de.producto_id
            WHERE de.entrada_id = ?
        ");
        $st->execute([$e['id']]);
        $e['detalle'] = $st->fetchAll();
    }
    return $entradas;
}

/**
 * Lista salidas completadas de un mes/año (cabecera + detalle agrupado).
 */
function listarSalidasPorMes(int $anio, int $mes): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT s.id, s.referencia, s.fecha, s.estado, s.created_at,
               qe.nombre AS nombre_entrega, pl.nombre AS plantel_nombre, rec.nombre AS nombre_receptor,
               u.nombre AS created_by_nombre
        FROM salidas s
        LEFT JOIN catalogo_quien_entrega qe ON qe.id = s.quien_entrega_id
        LEFT JOIN catalogo_plantel pl ON pl.id = s.plantel_id
        LEFT JOIN catalogo_receptor rec ON rec.id = s.receptor_id
        LEFT JOIN usuarios u ON u.id = s.created_by
        WHERE s.estado = 'completada'
          AND YEAR(s.fecha) = ?
          AND MONTH(s.fecha) = ?
        ORDER BY s.fecha ASC, s.id ASC
    ");
    $stmt->execute([$anio, $mes]);
    $salidas = $stmt->fetchAll();
    foreach ($salidas as &$s) {
        $st = $pdo->prepare("
            SELECT ds.cantidad, p.nombre AS producto_nombre, p.unidad
            FROM detalle_salidas ds
            JOIN productos p ON p.id = ds.producto_id
            WHERE ds.salida_id = ?
        ");
        $st->execute([$s['id']]);
        $s['detalle'] = $st->fetchAll();
    }
    return $salidas;
}

/**
 * Totales de unidades entradas/salidas en un mes.
 */
function resumenMes(int $anio, int $mes): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(de.cantidad), 0) AS total
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        WHERE e.estado = 'completada' AND YEAR(e.fecha) = ? AND MONTH(e.fecha) = ?
    ");
    $stmt->execute([$anio, $mes]);
    $totalEntradas = (int) $stmt->fetch()['total'];

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ds.cantidad), 0) AS total
        FROM detalle_salidas ds
        JOIN salidas s ON s.id = ds.salida_id
        WHERE s.estado = 'completada' AND YEAR(s.fecha) = ? AND MONTH(s.fecha) = ?
    ");
    $stmt->execute([$anio, $mes]);
    $totalSalidas = (int) $stmt->fetch()['total'];

    return [
        'total_entradas' => $totalEntradas,
        'total_salidas'  => $totalSalidas,
        'anio' => $anio,
        'mes'  => $mes,
    ];
}

$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
];
