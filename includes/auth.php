<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/request_logger.php';
require_once __DIR__ . '/almacenes.php';
initRequestLogging();

function estaLogueado(): bool {
    return !empty($_SESSION['usuario_id']);
}

function requerirLogin(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!estaLogueado()) {
        header('Location: login.php?redir=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }

    // Asegura que exista un almacén activo en sesión.
    asegurarAlmacenActivo();
}

function login(string $usuario, string $clave): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, usuario, clave, nombre, almacen_id FROM usuarios WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $u = $stmt->fetch();
    if (!$u || !password_verify($clave, $u['clave'])) {
        return false;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['usuario_id'] = $u['id'];
    $_SESSION['usuario_nombre'] = $u['nombre'] ?: $u['usuario'];

    // Si el usuario ya tiene almacén asignado, lo ponemos en sesión.
    if (isset($u['almacen_id']) && (int)$u['almacen_id'] > 0) {
        $_SESSION['almacen_id'] = (int)$u['almacen_id'];
    }

    // Si es admin y no hay almacén aún, se resolverá en asegurarAlmacenActivo().
    asegurarAlmacenActivo();
    return true;
}

function logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
