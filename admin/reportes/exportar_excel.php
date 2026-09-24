<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../reportes_helper.php';

requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

$mes = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: (int)date('n');
$anio = filter_input(INPUT_GET, 'anio', FILTER_VALIDATE_INT) ?: (int)date('Y');
$usuarioFiltro = filter_input(INPUT_GET, 'usuario_id', FILTER_VALIDATE_INT) ?: 0;

$reporteDatos = obtenerReporteMensual($pdo, $anio, $mes, $usuarioFiltro);

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
            <?php foreach ($reporteDatos as $datos): 
                $saldoNeto = $datos['saldo_neto'];
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
                <Cell><Data ss:Type="Number"><?= $datos['dias_trabajados'] ?></Data></Cell>
                <Cell><Data ss:Type="Number"><?= $datos['total_marcas'] ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $saldoTxt ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>

    <?php foreach ($reporteDatos as $datos): 
        $sheetName = substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $datos['nombre']), 0, 31);
    ?>
    <Worksheet ss:Name="<?= htmlspecialchars($sheetName) ?>">
        <Table>
            <Column ss:Width="120" />
            <Column ss:Width="100" />
            <Column ss:Width="100" />
            <Column ss:Width="100" />
            <Column ss:Width="100" />
            <Column ss:Width="150" />
            <Column ss:Width="120" />
            <Row>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Fecha</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Entrada</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">S. Comida</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">R. Comida</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Salida</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Balance (Minutos)</Data></Cell>
                <Cell ss:StyleID="Header"><Data ss:Type="String">Notas</Data></Cell>
            </Row>
            <?php foreach ($datos['resumen_diario'] as $dia): 
                $fechaFormat = date('d/m/Y', strtotime($dia['fecha']));
                $entrada = $dia['entrada'] ?: '-';
                $scomida = $dia['salida_comida'] ?: '-';
                $rcomida = $dia['regreso_comida'] ?: '-';
                $salida = $dia['salida'] ?: '-';
                
                $notas = '';
                if ($dia['ausencia'] && !$dia['justificada']) {
                    $notas = 'Ausencia Injustificada';
                } elseif ($dia['justificada'] && $dia['ausencia']) {
                    $incInfo = ucfirst(str_replace('_', ' ', $dia['incidencia_detalle']['tipo'])) . ': ' . $dia['incidencia_detalle']['motivo'];
                    $notas = 'Falta Justificada - ' . $incInfo;
                } elseif ($dia['justificada'] && $dia['varianza'] > 0) {
                    $incInfo = ucfirst(str_replace('_', ' ', $dia['incidencia_detalle']['tipo'])) . ': ' . $dia['incidencia_detalle']['motivo'];
                    $notas = 'Retardo Justificado - ' . $incInfo;
                } elseif ($dia['vino']) {
                    $notas = 'Asistió';
                }

                $var = $dia['varianza'] === null ? '' : (int)$dia['varianza'];
            ?>
            <Row>
                <Cell><Data ss:Type="String"><?= $fechaFormat ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $entrada ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $scomida ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $rcomida ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $salida ?></Data></Cell>
                <Cell><Data ss:Type="<?= $var === '' ? 'String' : 'Number' ?>"><?= $var ?></Data></Cell>
                <Cell><Data ss:Type="String"><?= $notas ?></Data></Cell>
            </Row>
            <?php endforeach; ?>
        </Table>
    </Worksheet>
    <?php endforeach; ?>

</Workbook>
