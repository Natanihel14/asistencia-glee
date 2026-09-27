<?php
/**
 * Helper para enviar notificaciones Push a través de OneSignal.
 */

define('ONESIGNAL_APP_ID', '0d8bb111-847d-47b8-9d77-f3a34a26486c');
require_once __DIR__ . '/onesignal_key.php';

/**
 * Envia una notificación a todos los usuarios que tengan el tag 'rol' = 'admin'
 */
function enviarNotificacionAdmins($titulo, $mensaje, $url = '/admin/incidencias/') {
    $fields = array(
        'app_id' => ONESIGNAL_APP_ID,
        'filters' => array(
            array("field" => "tag", "key" => "rol", "relation" => "=", "value" => "Administrador")
        ),
        'headings' => array("en" => $titulo, "es" => $titulo),
        'contents' => array("en" => $mensaje, "es" => $mensaje),
        'url' => $url
    );
    return ejecutarCurlOneSignal($fields);
}

/**
 * Envia una notificación a un usuario específico por su ID de base de datos
 */
function enviarNotificacionUsuario($usuario_id, $titulo, $mensaje, $url = '/vendedor/dashboard.php') {
    $fields = array(
        'app_id' => ONESIGNAL_APP_ID,
        'include_aliases' => array(
            'external_id' => array("USUARIO_" . $usuario_id)
        ),
        'target_channel' => 'push',
        'headings' => array("en" => $titulo, "es" => $titulo),
        'contents' => array("en" => $mensaje, "es" => $mensaje),
        'url' => $url
    );
    return ejecutarCurlOneSignal($fields);
}

/**
 * Ejecuta la petición cURL a la API de OneSignal
 */
function ejecutarCurlOneSignal($fields) {
    $fields = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . ONESIGNAL_REST_API_KEY
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}
?>
