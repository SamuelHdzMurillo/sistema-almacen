<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/inventario.php';
require_once __DIR__ . '/includes/catalogos_entrada.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $factura = trim((string)($_POST['factura'] ?? ''));
    if ($factura === '') $factura = null;
    $proveedorNuevo = trim($_POST['proveedor_nuevo'] ?? '');
    $quienRecibeNuevo = trim($_POST['quien_recibe_nuevo'] ?? '');
    $proveedorId = !empty($proveedorNuevo) ? null : (int)($_POST['proveedor_id'] ?? 0);
    $quienRecibeId = !empty($quienRecibeNuevo) ? null : (int)($_POST['quien_recibe_id'] ?? 0);
    $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
    if (!empty($proveedorNuevo)) $proveedorId = obtenerOcrearProveedor($proveedorNuevo);
    if (!empty($quienRecibeNuevo)) $quienRecibeId = obtenerOcrearQuienRecibeEntrada($quienRecibeNuevo);
    $lineas = [];
    $huboErrorAltaRapidaProducto = false;
    if (!empty($_POST['producto_id']) && is_array($_POST['producto_id'])) {
        foreach ($_POST['producto_id'] as $i => $pid) {
            $cantidad = (int)($_POST['cantidad'][$i] ?? 0);
            if ($cantidad <= 0) continue;

            $pid = is_string($pid) ? trim($pid) : $pid;
            if ($pid === 'nuevo') {
                $nombreNuevo = trim($_POST['producto_nuevo_nombre'][$i] ?? '');
                $codigoNuevo = trim($_POST['producto_nuevo_codigo'][$i] ?? '') ?: null;
                $descripcionNuevo = trim($_POST['producto_nuevo_descripcion'][$i] ?? '');
                $unidadSeleccionada = trim($_POST['producto_nuevo_unidad'][$i] ?? '');
                $unidadNueva = trim($_POST['producto_nuevo_unidad_nueva'][$i] ?? '');

                if ($nombreNuevo === '') {
                    $error = 'Indique el nombre del producto para la alta rápida (línea ' . ((int)$i + 1) . ').';
                    $huboErrorAltaRapidaProducto = true;
                    break;
                }
                if ($unidadSeleccionada === '') {
                    $error = 'Seleccione la unidad para la alta rápida (línea ' . ((int)$i + 1) . ').';
                    $huboErrorAltaRapidaProducto = true;
                    break;
                }
                if ($unidadSeleccionada === 'otra') {
                    if ($unidadNueva === '') {
                        $error = 'Indique la unidad nueva para la alta rápida (línea ' . ((int)$i + 1) . ').';
                        $huboErrorAltaRapidaProducto = true;
                        break;
                    }
                    $unidadSeleccionada = substr($unidadNueva, 0, 20);
                }

                $lineas[] = [
                    'producto_id' => 'nuevo',
                    'cantidad' => $cantidad,
                    'nuevo' => [
                        'codigo' => $codigoNuevo,
                        'nombre' => $nombreNuevo,
                        'descripcion' => $descripcionNuevo !== '' ? $descripcionNuevo : null,
                        'unidad' => $unidadSeleccionada,
                    ],
                ];
            } else {
                if (empty($pid)) continue;
                $lineas[] = [
                    'producto_id' => (int)$pid,
                    'cantidad' => $cantidad,
                ];
            }
        }
    }
    if (!$huboErrorAltaRapidaProducto) {
        if ($proveedorId <= 0) {
            $error = 'Seleccione o indique el proveedor.';
        } elseif ($quienRecibeId <= 0) {
            $error = 'Seleccione o indique quién recibe en almacén.';
        } elseif (empty($lineas)) {
            $error = 'Añada al menos un producto con cantidad.';
        } else {
            try {
                // Si hay líneas con alta rápida, creamos esos productos antes de registrar la entrada.
                foreach ($lineas as &$l) {
                    if (($l['producto_id'] ?? null) === 'nuevo' && !empty($l['nuevo'])) {
                        // Si no se indicó código, generamos el siguiente disponible
                        // para mantener el autoincremento por catálogo de productos.
                        if (empty($l['nuevo']['codigo'])) {
                            $l['nuevo']['codigo'] = obtenerSiguienteCodigoProducto();
                        }
                        $l['producto_id'] = crearProducto($l['nuevo'], $usuarioId);
                        unset($l['nuevo']);
                    }
                }
                unset($l);

                $entradaId = crearEntrada($fecha, $proveedorId, $quienRecibeId, $lineas, $factura, $usuarioId);
                header('Location: recibo-entrada.php?id=' . $entradaId);
                exit;
            } catch (Exception $e) {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

$productos = listarProductos();
$siguienteCodigo = obtenerSiguienteCodigoProducto();
$unidadesDisponibles = listarUnidadesDisponibles();
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
  <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>
<body class="pagina-nueva-entrada">
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
        <h1 class="page-title">Nueva entrada al almacén</h1>
        <p class="page-header-subtitulo">Al guardar se generará el recibo de entrada con los datos indicados.</p>
      </div>
    </header>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="nueva-entrada.php" id="formEntrada" onsubmit="return confirm('¿Está seguro de que desea continuar y registrar la entrada?');">
      <div class="form-layout-dual">
      <div class="form-card form-card--entrada form-card--datos">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg></span>
          <h3 class="form-card-title">Datos del documento</h3>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="fecha_entrada">Fecha</label>
            <input type="date" name="fecha" id="fecha_entrada" value="<?= htmlspecialchars($_POST['fecha'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div class="form-group">
            <label for="factura">Factura / Orden</label>
            <input type="text" name="factura" id="factura" value="<?= htmlspecialchars($_POST['factura'] ?? '') ?>" placeholder="Ej. F-1234 (opcional)" maxlength="50">
          </div>
          <div class="form-group form-group--full">
            <label for="proveedor_id">Proveedor</label>
            <select name="proveedor_id" id="proveedor_id" aria-describedby="proveedor_hint">
              <option value="">— Seleccione del catálogo —</option>
              <?php foreach ($proveedores as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>" <?= (isset($_POST['proveedor_id']) && (int)$_POST['proveedor_id'] === (int)$prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo">+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="proveedor_nuevo" id="proveedor_nuevo" value="<?= htmlspecialchars($_POST['proveedor_nuevo'] ?? '') ?>" placeholder="Nombre del proveedor" class="form-nuevo-catalogo" style="display:none;" aria-label="Nombre del nuevo proveedor">
            <span id="proveedor_hint" class="form-hint">Puede elegir un proveedor existente o agregar uno nuevo.</span>
          </div>
          <div class="form-group form-group--full">
            <label for="quien_recibe_id">Quien recibe (en almacén)</label>
            <select name="quien_recibe_id" id="quien_recibe_id" aria-describedby="quien_recibe_hint">
              <option value="">— Seleccione del catálogo —</option>
              <?php foreach ($quienRecibeEntrada as $qr): ?>
                <option value="<?= (int)$qr['id'] ?>" <?= (isset($_POST['quien_recibe_id']) && (int)$_POST['quien_recibe_id'] === (int)$qr['id']) ? 'selected' : '' ?>><?= htmlspecialchars($qr['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo">+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="quien_recibe_nuevo" id="quien_recibe_nuevo" value="<?= htmlspecialchars($_POST['quien_recibe_nuevo'] ?? '') ?>" placeholder="Nombre de quien recibe" class="form-nuevo-catalogo" style="display:none;" aria-label="Nombre de quien recibe (nuevo)">
            <span id="quien_recibe_hint" class="form-hint">Persona que recibe el material en almacén.</span>
          </div>
        </div>
      </div>

      <div class="form-card form-card--wide form-card--entrada form-card--detalle">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
          <h3 class="form-card-title">Detalle de productos</h3>
        </div>
        <p class="form-card-sub">Indique los productos que ingresan y la cantidad de cada uno. Se muestra el stock actual en almacén.</p>
        <div class="table-wrap">
          <table class="lineas-table">
            <thead>
              <tr>
                <th>Producto</th>
                <th class="col-stock">En almacén</th>
                <th class="col-qty">Cantidad que entra</th>
                <th class="col-del"></th>
              </tr>
            </thead>
            <tbody id="lineas">
              <tr class="linea">
                <td>
                  <select name="producto_id[]" required aria-label="Producto">
                    <option value="">— Seleccione producto —</option>
                    <option value="nuevo">+ Alta rápida de producto</option>
                    <?php foreach ($productos as $p): ?>
                      <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['unidad'] ?? 'und') ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-nuevo-catalogo producto-nuevo-container">
                    <div class="form-group">
                      <label>Nombre del producto</label>
                      <input
                        type="text"
                        name="producto_nuevo_nombre[]"
                        class="producto-nuevo-nombre"
                        placeholder="Nombre del producto"
                        disabled
                        required
                      >
                    </div>

                    <div class="form-group">
                      <label>Código</label>
                      <input
                        type="text"
                        name="producto_nuevo_codigo[]"
                        class="producto-nuevo-codigo"
                        placeholder="<?= htmlspecialchars($siguienteCodigo) ?>"
                        disabled
                      >
                    </div>

                    <div class="form-group">
                      <label>Descripción</label>
                      <input
                        type="text"
                        name="producto_nuevo_descripcion[]"
                        class="producto-nuevo-descripcion"
                        placeholder="Descripción (opcional)"
                        disabled
                      >
                    </div>

                    <div class="form-group">
                      <label>Unidad</label>
                      <select name="producto_nuevo_unidad[]" class="producto-nuevo-unidad" disabled required>
                        <option value="">— Unidad —</option>
                        <?php foreach ($unidadesDisponibles as $u): ?>
                          <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                        <?php endforeach; ?>
                        <option value="otra">Otra...</option>
                      </select>
                      <div class="wrap-unidad-nueva-producto">
                        <input
                          type="text"
                          name="producto_nuevo_unidad_nueva[]"
                          class="producto-nuevo-unidad-nueva"
                          placeholder="Unidad nueva"
                          maxlength="20"
                          disabled
                        >
                      </div>
                    </div>
                  </div>
                </td>
                <td class="col-stock"><span class="stock-display" aria-live="polite">—</span></td>
                <td><input type="number" name="cantidad[]" min="1" value="1" required aria-label="Cantidad que entra"></td>
                <td></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary btn-add-line" id="addLine">+ Añadir línea</button>
          <button type="submit" class="btn btn-primary">Registrar entrada y generar recibo</button>
        </div>
      </div>
      </div>
    </form>
  </div>

  <script>
    var stockPorProducto = <?= json_encode($stockInfo) ?>;
    var siguienteCodigoInicial = <?= json_encode($siguienteCodigo) ?>;
    var siguienteCodigoParaAsignar = siguienteCodigoInicial;

    function codigoSiguienteLocal(codigo) {
      codigo = String(codigo ?? '').trim();
      if (!codigo) return siguienteCodigoInicial;
      var m = codigo.match(/^(.+?)(\d+)$/);
      if (!m) return siguienteCodigoInicial;
      var prefijo = m[1];
      var numero = parseInt(m[2], 10);
      var longitud = m[2].length;
      var siguiente = numero + 1;
      var sufijo = String(siguiente).padStart(longitud, '0');
      return prefijo + sufijo;
    }

    function toggleUnidadNuevaEnFila(fila) {
      var selUnidad = fila.querySelector('select[name="producto_nuevo_unidad[]"]');
      var wrapUnidadNueva = fila.querySelector('.wrap-unidad-nueva-producto');
      var inputUnidadNueva = fila.querySelector('input[name="producto_nuevo_unidad_nueva[]"]');
      if (!selUnidad || !wrapUnidadNueva || !inputUnidadNueva) return;
      var esOtra = selUnidad.value === 'otra';
      wrapUnidadNueva.style.display = esOtra ? 'block' : 'none';
      inputUnidadNueva.disabled = !esOtra;
      if (!esOtra) inputUnidadNueva.value = '';
    }

    function toggleProductoNuevoEnFila(fila) {
      var selProducto = fila.querySelector('select[name="producto_id[]"]');
      var contNuevo = fila.querySelector('.producto-nuevo-container');
      var inputNombre = fila.querySelector('input[name="producto_nuevo_nombre[]"]');
      var inputCodigo = fila.querySelector('input[name="producto_nuevo_codigo[]"]');
      var inputDescripcion = fila.querySelector('input[name="producto_nuevo_descripcion[]"]');
      var selUnidad = fila.querySelector('select[name="producto_nuevo_unidad[]"]');
      if (!selProducto || !contNuevo || !inputNombre || !inputCodigo || !inputDescripcion || !selUnidad) return;

      var activar = selProducto.value === 'nuevo';
      contNuevo.style.display = activar ? 'block' : 'none';

      inputNombre.disabled = !activar;
      inputNombre.required = activar;
      inputCodigo.disabled = !activar;
      inputDescripcion.disabled = !activar;
      selUnidad.disabled = !activar;
      selUnidad.required = activar;

      if (!activar) {
        inputNombre.value = '';
        inputCodigo.value = '';
        inputDescripcion.value = '';
        selUnidad.value = '';
        toggleUnidadNuevaEnFila(fila);
      } else {
        // Si el usuario no indicó código en esta línea, asignamos el siguiente disponible en la página.
        // (El backend también completa si llega vacío.)
        if (!inputCodigo.value || String(inputCodigo.value).trim() === '') {
          inputCodigo.value = siguienteCodigoParaAsignar;
          siguienteCodigoParaAsignar = codigoSiguienteLocal(siguienteCodigoParaAsignar);
        } else {
          // Si trae el código prellenado y coincide con el siguiente a asignar,
          // avanzamos el puntero para que el próximo renglón no repita el mismo código.
          if (String(inputCodigo.value).trim() === String(siguienteCodigoParaAsignar).trim()) {
            siguienteCodigoParaAsignar = codigoSiguienteLocal(siguienteCodigoParaAsignar);
          }
        }
        toggleUnidadNuevaEnFila(fila);
      }
    }

    function actualizarStockEnFilaEntrada(fila) {
      var sel = fila.querySelector('select[name="producto_id[]"]');
      var span = fila.querySelector('.stock-display');
      if (!sel || !span) return;
      var id = sel.value;
      if (!id) { span.textContent = '—'; return; }
      var info = stockPorProducto[id];
      if (!info) { span.textContent = '—'; return; }
      var u = info.unidad || 'und';
      var stock = info.stock || 0;
      span.textContent = stock + ' ' + u;
    }

    document.getElementById('lineas').addEventListener('change', function(e) {
      if (e.target && e.target.matches('select[name="producto_id[]"]')) {
        var fila = e.target.closest('tr');
        actualizarStockEnFilaEntrada(fila);
        toggleProductoNuevoEnFila(fila);
      }
      if (e.target && e.target.matches('select[name="producto_nuevo_unidad[]"]')) {
        var fila2 = e.target.closest('tr');
        toggleUnidadNuevaEnFila(fila2);
      }
    });

    document.getElementById('addLine').addEventListener('click', function() {
      const tbody = document.getElementById('lineas');
      const first = tbody.querySelector('tr.linea');
      const clone = first.cloneNode(true);
      clone.querySelectorAll('input, select').forEach(function(el) {
        if (el.name && el.name.includes('cantidad')) el.value = 1;
        else if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else if (el.name && el.name.includes('producto_nuevo_')) el.value = '';
      });
      toggleProductoNuevoEnFila(clone);
      var stockSpan = clone.querySelector('.stock-display');
      if (stockSpan) stockSpan.textContent = '—';
      const lastTd = clone.querySelector('td:last-child');
      lastTd.innerHTML = '<button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="this.closest(\'tr\').remove()" title="Quitar línea" aria-label="Quitar línea"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
      tbody.appendChild(clone);
    });
    document.querySelectorAll('#lineas tr.linea').forEach(function(fila) {
      actualizarStockEnFilaEntrada(fila);
      toggleProductoNuevoEnFila(fila);
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
