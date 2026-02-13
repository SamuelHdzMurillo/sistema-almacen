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
  <link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body class="pagina-inventario">
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

    <nav class="nav-links">
      <a href="index.php">Inicio</a>
      <a href="transacciones.php">Transacciones</a>
      <a href="inventario.php" class="active">Inventario</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>

    <main class="inventario-main">
      <!-- Cabecera: título + período + acción principal -->
      <header class="inventario-page-header">
        <div class="inventario-page-header-texto">
          <h1 class="inventario-page-titulo">Inventario</h1>
          <p class="inventario-page-subtitulo"><?= $vista === 'actual' ? 'Stock en existencia por producto' : 'Resumen por mes · ' . htmlspecialchars($periodoTexto) ?></p>
        </div>
        <?php if ($vista === 'mes'): ?>
        <div class="inventario-page-header-accion">
          <a href="recibo-mes.php?anio=<?= $anio ?>&mes=<?= $mes ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-imprimir-recibo">
            Imprimir recibo del mes
          </a>
        </div>
        <?php endif; ?>
      </header>

      <!-- Pestañas -->
      <nav class="inventario-tabs" role="tablist">
        <a href="inventario.php?vista=actual" class="inventario-tab <?= $vista === 'actual' ? 'active' : '' ?>" role="tab">Inventario actual</a>
        <a href="inventario.php?vista=mes&amp;anio=<?= $anio ?>&amp;mes=<?= $mes ?>" class="inventario-tab <?= $vista === 'mes' ? 'active' : '' ?>" role="tab">Inventario por mes</a>
      </nav>

      <!-- Contenido pestaña: Inventario actual -->
      <div class="inventario-pestana-content <?= $vista === 'actual' ? 'activa' : '' ?>" id="pestana-actual" role="tabpanel">
      <section class="inventario-seccion inventario-seccion-actual">
        <h2 class="inventario-seccion-titulo">Inventario actual</h2>
        <article class="inventario-panel inventario-panel-actual">
          <header class="inventario-panel-cabecera">
            <h3 class="inventario-panel-titulo">Stock por producto</h3>
            <span class="inventario-panel-contador"><?= count($inventarioActual) ?></span>
          </header>
          <div class="inventario-panel-cuerpo">
            <div class="inventario-tabla-wrap">
              <table class="inventario-tabla inventario-tabla-actual">
                <thead>
                  <tr>
                    <th class="inventario-col-codigo">Código</th>
                    <th class="inventario-col-producto">Producto</th>
                    <th class="inventario-col-unidad">Unidad</th>
                    <th class="inventario-th-num inventario-col-cantidad">Cantidad actual</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($inventarioActual)): ?>
                    <tr><td colspan="4" class="inventario-empty">No hay productos registrados.</td></tr>
                  <?php else: ?>
                    <?php
                    $totalUnidades = 0;
                    foreach ($inventarioActual as $inv):
                      $stock = (int) $inv['stock'];
                      $totalUnidades += $stock;
                    ?>
                      <tr>
                        <td class="inventario-col-codigo"><?= htmlspecialchars($inv['codigo'] ?? '—') ?></td>
                        <td class="inventario-col-producto"><strong><?= htmlspecialchars($inv['nombre']) ?></strong></td>
                        <td class="inventario-col-unidad"><?= htmlspecialchars($inv['unidad'] ?? 'und') ?></td>
                        <td class="inventario-th-num inventario-col-cantidad <?= $stock > 0 ? 'qty-pos' : ($stock < 0 ? 'qty-neg' : '') ?>"><?= number_format($stock) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <tr class="inventario-tabla-total">
                      <td colspan="3" class="inventario-td-total-label"><strong>Total unidades en almacén</strong></td>
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
                    <th>Responsable</th>
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
                        <td><?= htmlspecialchars($e['responsable']) ?></td>
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
                          <span title="Entrega"><?= htmlspecialchars($s['nombre_entrega']) ?></span>
                          <span class="inventario-sep">/</span>
                          <span title="Recibe"><?= htmlspecialchars($s['nombre_receptor']) ?></span>
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
</body>
</html>
