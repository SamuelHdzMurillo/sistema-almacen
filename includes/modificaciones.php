<?php
require_once __DIR__ . '/../config/database.php';

function ensureTransaccionModificacionesTable(): void {
    $pdo = getDB();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transaccion_modificaciones (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo ENUM('entrada','salida') NOT NULL,
            transaccion_id INT UNSIGNED NOT NULL,
            razon TEXT NOT NULL,
            cambios_json JSON NULL,
            request_id VARCHAR(64) NULL,
            usuario_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tipo_transaccion (tipo, transaccion_id),
            INDEX idx_request_id (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function guardarModificacionTransaccion(string $tipo, int $transaccionId, string $razon, array $cambios, ?int $usuarioId = null): int {
    ensureTransaccionModificacionesTable();
    $pdo = getDB();

    $tipo = $tipo === 'salida' ? 'salida' : 'entrada';
    $usuarioId = $usuarioId ?? (isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null);
    $requestId = $GLOBALS['APP_REQUEST_ID'] ?? null;

    $cambiosJson = null;
    try {
        $cambiosJson = json_encode($cambios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        $cambiosJson = null;
    }

    $stmt = $pdo->prepare('
        INSERT INTO transaccion_modificaciones (tipo, transaccion_id, razon, cambios_json, request_id, usuario_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$tipo, $transaccionId, $razon, $cambiosJson, $requestId, $usuarioId]);
    return (int)$pdo->lastInsertId();
}

function obtenerUltimaModificacionTransaccion(string $tipo, int $transaccionId): ?array {
    ensureTransaccionModificacionesTable();
    $pdo = getDB();
    $tipo = $tipo === 'salida' ? 'salida' : 'entrada';
    $stmt = $pdo->prepare('
        SELECT id, tipo, transaccion_id, razon, cambios_json, request_id, usuario_id, created_at
        FROM transaccion_modificaciones
        WHERE tipo = ? AND transaccion_id = ?
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->execute([$tipo, $transaccionId]);
    $row = $stmt->fetch();
    if (!$row) return null;

    if (isset($row['cambios_json'])) {
        // En MySQL, JSON puede volver como string o como array según driver/config.
        if (is_string($row['cambios_json'])) {
            $decoded = json_decode($row['cambios_json'], true);
            $row['cambios_json'] = $decoded ?? null;
        }
    }
    return $row;
}

function listarModificacionesTransaccion(string $tipo, int $transaccionId): array {
    ensureTransaccionModificacionesTable();
    $pdo = getDB();
    $tipo = $tipo === 'salida' ? 'salida' : 'entrada';

    $stmt = $pdo->prepare('
        SELECT id, tipo, transaccion_id, razon, cambios_json, request_id, usuario_id, created_at
        FROM transaccion_modificaciones
        WHERE tipo = ? AND transaccion_id = ?
        ORDER BY created_at DESC, id DESC
    ');
    $stmt->execute([$tipo, $transaccionId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (isset($row['cambios_json'])) {
            if (is_string($row['cambios_json'])) {
                $decoded = json_decode($row['cambios_json'], true);
                $row['cambios_json'] = $decoded ?? null;
            }
        }
    }
    unset($row);

    return $rows ?: [];
}

