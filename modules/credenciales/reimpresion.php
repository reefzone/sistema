<?php
/**
 * Reimpresión de Credenciales
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

// Filtros de búsqueda
$filtro_tipo = isset($_GET['tipo']) ? sanitizar_texto($_GET['tipo']) : '';
$filtro_grupo = isset($_GET['grupo']) ? intval($_GET['grupo']) : 0;
$filtro_ciclo = isset($_GET['ciclo']) ? sanitizar_texto($_GET['ciclo']) : '';
$filtro_fecha = isset($_GET['fecha']) ? sanitizar_texto($_GET['fecha']) : '';

// Paginación
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 20;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Consulta base para obtener historial
$query_base = "FROM credenciales_generadas cg 
               LEFT JOIN grupos g ON cg.id_grupo = g.id_grupo 
               LEFT JOIN grados gr ON g.id_grado = gr.id_grado 
               LEFT JOIN turnos t ON g.id_turno = t.id_turno 
               LEFT JOIN alumnos a ON cg.id_alumno = a.id_alumno
               LEFT JOIN usuarios u ON cg.generado_por = u.id_usuario";

// Agregar condiciones según filtros
$params = [];
$tipos = "";

if (!empty($filtro_tipo)) {
    $query_base .= " AND cg.tipo = ?";
    $params[] = $filtro_tipo;
    $tipos .= "s";
}

if ($filtro_grupo > 0) {
    $query_base .= " AND cg.id_grupo = ?";
    $params[] = $filtro_grupo;
    $tipos .= "i";
}

if (!empty($filtro_ciclo)) {
    $query_base .= " AND g.ciclo_escolar = ?";
    $params[] = $filtro_ciclo;
    $tipos .= "s";
}

if (!empty($filtro_fecha)) {
    $fecha_inicio = $filtro_fecha . ' 00:00:00';
    $fecha_fin = $filtro_fecha . ' 23:59:59';
    $query_base .= " AND cg.fecha_generacion BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
    $tipos .= "ss";
}

// Consulta para contar total de registros
$query_count = "SELECT COUNT(cg.id_generacion) as total $query_base";
$stmt_count = $conexion->prepare($query_count);

if (!empty($params)) {
    $stmt_count->bind_param($tipos, ...$params);
}

$stmt_count->execute();
$result_count = $stmt_count->get_result();
$row_count = $result_count->fetch_assoc();
$total_registros = $row_count['total'];

// Calcular total de páginas
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta para obtener historial paginado
$query = "SELECT cg.id_generacion, cg.tipo, cg.fecha_generacion, cg.ruta_archivo, 
          g.nombre_grupo, g.ciclo_escolar, gr.nombre_grado, t.nombre_turno, 
          a.nombre AS alumno_nombre, a.apellido AS alumno_apellido, a.matricula,
          CONCAT(u.nombre, ' ', u.apellido) as generado_por,
          (SELECT COUNT(*) FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1) as total_alumnos
          $query_base 
          ORDER BY cg.fecha_generacion DESC 
          LIMIT ?, ?";

$stmt = $conexion->prepare($query);

if (!empty($params)) {
    $params[] = $inicio;
    $params[] = $registros_por_pagina;
    $stmt->bind_param($tipos . "ii", ...$params);
} else {
    $stmt->bind_param("ii", $inicio, $registros_por_pagina);
}

$stmt->execute();
$result = $stmt->get_result();

// Obtener grupos y ciclos para filtros
$grupos = [];
$query_grupos = "SELECT g.id_grupo, g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
                FROM grupos g 
                JOIN grados gr ON g.id_grado = gr.id_grado 
                JOIN turnos t ON g.id_turno = t.id_turno 
                WHERE g.activo = 1 
                ORDER BY t.id_turno, gr.id_grado, g.nombre_grupo";
$result_grupos = $conexion->query($query_grupos);
while ($row = $result_grupos->fetch_assoc()) {
    $grupos[$row['id_grupo']] = $row['nombre_grupo'] . ' - ' . $row['nombre_grado'] . ' ' . $row['nombre_turno'];
}

$ciclos = [];
$query_ciclos = "SELECT DISTINCT ciclo_escolar FROM grupos WHERE activo = 1 ORDER BY ciclo_escolar DESC";
$result_ciclos = $conexion->query($query_ciclos);
while ($row = $result_ciclos->fetch_assoc()) {
    $ciclos[] = $row['ciclo_escolar'];
}

// Ver detalles de generación específica
$mostrar_detalles = false;
$generacion = null;
$alumnos_generacion = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_generacion = intval($_GET['id']);
    
    // Obtener detalles de la generación
    $query_generacion = "SELECT cg.*, g.nombre_grupo, g.ciclo_escolar, gr.nombre_grado, t.nombre_turno,
                         a.nombre AS alumno_nombre, a.apellido AS alumno_apellido, a.matricula,
                         CONCAT(u.nombre, ' ', u.apellido) as generado_por
                         FROM credenciales_generadas cg
                         LEFT JOIN grupos g ON cg.id_grupo = g.id_grupo
                         LEFT JOIN grados gr ON g.id_grado = gr.id_grado
                         LEFT JOIN turnos t ON g.id_turno = t.id_turno
                         LEFT JOIN alumnos a ON cg.id_alumno = a.id_alumno
                         LEFT JOIN usuarios u ON cg.generado_por = u.id_usuario
                         WHERE cg.id_generacion = ?";
    
    $stmt_generacion = $conexion->prepare($query_generacion);
    $stmt_generacion->bind_param("i", $id_generacion);
    $stmt_generacion->execute();
    $result_generacion = $stmt_generacion->get_result();
    
    if ($result_generacion->num_rows > 0) {
        $generacion = $result_generacion->fetch_assoc();
        $mostrar_detalles = true;
        
        // Si es generación de grupo, obtener lista de alumnos
        if ($generacion['tipo'] == 'grupo') {
            $query_alumnos = "SELECT a.id_alumno, a.nombre, a.apellido, a.matricula
                             FROM alumnos a
                             WHERE a.id_grupo = ? AND a.activo = 1
                             ORDER BY a.apellido, a.nombre";
            
            $stmt_alumnos = $conexion->prepare($query_alumnos);
            $stmt_alumnos->bind_param("i", $generacion['id_grupo']);
            $stmt_alumnos->execute();
            $result_alumnos = $stmt_alumnos->get_result();
            
            while ($alumno = $result_alumnos->fetch_assoc()) {
                $alumnos_generacion[] = $alumno;
            }
        }
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <?php if ($mostrar_detalles): ?>
    <!-- Vista de detalles de una generación específica -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-history"></i> Detalles de Credencial</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="reimpresion.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Historial
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-info-circle"></i> 
                <?= $generacion['tipo'] == 'grupo' ? 'Credenciales de Grupo' : 'Credencial Individual' ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h5>Información</h5>
                    <ul class="list-group mb-4">
                        <?php if ($generacion['tipo'] == 'grupo'): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grupo:</strong></span>
                            <span><?= htmlspecialchars($generacion['nombre_grupo']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grado:</strong></span>
                            <span><?= htmlspecialchars($generacion['nombre_grado']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Turno:</strong></span>
                            <span><?= htmlspecialchars($generacion['nombre_turno']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Total Alumnos:</strong></span>
                            <span class="badge bg-primary rounded-pill"><?= count($alumnos_generacion) ?></span>
                        </li>
                        <?php else: ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Alumno:</strong></span>
                            <span><?= htmlspecialchars($generacion['alumno_nombre'] . ' ' . $generacion['alumno_apellido']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Matrícula:</strong></span>
                            <span><?= htmlspecialchars($generacion['matricula']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grupo:</strong></span>
                            <span><?= htmlspecialchars($generacion['nombre_grupo']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grado/Turno:</strong></span>
                            <span><?= htmlspecialchars($generacion['nombre_grado'] . ' - ' . $generacion['nombre_turno']) ?></span>
                        </li>
                        <?php endif; ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Ciclo Escolar:</strong></span>
                            <span><?= htmlspecialchars($generacion['ciclo_escolar']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Fecha de Generación:</strong></span>
                            <span><?= date('d/m/Y H:i', strtotime($generacion['fecha_generacion'])) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Generado por:</strong></span>
                            <span><?= htmlspecialchars($generacion['generado_por']) ?></span>
                        </li>
                    </ul>
                    
                    <?php if ($generacion['tipo'] == 'grupo' && !empty($alumnos_generacion)): ?>
                   <div class="accordion-item">
                            <h2 class="accordion-header" id="headingAlumnos">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapseAlumnos" aria-expanded="false" aria-controls="collapseAlumnos">
                                    Lista de Alumnos (<?= count($alumnos_generacion) ?>)
                                </button>
                            </h2>
                            <div id="collapseAlumnos" class="accordion-collapse collapse" aria-labelledby="headingAlumnos" 
                                 data-bs-parent="#accordionAlumnos">
                                <div class="accordion-body p-0">
                                    <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($alumnos_generacion as $alumno): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']) ?></span>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($alumno['matricula']) ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-8">
                    <div class="credencial-preview text-center mb-4">
                        <?php if (file_exists($generacion['ruta_archivo'])): ?>
                        <iframe src="<?= $generacion['ruta_archivo'] ?>" width="100%" height="500" 
                                style="border: 1px solid #ddd; border-radius: 8px;"></iframe>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            El archivo PDF no está disponible. Es posible que haya sido eliminado.
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        <?php if (file_exists($generacion['ruta_archivo'])): ?>
                        <div class="btn-group">
                            <a href="<?= $generacion['ruta_archivo'] ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-eye"></i> Ver PDF
                            </a>
                            <a href="<?= $generacion['ruta_archivo'] ?>" class="btn btn-success" download>
                                <i class="fas fa-download"></i> Descargar
                            </a>
                            <button class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="btn-group">
                            <?php if ($generacion['tipo'] == 'grupo'): ?>
                            <a href="generar_grupo.php?id_grupo=<?= $generacion['id_grupo'] ?>" class="btn btn-warning">
                                <i class="fas fa-redo"></i> Regenerar Credenciales
                            </a>
                            <?php else: ?>
                            <a href="generar.php?id_alumno=<?= $generacion['id_alumno'] ?>" class="btn btn-warning">
                                <i class="fas fa-redo"></i> Regenerar Credencial
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Vista de listado de historial -->
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-history"></i> Historial de Credenciales</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <option value="individual" <?= $filtro_tipo == 'individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="grupo" <?= $filtro_tipo == 'grupo' ? 'selected' : '' ?>>Grupo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="grupo" class="form-label">Grupo</label>
                    <select class="form-select" id="grupo" name="grupo">
                        <option value="0">Todos</option>
                        <?php foreach ($grupos as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $filtro_grupo == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ciclo" class="form-label">Ciclo Escolar</label>
                    <select class="form-select" id="ciclo" name="ciclo">
                        <option value="">Todos</option>
                        <?php foreach ($ciclos as $ciclo): ?>
                        <option value="<?= $ciclo ?>" <?= $filtro_ciclo == $ciclo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ciclo) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha de Generación</label>
                    <input type="date" class="form-control" id="fecha" name="fecha" 
                           value="<?= $filtro_fecha ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="reimpresion.php" class="btn btn-secondary">
                        <i class="fas fa-broom"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de generaciones -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> Historial de Generación de Credenciales
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary"><?= $total_registros ?> registros encontrados</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Grupo/Alumno</th>
                            <th scope="col">Grado/Turno</th>
                            <th scope="col">Ciclo Escolar</th>
                            <th scope="col">Generado por</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                        ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($row['fecha_generacion'])) ?></td>
                            <td>
                                <?php if ($row['tipo'] == 'grupo'): ?>
                                <span class="badge bg-info">Grupo</span>
                                <?php else: ?>
                                <span class="badge bg-success">Individual</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['tipo'] == 'grupo'): ?>
                                    <?= htmlspecialchars($row['nombre_grupo']) ?>
                                    <span class="badge bg-secondary"><?= $row['total_alumnos'] ?> alumnos</span>
                                <?php else: ?>
                                    <?= htmlspecialchars($row['alumno_apellido'] . ', ' . $row['alumno_nombre']) ?>
                                    <small class="d-block text-muted"><?= htmlspecialchars($row['matricula']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['nombre_grado'] . ' - ' . $row['nombre_turno']) ?></td>
                            <td><?= htmlspecialchars($row['ciclo_escolar']) ?></td>
                            <td><?= htmlspecialchars($row['generado_por']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="reimpresion.php?id=<?= $row['id_generacion'] ?>" class="btn btn-info" 
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (file_exists($row['ruta_archivo'])): ?>
                                    <a href="<?= $row['ruta_archivo'] ?>" class="btn btn-primary" target="_blank" 
                                       title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                    <a href="<?= $row['ruta_archivo'] ?>" class="btn btn-success" download 
                                       title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php else: ?>
                                    <?php if ($row['tipo'] == 'grupo'): ?>
                                    <a href="generar_grupo.php?id_grupo=<?= $row['id_grupo'] ?>" class="btn btn-warning" 
                                       title="Regenerar">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                    <?php else: ?>
                                    <a href="generar.php?id_alumno=<?= $row['id_alumno'] ?>" class="btn btn-warning" 
                                       title="Regenerar">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-info-circle me-2"></i>
                                No se encontraron registros con los criterios de búsqueda.
                            </td>
                        </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de historial">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=1&tipo=<?= urlencode($filtro_tipo) ?>&grupo=<?= $filtro_grupo ?>&ciclo=<?= urlencode($filtro_ciclo) ?>&fecha=<?= urlencode($filtro_fecha) ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $pagina_actual - 1 ?>&tipo=<?= urlencode($filtro_tipo) ?>&grupo=<?= $filtro_grupo ?>&ciclo=<?= urlencode($filtro_ciclo) ?>&fecha=<?= urlencode($filtro_fecha) ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $inicio_paginas = max(1, $pagina_actual - 2);
                    $fin_paginas = min($total_paginas, $pagina_actual + 2);
                    
                    for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): 
                    ?>
                    <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&tipo=<?= urlencode($filtro_tipo) ?>&grupo=<?= $filtro_grupo ?>&ciclo=<?= urlencode($filtro_ciclo) ?>&fecha=<?= urlencode($filtro_fecha) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $pagina_actual + 1 ?>&tipo=<?= urlencode($filtro_tipo) ?>&grupo=<?= $filtro_grupo ?>&ciclo=<?= urlencode($filtro_ciclo) ?>&fecha=<?= urlencode($filtro_fecha) ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $total_paginas ?>&tipo=<?= urlencode($filtro_tipo) ?>&grupo=<?= $filtro_grupo ?>&ciclo=<?= urlencode($filtro_ciclo) ?>&fecha=<?= urlencode($filtro_fecha) ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>