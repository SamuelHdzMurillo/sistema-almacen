<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/log_actividad.php';
requerirLogin();

function esAdminSesion(): bool {
    $usuarioNombre = (string) ($_SESSION['usuario_nombre'] ?? '');
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    return $usuarioNombre === 'Administrador' || $usuarioId === 1;
}

if (!esAdminSesion()) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Acceso restringido - Sistema de Almacén</title>
      <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
      <link rel="stylesheet" href="assets/css/style.css?v=15">
    </head>
    <body>
      <div class="container">
        <header class="header">
          <div class="logo">
            <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
            <span>Sistema de Almacén</span>
            <span class="logo-sub">Acceso restringido</span>
          </div>
          <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">Volver al panel</a>
          </div>
        </header>
        <div class="alert alert-error">Solo el administrador puede ver el registro de actividad.</div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

function h($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function safeInt($v, int $default = 0): int {
    return is_numeric($v) ? (int) $v : $default;
}

function formatIsoTimestamp(string $ts): string {
    $t = strtotime($ts);
    if ($t === false) {
        return $ts;
    }
    return date('d/m/Y H:i:s', $t);
}

function readLastJsonlEntries(string $filePath, int $limit, bool $leerTodo = false): array {
    if (!is_file($filePath) || !is_readable($filePath)) {
        return [];
    }

    $lastLines = [];
    $fh = new SplFileObject($filePath, 'r');
    $fh->setFlags(SplFileObject::DROP_NEW_LINE);
    foreach ($fh as $line) {
        if ($line === false) {
            continue;
        }
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $lastLines[] = $line;
        if (!$leerTodo && count($lastLines) > $limit) {
            array_shift($lastLines);
        }
    }

    $out = [];
    foreach ($lastLines as $line) {
        $obj = @json_decode($line, true);
        if (is_array($obj)) {
            $out[] = $obj;
        }
    }
    return array_reverse($out);
}

function parseFechaFiltro(string $fecha): ?int {
    $fecha = trim($fecha);
    if ($fecha === '') {
        return null;
    }
    $t = strtotime($fecha . ' 00:00:00');
    return $t !== false ? $t : null;
}

function timestampLog(string $ts): ?int {
    if ($ts === '') {
        return null;
    }
    $t = strtotime($ts);
    return $t !== false ? $t : null;
}

function coincideFechaRango(?int $tLog, ?int $desde, ?int $hasta): bool {
    if ($tLog === null) {
        return false;
    }
    if ($desde !== null && $tLog < $desde) {
        return false;
    }
    if ($hasta !== null) {
        $finDia = strtotime(date('Y-m-d', $hasta) . ' 23:59:59');
        if ($finDia !== false && $tLog > $finDia) {
            return false;
        }
    }
    return true;
}

function listarUsuariosFiltro(): array {
    $usuarios = [];
    try {
        $pdo = getDB();
        $stmt = $pdo->query('SELECT id, nombre, usuario FROM usuarios ORDER BY nombre, usuario');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $etiqueta = trim((string) ($row['nombre'] ?? ''));
            if ($etiqueta === '') {
                $etiqueta = trim((string) ($row['usuario'] ?? ''));
            }
            if ($etiqueta === '') {
                continue;
            }
            $usuarios[(int) $row['id']] = $etiqueta;
        }
    } catch (Throwable $e) {
        // Si falla la BD, el filtro se llenará solo con nombres del log.
    }
    return $usuarios;
}

function hayFiltrosActivos(string $tipo, string $usuario, string $fechaDesde, string $fechaHasta, string $q): bool {
    return $tipo !== '' || $usuario !== '' || $fechaDesde !== '' || $fechaHasta !== '' || $q !== '';
}

$sensitiveKeys = ['clave', 'password', 'pass', 'token', 'secret'];

function maskSensitiveKeys(mixed $v, array $sensitiveKeys): mixed {
    if (!is_array($v)) {
        return $v;
    }
    $out = [];
    foreach ($v as $k => $vv) {
        $keyLower = is_string($k) ? mb_strtolower($k) : '';
        if ($keyLower !== '' && in_array($keyLower, $sensitiveKeys, true)) {
            $out[$k] = '******';
        } else {
            $out[$k] = maskSensitiveKeys($vv, $sensitiveKeys);
        }
    }
    return $out;
}

$limit = safeInt($_GET['limit'] ?? 200, 200);
$limit = max(20, min(2000, $limit));
$q = trim((string) ($_GET['q'] ?? ''));
$filtroTipo = trim((string) ($_GET['tipo'] ?? ''));
$filtroUsuario = trim((string) ($_GET['usuario'] ?? ''));
$fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
$modo = (string) ($_GET['modo'] ?? 'actividad');
if ($modo !== 'tecnico') {
    $modo = 'actividad';
}

$tiposPermitidos = array_keys(tiposActividadParaFiltro());
if ($filtroTipo !== '' && !in_array($filtroTipo, $tiposPermitidos, true)) {
    $filtroTipo = '';
}

$tsDesde = parseFechaFiltro($fechaDesde);
$tsHasta = parseFechaFiltro($fechaHasta);
$filtrosActivos = hayFiltrosActivos($filtroTipo, $filtroUsuario, $fechaDesde, $fechaHasta, $q);

$usuariosDb = listarUsuariosFiltro();
$usuariosExtraLog = [];

$logDir = __DIR__ . '/logs';
$path = $logDir . '/requests.log';
$lineasLeer = $filtrosActivos ? min(10000, max($limit * 5, 2000)) : $limit;
$rowsRaw = readLastJsonlEntries($path, $lineasLeer, $filtrosActivos);

$rows = [];
foreach ($rowsRaw as $r) {
    $timestamp = (string) ($r['timestamp'] ?? '');
    $usuarioNombre = (string) ($r['usuario_nombre'] ?? '');
    $usuarioId = $r['usuario_id'] ?? null;
    $usuario = $usuarioNombre !== '' ? $usuarioNombre : ($usuarioId !== null ? ('Usuario #' . (string) $usuarioId) : 'Sin sesión');
    $method = (string) ($r['method'] ?? '');
    $ruta = (string) ($r['ruta'] ?? '');
    $queryString = (string) ($r['query_string'] ?? '');
    $postRaw = is_array($r['post_raw'] ?? null) ? (array) $r['post_raw'] : [];

    $accionInferida = (string) ($r['accion_inferida'] ?? '');
    $detalleInferida = (string) ($r['detalle_inferida'] ?? '');
    if ($accionInferida === '') {
        [$accionInferida, $detalleInferida] = inferRequestAction($method, $ruta, $postRaw, $queryString);
    }

    $mensaje = (string) ($r['mensaje_legible'] ?? '');

    if ($filtroUsuario !== '') {
        $coincideUsuario = false;
        if (is_numeric($filtroUsuario) && $usuarioId !== null && (int) $filtroUsuario === (int) $usuarioId) {
            $coincideUsuario = true;
        } elseif (mb_strtolower($usuario) === mb_strtolower($filtroUsuario)) {
            $coincideUsuario = true;
        } elseif ($usuarioNombre !== '' && mb_strtolower($usuarioNombre) === mb_strtolower($filtroUsuario)) {
            $coincideUsuario = true;
        }
        if (!$coincideUsuario) {
            continue;
        }
    }

    if ($filtroTipo !== '' && $accionInferida !== $filtroTipo) {
        continue;
    }

    $tLog = timestampLog($timestamp);
    if (($fechaDesde !== '' || $fechaHasta !== '') && !coincideFechaRango($tLog, $tsDesde, $tsHasta)) {
        continue;
    }

    if ($usuarioNombre !== '') {
        $enBd = false;
        foreach ($usuariosDb as $idBd => $nombreBd) {
            if ((int) ($usuarioId ?? 0) === (int) $idBd || mb_strtolower($nombreBd) === mb_strtolower($usuarioNombre)) {
                $enBd = true;
                break;
            }
        }
        if (!$enBd && $usuario !== 'Sin sesión') {
            $usuariosExtraLog[$usuarioNombre] = $usuario;
        }
    }

    $contextoGuardado = is_array($r['contexto'] ?? null) ? (array) $r['contexto'] : [];
    $detalleItems = is_array($r['detalle_items'] ?? null) ? (array) $r['detalle_items'] : [];
    if ($detalleItems === []) {
        $enriquecido = enriquecerDetalleParaVisor($accionInferida, $detalleInferida, $postRaw, $contextoGuardado);
        $detalleItems = $enriquecido['items'];
        if ($contextoGuardado === [] && $enriquecido['contexto'] !== []) {
            $contextoGuardado = $enriquecido['contexto'];
        }
    }
    $contextoGuardado['_codigo'] = $accionInferida;
    if ($mensaje === '' || ($detalleItems !== [] && !str_contains($mensaje, 'ver detalle'))) {
        $detalleParaMsg = $detalleInferida !== '' ? $detalleInferida : resumenTextoMovimiento($contextoGuardado);
        $mensaje = mensajeActividadLegible(
            $accionInferida,
            $detalleParaMsg,
            $usuarioNombre !== '' ? $usuarioNombre : null,
            $method,
            $ruta,
            $contextoGuardado
        );
    }
    $tipoMovDetalle = (string) ($contextoGuardado['tipo_movimiento'] ?? '');
    if ($tipoMovDetalle === '' && str_contains($accionInferida, 'SALIDA')) {
        $tipoMovDetalle = 'salida';
    } elseif ($tipoMovDetalle === '') {
        $tipoMovDetalle = 'entrada';
    }

    $nombresProductos = [];
    foreach ($detalleItems as $it) {
        if (!empty($it['nombre'])) {
            $nombresProductos[] = (string) $it['nombre'];
        }
    }

    $tipoAccion = etiquetaAccion($accionInferida);
    $searchText = implode(' ', array_merge(
        [$timestamp, $usuario, $mensaje, $tipoAccion, $detalleInferida, $ruta],
        $nombresProductos,
        array_filter([
            $contextoGuardado['proveedor'] ?? '',
            $contextoGuardado['plantel'] ?? '',
            $contextoGuardado['referencia'] ?? '',
        ])
    ));
    if ($q !== '' && mb_stripos($searchText, $q) === false) {
        continue;
    }

    $postMasked = maskSensitiveKeys($postRaw, $sensitiveKeys);
    $detalleHtml = $detalleItems !== [] ? generarHtmlDetalleActividad($contextoGuardado, $detalleItems) : '';

    $rows[] = [
        'timestamp' => $timestamp,
        'timestamp_sort' => $tLog ?? 0,
        'usuario' => $usuario,
        'mensaje' => $mensaje,
        'tipo' => $tipoAccion,
        'codigo' => $accionInferida,
        'detalle' => $detalleInferida,
        'detalle_html' => $detalleHtml,
        'tiene_detalle' => $detalleHtml !== '',
        'method' => $method,
        'ruta' => $ruta,
        'query_string' => $queryString,
        'request_id' => (string) ($r['request_id'] ?? ''),
        'ip' => (string) ($r['ip'] ?? ''),
        'post_raw' => $postMasked,
    ];
}

usort($rows, static fn($a, $b) => ($b['timestamp_sort'] ?? 0) <=> ($a['timestamp_sort'] ?? 0));
if (count($rows) > $limit) {
    $rows = array_slice($rows, 0, $limit);
}

$conteo = count($rows);
$tiposFiltro = tiposActividadParaFiltro();
asort($usuariosDb);
asort($usuariosExtraLog);

$queryFiltros = static function () use ($modo, $q, $filtroTipo, $filtroUsuario, $fechaDesde, $fechaHasta, $limit): string {
    $p = [
        'modo' => $modo,
        'limit' => (string) $limit,
    ];
    if ($q !== '') {
        $p['q'] = $q;
    }
    if ($filtroTipo !== '') {
        $p['tipo'] = $filtroTipo;
    }
    if ($filtroUsuario !== '') {
        $p['usuario'] = $filtroUsuario;
    }
    if ($fechaDesde !== '') {
        $p['fecha_desde'] = $fechaDesde;
    }
    if ($fechaHasta !== '') {
        $p['fecha_hasta'] = $fechaHasta;
    }
    return http_build_query($p);
};
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de actividad - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=15">
  <style>
    .log-mensaje {
      font-size: 1rem;
      line-height: 1.45;
      color: var(--text-primary);
    }
    .log-tipo {
      display: inline-flex;
      align-items: center;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.75rem;
      border: 1px solid var(--border);
      background: #fff;
      color: var(--text-muted);
      white-space: nowrap;
    }
    .log-tipo--sesion { background: rgba(33, 150, 243, 0.12); border-color: rgba(33, 150, 243, 0.35); color: #1565c0; }
    .log-tipo--entrada { background: rgba(76, 175, 80, 0.15); border-color: rgba(76, 175, 80, 0.4); color: #2e7d32; }
    .log-tipo--salida { background: rgba(255, 152, 0, 0.15); border-color: rgba(255, 152, 0, 0.45); color: #e65100; }
    .log-tipo--cancel { background: rgba(229, 57, 53, 0.15); border-color: rgba(229, 57, 53, 0.4); color: #c62828; }
    .log-tipo--consulta { background: rgba(158, 158, 158, 0.15); border-color: rgba(158, 158, 158, 0.35); color: #616161; }
    .log-intro {
      margin: 0 0 1rem;
      color: var(--text-muted);
      font-size: 0.95rem;
      max-width: 52rem;
    }
    details.log-details > summary {
      cursor: pointer;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .log-pre {
      background: #f7f7f7;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.6rem 0.75rem;
      overflow: auto;
      max-height: 200px;
      white-space: pre-wrap;
      word-break: break-word;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 0.82rem;
      margin: 0.35rem 0 0;
    }
    .log-filtros {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
      gap: 0.75rem 1rem;
      align-items: end;
      padding: 1rem 1.1rem;
      background: var(--bg-card, #fff);
      border: 1px solid var(--border);
      border-radius: 10px;
      margin-bottom: 1rem;
    }
    .log-filtros .form-group {
      margin: 0;
    }
    .log-filtros .form-group label {
      font-size: 0.8rem;
      margin-bottom: 0.25rem;
    }
    .log-filtros .form-group select,
    .log-filtros .form-group input {
      width: 100%;
      min-width: 0;
    }
    .log-filtros-acciones {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
      align-items: center;
    }
    .log-filtros-buscar {
      grid-column: 1 / -1;
    }
    @media (min-width: 900px) {
      .log-filtros-buscar {
        grid-column: span 2;
      }
    }
    .log-items-list {
      margin: 0.35rem 0 0;
      padding-left: 1.25rem;
      font-size: 0.92rem;
    }
    .log-items-list li {
      margin-bottom: 0.25rem;
    }
    .log-detalle-meta {
      display: grid;
      gap: 0.2rem;
      font-size: 0.88rem;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
    }
    .log-detalle-titulo-lista {
      font-weight: 700;
      font-size: 0.85rem;
      margin: 0.35rem 0 0.15rem;
      color: var(--text-primary);
    }
    .log-detalle-bloque {
      margin-top: 0.5rem;
      padding: 0.65rem 0.75rem;
      background: #f9fafb;
      border: 1px solid var(--border);
      border-radius: 8px;
    }
  </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Registro de actividad</span>
      </div>
      <div class="header-actions">
        <a href="index.php" class="btn btn-secondary">Volver al panel</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <header class="page-header">
      <div class="page-header-texto">
        <h1 class="page-title">¿Qué hizo cada usuario?</h1>
        <p class="log-intro">
          Aquí se registran las acciones del sistema en lenguaje sencillo: inicios de sesión, entradas, salidas, cancelaciones, productos creados, etc.
          No hace falta saber de programación para entenderlo.
        </p>
      </div>
    </header>

    <form method="get" action="ver-logs.php" class="log-filtros">
      <input type="hidden" name="modo" value="<?= h($modo) ?>">

      <div class="form-group">
        <label for="filtro_tipo">Tipo de acción</label>
        <select name="tipo" id="filtro_tipo">
          <option value="">Todos</option>
          <?php foreach ($tiposFiltro as $codigo => $etiqueta): ?>
            <option value="<?= h($codigo) ?>" <?= $filtroTipo === $codigo ? 'selected' : '' ?>><?= h($etiqueta) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="filtro_usuario">Usuario</label>
        <select name="usuario" id="filtro_usuario">
          <option value="">Todos</option>
          <?php foreach ($usuariosDb as $idUsr => $nombreUsr): ?>
            <option value="<?= (int) $idUsr ?>" <?= $filtroUsuario === (string) $idUsr ? 'selected' : '' ?>><?= h($nombreUsr) ?></option>
          <?php endforeach; ?>
          <?php foreach ($usuariosExtraLog as $clave => $nombreExtra): ?>
            <option value="<?= h($clave) ?>" <?= $filtroUsuario === $clave ? 'selected' : '' ?>><?= h($nombreExtra) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="fecha_desde">Desde</label>
        <input type="date" name="fecha_desde" id="fecha_desde" value="<?= h($fechaDesde) ?>">
      </div>

      <div class="form-group">
        <label for="fecha_hasta">Hasta</label>
        <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?= h($fechaHasta) ?>">
      </div>

      <div class="form-group">
        <label for="limit">Cantidad máx.</label>
        <select name="limit" id="limit">
          <?php foreach ([50, 100, 200, 500, 1000] as $opt): ?>
            <option value="<?= (int) $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= (int) $opt ?> registros</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group log-filtros-buscar">
        <label for="q">Buscar texto</label>
        <input type="search" name="q" id="q" value="<?= h($q) ?>" placeholder="Palabra en el mensaje, detalle…" autocomplete="off">
      </div>

      <div class="form-group log-filtros-acciones">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtrosActivos): ?>
          <a href="ver-logs.php?modo=<?= h($modo) ?>&limit=<?= (int) $limit ?>" class="btn btn-secondary">Limpiar filtros</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:11rem;">Cuándo</th>
            <th style="width:10rem;">Quién</th>
            <th style="width:9rem;">Tipo</th>
            <th>Qué pasó</th>
            <?php if ($modo === 'tecnico'): ?>
              <th>Técnico</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="<?= $modo === 'tecnico' ? 5 : 4 ?>" class="empty-msg">
                No hay registros para los filtros seleccionados.
                <?php if ($filtrosActivos): ?>
                  <div style="margin-top:0.5rem; font-size:0.9rem; color:var(--text-muted);">
                    Prueba ampliar el rango de fechas o quitar algún filtro.
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r):
              $codigo = '';
              $tipoClass = 'log-tipo--consulta';
              if (preg_match('/sesión|sesion/i', $r['tipo'])) {
                $tipoClass = 'log-tipo--sesion';
              } elseif (preg_match('/entrada/i', $r['tipo'])) {
                $tipoClass = 'log-tipo--entrada';
              } elseif (preg_match('/salida/i', $r['tipo'])) {
                $tipoClass = 'log-tipo--salida';
              } elseif (preg_match('/cancel/i', $r['tipo'])) {
                $tipoClass = 'log-tipo--cancel';
              }
            ?>
              <tr>
                <td><?= h($r['timestamp'] !== '' ? formatIsoTimestamp($r['timestamp']) : '—') ?></td>
                <td><strong><?= h($r['usuario']) ?></strong></td>
                <td><span class="log-tipo <?= h($tipoClass) ?>"><?= h($r['tipo']) ?></span></td>
                <td class="log-mensaje">
                  <div><?= h($r['mensaje']) ?></div>
                  <?php if (!empty($r['tiene_detalle'])): ?>
                    <details class="log-details" style="margin-top:0.45rem;">
                      <summary>Ver detalle (qué entró / salió)</summary>
                      <?= $r['detalle_html'] ?>
                    </details>
                  <?php endif; ?>
                </td>
                <?php if ($modo === 'tecnico'): ?>
                  <td>
                    <details class="log-details">
                      <summary>Detalles</summary>
                      <div style="margin-top:0.5rem; font-size:0.85rem;">
                        <div><strong>Ruta:</strong> <?= h($r['ruta']) ?></div>
                        <?php if ($r['query_string'] !== ''): ?>
                          <div><strong>Consulta:</strong> <?= h($r['query_string']) ?></div>
                        <?php endif; ?>
                        <?php if ($r['detalle'] !== ''): ?>
                          <div><strong>Detalle:</strong> <?= h($r['detalle']) ?></div>
                        <?php endif; ?>
                        <?php if ($r['ip'] !== ''): ?>
                          <div><strong>IP:</strong> <?= h($r['ip']) ?></div>
                        <?php endif; ?>
                        <?php if ($r['request_id'] !== ''): ?>
                          <div><strong>ID interno:</strong> <?= h($r['request_id']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($r['post_raw'])): ?>
                          <pre class="log-pre"><?= h(json_encode($r['post_raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') ?></pre>
                        <?php endif; ?>
                      </div>
                    </details>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center; color:var(--text-muted); font-size:0.9rem;">
      <span>Registros mostrados: <strong><?= (int) $conteo ?></strong><?php if ($filtrosActivos): ?> (con filtros)<?php endif; ?></span>
      <?php if ($modo === 'actividad'): ?>
        <a href="ver-logs.php?<?= h($queryFiltros()) ?>&modo=tecnico" class="btn btn-secondary" style="font-size:0.85rem; padding:0.35rem 0.75rem;">
          Modo técnico (desarrollo)
        </a>
      <?php else: ?>
        <a href="ver-logs.php?<?= h($queryFiltros()) ?>&modo=actividad" class="btn btn-secondary" style="font-size:0.85rem; padding:0.35rem 0.75rem;">
          Volver a vista sencilla
        </a>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
