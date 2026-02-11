<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/inventario.php';

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '') ?: null;
    if ($nombre !== '') {
        try {
            $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            crearProducto(['nombre' => $nombre, 'codigo' => $codigo, 'descripcion' => trim($_POST['descripcion'] ?? ''), 'unidad' => $_POST['unidad'] ?? 'und'], $usuarioId);
            $mensaje = 'Producto creado correctamente.';
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
        }
    }
}

$productos = listarProductos();
$inventario = inventarioPorProducto();
$siguienteCodigo = obtenerSiguienteCodigoProducto();
$stockPorId = [];
foreach ($inventario as $inv) {
    $stockPorId[(int)$inv['id']] = (int)$inv['stock'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Productos - Almacén Cecyte 11</title>
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
      <a href="transacciones.php">Transacciones</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php" class="active">Productos</a>
    </nav>

    <h1>Productos</h1>
    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <div class="form-card">
      <h3 style="margin-top:0;">Nuevo producto</h3>
      <form method="post">
        <input type="hidden" name="crear" value="1">
        <div class="form-group">
          <label>Nombre</label>
          <input type="text" name="nombre" required placeholder="Nombre del producto">
        </div>
        <div class="form-group">
          <label>Código (opcional)</label>
          <input type="text" name="codigo" value="<?= htmlspecialchars($siguienteCodigo) ?>" placeholder="<?= htmlspecialchars($siguienteCodigo) ?>">
        </div>
        <div class="form-group">
          <label>Descripción (opcional)</label>
          <input type="text" name="descripcion" placeholder="Breve descripción">
        </div>
        <div class="form-group">
          <label>Unidad</label>
          <select name="unidad">
            <option value="und">und</option>
            <option value="caja">caja</option>
            <option value="rollo">rollo</option>
            <option value="kg">kg</option>
            <option value="L">L</option>
            <option value="m">m</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Crear producto</button>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Unidad</th>
            <th>Stock actual</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($productos)): ?>
            <tr><td colspan="4" class="empty-msg">No hay productos. Cree uno arriba.</td></tr>
          <?php else: ?>
            <?php foreach ($productos as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['codigo'] ?? '-') ?></td>
              <td><?= htmlspecialchars($p['nombre']) ?></td>
              <td><?= htmlspecialchars($p['unidad'] ?? 'und') ?></td>
              <td><?= $stockPorId[(int)$p['id']] ?? 0 ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
