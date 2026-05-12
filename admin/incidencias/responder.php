<?php
/**
 * admin/incidencias/responder.php — Aprobar o rechazar una incidencia.
 * Solo acepta POST. Redirige siempre al listado con un mensaje flash.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/incidencias/index.php');
    exit;
}

$id       = filter_input(INPUT_POST, 'id',     FILTER_VALIDATE_INT);
$accion   = $_POST['accion']   ?? '';
$respuesta = trim($_POST['respuesta'] ?? '');

if (!$id || !in_array($accion, ['aprobada', 'rechazada'], true)) {
    $_SESSION['flash_msg']  = 'Datos inválidos.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/incidencias/index.php');
    exit;
}

$pdo = conectarBD();

// Verificar que la incidencia existe y está pendiente
$stmtV = $pdo->prepare('SELECT id, estado FROM incidencias WHERE id = ? LIMIT 1');
$stmtV->execute([$id]);
$inc = $stmtV->fetch();

if (!$inc) {
    $_SESSION['flash_msg']  = 'Incidencia no encontrada.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/incidencias/index.php');
    exit;
}

if ($inc['estado'] !== 'pendiente') {
    $_SESSION['flash_msg']  = 'Esta incidencia ya fue procesada anteriormente.';
    $_SESSION['flash_tipo'] = 'advertencia';
    header('Location: ' . APP_URL . '/admin/incidencias/index.php');
    exit;
}

$stmt = $pdo->prepare('
    UPDATE incidencias
       SET estado = ?, respuesta = ?, respondido_por = ?
     WHERE id = ?
');
$stmt->execute([
    $accion,
    $respuesta !== '' ? $respuesta : null,
    $_SESSION['usuario_id'],
    $id,
]);

$etiqueta = ($accion === 'aprobada') ? 'aprobada' : 'rechazada';
$_SESSION['flash_msg']  = 'Incidencia ' . $etiqueta . ' correctamente.';
$_SESSION['flash_tipo'] = ($accion === 'aprobada') ? 'exito' : 'advertencia';
header('Location: ' . APP_URL . '/admin/incidencias/index.php?estado=pendiente');
exit;
