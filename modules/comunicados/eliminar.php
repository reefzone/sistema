<?php
/**
 * Eliminar Comunicado
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin puede eliminar)
if ($_SESSION['tipo_usuario'] != 'superadmin') {
    redireccionar_con_mensaje('index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar método POST y token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redireccionar_con_mensaje('index.php', 'Método no permitido', 'danger');
}

if (!verificar_token_csrf($_POST['csrf_token'])) {
    redireccionar_con_mensaje('index.php', 'Token de seguridad inválido', 'danger');
}

// Obtener ID del comunicado
$id_comunicado = isset($_POST['id_comunicado']) ? intval($_POST['id_comunicado']) : 0;

if ($id_comunicado <= 0) {
    redireccionar_con_mensaje('index.php', 'Comunicado no válido', 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Verificar si el comunicado existe
    $query_check = "SELECT titulo FROM comunicados WHERE id_comunicado = ? AND eliminado = 0";
    $stmt_check = $conexion->prepare($query_check);
    $stmt_check->bind_param("i", $id_comunicado);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        throw new Exception("El comunicado no existe o ya ha sido eliminado.");
    }
    
    $row_check = $result_check->fetch_assoc();
    $titulo_comunicado = $row_check['titulo'];
    
    // Eliminar lógicamente el comunicado (marcar como eliminado)
    $query = "UPDATE comunicados SET eliminado = 1 WHERE id_comunicado = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_comunicado);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar el comunicado: " . $conexion->error);
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje('index.php', 'Comunicado "' . $titulo_comunicado . '" eliminado correctamente', 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Redireccionar con mensaje de error
    redireccionar_con_mensaje('index.php', 'Error: ' . $e->getMessage(), 'danger');
}