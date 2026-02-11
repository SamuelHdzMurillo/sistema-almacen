<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/salidas.php';
require_once __DIR__ . '/includes/productos.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $nombreEntrega = trim($_POST['nombre_entrega'] ?? '');
    $nombreReceptor = trim($_POST['nombre_receptor'] ?? '');
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
    if (empty($nombreEntrega)) {
        $error = 'Indique el nombre de la persona que entrega los artículos.';
    } elseif (empty($nombreReceptor)) {
        $error = 'Indique el nombre de la persona que recibe los artículos.';
    } elseif (empty($lineas)) {
        $error = 'Añada al menos un producto con cantidad.';
    } else {
        try {
            $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            $salidaId = crearSalida($fecha, $nombreEntrega, $nombreReceptor, $lineas, $usuarioId);
            header('Location: recibo.php?id=' . $salidaId);
            exit;
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
  <title>Nueva salida - Almacén Cecyte 11</title>
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
      <a href="nueva-salida.php" class="active">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>

    <h1 class="page-title">Nueva salida del almacén</h1>
    <p class="card-sub">Al guardar se generará automáticamente el recibo con los datos indicados.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="nueva-salida.php">
      <div class="form-card">
        <div class="form-group">
          <label>Fecha</label>
          <input type="date" name="fecha" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
          <label>Nombre de quien entrega (entrega el material)</label>
          <input type="text" name="nombre_entrega" value="<?= htmlspecialchars($_POST['nombre_entrega'] ?? '') ?>" placeholder="Nombre completo de quien entrega" required>
        </div>
        <div class="form-group">
          <label>Nombre de quien recibe (recibe los artículos)</label>
          <input type="text" name="nombre_receptor" value="<?= htmlspecialchars($_POST['nombre_receptor'] ?? '') ?>" placeholder="Nombre completo de quien recibe" required>
        </div>
      </div>

      <div class="form-card">
        <h3 class="form-card-title">Artículos a entregar</h3>
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

      <button type="submit" class="btn btn-primary">Registrar salida y generar recibo</button>
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
