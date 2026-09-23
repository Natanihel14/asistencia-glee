<?php
/**
 * admin/reportes/index.php — Reporte mensual de asistencia por colaborador.
 * Muestra marcas, varianza, geocerca e incidencias aprobadas del período.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

// ── Filtros GET ───────────────────────────────────────────────
$mesActual  = (int)date('n');
$anioActual = (int)date('Y');

$mes  = (isset($_GET['mes'])  && (int)$_GET['mes']  >= 1  && (int)$_GET['mes']  <= 12)
      ? (int)$_GET['mes'] : $mesActual;
$anio = (isset($_GET['anio']) && (int)$_GET['anio'] >= 2020 && (int)$_GET['anio'] <= 2099)
      ? (int)$_GET['anio'] : $anioActual;
$usuarioFiltro = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT) ?: 0;

// Colaboradores activos e inactivos para el selector (queremos ver reportes históricos de inactivos también)
$colaboradores = $pdo->query("
    SELECT id, nombre_completo
      FROM usuarios
     WHERE rol IN ('vendedor','bodega')
     ORDER BY activo DESC, nombre_completo ASC
")->fetchAll();

// ── Consulta principal ────────────────────────────────────────
$sql = "
    SELECT
        ma.id, ma.tipo, ma.fecha_hora, ma.dentro_geocerca, ma.minutos_variacion,
          u.id   AS usuario_id,
          u.nombre_completo,
          u.rol,
          u.tipo_jornada,
          s.nombre AS sucursal_nombre,
        EXISTS (
            SELECT 1 FROM incidencias i
             WHERE i.usuario_id = ma.usuario_id
               AND i.fecha      = DATE(ma.fecha_hora)
               AND i.estado     = 'aprobada'
        ) AS tiene_justificacion
      FROM marcas_asistencia ma
      JOIN usuarios   u ON ma.usuario_id  = u.id
      JOIN sucursales s ON ma.sucursal_id = s.id
     WHERE YEAR(ma.fecha_hora)  = ?
       AND MONTH(ma.fecha_hora) = ?
       AND u.rol IN ('vendedor','bodega')
";
$params = [$anio, $mes];

if ($usuarioFiltro) {
    $sql    .= ' AND ma.usuario_id = ?';
    $params[] = $usuarioFiltro;
}
$sql .= ' ORDER BY u.nombre_completo ASC, ma.fecha_hora ASC';

$stmtM = $pdo->prepare($sql);
$stmtM->execute($params);
$todasMarcas = $stmtM->fetchAll();

// Agrupar por colaborador en PHP
$porColaborador = [];
foreach ($todasMarcas as $m) {
    $uid = $m['usuario_id'];
    if (!isset($porColaborador[$uid])) {
        $porColaborador[$uid] = [
            'nombre'       => $m['nombre_completo'],
            'rol'          => $m['rol'],
            'tipo_jornada' => $m['tipo_jornada'],
            'marcas'       => [],
        ];
    }
    $porColaborador[$uid]['marcas'][] = $m;
}

$mesesNombre = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
    5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre',
];

$tiposBadge = [
    'entrada'        => ['bg'=>'#d4f4e0','color'=>'#155724','icono'=>'fa-arrow-right-to-bracket',  'txt'=>'Entrada'],
    'salida_comida'  => ['bg'=>'#fff3cd','color'=>'#7d5a00','icono'=>'fa-utensils',               'txt'=>'Salida a comer'],
    'regreso_comida' => ['bg'=>'#d0eaf8','color'=>'#0b4c6e','icono'=>'fa-rotate-left',            'txt'=>'Regreso de comer'],
    'salida'         => ['bg'=>'#fde8d8','color'=>'#7d3000','icono'=>'fa-arrow-right-from-bracket','txt'=>'Salida'],
];

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes — GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reporte-seccion { margin-bottom: 2.5rem; }
        .reporte-encabezado {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .75rem;
            padding-bottom: .5rem;
            border-bottom: 2px solid var(--primario);
        }
        .reporte-encabezado h3 { margin: 0; font-size: 1.1rem; color: var(--primario); }
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: .75rem;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--fondo);
            border-radius: var(--radio-sm);
        }
        .resumen-item { text-align: center; }
        .resumen-valor {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primario);
            display: block;
        }
        .resumen-valor.alerta { color: var(--peligro, #c0392b); }
        .resumen-label { font-size: .78rem; color: var(--gris); }
        .badge-varianza-ok  { background:#d4f4e0; color:#155724; padding:.2rem .55rem; border-radius:10px; font-size:.8rem; font-weight:600; white-space:nowrap; }
        .badge-varianza-mal { background:#fde8d8; color:#7d3000; padding:.2rem .55rem; border-radius:10px; font-size:.8rem; font-weight:600; white-space:nowrap; }
        .badge-justificada  { background:#e8d5f5; color:#5a1a8a; padding:.2rem .55rem; border-radius:10px; font-size:.78rem; font-weight:600; }
        .geo-ok    { color:#155724; font-size:.85rem; }
        .geo-fuera { color:#e67e22; font-size:.85rem; }
        .geo-nd    { color:var(--gris); font-size:.85rem; }
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

        <div class="seccion-header" style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
            <h2><i class="fa-solid fa-chart-bar"></i> Reporte Mensual de Asistencia</h2>
            <div style="display:flex; gap:0.5rem;">
                <a href="exportar_excel.php?mes=<?= $mes ?>&anio=<?= $anio ?>&usuario_id=<?= $usuarioFiltro ?>" class="btn btn-primario" style="background:#1d6f42; border-color:#1d6f42;">
                    <i class="fa-solid fa-file-excel"></i> Exportar a Excel
                </a>
            </div>
        </div>

        <!-- Filtros -->
        <div class="tarjeta" style="margin-bottom:1rem;padding:1rem 1.25rem">
            <form method="GET" action="" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">

                <div class="form-grupo" style="margin:0;min-width:130px">
                    <label for="f-mes">Mes</label>
                    <select id="f-mes" name="mes">
                        <?php foreach ($mesesNombre as $n => $etq): ?>
                            <option value="<?= $n ?>" <?= $mes === $n ? 'selected' : '' ?>>
                                <?= $etq ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grupo" style="margin:0;min-width:90px">
                    <label for="f-anio">Año</label>
                    <select id="f-anio" name="anio">
                        <?php for ($y = $anioActual - 1; $y <= $anioActual + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= $anio === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-grupo" style="margin:0;flex:1;min-width:180px">
                    <label for="f-usuario">Colaborador</label>
                    <select id="f-usuario" name="usuario_id">
                        <option value="0">Todos los colaboradores</option>
                        <?php foreach ($colaboradores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $usuarioFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_completo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primario btn-sm">
                    <i class="fa-solid fa-magnifying-glass"></i> Ver reporte
                </button>

            </form>
        </div>

        <?php if (empty($porColaborador)): ?>
            <div class="tarjeta" style="text-align:center;padding:3rem 1.5rem">
                <i class="fa-solid fa-inbox" style="font-size:2.5rem;color:var(--borde);display:block;margin-bottom:.75rem"></i>
                <p style="color:var(--gris)">
                    No hay marcas de asistencia para
                    <strong><?= $mesesNombre[$mes] ?> <?= $anio ?></strong>
                    <?= $usuarioFiltro ? 'de este colaborador' : '' ?>.
                </p>
            </div>
        <?php else: ?>

            <p style="font-size:.85rem;color:var(--gris);margin-bottom:1rem">
                <i class="fa-solid fa-calendar-days"></i>
                <?= $mesesNombre[$mes] ?> <?= $anio ?> &mdash;
                <?= count($porColaborador) ?> colaborador<?= count($porColaborador) !== 1 ? 'es' : '' ?>
            </p>

            <?php foreach ($porColaborador as $uid => $datos):

                // ── Calcular resumen del colaborador ──────────────────────
                $totalMarcas      = count($datos['marcas']);
                $diasTrabajados   = count(array_unique(array_map(
                    fn($m) => substr($m['fecha_hora'], 0, 10),
                    $datos['marcas']
                )));
            ?>

            <div class="reporte-seccion">
                <div class="tarjeta">

                    <div class="reporte-encabezado">
                        <i class="fa-solid fa-user" style="color:var(--primario)"></i>
                        <h3><?= htmlspecialchars($datos['nombre']) ?></h3>
                        <span class="badge badge-<?= $datos['rol'] ?>"><?= ucfirst($datos['rol']) ?></span>
                    </div>

                      <div class="tabla-contenedor">
                          <div class="table-responsive"><table>
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Entrada</th>
                                        <?php if ($datos['tipo_jornada'] === 'completa'): ?>
                                        <th>S. Comida</th>
                                        <th>R. Comida</th>
                                        <?php endif; ?>
                                        <th>Salida</th>
                                        <th>Balance (Bolsa de Horas)</th>
                                        <th>Incidencias</th>
                                    </tr>
                                </thead>
                              <tbody>
                              <?php 
                              // Agrupar por día para el resumen ejecutivo
                              $resumenDiario = [];
                              foreach ($datos['marcas'] as $m) {
                                  $fecha = substr($m['fecha_hora'], 0, 10);
                                  if (!isset($resumenDiario[$fecha])) {
                                      $resumenDiario[$fecha] = [
                                          'fecha' => $fecha,
                                          'entrada' => null,
                                          'salida_comida' => null,
                                          'regreso_comida' => null,
                                          'salida' => null,
                                          'varianza' => null,
                                          'justificada' => false
                                      ];
                                  }
                                  
                                  $hora = date('H:i:s', strtotime($m['fecha_hora']));
                                  if ($m['tipo'] === 'entrada') $resumenDiario[$fecha]['entrada'] = $hora;
                                  if ($m['tipo'] === 'salida_comida') $resumenDiario[$fecha]['salida_comida'] = $hora;
                                  if ($m['tipo'] === 'regreso_comida') $resumenDiario[$fecha]['regreso_comida'] = $hora;
                                  if ($m['tipo'] === 'salida') $resumenDiario[$fecha]['salida'] = $hora;
                                  
                                  if ($m['minutos_variacion'] !== null) {
                                      $resumenDiario[$fecha]['varianza'] = (int)$m['minutos_variacion'];
                                  }
                                  if ($m['tiene_justificacion']) {
                                      $resumenDiario[$fecha]['justificada'] = true;
                                  }
                              }

                              foreach ($resumenDiario as $dia):
                                  $fechaFormat = date('d/m/Y', strtotime($dia['fecha']));
                                  $diaNom = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][date('w', strtotime($dia['fecha']))];
                              ?>
                                  <tr>
                                      <td style="white-space:nowrap">
                                          <span style="font-size:.78rem;color:var(--gris)"><?= $diaNom ?></span>
                                          <?= $fechaFormat ?>
                                      </td>
                                      <td style="font-weight:600"><?= $dia['entrada'] ?: '<span class="geo-nd">—</span>' ?></td>
                                      <?php if ($datos['tipo_jornada'] === 'completa'): ?>
                                      <td style="font-weight:600"><?= $dia['salida_comida'] ?: '<span class="geo-nd">—</span>' ?></td>
                                      <td style="font-weight:600"><?= $dia['regreso_comida'] ?: '<span class="geo-nd">—</span>' ?></td>
                                      <?php endif; ?>
                                      <td style="font-weight:600"><?= $dia['salida'] ?: '<span class="geo-nd">—</span>' ?></td>
                                      
                                      <td>
                                          <?php if ($dia['varianza'] === null): ?>
                                              <span class="geo-nd">Jornada incompleta</span>
                                          <?php elseif ($dia['justificada'] && $dia['varianza'] > 0): ?>
                                              <span class="badge-varianza-ok" style="background:#e8f5e9;color:#2e7d32">
                                                  Tiempo Justificado (0 min)
                                              </span>
                                          <?php elseif ($dia['varianza'] <= 0): ?>
                                              <span class="badge-varianza-ok">
                                                  <?= $dia['varianza'] == 0 ? 'Horas completas' : '+' . abs($dia['varianza']) . ' min extra' ?>
                                              </span>
                                          <?php else: ?>
                                              <span class="badge-varianza-mal">
                                                  -<?= $dia['varianza'] ?> min (Faltante)
                                              </span>
                                          <?php endif; ?>
                                      </td>
                                      
                                      <td>
                                          <?php if ($dia['justificada']): ?>
                                              <a href="/admin/incidencias/index.php?estado=aprobada" class="badge-justificada" style="text-decoration:none; display:inline-block">
                                                  <i class="fa-solid fa-file-circle-check"></i> Justificada
                                              </a>
                                          <?php else: ?>
                                              <span style="color:var(--gris);font-size:.82rem">—</span>
                                          <?php endif; ?>
                                      </td>
                                  </tr>
                              <?php endforeach; ?>
                              </tbody>
                          </table></div>
                      </div>

                    <!-- Resumen del colaborador -->
                    <?php
                    $saldoNeto = 0;
                    foreach ($resumenDiario as $dia) {
                        if ($dia['varianza'] !== null) {
                            // Si tiene justificación aprobada y es un retardo/faltante (positivo), se perdona (no se suma)
                            if ($dia['varianza'] > 0 && !empty($dia['justificada'])) {
                                continue;
                            }
                            $saldoNeto += $dia['varianza'];
                        }
                    }
                    ?>
                    <div class="resumen-grid">
                        <div class="resumen-item">
                            <span class="resumen-valor"><?= $totalMarcas ?></span>
                            <span class="resumen-label">Total de marcas</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-valor"><?= $diasTrabajados ?></span>
                            <span class="resumen-label">Días con registro</span>
                        </div>
                          <div class="resumen-item">
                              <?php if ($saldoNeto === 0): ?>
                                  <span class="resumen-valor" style="color:var(--gris)">0 min</span>
                                  <span class="resumen-label">Saldo Neto (Bolsa de Horas)</span>
                              <?php elseif ($saldoNeto > 0): ?>
                                  <span class="resumen-valor alerta"><?= $saldoNeto ?> min</span>
                                  <span class="resumen-label">Saldo en contra (Retardos)</span>
                              <?php else: ?>
                                  <span class="resumen-valor" style="color:#1d6f42">+<?= abs($saldoNeto) ?> min</span>
                                  <span class="resumen-label">Saldo a favor (Horas extra)</span>
                              <?php endif; ?>
                          </div>
                    </div>

                    <?php
                    $chartLabels = [];
                    $chartData = [];
                    $chartColors = [];
                    foreach ($resumenDiario as $dia) {
                        $chartLabels[] = date('d/m', strtotime($dia['fecha']));
                        $val = $dia['varianza'] !== null ? $dia['varianza'] : 0;
                        
                        // Perdonar retardo si hay justificación
                        if ($val > 0 && !empty($dia['justificada'])) {
                            $val = 0;
                        }
                        
                        $chartData[] = $val;
                        $chartColors[] = $val <= 0 ? '#1d6f42' : '#e74c3c';
                    }
                    ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <h4 style="margin-bottom: 1rem; color: var(--gris); font-size: 0.9rem; text-align: center;">Gráfico de Rendimiento (Varianza en Minutos)</h4>
                        <div style="height: 150px;">
                            <canvas id="grafico-<?= $uid ?>"></canvas>
                        </div>
                    </div>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            new Chart(document.getElementById("grafico-<?= $uid ?>"), {
                                type: 'bar',
                                data: {
                                    labels: <?= json_encode($chartLabels) ?>,
                                    datasets: [{
                                        label: 'Minutos (Negativo = Extra, Positivo = Retardo)',
                                        data: <?= json_encode($chartData) ?>,
                                        backgroundColor: <?= json_encode($chartColors) ?>,
                                        borderRadius: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: { beginAtZero: true }
                                    }
                                }
                            });
                        });
                    </script>

                </div>
            </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>
</body>
</html>

