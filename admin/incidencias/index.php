<?php
/**
 * admin/incidencias/index.php - Listado de incidencias para aprobar o rechazar.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

// Filtro por estado (por defecto: pendientes)
$filtroEstado = $_GET['estado'] ?? 'pendiente';
$estadosValidos = ['todos', 'pendiente', 'aprobada', 'rechazada'];
if (!in_array($filtroEstado, $estadosValidos, true)) {
    $filtroEstado = 'pendiente';
}

$usuarioFiltro = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT) ?: 0;

$sql    = '
    SELECT i.id, i.fecha, i.tipo, i.motivo, i.estado, i.respuesta, i.created_at,
           u.nombre_completo, u.rol,
           s.nombre AS sucursal_nombre,
           a.nombre_completo AS admin_nombre
      FROM incidencias i
      JOIN usuarios u ON i.usuario_id = u.id
      LEFT JOIN sucursales s ON u.sucursal_id = s.id
      LEFT JOIN usuarios a ON i.respondido_por = a.id
';
$params = [];
$whereClauses = [];

if ($filtroEstado !== 'todos') {
    $whereClauses[] = 'i.estado = ?';
    $params[] = $filtroEstado;
}

if ($usuarioFiltro > 0) {
    $whereClauses[] = 'i.usuario_id = ?';
    $params[] = $usuarioFiltro;
}

if (!empty($whereClauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
}

// Ordenar de más nuevo a más viejo por fecha de la incidencia, luego por fecha de creación
$sql .= ' ORDER BY i.fecha DESC, i.created_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$incidencias = $stmt->fetchAll();

// Conteo de pendientes para mostrar en pestañas
$stmtCnt = $pdo->query("SELECT estado, COUNT(*) AS cnt FROM incidencias GROUP BY estado");
$conteos  = [];
foreach ($stmtCnt->fetchAll() as $row) {
    $conteos[$row['estado']] = $row['cnt'];
}
$totalPendientes = $conteos['pendiente'] ?? 0;

$colaboradores = $pdo->query("
    SELECT id, nombre_completo
      FROM usuarios
     WHERE rol IN ('vendedor','bodega') AND activo = 1
     ORDER BY nombre_completo ASC
")->fetchAll();

$tipos = [
    'permiso'             => 'Permiso de ausencia',
    'retardo_justificado' => 'Justificación de retardo',
    'falta_justificada'   => 'Justificación de falta',
    'salida_anticipada'   => 'Salida anticipada',
];

$badgeEstado = [
    'pendiente' => ['bg' => '#fff3cd', 'color' => '#7d5a00'],
    'aprobada'  => ['bg' => '#d4f4e0', 'color' => '#155724'],
    'rechazada' => ['bg' => '#f8d7da', 'color' => '#721c24'],
];

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidencias - GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .tabs { display:flex; gap:.5rem; margin-bottom:1.25rem; flex-wrap:wrap; }
        .tab  { padding:.45rem 1.1rem; border-radius:20px; font-size:.88rem; font-weight:600;
                text-decoration:none; background:var(--blanco); color:var(--gris);
                border:1.5px solid var(--borde); transition:all .15s; }
        .tab:hover  { border-color:var(--acento); color:var(--acento); text-decoration:none; }
        .tab.activo { background:var(--primario); color:var(--blanco); border-color:var(--primario); }
        .pill { display:inline-block; background:#e74c3c; color:#fff;
                border-radius:10px; font-size:.72rem; padding:.05rem .45rem;
                margin-left:.35rem; font-weight:700; vertical-align:middle; }
    </style>
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

        <div class="seccion-header">
            <h2>
                <i class="fa-solid fa-file-circle-exclamation"></i>
                Incidencias
                <?php if ($totalPendientes > 0): ?>
                    <span class="pill"><?= $totalPendientes ?></span>
                <?php endif; ?>
            </h2>
        </div>

        <!-- Pestañas de filtro -->
        <div class="tabs">
            <?php
            $tabOpciones = [
                'pendiente' => 'Pendientes',
                'aprobada'  => 'Aprobadas',
                'rechazada' => 'Rechazadas',
                'todos'     => 'Todas',
            ];
            foreach ($tabOpciones as $val => $etiq):
                $activo = $filtroEstado === $val ? ' activo' : '';
                $cnt    = ($val !== 'todos') ? ($conteos[$val] ?? 0) : array_sum($conteos);
            ?>
                <a href="?estado=<?= $val ?>&usuario_id=<?= $usuarioFiltro ?>" class="tab<?= $activo ?>">
                    <?= $etiq ?>
                    <?php if ($cnt > 0): ?>
                        <span style="font-weight:400;color:inherit">(<?= $cnt ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Filtros secundarios -->
        <div class="tarjeta" style="margin-bottom:1rem;padding:1rem 1.25rem">
            <form method="GET" action="" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="estado" value="<?= htmlspecialchars($filtroEstado) ?>">
                <div class="form-grupo" style="margin:0;flex:1;min-width:200px">
                    <label for="f-usuario">Filtrar por colaborador</label>
                    <select id="f-usuario" name="usuario_id">
                        <option value="">-- Todos los colaboradores --</option>
                        <?php foreach ($colaboradores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $usuarioFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primario btn-sm" style="margin-bottom:2px">
                    <i class="fa-solid fa-filter"></i> Filtrar
                </button>
                <?php if ($usuarioFiltro > 0): ?>
                    <a href="?estado=<?= htmlspecialchars($filtroEstado) ?>" class="btn btn-gris btn-sm" style="margin-bottom:2px">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="tarjeta">
            <?php if (empty($incidencias)): ?>
                <p style="color:var(--gris);text-align:center;padding:2.5rem 0">
                    <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                    No hay incidencias <?= $filtroEstado !== 'todos' ? $filtroEstado . 's' : '' ?>.
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <div class="table-responsive"><table>
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidencias as $inc): ?>
                            <?php $be = $badgeEstado[$inc['estado']] ?? $badgeEstado['pendiente']; ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($inc['nombre_completo']) ?></strong>
                                    <br><small style="color:var(--gris)">
                                        <?= htmlspecialchars($inc['sucursal_nombre'] ?? '-') ?>
                                    </small>
                                </td>
                                <td style="white-space:nowrap">
                                    <?= date('d/m/Y', strtotime($inc['fecha'])) ?>
                                </td>
                                <td style="font-size:.88rem">
                                    <?= $tipos[$inc['tipo']] ?? $inc['tipo'] ?>
                                </td>
                                <td style="max-width:200px;font-size:.88rem">
                                    <?= htmlspecialchars($inc['motivo']) ?>
                                </td>
                                <td>
                                    <span style="
                                        display:inline-block;
                                        background:<?= $be['bg'] ?>;
                                        color:<?= $be['color'] ?>;
                                        padding:.2rem .6rem;
                                        border-radius:12px;
                                        font-size:.8rem;
                                        font-weight:600;
                                    ">
                                        <?= ucfirst($inc['estado']) ?>
                                    </span>
                                    <?php if ($inc['estado'] !== 'pendiente' && $inc['admin_nombre']): ?>
                                        <div style="font-size:.7rem;color:var(--gris);margin-top:.25rem">
                                            por <?= htmlspecialchars($inc['admin_nombre']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inc['estado'] === 'pendiente'): ?>
                                        <div style="display:flex;gap:.35rem">
                                            <!-- Aprobar -->
                                            <form method="POST" action="procesar.php" style="display:inline">
                                                <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                                                <input type="hidden" name="accion" value="aprobar">
                                                <button type="submit" class="btn btn-sm" style="background:#1d6f42;color:#fff;border:none;padding:.3rem .6rem" title="Aprobar" onclick="return confirm('¿Aprobar esta incidencia?');">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Rechazar -->
                                            <form method="POST" action="procesar.php" style="display:inline" onsubmit="
                                                let r = prompt('Escribe el motivo del rechazo (opcional):');
                                                if(r===null) return false;
                                                this.respuesta.value = r;
                                                return true;
                                            ">
                                                <input type="hidden" name="id" value="<?= $inc['id'] ?>">
                                                <input type="hidden" name="accion" value="rechazar">
                                                <input type="hidden" name="respuesta" value="">
                                                <button type="submit" class="btn btn-sm" style="background:#e74c3c;color:#fff;border:none;padding:.3rem .6rem" title="Rechazar">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($inc['respuesta']): ?>
                                            <div style="font-size:.8rem;color:var(--gris)">
                                                <?= htmlspecialchars($inc['respuesta']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--gris)">—</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
