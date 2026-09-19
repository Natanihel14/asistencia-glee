<?php
/**
 * auth.php — Validación de sesión y control de acceso por rol.
 * Incluir al inicio de cada página protegida, ANTES de cualquier salida HTML.
 */

// Ruta base de la aplicación (sin barra final)
define('APP_URL', '');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 2592000);
    ini_set('session.cookie_lifetime', 2592000);
    session_save_path(__DIR__ . '/sessions');
    session_start();
    
    // Forzar actualización de la cookie en el navegador
    setcookie(session_name(), session_id(), time() + 2592000, "/", "", true, true);
}

/**
 * Verifica que haya sesión activa.
 * Si no la hay, intenta auto-login con cookie 'remember_user'.
 * Si falla, redirige al login.
 */
function requerirLogin() {
    if (empty($_SESSION['usuario_id'])) {
        // Intento de auto-login con cookie persistente
        if (!empty($_COOKIE['remember_user'])) {
            $pdo = conectarBD();
            $stmt = $pdo->prepare("SELECT id, nombre_completo, rol, sucursal_id FROM usuarios WHERE id = ? AND activo = 1");
            $stmt->execute([$_COOKIE['remember_user']]);
            $u = $stmt->fetch();
            if ($u) {
                $_SESSION['usuario_id'] = $u['id'];
                $_SESSION['nombre_completo'] = $u['nombre_completo'];
                $_SESSION['rol'] = $u['rol'];
                $_SESSION['sucursal_id'] = $u['sucursal_id'];
                return; // Auto-login exitoso
            }
        }
        
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

/**
 * Verifica que haya sesión activa.
 * Si no la hay, redirige al login.
 */
function requerirSesion(): void
{
    requerirLogin();
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
