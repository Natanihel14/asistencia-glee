<?php
/**
 * admin/reportes/index.php
 * Reporte mensual de asistencia por colaborador, incluyendo ausencias.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../reportes_helper.php';

requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

// Filtros GET
$mesActual  = (int)date('n');
$anioActual = (int)date('Y');

$mes  = (isset($_GET['mes'])  && (int)$_GET['mes']  >= 1  && (int)$_GET['mes']  <= 12)
      ? (int)$_GET['mes'] : $mesActual;
$anio = (isset($_GET['anio']) && (int)$_GET['anio'] >= 2020 && (int)$_GET['anio'] <= 2099)
      ? (int)$_GET['anio'] : $anioActual;
$usuarioFiltro = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT) ?: 0;

$colaboradores = $pdo->query("
    SELECT id, nombre_completo
      FROM usuarios
     WHERE rol IN ('vendedor','bodega')
     ORDER BY activo DESC, nombre_completo ASC
")->fetchAll();

$reporteDatos = obtenerReporteMensual($pdo, $anio, $mes, $usuarioFiltro);

$mesesNombre = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
    5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre',
];

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - GLEE</title>
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
        .resumen-valor { font-size: 1.6rem; font-weight: 800; color: var(--primario); display: block; }
        .resumen-valor.alerta { color: var(--peligro, #c0392b); }
        .resumen-label { font-size: .78rem; color: var(--gris); }
        .badge-varianza-ok  { background:#d4f4e0; color:#155724; padding:.2rem .55rem; border-radius:10px; font-size:.8rem; font-weight:600; white-space:nowrap; }
        .badge-varianza-mal { background:#fde8d8; color:#7d3000; padding:.2rem .55rem; border-radius:10px; font-size:.8rem; font-weight:600; white-space:nowrap; }
        .badge-ausencia     { background:#ffcdd2; color:#b71c1c; padding:.2rem .55rem; border-radius:10px; font-size:.8rem; font-weight:600; white-space:nowrap; }
        .badge-justificada  { background:#e8d5f5; color:#5a1a8a; padding:.2rem .55rem; border-radius:10px; font-size:.78rem; font-weight:600; }
        .geo-nd    { color:var(--gris); font-size:.85rem; }
    </style>
</head>
<body>

    <header class="topbar">
        <a class="topbar-marca" href="<?= urlDashboard() ?>">GLEE</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?></span>
            <a href="/logout.php" class="btn-salir"><i class="fa-solid fa-right-from-bracket"></i> Salir</a>
        </div>
    </header>

    <main class="contenedor">
        <a class="volver" href="/admin/dashboard.php"><i class="fa-solid fa-arrow-left"></i> Volver al panel</a>

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
                            <option value="<?= $n ?>" <?= $mes === $n ? 'selected' : '' ?>><?= $etq ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grupo" style="margin:0;min-width:100px">
                    <label for="f-anio">Año</label>
                    <input type="number" id="f-anio" name="anio" value="<?= $anio ?>" min="2020" max="2099">
                </div>
                <div class="form-grupo" style="margin:0;flex:1;min-width:200px">
                    <label for="f-usuario">Colaborador</label>
                    <select id="f-usuario" name="usuario_id">
                        <option value="">-- Todos los colaboradores --</option>
                        <?php foreach ($colaboradores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $usuarioFiltro === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primario btn-sm" style="margin-bottom:2px">
                    <i class="fa-solid fa-filter"></i> Generar
                </button>
            </form>
        </div>

        <?php if (empty($reporteDatos)): ?>
            <div class="tarjeta" style="padding:3rem 1rem; text-align:center; color:var(--gris)">
                <i class="fa-solid fa-folder-open" style="font-size:3rem; margin-bottom:1rem; opacity:0.5"></i>
                <p>No hay datos registrados en el período seleccionado.</p>
            </div>
        <?php else: ?>
            <?php foreach ($reporteDatos as $datos): 
                $uid = $datos['usuario_id'];
            ?>
            <div class="tarjeta reporte-seccion">
                <div class="tarjeta-body">
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
                            foreach ($datos['resumen_diario'] as $dia):
                                $fechaFormat = date('d/m/Y', strtotime($dia['fecha']));
                                $diaNom = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][date('w', strtotime($dia['fecha']))];
                            ?>
                                <tr>
                                    <td style="white-space:nowrap">
                                        <span style="font-size:.78rem;color:var(--gris)"><?= $diaNom ?></span>
                                        <?= $fechaFormat ?>
                                    </td>
                                    <td style="font-weight:600"><?= $dia['entrada'] ?: '<span class="geo-nd">-</span>' ?></td>
                                    <?php if ($datos['tipo_jornada'] === 'completa'): ?>
                                    <td style="font-weight:600"><?= $dia['salida_comida'] ?: '<span class="geo-nd">-</span>' ?></td>
                                    <td style="font-weight:600"><?= $dia['regreso_comida'] ?: '<span class="geo-nd">-</span>' ?></td>
                                    <?php endif; ?>
                                    <td style="font-weight:600"><?= $dia['salida'] ?: '<span class="geo-nd">-</span>' ?></td>
                                    
                                    <td>
                                        <?php if ($dia['ausencia'] && !$dia['justificada']): ?>
                                            <span class="badge-ausencia">Ausencia Injustificada (-<?= $dia['varianza'] ?> min)</span>
                                        <?php elseif ($dia['ausencia'] && $dia['justificada']): ?>
                                            <span class="badge-varianza-ok" style="background:#e8f5e9;color:#2e7d32">Falta Justificada (0 min)</span>
                                        <?php elseif ($dia['varianza'] === null): ?>
                                            <span class="geo-nd">Jornada incompleta</span>
                                        <?php elseif ($dia['varianza'] > 0 && $dia['justificada']): ?>
                                            <span class="badge-varianza-ok" style="background:#e8f5e9;color:#2e7d32">Retardo Justificado (0 min)</span>
                                        <?php elseif ($dia['varianza'] <= 0): ?>
                                            <span class="badge-varianza-ok"><?= $dia['varianza'] == 0 ? 'Horas completas' : '+' . abs($dia['varianza']) . ' min extra' ?></span>
                                        <?php else: ?>
                                            <span class="badge-varianza-mal">-<?= $dia['varianza'] ?> min (Faltante)</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($dia['justificada']): ?>
                                            <a href="/admin/incidencias/index.php?estado=aprobada" class="badge-justificada" style="text-decoration:none; display:inline-block">
                                                <i class="fa-solid fa-file-circle-check"></i> Justificada
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--gris);font-size:.82rem">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table></div>
                    </div>

                    <!-- Resumen del colaborador -->
                    <div class="resumen-grid">
                        <div class="resumen-item">
                            <span class="resumen-valor"><?= $datos['total_marcas'] ?></span>
                            <span class="resumen-label">Total de marcas</span>
                        </div>
                        <div class="resumen-item">
                            <span class="resumen-valor"><?= $datos['dias_trabajados'] ?></span>
                            <span class="resumen-label">Días con registro</span>
                        </div>
                        <div class="resumen-item">
                            <?php if ($datos['saldo_neto'] === 0): ?>
                                <span class="resumen-valor" style="color:var(--gris)">0 min</span>
                                <span class="resumen-label">Saldo Neto (Bolsa de Horas)</span>
                            <?php elseif ($datos['saldo_neto'] > 0): ?>
                                <span class="resumen-valor alerta"><?= $datos['saldo_neto'] ?> min</span>
                                <span class="resumen-label">Saldo en contra (Deuda)</span>
                            <?php else: ?>
                                <span class="resumen-valor" style="color:#1d6f42">+<?= abs($datos['saldo_neto']) ?> min</span>
                                <span class="resumen-label">Saldo a favor (Horas extra)</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                    $chartLabels = [];
                    $chartData = [];
                    $chartColors = [];
                    foreach ($datos['resumen_diario'] as $dia) {
                        $chartLabels[] = date('d/m', strtotime($dia['fecha']));
                        
                        $val = $dia['varianza'] !== null ? $dia['varianza'] : 0;
                        if ($val > 0 && $dia['justificada']) {
                            $val = 0; // Perdonado
                        }
                        
                        // En el grafico: valores positivos hacia abajo (rojo, deuda), negativos hacia arriba (verde, extra)
                        // Para que se vea lgico, invertiremos el signo visualmente en el grafico para que horas extra sean barras hacia arriba
                        $chartData[] = -$val; 
                        $chartColors[] = $val <= 0 ? '#1d6f42' : '#e74c3c';
                    }
                    ?>
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <h4 style="margin-bottom: 1rem; color: var(--gris); font-size: 0.9rem; text-align: center;">Gráfico de Rendimiento (Bolsa de Horas)</h4>
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
                                        label: 'Minutos (Hacia arriba = A favor, Hacia abajo = En contra)',
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
