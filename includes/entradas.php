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
    $stmt = $pdo->prepare('SELECT e.id, e.referencia, e.factura, e.fecha, e.estado, e.created_at, e.updated_at, e.created_by, u.nombre AS created_by_nombre, prov.nombre AS proveedor_nombre, qr.nombre AS quien_recibe_nombre FROM entradas e LEFT JOIN usuarios u ON u.id = e.created_by LEFT JOIN catalogo_proveedor prov ON prov.id = e.proveedor_id LEFT JOIN catalogo_quien_recibe_entrada qr ON qr.id = e.quien_recibe_id ORDER BY e.created_at DESC LIMIT ?');
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

function obtenerEntradaConDetalle(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT e.id, e.referencia, e.factura, e.fecha, e.estado, e.created_at, e.updated_at, e.created_by, u.nombre AS created_by_nombre, prov.nombre AS proveedor_nombre, qr.nombre AS quien_recibe_nombre FROM entradas e LEFT JOIN usuarios u ON u.id = e.created_by LEFT JOIN catalogo_proveedor prov ON prov.id = e.proveedor_id LEFT JOIN catalogo_quien_recibe_entrada qr ON qr.id = e.quien_recibe_id WHERE e.id = ?');
    $stmt->execute([$id]);
    $e = $stmt->fetch();
    if (!$e) return null;
    $stmt2 = $pdo->prepare('
        SELECT de.id, de.producto_id, de.cantidad, COALESCE(de.estado, \'activa\') AS estado, p.nombre AS producto_nombre, p.unidad
        FROM detalle_entradas de
        JOIN productos p ON p.id = de.producto_id
        WHERE de.entrada_id = ?
        ORDER BY de.id
    ');
    $stmt2->execute([$id]);
    $e['detalle'] = $stmt2->fetchAll();
    return $e;
}

function crearEntrada(string $fecha, int $proveedorId, int $quienRecibeId, array $lineas, ?string $factura = null, ?int $usuarioId = null): int {
    $pdo = getDB();
    $ref = generarReferenciaEntrada();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO entradas (referencia, factura, fecha, proveedor_id, quien_recibe_id, estado, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$ref, $factura, $fecha, $proveedorId, $quienRecibeId, 'completada', $usuarioId]);
        $entradaId = (int) $pdo->lastInsertId();
        $stmt2 = $pdo->prepare('INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad, created_by) VALUES (?, ?, ?, ?)');
        foreach ($lineas as $l) {
            if (empty($l['producto_id']) || (int)($l['cantidad'] ?? 0) <= 0) continue;
            $stmt2->execute([$entradaId, $l['producto_id'], (int)$l['cantidad'], $usuarioId]);
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
    $stmt = $pdo->query("SELECT COALESCE(SUM(de.cantidad), 0) AS t FROM detalle_entradas de JOIN entradas e ON e.id = de.entrada_id WHERE e.estado = 'completada' AND (de.estado = 'activa' OR de.estado IS NULL) AND YEAR(e.fecha) = YEAR(CURDATE()) AND MONTH(e.fecha) = MONTH(CURDATE())");
    return (int) $stmt->fetch()['t'];
}

function totalEntradasMesAnterior(): int {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COALESCE(SUM(de.cantidad), 0) AS t FROM detalle_entradas de JOIN entradas e ON e.id = de.entrada_id WHERE e.estado = 'completada' AND (de.estado = 'activa' OR de.estado IS NULL) AND e.fecha >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND e.fecha < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
    return (int) $stmt->fetch()['t'];
}

/** Marca una entrada como cancelada. El stock dejará de contar sus ítems. */
function cancelarEntrada(int $id): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE entradas SET estado = ? WHERE id = ? AND estado = ?');
    $stmt->execute(['cancelada', $id, 'completada']);
    return $stmt->rowCount() > 0;
}

/** Devuelve una línea de detalle_entradas por ID (para redirigir tras cancelar). */
function obtenerLineaEntrada(int $detalleId): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, entrada_id, producto_id, cantidad, COALESCE(estado, \'activa\') AS estado FROM detalle_entradas WHERE id = ?');
    $stmt->execute([$detalleId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Marca una línea de detalle de entrada como cancelada. Solo si la entrada está completada y la línea activa. */
function cancelarLineaEntrada(int $detalleId): bool {
    $pdo = getDB();
    $linea = obtenerLineaEntrada($detalleId);
    if (!$linea || ($linea['estado'] ?? 'activa') === 'cancelada') {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE detalle_entradas de
        INNER JOIN entradas e ON e.id = de.entrada_id AND e.estado = \'completada\'
        SET de.estado = \'cancelada\'
        WHERE de.id = ? AND (de.estado = \'activa\' OR de.estado IS NULL)');
    $stmt->execute([$detalleId]);
    return $stmt->rowCount() > 0;
}
