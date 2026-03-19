<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/inventario.php';
require_once __DIR__ . '/modificaciones.php';
require_once __DIR__ . '/almacenes.php';

function generarReferenciaSalida(): string {
    $pdo = getDB();
    $y = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM salidas WHERE referencia LIKE ?");
    $stmt->execute(["PS-{$y}-%"]);
    $n = (int) $stmt->fetch()['n'] + 1;
    return sprintf('PS-%s-%03d', $y, $n);
}

function listarSalidas(int $limite = 50, ?int $almacenId = null): array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        SELECT s.id, s.referencia, s.fecha, s.estado, s.created_at, s.updated_at, s.created_by,
               qe.nombre AS nombre_entrega, pl.nombre AS plantel_nombre, rec.nombre AS nombre_receptor,
               u.nombre AS created_by_nombre
        FROM salidas s
        LEFT JOIN catalogo_quien_entrega qe ON qe.id = s.quien_entrega_id
        LEFT JOIN catalogo_plantel pl ON pl.id = s.plantel_id
        LEFT JOIN catalogo_receptor rec ON rec.id = s.receptor_id
        LEFT JOIN usuarios u ON u.id = s.created_by
        WHERE s.almacen_id = ?
        ORDER BY s.created_at DESC LIMIT ?
    ');
    $stmt->execute([$almacenId, $limite]);
    return $stmt->fetchAll();
}

function obtenerSalidaConDetalle(int $id, ?int $almacenId = null): ?array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        SELECT s.id, s.referencia, s.fecha, s.estado, s.created_at, s.updated_at, s.created_by,
               s.quien_entrega_id, s.plantel_id, s.receptor_id,
               qe.nombre AS nombre_entrega, pl.nombre AS plantel_nombre, rec.nombre AS nombre_receptor,
               u.nombre AS created_by_nombre
        FROM salidas s
        LEFT JOIN catalogo_quien_entrega qe ON qe.id = s.quien_entrega_id
        LEFT JOIN catalogo_plantel pl ON pl.id = s.plantel_id
        LEFT JOIN catalogo_receptor rec ON rec.id = s.receptor_id
        LEFT JOIN usuarios u ON u.id = s.created_by
        WHERE s.id = ? AND s.almacen_id = ?
    ');
    $stmt->execute([$id, $almacenId]);
    $s = $stmt->fetch();
    if (!$s) return null;
    $stmt2 = $pdo->prepare('
        SELECT ds.id, ds.producto_id, ds.cantidad, p.nombre AS producto_nombre, p.unidad
        FROM detalle_salidas ds
        JOIN productos p ON p.id = ds.producto_id
        WHERE ds.salida_id = ?
        ORDER BY ds.id
    ');
    $stmt2->execute([$id]);
    $s['detalle'] = $stmt2->fetchAll();
    return $s;
}

function crearSalida(
    string $fecha,
    int $quienEntregaId,
    int $plantelId,
    int $receptorId,
    array $lineas,
    ?int $usuarioId = null,
    ?int $almacenId = null
): int {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $inventario = inventarioPorProducto($almacenId);
    $stockPorId = [];
    $nombrePorId = [];
    foreach ($inventario as $inv) {
        $id = (int) $inv['id'];
        $stockPorId[$id] = (int) $inv['stock'];
        $nombrePorId[$id] = $inv['nombre'];
    }
    $solicitadoPorId = [];
    foreach ($lineas as $l) {
        if (empty($l['producto_id']) || (int)($l['cantidad'] ?? 0) <= 0) continue;
        $pid = (int) $l['producto_id'];
        $solicitadoPorId[$pid] = ($solicitadoPorId[$pid] ?? 0) + (int) $l['cantidad'];
    }
    foreach ($solicitadoPorId as $pid => $totalSolicitado) {
        $stock = $stockPorId[$pid] ?? 0;
        if ($totalSolicitado > $stock) {
            $nombre = $nombrePorId[$pid] ?? 'ID ' . $pid;
            throw new Exception("No hay stock suficiente para \"{$nombre}\": solicitado {$totalSolicitado}, disponible {$stock}.");
        }
    }
    $ref = generarReferenciaSalida();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO salidas (referencia, fecha, quien_entrega_id, plantel_id, receptor_id, almacen_id, estado, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$ref, $fecha, $quienEntregaId, $plantelId, $receptorId, $almacenId, 'completada', $usuarioId]);
        $salidaId = (int) $pdo->lastInsertId();
        $stmt2 = $pdo->prepare('INSERT INTO detalle_salidas (salida_id, producto_id, cantidad, created_by) VALUES (?, ?, ?, ?)');
        foreach ($lineas as $l) {
            if (empty($l['producto_id']) || (int)($l['cantidad'] ?? 0) <= 0) continue;
            $stmt2->execute([$salidaId, $l['producto_id'], (int)$l['cantidad'], $usuarioId]);
        }
        $pdo->commit();
        return $salidaId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function totalSalidasEsteMes(?int $almacenId = null): int {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ds.cantidad), 0) AS t
        FROM detalle_salidas ds
        JOIN salidas s ON s.id = ds.salida_id
        WHERE s.estado = 'completada'
          AND s.almacen_id = ?
          AND YEAR(s.fecha) = YEAR(CURDATE())
          AND MONTH(s.fecha) = MONTH(CURDATE())
    ");
    $stmt->execute([$almacenId]);
    return (int) $stmt->fetch()['t'];
}

/** Marca una salida como cancelada. El stock volverá a contar esos ítems. */
function cancelarSalida(int $id, ?int $almacenId = null): bool {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        UPDATE salidas
        SET estado = ?
        WHERE id = ? AND estado = ? AND almacen_id = ?
    ');
    $stmt->execute(['cancelada', $id, 'completada', $almacenId]);
    return $stmt->rowCount() > 0;
}

function actualizarSalida(
    int $salidaId,
    string $fecha,
    int $quienEntregaId,
    int $plantelId,
    int $receptorId,
    array $lineas,
    ?int $usuarioId,
    string $razon,
    ?int $almacenId = null
): int {
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $transaccionVieja = obtenerSalidaConDetalle($salidaId, $almacenId);
    if (!$transaccionVieja) {
        throw new Exception('Salida no encontrada.');
    }
    if (($transaccionVieja['estado'] ?? 'completada') === 'cancelada') {
        throw new Exception('No se puede editar una salida cancelada.');
    }

    $oldFecha = (string)($transaccionVieja['fecha'] ?? '');
    $oldQuienEntregaId = (int)($transaccionVieja['quien_entrega_id'] ?? 0);
    $oldPlantelId = (int)($transaccionVieja['plantel_id'] ?? 0);
    $oldReceptorId = (int)($transaccionVieja['receptor_id'] ?? 0);

    $oldTotalesPorProducto = [];
    foreach (($transaccionVieja['detalle'] ?? []) as $d) {
        $pid = (int)($d['producto_id'] ?? 0);
        $qty = (int)($d['cantidad'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $oldTotalesPorProducto[$pid] = ($oldTotalesPorProducto[$pid] ?? 0) + $qty;
    }

    $newTotalesPorProducto = [];
    foreach ($lineas as $l) {
        $pid = (int)($l['producto_id'] ?? 0);
        $qty = (int)($l['cantidad'] ?? 0);
        if ($pid <= 0 || $qty <= 0) continue;
        $newTotalesPorProducto[$pid] = ($newTotalesPorProducto[$pid] ?? 0) + $qty;
    }

    // Validación de stock por producto (no dejar negativo).
    $inventario = inventarioPorProducto($almacenId);
    $stockPorId = [];
    foreach ($inventario as $inv) {
        $stockPorId[(int)$inv['id']] = (int)($inv['stock'] ?? 0);
    }
    $ids = array_unique(array_merge(array_keys($oldTotalesPorProducto), array_keys($newTotalesPorProducto)));
    foreach ($ids as $pid) {
        $pid = (int)$pid;
        $stockAntes = $stockPorId[$pid] ?? 0;
        $oldQty = (int)($oldTotalesPorProducto[$pid] ?? 0);
        $newQty = (int)($newTotalesPorProducto[$pid] ?? 0);
        $stockDespues = $stockAntes - $oldQty + $newQty;
        if ($stockDespues < 0) {
            throw new Exception('No hay stock suficiente para realizar estos cambios.');
        }
    }

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        $check = $pdo->prepare('
            SELECT id FROM salidas WHERE id = ? AND almacen_id = ? AND estado != ? LIMIT 1
        ');
        $check->execute([$salidaId, $almacenId, 'cancelada']);
        if (!$check->fetch()) {
            throw new Exception('No se puede editar esta salida (no encontrada o cancelada).');
        }

        $stmt = $pdo->prepare('
            UPDATE salidas
            SET fecha = ?, quien_entrega_id = ?, plantel_id = ?, receptor_id = ?
            WHERE id = ? AND almacen_id = ? AND estado != ?
        ');
        $stmt->execute([$fecha, $quienEntregaId, $plantelId, $receptorId, $salidaId, $almacenId, 'cancelada']);

        $stmtDel = $pdo->prepare('DELETE FROM detalle_salidas WHERE salida_id = ?');
        $stmtDel->execute([$salidaId]);

        $stmt2 = $pdo->prepare('INSERT INTO detalle_salidas (salida_id, producto_id, cantidad, created_by) VALUES (?, ?, ?, ?)');
        foreach ($lineas as $l) {
            $pid = (int)($l['producto_id'] ?? 0);
            $qty = (int)($l['cantidad'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $stmt2->execute([$salidaId, $pid, $qty, $usuarioId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    $cambios = [
        'header' => [],
        'detalle' => [
            'items' => [],
        ],
    ];

    if ($oldFecha !== (string)$fecha) {
        $cambios['header']['fecha'] = ['old' => $oldFecha, 'new' => (string)$fecha];
    }
    if ($oldQuienEntregaId !== $quienEntregaId) {
        $cambios['header']['quien_entrega_id'] = ['old' => $oldQuienEntregaId, 'new' => $quienEntregaId];
    }
    if ($oldPlantelId !== $plantelId) {
        $cambios['header']['plantel_id'] = ['old' => $oldPlantelId, 'new' => $plantelId];
    }
    if ($oldReceptorId !== $receptorId) {
        $cambios['header']['receptor_id'] = ['old' => $oldReceptorId, 'new' => $receptorId];
    }

    $idsDetalle = array_unique(array_merge(array_keys($oldTotalesPorProducto), array_keys($newTotalesPorProducto)));
    foreach ($idsDetalle as $pid) {
        $pid = (int)$pid;
        $oldQty = (int)($oldTotalesPorProducto[$pid] ?? 0);
        $newQty = (int)($newTotalesPorProducto[$pid] ?? 0);
        if ($oldQty !== $newQty) {
            $cambios['detalle']['items'][] = [
                'producto_id' => $pid,
                'old_cantidad' => $oldQty,
                'new_cantidad' => $newQty,
            ];
        }
    }

    return guardarModificacionTransaccion('salida', $salidaId, $razon, $cambios, $usuarioId);
}
