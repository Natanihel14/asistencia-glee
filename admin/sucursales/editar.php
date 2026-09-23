<?php
/**
 * admin/sucursales/editar.php — Editar datos de una sucursal.
 * Permite ajustar nombre, coordenadas GPS, radio de geocerca y estado.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

$pdo = conectarBD();

$id = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . APP_URL . '/admin/sucursales/index.php');
    exit;
}

$stmtS = $pdo->prepare('SELECT * FROM sucursales WHERE id = ? LIMIT 1');
$stmtS->execute([$id]);
$sucursal = $stmtS->fetch();

if (!$sucursal) {
    $_SESSION['flash_msg']  = 'Sucursal no encontrada.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/sucursales/index.php');
    exit;
}

$errores = [];

// Inicializar $d con los valores actuales de la BD
$d = [
    'nombre'                => $sucursal['nombre'],
    'departamento'          => $sucursal['departamento'],
    'latitud'               => $sucursal['latitud'],
    'longitud'              => $sucursal['longitud'],
    'radio_geocerca_metros' => $sucursal['radio_geocerca_metros'],
    'activo'                => $sucursal['activo'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['nombre']                = trim($_POST['nombre']                ?? '');
    $d['departamento']          = trim($_POST['departamento']          ?? '');
    $d['latitud']               = trim($_POST['latitud']               ?? '');
    $d['longitud']              = trim($_POST['longitud']              ?? '');
    $d['radio_geocerca_metros'] = trim($_POST['radio_geocerca_metros'] ?? '');
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
            UPDATE sucursales
               SET nombre = ?, departamento = ?, latitud = ?, longitud = ?,
                   radio_geocerca_metros = ?, activo = ?
             WHERE id = ?
        ');
        $stmt->execute([
            $d['nombre'],
            $d['departamento'],
            $lat,
            $lon,
            $radio,
            $d['activo'],
            $id,
        ]);

        $_SESSION['flash_msg']  = 'Sucursal "' . $d['nombre'] . '" actualizada correctamente.';
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
    <title>Editar Sucursal — GLEE</title>
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
            <h2><i class="fa-solid fa-store"></i> Editar Sucursal</h2>
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

            <form method="POST" action="editar.php?id=<?= $id ?>" novalidate>
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="form-grupo">
                    <label for="nombre">Nombre de la sucursal *</label>
                    <input type="text" id="nombre" name="nombre"
                           value="<?= htmlspecialchars($d['nombre']) ?>"
                           maxlength="100" required>
                </div>

                <div class="form-grupo">
                    <label for="departamento">Departamento *</label>
                    <select id="departamento" name="departamento" required>
                        <?php foreach (['Jalapa', 'Guatemala'] as $dep): ?>
                            <option value="<?= $dep ?>"
                                <?= $d['departamento'] === $dep ? 'selected' : '' ?>>
                                <?= $dep ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr style="margin:1.25rem 0;border:none;border-top:1px solid var(--borde)">
                <p style="font-size:.85rem;color:var(--gris);margin-bottom:1rem">
                    <i class="fa-solid fa-location-dot"></i>
                    <strong>Coordenadas GPS</strong> — Obtener desde Google Maps:
                    clic derecho sobre la tienda &rarr; copiar las coordenadas.
                </p>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:.75rem">
                    <div class="form-grupo" style="margin:0">
                        <label for="latitud">Latitud *</label>
                        <input type="text" id="latitud" name="latitud"
                               value="<?= htmlspecialchars($d['latitud']) ?>"
                               placeholder="14.53540000" required>
                    </div>
                    <div class="form-grupo" style="margin:0">
                        <label for="longitud">Longitud *</label>
                        <input type="text" id="longitud" name="longitud"
                               value="<?= htmlspecialchars($d['longitud']) ?>"
                               placeholder="-89.99290000" required>
                    </div>
                </div>

                <div class="form-grupo" style="margin-top:1rem">
                    <label for="radio_geocerca_metros">
                        Radio de geocerca (metros) *
                        <small style="color:var(--gris)"> — mín. 10 m, máx. 500 m</small>
                    </label>
                    <input type="number" id="radio_geocerca_metros" name="radio_geocerca_metros"
                           value="<?= htmlspecialchars($d['radio_geocerca_metros']) ?>"
                           min="10" max="500" required>
                    <p style="font-size:.78rem;color:var(--gris);margin-top:.3rem">
                        <i class="fa-solid fa-circle-info"></i>
                        El GPS en celulares tiene una precisión de &plusmn;5&ndash;15 m.
                        Se recomienda un m&iacute;nimo de 50 m para evitar falsos rechazos.
                    </p>
                </div>

                <div class="form-grupo">
                    <label for="activo">Estado</label>
                    <select id="activo" name="activo">
                        <option value="1" <?= (int)$d['activo'] === 1 ? 'selected' : '' ?>>Activa</option>
                        <option value="0" <?= (int)$d['activo'] === 0 ? 'selected' : '' ?>>Inactiva</option>
                    </select>
                </div>

                <div style="display:flex;gap:.75rem;margin-top:1.25rem">
                    <button type="submit" class="btn btn-primario">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                    <a href="index.php" class="btn btn-gris">Cancelar</a>
                </div>

            </form>
        </div>

    </main>
</body>
</html>
