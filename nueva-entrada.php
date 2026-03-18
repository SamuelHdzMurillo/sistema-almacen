<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/inventario.php';
require_once __DIR__ . '/includes/catalogos_entrada.php';

$mensaje = '';
$error = '';

$editId = (int)($_GET['id'] ?? 0);
$vista = (string)($_GET['vista'] ?? 'editar');
$vista = ($vista === 'historial') ? 'historial' : 'editar';
$modoHistorial = $vista === 'historial';
$histFecha = trim((string)($_GET['hist_fecha'] ?? ''));
$entradaExistente = null;
if ($editId > 0) {
    $entradaExistente = obtenerEntradaConDetalle($editId);
    if (!$entradaExistente) {
        header('Location: transacciones.php');
        exit;
    }
    // Para evitar inconsistencias con el cálculo de stock por estado de línea.
    foreach (($entradaExistente['detalle'] ?? []) as $d) {
        $estadoLinea = $d['estado'] ?? 'activa';
        if ($estadoLinea !== 'activa') {
            $error = 'No se puede editar una entrada con líneas canceladas.';
            break;
        }
    }
}

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
    $razonModificacion = trim((string)($_POST['razon_modificacion'] ?? ''));
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

                if ($editId > 0) {
                    if ($razonModificacion === '') {
                        $error = 'Indique una razón para guardar los cambios.';
                        throw new Exception('__RAZON_VACIA__');
                    }
                    actualizarEntrada($editId, $fecha, $proveedorId, $quienRecibeId, $lineas, $factura, $usuarioId, $razonModificacion);
                    header('Location: nueva-entrada.php?id=' . $editId . '&modificado=1');
                    exit;
                } else {
                    $entradaId = crearEntrada($fecha, $proveedorId, $quienRecibeId, $lineas, $factura, $usuarioId);
                    header('Location: recibo-entrada.php?id=' . $entradaId);
                    exit;
                }
            } catch (Exception $e) {
                if ($e->getMessage() !== '__RAZON_VACIA__') {
                    // Mostrar el motivo real (por qué no se puede actualizar).
                    $error = $e->getMessage();
                }
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

$formAction = $editId > 0 ? 'nueva-entrada.php?id=' . (int)$editId : 'nueva-entrada.php';
$fechaValor = $_POST['fecha'] ?? ($entradaExistente['fecha'] ?? date('Y-m-d'));
$facturaValor = $_POST['factura'] ?? ($entradaExistente['factura'] ?? '');
$proveedorSeleccionado = $_POST['proveedor_id'] ?? ($entradaExistente['proveedor_id'] ?? '');
$quienRecibeSeleccionado = $_POST['quien_recibe_id'] ?? ($entradaExistente['quien_recibe_id'] ?? '');

$lineasFormulario = [];
if ($editId > 0 && $entradaExistente) {
    foreach (($entradaExistente['detalle'] ?? []) as $d) {
        $estadoLinea = $d['estado'] ?? 'activa';
        if ($estadoLinea !== 'activa') continue;
        $lineasFormulario[] = [
            'producto_id' => (int)($d['producto_id'] ?? 0),
            'cantidad' => (int)($d['cantidad'] ?? 0),
        ];
    }
}
if (empty($lineasFormulario)) {
    $lineasFormulario = [['producto_id' => '', 'cantidad' => 1]];
}

$ultimaModificacion = $editId > 0 ? obtenerUltimaModificacionTransaccion('entrada', $editId) : null;
$historialModificaciones = $editId > 0 ? listarModificacionesTransaccion('entrada', $editId) : [];
$fechasDisponibles = [];
foreach ($historialModificaciones as $m) {
    $ts = $m['created_at'] ?? null;
    if (!$ts) continue;
    $fechasDisponibles[] = date('Y-m-d', strtotime((string)$ts));
}
$fechasDisponibles = array_values(array_unique($fechasDisponibles));
rsort($fechasDisponibles); // de más reciente a más antigua
$histFechaSeleccionada = $histFecha !== '' ? $histFecha : (count($fechasDisponibles) ? $fechasDisponibles[0] : '');
$mostrarTodas = $histFechaSeleccionada === 'all';
$modificacionesParaVista = $mostrarTodas ? $historialModificaciones : array_values(array_filter($historialModificaciones, function($m) use ($histFechaSeleccionada) {
    return date('Y-m-d', strtotime((string)($m['created_at'] ?? ''))) === $histFechaSeleccionada;
}));
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
        <h1 class="page-title"><?= $editId > 0 ? 'Editar entrada al almacén' : 'Nueva entrada al almacén' ?></h1>
        <p class="page-header-subtitulo"><?= $editId > 0 ? 'Al guardar se registrarán los cambios con una razón.' : 'Al guardar se generará el recibo de entrada con los datos indicados.' ?></p>
      </div>
    </header>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>" id="formEntrada" onsubmit="return confirm('¿Está seguro de que desea continuar y guardar los cambios?');">
      <div class="form-layout-dual">
      <div class="form-card form-card--entrada form-card--datos">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg></span>
          <h3 class="form-card-title">Datos del documento</h3>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="fecha_entrada">Fecha</label>
            <input type="date" name="fecha" id="fecha_entrada" value="<?= htmlspecialchars($fechaValor) ?>" required>
          </div>
          <div class="form-group">
            <label for="factura">Factura / Orden</label>
            <input type="text" name="factura" id="factura" value="<?= htmlspecialchars($facturaValor) ?>" placeholder="Ej. F-1234 (opcional)" maxlength="50">
          </div>
          <div class="form-group form-group--full">
            <label for="proveedor_id">Proveedor</label>
            <select name="proveedor_id" id="proveedor_id" aria-describedby="proveedor_hint">
              <option value="" <?= $proveedorSeleccionado === '' || $proveedorSeleccionado === null ? 'selected' : '' ?>>— Seleccione del catálogo —</option>
              <?php foreach ($proveedores as $prov): ?>
                <option value="<?= (int)$prov['id'] ?>" <?= ($proveedorSeleccionado !== 'nuevo' && (string)$proveedorSeleccionado !== '' && (int)$proveedorSeleccionado === (int)$prov['id']) ? 'selected' : '' ?>><?= htmlspecialchars($prov['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo" <?= $proveedorSeleccionado === 'nuevo' ? 'selected' : '' ?>>+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="proveedor_nuevo" id="proveedor_nuevo" value="<?= htmlspecialchars($_POST['proveedor_nuevo'] ?? '') ?>" placeholder="Nombre del proveedor" class="form-nuevo-catalogo" style="display:none;" aria-label="Nombre del nuevo proveedor">
            <span id="proveedor_hint" class="form-hint">Puede elegir un proveedor existente o agregar uno nuevo.</span>
          </div>
          <div class="form-group form-group--full">
            <label for="quien_recibe_id">Quien recibe (en almacén)</label>
            <select name="quien_recibe_id" id="quien_recibe_id" aria-describedby="quien_recibe_hint">
              <option value="" <?= $quienRecibeSeleccionado === '' || $quienRecibeSeleccionado === null ? 'selected' : '' ?>>— Seleccione del catálogo —</option>
              <?php foreach ($quienRecibeEntrada as $qr): ?>
                <option value="<?= (int)$qr['id'] ?>" <?= ($quienRecibeSeleccionado !== 'nuevo' && (string)$quienRecibeSeleccionado !== '' && (int)$quienRecibeSeleccionado === (int)$qr['id']) ? 'selected' : '' ?>><?= htmlspecialchars($qr['nombre']) ?></option>
              <?php endforeach; ?>
              <option value="nuevo" <?= $quienRecibeSeleccionado === 'nuevo' ? 'selected' : '' ?>>+ Agregar nuevo al catálogo</option>
            </select>
            <input type="text" name="quien_recibe_nuevo" id="quien_recibe_nuevo" value="<?= htmlspecialchars($_POST['quien_recibe_nuevo'] ?? '') ?>" placeholder="Nombre de quien recibe" class="form-nuevo-catalogo" style="display:none;" aria-label="Nombre de quien recibe (nuevo)">
            <span id="quien_recibe_hint" class="form-hint">Persona que recibe el material en almacén.</span>
          </div>
        </div>

        <?php if ($editId > 0 && !$modoHistorial): ?>
          <div class="form-group form-group--full">
            <label for="razon_modificacion">Razón de la modificación</label>
            <textarea name="razon_modificacion" id="razon_modificacion" rows="3" required><?= htmlspecialchars($_POST['razon_modificacion'] ?? '') ?></textarea>
          </div>
        <?php endif; ?>
      </div>

      <div class="form-card form-card--wide form-card--entrada form-card--detalle">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
          <h3 class="form-card-title">Detalle de productos</h3>
        </div>
        <p class="form-card-sub">Indique los productos que ingresan y la cantidad de cada uno. Se muestra el stock actual en almacén.</p>
        <?php if ($editId > 0): ?>
          <div class="choice-btns" style="margin-top:0; margin-bottom:0.75rem; width:100%; justify-content:flex-start;">
            <a
              class="btn btn-sm <?= $modoHistorial ? 'btn-secondary' : 'btn-primary' ?>"
              href="nueva-entrada.php?id=<?= (int)$editId ?>&vista=editar&hist_fecha=<?= htmlspecialchars($histFechaSeleccionada) ?>"
            >
              Editar
            </a>
            <?php if (!$modoHistorial): ?>
              <a
                class="btn btn-sm btn-secondary"
                href="nueva-entrada.php?id=<?= (int)$editId ?>&vista=historial&hist_fecha=<?= htmlspecialchars($histFechaSeleccionada) ?>"
              >
                Historial
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ($modoHistorial && $editId > 0 && !empty($historialModificaciones)): ?>
          <div style="display:flex; gap:0.75rem; align-items:center; margin-bottom:0.75rem;">
            <form method="get" action="nueva-entrada.php" style="display:flex; gap:0.75rem; align-items:center; margin:0;">
              <input type="hidden" name="id" value="<?= (int)$editId ?>">
              <input type="hidden" name="vista" value="historial">
              <label for="hist_fecha" style="margin:0; font-weight:600; font-size:0.9rem;">Historial por fecha:</label>
              <select name="hist_fecha" id="hist_fecha" class="form-select" style="max-width:220px;" onchange="this.form.submit()">
                <option value="all" <?= $mostrarTodas ? 'selected' : '' ?>>Todas</option>
                <?php foreach ($fechasDisponibles as $f): ?>
                  <option value="<?= htmlspecialchars($f) ?>" <?= $histFechaSeleccionada === $f ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <?php
            $proveedorMap = [];
            foreach ($proveedores as $p) { $proveedorMap[(int)$p['id']] = $p['nombre']; }
            $quienRecibeMap = [];
            foreach ($quienRecibeEntrada as $q) { $quienRecibeMap[(int)$q['id']] = $q['nombre']; }
            $productosMap = [];
            foreach ($productos as $pr) { $productosMap[(int)$pr['id']] = $pr['nombre']; }
            $labelCampos = [
              'fecha' => 'Fecha',
              'factura' => 'Factura / Orden',
              'proveedor_id' => 'Proveedor',
              'quien_recibe_id' => 'Quien recibe',
            ];
          ?>
          <div class="modificado-banner" id="panelCambiosEntradaDetalle" style="display:block;">
            <?php if (empty($modificacionesParaVista)): ?>
              <div class="modificado-cambio-item">No hay modificaciones para esta fecha.</div>
            <?php else: ?>
              <?php foreach ($modificacionesParaVista as $modificacion): ?>
                <?php
                  $cambios = $modificacion['cambios_json'] ?? null;
                  $cambiosHeader = is_array($cambios['header'] ?? null) ? $cambios['header'] : [];
                  $cambiosDetalleItems = is_array($cambios['detalle']['items'] ?? null) ? $cambios['detalle']['items'] : [];
                  $createdAt = (string)($modificacion['created_at'] ?? '');
                  $razon = (string)($modificacion['razon'] ?? '');
                ?>
                <div class="modificado-cambios-seccion" style="margin-bottom:1rem;">
                  <div class="modificado-banner-top">
                    <span class="status-badge status-modificado">MODIFICADO</span>
                    <span class="modificado-fecha">
                      Se modificó el: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($createdAt ?: 'now'))) ?>
                    </span>
                  </div>
                  <div class="modificado-razon"><strong>Porque:</strong> <?= htmlspecialchars($razon) ?></div>

                  <div class="modificado-cambios"><strong>Cambios realizados:</strong></div>
                  <table class="modificado-cambios-table">
                    <thead>
                      <tr>
                        <th class="col-peq">Tipo</th>
                        <th class="col-campo">Campo / Producto</th>
                        <th>Anterior</th>
                        <th>Nuevo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($cambiosHeader)): ?>
                        <?php foreach ($cambiosHeader as $campo => $info): ?>
                          <?php
                            $oldVal = $info['old'] ?? null;
                            $newVal = $info['new'] ?? null;
                            if ($campo === 'proveedor_id') { $oldVal = $proveedorMap[(int)$oldVal] ?? $oldVal; $newVal = $proveedorMap[(int)$newVal] ?? $newVal; }
                            if ($campo === 'quien_recibe_id') { $oldVal = $quienRecibeMap[(int)$oldVal] ?? $oldVal; $newVal = $quienRecibeMap[(int)$newVal] ?? $newVal; }
                            $label = $labelCampos[$campo] ?? $campo;
                          ?>
                          <tr>
                            <td>Encabezado</td>
                            <td><?= htmlspecialchars($label) ?></td>
                            <td><?= htmlspecialchars((string)$oldVal) ?></td>
                            <td><?= htmlspecialchars((string)$newVal) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>

                      <?php if (!empty($cambiosDetalleItems)): ?>
                        <?php foreach ($cambiosDetalleItems as $it): ?>
                          <?php
                            $pidItem = (int)($it['producto_id'] ?? 0);
                            $prodNom = $productosMap[$pidItem] ?? ('ID ' . $pidItem);
                            $oldQty = (int)($it['old_cantidad'] ?? 0);
                            $newQty = (int)($it['new_cantidad'] ?? 0);
                          ?>
                          <tr>
                            <td>Detalle</td>
                            <td>Cantidad (<?= htmlspecialchars($prodNom) ?>)</td>
                            <td><?= (int)$oldQty ?></td>
                            <td><?= (int)$newQty ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>

                      <?php if (empty($cambiosHeader) && empty($cambiosDetalleItems)): ?>
                        <tr>
                          <td colspan="4">No se detectaron cambios.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!$modoHistorial): ?>
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
              <?php foreach ($lineasFormulario as $i => $lf): ?>
                <?php $productoSel = (string)($lf['producto_id'] ?? ''); $cantidadSel = (int)($lf['cantidad'] ?? 1); ?>
                <tr class="linea">
                  <td>
                    <select name="producto_id[]" required aria-label="Producto">
                      <option value="" <?= $productoSel === '' ? 'selected' : '' ?>>— Seleccione producto —</option>
                      <option value="nuevo" <?= $productoSel === 'nuevo' ? 'selected' : '' ?>>+ Alta rápida de producto</option>
                      <?php foreach ($productos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= ($productoSel !== '' && (int)$productoSel === (int)$p['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($p['nombre']) ?> (<?= htmlspecialchars($p['unidad'] ?? 'und') ?>)
                        </option>
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
                  <td><input type="number" name="cantidad[]" min="1" value="<?= (int)$cantidadSel ?>" required aria-label="Cantidad que entra"></td>
                  <td>
                    <?php if ($editId > 0): ?>
                      <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="this.closest('tr').remove()" title="Quitar línea" aria-label="Quitar línea">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <div class="form-actions">
          <?php if (!$modoHistorial): ?>
            <button type="button" class="btn btn-secondary btn-add-line" id="addLine">+ Añadir línea</button>
          <?php endif; ?>
          <?php if (!$modoHistorial): ?>
            <button type="submit" class="btn btn-primary"><?= $editId > 0 ? 'Guardar cambios' : 'Registrar entrada y generar recibo' ?></button>
          <?php endif; ?>
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

    var lineasEl = document.getElementById('lineas');
    if (lineasEl) {
      lineasEl.addEventListener('change', function(e) {
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
    }

    (function() {
      var addLineBtn = document.getElementById('addLine');
      if (!addLineBtn) return;
      addLineBtn.addEventListener('click', function() {
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
        lastTd.innerHTML = "<button type=\"button\" class=\"btn btn-secondary btn-sm btn-icon-only\" onclick=\"this.closest('tr').remove()\" title=\"Quitar línea\" aria-label=\"Quitar línea\"><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><line x1=\"18\" y1=\"6\" x2=\"6\" y2=\"18\"/><line x1=\"6\" y1=\"6\" x2=\"18\" y2=\"18\"/></svg></button>";
        tbody.appendChild(clone);
      });
    })();
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
