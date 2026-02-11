<?php
require_once __DIR__ . '/../config/database.php';

function listarZonas(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre, capacidad FROM zonas ORDER BY nombre');
    return $stmt->fetchAll();
}
