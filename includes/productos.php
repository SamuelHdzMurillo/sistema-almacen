<?php
require_once __DIR__ . '/../config/database.php';

function listarProductos(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, codigo, nombre, descripcion, unidad FROM productos ORDER BY nombre');
    return $stmt->fetchAll();
}

function obtenerProducto(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, codigo, nombre, descripcion, unidad FROM productos WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function crearProducto(array $d): int {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO productos (codigo, nombre, descripcion, unidad) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $d['codigo'] ?? null,
        $d['nombre'],
        $d['descripcion'] ?? null,
        $d['unidad'] ?? 'und'
    ]);
    return (int) $pdo->lastInsertId();
}
