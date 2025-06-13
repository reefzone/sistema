<?php
/**
 * Archivo: ajax_handler.php
 * Ubicación: modules/admin/ajax_handler.php
 * Propósito: Manejador de peticiones AJAX para el módulo de administración
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa
verificarSesion();

// Verificar que sea una petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode([
        'exito' => false,
        'mensaje' => 'Acceso no permitido'
    ]));
}

// Verificar que se envió una acción
if (!isset($_POST['accion'])) {
    die(json_encode([
        'exito' => false,
        'mensaje' => 'Acción no especificada'
    ]));
}

$accion = $_POST['accion'];
$respuesta = [];

switch ($accion) {
    case 'probar_correo':
        // Verificar permisos
        if (!tienePermiso('admin_configuracion')) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'No tienes permisos para realizar esta acción'
            ]));
        }
        
        // Verificar email
        if (!isset($_POST['email']) || empty($_POST['email'])) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'Debe proporcionar un correo electrónico'
            ]));
        }
        
        $email = $_POST['email'];
        
        // Leer configuración de correo
        $config = obtener_configuracion();
        
        // Verificar configuración mínima
        if (empty($config['smtp_host']) || empty($config['smtp_puerto']) || empty($config['correo_remitente'])) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'La configuración de correo está incompleta. Por favor, complete todos los campos obligatorios.'
            ]));
        }
        
        // Crear mensaje de prueba
        $subject = "Correo de prueba - Sistema Escolar EST #82";
        
        $html_body = "<h2>Prueba de Configuración de Correo</h2>";
        $html_body .= "<p>Este es un mensaje de prueba enviado desde el Sistema Escolar de la ESCUELA SECUNDARIA TECNICA #82.</p>";
        $html_body .= "<p>Si has recibido este correo, la configuración de correo funciona correctamente.</p>";
        $html_body .= "<p>Fecha y hora de envío: " . date('d/m/Y H:i:s') . "</p>";
        $html_body .= "<hr>";
        
        if (!empty($config['firma_correo'])) {
            $html_body .= $config['firma_correo'];
        } else {
            $html_body .= "<p>Atentamente,<br>Administración - EST #82</p>";
        }
        
        // Intentar enviar correo
        $enviado = sendEmail(
            $email,
            $_SESSION['nombre_completo'],
            $subject,
            $html_body
        );
        
        if ($enviado) {
            $respuesta = [
                'exito' => true,
                'mensaje' => "Correo de prueba enviado correctamente a $email"
            ];
            
            // Registrar en log
            registrarLog(
                'operacion',
                $_SESSION['user_id'],
                null,
                "Correo de prueba enviado a $email"
            );
        } else {
            $respuesta = [
                'exito' => false,
                'mensaje' => "Error al enviar el correo de prueba. Verifique la configuración SMTP e inténtelo nuevamente."
            ];
        }
        break;
        
    case 'obtener_actividad_usuario':
        // Verificar permisos
        if (!tienePermiso('admin_logs')) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'No tienes permisos para realizar esta acción'
            ]));
        }
        
        // Verificar usuario
        if (!isset($_POST['username']) || empty($_POST['username'])) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'Usuario no especificado'
            ]));
        }
        
        $username = $_POST['username'];
        
        // Obtener actividad reciente
        $query = "SELECT * FROM logs_sistema 
                 WHERE usuario = ? 
                 ORDER BY fecha DESC 
                 LIMIT 20";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $actividad = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Formatear fechas
        foreach ($actividad as &$log) {
            $log['fecha_formateada'] = date('d/m/Y H:i', strtotime($log['fecha']));
        }
        
        $respuesta = [
            'exito' => true,
            'actividad' => $actividad
        ];
        break;
        
    case 'obtener_estadisticas':
        // Verificar permisos
        if (!tienePermiso('admin_ver')) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'No tienes permisos para realizar esta acción'
            ]));
        }
        
        // Obtener estadísticas
        $estadisticas = obtener_estadisticas_sistema();
        
        $respuesta = [
            'exito' => true,
            'estadisticas' => $estadisticas
        ];
        break;
        
    case 'verificar_backup_auto':
        // Verificar permisos
        if (!tienePermiso('admin_backup')) {
            die(json_encode([
                'exito' => false,
                'mensaje' => 'No tienes permisos para realizar esta acción'
            ]));
        }
        
        // Verificar configuración
        $backup_auto_habilitado = obtener_configuracion('backup_auto_habilitado') == '1';
        
        if (!$backup_auto_habilitado) {
            $respuesta = [
                'exito' => true,
                'estado' => 'deshabilitado',
                'mensaje' => 'Los respaldos automáticos están deshabilitados'
            ];
            break;
        }
        
        // Obtener última ejecución
        $query = "SELECT fecha_creacion FROM backups ORDER BY fecha_creacion DESC LIMIT 1";
        $resultado = $conexion->query($query);
        
        if ($resultado->num_rows > 0) {
            $ultimo_backup = $resultado->fetch_assoc()['fecha_creacion'];
            $tiempo_transcurrido = time() - strtotime($ultimo_backup);
            $horas_transcurridas = round($tiempo_transcurrido / 3600, 1);
            
            $respuesta = [
                'exito' => true,
                'estado' => 'habilitado',
                'ultimo_backup' => date('d/m/Y H:i', strtotime($ultimo_backup)),
                'horas_transcurridas' => $horas_transcurridas,
                'mensaje' => "Último respaldo: " . date('d/m/Y H:i', strtotime($ultimo_backup)) . " ($horas_transcurridas horas)"
            ];
        } else {
            $respuesta = [
                'exito' => true,
                'estado' => 'habilitado',
                'mensaje' => 'No se han realizado respaldos automáticos aún'
            ];
        }
        break;
        
    default:
        $respuesta = [
            'exito' => false,
            'mensaje' => 'Acción no válida'
        ];
        break;
}

// Devolver respuesta como JSON
header('Content-Type: application/json');
echo json_encode($respuesta);