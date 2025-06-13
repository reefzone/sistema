<?php
/**
 * Guardar Entrada en Historial Escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/historial_functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
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

// Obtener y validar datos del formulario
$id_alumno = isset($_POST['id_alumno']) ? intval($_POST['id_alumno']) : 0;
$tipo_registro = isset($_POST['tipo_registro']) ? sanitizar_texto($_POST['tipo_registro']) : '';
$categoria = isset($_POST['categoria']) ? sanitizar_texto($_POST['categoria']) : '';
$fecha_evento = isset($_POST['fecha_evento']) ? sanitizar_texto($_POST['fecha_evento']) : '';
$titulo = isset($_POST['titulo']) ? sanitizar_texto($_POST['titulo']) : '';
$descripcion = isset($_POST['descripcion']) ? sanitizar_texto($_POST['descripcion']) : '';
$calificacion = isset($_POST['calificacion']) && $_POST['calificacion'] !== '' ? floatval($_POST['calificacion']) : null;
$relevancia = isset($_POST['relevancia']) ? sanitizar_texto($_POST['relevancia']) : 'normal';

// Validar datos
if ($id_alumno <= 0) {
    redireccionar_con_mensaje('registrar.php', 'ID de alumno no válido', 'danger');
}

if (empty($tipo_registro) || !in_array($tipo_registro, ['academico', 'asistencia', 'conducta', 'reconocimiento', 'observacion'])) {
    redireccionar_con_mensaje('registrar.php?id='.$id_alumno, 'Tipo de registro no válido', 'danger');
}

if (empty($fecha_evento) || empty($titulo) || empty($descripcion) || empty($categoria)) {
    redireccionar_con_mensaje('registrar.php?id='.$id_alumno, 'Todos los campos marcados con * son obligatorios', 'danger');
}

// Validar fecha (no futura)
$fecha_actual = date('Y-m-d');
if ($fecha_evento > $fecha_actual) {
    redireccionar_con_mensaje('registrar.php?id='.$id_alumno, 'La fecha del evento no puede ser futura', 'danger');
}

// Validar calificación si aplica
if ($tipo_registro === 'academico' && !is_null($calificacion)) {
    if ($calificacion < 0 || $calificacion > 10) {
        redireccionar_con_mensaje('registrar.php?id='.$id_alumno, 'La calificación debe estar entre 0 y 10', 'danger');
    }
}

// Verificar si el alumno existe
$query_alumno = "SELECT id_alumno FROM alumnos WHERE id_alumno = ? AND activo = 1";
$stmt_alumno = $conexion->prepare($query_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();
$result_alumno = $stmt_alumno->get_result();

if ($result_alumno->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El alumno no existe o no está activo', 'danger');
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Determinar si hay archivos adjuntos
    $tiene_adjunto = isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0]) ? 1 : 0;
    
    // Insertar registro en historial_escolar
    $query = "INSERT INTO historial_escolar (
                id_alumno, tipo_registro, fecha_evento, titulo, descripcion, 
                calificacion, categoria, tiene_adjunto, relevancia, 
                registrado_por, fecha_registro
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param(
        "issssdsisi",
        $id_alumno, $tipo_registro, $fecha_evento, $titulo, $descripcion,
        $calificacion, $categoria, $tiene_adjunto, $relevancia,
        $_SESSION['id_usuario']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Error al guardar el registro: " . $conexion->error);
    }
    
    $id_historial = $conexion->insert_id;
    
    // Procesar archivos adjuntos
    if ($tiene_adjunto) {
        // Crear estructura de directorios
        $anio = date('Y');
        $mes = date('m');
        $directorio = "../../uploads/historial/$anio/$mes/$id_historial";
        
        if (!file_exists($directorio)) {
            if (!mkdir($directorio, 0755, true)) {
                throw new Exception("Error al crear el directorio para archivos adjuntos");
            }
        }
        
        // Procesar cada archivo
        $archivos = $_FILES['archivos'];
        $total_archivos = count($archivos['name']);
        
        for ($i = 0; $i < $total_archivos; $i++) {
            if ($archivos['error'][$i] === UPLOAD_ERR_OK) {
                $nombre_original = $archivos['name'][$i];
                $tipo = $archivos['type'][$i];
                $tamano = $archivos['size'][$i];
                $tmp_name = $archivos['tmp_name'][$i];
                
                // Validar tipo de archivo
                $extensiones_permitidas = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                
                if (!in_array(strtolower($extension), $extensiones_permitidas)) {
                    throw new Exception("El archivo '$nombre_original' tiene un formato no permitido");
                }
                
                // Validar tamaño (5MB máximo)
                $tamano_maximo = 5 * 1024 * 1024; // 5MB
                if ($tamano > $tamano_maximo) {
                    throw new Exception("El archivo '$nombre_original' excede el tamaño máximo permitido (5MB)");
                }
                
                // Generar nombre único para el archivo
                $nombre_archivo = md5(uniqid(rand(), true)) . '.' . $extension;
                $ruta_archivo = "$directorio/$nombre_archivo";
                $ruta_relativa = "uploads/historial/$anio/$mes/$id_historial/$nombre_archivo";
                
                // Mover el archivo
                if (!move_uploaded_file($tmp_name, $ruta_archivo)) {
                    throw new Exception("Error al guardar el archivo '$nombre_original'");
                }
                
                // Registrar el archivo en la base de datos
                $query_adjunto = "INSERT INTO historial_adjuntos (
                                    id_historial, nombre_original, nombre_archivo, 
                                    ruta, tipo, tamano, fecha_subida
                                  ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                                  
                $stmt_adjunto = $conexion->prepare($query_adjunto);
                $stmt_adjunto->bind_param(
                    "issssi",
                    $id_historial, $nombre_original, $nombre_archivo,
                    $ruta_relativa, $tipo, $tamano
                );
                
                if (!$stmt_adjunto->execute()) {
                    throw new Exception("Error al registrar el archivo adjunto: " . $conexion->error);
                }
            } elseif ($archivos['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                // Si hay error y no es porque no se seleccionó archivo
                throw new Exception("Error al subir el archivo: " . obtener_error_subida($archivos['error'][$i]));
            }
        }
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje("ver.php?id=$id_alumno", "Registro añadido correctamente al historial", 'success');
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Redireccionar con mensaje de error
    redireccionar_con_mensaje("registrar.php?id=$id_alumno", "Error: " . $e->getMessage(), 'danger');
}

// Función para obtener mensaje de error de subida de archivos
function obtener_error_subida($codigo_error) {
    switch ($codigo_error) {
        case UPLOAD_ERR_INI_SIZE:
            return "El archivo excede el tamaño máximo permitido por PHP";
        case UPLOAD_ERR_FORM_SIZE:
            return "El archivo excede el tamaño máximo permitido por el formulario";
        case UPLOAD_ERR_PARTIAL:
            return "El archivo fue subido parcialmente";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "No se encuentra el directorio temporal";
        case UPLOAD_ERR_CANT_WRITE:
            return "Error al escribir el archivo en el disco";
        case UPLOAD_ERR_EXTENSION:
            return "Una extensión de PHP detuvo la subida del archivo";
        default:
            return "Error desconocido al subir el archivo";
    }
}