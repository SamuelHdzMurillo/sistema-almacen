<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/inventario.php';
require_once __DIR__ . '/modificaciones.php';
require_once __DIR__ . '/almacenes.php';

function generarReferenciaEntrada(): string {
    $pdo = getDB();
    $y = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM entradas WHERE referencia LIKE ?");
    $stmt->execute(["PE-{$y}-%"]);
    $n = (int) $stmt->fetch()['n'] + 1;
    return sprintf('PE-%s-%03d', $y, $n);
}

function listarEntradas(int $limite = 50, ?int $almacenId = null): array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        SELECT e.id, e.referencia, e.factura, e.fecha, e.estado, e.created_at, e.updated_at, e.created_by,
               u.nombre AS created_by_nombre, prov.nombre AS proveedor_nombre, qr.nombre AS quien_recibe_nombre
        FROM entradas e
        LEFT JOIN usuarios u ON u.id = e.created_by
        LEFT JOIN catalogo_proveedor prov ON prov.id = e.proveedor_id
        LEFT JOIN catalogo_quien_recibe_entrada qr ON qr.id = e.quien_recibe_id
        WHERE e.almacen_id = ?
        ORDER BY e.created_at DESC
        LIMIT ?
    ');
    $stmt->execute([$almacenId, $limite]);
    return $stmt->fetchAll();
}

function obtenerEntradaConDetalle(int $id, ?int $almacenId = null): ?array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        SELECT e.id, e.referencia, e.factura, e.fecha, e.estado, e.created_at, e.updated_at, e.created_by,
               e.proveedor_id, e.quien_recibe_id,
               u.nombre AS created_by_nombre,
               prov.nombre AS proveedor_nombre,
               qr.nombre AS quien_recibe_nombre
        FROM entradas e
        LEFT JOIN usuarios u ON u.id = e.created_by
        LEFT JOIN catalogo_proveedor prov ON prov.id = e.proveedor_id
        LEFT JOIN catalogo_quien_recibe_entrada qr ON qr.id = e.quien_recibe_id
        WHERE e.id = ? AND e.almacen_id = ?
    ');
    $stmt->execute([$id, $almacenId]);
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

function crearEntrada(
    string $fecha,
    int $proveedorId,
    int $quienRecibeId,
    array $lineas,
    ?string $factura = null,
    ?int $usuarioId = null,
    ?int $almacenId = null
): int {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $ref = generarReferenciaEntrada();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO entradas (referencia, factura, fecha, proveedor_id, quien_recibe_id, almacen_id, estado, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$ref, $factura, $fecha, $proveedorId, $quienRecibeId, $almacenId, 'completada', $usuarioId]);
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

function totalEntradasEsteMes(?int $almacenId = null): int {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(de.cantidad), 0) AS t
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        WHERE e.estado = 'completada'
          AND e.almacen_id = ?
          AND (de.estado = 'activa' OR de.estado IS NULL)
          AND YEAR(e.fecha) = YEAR(CURDATE())
          AND MONTH(e.fecha) = MONTH(CURDATE())
    ");
    $stmt->execute([$almacenId]);
    return (int) $stmt->fetch()['t'];
}

function totalEntradasMesAnterior(?int $almacenId = null): int {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(de.cantidad), 0) AS t
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        WHERE e.estado = 'completada'
          AND e.almacen_id = ?
          AND (de.estado = 'activa' OR de.estado IS NULL)
          AND e.fecha >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
          AND e.fecha < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ");
    $stmt->execute([$almacenId]);
    return (int) $stmt->fetch()['t'];
}

/** Marca una entrada como cancelada. El stock dejará de contar sus ítems. */
function cancelarEntrada(int $id, ?int $almacenId = null): bool {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        UPDATE entradas
        SET estado = ?
        WHERE id = ? AND estado = ? AND almacen_id = ?
    ');
    $stmt->execute(['cancelada', $id, 'completada', $almacenId]);
    return $stmt->rowCount() > 0;
}

/** Devuelve una línea de detalle_entradas por ID (para redirigir tras cancelar). */
function obtenerLineaEntrada(int $detalleId, ?int $almacenId = null): ?array {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $stmt = $pdo->prepare('
        SELECT de.id, de.entrada_id, de.producto_id, de.cantidad, COALESCE(de.estado, \'activa\') AS estado
        FROM detalle_entradas de
        JOIN entradas e ON e.id = de.entrada_id
        WHERE de.id = ? AND e.almacen_id = ?
    ');
    $stmt->execute([$detalleId, $almacenId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Marca una línea de detalle de entrada como cancelada. Solo si la entrada está completada y la línea activa. */
function cancelarLineaEntrada(int $detalleId, ?int $almacenId = null): bool {
    $pdo = getDB();
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $linea = obtenerLineaEntrada($detalleId, $almacenId);
    if (!$linea || ($linea['estado'] ?? 'activa') === 'cancelada') {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE detalle_entradas de
        INNER JOIN entradas e ON e.id = de.entrada_id AND e.estado = \'completada\' AND e.almacen_id = ?
        SET de.estado = \'cancelada\'
        WHERE de.id = ? AND (de.estado = \'activa\' OR de.estado IS NULL)');
    $stmt->execute([$almacenId, $detalleId]);
    return $stmt->rowCount() > 0;
}

function actualizarEntrada(
    int $entradaId,
    string $fecha,
    int $proveedorId,
    int $quienRecibeId,
    array $lineas,
    ?string $factura,
    ?int $usuarioId,
    string $razon,
    ?int $almacenId = null
): int {
    $almacenId = $almacenId !== null ? (int)$almacenId : getAlmacenActivo();
    $transaccionVieja = obtenerEntradaConDetalle($entradaId, $almacenId);
    if (!$transaccionVieja) {
        throw new Exception('Entrada no encontrada.');
    }
    if (($transaccionVieja['estado'] ?? 'completada') === 'cancelada') {
        throw new Exception('No se puede editar una entrada cancelada.');
    }
    foreach (($transaccionVieja['detalle'] ?? []) as $d) {
        $estadoLinea = $d['estado'] ?? 'activa';
        if ($estadoLinea !== 'activa') {
            throw new Exception('No se puede editar una entrada con líneas canceladas.');
        }
    }

    $oldFecha = (string)($transaccionVieja['fecha'] ?? '');
    $oldFactura = $transaccionVieja['factura'] ?? null;
    $oldProveedorId = (int)($transaccionVieja['proveedor_id'] ?? 0);
    $oldQuienRecibeId = (int)($transaccionVieja['quien_recibe_id'] ?? 0);

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

    // Validación de stock (evita quedar en negativo al reducir cantidades).
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
            throw new Exception('Al editar quedaría stock negativo. Verifique cantidades.');
        }
    }

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        // rowCount() en MySQL puede venir en 0 si los valores no cambiaron,
        // pero el registro sí existe. Por eso validamos existencia antes.
        $check = $pdo->prepare('
            SELECT id FROM entradas WHERE id = ? AND almacen_id = ? AND estado != ? LIMIT 1
        ');
        $check->execute([$entradaId, $almacenId, 'cancelada']);
        if (!$check->fetch()) {
            throw new Exception('No se puede editar esta entrada (no encontrada o cancelada).');
        }

        $stmt = $pdo->prepare('
            UPDATE entradas
            SET fecha = ?, factura = ?, proveedor_id = ?, quien_recibe_id = ?
            WHERE id = ? AND almacen_id = ? AND estado != ?
        ');
        $stmt->execute([$fecha, $factura, $proveedorId, $quienRecibeId, $entradaId, $almacenId, 'cancelada']);

        $stmtDel = $pdo->prepare('DELETE FROM detalle_entradas WHERE entrada_id = ?');
        $stmtDel->execute([$entradaId]);

        $stmt2 = $pdo->prepare('INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad, created_by) VALUES (?, ?, ?, ?)');
        foreach ($lineas as $l) {
            $pid = (int)($l['producto_id'] ?? 0);
            $qty = (int)($l['cantidad'] ?? 0);
            if ($pid <= 0 || $qty <= 0) continue;
            $stmt2->execute([$entradaId, $pid, $qty, $usuarioId]);
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
    if ((string)($oldFactura ?? '') !== (string)($factura ?? '')) {
        $cambios['header']['factura'] = ['old' => $oldFactura, 'new' => $factura];
    }
    if ($oldProveedorId !== $proveedorId) {
        $cambios['header']['proveedor_id'] = ['old' => $oldProveedorId, 'new' => $proveedorId];
    }
    if ($oldQuienRecibeId !== $quienRecibeId) {
        $cambios['header']['quien_recibe_id'] = ['old' => $oldQuienRecibeId, 'new' => $quienRecibeId];
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

    return guardarModificacionTransaccion('entrada', $entradaId, $razon, $cambios, $usuarioId);
}
