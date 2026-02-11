<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/inventario.php';
require_once __DIR__ . '/includes/entradas.php';
require_once __DIR__ . '/includes/salidas.php';
require_once __DIR__ . '/includes/transacciones.php';

$totalItems = totalItems();
$cap = capacidadTotal();
$transacciones = listarTransaccionesRecientes(10);

$pdo = getDB();
$totalEntradas = (int) $pdo->query("SELECT COALESCE(SUM(de.cantidad), 0) AS t FROM detalle_entradas de JOIN entradas e ON e.id = de.entrada_id WHERE e.estado = 'completada'")->fetch()['t'];
$totalSalidas = (int) $pdo->query("SELECT COALESCE(SUM(ds.cantidad), 0) AS t FROM detalle_salidas ds JOIN salidas s ON s.id = ds.salida_id WHERE s.estado = 'completada'")->fetch()['t'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de control - Almacén Cecyte 11</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Panel de administración</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <nav class="nav-links">
      <a href="index.php" class="active">Inicio</a>
      <a href="transacciones.php">Transacciones</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>

    <section class="cards-grid">
      <div class="card card-destacada">
        <div class="card-etiqueta">Total artículos</div>
        <div class="card-valor"><?= number_format($totalItems) ?></div>
        <div class="card-desc">En almacén</div>
      </div>
      <div class="card">
        <div class="card-etiqueta">Artículos entrados</div>
        <div class="card-valor"><?= number_format($totalEntradas) ?></div>
        <div class="card-desc">Total acumulado</div>
      </div>
      <div class="card">
        <div class="card-etiqueta">Artículos salidos</div>
        <div class="card-valor"><?= number_format($totalSalidas) ?></div>
        <div class="card-desc">Total acumulado</div>
      </div>
    </section>

    <section class="section-header">
      <h2>Transacciones recientes</h2>
      <a href="transacciones.php" class="btn btn-secondary">Ver todas</a>
    </section>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Referencia</th>
            <th>Tipo</th>
            <th>Artículo</th>
            <th>Cant.</th>
            <th>Fecha</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transacciones)): ?>
            <tr><td colspan="6" class="empty-msg">No hay transacciones aún.</td></tr>
          <?php else: ?>
            <?php foreach ($transacciones as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['referencia']) ?></td>
              <td>
                <span class="type-badge <?= $t['tipo'] ?>">
                  <?= $t['tipo'] === 'in' ? 'Entrada' : 'Salida' ?>
                </span>
              </td>
              <td><?= htmlspecialchars($t['item_nombre']) ?></td>
              <td class="<?= $t['tipo'] === 'in' ? 'qty-pos' : 'qty-neg' ?>"><?= $t['cantidad_show'] ?></td>
              <td><?= htmlspecialchars($t['fecha']) ?></td>
              <td><span class="status-badge status-<?= $t['estado'] ?>"><?= $t['estado'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
