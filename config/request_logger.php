<?php
/**
 * Logger de nivel petición (GET/POST) para auditar qué usuario/proceso hizo qué.
 *
 * Registra en `logs/requests.log` en formato JSONL (una entrada por línea).
 */

function initRequestLogging(): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($GLOBALS['APP_REQUEST_ID']) || !is_string($GLOBALS['APP_REQUEST_ID'])) {
        $GLOBALS['APP_REQUEST_ID'] = generateRequestId();
    }

    $GLOBALS['APP_USER_ID'] = $_SESSION['usuario_id'] ?? null;
    $GLOBALS['APP_USER_NAME'] = $_SESSION['usuario_nombre'] ?? null;
    $GLOBALS['APP_REQUEST_CTX'] = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'ruta' => '',
    ];

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/requests.log';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = $requestUri ? (parse_url($requestUri, PHP_URL_PATH) ?? $requestUri) : '';
    $GLOBALS['APP_REQUEST_CTX']['method'] = $method;
    $GLOBALS['APP_REQUEST_CTX']['ruta'] = $path;
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // POST raw (incluye campos sensibles si los envías).
    $postRaw = is_array($_POST ?? null) ? $_POST : [];

    [$accionInferida, $detalleInferida] = inferRequestAction($method, $path, $postRaw);

    $entry = [
        'timestamp' => date('c'),
        'request_id' => $GLOBALS['APP_REQUEST_ID'],
        'usuario_id' => $GLOBALS['APP_USER_ID'],
        'usuario_nombre' => $GLOBALS['APP_USER_NAME'],
        'method' => $method,
        'ruta' => $path,
        'query_string' => $queryString,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'post_raw' => $postRaw,
        'accion_inferida' => $accionInferida,
        'detalle_inferida' => $detalleInferida,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Heurísticas simples para hacer legibles los logs de petición.
 * (No cambia seguridad; solo “traduce” ruta+POST a un texto.)
 */
function inferRequestAction(string $method, string $path, array $postRaw): array {
    if ($method !== 'POST') {
        return ['GET', $path];
    }

    $basename = strtolower(basename($path));

    // Login
    if ($basename === 'login.php') {
        $usuario = isset($postRaw['usuario']) ? (string)$postRaw['usuario'] : '';
        return ['LOGIN', $usuario !== '' ? 'usuario=' . $usuario : ''];
    }

    if ($basename === 'productos.php' && isset($postRaw['crear'])) {
        $nombre = trim((string)($postRaw['nombre'] ?? ''));
        $codigo = trim((string)($postRaw['codigo'] ?? ''));
        return ['CREAR_PRODUCTO', trim($nombre) !== '' ? "nombre={$nombre}" . ($codigo !== '' ? " codigo={$codigo}" : '') : ''];
    }

    if ($basename === 'nueva-entrada.php') {
        $fecha = trim((string)($postRaw['fecha'] ?? ''));
        $proveedorId = isset($postRaw['proveedor_id']) && $postRaw['proveedor_id'] !== '' ? (string)$postRaw['proveedor_id'] : '';
        $productoIds = isset($postRaw['producto_id']) && is_array($postRaw['producto_id']) ? $postRaw['producto_id'] : [];
        $lineas = is_array($productoIds) ? count($productoIds) : 0;
        return ['REGISTRAR_ENTRADA', ($fecha !== '' ? "fecha={$fecha} " : '') . ($proveedorId !== '' ? "proveedor_id={$proveedorId} " : '') . "lineas={$lineas}"];
    }

    if ($basename === 'nueva-salida.php') {
        $fecha = trim((string)($postRaw['fecha'] ?? ''));
        $plantelId = isset($postRaw['plantel_id']) && $postRaw['plantel_id'] !== '' ? (string)$postRaw['plantel_id'] : '';
        $productoIds = isset($postRaw['producto_id']) && is_array($postRaw['producto_id']) ? $postRaw['producto_id'] : [];
        $lineas = is_array($productoIds) ? count($productoIds) : 0;
        return ['REGISTRAR_SALIDA', ($fecha !== '' ? "fecha={$fecha} " : '') . ($plantelId !== '' ? "plantel_id={$plantelId} " : '') . "lineas={$lineas}"];
    }

    if ($basename === 'cancelar-entrada.php' && isset($postRaw['confirmar'])) {
        $id = isset($postRaw['id']) ? (string)$postRaw['id'] : '';
        return ['CANCELAR_ENTRADA', $id !== '' ? "id={$id}" : ''];
    }

    if ($basename === 'cancelar-salida.php' && isset($postRaw['confirmar'])) {
        $id = isset($postRaw['id']) ? (string)$postRaw['id'] : '';
        return ['CANCELAR_SALIDA', $id !== '' ? "id={$id}" : ''];
    }

    if ($basename === 'cancelar-linea-entrada.php') {
        $detalleId = isset($postRaw['id']) ? (string)$postRaw['id'] : '';
        return ['CANCELAR_LINEA_ENTRADA', $detalleId !== '' ? "detalle_id={$detalleId}" : ''];
    }

    // Fallback
    return ['POST', $basename];
}

function generateRequestId(): string {
    try {
        $bytes = random_bytes(16);
        // UUID v4 (aproximado): ajustar bits de versión/variante.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    } catch (Throwable $e) {
        // Fallback sin criptografía.
        return bin2hex((string)microtime(true)) . '-' . mt_rand(1000, 9999);
    }
}

