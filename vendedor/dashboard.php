<?php
/**
 * vendedor/dashboard.php — Panel principal del Vendedor / Bodega.
 * Solo accesible para roles: vendedor, bodega.
 */
require_once __DIR__ . '/../auth.php';
requerirRol(['vendedor', 'bodega']);

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
$rol    = ucfirst($_SESSION['rol']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Panel &mdash; GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e293b">
    <link rel="apple-touch-icon" href="/assets/img/logo.png">
</head>
<body>

    <header class="topbar">
        <a class="topbar-marca" href="<?= urlDashboard() ?>">GLEE</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?> &mdash; <?= $rol ?></span>
            <a href="/logout.php" class="btn-salir">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <main class="contenedor">
        <div class="bienvenida">
            <h2>Mi Panel</h2>
            <p>Bienvenido, <?= $nombre ?>. &iquest;Qu&eacute; deseas hacer hoy?</p>
        </div>

        <nav class="menu-grid">

            <a class="menu-tarjeta" href="/vendedor/marcar.php">
                <div class="menu-tarjeta-icono verde">
                    <i class="fa-solid fa-fingerprint"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Marcar Asistencia</h3>
                    <p>Registrar entrada o salida del turno</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/vendedor/mis_marcas.php">
                <div class="menu-tarjeta-icono azul">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Mis Marcas</h3>
                    <p>Ver mi historial de asistencia</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/vendedor/incidencias.php">
                <div class="menu-tarjeta-icono" style="background:#e67e22">
                    <i class="fa-solid fa-file-pen"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Incidencias / Permisos</h3>
                    <p>Solicitar permiso o justificaci&oacute;n de retardo</p>
                </div>
            </a>

            <a class="menu-tarjeta peligro" href="/logout.php">
                <div class="menu-tarjeta-icono rojo">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Cerrar Sesi&oacute;n</h3>
                    <p>Salir del sistema de forma segura</p>
                </div>
            </a>

        </nav>
    </main>

    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js');
        });
      }
    </script>
</body>
</html>
