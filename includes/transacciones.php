<?php
require_once __DIR__ . '/../config/database.php';

function listarTransaccionesRecientes(int $limite = 20, ?string $tipo = null, ?string $busqueda = null): array {
    $pdo = getDB();
    $entradas = [];
    $salidas = [];

    if ($tipo !== 'out') {
        $sql = "
            SELECT e.id, e.referencia, e.fecha, e.responsable AS persona, e.estado,
                   'in' AS tipo, de.cantidad, p.nombre AS item_nombre
            FROM entradas e
            JOIN detalle_entradas de ON de.entrada_id = e.id
            JOIN productos p ON p.id = de.producto_id
            WHERE 1=1
        ";
        $params = [];
        if ($busqueda) {
            $sql .= " AND (e.referencia LIKE ? OR p.nombre LIKE ? OR e.responsable LIKE ?)";
            $q = "%{$busqueda}%";
            $params = array_merge($params, [$q, $q, $q]);
        }
        $sql .= " ORDER BY e.created_at DESC, de.id DESC LIMIT " . (int)$limite;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entradas = $stmt->fetchAll();
    }

    if ($tipo !== 'in') {
        $sql = "
            SELECT s.id, s.referencia, s.fecha, s.nombre_receptor AS persona, s.estado,
                   'out' AS tipo, ds.cantidad, p.nombre AS item_nombre
            FROM salidas s
            JOIN detalle_salidas ds ON ds.salida_id = s.id
            JOIN productos p ON p.id = ds.producto_id
            WHERE 1=1
        ";
        $params = [];
        if ($busqueda) {
            $sql .= " AND (s.referencia LIKE ? OR p.nombre LIKE ? OR s.nombre_receptor LIKE ?)";
            $q = "%{$busqueda}%";
            $params = array_merge($params, [$q, $q, $q]);
        }
        $sql .= " ORDER BY s.created_at DESC, ds.id DESC LIMIT " . (int)$limite;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $salidas = $stmt->fetchAll();
    }

    $todas = [];
    foreach ($entradas as $r) {
        $r['cantidad_show'] = '+' . (int)$r['cantidad'];
        $todas[] = $r;
    }
    foreach ($salidas as $r) {
        $r['cantidad_show'] = '-' . (int)$r['cantidad'];
        $todas[] = $r;
    }
    usort($todas, function ($a, $b) {
        $da = $a['fecha'] . ' ' . ($a['id'] ?? 0);
        $db = $b['fecha'] . ' ' . ($b['id'] ?? 0);
        return strcmp($db, $da);
    });
    return array_slice($todas, 0, $limite);
}
