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
  <link rel="stylesheet" href="assets/css/style.css?v=15">
  <style>
    /* Miniatura de factura en tabla */
    .fac-thumb {
      width: 40px; height: 40px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border);
      cursor: zoom-in;
      vertical-align: middle;
    }
    .fac-pdf-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      background: #e53935;
      color: #fff;
      font-size: 0.72rem;
      font-weight: 700;
      border-radius: 6px;
      padding: 0.15rem 0.45rem;
      text-decoration: none;
      vertical-align: middle;
    }
    .fac-pdf-badge:hover { opacity: 0.85; }

    /* Barra de búsqueda */
    .facturas-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
    }
    .facturas-search-form {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    .facturas-search-input {
      background: var(--bg-primary);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.5rem 1rem;
      color: var(--text-primary);
      width: 260px;
      font-size: 0.9rem;
    }
    .facturas-search-input::placeholder { color: var(--text-muted); }
    .facturas-contador {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    /* Lightbox */
    .fac-lightbox {
      position: fixed; inset: 0; z-index: 1000;
      display: none; align-items: center; justify-content: center;
    }
    .fac-lightbox-overlay {
      position: absolute; inset: 0;
      background: rgba(0,0,0,.75); cursor: pointer;
    }
    .fac-lightbox-inner {
      position: relative; z-index: 1;
      max-width: 90vw; max-height: 90vh;
    }
    .fac-lightbox-img {
      max-width: 90vw; max-height: 85vh;
      border-radius: var(--radius);
      box-shadow: 0 8px 40px rgba(0,0,0,.5);
      display: block;
    }
    .fac-lightbox-close {
      position: absolute; top: -14px; right: -14px;
      background: #fff; border: none; border-radius: 50%;
      width: 30px; height: 30px; font-size: 1rem; cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,.3);
      display: flex; align-items: center; justify-content: center;
    }

    .col-ref  { font-family: monospace; font-weight: 700; font-size: 0.9rem; }
    .col-fecha { white-space: nowrap; }
    .col-folio { font-weight: 600; }
  </style>
</head>
<body>
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

    <!-- Barra de búsqueda y contador -->
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

    <!-- Tabla de facturas -->
    <div class="table-wrap" style="overflow-x:auto;">
      <table style="min-width:860px;">
        <thead>
          <tr>
            <th style="width:56px;">Documento</th>
            <th style="width:100px;">Folio</th>
            <th style="width:120px;">Referencia</th>
            <th style="width:95px;">Fecha</th>
            <th>Proveedor</th>
            <th>Recibió</th>
            <th style="width:130px;">Registrado por</th>
            <th style="width:100px;">Estado</th>
            <th style="width:200px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($entradas)): ?>
            <tr>
              <td colspan="9" class="empty-msg">
                <?= $busqueda !== ''
                  ? 'No se encontraron documentos con esa búsqueda.'
                  : 'Aún no hay documentos adjuntos en ninguna entrada.' ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($entradas as $e):
              $doc          = (string)($e['factura_doc'] ?? '');
              $esPdf        = (bool)preg_match('/\.pdf$/i', $doc);
              $folio        = $e['factura'] ?? null;
              $ref          = $e['referencia'] ?? '—';
              $fecha        = $e['fecha'] ?? '';
              $proveedor    = $e['proveedor_nombre'] ?? '—';
              $receptor     = $e['quien_recibe_nombre'] ?? '—';
              $estado       = $e['estado'] ?? 'completada';
              $fechaFmt     = $fecha ? date('d/m/Y', strtotime($fecha)) : '—';
              $registradoPor = $e['created_by_nombre'] ?? null;
              $createdAt    = $e['created_at'] ?? null;
              $createdAtFmt = $createdAt ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
            ?>
            <tr>
              <td>
                <?php if ($esPdf): ?>
                  <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" class="fac-pdf-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    PDF
                  </a>
                <?php else: ?>
                  <img
                    src="<?= htmlspecialchars($doc) ?>"
                    alt="Factura <?= htmlspecialchars($folio ?? $ref) ?>"
                    class="fac-thumb fac-lb-trigger"
                    loading="lazy"
                    data-src="<?= htmlspecialchars($doc) ?>"
                  >
                <?php endif; ?>
              </td>
              <td class="col-folio">
                <?php if ($folio): ?>
                  <?= htmlspecialchars($folio) ?>
                <?php else: ?>
                  <span style="color:var(--text-muted); font-weight:400;">Sin folio</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;">
                <a href="ver-transaccion.php?tipo=in&id=<?= (int)$e['id'] ?>" class="col-ref" title="Ver entrada"><?= htmlspecialchars($ref) ?></a>
              </td>
              <td style="white-space:nowrap;"><?= htmlspecialchars($fechaFmt) ?></td>
              <td><?= htmlspecialchars($proveedor) ?></td>
              <td><?= htmlspecialchars($receptor) ?></td>
              <td>
                <?php if ($registradoPor): ?>
                  <span style="font-size:0.85rem;"><?= htmlspecialchars($registradoPor) ?></span><br>
                  <span style="font-size:0.78rem; color:var(--text-muted);"><?= htmlspecialchars($createdAtFmt) ?></span>
                <?php else: ?>
                  <span style="color:var(--text-muted);">—</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;"><span class="status-badge status-<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></span></td>
              <td style="white-space:nowrap;">
                <div style="display:flex; gap:0.4rem; align-items:center;">
                  <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Ver documento</a>
                  <a href="ver-transaccion.php?tipo=in&id=<?= (int)$e['id'] ?>" class="btn btn-secondary btn-sm">Ver entrada</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- Lightbox para imágenes -->
  <div id="facLightbox" class="fac-lightbox" role="dialog" aria-modal="true" aria-label="Vista ampliada del documento">
    <div class="fac-lightbox-overlay" id="facLbOverlay"></div>
    <div class="fac-lightbox-inner">
      <button class="fac-lightbox-close" id="facLbClose" aria-label="Cerrar">&#x2715;</button>
      <img id="facLbImg" src="" alt="Documento ampliado" class="fac-lightbox-img">
    </div>
  </div>

  <script>
  (function() {
    var lb      = document.getElementById('facLightbox');
    var lbImg   = document.getElementById('facLbImg');
    var overlay = document.getElementById('facLbOverlay');
    var close   = document.getElementById('facLbClose');

    document.querySelectorAll('.fac-lb-trigger').forEach(function(img) {
      img.addEventListener('click', function() {
        lbImg.src = this.dataset.src || this.src;
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      });
    });

    function cerrar() {
      lb.style.display = 'none';
      lbImg.src = '';
      document.body.style.overflow = '';
    }
    if (overlay) overlay.addEventListener('click', cerrar);
    if (close)   close.addEventListener('click', cerrar);
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') cerrar();
    });
  })();
  </script>
</body>
</html>
