<?php
/**
 * Eliminar Registro de Asistencia
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin puede eliminar)
if ($_SESSION['tipo_usuario'] !== 'superadmin') {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método de solicitud no válido', 'danger');
}

// Verificar token CSRF
verificar_token_csrf($_POST['csrf_token']);

// Obtener datos del formulario
$id_asistencia = isset($_POST['id_asistencia']) ? intval($_POST['id_asistencia']) : 0;
$id_alumno = isset($_POST['id_alumno']) ? intval($_POST['id_alumno']) : 0;

// Validar datos
if ($id_asistencia <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de asistencia no válido', 'danger');
}

// Obtener información del registro antes de eliminarlo
$query_info = "SELECT a.fecha, 
              CONCAT(al.apellido_paterno, ' ', al.apellido_materno, ' ', al.nombre) as nombre_alumno,
              al.matricula,
              g.nombre_grupo, gr.nombre_grado, t.nombre_turno
              FROM asistencia a
              JOIN alumnos al ON a.id_alumno = al.id_alumno
              JOIN grupos g ON al.id_grupo = g.id_grupo
              JOIN grados gr ON g.id_grado = gr.id_grado
              JOIN turnos t ON g.id_turno = t.id_turno
              WHERE a.id_asistencia = ?";
$stmt_info = $conexion->prepare($query_info);
$stmt_info->bind_param("i", $id_asistencia);
$stmt_info->execute();
$result_info = $stmt_info->get_result();

// Verificar si el registro existe
if ($result_info->num_rows == 0) {
    redireccionar_con_mensaje('index.php', 'El registro de asistencia no existe', 'danger');
}

$info_asistencia = $result_info->fetch_assoc();

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Verificar si hay documentos de justificación asociados
    $query_docs = "SELECT id_documento, ruta_archivo FROM documentos_justificacion WHERE id_asistencia = ?";
    $stmt_docs = $conexion->prepare($query_docs);
    $stmt_docs->bind_param("i", $id_asistencia);
    $stmt_docs->execute();
    $result_docs = $stmt_docs->get_result();
    
    // Eliminar documentos físicos si existen
    while ($doc = $result_docs->fetch_assoc()) {
        $ruta_archivo = '../../uploads/justificaciones/' . $doc['ruta_archivo'];
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        }
        
        // Eliminar registro de documento
        $query_del_doc = "DELETE FROM documentos_justificacion WHERE id_documento = ?";
        $stmt_del_doc = $conexion->prepare($query_del_doc);
        $stmt_del_doc->bind_param("i", $doc['id_documento']);
        $stmt_del_doc->execute();
    }
    
    // Eliminar el registro de asistencia
    $query_delete = "DELETE FROM asistencia WHERE id_asistencia = ?";
    $stmt_delete = $conexion->prepare($query_delete);
    $stmt_delete->bind_param("i", $id_asistencia);
    $stmt_delete->execute();
    
    // Verificar que se haya eliminado correctamente
    if ($stmt_delete->affected_rows <= 0) {
        throw new Exception("No se pudo eliminar el registro de asistencia");
    }
    
    // Registrar la acción en el log
    $detalle_log = "Se eliminó el registro de asistencia del alumno {$info_asistencia['nombre_alumno']} ".
                  "({$info_asistencia['matricula']}), grupo {$info_asistencia['nombre_grupo']} de ".
                  "{$info_asistencia['nombre_grado']} turno {$info_asistencia['nombre_turno']} ".
                  "correspondiente al día " . date('d/m/Y', strtotime($info_asistencia['fecha']));
                  
    registrar_log($conexion, 'eliminar_asistencia', $detalle_log, $_SESSION['id_usuario']);
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    if ($id_alumno > 0) {
        // Si venimos de reporte_alumno, volvemos ahí
        redireccionar_con_mensaje(
            "reporte_alumno.php?id=$id_alumno", 
            "El registro de asistencia ha sido eliminado exitosamente", 
            'success'
        );
    } else {
        // Si no, volvemos al índice
        redireccionar_con_mensaje(
            "index.php", 
            "El registro de asistencia ha sido eliminado exitosamente", 
            'success'
        );
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Registrar el error
    $error_msg = "Error al eliminar registro de asistencia: " . $e->getMessage();
    error_log($error_msg);
    
    // Redirigir con mensaje de error
    if ($id_alumno > 0) {
        redireccionar_con_mensaje(
            "reporte_alumno.php?id=$id_alumno", 
            "Error: " . $e->getMessage(), 
            'danger'
        );
    } else {
        redireccionar_con_mensaje(
            "index.php", 
            "Error: " . $e->getMessage(), 
            'danger'
        );
    }
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