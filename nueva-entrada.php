<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/catalogos_entrada.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $proveedorNuevo = trim($_POST['proveedor_nuevo'] ?? '');
    $quienRecibeNuevo = trim($_POST['quien_recibe_nuevo'] ?? '');
    $proveedorId = !empty($proveedorNuevo) ? null : (int)($_POST['proveedor_id'] ?? 0);
    $quienRecibeId = !empty($quienRecibeNuevo) ? null : (int)($_POST['quien_recibe_id'] ?? 0);
    if (!empty($proveedorNuevo)) $proveedorId = obtenerOcrearProveedor($proveedorNuevo);
    if (!empty($quienRecibeNuevo)) $quienRecibeId = obtenerOcrearQuienRecibeEntrada($quienRecibeNuevo);
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
    if ($proveedorId <= 0) {
        $error = 'Seleccione o indique el proveedor.';
    } elseif ($quienRecibeId <= 0) {
        $error = 'Seleccione o indique quién recibe en almacén.';
    } elseif (empty($lineas)) {
        $error = 'Añada al menos un producto con cantidad.';
    } else {
        try {
            $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            $entradaId = crearEntrada($fecha, $proveedorId, $quienRecibeId, $lineas, $usuarioId);
            header('Location: recibo-entrada.php?id=' . $entradaId);
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$productos = listarProductos();
$proveedores = listarProveedores();
$quienRecibeEntrada = listarQuienRecibeEntrada();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva entrada - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
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
      <a href="inventario.php">Inventario</a>
      <a href="productos.php">Productos</a>
    </nav>

    <h1 class="page-title">Nueva entrada al almacén</h1>
    <p class="card-sub">Al guardar se generará el recibo de entrada con los datos indicados.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="nueva-entrada.php">
      <div class="form-card">
        <div class="form-group">
          <label>Fecha</label>
          <input type="date" name="fecha" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
          <label>Proveedor</label>
          <select name="proveedor_id" id="proveedor_id">
            <option value="">-- Seleccione del catálogo --</option>
            <?php foreach ($proveedores as $prov): ?>
              <option value="<?= (int)$prov['id'] ?>" <?= (isset($_POST['proveedor_id']) && (int)$_POST['proveedor_id'] === (int)$prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['nombre']) ?></option>
            <?php endforeach; ?>
            <option value="nuevo">+ Agregar nuevo al catálogo</option>
          </select>
          <input type="text" name="proveedor_nuevo" id="proveedor_nuevo" value="<?= htmlspecialchars($_POST['proveedor_nuevo'] ?? '') ?>" placeholder="Nombre del proveedor (si agregó nuevo)" class="form-nuevo-catalogo" style="display:none; margin-top:6px;">
        </div>
        <div class="form-group">
          <label>Quien recibe (en almacén)</label>
          <select name="quien_recibe_id" id="quien_recibe_id">
            <option value="">-- Seleccione del catálogo --</option>
            <?php foreach ($quienRecibeEntrada as $qr): ?>
              <option value="<?= (int)$qr['id'] ?>" <?= (isset($_POST['quien_recibe_id']) && (int)$_POST['quien_recibe_id'] === (int)$qr['id']) ? 'selected' : '' ?>><?= htmlspecialchars($qr['nombre']) ?></option>
            <?php endforeach; ?>
            <option value="nuevo">+ Agregar nuevo al catálogo</option>
          </select>
          <input type="text" name="quien_recibe_nuevo" id="quien_recibe_nuevo" value="<?= htmlspecialchars($_POST['quien_recibe_nuevo'] ?? '') ?>" placeholder="Nombre (si agregó nuevo)" class="form-nuevo-catalogo" style="display:none; margin-top:6px;">
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

      <button type="submit" class="btn btn-primary">Registrar entrada y generar recibo</button>
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
    function toggleNuevo(selId, inputId) {
      var sel = document.getElementById(selId);
      var input = document.getElementById(inputId);
      if (sel.value === 'nuevo') {
        input.style.display = 'block';
        input.required = true;
        sel.removeAttribute('required');
      } else {
        input.style.display = 'none';
        input.required = false;
        input.value = '';
        sel.required = true;
      }
    }
    document.getElementById('proveedor_id').addEventListener('change', function() { toggleNuevo('proveedor_id', 'proveedor_nuevo'); });
    document.getElementById('quien_recibe_id').addEventListener('change', function() { toggleNuevo('quien_recibe_id', 'quien_recibe_nuevo'); });
    toggleNuevo('proveedor_id', 'proveedor_nuevo');
    toggleNuevo('quien_recibe_id', 'quien_recibe_nuevo');
  </script>
</body>
</html>
