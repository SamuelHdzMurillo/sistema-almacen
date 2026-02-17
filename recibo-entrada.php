<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';

$id = (int)($_GET['id'] ?? 0);
$entrada = $id ? obtenerEntradaConDetalle($id) : null;

if (!$entrada) {
    header('Location: transacciones.php');
    exit;
}

$hoja = isset($_GET['hoja']) && $_GET['hoja'] === '1';
$titulo = 'Recibo de entrada - ' . $entrada['referencia'];
$totalUnidades = array_sum(array_column($entrada['detalle'], 'cantidad'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?></title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=5">
  <?php if ($hoja): ?>
  <style media="print">@page { size: letter; margin: 10mm; }</style>
  <?php endif; ?>
</head>
<body class="<?= $hoja ? 'recibo-hoja' : 'recibo-page' ?>">
<?php if ($hoja): ?>
  <!-- Vista hoja tipo PDF: solo el documento para firmar e imprimir -->
  <div class="recibo-sheet-wrap">
    <div class="recibo-sheet" id="reciboSheet">
      <div class="recibo-header">
        <div class="recibo-header-logo">
          <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
          <div>
            <strong>Sistema de Almacén</strong>
            <span class="recibo-subtitulo">Comprobante de recepción de material (entrada)</span>
          </div>
        </div>
        <div class="recibo-ref">
          <span class="recibo-ref-label">Folio</span>
          <span class="recibo-ref-valor"><?= htmlspecialchars($entrada['referencia']) ?></span>
        </div>
      </div>

      <div class="recibo-datos">
        <div class="recibo-dato">
          <span class="recibo-dato-label">Fecha de entrada</span>
          <span class="recibo-dato-valor"><?= htmlspecialchars($entrada['fecha']) ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Proveedor</span>
          <span class="recibo-dato-valor"><?= htmlspecialchars($entrada['proveedor_nombre'] ?? '—') ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Quien recibe en almacén</span>
          <span class="recibo-dato-valor recibo-receptor"><?= htmlspecialchars($entrada['quien_recibe_nombre'] ?? '—') ?></span>
        </div>
      </div>

      <div class="recibo-tabla-wrap">
        <table class="recibo-tabla">
          <thead>
            <tr>
              <th>#</th>
              <th>Descripción del artículo</th>
              <th>Cantidad</th>
              <th>Unidad</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entrada['detalle'] as $i => $d): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
              <td class="recibo-cantidad-item"><?= (int)$d['cantidad'] ?></td>
              <td><?= htmlspecialchars($d['unidad']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="recibo-tr-suma">
              <td></td>
              <td class="recibo-suma-label">Total</td>
              <td class="recibo-total-cell"><?= $totalUnidades ?></td>
              <td>und</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="recibo-total recibo-total-secundario">Total de ítems recibidos: <?= $totalUnidades ?></p>

      <div class="recibo-firma-block">
        <p class="recibo-firma-leyenda">Firma de conformidad de quien recibe el material en almacén.</p>
        <div class="recibo-firmas-doble">
          <div class="recibo-firma-fisica">
            <div class="recibo-firma-linea">
              <span class="recibo-firma-label">Firma de quien recibe</span>
              <span class="recibo-firma-espacio"></span>
            </div>
            <div class="recibo-firma-nombre">
              <span class="recibo-firma-nombre-label">Nombre:</span>
              <span class="recibo-firma-nombre-valor"><?= htmlspecialchars($entrada['quien_recibe_nombre'] ?? '—') ?></span>
            </div>
          </div>
        </div>
      </div>

      <p class="recibo-pie">Documento generado por el sistema de control de almacén. Quien recibe debe firmar en el espacio indicado.</p>
    </div>
  </div>

  <div class="recibo-hoja-toolbar no-print">
    <button type="button" class="btn btn-primary" id="btnImprimir">Imprimir recibo</button>
    <button type="button" class="btn btn-secondary" id="btnCerrar">Cerrar ventana</button>
  </div>

  <script>
  (function() {
    document.getElementById('btnImprimir').addEventListener('click', function() { window.print(); });
    document.getElementById('btnCerrar').addEventListener('click', function() { window.close(); });
  })();
  </script>

<?php else: ?>
  <!-- Vista normal: resumen y botón para abrir la hoja para firmar -->
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
      </div>
      <a href="transacciones.php" class="btn btn-secondary">Volver a transacciones</a>
    </header>

    <div class="recibo-recibido-msg">
      <h1>Recibo de entrada generado</h1>
      <p>Se ha registrado la entrada <strong><?= htmlspecialchars($entrada['referencia']) ?></strong>. Abra el recibo para imprimirlo; quien recibe firmará a mano en el espacio indicado.</p>
      <div class="recibo-recibido-preview">
        <span><strong>Folio:</strong> <?= htmlspecialchars($entrada['referencia']) ?></span>
        <span><strong>Proveedor:</strong> <?= htmlspecialchars($entrada['proveedor_nombre'] ?? '—') ?></span>
        <span><strong>Quien recibe:</strong> <?= htmlspecialchars($entrada['quien_recibe_nombre'] ?? '—') ?></span>
        <span><strong>Fecha:</strong> <?= htmlspecialchars($entrada['fecha']) ?></span>
        <span><strong>Ítems:</strong> <?= $totalUnidades ?></span>
      </div>
      <p class="recibo-recibido-accion">
        <a href="recibo-entrada.php?id=<?= (int)$id ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-lg" id="btnAbrirHoja">Abrir recibo para imprimir</a>
      </p>
      <p class="recibo-recibido-hint">Se abrirá el comprobante en formato hoja. Imprímalo o guarde como PDF; quien recibe debe firmar a mano en el espacio indicado.</p>
    </div>
  </div>

  <script>
  (function() {
    var btn = document.getElementById('btnAbrirHoja');
    if (btn && btn.href) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        window.open(btn.href, 'recibo_entrada_firma', 'width=820,height=1000,scrollbars=yes,resizable=yes');
        return false;
      });
    }
  })();
  </script>
<?php endif; ?>
</body>
</html>
