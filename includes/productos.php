<?php
require_once __DIR__ . '/../config/database.php';

function listarProductos(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, codigo, nombre, descripcion, unidad FROM productos ORDER BY nombre');
    return $stmt->fetchAll();
}

/** Unidades por defecto + las que ya se usan en productos (sin duplicados, ordenadas). */
function listarUnidadesDisponibles(): array {
    $defecto = ['und', 'caja', 'rollo', 'kg', 'L', 'm'];
    $pdo = getDB();
    $stmt = $pdo->query('SELECT DISTINCT unidad FROM productos WHERE unidad IS NOT NULL AND unidad != "" ORDER BY unidad');
    $enUso = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $todas = array_values(array_unique(array_merge($defecto, $enUso)));
    sort($todas);
    return $todas;
}

function obtenerProducto(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, codigo, nombre, descripcion, unidad FROM productos WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

/**
 * Obtiene el siguiente código de producto según el último ingresado.
 * Si el último es PROD-001 devuelve PROD-002; si no hay productos o no hay patrón, devuelve PROD-001.
 */
function obtenerSiguienteCodigoProducto(): string {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT codigo FROM productos WHERE codigo IS NOT NULL AND codigo != "" ORDER BY id DESC LIMIT 1');
    $row = $stmt->fetch();
    if (!$row || empty($row['codigo'])) {
        return 'PROD-001';
    }
    $codigo = trim($row['codigo']);
    if (preg_match('/^(.+?)(\d+)$/', $codigo, $m)) {
        $prefijo = $m[1];
        $numero = (int) $m[2];
        $longitud = strlen($m[2]);
        return $prefijo . str_pad($numero + 1, $longitud, '0', STR_PAD_LEFT);
    }
    return 'PROD-001';
}

function crearProducto(array $d, ?int $usuarioId = null): int {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO productos (codigo, nombre, descripcion, unidad, created_by) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $d['codigo'] ?? null,
        $d['nombre'],
        $d['descripcion'] ?? null,
        $d['unidad'] ?? 'und',
        $usuarioId
    ]);
    return (int) $pdo->lastInsertId();
}
