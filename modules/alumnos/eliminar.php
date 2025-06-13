<?php
/**
 * Eliminar Alumno
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin puede eliminar alumnos)
if ($_SESSION['tipo_usuario'] != 'superadmin') {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a esta sección', 'danger');
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método de solicitud no válido', 'danger');
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verificar_token_csrf($_POST['csrf_token'])) {
    redireccionar_con_mensaje('index.php', 'Error de seguridad. Por favor, intente nuevamente', 'danger');
}

// Verificar ID de alumno
if (!isset($_POST['id_alumno']) || empty($_POST['id_alumno'])) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

$id_alumno = intval($_POST['id_alumno']);

// Verificar que el alumno exista
$query = "SELECT nombres, apellido_paterno, apellido_materno FROM alumnos WHERE id_alumno = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El alumno no existe', 'danger');
}

$alumno = $result->fetch_assoc();
$nombre_completo = $alumno['nombres'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'];

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Eliminar contactos de emergencia
    $query = "DELETE FROM contactos_emergencia WHERE id_alumno = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar contactos de emergencia: " . $stmt->error);
    }
    
    // Marcar asistencias como eliminadas o desactivarlas
    $query = "UPDATE asistencia SET eliminado = 1 WHERE id_alumno = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar registros de asistencia: " . $stmt->error);
    }
    
    // Marcar historiales como eliminados
    $query = "UPDATE historial_escolar SET eliminado = 1 WHERE id_alumno = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar historiales: " . $stmt->error);
    }
    
    // Marcar seguimientos emocionales como eliminados
    $query = "UPDATE seguimiento_emocional SET eliminado = 1 WHERE id_alumno = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar seguimientos emocionales: " . $stmt->error);
    }
    
    // Marcar alumno como inactivo en lugar de eliminarlo físicamente
    $query = "UPDATE alumnos SET activo = 0 WHERE id_alumno = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al desactivar alumno: " . $stmt->error);
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Registrar acción
    registrarLog('operacion', $_SESSION['user_id'], null, 
        "Alumno eliminado: $nombre_completo (ID: $id_alumno)");
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje('index.php', 'Alumno eliminado correctamente', 'success');
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conexion->rollback();
    registrarLog('error', $_SESSION['user_id'], null, "Error al eliminar alumno: " . $e->getMessage());
    redireccionar_con_mensaje('index.php', 'Error: ' . $e->getMessage(), 'danger');
}