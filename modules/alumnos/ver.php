<?php
/**
 * Ver Alumno
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

$id_alumno = intval($_GET['id']);

// Obtener datos del alumno
$query = "SELECT a.*, g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
          FROM alumnos a 
          JOIN grupos g ON a.id_grupo = g.id_grupo 
          JOIN grados gr ON g.id_grado = gr.id_grado 
          JOIN turnos t ON g.id_turno = t.id_turno 
          WHERE a.id_alumno = ? AND a.activo = 1";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El alumno solicitado no existe o no está activo', 'danger');
}

$alumno = $result->fetch_assoc();

// Obtener contactos de emergencia
$query = "SELECT * FROM contactos_emergencia WHERE id_alumno = ? ORDER BY es_principal DESC";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$contactos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-user-graduate"></i> Detalles del Alumno</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
            <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
            <a href="editar.php?id=<?= $id_alumno ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Editar
            </a>
            <?php endif; ?>
            <div class="btn-group">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-file-alt"></i> Documentos
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="../credenciales/generar.php?id=<?= $id_alumno ?>" target="_blank">
                        <i class="fas fa-id-card"></i> Generar Credencial
                    </a></li>
                    <li><a class="dropdown-item" href="../historial_escolar/reporte.php?id=<?= $id_alumno ?>" target="_blank">
                        <i class="fas fa-history"></i> Historial Escolar
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Información personal -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user"></i> Información Personal
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php
                        // Verificar si existe la foto
                        $ruta_foto = UPLOADS_DIR . 'fotos/' . $id_alumno . '.jpg';
                        $ruta_foto_png = UPLOADS_DIR . 'fotos/' . $id_alumno . '.png';
                        $tiene_foto = file_exists($ruta_foto) || file_exists($ruta_foto_png);
                        $url_foto = $tiene_foto ? 
                            (file_exists($ruta_foto) ? BASE_URL . 'uploads/fotos/' . $id_alumno . '.jpg' : BASE_URL . 'uploads/fotos/' . $id_alumno . '.png') : 
                            BASE_URL . 'assets/images/user-placeholder.png';
                        ?>
                        <img src="<?= $url_foto ?>" alt="Foto de <?= htmlspecialchars($alumno['nombres']) ?>" 
                             class="img-fluid rounded-circle" style="max-width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    
                    <h4 class="text-center mb-3">
                        <?= htmlspecialchars($alumno['nombres'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']) ?>
                    </h4>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="fas fa-id-card"></i> CURP:</h6>
                        <p><?= htmlspecialchars($alumno['curp']) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="fas fa-calendar-alt"></i> Fecha de Nacimiento:</h6>
                        <p><?= formatear_fecha($alumno['fecha_nacimiento']) ?></p>
                    </div>
                    
                    <div>
                        <h6 class="fw-bold"><i class="fas fa-calendar-check"></i> Fecha de Registro:</h6>
                        <p><?= formatear_fecha($alumno['fecha_registro'], true) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Información escolar y médica -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-school"></i> Información Escolar
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="fas fa-graduation-cap"></i> Grado:</h6>
                        <p><?= htmlspecialchars($alumno['nombre_grado']) ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="fas fa-users"></i> Grupo:</h6>
                        <p><?= htmlspecialchars($alumno['nombre_grupo']) ?></p>
                    </div>
                    
                    <div>
                        <h6 class="fw-bold"><i class="fas fa-clock"></i> Turno:</h6>
                        <p><?= htmlspecialchars($alumno['nombre_turno']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-heartbeat"></i> Información Médica
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="fas fa-tint"></i> Tipo de Sangre:</h6>
                        <p><?= !empty($alumno['tipo_sangre']) ? htmlspecialchars($alumno['tipo_sangre']) : 'No registrado' ?></p>
                    </div>
                    
                    <div>
                        <h6 class="fw-bold"><i class="fas fa-notes-medical"></i> Enfermedades o Condiciones Médicas:</h6>
                        <p><?= !empty($alumno['enfermedades']) ? nl2br(htmlspecialchars($alumno['enfermedades'])) : 'Ninguna registrada' ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contactos de emergencia -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-phone-alt"></i> Contactos de Emergencia
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($contactos)): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay contactos de emergencia registrados.
                    </div>
                    <?php else: ?>
                        <?php foreach ($contactos as $index => $contacto): ?>
                        <div class="card mb-3 <?= $contacto['es_principal'] ? 'border-primary' : '' ?>">
                            <div class="card-body">
                                <h6 class="card-title d-flex align-items-center">
                                    <?= htmlspecialchars($contacto['nombre_completo']) ?>
                                    <?php if ($contacto['es_principal']): ?>
                                    <span class="badge bg-primary ms-2">Principal</span>
                                    <?php endif; ?>
                                </h6>
                                <p class="card-text">
                                    <strong>Parentesco:</strong> <?= htmlspecialchars($contacto['parentesco']) ?><br>
                                    <strong>Teléfono:</strong> <?= htmlspecialchars($contacto['telefono']) ?><br>
                                    <?php if (!empty($contacto['email'])): ?>
                                    <strong>Email:</strong> <?= htmlspecialchars($contacto['email']) ?>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="mt-2">
                                        <a href="tel:<?= preg_replace('/[^0-9]/', '', $contacto['telefono']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-phone"></i> Llamar
                                        </a>
                                        <?php if (!empty($contacto['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($contacto['email']) ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-envelope"></i> Email
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                    <div class="text-center mt-3">
                        <a href="editar.php?id=<?= $id_alumno ?>#contactos" class="btn btn-outline-warning">
                            <i class="fas fa-edit"></i> Editar Contactos
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Botones de acciones rápidas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt"></i> Acciones Rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <a href="../asistencia/registrar.php?id=<?= $id_alumno ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-calendar-check fa-2x mb-2"></i><br>
                                Registrar Asistencia
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../historial_escolar/ver.php?id=<?= $id_alumno ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-history fa-2x mb-2"></i><br>
                                Ver Historial
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../comunicados/enviar.php?id=<?= $id_alumno ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-envelope fa-2x mb-2"></i><br>
                                Enviar Comunicado
                            </a>
                        </div>
                        <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                        <div class="col-md-3 mb-3">
                            <a href="../seguimiento_emocional/crear.php?id=<?= $id_alumno ?>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-heart fa-2x mb-2"></i><br>
                                Registrar Seguimiento
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>