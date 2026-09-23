<?php
/**
 * admin/usuarios/eliminar.php — Borrado lógico: marca activo = 0.
 * No se borra físicamente para preservar el historial de asistencia
 * y evitar errores por llaves foráneas. Solo acepta POST.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

// Solo se procesa si la petición es POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['flash_msg']  = 'ID de usuario inválido.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

// Impedir que el admin se elimine a sí mismo
if ((int)$id === (int)$_SESSION['usuario_id']) {
    $_SESSION['flash_msg']  = 'No puedes eliminar tu propia cuenta mientras estás activo en el sistema.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

$pdo = conectarBD();

// Obtener nombre y estado actual del usuario
$stmtN = $pdo->prepare('SELECT nombre_completo, activo FROM usuarios WHERE id = ?');
$stmtN->execute([$id]);
$fila  = $stmtN->fetch();

if (!$fila) {
    $_SESSION['flash_msg']  = 'Usuario no encontrado.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

// Verificar si ya está inactivo (por si se intenta dos veces)
if (!(int)$fila['activo']) {
    $_SESSION['flash_msg']  = 'El usuario "' . $fila['nombre_completo'] . '" ya estaba inactivo.';
    $_SESSION['flash_tipo'] = 'advertencia';
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

try {
    // Borrado lógico: desactivar en lugar de eliminar físicamente.
    // Preserva el historial de asistencia y evita errores de llave foránea.
    $stmtD = $pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?');
    $stmtD->execute([$id]);

    $_SESSION['flash_msg']  = 'Usuario "' . $fila['nombre_completo'] . '" desactivado correctamente. Su historial de asistencia se conserva.';
    $_SESSION['flash_tipo'] = 'exito';
} catch (\Exception $e) {
    $_SESSION['flash_msg']  = 'No se pudo desactivar al usuario. Intenta de nuevo.';
    $_SESSION['flash_tipo'] = 'error';
}

header('Location: ' . APP_URL . '/admin/usuarios/index.php');
exit;
