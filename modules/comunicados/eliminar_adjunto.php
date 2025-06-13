<?php
/**
 * Eliminar Archivo Adjunto
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

// Verificar método POST y token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método no permitido', 'danger');
}

if (!verificar_token_csrf($_POST['csrf_token'])) {
    redireccionar_con_mensaje('index.php', 'Token de seguridad inválido', 'danger');
}

// Obtener datos del formulario
$id_adjunto = isset($_POST['id_adjunto']) ? intval($_POST['id_adjunto']) : 0;
$id_comunicado = isset($_POST['id_comunicado']) ? intval($_POST['id_comunicado']) : 0;

if ($id_adjunto <= 0 || $id_comunicado <= 0) {
    redireccionar_con_mensaje('crear.php?id=' . $id_comunicado, 'Datos inválidos', 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Verificar si el adjunto existe y obtener ruta del archivo
    $query_check = "SELECT ca.*, c.enviado_por, c.estado 
                   FROM comunicados_adjuntos ca
                   JOIN comunicados c ON ca.id_comunicado = c.id_comunicado
                   WHERE ca.id_adjunto = ? AND ca.id_comunicado = ?";
    $stmt_check = $conexion->prepare($query_check);
    $stmt_check->bind_param("ii", $id_adjunto, $id_comunicado);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        throw new Exception("El archivo adjunto no existe o no pertenece al comunicado seleccionado.");
    }
    
    $adjunto = $result_check->fetch_assoc();
    
    // Verificar permisos (solo el creador o superadmin pueden eliminar)
    if ($_SESSION['tipo_usuario'] != 'superadmin' && $adjunto['enviado_por'] != $_SESSION['id_usuario']) {
        throw new Exception("No tienes permisos para eliminar este archivo adjunto.");
    }
    
    // Verificar que el comunicado esté en estado borrador
    if ($adjunto['estado'] != 'borrador') {
        throw new Exception("Solo se pueden eliminar archivos adjuntos de comunicados en estado borrador.");
    }
    
    // Eliminar archivo físico
    if (file_exists($adjunto['ruta'])) {
        if (!unlink($adjunto['ruta'])) {
            throw new Exception("Error al eliminar archivo físico.");
        }
    }
    
    // Eliminar registro de la base de datos
    $query = "DELETE FROM comunicados_adjuntos WHERE id_adjunto = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_adjunto);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar registro de adjunto: " . $conexion->error);
    }
    
    // Verificar si quedan adjuntos para este comunicado
    $query_check_remaining = "SELECT COUNT(*) as total FROM comunicados_adjuntos WHERE id_comunicado = ?";
    $stmt_check_remaining = $conexion->prepare($query_check_remaining);
    $stmt_check_remaining->bind_param("i", $id_comunicado);
    $stmt_check_remaining->execute();
    $result_check_remaining = $stmt_check_remaining->get_result();
    $row_check_remaining = $result_check_remaining->fetch_assoc();
    
    // Actualizar campo tiene_adjuntos si ya no hay adjuntos
    if ($row_check_remaining['total'] == 0) {
        $query_update = "UPDATE comunicados SET tiene_adjuntos = 0 WHERE id_comunicado = ?";
        $stmt_update = $conexion->prepare($query_update);
        $stmt_update->bind_param("i", $id_comunicado);
        
        if (!$stmt_update->execute()) {
            throw new Exception("Error al actualizar estado de adjuntos: " . $conexion->error);
        }
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje('crear.php?id=' . $id_comunicado, 'Archivo adjunto eliminado correctamente', 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Redireccionar con mensaje de error
    redireccionar_con_mensaje('crear.php?id=' . $id_comunicado, 'Error: ' . $e->getMessage(), 'danger');
}