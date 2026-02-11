<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/transacciones.php';

$tipo = $_GET['tipo'] ?? null;
$busqueda = trim($_GET['q'] ?? '');
$transacciones = listarTransaccionesRecientes(50, $tipo, $busqueda !== '' ? $busqueda : null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transacciones - Almacén Cecyte 11</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <div class="logo-icon">S</div>
        <span>Almacén Cecyte 11</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <nav class="nav-links">
      <a href="index.php">Dashboard</a>
      <a href="transacciones.php" class="active">Transacciones</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>

    <section class="section-header">
      <h2>Transacciones recientes</h2>
      <div class="search-filter">
        <form method="get" action="transacciones.php" style="display:flex; gap:0.5rem; align-items:center;">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo ?? '') ?>">
          <input type="search" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar transacciones..." autocomplete="off">
          <button type="submit" class="btn btn-secondary">Buscar</button>
        </form>
        <div class="filter-btns">
          <a href="transacciones.php?tipo=<?= $tipo === null ? '' : '' ?>&q=<?= urlencode($busqueda) ?>" class="btn btn-secondary <?= $tipo === null ? 'active' : '' ?>">Todas</a>
          <a href="transacciones.php?tipo=in&q=<?= urlencode($busqueda) ?>" class="btn btn-secondary <?= $tipo === 'in' ? 'active' : '' ?>">Entradas</a>
          <a href="transacciones.php?tipo=out&q=<?= urlencode($busqueda) ?>" class="btn btn-secondary <?= $tipo === 'out' ? 'active' : '' ?>">Salidas</a>
        </div>
      </div>
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
            <tr><td colspan="6" class="empty-msg">No hay transacciones con los filtros indicados.</td></tr>
          <?php else: ?>
            <?php foreach ($transacciones as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['referencia']) ?></td>
              <td>
                <span class="type-badge <?= $t['tipo'] ?>">
                  <?= $t['tipo'] === 'in' ? '↓ Stock In' : '↑ Stock Out' ?>
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
