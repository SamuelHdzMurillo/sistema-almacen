<?php
require_once __DIR__ . '/../config/database.php';

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
}

function login(string $usuario, string $clave): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, usuario, clave, nombre FROM usuarios WHERE usuario = ?');
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
