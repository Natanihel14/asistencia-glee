<?php
/**
 * admin/sucursales/crear.php - Crear nueva sucursal.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

$pdo = conectarBD();

$errores = [];

$d = [
    'nombre'                => '',
    'departamento'          => '',
    'latitud'               => '',
    'longitud'              => '',
    'radio_geocerca_metros' => 50,
    'activo'                => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['nombre']                = trim($_POST['nombre']                ?? '');
    $d['departamento']          = trim($_POST['departamento']          ?? '');
    $d['latitud']               = trim($_POST['latitud']               ?? '');
    $d['longitud']              = trim($_POST['longitud']              ?? '');
    $d['radio_geocerca_metros'] = trim($_POST['radio_geocerca_metros'] ?? '50');
    $d['activo']                = (int)($_POST['activo']               ?? 1);

    // Validaciones
    if ($d['nombre'] === '') {
        $errores[] = 'El nombre de la sucursal es obligatorio.';
    }
    if ($d['departamento'] === '') {
        $errores[] = 'El departamento es obligatorio.';
    }

    $lat = filter_var($d['latitud'], FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lat < -90 || $lat > 90) {
        $errores[] = 'Latitud inválida. Debe ser un número entre -90 y 90.';
    }

    $lon = filter_var($d['longitud'], FILTER_VALIDATE_FLOAT);
    if ($lon === false || $lon < -180 || $lon > 180) {
        $errores[] = 'Longitud inválida. Debe ser un número entre -180 y 180.';
    }

    $radio = filter_var($d['radio_geocerca_metros'], FILTER_VALIDATE_INT);
    if ($radio === false || $radio < 10 || $radio > 500) {
        $errores[] = 'El radio de geocerca debe ser un número entero entre 10 y 500 metros.';
    }

    if (empty($errores)) {
        $stmt = $pdo->prepare('
            INSERT INTO sucursales (nombre, departamento, latitud, longitud, radio_geocerca_metros, activo)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $d['nombre'],
            $d['departamento'],
            $lat,
            $lon,
            $radio,
            $d['activo'],
        ]);

        $_SESSION['flash_msg']  = 'Sucursal "' . $d['nombre'] . '" creada correctamente.';
        $_SESSION['flash_tipo'] = 'exito';
        header('Location: ' . APP_URL . '/admin/sucursales/index.php');
        exit;
    }
}

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Sucursal - GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <header class="topbar">
        <a class="topbar-marca" href="<?= urlDashboard() ?>">GLEE</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?></span>
            <a href="/logout.php" class="btn-salir">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <main class="contenedor">
        <a class="volver" href="index.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al listado
        </a>

        <div class="seccion-header">
            <h2><i class="fa-solid fa-plus"></i> Crear Sucursal</h2>
        </div>

        <div class="tarjeta" style="max-width:560px">
            <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <strong><i class="fa-solid fa-circle-exclamation"></i> Corrige los siguientes errores:</strong>
                    <ul style="margin:.5rem 0 0 1.2rem">
                        <?php foreach ($errores as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="crear.php" novalidate>
                <div class="form-grupo">
                    <label for="nombre">Nombre de la Sucursal</label>
                    <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($d['nombre']) ?>" required>
                </div>
                <div class="form-grupo">
                    <label for="departamento">Departamento</label>
                    <input type="text" id="departamento" name="departamento" value="<?= htmlspecialchars($d['departamento']) ?>" required>
                </div>
                <div style="display:flex; gap:1rem;">
                    <div class="form-grupo" style="flex:1;">
                        <label for="latitud">Latitud GPS</label>
                        <input type="number" step="0.00000001" id="latitud" name="latitud" value="<?= htmlspecialchars($d['latitud']) ?>" required>
                    </div>
                    <div class="form-grupo" style="flex:1;">
                        <label for="longitud">Longitud GPS</label>
                        <input type="number" step="0.00000001" id="longitud" name="longitud" value="<?= htmlspecialchars($d['longitud']) ?>" required>
                    </div>
                </div>
                <div class="form-grupo">
                    <label for="radio_geocerca_metros">Radio de Geocerca (Metros)</label>
                    <input type="number" id="radio_geocerca_metros" name="radio_geocerca_metros" value="<?= htmlspecialchars($d['radio_geocerca_metros']) ?>" required>
                </div>
                <div class="form-grupo">
                    <label for="activo">Estado</label>
                    <select id="activo" name="activo">
                        <option value="1" <?= $d['activo'] === 1 ? 'selected' : '' ?>>Activa</option>
                        <option value="0" <?= $d['activo'] === 0 ? 'selected' : '' ?>>Inactiva</option>
                    </select>
                </div>
                
                <div style="margin-top:1.5rem; text-align:right;">
                    <button type="submit" class="btn btn-primario"><i class="fa-solid fa-save"></i> Guardar Sucursal</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
