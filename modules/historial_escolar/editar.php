<?php
/**
 * Edición de Entrada en Historial Escolar
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

// Verificar ID del registro
$id_historial = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_historial <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de registro no válido', 'danger');
}

// Obtener datos del registro
$query = "SELECT h.*, a.id_alumno, CONCAT(a.nombre, ' ', a.apellido) as nombre_alumno, 
          a.matricula, g.nombre_grupo, gr.nombre_grado, t.nombre_turno
          FROM historial_escolar h
          JOIN alumnos a ON h.id_alumno = a.id_alumno
          JOIN grupos g ON a.id_grupo = g.id_grupo
          JOIN grados gr ON g.id_grado = gr.id_grado
          JOIN turnos t ON g.id_turno = t.id_turno
          WHERE h.id_historial = ? AND h.eliminado = 0";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_historial);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'Registro no encontrado o eliminado', 'danger');
}

$registro = $result->fetch_assoc();

// Verificar restricciones de edición
$fecha_registro = new DateTime($registro['fecha_registro']);
$fecha_actual = new DateTime();
$diferencia = $fecha_actual->diff($fecha_registro);
$dias_diferencia = $diferencia->days;

// Solo superadmin puede editar registros antiguos (más de 7 días)
if ($dias_diferencia > 7 && $_SESSION['tipo_usuario'] !== 'superadmin') {
    redireccionar_con_mensaje('ver.php?id='.$registro['id_alumno'], 'No puede editar registros con más de 7 días de antigüedad', 'danger');
}

// Obtener adjuntos
$query_adjuntos = "SELECT * FROM historial_adjuntos 
                   WHERE id_historial = ? AND eliminado = 0";
$stmt_adjuntos = $conexion->prepare($query_adjuntos);
$stmt_adjuntos->bind_param("i", $id_historial);
$stmt_adjuntos->execute();
$result_adjuntos = $stmt_adjuntos->get_result();

$adjuntos = [];
while ($row = $result_adjuntos->fetch_assoc()) {
    $adjuntos[] = $row;
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
                    <li class="breadcrumb-item"><a href="ver.php?id=<?= $registro['id_alumno'] ?>">Ver Historial</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar Registro</li>
                </ol>
            </nav>
            <h1><i class="fas fa-edit"></i> Editar Registro en Historial</h1>
        </div>
    </div>
    
    <!-- Formulario de edición -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-edit"></i> Formulario de Edición
            </h5>
        </div>
        <div class="card-body">
            <form action="actualizar.php" method="post" enctype="multipart/form-data" id="form-edicion">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <input type="hidden" name="id_historial" value="<?= $id_historial ?>">
                <input type="hidden" name="id_alumno" value="<?= $registro['id_alumno'] ?>">
                
                <!-- Alumno -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Alumno</h5>
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <img src="../../uploads/alumnos/<?= $registro['id_alumno'] ?>.jpg" 
                                 class="rounded-circle" width="80" height="80" 
                                 alt="<?= htmlspecialchars($registro['nombre_alumno']) ?>"
                                 onerror="this.src='../../assets/img/user-default.png'">
                        </div>
                        <div class="col">
                            <h5 class="mb-1"><?= htmlspecialchars($registro['nombre_alumno']) ?></h5>
                            <p class="mb-0 text-muted">
                                Matrícula: <?= htmlspecialchars($registro['matricula']) ?> | 
                                Grupo: <?= htmlspecialchars($registro['nombre_grupo']) ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Información del registro -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Información del Registro</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="tipo_registro" class="form-label">Tipo de Registro *</label>
                            <select name="tipo_registro" id="tipo_registro" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="academico" <?= $registro['tipo_registro'] == 'academico' ? 'selected' : '' ?>>Académico</option>
                                <option value="asistencia" <?= $registro['tipo_registro'] == 'asistencia' ? 'selected' : '' ?>>Asistencia</option>
                                <option value="conducta" <?= $registro['tipo_registro'] == 'conducta' ? 'selected' : '' ?>>Conducta</option>
                                <option value="reconocimiento" <?= $registro['tipo_registro'] == 'reconocimiento' ? 'selected' : '' ?>>Reconocimiento</option>
                                <option value="observacion" <?= $registro['tipo_registro'] == 'observacion' ? 'selected' : '' ?>>Observación General</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select name="categoria" id="categoria" class="form-select" required>
                                <option value="">Cargando categorías...</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="fecha_evento" class="form-label">Fecha del Evento *</label>
                            <input type="date" name="fecha_evento" id="fecha_evento" class="form-control" 
                                   required max="<?= date('Y-m-d') ?>" value="<?= $registro['fecha_evento'] ?>">
                        </div>
                        
                        <div class="col-md-8">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" name="titulo" id="titulo" class="form-control" 
                                   required maxlength="100" value="<?= htmlspecialchars($registro['titulo']) ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="relevancia" class="form-label">Relevancia</label>
                            <select name="relevancia" id="relevancia" class="form-select">
                                <option value="normal" <?= $registro['relevancia'] == 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="alta" <?= $registro['relevancia'] == 'alta' ? 'selected' : '' ?>>Alta</option>
                                <option value="baja" <?= $registro['relevancia'] == 'baja' ? 'selected' : '' ?>>Baja</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea name="descripcion" id="descripcion" class="form-control" 
                                      rows="5" required><?= htmlspecialchars($registro['descripcion']) ?></textarea>
                        </div>
                        
                        <!-- Campo calificación (visible solo para tipo académico) -->
                        <div class="col-md-4" id="campo_calificacion" style="display: <?= $registro['tipo_registro'] == 'academico' ? 'block' : 'none' ?>;">
                            <label for="calificacion" class="form-label">Calificación</label>
                            <input type="number" name="calificacion" id="calificacion" class="form-control" 
                                   min="0" max="10" step="0.1" value="<?= $registro['calificacion'] ?>">
                            <div class="form-text">Deje en blanco si no aplica</div>
                        </div>
                    </div>
                </div>
                
                <!-- Archivos adjuntos existentes -->
                <?php if (count($adjuntos) > 0): ?>
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Archivos Adjuntos Existentes</h5>
                    <div class="list-group">
                        <?php foreach ($adjuntos as $adjunto): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="far fa-file me-2"></i>
                                <a href="../../<?= $adjunto['ruta'] ?>" target="_blank">
                                    <?= htmlspecialchars($adjunto['nombre_original']) ?>
                                </a>
                                <span class="badge bg-primary rounded-pill ms-2">
                                    <?= formatear_tamano_archivo($adjunto['tamano']) ?>
                                </span>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="eliminar_adjunto[]" 
                                       value="<?= $adjunto['id_adjunto'] ?>" id="adjunto_<?= $adjunto['id_adjunto'] ?>">
                                <label class="form-check-label text-danger" for="adjunto_<?= $adjunto['id_adjunto'] ?>">
                                    Eliminar
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Nuevos archivos adjuntos -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Agregar Nuevos Archivos</h5>
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
                
                <!-- Información de auditoría -->
                <div class="mb-4">
                    <h5 class="border-bottom pb-2">Información de Auditoría</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Registrado por:</strong> 
                                <?= obtener_nombre_usuario($conexion, $registro['registrado_por']) ?>
                            </p>
                            <p class="mb-1"><strong>Fecha de registro:</strong> 
                                <?= date('d/m/Y H:i', strtotime($registro['fecha_registro'])) ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <?php if (!is_null($registro['modificado_por'])): ?>
                            <p class="mb-1"><strong>Última modificación por:</strong> 
                                <?= obtener_nombre_usuario($conexion, $registro['modificado_por']) ?>
                            </p>
                            <p class="mb-1"><strong>Fecha de modificación:</strong> 
                                <?= date('d/m/Y H:i', strtotime($registro['fecha_modificacion'])) ?>
                            </p>
                            <?php else: ?>
                            <p class="mb-1"><strong>Última modificación:</strong> Sin modificaciones</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botones de acción -->
                <div class="text-end mt-4">
                    <a href="ver.php?id=<?= $registro['id_alumno'] ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables para campos
    const tipoRegistroSelect = document.getElementById('tipo_registro');
    const categoriaSelect = document.getElementById('categoria');
    const campoCalificacion = document.getElementById('campo_calificacion');
    const categoriaActual = '<?= $registro['categoria'] ?>';
    
    // Cargar categorías al inicio
    cargarCategorias(tipoRegistroSelect.value, categoriaActual);
    
    // Evento para cambio de tipo de registro
    tipoRegistroSelect.addEventListener('change', function() {
        const tipoSeleccionado = this.value;
        
        // Mostrar/ocultar campo de calificación
        campoCalificacion.style.display = tipoSeleccionado === 'academico' ? 'block' : 'none';
        
        // Cargar categorías
        cargarCategorias(tipoSeleccionado);
    });
    
    // Función para cargar categorías mediante AJAX
    function cargarCategorias(tipo, categoriaSeleccionada = '') {
        if (!tipo) return;
        
        categoriaSelect.innerHTML = '<option value="">Cargando categorías...</option>';
        categoriaSelect.disabled = true;
        
        fetch(`ajax/cargar_categorias.php?tipo=${tipo}`)
            .then(response => response.json())
            .then(data => {
                categoriaSelect.innerHTML = '';
                
                Object.keys(data).forEach(key => {
                    const option = document.createElement('option');
                    option.value = key;
                    option.textContent = data[key];
                    
                    if (key === categoriaSeleccionada) {
                        option.selected = true;
                    }
                    
                    categoriaSelect.appendChild(option);
                });
                
                categoriaSelect.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                categoriaSelect.innerHTML = '<option value="">Error al cargar categorías</option>';
            });
    }
    
    // Validación del formulario
    document.getElementById('form-edicion').addEventListener('submit', function(e) {
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

// Función auxiliar para obtener nombre de usuario
<?php
function obtener_nombre_usuario($conexion, $id_usuario) {
    $query = "SELECT CONCAT(nombre, ' ', apellido_paterno) as nombre_completo 
              FROM usuarios WHERE id_usuario = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return htmlspecialchars($row['nombre_completo']);
    }
    
    return 'Usuario desconocido';
}
?>
</script>