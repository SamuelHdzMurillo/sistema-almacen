<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/salidas.php';

$id = (int)($_GET['id'] ?? 0);
$salida = $id ? obtenerSalidaConDetalle($id) : null;

if (!$salida) {
    header('Location: transacciones.php');
    exit;
}

$hoja = isset($_GET['hoja']) && $_GET['hoja'] === '1';
$titulo = 'Recibo de salida - ' . $salida['referencia'];
$totalUnidades = array_sum(array_column($salida['detalle'], 'cantidad'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?></title>
  <link rel="stylesheet" href="assets/css/style.css?v=5">
  <?php if ($hoja): ?>
  <style media="print">@page { size: 216mm 330mm; margin: 0; }</style>
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
            <span class="recibo-subtitulo">Comprobante de entrega de material</span>
          </div>
        </div>
        <div class="recibo-ref">
          <span class="recibo-ref-label">Referencia</span>
          <span class="recibo-ref-valor"><?= htmlspecialchars($salida['referencia']) ?></span>
        </div>
      </div>

      <div class="recibo-datos">
        <div class="recibo-dato">
          <span class="recibo-dato-label">Fecha de entrega</span>
          <span class="recibo-dato-valor"><?= htmlspecialchars($salida['fecha']) ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Entrega (quien entrega el material)</span>
          <span class="recibo-dato-valor"><?= htmlspecialchars($salida['nombre_entrega'] ?? '—') ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Recibe (quien recibe el material)</span>
          <span class="recibo-dato-valor recibo-receptor"><?= htmlspecialchars($salida['nombre_receptor']) ?></span>
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
            <?php foreach ($salida['detalle'] as $i => $d): ?>
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
      <p class="recibo-total recibo-total-secundario">Total de ítems entregados: <?= $totalUnidades ?></p>

      <div class="recibo-firma-block">
        <p class="recibo-firma-leyenda">Ambas partes firman de conformidad: quien entrega el material y quien lo recibe.</p>
        <div class="recibo-firmas-doble">
          <div class="recibo-firma-fisica">
            <div class="recibo-firma-linea">
              <span class="recibo-firma-label">Firma de quien entrega</span>
              <span class="recibo-firma-espacio"></span>
            </div>
            <div class="recibo-firma-nombre">
              <span class="recibo-firma-nombre-label">Nombre:</span>
              <span class="recibo-firma-nombre-valor"><?= htmlspecialchars($salida['nombre_entrega'] ?? '—') ?></span>
            </div>
          </div>
          <div class="recibo-firma-fisica">
            <div class="recibo-firma-linea">
              <span class="recibo-firma-label">Firma de quien recibe</span>
              <span class="recibo-firma-espacio"></span>
            </div>
            <div class="recibo-firma-nombre">
              <span class="recibo-firma-nombre-label">Nombre:</span>
              <span class="recibo-firma-nombre-valor"><?= htmlspecialchars($salida['nombre_receptor']) ?></span>
            </div>
          </div>
        </div>
      </div>

      <p class="recibo-pie">Documento generado por el sistema de control de almacén. Ambas partes deben firmar en los espacios indicados.</p>
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
      <h1>Recibo generado</h1>
      <p>Se ha registrado la salida <strong><?= htmlspecialchars($salida['referencia']) ?></strong>. Abra el recibo para imprimirlo; el receptor firmará a mano en el espacio indicado en el documento.</p>
      <div class="recibo-recibido-preview">
        <span><strong>Entrega:</strong> <?= htmlspecialchars($salida['nombre_entrega'] ?? '—') ?></span>
        <span><strong>Recibe:</strong> <?= htmlspecialchars($salida['nombre_receptor']) ?></span>
        <span><strong>Fecha:</strong> <?= htmlspecialchars($salida['fecha']) ?></span>
        <span><strong>Ítems:</strong> <?= $totalUnidades ?></span>
      </div>
      <p class="recibo-recibido-accion">
        <a href="recibo.php?id=<?= (int)$id ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-lg" id="btnAbrirHoja">Abrir recibo para imprimir</a>
      </p>
      <p class="recibo-recibido-hint">Se abrirá el comprobante en formato hoja. Imprímalo o guarde como PDF; el receptor debe firmar a mano en el espacio indicado.</p>
    </div>
  </div>

  <script>
  (function() {
    var btn = document.getElementById('btnAbrirHoja');
    if (btn && btn.href) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        window.open(btn.href, 'recibo_firma', 'width=820,height=1000,scrollbars=yes,resizable=yes');
        return false;
      });
    }
  })();
  </script>
<?php endif; ?>
</body>
</html>
