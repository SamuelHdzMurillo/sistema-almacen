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
$ok = cancelarLineaEntrada($detalleId);

if ($ok) {
    header('Location: ver-transaccion.php?tipo=in&id=' . $entradaId . '&cancelado_linea=1');
} else {
    header('Location: ver-transaccion.php?tipo=in&id=' . $entradaId . '&error_linea=1');
}
exit;
