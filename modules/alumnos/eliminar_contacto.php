<?php
/**
 * Eliminar Contacto de Emergencia
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin y organizador pueden editar alumnos)
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
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

// Verificar ID de contacto y alumno
if (!isset($_POST['id_contacto']) || empty($_POST['id_contacto']) || 
    !isset($_POST['id_alumno']) || empty($_POST['id_alumno'])) {
    redireccionar_con_mensaje('index.php', 'ID no válido', 'danger');
}

$id_contacto = intval($_POST['id_contacto']);
$id_alumno = intval($_POST['id_alumno']);

// Verificar que el contacto pertenezca al alumno
$query = "SELECT c.*, a.nombres, a.apellido_paterno, a.apellido_materno 
          FROM contactos_emergencia c 
          JOIN alumnos a ON c.id_alumno = a.id_alumno 
          WHERE c.id_contacto = ? AND c.id_alumno = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("ii", $id_contacto, $id_alumno);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'El contacto no existe o no pertenece al alumno', 'danger');
}

$contacto = $result->fetch_assoc();

// Verificar que no sea el único contacto principal
if ($contacto['es_principal'] == 1) {
    $query = "SELECT COUNT(*) as total FROM contactos_emergencia WHERE id_alumno = ? AND es_principal = 1";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['total'] <= 1) {
        redireccionar_con_mensaje("editar.php?id=$id_alumno", 'No se puede eliminar el único contacto principal', 'danger');
    }
}

// Eliminar contacto
$query = "DELETE FROM contactos_emergencia WHERE id_contacto = ? AND id_alumno = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("ii", $id_contacto, $id_alumno);

if ($stmt->execute()) {
    $nombre_alumno = $contacto['nombres'] . ' ' . $contacto['apellido_paterno'] . ' ' . $contacto['apellido_materno'];
    registrarLog('operacion', $_SESSION['user_id'], null, 
        "Contacto eliminado: {$contacto['nombre_completo']} del alumno $nombre_alumno (ID: $id_alumno)");
    
    redireccionar_con_mensaje("editar.php?id=$id_alumno#contactos", 'Contacto eliminado correctamente', 'success');
} else {
    registrarLog('error', $_SESSION['user_id'], null, 
        "Error al eliminar contacto: " . $conexion->error);
    
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'Error al eliminar el contacto', 'danger');
}