<?php
/**
 * admin/dashboard.php — Panel principal del Administrador.
 * Solo accesible para roles: administrador, supervisor.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();
$stmt = $pdo->query("SELECT COUNT(*) FROM incidencias WHERE estado = 'pendiente'");
$incidenciasPendientes = (int)$stmt->fetchColumn();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
$rol    = ucfirst($_SESSION['rol']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador — GLEE</title>
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
            <h2>Panel de Administrador</h2>
            <p>Bienvenido, <?= $nombre ?>. Selecciona una opci&oacute;n para continuar.</p>
        </div>

        <nav class="menu-grid">

            <a class="menu-tarjeta" href="/admin/usuarios/index.php">
                <div class="menu-tarjeta-icono">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Gesti&oacute;n de Usuarios</h3>
                    <p>Crear, editar y eliminar colaboradores</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/admin/sucursales/index.php">
                <div class="menu-tarjeta-icono azul">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Gesti&oacute;n de Sucursales</h3>
                    <p>Ver y administrar las 4 tiendas GLEE</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/admin/asistencia/index.php">
                <div class="menu-tarjeta-icono verde">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Registros de Asistencia</h3>
                    <p>Ver todas las marcas de entrada y salida</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/admin/reportes/index.php">
                <div class="menu-tarjeta-icono morado">
                    <i class="fa-solid fa-chart-bar"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Reportes</h3>
                    <p>Resumen mensual por colaborador</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/admin/horarios/index.php">
                <div class="menu-tarjeta-icono" style="background:#16a085">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Horarios</h3>
                    <p>Asignar turnos y horarios por colaborador</p>
                </div>
            </a>

            <a class="menu-tarjeta" href="/admin/incidencias/index.php" style="position:relative;">
                <?php if ($incidenciasPendientes > 0): ?>
                    <span style="position:absolute; top:-8px; right:-8px; background:#e74c3c; color:white; font-size:0.85rem; font-weight:bold; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:50%; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
                        <?= $incidenciasPendientes ?>
                    </span>
                <?php endif; ?>
                <div class="menu-tarjeta-icono" style="background:#e67e22">
                    <i class="fa-solid fa-file-circle-exclamation"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Incidencias</h3>
                    <p>Aprobar permisos y justificaciones del personal</p>
                </div>
            </a>

            <!-- NUEVO BOTÓN: REGISTRO BIOMÉTRICO -->
            <a class="menu-tarjeta" href="/admin/registro_facial.php">
                <div class="menu-tarjeta-icono" style="background:#8e44ad">
                    <i class="fa-solid fa-user-lock"></i>
                </div>
                <div class="menu-tarjeta-texto">
                    <h3>Registro Biométrico</h3>
                    <p>Enrolar rostros para reconocimiento facial</p>
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
