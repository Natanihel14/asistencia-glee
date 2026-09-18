<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

requerirRol(['vendedor', 'bodega']);

$mensaje = $_SESSION['flash_msg'] ?? 'Marcaje completado exitosamente.';
$tipo = $_SESSION['flash_tipo'] ?? 'exito';

// Limpiar el mensaje flash
unset($_SESSION['flash_msg']);
unset($_SESSION['flash_tipo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcaje Exitoso - GLEE</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .exito-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 50vh;
            padding: 2rem;
            margin-top: 2rem;
        }
        .exito-icono {
            font-size: 5rem;
            color: #27ae60;
            margin-bottom: 1rem;
            animation: popIn 0.5s ease-out;
        }
        .exito-mensaje {
            font-size: 1.25rem;
            color: var(--texto);
            margin-bottom: 2rem;
            line-height: 1.5;
        }
        .btn-grande {
            display: inline-block;
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 1rem;
            transition: all 0.3s;
            box-sizing: border-box;
            cursor: pointer;
        }
        .btn-volver {
            background-color: var(--primario);
            color: white;
            border: none;
        }
        .btn-salir {
            background-color: transparent;
            color: var(--peligro);
            border: 2px solid var(--peligro);
        }
        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            80% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-marca">GLEE</div>
        <div class="topbar-usuario">
            <span><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Vendedor') ?></span>
        </div>
    </header>

    <main class="contenedor">
        <div class="tarjeta exito-container">
            <?php if($tipo === 'exito'): ?>
                <i class="fa-solid fa-circle-check exito-icono"></i>
                <h2>¡Completado!</h2>
            <?php else: ?>
                <i class="fa-solid fa-triangle-exclamation exito-icono" style="color: #e67e22;"></i>
                <h2>¡Atención!</h2>
            <?php endif; ?>
            
            <p class="exito-mensaje"><?= htmlspecialchars($mensaje) ?></p>

            <a href="/vendedor/dashboard.php" class="btn-grande btn-volver">
                <i class="fa-solid fa-arrow-left"></i> Volver a mi panel
            </a>
            
            <a href="/logout.php" class="btn-grande btn-salir" style="border:none; margin-top: 0.5rem;">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión
            </a>
        </div>
    </main>
</body>
</html>
