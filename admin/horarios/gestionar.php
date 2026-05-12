<?php
/**
 * admin/horarios/gestionar.php — Editar / crear los 7 días de horario de un colaborador.
 * Un solo formulario con 7 filas; usa UPSERT (INSERT … ON DUPLICATE KEY UPDATE).
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

$usuarioId = filter_input(INPUT_GET,  'usuario_id', FILTER_VALIDATE_INT)
           ?: filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);

if (!$usuarioId) {
    header('Location: ' . APP_URL . '/admin/horarios/index.php');
    exit;
}

// Verificar que el colaborador existe y es vendedor/bodega
$stmtU = $pdo->prepare('
    SELECT id, nombre_completo, tipo_jornada FROM usuarios
     WHERE id = ? AND rol IN ("vendedor","bodega") LIMIT 1
');
$stmtU->execute([$usuarioId]);
$colaborador = $stmtU->fetch();

if (!$colaborador) {
    $_SESSION['flash_msg']  = 'Colaborador no encontrado.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/horarios/index.php');
    exit;
}

$esCompleta = ($colaborador['tipo_jornada'] === 'completa');

// Nombres de los días (índice 0=Dom…6=Sáb, igual que date('w'))
$diasNombre = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

// Cargar horarios existentes del colaborador indexados por dia_semana
$stmtH = $pdo->prepare('SELECT * FROM horarios WHERE usuario_id = ?');
$stmtH->execute([$usuarioId]);
$horariosExistentes = [];
foreach ($stmtH->fetchAll() as $h) {
    $horariosExistentes[(int)$h['dia_semana']] = $h;
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar los 7 días
    // dias[] es array de índices de día que vienen activos (checkbox)
    $diasActivos = $_POST['dias'] ?? [];   // array de strings '0'..'6'

    $pdo->beginTransaction();
    try {
        for ($d = 0; $d <= 6; $d++) {
            $activo       = in_array((string)$d, $diasActivos, true) ? 1 : 0;
            $horaEntrada  = trim($_POST['hora_entrada'][$d]        ?? '');
            $horaSalida   = trim($_POST['hora_salida'][$d]         ?? '');
            $horaSalCom   = trim($_POST['hora_salida_comida'][$d]  ?? '');
            $horaRegCom   = trim($_POST['hora_regreso_comida'][$d] ?? '');

            // Validar solo los días activos
            if ($activo) {
                if (!preg_match('/^\d{2}:\d{2}$/', $horaEntrada)) {
                    $errores[] = $diasNombre[$d] . ': hora de entrada inválida.';
                }
                if (!preg_match('/^\d{2}:\d{2}$/', $horaSalida)) {
                    $errores[] = $diasNombre[$d] . ': hora de salida inválida.';
                }
                if ($esCompleta) {
                    if (!preg_match('/^\d{2}:\d{2}$/', $horaSalCom)) {
                        $errores[] = $diasNombre[$d] . ': hora de salida a comer inválida.';
                    }
                    if (!preg_match('/^\d{2}:\d{2}$/', $horaRegCom)) {
                        $errores[] = $diasNombre[$d] . ': hora de regreso de comer inválida.';
                    }
                }
            }
        }

        if (!empty($errores)) {
            $pdo->rollBack();
        } else {
            for ($d = 0; $d <= 6; $d++) {
                $activo      = in_array((string)$d, $diasActivos, true) ? 1 : 0;
                if ($activo) {
                    // Día trabajado: insertar o actualizar el horario
                    $horaEntrada = trim($_POST['hora_entrada'][$d]        ?? '') ?: null;
                    $horaSalida  = trim($_POST['hora_salida'][$d]         ?? '') ?: null;
                    $horaSalCom  = $esCompleta ? (trim($_POST['hora_salida_comida'][$d]  ?? '') ?: null) : null;
                    $horaRegCom  = $esCompleta ? (trim($_POST['hora_regreso_comida'][$d] ?? '') ?: null) : null;

                    $stmt = $pdo->prepare('
                        INSERT INTO horarios
                               (usuario_id, dia_semana, hora_entrada, hora_salida_comida,
                                hora_regreso_comida, hora_salida, activo)
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE
                               hora_entrada        = VALUES(hora_entrada),
                               hora_salida_comida  = VALUES(hora_salida_comida),
                               hora_regreso_comida = VALUES(hora_regreso_comida),
                               hora_salida         = VALUES(hora_salida),
                               activo              = 1
                    ');
                    $stmt->execute([$usuarioId, $d, $horaEntrada, $horaSalCom, $horaRegCom, $horaSalida]);
                } else {
                    // Día no trabajado: eliminar el horario si existía (no dejar fila con NULLs)
                    $stmtDel = $pdo->prepare('DELETE FROM horarios WHERE usuario_id = ? AND dia_semana = ?');
                    $stmtDel->execute([$usuarioId, $d]);
                }
            }

            $pdo->commit();
            $_SESSION['flash_msg']  = 'Horario de "' . $colaborador['nombre_completo'] . '" guardado correctamente.';
            $_SESSION['flash_tipo'] = 'exito';
            header('Location: ' . APP_URL . '/admin/horarios/index.php');
            exit;
        }
    } catch (\Exception $e) {
        $pdo->rollBack();
        $errores[] = 'Error al guardar. Intenta de nuevo.';
    }
}

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
$nombreColab = htmlspecialchars($colaborador['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horario: <?= $nombreColab ?> — GLEE</title>
    <link rel="stylesheet" href="/asistencia-glee/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .horario-tabla { width:100%; border-collapse:collapse; }
        .horario-tabla th,
        .horario-tabla td {
            padding:.6rem .75rem;
            border-bottom:1px solid var(--borde);
            vertical-align:middle;
        }
        .horario-tabla thead th {
            background:var(--fondo);
            font-size:.8rem;
            font-weight:600;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:var(--gris);
        }
        .horario-tabla td:first-child { font-weight:600; width:110px; }
        .horario-tabla input[type="time"] {
            padding:.4rem .5rem;
            border:1px solid var(--borde);
            border-radius:var(--radio-sm);
            font-size:.9rem;
            width:120px;
            color:var(--texto);
            background:#fff;
        }
        .horario-tabla input[type="time"]:disabled {
            background:var(--fondo);
            color:var(--gris);
            border-color:var(--borde);
            cursor:not-allowed;
        }
        .horario-tabla tr.dia-inactivo td:not(:first-child):not(.col-activo) {
            opacity:.4;
        }
        .toggle-dia { width:18px; height:18px; cursor:pointer; accent-color:var(--primario); }
        .col-activo { text-align:center; width:70px; }
    </style>
</head>
<body>

    <header class="topbar">
        <a class="topbar-marca" href="<?= urlDashboard() ?>">GLEE</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?></span>
            <a href="/asistencia-glee/logout.php" class="btn-salir">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <main class="contenedor">

        <a class="volver" href="index.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al listado
        </a>

        <div class="seccion-header">
            <h2><i class="fa-solid fa-clock"></i> Horario — <?= $nombreColab ?></h2>
        </div>

        <div class="tarjeta" style="margin-bottom:.75rem;padding:.75rem 1.25rem">
            <small style="color:var(--gris)">
                <i class="fa-solid fa-circle-info"></i>
                Jornada: <strong><?= $esCompleta ? 'Completa (4 marcas)' : 'Media (2 marcas)' ?></strong>.
                <?= $esCompleta ? 'Completa los 4 horarios por día activo.' : 'Solo se usan entrada y salida.' ?>
                Deja un día sin marcar (casilla vacía) si el colaborador no trabaja ese día.
            </small>
        </div>

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

        <div class="tarjeta" style="overflow-x:auto">
            <form method="POST" action="gestionar.php?usuario_id=<?= $usuarioId ?>">
                <input type="hidden" name="usuario_id" value="<?= $usuarioId ?>">

                <table class="horario-tabla">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th class="col-activo">Trabaja</th>
                            <th>Entrada</th>
                            <?php if ($esCompleta): ?>
                                <th>Salida a comer</th>
                                <th>Regreso de comer</th>
                            <?php endif; ?>
                            <th>Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    // Mostrar Lunes→Domingo (1..6, luego 0) para orden laboral natural
                    $ordenDias = [1, 2, 3, 4, 5, 6, 0];
                    foreach ($ordenDias as $d):
                        $h      = $horariosExistentes[$d] ?? [];
                        $activo = isset($h['activo']) ? (int)$h['activo'] : 0;

                        // Al procesar POST con errores, recuperar valores enviados
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $activo     = in_array((string)$d, ($_POST['dias'] ?? []), true) ? 1 : 0;
                            $hEntrada   = $_POST['hora_entrada'][$d]        ?? '';
                            $hSalCom    = $_POST['hora_salida_comida'][$d]  ?? '';
                            $hRegCom    = $_POST['hora_regreso_comida'][$d] ?? '';
                            $hSalida    = $_POST['hora_salida'][$d]         ?? '';
                        } else {
                            $hEntrada   = $h['hora_entrada']        ?? '';
                            $hSalCom    = $h['hora_salida_comida']  ?? '';
                            $hRegCom    = $h['hora_regreso_comida'] ?? '';
                            $hSalida    = $h['hora_salida']         ?? '';
                        }

                        // Formatear para input type="time" (HH:MM)
                        $fmt = fn($t) => $t ? substr($t, 0, 5) : '';
                        $rowClass = $activo ? '' : 'dia-inactivo';
                    ?>
                        <tr class="<?= $rowClass ?>" id="fila-<?= $d ?>">
                            <td><?= $diasNombre[$d] ?></td>
                            <td class="col-activo">
                                <input type="checkbox" class="toggle-dia" name="dias[]"
                                       value="<?= $d ?>"
                                       id="chk-<?= $d ?>"
                                       onchange="toggleDia(<?= $d ?>, this.checked)"
                                       <?= $activo ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <input type="time" name="hora_entrada[<?= $d ?>]"
                                       value="<?= htmlspecialchars($fmt($hEntrada)) ?>"
                                       <?= !$activo ? 'disabled' : '' ?>>
                            </td>
                            <?php if ($esCompleta): ?>
                                <td>
                                    <input type="time" name="hora_salida_comida[<?= $d ?>]"
                                           value="<?= htmlspecialchars($fmt($hSalCom)) ?>"
                                           <?= !$activo ? 'disabled' : '' ?>>
                                </td>
                                <td>
                                    <input type="time" name="hora_regreso_comida[<?= $d ?>]"
                                           value="<?= htmlspecialchars($fmt($hRegCom)) ?>"
                                           <?= !$activo ? 'disabled' : '' ?>>
                                </td>
                            <?php endif; ?>
                            <td>
                                <input type="time" name="hora_salida[<?= $d ?>]"
                                       value="<?= htmlspecialchars($fmt($hSalida)) ?>"
                                       <?= !$activo ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="display:flex;gap:.75rem;margin-top:1.5rem">
                    <button type="submit" class="btn btn-primario">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar horario
                    </button>
                    <a href="index.php" class="btn btn-gris">Cancelar</a>
                </div>
            </form>
        </div>

    </main>

    <script>
    function toggleDia(dia, activo) {
        const fila   = document.getElementById('fila-' + dia);
        const inputs = fila.querySelectorAll('input[type="time"]');
        fila.className = activo ? '' : 'dia-inactivo';
        inputs.forEach(function(inp) { inp.disabled = !activo; });
    }
    </script>

</body>
</html>
