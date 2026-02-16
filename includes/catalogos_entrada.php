<?php
require_once __DIR__ . '/../config/database.php';

/** Normaliza texto a mayúsculas para guardar en catálogos (UTF-8) */
function normalizarNombreCatalogoEntrada(string $nombre): string {
    $nombre = trim($nombre);
    if ($nombre === '') return $nombre;
    return mb_strtoupper($nombre, 'UTF-8');
}

/** Lista activos del catálogo Proveedor */
function listarProveedores(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre FROM catalogo_proveedor WHERE activo = 1 ORDER BY nombre');
    return $stmt->fetchAll();
}

/** Lista activos del catálogo Quien recibe (entrada) */
function listarQuienRecibeEntrada(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre FROM catalogo_quien_recibe_entrada WHERE activo = 1 ORDER BY nombre');
    return $stmt->fetchAll();
}

/** Inserta o devuelve ID de Proveedor por nombre. Se guarda en mayúsculas. */
function obtenerOcrearProveedor(string $nombre): int {
    $pdo = getDB();
    $nombre = trim($nombre);
    if ($nombre === '') $nombre = 'No indicado';
    $nombre = normalizarNombreCatalogoEntrada($nombre);
    $stmt = $pdo->prepare('SELECT id FROM catalogo_proveedor WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $stmt = $pdo->prepare('INSERT INTO catalogo_proveedor (nombre) VALUES (?)');
    $stmt->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}

/** Inserta o devuelve ID de Quien recibe entrada por nombre. Se guarda en mayúsculas. */
function obtenerOcrearQuienRecibeEntrada(string $nombre): int {
    $pdo = getDB();
    $nombre = trim($nombre);
    if ($nombre === '') $nombre = 'No indicado';
    $nombre = normalizarNombreCatalogoEntrada($nombre);
    $stmt = $pdo->prepare('SELECT id FROM catalogo_quien_recibe_entrada WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $stmt = $pdo->prepare('INSERT INTO catalogo_quien_recibe_entrada (nombre) VALUES (?)');
    $stmt->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}
