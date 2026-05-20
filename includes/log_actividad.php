<?php
/**
 * Traduce peticiones HTTP a mensajes legibles para personal no técnico.
 */

require_once __DIR__ . '/../config/database.php';

function nombrePaginaLegible(string $basename): string {
    $map = [
        'index.php' => 'el inicio',
        'login.php' => 'el inicio de sesión',
        'logout.php' => 'cerrar sesión',
        'inventario.php' => 'inventario',
        'inventario-global.php' => 'inventario global',
        'transacciones.php' => 'transacciones',
        'productos.php' => 'productos',
        'nueva-entrada.php' => 'entradas de almacén',
        'nueva-salida.php' => 'salidas de almacén',
        'nueva-transaccion.php' => 'nueva transacción',
        'ver-transaccion.php' => 'detalle de transacción',
        'ver-logs.php' => 'registro de actividad',
        'almacenes-admin.php' => 'administración de almacenes',
        'facturas.php' => 'facturas',
        'recibos.php' => 'recibos',
        'recibo.php' => 'un recibo',
        'recibo-entrada.php' => 'recibo de entrada',
        'recibo-mes.php' => 'recibo mensual',
        'entregas-plantel-persona.php' => 'entregas por plantel',
        'imprimir-inventario-actual.php' => 'impresión de inventario',
        'cancelar-entrada.php' => 'cancelación de entrada',
        'cancelar-salida.php' => 'cancelación de salida',
        'cancelar-linea-entrada.php' => 'cancelación de línea',
    ];
    return $map[strtolower($basename)] ?? $basename;
}

/**
 * @return array{0: string, 1: string} [código_acción, detalle_técnico_corto]
 */
function inferRequestAction(string $method, string $path, array $postRaw, string $queryString = ''): array {
    $basename = strtolower(basename($path));
    $query = [];
    if ($queryString !== '') {
        parse_str($queryString, $query);
    }

    if ($method !== 'POST') {
        if ($basename === 'logout.php') {
            return ['CERRAR_SESION', ''];
        }
        if ($basename === 'login.php') {
            return ['VER_LOGIN', ''];
        }
        if ($basename === 'nueva-entrada.php' && !empty($query['id'])) {
            return ['VER_ENTRADA', 'entrada #' . (int) $query['id']];
        }
        if ($basename === 'nueva-salida.php' && !empty($query['id'])) {
            return ['VER_SALIDA', 'salida #' . (int) $query['id']];
        }
        if ($basename === 'ver-transaccion.php') {
            $tipo = trim((string) ($query['tipo'] ?? ''));
            $id = (int) ($query['id'] ?? 0);
            if ($id > 0) {
                return ['VER_TRANSACCION', ($tipo !== '' ? $tipo : 'movimiento') . ' #' . $id];
            }
        }
        return ['CONSULTA', nombrePaginaLegible($basename)];
    }

    if ($basename === 'login.php') {
        $usuario = isset($postRaw['usuario']) ? trim((string) $postRaw['usuario']) : '';
        return ['INICIAR_SESION', $usuario !== '' ? $usuario : ''];
    }

    if ($basename === 'productos.php' && isset($postRaw['crear'])) {
        $nombre = trim((string) ($postRaw['nombre'] ?? ''));
        $codigo = trim((string) ($postRaw['codigo'] ?? ''));
        $det = $nombre;
        if ($codigo !== '') {
            $det .= ($det !== '' ? ' · ' : '') . 'código ' . $codigo;
        }
        return ['CREAR_PRODUCTO', $det];
    }

    if ($basename === 'nueva-entrada.php') {
        $editId = (int) ($query['id'] ?? 0);
        $fecha = trim((string) ($postRaw['fecha'] ?? ''));
        $factura = trim((string) ($postRaw['factura'] ?? ''));
        $proveedorNuevo = trim((string) ($postRaw['proveedor_nuevo'] ?? ''));
        $productoIds = isset($postRaw['producto_id']) && is_array($postRaw['producto_id']) ? $postRaw['producto_id'] : [];
        $lineas = count($productoIds);
        $razon = trim((string) ($postRaw['razon_modificacion'] ?? ''));
        $partes = [];
        if ($fecha !== '') {
            $partes[] = 'fecha ' . formatearFechaLog($fecha);
        }
        if ($factura !== '') {
            $partes[] = 'factura ' . $factura;
        }
        if ($proveedorNuevo !== '') {
            $partes[] = 'proveedor nuevo: ' . $proveedorNuevo;
        }
        if ($lineas > 0) {
            $partes[] = $lineas . ' ' . ($lineas === 1 ? 'producto' : 'productos');
        }
        if ($razon !== '') {
            $partes[] = 'motivo: ' . truncateLogText($razon, 120);
        }
        $det = implode(' · ', $partes);
        if ($editId > 0) {
            return ['MODIFICAR_ENTRADA', 'entrada #' . $editId . ($det !== '' ? ' · ' . $det : '')];
        }
        return ['REGISTRAR_ENTRADA', $det];
    }

    if ($basename === 'nueva-salida.php') {
        $editId = (int) ($query['id'] ?? 0);
        $fecha = trim((string) ($postRaw['fecha'] ?? ''));
        $plantelNuevo = trim((string) ($postRaw['plantel_nuevo'] ?? ''));
        $productoIds = isset($postRaw['producto_id']) && is_array($postRaw['producto_id']) ? $postRaw['producto_id'] : [];
        $lineas = count($productoIds);
        $razon = trim((string) ($postRaw['razon_modificacion'] ?? ''));
        $partes = [];
        if ($fecha !== '') {
            $partes[] = 'fecha ' . formatearFechaLog($fecha);
        }
        if ($plantelNuevo !== '') {
            $partes[] = 'plantel nuevo: ' . $plantelNuevo;
        }
        if ($lineas > 0) {
            $partes[] = $lineas . ' ' . ($lineas === 1 ? 'producto' : 'productos');
        }
        if ($razon !== '') {
            $partes[] = 'motivo: ' . truncateLogText($razon, 120);
        }
        $det = implode(' · ', $partes);
        if ($editId > 0) {
            return ['MODIFICAR_SALIDA', 'salida #' . $editId . ($det !== '' ? ' · ' . $det : '')];
        }
        return ['REGISTRAR_SALIDA', $det];
    }

    if ($basename === 'cancelar-entrada.php' && isset($postRaw['confirmar'])) {
        $id = (int) ($postRaw['id'] ?? 0);
        return ['CANCELAR_ENTRADA', $id > 0 ? 'entrada #' . $id : ''];
    }

    if ($basename === 'cancelar-salida.php' && isset($postRaw['confirmar'])) {
        $id = (int) ($postRaw['id'] ?? 0);
        return ['CANCELAR_SALIDA', $id > 0 ? 'salida #' . $id : ''];
    }

    if ($basename === 'cancelar-linea-entrada.php') {
        $detalleId = (int) ($postRaw['id'] ?? 0);
        return ['CANCELAR_LINEA_ENTRADA', $detalleId > 0 ? 'línea #' . $detalleId : ''];
    }

    if ($basename === 'almacenes-admin.php') {
        $accion = trim((string) ($postRaw['accion'] ?? ''));
        if ($accion === 'crear_almacen') {
            $nombre = trim((string) ($postRaw['nombre_almacen'] ?? ''));
            return ['CREAR_ALMACEN', $nombre];
        }
        if ($accion === 'crear_usuario') {
            $login = trim((string) ($postRaw['usuario_login'] ?? ''));
            $nombre = trim((string) ($postRaw['nombre_persona'] ?? ''));
            $det = $nombre !== '' ? $nombre : $login;
            if ($login !== '' && $nombre !== '' && $login !== $nombre) {
                $det .= ' (usuario ' . $login . ')';
            }
            return ['CREAR_USUARIO', $det];
        }
        if ($accion === 'guardar_asignaciones') {
            $ids = isset($postRaw['usuario_id']) && is_array($postRaw['usuario_id']) ? count($postRaw['usuario_id']) : 0;
            return ['ASIGNAR_ALMACENES', $ids . ' ' . ($ids === 1 ? 'usuario' : 'usuarios')];
        }
    }

    if ($basename === 'recibos.php') {
        $salidaId = (int) ($postRaw['salida_id'] ?? 0);
        return ['ACCION_RECIBOS', $salidaId > 0 ? 'salida #' . $salidaId : ''];
    }

    return ['ACCION', nombrePaginaLegible($basename)];
}

/**
 * Tipos de actividad para el filtro del visor (código => etiqueta legible).
 *
 * @return array<string, string>
 */
function tiposActividadParaFiltro(): array {
    return [
        'INICIAR_SESION' => 'Inicio de sesión',
        'CERRAR_SESION' => 'Cierre de sesión',
        'CREAR_PRODUCTO' => 'Creó un producto',
        'REGISTRAR_ENTRADA' => 'Registró una entrada',
        'MODIFICAR_ENTRADA' => 'Modificó una entrada',
        'CANCELAR_ENTRADA' => 'Canceló una entrada',
        'CANCELAR_LINEA_ENTRADA' => 'Canceló línea de entrada',
        'REGISTRAR_SALIDA' => 'Registró una salida',
        'MODIFICAR_SALIDA' => 'Modificó una salida',
        'CANCELAR_SALIDA' => 'Canceló una salida',
        'CREAR_ALMACEN' => 'Creó un almacén',
        'CREAR_USUARIO' => 'Creó un usuario',
        'ASIGNAR_ALMACENES' => 'Asignó almacenes',
        'ACCION_RECIBOS' => 'Acción en recibos',
        'ACCION' => 'Otra acción',
    ];
}

function etiquetaAccion(string $codigo): string {
    return match ($codigo) {
        'INICIAR_SESION' => 'Inició sesión',
        'CERRAR_SESION' => 'Cerró sesión',
        'VER_LOGIN' => 'Vio pantalla de acceso',
        'CONSULTA' => 'Consultó',
        'VER_ENTRADA' => 'Abrió entrada',
        'VER_SALIDA' => 'Abrió salida',
        'VER_TRANSACCION' => 'Vio transacción',
        'CREAR_PRODUCTO' => 'Creó un producto',
        'REGISTRAR_ENTRADA' => 'Registró una entrada',
        'MODIFICAR_ENTRADA' => 'Modificó una entrada',
        'REGISTRAR_SALIDA' => 'Registró una salida',
        'MODIFICAR_SALIDA' => 'Modificó una salida',
        'CANCELAR_ENTRADA' => 'Canceló una entrada',
        'CANCELAR_SALIDA' => 'Canceló una salida',
        'CANCELAR_LINEA_ENTRADA' => 'Canceló una línea de entrada',
        'CREAR_ALMACEN' => 'Creó un almacén',
        'CREAR_USUARIO' => 'Creó un usuario',
        'ASIGNAR_ALMACENES' => 'Asignó almacenes a usuarios',
        'ACCION_RECIBOS' => 'Acción en recibos',
        'ACCION' => 'Realizó una acción',
        default => $codigo !== '' ? $codigo : 'Actividad',
    };
}

/**
 * Frase completa para mostrar en la tabla principal.
 */
/** @return array<string, mixed> */
function contextoDesdeEntrada(array $entrada): array {
    $productos = [];
    foreach ($entrada['detalle'] ?? [] as $d) {
        if (($d['estado'] ?? 'activa') === 'cancelada') {
            continue;
        }
        $cant = (int) ($d['cantidad'] ?? 0);
        if ($cant <= 0) {
            continue;
        }
        $productos[] = [
            'nombre' => (string) ($d['producto_nombre'] ?? 'Producto'),
            'cantidad' => $cant,
            'unidad' => (string) ($d['unidad'] ?? 'und'),
            'tipo' => 'entrada',
        ];
    }
    return [
        'tipo_movimiento' => 'entrada',
        'movimiento_id' => (int) ($entrada['id'] ?? 0),
        'referencia' => (string) ($entrada['referencia'] ?? ''),
        'fecha' => (string) ($entrada['fecha'] ?? ''),
        'proveedor' => (string) ($entrada['proveedor_nombre'] ?? ''),
        'quien_recibe' => (string) ($entrada['quien_recibe_nombre'] ?? ''),
        'factura' => (string) ($entrada['factura'] ?? ''),
        'productos' => $productos,
    ];
}

/** @return array<string, mixed> */
function contextoDesdeSalida(array $salida): array {
    $productos = [];
    foreach ($salida['detalle'] ?? [] as $d) {
        $cant = (int) ($d['cantidad'] ?? 0);
        if ($cant <= 0) {
            continue;
        }
        $productos[] = [
            'nombre' => (string) ($d['producto_nombre'] ?? 'Producto'),
            'cantidad' => $cant,
            'unidad' => (string) ($d['unidad'] ?? 'und'),
            'tipo' => 'salida',
        ];
    }
    return [
        'tipo_movimiento' => 'salida',
        'movimiento_id' => (int) ($salida['id'] ?? 0),
        'referencia' => (string) ($salida['referencia'] ?? ''),
        'fecha' => (string) ($salida['fecha'] ?? ''),
        'quien_entrega' => (string) ($salida['nombre_entrega'] ?? ''),
        'plantel' => (string) ($salida['plantel_nombre'] ?? ''),
        'receptor' => (string) ($salida['nombre_receptor'] ?? ''),
        'productos' => $productos,
    ];
}

function nombreCatalogoPorId(string $tabla, int $id): string {
    if ($id <= 0) {
        return '';
    }
    $tablas = [
        'catalogo_proveedor' => 'proveedor',
        'catalogo_quien_recibe_entrada' => 'quien_recibe',
        'catalogo_quien_entrega' => 'quien_entrega',
        'catalogo_plantel' => 'plantel',
        'catalogo_receptor' => 'receptor',
    ];
    if (!isset($tablas[$tabla])) {
        return '';
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT nombre FROM {$tabla} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return trim((string) ($row['nombre'] ?? ''));
    } catch (Throwable $e) {
        return '';
    }
}

function nombreProductoPorId(int $id): string {
    if ($id <= 0) {
        return '';
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT nombre, unidad FROM productos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }
        return trim((string) ($row['nombre'] ?? ''));
    } catch (Throwable $e) {
        return '';
    }
}

function unidadProductoPorId(int $id): string {
    if ($id <= 0) {
        return 'und';
    }
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT unidad FROM productos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return trim((string) ($row['unidad'] ?? 'und')) ?: 'und';
    } catch (Throwable $e) {
        return 'und';
    }
}

/**
 * Extrae líneas de producto desde POST (entrada o salida).
 *
 * @return list<array{nombre: string, cantidad: int, unidad: string, tipo: string}>
 */
function productosDesdePost(array $post, string $tipoMovimiento): array {
    $items = [];
    $ids = isset($post['producto_id']) && is_array($post['producto_id']) ? $post['producto_id'] : [];
    $cantidades = isset($post['cantidad']) && is_array($post['cantidad']) ? $post['cantidad'] : [];

    foreach ($ids as $i => $pid) {
        $cant = (int) ($cantidades[$i] ?? 0);
        if ($cant <= 0) {
            continue;
        }
        $pid = is_string($pid) ? trim($pid) : (string) $pid;
        if ($pid === '' || $pid === '0') {
            continue;
        }

        if ($pid === 'nuevo') {
            $nombre = trim((string) ($post['producto_nuevo_nombre'][$i] ?? ''));
            $unidad = trim((string) ($post['producto_nuevo_unidad'][$i] ?? 'und'));
            if ($unidad === 'otra') {
                $unidad = trim((string) ($post['producto_nuevo_unidad_nueva'][$i] ?? 'und'));
            }
            if ($nombre === '') {
                $nombre = 'Producto nuevo';
            }
        } else {
            $idNum = (int) $pid;
            $nombre = nombreProductoPorId($idNum);
            if ($nombre === '') {
                $nombre = 'Producto #' . $idNum;
            }
            $unidad = unidadProductoPorId($idNum);
        }

        $items[] = [
            'nombre' => $nombre,
            'cantidad' => $cant,
            'unidad' => $unidad !== '' ? $unidad : 'und',
            'tipo' => $tipoMovimiento,
        ];
    }
    return $items;
}

function resumenTextoMovimiento(array $ctx): string {
    $partes = [];
    $ref = trim((string) ($ctx['referencia'] ?? ''));
    $movId = (int) ($ctx['movimiento_id'] ?? 0);
    if ($ref !== '') {
        $partes[] = $ref;
    } elseif ($movId > 0) {
        $partes[] = '#' . $movId;
    }

    $fecha = trim((string) ($ctx['fecha'] ?? ''));
    if ($fecha !== '') {
        $partes[] = 'fecha ' . formatearFechaLog($fecha);
    }

    if (!empty($ctx['proveedor'])) {
        $partes[] = 'proveedor: ' . $ctx['proveedor'];
    }
    if (!empty($ctx['quien_recibe'])) {
        $partes[] = 'recibe en almacén: ' . $ctx['quien_recibe'];
    }
    if (!empty($ctx['quien_entrega'])) {
        $partes[] = 'entrega: ' . $ctx['quien_entrega'];
    }
    if (!empty($ctx['plantel'])) {
        $partes[] = 'plantel: ' . $ctx['plantel'];
    }
    if (!empty($ctx['receptor'])) {
        $partes[] = 'receptor: ' . $ctx['receptor'];
    }
    if (!empty($ctx['factura'])) {
        $partes[] = 'factura: ' . $ctx['factura'];
    }
    if (!empty($ctx['motivo'])) {
        $partes[] = 'motivo: ' . truncateLogText((string) $ctx['motivo'], 100);
    }

    $productos = $ctx['productos'] ?? [];
    if (is_array($productos) && count($productos) > 0) {
        $n = count($productos);
        $tipo = ($ctx['tipo_movimiento'] ?? '') === 'salida' ? 'salieron' : 'entraron';
        $partes[] = $n . ' ' . ($n === 1 ? 'producto' : 'productos') . ' (' . $tipo . ')';
    }

    return implode(' · ', $partes);
}

/**
 * @param list<array{nombre?: string, cantidad?: int, unidad?: string, tipo?: string}> $items
 */
/**
 * HTML del bloque expandible (metadatos + lista de productos).
 *
 * @param list<array<string, mixed>> $items
 * @param array<string, mixed> $ctx
 */
function generarHtmlDetalleActividad(array $ctx, array $items): string {
    if ($items === []) {
        return '';
    }
    $tipoMov = (string) ($ctx['tipo_movimiento'] ?? 'entrada');
    $meta = [];
    if (!empty($ctx['referencia'])) {
        $meta[] = '<div><strong>Folio:</strong> ' . htmlspecialchars((string) $ctx['referencia'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['fecha'])) {
        $meta[] = '<div><strong>Fecha:</strong> ' . htmlspecialchars(formatearFechaLog((string) $ctx['fecha']), ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['proveedor'])) {
        $meta[] = '<div><strong>Proveedor:</strong> ' . htmlspecialchars((string) $ctx['proveedor'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['quien_recibe'])) {
        $meta[] = '<div><strong>Recibe en almacén:</strong> ' . htmlspecialchars((string) $ctx['quien_recibe'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['quien_entrega'])) {
        $meta[] = '<div><strong>Quien entrega:</strong> ' . htmlspecialchars((string) $ctx['quien_entrega'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['plantel'])) {
        $meta[] = '<div><strong>Plantel:</strong> ' . htmlspecialchars((string) $ctx['plantel'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['receptor'])) {
        $meta[] = '<div><strong>Receptor:</strong> ' . htmlspecialchars((string) $ctx['receptor'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['factura'])) {
        $meta[] = '<div><strong>Factura:</strong> ' . htmlspecialchars((string) $ctx['factura'], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if (!empty($ctx['motivo'])) {
        $meta[] = '<div><strong>Motivo del cambio:</strong> ' . htmlspecialchars((string) $ctx['motivo'], ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $tituloLista = $tipoMov === 'salida' ? 'Productos que salieron del almacén' : 'Productos que entraron al almacén';
    if (str_contains((string) ($ctx['_codigo'] ?? ''), 'CANCELAR')) {
        $tituloLista = $tipoMov === 'salida' ? 'Productos afectados (salida cancelada)' : 'Productos afectados (entrada cancelada)';
    }

    $html = '<div class="log-detalle-bloque">';
    if ($meta !== []) {
        $html .= '<div class="log-detalle-meta">' . implode('', $meta) . '</div>';
    }
    $html .= '<div class="log-detalle-titulo-lista">' . htmlspecialchars($tituloLista, ENT_QUOTES, 'UTF-8') . '</div>';
    $html .= formatearListaProductosHtml($items, $tipoMov);
    $html .= '</div>';
    return $html;
}

function formatearListaProductosHtml(array $items, string $tipoMovimiento = 'entrada'): string {
    if ($items === []) {
        return '';
    }
    $verbo = $tipoMovimiento === 'salida' ? 'Salió' : 'Entró';
    $lineas = [];
    foreach ($items as $it) {
        $nombre = trim((string) ($it['nombre'] ?? 'Producto'));
        $cant = (int) ($it['cantidad'] ?? 0);
        $unidad = trim((string) ($it['unidad'] ?? 'und'));
        $tipoItem = (string) ($it['tipo'] ?? '');
        if ($tipoItem === 'catalogo' || $cant <= 0) {
            $lineas[] = 'Alta en catálogo: <strong>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</strong>'
                . ($unidad !== '' ? ' (' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8') . ')' : '');
            continue;
        }
        $lineas[] = $verbo . ': <strong>' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</strong> — '
            . $cant . ' ' . htmlspecialchars($unidad, ENT_QUOTES, 'UTF-8');
    }
    return '<ul class="log-items-list"><li>' . implode('</li><li>', $lineas) . '</li></ul>';
}

/**
 * Intenta reconstruir detalle para registros antiguos sin detalle_items.
 *
 * @return array{items: list<array>, contexto: array<string, mixed>}
 */
function enriquecerDetalleParaVisor(string $codigo, string $detalle, array $postRaw, array $contextoGuardado = []): array {
    if (!empty($contextoGuardado['productos']) && is_array($contextoGuardado['productos'])) {
        return [
            'items' => $contextoGuardado['productos'],
            'contexto' => $contextoGuardado,
        ];
    }

    if (preg_match('/línea\s*#(\d+)/iu', $detalle, $mLinea)) {
        $detalleId = (int) $mLinea[1];
        require_once __DIR__ . '/entradas.php';
        $linea = obtenerLineaEntrada($detalleId);
        if ($linea) {
            $nombre = nombreProductoPorId((int) ($linea['producto_id'] ?? 0));
            $items = [[
                'nombre' => $nombre !== '' ? $nombre : 'Producto',
                'cantidad' => (int) ($linea['cantidad'] ?? 0),
                'unidad' => unidadProductoPorId((int) ($linea['producto_id'] ?? 0)),
                'tipo' => 'entrada',
            ]];
            return [
                'items' => $items,
                'contexto' => [
                    'tipo_movimiento' => 'entrada',
                    'movimiento_id' => (int) ($linea['entrada_id'] ?? 0),
                    'productos' => $items,
                ],
            ];
        }
    }

    if (preg_match('/entrada\s*#(\d+)/i', $detalle, $m) || preg_match('/salida\s*#(\d+)/i', $detalle, $m) || preg_match('/#(\d+)/', $detalle, $m)) {
        $id = (int) $m[1];
        if (str_contains($codigo, 'ENTRADA') || str_contains($codigo, 'LINEA_ENTRADA')) {
            require_once __DIR__ . '/entradas.php';
            $entrada = obtenerEntradaConDetalle($id);
            if ($entrada) {
                $ctx = contextoDesdeEntrada($entrada);
                return ['items' => $ctx['productos'], 'contexto' => $ctx];
            }
        }
        if (str_contains($codigo, 'SALIDA')) {
            require_once __DIR__ . '/salidas.php';
            $salida = obtenerSalidaConDetalle($id);
            if ($salida) {
                $ctx = contextoDesdeSalida($salida);
                return ['items' => $ctx['productos'], 'contexto' => $ctx];
            }
        }
    }

    if ($postRaw !== []) {
        if (str_contains($codigo, 'ENTRADA')) {
            $ctx = [
                'tipo_movimiento' => 'entrada',
                'fecha' => trim((string) ($postRaw['fecha'] ?? '')),
                'proveedor' => trim((string) ($postRaw['proveedor_nuevo'] ?? ''))
                    ?: nombreCatalogoPorId('catalogo_proveedor', (int) ($postRaw['proveedor_id'] ?? 0)),
                'quien_recibe' => trim((string) ($postRaw['quien_recibe_nuevo'] ?? ''))
                    ?: nombreCatalogoPorId('catalogo_quien_recibe_entrada', (int) ($postRaw['quien_recibe_id'] ?? 0)),
                'factura' => trim((string) ($postRaw['factura'] ?? '')),
                'motivo' => trim((string) ($postRaw['razon_modificacion'] ?? '')),
                'productos' => productosDesdePost($postRaw, 'entrada'),
            ];
            return ['items' => $ctx['productos'], 'contexto' => $ctx];
        }
        if (str_contains($codigo, 'SALIDA')) {
            $ctx = [
                'tipo_movimiento' => 'salida',
                'fecha' => trim((string) ($postRaw['fecha'] ?? '')),
                'quien_entrega' => trim((string) ($postRaw['quien_entrega_nuevo'] ?? ''))
                    ?: nombreCatalogoPorId('catalogo_quien_entrega', (int) ($postRaw['quien_entrega_id'] ?? 0)),
                'plantel' => trim((string) ($postRaw['plantel_nuevo'] ?? ''))
                    ?: nombreCatalogoPorId('catalogo_plantel', (int) ($postRaw['plantel_id'] ?? 0)),
                'receptor' => trim((string) ($postRaw['receptor_nuevo'] ?? ''))
                    ?: nombreCatalogoPorId('catalogo_receptor', (int) ($postRaw['receptor_id'] ?? 0)),
                'motivo' => trim((string) ($postRaw['razon_modificacion'] ?? '')),
                'productos' => productosDesdePost($postRaw, 'salida'),
            ];
            return ['items' => $ctx['productos'], 'contexto' => $ctx];
        }
    }

    return ['items' => [], 'contexto' => []];
}

function mensajeActividadLegible(
    string $codigo,
    string $detalle,
    ?string $usuarioNombre = null,
    string $method = '',
    string $path = '',
    array $contexto = []
): string {
    $quien = trim((string) $usuarioNombre);
    if ($quien === '') {
        $quien = 'Alguien';
    }

    $etiqueta = etiquetaAccion($codigo);
    $detalle = trim($detalle);

    if ($codigo === 'CONSULTA') {
        $pagina = $detalle !== '' ? $detalle : nombrePaginaLegible(basename($path));
        return $quien . ' consultó ' . $pagina . '.';
    }

    if ($codigo === 'INICIAR_SESION') {
        if ($detalle !== '') {
            return $quien . ' inició sesión (usuario «' . $detalle . '»).';
        }
        return $quien . ' inició sesión.';
    }

    if ($codigo === 'CERRAR_SESION') {
        return $quien . ' cerró sesión.';
    }

    if ($codigo === 'VER_LOGIN') {
        return $quien . ' abrió la pantalla de inicio de sesión.';
    }

    if ($codigo === 'VER_ENTRADA' || $codigo === 'VER_SALIDA' || $codigo === 'VER_TRANSACCION') {
        return $quien . ' ' . mb_strtolower($etiqueta) . ($detalle !== '' ? ' (' . $detalle . ')' : '') . '.';
    }

    if ($codigo === 'CREAR_PRODUCTO') {
        if ($detalle !== '') {
            return $quien . ' creó el producto «' . $detalle . '».';
        }
        return $quien . ' creó un producto nuevo.';
    }

    if ($codigo === 'CREAR_ALMACEN') {
        if ($detalle !== '') {
            return $quien . ' creó el almacén «' . $detalle . '».';
        }
        return $quien . ' creó un almacén nuevo.';
    }

    if ($codigo === 'CREAR_USUARIO') {
        if ($detalle !== '') {
            return $quien . ' creó el usuario «' . $detalle . '».';
        }
        return $quien . ' creó un usuario nuevo.';
    }

    if ($codigo === 'ASIGNAR_ALMACENES') {
        if ($detalle !== '') {
            return $quien . ' actualizó las asignaciones de almacén (' . $detalle . ').';
        }
        return $quien . ' actualizó las asignaciones de almacén.';
    }

    $verbos = [
        'REGISTRAR_ENTRADA' => 'registró una entrada de almacén',
        'MODIFICAR_ENTRADA' => 'modificó una entrada de almacén',
        'REGISTRAR_SALIDA' => 'registró una salida de almacén',
        'MODIFICAR_SALIDA' => 'modificó una salida de almacén',
        'CANCELAR_ENTRADA' => 'canceló una entrada',
        'CANCELAR_SALIDA' => 'canceló una salida',
        'CANCELAR_LINEA_ENTRADA' => 'canceló una línea de una entrada',
        'ACCION_RECIBOS' => 'realizó una acción en recibos',
        'ACCION' => 'realizó una acción en ' . nombrePaginaLegible(basename($path)),
    ];

    $verbo = $verbos[$codigo] ?? mb_strtolower($etiqueta);
    $productos = $contexto['productos'] ?? [];
    $nProd = is_array($productos) ? count($productos) : 0;
    $tipoMov = (string) ($contexto['tipo_movimiento'] ?? '');
    $sufijoProductos = '';
    if ($nProd > 0 && in_array($codigo, [
        'REGISTRAR_ENTRADA', 'MODIFICAR_ENTRADA', 'REGISTRAR_SALIDA', 'MODIFICAR_SALIDA',
        'CANCELAR_ENTRADA', 'CANCELAR_SALIDA', 'CANCELAR_LINEA_ENTRADA',
    ], true)) {
        $esSalida = $tipoMov === 'salida' || str_contains($codigo, 'SALIDA');
        $esCancel = str_starts_with($codigo, 'CANCELAR');
        if ($esCancel) {
            $accionProd = $esSalida ? 'se anuló salida de' : 'se anuló entrada de';
        } else {
            $accionProd = $esSalida ? 'salieron del' : 'entraron al';
        }
        $sufijoProductos = ' — ' . $nProd . ' ' . ($nProd === 1 ? 'producto' : 'productos') . ' (' . $accionProd . ' almacén, ver detalle)';
    }

    if ($detalle !== '') {
        return $quien . ' ' . $verbo . ' (' . $detalle . ')' . $sufijoProductos . '.';
    }
    return $quien . ' ' . $verbo . $sufijoProductos . '.';
}

function formatearFechaLog(string $fecha): string {
    $t = strtotime($fecha);
    if ($t === false) {
        return $fecha;
    }
    return date('d/m/Y', $t);
}

function truncateLogText(string $s, int $max = 200): string {
    $s = trim($s);
    if (mb_strlen($s) <= $max) {
        return $s;
    }
    return mb_substr($s, 0, $max) . '…';
}
