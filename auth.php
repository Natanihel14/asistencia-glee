<?php
/**
 * auth.php — Validación de sesión y control de acceso por rol.
 * Incluir al inicio de cada página protegida, ANTES de cualquier salida HTML.
 */

// Ruta base de la aplicación (sin barra final)
define('APP_URL', '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica que haya sesión activa.
 * Si no la hay, redirige al login.
 */
function requerirSesion(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

/**
 * Devuelve la URL del dashboard según el rol del usuario en sesión.
 */
function urlDashboard(): string
{
    $esAdmin = in_array($_SESSION['rol'] ?? '', ['administrador', 'supervisor'], true);
    return APP_URL . ($esAdmin ? '/admin/dashboard.php' : '/vendedor/dashboard.php');
}

/**
 * Verifica sesión Y que el rol del usuario esté en la lista permitida.
 * Sin sesión   → redirige al login.
 * Rol incorrecto → redirige a su panel correspondiente.
 */
function requerirRol(array $rolesPermitidos): void
{
    requerirSesion();

    if (!in_array($_SESSION['rol'], $rolesPermitidos, true)) {
        $esAdmin = in_array($_SESSION['rol'], ['administrador', 'supervisor'], true);
        header('Location: ' . APP_URL . ($esAdmin ? '/admin/dashboard.php' : '/vendedor/dashboard.php'));
        exit;
    }
}
