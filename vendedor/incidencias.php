<?php
/**
 * vendedor/incidencias.php — El colaborador solicita permisos o justificaciones.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requerirRol(['vendedor', 'bodega']);

$pdo       = conectarBD();
$usuarioId = (int)$_SESSION['usuario_id'];

$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$tipos = [
    'permiso'              => 'Permiso de ausencia',
    'retardo_justificado'  => 'Justificación de retardo',
    'falta_justificada'    => 'Justificación de falta',
    'salida_anticipada'    => 'Salida anticipada',
];

$errores = [];
$d = ['fecha' => date('Y-m-d'), 'tipo' => 'retardo_justificado', 'motivo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['fecha']  = $_POST['fecha']  ?? '';
    $d['tipo']   = $_POST['tipo']   ?? '';
    $d['motivo'] = trim($_POST['motivo'] ?? '');

    if ($d['fecha'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['fecha'])) {
        $errores[] = 'Selecciona una fecha válida.';
    }
    if (!array_key_exists($d['tipo'], $tipos)) {
        $errores[] = 'Selecciona un tipo válido.';
    }
    if (strlen($d['motivo']) < 10) {
        $errores[] = 'El motivo debe tener al menos 10 caracteres.';
    }

    if (empty($errores)) {
        $stmt = $pdo->prepare('
            INSERT INTO incidencias (usuario_id, fecha, tipo, motivo)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$usuarioId, $d['fecha'], $d['tipo'], $d['motivo']]);

        $_SESSION['flash_msg']  = 'Solicitud enviada correctamente. El administrador la revisará pronto.';
        $_SESSION['flash_tipo'] = 'exito';
        header('Location: ' . APP_URL . '/vendedor/incidencias.php');
        exit;
    }
}

// Historial de incidencias del colaborador
$incidencias = $pdo->prepare('
    SELECT id, fecha, tipo, motivo, estado, respuesta, created_at
      FROM incidencias
     WHERE usuario_id = ?
     ORDER BY created_at DESC
     LIMIT 50
');
$incidencias->execute([$usuarioId]);
$lista = $incidencias->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);

$badgeEstado = [
    'pendiente' => ['bg' => '#fff3cd', 'color' => '#7d5a00', 'icono' => 'fa-clock'],
    'aprobada'  => ['bg' => '#d4f4e0', 'color' => '#155724', 'icono' => 'fa-circle-check'],
    'rechazada' => ['bg' => '#f8d7da', 'color' => '#721c24', 'icono' => 'fa-circle-xmark'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidencias — GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>.select2-container { width: 100% !important; box-sizing: border-box; } .select2-container--default .select2-selection--single { height: 48px; border-radius: 8px; border: 1px solid var(--borde); display: flex; align-items: center; font-size: 1rem; box-sizing: border-box; } .select2-container--default .select2-selection--single .select2-selection__arrow { height: 46px; }</style><link href='https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' rel='stylesheet' /><script src='https://code.jquery.com/jquery-3.7.1.min.js'></script><script src='https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js'></script></head>
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

        <a class="volver" href="/vendedor/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>

        <?php if ($flashMsg !== ''): ?>
            <div class="alerta alerta-<?= htmlspecialchars($flashTipo) ?>">
                <i class="fa-solid fa-circle-info"></i>
                <?= htmlspecialchars($flashMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para nueva solicitud -->
        <div class="seccion-header">
            <h2><i class="fa-solid fa-file-pen"></i> Nueva Solicitud</h2>
        </div>

        <div class="tarjeta" style="max-width:560px;margin-bottom:2rem">

            <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <strong><i class="fa-solid fa-circle-exclamation"></i> Corrige los errores:</strong>
                    <ul style="margin:.5rem 0 0 1.2rem">
                        <?php foreach ($errores as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:.75rem">
                    <div class="form-grupo" style="margin:0">
                        <label for="fecha">Fecha del evento *</label>
                        <input type="date" id="fecha" name="fecha"
                               value="<?= htmlspecialchars($d['fecha']) ?>"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-grupo" style="margin:0">
                        <label for="tipo">Tipo de solicitud *</label>
                        <select id="tipo" name="tipo" required>
                            <?php foreach ($tipos as $val => $etiq): ?>
                                <option value="<?= $val ?>"
                                    <?= $d['tipo'] === $val ? 'selected' : '' ?>>
                                    <?= $etiq ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grupo" style="margin-top:1rem">
                    <label for="motivo">
                        Motivo o justificación *
                        <small style="color:var(--gris)">(mínimo 10 caracteres)</small>
                    </label>
                    <textarea id="motivo" name="motivo" rows="4" style="resize: none; width: 100%; box-sizing: border-box;"
                              placeholder="Describe brevemente el motivo de tu solicitud..."
                              required><?= htmlspecialchars($d['motivo']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primario">
                    <i class="fa-solid fa-paper-plane"></i> Enviar Solicitud
                </button>

            </form>
        </div>

        <!-- Historial -->
        <div class="seccion-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Mis Solicitudes</h2>
        </div>

        <div class="tarjeta">
            <?php if (empty($lista)): ?>
                <p style="color:var(--gris);text-align:center;padding:2rem 0">
                    No has enviado solicitudes aún.
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Respuesta del admin</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lista as $inc): ?>
                            <?php $be = $badgeEstado[$inc['estado']] ?? $badgeEstado['pendiente']; ?>
                            <tr>
                                <td style="white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($inc['fecha'])) ?>
                                </td>
                                <td><?= $tipos[$inc['tipo']] ?? $inc['tipo'] ?></td>
                                <td style="max-width:220px;font-size:.88rem">
                                    <?= htmlspecialchars($inc['motivo']) ?>
                                </td>
                                <td>
                                    <span class="badge"
                                          style="background:<?= $be['bg'] ?>;color:<?= $be['color'] ?>">
                                        <i class="fa-solid <?= $be['icono'] ?>"></i>
                                        <?= ucfirst($inc['estado']) ?>
                                    </span>
                                </td>
                                <td style="font-size:.88rem;color:var(--gris)">
                                    <?= $inc['respuesta']
                                        ? htmlspecialchars($inc['respuesta'])
                                        : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
<script>$(document).ready(function() { $('#tipo').select2({ minimumResultsForSearch: Infinity, width: '100%' }); });</script></body>
</html>


