<?php
/**
 * Eliminar Entrada de Historial Escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/historial_functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if ($_SESSION['tipo_usuario'] !== 'superadmin') {
    redireccionar_con_mensaje('index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método no permitido', 'danger');
}

// Verificar token CSRF
if (!verificar_token_csrf($_POST['csrf_token'])) {
    redireccionar_con_mensaje('index.php', 'Token de seguridad inválido', 'danger');
}

// Obtener los IDs necesarios
$id_historial = isset($_POST['id_historial']) ? intval($_POST['id_historial']) : 0;
$id_alumno = isset($_POST['id_alumno']) ? intval($_POST['id_alumno']) : 0;

if ($id_historial <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de registro no válido', 'danger');
}

// Verificar si el registro existe
$query_verificar = "SELECT id_alumno FROM historial_escolar 
                   WHERE id_historial = ? AND eliminado = 0";
$stmt_verificar = $conexion->prepare($query_verificar);
$stmt_verificar->bind_param("i", $id_historial);
$stmt_verificar->execute();
$result_verificar = $stmt_verificar->get_result();

if ($result_verificar->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El registro no existe o ya ha sido eliminado', 'danger');
}

// Si no se proporcionó ID de alumno, obtenerlo del registro
if ($id_alumno <= 0) {
    $id_alumno = $result_verificar->fetch_assoc()['id_alumno'];
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Marcar el registro como eliminado (eliminación lógica)
    $query = "UPDATE historial_escolar SET 
              eliminado = 1, 
              modificado_por = ?, 
              fecha_modificacion = NOW() 
              WHERE id_historial = ?";
              
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ii", $_SESSION['id_usuario'], $id_historial);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar el registro: " . $conexion->error);
    }
    
    // Marcar adjuntos como eliminados también
    $query_adjuntos = "UPDATE historial_adjuntos SET 
                      eliminado = 1 
                      WHERE id_historial = ?";
                      
    $stmt_adjuntos = $conexion->prepare($query_adjuntos);
    $stmt_adjuntos->bind_param("i", $id_historial);
    
    if (!$stmt_adjuntos->execute()) {
        throw new Exception("Error al actualizar los adjuntos: " . $conexion->error);
    }
    
    // Registrar esta acción en un log de auditoría
    $query_log = "INSERT INTO log_acciones (
                    id_usuario, 
                    accion, 
                    tabla_afectada, 
                    id_registro, 
                    detalles, 
                    fecha_hora
                  ) VALUES (?, 'eliminar', 'historial_escolar', ?, ?, NOW())";
                  
    $detalles = "Eliminación de registro en historial para alumno ID: $id_alumno";
    
    $stmt_log = $conexion->prepare($query_log);
    $stmt_log->bind_param("iis", $_SESSION['id_usuario'], $id_historial, $detalles);
    $stmt_log->execute();
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje("ver.php?id=$id_alumno", "Registro eliminado correctamente", 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Redireccionar con mensaje de error
    redireccionar_con_mensaje("ver.php?id=$id_alumno", "Error: " . $e->getMessage(), 'danger');
}