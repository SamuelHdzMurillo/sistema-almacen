<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/inventario.php';

function generarReferenciaSalida(): string {
    $pdo = getDB();
    $y = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM salidas WHERE referencia LIKE ?");
    $stmt->execute(["PS-{$y}-%"]);
    $n = (int) $stmt->fetch()['n'] + 1;
    return sprintf('PS-%s-%03d', $y, $n);
}

function listarSalidas(int $limite = 50): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT s.id, s.referencia, s.fecha, s.nombre_entrega, s.nombre_receptor, s.estado, s.created_at, s.updated_at, s.created_by, u.nombre AS created_by_nombre FROM salidas s LEFT JOIN usuarios u ON u.id = s.created_by ORDER BY s.created_at DESC LIMIT ?');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

function obtenerSalidaConDetalle(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT s.id, s.referencia, s.fecha, s.nombre_entrega, s.nombre_receptor, s.estado, s.created_at, s.updated_at, s.created_by, u.nombre AS created_by_nombre FROM salidas s LEFT JOIN usuarios u ON u.id = s.created_by WHERE s.id = ?');
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) return null;
    $stmt2 = $pdo->prepare('
        SELECT ds.id, ds.producto_id, ds.cantidad, p.nombre AS producto_nombre, p.unidad
        FROM detalle_salidas ds
        JOIN productos p ON p.id = ds.producto_id
        WHERE ds.salida_id = ?
    ');
    $stmt2->execute([$id]);
    $s['detalle'] = $stmt2->fetchAll();
    return $s;
}

function crearSalida(string $fecha, string $nombreEntrega, string $nombreReceptor, array $lineas, ?int $usuarioId = null): int {
    $pdo = getDB();
    $inventario = inventarioPorProducto();
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
        $stmt = $pdo->prepare('INSERT INTO salidas (referencia, fecha, nombre_entrega, nombre_receptor, estado, created_by) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ref, $fecha, $nombreEntrega, $nombreReceptor, 'completada', $usuarioId]);
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

function totalSalidasEsteMes(): int {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COALESCE(SUM(cantidad), 0) AS t FROM detalle_salidas ds JOIN salidas s ON s.id = ds.salida_id WHERE s.estado = 'completada' AND YEAR(s.fecha) = YEAR(CURDATE()) AND MONTH(s.fecha) = MONTH(CURDATE())");
    return (int) $stmt->fetch()['t'];
}

/** Marca una salida como cancelada. El stock volverá a contar esos ítems. */
function cancelarSalida(int $id): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE salidas SET estado = ? WHERE id = ? AND estado = ?');
    $stmt->execute(['cancelada', $id, 'completada']);
    return $stmt->rowCount() > 0;
}
