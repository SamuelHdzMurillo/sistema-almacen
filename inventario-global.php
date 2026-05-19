<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/inventario.php';

if (!esAdminSesionAlmacen()) {
    http_response_code(403);
    header('Location: index.php');
    exit;
}

$matriz = inventarioGlobalMatriz();
$almacenes = $matriz['almacenes'];
$filas = $matriz['filas'];
$totalesAlmacen = $matriz['totales_almacen'];
$totalGeneral = (int) $matriz['total_general'];
$soloConStock = isset($_GET['solo_stock']) && $_GET['solo_stock'] === '1';

if ($soloConStock) {
    $filas = array_values(array_filter($filas, static function (array $f): bool {
        return (int) $f['total'] !== 0;
    }));
}

$nav_activo = 'inventario-global';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventario global — Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=16">
</head>
<body class="pagina-inventario pagina-inventario-global">
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Inventario global</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <main class="inventario-main">
      <header class="inventario-page-header">
        <div class="inventario-page-header-top">
          <div class="inventario-page-header-texto">
            <h1 class="inventario-page-titulo">Inventario global</h1>
            <p class="inventario-page-subtitulo">Existencias de todos los productos en cada almacén</p>
          </div>
          <div class="inventario-page-header-accion inventario-global-acciones">
            <a href="inventario.php" class="btn btn-secondary">Inventario del almacén activo</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir</button>
          </div>
        </div>
      </header>

      <?php if (!empty($almacenes)): ?>
      <section class="inventario-global-resumen" aria-label="Totales por almacén">
        <?php foreach ($almacenes as $a):
          $aid = (int) $a['id'];
          $t = (int) ($totalesAlmacen[$aid] ?? 0);
        ?>
        <article class="inventario-resumen-item inventario-global-resumen-item">
          <span class="inventario-resumen-etiqueta"><?= htmlspecialchars($a['nombre']) ?></span>
          <strong class="inventario-resumen-valor <?= $t >= 0 ? 'qty-pos' : 'qty-neg' ?>"><?= number_format($t) ?></strong>
          <span class="inventario-resumen-unidad">unidades</span>
        </article>
        <?php endforeach; ?>
        <article class="inventario-resumen-item inventario-global-resumen-item inventario-global-resumen-total">
          <span class="inventario-resumen-etiqueta">Total general</span>
          <strong class="inventario-resumen-valor <?= $totalGeneral >= 0 ? 'qty-pos' : 'qty-neg' ?>"><?= number_format($totalGeneral) ?></strong>
          <span class="inventario-resumen-unidad">unidades</span>
        </article>
      </section>
      <?php endif; ?>

      <section class="inventario-seccion inventario-seccion-actual">
        <article class="inventario-panel inventario-panel-actual inventario-panel-global">
          <header class="inventario-panel-cabecera">
            <h2 class="inventario-panel-titulo">Stock por producto y almacén</h2>
            <span class="inventario-panel-contador" id="inventario-global-contador"><?= count($filas) ?></span>
          </header>
          <div class="inventario-panel-cuerpo">
            <div class="inventario-global-filtros">
              <div class="inventario-filtro-buscar inventario-global-buscar">
                <label for="inventario-global-buscador" class="inventario-form-label">
                  <span class="inventario-form-etiqueta">Buscar</span>
                  <input type="search" id="inventario-global-buscador" class="inventario-form-input" placeholder="Código o producto…" autocomplete="off" aria-label="Buscar en inventario global">
                </label>
              </div>
              <div class="inventario-global-filtro-stock">
                <?php if ($soloConStock): ?>
                  <a href="inventario-global.php" class="btn btn-secondary btn-sm">Mostrar todos los productos</a>
                <?php else: ?>
                  <a href="inventario-global.php?solo_stock=1" class="btn btn-secondary btn-sm">Solo con existencia</a>
                <?php endif; ?>
              </div>
            </div>

            <?php if (empty($almacenes)): ?>
              <p class="inventario-empty">No hay almacenes registrados.</p>
            <?php else: ?>
            <div class="inventario-tabla-wrap inventario-global-tabla-wrap">
              <table class="inventario-tabla inventario-tabla-global">
                <thead>
                  <tr>
                    <th class="inventario-col-codigo inventario-global-sticky">Código</th>
                    <th class="inventario-col-producto inventario-global-sticky">Producto</th>
                    <th class="inventario-col-unidad inventario-global-sticky">Unidad</th>
                    <?php foreach ($almacenes as $a): ?>
                      <th class="inventario-th-num inventario-col-almacen" title="<?= htmlspecialchars($a['nombre']) ?>"><?= htmlspecialchars($a['nombre']) ?></th>
                    <?php endforeach; ?>
                    <th class="inventario-th-num inventario-col-total-global">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($filas)): ?>
                    <tr><td colspan="<?= 3 + count($almacenes) + 1 ?>" class="inventario-empty">No hay productos<?= $soloConStock ? ' con existencia' : '' ?>.</td></tr>
                  <?php else: ?>
                    <?php foreach ($filas as $f):
                      $busqueda = mb_strtolower(($f['codigo'] ?? '') . ' ' . ($f['nombre'] ?? ''));
                      $totalFila = (int) $f['total'];
                    ?>
                    <tr class="inventario-fila-producto inventario-global-fila" data-busqueda="<?= htmlspecialchars($busqueda) ?>">
                      <td class="inventario-col-codigo inventario-global-sticky"><?= htmlspecialchars($f['codigo'] ?? '—') ?></td>
                      <td class="inventario-col-producto inventario-global-sticky"><strong><?= htmlspecialchars($f['nombre']) ?></strong></td>
                      <td class="inventario-col-unidad inventario-global-sticky"><?= htmlspecialchars($f['unidad']) ?></td>
                      <?php foreach ($almacenes as $a):
                        $aid = (int) $a['id'];
                        $qty = (int) ($f['por_almacen'][$aid] ?? 0);
                      ?>
                      <td class="inventario-th-num inventario-col-almacen <?= $qty > 0 ? 'qty-pos' : ($qty < 0 ? 'qty-neg' : 'qty-cero') ?>"><?= $qty !== 0 ? number_format($qty) : '—' ?></td>
                      <?php endforeach; ?>
                      <td class="inventario-th-num inventario-col-total-global <?= $totalFila > 0 ? 'qty-pos' : ($totalFila < 0 ? 'qty-neg' : '') ?>"><strong><?= number_format($totalFila) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="inventario-tabla-total inventario-fila-total inventario-global-fila-total">
                      <td colspan="3" class="inventario-td-total-label inventario-global-sticky"><strong>Total por almacén</strong></td>
                      <?php foreach ($almacenes as $a):
                        $aid = (int) $a['id'];
                        $t = (int) ($totalesAlmacen[$aid] ?? 0);
                      ?>
                      <td class="inventario-th-num inventario-col-almacen <?= $t >= 0 ? 'qty-pos' : 'qty-neg' ?>"><strong><?= number_format($t) ?></strong></td>
                      <?php endforeach; ?>
                      <td class="inventario-th-num inventario-col-total-global <?= $totalGeneral >= 0 ? 'qty-pos' : 'qty-neg' ?>"><strong><?= number_format($totalGeneral) ?></strong></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </article>
      </section>
    </main>
  </div>

  <script>
  (function() {
    var buscador = document.getElementById('inventario-global-buscador');
    var panel = document.querySelector('.inventario-panel-global');
    if (!buscador || !panel) return;
    var filas = panel.querySelectorAll('.inventario-global-fila');
    var filaTotal = panel.querySelector('.inventario-global-fila-total');
    var contador = document.getElementById('inventario-global-contador');
    var totalProductos = filas.length;
    function filtrar() {
      var q = (buscador.value || '').trim().toLowerCase();
      var visibles = 0;
      filas.forEach(function(tr) {
        var texto = (tr.getAttribute('data-busqueda') || '').toLowerCase();
        var coincide = !q || texto.indexOf(q) !== -1;
        tr.style.display = coincide ? '' : 'none';
        if (coincide) visibles++;
      });
      if (filaTotal) filaTotal.style.display = q ? (visibles === 0 ? 'none' : '') : '';
      if (contador) contador.textContent = q ? visibles + ' / ' + totalProductos : totalProductos;
    }
    buscador.addEventListener('input', filtrar);
    buscador.addEventListener('keyup', filtrar);
  })();
  </script>
</body>
</html>
