<?php
/**
 * Estadísticas del Historial Escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/historial_functions.php';
require_once '../../includes/session_checker.php';

// Verificar el ID del alumno
$id_alumno = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_alumno <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

// Obtener datos del alumno
$query_alumno = "SELECT a.*, CONCAT(a.nombre, ' ', a.apellido) as nombre_completo, 
                g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
                FROM alumnos a
                JOIN grupos g ON a.id_grupo = g.id_grupo
                JOIN grados gr ON g.id_grado = gr.id_grado
                JOIN turnos t ON g.id_turno = t.id_turno
                WHERE a.id_alumno = ?";

$stmt_alumno = $conexion->prepare($query_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();
$result_alumno = $stmt_alumno->get_result();

if ($result_alumno->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'Alumno no encontrado', 'danger');
}

$alumno = $result_alumno->fetch_assoc();

// Obtener estadísticas generales
$query_stats = "SELECT 
                tipo_registro,
                COUNT(*) as total,
                MIN(fecha_evento) as fecha_min,
                MAX(fecha_evento) as fecha_max,
                (SELECT COUNT(*) FROM historial_escolar 
                 WHERE id_alumno = ? AND tipo_registro = h.tipo_registro AND relevancia = 'alta' AND eliminado = 0) as total_alta,
                (SELECT COUNT(*) FROM historial_escolar 
                 WHERE id_alumno = ? AND tipo_registro = h.tipo_registro AND relevancia = 'normal' AND eliminado = 0) as total_normal,
                (SELECT COUNT(*) FROM historial_escolar 
                 WHERE id_alumno = ? AND tipo_registro = h.tipo_registro AND relevancia = 'baja' AND eliminado = 0) as total_baja
                FROM historial_escolar h 
                WHERE id_alumno = ? AND eliminado = 0 
                GROUP BY tipo_registro";

$stmt_stats = $conexion->prepare($query_stats);
$stmt_stats->bind_param("iiii", $id_alumno, $id_alumno, $id_alumno, $id_alumno);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();

$estadisticas = [];
$total_registros = 0;

while ($row = $result_stats->fetch_assoc()) {
    $estadisticas[$row['tipo_registro']] = $row;
    $total_registros += $row['total'];
}

// Obtener datos para gráfico de tendencia mensual (últimos 12 meses)
$query_tendencia = "SELECT 
                    DATE_FORMAT(fecha_evento, '%Y-%m') as mes,
                    tipo_registro,
                    COUNT(*) as total
                    FROM historial_escolar
                    WHERE id_alumno = ? AND eliminado = 0
                    AND fecha_evento >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(fecha_evento, '%Y-%m'), tipo_registro
                    ORDER BY mes";

$stmt_tendencia = $conexion->prepare($query_tendencia);
$stmt_tendencia->bind_param("i", $id_alumno);
$stmt_tendencia->execute();
$result_tendencia = $stmt_tendencia->get_result();

$tendencia_mensual = [];

while ($row = $result_tendencia->fetch_assoc()) {
    if (!isset($tendencia_mensual[$row['mes']])) {
        $tendencia_mensual[$row['mes']] = [
            'academico' => 0,
            'asistencia' => 0,
            'conducta' => 0,
            'reconocimiento' => 0,
            'observacion' => 0
        ];
    }
    
    $tendencia_mensual[$row['mes']][$row['tipo_registro']] = $row['total'];
}

// Obtener calificaciones (solo para tipo académico)
$query_calificaciones = "SELECT 
                        categoria,
                        ROUND(AVG(calificacion), 1) as promedio,
                        MIN(calificacion) as minima,
                        MAX(calificacion) as maxima,
                        COUNT(*) as total
                        FROM historial_escolar
                        WHERE id_alumno = ? AND eliminado = 0
                        AND tipo_registro = 'academico' AND calificacion IS NOT NULL
                        GROUP BY categoria";

$stmt_calificaciones = $conexion->prepare($query_calificaciones);
$stmt_calificaciones->bind_param("i", $id_alumno);
$stmt_calificaciones->execute();
$result_calificaciones = $stmt_calificaciones->get_result();

$calificaciones = [];

while ($row = $result_calificaciones->fetch_assoc()) {
    $calificaciones[$row['categoria']] = $row;
}

// Obtener estadísticas de comparación con el grupo
$query_grupo = "SELECT 
                g.id_grupo,
                (SELECT COUNT(*) FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1) as total_alumnos,
                (SELECT ROUND(AVG(hs.total), 1) FROM 
                 (SELECT id_alumno, COUNT(*) as total FROM historial_escolar 
                  WHERE eliminado = 0 AND id_alumno IN (SELECT id_alumno FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1)
                  GROUP BY id_alumno) as hs) as promedio_registros,
                (SELECT ROUND(AVG(hs.total), 1) FROM 
                 (SELECT id_alumno, COUNT(*) as total FROM historial_escolar 
                  WHERE eliminado = 0 AND tipo_registro = 'academico' AND id_alumno IN (SELECT id_alumno FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1)
                  GROUP BY id_alumno) as hs) as promedio_academico,
                (SELECT ROUND(AVG(hs.total), 1) FROM 
                 (SELECT id_alumno, COUNT(*) as total FROM historial_escolar 
                  WHERE eliminado = 0 AND tipo_registro = 'conducta' AND id_alumno IN (SELECT id_alumno FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1)
                  GROUP BY id_alumno) as hs) as promedio_conducta,
                (SELECT ROUND(AVG(hs.total), 1) FROM 
                 (SELECT id_alumno, COUNT(*) as total FROM historial_escolar 
                  WHERE eliminado = 0 AND tipo_registro = 'reconocimiento' AND id_alumno IN (SELECT id_alumno FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1)
                  GROUP BY id_alumno) as hs) as promedio_reconocimiento
                FROM grupos g
                WHERE g.id_grupo = (SELECT id_grupo FROM alumnos WHERE id_alumno = ?)";

$stmt_grupo = $conexion->prepare($query_grupo);
$stmt_grupo->bind_param("i", $id_alumno);
$stmt_grupo->execute();
$result_grupo = $stmt_grupo->get_result();

$estadisticas_grupo = $result_grupo->fetch_assoc();

// Incluir header
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/timeline.css">

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Historial Escolar</a></li>
                    <li class="breadcrumb-item"><a href="ver.php?id=<?= $id_alumno ?>">Ver Historial</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Estadísticas</li>
                </ol>
            </nav>
            <h1><i class="fas fa-chart-bar"></i> Estadísticas del Historial</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="ver.php?id=<?= $id_alumno ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Historial
            </a>
            <a href="reporte.php?id=<?= $id_alumno ?>" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Generar Reporte
            </a>
        </div>
    </div>

    <!-- Datos del alumno -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-user"></i> Datos del Alumno</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center">
                    <img src="../../uploads/alumnos/<?= $id_alumno ?>.jpg" 
                         class="img-fluid rounded-circle mb-2" style="max-width: 150px; max-height: 150px;"
                         alt="<?= htmlspecialchars($alumno['nombre_completo']) ?>"
                         onerror="this.src='../../assets/img/user-default.png'">
                </div>
                <div class="col-md-5">
                    <h3><?= htmlspecialchars($alumno['nombre_completo']) ?></h3>
                    <p class="mb-1"><strong>Matrícula:</strong> <?= htmlspecialchars($alumno['matricula']) ?></p>
                    <p class="mb-1"><strong>Grupo:</strong> <?= htmlspecialchars($alumno['nombre_grupo']) ?></p>
                    <p class="mb-1"><strong>Grado:</strong> <?= htmlspecialchars($alumno['nombre_grado']) ?></p>
                    <p class="mb-1"><strong>Turno:</strong> <?= htmlspecialchars($alumno['nombre_turno']) ?></p>
                </div>
                <div class="col-md-5">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Resumen de Registros</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($total_registros > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total de registros:</span>
                                <span class="badge bg-primary"><?= $total_registros ?></span>
                            </div>
                            <div class="progress mb-3" style="height: 10px;">
                                <?php foreach ($estadisticas as $tipo => $datos): 
                                    $color_clase = obtener_color_tipo_registro($tipo);
                                    $porcentaje = ($datos['total'] / $total_registros) * 100;
                                ?>
                                <div class="progress-bar bg-<?= $color_clase ?>" style="width: <?= $porcentaje ?>%" 
                                     title="<?= ucfirst($tipo) ?>: <?= $datos['total'] ?>"></div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row">
                                <?php 
                                $iconos = [
                                    'academico' => 'fas fa-graduation-cap text-primary',
                                    'asistencia' => 'fas fa-calendar-check text-info',
                                    'conducta' => 'fas fa-exclamation-triangle text-warning',
                                    'reconocimiento' => 'fas fa-award text-success',
                                    'observacion' => 'fas fa-comment text-secondary'
                                ];
                                
                                foreach ($iconos as $tipo => $icono): 
                                    $total = isset($estadisticas[$tipo]) ? $estadisticas[$tipo]['total'] : 0;
                                ?>
                                <div class="col-6">
                                    <p class="mb-1">
                                        <i class="<?= $icono ?>"></i> 
                                        <strong><?= ucfirst($tipo) ?>:</strong> <?= $total ?>
                                    </p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                No hay registros en el historial para este alumno.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($total_registros > 0): ?>
    <!-- Análisis por tipo de registro -->
    <div class="row mb-4">
        <!-- Gráfico de distribución por tipo -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Distribución por Tipo de Registro</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px; width:100%">
                        <canvas id="tipoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de relevancia -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-exclamation-circle"></i> Distribución por Relevancia</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px; width:100%">
                        <canvas id="relevanciaChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tendencia mensual y comparativa con grupo -->
    <div class="row mb-4">
        <!-- Tendencia mensual -->
        <div class="col-md-8">
            <div class="card h-100">
               <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> Tendencia Mensual (Últimos 12 meses)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px; width:100%">
                        <canvas id="tendenciaChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Comparativa con grupo -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-users"></i> Comparativa con el Grupo</h5>
                </div>
                <div class="card-body">
                    <?php if ($estadisticas_grupo && $estadisticas_grupo['total_alumnos'] > 0): ?>
                    <p class="mb-2">Alumnos en el grupo: <strong><?= $estadisticas_grupo['total_alumnos'] ?></strong></p>
                    
                    <div class="mb-3">
                        <p class="mb-1">Registros totales:</p>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <?php 
                                $promedio_grupo = $estadisticas_grupo['promedio_registros'] ?: 0;
                                $registros_alumno = $total_registros;
                                $max_valor = max($promedio_grupo, $registros_alumno);
                                
                                $porcentaje_grupo = $max_valor > 0 ? ($promedio_grupo / $max_valor) * 100 : 0;
                                $porcentaje_alumno = $max_valor > 0 ? ($registros_alumno / $max_valor) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-secondary" style="width: <?= $porcentaje_grupo ?>%" 
                                     title="Promedio grupo: <?= $promedio_grupo ?>"></div>
                            </div>
                            <span class="ms-2"><?= $promedio_grupo ?></span>
                        </div>
                        <div class="d-flex align-items-center mt-1">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <div class="progress-bar bg-primary" style="width: <?= $porcentaje_alumno ?>%" 
                                     title="Este alumno: <?= $registros_alumno ?>"></div>
                            </div>
                            <span class="ms-2"><?= $registros_alumno ?></span>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">Promedio del grupo</small>
                            <small class="text-muted">Este alumno</small>
                        </div>
                    </div>
                    
                    <!-- Académico -->
                    <div class="mb-3">
                        <p class="mb-1">Registros académicos:</p>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <?php 
                                $promedio_grupo = $estadisticas_grupo['promedio_academico'] ?: 0;
                                $registros_alumno = isset($estadisticas['academico']) ? $estadisticas['academico']['total'] : 0;
                                $max_valor = max($promedio_grupo, $registros_alumno);
                                
                                $porcentaje_grupo = $max_valor > 0 ? ($promedio_grupo / $max_valor) * 100 : 0;
                                $porcentaje_alumno = $max_valor > 0 ? ($registros_alumno / $max_valor) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-secondary" style="width: <?= $porcentaje_grupo ?>%" 
                                     title="Promedio grupo: <?= $promedio_grupo ?>"></div>
                            </div>
                            <span class="ms-2"><?= $promedio_grupo ?></span>
                        </div>
                        <div class="d-flex align-items-center mt-1">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <div class="progress-bar bg-primary" style="width: <?= $porcentaje_alumno ?>%" 
                                     title="Este alumno: <?= $registros_alumno ?>"></div>
                            </div>
                            <span class="ms-2"><?= $registros_alumno ?></span>
                        </div>
                    </div>
                    
                    <!-- Conducta -->
                    <div class="mb-3">
                        <p class="mb-1">Registros de conducta:</p>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <?php 
                                $promedio_grupo = $estadisticas_grupo['promedio_conducta'] ?: 0;
                                $registros_alumno = isset($estadisticas['conducta']) ? $estadisticas['conducta']['total'] : 0;
                                $max_valor = max($promedio_grupo, $registros_alumno);
                                
                                $porcentaje_grupo = $max_valor > 0 ? ($promedio_grupo / $max_valor) * 100 : 0;
                                $porcentaje_alumno = $max_valor > 0 ? ($registros_alumno / $max_valor) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-secondary" style="width: <?= $porcentaje_grupo ?>%" 
                                     title="Promedio grupo: <?= $promedio_grupo ?>"></div>
                            </div>
                            <span class="ms-2"><?= $promedio_grupo ?></span>
                        </div>
                        <div class="d-flex align-items-center mt-1">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <div class="progress-bar bg-warning" style="width: <?= $porcentaje_alumno ?>%" 
                                     title="Este alumno: <?= $registros_alumno ?>"></div>
                            </div>
                            <span class="ms-2"><?= $registros_alumno ?></span>
                        </div>
                    </div>
                    
                    <!-- Reconocimientos -->
                    <div class="mb-3">
                        <p class="mb-1">Reconocimientos:</p>
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <?php 
                                $promedio_grupo = $estadisticas_grupo['promedio_reconocimiento'] ?: 0;
                                $registros_alumno = isset($estadisticas['reconocimiento']) ? $estadisticas['reconocimiento']['total'] : 0;
                                $max_valor = max($promedio_grupo, $registros_alumno);
                                
                                $porcentaje_grupo = $max_valor > 0 ? ($promedio_grupo / $max_valor) * 100 : 0;
                                $porcentaje_alumno = $max_valor > 0 ? ($registros_alumno / $max_valor) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-secondary" style="width: <?= $porcentaje_grupo ?>%" 
                                     title="Promedio grupo: <?= $promedio_grupo ?>"></div>
                            </div>
                            <span class="ms-2"><?= $promedio_grupo ?></span>
                        </div>
                        <div class="d-flex align-items-center mt-1">
                            <div class="progress flex-grow-1" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?= $porcentaje_alumno ?>%" 
                                     title="Este alumno: <?= $registros_alumno ?>"></div>
                            </div>
                            <span class="ms-2"><?= $registros_alumno ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No hay suficientes datos para mostrar la comparativa con el grupo.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($calificaciones)): ?>
    <!-- Análisis de calificaciones -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-graduation-cap"></i> Análisis de Calificaciones</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="chart-container" style="position: relative; height:300px; width:100%">
                                <canvas id="calificacionesChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <h6 class="border-bottom pb-2">Resumen de Calificaciones</h6>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Categoría</th>
                                        <th>Promedio</th>
                                        <th>Mínima</th>
                                        <th>Máxima</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($calificaciones as $categoria => $datos): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($categoria) ?></td>
                                        <td><strong><?= $datos['promedio'] ?></strong></td>
                                        <td><?= $datos['minima'] ?></td>
                                        <td><?= $datos['maxima'] ?></td>
                                        <td><?= $datos['total'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detalle por tipo de registro -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-table"></i> Detalle por Tipo de Registro</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo de Registro</th>
                                <th>Total</th>
                                <th>Relevancia Alta</th>
                                <th>Relevancia Normal</th>
                                <th>Relevancia Baja</th>
                                <th>Primer Registro</th>
                                <th>Último Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tipos_ordenados = ['academico', 'asistencia', 'conducta', 'reconocimiento', 'observacion'];
                            $nombres_tipos = [
                                'academico' => 'Académico',
                                'asistencia' => 'Asistencia',
                                'conducta' => 'Conducta',
                                'reconocimiento' => 'Reconocimiento',
                                'observacion' => 'Observación'
                            ];
                            
                            foreach ($tipos_ordenados as $tipo):
                                if (isset($estadisticas[$tipo])):
                                    $datos = $estadisticas[$tipo];
                                    $color_clase = obtener_color_tipo_registro($tipo);
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?= $color_clase ?> me-1">
                                        <i class="<?= obtener_icono_tipo_registro($tipo) ?>"></i>
                                    </span>
                                    <?= $nombres_tipos[$tipo] ?>
                                </td>
                                <td><strong><?= $datos['total'] ?></strong></td>
                                <td><?= $datos['total_alta'] ?></td>
                                <td><?= $datos['total_normal'] ?></td>
                                <td><?= $datos['total_baja'] ?></td>
                                <td><?= date('d/m/Y', strtotime($datos['fecha_min'])) ?></td>
                                <td><?= date('d/m/Y', strtotime($datos['fecha_max'])) ?></td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> No hay registros en el historial para este alumno.
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($total_registros > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración general de colores
    const colores = {
        academico: 'rgba(0, 123, 255, 0.7)',
        asistencia: 'rgba(23, 162, 184, 0.7)',
        conducta: 'rgba(255, 193, 7, 0.7)',
        reconocimiento: 'rgba(40, 167, 69, 0.7)',
        observacion: 'rgba(108, 117, 125, 0.7)',
        alta: 'rgba(220, 53, 69, 0.7)',
        normal: 'rgba(52, 58, 64, 0.7)',
        baja: 'rgba(40, 167, 69, 0.7)'
    };
    
    const bordersColores = {
        academico: 'rgb(0, 123, 255)',
        asistencia: 'rgb(23, 162, 184)',
        conducta: 'rgb(255, 193, 7)',
        reconocimiento: 'rgb(40, 167, 69)',
        observacion: 'rgb(108, 117, 125)',
        alta: 'rgb(220, 53, 69)',
        normal: 'rgb(52, 58, 64)',
        baja: 'rgb(40, 167, 69)'
    };
    
    const nombresTipos = {
        academico: 'Académico',
        asistencia: 'Asistencia',
        conducta: 'Conducta',
        reconocimiento: 'Reconocimiento',
        observacion: 'Observación',
        alta: 'Alta',
        normal: 'Normal',
        baja: 'Baja'
    };
    
    // Gráfico de distribución por tipo
    const ctxTipo = document.getElementById('tipoChart').getContext('2d');
    const tipoData = {
        labels: [
            <?php 
            foreach ($estadisticas as $tipo => $datos) {
                echo "'" . $nombresTipos[$tipo] . "', ";
            }
            ?>
        ],
        datasets: [{
            data: [
                <?php 
                foreach ($estadisticas as $tipo => $datos) {
                    echo $datos['total'] . ", ";
                }
                ?>
            ],
            backgroundColor: [
                <?php 
                foreach ($estadisticas as $tipo => $datos) {
                    echo "'" . $colores[$tipo] . "', ";
                }
                ?>
            ],
            borderColor: [
                <?php 
                foreach ($estadisticas as $tipo => $datos) {
                    echo "'" . $bordersColores[$tipo] . "', ";
                }
                ?>
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(ctxTipo, {
        type: 'pie',
        data: tipoData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.getDatasetMeta(0).total;
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de distribución por relevancia
    const ctxRelevancia = document.getElementById('relevanciaChart').getContext('2d');
    
    // Calcular totales por relevancia
    const totalesRelevancia = {
        alta: 0,
        normal: 0,
        baja: 0
    };
    
    <?php 
    foreach ($estadisticas as $tipo => $datos) {
        echo "totalesRelevancia.alta += " . $datos['total_alta'] . ";\n";
        echo "totalesRelevancia.normal += " . $datos['total_normal'] . ";\n";
        echo "totalesRelevancia.baja += " . $datos['total_baja'] . ";\n";
    }
    ?>
    
    const relevanciaData = {
        labels: ['Alta', 'Normal', 'Baja'],
        datasets: [{
            data: [
                totalesRelevancia.alta,
                totalesRelevancia.normal,
                totalesRelevancia.baja
            ],
            backgroundColor: [
                colores.alta,
                colores.normal,
                colores.baja
            ],
            borderColor: [
                bordersColores.alta,
                bordersColores.normal,
                bordersColores.baja
            ],
            borderWidth: 1
        }]
    };
    
    new Chart(ctxRelevancia, {
        type: 'doughnut',
        data: relevanciaData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.getDatasetMeta(0).total;
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de tendencia mensual
    const ctxTendencia = document.getElementById('tendenciaChart').getContext('2d');
    
    // Preparar datos para el gráfico de tendencia
    const meses = [
        <?php 
        foreach ($tendencia_mensual as $mes => $datos) {
            // Convertir formato YYYY-MM a formato legible
            $fecha = new DateTime($mes . '-01');
            echo "'" . $fecha->format('M Y') . "', ";
        }
        ?>
    ];
    
    const tendenciaDatasets = [
        {
            label: 'Académico',
            data: [
                <?php 
                foreach ($tendencia_mensual as $mes => $datos) {
                    echo $datos['academico'] . ", ";
                }
                ?>
            ],
            backgroundColor: colores.academico,
            borderColor: bordersColores.academico,
            borderWidth: 1,
            fill: false,
            tension: 0.4
        },
        {
            label: 'Asistencia',
            data: [
                <?php 
                foreach ($tendencia_mensual as $mes => $datos) {
                    echo $datos['asistencia'] . ", ";
                }
                ?>
            ],
            backgroundColor: colores.asistencia,
            borderColor: bordersColores.asistencia,
            borderWidth: 1,
            fill: false,
            tension: 0.4
        },
        {
            label: 'Conducta',
            data: [
                <?php 
                foreach ($tendencia_mensual as $mes => $datos) {
                    echo $datos['conducta'] . ", ";
                }
                ?>
            ],
            backgroundColor: colores.conducta,
            borderColor: bordersColores.conducta,
            borderWidth: 1,
            fill: false,
            tension: 0.4
        },
        {
            label: 'Reconocimiento',
            data: [
                <?php 
                foreach ($tendencia_mensual as $mes => $datos) {
                    echo $datos['reconocimiento'] . ", ";
                }
                ?>
            ],
            backgroundColor: colores.reconocimiento,
            borderColor: bordersColores.reconocimiento,
            borderWidth: 1,
            fill: false,
            tension: 0.4
        },
        {
            label: 'Observación',
            data: [
                <?php 
                foreach ($tendencia_mensual as $mes => $datos) {
                    echo $datos['observacion'] . ", ";
                }
                ?>
            ],
            backgroundColor: colores.observacion,
            borderColor: bordersColores.observacion,
            borderWidth: 1,
            fill: false,
            tension: 0.4
        }
    ];
    
    new Chart(ctxTendencia, {
        type: 'line',
        data: {
            labels: meses,
            datasets: tendenciaDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Número de registros'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Mes'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    
    <?php if (!empty($calificaciones)): ?>
    // Gráfico de calificaciones
    const ctxCalificaciones = document.getElementById('calificacionesChart').getContext('2d');
    
    const calificacionesData = {
        labels: [
            <?php 
            foreach ($calificaciones as $categoria => $datos) {
                echo "'" . $categoria . "', ";
            }
            ?>
        ],
        datasets: [
            {
                label: 'Promedio',
                data: [
                    <?php 
                    foreach ($calificaciones as $categoria => $datos) {
                        echo $datos['promedio'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: colores.academico,
                borderColor: bordersColores.academico,
                borderWidth: 1,
                type: 'bar'
            },
            {
                label: 'Máxima',
                data: [
                    <?php 
                    foreach ($calificaciones as $categoria => $datos) {
                        echo $datos['maxima'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: colores.reconocimiento,
                borderColor: bordersColores.reconocimiento,
                borderWidth: 1,
                type: 'line',
                fill: false
            },
            {
                label: 'Mínima',
                data: [
                    <?php 
                    foreach ($calificaciones as $categoria => $datos) {
                        echo $datos['minima'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: colores.conducta,
                borderColor: bordersColores.conducta,
                borderWidth: 1,
                type: 'line',
                fill: false
            }
        ]
    };
    
    new Chart(ctxCalificaciones, {
        type: 'bar',
        data: calificacionesData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    title: {
                        display: true,
                        text: 'Calificación'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Categoría'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>