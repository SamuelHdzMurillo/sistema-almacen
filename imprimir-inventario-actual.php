<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/inventario.php';

$inventarioActual = inventarioPorProducto();
$totalUnidades = array_sum(array_column($inventarioActual, 'stock'));
$titulo = 'Inventario actual — para conteo físico';
$hoja = isset($_GET['hoja']) && $_GET['hoja'] === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?> - Sistema de Almacén</title>
  <link rel="stylesheet" href="assets/css/style.css?v=6">
  <?php if ($hoja): ?>
  <style media="print">
    @page {
      size: letter;
      margin: 20mm 18mm 30mm 18mm;
      @bottom-center {
        content: "Hoja " counter(page);
        font-size: 11pt;
        font-weight: 600;
        color: #000;
        font-family: "Segoe UI", system-ui, sans-serif;
      }
    }
  </style>
  <style>
    .inventario-lista-impresion {
      list-style: none;
      margin: 0;
      padding: 0;
      border: 1px solid #ddd;
      border-radius: 6px;
      overflow: hidden;
    }
    .inventario-lista-impresion-header {
      display: flex;
      align-items: center;
      gap: 0.5rem 1rem;
      padding: 0.5rem 0.75rem;
      background: #f2f2f2;
      font-weight: 600;
      font-size: 0.9rem;
      border-bottom: 2px solid #333;
    }
    .inventario-lista-impresion-header .lista-col-caja { width: 2.25rem; flex-shrink: 0; text-align: center; }
    .inventario-lista-impresion-header .lista-col-num { width: 2rem; flex-shrink: 0; }
    .inventario-lista-impresion-header .lista-col-codigo { width: 5.5rem; flex-shrink: 0; }
    .inventario-lista-impresion-header .lista-col-nombre { flex: 1; min-width: 0; }
    .inventario-lista-impresion-header .lista-col-unidad { width: 3rem; flex-shrink: 0; text-align: center; }
    .inventario-lista-impresion-header .lista-col-cant { width: 4rem; flex-shrink: 0; text-align: right; }
    .inventario-lista-item {
      display: flex;
      align-items: center;
      gap: 0.5rem 1rem;
      padding: 0.4rem 0.75rem;
      font-size: 0.95rem;
      border-bottom: 1px solid #eee;
      min-height: 2rem;
    }
    .inventario-lista-item:last-child { border-bottom: none; }
    .inventario-lista-item:nth-child(even) { background: #fafafa; }
    .inventario-lista-item .lista-col-caja { width: 2.25rem; flex-shrink: 0; text-align: center; }
    .inventario-lista-item .lista-col-num { width: 2rem; flex-shrink: 0; color: #555; }
    .inventario-lista-item .lista-col-codigo { width: 5.5rem; flex-shrink: 0; font-family: monospace; font-size: 0.9em; }
    .inventario-lista-item .lista-col-nombre { flex: 1; min-width: 0; }
    .inventario-lista-item .lista-col-unidad { width: 3rem; flex-shrink: 0; text-align: center; color: #555; font-size: 0.9em; }
    .inventario-lista-item .lista-col-cant { width: 4rem; flex-shrink: 0; text-align: right; font-weight: 600; }
    .lista-caja-palomear {
      display: inline-block;
      width: 1.25em;
      height: 1.25em;
      border: 2px solid #333;
      border-radius: 2px;
      background: #fff;
      vertical-align: middle;
    }
    .inventario-lista-total {
      display: flex;
      align-items: center;
      gap: 0.5rem 1rem;
      padding: 0.5rem 0.75rem;
      background: #e8e8e8;
      font-weight: 700;
      border-top: 2px solid #333;
    }
    .inventario-lista-total .lista-col-caja { width: 2.25rem; flex-shrink: 0; }
    .inventario-lista-total .lista-col-num { width: 2rem; flex-shrink: 0; }
    .inventario-lista-total .lista-col-codigo { width: 5.5rem; flex-shrink: 0; }
    .inventario-lista-total .lista-col-nombre { flex: 1; min-width: 0; }
    .inventario-lista-total .lista-col-unidad { width: 3rem; flex-shrink: 0; }
    .inventario-lista-total .lista-col-cant { width: 4rem; flex-shrink: 0; text-align: right; }
    .inventario-impresion-instruccion {
      margin: 0 0 0.75rem;
      font-size: 0.9rem;
      color: #444;
    }
    @media print {
      .lista-caja-palomear { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .inventario-lista-item { page-break-inside: avoid; }
    }
  </style>
  <?php endif; ?>
</head>
<body class="<?= $hoja ? 'recibo-hoja recibo-mes' : 'recibo-page' ?>">
<?php if ($hoja): ?>
  <div class="recibo-sheet-wrap">
    <div class="recibo-sheet" id="reciboSheet">
      <div class="recibo-header">
        <div class="recibo-header-logo">
          <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
          <div>
            <strong>Sistema de Almacén</strong>
            <span class="recibo-subtitulo">Inventario actual — Conteo físico</span>
          </div>
        </div>
        <div class="recibo-ref">
          <span class="recibo-ref-label">Fecha</span>
          <span class="recibo-ref-valor"><?= date('d/m/Y') ?></span>
        </div>
      </div>

      <p class="inventario-impresion-instruccion">Marque con una paloma (✓) en la casilla <strong>¿Hay?</strong> los artículos que confirme en almacén.</p>

      <div class="recibo-tabla-wrap">
        <ul class="inventario-lista-impresion">
          <li class="inventario-lista-impresion-header">
            <span class="lista-col-caja">¿Hay?</span>
            <span class="lista-col-num">#</span>
            <span class="lista-col-codigo">Código</span>
            <span class="lista-col-nombre">Producto</span>
            <span class="lista-col-unidad">Unid.</span>
            <span class="lista-col-cant">Cant.</span>
          </li>
          <?php if (empty($inventarioActual)): ?>
            <li class="inventario-lista-item">
              <span class="lista-col-caja">—</span>
              <span class="lista-col-num">—</span>
              <span class="lista-col-codigo">—</span>
              <span class="lista-col-nombre">No hay productos en inventario.</span>
              <span class="lista-col-unidad">—</span>
              <span class="lista-col-cant">—</span>
            </li>
          <?php else: ?>
            <?php foreach ($inventarioActual as $i => $inv): ?>
              <?php $stock = (int) $inv['stock']; ?>
              <li class="inventario-lista-item">
                <span class="lista-col-caja"><span class="lista-caja-palomear" aria-label="Marcar si hay"></span></span>
                <span class="lista-col-num"><?= $i + 1 ?></span>
                <span class="lista-col-codigo"><?= htmlspecialchars($inv['codigo'] ?? '—') ?></span>
                <span class="lista-col-nombre"><?= htmlspecialchars($inv['nombre']) ?></span>
                <span class="lista-col-unidad"><?= htmlspecialchars($inv['unidad'] ?? 'und') ?></span>
                <span class="lista-col-cant"><?= number_format($stock) ?></span>
              </li>
            <?php endforeach; ?>
            <li class="inventario-lista-total">
              <span class="lista-col-caja">—</span>
              <span class="lista-col-num">—</span>
              <span class="lista-col-codigo">—</span>
              <span class="lista-col-nombre">Total unidades (sistema)</span>
              <span class="lista-col-unidad">—</span>
              <span class="lista-col-cant"><?= number_format($totalUnidades) ?></span>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <p class="recibo-pie">Inventario actual. Impreso: <?= date('d/m/Y H:i') ?></p>
    </div>
  </div>

  <div class="recibo-hoja-toolbar no-print">
    <button type="button" class="btn btn-primary" id="btnImprimir">Imprimir inventario</button>
    <button type="button" class="btn btn-secondary" id="btnCerrar">Cerrar ventana</button>
  </div>

  <script>
  (function() {
    var i = document.getElementById('btnImprimir');
    var c = document.getElementById('btnCerrar');
    if (i) i.addEventListener('click', function() { window.print(); });
    if (c) c.addEventListener('click', function() { window.close(); });
  })();
  </script>

<?php else: ?>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
      </div>
      <a href="inventario.php?vista=actual" class="btn btn-secondary">Volver a inventario</a>
    </header>
    <div class="recibo-recibido-msg">
      <h1>Imprimir inventario actual</h1>
      <p>Listado del inventario actual con espacio para marcar (palomear) lo que sí hay en almacén.</p>
      <p class="recibo-recibido-accion">
        <a href="imprimir-inventario-actual.php?hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-lg">Abrir inventario para imprimir</a>
      </p>
    </div>
  </div>
<?php endif; ?>
</body>
</html>
