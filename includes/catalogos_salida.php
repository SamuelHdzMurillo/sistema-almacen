<?php
require_once __DIR__ . '/../config/database.php';

/** Normaliza texto a mayúsculas para guardar en catálogos (UTF-8, respeta Ñ y acentos) */
function normalizarNombreCatalogo(string $nombre): string {
    $nombre = trim($nombre);
    if ($nombre === '') return $nombre;
    return mb_strtoupper($nombre, 'UTF-8');
}

/** Lista activos del catálogo "Quien entrega" */
function listarQuienEntrega(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre FROM catalogo_quien_entrega WHERE activo = 1 ORDER BY nombre');
    return $stmt->fetchAll();
}

/** Lista activos del catálogo "Plantel" */
function listarPlanteles(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre FROM catalogo_plantel WHERE activo = 1 ORDER BY nombre');
    return $stmt->fetchAll();
}

/** Lista activos del catálogo "Receptor" (persona que recibe) */
function listarReceptores(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre FROM catalogo_receptor WHERE activo = 1 ORDER BY nombre');
    return $stmt->fetchAll();
}

/** Inserta o devuelve ID de "Quien entrega" por nombre (evita duplicados). Se guarda en mayúsculas. */
function obtenerOcrearQuienEntrega(string $nombre): int {
    $pdo = getDB();
    $nombre = trim($nombre);
    if ($nombre === '') $nombre = 'No indicado';
    $nombre = normalizarNombreCatalogo($nombre);
    $stmt = $pdo->prepare('SELECT id FROM catalogo_quien_entrega WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $stmt = $pdo->prepare('INSERT INTO catalogo_quien_entrega (nombre) VALUES (?)');
    $stmt->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}

/** Inserta o devuelve ID de "Plantel" por nombre. Se guarda en mayúsculas. */
function obtenerOcrearPlantel(string $nombre): int {
    $pdo = getDB();
    $nombre = trim($nombre);
    if ($nombre === '') $nombre = 'No especificado';
    $nombre = normalizarNombreCatalogo($nombre);
    $stmt = $pdo->prepare('SELECT id FROM catalogo_plantel WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $stmt = $pdo->prepare('INSERT INTO catalogo_plantel (nombre) VALUES (?)');
    $stmt->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}

/** Inserta o devuelve ID de "Receptor" por nombre. Se guarda en mayúsculas. */
function obtenerOcrearReceptor(string $nombre): int {
    $pdo = getDB();
    $nombre = trim($nombre);
    if ($nombre === '') $nombre = 'No indicado';
    $nombre = normalizarNombreCatalogo($nombre);
    $stmt = $pdo->prepare('SELECT id FROM catalogo_receptor WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $stmt = $pdo->prepare('INSERT INTO catalogo_receptor (nombre) VALUES (?)');
    $stmt->execute([$nombre]);
    return (int) $pdo->lastInsertId();
}
