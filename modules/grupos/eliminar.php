<?php
/**
 * Eliminar Grupo
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin puede eliminar grupos)
if ($_SESSION['tipo_usuario'] !== 'superadmin') {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método de solicitud no válido', 'danger');
}

// Verificar token CSRF
verificar_token_csrf($_POST['csrf_token']);

// Obtener ID del grupo
$id_grupo = isset($_POST['id_grupo']) ? intval($_POST['id_grupo']) : 0;

if ($id_grupo <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de grupo no válido', 'danger');
}

// Verificar si el grupo existe
$query = "SELECT g.*, gr.nombre_grado, t.nombre_turno 
          FROM grupos g 
          JOIN grados gr ON g.id_grado = gr.id_grado 
          JOIN turnos t ON g.id_turno = t.id_turno 
          WHERE g.id_grupo = ? AND g.activo = 1";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_grupo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El grupo no existe o ya ha sido eliminado', 'danger');
}

$grupo = $result->fetch_assoc();

// Verificar si hay alumnos activos asignados al grupo
$query_alumnos = "SELECT COUNT(*) as total FROM alumnos WHERE id_grupo = ? AND activo = 1";
$stmt_alumnos = $conexion->prepare($query_alumnos);
$stmt_alumnos->bind_param("i", $id_grupo);
$stmt_alumnos->execute();
$result_alumnos = $stmt_alumnos->get_result();
$row_alumnos = $result_alumnos->fetch_assoc();

if ($row_alumnos['total'] > 0) {
    redireccionar_con_mensaje('index.php', "No se puede eliminar el grupo porque tiene {$row_alumnos['total']} alumnos activos asignados. Reasigne los alumnos a otro grupo antes de eliminar.", 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Eliminar el grupo (desactivar)
    $query = "UPDATE grupos SET activo = 0 WHERE id_grupo = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_grupo);
    $stmt->execute();
    
    // Registrar la acción en el log
    $detalle_log = "Se eliminó el grupo {$grupo['nombre_grupo']} del grado {$grupo['nombre_grado']} turno {$grupo['nombre_turno']} ciclo escolar {$grupo['ciclo_escolar']}";
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha = date('Y-m-d H:i:s');
    $accion = 'eliminar_grupo';
    
    $query_log = "INSERT INTO logs_sistema (fecha, accion, detalle, ip, id_usuario) VALUES (?, ?, ?, ?, ?)";
    $stmt_log = $conexion->prepare($query_log);
    $stmt_log->bind_param("ssssi", $fecha, $accion, $detalle_log, $ip, $_SESSION['id_usuario']);
    $stmt_log->execute();
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    redireccionar_con_mensaje('index.php', "El grupo {$grupo['nombre_grupo']} ha sido eliminado exitosamente", 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Registrar el error
    $error_msg = "Error al eliminar el grupo: " . $e->getMessage();
    error_log($error_msg);
    
    // Redirigir con mensaje de error
    redireccionar_con_mensaje('index.php', "Error al eliminar el grupo. Por favor, intente nuevamente.", 'danger');
}