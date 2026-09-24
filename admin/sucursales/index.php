<?php
/**
 * admin/sucursales/index.php — Listado de sucursales con opción de editar.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

$pdo = conectarBD();

$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$sucursales = $pdo->query('
    SELECT s.id, s.nombre, s.departamento, s.latitud, s.longitud,
           s.radio_geocerca_metros, s.activo,
           COUNT(u.id) AS total_usuarios
      FROM sucursales s
      LEFT JOIN usuarios u ON u.sucursal_id = s.id AND u.activo = 1
     GROUP BY s.id
     ORDER BY s.id ASC
')->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sucursales — GLEE</title>
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

        <a class="volver" href="/admin/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>

        <?php if ($flashMsg !== ''): ?>
            <div class="alerta alerta-<?= htmlspecialchars($flashTipo) ?>">
                <i class="fa-solid fa-circle-info"></i>
                <?= htmlspecialchars($flashMsg) ?>
            </div>
        <?php endif; ?>

        <div class="seccion-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2><i class="fa-solid fa-store"></i> Sucursales GLEE</h2>
            <a href="crear.php" class="btn btn-primario"><i class="fa-solid fa-plus"></i> Nueva Sucursal</a>
        </div>

        <div class="tarjeta">
            <div class="tabla-contenedor">
                <div class="table-responsive"><table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sucursal</th>
                            <th>Departamento</th>
                            <th>Geocerca</th>
                            <th>Colaboradores activos</th>
                            <th>Coordenadas GPS</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sucursales as $s): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><strong><?= htmlspecialchars($s['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($s['departamento']) ?></td>
                            <td><?= $s['radio_geocerca_metros'] ?> m</td>
                            <td style="text-align:center"><?= $s['total_usuarios'] ?></td>
                            <td style="font-size:.82rem;color:var(--gris)">
                                <?= $s['latitud'] ?>, <?= $s['longitud'] ?>
                            </td>
                            <td>
                                <?php if ($s['activo']): ?>
                                    <span class="badge" style="background:#d4f4e0;color:#155724">Activa</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f8d7da;color:#721c24">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:0.4rem;">
                                    <a href="editar.php?id=<?= $s['id'] ?>" class="btn btn-acento btn-sm" style="text-align:center;">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </a>
                                    
                                    <form method="POST" action="eliminar.php" style="margin:0;" onsubmit="return confirm('¿Seguro que deseas <?= $s['activo'] ? 'desactivar' : 'activar' ?> esta sucursal?');">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <?php if ($s['activo']): ?>
                                            <button type="submit" class="btn btn-peligro btn-sm" style="width:100%;"><i class="fa-solid fa-ban"></i> Desactivar</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primario btn-sm" style="background:#1d6f42; border-color:#1d6f42; width:100%;"><i class="fa-solid fa-check"></i> Activar</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>

        </div>

    </main>
</body>
</html>
