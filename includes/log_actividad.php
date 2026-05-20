<?php
/**
 * Traduce peticiones HTTP a mensajes legibles para personal no técnico.
 */

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
function mensajeActividadLegible(
    string $codigo,
    string $detalle,
    ?string $usuarioNombre = null,
    string $method = '',
    string $path = ''
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
    if ($detalle !== '') {
        return $quien . ' ' . $verbo . ' (' . $detalle . ').';
    }
    return $quien . ' ' . $verbo . '.';
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
