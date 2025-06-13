<?php
/**
 * Justificar Inasistencia
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

// Obtener ID de asistencia
$id_asistencia = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar ID válido
if ($id_asistencia <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de asistencia no válido', 'danger');
}

// Obtener datos de la asistencia
$query_asistencia = "SELECT a.id_asistencia, a.id_alumno, a.fecha, a.asistio, a.justificada, a.observaciones,
                    al.matricula, al.nombre, al.apellido_paterno, al.apellido_materno,
                    g.id_grupo, g.nombre_grupo, gr.nombre_grado, t.nombre_turno
                    FROM asistencia a
                    JOIN alumnos al ON a.id_alumno = al.id_alumno
                    JOIN grupos g ON al.id_grupo = g.id_grupo
                    JOIN grados gr ON g.id_grado = gr.id_grado
                    JOIN turnos t ON g.id_turno = t.id_turno
                    WHERE a.id_asistencia = ?";
$stmt_asistencia = $conexion->prepare($query_asistencia);
$stmt_asistencia->bind_param("i", $id_asistencia);
$stmt_asistencia->execute();
$result_asistencia = $stmt_asistencia->get_result();

// Verificar si existe el registro
if ($result_asistencia->num_rows == 0) {
    redireccionar_con_mensaje('index.php', 'El registro de asistencia no existe', 'danger');
}

$asistencia = $result_asistencia->fetch_assoc();

// Verificar que sea una inasistencia no justificada
if ($asistencia['asistio'] == 1) {
    redireccionar_con_mensaje('reporte_alumno.php?id=' . $asistencia['id_alumno'], 'No se puede justificar una asistencia', 'danger');
}

if ($asistencia['justificada'] == 1) {
    redireccionar_con_mensaje('reporte_alumno.php?id=' . $asistencia['id_alumno'], 'Esta inasistencia ya está justificada', 'info');
}

// Procesar el formulario de justificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    verificar_token_csrf($_POST['csrf_token']);
    
    // Obtener datos del formulario
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
    
    // Validar motivo
    if (empty($motivo)) {
        redireccionar_con_mensaje('justificar.php?id=' . $id_asistencia, 'El motivo de la justificación es obligatorio', 'danger');
    }
    
    // Sanitizar motivo
    $motivo = sanitizar_texto($motivo);
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Actualizar registro de asistencia
        $query_update = "UPDATE asistencia SET 
                         justificada = 1, 
                         observaciones = ?, 
                         registrado_por = ?, 
                         fecha_registro = NOW() 
                         WHERE id_asistencia = ?";
        $stmt_update = $conexion->prepare($query_update);
        $stmt_update->bind_param("sii", $motivo, $_SESSION['id_usuario'], $id_asistencia);
        $stmt_update->execute();
        
        // Manejar archivo de justificación si se proporcionó
        $archivo_subido = null;
        
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] == UPLOAD_ERR_OK) {
            $archivo_tmp = $_FILES['documento']['tmp_name'];
            $archivo_nombre = $_FILES['documento']['name'];
            $archivo_tipo = $_FILES['documento']['type'];
            $archivo_tamaño = $_FILES['documento']['size'];
            
            // Validar tipo de archivo (PDF, JPG, PNG)
            $tipos_permitidos = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!in_array($archivo_tipo, $tipos_permitidos)) {
                throw new Exception('Tipo de archivo no permitido. Solo se permiten PDF, JPG y PNG.');
            }
            
            // Validar tamaño (máximo 5MB)
            $tamaño_maximo = 5 * 1024 * 1024; // 5MB en bytes
            if ($archivo_tamaño > $tamaño_maximo) {
                throw new Exception('El archivo es demasiado grande. El tamaño máximo permitido es 5MB.');
            }
            
            // Crear directorio si no existe
            $directorio = '../../uploads/justificaciones/';
            if (!file_exists($directorio)) {
                mkdir($directorio, 0755, true);
            }
            
            // Generar nombre único para el archivo
            $extension = pathinfo($archivo_nombre, PATHINFO_EXTENSION);
            $nombre_unico = 'justificacion_' . $asistencia['id_alumno'] . '_' . date('Ymd', strtotime($asistencia['fecha'])) . '_' . uniqid() . '.' . $extension;
            $ruta_destino = $directorio . $nombre_unico;
            
            // Mover archivo
            if (move_uploaded_file($archivo_tmp, $ruta_destino)) {
                $archivo_subido = $nombre_unico;
                
                // Guardar referencia al archivo en la base de datos (tabla documentos_justificacion)
                $query_documento = "INSERT INTO documentos_justificacion 
                                    (id_asistencia, nombre_archivo, ruta_archivo, tipo_archivo, fecha_subida, id_usuario) 
                                    VALUES (?, ?, ?, ?, NOW(), ?)";
                $stmt_documento = $conexion->prepare($query_documento);
                $stmt_documento->bind_param("isssi", $id_asistencia, $archivo_nombre, $nombre_unico, $archivo_tipo, $_SESSION['id_usuario']);
                $stmt_documento->execute();
            } else {
                throw new Exception('Error al subir el archivo. Por favor, intente nuevamente.');
            }
        }
        
        // Registrar la acción en el log
        $detalle_log = "Se justificó la inasistencia de " . $asistencia['apellido_paterno'] . ' ' . $asistencia['apellido_materno'] . ' ' . $asistencia['nombre'] . 
                      " del día " . date('d/m/Y', strtotime($asistencia['fecha'])) . ". Motivo: $motivo";
                      
        if ($archivo_subido) {
            $detalle_log .= ". Se adjuntó documento: $archivo_subido";
        }
        
        registrar_log($conexion, 'justificar_inasistencia', $detalle_log, $_SESSION['id_usuario']);
        
        // Confirmar transacción
        $conexion->commit();
        
        // Redirigir con mensaje de éxito
        redireccionar_con_mensaje(
            'reporte_alumno.php?id=' . $asistencia['id_alumno'], 
            'La inasistencia ha sido justificada exitosamente', 
            'success'
        );
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        
        // Registrar el error
        $error_msg = "Error al justificar inasistencia: " . $e->getMessage();
        error_log($error_msg);
        
     // Redirigir con mensaje de error
        redireccionar_con_mensaje(
            'justificar.php?id=' . $id_asistencia, 
            "Error: " . $e->getMessage(), 
            'danger'
        );
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-file-medical"></i> Justificar Inasistencia</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="reporte_alumno.php?id=<?= $asistencia['id_alumno'] ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Reporte
            </a>
        </div>
    </div>
    
    <!-- Datos del alumno y la inasistencia -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> Información de la Inasistencia</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Datos del Alumno</h5>
                    <p><strong>Matrícula:</strong> <?= htmlspecialchars($asistencia['matricula']) ?></p>
                    <p><strong>Nombre:</strong> <?= htmlspecialchars($asistencia['apellido_paterno'] . ' ' . $asistencia['apellido_materno'] . ' ' . $asistencia['nombre']) ?></p>
                    <p><strong>Grupo:</strong> <?= htmlspecialchars($asistencia['nombre_grupo'] . ' - ' . $asistencia['nombre_grado'] . ' - ' . $asistencia['nombre_turno']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5>Datos de la Inasistencia</h5>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($asistencia['fecha'])) ?> (<?= date('l', strtotime($asistencia['fecha'])) ?>)</p>
                    <p><strong>Estado:</strong> <span class="badge bg-danger">Ausente sin justificar</span></p>
                    <?php if (!empty($asistencia['observaciones'])): ?>
                    <p><strong>Observaciones actuales:</strong> <?= htmlspecialchars($asistencia['observaciones']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de justificación -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-clipboard-check"></i> Registrar Justificación</h5>
        </div>
        <div class="card-body">
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                
                <div class="mb-3">
                    <label for="motivo" class="form-label">Motivo de la Justificación <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea>
                    <div class="form-text">Ingrese el motivo por el cual se justifica la inasistencia del alumno.</div>
                </div>
                
                <div class="mb-3">
                    <label for="documento" class="form-label">Documento de Respaldo</label>
                    <input class="form-control" type="file" id="documento" name="documento">
                    <div class="form-text">Formatos permitidos: PDF, JPG, PNG. Tamaño máximo: 5MB.</div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i> Registrar Justificación
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Información sobre justificaciones -->
    <div class="alert alert-info mt-4">
        <h5><i class="fas fa-info-circle me-2"></i> Información Importante</h5>
        <p>Las justificaciones de inasistencias deben ser respaldadas por documentos oficiales cuando sea posible (constancias médicas, permisos especiales, etc.).</p>
        <p>Una vez registrada la justificación, no podrá ser eliminada excepto por un administrador del sistema.</p>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
// Función para registrar acción en el log del sistema
function registrar_log($conexion, $accion, $detalle, $id_usuario) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO logs_sistema (fecha, accion, detalle, ip, id_usuario) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ssssi", $fecha, $accion, $detalle, $ip, $id_usuario);
    $stmt->execute();
}
?>