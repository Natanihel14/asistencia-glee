<?php
/**
 * admin/asistencia/index.php — Todos los registros de asistencia (todas las sucursales).
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

// Filtro opcional por fecha
$fechaFiltro = trim($_GET['fecha'] ?? '');

$sql = '
    SELECT ma.id, ma.tipo, ma.fecha_hora, ma.dentro_geocerca,
           u.nombre_completo, u.rol,
           s.nombre AS sucursal_nombre
      FROM marcas_asistencia ma
      JOIN usuarios   u ON ma.usuario_id   = u.id
      JOIN sucursales s ON ma.sucursal_id  = s.id
';

$params = [];
if ($fechaFiltro !== '') {
    $sql    .= ' WHERE DATE(ma.fecha_hora) = ?';
    $params[] = $fechaFiltro;
}

$sql .= ' ORDER BY ma.fecha_hora DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$marcas = $stmt->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Asistencia — GLEE</title>
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

        <div class="seccion-header">
            <h2><i class="fa-solid fa-clipboard-list"></i> Registros de Asistencia</h2>
        </div>

        <!-- Filtro por fecha -->
        <div class="tarjeta" style="margin-bottom:1rem;padding:1rem 1.25rem">
            <form method="GET" action="" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
                <div class="form-grupo" style="margin:0;flex:1;min-width:160px">
                    <label for="fecha">Filtrar por fecha</label>
                    <input type="date" id="fecha" name="fecha"
                           value="<?= htmlspecialchars($fechaFiltro) ?>">
                </div>
                <button type="submit" class="btn btn-primario btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                </button>
                <?php if ($fechaFiltro !== ''): ?>
                    <a href="?" class="btn btn-gris btn-sm">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="tarjeta">
            <?php if (empty($marcas)): ?>
                <p style="color:var(--gris);text-align:center;padding:2rem 0">
                    <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                    No hay registros de asistencia<?= $fechaFiltro !== '' ? ' para esa fecha' : ' aún' ?>.
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <table>
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Sucursal</th>
                                <th>Tipo</th>
                                <th>Fecha y hora</th>
                                <th>Geocerca</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($marcas as $m): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($m['nombre_completo']) ?>
                                    <br><small class="badge badge-<?= $m['rol'] ?>"><?= ucfirst($m['rol']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($m['sucursal_nombre']) ?></td>
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
                                <td style="white-space:nowrap">
                                    <?= date('d/m/Y H:i', strtotime($m['fecha_hora'])) ?>
                                </td>
                                <td>
                                    <?php if ($m['dentro_geocerca'] === null): ?>
                                        <span style="color:var(--gris)">—</span>
                                    <?php elseif ($m['dentro_geocerca']): ?>
                                        <span style="color:var(--exito)"><i class="fa-solid fa-check"></i> Dentro</span>
                                    <?php else: ?>
                                        <span style="color:var(--peligro)"><i class="fa-solid fa-xmark"></i> Fuera</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="font-size:.82rem;color:var(--gris);margin-top:.75rem">
                    Mostrando los últimos <?= count($marcas) ?> registros.
                </p>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>
