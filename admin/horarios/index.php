<?php
/**
 * admin/horarios/index.php — Lista de colaboradores con estado de horario asignado.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

// Colaboradores (vendedor y bodega) con conteo de días con horario activo
$colaboradores = $pdo->query('
    SELECT u.id, u.nombre_completo, u.rol, u.tipo_jornada, u.activo,
           COALESCE(s.nombre, "—") AS sucursal_nombre,
           COUNT(h.id)             AS dias_asignados
      FROM usuarios u
      LEFT JOIN sucursales s ON u.sucursal_id = s.id
      LEFT JOIN horarios   h ON h.usuario_id = u.id AND h.activo = 1
     WHERE u.rol IN ("vendedor", "bodega")
     GROUP BY u.id
     ORDER BY u.activo DESC, u.nombre_completo ASC
')->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios — GLEE</title>
    <link rel="stylesheet" href="/asistencia-glee/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        <a class="volver" href="/asistencia-glee/admin/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>

        <?php if ($flashMsg !== ''): ?>
            <div class="alerta alerta-<?= htmlspecialchars($flashTipo) ?>">
                <i class="fa-solid fa-circle-info"></i>
                <?= htmlspecialchars($flashMsg) ?>
            </div>
        <?php endif; ?>

        <div class="seccion-header">
            <h2><i class="fa-solid fa-calendar-days"></i> Gestión de Horarios</h2>
        </div>

        <div class="tarjeta">
            <?php if (empty($colaboradores)): ?>
                <p style="color:var(--gris);text-align:center;padding:2rem 0">
                    No hay colaboradores registrados.
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Rol</th>
                                <th>Jornada</th>
                                <th>Sucursal</th>
                                <th>Días con horario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($colaboradores as $c): ?>
                            <tr <?= !$c['activo'] ? 'style="opacity:.55"' : '' ?>>
                                <td>
                                    <?= htmlspecialchars($c['nombre_completo']) ?>
                                    <?php if (!$c['activo']): ?>
                                        <small style="color:var(--gris)"> (inactivo)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $c['rol'] ?>">
                                        <?= ucfirst($c['rol']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c['tipo_jornada'] === 'media'): ?>
                                        <span class="badge" style="background:#e8d5f5;color:#5a1a8a">Media</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#dce8ff;color:#1a3a8a">Completa</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($c['sucursal_nombre']) ?></td>
                                <td style="text-align:center">
                                    <?php
                                    $total = (int)$c['dias_asignados'];
                                    if ($total === 0):
                                    ?>
                                        <span style="color:var(--peligro);font-weight:600">Sin horario</span>
                                    <?php elseif ($total < 5): ?>
                                        <span style="color:#7d5a00;font-weight:600"><?= $total ?> / 7 días</span>
                                    <?php else: ?>
                                        <span style="color:var(--exito);font-weight:600"><?= $total ?> / 7 días</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="gestionar.php?usuario_id=<?= $c['id'] ?>"
                                       class="btn btn-acento btn-sm">
                                        <i class="fa-solid fa-clock"></i> Gestionar horario
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>
