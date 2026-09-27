<?php
/**
 * CRON JOB: Recordatorio de Asistencia
 * Se recomienda ejecutar en AlwaysData cada 5 minutos.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/onesignal_helper.php';

$pdo = conectarBD();

// Obtener fecha actual y el día de la semana (0=Dom, 1=Lun...)
date_default_timezone_set('America/Guatemala'); // Asegurar zona horaria
$hoy = date('Y-m-d');
$diaSemana = (int)date('w');

// Buscar empleados que entran a trabajar en los próximos 15 minutos
$stmt = $pdo->prepare('
    SELECT h.usuario_id, h.hora_entrada, u.nombre
    FROM horarios h
    JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.dia_semana = ? 
      AND h.activo = 1 
      AND u.activo = 1
      AND h.hora_entrada IS NOT NULL
      AND h.hora_entrada > CURTIME()
      AND h.hora_entrada <= ADDTIME(CURTIME(), "00:15:00")
');
$stmt->execute([$diaSemana]);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enviados = 0;

foreach ($empleados as $emp) {
    // Verificar si el usuario ya registró su entrada hoy
    $stmtM = $pdo->prepare("
        SELECT id FROM marcas 
        WHERE usuario_id = ? AND DATE(created_at) = ? AND tipo = 'entrada'
        LIMIT 1
    ");
    $stmtM->execute([$emp['usuario_id'], $hoy]);
    $yaMarco = $stmtM->fetch();

    if (!$yaMarco) {
        // No ha marcado, enviarle notificación
        $horaFormato = date('h:i A', strtotime($emp['hora_entrada']));
        $mensaje = "Hola {$emp['nombre']}, tu turno de hoy empieza a las {$horaFormato}. ¡No olvides marcar tu entrada!";
        
        enviarNotificacionUsuario($emp['usuario_id'], "⏰ ¡Se acerca tu hora de entrada!", $mensaje);
        $enviados++;
    }
}

echo "Cron ejecutado correctamente. Se enviaron {$enviados} notificaciones.";
?>
