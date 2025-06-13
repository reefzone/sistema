<?php
/**
 * Visualización de Historial Escolar
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

// Registrar la consulta en el historial de consultas
$query_registro = "INSERT INTO historial_consultas (id_alumno, id_usuario, fecha_registro)
                  VALUES (?, ?, NOW())";
$stmt_registro = $conexion->prepare($query_registro);
$stmt_registro->bind_param("ii", $id_alumno, $_SESSION['id_usuario']);
$stmt_registro->execute();

// Obtener parámetros de filtro
$filtro_tipo = isset($_GET['tipo']) ? sanitizar_texto($_GET['tipo']) : '';
$filtro_fecha_inicio = isset($_GET['fecha_inicio']) ? sanitizar_texto($_GET['fecha_inicio']) : '';
$filtro_fecha_fin = isset($_GET['fecha_fin']) ? sanitizar_texto($_GET['fecha_fin']) : '';
$filtro_relevancia = isset($_GET['relevancia']) ? sanitizar_texto($_GET['relevancia']) : '';
$filtro_busqueda = isset($_GET['busqueda']) ? sanitizar_texto($_GET['busqueda']) : '';

// Preparar filtros para la consulta
$filtros = [];
if (!empty($filtro_tipo)) $filtros['tipo'] = $filtro_tipo;
if (!empty($filtro_fecha_inicio)) $filtros['fecha_inicio'] = $filtro_fecha_inicio;
if (!empty($filtro_fecha_fin)) $filtros['fecha_fin'] = $filtro_fecha_fin;
if (!empty($filtro_relevancia)) $filtros['relevancia'] = $filtro_relevancia;
if (!empty($filtro_busqueda)) $filtros['busqueda'] = $filtro_busqueda;

// Obtener el historial del alumno
$historial = obtener_historial_alumno($id_alumno, $filtros);

// Obtener conteo por tipo de registro
$query_conteo = "SELECT tipo_registro, COUNT(*) as total FROM historial_escolar 
                WHERE id_alumno = ? AND eliminado = 0 
                GROUP BY tipo_registro";
$stmt_conteo = $conexion->prepare($query_conteo);
$stmt_conteo->bind_param("i", $id_alumno);
$stmt_conteo->execute();
$result_conteo = $stmt_conteo->get_result();

$conteos = [
    'academico' => 0,
    'asistencia' => 0,
    'conducta' => 0,
    'reconocimiento' => 0,
    'observacion' => 0,
    'total' => 0
];

while ($row = $result_conteo->fetch_assoc()) {
    $conteos[$row['tipo_registro']] = $row['total'];
    $conteos['total'] += $row['total'];
}

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
                    <li class="breadcrumb-item active" aria-current="page">Ver Historial</li>
                </ol>
            </nav>
            <h1><i class="fas fa-history"></i> Historial Escolar</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="registrar.php?id=<?= $id_alumno ?>" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nuevo Registro
            </a>
            <a href="reporte.php?id=<?= $id_alumno ?>" class="btn btn-success">
                <i class="fas fa-file-pdf"></i> Generar Reporte
            </a>
            <a href="estadisticas.php?id=<?= $id_alumno ?>" class="btn btn-info">
                <i class="fas fa-chart-bar"></i> Estadísticas
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
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total de registros:</span>
                                <span class="badge bg-primary"><?= $conteos['total'] ?></span>
                            </div>
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar bg-primary" style="width: <?= $conteos['total'] > 0 ? ($conteos['academico'] / $conteos['total'] * 100) : 0 ?>%" 
                                     title="Académico: <?= $conteos['academico'] ?>"></div>
                                <div class="progress-bar bg-info" style="width: <?= $conteos['total'] > 0 ? ($conteos['asistencia'] / $conteos['total'] * 100) : 0 ?>%" 
                                     title="Asistencia: <?= $conteos['asistencia'] ?>"></div>
                                <div class="progress-bar bg-warning" style="width: <?= $conteos['total'] > 0 ? ($conteos['conducta'] / $conteos['total'] * 100) : 0 ?>%" 
                                     title="Conducta: <?= $conteos['conducta'] ?>"></div>
                                <div class="progress-bar bg-success" style="width: <?= $conteos['total'] > 0 ? ($conteos['reconocimiento'] / $conteos['total'] * 100) : 0 ?>%" 
                                     title="Reconocimiento: <?= $conteos['reconocimiento'] ?>"></div>
                                <div class="progress-bar bg-secondary" style="width: <?= $conteos['total'] > 0 ? ($conteos['observacion'] / $conteos['total'] * 100) : 0 ?>%" 
                                     title="Observación: <?= $conteos['observacion'] ?>"></div>
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <p class="mb-1">
                                        <i class="fas fa-graduation-cap text-primary"></i> 
                                        <strong>Académico:</strong> <?= $conteos['academico'] ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1">
                                        <i class="fas fa-calendar-check text-info"></i> 
                                        <strong>Asistencia:</strong> <?= $conteos['asistencia'] ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1">
                                        <i class="fas fa-exclamation-triangle text-warning"></i> 
                                        <strong>Conducta:</strong> <?= $conteos['conducta'] ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1">
                                        <i class="fas fa-award text-success"></i> 
                                        <strong>Reconocimientos:</strong> <?= $conteos['reconocimiento'] ?>
                                    </p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1">
                                        <i class="fas fa-comment text-secondary"></i> 
                                        <strong>Observaciones:</strong> <?= $conteos['observacion'] ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <input type="hidden" name="id" value="<?= $id_alumno ?>">
                
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo de Registro</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="">Todos los tipos</option>
                        <option value="academico" <?= $filtro_tipo == 'academico' ? 'selected' : '' ?>>Académico</option>
                        <option value="asistencia" <?= $filtro_tipo == 'asistencia' ? 'selected' : '' ?>>Asistencia</option>
                        <option value="conducta" <?= $filtro_tipo == 'conducta' ? 'selected' : '' ?>>Conducta</option>
                        <option value="reconocimiento" <?= $filtro_tipo == 'reconocimiento' ? 'selected' : '' ?>>Reconocimiento</option>
                        <option value="observacion" <?= $filtro_tipo == 'observacion' ? 'selected' : '' ?>>Observación</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="fecha_inicio" class="form-label">Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?= $filtro_fecha_inicio ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="fecha_fin" class="form-label">Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?= $filtro_fecha_fin ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="relevancia" class="form-label">Relevancia</label>
                    <select name="relevancia" id="relevancia" class="form-select">
                        <option value="">Todas</option>
                        <option value="baja" <?= $filtro_relevancia == 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="normal" <?= $filtro_relevancia == 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="alta" <?= $filtro_relevancia == 'alta' ? 'selected' : '' ?>>Alta</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="busqueda" class="form-label">Búsqueda</label>
                    <input type="text" name="busqueda" id="busqueda" class="form-control" 
                           placeholder="Buscar en título o descripción" value="<?= htmlspecialchars($filtro_busqueda) ?>">
                </div>
                
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                    <a href="ver.php?id=<?= $id_alumno ?>" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Limpiar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Pestañas de tipos de registro -->
    <ul class="nav nav-tabs mb-4" id="tiposTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="todos-tab" data-bs-toggle="tab" data-bs-target="#todos" 
                    type="button" role="tab" aria-controls="todos" aria-selected="true">
                <i class="fas fa-list"></i> Todos <span class="badge bg-primary ms-1"><?= $conteos['total'] ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="academico-tab" data-bs-toggle="tab" data-bs-target="#academico" 
                    type="button" role="tab" aria-controls="academico" aria-selected="false">
                <i class="fas fa-graduation-cap"></i> Académico <span class="badge bg-primary ms-1"><?= $conteos['academico'] ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="asistencia-tab" data-bs-toggle="tab" data-bs-target="#asistencia" 
                    type="button" role="tab" aria-controls="asistencia" aria-selected="false">
                <i class="fas fa-calendar-check"></i> Asistencia <span class="badge bg-info ms-1"><?= $conteos['asistencia'] ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="conducta-tab" data-bs-toggle="tab" data-bs-target="#conducta" 
                    type="button" role="tab" aria-controls="conducta" aria-selected="false">
                <i class="fas fa-exclamation-triangle"></i> Conducta <span class="badge bg-warning ms-1"><?= $conteos['conducta'] ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reconocimiento-tab" data-bs-toggle="tab" data-bs-target="#reconocimiento" 
                    type="button" role="tab" aria-controls="reconocimiento" aria-selected="false">
                <i class="fas fa-award"></i> Reconocimientos <span class="badge bg-success ms-1"><?= $conteos['reconocimiento'] ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="observacion-tab" data-bs-toggle="tab" data-bs-target="#observacion" 
                    type="button" role="tab" aria-controls="observacion" aria-selected="false">
                <i class="fas fa-comment"></i> Observaciones <span class="badge bg-secondary ms-1"><?= $conteos['observacion'] ?></span>
            </button>
        </li>
    </ul>

    <!-- Contenido de las pestañas -->
    <div class="tab-content" id="tiposTabsContent">
        <!-- Pestaña Todos -->
        <div class="tab-pane fade show active" id="todos" role="tabpanel" aria-labelledby="todos-tab">
            <?php if (count($historial) > 0): ?>
                <div class="timeline">
                    <?php
                    $posicion = 'left';
                    foreach ($historial as $index => $registro):
                        $tipo_clase = obtener_color_tipo_registro($registro['tipo_registro']);
                        $icono = obtener_icono_tipo_registro($registro['tipo_registro']);
                        $relevancia_badge = '';
                        
                        switch ($registro['relevancia']) {
                            case 'alta': $relevancia_badge = '<span class="badge bg-danger ms-2">Alta</span>'; break;
                            case 'baja': $relevancia_badge = '<span class="badge bg-success ms-2">Baja</span>'; break;
                        }
                    ?>
                    <div class="timeline-container timeline-<?= $posicion ?> timeline-badge-<?= $registro['tipo_registro'] ?>">
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <h5 class="mb-1">
                                    <i class="<?= $icono ?>"></i> <?= htmlspecialchars($registro['titulo']) ?>
                                    <?= $relevancia_badge ?>
                                </h5>
                                <div class="text-muted small">
                                    <?= date('d/m/Y', strtotime($registro['fecha_evento'])) ?>
                                </div>
                            </div>
                            
                            <p><?= nl2br(htmlspecialchars($registro['descripcion'])) ?></p>
                            
                            <?php if (!empty($registro['categoria'])): ?>
                            <div class="mb-2">
                                <span class="badge bg-<?= $tipo_clase ?>">
                                    <?= htmlspecialchars($registro['categoria']) ?>
                                </span>
                                
                                <?php if (!is_null($registro['calificacion'])): ?>
                                <span class="badge bg-dark ms-2">
                                    Calificación: <?= $registro['calificacion'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($registro['adjuntos'])): ?>
                            <div class="mt-2">
                                <p class="mb-1"><strong>Archivos adjuntos:</strong></p>
                                <div class="list-group">
                                    <?php foreach ($registro['adjuntos'] as $adjunto): ?>
                                    <a href="<?= $adjunto['ruta'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="far fa-file me-2"></i>
                                            <?= htmlspecialchars($adjunto['nombre_original']) ?>
                                        </span>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= formatear_tamano_archivo($adjunto['tamano']) ?>
                                        </span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 text-end">
                                <div class="btn-group">
                                    <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                                    <a href="editar.php?id=<?= $registro['id_historial'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                                    <button type="button" class="btn btn-sm btn-danger btn-eliminar" 
                                            data-id="<?= $registro['id_historial'] ?>" 
                                            data-titulo="<?= htmlspecialchars($registro['titulo']) ?>">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <small class="text-muted d-block mt-1">
                                    Registrado por <?= htmlspecialchars($registro['registrado_por_nombre']) ?> 
                                    el <?= date('d/m/Y H:i', strtotime($registro['fecha_registro'])) ?>
                                    
                                    <?php if (!is_null($registro['modificado_por'])): ?>
                                    <br>Modificado por <?= htmlspecialchars($registro['modificado_por_nombre']) ?> 
                                    el <?= date('d/m/Y H:i', strtotime($registro['fecha_modificacion'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php 
                        // Alternar posición
                        $posicion = ($posicion == 'left') ? 'right' : 'left';
                    endforeach; 
                    ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No se encontraron registros para el alumno con los filtros seleccionados.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pestañas para cada tipo de registro -->
        <?php
        $tipos = ['academico', 'asistencia', 'conducta', 'reconocimiento', 'observacion'];
        
        foreach ($tipos as $tipo):
            // Filtrar el historial por tipo
            $historial_tipo = array_filter($historial, function($item) use ($tipo) {
                return $item['tipo_registro'] == $tipo;
            });
        ?>
        <div class="tab-pane fade" id="<?= $tipo ?>" role="tabpanel" aria-labelledby="<?= $tipo ?>-tab">
            <?php if (count($historial_tipo) > 0): ?>
                <div class="timeline">
                    <?php
                    $posicion = 'left';
                    foreach ($historial_tipo as $index => $registro):
                        $tipo_clase = obtener_color_tipo_registro($registro['tipo_registro']);
                        $icono = obtener_icono_tipo_registro($registro['tipo_registro']);
                        $relevancia_badge = '';
                        
                        switch ($registro['relevancia']) {
                            case 'alta': $relevancia_badge = '<span class="badge bg-danger ms-2">Alta</span>'; break;
                            case 'baja': $relevancia_badge = '<span class="badge bg-success ms-2">Baja</span>'; break;
                        }
                    ?>
                    <div class="timeline-container timeline-<?= $posicion ?> timeline-badge-<?= $registro['tipo_registro'] ?>">
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <h5 class="mb-1">
                                    <i class="<?= $icono ?>"></i> <?= htmlspecialchars($registro['titulo']) ?>
                                    <?= $relevancia_badge ?>
                                </h5>
                                <div class="text-muted small">
                                    <?= date('d/m/Y', strtotime($registro['fecha_evento'])) ?>
                                </div>
                            </div>
                            
                            <p><?= nl2br(htmlspecialchars($registro['descripcion'])) ?></p>
                            
                            <?php if (!empty($registro['categoria'])): ?>
                            <div class="mb-2">
                                <span class="badge bg-<?= $tipo_clase ?>">
                                    <?= htmlspecialchars($registro['categoria']) ?>
                                </span>
                                
                                <?php if (!is_null($registro['calificacion'])): ?>
                                <span class="badge bg-dark ms-2">
                                    Calificación: <?= $registro['calificacion'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($registro['adjuntos'])): ?>
                            <div class="mt-2">
                                <p class="mb-1"><strong>Archivos adjuntos:</strong></p>
                                <div class="list-group">
                                    <?php foreach ($registro['adjuntos'] as $adjunto): ?>
                                    <a href="<?= $adjunto['ruta'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="far fa-file me-2"></i>
                                            <?= htmlspecialchars($adjunto['nombre_original']) ?>
                                        </span>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= formatear_tamano_archivo($adjunto['tamano']) ?>
                                        </span>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3 text-end">
                                <div class="btn-group">
                                    <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                                    <a href="editar.php?id=<?= $registro['id_historial'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                                    <button type="button" class="btn btn-sm btn-danger btn-eliminar" 
                                            data-id="<?= $registro['id_historial'] ?>" 
                                            data-titulo="<?= htmlspecialchars($registro['titulo']) ?>">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                    <?php endif; ?>
                                </div>
                                
                                <small class="text-muted d-block mt-1">
                                    Registrado por <?= htmlspecialchars($registro['registrado_por_nombre']) ?> 
                                    el <?= date('d/m/Y H:i', strtotime($registro['fecha_registro'])) ?>
                                    
                                    <?php if (!is_null($registro['modificado_por'])): ?>
                                    <br>Modificado por <?= htmlspecialchars($registro['modificado_por_nombre']) ?> 
                                    el <?= date('d/m/Y H:i', strtotime($registro['fecha_modificacion'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php 
                        // Alternar posición
                        $posicion = ($posicion == 'left') ? 'right' : 'left';
                    endforeach; 
                    ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No se encontraron registros de tipo <?= ucfirst($tipo) ?> para el alumno.
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
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
                <p>¿Está seguro que desea eliminar el registro <strong id="titulo-registro"></strong>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="form-eliminar" action="eliminar.php" method="post">
                    <input type="hidden" name="id_historial" id="id-historial">
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
    // Configurar modal de eliminación
    const btnsEliminar = document.querySelectorAll('.btn-eliminar');
    const modalEliminar = document.getElementById('eliminarModal');
    const idHistorial = document.getElementById('id-historial');
    const tituloRegistro = document.getElementById('titulo-registro');
    
    btnsEliminar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const titulo = this.getAttribute('data-titulo');
            
            idHistorial.value = id;
            tituloRegistro.textContent = titulo;
            
            const modal = new bootstrap.Modal(modalEliminar);
            modal.show();
        });
    });
    
    // Activar pestaña según el filtro
    <?php if (!empty($filtro_tipo)): ?>
    const tipoTab = document.getElementById('<?= $filtro_tipo ?>-tab');
    if (tipoTab) {
        const tab = new bootstrap.Tab(tipoTab);
        tab.show();
    }
    <?php endif; ?>
});
</script>