<?php
/**
 * Pase de Lista por Grupo
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

// Obtener fecha actual (por defecto)
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
// Validar que la fecha no sea futura
if ($fecha_seleccionada > date('Y-m-d')) {
    $fecha_seleccionada = date('Y-m-d');
}

// Obtener grupo seleccionado
$id_grupo_seleccionado = isset($_GET['id_grupo']) ? intval($_GET['id_grupo']) : 0;

// Filtros adicionales
$filtro_grado = isset($_GET['grado']) ? intval($_GET['grado']) : 0;
$filtro_turno = isset($_GET['turno']) ? intval($_GET['turno']) : 0;

// Obtener turnos para filtro
$turnos = [];
$query_turnos = "SELECT id_turno, nombre_turno FROM turnos ORDER BY id_turno";
$result_turnos = $conexion->query($query_turnos);
while ($row = $result_turnos->fetch_assoc()) {
    $turnos[$row['id_turno']] = $row['nombre_turno'];
}

// Obtener grados para filtro
$grados = [];
$query_grados = "SELECT id_grado, nombre_grado FROM grados ORDER BY id_grado";
$result_grados = $conexion->query($query_grados);
while ($row = $result_grados->fetch_assoc()) {
    $grados[$row['id_grado']] = $row['nombre_grado'];
}

// Obtener grupos filtrados
$grupos = [];
$query_grupos = "SELECT g.id_grupo, CONCAT(g.nombre_grupo, ' - ', gr.nombre_grado, ' - ', t.nombre_turno, ' (', g.ciclo_escolar, ')') as nombre_completo 
                FROM grupos g 
                JOIN grados gr ON g.id_grado = gr.id_grado 
                JOIN turnos t ON g.id_turno = t.id_turno 
                WHERE g.activo = 1";

// Aplicar filtros si están seleccionados
if ($filtro_grado > 0) {
    $query_grupos .= " AND g.id_grado = $filtro_grado";
}
if ($filtro_turno > 0) {
    $query_grupos .= " AND g.id_turno = $filtro_turno";
}

$query_grupos .= " ORDER BY t.id_turno, gr.id_grado, g.nombre_grupo";
$result_grupos = $conexion->query($query_grupos);
while ($row = $result_grupos->fetch_assoc()) {
    $grupos[$row['id_grupo']] = $row['nombre_completo'];
}

// Arreglo para almacenar alumnos del grupo seleccionado
$alumnos = [];
$asistencia_registrada = false;
$total_alumnos = 0;

// Si hay un grupo seleccionado, obtener sus alumnos
if ($id_grupo_seleccionado > 0) {
    $query_alumnos = "SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno 
                    FROM alumnos a 
                    WHERE a.id_grupo = ? AND a.activo = 1 
                    ORDER BY a.apellido_paterno, a.apellido_materno, a.nombre";
    $stmt_alumnos = $conexion->prepare($query_alumnos);
    $stmt_alumnos->bind_param("i", $id_grupo_seleccionado);
    $stmt_alumnos->execute();
    $result_alumnos = $stmt_alumnos->get_result();
    
    // Verificar si ya existe registro de asistencia para este grupo en la fecha seleccionada
    $query_check = "SELECT COUNT(*) as total FROM asistencia 
                   WHERE id_alumno IN (SELECT id_alumno FROM alumnos WHERE id_grupo = ?) 
                   AND fecha = ?";
    $stmt_check = $conexion->prepare($query_check);
    $stmt_check->bind_param("is", $id_grupo_seleccionado, $fecha_seleccionada);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    
    // Si hay registros, marcar que la asistencia ya fue registrada
    if ($row_check['total'] > 0) {
        $asistencia_registrada = true;
    }
    
    // Obtener los alumnos con su estado de asistencia (si existe)
    while ($alumno = $result_alumnos->fetch_assoc()) {
        $total_alumnos++;
        
        // Verificar si ya existe registro de asistencia para este alumno en esta fecha
        if ($asistencia_registrada) {
            $query_asistencia = "SELECT id_asistencia, asistio, justificada, observaciones 
                               FROM asistencia 
                               WHERE id_alumno = ? AND fecha = ?";
            $stmt_asistencia = $conexion->prepare($query_asistencia);
            $stmt_asistencia->bind_param("is", $alumno['id_alumno'], $fecha_seleccionada);
            $stmt_asistencia->execute();
            $result_asistencia = $stmt_asistencia->get_result();
            $asistencia = $result_asistencia->fetch_assoc();
            
            if ($asistencia) {
                $alumno['asistio'] = $asistencia['asistio'];
                $alumno['justificada'] = $asistencia['justificada'];
                $alumno['observaciones'] = $asistencia['observaciones'];
                $alumno['id_asistencia'] = $asistencia['id_asistencia'];
            } else {
                $alumno['asistio'] = 1; // Por defecto presente
                $alumno['justificada'] = 0;
                $alumno['observaciones'] = '';
            }
        } else {
            $alumno['asistio'] = 1; // Por defecto presente
            $alumno['justificada'] = 0;
            $alumno['observaciones'] = '';
        }
        
        $alumnos[] = $alumno;
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-clipboard-list"></i> Pase de Lista</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="reporte.php" class="btn btn-info">
                <i class="fas fa-chart-bar"></i> Reportes de Asistencia
            </a>
        </div>
    </div>
    
    <!-- Filtros y selección de grupo -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Selección de Grupo y Fecha</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="turno" class="form-label">Turno</label>
                    <select class="form-select" id="turno" name="turno">
                        <option value="0">Todos los turnos</option>
                        <?php foreach ($turnos as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $filtro_turno == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="grado" class="form-label">Grado</label>
                    <select class="form-select" id="grado" name="grado">
                        <option value="0">Todos los grados</option>
                        <?php foreach ($grados as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $filtro_grado == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" 
                           value="<?= $fecha_seleccionada ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Selección de grupo -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-users-class"></i> Grupo para Pase de Lista</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <input type="hidden" name="turno" value="<?= $filtro_turno ?>">
                <input type="hidden" name="grado" value="<?= $filtro_grado ?>">
                <input type="hidden" name="fecha" value="<?= $fecha_seleccionada ?>">
                
                <div class="col-md-8">
                    <label for="id_grupo" class="form-label">Seleccionar Grupo</label>
                    <select class="form-select" id="id_grupo" name="id_grupo" required>
                        <option value="">-- Seleccione un grupo --</option>
                        <?php foreach ($grupos as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $id_grupo_seleccionado == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-clipboard-check"></i> Cargar Lista
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($id_grupo_seleccionado > 0): ?>
    <!-- Lista de alumnos para registrar asistencia -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-check"></i> Pase de Lista - <?= htmlspecialchars($grupos[$id_grupo_seleccionado] ?? '') ?>
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary"><?= $total_alumnos ?> alumnos</span>
                    <span class="badge <?= $asistencia_registrada ? 'bg-success' : 'bg-warning' ?>">
                        <?= $asistencia_registrada ? 'Asistencia registrada' : 'Asistencia no registrada' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($alumnos)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No hay alumnos registrados en este grupo.
            </div>
            <?php else: ?>
            <form action="guardar.php" method="post" id="form-asistencia">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <input type="hidden" name="id_grupo" value="<?= $id_grupo_seleccionado ?>">
                <input type="hidden" name="fecha" value="<?= $fecha_seleccionada ?>">
                
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="todos-presentes" checked>
                        <label class="form-check-label" for="todos-presentes">
                            <strong>Todos Presentes</strong> (desmarcar para registrar ausencias)
                        </label>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th style="width: 60px">#</th>
                                <th style="width: 120px">Matrícula</th>
                                <th>Nombre Completo</th>
                                <th style="width: 150px">Asistencia</th>
                                <th style="width: 150px">Justificada</th>
                                <th style="width: 200px">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $index => $alumno): ?>
                            <tr class="<?= (!$alumno['asistio']) ? ($alumno['justificada'] ? 'table-warning' : 'table-danger') : '' ?>">
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($alumno['matricula']) ?></td>
                                <td>
                                    <?= htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'] . ' ' . $alumno['nombre']) ?>
                                    <input type="hidden" name="alumnos[<?= $alumno['id_alumno'] ?>][id_alumno]" value="<?= $alumno['id_alumno'] ?>">
                                    <?php if (isset($alumno['id_asistencia'])): ?>
                                    <input type="hidden" name="alumnos[<?= $alumno['id_alumno'] ?>][id_asistencia]" value="<?= $alumno['id_asistencia'] ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input check-asistencia" type="checkbox" 
                                               id="asistio-<?= $alumno['id_alumno'] ?>" 
                                               name="alumnos[<?= $alumno['id_alumno'] ?>][asistio]" value="1"
                                               <?= $alumno['asistio'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="asistio-<?= $alumno['id_alumno'] ?>">
                                            <span class="badge <?= $alumno['asistio'] ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $alumno['asistio'] ? 'Presente' : 'Ausente' ?>
                                            </span>
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input check-justificacion" type="checkbox" 
                                               id="justificada-<?= $alumno['id_alumno'] ?>" 
                                               name="alumnos[<?= $alumno['id_alumno'] ?>][justificada]" value="1"
                                               <?= $alumno['justificada'] ? 'checked' : '' ?>
                                               <?= $alumno['asistio'] ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="justificada-<?= $alumno['id_alumno'] ?>">
                                            <span class="badge <?= $alumno['justificada'] ? 'bg-warning text-dark' : 'bg-secondary' ?>">
                                                <?= $alumno['justificada'] ? 'Justificada' : 'Sin justificar' ?>
                                            </span>
                                        </label>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="alumnos[<?= $alumno['id_alumno'] ?>][observaciones]" 
                                           value="<?= htmlspecialchars($alumno['observaciones'] ?? '') ?>"
                                           <?= $alumno['asistio'] ? 'disabled' : '' ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> <?= $asistencia_registrada ? 'Actualizar Registro' : 'Guardar Asistencia' ?>
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Historial de asistencia del grupo -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-history"></i> Historial de Asistencia Reciente</h5>
        </div>
        <div class="card-body">
            <?php
            // Obtener últimos 5 días con registros de asistencia para este grupo
            $query_historial = "SELECT DISTINCT a.fecha, 
                               COUNT(*) as total_alumnos,
                               SUM(CASE WHEN a.asistio = 0 THEN 1 ELSE 0 END) as ausentes,
                               SUM(CASE WHEN a.asistio = 0 AND a.justificada = 1 THEN 1 ELSE 0 END) as justificados
                               FROM asistencia a
                               JOIN alumnos al ON a.id_alumno = al.id_alumno
                               WHERE al.id_grupo = ?
                               GROUP BY a.fecha
                               ORDER BY a.fecha DESC
                               LIMIT 5";
            $stmt_historial = $conexion->prepare($query_historial);
            $stmt_historial->bind_param("i", $id_grupo_seleccionado);
            $stmt_historial->execute();
            $result_historial = $stmt_historial->get_result();
            
            if ($result_historial->num_rows > 0):
            ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-secondary">
                        <tr>
                            <th>Fecha</th>
                            <th>Total Alumnos</th>
                            <th>Presentes</th>
                            <th>Ausentes</th>
                            <th>Justificados</th>
                            <th>% Asistencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result_historial->fetch_assoc()): 
                            $presentes = $row['total_alumnos'] - $row['ausentes'];
                            $porcentaje = round(($presentes / $row['total_alumnos']) * 100, 1);
                        ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                            <td><?= $row['total_alumnos'] ?></td>
                            <td class="text-success"><?= $presentes ?></td>
                            <td class="text-danger"><?= $row['ausentes'] ?></td>
                            <td class="text-warning"><?= $row['justificados'] ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= $porcentaje >= 90 ? 'bg-success' : ($porcentaje >= 75 ? 'bg-info' : 'bg-danger') ?>" 
                                         role="progressbar" style="width: <?= $porcentaje ?>%;" 
                                         aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?= $porcentaje ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="?id_grupo=<?= $id_grupo_seleccionado ?>&fecha=<?= $row['fecha'] ?>&turno=<?= $filtro_turno ?>&grado=<?= $filtro_grado ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No hay registros previos de asistencia para este grupo.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Control de "Todos Presentes"
    const checkTodosPresentes = document.getElementById('todos-presentes');
    const checksAsistencia = document.querySelectorAll('.check-asistencia');
    const checksJustificacion = document.querySelectorAll('.check-justificacion');
    const camposObservaciones = document.querySelectorAll('input[name$="[observaciones]"]');
    
    if (checkTodosPresentes) {
        checkTodosPresentes.addEventListener('change', function() {
            if (this.checked) {
                // Marcar todos como presentes
                checksAsistencia.forEach(check => {
                    check.checked = true;
                    
                    // Actualizar filas y deshabilitar justificaciones y observaciones
                    const tr = check.closest('tr');
                    tr.classList.remove('table-danger', 'table-warning');
                    
                    const idAlumno = check.id.split('-')[1];
                    const checkJustificacion = document.getElementById('justificada-' + idAlumno);
                    const labelAsistencia = check.nextElementSibling.querySelector('.badge');
                    
                    labelAsistencia.textContent = 'Presente';
                    labelAsistencia.classList.remove('bg-danger');
                    labelAsistencia.classList.add('bg-success');
                    
                    if (checkJustificacion) {
                        checkJustificacion.checked = false;
                        checkJustificacion.disabled = true;
                        const labelJustificacion = checkJustificacion.nextElementSibling.querySelector('.badge');
                        labelJustificacion.textContent = 'Sin justificar';
                        labelJustificacion.classList.remove('bg-warning', 'text-dark');
                        labelJustificacion.classList.add('bg-secondary');
                    }
                    
                    // Limpiar y deshabilitar observaciones
                    const inputObservacion = tr.querySelector('input[name$="[observaciones]"]');
                    if (inputObservacion) {
                        inputObservacion.value = '';
                        inputObservacion.disabled = true;
                    }
                });
            } else {
                // Permitir marcar ausencias manualmente
                checksAsistencia.forEach(check => {
                    // Solo habilitamos los controles, sin cambiar estado
                    const idAlumno = check.id.split('-')[1];
                    const checkJustificacion = document.getElementById('justificada-' + idAlumno);
                    if (checkJustificacion && !check.checked) {
                        checkJustificacion.disabled = false;
                    }
                    
                    const inputObservacion = check.closest('tr').querySelector('input[name$="[observaciones]"]');
                    if (inputObservacion && !check.checked) {
                        inputObservacion.disabled = false;
                    }
                });
            }
        });
    }
    
    // Control individual de asistencia
    checksAsistencia.forEach(check => {
        check.addEventListener('change', function() {
            const tr = this.closest('tr');
            const idAlumno = this.id.split('-')[1];
            const checkJustificacion = document.getElementById('justificada-' + idAlumno);
            const labelAsistencia = this.nextElementSibling.querySelector('.badge');
            const inputObservacion = tr.querySelector('input[name$="[observaciones]"]');
            
            if (this.checked) {
                // Marcado como presente
                tr.classList.remove('table-danger', 'table-warning');
                labelAsistencia.textContent = 'Presente';
                labelAsistencia.classList.remove('bg-danger');
                labelAsistencia.classList.add('bg-success');
                
                if (checkJustificacion) {
                    checkJustificacion.checked = false;
                    checkJustificacion.disabled = true;
                    const labelJustificacion = checkJustificacion.nextElementSibling.querySelector('.badge');
                    labelJustificacion.textContent = 'Sin justificar';
                    labelJustificacion.classList.remove('bg-warning', 'text-dark');
                    labelJustificacion.classList.add('bg-secondary');
                }
                
                if (inputObservacion) {
                    inputObservacion.value = '';
                    inputObservacion.disabled = true;
                }
            } else {
                // Marcado como ausente
                tr.classList.add('table-danger');
                labelAsistencia.textContent = 'Ausente';
                labelAsistencia.classList.remove('bg-success');
                labelAsistencia.classList.add('bg-danger');
                
                if (checkJustificacion) {
                    checkJustificacion.disabled = false;
                }
                
                if (inputObservacion) {
                    inputObservacion.disabled = false;
                }
                
                // Si todos están marcados manualmente, desactivar "Todos presentes"
                let todosPresentes = true;
                checksAsistencia.forEach(c => {
                    if (!c.checked) todosPresentes = false;
                });
                
                if (checkTodosPresentes && !todosPresentes) {
                    checkTodosPresentes.checked = false;
                }
            }
        });
    });
    
    // Control de justificaciones
    checksJustificacion.forEach(check => {
        check.addEventListener('change', function() {
            const tr = this.closest('tr');
            const labelJustificacion = this.nextElementSibling.querySelector('.badge');
            
            if (this.checked) {
                // Justificación activada
                tr.classList.remove('table-danger');
                tr.classList.add('table-warning');
                labelJustificacion.textContent = 'Justificada';
                labelJustificacion.classList.remove('bg-secondary');
                labelJustificacion.classList.add('bg-warning', 'text-dark');
            } else {
                // Justificación desactivada
                tr.classList.remove('table-warning');
                tr.classList.add('table-danger');
                labelJustificacion.textContent = 'Sin justificar';
                labelJustificacion.classList.remove('bg-warning', 'text-dark');
                labelJustificacion.classList.add('bg-secondary');
            }
        });
    });
    
    // Actualización automática al cambiar turno o grado
    const selectTurno = document.getElementById('turno');
    const selectGrado = document.getElementById('grado');
    
    if (selectTurno && selectGrado) {
        selectTurno.addEventListener('change', actualizarFiltros);
        selectGrado.addEventListener('change', actualizarFiltros);
    }
    
    function actualizarFiltros() {
        const formFiltros = document.querySelector('form');
        formFiltros.submit();
    }
});
</script>