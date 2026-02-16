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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titulo) ?> - Sistema de Almacén</title>
  <link rel="stylesheet" href="assets/css/style.css?v=2">
</head>
<body>
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
    <nav class="nav-links">
      <a href="index.php">Dashboard</a>
      <a href="transacciones.php" class="active">Transacciones</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="inventario.php">Inventario</a>
      <a href="productos.php">Productos</a>
    </nav>

    <h1><?= htmlspecialchars($titulo) ?></h1>
    <p><span class="status-badge status-<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></span></p>

    <?php if ($tipo === 'out'): ?>
    <p class="recibo-reimprimir-acciones">
      <a href="recibo.php?id=<?= (int)$id ?>" class="btn btn-secondary">Ver recibo</a>
      <a href="recibo.php?id=<?= (int)$id ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-primary" id="btnImprimirRecibo">Imprimir recibo</a>
    </p>
    <script>
      document.getElementById('btnImprimirRecibo').addEventListener('click', function(e) {
        e.preventDefault();
        window.open(this.href, 'recibo_imprimir', 'width=820,height=1000,scrollbars=yes,resizable=yes');
        return false;
      });
    </script>
    <?php endif; ?>

    <div class="form-card">
      <?php if ($tipo === 'in'): ?>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($transaccion['fecha']) ?></p>
        <p><strong>Responsable:</strong> <?= htmlspecialchars($transaccion['responsable']) ?></p>
      <?php else: ?>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($transaccion['fecha']) ?></p>
        <p><strong>Quien entrega:</strong> <?= htmlspecialchars($transaccion['nombre_entrega'] ?? '—') ?></p>
        <p><strong>Plantel:</strong> <?= htmlspecialchars($transaccion['plantel_nombre'] ?? '—') ?></p>
        <p><strong>Quien recibe:</strong> <?= htmlspecialchars($transaccion['nombre_receptor'] ?? '—') ?></p>
      <?php endif; ?>
      <?php
        $registradoAt = $transaccion['created_at'] ?? null;
        $registradoPor = $transaccion['created_by_nombre'] ?? null;
        if ($registradoAt || $registradoPor):
      ?>
      <p><strong>Registrado:</strong>
        <?php if ($registradoAt): ?>
          <?= date('d/m/Y H:i', strtotime($registradoAt)) ?>
        <?php endif; ?>
        <?php if ($registradoPor): ?>
          <?= $registradoAt ? ' por ' : '' ?><strong><?= htmlspecialchars($registradoPor) ?></strong>
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>

    <div class="form-card">
      <h3 style="margin-top:0;">Detalle</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th>Cantidad</th>
              <th>Unidad</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transaccion['detalle'] as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
              <td><?= (int)$d['cantidad'] ?></td>
              <td><?= htmlspecialchars($d['unidad']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($puedeCancelar): ?>
    <div class="form-card" style="border-color: var(--accent-orange);">
      <h3 style="margin-top:0;">Cancelar transacción</h3>
      <p>Al cancelar esta transacción, el stock volverá a contar estos ítems (en una salida se devuelve al inventario; en una entrada se resta). La transacción quedará marcada como cancelada.</p>
      <form method="post" action="<?= $tipo === 'in' ? 'cancelar-entrada.php' : 'cancelar-salida.php' ?>" onsubmit="return confirm('¿Está seguro de cancelar esta transacción?');">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="confirmar" value="1">
        <button type="submit" class="btn btn-secondary" style="background: var(--accent-orange); color: #fff;">Cancelar transacción</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
