<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/salidas.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/inventario.php';
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
$inventario = inventarioPorProducto();
$stockPorId = [];
foreach ($inventario as $inv) {
    $stockPorId[(int)$inv['id']] = (int)($inv['stock'] ?? 0);
}
$stockInfo = [];
foreach ($productos as $p) {
    $id = (int)$p['id'];
    $stockInfo[$id] = ['stock' => $stockPorId[$id] ?? 0, 'unidad' => $p['unidad'] ?? 'und'];
}
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
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body class="pagina-nueva-salida">
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
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <header class="page-header">
      <div class="page-header-texto">
        <h1 class="page-title">Nueva salida del almacén</h1>
        <p class="page-header-subtitulo">Al guardar se generará automáticamente el recibo con los datos indicados.</p>
      </div>
    </header>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="nueva-salida.php" id="formSalida" onsubmit="return validarSalida();">
      <div class="form-layout-dual">
      <div class="form-card form-card--salida form-card--datos">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg></span>
          <h3 class="form-card-title">Datos del documento</h3>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="fecha_salida">Fecha</label>
            <input type="date" name="fecha" id="fecha_salida" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div class="form-group form-group--full">
            <label for="quien_entrega_id">Quien entrega (entrega el material)</label>
            <select name="quien_entrega_id" id="quien_entrega_id" aria-describedby="quien_entrega_hint">
              <option value="">— Seleccione del catálogo —</option>
              <?php foreach ($quienEntrega as $q): ?>
                <option value="<?= (int)$q['id'] ?>" <?= (isset($_POST['quien_entrega_id']) && (int)$_POST['quien_entrega_id'] === (int)$q['id']) ? 'selected' : '' ?>><?= htmlspecialchars($q['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo">+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="quien_entrega_nuevo" id="quien_entrega_nuevo" value="<?= htmlspecialchars($_POST['quien_entrega_nuevo'] ?? '') ?>" placeholder="Nombre de quien entrega" class="form-nuevo-catalogo" style="display:none;" aria-label="Nombre (nuevo)">
            <span id="quien_entrega_hint" class="form-hint">Persona que entrega el material desde almacén.</span>
          </div>
          <div class="form-group form-group--full">
            <label for="plantel_id">Plantel al que se entrega</label>
            <select name="plantel_id" id="plantel_id" aria-describedby="plantel_hint">
              <option value="">— Seleccione del catálogo —</option>
              <?php foreach ($planteles as $pl): ?>
                <option value="<?= (int)$pl['id'] ?>" <?= (isset($_POST['plantel_id']) && (int)$_POST['plantel_id'] === (int)$pl['id']) ? 'selected' : '' ?>><?= htmlspecialchars($pl['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo">+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="plantel_nuevo" id="plantel_nuevo" value="<?= htmlspecialchars($_POST['plantel_nuevo'] ?? '') ?>" placeholder="Nombre del plantel" class="form-nuevo-catalogo" style="display:none;" aria-label="Plantel (nuevo)">
            <span id="plantel_hint" class="form-hint">Destino del material.</span>
          </div>
          <div class="form-group form-group--full">
            <label for="receptor_id">Persona que recibe el material</label>
            <select name="receptor_id" id="receptor_id" aria-describedby="receptor_hint">
              <option value="">— Seleccione del catálogo —</option>
              <?php foreach ($receptores as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= (isset($_POST['receptor_id']) && (int)$_POST['receptor_id'] === (int)$r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo">+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="receptor_nuevo" id="receptor_nuevo" value="<?= htmlspecialchars($_POST['receptor_nuevo'] ?? '') ?>" placeholder="Nombre del receptor" class="form-nuevo-catalogo" style="display:none;" aria-label="Receptor (nuevo)">
            <span id="receptor_hint" class="form-hint">Quien recibe el material en destino.</span>
          </div>
        </div>
      </div>

      <div class="form-card form-card--wide form-card--salida form-card--detalle">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
          <h3 class="form-card-title">Artículos a entregar</h3>
        </div>
        <p class="form-card-sub">Seleccione los productos y cantidades. Se muestra el stock disponible en almacén.</p>
        <div class="table-wrap">
          <table class="lineas-table">
            <thead>
              <tr>
                <th>Producto</th>
                <th class="col-stock">En almacén (actual)</th>
                <th class="col-qty">Cantidad a entregar</th>
                <th class="col-del"></th>
              </tr>
            </thead>
            <tbody id="lineas">
              <tr class="linea">
                <td>
                  <select name="producto_id[]" required aria-label="Producto">
                    <option value="">— Seleccione producto —</option>
                    <?php foreach ($productos as $p): ?>
                      <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['unidad'] ?? 'und') ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="col-stock"><span class="stock-display" aria-live="polite">—</span><span class="stock-warning-msg" role="alert"></span></td>
                <td><input type="number" name="cantidad[]" min="1" value="1" required aria-label="Cantidad a entregar"></td>
                <td></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary btn-add-line" id="addLine">+ Añadir línea</button>
          <button type="submit" class="btn btn-primary">Registrar salida y generar recibo</button>
        </div>
      </div>
      </div>
    </form>
  </div>

  <script>
    var stockPorProducto = <?= json_encode($stockInfo) ?>;

    function actualizarStockEnFila(fila) {
      var sel = fila.querySelector('select[name="producto_id[]"]');
      var span = fila.querySelector('.stock-display');
      var warnSpan = fila.querySelector('.stock-warning-msg');
      if (!sel || !span) return;
      var id = sel.value;
      if (warnSpan) warnSpan.textContent = '';
      if (!id) {
        span.textContent = '—';
        if (warnSpan) warnSpan.className = 'stock-warning-msg';
        return;
      }
      var info = stockPorProducto[id];
      if (!info) {
        span.textContent = '—';
        return;
      }
      var u = info.unidad || 'und';
      var stock = info.stock || 0;
      span.textContent = stock + ' ' + u;
      if (warnSpan) {
        var cantInput = fila.querySelector('input[name="cantidad[]"]');
        var cant = cantInput ? parseInt(cantInput.value, 10) : 0;
        if (cant > stock) {
          warnSpan.textContent = 'Cantidad mayor al stock';
          warnSpan.className = 'stock-warning-msg stock-warning';
        } else {
          warnSpan.className = 'stock-warning-msg';
        }
      }
    }

    function revisarCantidadEnFila(fila) {
      var sel = fila.querySelector('select[name="producto_id[]"]');
      var warnSpan = fila.querySelector('.stock-warning-msg');
      if (!sel || !warnSpan) return;
      var id = sel.value;
      if (!id) { warnSpan.textContent = ''; warnSpan.className = 'stock-warning-msg'; return; }
      var info = stockPorProducto[id];
      if (!info) return;
      var cantInput = fila.querySelector('input[name="cantidad[]"]');
      var cant = cantInput ? parseInt(cantInput.value, 10) : 0;
      if (cant > info.stock) {
        warnSpan.textContent = 'Cantidad mayor al stock';
        warnSpan.className = 'stock-warning-msg stock-warning';
      } else {
        warnSpan.textContent = '';
        warnSpan.className = 'stock-warning-msg';
      }
    }

    function validarSalida() {
      var hayExceso = false;
      document.querySelectorAll('#lineas tr.linea').forEach(function(tr) {
        var sel = tr.querySelector('select[name="producto_id[]"]');
        var cantInput = tr.querySelector('input[name="cantidad[]"]');
        if (!sel || !sel.value || !cantInput) return;
        var info = stockPorProducto[sel.value];
        if (info && parseInt(cantInput.value, 10) > info.stock) hayExceso = true;
      });
      if (hayExceso && !confirm('Hay líneas con cantidad mayor al stock disponible. ¿Desea continuar de todos modos?')) {
        return false;
      }
      return confirm('¿Está seguro de que desea continuar y registrar la salida?');
    }

    document.getElementById('lineas').addEventListener('change', function(e) {
      if (e.target && e.target.matches('select[name="producto_id[]"]')) {
        actualizarStockEnFila(e.target.closest('tr'));
      }
    });
    document.getElementById('lineas').addEventListener('input', function(e) {
      if (e.target && e.target.matches('input[name="cantidad[]"]')) {
        revisarCantidadEnFila(e.target.closest('tr'));
      }
    });

    document.getElementById('addLine').addEventListener('click', function() {
      var tbody = document.getElementById('lineas');
      var first = tbody.querySelector('tr.linea');
      var clone = first.cloneNode(true);
      clone.querySelectorAll('input, select').forEach(function(el) {
        if (el.name && el.name.includes('cantidad')) el.value = 1;
        else if (el.tagName === 'SELECT') el.selectedIndex = 0;
      });
      var stockSpan = clone.querySelector('.stock-display');
      if (stockSpan) stockSpan.textContent = '—';
      var warnSpan = clone.querySelector('.stock-warning-msg');
      if (warnSpan) { warnSpan.textContent = ''; warnSpan.className = 'stock-warning-msg'; }
      var lastTd = clone.querySelector('td:last-child');
      lastTd.innerHTML = '<button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="this.closest(\'tr\').remove()" title="Quitar línea" aria-label="Quitar línea"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
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
    document.querySelectorAll('#lineas tr.linea').forEach(actualizarStockEnFila);
  </script>
</body>
</html>
