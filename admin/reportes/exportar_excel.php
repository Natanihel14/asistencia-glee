<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('n');
$anio = filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT) ?: (int)date('Y');
$usuarioFiltro = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT) ?: 0;

$where = "YEAR(ma.fecha_hora) = ? AND MONTH(ma.fecha_hora) = ?";
$params = [$anio, $mes];

if ($usuarioFiltro > 0) {
    $where .= " AND ma.usuario_id = ?";
    $params[] = $usuarioFiltro;
}

$sql = "
    SELECT
        ma.id, ma.tipo, ma.fecha_hora, ma.dentro_geocerca, ma.minutos_variacion,
        u.id AS usuario_id,
        u.nombre_completo,
        s.nombre AS sucursal_nombre,
        (SELECT COUNT(*) FROM incidencias i 
          WHERE i.usuario_id = ma.usuario_id 
            AND i.fecha = DATE(ma.fecha_hora) 
            AND i.estado = 'aprobada') AS incidencias_dia
    FROM marcas_asistencia ma
    JOIN usuarios u ON ma.usuario_id = u.id
    JOIN sucursales s ON ma.sucursal_id = s.id
    WHERE $where
    ORDER BY u.nombre_completo ASC, ma.fecha_hora ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$marcas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$porColaborador = [];
foreach ($marcas as $m) {
    $uid = $m['usuario_id'];
    if (!isset($porColaborador[$uid])) {
        $porColaborador[$uid] = [
            'nombre' => $m['nombre_completo'],
            'marcas' => []
        ];
    }
    $porColaborador[$uid]['marcas'][] = $m;
}

$filename = "Reporte_GLEE_{$anio}_{$mes}.csv";
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
// BOM para que Excel detecte UTF-8
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

// RESUMEN
fputcsv($output, ['RESUMEN GENERAL']);
fputcsv($output, ['Colaborador', 'Dias Trabajados', 'Total Marcas', 'Saldo Neto (Bolsa de Horas)']);

foreach ($porColaborador as $uid => $datos) {
    $diasTrabajados = count(array_unique(array_map(fn($m) => substr($m['fecha_hora'], 0, 10), $datos['marcas'])));
    $resumenDiario = [];
    foreach ($datos['marcas'] as $m) {
        $fecha = substr($m['fecha_hora'], 0, 10);
        if ($m['minutos_variacion'] !== null) {
            $resumenDiario[$fecha] = [
                'varianza' => (int)$m['minutos_variacion'],
                'justificada' => $m['incidencias_dia'] > 0
            ];
        }
    }
    
    $saldoNeto = 0;
    foreach ($resumenDiario as $dia) {
        if ($dia['varianza'] > 0 && $dia['justificada']) continue;
        $saldoNeto += $dia['varianza'];
    }
    
    if ($saldoNeto === 0) {
        $saldoTxt = "0 min";
    } elseif ($saldoNeto > 0) {
        $saldoTxt = "$saldoNeto min (En contra)";
    } else {
        $saldoTxt = "+" . abs($saldoNeto) . " min (A favor)";
    }
    
    fputcsv($output, [$datos['nombre'], $diasTrabajados, count($datos['marcas']), $saldoTxt]);
}

fputcsv($output, []);
fputcsv($output, []);

// DETALLES
fputcsv($output, ['DETALLES POR COLABORADOR']);
fputcsv($output, ['Colaborador', 'Fecha y Hora', 'Tipo', 'Sucursal', 'Varianza (Min)', 'Incidencia Justificada']);

foreach ($porColaborador as $uid => $datos) {
    foreach ($datos['marcas'] as $m) {
        $fechaHora = date('d/m/Y H:i', strtotime($m['fecha_hora']));
        $tipo = ucfirst(str_replace('_', ' ', $m['tipo']));
        $var = $m['minutos_variacion'] === null ? '' : (int)$m['minutos_variacion'];
        $incidencias = $m['incidencias_dia'] > 0 ? 'Si' : 'No';
        
        fputcsv($output, [
            $datos['nombre'],
            $fechaHora,
            $tipo,
            $m['sucursal_nombre'],
            $var,
            $incidencias
        ]);
    }
}

fclose($output);
exit;
