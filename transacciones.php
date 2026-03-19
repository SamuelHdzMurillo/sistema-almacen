<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/transacciones.php';

$tipo = $_GET['tipo'] ?? null;
$busqueda = trim($_GET['q'] ?? '');
$transacciones = listarTransaccionesRecientes(50, $tipo, $busqueda !== '' ? $busqueda : null);
$mensajeCancelado = isset($_GET['cancelado']) && $_GET['cancelado'] === '1';
$accionesMostradas = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transacciones - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=15">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Transacciones</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <section class="section-header">
      <h2>Transacciones recientes</h2>
      <nav class="nav-links nav-links-sub nav-links-below-title" aria-label="Subnavegación transacciones">
        <a href="transacciones.php" class="nav-link active">Listado de transacciones</a>
        <a href="entregas-plantel-persona.php" class="nav-link">Entregas por plantel y persona</a>
      </nav>
      <?php if ($mensajeCancelado): ?>
        <div class="alert alert-success" style="margin-bottom:1rem;">Transacción cancelada. El stock se ha actualizado.</div>
      <?php endif; ?>
      <div class="search-filter">
        <form method="get" action="transacciones.php" style="display:flex; gap:0.5rem; align-items:center;">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo ?? '') ?>">
          <input type="search" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar transacciones..." autocomplete="off">
          <button type="submit" class="btn btn-secondary">Buscar</button>
        </form>
        <div class="filter-btns">
          <a href="transacciones.php?tipo=<?= $tipo === null ? '' : '' ?>&q=<?= urlencode($busqueda) ?>" class="btn btn-secondary <?= $tipo === null ? 'active' : '' ?>">Todas</a>
          <a href="transacciones.php?tipo=in&q=<?= urlencode($busqueda) ?>" class="btn btn-secondary <?= $tipo === 'in' ? 'active' : '' ?>">Entradas</a>
          <a href="transacciones.php?tipo=out&q=<?= urlencode($busqueda) ?>" class="btn btn-secondary <?= $tipo === 'out' ? 'active' : '' ?>">Salidas</a>
        </div>
      </div>
    </section>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Referencia</th>
            <th>Tipo</th>
            <th>Artículo</th>
            <th>Cant.</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Registrado por</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transacciones)): ?>
            <tr><td colspan="9" class="empty-msg">No hay transacciones con los filtros indicados.</td></tr>
          <?php else: ?>
            <?php foreach ($transacciones as $t): ?>
            <?php
              $key = $t['tipo'] . '-' . $t['id'];
              $mostrarAcciones = !isset($accionesMostradas[$key]);
              if ($mostrarAcciones) $accionesMostradas[$key] = true;
            ?>
            <tr>
              <td><?= htmlspecialchars($t['referencia']) ?></td>
              <td>
                <span class="type-badge <?= $t['tipo'] ?>">
                  <?php if ($t['tipo'] === 'in'): ?><span class="type-badge-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg></span>Stock In<?php else: ?><span class="type-badge-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg></span>Stock Out<?php endif; ?>
                </span>
              </td>
              <td><?= htmlspecialchars($t['item_nombre']) ?></td>
              <td class="<?= $t['tipo'] === 'in' ? 'qty-pos' : 'qty-neg' ?>"><?= $t['cantidad_show'] ?></td>
              <td><?= htmlspecialchars($t['fecha']) ?></td>
              <td><?= !empty($t['created_at']) ? date('H:i', strtotime($t['created_at'])) : '—' ?></td>
              <td><?= htmlspecialchars($t['created_by_nombre'] ?? '—') ?></td>
              <td><span class="status-badge status-<?= $t['estado'] ?>"><?= $t['estado'] ?></span></td>
              <td>
                <?php if ($mostrarAcciones): ?>
                  <a href="ver-transaccion.php?tipo=<?= urlencode($t['tipo']) ?>&id=<?= (int)$t['id'] ?>" class="btn btn-secondary btn-sm">Ver</a>
                  <?php if ($t['tipo'] === 'out'): ?>
                    <a href="recibo.php?id=<?= (int)$t['id'] ?>&hoja=1" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Reimprimir recibo">Imprimir</a>
                  <?php endif; ?>
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
