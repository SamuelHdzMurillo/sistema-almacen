<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();
require_once __DIR__ . '/includes/entradas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirmar']) || $_POST['confirmar'] !== '1') {
    header('Location: transacciones.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: transacciones.php?error=id');
    exit;
}

$entradaLog = obtenerEntradaConDetalle($id);
$ok = cancelarEntrada($id);
if ($ok && $entradaLog) {
    registrarActividad('CANCELAR_ENTRADA', contextoDesdeEntrada($entradaLog), '/cancelar-entrada.php');
}
header('Location: transacciones.php?' . ($ok ? 'cancelado=1' : 'error=cancelar'));
exit;
