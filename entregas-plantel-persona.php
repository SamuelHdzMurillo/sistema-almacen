<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/transacciones.php';
require_once __DIR__ . '/includes/catalogos_salida.php';

$plantelId = isset($_GET['plantel_id']) ? (int) $_GET['plantel_id'] : null;
if ($plantelId === 0) $plantelId = null;
$receptorId = isset($_GET['receptor_id']) ? (int) $_GET['receptor_id'] : null;
if ($receptorId === 0) $receptorId = null;
$busqueda = trim($_GET['q'] ?? '');

$planteles = listarPlanteles();
$receptores = listarReceptores();
$entregas = listarEntregasPorPlantelYReceptor(
    $plantelId,
    $receptorId,
    $busqueda !== '' ? $busqueda : null,
    300
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entregas por plantel y persona - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
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
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <nav class="nav-links">
      <a href="index.php">Dashboard</a>
      <a href="transacciones.php" class="active">Transacciones</a>
      <a href="inventario.php">Inventario</a>
      <a href="nueva-entrada.php">Nueva entrada</a>
      <a href="nueva-salida.php">Nueva salida</a>
      <a href="productos.php">Productos</a>
    </nav>

    <nav class="nav-links nav-links-sub" style="margin-top:-0.5rem; margin-bottom:1rem;">
      <a href="transacciones.php">Listado de transacciones</a>
      <a href="entregas-plantel-persona.php" class="active">Entregas por plantel y persona</a>
    </nav>

    <section class="section-header">
      <h2>Qué se ha entregado a cada plantel y persona</h2>
      <p class="section-desc">Filtra por plantel y/o por persona que recibe para ver el detalle de entregas.</p>
      <div class="search-filter" style="flex-wrap: wrap; gap: 0.75rem;">
        <form method="get" action="entregas-plantel-persona.php" style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
          <label for="plantel_id" style="font-size:0.9rem;">Plantel:</label>
          <select name="plantel_id" id="plantel_id" style="min-width: 180px;">
            <option value="">— Todos —</option>
            <?php foreach ($planteles as $pl): ?>
              <option value="<?= (int) $pl['id'] ?>" <?= $plantelId === (int) $pl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pl['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="receptor_id" style="font-size:0.9rem;">Persona que recibe:</label>
          <select name="receptor_id" id="receptor_id" style="min-width: 180px;">
            <option value="">— Todos —</option>
            <?php foreach ($receptores as $rec): ?>
              <option value="<?= (int) $rec['id'] ?>" <?= $receptorId === (int) $rec['id'] ? 'selected' : '' ?>><?= htmlspecialchars($rec['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="search" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar..." autocomplete="off" style="min-width: 140px;">
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <a href="entregas-plantel-persona.php" class="btn btn-secondary">Limpiar</a>
        </form>
      </div>
    </section>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Referencia</th>
            <th>Plantel</th>
            <th>Persona que recibe</th>
            <th>Quien entrega</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($entregas)): ?>
            <tr><td colspan="8" class="empty-msg">No hay entregas con los filtros indicados.</td></tr>
          <?php else: ?>
            <?php
            $salidaAnterior = null;
            foreach ($entregas as $e):
              $agruparRef = ($salidaAnterior !== $e['salida_id']);
              $salidaAnterior = $e['salida_id'];
            ?>
            <tr>
              <td><?= htmlspecialchars($e['fecha']) ?></td>
              <td><?= htmlspecialchars($e['referencia']) ?></td>
              <td><?= htmlspecialchars($e['plantel_nombre'] ?? '—') ?></td>
              <td><?= htmlspecialchars($e['receptor_nombre'] ?? '—') ?></td>
              <td><?= htmlspecialchars($e['quien_entrega_nombre'] ?? '—') ?></td>
              <td><?= htmlspecialchars($e['producto_nombre']) ?></td>
              <td class="qty-neg"><?= (int) $e['cantidad'] ?> <?= htmlspecialchars($e['unidad'] ?? 'und') ?></td>
              <td>
                <?php if ($agruparRef): ?>
                  <a href="ver-transaccion.php?tipo=out&id=<?= (int) $e['salida_id'] ?>" class="btn btn-secondary btn-sm">Ver salida</a>
                  <a href="recibo.php?id=<?= (int) $e['salida_id'] ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Imprimir recibo">Imprimir</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
