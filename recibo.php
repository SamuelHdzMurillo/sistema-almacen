<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/salidas.php';

$id = (int)($_GET['id'] ?? 0);
$salida = $id ? obtenerSalidaConDetalle($id) : null;

if (!$salida) {
    header('Location: transacciones.php');
    exit;
}

$titulo = 'Recibo de salida - ' . $salida['referencia'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <div class="logo-icon">S</div>
        <span>Almacén Cecyte 11</span>
      </div>
      <a href="transacciones.php" class="btn btn-secondary">Volver a transacciones</a>
    </header>

    <div class="recibo">
      <h1>Recibo de entrega</h1>
      <div class="meta">
        <strong>Referencia:</strong> <?= htmlspecialchars($salida['referencia']) ?><br>
        <strong>Fecha:</strong> <?= htmlspecialchars($salida['fecha']) ?><br>
        <strong>Recibe:</strong> <?= htmlspecialchars($salida['nombre_receptor']) ?>
      </div>
      <table>
        <thead>
          <tr>
            <th>Artículo</th>
            <th>Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($salida['detalle'] as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
            <td><?= (int)$d['cantidad'] ?> <?= htmlspecialchars($d['unidad']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="total">Total de ítems: <?= array_sum(array_column($salida['detalle'], 'cantidad')) ?></div>
      <p style="margin-top:1.5rem; font-size:0.85rem; color:#666;">Documento generado automáticamente por el sistema de almacén.</p>
    </div>

    <p style="text-align:center; margin-top:1rem;">
      <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir recibo</button>
    </p>
  </div>
</body>
</html>
