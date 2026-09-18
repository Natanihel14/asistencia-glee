<?php
/**
 * admin/incidencias/index.php — Listado de incidencias para aprobar o rechazar.
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
if ($filtroEstado !== 'todos') {
    $sql    .= ' WHERE i.estado = ?';
    $params[] = $filtroEstado;
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 200';

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
    <title>Incidencias — GLEE</title>
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
                <a href="?estado=<?= $val ?>" class="tab<?= $activo ?>">
                    <?= $etiq ?>
                    <?php if ($cnt > 0): ?>
                        <span style="font-weight:400;color:inherit">(<?= $cnt ?>)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
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
                                        <?= htmlspecialchars($inc['sucursal_nombre'] ?? '—') ?>
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
                                    <span class="badge"
                                          style="background:<?= $be['bg'] ?>;color:<?= $be['color'] ?>">
                                        <?= ucfirst($inc['estado']) ?>
                                    </span>
                                    <?php if ($inc['estado'] !== 'pendiente' && $inc['admin_nombre']): ?>
                                        <br><small style="color:var(--gris);font-size:.78rem">
                                            por <?= htmlspecialchars($inc['admin_nombre']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($inc['estado'] === 'pendiente'): ?>
                                        <!-- Botón Aprobar -->
                                        <form method="POST" action="responder.php"
                                              style="display:inline"
                                              onsubmit="return prepararRespuesta(this, 'aprobada')">
                                            <input type="hidden" name="id"     value="<?= $inc['id'] ?>">
                                            <input type="hidden" name="accion" value="aprobada">
                                            <input type="hidden" name="respuesta" class="campo-respuesta" value="">
                                            <button type="submit" class="btn btn-exito btn-sm">
                                                <i class="fa-solid fa-check"></i> Aprobar
                                            </button>
                                        </form>
                                        <!-- Botón Rechazar -->
                                        <form method="POST" action="responder.php"
                                              style="display:inline;margin-left:.35rem"
                                              onsubmit="return prepararRespuesta(this, 'rechazada')">
                                            <input type="hidden" name="id"     value="<?= $inc['id'] ?>">
                                            <input type="hidden" name="accion" value="rechazada">
                                            <input type="hidden" name="respuesta" class="campo-respuesta" value="">
                                            <button type="submit" class="btn btn-peligro btn-sm">
                                                <i class="fa-solid fa-xmark"></i> Rechazar
                                            </button>
                                        </form>
                                    <?php elseif ($inc['respuesta']): ?>
                                        <span style="font-size:.82rem;color:var(--gris)">
                                            <?= htmlspecialchars($inc['respuesta']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--gris)">—</span>
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

    <!-- Modal para Respuesta -->
    <div id="modal-respuesta" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center; z-index:9999; padding:1rem;">
        <div style="background:#fff; padding:1.5rem; border-radius:12px; width:100%; max-width:380px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <h3 id="modal-titulo" style="margin-top:0; color:var(--primario); margin-bottom:1rem;"><i class="fa-solid fa-comment-dots"></i> Responder Solicitud</h3>
            <div class="form-grupo" style="margin-bottom:1.5rem;">
                <label for="modal-input">Observación (Opcional):</label>
                <input type="text" id="modal-input" placeholder="Ej. Todo en orden..." style="width:100%; box-sizing:border-box;">
            </div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                <button type="button" class="btn btn-gris" onclick="cerrarModal()">Cancelar</button>
                <button type="button" class="btn btn-primario" id="modal-btn-confirmar">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
    function prepararRespuesta(forma, accion) {
        const etiqueta = accion === 'aprobada' ? 'Aprobar' : 'Rechazar';
        document.getElementById('modal-titulo').innerHTML = '<i class="fa-solid fa-comment-dots"></i> ' + etiqueta + ' Solicitud';
        document.getElementById('modal-input').value = '';
        
        document.getElementById('modal-btn-confirmar').onclick = function() {
            forma.querySelector('.campo-respuesta').value = document.getElementById('modal-input').value;
            forma.submit();
        };
        
        document.getElementById('modal-respuesta').style.display = 'flex';
        setTimeout(() => document.getElementById('modal-input').focus(), 100);
        return false; // previene el envo automtico
    }

    function cerrarModal() {
        document.getElementById('modal-respuesta').style.display = 'none';
    }
    </script>

</body>
</html>
