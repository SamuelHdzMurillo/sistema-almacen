<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirmar']) || $_POST['confirmar'] !== '1') {
    header('Location: transacciones.php');
    exit;
}

$detalleId = (int)($_POST['id'] ?? 0);
if ($detalleId <= 0) {
    header('Location: transacciones.php?error=id');
    exit;
}

$linea = obtenerLineaEntrada($detalleId);
if (!$linea) {
    header('Location: transacciones.php?error=id');
    exit;
}

$entradaId = (int)$linea['entrada_id'];
$entradaLog = obtenerEntradaConDetalle($entradaId);
$lineaDetalle = null;
if ($entradaLog) {
    foreach (($entradaLog['detalle'] ?? []) as $d) {
        if ((int)($d['id'] ?? 0) === $detalleId) {
            $lineaDetalle = $d;
            break;
        }
    }
}
$ok = cancelarLineaEntrada($detalleId);

if ($ok && $entradaLog && $lineaDetalle) {
    $ctxLog = contextoDesdeEntrada($entradaLog);
    $ctxLog['productos'] = [[
        'nombre' => (string)($lineaDetalle['producto_nombre'] ?? 'Producto'),
        'cantidad' => (int)($lineaDetalle['cantidad'] ?? 0),
        'unidad' => (string)($lineaDetalle['unidad'] ?? 'und'),
        'tipo' => 'entrada',
    ]];
    $ctxLog['detalle'] = ($entradaLog['referencia'] ?? ('entrada #' . $entradaId))
        . ' · línea: ' . ($lineaDetalle['producto_nombre'] ?? '');
    registrarActividad('CANCELAR_LINEA_ENTRADA', $ctxLog, '/cancelar-linea-entrada.php');
}

if ($ok) {
    header('Location: ver-transaccion.php?tipo=in&id=' . $entradaId . '&cancelado_linea=1');
} else {
    header('Location: ver-transaccion.php?tipo=in&id=' . $entradaId . '&error_linea=1');
}
exit;
