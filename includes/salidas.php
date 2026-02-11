<?php
require_once __DIR__ . '/../config/database.php';

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
    $stmt = $pdo->prepare('SELECT id, referencia, fecha, nombre_receptor, estado, created_at FROM salidas ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

function obtenerSalidaConDetalle(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, referencia, fecha, nombre_receptor, estado FROM salidas WHERE id = ?');
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

function crearSalida(string $fecha, string $nombreReceptor, array $lineas): int {
    $pdo = getDB();
    $ref = generarReferenciaSalida();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO salidas (referencia, fecha, nombre_receptor, estado) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ref, $fecha, $nombreReceptor, 'completada']);
        $salidaId = (int) $pdo->lastInsertId();
        $stmt2 = $pdo->prepare('INSERT INTO detalle_salidas (salida_id, producto_id, cantidad) VALUES (?, ?, ?)');
        foreach ($lineas as $l) {
            if (empty($l['producto_id']) || (int)($l['cantidad'] ?? 0) <= 0) continue;
            $stmt2->execute([$salidaId, $l['producto_id'], (int)$l['cantidad']]);
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
