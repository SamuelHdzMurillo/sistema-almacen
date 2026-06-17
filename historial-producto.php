<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/inventario.php';

$nav_activo = 'inventario';
$productoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$hoja = isset($_GET['hoja']) && $_GET['hoja'] === '1';

$historial = $productoId > 0 ? historialProducto($productoId) : null;

if ($historial === null) {
    http_response_code(404);
    $titulo = 'Producto no encontrado';
} else {
    $p = $historial['producto'];
    $titulo = 'Historial — ' . ($p['nombre'] ?? 'Producto');
}

$almacenNombre = getNombreAlmacenActivo();

function fechaCorta(?string $fecha): string {
    if (!$fecha) return '—';
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : htmlspecialchars($fecha);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?> - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=15">
  <style>
    .hist-prod-meta { color: #555; font-size: 0.95rem; margin: 0.25rem 0 0; }
  </style>
  <?php if ($hoja): ?>
  <style media="print">
    @page {
      size: letter;
      margin: 16mm 14mm 22mm 14mm;
      @bottom-center {
        content: "Hoja " counter(page);
        font-size: 10pt;
        color: #000;
        font-family: "Segoe UI", system-ui, sans-serif;
      }
    }
  </style>
  <style>
    /* Documento simple: tablas planas y compactas, sin tarjetas */
    body.recibo-hoja .inventario-panel {
      border: none; box-shadow: none; background: none; border-radius: 0;
      margin: 0 0 0.9rem; padding: 0;
    }
    body.recibo-hoja .inventario-panel-cabecera {
      border: none; background: none; padding: 0 0 0.15rem; margin: 0;
    }
    body.recibo-hoja .inventario-panel-titulo { font-size: 1rem; margin: 0; }
    body.recibo-hoja .inventario-panel-contador { display: none; }
    body.recibo-hoja .inventario-panel-cuerpo { padding: 0; }
    body.recibo-hoja .inventario-tabla-wrap { border: none; box-shadow: none; }
    body.recibo-hoja .inventario-tabla { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    body.recibo-hoja .inventario-tabla th,
    body.recibo-hoja .inventario-tabla td {
      padding: 0.2rem 0.4rem; border: none; border-bottom: 1px solid #ccc;
    }
    body.recibo-hoja .inventario-tabla thead th { border-bottom: 1.5px solid #333; }
    body.recibo-hoja .inventario-tabla tbody tr:nth-child(even) { background: none; }
  </style>
  <?php endif; ?>
</head>
<body class="<?= $hoja ? 'recibo-hoja' : 'pagina-inventario' ?>">

<?php if ($historial === null): ?>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
      </div>
      <a href="inventario.php?vista=actual" class="btn btn-secondary">Volver a inventario</a>
    </header>
    <main class="inventario-main">
      <p class="inventario-empty">El producto solicitado no existe.</p>
    </main>
  </div>

<?php elseif ($hoja): ?>
  <?php $p = $historial['producto']; ?>
  <div class="recibo-sheet-wrap">
    <div class="recibo-sheet" id="reciboSheet">
      <div class="recibo-header">
        <div class="recibo-header-logo">
          <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
          <div>
            <strong>Sistema de Almacén</strong>
            <span class="recibo-subtitulo">Historial de producto<?= $almacenNombre !== '' ? ' · ' . htmlspecialchars($almacenNombre) : '' ?></span>
          </div>
        </div>
        <div class="recibo-ref">
          <span class="recibo-ref-label">Fecha</span>
          <span class="recibo-ref-valor"><?= date('d/m/Y') ?></span>
        </div>
      </div>

      <h2 style="margin:0.5rem 0 0;"><?= htmlspecialchars($p['nombre']) ?></h2>
      <p class="hist-prod-meta">
        Código: <strong><?= htmlspecialchars($p['codigo'] ?? '—') ?></strong> ·
        Unidad: <strong><?= htmlspecialchars($p['unidad'] ?? 'und') ?></strong> ·
        Stock actual: <strong><?= number_format($historial['stock']) ?></strong>
      </p>

      <?php include __DIR__ . '/includes/_historial_producto_cuerpo.php'; ?>

      <p class="recibo-pie">Historial de producto. Impreso: <?= date('d/m/Y H:i') ?></p>
    </div>
  </div>

  <div class="recibo-hoja-toolbar no-print">
    <button type="button" class="btn btn-primary" id="btnImprimir">Imprimir historial</button>
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
  <?php $p = $historial['producto']; ?>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Historial de producto</span>
      </div>
      <div class="header-actions">
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <main class="inventario-main">
      <header class="inventario-page-header">
        <div class="inventario-page-header-top">
          <div class="inventario-page-header-texto">
            <h1 class="inventario-page-titulo"><?= htmlspecialchars($p['nombre']) ?></h1>
            <p class="hist-prod-meta">
              Código: <strong><?= htmlspecialchars($p['codigo'] ?? '—') ?></strong> ·
              Unidad: <strong><?= htmlspecialchars($p['unidad'] ?? 'und') ?></strong> ·
              Stock actual: <strong><?= number_format($historial['stock']) ?></strong>
            </p>
          </div>
          <div class="inventario-page-header-accion">
            <a href="inventario.php?vista=actual" class="btn btn-secondary">Volver</a>
            <a href="historial-producto.php?id=<?= (int)$p['id'] ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-imprimir-recibo">
              Imprimir historial
            </a>
          </div>
        </div>
      </header>

      <section class="inventario-seccion">
        <?php include __DIR__ . '/includes/_historial_producto_cuerpo.php'; ?>
      </section>
    </main>
  </div>
<?php endif; ?>
</body>
</html>
