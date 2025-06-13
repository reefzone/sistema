<?php
/**
 * Registrar Asistencia Individual
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador', 'profesor'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Obtener id del alumno
$id_alumno = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener fecha (por defecto hoy)
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Validar que la fecha no sea futura
if ($fecha > date('Y-m-d')) {
    $fecha = date('Y-m-d');
}

// Verificar si el alumno existe
if ($id_alumno <= 0) {
    redireccionar_con_mensaje('index.php', 'Alumno no válido', 'danger');
}

// Obtener datos del alumno
$query_alumno = "SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, 
                g.id_grupo, g.nombre_grupo, gr.nombre_grado, t.nombre_turno, g.ciclo_escolar
                FROM alumnos a 
                JOIN grupos g ON a.id_grupo = g.id_grupo 
                JOIN grados gr ON g.id_grado = gr.id_grado 
                JOIN turnos t ON g.id_turno = t.id_turno 
                WHERE a.id_alumno = ? AND a.activo = 1";
$stmt_alumno = $conexion->prepare($query_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();
$result_alumno = $stmt_alumno->get_result();

// Verificar si el alumno existe
if ($result_alumno->num_rows == 0) {
    redireccionar_con_mensaje('index.php', 'El alumno no existe o no está activo', 'danger');
}

$alumno = $result_alumno->fetch_assoc();

// Verificar si ya existe registro de asistencia para este alumno en esta fecha
$query_asistencia = "SELECT id_asistencia, asistio, justificada, observaciones, 
                    DATE_FORMAT(fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro,
                    (SELECT CONCAT(nombre, ' ', apellido_paterno) FROM usuarios WHERE id_usuario = registrado_por) as registrado_por
                    FROM asistencia 
                    WHERE id_alumno = ? AND fecha = ?";
$stmt_asistencia = $conexion->prepare($query_asistencia);
$stmt_asistencia->bind_param("is", $id_alumno, $fecha);
$stmt_asistencia->execute();
$result_asistencia = $stmt_asistencia->get_result();

$asistencia_actual = null;
$asistencia_registrada = false;

if ($result_asistencia->num_rows > 0) {
    $asistencia_actual = $result_asistencia->fetch_assoc();
    $asistencia_registrada = true;
}

// Obtener historial de asistencia reciente
$query_historial = "SELECT a.id_asistencia, a.fecha, 
                   a.asistio, a.justificada, a.observaciones,
                   DATE_FORMAT(a.fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro,
                   (SELECT CONCAT(nombre, ' ', apellido_paterno) FROM usuarios WHERE id_usuario = a.registrado_por) as registrado_por
                   FROM asistencia a
                   WHERE a.id_alumno = ?
                   ORDER BY a.fecha DESC
                   LIMIT 10";
$stmt_historial = $conexion->prepare($query_historial);
$stmt_historial->bind_param("i", $id_alumno);
$stmt_historial->execute();
$result_historial = $stmt_historial->get_result();

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-user-check"></i> Registro de Asistencia Individual</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Pase de Lista
            </a>
            <a href="reporte_alumno.php?id=<?= $id_alumno ?>" class="btn btn-info">
                <i class="fas fa-chart-bar"></i> Reporte Completo
            </a>
        </div>
    </div>
    
    <!-- Datos del alumno -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-user-graduate"></i> Datos del Alumno</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Matrícula:</strong> <?= htmlspecialchars($alumno['matricula']) ?></p>
                    <p><strong>Nombre:</strong> <?= htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'] . ' ' . $alumno['nombre']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Grupo:</strong> <?= htmlspecialchars($alumno['nombre_grupo']) ?></p>
                    <p><strong>Grado/Turno:</strong> <?= htmlspecialchars($alumno['nombre_grado'] . ' - ' . $alumno['nombre_turno']) ?></p>
                    <p><strong>Ciclo Escolar:</strong> <?= htmlspecialchars($alumno['ciclo_escolar']) ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de registro de asistencia -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-clipboard-check"></i> Registro de Asistencia para el día <?= date('d/m/Y', strtotime($fecha)) ?></h5>
        </div>
        <div class="card-body">
            <form action="guardar.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <input type="hidden" name="id_grupo" value="<?= $alumno['id_grupo'] ?>">
                <input type="hidden" name="alumnos[<?= $id_alumno ?>][id_alumno]" value="<?= $id_alumno ?>">
                <?php if ($asistencia_registrada): ?>
                <input type="hidden" name="alumnos[<?= $id_alumno ?>][id_asistencia]" value="<?= $asistencia_actual['id_asistencia'] ?>">
                <?php endif; ?>
                
                <div class="row mb-3">
                    <label for="fecha" class="col-sm-2 col-form-label">Fecha:</label>
                    <div class="col-sm-4">
                        <input type="date" class="form-control" id="fecha" name="fecha" 
                               value="<?= $fecha ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label class="col-sm-2 col-form-label">Asistencia:</label>
                    <div class="col-sm-10">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="asistio" 
                                   name="alumnos[<?= $id_alumno ?>][asistio]" value="1"
                                   <?= !$asistencia_registrada || ($asistencia_registrada && $asistencia_actual['asistio']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="asistio">
                                <span class="badge <?= !$asistencia_registrada || ($asistencia_registrada && $asistencia_actual['asistio']) ? 'bg-success' : 'bg-danger' ?>">
                                    <?= !$asistencia_registrada || ($asistencia_registrada && $asistencia_actual['asistio']) ? 'Presente' : 'Ausente' ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3" id="seccion-justificacion" <?= !$asistencia_registrada || ($asistencia_registrada && $asistencia_actual['asistio']) ? 'style="display:none;"' : '' ?>>
                    <label class="col-sm-2 col-form-label">Justificada:</label>
                    <div class="col-sm-10">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="justificada" 
                                   name="alumnos[<?= $id_alumno ?>][justificada]" value="1"
                                   <?= $asistencia_registrada && !$asistencia_actual['asistio'] && $asistencia_actual['justificada'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="justificada">
                                <span class="badge <?= $asistencia_registrada && !$asistencia_actual['asistio'] && $asistencia_actual['justificada'] ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                    <?= $asistencia_registrada && !$asistencia_actual['asistio'] && $asistencia_actual['justificada'] ? 'Justificada' : 'Sin justificar' ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3" id="seccion-observaciones" <?= !$asistencia_registrada || ($asistencia_registrada && $asistencia_actual['asistio']) ? 'style="display:none;"' : '' ?>>
                    <label for="observaciones" class="col-sm-2 col-form-label">Observaciones:</label>
                    <div class="col-sm-10">
                        <textarea class="form-control" id="observaciones" name="alumnos[<?= $id_alumno ?>][observaciones]" rows="3"><?= $asistencia_registrada ? htmlspecialchars($asistencia_actual['observaciones']) : '' ?></textarea>
                        <div class="form-text">Ingrese motivo de inasistencia o información adicional.</div>
                    </div>
                </div>
                
                <?php if ($asistencia_registrada): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> 
                    Registro actual: <?= $asistencia_actual['asistio'] ? '<span class="badge bg-success">Presente</span>' : 
                                          ($asistencia_actual['justificada'] ? '<span class="badge bg-warning text-dark">Ausente Justificado</span>' : 
                                           '<span class="badge bg-danger">Ausente</span>') ?>
                    <br>
                    <small>Registrado por: <?= htmlspecialchars($asistencia_actual['registrado_por']) ?> el <?= $asistencia_actual['fecha_registro'] ?></small>
                </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $asistencia_registrada ? 'Actualizar Registro' : 'Guardar Asistencia' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Historial de asistencia -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-history"></i> Historial de Asistencia Reciente</h5>
        </div>
        <div class="card-body">
            <?php if ($result_historial->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-secondary">
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Registrado por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($registro = $result_historial->fetch_assoc()): ?>
                        <tr class="<?= !$registro['asistio'] ? ($registro['justificada'] ? 'table-warning' : 'table-danger') : '' ?>">
                            <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                            <td>
                                <?php if ($registro['asistio']): ?>
                                <span class="badge bg-success">Presente</span>
                                <?php else: ?>
                                <span class="badge <?= $registro['justificada'] ? 'bg-warning text-dark' : 'bg-danger' ?>">
                                    <?= $registro['justificada'] ? 'Ausente Justificado' : 'Ausente' ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($registro['observaciones']) ?></td>
                            <td><?= htmlspecialchars($registro['registrado_por']) ?><br>
                                <small class="text-muted"><?= $registro['fecha_registro'] ?></small>
                            </td>
                            <td>
                                <a href="registrar.php?id=<?= $id_alumno ?>&fecha=<?= $registro['fecha'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar" 
                                        data-id="<?= $registro['id_asistencia'] ?>" 
                                        data-fecha="<?= date('d/m/Y', strtotime($registro['fecha'])) ?>">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No hay registros previos de asistencia para este alumno.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="eliminarModal" tabindex="-1" aria-labelledby="eliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="eliminarModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar el registro de asistencia del día <strong id="fecha-asistencia"></strong>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="form-eliminar" action="eliminar_asistencia.php" method="post">
                    <input type="hidden" name="id_asistencia" id="id-asistencia">
                    <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Control de checkbox de asistencia
    const checkAsistio = document.getElementById('asistio');
    const checkJustificada = document.getElementById('justificada');
    const seccionJustificacion = document.getElementById('seccion-justificacion');
    const seccionObservaciones = document.getElementById('seccion-observaciones');
    const labelAsistio = checkAsistio.nextElementSibling.querySelector('.badge');
    
    checkAsistio.addEventListener('change', function() {
        if (this.checked) {
            // Marcado como presente
            labelAsistio.textContent = 'Presente';
            labelAsistio.classList.remove('bg-danger');
            labelAsistio.classList.add('bg-success');
            
            // Ocultar secciones de justificación y observaciones
            seccionJustificacion.style.display = 'none';
            seccionObservaciones.style.display = 'none';
            
            // Limpiar campos
            if (checkJustificada) checkJustificada.checked = false;
            document.getElementById('observaciones').value = '';
        } else {
            // Marcado como ausente
            labelAsistio.textContent = 'Ausente';
            labelAsistio.classList.remove('bg-success');
            labelAsistio.classList.add('bg-danger');
            
            // Mostrar secciones de justificación y observaciones
            seccionJustificacion.style.display = '';
            seccionObservaciones.style.display = '';
        }
    });
    
    // Control de checkbox de justificación
    if (checkJustificada) {
        const labelJustificada = checkJustificada.nextElementSibling.querySelector('.badge');
        
        checkJustificada.addEventListener('change', function() {
            if (this.checked) {
                // Justificación activada
                labelJustificada.textContent = 'Justificada';
                labelJustificada.classList.remove('bg-secondary');
                labelJustificada.classList.add('bg-warning', 'text-dark');
            } else {
                // Justificación desactivada
                labelJustificada.textContent = 'Sin justificar';
                labelJustificada.classList.remove('bg-warning', 'text-dark');
                labelJustificada.classList.add('bg-secondary');
            }
        });
    }
    
    // Configurar modal de eliminación
    const btnsEliminar = document.querySelectorAll('.btn-eliminar');
    const modalEliminar = document.getElementById('eliminarModal');
    const fechaAsistencia = document.getElementById('fecha-asistencia');
    const idAsistencia = document.getElementById('id-asistencia');
    
    btnsEliminar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const fecha = this.getAttribute('data-fecha');
            
            idAsistencia.value = id;
            fechaAsistencia.textContent = fecha;
            
            const modal = new bootstrap.Modal(modalEliminar);
            modal.show();
        });
    });
});
</script>