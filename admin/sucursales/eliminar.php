<?php
/**
 * admin/sucursales/eliminar.php - Borrado lógico: marca activo = 0.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/sucursales/index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['flash_msg']  = 'ID de sucursal inválido.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/sucursales/index.php');
    exit;
}

$pdo = conectarBD();

$stmtN = $pdo->prepare('SELECT nombre, activo FROM sucursales WHERE id = ?');
$stmtN->execute([$id]);
$fila  = $stmtN->fetch();

if (!$fila) {
    $_SESSION['flash_msg']  = 'Sucursal no encontrada.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/sucursales/index.php');
    exit;
}

$nuevoEstado = $fila['activo'] ? 0 : 1;
$msgEstado = $nuevoEstado ? 'activada' : 'desactivada';

try {
    $stmtD = $pdo->prepare('UPDATE sucursales SET activo = ? WHERE id = ?');
    $stmtD->execute([$nuevoEstado, $id]);

    $_SESSION['flash_msg']  = 'Sucursal "' . $fila['nombre'] . '" ' . $msgEstado . ' correctamente.';
    $_SESSION['flash_tipo'] = 'exito';
} catch (\Exception $e) {
    $_SESSION['flash_msg']  = 'No se pudo modificar la sucursal. Intenta de nuevo.';
    $_SESSION['flash_tipo'] = 'error';
}

header('Location: ' . APP_URL . '/admin/sucursales/index.php');
exit;
