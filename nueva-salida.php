<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/salidas.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/catalogos_salida.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $quienEntregaNuevo = trim($_POST['quien_entrega_nuevo'] ?? '');
    $plantelNuevo = trim($_POST['plantel_nuevo'] ?? '');
    $receptorNuevo = trim($_POST['receptor_nuevo'] ?? '');
    $quienEntregaId = !empty($quienEntregaNuevo) ? null : (int)($_POST['quien_entrega_id'] ?? 0);
    $plantelId = !empty($plantelNuevo) ? null : (int)($_POST['plantel_id'] ?? 0);
    $receptorId = !empty($receptorNuevo) ? null : (int)($_POST['receptor_id'] ?? 0);
    if (!empty($quienEntregaNuevo)) $quienEntregaId = obtenerOcrearQuienEntrega($quienEntregaNuevo);
    if (!empty($plantelNuevo)) $plantelId = obtenerOcrearPlantel($plantelNuevo);
    if (!empty($receptorNuevo)) $receptorId = obtenerOcrearReceptor($receptorNuevo);
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
    if ($quienEntregaId <= 0) {
        $error = 'Seleccione o indique quién entrega el material.';
    } elseif ($plantelId <= 0) {
        $error = 'Seleccione o indique el plantel al que se entrega.';
    } elseif ($receptorId <= 0) {
        $error = 'Seleccione o indique la persona que recibe el material.';
    } elseif (empty($lineas)) {
        $error = 'Añada al menos un producto con cantidad.';
    } else {
        try {
            $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            $salidaId = crearSalida($fecha, $quienEntregaId, $plantelId, $receptorId, $lineas, $usuarioId);
            header('Location: recibo.php?id=' . $salidaId);
            exit;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$productos = listarProductos();
$quienEntrega = listarQuienEntrega();
$planteles = listarPlanteles();
$receptores = listarReceptores();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva salida - Sistema de Almacén</title>
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
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php" class="active">Nueva salida</a>
      <a href="inventario.php">Inventario</a>
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
          <label>Quien entrega (entrega el material)</label>
          <select name="quien_entrega_id" id="quien_entrega_id">
            <option value="">-- Seleccione del catálogo --</option>
            <?php foreach ($quienEntrega as $q): ?>
              <option value="<?= (int)$q['id'] ?>" <?= (isset($_POST['quien_entrega_id']) && (int)$_POST['quien_entrega_id'] === (int)$q['id']) ? 'selected' : '' ?>><?= htmlspecialchars($q['nombre']) ?></option>
            <?php endforeach; ?>
            <option value="nuevo">+ Agregar nuevo al catálogo</option>
          </select>
          <input type="text" name="quien_entrega_nuevo" id="quien_entrega_nuevo" value="<?= htmlspecialchars($_POST['quien_entrega_nuevo'] ?? '') ?>" placeholder="Nombre (si agregó nuevo)" class="form-nuevo-catalogo" style="display:none; margin-top:6px;">
        </div>
        <div class="form-group">
          <label>Plantel al que se entrega</label>
          <select name="plantel_id" id="plantel_id">
            <option value="">-- Seleccione del catálogo --</option>
            <?php foreach ($planteles as $pl): ?>
              <option value="<?= (int)$pl['id'] ?>" <?= (isset($_POST['plantel_id']) && (int)$_POST['plantel_id'] === (int)$pl['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pl['nombre']) ?></option>
            <?php endforeach; ?>
            <option value="nuevo">+ Agregar nuevo al catálogo</option>
          </select>
          <input type="text" name="plantel_nuevo" id="plantel_nuevo" value="<?= htmlspecialchars($_POST['plantel_nuevo'] ?? '') ?>" placeholder="Nombre del plantel (si agregó nuevo)" class="form-nuevo-catalogo" style="display:none; margin-top:6px;">
        </div>
        <div class="form-group">
          <label>Persona que recibe el material</label>
          <select name="receptor_id" id="receptor_id">
            <option value="">-- Seleccione del catálogo --</option>
            <?php foreach ($receptores as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= (isset($_POST['receptor_id']) && (int)$_POST['receptor_id'] === (int)$r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
            <option value="nuevo">+ Agregar nuevo al catálogo</option>
          </select>
          <input type="text" name="receptor_nuevo" id="receptor_nuevo" value="<?= htmlspecialchars($_POST['receptor_nuevo'] ?? '') ?>" placeholder="Nombre (si agregó nuevo)" class="form-nuevo-catalogo" style="display:none; margin-top:6px;">
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
    document.getElementById('quien_entrega_id').addEventListener('change', function() { toggleNuevo('quien_entrega_id', 'quien_entrega_nuevo'); });
    document.getElementById('plantel_id').addEventListener('change', function() { toggleNuevo('plantel_id', 'plantel_nuevo'); });
    document.getElementById('receptor_id').addEventListener('change', function() { toggleNuevo('receptor_id', 'receptor_nuevo'); });
    toggleNuevo('quien_entrega_id', 'quien_entrega_nuevo');
    toggleNuevo('plantel_id', 'plantel_nuevo');
    toggleNuevo('receptor_id', 'receptor_nuevo');
  </script>
</body>
</html>
