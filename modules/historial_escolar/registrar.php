<?php
/**
 * Registro de Nueva Entrada en Historial Escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/historial_functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar si se proporcionó un ID de alumno
$id_alumno = isset($_GET['id']) ? intval($_GET['id']) : 0;
$alumno = null;

if ($id_alumno > 0) {
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

    if ($result_alumno->num_rows > 0) {
        $alumno = $result_alumno->fetch_assoc();
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Historial Escolar</a></li>
                    <?php if ($alumno): ?>
                    <li class="breadcrumb-item"><a href="ver.php?id=<?= $id_alumno ?>">Ver Historial</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active" aria-current="page">Registrar Entrada</li>
                </ol>
            </nav>
            <h1><i class="fas fa-plus-circle"></i> Nuevo Registro en Historial</h1>
        </div>
    </div>
    
    <!-- Formulario de registro -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-edit"></i> Formulario de Registro
            </h5>
        </div>
        <div class="card-body">
            <form action="guardar.php" method="post" enctype="multipart/form-data" id="form-registro">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                
                <!-- Selección de alumno (si no viene preseleccionado) -->
                <?php if (!$alumno): ?>
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Selección de Alumno</h5>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="busqueda_alumno" class="form-label">Buscar Alumno</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="busqueda_alumno" class="form-control" 
                                       placeholder="Buscar por nombre, apellido o matrícula (mínimo 3 caracteres)">
                                <button class="btn btn-outline-primary" type="button" id="btn_buscar">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                            <div class="form-text">Ingrese al menos 3 caracteres para realizar la búsqueda</div>
                        </div>
                        <div class="col-md-4">
                            <label for="filtro_grupo" class="form-label">Filtrar por Grupo</label>
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
                    
                    <div id="resultados_busqueda" class="mt-3" style="display: none;">
                        <div class="list-group" id="lista_alumnos"></div>
                    </div>
                    
                    <div class="mt-3">
                        <div class="row align-items-center" id="alumno_seleccionado" style="display: none;">
                            <div class="col-auto">
                                <img id="foto_alumno" src="" class="rounded-circle" width="80" height="80" alt="">
                            </div>
                            <div class="col">
                                <h5 id="nombre_alumno" class="mb-1"></h5>
                                <p class="mb-0 text-muted">
                                    Matrícula: <span id="matricula_alumno"></span> | 
                                    Grupo: <span id="grupo_alumno"></span>
                                </p>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="cambiar_alumno">
                                    <i class="fas fa-exchange-alt"></i> Cambiar alumno
                                </button>
                            </div>
                        </div>
                        
                        <input type="hidden" name="id_alumno" id="id_alumno" value="">
                    </div>
                </div>
                <?php else: ?>
                <!-- Alumno preseleccionado -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Alumno Seleccionado</h5>
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <img src="../../uploads/alumnos/<?= $id_alumno ?>.jpg" 
                                 class="rounded-circle" width="80" height="80" 
                                 alt="<?= htmlspecialchars($alumno['nombre_completo']) ?>"
                                 onerror="this.src='../../assets/img/user-default.png'">
                        </div>
                        <div class="col">
                            <h5 class="mb-1"><?= htmlspecialchars($alumno['nombre_completo']) ?></h5>
                            <p class="mb-0 text-muted">
                                Matrícula: <?= htmlspecialchars($alumno['matricula']) ?> | 
                                Grupo: <?= htmlspecialchars($alumno['nombre_grupo']) ?>
                            </p>
                        </div>
                        <div class="col-auto">
                            <a href="registrar.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-exchange-alt"></i> Cambiar alumno
                            </a>
                        </div>
                    </div>
                    <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                </div>
                <?php endif; ?>
                
                <!-- Información del registro -->
                <div class="mb-4" id="info_registro" <?= !$alumno ? 'style="display: none;"' : '' ?>>
                    <h5 class="border-bottom pb-2">Información del Registro</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="tipo_registro" class="form-label">Tipo de Registro *</label>
                            <select name="tipo_registro" id="tipo_registro" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="academico">Académico</option>
                                <option value="asistencia">Asistencia</option>
                                <option value="conducta">Conducta</option>
                                <option value="reconocimiento">Reconocimiento</option>
                                <option value="observacion">Observación General</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select name="categoria" id="categoria" class="form-select" required disabled>
                                <option value="">Seleccione primero un tipo de registro</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="fecha_evento" class="form-label">Fecha del Evento *</label>
                            <input type="date" name="fecha_evento" id="fecha_evento" class="form-control" 
                                   required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="col-md-8">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" name="titulo" id="titulo" class="form-control" 
                                   required maxlength="100" placeholder="Título descriptivo del registro">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="relevancia" class="form-label">Relevancia</label>
                            <select name="relevancia" id="relevancia" class="form-select">
                                <option value="normal">Normal</option>
                                <option value="alta">Alta</option>
                                <option value="baja">Baja</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" 
                                      rows="5" required placeholder="Describa detalladamente el evento o situación"></textarea>
                        </div>
                        
                        <!-- Campo calificación (visible solo para tipo académico) -->
                        <div class="col-md-4" id="campo_calificacion" style="display: none;">
                            <label for="calificacion" class="form-label">Calificación</label>
                            <input type="number" name="calificacion" id="calificacion" class="form-control" 
                                   min="0" max="10" step="0.1" placeholder="Valor de 0 a 10">
                            <div class="form-text">Deje en blanco si no aplica</div>
                        </div>
                    </div>
                </div>
                
                <!-- Archivos adjuntos -->
                <div class="mb-4" id="archivos_adjuntos" <?= !$alumno ? 'style="display: none;"' : '' ?>>
                    <h5 class="border-bottom pb-2">Archivos Adjuntos</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="archivos" class="form-label">Adjuntar archivos</label>
                            <input type="file" name="archivos[]" id="archivos" class="form-control" multiple>
                            <div class="form-text">
                                Puede adjuntar múltiples archivos. Tipos permitidos: PDF, DOC, DOCX, JPG, PNG. Tamaño máximo por archivo: 5MB.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="text-end mt-4">
                    <a href="<?= $alumno ? 'ver.php?id='.$id_alumno : 'index.php' ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary" id="btn_guardar" <?= !$alumno ? 'disabled' : '' ?>>
                        <i class="fas fa-save"></i> Guardar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables para búsqueda de alumnos
    const busquedaInput = document.getElementById('busqueda_alumno');
    const buscarBtn = document.getElementById('btn_buscar');
    const resultadosDiv = document.getElementById('resultados_busqueda');
    const listaAlumnos = document.getElementById('lista_alumnos');
    const filtroGrupo = document.getElementById('filtro_grupo');
    
    // Variables para alumno seleccionado
    const alumnoSeleccionadoDiv = document.getElementById('alumno_seleccionado');
    const fotoAlumno = document.getElementById('foto_alumno');
    const nombreAlumno = document.getElementById('nombre_alumno');
    const matriculaAlumno = document.getElementById('matricula_alumno');
    const grupoAlumno = document.getElementById('grupo_alumno');
    const idAlumnoInput = document.getElementById('id_alumno');
    const cambiarAlumnoBtn = document.getElementById('cambiar_alumno');
    
    // Variables para secciones del formulario
    const infoRegistroDiv = document.getElementById('info_registro');
    const archivosAdjuntosDiv = document.getElementById('archivos_adjuntos');
    const btnGuardar = document.getElementById('btn_guardar');
    
    // Variables para campos dinámicos
    const tipoRegistroSelect = document.getElementById('tipo_registro');
    const categoriaSelect = document.getElementById('categoria');
    const campoCalificacion = document.getElementById('campo_calificacion');
    
    // Eventos para búsqueda de alumnos
    if (buscarBtn) {
        buscarBtn.addEventListener('click', function() {
            realizarBusqueda();
        });
    }
    
    if (busquedaInput) {
        busquedaInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                realizarBusqueda();
            }
        });
    }
    
    if (filtroGrupo) {
        filtroGrupo.addEventListener('change', function() {
            if (busquedaInput && busquedaInput.value.length >= 3) {
                realizarBusqueda();
            }
        });
    }
    
    // Cambiar alumno
    if (cambiarAlumnoBtn) {
        cambiarAlumnoBtn.addEventListener('click', function() {
            alumnoSeleccionadoDiv.style.display = 'none';
            resultadosDiv.style.display = 'none';
            busquedaInput.value = '';
            idAlumnoInput.value = '';
            infoRegistroDiv.style.display = 'none';
            archivosAdjuntosDiv.style.display = 'none';
            btnGuardar.disabled = true;
        });
    }
    
    // Evento para tipo de registro
    tipoRegistroSelect.addEventListener('change', function() {
        const tipoSeleccionado = this.value;
        
        // Reiniciar categoría
        categoriaSelect.innerHTML = '<option value="">Cargando categorías...</option>';
        categoriaSelect.disabled = true;
        
        // Mostrar/ocultar campo de calificación
        campoCalificacion.style.display = tipoSeleccionado === 'academico' ? 'block' : 'none';
        
        if (tipoSeleccionado) {
            // Cargar categorías mediante AJAX
            fetch(`ajax/cargar_categorias.php?tipo=${tipoSeleccionado}`)
                .then(response => response.json())
                .then(data => {
                    categoriaSelect.innerHTML = '';
                    
                    Object.keys(data).forEach(key => {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = data[key];
                        categoriaSelect.appendChild(option);
                    });
                    
                    categoriaSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    categoriaSelect.innerHTML = '<option value="">Error al cargar categorías</option>';
                });
        } else {
            categoriaSelect.innerHTML = '<option value="">Seleccione primero un tipo de registro</option>';
        }
    });
    
    // Función para realizar búsqueda de alumnos
    function realizarBusqueda() {
        const busqueda = busquedaInput.value.trim();
        const idGrupo = filtroGrupo.value;
        
        if (busqueda.length < 3) {
            alert('Ingrese al menos 3 caracteres para realizar la búsqueda');
            return;
        }
        
        listaAlumnos.innerHTML = '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        resultadosDiv.style.display = 'block';
        
        fetch(`ajax/buscar_alumnos.php?q=${encodeURIComponent(busqueda)}&grupo=${idGrupo}`)
            .then(response => response.json())
            .then(data => {
                listaAlumnos.innerHTML = '';
                
                if (data.length === 0) {
                    listaAlumnos.innerHTML = '<div class="alert alert-info">No se encontraron alumnos con los criterios especificados.</div>';
                } else {
                    data.forEach(alumno => {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="../../uploads/alumnos/${alumno.id_alumno}.jpg" 
                                         class="rounded-circle me-3" width="40" height="40" 
                                         alt="${alumno.nombre} ${alumno.apellido}"
                                         onerror="this.src='../../assets/img/user-default.png'">
                                    <div>
                                        <h6 class="mb-0">${alumno.nombre} ${alumno.apellido}</h6>
                                        <small class="text-muted">Matrícula: ${alumno.matricula} | Grupo: ${alumno.nombre_grupo}</small>
                                    </div>
                                </div>
                                <span class="badge bg-primary">Seleccionar</span>
                            </div>
                        `;
                        
                        item.addEventListener('click', function() {
                            seleccionarAlumno(alumno);
                        });
                        
                        listaAlumnos.appendChild(item);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                listaAlumnos.innerHTML = '<div class="alert alert-danger">Error al realizar la búsqueda. Intente nuevamente.</div>';
            });
    }
    
    // Función para seleccionar un alumno
    function seleccionarAlumno(alumno) {
        fotoAlumno.src = `../../uploads/alumnos/${alumno.id_alumno}.jpg`;
        fotoAlumno.onerror = function() { this.src = '../../assets/img/user-default.png'; };
        nombreAlumno.textContent = `${alumno.nombre} ${alumno.apellido}`;
        matriculaAlumno.textContent = alumno.matricula;
        grupoAlumno.textContent = alumno.nombre_grupo;
        idAlumnoInput.value = alumno.id_alumno;
        
        resultadosDiv.style.display = 'none';
        alumnoSeleccionadoDiv.style.display = 'flex';
        infoRegistroDiv.style.display = 'block';
        archivosAdjuntosDiv.style.display = 'block';
        btnGuardar.disabled = false;
    }
    
    // Validación del formulario
    document.getElementById('form-registro').addEventListener('submit', function(e) {
        // Validar que se haya seleccionado un alumno
        if (idAlumnoInput.value === '') {
            e.preventDefault();
            alert('Debe seleccionar un alumno');
            return;
        }
        
        // Validar que se haya seleccionado tipo y categoría
        if (tipoRegistroSelect.value === '') {
            e.preventDefault();
            alert('Debe seleccionar un tipo de registro');
            tipoRegistroSelect.focus();
            return;
        }
        
        if (categoriaSelect.value === '') {
            e.preventDefault();
            alert('Debe seleccionar una categoría');
            categoriaSelect.focus();
            return;
        }
        
        // Validar fecha (no futura)
        const fechaEvento = new Date(document.getElementById('fecha_evento').value);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        
        if (fechaEvento > hoy) {
            e.preventDefault();
            alert('La fecha del evento no puede ser futura');
            document.getElementById('fecha_evento').focus();
            return;
        }
        
        // Validar archivos
        const fileInput = document.getElementById('archivos');
        if (fileInput.files.length > 0) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
            
            for (let i = 0; i < fileInput.files.length; i++) {
                const file = fileInput.files[i];
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert(`El archivo "${file.name}" excede el tamaño máximo permitido (5MB)`);
                    return;
                }
                
                const fileType = file.type;
                if (!allowedTypes.includes(fileType)) {
                    e.preventDefault();
                    alert(`El archivo "${file.name}" tiene un formato no permitido. Use PDF, DOC, DOCX, JPG o PNG`);
                    return;
                }
            }
        }
    });
});
</script>