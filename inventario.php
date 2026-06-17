<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/inventario_mes.php';
require_once __DIR__ . '/includes/inventario.php';

$anioActual = (int) date('Y');
$mesActual = (int) date('n');
$anio = isset($_GET['anio']) ? (int) $_GET['anio'] : $anioActual;
$mes  = isset($_GET['mes'])  ? (int) $_GET['mes']  : $mesActual;

if ($mes < 1 || $mes > 12) $mes = $mesActual;
if ($anio < 2000 || $anio > 2100) $anio = $anioActual;

$entradas = listarEntradasPorMes($anio, $mes);
$salidas  = listarSalidasPorMes($anio, $mes);
$resumen  = resumenMes($anio, $mes);
$inventarioActual = inventarioPorProducto();

// Listas para los filtros del inventario actual (proveedores y unidades distintas).
$filtroProveedores = [];
$filtroUnidades = [];
foreach ($inventarioActual as $inv) {
    $unidad = trim((string)($inv['unidad'] ?? ''));
    if ($unidad !== '') {
        $filtroUnidades[$unidad] = true;
    }
    $provStr = trim((string)($inv['proveedores'] ?? ''));
    if ($provStr !== '') {
        foreach (explode(' | ', $provStr) as $prov) {
            $prov = trim($prov);
            if ($prov !== '') {
                $filtroProveedores[$prov] = true;
            }
        }
    }
}
$filtroProveedores = array_keys($filtroProveedores);
$filtroUnidades = array_keys($filtroUnidades);
sort($filtroProveedores, SORT_NATURAL | SORT_FLAG_CASE);
sort($filtroUnidades, SORT_NATURAL | SORT_FLAG_CASE);

$mesNombre = $mesesNombres[$mes] ?? 'Mes';
$periodoTexto = $mesNombre . ' ' . $anio;
$vista = isset($_GET['vista']) && $_GET['vista'] === 'mes' ? 'mes' : 'actual';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventario — <?= htmlspecialchars($periodoTexto) ?> - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=15">
  <style>
    .inventario-link-historial { color: inherit; text-decoration: none; border-bottom: 1px dashed transparent; }
    .inventario-link-historial:hover { color: #1d4ed8; border-bottom-color: #1d4ed8; }
    .inventario-filtros {
      display: flex;
      flex-wrap: wrap;
      align-items: flex-end;
      gap: 0.85rem 1rem;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .inventario-filtros .inventario-filtro-campo { flex: 1 1 180px; min-width: 150px; }
    .inventario-filtros .inventario-filtro-buscar-campo { flex: 2 1 260px; min-width: 220px; }
    .inventario-filtros .inventario-form-select { width: 100%; min-width: 0; }
    .inventario-filtro-limpiar { flex: 0 0 auto; white-space: nowrap; }
    .inventario-col-proveedor { color: var(--text-muted); font-size: 0.9rem; }
    @media (max-width: 640px) {
      .inventario-filtros .inventario-filtro-campo,
      .inventario-filtros .inventario-filtro-buscar-campo { flex: 1 1 100%; }
      .inventario-filtro-limpiar { width: 100%; }
    }
  </style>
</head>
<body class="pagina-inventario">
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Inventario</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <main class="inventario-main">
      <!-- Cabecera: título + período + acción + pestañas -->
      <header class="inventario-page-header">
        <div class="inventario-page-header-top">
          <div class="inventario-page-header-texto">
            <h1 class="inventario-page-titulo">Inventario</h1>
            <p class="inventario-page-subtitulo"><?= $vista === 'actual' ? 'Stock en existencia por producto' : 'Resumen por mes · ' . htmlspecialchars($periodoTexto) ?></p>
          </div>
          <div class="inventario-page-header-accion">
            <?php if (esAdminSesionAlmacen()): ?>
            <a href="inventario-global.php" class="btn btn-secondary">Inventario global</a>
            <?php endif; ?>
            <?php if ($vista === 'actual'): ?>
            <a href="imprimir-inventario-actual.php?hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-imprimir-recibo">
              Imprimir inventario actual
            </a>
            <?php elseif ($vista === 'mes'): ?>
            <a href="recibo-mes.php?anio=<?= $anio ?>&mes=<?= $mes ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-imprimir-recibo">
              Imprimir recibo del mes
            </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Pestañas (botones) dentro del header -->
        <nav class="inventario-tabs" role="tablist" aria-label="Pestañas inventario">
          <a href="inventario.php?vista=actual" class="inventario-tab <?= $vista === 'actual' ? 'active' : '' ?>" role="tab">Inventario actual</a>
          <a href="inventario.php?vista=mes&amp;anio=<?= $anio ?>&amp;mes=<?= $mes ?>" class="inventario-tab <?= $vista === 'mes' ? 'active' : '' ?>" role="tab">Inventario por mes</a>
        </nav>
      </header>

      <!-- Contenido pestaña: Inventario actual -->
      <div class="inventario-pestana-content <?= $vista === 'actual' ? 'activa' : '' ?>" id="pestana-actual" role="tabpanel">
      <section class="inventario-seccion inventario-seccion-actual">
        <h2 class="inventario-seccion-titulo">Inventario actual</h2>
        <article class="inventario-panel inventario-panel-actual">
          <header class="inventario-panel-cabecera">
            <h3 class="inventario-panel-titulo">Stock por producto <span style="font-weight:400;font-size:0.8rem;color:#6b7280;">(clic en un producto para ver su historial)</span></h3>
            <span class="inventario-panel-contador" id="inventario-contador-actual"><?= count($inventarioActual) ?></span>
          </header>
          <div class="inventario-panel-cuerpo">
            <div class="inventario-filtros" id="inventario-filtros">
              <label for="inventario-buscador" class="inventario-form-label inventario-filtro-campo inventario-filtro-buscar-campo">
                <span class="inventario-form-etiqueta">Buscar</span>
                <input type="search" id="inventario-buscador" class="inventario-form-input" placeholder="Código, producto, unidad o proveedor…" autocomplete="off" aria-label="Buscar en inventario">
              </label>
              <label for="inventario-filtro-proveedor" class="inventario-form-label inventario-filtro-campo">
                <span class="inventario-form-etiqueta">Proveedor</span>
                <select id="inventario-filtro-proveedor" class="inventario-form-select">
                  <option value="">Todos</option>
                  <?php foreach ($filtroProveedores as $prov): ?>
                    <option value="<?= htmlspecialchars(mb_strtolower($prov)) ?>"><?= htmlspecialchars($prov) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label for="inventario-filtro-unidad" class="inventario-form-label inventario-filtro-campo">
                <span class="inventario-form-etiqueta">Unidad</span>
                <select id="inventario-filtro-unidad" class="inventario-form-select">
                  <option value="">Todas</option>
                  <?php foreach ($filtroUnidades as $unidad): ?>
                    <option value="<?= htmlspecialchars(mb_strtolower($unidad)) ?>"><?= htmlspecialchars($unidad) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label for="inventario-filtro-stock" class="inventario-form-label inventario-filtro-campo">
                <span class="inventario-form-etiqueta">Existencia</span>
                <select id="inventario-filtro-stock" class="inventario-form-select">
                  <option value="">Todas</option>
                  <option value="positivo">Con stock (&gt; 0)</option>
                  <option value="cero">Sin stock (= 0)</option>
                  <option value="negativo">Stock negativo (&lt; 0)</option>
                </select>
              </label>
              <button type="button" id="inventario-filtro-limpiar" class="btn btn-secondary inventario-filtro-limpiar">Limpiar</button>
            </div>
            <div class="inventario-tabla-wrap">
              <table class="inventario-tabla inventario-tabla-actual">
                <thead>
                  <tr>
                    <th class="inventario-col-codigo">Código</th>
                    <th class="inventario-col-producto">Producto</th>
                    <th class="inventario-col-proveedor">Proveedor(es)</th>
                    <th class="inventario-col-unidad">Unidad</th>
                    <th class="inventario-th-num inventario-col-cantidad">Cantidad actual</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($inventarioActual)): ?>
                    <tr><td colspan="5" class="inventario-empty">No hay productos registrados.</td></tr>
                  <?php else: ?>
                    <?php
                    $totalUnidades = 0;
                    foreach ($inventarioActual as $inv):
                      $stock = (int) $inv['stock'];
                      $totalUnidades += $stock;
                      $proveedoresProd = trim((string)($inv['proveedores'] ?? ''));
                      $estadoStock = $stock > 0 ? 'positivo' : ($stock < 0 ? 'negativo' : 'cero');
                    ?>
                      <tr class="inventario-fila-producto"
                          data-busqueda="<?= htmlspecialchars(mb_strtolower(($inv['codigo'] ?? '') . ' ' . ($inv['nombre'] ?? '') . ' ' . ($inv['unidad'] ?? '') . ' ' . $proveedoresProd)) ?>"
                          data-unidad="<?= htmlspecialchars(mb_strtolower((string)($inv['unidad'] ?? ''))) ?>"
                          data-proveedores="<?= htmlspecialchars(mb_strtolower($proveedoresProd)) ?>"
                          data-stock-estado="<?= $estadoStock ?>">
                        <td class="inventario-col-codigo"><?= htmlspecialchars($inv['codigo'] ?? '—') ?></td>
                        <td class="inventario-col-producto">
                          <a href="historial-producto.php?id=<?= (int)$inv['id'] ?>" class="inventario-link-historial" title="Ver historial del producto"><strong><?= htmlspecialchars($inv['nombre']) ?></strong></a>
                        </td>
                        <td class="inventario-col-proveedor"><?= $proveedoresProd !== '' ? htmlspecialchars($proveedoresProd) : '—' ?></td>
                        <td class="inventario-col-unidad"><?= htmlspecialchars($inv['unidad'] ?? 'und') ?></td>
                        <td class="inventario-th-num inventario-col-cantidad <?= $stock > 0 ? 'qty-pos' : ($stock < 0 ? 'qty-neg' : '') ?>"><?= number_format($stock) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <tr class="inventario-tabla-total inventario-fila-total">
                      <td colspan="4" class="inventario-td-total-label"><strong>Total unidades en almacén</strong></td>
                      <td class="inventario-th-num inventario-col-cantidad inventario-td-total-num <?= $totalUnidades >= 0 ? 'qty-pos' : 'qty-neg' ?>"><?= number_format($totalUnidades) ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </article>
      </section>
      </div>

      <!-- Contenido pestaña: Inventario por mes -->
      <div class="inventario-pestana-content <?= $vista === 'mes' ? 'activa' : '' ?>" id="pestana-mes" role="tabpanel">
      <section class="inventario-seccion inventario-seccion-por-mes">
        <h2 class="inventario-seccion-titulo">Inventario por mes</h2>

        <!-- Período consultado + resumen numérico -->
        <div class="inventario-periodo-card">
          <div class="inventario-periodo-filtro">
            <form method="get" action="inventario.php" class="inventario-form-periodo">
              <input type="hidden" name="vista" value="mes">
              <label class="inventario-form-label">
                <span class="inventario-form-etiqueta">Mes</span>
                <select name="mes" class="inventario-form-select">
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $mesesNombres[$m] ?></option>
                  <?php endfor; ?>
                </select>
              </label>
              <label class="inventario-form-label">
                <span class="inventario-form-etiqueta">Año</span>
                <select name="anio" class="inventario-form-select">
                  <?php for ($a = $anioActual; $a >= $anioActual - 10; $a--): ?>
                    <option value="<?= $a ?>" <?= $anio === $a ? 'selected' : '' ?>><?= $a ?></option>
                  <?php endfor; ?>
                </select>
              </label>
              <button type="submit" class="btn btn-primary">Aplicar</button>
            </form>
          </div>
          <div class="inventario-periodo-resumen">
            <div class="inventario-resumen-item inventario-resumen-entrada">
              <span class="inventario-resumen-numero"><?= number_format($resumen['total_entradas']) ?></span>
              <span class="inventario-resumen-texto">Unidades entradas</span>
              <span class="inventario-resumen-docs"><?= count($entradas) ?> documento(s)</span>
            </div>
            <div class="inventario-resumen-item inventario-resumen-salida">
              <span class="inventario-resumen-numero"><?= number_format($resumen['total_salidas']) ?></span>
              <span class="inventario-resumen-texto">Unidades salidas</span>
              <span class="inventario-resumen-docs"><?= count($salidas) ?> documento(s)</span>
            </div>
          </div>
        </div>

        <!-- Detalle de movimientos del mes -->
        <h3 class="inventario-subseccion-titulo">Detalle de movimientos — <?= htmlspecialchars($periodoTexto) ?></h3>

        <article class="inventario-panel inventario-panel-entradas">
          <header class="inventario-panel-cabecera">
            <h3 class="inventario-panel-titulo">Entradas</h3>
            <span class="inventario-panel-contador"><?= count($entradas) ?></span>
          </header>
          <div class="inventario-panel-cuerpo">
            <div class="inventario-tabla-wrap">
              <table class="inventario-tabla">
                <thead>
                  <tr>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th>Proveedor / Quien recibe</th>
                    <th>Detalle</th>
                    <th class="inventario-th-num">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($entradas)): ?>
                    <tr><td colspan="5" class="inventario-empty">No hay entradas en este mes.</td></tr>
                  <?php else: ?>
                    <?php foreach ($entradas as $e): ?>
                      <?php $totalLineas = array_sum(array_column($e['detalle'], 'cantidad')); ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($e['referencia']) ?></strong></td>
                        <td><?= htmlspecialchars($e['fecha']) ?></td>
                        <td><?= htmlspecialchars($e['proveedor_nombre'] ?? '—') ?> / <?= htmlspecialchars($e['quien_recibe_nombre'] ?? '—') ?></td>
                        <td class="inventario-cell-detalle">
                          <ul class="inventario-lista-detalle">
                            <?php foreach ($e['detalle'] as $d): ?>
                              <li><?= htmlspecialchars($d['producto_nombre']) ?> — <?= (int)$d['cantidad'] ?> <?= htmlspecialchars($d['unidad']) ?></li>
                            <?php endforeach; ?>
                          </ul>
                        </td>
                        <td class="inventario-th-num qty-pos"><?= $totalLineas ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </article>

        <article class="inventario-panel inventario-panel-salidas">
          <header class="inventario-panel-cabecera">
            <h3 class="inventario-panel-titulo">Salidas</h3>
            <span class="inventario-panel-contador"><?= count($salidas) ?></span>
          </header>
          <div class="inventario-panel-cuerpo">
            <div class="inventario-tabla-wrap">
              <table class="inventario-tabla">
                <thead>
                  <tr>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th>Entrega / Receptor</th>
                    <th>Detalle</th>
                    <th class="inventario-th-num">Total</th>
                    <th>Recibo</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($salidas)): ?>
                    <tr><td colspan="6" class="inventario-empty">No hay salidas en este mes.</td></tr>
                  <?php else: ?>
                    <?php foreach ($salidas as $s): ?>
                      <?php $totalLineas = array_sum(array_column($s['detalle'], 'cantidad')); ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($s['referencia']) ?></strong></td>
                        <td><?= htmlspecialchars($s['fecha']) ?></td>
                        <td class="inventario-cell-personas">
                          <span title="Entrega"><?= htmlspecialchars($s['nombre_entrega'] ?? '—') ?></span>
                          <span class="inventario-sep">/</span>
                          <span title="Plantel"><?= htmlspecialchars($s['plantel_nombre'] ?? '—') ?></span>
                          <span class="inventario-sep">/</span>
                          <span title="Recibe"><?= htmlspecialchars($s['nombre_receptor'] ?? '—') ?></span>
                        </td>
                        <td class="inventario-cell-detalle">
                          <ul class="inventario-lista-detalle">
                            <?php foreach ($s['detalle'] as $d): ?>
                              <li><?= htmlspecialchars($d['producto_nombre']) ?> — <?= (int)$d['cantidad'] ?> <?= htmlspecialchars($d['unidad']) ?></li>
                            <?php endforeach; ?>
                          </ul>
                        </td>
                        <td class="inventario-th-num qty-neg"><?= $totalLineas ?></td>
                        <td>
                          <a href="recibo.php?id=<?= (int)$s['id'] ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Imprimir</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </article>
      </section>
      </div>
    </main>
  </div>
  <script>
  (function() {
    var buscador = document.getElementById('inventario-buscador');
    var selProveedor = document.getElementById('inventario-filtro-proveedor');
    var selUnidad = document.getElementById('inventario-filtro-unidad');
    var selStock = document.getElementById('inventario-filtro-stock');
    var btnLimpiar = document.getElementById('inventario-filtro-limpiar');
    var panel = document.querySelector('.inventario-panel-actual');
    if (!buscador || !panel) return;
    var filas = panel.querySelectorAll('.inventario-fila-producto');
    var filaTotal = panel.querySelector('.inventario-fila-total');
    var contador = document.getElementById('inventario-contador-actual');
    var totalProductos = filas.length;

    function filtrar() {
      var q = (buscador.value || '').trim().toLowerCase();
      var proveedor = selProveedor ? selProveedor.value.toLowerCase() : '';
      var unidad = selUnidad ? selUnidad.value.toLowerCase() : '';
      var stock = selStock ? selStock.value : '';
      var hayFiltro = q || proveedor || unidad || stock;
      var visibles = 0;
      filas.forEach(function(tr) {
        var texto = (tr.getAttribute('data-busqueda') || '').toLowerCase();
        var provFila = (tr.getAttribute('data-proveedores') || '').toLowerCase();
        var unidadFila = (tr.getAttribute('data-unidad') || '').toLowerCase();
        var stockFila = tr.getAttribute('data-stock-estado') || '';
        var coincide = (!q || texto.indexOf(q) !== -1)
          && (!proveedor || provFila.indexOf(proveedor) !== -1)
          && (!unidad || unidadFila === unidad)
          && (!stock || stockFila === stock);
        tr.style.display = coincide ? '' : 'none';
        if (coincide) visibles++;
      });
      if (filaTotal) filaTotal.style.display = hayFiltro ? (visibles === 0 ? 'none' : '') : '';
      if (contador) contador.textContent = hayFiltro ? visibles + ' / ' + totalProductos : totalProductos;
    }

    buscador.addEventListener('input', filtrar);
    buscador.addEventListener('keyup', filtrar);
    if (selProveedor) selProveedor.addEventListener('change', filtrar);
    if (selUnidad) selUnidad.addEventListener('change', filtrar);
    if (selStock) selStock.addEventListener('change', filtrar);
    if (btnLimpiar) btnLimpiar.addEventListener('click', function() {
      buscador.value = '';
      if (selProveedor) selProveedor.value = '';
      if (selUnidad) selUnidad.value = '';
      if (selStock) selStock.value = '';
      filtrar();
      buscador.focus();
    });
  })();
  </script>
</body>
</html>
