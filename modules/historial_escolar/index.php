<?php
/**
 * Listado de Historial Escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/historial_functions.php';
require_once '../../includes/session_checker.php';

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-history"></i> Historial Escolar</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="registrar.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nuevo Registro
            </a>
        </div>
    </div>

    <!-- Buscador de Alumnos -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0"><i class="fas fa-search"></i> Buscar Alumno</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" id="busqueda_alumno" class="form-control" 
                               placeholder="Buscar por nombre, apellido o matrícula (mínimo 3 caracteres)">
                        <button class="btn btn-outline-primary" type="button" id="btn_buscar">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                    <div class="form-text">Ingrese al menos 3 caracteres para realizar la búsqueda</div>
                </div>
                <div class="col-md-4">
                    <select id="filtro_grupo" class="form-select">
                        <option value="0">Todos los grupos</option>
                        <?php
                        // Obtener y listar grupos
                        $query_grupos = "SELECT g.id_grupo, g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
                                         FROM grupos g 
                                         JOIN grados gr ON g.id_grado = gr.id_grado
                                         JOIN turnos t ON g.id_turno = t.id_turno
                                         WHERE g.activo = 1
                                         ORDER BY t.nombre_turno, gr.nombre_grado, g.nombre_grupo";
                        $result_grupos = $conexion->query($query_grupos);
                        while ($grupo = $result_grupos->fetch_assoc()) {
                            echo '<option value="'.$grupo['id_grupo'].'">'.$grupo['nombre_grupo'].' - '.$grupo['nombre_grado'].' - '.$grupo['nombre_turno'].'</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Resultados de búsqueda -->
    <div id="resultados_busqueda" class="row row-cols-1 row-cols-md-3 g-4 mb-4" style="display: none;">
        <!-- Aquí se cargarán los resultados mediante AJAX -->
    </div>

    <!-- Accesos rápidos a alumnos consultados recientemente -->
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0"><i class="fas fa-clock"></i> Alumnos consultados recientemente</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                // Obtener alumnos consultados recientemente (desde una tabla o sesión)
                // MODIFICADO: Cambio de a.nombre y a.apellido a a.nombres, a.apellido_paterno, a.apellido_materno
                $query_recientes = "SELECT a.id_alumno, a.nombres, a.apellido_paterno, a.apellido_materno, 
                                    a.curp AS matricula, g.nombre_grupo, 
                                    COALESCE(h.fecha_consulta, '0000-00-00') as fecha_consulta
                                    FROM alumnos a
                                    JOIN grupos g ON a.id_grupo = g.id_grupo
                                    LEFT JOIN (
                                        SELECT id_alumno, MAX(fecha_registro) as fecha_consulta 
                                        FROM historial_consultas 
                                        WHERE id_usuario = ? 
                                        GROUP BY id_alumno
                                    ) h ON a.id_alumno = h.id_alumno
                                    WHERE a.activo = 1 AND h.fecha_consulta IS NOT NULL
                                    ORDER BY h.fecha_consulta DESC
                                    LIMIT 6";
                
                $stmt_recientes = $conexion->prepare($query_recientes);
                $stmt_recientes->bind_param("i", $_SESSION['id_usuario']);
                $stmt_recientes->execute();
                $result_recientes = $stmt_recientes->get_result();
                
                if ($result_recientes->num_rows > 0) {
                    while ($alumno = $result_recientes->fetch_assoc()) {
                        // Obtener resumen del historial
                        $resumen = obtener_resumen_historial($alumno['id_alumno']);
                        
                        // MODIFICADO: Crear el nombre completo a partir de nombres, apellido_paterno y apellido_materno
                        $nombre_completo = $alumno['nombres'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'];
                        
                        // Mostrar tarjeta de alumno con resumen
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <img src="../../uploads/alumnos/<?= $alumno['id_alumno'] ?>.jpg" 
                                                 class="rounded-circle" width="50" height="50" 
                                                 alt="<?= htmlspecialchars($nombre_completo) ?>"
                                                 onerror="this.src='../../assets/img/user-default.png'">
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="card-title mb-0">
                                                <?= htmlspecialchars($nombre_completo) ?>
                                            </h5>
                                            <p class="card-text text-muted small mb-0">
                                                Matrícula: <?= htmlspecialchars($alumno['matricula']) ?> | 
                                                Grupo: <?= htmlspecialchars($alumno['nombre_grupo']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total de registros:</span>
                                        <span class="badge bg-primary"><?= $resumen['total_registros'] ?></span>
                                    </div>
                                    <div class="progress mb-2" style="height: 5px;">
                                        <div class="progress-bar bg-primary" style="width: <?= $resumen['academico'] ? ($resumen['academico'] / $resumen['total_registros'] * 100) : 0 ?>%" 
                                             title="Académico: <?= $resumen['academico'] ?>"></div>
                                        <div class="progress-bar bg-info" style="width: <?= $resumen['asistencia'] ? ($resumen['asistencia'] / $resumen['total_registros'] * 100) : 0 ?>%" 
                                             title="Asistencia: <?= $resumen['asistencia'] ?>"></div>
                                        <div class="progress-bar bg-warning" style="width: <?= $resumen['conducta'] ? ($resumen['conducta'] / $resumen['total_registros'] * 100) : 0 ?>%" 
                                             title="Conducta: <?= $resumen['conducta'] ?>"></div>
                                        <div class="progress-bar bg-success" style="width: <?= $resumen['reconocimiento'] ? ($resumen['reconocimiento'] / $resumen['total_registros'] * 100) : 0 ?>%" 
                                             title="Reconocimiento: <?= $resumen['reconocimiento'] ?>"></div>
                                        <div class="progress-bar bg-secondary" style="width: <?= $resumen['observacion'] ? ($resumen['observacion'] / $resumen['total_registros'] * 100) : 0 ?>%" 
                                             title="Observación: <?= $resumen['observacion'] ?>"></div>
                                    </div>
                                    
                                    <div class="row small mb-2">
                                        <div class="col-6">
                                            <i class="fas fa-graduation-cap text-primary"></i> Académico: <?= $resumen['academico'] ?>
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-calendar-check text-info"></i> Asistencia: <?= $resumen['asistencia'] ?>
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-exclamation-triangle text-warning"></i> Conducta: <?= $resumen['conducta'] ?>
                                        </div>
                                        <div class="col-6">
                                            <i class="fas fa-award text-success"></i> Reconocimientos: <?= $resumen['reconocimiento'] ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($resumen['ultimo_registro']): ?>
                                    <div class="alert alert-light small">
                                        <strong>Último registro:</strong> 
                                        <?= htmlspecialchars($resumen['ultimo_registro']['titulo']) ?>
                                        <span class="d-block text-muted">
                                            <?= date('d/m/Y', strtotime($resumen['ultimo_registro']['fecha_evento'])) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <a href="ver.php?id=<?= $alumno['id_alumno'] ?>" class="btn btn-primary btn-sm w-100">
                                        <i class="fas fa-eye"></i> Ver Historial
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="col-12"><div class="alert alert-info">No hay alumnos consultados recientemente.</div></div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const busquedaInput = document.getElementById('busqueda_alumno');
    const buscarBtn = document.getElementById('btn_buscar');
    const resultadosDiv = document.getElementById('resultados_busqueda');
    const filtroGrupo = document.getElementById('filtro_grupo');
    
    // Búsqueda al hacer clic en el botón
    buscarBtn.addEventListener('click', function() {
        realizarBusqueda();
    });
    
    // Búsqueda al presionar Enter
    busquedaInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            realizarBusqueda();
        }
    });
    
    // Cambio en el filtro de grupo
    filtroGrupo.addEventListener('change', function() {
        if (busquedaInput.value.length >= 3) {
            realizarBusqueda();
        }
    });
    
    function realizarBusqueda() {
        const busqueda = busquedaInput.value.trim();
        const idGrupo = filtroGrupo.value;
        
        if (busqueda.length < 3) {
            alert('Ingrese al menos 3 caracteres para realizar la búsqueda');
            return;
        }
        
        // Mostrar indicador de carga
        resultadosDiv.innerHTML = '<div class="col-12 text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        resultadosDiv.style.display = 'flex';
        
        // Realizar petición AJAX
        fetch(`ajax/buscar_alumnos.php?q=${encodeURIComponent(busqueda)}&grupo=${idGrupo}`)
            .then(response => response.json())
            .then(data => {
                resultadosDiv.innerHTML = '';
                
                if (data.length === 0) {
                    resultadosDiv.innerHTML = '<div class="col-12"><div class="alert alert-info">No se encontraron alumnos con los criterios especificados.</div></div>';
                } else {
                    // Procesar cada alumno
                    data.forEach(alumno => {
                        // MODIFICADO: Crear el nombre completo a partir de nombres, apellido_paterno y apellido_materno
                        const nombreCompleto = `${alumno.nombres} ${alumno.apellido_paterno} ${alumno.apellido_materno}`;
                        
                        // Crear tarjeta para cada alumno usando template literals
                        const tarjeta = `
                            <div class="col">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <img src="../../uploads/alumnos/${alumno.id_alumno}.jpg" 
                                                     class="rounded-circle" width="50" height="50" 
                                                     alt="${nombreCompleto}"
                                                     onerror="this.src='../../assets/img/user-default.png'">
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h5 class="card-title mb-0">${nombreCompleto}</h5>
                                                <p class="card-text text-muted small mb-0">
                                                    Matrícula: ${alumno.matricula} | 
                                                    Grupo: ${alumno.nombre_grupo}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Resumen del Historial -->
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total de registros:</span>
                                            <span class="badge bg-primary">${alumno.resumen.total_registros}</span>
                                        </div>
                                        <div class="progress mb-2" style="height: 5px;">
                                            <div class="progress-bar bg-primary" style="width: ${alumno.resumen.academico ? (alumno.resumen.academico / alumno.resumen.total_registros * 100) : 0}%" 
                                                 title="Académico: ${alumno.resumen.academico}"></div>
                                            <div class="progress-bar bg-info" style="width: ${alumno.resumen.asistencia ? (alumno.resumen.asistencia / alumno.resumen.total_registros * 100) : 0}%" 
                                                 title="Asistencia: ${alumno.resumen.asistencia}"></div>
                                            <div class="progress-bar bg-warning" style="width: ${alumno.resumen.conducta ? (alumno.resumen.conducta / alumno.resumen.total_registros * 100) : 0}%" 
                                                 title="Conducta: ${alumno.resumen.conducta}"></div>
                                            <div class="progress-bar bg-success" style="width: ${alumno.resumen.reconocimiento ? (alumno.resumen.reconocimiento / alumno.resumen.total_registros * 100) : 0}%" 
                                                 title="Reconocimiento: ${alumno.resumen.reconocimiento}"></div>
                                            <div class="progress-bar bg-secondary" style="width: ${alumno.resumen.observacion ? (alumno.resumen.observacion / alumno.resumen.total_registros * 100) : 0}%" 
                                                 title="Observación: ${alumno.resumen.observacion}"></div>
                                        </div>
                                        
                                        <div class="row small mb-2">
                                            <div class="col-6">
                                                <i class="fas fa-graduation-cap text-primary"></i> Académico: ${alumno.resumen.academico}
                                            </div>
                                            <div class="col-6">
                                                <i class="fas fa-calendar-check text-info"></i> Asistencia: ${alumno.resumen.asistencia}
                                            </div>
                                            <div class="col-6">
                                                <i class="fas fa-exclamation-triangle text-warning"></i> Conducta: ${alumno.resumen.conducta}
                                            </div>
                                            <div class="col-6">
                                                <i class="fas fa-award text-success"></i> Reconocimientos: ${alumno.resumen.reconocimiento}
                                            </div>
                                        </div>
                                        
                                        ${alumno.resumen.ultimo_registro ? `
                                        <div class="alert alert-light small">
                                            <strong>Último registro:</strong> 
                                            ${alumno.resumen.ultimo_registro.titulo}
                                            <span class="d-block text-muted">
                                                ${new Date(alumno.resumen.ultimo_registro.fecha_evento).toLocaleDateString()}
                                            </span>
                                        </div>
                                        ` : ''}
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group w-100">
                                            <a href="ver.php?id=${alumno.id_alumno}" class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i> Ver Historial
                                            </a>
                                            <a href="registrar.php?id=${alumno.id_alumno}" class="btn btn-success btn-sm">
                                                <i class="fas fa-plus-circle"></i> Nuevo Registro
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        resultadosDiv.innerHTML += tarjeta;
                    });
                }
                
                resultadosDiv.style.display = 'flex';
            })
            .catch(error => {
                console.error('Error:', error);
                resultadosDiv.innerHTML = '<div class="col-12"><div class="alert alert-danger">Error al realizar la búsqueda. Intente nuevamente.</div></div>';
            });
    }
});
</script>