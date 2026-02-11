<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva transacción - Sistema de Almacén</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
      </div>
      <a href="logout.php" class="btn btn-secondary">Salir</a>
    </header>
    <nav class="nav-links">
      <a href="index.php">Dashboard</a>
      <a href="transacciones.php">Transacciones</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>
    <div class="form-card">
      <h2 class="form-card-title">Nueva transacción</h2>
      <p class="card-sub">Elija el tipo de movimiento:</p>
      <div class="choice-btns">
        <a href="nueva-entrada.php" class="btn btn-primary">↓ Entrada al almacén</a>
        <a href="nueva-salida.php" class="btn btn-secondary">↑ Salida del almacén</a>
      </div>
    </div>
  </div>
</body>
</html>
