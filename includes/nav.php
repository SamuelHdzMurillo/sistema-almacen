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
        'inventario' => 'inventario',
        'nueva-entrada' => 'nueva-entrada',
        'nueva-salida' => 'nueva-salida',
        'productos' => 'productos',
    ];
    $nav_activo = $map[$script] ?? '';
}
$nav_items = [
    'inicio' => ['url' => 'index.php', 'label' => 'Inicio'],
    'transacciones' => ['url' => 'transacciones.php', 'label' => 'Transacciones'],
    'inventario' => ['url' => 'inventario.php', 'label' => 'Inventario'],
    'nueva-entrada' => ['url' => 'nueva-entrada.php', 'label' => 'Nueva entrada'],
    'nueva-salida' => ['url' => 'nueva-salida.php', 'label' => 'Nueva salida'],
    'productos' => ['url' => 'productos.php', 'label' => 'Productos'],
];
?>
<nav class="nav-links" aria-label="Navegación principal">
  <?php foreach ($nav_items as $key => $item): ?>
  <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-link <?= $nav_activo === $key ? 'active' : '' ?>"><?= htmlspecialchars($item['label']) ?></a>
  <?php endforeach; ?>
</nav>
