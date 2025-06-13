<?php
/**
 * Generación de Credencial Individual - DISEÑO PERFECTO
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';
require_once './pdf_html.php'; // Nuevo generador perfecto

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar existencia de ID de alumno
if (!isset($_GET['id_alumno']) || !is_numeric($_GET['id_alumno'])) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

$id_alumno = intval($_GET['id_alumno']);

// Obtener datos del alumno
$query = "SELECT a.*, 
          a.nombres as nombre,
          CONCAT(a.apellido_paterno, ' ', a.apellido_materno) as apellido,
          a.curp as matricula,
          g.nombre_grupo, g.color_credencial, g.ciclo_escolar, 
          g.id_grupo, gr.nombre_grado, t.nombre_turno, 
          (SELECT ruta_foto FROM alumnos_fotos WHERE id_alumno = a.id_alumno ORDER BY fecha_subida DESC LIMIT 1) as ruta_foto
          FROM alumnos a
          JOIN grupos g ON a.id_grupo = g.id_grupo
          JOIN grados gr ON g.id_grado = gr.id_grado
          JOIN turnos t ON g.id_turno = t.id_turno
          WHERE a.id_alumno = ? AND a.activo = 1";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redireccionar_con_mensaje('index.php', 'El alumno no existe o no está activo', 'danger');
}

$alumno = $result->fetch_assoc();

// Obtener configuración de plantilla predeterminada
$query_config = "SELECT * FROM credenciales_config WHERE es_default = 1 LIMIT 1";
$result_config = $conexion->query($query_config);

if ($result_config->num_rows == 0) {
    redireccionar_con_mensaje('plantilla.php', 'No hay una plantilla predeterminada configurada', 'warning');
}

$config = $result_config->fetch_assoc();

// Preparar directorio para guardar la credencial
$year = date('Y');

// Usar ruta absoluta en lugar de relativa
$uploads_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/credenciales/individuales/' . $year . '/';

// Crear directorio si no existe (automático)
if (!file_exists($uploads_dir)) {
    if (!mkdir($uploads_dir, 0755, true)) {
        redireccionar_con_mensaje('index.php', 'Error al crear directorio para credenciales', 'danger');
    }
}

// Nombre del archivo PDF
$nombre_archivo = "credencial_{$alumno['matricula']}_{$year}.pdf";
$ruta_pdf = $uploads_dir . $nombre_archivo;

// Para mostrar en el navegador, usar ruta relativa
$ruta_pdf_web = "/uploads/credenciales/individuales/{$year}/" . $nombre_archivo;

try {
    // ✨ USAR EL NUEVO GENERADOR ULTRA PROFESIONAL ✨
    require_once './pdf_ultra_pro.php';
    $credencial = new CredencialUltraProfesional($conexion, $config);
    $resultado = $credencial->generarCredencialPerfecta($alumno, $ruta_pdf);
    
    if (!$resultado) {
        throw new Exception('Error al generar la credencial profesional');
    }
    
    // Usar user_id que sí existe en la sesión
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id === null) {
        redireccionar_con_mensaje('index.php', 'Error de sesión. Por favor, inicie sesión nuevamente.', 'danger');
    }

    // Registrar en la base de datos
    $query_insert = "INSERT INTO credenciales_generadas 
                    (id_alumno, id_grupo, tipo, ruta_archivo, fecha_generacion, generado_por) 
                    VALUES (?, ?, 'individual', ?, NOW(), ?)";
    $stmt_insert = $conexion->prepare($query_insert);
    $stmt_insert->bind_param("iisi", $id_alumno, $alumno['id_grupo'], $ruta_pdf_web, $user_id);
    $stmt_insert->execute();
    $id_generacion = $conexion->insert_id;

    // Registrar en el log del sistema
    $detalle_log = "Se generó credencial individual para el alumno {$alumno['nombre']} {$alumno['apellido']} (Matrícula: {$alumno['matricula']})";
    registrarLog('operacion', $user_id, null, $detalle_log);

    $credencial_generada = true;
    $mensaje_exito = "¡Credencial generada con diseño perfecto! 🎨";
    
} catch (Exception $e) {
    $credencial_generada = false;
    $mensaje_error = "Error al generar la credencial: " . $e->getMessage();
    
    // Log del error
    error_log("Error generando credencial: " . $e->getMessage());
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-id-card"></i> Credencial Individual - Diseño Perfecto</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($credencial_generada)): ?>
        <?php if ($credencial_generada): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <strong>¡Éxito!</strong> <?= $mensaje_exito ?>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error:</strong> <?= $mensaje_error ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($credencial_generada) && $credencial_generada): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user"></i> Datos del Alumno
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($alumno['ruta_foto'])): ?>
                        <img src="<?= $alumno['ruta_foto'] ?>" alt="Foto de <?= htmlspecialchars($alumno['nombre']) ?>" 
                             class="img-thumbnail" style="max-height: 150px;">
                        <?php else: ?>
                        <div class="no-photo-placeholder">
                            <i class="fas fa-user-circle fa-5x text-secondary"></i>
                            <p class="text-muted">Sin fotografía</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Nombre:</strong> <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Matrícula:</strong> <?= htmlspecialchars($alumno['matricula']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Grupo:</strong> <?= htmlspecialchars($alumno['nombre_grupo']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Grado:</strong> <?= htmlspecialchars($alumno['nombre_grado']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Turno:</strong> <?= htmlspecialchars($alumno['nombre_turno']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Ciclo Escolar:</strong> <?= htmlspecialchars($alumno['ciclo_escolar']) ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-gradient" style="background: linear-gradient(45deg, #3b82f6, #8b5cf6);">
                    <h5 class="card-title mb-0 text-white">
                        <i class="fas fa-sparkles"></i> Credencial Generada - Diseño Ultra Profesional
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="credencial-preview mb-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>¡Credencial generada con diseño perfecto!</strong><br>
                            El PDF mantiene exactamente el mismo diseño que viste en el editor visual.
                        </div>
                        
                        <!-- Preview simulado -->
                        <div class="border rounded p-4 bg-light mb-3">
                            <div class="d-flex align-items-center justify-content-center" style="height: 200px;">
                                <div class="text-center">
                                    <i class="fas fa-id-card fa-5x text-primary mb-3"></i>
                                    <h5>Credencial Profesional</h5>
                                    <p class="text-muted">Diseño idéntico al editor visual</p>
                                    <small class="badge bg-success">✨ Diseño Perfecto</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="btn-group btn-lg">
                        <a href="<?= $ruta_pdf_web ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-eye"></i> Ver PDF
                        </a>
                        <a href="<?= $ruta_pdf_web ?>" class="btn btn-success" download>
                            <i class="fas fa-download"></i> Descargar
                        </a>
                        <button class="btn btn-info" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-success">
                            <i class="fas fa-check-circle"></i>
                            Credencial generada con diseño ultra profesional manteniendo toda la calidad visual.
                        </small>
                        <a href="generar.php?id_alumno=<?= $id_alumno ?>&regenerar=1" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-redo"></i> Regenerar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<style>
.bg-gradient {
    background: linear-gradient(45deg, #3b82f6, #8b5cf6) !important;
}

.no-photo-placeholder {
    padding: 20px;
    text-align: center;
    background: #f8f9fa;
    border-radius: 8px;
}

.credencial-preview {
    background: linear-gradient(145deg, #f8fafc, #e2e8f0);
    border-radius: 15px;
    padding: 20px;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 16px;
}

.btn-lg .btn {
    margin: 0 5px;
    border-radius: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>