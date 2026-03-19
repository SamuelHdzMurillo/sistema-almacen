<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();

function esAdminSesion(): bool {
    $usuarioNombre = (string) ($_SESSION['usuario_nombre'] ?? '');
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    // En tu seed inicial: usuario="admin", nombre="Administrador", id normalmente 1.
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
      <link rel="stylesheet" href="assets/css/style.css?v=12">
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

        <div class="alert alert-error">Solo el administrador puede ver los logs.</div>
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
    return (is_numeric($v) ? (int) $v : $default);
}

function formatIsoTimestamp(string $ts): string {
    // Los logs usan ISO 8601 con offset (ej. 2026-03-18T18:25:42+01:00)
    // Si falla el parseo, devolvemos tal cual.
    $t = strtotime($ts);
    if ($t === false) return $ts;
    return date('d/m/Y H:i:s', $t);
}

function truncateText(string $s, int $maxChars = 220): string {
    $s = trim($s);
    if (mb_strlen($s) <= $maxChars) return $s;
    return mb_substr($s, 0, $maxChars) . '…';
}

function maskSensitiveValue(mixed $v): mixed {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $vv) {
            $out[$k] = maskSensitiveValue($vv);
        }
        return $out;
    }
    if (!is_string($v)) return $v;
    // No enmascaramos por valor porque no sabemos la clave original aquí.
    return $v;
}

function maskSensitiveKeys(mixed $v, array $sensitiveKeys): mixed {
    if (is_array($v)) {
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
    return $v;
}

function inferRequestActionForViewer(string $method, string $ruta, array $postRaw): array {
    // Replica la idea de `inferRequestAction()` para que el visor
    // funcione incluso si el log no trae `accion_inferida/detalle_inferida`.
    $basename = strtolower(basename($ruta));

    if ($method !== 'POST') {
        return ['GET', $ruta];
    }

    if ($basename === 'login.php') {
        $usuario = isset($postRaw['usuario']) ? (string) $postRaw['usuario'] : '';
        return ['LOGIN', $usuario !== '' ? 'usuario=' . $usuario : ''];
    }

    if ($basename === 'productos.php' && isset($postRaw['crear'])) {
        $nombre = trim((string) ($postRaw['nombre'] ?? ''));
        $codigo = trim((string) ($postRaw['codigo'] ?? ''));
        return ['CREAR_PRODUCTO', $nombre !== '' ? "nombre={$nombre}" . ($codigo !== '' ? " codigo={$codigo}" : '') : ''];
    }

    if ($basename === 'nueva-entrada.php') {
        $fecha = trim((string) ($postRaw['fecha'] ?? ''));
        $proveedorId = isset($postRaw['proveedor_id']) && $postRaw['proveedor_id'] !== '' ? (string) $postRaw['proveedor_id'] : '';
        $productoIds = isset($postRaw['producto_id']) && is_array($postRaw['producto_id']) ? $postRaw['producto_id'] : [];
        $lineas = is_array($productoIds) ? count($productoIds) : 0;
        return ['REGISTRAR_ENTRADA', ($fecha !== '' ? "fecha={$fecha} " : '') . ($proveedorId !== '' ? "proveedor_id={$proveedorId} " : '') . "lineas={$lineas}"];
    }

    if ($basename === 'nueva-salida.php') {
        $fecha = trim((string) ($postRaw['fecha'] ?? ''));
        $plantelId = isset($postRaw['plantel_id']) && $postRaw['plantel_id'] !== '' ? (string) $postRaw['plantel_id'] : '';
        $productoIds = isset($postRaw['producto_id']) && is_array($postRaw['producto_id']) ? $postRaw['producto_id'] : [];
        $lineas = is_array($productoIds) ? count($productoIds) : 0;
        return ['REGISTRAR_SALIDA', ($fecha !== '' ? "fecha={$fecha} " : '') . ($plantelId !== '' ? "plantel_id={$plantelId} " : '') . "lineas={$lineas}"];
    }

    if ($basename === 'cancelar-entrada.php' && isset($postRaw['confirmar'])) {
        $id = isset($postRaw['id']) ? (string) $postRaw['id'] : '';
        return ['CANCELAR_ENTRADA', $id !== '' ? "id={$id}" : ''];
    }

    if ($basename === 'cancelar-salida.php' && isset($postRaw['confirmar'])) {
        $id = isset($postRaw['id']) ? (string) $postRaw['id'] : '';
        return ['CANCELAR_SALIDA', $id !== '' ? "id={$id}" : ''];
    }

    if ($basename === 'cancelar-linea-entrada.php') {
        $detalleId = isset($postRaw['id']) ? (string) $postRaw['id'] : '';
        return ['CANCELAR_LINEA_ENTRADA', $detalleId !== '' ? "detalle_id={$detalleId}" : ''];
    }

    return [$method, $ruta];
}

function translateSqlOp(string $op): string {
    $op = strtoupper(trim($op));
    return match ($op) {
        'INSERT' => 'Insertar',
        'UPDATE' => 'Actualizar',
        'DELETE' => 'Eliminar',
        'REPLACE' => 'Reemplazar',
        'SELECT' => 'Consultar',
        'WITH' => 'WITH',
        default => $op !== '' ? $op : '—',
    };
}

function translateMethod(string $method): string {
    $m = strtoupper(trim($method));
    return match ($m) {
        'GET' => 'GET (Consulta)',
        'POST' => 'POST (Acción)',
        'CLI' => 'CLI',
        'PUT' => 'PUT',
        'DELETE' => 'DELETE',
        default => $method !== '' ? $method : '—',
    };
}

function readLastJsonlEntries(string $filePath, int $limit): array {
    if (!is_file($filePath) || !is_readable($filePath)) return [];

    $lastLines = [];
    $fh = new SplFileObject($filePath, 'r');
    $fh->setFlags(SplFileObject::DROP_NEW_LINE);
    foreach ($fh as $line) {
        if ($line === false) continue;
        $line = trim((string) $line);
        if ($line === '') continue;
        $lastLines[] = $line;
        if (count($lastLines) > $limit) {
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
    return $out;
}

function readLastLines(string $filePath, int $limit): array {
    if (!is_file($filePath) || !is_readable($filePath)) return [];

    $lastLines = [];
    $fh = new SplFileObject($filePath, 'r');
    $fh->setFlags(SplFileObject::DROP_NEW_LINE);
    foreach ($fh as $line) {
        if ($line === false) continue;
        $line = trim((string) $line);
        if ($line === '') continue;
        $lastLines[] = $line;
        if (count($lastLines) > $limit) {
            array_shift($lastLines);
        }
    }
    return $lastLines;
}

$tipo = (string) ($_GET['tipo'] ?? 'requests');
$limit = safeInt($_GET['limit'] ?? 200, 200);
$limit = max(20, min(2000, $limit));
$q = trim((string) ($_GET['q'] ?? ''));

$tiposLogs = [
    'requests' => ['label' => 'Peticiones', 'file' => 'requests.log', 'kind' => 'jsonl'],
    'sql_pretty' => ['label' => 'SQL (bonito)', 'file' => 'sql_pretty.log', 'kind' => 'pretty'],
    'sql' => ['label' => 'SQL (raw)', 'file' => 'sql.log', 'kind' => 'jsonl'],
];
if (!isset($tiposLogs[$tipo])) {
    $tipo = 'requests';
}

$logDir = __DIR__ . '/logs';

// Descarga simple del raw (opcional, por si quieres). No la mostramos en UI para no ensuciar.
if (isset($_GET['download']) && $_GET['download'] === '1') {
    if (!isset($tiposLogs[$tipo])) {
        http_response_code(400);
        echo 'Tipo inválido.';
        exit;
    }
    $path = $logDir . '/' . $tiposLogs[$tipo]['file'];
    if (!is_file($path) || !is_readable($path)) {
        http_response_code(404);
        echo 'No se encontró el archivo.';
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

$sensitiveKeys = [
    'clave', 'password', 'pass', 'token', 'secret',
];

$rows = [];
$archivo = '';
$conteo = 0;

if ($tipo === 'requests') {
    $archivo = 'requests.log';
    $path = $logDir . '/' . $archivo;
    $rowsJson = readLastJsonlEntries($path, $limit);

    foreach ($rowsJson as $r) {
        $timestamp = (string) ($r['timestamp'] ?? '');
        $usuarioNombre = (string) ($r['usuario_nombre'] ?? '');
        $usuarioId = $r['usuario_id'] ?? null;
        $usuario = $usuarioNombre !== '' ? $usuarioNombre : ($usuarioId !== null ? ('ID ' . (string) $usuarioId) : '—');
        $method = (string) ($r['method'] ?? '—');
        $ruta = (string) ($r['ruta'] ?? '');

        $accionInferida = (string) ($r['accion_inferida'] ?? '');
        $detalleInferida = (string) ($r['detalle_inferida'] ?? '');
        $postRaw = is_array($r['post_raw'] ?? null) ? (array) $r['post_raw'] : [];

        if ($accionInferida === '') {
            [$ai, $di] = inferRequestActionForViewer($method, $ruta, $postRaw);
            $accionInferida = $ai;
            $detalleInferida = $di;
        }

        // Enmascarar campos sensibles si vienen en POST (ej: clave del login).
        $postMasked = maskSensitiveKeys($postRaw, $sensitiveKeys);
        $postRawStr = $postMasked !== [] ? @json_encode($postMasked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        if ($postRawStr === false) $postRawStr = '';

        // Traducción "bonita" de la acción inferida.
        $accionMapa = [
            'GET' => 'Consulta',
            'POST' => 'Acción',
            'LOGIN' => 'Inicio de sesión',
            'CREAR_PRODUCTO' => 'Crear producto',
            'REGISTRAR_ENTRADA' => 'Registrar entrada',
            'REGISTRAR_SALIDA' => 'Registrar salida',
            'CANCELAR_ENTRADA' => 'Cancelar entrada',
            'CANCELAR_SALIDA' => 'Cancelar salida',
            'CANCELAR_LINEA_ENTRADA' => 'Cancelar línea de entrada',
        ];
        $accionTrad = $accionMapa[$accionInferida] ?? $accionInferida;

        $searchText = implode(' ', [
            $timestamp,
            (string) ($r['request_id'] ?? ''),
            $usuario,
            $method,
            $ruta,
            $accionInferida,
            $detalleInferida,
            $postRawStr,
        ]);
        if ($q !== '' && mb_stripos($searchText, $q) === false) {
            continue;
        }

        $rows[] = [
            'timestamp' => $timestamp,
            'request_id' => (string) ($r['request_id'] ?? ''),
            'usuario' => $usuario,
            'method' => $method,
            'ruta' => $ruta,
            'accion' => $accionTrad,
            'accion_inferida' => $accionInferida,
            'detalle' => $detalleInferida !== '' ? $detalleInferida : ($ruta !== '' ? $ruta : '—'),
            'ip' => (string) ($r['ip'] ?? ''),
            'user_agent' => (string) ($r['user_agent'] ?? ''),
            'post_raw' => $postMasked,
            'post_raw_str' => $postRawStr,
            'query_string' => (string) ($r['query_string'] ?? ''),
        ];
    }
    $conteo = count($rows);
} elseif ($tipo === 'sql_pretty') {
    $archivo = 'sql_pretty.log';
    $path = $logDir . '/' . $archivo;
    $lines = readLastLines($path, $limit);

    foreach ($lines as $line) {
        // Formato: 2026-... request_id=<uuid> usuario=... GET /ruta -> SELECT tabla? params=[...]
        if (!preg_match('/^(\S+)\s+request_id=([^\s]+)\s+(.*)$/', $line, $m)) {
            continue;
        }
        $timestamp = $m[1];
        $requestId = $m[2];
        $resumen = $m[3] ?? '';

        $usuario = '—';
        $method = '—';
        $ruta = '—';
        $op = '—';
        $tabla = '—';
        $params = '';

        if (preg_match('/^usuario=(.*?)\s+(GET|POST|CLI|PUT|DELETE)\s+(.*?)\s+->\s+([A-Z]+)\s+(.*?)\s+params=(.*)$/', $resumen, $mm)) {
            $usuario = trim($mm[1] ?? '—');
            $method = trim($mm[2] ?? '—');
            $ruta = trim($mm[3] ?? '—');
            $op = trim($mm[4] ?? '—');
            $tabla = trim($mm[5] ?? '—');
            $params = trim($mm[6] ?? '');
        } elseif (preg_match('/^usuario=(.*?)\s+(GET|POST|CLI)\s+(.*?)\s+->\s+(.*?)$/', $resumen, $mm2)) {
            $usuario = trim($mm2[1] ?? '—');
            $method = trim($mm2[2] ?? '—');
            $ruta = trim($mm2[3] ?? '—');
            $op = '—';
            $tabla = '—';
            $params = $mm2[4] ?? '';
        }

        $opTrad = translateSqlOp($op);
        $methodTrad = translateMethod($method);
        $paramsShort = $params !== '' ? truncateText($params, 180) : '—';

        $searchText = implode(' ', [
            $timestamp,
            $requestId,
            $usuario,
            $method,
            $ruta,
            $op,
            $tabla,
            $params,
        ]);
        if ($q !== '' && mb_stripos($searchText, $q) === false) {
            continue;
        }

        $rows[] = [
            'timestamp' => $timestamp,
            'request_id' => $requestId,
            'usuario' => $usuario,
            'method' => $method,
            'method_trad' => $methodTrad,
            'ruta' => $ruta,
            'op' => $op,
            'op_trad' => $opTrad,
            'tabla' => $tabla,
            'params' => $params,
            'params_short' => $paramsShort,
        ];
    }
    $conteo = count($rows);
} elseif ($tipo === 'sql') {
    $archivo = 'sql.log';
    $path = $logDir . '/' . $archivo;
    $rowsJson = readLastJsonlEntries($path, $limit);

    foreach ($rowsJson as $r) {
        $timestamp = (string) ($r['timestamp'] ?? '');
        $requestId = (string) ($r['request_id'] ?? '');
        $usuarioNombre = (string) ($r['usuario_nombre'] ?? '');
        $usuarioId = $r['usuario_id'] ?? null;
        $usuario = $usuarioNombre !== '' ? $usuarioNombre : ($usuarioId !== null ? ('ID ' . (string) $usuarioId) : '—');

        $durationMs = $r['duration_ms'] ?? null;
        $op = (string) ($r['op'] ?? '—');
        $tabla = $r['tabla'] ?? null;
        $tablaStr = $tabla !== null && $tabla !== '' ? (string) $tabla : '—';
        $write = (bool) ($r['write'] ?? false);
        $ok = (bool) ($r['ok'] ?? false);

        $resumen = (string) ($r['resumen'] ?? '');
        $sql = (string) ($r['sql'] ?? '');
        $paramsRaw = $r['params_raw'] ?? null;
        $paramsStr = '';
        if ($paramsRaw !== null) {
            $paramsStr = @json_encode($paramsRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($paramsStr === false) $paramsStr = (string) $paramsRaw;
        }
        $errClass = (string) ($r['error_class'] ?? '');
        $errCode = $r['error_code'] ?? null;

        $searchText = implode(' ', [
            $timestamp,
            $requestId,
            $usuario,
            $op,
            $tablaStr,
            $resumen,
            $sql,
            $paramsStr,
            $ok ? 'OK' : 'ERROR',
            $errClass,
            (string) $errCode,
        ]);
        if ($q !== '' && mb_stripos($searchText, $q) === false) {
            continue;
        }

        $rows[] = [
            'timestamp' => $timestamp,
            'request_id' => $requestId,
            'usuario' => $usuario,
            'duration_ms' => $durationMs,
            'op' => $op,
            'op_trad' => translateSqlOp($op),
            'tabla' => $tablaStr,
            'write' => $write,
            'ok' => $ok,
            'resumen' => $resumen,
            'sql' => $sql,
            'params_raw' => $paramsRaw,
            'params_str' => $paramsStr,
            'error_class' => $errClass,
            'error_code' => $errCode,
        ];
    }
    $conteo = count($rows);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Visor de Logs - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=12">
  <style>
    .log-pre {
      background: #f7f7f7;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.6rem 0.75rem;
      overflow: auto;
      max-height: 260px;
      white-space: pre-wrap;
      word-break: break-word;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.88rem;
      margin: 0;
    }
    .log-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.2rem 0.55rem;
      border-radius: 999px;
      font-weight: 700;
      font-size: 0.75rem;
      border: 1px solid var(--border);
      background: #fff;
      color: var(--text-primary);
      white-space: nowrap;
    }
    .log-badge.ok {
      background: rgba(76, 175, 80, 0.18);
      border-color: rgba(76, 175, 80, 0.45);
      color: #2e7d32;
    }
    .log-badge.err {
      background: rgba(229, 57, 53, 0.18);
      border-color: rgba(229, 57, 53, 0.45);
      color: #c62828;
    }
    .log-type-row {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 0.75rem;
      align-items: center;
    }
    details.log-details > summary {
      cursor: pointer;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
        <span class="logo-sub">Visor de logs</span>
      </div>
      <div class="header-actions">
        <a href="index.php" class="btn btn-secondary">Volver al panel</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <section class="section-header">
      <div class="log-type-row" style="width:100%;">
        <h2 style="margin:0;">Logs traducidos</h2>
        <div class="search-filter" style="justify-content:flex-end; margin:0;">
          <form method="get" action="ver-logs.php" style="display:flex; gap:0.5rem; align-items:center;">
            <input type="hidden" name="tipo" value="<?= h($tipo) ?>">
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="Buscar en logs..." autocomplete="off">
            <button type="submit" class="btn btn-secondary">Buscar</button>
          </form>
        </div>
      </div>

      <div class="filter-btns" style="width:100%; margin-top:0.75rem;">
        <?php foreach ($tiposLogs as $key => $info): ?>
          <a
            href="ver-logs.php?tipo=<?= h($key) ?>&q=<?= urlencode($q) ?>&limit=<?= (int)$limit ?>"
            class="btn btn-secondary <?= $tipo === $key ? 'active' : '' ?>"
          >
            <?= h($info['label']) ?>
          </a>
        <?php endforeach; ?>

        <div style="margin-left:auto; display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
          <form method="get" action="ver-logs.php" style="display:flex; gap:0.5rem; align-items:center;">
            <input type="hidden" name="tipo" value="<?= h($tipo) ?>">
            <input type="hidden" name="q" value="<?= h($q) ?>">
            <label for="limit" style="font-weight:700; font-size:0.85rem; color:var(--text-muted);">Últimas</label>
            <select name="limit" id="limit" class="btn btn-secondary" style="padding:0.45rem 0.9rem;">
              <?php foreach ([50, 100, 200, 500, 1000] as $opt): ?>
                <option value="<?= (int)$opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= (int)$opt ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Aplicar</button>
          </form>
        </div>
      </div>
    </section>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <?php if ($tipo === 'requests'): ?>
              <th>Fecha/hora</th>
              <th>Usuario</th>
              <th>Método</th>
              <th>Ruta</th>
              <th>Acción</th>
              <th>Detalle</th>
              <th>Más</th>
            <?php elseif ($tipo === 'sql_pretty'): ?>
              <th>Fecha/hora</th>
              <th>Usuario</th>
              <th>Método</th>
              <th>Ruta</th>
              <th>SQL</th>
              <th>Tabla</th>
              <th>Paráms</th>
            <?php else: ?>
              <th>Fecha/hora</th>
              <th>Usuario</th>
              <th>SQL</th>
              <th>Tabla</th>
              <th>Duración</th>
              <th>Estado</th>
              <th>Resumen</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="10" class="empty-msg">
                No hay entradas para los filtros seleccionados.
                <?php if ($q !== ''): ?>
                  <div style="margin-top:0.5rem; font-size:0.9rem; color:var(--text-muted);">
                    Búsqueda: <strong><?= h($q) ?></strong>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php else: ?>
            <?php if ($tipo === 'requests'): ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= h($r['timestamp'] !== '' ? formatIsoTimestamp($r['timestamp']) : '—') ?></td>
                  <td><?= h($r['usuario']) ?></td>
                  <td><?= h(translateMethod($r['method'])) ?></td>
                  <td title="<?= h($r['ruta']) ?>"><?= h(truncateText($r['ruta'], 42)) ?></td>
                  <td>
                    <span class="log-badge" title="<?= h($r['accion_inferida']) ?>">
                      <?= h($r['accion']) ?>
                    </span>
                  </td>
                  <td title="<?= h($r['detalle']) ?>"><?= h(truncateText($r['detalle'], 58)) ?></td>
                  <td>
                    <details class="log-details">
                      <summary>Ver</summary>
                      <div style="margin-top:0.5rem; display:flex; flex-direction:column; gap:0.75rem;">
                        <div>
                          <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">request_id</div>
                          <div><?= h($r['request_id']) !== '' ? h($r['request_id']) : '—' ?></div>
                        </div>
                        <div>
                          <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">IP / Query</div>
                          <div><?= h($r['ip'] !== '' ? $r['ip'] : '—') ?><?php if ($r['query_string'] !== ''): ?> · <?= h($r['query_string']) ?><?php endif; ?></div>
                        </div>
                        <?php if (!empty($r['post_raw_str'])): ?>
                          <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">POST (enmascarado)</div>
                            <pre class="log-pre"><?= h($r['post_raw_str']) ?></pre>
                          </div>
                        <?php else: ?>
                          <div style="color:var(--text-muted);">POST: —</div>
                        <?php endif; ?>
                        <?php if ($r['user_agent'] !== ''): ?>
                          <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">User-Agent</div>
                            <pre class="log-pre"><?= h($r['user_agent']) ?></pre>
                          </div>
                        <?php endif; ?>
                      </div>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php elseif ($tipo === 'sql_pretty'): ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= h($r['timestamp'] !== '' ? formatIsoTimestamp($r['timestamp']) : '—') ?></td>
                  <td><?= h($r['usuario']) ?></td>
                  <td><?= h($r['method_trad']) ?></td>
                  <td title="<?= h($r['ruta']) ?>"><?= h(truncateText($r['ruta'], 40)) ?></td>
                  <td>
                    <span class="log-badge" title="<?= h($r['op']) ?>">
                      <?= h($r['op_trad']) ?>
                    </span>
                  </td>
                  <td><?= h(truncateText($r['tabla'], 32)) ?></td>
                  <td>
                    <details class="log-details">
                      <summary><?= h($r['params_short']) ?></summary>
                      <div style="margin-top:0.5rem;">
                        <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">request_id</div>
                        <div><?= h($r['request_id']) ?></div>
                      </div>
                      <div style="margin-top:0.75rem;">
                        <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">Paráms (completos)</div>
                        <pre class="log-pre"><?= h($r['params'] !== '' ? $r['params'] : '—') ?></pre>
                      </div>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= h($r['timestamp'] !== '' ? formatIsoTimestamp($r['timestamp']) : '—') ?></td>
                  <td><?= h($r['usuario']) ?></td>
                  <td>
                    <span class="log-badge" title="<?= h($r['op']) ?>">
                      <?= h($r['op_trad']) ?>
                    </span>
                  </td>
                  <td><?= h(truncateText($r['tabla'], 26)) ?></td>
                  <td><?= $r['duration_ms'] !== null ? h(number_format((float)$r['duration_ms'], 3)) . ' ms' : '—' ?></td>
                  <td>
                    <?php if ($r['ok']): ?>
                      <span class="log-badge ok">OK</span>
                    <?php else: ?>
                      <span class="log-badge err">ERROR</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <details class="log-details">
                      <summary><?= h(truncateText($r['resumen'] !== '' ? $r['resumen'] : $r['sql'], 80)) ?></summary>
                      <div style="margin-top:0.75rem; display:flex; flex-direction:column; gap:0.85rem;">
                        <div>
                          <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">request_id</div>
                          <div><?= h($r['request_id'] !== '' ? $r['request_id'] : '—') ?></div>
                        </div>
                        <?php if ($r['sql'] !== ''): ?>
                          <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">SQL</div>
                            <pre class="log-pre"><?= h($r['sql']) ?></pre>
                          </div>
                        <?php endif; ?>
                        <?php if ($r['params_str'] !== ''): ?>
                          <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">Paráms</div>
                            <pre class="log-pre"><?= h($r['params_str']) ?></pre>
                          </div>
                        <?php endif; ?>
                        <?php if (!$r['ok']): ?>
                          <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.03em;">Error</div>
                            <div>Clase: <?= h($r['error_class'] !== '' ? $r['error_class'] : '—') ?></div>
                            <div>Código: <?= h($r['error_code'] !== null ? (string)$r['error_code'] : '—') ?></div>
                          </div>
                        <?php endif; ?>
                      </div>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top:1rem; color:var(--text-muted); font-size:0.9rem;">
      Archivo: <strong><?= h($archivo) ?></strong> · Entradas mostradas: <strong><?= (int) $conteo ?></strong> · Límite: <strong><?= (int) $limit ?></strong>
    </div>
  </div>
</body>
</html>

