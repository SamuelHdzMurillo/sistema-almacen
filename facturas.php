<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';

$busqueda = trim($_GET['q'] ?? '');
$entradas = listarEntradasConDocumento(200, $busqueda !== '' ? $busqueda : null);
$total    = count($entradas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Facturas - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=13">
</head>
<body class="pagina-facturas">
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Facturas</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <header class="page-header">
      <div class="page-header-texto">
        <h1 class="page-title">Facturas adjuntas</h1>
        <p class="page-header-subtitulo">Documentos (imágenes o PDFs) adjuntados a las entradas del almacén.</p>
      </div>
    </header>

    <div class="facturas-toolbar">
      <form method="get" action="facturas.php" class="facturas-search-form">
        <input
          type="search"
          name="q"
          value="<?= htmlspecialchars($busqueda) ?>"
          placeholder="Buscar por folio, referencia o proveedor…"
          autocomplete="off"
          class="facturas-search-input"
        >
        <button type="submit" class="btn btn-secondary">Buscar</button>
        <?php if ($busqueda !== ''): ?>
          <a href="facturas.php" class="btn btn-secondary">Limpiar</a>
        <?php endif; ?>
      </form>
      <span class="facturas-contador">
        <?= $total ?> <?= $total === 1 ? 'documento' : 'documentos' ?>
        <?= $busqueda !== '' ? 'encontrados' : 'en total' ?>
      </span>
    </div>

    <?php if (empty($entradas)): ?>
      <div class="facturas-empty">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="facturas-empty-icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
        <p><?= $busqueda !== '' ? 'No se encontraron documentos con esa búsqueda.' : 'Aún no hay documentos adjuntos en ninguna entrada.' ?></p>
        <?php if ($busqueda !== ''): ?>
          <a href="facturas.php" class="btn btn-secondary btn-sm">Ver todos</a>
        <?php else: ?>
          <a href="nueva-entrada.php" class="btn btn-primary btn-sm">Registrar entrada</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="facturas-grid">
        <?php foreach ($entradas as $e):
          $doc       = (string)($e['factura_doc'] ?? '');
          $esPdf     = preg_match('/\.pdf$/i', $doc);
          $esImagen  = !$esPdf;
          $folio     = $e['factura'] ?? null;
          $ref       = $e['referencia'] ?? '—';
          $fecha     = $e['fecha'] ?? '';
          $proveedor = $e['proveedor_nombre'] ?? '—';
          $receptor  = $e['quien_recibe_nombre'] ?? '—';
          $estado    = $e['estado'] ?? 'completada';
          $fechaFmt  = $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
          $registradoPor = $e['created_by_nombre'] ?? null;
          $createdAt = $e['created_at'] ?? null;
          $createdAtFmt = $createdAt ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
        ?>
        <div class="facturas-card">
          <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" class="facturas-card-thumb-link" title="Ver documento">
            <?php if ($esImagen): ?>
              <img src="<?= htmlspecialchars($doc) ?>" alt="Factura <?= htmlspecialchars($folio ?? $ref) ?>" class="facturas-card-thumb" loading="lazy">
            <?php else: ?>
              <div class="facturas-card-pdf-placeholder">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                <span class="facturas-card-pdf-label">PDF</span>
              </div>
            <?php endif; ?>
          </a>

          <div class="facturas-card-body">
            <div class="facturas-card-folio">
              <?php if ($folio): ?>
                <span class="facturas-card-folio-valor"><?= htmlspecialchars($folio) ?></span>
              <?php else: ?>
                <span class="facturas-card-folio-vacio">Sin folio</span>
              <?php endif; ?>
              <span class="status-badge status-<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></span>
            </div>

            <table class="facturas-card-meta">
              <tr>
                <td class="facturas-meta-label">Entrada</td>
                <td class="facturas-meta-valor"><strong><?= htmlspecialchars($ref) ?></strong></td>
              </tr>
              <tr>
                <td class="facturas-meta-label">Fecha</td>
                <td class="facturas-meta-valor"><?= htmlspecialchars($fechaFmt) ?></td>
              </tr>
              <tr>
                <td class="facturas-meta-label">Proveedor</td>
                <td class="facturas-meta-valor"><?= htmlspecialchars($proveedor) ?></td>
              </tr>
              <tr>
                <td class="facturas-meta-label">Recibió</td>
                <td class="facturas-meta-valor"><?= htmlspecialchars($receptor) ?></td>
              </tr>
              <?php if ($registradoPor): ?>
              <tr>
                <td class="facturas-meta-label">Registrado</td>
                <td class="facturas-meta-valor"><?= htmlspecialchars($createdAtFmt) ?><br><span class="facturas-meta-por"><?= htmlspecialchars($registradoPor) ?></span></td>
              </tr>
              <?php endif; ?>
            </table>

            <div class="facturas-card-actions">
              <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:3px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Ver documento
              </a>
              <a href="ver-transaccion.php?tipo=in&id=<?= (int)$e['id'] ?>" class="btn btn-secondary btn-sm">
                Ver entrada
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Lightbox para imágenes -->
  <div id="facturasLightbox" class="facturas-lightbox" style="display:none;" role="dialog" aria-modal="true" aria-label="Vista ampliada del documento">
    <div class="facturas-lightbox-overlay" id="facturasLbOverlay"></div>
    <div class="facturas-lightbox-inner">
      <button class="facturas-lightbox-close" id="facturasLbClose" aria-label="Cerrar">&#x2715;</button>
      <img id="facturasLbImg" src="" alt="Documento ampliado" class="facturas-lightbox-img">
    </div>
  </div>

  <script>
  (function() {
    var lightbox  = document.getElementById('facturasLightbox');
    var lbImg     = document.getElementById('facturasLbImg');
    var lbOverlay = document.getElementById('facturasLbOverlay');
    var lbClose   = document.getElementById('facturasLbClose');

    document.querySelectorAll('.facturas-card-thumb').forEach(function(img) {
      img.style.cursor = 'zoom-in';
      img.addEventListener('click', function(e) {
        e.preventDefault();
        lbImg.src = img.src;
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      });
    });

    function cerrarLb() {
      lightbox.style.display = 'none';
      lbImg.src = '';
      document.body.style.overflow = '';
    }
    if (lbOverlay) lbOverlay.addEventListener('click', cerrarLb);
    if (lbClose)   lbClose.addEventListener('click', cerrarLb);
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') cerrarLb();
    });
  })();
  </script>
</body>
</html>
