<?php
// ============================================================
// config.php — Conexión centralizada a la base de datos
// Incluir siempre con: require_once __DIR__ . '/config.php';
// o con ruta relativa al raíz según la profundidad del archivo.
// ============================================================

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'glee_asistencia');
define('DB_USER',    'root');
define('DB_PASS',    '');           // Laragon: contraseña vacía por defecto
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('America/Guatemala');

/**
 * Devuelve una instancia única de PDO (patrón singleton).
 */
function conectarBD(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST, DB_NAME, DB_CHARSET
    );

      $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-06:00'"
    ];



    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $e) {
        // No exponer detalles técnicos 
        $detalle = htmlspecialchars($e->getMessage());
        die("
            <style>body{font-family:sans-serif;padding:2rem;color:#c00}</style>
            <h2>Error de conexión a la base de datos</h2>
            <p>Verifica que MySQL esté corriendo en Laragon y que
               <code>config.php</code> tenga las credenciales correctas.</p>
            <pre>{$detalle}</pre>
        ");
    }

    return $pdo;
}
