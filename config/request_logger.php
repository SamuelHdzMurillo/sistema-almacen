<?php
/**
 * Logger de nivel petición (GET/POST) para auditar qué usuario/proceso hizo qué.
 *
 * Registra en `logs/requests.log` en formato JSONL (una entrada por línea).
 */

require_once __DIR__ . '/../includes/log_actividad.php';

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

    [$accionInferida, $detalleInferida] = inferRequestAction($method, $path, $postRaw, $queryString);

    // No registrar cada clic o consulta de pantalla (solo acciones relevantes).
    if (in_array($accionInferida, ['CONSULTA', 'VER_LOGIN', 'INICIAR_SESION'], true)) {
        return;
    }

    $mensajeLegible = mensajeActividadLegible(
        $accionInferida,
        $detalleInferida,
        $GLOBALS['APP_USER_NAME'] ?? null,
        $method,
        $path
    );

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
        'mensaje_legible' => $mensajeLegible,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Registra una actividad después de que la sesión ya esté establecida
 * (p. ej. justo después de un login exitoso).
 */
function registrarActividadSesion(string $codigo, string $detalle = ''): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logFile = $logDir . '/requests.log';

    $usuarioNombre = $_SESSION['usuario_nombre'] ?? null;
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $mensajeLegible = mensajeActividadLegible($codigo, $detalle, $usuarioNombre, 'POST', '');

    $entry = [
        'timestamp' => date('c'),
        'request_id' => $GLOBALS['APP_REQUEST_ID'] ?? generateRequestId(),
        'usuario_id' => $usuarioId,
        'usuario_nombre' => $usuarioNombre,
        'method' => 'POST',
        'ruta' => '/login.php',
        'query_string' => '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'post_raw' => [],
        'accion_inferida' => $codigo,
        'detalle_inferida' => $detalle,
        'mensaje_legible' => $mensajeLegible,
    ];

    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
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

