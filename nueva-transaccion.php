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
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=12">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Nueva transacción</span>
      </div>
      <a href="logout.php" class="btn btn-secondary">Salir</a>
    </header>
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <div class="form-card">
      <h2 class="form-card-title">Nueva transacción</h2>
      <p class="card-sub">Elija el tipo de movimiento:</p>
      <div class="choice-btns">
        <a href="nueva-entrada.php" class="btn btn-primary"><span class="btn-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg></span>Entrada al almacén</a>
        <a href="nueva-salida.php" class="btn btn-secondary"><span class="btn-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg></span>Salida del almacén</a>
      </div>
    </div>
  </div>
</body>
</html>
