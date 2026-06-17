<?php
/**
 * Cuerpo del historial de producto (tablas de entradas y salidas).
 * Requiere $historial (de historialProducto()) y la función fechaCorta().
 */
if (!isset($historial) || !is_array($historial)) return;
?>
<article class="inventario-panel inventario-panel-entradas">
  <header class="inventario-panel-cabecera">
    <h3 class="inventario-panel-titulo">Entradas</h3>
    <span class="inventario-panel-contador"><?= count($historial['entradas']) ?></span>
  </header>
  <div class="inventario-panel-cuerpo">
    <div class="inventario-tabla-wrap">
      <table class="inventario-tabla">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Factura</th>
            <th>Proveedor</th>
            <th class="inventario-th-num">Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($historial['entradas'])): ?>
            <tr><td colspan="4" class="inventario-empty">Sin entradas.</td></tr>
          <?php else: ?>
            <?php foreach ($historial['entradas'] as $e): ?>
              <tr>
                <td><?= fechaCorta($e['fecha']) ?></td>
                <td><?= htmlspecialchars($e['factura'] ?: '—') ?></td>
                <td><?= htmlspecialchars($e['proveedor_nombre'] ?? '—') ?></td>
                <td class="inventario-th-num qty-pos"><?= number_format((int)$e['cantidad']) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="inventario-tabla-total">
              <td colspan="3" class="inventario-td-total-label"><strong>Total entradas</strong></td>
              <td class="inventario-th-num qty-pos inventario-td-total-num"><?= number_format($historial['total_entradas']) ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</article>

<article class="inventario-panel inventario-panel-salidas">
  <header class="inventario-panel-cabecera">
    <h3 class="inventario-panel-titulo">Salidas</h3>
    <span class="inventario-panel-contador"><?= count($historial['salidas']) ?></span>
  </header>
  <div class="inventario-panel-cuerpo">
    <div class="inventario-tabla-wrap">
      <table class="inventario-tabla">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Plantel</th>
            <th>Receptor</th>
            <th class="inventario-th-num">Cantidad</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($historial['salidas'])): ?>
            <tr><td colspan="4" class="inventario-empty">Sin salidas.</td></tr>
          <?php else: ?>
            <?php foreach ($historial['salidas'] as $s): ?>
              <tr>
                <td><?= fechaCorta($s['fecha']) ?></td>
                <td><?= htmlspecialchars($s['plantel_nombre'] ?? '—') ?></td>
                <td><?= htmlspecialchars($s['receptor_nombre'] ?? '—') ?></td>
                <td class="inventario-th-num qty-neg"><?= number_format((int)$s['cantidad']) ?></td>
              </tr>
            <?php endforeach; ?>
            <tr class="inventario-tabla-total">
              <td colspan="3" class="inventario-td-total-label"><strong>Total salidas</strong></td>
              <td class="inventario-th-num qty-neg inventario-td-total-num"><?= number_format($historial['total_salidas']) ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</article>
