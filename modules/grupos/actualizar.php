<?php
/**
 * Actualizar Grupo
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
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
$id_turno = isset($_POST['turno']) ? intval($_POST['turno']) : 0;
$id_grado = isset($_POST['grado']) ? intval($_POST['grado']) : 0;
$nombre_grupo = isset($_POST['nombre_grupo']) ? trim($_POST['nombre_grupo']) : '';
$ciclo_escolar = isset($_POST['ciclo_escolar']) ? trim($_POST['ciclo_escolar']) : '';
$color_credencial = isset($_POST['color_credencial']) ? trim($_POST['color_credencial']) : '#FFFFFF';

// Sanitizar datos
$nombre_grupo = sanitizar_texto($nombre_grupo);
$ciclo_escolar = sanitizar_texto($ciclo_escolar);
$color_credencial = preg_match('/#[a-fA-F0-9]{6}/', $color_credencial) ? $color_credencial : '#FFFFFF';

// Validar datos obligatorios
$errores = [];

if ($id_grupo <= 0) {
    $errores[] = "ID de grupo no válido";
}

if ($id_turno <= 0) {
    $errores[] = "Debe seleccionar un turno válido";
}

if ($id_grado <= 0) {
    $errores[] = "Debe seleccionar un grado válido";
}

if (empty($nombre_grupo)) {
    $errores[] = "El nombre del grupo es obligatorio";
} elseif (!preg_match('/^[A-Z0-9]{1,10}$/', $nombre_grupo)) {
    $errores[] = "El nombre del grupo solo debe contener letras (A-Z) y números, máximo 10 caracteres";
}

if (empty($ciclo_escolar)) {
    $errores[] = "El ciclo escolar es obligatorio";
} elseif (!preg_match('/^\d{4}-\d{4}$/', $ciclo_escolar)) {
    $errores[] = "El formato del ciclo escolar debe ser AAAA-AAAA (Ejemplo: 2024-2025)";
} else {
    $años = explode('-', $ciclo_escolar);
    if (intval($años[1]) !== intval($años[0]) + 1) {
        $errores[] = "El segundo año del ciclo escolar debe ser consecutivo al primero";
    }
}

// Si hay errores, redirigir de vuelta al formulario
if (!empty($errores)) {
    $errores_str = implode('. ', $errores);
    redireccionar_con_mensaje("editar.php?id={$id_grupo}", $errores_str, 'danger');
}

// Obtener datos actuales del grupo para comparar
$query = "SELECT id_turno, id_grado, nombre_grupo, ciclo_escolar FROM grupos WHERE id_grupo = ? AND activo = 1";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_grupo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El grupo no existe o ha sido eliminado', 'danger');
}

$grupo_actual = $result->fetch_assoc();

// Verificar si ya existe un grupo con el mismo nombre, grado y turno en el ciclo actual (excluyendo el actual)
if ($nombre_grupo != $grupo_actual['nombre_grupo'] || $id_grado != $grupo_actual['id_grado'] || 
    $id_turno != $grupo_actual['id_turno'] || $ciclo_escolar != $grupo_actual['ciclo_escolar']) {
    
    $query = "SELECT id_grupo FROM grupos WHERE nombre_grupo = ? AND id_grado = ? AND id_turno = ? AND ciclo_escolar = ? AND activo = 1 AND id_grupo != ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("siisi", $nombre_grupo, $id_grado, $id_turno, $ciclo_escolar, $id_grupo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        redireccionar_con_mensaje("editar.php?id={$id_grupo}", "Ya existe un grupo con el nombre '{$nombre_grupo}' para el grado y turno seleccionados en el ciclo escolar {$ciclo_escolar}", 'danger');
    }
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Actualizar el grupo
    $query = "UPDATE grupos SET id_turno = ?, id_grado = ?, nombre_grupo = ?, ciclo_escolar = ?, color_credencial = ? WHERE id_grupo = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("iisssi", $id_turno, $id_grado, $nombre_grupo, $ciclo_escolar, $color_credencial, $id_grupo);
    $stmt->execute();
    
    // Construir mensaje de cambios
    $cambios = [];
    
    if ($grupo_actual['nombre_grupo'] != $nombre_grupo) {
        $cambios[] = "Nombre del grupo de '{$grupo_actual['nombre_grupo']}' a '{$nombre_grupo}'";
    }
    
    if ($grupo_actual['id_grado'] != $id_grado) {
        $grado_anterior = obtener_nombre_grado($grupo_actual['id_grado']);
        $grado_nuevo = obtener_nombre_grado($id_grado);
        $cambios[] = "Grado de '{$grado_anterior}' a '{$grado_nuevo}'";
    }
    
    if ($grupo_actual['id_turno'] != $id_turno) {
        $turno_anterior = obtener_nombre_turno($grupo_actual['id_turno']);
        $turno_nuevo = obtener_nombre_turno($id_turno);
        $cambios[] = "Turno de '{$turno_anterior}' a '{$turno_nuevo}'";
    }
    
    if ($grupo_actual['ciclo_escolar'] != $ciclo_escolar) {
        $cambios[] = "Ciclo escolar de '{$grupo_actual['ciclo_escolar']}' a '{$ciclo_escolar}'";
    }
    
    $detalle_cambios = count($cambios) > 0 ? implode(', ', $cambios) : "Se actualizó el color del grupo";
    
    // Registrar la acción en el log
    $detalle_log = "Se actualizó el grupo ID: {$id_grupo}. Cambios: {$detalle_cambios}";
    registrar_log($conexion, 'actualizar_grupo', $detalle_log, $_SESSION['id_usuario']);
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    redireccionar_con_mensaje("ver.php?id={$id_grupo}", "El grupo ha sido actualizado exitosamente", 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Registrar el error
    $error_msg = "Error al actualizar el grupo: " . $e->getMessage();
    error_log($error_msg);
    
    // Redirigir con mensaje de error
    redireccionar_con_mensaje("editar.php?id={$id_grupo}", "Error al actualizar el grupo. Por favor, intente nuevamente.", 'danger');
}

// Funciones auxiliares para obtener nombres
function obtener_nombre_grado($id_grado) {
    global $conexion;
    $query = "SELECT nombre_grado FROM grados WHERE id_grado = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_grado);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['nombre_grado'] : 'Desconocido';
}

function obtener_nombre_turno($id_turno) {
    global $conexion;
    $query = "SELECT nombre_turno FROM turnos WHERE id_turno = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_turno);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['nombre_turno'] : 'Desconocido';
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