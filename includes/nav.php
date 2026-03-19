<?php
/**
 * Barra de navegación principal. La pestaña activa se detecta por script actual
 * o se puede forzar con $nav_activo (ej: en ver-transaccion.php usar 'transacciones').
 */
if (!isset($nav_activo)) {
    $script = basename($_SERVER['PHP_SELF'], '.php');
    $map = [
        'index' => 'inicio',
        'transacciones' => 'transacciones',
        'ver-transaccion' => 'transacciones',
        'entregas-plantel-persona' => 'transacciones',
        'almacenes-admin' => 'almacenes-admin',
        'inventario' => 'inventario',
        'nueva-entrada' => 'nueva-entrada',
        'nueva-salida' => 'nueva-salida',
        'productos' => 'productos',
        'ver-logs' => 'logs',
    ];
    $nav_activo = $map[$script] ?? '';
}

$usuarioNombreSesion = (string) ($_SESSION['usuario_nombre'] ?? '');
$usuarioIdSesion = (int) ($_SESSION['usuario_id'] ?? 0);
$esAdmin = ($usuarioNombreSesion === 'Administrador') || ($usuarioIdSesion === 1);

$nav_items = [
    'inicio' => ['url' => 'index.php', 'label' => 'Inicio'],
    'transacciones' => ['url' => 'transacciones.php', 'label' => 'Transacciones'],
    'inventario' => ['url' => 'inventario.php', 'label' => 'Inventario'],
    'nueva-entrada' => ['url' => 'nueva-entrada.php', 'label' => 'Nueva entrada'],
    'nueva-salida' => ['url' => 'nueva-salida.php', 'label' => 'Nueva salida'],
    'productos' => ['url' => 'productos.php', 'label' => 'Productos'],
];

if ($esAdmin) {
    $nav_items['logs'] = ['url' => 'ver-logs.php', 'label' => 'Logs'];
    $nav_items['almacenes-admin'] = ['url' => 'almacenes-admin.php', 'label' => 'Almacenes'];
}
?>
<div class="nav-wrap">
  <nav class="nav-links" aria-label="Navegación principal">
    <?php foreach ($nav_items as $key => $item): ?>
    <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link <?= $nav_activo === $key ? 'active' : '' ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($esAdmin): ?>
    <?php
      $almacenActivo = (int) (getAlmacenActivo() ?? 0);
      $almacenes = listarAlmacenes();
    ?>
    <div class="almacen-selector" style="margin-top:0.75rem; display:flex; gap:0.5rem; align-items:center;">
      <label for="almacen_id_select" style="font-weight:600; font-size:0.95rem;">Almacén:</label>
      <select
        id="almacen_id_select"
        style="min-width:220px;"
        aria-label="Selector de almacén"
      >
        <?php foreach ($almacenes as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === $almacenActivo) ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <script>
      (function() {
        var sel = document.getElementById('almacen_id_select');
        if (!sel) return;
        sel.addEventListener('change', function() {
          try {
            var url = new URL(window.location.href);
            url.searchParams.set('almacen_id', this.value);
            window.location.href = url.pathname + url.search;
          } catch (e) {
            // Fallback simple si URL no está disponible.
            var sep = window.location.search && window.location.search.length ? '&' : '?';
            window.location.href = window.location.pathname + window.location.search + sep + 'almacen_id=' + encodeURIComponent(this.value);
          }
        });
      })();
    </script>
  <?php endif; ?>
</div>
