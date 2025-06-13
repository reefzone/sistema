<?php
/**
 * Guardar Asistencia
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador', 'profesor'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método de solicitud no válido', 'danger');
}

// Verificar token CSRF
verificar_token_csrf($_POST['csrf_token']);

// Obtener y validar datos del formulario
$id_grupo = isset($_POST['id_grupo']) ? intval($_POST['id_grupo']) : 0;
$fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
$alumnos = isset($_POST['alumnos']) ? $_POST['alumnos'] : [];

// Sanitizar fecha
if (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    redireccionar_con_mensaje('index.php', 'Formato de fecha no válido', 'danger');
}

// Validar que la fecha no sea futura
if ($fecha > date('Y-m-d')) {
    redireccionar_con_mensaje('index.php', 'No se puede registrar asistencia para fechas futuras', 'danger');
}

// Validar que no sea una fecha muy antigua (más de 7 días)
$limite_dias = 7; // Configurable
$fecha_limite = date('Y-m-d', strtotime("-$limite_dias days"));
if ($fecha < $fecha_limite && $_SESSION['tipo_usuario'] != 'superadmin') {
    redireccionar_con_mensaje('index.php', "No se puede registrar o modificar asistencia para fechas anteriores a $limite_dias días", 'danger');
}

// Validar ID de grupo
if ($id_grupo <= 0) {
    redireccionar_con_mensaje('index.php', 'Debe seleccionar un grupo válido', 'danger');
}

// Validar que existan alumnos para registrar
if (empty($alumnos)) {
    redireccionar_con_mensaje("index.php?id_grupo=$id_grupo&fecha=$fecha", 'No hay alumnos para registrar asistencia', 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Contador para estadísticas
    $total_registros = 0;
    $total_presentes = 0;
    $total_ausentes = 0;
    $total_justificados = 0;
    
    // Procesar cada alumno
    foreach ($alumnos as $id_alumno => $datos) {
        $id_alumno = intval($id_alumno);
        $asistio = isset($datos['asistio']) ? 1 : 0;
        $justificada = (!$asistio && isset($datos['justificada'])) ? 1 : 0;
        $observaciones = isset($datos['observaciones']) ? sanitizar_texto($datos['observaciones']) : '';
        
        // Si el alumno está presente, no puede tener justificación
        if ($asistio == 1) {
            $justificada = 0;
            $observaciones = '';
        }
        
        // Contabilizar para estadísticas
        $total_registros++;
        if ($asistio) {
            $total_presentes++;
        } else {
            $total_ausentes++;
            if ($justificada) {
                $total_justificados++;
            }
        }
        
        // Verificar si ya existe un registro para este alumno en esta fecha
        if (isset($datos['id_asistencia']) && intval($datos['id_asistencia']) > 0) {
            // Actualizar registro existente
            $query = "UPDATE asistencia SET 
                      asistio = ?, 
                      justificada = ?, 
                      observaciones = ?, 
                      registrado_por = ?, 
                      fecha_registro = NOW() 
                      WHERE id_asistencia = ?";
                      
            $stmt = $conexion->prepare($query);
            $id_asistencia = intval($datos['id_asistencia']);
            $stmt->bind_param("iisis", $asistio, $justificada, $observaciones, $_SESSION['id_usuario'], $id_asistencia);
            $stmt->execute();
        } else {
            // Crear nuevo registro
            $query = "INSERT INTO asistencia 
                     (id_alumno, fecha, asistio, justificada, observaciones, registrado_por, fecha_registro) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE 
                     asistio = VALUES(asistio), 
                     justificada = VALUES(justificada), 
                     observaciones = VALUES(observaciones), 
                     registrado_por = VALUES(registrado_por), 
                     fecha_registro = NOW()";
                     
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("isiisi", $id_alumno, $fecha, $asistio, $justificada, $observaciones, $_SESSION['id_usuario']);
            $stmt->execute();
        }
    }
    
    // Obtener información del grupo para el log
    $query_grupo = "SELECT g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
                   FROM grupos g 
                   JOIN grados gr ON g.id_grado = gr.id_grado 
                   JOIN turnos t ON g.id_turno = t.id_turno 
                   WHERE g.id_grupo = ?";
    $stmt_grupo = $conexion->prepare($query_grupo);
    $stmt_grupo->bind_param("i", $id_grupo);
    $stmt_grupo->execute();
    $result_grupo = $stmt_grupo->get_result();
    $info_grupo = $result_grupo->fetch_assoc();
    
    // Registrar la acción en el log
    $detalle_log = "Se registró asistencia para el grupo {$info_grupo['nombre_grupo']} de {$info_grupo['nombre_grado']} ".
                  "turno {$info_grupo['nombre_turno']} para el día " . date('d/m/Y', strtotime($fecha)) . 
                  ". Total: $total_registros alumnos. Presentes: $total_presentes. Ausentes: $total_ausentes. Justificados: $total_justificados";
    
    registrar_log($conexion, 'registro_asistencia', $detalle_log, $_SESSION['id_usuario']);
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    redireccionar_con_mensaje(
        "index.php?id_grupo=$id_grupo&fecha=$fecha", 
        "La asistencia ha sido registrada exitosamente. Presentes: $total_presentes, Ausentes: $total_ausentes, Justificados: $total_justificados", 
        'success'
    );
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Registrar el error
    $error_msg = "Error al registrar asistencia: " . $e->getMessage();
    error_log($error_msg);
    
    // Redirigir con mensaje de error
    redireccionar_con_mensaje(
        "index.php?id_grupo=$id_grupo&fecha=$fecha", 
        "Error al registrar la asistencia. Por favor, intente nuevamente.", 
        'danger'
    );
}

// Función para registrar acción en el log del sistema
function registrar_log($conexion, $accion, $detalle, $id_usuario) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO logs_sistema (fecha, accion, detalle, ip, id_usuario) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ssssi", $fecha, $accion, $detalle, $ip, $id_usuario);
    $stmt->execute();
}