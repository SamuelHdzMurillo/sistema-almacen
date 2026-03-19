<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/productos.php';
require_once __DIR__ . '/includes/inventario.php';

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '') ?: null;
    $unidad = trim($_POST['unidad'] ?? '');
    if ($unidad === '' || $unidad === 'otra') {
        $unidad = trim($_POST['unidad_nueva'] ?? '');
        $unidad = $unidad !== '' ? substr($unidad, 0, 20) : 'und';
    }
    if ($nombre !== '') {
        try {
            $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
            crearProducto(['nombre' => $nombre, 'codigo' => $codigo, 'descripcion' => trim($_POST['descripcion'] ?? ''), 'unidad' => $unidad], $usuarioId);
            $mensaje = 'Producto creado correctamente.';
        } catch (Exception $e) {
            $mensaje = 'Error: ' . $e->getMessage();
        }
    }
}

$productos = listarProductos();
$unidadesDisponibles = listarUnidadesDisponibles();
$inventario = inventarioPorProducto();
$siguienteCodigo = obtenerSiguienteCodigoProducto();
$stockPorId = [];
foreach ($inventario as $inv) {
    $stockPorId[(int)$inv['id']] = (int)$inv['stock'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Productos - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=12">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Productos</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>
    <?php include __DIR__ . '/includes/nav.php'; ?>

    <header class="page-header">
      <div class="page-header-texto">
        <h1 class="page-title">Productos</h1>
      </div>
    </header>
    <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>

    <div class="form-layout-dual">
      <div class="form-card form-card--datos">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
          <h3 class="form-card-title">Nuevo producto</h3>
        </div>
        <form method="post">
          <input type="hidden" name="crear" value="1">
          <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="nombre" required placeholder="Nombre del producto">
          </div>
          <div class="form-group">
            <label>Código (opcional)</label>
            <input type="text" name="codigo" value="<?= htmlspecialchars($siguienteCodigo) ?>" placeholder="<?= htmlspecialchars($siguienteCodigo) ?>">
          </div>
          <div class="form-group">
            <label>Descripción (opcional)</label>
            <input type="text" name="descripcion" placeholder="Breve descripción">
          </div>
          <div class="form-group">
            <label>Unidad</label>
            <select name="unidad" id="unidad">
              <?php foreach ($unidadesDisponibles as $u): ?>
                <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
              <?php endforeach; ?>
              <option value="otra">Otra...</option>
            </select>
            <div class="form-group" id="wrap-unidad-nueva" style="display:none; margin-top:0.5rem;">
              <label for="unidad_nueva">Nueva unidad de medida</label>
              <input type="text" name="unidad_nueva" id="unidad_nueva" placeholder="Ej: pieza, litro, bolsa" maxlength="20">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Crear producto</button>
        </form>
      </div>

      <div class="form-card form-card--wide form-card--detalle">
        <div class="form-card-header">
          <span class="form-card-header-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
          <h3 class="form-card-title">Lista de productos</h3>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Código</th>
                <th>Nombre</th>
                <th>Unidad</th>
                <th>Stock actual</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($productos)): ?>
                <tr><td colspan="4" class="empty-msg">No hay productos. Cree uno en el panel de la izquierda.</td></tr>
              <?php else: ?>
                <?php foreach ($productos as $p): ?>
                <tr>
                  <td><?= htmlspecialchars($p['codigo'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($p['nombre']) ?></td>
                  <td><?= htmlspecialchars($p['unidad'] ?? 'und') ?></td>
                  <td><?= $stockPorId[(int)$p['id']] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <script>
  (function() {
    var sel = document.getElementById('unidad');
    var wrap = document.getElementById('wrap-unidad-nueva');
    var input = document.getElementById('unidad_nueva');
    function toggle() {
      var otra = sel.value === 'otra';
      wrap.style.display = otra ? 'block' : 'none';
      if (otra) input.focus(); else input.value = '';
    }
    sel.addEventListener('change', toggle);
    toggle();
  })();
  </script>
</body>
</html>
