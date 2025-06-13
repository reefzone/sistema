<?php
/**
 * Guardar/Enviar Comunicado
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';
require_once '../../includes/mail_functions.php';

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
$id_comunicado = isset($_POST['id_comunicado']) ? intval($_POST['id_comunicado']) : 0;
$es_edicion = ($id_comunicado > 0);

$titulo = sanitizar_texto($_POST['titulo']);
$contenido = $_POST['contenido']; // No sanitizamos para permitir HTML
$id_plantilla = !empty($_POST['id_plantilla']) ? intval($_POST['id_plantilla']) : null;
$estado = sanitizar_texto($_POST['estado']);
$prioridad = sanitizar_texto($_POST['prioridad']);
$tipo_destinatario = sanitizar_texto($_POST['tipo_destinatario']);

// Determinar id_grupo y grupo_especifico según tipo de destinatario
$id_grupo = null;
$grupo_especifico = 0;

switch ($tipo_destinatario) {
    case 'todos':
        $id_grupo = null;
        $grupo_especifico = 0;
        break;
    case 'grupo':
        $id_grupo = isset($_POST['id_grupo']) ? intval($_POST['id_grupo']) : null;
        $grupo_especifico = 0;
        break;
    case 'alumnos':
        $id_grupo = null;
        $grupo_especifico = 1;
        break;
}

// Verificar programación de envío
$fecha_envio = null;
if ($estado === 'programado' || ($estado === 'enviar' && isset($_POST['programar']) && $_POST['programar'] === 'on')) {
    $fecha_programada = sanitizar_texto($_POST['fecha_programada']);
    $hora_programada = sanitizar_texto($_POST['hora_programada']);
    $fecha_envio = $fecha_programada . ' ' . $hora_programada . ':00';
    $estado = 'programado';
} elseif ($estado === 'enviar') {
    $fecha_envio = date('Y-m-d H:i:s');
    $estado = 'enviado';
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    // Manejo de archivos adjuntos
    $tiene_adjuntos = false;
    $nuevos_adjuntos = [];
    
    if (!empty($_FILES['adjuntos']['name'][0])) {
        // Directorio para guardar archivos
        $year_month = date('Y-m');
        $dir_base = "../../uploads/comunicados/adjuntos/$year_month/";
        
        // Si es nuevo comunicado, crear directorio temporal
        if (!$es_edicion) {
            $dir_temp = "../../uploads/comunicados/temp/" . uniqid();
            if (!is_dir($dir_temp)) {
                mkdir($dir_temp, 0755, true);
            }
            $upload_dir = $dir_temp;
        } else {
            // Si es edición, usar directorio del comunicado
            $upload_dir = $dir_base . $id_comunicado;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
        }
        
        // Formatos permitidos
        $formatos_permitidos = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        
        // Subir archivos
        $total_archivos = count($_FILES['adjuntos']['name']);
        
        for ($i = 0; $i < $total_archivos; $i++) {
            if ($_FILES['adjuntos']['error'][$i] === UPLOAD_ERR_OK) {
                $nombre_original = $_FILES['adjuntos']['name'][$i];
                $tipo_archivo = $_FILES['adjuntos']['type'][$i];
                $tamano_archivo = $_FILES['adjuntos']['size'][$i];
                $archivo_tmp = $_FILES['adjuntos']['tmp_name'][$i];
                
                // Verificar formato
                if (!in_array($tipo_archivo, $formatos_permitidos)) {
                    throw new Exception("El formato del archivo '$nombre_original' no está permitido.");
                }
                
                // Verificar tamaño (máximo 5MB)
                if ($tamano_archivo > 5 * 1024 * 1024) {
                    throw new Exception("El archivo '$nombre_original' excede el tamaño máximo permitido (5MB).");
                }
                
                // Generar nombre único
                $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
                $nombre_archivo = uniqid() . '.' . $extension;
                $ruta_destino = $upload_dir . '/' . $nombre_archivo;
                
                // Mover archivo
                if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                    $nuevos_adjuntos[] = [
                        'nombre_original' => $nombre_original,
                        'nombre_archivo' => $nombre_archivo,
                        'ruta' => $ruta_destino,
                        'tipo' => $tipo_archivo,
                        'tamano' => $tamano_archivo
                    ];
                    $tiene_adjuntos = true;
                } else {
                    throw new Exception("Error al subir el archivo '$nombre_original'.");
                }
            } elseif ($_FILES['adjuntos']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                throw new Exception("Error al subir un archivo: " . $_FILES['adjuntos']['error'][$i]);
            }
        }
    }
    
    // Si es edición, verificar si ya tenía adjuntos
    if ($es_edicion && !$tiene_adjuntos) {
        $query_check = "SELECT tiene_adjuntos FROM comunicados WHERE id_comunicado = ?";
        $stmt_check = $conexion->prepare($query_check);
        $stmt_check->bind_param("i", $id_comunicado);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        $tiene_adjuntos = $row_check['tiene_adjuntos'];
    }
    
    // Guardar comunicado en la base de datos
    if ($es_edicion) {
        // Actualizar comunicado existente
        $query = "UPDATE comunicados SET 
                  titulo = ?, 
                  contenido = ?, 
                  id_grupo = ?, 
                  fecha_envio = ?, 
                  estado = ?, 
                  tiene_adjuntos = ?, 
                  id_plantilla = ?, 
                  prioridad = ?, 
                  grupo_especifico = ?
                  WHERE id_comunicado = ?";
        
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("sssssissii", 
            $titulo, 
            $contenido, 
            $id_grupo, 
            $fecha_envio, 
            $estado, 
            $tiene_adjuntos, 
            $id_plantilla, 
            $prioridad, 
            $grupo_especifico,
            $id_comunicado
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar el comunicado: " . $conexion->error);
        }
        
        // Si se cambia el tipo de destinatario, eliminar destinatarios anteriores
        if ($grupo_especifico != 1) {
            $query_del = "DELETE FROM comunicados_destinatarios WHERE id_comunicado = ?";
            $stmt_del = $conexion->prepare($query_del);
            $stmt_del->bind_param("i", $id_comunicado);
            
            if (!$stmt_del->execute()) {
                throw new Exception("Error al eliminar destinatarios anteriores: " . $conexion->error);
            }
        }
    } else {
        // Insertar nuevo comunicado
        $query = "INSERT INTO comunicados (
                  titulo, 
                  contenido, 
                  id_grupo, 
                  fecha_creacion, 
                  fecha_envio, 
                  enviado_por, 
                  estado, 
                  tiene_adjuntos, 
                  id_plantilla, 
                  prioridad, 
                  grupo_especifico
                 ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("ssssisssi", 
            $titulo, 
            $contenido, 
            $id_grupo, 
            $fecha_envio, 
            $_SESSION['id_usuario'], 
            $estado, 
            $tiene_adjuntos, 
            $id_plantilla, 
            $prioridad, 
            $grupo_especifico
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al guardar el comunicado: " . $conexion->error);
        }
        
        $id_comunicado = $conexion->insert_id;
        
        // Si hay archivos adjuntos en directorio temporal, moverlos al directorio definitivo
        if (!empty($nuevos_adjuntos)) {
            $dir_final = $dir_base . $id_comunicado;
            if (!is_dir($dir_final)) {
                mkdir($dir_final, 0755, true);
            }
            
            foreach ($nuevos_adjuntos as &$adjunto) {
                $nombre_archivo = basename($adjunto['ruta']);
                $nueva_ruta = $dir_final . '/' . $nombre_archivo;
                
                // Mover archivo
                if (rename($adjunto['ruta'], $nueva_ruta)) {
                    $adjunto['ruta'] = $nueva_ruta;
                } else {
                    throw new Exception("Error al mover archivo adjunto: " . $nombre_archivo);
                }
            }
            
            // Eliminar directorio temporal
            if (is_dir($dir_temp)) {
                rmdir($dir_temp);
            }
        }
    }
    
    // Guardar archivos adjuntos en la base de datos
    if (!empty($nuevos_adjuntos)) {
        $query_adjunto = "INSERT INTO comunicados_adjuntos (
                         id_comunicado, 
                         nombre_original, 
                         nombre_archivo, 
                         ruta, 
                         tipo, 
                         tamano, 
                         fecha_subida
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt_adjunto = $conexion->prepare($query_adjunto);
        
        foreach ($nuevos_adjuntos as $adjunto) {
            $stmt_adjunto->bind_param("isssis", 
                $id_comunicado, 
                $adjunto['nombre_original'], 
                $adjunto['nombre_archivo'], 
                $adjunto['ruta'], 
                $adjunto['tipo'], 
                $adjunto['tamano']
            );
            
            if (!$stmt_adjunto->execute()) {
                throw new Exception("Error al guardar archivo adjunto: " . $conexion->error);
            }
        }
    }
    
    // Guardar destinatarios si son alumnos específicos
    if ($grupo_especifico == 1 && isset($_POST['alumnos_seleccionados']) && !empty($_POST['alumnos_seleccionados'])) {
        // Si es edición, eliminar destinatarios anteriores
        if ($es_edicion) {
            $query_del = "DELETE FROM comunicados_destinatarios WHERE id_comunicado = ?";
            $stmt_del = $conexion->prepare($query_del);
            $stmt_del->bind_param("i", $id_comunicado);
            
            if (!$stmt_del->execute()) {
                throw new Exception("Error al eliminar destinatarios anteriores: " . $conexion->error);
            }
        }
        
        // Insertar nuevos destinatarios
        $query_dest = "INSERT INTO comunicados_destinatarios (
                      id_comunicado, 
                      id_alumno, 
                      estado
                     ) VALUES (?, ?, 'pendiente')";
        
        $stmt_dest = $conexion->prepare($query_dest);
        
        foreach ($_POST['alumnos_seleccionados'] as $id_alumno) {
            $stmt_dest->bind_param("ii", 
                $id_comunicado, 
                $id_alumno
            );
            
            if (!$stmt_dest->execute()) {
                throw new Exception("Error al guardar destinatario: " . $conexion->error);
            }
        }
    }
    
    // Si el estado es 'enviado', procesar envío
    if ($estado === 'enviado') {
        // Procesar envío del comunicado (función en includes/mail_functions.php)
        $resultado_envio = enviar_comunicado($id_comunicado);
        
        if (!$resultado_envio['success']) {
            throw new Exception("Error al enviar el comunicado: " . $resultado_envio['error']);
        }
    }
    
    // Confirmar transacción
    $conexion->commit();
    
    // Redireccionar con mensaje de éxito
    if ($estado === 'enviado') {
        redireccionar_con_mensaje('index.php', 'Comunicado enviado correctamente', 'success');
    } elseif ($estado === 'programado') {
        redireccionar_con_mensaje('index.php', 'Comunicado programado correctamente', 'success');
    } else {
        redireccionar_con_mensaje('index.php', 'Comunicado guardado como borrador', 'success');
    }
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conexion->rollback();
    
    // Limpiar archivos temporales
    if (isset($dir_temp) && is_dir($dir_temp)) {
        // Eliminar archivos en el directorio temporal
        $archivos = glob($dir_temp . '/*');
        foreach ($archivos as $archivo) {
            unlink($archivo);
        }
        rmdir($dir_temp);
    }
    
    // Redireccionar con mensaje de error
    redireccionar_con_mensaje('crear.php' . ($es_edicion ? "?id=$id_comunicado" : ''), 'Error: ' . $e->getMessage(), 'danger');
}