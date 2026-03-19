<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/salidas.php';
require_once __DIR__ . '/includes/upload_recibo.php';

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salidaId = (int)($_POST['salida_id'] ?? 0);
    if ($salidaId <= 0) {
        $error = 'Salida no válida.';
    } elseif (!isset($_FILES['recibo_doc']) || (int)($_FILES['recibo_doc']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'Seleccione un archivo para subir.';
    } else {
        try {
            $ruta = procesarArchivoRecibo($_FILES['recibo_doc']);
            if (!guardarReciboSalida($salidaId, $ruta)) {
                eliminarArchivoRecibo($ruta);
                $error = 'No se pudo guardar el recibo. Verifique que la salida exista.';
            } else {
                $mensaje = 'Recibo de entrega guardado correctamente.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$tab = $_GET['tab'] ?? 'pendientes';
$tab = in_array($tab, ['pendientes', 'entregados']) ? $tab : 'pendientes';

$sinRecibo = listarSalidasSinRecibo();
$conRecibo = listarSalidasConRecibo();
$totalPend = count($sinRecibo);
$totalConf = count($conRecibo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recibos de entrega - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=13">
  <style>
    /* ── estilos exclusivos de esta página ── */
    .recibos-inline-form {
      display: none;
      background: var(--bg-secondary);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      margin-top: 0.5rem;
      flex-direction: column;
      gap: 0.6rem;
    }
    .recibos-inline-form.abierto { display: flex; }

    .recibos-inline-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    .recibos-inline-row input[type="file"] {
      flex: 1;
      min-width: 0;
      font-size: 0.85rem;
      color: var(--text-primary);
    }

    .recibos-preview-row {
      display: none;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    .recibos-preview-thumb {
      width: 48px; height: 48px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border);
      display: none;
    }
    .recibos-preview-pdf {
      display: none;
      background: #e53935;
      color: #fff;
      font-weight: 700;
      font-size: 0.72rem;
      border-radius: 6px;
      padding: 0.15rem 0.45rem;
      letter-spacing: .05em;
    }
    .recibos-preview-nombre {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    /* Miniatura en tabla "Con recibo" */
    .rec-thumb {
      width: 40px; height: 40px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border);
      cursor: zoom-in;
      vertical-align: middle;
    }
    .rec-pdf-badge {
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
    .rec-pdf-badge:hover { opacity: 0.85; }

    /* Lightbox */
    .rec-lightbox {
      position: fixed; inset: 0; z-index: 1000;
      display: none; align-items: center; justify-content: center;
    }
    .rec-lightbox-overlay {
      position: absolute; inset: 0;
      background: rgba(0,0,0,.75); cursor: pointer;
    }
    .rec-lightbox-inner {
      position: relative; z-index: 1;
      max-width: 90vw; max-height: 90vh;
    }
    .rec-lightbox-img {
      max-width: 90vw; max-height: 85vh;
      border-radius: var(--radius);
      box-shadow: 0 8px 40px rgba(0,0,0,.5);
      display: block;
    }
    .rec-lightbox-close {
      position: absolute; top: -14px; right: -14px;
      background: #fff; border: none; border-radius: 50%;
      width: 30px; height: 30px; font-size: 1rem; cursor: pointer;
      box-shadow: 0 2px 8px rgba(0,0,0,.3);
      display: flex; align-items: center; justify-content: center;
    }

    /* Badge contador en sub-nav */
    .nav-badge {
      display: inline-block;
      background: var(--accent-orange);
      color: #fff;
      border-radius: 99px;
      padding: 0 0.4rem;
      font-size: 0.72rem;
      font-weight: 700;
      min-width: 1.25rem;
      text-align: center;
      line-height: 1.5;
      vertical-align: middle;
      margin-left: 0.3rem;
    }
    .nav-badge-green { background: var(--accent-green); }

    /* Referencia con fuente monospace como en transacciones */
    .col-ref { font-family: monospace; font-weight: 700; font-size: 0.9rem; }
    .col-fecha { white-space: nowrap; }
  </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Recibos de entrega</span>
      </div>
      <div class="header-actions">
        <a href="nueva-transaccion.php" class="btn btn-primary">+ Nueva transacción</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <header class="page-header">
      <div class="page-header-texto">
        <h1 class="page-title">Recibos de entrega</h1>
        <p class="page-header-subtitulo">Suba los recibos firmados de cada salida. Aquí se muestran las salidas pendientes de adjuntar su recibo.</p>
      </div>
    </header>

    <?php if ($mensaje): ?>
      <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Sub-navegación de pestañas (mismo estilo que transacciones) -->
    <section class="section-header" style="margin-bottom:1rem;">
      <h2 style="margin:0 0 0.75rem 0; font-size:1.1rem; font-weight:700; color:var(--text-primary);">Recibos de entrega</h2>
      <nav class="nav-links nav-links-sub nav-links-below-title" style="margin-bottom:0;" aria-label="Filtro de recibos">
        <a href="recibos.php?tab=pendientes"
           class="nav-link <?= $tab === 'pendientes' ? 'active' : '' ?>">
          Pendientes
          <?php if ($totalPend > 0): ?>
            <span class="nav-badge"><?= $totalPend ?></span>
          <?php endif; ?>
        </a>
        <a href="recibos.php?tab=entregados"
           class="nav-link <?= $tab === 'entregados' ? 'active' : '' ?>">
          Con recibo adjunto
          <?php if ($totalConf > 0): ?>
            <span class="nav-badge nav-badge-green"><?= $totalConf ?></span>
          <?php endif; ?>
        </a>
      </nav>
    </section>

    <?php if ($tab === 'pendientes'): ?>
      <!-- ═══ PENDIENTES ═══ -->
      <div class="table-wrap" style="overflow-x:auto;">
        <table style="min-width:780px;">
          <thead>
            <tr>
              <th style="width:120px;">Referencia</th>
              <th style="width:95px;">Fecha</th>
              <th>Entregó</th>
              <th>Plantel destino</th>
              <th>Recibió</th>
              <th style="width:100px;">Estado</th>
              <th style="width:135px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($sinRecibo)): ?>
              <tr>
                <td colspan="7" class="empty-msg">
                  ¡Todo al día! No hay salidas pendientes de recibo firmado.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($sinRecibo as $s):
                $sid      = (int)$s['id'];
                $ref      = $s['referencia'] ?? '—';
                $fecha    = $s['fecha'] ? date('d/m/Y', strtotime($s['fecha'])) : '—';
                $entrega  = $s['nombre_entrega'] ?? '—';
                $plantel  = $s['plantel_nombre'] ?? '—';
                $receptor = $s['nombre_receptor'] ?? '—';
                $estado   = $s['estado'] ?? 'completada';
              ?>
              <tr>
                <td>
                  <a href="ver-transaccion.php?tipo=out&id=<?= $sid ?>" class="col-ref" title="Ver salida"><?= htmlspecialchars($ref) ?></a>
                </td>
                <td style="white-space:nowrap;"><?= htmlspecialchars($fecha) ?></td>
                <td><?= htmlspecialchars($entrega) ?></td>
                <td><?= htmlspecialchars($plantel) ?></td>
                <td><?= htmlspecialchars($receptor) ?></td>
                <td style="white-space:nowrap;"><span class="status-badge status-<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></span></td>
                <td style="white-space:nowrap;">
                  <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    onclick="toggleUpload(<?= $sid ?>)"
                    aria-expanded="false"
                    id="btn-toggle-<?= $sid ?>"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    Subir recibo
                  </button>
                  <div class="recibos-inline-form" id="form-upload-<?= $sid ?>">
                    <form method="post" action="recibos.php?tab=pendientes" enctype="multipart/form-data"
                          onsubmit="return confirm('¿Confirmar la subida del recibo para esta salida?')">
                      <input type="hidden" name="salida_id" value="<?= $sid ?>">
                      <div class="recibos-inline-row">
                        <input
                          type="file"
                          name="recibo_doc"
                          id="file-<?= $sid ?>"
                          accept="image/jpeg,image/png,image/gif,image/webp,application/pdf"
                          onchange="previsualizarRecibo(this, <?= $sid ?>)"
                          required
                        >
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleUpload(<?= $sid ?>)">Cancelar</button>
                      </div>
                      <div class="recibos-preview-row" id="preview-row-<?= $sid ?>">
                        <img   id="prev-img-<?= $sid ?>" src="" alt="Vista previa" class="recibos-preview-thumb">
                        <span  id="prev-pdf-<?= $sid ?>" class="recibos-preview-pdf">PDF</span>
                        <span  id="prev-nom-<?= $sid ?>" class="recibos-preview-nombre"></span>
                      </div>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php else: ?>
      <!-- ═══ CON RECIBO ═══ -->
      <div class="table-wrap" style="overflow-x:auto;">
        <table style="min-width:860px;">
          <thead>
            <tr>
              <th style="width:56px;">Recibo</th>
              <th style="width:120px;">Referencia</th>
              <th style="width:95px;">Fecha</th>
              <th>Entregó</th>
              <th>Plantel destino</th>
              <th>Recibió</th>
              <th style="width:100px;">Estado</th>
              <th style="width:200px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($conRecibo)): ?>
              <tr>
                <td colspan="8" class="empty-msg">Aún no hay salidas con recibo adjunto.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($conRecibo as $s):
                $sid      = (int)$s['id'];
                $doc      = (string)($s['recibo_entrega_doc'] ?? '');
                $esPdf    = (bool)preg_match('/\.pdf$/i', $doc);
                $ref      = $s['referencia'] ?? '—';
                $fecha    = $s['fecha'] ? date('d/m/Y', strtotime($s['fecha'])) : '—';
                $entrega  = $s['nombre_entrega'] ?? '—';
                $plantel  = $s['plantel_nombre'] ?? '—';
                $receptor = $s['nombre_receptor'] ?? '—';
                $estado   = $s['estado'] ?? 'completada';
              ?>
              <tr>
                <td>
                  <?php if ($esPdf): ?>
                    <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" class="rec-pdf-badge">
                      <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      PDF
                    </a>
                  <?php else: ?>
                    <img
                      src="<?= htmlspecialchars($doc) ?>"
                      alt="Recibo <?= htmlspecialchars($ref) ?>"
                      class="rec-thumb rec-lb-trigger"
                      loading="lazy"
                      data-src="<?= htmlspecialchars($doc) ?>"
                    >
                  <?php endif; ?>
                </td>
                <td>
                  <a href="ver-transaccion.php?tipo=out&id=<?= $sid ?>" class="col-ref"><?= htmlspecialchars($ref) ?></a>
                </td>
                <td style="white-space:nowrap;"><?= htmlspecialchars($fecha) ?></td>
                <td><?= htmlspecialchars($entrega) ?></td>
                <td><?= htmlspecialchars($plantel) ?></td>
                <td><?= htmlspecialchars($receptor) ?></td>
                <td style="white-space:nowrap;"><span class="status-badge status-<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></span></td>
                <td style="white-space:nowrap;">
                  <div style="display:flex; gap:0.4rem; align-items:center;">
                    <a href="<?= htmlspecialchars($doc) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Ver documento</a>
                    <a href="ver-transaccion.php?tipo=out&id=<?= $sid ?>" class="btn btn-secondary btn-sm">Ver salida</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div>

  <!-- Lightbox -->
  <div id="recLightbox" class="rec-lightbox" role="dialog" aria-modal="true" aria-label="Vista ampliada del recibo">
    <div class="rec-lightbox-overlay" id="recLbOverlay"></div>
    <div class="rec-lightbox-inner">
      <button class="rec-lightbox-close" id="recLbClose" aria-label="Cerrar">&#x2715;</button>
      <img id="recLbImg" src="" alt="Recibo ampliado" class="rec-lightbox-img">
    </div>
  </div>

  <script>
    function toggleUpload(id) {
      var form = document.getElementById('form-upload-' + id);
      var btn  = document.getElementById('btn-toggle-' + id);
      if (!form) return;
      var abierto = form.classList.toggle('abierto');
      btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
      if (!abierto) {
        var inp = document.getElementById('file-' + id);
        if (inp) inp.value = '';
        var row = document.getElementById('preview-row-' + id);
        if (row) row.style.display = 'none';
      }
    }

    function previsualizarRecibo(input, id) {
      var file = input.files[0];
      var row  = document.getElementById('preview-row-' + id);
      var img  = document.getElementById('prev-img-' + id);
      var pdf  = document.getElementById('prev-pdf-' + id);
      var nom  = document.getElementById('prev-nom-' + id);
      if (!file || !row) return;
      nom.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
      if (file.type === 'application/pdf') {
        img.style.display = 'none';
        pdf.style.display = 'inline-block';
      } else {
        pdf.style.display = 'none';
        var reader = new FileReader();
        reader.onload = function(e) {
          img.src = e.target.result;
          img.style.display = 'block';
        };
        reader.readAsDataURL(file);
      }
      row.style.display = 'flex';
    }

    // Lightbox para imágenes
    (function() {
      var lb      = document.getElementById('recLightbox');
      var lbImg   = document.getElementById('recLbImg');
      var overlay = document.getElementById('recLbOverlay');
      var close   = document.getElementById('recLbClose');

      document.querySelectorAll('.rec-lb-trigger').forEach(function(img) {
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
