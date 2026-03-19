<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Multi-almacén:
 * - cada usuario pertenece a un almacén (usuarios.almacen_id)
 * - el admin puede elegir el almacén activo por sesión ($_SESSION['almacen_id'])
 */

function listarAlmacenes(): array {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT id, nombre FROM almacenes ORDER BY nombre');
    return $stmt->fetchAll();
}

function esAdminSesionAlmacen(): bool {
    $usuarioNombreSesion = (string)($_SESSION['usuario_nombre'] ?? '');
    $usuarioIdSesion = (int)($_SESSION['usuario_id'] ?? 0);
    return ($usuarioNombreSesion === 'Administrador') || ($usuarioIdSesion === 1);
}

function setAlmacenActivo(int $almacenId): bool {
    $almacenId = (int) $almacenId;
    if ($almacenId <= 0) return false;

    // Validar que exista.
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id FROM almacenes WHERE id = ? LIMIT 1');
    $stmt->execute([$almacenId]);
    if (!$stmt->fetch()) return false;

    $_SESSION['almacen_id'] = $almacenId;
    return true;
}

function asegurarAlmacenActivo(): void {
    if (!isset($_SESSION)) {
        // No debería pasar (auth.php siempre arranca sesión antes), pero evitamos fatales.
        return;
    }

    $esAdmin = esAdminSesionAlmacen();
    $almacenSesion = isset($_SESSION['almacen_id']) ? (int)$_SESSION['almacen_id'] : 0;

    // Si el admin manda un parámetro, se respeta (si existe en DB).
    if ($esAdmin && isset($_GET['almacen_id'])) {
        $almacenParam = (int)($_GET['almacen_id'] ?? 0);
        if ($almacenParam > 0) {
            setAlmacenActivo($almacenParam);
            $almacenSesion = (int)($_SESSION['almacen_id'] ?? 0);
        }
    }

    if ($almacenSesion > 0) {
        return;
    }

    if ($esAdmin) {
        // Admin: default al primer almacén (por nombre).
        $almacenes = listarAlmacenes();
        if (!empty($almacenes)) {
            $_SESSION['almacen_id'] = (int)$almacenes[0]['id'];
            return;
        }
        // Si no hay almacenes, no hacemos nada: el sistema fallará por stock/consultas.
        return;
    }

    // Usuario normal: leer su almacén desde usuarios.
    $usuarioIdSesion = (int)($_SESSION['usuario_id'] ?? 0);
    if ($usuarioIdSesion <= 0) return;

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT almacen_id FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioIdSesion]);
    $row = $stmt->fetch();
    $almacenDb = isset($row['almacen_id']) ? (int)$row['almacen_id'] : 0;
    if ($almacenDb > 0) {
        $_SESSION['almacen_id'] = $almacenDb;
        return;
    }

    // Fallback: 'Principal' si existe.
    $stmt = $pdo->prepare('SELECT id FROM almacenes WHERE nombre = ? LIMIT 1');
    $stmt->execute(['Principal']);
    $principal = $stmt->fetch();
    if ($principal) $_SESSION['almacen_id'] = (int)$principal['id'];
}

function getAlmacenActivo(): int {
    asegurarAlmacenActivo();
    return (int)($_SESSION['almacen_id'] ?? 0);
}

function getNombreAlmacenActivo(): string {
    $id = getAlmacenActivo();
    if ($id <= 0) return '';
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT nombre FROM almacenes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? (string)$row['nombre'] : '';
}

