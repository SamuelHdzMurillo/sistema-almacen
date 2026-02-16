<?php
require_once __DIR__ . '/includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (estaLogueado()) {
    header('Location: ' . ($_GET['redir'] ?? 'index.php'));
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['clave'] ?? '';
    if ($user === '' || $pass === '') {
        $error = 'Ingrese usuario y contraseña.';
    } elseif (login($user, $pass)) {
        header('Location: ' . ($_GET['redir'] ?? 'index.php'));
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión - Sistema de Almacén</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body class="login-page">
  <div class="login-box">
    <div class="login-header">
      <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img logo-img-lg">
      <h1>Sistema de Almacén</h1>
      <p>Panel de administración</p>
    </div>
    <form method="post" class="login-form">
      <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <div class="form-group">
        <label>Usuario</label>
        <input type="text" name="usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" autocomplete="username" required autofocus>
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <input type="password" name="clave" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
  </div>
</body>
</html>
