<?php
require_once __DIR__ . '/../config/database.php';

function generarReferenciaEntrada(): string {
    $pdo = getDB();
    $y = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM entradas WHERE referencia LIKE ?");
    $stmt->execute(["PE-{$y}-%"]);
    $n = (int) $stmt->fetch()['n'] + 1;
    return sprintf('PE-%s-%03d', $y, $n);
}

function listarEntradas(int $limite = 50): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, referencia, fecha, responsable, estado, created_at FROM entradas ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

function obtenerEntradaConDetalle(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, referencia, fecha, responsable, estado FROM entradas WHERE id = ?');
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) return null;
    $stmt2 = $pdo->prepare('
        SELECT de.id, de.producto_id, de.cantidad, p.nombre AS producto_nombre, p.unidad
        FROM detalle_entradas de
        JOIN productos p ON p.id = de.producto_id
        WHERE de.entrada_id = ?
    ');
    $stmt2->execute([$id]);
    $e['detalle'] = $stmt2->fetchAll();
    return $e;
}

function crearEntrada(string $fecha, string $responsable, array $lineas): int {
    $pdo = getDB();
    $ref = generarReferenciaEntrada();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO entradas (referencia, fecha, responsable, estado) VALUES (?, ?, ?, ?)');
        $stmt->execute([$ref, $fecha, $responsable, 'completada']);
        $entradaId = (int) $pdo->lastInsertId();
        $stmt2 = $pdo->prepare('INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad) VALUES (?, ?, ?)');
        foreach ($lineas as $l) {
            if (empty($l['producto_id']) || (int)($l['cantidad'] ?? 0) <= 0) continue;
            $stmt2->execute([$entradaId, $l['producto_id'], (int)$l['cantidad']]);
        }
        $pdo->commit();
        return $entradaId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function totalEntradasEsteMes(): int {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COALESCE(SUM(cantidad), 0) AS t FROM detalle_entradas de JOIN entradas e ON e.id = de.entrada_id WHERE e.estado = 'completada' AND YEAR(e.fecha) = YEAR(CURDATE()) AND MONTH(e.fecha) = MONTH(CURDATE())");
    return (int) $stmt->fetch()['t'];
}

function totalEntradasMesAnterior(): int {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COALESCE(SUM(cantidad), 0) AS t FROM detalle_entradas de JOIN entradas e ON e.id = de.entrada_id WHERE e.estado = 'completada' AND e.fecha >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND e.fecha < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    return (int) $stmt->fetch()['t'];
}
