<?php
/**
 * logout.php — Cierra la sesión y redirige al login.
 */
require_once __DIR__ . '/auth.php';

session_unset();
session_destroy();

header('Location: ' . APP_URL . '/index.php');
exit;
