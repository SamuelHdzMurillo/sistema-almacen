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
             AND s.estado != 'cancelada') AS stock,
          (SELECT GROUP_CONCAT(DISTINCT prov.nombre ORDER BY prov.nombre SEPARATOR ' | ')
           FROM detalle_entradas de2
           JOIN entradas e2 ON e2.id = de2.entrada_id
           LEFT JOIN catalogo_proveedor prov ON prov.id = e2.proveedor_id
           WHERE de2.producto_id = p.id
             AND e2.almacen_id = ?
             AND e2.estado != 'cancelada'
             AND (de2.estado = 'activa' OR de2.estado IS NULL)) AS proveedores
        FROM productos p
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$almacenId, $almacenId, $almacenId]);
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
 * Historial completo de un producto en un almacén:
 *  - datos del producto y stock actual
 *  - entradas (de qué factura / proveedor llegó)
 *  - salidas (a qué plantel / receptor se fue)
 *  - resumen agrupado por plantel y por proveedor
 */
function historialProducto(int $productoId, ?int $almacenId = null): ?array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();

    $stmt = $pdo->prepare('SELECT id, codigo, nombre, descripcion, unidad FROM productos WHERE id = ? LIMIT 1');
    $stmt->execute([$productoId]);
    $producto = $stmt->fetch();
    if (!$producto) {
        return null;
    }

    // Entradas: de qué factura / proveedor llegó este producto.
    $stEnt = $pdo->prepare("
        SELECT e.id AS entrada_id, e.referencia, e.fecha, e.factura, e.factura_doc,
               prov.nombre AS proveedor_nombre, qr.nombre AS quien_recibe_nombre,
               de.cantidad
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        LEFT JOIN catalogo_proveedor prov ON prov.id = e.proveedor_id
        LEFT JOIN catalogo_quien_recibe_entrada qr ON qr.id = e.quien_recibe_id
        WHERE de.producto_id = ?
          AND e.almacen_id = ?
          AND e.estado != 'cancelada'
          AND (de.estado = 'activa' OR de.estado IS NULL)
        ORDER BY e.fecha DESC, e.id DESC
    ");
    $stEnt->execute([$productoId, $almacenId]);
    $entradas = $stEnt->fetchAll();

    // Salidas: a qué plantel / receptor se fue este producto.
    $stSal = $pdo->prepare("
        SELECT s.id AS salida_id, s.referencia, s.fecha,
               pl.nombre AS plantel_nombre, rec.nombre AS receptor_nombre, qe.nombre AS quien_entrega_nombre,
               ds.cantidad
        FROM detalle_salidas ds
        JOIN salidas s ON s.id = ds.salida_id
        LEFT JOIN catalogo_plantel pl ON pl.id = s.plantel_id
        LEFT JOIN catalogo_receptor rec ON rec.id = s.receptor_id
        LEFT JOIN catalogo_quien_entrega qe ON qe.id = s.quien_entrega_id
        WHERE ds.producto_id = ?
          AND s.almacen_id = ?
          AND s.estado != 'cancelada'
        ORDER BY s.fecha DESC, s.id DESC
    ");
    $stSal->execute([$productoId, $almacenId]);
    $salidas = $stSal->fetchAll();

    $totalEntradas = 0;
    $porProveedor = [];
    foreach ($entradas as $e) {
        $cant = (int) $e['cantidad'];
        $totalEntradas += $cant;
        $prov = $e['proveedor_nombre'] ?? '—';
        $porProveedor[$prov] = ($porProveedor[$prov] ?? 0) + $cant;
    }

    $totalSalidas = 0;
    $porPlantel = [];
    foreach ($salidas as $s) {
        $cant = (int) $s['cantidad'];
        $totalSalidas += $cant;
        $plantel = $s['plantel_nombre'] ?? '—';
        $porPlantel[$plantel] = ($porPlantel[$plantel] ?? 0) + $cant;
    }

    arsort($porPlantel);
    arsort($porProveedor);

    return [
        'producto'       => $producto,
        'entradas'       => $entradas,
        'salidas'        => $salidas,
        'total_entradas' => $totalEntradas,
        'total_salidas'  => $totalSalidas,
        'stock'          => $totalEntradas - $totalSalidas,
        'por_plantel'    => $porPlantel,
        'por_proveedor'  => $porProveedor,
    ];
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
