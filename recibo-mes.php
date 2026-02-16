<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/inventario_mes.php';

$anio = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
$mes  = isset($_GET['mes'])  ? (int) $_GET['mes']  : (int) date('n');
if ($mes < 1 || $mes > 12) $mes = (int) date('n');
if ($anio < 2000 || $anio > 2100) $anio = (int) date('Y');

$entradas = listarEntradasPorMes($anio, $mes);
$salidas  = listarSalidasPorMes($anio, $mes);
$resumen  = resumenMes($anio, $mes);
$mesNombre = $mesesNombres[$mes] ?? 'Mes';
$periodoTexto = $mesNombre . ' ' . $anio;
$titulo = "Recibo de inventario — {$periodoTexto}";

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
    /* Tamaño carta: 8.5" x 11" (216mm x 279mm) */
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
  <?php endif; ?>
</head>
<body class="<?= $hoja ? 'recibo-hoja recibo-mes' : 'recibo-page' ?>">
<?php if ($hoja): ?>
  <div class="recibo-sheet-wrap">
    <div class="recibo-sheet" id="reciboSheet">
      <!-- Mismo encabezado que recibo de salida -->
      <div class="recibo-header">
        <div class="recibo-header-logo">
          <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
          <div>
            <strong>Sistema de Almacén</strong>
            <span class="recibo-subtitulo">Resumen de inventario mensual</span>
          </div>
        </div>
        <div class="recibo-ref">
          <span class="recibo-ref-label">Período</span>
          <span class="recibo-ref-valor"><?= htmlspecialchars($periodoTexto) ?></span>
        </div>
      </div>

      <!-- Datos del período (mismo estilo que recibo-datos de salida) -->
      <div class="recibo-datos">
        <div class="recibo-dato">
          <span class="recibo-dato-label">Unidades entradas</span>
          <span class="recibo-dato-valor"><?= number_format($resumen['total_entradas']) ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Unidades salidas</span>
          <span class="recibo-dato-valor"><?= number_format($resumen['total_salidas']) ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Documentos de entrada</span>
          <span class="recibo-dato-valor"><?= count($entradas) ?></span>
        </div>
        <div class="recibo-dato">
          <span class="recibo-dato-label">Documentos de salida</span>
          <span class="recibo-dato-valor"><?= count($salidas) ?></span>
        </div>
      </div>

      <?php if (!empty($entradas)): ?>
      <h3 class="recibo-seccion-titulo">Entradas</h3>
      <div class="recibo-tabla-wrap">
        <table class="recibo-tabla">
          <thead>
            <tr>
              <th>#</th>
              <th>Descripción / Artículo</th>
              <th>Cantidad</th>
              <th>Unidad</th>
            </tr>
          </thead>
            <?php foreach ($entradas as $i => $e): ?>
              <?php $totalLineas = array_sum(array_column($e['detalle'], 'cantidad')); ?>
          <tbody class="recibo-tbody-doc">
              <tr class="recibo-tr-doc-header">
                <td><?= $i + 1 ?></td>
                <td class="recibo-cell-desc"><?= htmlspecialchars($e['referencia']) ?> · <?= htmlspecialchars($e['fecha']) ?> · <?= htmlspecialchars($e['proveedor_nombre'] ?? '—') ?> · <?= htmlspecialchars($e['quien_recibe_nombre'] ?? '—') ?></td>
                <td colspan="2"></td>
              </tr>
              <?php foreach ($e['detalle'] as $j => $d): ?>
              <tr class="recibo-tr-item">
                <td></td>
                <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
                <td class="recibo-cantidad-item"><?= (int)$d['cantidad'] ?></td>
                <td><?= htmlspecialchars($d['unidad']) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="recibo-tr-suma">
                <td></td>
                <td class="recibo-suma-label">Total</td>
                <td class="recibo-total-cell"><?= $totalLineas ?></td>
                <td>und</td>
              </tr>
          </tbody>
            <?php endforeach; ?>
        </table>
      </div>
      <?php endif; ?>

      <?php if (!empty($salidas)): ?>
      <h3 class="recibo-seccion-titulo">Salidas</h3>
      <div class="recibo-tabla-wrap">
        <table class="recibo-tabla">
          <thead>
            <tr>
              <th>#</th>
              <th>Descripción / Artículo</th>
              <th>Cantidad</th>
              <th>Unidad</th>
            </tr>
          </thead>
            <?php foreach ($salidas as $i => $s): ?>
              <?php $totalLineas = array_sum(array_column($s['detalle'], 'cantidad')); ?>
          <tbody class="recibo-tbody-doc">
              <tr class="recibo-tr-doc-header">
                <td><?= $i + 1 ?></td>
                <td class="recibo-cell-desc"><?= htmlspecialchars($s['referencia']) ?> · <?= htmlspecialchars($s['fecha']) ?> · <?= htmlspecialchars($s['nombre_entrega'] ?? '—') ?> / <?= htmlspecialchars($s['plantel_nombre'] ?? '—') ?> / <?= htmlspecialchars($s['nombre_receptor'] ?? '—') ?></td>
                <td colspan="2"></td>
              </tr>
              <?php foreach ($s['detalle'] as $j => $d): ?>
              <tr class="recibo-tr-item">
                <td></td>
                <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
                <td class="recibo-cantidad-item"><?= (int)$d['cantidad'] ?></td>
                <td><?= htmlspecialchars($d['unidad']) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr class="recibo-tr-suma">
                <td></td>
                <td class="recibo-suma-label">Total</td>
                <td class="recibo-total-cell"><?= $totalLineas ?></td>
                <td>und</td>
              </tr>
          </tbody>
            <?php endforeach; ?>
        </table>
      </div>
      <?php endif; ?>

      <?php if (empty($entradas) && empty($salidas)): ?>
        <p class="recibo-sin-movimientos">No hay movimientos registrados en este mes.</p>
      <?php endif; ?>

      <p class="recibo-total recibo-total-secundario">
        Resumen del mes: <?= number_format($resumen['total_entradas']) ?> unidades entradas,
        <?= number_format($resumen['total_salidas']) ?> unidades salidas.
      </p>

      <!-- Misma zona de firma que recibo de salida (una firma: quien recibe) -->
      <div class="recibo-firma-block">
        <p class="recibo-firma-leyenda">Documento de resumen mensual generado por el sistema de control de almacén.</p>
        <div class="recibo-firma-fisica">
          <div class="recibo-firma-linea">
            <span class="recibo-firma-label">Quien recibe en almacén</span>
            <span class="recibo-firma-espacio"></span>
          </div>
        </div>
      </div>

      <p class="recibo-pie">Recibo de inventario <?= htmlspecialchars($periodoTexto) ?>. Impreso: <?= date('d/m/Y H:i') ?></p>
    </div>
  </div>

  <div class="recibo-hoja-toolbar no-print">
    <button type="button" class="btn btn-primary" id="btnImprimir">Imprimir recibo</button>
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
      <a href="inventario.php?anio=<?= $anio ?>&mes=<?= $mes ?>" class="btn btn-secondary">Volver a inventario</a>
    </header>
    <div class="recibo-recibido-msg">
      <h1>Recibo del mes</h1>
      <p>Resumen de <strong><?= htmlspecialchars($periodoTexto) ?></strong>.</p>
      <div class="recibo-recibido-preview">
        <span><strong>Entradas:</strong> <?= number_format($resumen['total_entradas']) ?> unidades (<?= count($entradas) ?> doc.)</span>
        <span><strong>Salidas:</strong> <?= number_format($resumen['total_salidas']) ?> unidades (<?= count($salidas) ?> doc.)</span>
      </div>
      <p class="recibo-recibido-accion">
        <a href="recibo-mes.php?anio=<?= $anio ?>&mes=<?= $mes ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-lg">Abrir recibo para imprimir</a>
      </p>
    </div>
  </div>
<?php endif; ?>
</body>
</html>
