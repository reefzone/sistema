<?php
/**
 * Actualizar Alumno
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

// Verificar ID de alumno
if (!isset($_POST['id_alumno']) || empty($_POST['id_alumno'])) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

$id_alumno = intval($_POST['id_alumno']);

// Sanitizar datos
$apellido_paterno = sanitizar_texto($_POST['apellido_paterno']);
$apellido_materno = sanitizar_texto($_POST['apellido_materno']);
$nombres = sanitizar_texto($_POST['nombres']);
$curp = sanitizar_texto($_POST['curp']);
$fecha_nacimiento = sanitizar_texto($_POST['fecha_nacimiento']);
$turno = intval($_POST['turno']);
$grado = intval($_POST['grado']);
$grupo = intval($_POST['grupo']);
$tipo_sangre = sanitizar_texto($_POST['tipo_sangre']);
$enfermedades = sanitizar_texto($_POST['enfermedades']);

// Validar datos obligatorios
if (empty($apellido_paterno) || empty($apellido_materno) || empty($nombres) || 
    empty($curp) || empty($fecha_nacimiento) || $turno <= 0 || $grado <= 0 || $grupo <= 0) {
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'Por favor complete todos los campos obligatorios', 'danger');
}

// Validar CURP
if (!es_curp_valido($curp)) {
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'El formato del CURP no es válido', 'danger');
}

// Validar fecha de nacimiento
if (!es_fecha_valida($fecha_nacimiento)) {
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'La fecha de nacimiento no es válida', 'danger');
}

// Verificar si el CURP ya existe en otro alumno
$query = "SELECT id_alumno FROM alumnos WHERE curp = ? AND id_alumno != ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("si", $curp, $id_alumno);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'Ya existe otro alumno registrado con ese CURP', 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Actualizar alumno
    $query = "UPDATE alumnos SET 
              apellido_paterno = ?, 
              apellido_materno = ?, 
              nombres = ?, 
              curp = ?, 
              fecha_nacimiento = ?, 
              id_grupo = ?, 
              id_turno = ?, 
              id_grado = ?, 
              tipo_sangre = ?, 
              enfermedades = ? 
              WHERE id_alumno = ?";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("sssssiiissi", 
        $apellido_paterno, 
        $apellido_materno, 
        $nombres, 
        $curp, 
        $fecha_nacimiento, 
        $grupo, 
        $turno, 
        $grado, 
        $tipo_sangre, 
        $enfermedades,
        $id_alumno
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar los datos del alumno: " . $stmt->error);
    }
    
    // Procesar contactos de emergencia
    if (isset($_POST['contacto_id']) && is_array($_POST['contacto_id'])) {
        $contacto_ids = $_POST['contacto_id'];
        $contacto_nombres = $_POST['contacto_nombre'];
        $contacto_telefonos = $_POST['contacto_telefono'];
        $contacto_parentescos = $_POST['contacto_parentesco'];
        $contacto_emails = isset($_POST['contacto_email']) ? $_POST['contacto_email'] : [];
        $contacto_principal = isset($_POST['contacto_principal']) ? $_POST['contacto_principal'] : [];
        
        // Resetear todos los contactos como no principales
        $query = "UPDATE contactos_emergencia SET es_principal = 0 WHERE id_alumno = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("i", $id_alumno);
        $stmt->execute();
        
        // Procesar cada contacto
        for ($i = 0; $i < count($contacto_ids); $i++) {
            $id_contacto = intval($contacto_ids[$i]);
            
            if (empty($contacto_nombres[$i]) || empty($contacto_telefonos[$i]) || empty($contacto_parentescos[$i])) {
                continue; // Saltar contactos incompletos
            }
            
            $nombre = sanitizar_texto($contacto_nombres[$i]);
            $telefono = sanitizar_texto($contacto_telefonos[$i]);
            $parentesco = sanitizar_texto($contacto_parentescos[$i]);
            $email = isset($contacto_emails[$i]) ? sanitizar_texto($contacto_emails[$i]) : '';
            $es_principal = in_array($i + 1, $contacto_principal) ? 1 : 0;
            
            if ($id_contacto > 0) {
                // Actualizar contacto existente
                $query = "UPDATE contactos_emergencia 
                         SET nombre_completo = ?, telefono = ?, parentesco = ?, 
                         email = ?, es_principal = ? 
                         WHERE id_contacto = ? AND id_alumno = ?";
                $stmt = $conexion->prepare($query);
                $stmt->bind_param("ssssiis", $nombre, $telefono, $parentesco, $email, $es_principal, $id_contacto, $id_alumno);
            } else {
                // Crear nuevo contacto
                $query = "INSERT INTO contactos_emergencia 
                         (id_alumno, nombre_completo, telefono, parentesco, email, es_principal) 
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($query);
                $stmt->bind_param("issssi", $id_alumno, $nombre, $telefono, $parentesco, $email, $es_principal);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar contacto de emergencia: " . $stmt->error);
            }
        }
    }
    
    // Procesar foto si se proporcionó
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['foto'];
        $nombre_temporal = $archivo['tmp_name'];
        $tipo = $archivo['type'];
        
        // Validar tipo de archivo
        if ($tipo !== 'image/jpeg' && $tipo !== 'image/png') {
            throw new Exception("Tipo de archivo no permitido. Solo se permiten imágenes JPG y PNG.");
        }
        
        // Validar tamaño (2MB máximo)
        if ($archivo['size'] > 2 * 1024 * 1024) {
            throw new Exception("La imagen es demasiado grande. El tamaño máximo permitido es 2MB.");
        }
        
        // Crear directorio si no existe
        $directorio = UPLOADS_DIR . 'fotos/';
        if (!file_exists($directorio)) {
            mkdir($directorio, 0777, true);
        }
        
        // Eliminar foto anterior si existe y es diferente formato
        $extension = ($tipo === 'image/jpeg') ? 'jpg' : 'png';
        $extension_anterior = ($tipo === 'image/jpeg') ? 'png' : 'jpg';
        $ruta_foto_anterior = $directorio . $id_alumno . '.' . $extension_anterior;
        
        if (file_exists($ruta_foto_anterior)) {
            unlink($ruta_foto_anterior);
        }
        
        // Guardar nueva imagen
        $nombre_archivo = $id_alumno . '.' . $extension;
        $ruta_destino = $directorio . $nombre_archivo;
        
        if (!move_uploaded_file($nombre_temporal, $ruta_destino)) {
            throw new Exception("Error al guardar la fotografía del alumno.");
        }
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Registrar acción
    registrarLog('operacion', $_SESSION['user_id'], null, 
        "Alumno actualizado: $nombres $apellido_paterno $apellido_materno (ID: $id_alumno)");
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje("ver.php?id=$id_alumno", 'Alumno actualizado correctamente', 'success');
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    $conexion->rollback();
    registrarLog('error', $_SESSION['user_id'], null, "Error al actualizar alumno: " . $e->getMessage());
    redireccionar_con_mensaje("editar.php?id=$id_alumno", 'Error: ' . $e->getMessage(), 'danger');
}