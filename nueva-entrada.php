<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';
require_once __DIR__ . '/includes/productos.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $responsable = trim($_POST['responsable'] ?? '');
    $lineas = [];
    if (!empty($_POST['producto_id']) && is_array($_POST['producto_id'])) {
        foreach ($_POST['producto_id'] as $i => $pid) {
            if (empty($pid)) continue;
            $lineas[] = [
                'producto_id' => $pid,
                'cantidad' => (int)($_POST['cantidad'][$i] ?? 0),
            ];
        }
    }
    if (empty($responsable)) {
        $error = 'Indique el responsable.';
    } elseif (empty($lineas)) {
        $error = 'Añada al menos un producto con cantidad.';
    } else {
        try {
            $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            crearEntrada($fecha, $responsable, $lineas, $usuarioId);
            $mensaje = 'Entrada registrada correctamente (ref. generada automáticamente).';
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$productos = listarProductos();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva entrada - Almacén Cecyte 11</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
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
      <a href="nueva-entrada.php" class="active">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>

    <h1 class="page-title">Nueva entrada al almacén</h1>
    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="nueva-entrada.php">
      <div class="form-card">
        <div class="form-group">
          <label>Fecha</label>
          <input type="date" name="fecha" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
          <label>Responsable</label>
          <input type="text" name="responsable" value="<?= htmlspecialchars($_POST['responsable'] ?? '') ?>" placeholder="Nombre del responsable" required>
        </div>
      </div>

      <div class="form-card">
        <h3 class="form-card-title">Detalle de productos</h3>
        <table class="lineas-table">
          <thead>
            <tr>
              <th>Producto</th>
              <th class="col-qty">Cantidad</th>
              <th class="col-del"></th>
            </tr>
          </thead>
          <tbody id="lineas">
            <tr class="linea">
              <td>
                <select name="producto_id[]" required>
                  <option value="">-- Seleccione --</option>
                  <?php foreach ($productos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['unidad'] ?? 'und') ?>)</option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="cantidad[]" min="1" value="1" required></td>
              <td></td>
            </tr>
          </tbody>
        </table>
        <button type="button" class="btn btn-secondary btn-add-line" id="addLine">+ Añadir línea</button>
      </div>

      <button type="submit" class="btn btn-primary">Registrar entrada</button>
    </form>
  </div>

  <script>
    document.getElementById('addLine').addEventListener('click', function() {
      const tbody = document.getElementById('lineas');
      const first = tbody.querySelector('tr.linea');
      const clone = first.cloneNode(true);
      clone.querySelectorAll('input, select').forEach(function(el) {
        if (el.name && el.name.includes('cantidad')) el.value = 1;
        else if (el.tagName === 'SELECT') el.selectedIndex = 0;
      });
      const lastTd = clone.querySelector('td:last-child');
      lastTd.innerHTML = '<button type="button" class="btn btn-secondary btn-sm" onclick="this.closest(\'tr\').remove()">✕</button>';
      tbody.appendChild(clone);
    });
  </script>
</body>
</html>
