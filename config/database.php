<?php
/**
 * Conexión a la base de datos - Sistema de Almacén
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_almacen');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if (!isset($GLOBALS['APP_REQUEST_ID'])) {
        // Si por alguna razón el request logger aún no se inicializó (p.ej. en tests),
        // lo inicializamos aquí para que los triggers/auditoría tengan correlación.
        if (function_exists('initRequestLogging')) {
            initRequestLogging();
        } else {
            require_once __DIR__ . '/request_logger.php';
            initRequestLogging();
        }
    }
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STATEMENT_CLASS   => [LoggedPDOStatement::class, []],
        ];
        require_once __DIR__ . '/LoggedPDOStatement.php';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }

    // Contexto de auditoría para triggers (por request).
    $requestId = $GLOBALS['APP_REQUEST_ID'] ?? null;
    $userId = null;
    if (isset($_SESSION) && isset($_SESSION['usuario_id'])) {
        $userId = (int)$_SESSION['usuario_id'];
    } elseif (isset($GLOBALS['APP_USER_ID'])) {
        $userId = $GLOBALS['APP_USER_ID'];
    }

    $requestSql = $requestId !== null ? $pdo->quote((string)$requestId) : 'NULL';
    $userSql = $userId !== null ? (int)$userId : 'NULL';
    $pdo->exec('SET @app_request_id = ' . $requestSql . ', @app_user_id = ' . $userSql);

    return $pdo;
}
