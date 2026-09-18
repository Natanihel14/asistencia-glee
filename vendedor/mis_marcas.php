<?php
/**
 * vendedor/mis_marcas.php — Historial de asistencia del colaborador en sesión.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requerirRol(['vendedor', 'bodega']);

$pdo      = conectarBD();
$usuarioId = (int)$_SESSION['usuario_id'];

// Últimas 60 marcas del colaborador
$stmt = $pdo->prepare('
    SELECT ma.tipo, ma.fecha_hora, ma.dentro_geocerca,
           s.nombre AS sucursal_nombre
      FROM marcas_asistencia ma
      JOIN sucursales s ON ma.sucursal_id = s.id
     WHERE ma.usuario_id = ?
     ORDER BY ma.fecha_hora DESC
     LIMIT 60
');
$stmt->execute([$usuarioId]);
$marcas = $stmt->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Marcas — GLEE</title>
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

        <a class="volver" href="/vendedor/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>

        <div class="seccion-header">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> Mis Marcas de Asistencia</h2>
        </div>

        <div class="tarjeta">
            <?php if (empty($marcas)): ?>
                <p style="color:var(--gris);text-align:center;padding:2.5rem 0">
                    <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                    Aún no tienes marcas de asistencia registradas.
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <div class="table-responsive"><table>
                        <thead>
                            <tr>
                            <th>Fecha y Hora</th>
                            <th>Tipo</th>
                            <th>Sucursal</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($marcas as $m): ?>
                            <tr>
                                <td style="white-space:nowrap">
                                    <?= date('d/m/Y H:i:s', strtotime($m['fecha_hora'])) ?>
                                </td>
                                <td>
                                    <?php
                                    $badgesMarca = [
                                        'entrada'        => ['bg'=>'#d4f4e0','color'=>'#155724','icono'=>'fa-arrow-right-to-bracket',  'txt'=>'Entrada'],
                                        'salida_comida'  => ['bg'=>'#fff3cd','color'=>'#7d5a00','icono'=>'fa-utensils',               'txt'=>'Salida a comer'],
                                        'regreso_comida' => ['bg'=>'#d0eaf8','color'=>'#0b4c6e','icono'=>'fa-rotate-left',            'txt'=>'Regreso de comer'],
                                        'salida'         => ['bg'=>'#fde8d8','color'=>'#7d3000','icono'=>'fa-arrow-right-from-bracket','txt'=>'Salida'],
                                    ];
                                    $bm = $badgesMarca[$m['tipo']] ?? ['bg'=>'#eee','color'=>'#555','icono'=>'fa-circle','txt'=>ucfirst($m['tipo'])];
                                    ?>
                                    <span class="badge" style="background:<?= $bm['bg'] ?>;color:<?= $bm['color'] ?>">
                                        <i class="fa-solid <?= $bm['icono'] ?>"></i> <?= $bm['txt'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($m['sucursal_nombre']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
                <p style="font-size:.82rem;color:var(--gris);margin-top:.75rem">
                    Últimos <?= count($marcas) ?> registros.
                </p>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>
