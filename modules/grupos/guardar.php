<?php
/**
 * Guardar Grupo
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
    redireccionar_con_mensaje('crear.php', $errores_str, 'danger');
}

// Verificar si ya existe un grupo con el mismo nombre, grado y turno en el ciclo actual
$query = "SELECT id_grupo FROM grupos WHERE nombre_grupo = ? AND id_grado = ? AND id_turno = ? AND ciclo_escolar = ? AND activo = 1";
$stmt = $conexion->prepare($query);
$stmt->bind_param("siis", $nombre_grupo, $id_grado, $id_turno, $ciclo_escolar);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    redireccionar_con_mensaje('crear.php', "Ya existe un grupo con el nombre '{$nombre_grupo}' para el grado y turno seleccionados en el ciclo escolar {$ciclo_escolar}", 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Insertar el nuevo grupo
    $query = "INSERT INTO grupos (id_turno, id_grado, nombre_grupo, ciclo_escolar, color_credencial, activo) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("iisss", $id_turno, $id_grado, $nombre_grupo, $ciclo_escolar, $color_credencial);
    $stmt->execute();
    
    $id_grupo = $conexion->insert_id;
    
    // Registrar la acción en el log
    $detalle_log = "Se creó el grupo {$nombre_grupo} para el grado " . obtener_nombre_grado($id_grado) . 
                   " turno " . obtener_nombre_turno($id_turno) . " ciclo escolar {$ciclo_escolar}";
    
    registrar_log($conexion, 'crear_grupo', $detalle_log, $_SESSION['id_usuario']);
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redirigir con mensaje de éxito
    redireccionar_con_mensaje('index.php', "El grupo {$nombre_grupo} ha sido creado exitosamente", 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Registrar el error
    $error_msg = "Error al crear el grupo: " . $e->getMessage();
    error_log($error_msg);
    
    // Redirigir con mensaje de error
    redireccionar_con_mensaje('crear.php', "Error al crear el grupo. Por favor, intente nuevamente.", 'danger');
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