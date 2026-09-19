<?php
/**
 * logout.php — Cierra la sesión y redirige al login.
 */
require_once __DIR__ . '/auth.php';

session_unset();
session_destroy();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"]
    );
}

// Destruir cookie de autologin
setcookie('remember_user', '', time() - 3600, '/');

header('Location: ' . APP_URL . '/index.php');
exit;
