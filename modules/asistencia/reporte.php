<?php
/**
 * Reporte General de Asistencia
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

// Filtros de búsqueda
$id_grupo_filtro = isset($_GET['id_grupo']) ? intval($_GET['id_grupo']) : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$filtro_grado = isset($_GET['grado']) ? intval($_GET['grado']) : 0;
$filtro_turno = isset($_GET['turno']) ? intval($_GET['turno']) : 0;
$mostrar_justificadas = isset($_GET['mostrar_justificadas']) ? (int)$_GET['mostrar_justificadas'] : 1;

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
    $fecha_fin = date('Y-m-d');
}

// Validar que fecha fin no sea anterior a fecha inicio
if ($fecha_fin < $fecha_inicio) {
    $temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $temp;
}

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

// Paginación
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 15;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Array para almacenar resultados de reporte
$datos_reporte = [];
$total_registros = 0;
$total_paginas = 1;

// Estadísticas generales
$total_alumnos = 0;
$total_dias = 0;
$total_asistencias = 0;
$total_inasistencias = 0;
$total_justificadas = 0;
$porcentaje_asistencia = 0;

// Si hay un grupo seleccionado, generar reporte
if ($id_grupo_filtro > 0) {
    // Consulta para obtener resumen diario de asistencia
    $query_resumen_diario = "SELECT a.fecha, 
                            COUNT(DISTINCT a.id_alumno) as total_alumnos,
                            SUM(CASE WHEN a.asistio = 1 THEN 1 ELSE 0 END) as presentes,
                            SUM(CASE WHEN a.asistio = 0 THEN 1 ELSE 0 END) as ausentes,
                            SUM(CASE WHEN a.asistio = 0 AND a.justificada = 1 THEN 1 ELSE 0 END) as justificados
                            FROM asistencia a
                            JOIN alumnos al ON a.id_alumno = al.id_alumno
                            WHERE al.id_grupo = ? AND a.fecha BETWEEN ? AND ?
                            GROUP BY a.fecha
                            ORDER BY a.fecha DESC";
    
    $stmt_resumen = $conexion->prepare($query_resumen_diario);
    $stmt_resumen->bind_param("iss", $id_grupo_filtro, $fecha_inicio, $fecha_fin);
    $stmt_resumen->execute();
    $result_resumen = $stmt_resumen->get_result();
    
    // Obtener total de registros para paginación
    $total_registros = $result_resumen->num_rows;
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // Restablecemos el puntero del resultado
    $result_resumen->data_seek(0);
    
    // Contador de días
    $contador_dias = 0;
    
    // Recorrer resultados para estadísticas generales
    while ($row = $result_resumen->fetch_assoc()) {
        $contador_dias++;
        $total_alumnos = max($total_alumnos, $row['total_alumnos']);
        $total_asistencias += $row['presentes'];
        $total_inasistencias += $row['ausentes'];
        $total_justificadas += $row['justificados'];
    }
    
    // Calcular estadísticas
    $total_dias = $contador_dias;
    if ($total_dias > 0 && $total_alumnos > 0) {
        $porcentaje_asistencia = round(($total_asistencias / ($total_dias * $total_alumnos)) * 100, 2);
    }
    
    // Restablecemos el puntero del resultado nuevamente
    $result_resumen->data_seek(0);
    
    // Limitar resultados para paginación
    $contador = 0;
    while ($row = $result_resumen->fetch_assoc()) {
        $contador++;
        if ($contador <= $inicio) continue;
        if (count($datos_reporte) >= $registros_por_pagina) break;
        
        $porcentaje_diario = round(($row['presentes'] / $row['total_alumnos']) * 100, 1);
        
        $row['porcentaje'] = $porcentaje_diario;
        $datos_reporte[] = $row;
    }
    
    // Obtener alumnos con más inasistencias
    $query_top_ausentes = "SELECT al.id_alumno, al.matricula, 
                          CONCAT(al.apellido_paterno, ' ', al.apellido_materno, ' ', al.nombre) as nombre_completo,
                          COUNT(a.id_asistencia) as total_registros,
                          SUM(CASE WHEN a.asistio = 0 THEN 1 ELSE 0 END) as ausencias,
                          SUM(CASE WHEN a.asistio = 0 AND a.justificada = 1 THEN 1 ELSE 0 END) as justificadas,
                          ROUND((SUM(CASE WHEN a.asistio = 1 THEN 1 ELSE 0 END) / COUNT(a.id_asistencia)) * 100, 1) as porcentaje_asistencia
                          FROM alumnos al
                          LEFT JOIN asistencia a ON al.id_alumno = a.id_alumno AND a.fecha BETWEEN ? AND ?
                          WHERE al.id_grupo = ? AND al.activo = 1
                          GROUP BY al.id_alumno
                          HAVING ausencias > 0
                          ORDER BY ausencias DESC, justificadas ASC
                          LIMIT 10";
                          
    $stmt_top = $conexion->prepare($query_top_ausentes);
    $stmt_top->bind_param("ssi", $fecha_inicio, $fecha_fin, $id_grupo_filtro);
    $stmt_top->execute();
    $result_top = $stmt_top->get_result();
    
    $alumnos_ausentes = [];
    while ($row = $result_top->fetch_assoc()) {
        $alumnos_ausentes[] = $row;
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-chart-bar"></i> Reporte de Asistencia</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-clipboard-list"></i> Volver a Pase de Lista
            </a>
            <?php if ($id_grupo_filtro > 0): ?>
            <a href="exportar_reporte.php?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&formato=excel" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </a>
            <a href="exportar_reporte.php?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&formato=pdf" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Exportar a PDF
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filtros de búsqueda -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtros del Reporte</h5>
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
                <div class="col-md-6">
                    <label for="id_grupo" class="form-label">Grupo</label>
                    <select class="form-select" id="id_grupo" name="id_grupo" required>
                        <option value="">-- Seleccione un grupo --</option>
                        <?php foreach ($grupos as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $id_grupo_filtro == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                           value="<?= $fecha_inicio ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                           value="<?= $fecha_fin ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="mostrar_justificadas" class="form-label">Mostrar inasistencias justificadas como</label>
                    <select class="form-select" id="mostrar_justificadas" name="mostrar_justificadas">
                        <option value="1" <?= $mostrar_justificadas == 1 ? 'selected' : '' ?>>Separadas (ausencias / justificadas)</option>
                        <option value="0" <?= $mostrar_justificadas == 0 ? 'selected' : '' ?>>Unificadas (solo ausencias)</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generar Reporte
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($id_grupo_filtro > 0): ?>
    <!-- Estadísticas generales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-users"></i> Total Alumnos</h5>
                    <p class="card-text display-4"><?= $total_alumnos ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-calendar-alt"></i> Días Registrados</h5>
                    <p class="card-text display-4"><?= $total_dias ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-check-circle"></i> % Asistencia</h5>
                    <p class="card-text display-4"><?= $porcentaje_asistencia ?>%</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-times-circle"></i> Total Inasistencias</h5>
                    <p class="card-text display-4"><?= $total_inasistencias ?></p>
                    <?php if ($total_justificadas > 0): ?>
                    <p class="card-text">
                        <span class="badge bg-warning text-dark">
                            <?= $total_justificadas ?> justificadas
                        </span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de asistencia -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-chart-line"></i> Gráfico de Asistencia</h5>
        </div>
        <div class="card-body">
            <canvas id="graficoAsistencia" height="250"></canvas>
        </div>
    </div>
    
    <!-- Tabla de resumen diario -->
    <div class="card mb-4">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-table"></i> Resumen Diario de Asistencia
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary"><?= $total_registros ?> días registrados</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th>Fecha</th>
                            <th>Total Alumnos</th>
                            <th>Presentes</th>
                            <th>Ausentes</th>
                            <?php if ($mostrar_justificadas): ?>
                            <th>Justificados</th>
                            <?php endif; ?>
                            <th>% Asistencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datos_reporte)): ?>
                        <tr>
                            <td colspan="<?= $mostrar_justificadas ? 7 : 6 ?>" class="text-center py-4">
                                <i class="fas fa-info-circle me-2"></i>
                                No se encontraron registros de asistencia para este grupo en el período seleccionado.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($datos_reporte as $dia): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($dia['fecha'])) ?> (<?= date('l', strtotime($dia['fecha'])) ?>)</td>
                                <td><?= $dia['total_alumnos'] ?></td>
                                <td class="text-success"><?= $dia['presentes'] ?></td>
                                <td class="text-danger"><?= $dia['ausentes'] ?></td>
                                <?php if ($mostrar_justificadas): ?>
                                <td class="text-warning"><?= $dia['justificados'] ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $dia['porcentaje'] >= 90 ? 'bg-success' : ($dia['porcentaje'] >= 75 ? 'bg-info' : 'bg-danger') ?>" 
                                             role="progressbar" style="width: <?= $dia['porcentaje'] ?>%;" 
                                             aria-valuenow="<?= $dia['porcentaje'] ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= $dia['porcentaje'] ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="index.php?id_grupo=<?= $id_grupo_filtro ?>&fecha=<?= $dia['fecha'] ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i> Ver Detalle
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de reporte">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&mostrar_justificadas=<?= $mostrar_justificadas ?>&pagina=1">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&mostrar_justificadas=<?= $mostrar_justificadas ?>&pagina=<?= $pagina_actual - 1 ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $inicio_paginas = max(1, $pagina_actual - 2);
                    $fin_paginas = min($total_paginas, $pagina_actual + 2);
                    
                    for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): 
                    ?>
                    <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                        <a class="page-link" href="?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&mostrar_justificadas=<?= $mostrar_justificadas ?>&pagina=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&mostrar_justificadas=<?= $mostrar_justificadas ?>&pagina=<?= $pagina_actual + 1 ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?id_grupo=<?= $id_grupo_filtro ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&mostrar_justificadas=<?= $mostrar_justificadas ?>&pagina=<?= $total_paginas ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Alumnos con más inasistencias -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle"></i> Alumnos con Mayor Inasistencia</h5>
        </div>
        <div class="card-body">
            <?php if (empty($alumnos_ausentes)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> No hay alumnos con inasistencias en el período seleccionado.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-danger">
                        <tr>
                            <th>Matrícula</th>
                            <th>Nombre</th>
                            <th>Total Registros</th>
                            <th>Ausencias</th>
                            <th>Justificadas</th>
                            <th>% Asistencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos_ausentes as $alumno): ?>
                        <tr>
                            <td><?= htmlspecialchars($alumno['matricula']) ?></td>
                            <td><?= htmlspecialchars($alumno['nombre_completo']) ?></td>
                            <td><?= $alumno['total_registros'] ?></td>
                            <td class="text-danger"><?= $alumno['ausencias'] ?></td>
                            <td class="text-warning"><?= $alumno['justificadas'] ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= $alumno['porcentaje_asistencia'] >= 90 ? 'bg-success' : ($alumno['porcentaje_asistencia'] >= 75 ? 'bg-info' : 'bg-danger') ?>" 
                                         role="progressbar" style="width: <?= $alumno['porcentaje_asistencia'] ?>%;" 
                                         aria-valuenow="<?= $alumno['porcentaje_asistencia'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?= $alumno['porcentaje_asistencia'] ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="reporte_alumno.php?id=<?= $alumno['id_alumno'] ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-chart-line"></i> Ver Detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Mensaje para seleccionar grupo -->
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i> Seleccione un grupo y período para generar el reporte de asistencia.
    </div>
    <?php endif; ?>
</div>

<!-- Incluir Chart.js para los gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include '../../includes/footer.php'; ?>

<?php if ($id_grupo_filtro > 0 && !empty($datos_reporte)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración del gráfico de asistencia
    const ctx = document.getElementById('graficoAsistencia').getContext('2d');
    
    // Preparar datos para el gráfico
    const fechas = [];
    const presentes = [];
    const ausentes = [];
    const justificados = [];
    const porcentajes = [];
    
    <?php
    // Invertir orden para que aparezcan cronológicamente en el gráfico
    $datos_invertidos = array_reverse($datos_reporte);
    foreach ($datos_invertidos as $dia):
    ?>
    fechas.push('<?= date('d/m/Y', strtotime($dia['fecha'])) ?>');
    presentes.push(<?= $dia['presentes'] ?>);
    ausentes.push(<?= $dia['ausentes'] - $dia['justificados'] ?>);
    justificados.push(<?= $dia['justificados'] ?>);
    porcentajes.push(<?= $dia['porcentaje'] ?>);
    <?php endforeach; ?>
    
    // Crear gráfico
    const asistenciaChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: fechas,
            datasets: [
                {
                    label: 'Presentes',
                    data: presentes,
                    backgroundColor: 'rgba(40, 167, 69, 0.6)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Ausentes',
                    data: ausentes,
                    backgroundColor: 'rgba(220, 53, 69, 0.6)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Justificados',
                    data: justificados,
                    backgroundColor: 'rgba(255, 193, 7, 0.6)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 1
                },
                {
                    label: '% Asistencia',
                    data: porcentajes,
                    type: 'line',
                    fill: false,
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    yAxisID: 'porcentaje'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    title: {
                        display: true,
                        text: 'Número de Alumnos'
                    }
                },
                porcentaje: {
                    position: 'right',
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Porcentaje (%)'
                    }
                }
            }
        }
    });
    
    // Actualización automática al cambiar turno o grado
    const selectTurno = document.getElementById('turno');
    const selectGrado = document.getElementById('grado');
    
    if (selectTurno && selectGrado) {
        selectTurno.addEventListener('change', actualizarGrupos);
        selectGrado.addEventListener('change', actualizarGrupos);
    }
    
    function actualizarGrupos() {
        // Mantener los valores actuales de fechas
        const fechaInicio = document.getElementById('fecha_inicio').value;
        const fechaFin = document.getElementById('fecha_fin').value;
        const mostrarJustificadas = document.getElementById('mostrar_justificadas').value;
        
        // Construir URL
        let url = `reporte.php?turno=${selectTurno.value}&grado=${selectGrado.value}`;
        
        // Agregar parámetros si existen
        if (fechaInicio) url += `&fecha_inicio=${fechaInicio}`;
        if (fechaFin) url += `&fecha_fin=${fechaFin}`;
        if (mostrarJustificadas) url += `&mostrar_justificadas=${mostrarJustificadas}`;
        
        // Redireccionar
        window.location.href = url;
    }
});
</script>
<?php endif; ?>