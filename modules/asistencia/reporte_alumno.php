<?php
/**
 * Reporte de Asistencia por Alumno
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

// Filtros de fechas
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_inicio = date('Y-m-d', strtotime('-90 days'));
    $fecha_fin = date('Y-m-d');
}

// Validar que fecha fin no sea anterior a fecha inicio
if ($fecha_fin < $fecha_inicio) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
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

// Obtener el historial de asistencia del alumno
$query_asistencia = "SELECT a.id_asistencia, a.fecha, a.asistio, a.justificada, a.observaciones,
                    DATE_FORMAT(a.fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro,
                    (SELECT CONCAT(nombre, ' ', apellido_paterno) FROM usuarios WHERE id_usuario = a.registrado_por) as registrado_por
                    FROM asistencia a
                    WHERE a.id_alumno = ? AND a.fecha BETWEEN ? AND ?
                    ORDER BY a.fecha DESC";
$stmt_asistencia = $conexion->prepare($query_asistencia);
$stmt_asistencia->bind_param("iss", $id_alumno, $fecha_inicio, $fecha_fin);
$stmt_asistencia->execute();
$result_asistencia = $stmt_asistencia->get_result();

// Estadísticas
$total_dias = $result_asistencia->num_rows;
$presentes = 0;
$ausentes = 0;
$justificadas = 0;
$porcentaje_asistencia = 0;

$datos_grafico = [];

// Solo calculamos estadísticas si hay registros
if ($total_dias > 0) {
    // Resetear el puntero del resultado
    $result_asistencia->data_seek(0);
    
    while ($row = $result_asistencia->fetch_assoc()) {
        if ($row['asistio']) {
            $presentes++;
        } else {
            $ausentes++;
            if ($row['justificada']) {
                $justificadas++;
            }
        }
        
        // Agregar datos para el gráfico
        $datos_grafico[] = [
            'fecha' => $row['fecha'],
            'asistio' => $row['asistio'],
            'justificada' => $row['justificada']
        ];
    }
    
    // Calcular porcentaje de asistencia
    $porcentaje_asistencia = round(($presentes / $total_dias) * 100, 2);
    
    // Resetear el puntero para usar el resultado más adelante
    $result_asistencia->data_seek(0);
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-user-chart"></i> Reporte de Asistencia Individual</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-clipboard-list"></i> Volver a Pase de Lista
            </a>
            <a href="reporte.php?id_grupo=<?= $alumno['id_grupo'] ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>" class="btn btn-info">
                <i class="fas fa-users-class"></i> Reporte del Grupo
            </a>
            <a href="exportar_reporte_alumno.php?id=<?= $id_alumno ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&formato=pdf" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Exportar a PDF
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
                <div class="col-md-2 text-center">
                    <div class="avatar-container mb-3">
                        <i class="fas fa-user-circle fa-6x text-secondary"></i>
                    </div>
                    <h5><?= htmlspecialchars($alumno['matricula']) ?></h5>
                </div>
                <div class="col-md-5">
                    <h4><?= htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'] . ' ' . $alumno['nombre']) ?></h4>
                    <p><strong>Grupo:</strong> <?= htmlspecialchars($alumno['nombre_grupo']) ?></p>
                    <p><strong>Grado/Turno:</strong> <?= htmlspecialchars($alumno['nombre_grado'] . ' - ' . $alumno['nombre_turno']) ?></p>
                    <p><strong>Ciclo Escolar:</strong> <?= htmlspecialchars($alumno['ciclo_escolar']) ?></p>
                </div>
                <div class="col-md-5">
                    <h4 class="mb-3">Estadísticas de Asistencia</h4>
                    <div class="row">
                        <div class="col-4 text-center">
                            <div class="card bg-success text-white mb-2">
                                <div class="card-body py-2">
                                    <h2 class="mb-0"><?= $presentes ?></h2>
                                </div>
                            </div>
                            <p>Presentes</p>
                        </div>
                        <div class="col-4 text-center">
                            <div class="card bg-danger text-white mb-2">
                                <div class="card-body py-2">
                                    <h2 class="mb-0"><?= $ausentes ?></h2>
                                </div>
                            </div>
                            <p>Ausencias</p>
                        </div>
                        <div class="col-4 text-center">
                            <div class="card bg-warning text-dark mb-2">
                                <div class="card-body py-2">
                                    <h2 class="mb-0"><?= $justificadas ?></h2>
                                </div>
                            </div>
                            <p>Justificadas</p>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height: 25px;">
                        <div class="progress-bar <?= $porcentaje_asistencia >= 90 ? 'bg-success' : ($porcentaje_asistencia >= 75 ? 'bg-info' : 'bg-danger') ?>" 
                             role="progressbar" style="width: <?= $porcentaje_asistencia ?>%;" 
                             aria-valuenow="<?= $porcentaje_asistencia ?>" aria-valuemin="0" aria-valuemax="100">
                            <?= $porcentaje_asistencia ?>% de asistencia
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtro de fechas -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtro de Fechas</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <input type="hidden" name="id" value="<?= $id_alumno ?>">
                <div class="col-md-4">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?= $fecha_inicio ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?= $fecha_fin ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Aplicar Filtro
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Gráfico de tendencia -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> Tendencia de Asistencia</h5>
        </div>
        <div class="card-body">
            <canvas id="graficoTendencia" height="250"></canvas>
        </div>
    </div>
    
    <!-- Tabla de asistencia -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-check"></i> Historial de Asistencia
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary"><?= $total_dias ?> días registrados</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($total_dias > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Registrado por</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($registro = $result_asistencia->fetch_assoc()): ?>
                        <tr class="<?= !$registro['asistio'] ? ($registro['justificada'] ? 'table-warning' : 'table-danger') : '' ?>">
                            <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                            <td><?= date('l', strtotime($registro['fecha'])) ?></td>
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
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="registrar.php?id=<?= $id_alumno ?>&fecha=<?= $registro['fecha'] ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!$registro['asistio'] && !$registro['justificada']): ?>
                                    <a href="justificar.php?id=<?= $registro['id_asistencia'] ?>" class="btn btn-outline-warning">
                                        <i class="fas fa-file-medical"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                                    <button type="button" class="btn btn-outline-danger btn-eliminar" 
                                            data-id="<?= $registro['id_asistencia'] ?>" 
                                            data-fecha="<?= date('d/m/Y', strtotime($registro['fecha'])) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info m-3">
                <i class="fas fa-info-circle me-2"></i> No hay registros de asistencia para este alumno en el período seleccionado.
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

<!-- Incluir Chart.js para los gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include '../../includes/footer.php'; ?>

<?php if ($total_dias > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración del gráfico de tendencia
    const ctx = document.getElementById('graficoTendencia').getContext('2d');
    
    // Preparar datos para el gráfico
    const fechas = [];
    const estados = [];
    
    <?php
    // Invertir el array para que aparezca cronológicamente
    $datos_invertidos = array_reverse($datos_grafico);
    foreach ($datos_invertidos as $dato):
        $fecha_formateada = date('d/m/Y', strtotime($dato['fecha']));
        // Estado: 2 = presente, 1 = justificada, 0 = ausente
        $estado = $dato['asistio'] ? 2 : ($dato['justificada'] ? 1 : 0);
    ?>
    fechas.push('<?= $fecha_formateada ?>');
    estados.push(<?= $estado ?>);
    <?php endforeach; ?>
    
    // Crear gráfico
    const tendenciaChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: fechas,
            datasets: [{
                label: 'Asistencia',
                data: estados,
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 2,
                pointRadius: 5,
                pointBackgroundColor: function(context) {
                    const value = context.dataset.data[context.dataIndex];
                    return value === 2 ? 'rgba(40, 167, 69, 1)' : 
                           value === 1 ? 'rgba(255, 193, 7, 1)' : 
                           'rgba(220, 53, 69, 1)';
                },
                pointBorderColor: '#fff',
                pointBorderWidth: 1,
                pointHoverRadius: 7,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    min: 0,
                    max: 2,
                    ticks: {
                        stepSize: 1,
                        callback: function(value, index, values) {
                            return value === 2 ? 'Presente' : 
                                   value === 1 ? 'Justificada' : 
                                   'Ausente';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.y;
                            return value === 2 ? 'Presente' : 
                                   value === 1 ? 'Ausente Justificada' : 
                                   'Ausente';
                        }
                    }
                }
            }
        }
    });
    
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
<?php endif; ?>