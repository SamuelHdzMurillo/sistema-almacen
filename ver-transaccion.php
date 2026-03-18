<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';
require_once __DIR__ . '/includes/salidas.php';

$tipo = $_GET['tipo'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($tipo !== 'in' && $tipo !== 'out' || $id <= 0) {
    header('Location: transacciones.php');
    exit;
}

if ($tipo === 'in') {
    $transaccion = obtenerEntradaConDetalle($id);
    $titulo = 'Entrada ' . ($transaccion['referencia'] ?? '');
} else {
    $transaccion = obtenerSalidaConDetalle($id);
    $titulo = 'Salida ' . ($transaccion['referencia'] ?? '');
}

if (!$transaccion) {
    header('Location: transacciones.php');
    exit;
}

$estado = $transaccion['estado'] ?? 'completada';
$puedeCancelar = ($estado === 'completada');
$mensajeLineaCancelada = isset($_GET['cancelado_linea']) && $_GET['cancelado_linea'] === '1';
$errorLinea = isset($_GET['error_linea']) && $_GET['error_linea'] === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?> - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=3">
</head>
<body class="pagina-detalle-transaccion">
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
      </div>
      <div class="header-actions">
        <a href="transacciones.php" class="btn btn-secondary">← Volver a transacciones</a>
      </div>
    </header>
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <header class="page-header">
      <div class="page-header-texto">
        <h1 class="page-title"><?= htmlspecialchars($titulo) ?></h1>
        <p><span class="status-badge status-<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></span></p>
      </div>
      <?php if ($tipo === 'in'): ?>
        <div class="page-header-accion detalle-transaccion-header-actions">
          <a href="nueva-entrada.php?id=<?= (int)$id ?>" class="btn btn-primary btn-sm">Editar</a>
          <a href="recibo-entrada.php?id=<?= (int)$id ?>" class="btn btn-secondary btn-sm">Ver recibo</a>
          <a href="recibo-entrada.php?id=<?= (int)$id ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-sm" id="btnImprimirReciboEntrada">Imprimir recibo</a>
        </div>
        <script>
          document.getElementById('btnImprimirReciboEntrada').addEventListener('click', function(e) {
            e.preventDefault();
            window.open(this.href, 'recibo_entrada_imprimir', 'width=820,height=1000,scrollbars=yes,resizable=yes');
            return false;
          });
        </script>
      <?php elseif ($tipo === 'out'): ?>
        <div class="page-header-accion detalle-transaccion-header-actions">
          <a href="nueva-salida.php?id=<?= (int)$id ?>" class="btn btn-primary btn-sm">Editar</a>
          <a href="recibo.php?id=<?= (int)$id ?>" class="btn btn-secondary btn-sm">Ver recibo</a>
          <a href="recibo.php?id=<?= (int)$id ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary btn-sm" id="btnImprimirRecibo">Imprimir recibo</a>
        </div>
        <script>
          document.getElementById('btnImprimirRecibo').addEventListener('click', function(e) {
            e.preventDefault();
            window.open(this.href, 'recibo_imprimir', 'width=820,height=1000,scrollbars=yes,resizable=yes');
            return false;
          });
        </script>
      <?php endif; ?>
    </header>
    <?php if ($mensajeLineaCancelada): ?>
      <div class="alert alert-success">Línea cancelada. El stock se ha actualizado.</div>
    <?php endif; ?>
    <?php if ($errorLinea): ?>
      <div class="alert alert-error">No se pudo cancelar la línea (ya estaba cancelada o no existe).</div>
    <?php endif; ?>

    <div class="detalle-transaccion-layout">
      <div class="form-card detalle-transaccion-meta">
        <div class="detalle-transaccion-meta-grid">
          <?php if ($tipo === 'in'): ?>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Fecha</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['fecha']) ?></span>
            </div>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Factura / Orden</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['factura'] ?? '—') ?></span>
            </div>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Proveedor</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['proveedor_nombre'] ?? '—') ?></span>
            </div>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Quien recibe</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['quien_recibe_nombre'] ?? '—') ?></span>
            </div>
          <?php else: ?>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Fecha</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['fecha']) ?></span>
            </div>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Quien entrega</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['nombre_entrega'] ?? '—') ?></span>
            </div>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Plantel</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['plantel_nombre'] ?? '—') ?></span>
            </div>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Quien recibe</span>
              <span class="detalle-meta-value"><?= htmlspecialchars($transaccion['nombre_receptor'] ?? '—') ?></span>
            </div>
          <?php endif; ?>

          <?php
            $registradoAt = $transaccion['created_at'] ?? null;
            $registradoPor = $transaccion['created_by_nombre'] ?? null;
            if ($registradoAt || $registradoPor):
          ?>
            <div class="detalle-meta-item">
              <span class="detalle-meta-label">Registrado</span>
              <span class="detalle-meta-value">
                <?php if ($registradoAt): ?>
                  <?= date('d/m/Y H:i', strtotime($registradoAt)) ?>
                <?php endif; ?>
                <?php if ($registradoPor): ?>
                  <?= $registradoAt ? ' por ' : '' ?><strong><?= htmlspecialchars($registradoPor) ?></strong>
                <?php endif; ?>
              </span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-card detalle-transaccion-detalle">
        <h3 class="detalle-transaccion-title">Detalle</h3>
        <div class="table-wrap detalle-transaccion-table-wrap">
          <table class="detalle-transaccion-table">
            <thead>
              <tr>
                <th class="col-producto">Producto</th>
                <th class="col-cantidad">Cantidad</th>
                <th class="col-unidad">Unidad</th>
                <?php if ($tipo === 'in'): ?><th class="col-estado">Estado</th><th class="col-acciones">Acciones</th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transaccion['detalle'] as $d):
                $estadoLinea = $d['estado'] ?? 'activa';
                $lineaActiva = ($estadoLinea === 'activa');
              ?>
              <tr class="<?= $tipo === 'in' && !$lineaActiva ? 'linea-cancelada' : '' ?>">
                <td class="col-producto"><?= htmlspecialchars($d['producto_nombre']) ?></td>
                <td class="col-cantidad"><?= (int)$d['cantidad'] ?></td>
                <td class="col-unidad"><?= htmlspecialchars($d['unidad']) ?></td>
                <?php if ($tipo === 'in'): ?>
                <td class="col-estado"><span class="status-badge status-<?= htmlspecialchars($estadoLinea) ?>"><?= htmlspecialchars($estadoLinea) ?></span></td>
                <td class="col-acciones">
                  <?php if ($lineaActiva && $puedeCancelar): ?>
                  <form method="post" action="cancelar-linea-entrada.php" onsubmit="return confirm('¿Cancelar esta línea? El stock dejará de contar estos ítems.');">
                    <input type="hidden" name="id" value="<?= (int)($d['id'] ?? 0) ?>">
                    <input type="hidden" name="confirmar" value="1">
                    <button type="submit" class="btn btn-secondary btn-sm btn-accent-orange">Cancelar línea</button>
                  </form>
                  <?php else: ?>
                    <span class="detalle-td-null">—</span>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if ($puedeCancelar): ?>
        <div class="detalle-transaccion-cancel-wrapper">
          <div class="detalle-transaccion-cancel-card">
            <h3 class="detalle-transaccion-title">Cancelar transacción</h3>
            <p>Al cancelar esta transacción, el stock volverá a contar estos ítems (en una salida se devuelve al inventario; en una entrada se resta). La transacción quedará marcada como cancelada.</p>
            <form method="post" action="<?= $tipo === 'in' ? 'cancelar-entrada.php' : 'cancelar-salida.php' ?>" onsubmit="return confirm('¿Está seguro de cancelar esta transacción?');">
              <input type="hidden" name="id" value="<?= (int)$id ?>">
              <input type="hidden" name="confirmar" value="1">
              <button type="submit" class="btn btn-secondary btn-accent-orange">Cancelar transacción</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
