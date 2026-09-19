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

$filename = "Reporte_GLEE_{$anio}_{$mes}.xls";
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" 
          xmlns:o="urn:schemas-microsoft-com:office:office" 
          xmlns:x="urn:schemas-microsoft-com:office:excel" 
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" 
          xmlns:html="http://www.w3.org/TR/REC-html40">
    
    <Styles>
        <Style ss:ID="Header">
            <Font ss:Bold="1" ss:Color="#FFFFFF" />
            <Interior ss:Color="#0b4c6e" ss:Pattern="Solid" />
            <Alignment ss:Horizontal="Center" />
        </Style>
        <Style ss:ID="Bold">
            <Font ss:Bold="1" />
        </Style>
    </Styles>

    <Worksheet ss:Name="Resumen General">
        <Table>
            <Column ss:Width="150" />
            <Column ss:Width="100" />
            <Column ss:Width="100" />
            <Column ss:Width="150" />
            <Row>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Colaborador</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Total Días</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Total Marcas</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Saldo Neto (Bolsa de Horas)</Data></Cell>
            </Row>
            <?php foreach ($porColaborador as $uid => $datos): 
                $diasTrabajados = count(array_unique(array_map(fn($m) => substr($m['fecha_hora'], 0, 10), $datos['marcas'])));
                
                // Agrupar por día igual que en la web
                $resumenDiarioXLS = [];
                foreach ($datos['marcas'] as $m) {
                    $fecha = substr($m['fecha_hora'], 0, 10);
                    if ($m['minutos_variacion'] !== null) {
                        $resumenDiarioXLS[$fecha] = [
                            'varianza' => (int)$m['minutos_variacion'],
                            'justificada' => $m['incidencias_dia'] > 0
                        ];
                    }
                }
                
                $saldoNeto = 0;
                foreach ($resumenDiarioXLS as $dia) {
                    if ($dia['varianza'] > 0 && $dia['justificada']) {
                        continue;
                    }
                    $saldoNeto += $dia['varianza'];
                }
                
                // Formateo de texto amigable para la bolsa de horas
                if ($saldoNeto === 0) {
                    $saldoTxt = "0 min";
                } elseif ($saldoNeto > 0) {
                    $saldoTxt = "$saldoNeto min (En contra)";
                } else {
                    $saldoTxt = "+" . abs($saldoNeto) . " min (A favor)";
                }
            ?>
            <Row>
                <Cell><Data ss:Type="String"><?= htmlspecialchars($datos['nombre']) ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?= $diasTrabajados ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?= count($datos['marcas']) ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $saldoTxt ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>

    <?php foreach ($porColaborador as $uid => $datos): 
        // Excel worksheet names have a max limit of 31 chars and can't contain certain symbols
        $sheetName = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $datos['nombre']), 0, 31);
    ?>
    <Worksheet ss:Name="<?= htmlspecialchars($sheetName) ?>">
        <Table>
            <Column ss:Width="120" />
            <Column ss:Width="100" />
            <Column ss:Width="180" />
            <Column ss:Width="120" />
            <Column ss:Width="150" />
            <Row>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Fecha y Hora</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Tipo</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Sucursal</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Varianza (Minutos)</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Incidencias Aprobadas</Data></Cell>
            </Row>
            <?php foreach ($datos['marcas'] as $m): 
                $fechaHora = date('d/m/Y H:i', strtotime($m['fecha_hora']));
                $tipo = ucfirst(str_replace('_', ' ', $m['tipo']));
                $var = $m['minutos_variacion'] === null ? '' : (int)$m['minutos_variacion'];
                $incidencias = $m['incidencias_dia'] > 0 ? 'Sí' : 'No';
            ?>
            <Row>
                <Cell><Data ss:Type="String"><?= $fechaHora ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $tipo ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= htmlspecialchars($m['sucursal_nombre']) ?></Data></Cell>
                <Cell><Data ss:Type="<?= $var === '' ? 'String' : 'Number' ?>"><?= $var ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $incidencias ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>
    <?php endforeach; ?>

</Workbook>
