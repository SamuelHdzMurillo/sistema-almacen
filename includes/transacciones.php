<?php
require_once __DIR__ . '/../config/database.php';

function listarTransaccionesRecientes(int $limite = 20, ?string $tipo = null, ?string $busqueda = null): array {
    $pdo = getDB();
    $entradas = [];
    $salidas = [];

    if ($tipo !== 'out') {
        $sql = "
            SELECT e.id, e.referencia, e.fecha, prov.nombre AS persona, e.estado,
                   'in' AS tipo, de.cantidad, p.nombre AS item_nombre,
                   e.created_at, e.created_by, u.nombre AS created_by_nombre
            FROM entradas e
            LEFT JOIN catalogo_proveedor prov ON prov.id = e.proveedor_id
            JOIN detalle_entradas de ON de.entrada_id = e.id
            JOIN productos p ON p.id = de.producto_id
            LEFT JOIN usuarios u ON u.id = e.created_by
            WHERE 1=1
        ";
        $params = [];
        if ($busqueda) {
            $sql .= " AND (e.referencia LIKE ? OR p.nombre LIKE ? OR prov.nombre LIKE ?)";
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
            SELECT s.id, s.referencia, s.fecha, rec.nombre AS persona, s.estado,
                   'out' AS tipo, ds.cantidad, p.nombre AS item_nombre,
                   s.created_at, s.created_by, u.nombre AS created_by_nombre,
                   qe.nombre AS nombre_entrega, pl.nombre AS plantel_nombre, rec.nombre AS nombre_receptor
            FROM salidas s
            LEFT JOIN catalogo_quien_entrega qe ON qe.id = s.quien_entrega_id
            LEFT JOIN catalogo_plantel pl ON pl.id = s.plantel_id
            LEFT JOIN catalogo_receptor rec ON rec.id = s.receptor_id
            JOIN detalle_salidas ds ON ds.salida_id = s.id
            JOIN productos p ON p.id = ds.producto_id
            LEFT JOIN usuarios u ON u.id = s.created_by
            WHERE 1=1
        ";
        $params = [];
        if ($busqueda) {
            $sql .= " AND (s.referencia LIKE ? OR p.nombre LIKE ? OR rec.nombre LIKE ? OR qe.nombre LIKE ? OR pl.nombre LIKE ?)";
            $q = "%{$busqueda}%";
            $params = array_merge($params, [$q, $q, $q, $q, $q]);
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

/**
 * Lista entregas (salidas con detalle) filtradas por plantel y/o receptor (persona que recibe).
 * Útil para ver qué se ha entregado a cada plantel y a cada persona.
 *
 * @param int|null $plantelId   Filtrar por ID de plantel (null = todos)
 * @param int|null $receptorId  Filtrar por ID de receptor (null = todos)
 * @param string|null $busqueda Búsqueda en referencia, producto, plantel, receptor
 * @param int $limite           Límite de filas
 * @return array
 */
function listarEntregasPorPlantelYReceptor(?int $plantelId = null, ?int $receptorId = null, ?string $busqueda = null, int $limite = 200): array {
    $pdo = getDB();
    $sql = "
        SELECT s.id AS salida_id, s.referencia, s.fecha, s.estado,
               pl.nombre AS plantel_nombre, pl.id AS plantel_id,
               rec.nombre AS receptor_nombre, rec.id AS receptor_id,
               qe.nombre AS quien_entrega_nombre,
               ds.cantidad, p.nombre AS producto_nombre, p.unidad,
               s.created_at
        FROM salidas s
        LEFT JOIN catalogo_plantel pl ON pl.id = s.plantel_id
        LEFT JOIN catalogo_receptor rec ON rec.id = s.receptor_id
        LEFT JOIN catalogo_quien_entrega qe ON qe.id = s.quien_entrega_id
        JOIN detalle_salidas ds ON ds.salida_id = s.id
        JOIN productos p ON p.id = ds.producto_id
        WHERE s.estado = 'completada'
    ";
    $params = [];
    if ($plantelId !== null && $plantelId > 0) {
        $sql .= " AND s.plantel_id = ?";
        $params[] = $plantelId;
    }
    if ($receptorId !== null && $receptorId > 0) {
        $sql .= " AND s.receptor_id = ?";
        $params[] = $receptorId;
    }
    if ($busqueda !== null && $busqueda !== '') {
        $sql .= " AND (s.referencia LIKE ? OR p.nombre LIKE ? OR pl.nombre LIKE ? OR rec.nombre LIKE ? OR qe.nombre LIKE ?)";
        $q = '%' . $busqueda . '%';
        $params = array_merge($params, [$q, $q, $q, $q, $q]);
    }
    $sql .= " ORDER BY s.fecha DESC, s.created_at DESC, ds.id DESC LIMIT " . (int) $limite;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
