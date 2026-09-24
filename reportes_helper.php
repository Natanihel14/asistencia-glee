<?php
/**
 * reportes_helper.php
 * Funciones para calcular la bolsa de horas rellenando ausencias.
 */

function obtenerReporteMensual($pdo, $anio, $mes, $usuarioId = 0) {
    // 1. Obtener empleados
    $sqlUsu = "SELECT id, nombre_completo, rol, tipo_jornada, DATE(created_at) AS fecha_creacion FROM usuarios WHERE rol IN ('vendedor','bodega') AND activo = 1";
    $paramsUsu = [];
    if ($usuarioId > 0) {
        $sqlUsu .= " AND id = ?";
        $paramsUsu[] = $usuarioId;
    }
    $sqlUsu .= " ORDER BY nombre_completo ASC";
    $stmtU = $pdo->prepare($sqlUsu);
    $stmtU->execute($paramsUsu);
    $usuarios = $stmtU->fetchAll(PDO::FETCH_ASSOC);

    if (empty($usuarios)) return [];

    $ids = [];
    foreach ($usuarios as $u) {
        $ids[] = $u['id'];
    }
    $inIds = implode(',', $ids);

    // 2. Obtener horarios (solo dias que deben laborar)
    $horariosMap = [];
    $horarios = $pdo->query("SELECT usuario_id, dia_semana FROM horarios WHERE usuario_id IN ($inIds) AND activo = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($horarios as $h) {
        $horariosMap[$h['usuario_id']][$h['dia_semana']] = true;
    }

    // 3. Obtener incidencias aprobadas del mes
    $incidenciasMap = [];
    $stmtI = $pdo->prepare("SELECT usuario_id, fecha FROM incidencias WHERE usuario_id IN ($inIds) AND estado = 'aprobada' AND YEAR(fecha) = ? AND MONTH(fecha) = ?");
    $stmtI->execute([$anio, $mes]);
    foreach ($stmtI->fetchAll(PDO::FETCH_ASSOC) as $inc) {
        $incidenciasMap[$inc['usuario_id']][$inc['fecha']] = true;
    }

    // 4. Obtener marcas del mes
    $marcasMap = [];
    $stmtM = $pdo->prepare("SELECT ma.*, s.nombre AS sucursal_nombre FROM marcas_asistencia ma LEFT JOIN sucursales s ON ma.sucursal_id = s.id WHERE ma.usuario_id IN ($inIds) AND YEAR(ma.fecha_hora) = ? AND MONTH(ma.fecha_hora) = ? ORDER BY ma.fecha_hora ASC");
    $stmtM->execute([$anio, $mes]);
    foreach ($stmtM->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $fecha = substr($m['fecha_hora'], 0, 10);
        $marcasMap[$m['usuario_id']][$fecha][] = $m;
    }

    // 5. Construir el calendario
    $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $hoyStr = date('Y-m-d');

    $resultado = [];

    foreach ($usuarios as $u) {
        $uid = $u['id'];
        $fechaCreacion = $u['fecha_creacion'];
        
        $resumenDiario = [];
        $totalMarcas = 0;
        $diasTrabajados = 0;
        $saldoNeto = 0;

        for ($d = 1; $d <= $diasEnMes; $d++) {
            $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
            
            // Si la fecha es mayor a hoy, no la evaluamos
            if ($fecha > $hoyStr) {
                continue;
            }

            // Si la fecha es anterior a cuando se creo el usuario, la ignoramos
            if ($fecha < $fechaCreacion) {
                continue;
            }

            $diaSemana = (int)date('w', strtotime($fecha)); // 0=Dom, 1=Lun...
            
            // ¿Estaba programado para trabajar este dia?
            $debeTrabajar = isset($horariosMap[$uid][$diaSemana]);
            $tieneIncidencia = isset($incidenciasMap[$uid][$fecha]);
            $marcasDelDia = $marcasMap[$uid][$fecha] ?? [];

            // Si no tenia horario y no vino, no pasa nada
            if (!$debeTrabajar && empty($marcasDelDia) && !$tieneIncidencia) {
                continue;
            }

            // Extraer tiempos si vino
            $entrada = null;
            $salida_comida = null;
            $regreso_comida = null;
            $salida = null;
            $varianza = null;
            $sucursal = null;

            foreach ($marcasDelDia as $m) {
                $totalMarcas++;
                $h = date('H:i:s', strtotime($m['fecha_hora']));
                if ($m['tipo'] === 'entrada') $entrada = $h;
                if ($m['tipo'] === 'salida_comida') $salida_comida = $h;
                if ($m['tipo'] === 'regreso_comida') $regreso_comida = $h;
                if ($m['tipo'] === 'salida') $salida = $h;
                
                if ($m['sucursal_nombre']) $sucursal = $m['sucursal_nombre'];
                if ($m['minutos_variacion'] !== null) {
                    $varianza = (int)$m['minutos_variacion'];
                }
            }

            if (!empty($marcasDelDia)) {
                $diasTrabajados++;
            }

            // Si debia trabajar y NO vino ni marco nada
            $esAusencia = false;
            if ($debeTrabajar && empty($marcasDelDia)) {
                $esAusencia = true;
                if ($tieneIncidencia) {
                    $varianza = 0; // Justificado
                } else {
                    $varianza = 480; // Falta injustificada (8 horas) // Positivo = Deuda
                    if ($u['tipo_jornada'] === 'media') $varianza = 240;
                }
            }

            // Sumar a la bolsa de horas
            if ($varianza !== null) {
                // Si tiene justificacion y la varianza es deuda (positivo), se perdona
                if ($varianza > 0 && $tieneIncidencia) {
                    $varianza = 0;
                }
                $saldoNeto += $varianza;
            }

            $resumenDiario[] = [
                'fecha' => $fecha,
                'entrada' => $entrada,
                'salida_comida' => $salida_comida,
                'regreso_comida' => $regreso_comida,
                'salida' => $salida,
                'varianza' => $varianza,
                'justificada' => $tieneIncidencia,
                'sucursal' => $sucursal,
                'vino' => !empty($marcasDelDia),
                'ausencia' => $esAusencia
            ];
        }

        $resultado[] = [
            'usuario_id' => $uid,
            'nombre' => $u['nombre_completo'],
            'rol' => $u['rol'],
            'tipo_jornada' => $u['tipo_jornada'],
            'resumen_diario' => $resumenDiario,
            'total_marcas' => $totalMarcas,
            'dias_trabajados' => $diasTrabajados,
            'saldo_neto' => $saldoNeto
        ];
    }

    return $resultado;
}
