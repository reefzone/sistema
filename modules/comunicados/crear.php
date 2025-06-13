<?php
/**
 * Crear/Editar Comunicado
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';
require_once '../../includes/mail_functions.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador', 'profesor'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar si es edición o nuevo
$id_comunicado = isset($_GET['id']) ? intval($_GET['id']) : 0;
$es_edicion = ($id_comunicado > 0);
$comunicado = null;
$adjuntos = [];

// Si es edición, obtener datos del comunicado
if ($es_edicion) {
    // Consultar comunicado
    $query = "SELECT c.*, 
              CONCAT(u.nombre, ' ', u.apellido_paterno) as enviado_por_nombre
              FROM comunicados c
              LEFT JOIN usuarios u ON c.enviado_por = u.id_usuario
              WHERE c.id_comunicado = ? AND c.eliminado = 0";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_comunicado);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        redireccionar_con_mensaje('index.php', 'El comunicado no existe o ha sido eliminado', 'danger');
    }
    
    $comunicado = $result->fetch_assoc();
    
    // Verificar permisos (solo el creador o superadmin pueden editar)
    if ($_SESSION['tipo_usuario'] != 'superadmin' && $comunicado['enviado_por'] != $_SESSION['id_usuario']) {
        redireccionar_con_mensaje('index.php', 'No tienes permisos para editar este comunicado', 'danger');
    }
    
    // Verificar que esté en estado borrador
    if ($comunicado['estado'] != 'borrador') {
        redireccionar_con_mensaje('index.php', 'Solo se pueden editar comunicados en estado borrador', 'warning');
    }
    
    // Obtener adjuntos del comunicado
    if ($comunicado['tiene_adjuntos']) {
        $query_adjuntos = "SELECT * FROM comunicados_adjuntos WHERE id_comunicado = ? ORDER BY fecha_subida";
        $stmt_adjuntos = $conexion->prepare($query_adjuntos);
        $stmt_adjuntos->bind_param("i", $id_comunicado);
        $stmt_adjuntos->execute();
        $result_adjuntos = $stmt_adjuntos->get_result();
        
        while ($row = $result_adjuntos->fetch_assoc()) {
            $adjuntos[] = $row;
        }
    }
    
    // Obtener destinatarios si son específicos
    $destinatarios_especificos = [];
    if ($comunicado['grupo_especifico']) {
        $query_dest = "SELECT cd.id_alumno, a.matricula, a.nombre, a.apellido, g.nombre_grupo 
                      FROM comunicados_destinatarios cd
                      JOIN alumnos a ON cd.id_alumno = a.id_alumno
                      JOIN grupos g ON a.id_grupo = g.id_grupo
                      WHERE cd.id_comunicado = ?";
        $stmt_dest = $conexion->prepare($query_dest);
        $stmt_dest->bind_param("i", $id_comunicado);
        $stmt_dest->execute();
        $result_dest = $stmt_dest->get_result();
        
        while ($row = $result_dest->fetch_assoc()) {
            $destinatarios_especificos[$row['id_alumno']] = $row;
        }
    }
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

// Obtener grupos
$grupos = [];
$query_grupos = "SELECT g.id_grupo, CONCAT(g.nombre_grupo, ' - ', gr.nombre_grado, ' - ', t.nombre_turno) as nombre_completo 
                FROM grupos g 
                JOIN grados gr ON g.id_grado = gr.id_grado 
                JOIN turnos t ON g.id_turno = t.id_turno 
                WHERE g.activo = 1
                ORDER BY t.id_turno, gr.id_grado, g.nombre_grupo";
$result_grupos = $conexion->query($query_grupos);
while ($row = $result_grupos->fetch_assoc()) {
    $grupos[$row['id_grupo']] = $row['nombre_completo'];
}

// Obtener plantillas de comunicados
$plantillas = [];
$query_plantillas = "SELECT id_plantilla, nombre, descripcion FROM comunicados_plantillas ORDER BY nombre";
$result_plantillas = $conexion->query($query_plantillas);
while ($row = $result_plantillas->fetch_assoc()) {
    $plantillas[$row['id_plantilla']] = $row['nombre'] . ($row['descripcion'] ? ' - ' . $row['descripcion'] : '');
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-<?= $es_edicion ? 'edit' : 'plus-circle' ?>"></i> <?= $es_edicion ? 'Editar' : 'Nuevo' ?> Comunicado</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
        </div>
    </div>
    
    <form id="form-comunicado" action="guardar.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
        <?php if ($es_edicion): ?>
        <input type="hidden" name="id_comunicado" value="<?= $id_comunicado ?>">
        <?php endif; ?>
        
        <div class="row">
            <!-- Columna izquierda: Formulario principal -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-pen"></i> Contenido del Comunicado</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título del Comunicado</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" 
                                   value="<?= $es_edicion ? htmlspecialchars($comunicado['titulo']) : '' ?>" required>
                            <div class="form-text">Ingrese un título descriptivo y claro.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_plantilla" class="form-label">Usar Plantilla (opcional)</label>
                            <select class="form-select" id="id_plantilla" name="id_plantilla">
                                <option value="">-- Seleccione una plantilla --</option>
                                <?php foreach ($plantillas as $id => $nombre): ?>
                                <option value="<?= $id ?>" <?= ($es_edicion && $comunicado['id_plantilla'] == $id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nombre) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Si selecciona una plantilla, se cargará automáticamente en el editor.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contenido" class="form-label">Contenido</label>
                            <textarea class="form-control" id="contenido" name="contenido" rows="15"><?= $es_edicion ? $comunicado['contenido'] : '' ?></textarea>
                            <div class="form-text">
                                Puede utilizar las siguientes variables en su comunicado:
                                <span class="badge bg-secondary">{{NOMBRE_ALUMNO}}</span>
                                <span class="badge bg-secondary">{{NOMBRE_CONTACTO}}</span>
                                <span class="badge bg-secondary">{{GRADO}}</span>
                                <span class="badge bg-secondary">{{GRUPO}}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Archivos adjuntos -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-paperclip"></i> Archivos Adjuntos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($adjuntos)): ?>
                        <h6>Archivos Adjuntos Actuales</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Tamaño</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($adjuntos as $adjunto): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= $adjunto['ruta'] ?>" target="_blank">
                                                <?= htmlspecialchars($adjunto['nombre_original']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($adjunto['tipo']) ?></td>
                                        <td><?= formatear_tamano($adjunto['tamano']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger btn-eliminar-adjunto" 
                                                    data-id="<?= $adjunto['id_adjunto'] ?>" 
                                                    data-nombre="<?= htmlspecialchars($adjunto['nombre_original']) ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="adjuntos" class="form-label">Añadir Nuevos Archivos Adjuntos</label>
                            <input class="form-control" type="file" id="adjuntos" name="adjuntos[]" multiple>
                            <div class="form-text">
                                Formatos permitidos: PDF, imágenes (JPG, PNG, GIF), documentos (DOC, DOCX, XLS, XLSX, PPT, PPTX).<br>
                                Tamaño máximo por archivo: 5MB. Número máximo de archivos: 5.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Columna derecha: Opciones y destinatarios -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-cog"></i> Opciones de Envío</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="prioridad" class="form-label">Prioridad</label>
                            <select class="form-select" id="prioridad" name="prioridad" required>
                                <option value="baja" <?= ($es_edicion && $comunicado['prioridad'] == 'baja') ? 'selected' : '' ?>>Baja</option>
                                <option value="normal" <?= (!$es_edicion || ($es_edicion && $comunicado['prioridad'] == 'normal')) ? 'selected' : '' ?>>Normal</option>
                                <option value="alta" <?= ($es_edicion && $comunicado['prioridad'] == 'alta') ? 'selected' : '' ?>>Alta</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="programar_envio" class="form-label">Programar Envío</label>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="programar" 
                                       <?= ($es_edicion && $comunicado['estado'] == 'programado') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="programar">Programar para envío posterior</label>
                            </div>
                            
                            <div id="seccion-programacion" class="<?= ($es_edicion && $comunicado['estado'] == 'programado') ? '' : 'd-none' ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="date" class="form-control" id="fecha_programada" name="fecha_programada" 
                                               value="<?= ($es_edicion && $comunicado['fecha_envio']) ? date('Y-m-d', strtotime($comunicado['fecha_envio'])) : date('Y-m-d') ?>"
                                               min="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="time" class="form-control" id="hora_programada" name="hora_programada" 
                                               value="<?= ($es_edicion && $comunicado['fecha_envio']) ? date('H:i', strtotime($comunicado['fecha_envio'])) : date('H:i') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="borrador" <?= (!$es_edicion || ($es_edicion && $comunicado['estado'] == 'borrador')) ? 'selected' : '' ?>>Guardar como Borrador</option>
                                <option value="enviar">Enviar Ahora</option>
                                <option value="programado" class="<?= ($es_edicion && $comunicado['estado'] == 'programado') ? '' : 'd-none' ?>">Programado</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-users"></i> Destinatarios</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Destinatarios</label>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" id="tipo-todos" value="todos" 
                                       <?= (!$es_edicion || ($es_edicion && !$comunicado['grupo_especifico'] && $comunicado['id_grupo'] === null)) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo-todos">
                                    Todos los alumnos
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" id="tipo-grupo" value="grupo" 
                                       <?= ($es_edicion && !$comunicado['grupo_especifico'] && $comunicado['id_grupo'] !== null) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo-grupo">
                                    Un grupo específico
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" id="tipo-alumnos" value="alumnos" 
                                       <?= ($es_edicion && $comunicado['grupo_especifico']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tipo-alumnos">
                                    Alumnos específicos
                                </label>
                            </div>
                        </div>
                        
                        <!-- Selector de grupo -->
                        <div id="seccion-grupo" class="mb-3 <?= ($es_edicion && !$comunicado['grupo_especifico'] && $comunicado['id_grupo'] !== null) ? '' : 'd-none' ?>">
                            <label for="id_grupo" class="form-label">Seleccionar Grupo</label>
                            <select class="form-select" id="id_grupo" name="id_grupo">
                                <option value="">-- Seleccione un grupo --</option>
                                <?php foreach ($grupos as $id => $nombre): ?>
                                <option value="<?= $id ?>" <?= ($es_edicion && $comunicado['id_grupo'] == $id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nombre) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Selector de alumnos específicos -->
                        <div id="seccion-alumnos" class="mb-3 <?= ($es_edicion && $comunicado['grupo_especifico']) ? '' : 'd-none' ?>">
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <label for="filtro_turno" class="form-label">Turno</label>
                                    <select class="form-select" id="filtro_turno">
                                        <option value="0">Todos los turnos</option>
                                        <?php foreach ($turnos as $id => $nombre): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="filtro_grado" class="form-label">Grado</label>
                                    <select class="form-select" id="filtro_grado">
                                        <option value="0">Todos los grados</option>
                                        <?php foreach ($grados as $id => $nombre): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <label for="filtro_grupo" class="form-label">Grupo</label>
                                    <select class="form-select" id="filtro_grupo">
                                        <option value="0">Todos los grupos</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="buscar_alumno" class="form-label">Buscar Alumno</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="buscar_alumno" placeholder="Nombre o matrícula">
                                        <button class="btn btn-outline-primary" type="button" id="btn-buscar-alumno">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label">Resultados</label>
                                <div class="card">
                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                        <ul class="list-group" id="resultados-alumnos">
                                            <!-- Aquí se cargan los resultados de búsqueda -->
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label">Alumnos Seleccionados</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div id="alerta-sin-alumnos" class="alert alert-warning mb-0 <?= ($es_edicion && $comunicado['grupo_especifico'] && !empty($destinatarios_especificos)) ? 'd-none' : '' ?>">
                                            <i class="fas fa-exclamation-triangle me-2"></i> No hay alumnos seleccionados. Utilice los filtros o búsqueda para añadir alumnos.
                                        </div>
                                        
                                        <ul class="list-group list-group-flush" id="alumnos-seleccionados">
                                            <?php if ($es_edicion && $comunicado['grupo_especifico'] && !empty($destinatarios_especificos)): 
                                                foreach ($destinatarios_especificos as $id_alumno => $alumno): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center" id="alumno-seleccionado-<?= $id_alumno ?>">
                                                <span>
                                                    <strong><?= htmlspecialchars($alumno['apellido'] . ' ' . $alumno['nombre']) ?></strong> - 
                                                    <small class="text-muted"><?= htmlspecialchars($alumno['matricula']) ?> (<?= htmlspecialchars($alumno['nombre_grupo']) ?>)</small>
                                                </span>
                                                <input type="hidden" name="alumnos_seleccionados[]" value="<?= $id_alumno ?>">
                                                <button type="button" class="btn btn-sm btn-danger btn-quitar-alumno" data-id-alumno="<?= $id_alumno ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </li>
                                            <?php endforeach; endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="button" id="btn-vista-previa" class="btn btn-info">
                        <i class="fas fa-eye"></i> Vista Previa
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> <?= $es_edicion ? 'Actualizar' : 'Guardar' ?> Comunicado
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal de confirmación de eliminación de adjunto -->
<div class="modal fade" id="eliminarAdjuntoModal" tabindex="-1" aria-labelledby="eliminarAdjuntoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="eliminarAdjuntoModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar el archivo adjunto <strong id="nombre-adjunto"></strong>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="form-eliminar-adjunto" action="eliminar_adjunto.php" method="post">
                    <input type="hidden" name="id_adjunto" id="id-adjunto">
                    <input type="hidden" name="id_comunicado" value="<?= $id_comunicado ?>">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de vista previa -->
<div class="modal fade" id="vistaPreviaModal" tabindex="-1" aria-labelledby="vistaPreviaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="vistaPreviaModalLabel">
                    <i class="fas fa-eye me-2"></i>
                    Vista Previa del Comunicado
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 id="vista-previa-titulo"></h4>
                <hr>
                <div id="vista-previa-contenido"></div>
                
                <div id="vista-previa-adjuntos" class="mt-3">
                    <h5><i class="fas fa-paperclip"></i> Archivos Adjuntos</h5>
                    <ul class="list-group list-group-flush" id="lista-adjuntos-vista-previa"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar TinyMCE
    tinymce.init({
        selector: '#contenido',
        plugins: 'autolink lists link image charmap print preview anchor pagebreak',
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image',
        height: 400,
        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });
    
    // Control de programación de envío
    const checkProgramar = document.getElementById('programar');
    const seccionProgramacion = document.getElementById('seccion-programacion');
    const selectEstado = document.getElementById('estado');
    
    checkProgramar.addEventListener('change', function() {
        if (this.checked) {
            seccionProgramacion.classList.remove('d-none');
            // Actualizar opciones del select de estado
            if (Array.from(selectEstado.options).find(opt => opt.value === 'programado')) {
                selectEstado.value = 'programado';
            } else {
                const optProgramado = document.createElement('option');
                optProgramado.value = 'programado';
                optProgramado.text = 'Programado';
                selectEstado.add(optProgramado);
                selectEstado.value = 'programado';
            }
        } else {
            seccionProgramacion.classList.add('d-none');
            // Actualizar opciones del select de estado
            selectEstado.value = 'borrador';
        }
    });
    
    // Control de tipo de destinatarios
    const radiosTipoDestinatario = document.querySelectorAll('.tipo-destinatario');
    const seccionGrupo = document.getElementById('seccion-grupo');
    const seccionAlumnos = document.getElementById('seccion-alumnos');
    
    radiosTipoDestinatario.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'todos') {
                seccionGrupo.classList.add('d-none');
                seccionAlumnos.classList.add('d-none');
            } else if (this.value === 'grupo') {
                seccionGrupo.classList.remove('d-none');
                seccionAlumnos.classList.add('d-none');
            } else if (this.value === 'alumnos') {
                seccionGrupo.classList.add('d-none');
                seccionAlumnos.classList.remove('d-none');
            }
        });
    });
    
    // Carga de plantillas
    const selectPlantilla = document.getElementById('id_plantilla');
    
    selectPlantilla.addEventListener('change', function() {
        const id_plantilla = this.value;
        
        if (id_plantilla) {
            // Cargar la plantilla desde el servidor
            fetch('../../includes/ajax/obtener_plantilla.php?id=' + id_plantilla)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tinymce.get('contenido').setContent(data.contenido);
                    } else {
                        alert('Error al cargar la plantilla: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al cargar la plantilla');
                });
        }
    });
    
    // Filtros de alumnos
    const filtroTurno = document.getElementById('filtro_turno');
    const filtroGrado = document.getElementById('filtro_grado');
    const filtroGrupo = document.getElementById('filtro_grupo');
    
    // Función para cargar grupos según turno y grado seleccionados
    function cargarGrupos() {
        const id_turno = filtroTurno.value;
        const id_grado = filtroGrado.value;
        
        if (id_turno > 0 && id_grado > 0) {
            fetch('../../includes/ajax/obtener_grupos.php?turno=' + id_turno + '&grado=' + id_grado)
                .then(response => response.json())
                .then(data => {
                    // Limpiar select
                    filtroGrupo.innerHTML = '<option value="0">Todos los grupos</option>';
                    
                    // Añadir opciones
                    data.forEach(grupo => {
                        const option = document.createElement('option');
                        option.value = grupo.id_grupo;
                        option.textContent = grupo.nombre_grupo;
                        filtroGrupo.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        } else {
            // Limpiar select si no hay suficientes datos
            filtroGrupo.innerHTML = '<option value="0">Todos los grupos</option>';
        }
    }
    
    filtroTurno.addEventListener('change', cargarGrupos);
    filtroGrado.addEventListener('change', cargarGrupos);
    
    // Buscar alumnos
    const buscarAlumnoInput = document.getElementById('buscar_alumno');
    const btnBuscarAlumno = document.getElementById('btn-buscar-alumno');
    const resultadosAlumnos = document.getElementById('resultados-alumnos');
    
    function buscarAlumnos() {
        const busqueda = buscarAlumnoInput.value.trim();
        if (busqueda.length < 3) {
            resultadosAlumnos.innerHTML = '<li class="list-group-item text-muted">Ingrese al menos 3 caracteres para buscar</li>';
            return;
        }
        
        const filtroGrupoVal = filtroGrupo.value;
        let url = '../../includes/ajax/buscar_alumnos.php?q=' + encodeURIComponent(busqueda);
        
        // Añadir filtros si están seleccionados
        if (filtroGrupoVal > 0) {
            url += '&grupo=' + filtroGrupoVal;
        } else {
            if (filtroTurno.value > 0) {
                url += '&turno=' + filtroTurno.value;
            }
            if (filtroGrado.value > 0) {
                url += '&grado=' + filtroGrado.value;
            }
        }
      
	  resultadosAlumnos.innerHTML = '<li class="list-group-item text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</li>';
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                resultadosAlumnos.innerHTML = '';
                
                if (data.length === 0) {
                    resultadosAlumnos.innerHTML = '<li class="list-group-item text-muted">No se encontraron resultados</li>';
                    return;
                }
                
                data.forEach(alumno => {
                    // Verificar si el alumno ya está seleccionado
                    const yaSeleccionado = document.querySelector(`#alumno-seleccionado-${alumno.id_alumno}`);
                    
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    li.innerHTML = `
                        <span>
                            <strong>${alumno.apellido} ${alumno.nombre}</strong> - 
                            <small class="text-muted">${alumno.matricula} (${alumno.nombre_grupo})</small>
                        </span>
                        <button type="button" class="btn btn-sm ${yaSeleccionado ? 'btn-secondary disabled' : 'btn-primary'} btn-agregar-alumno" 
                                data-id-alumno="${alumno.id_alumno}" 
                                data-nombre="${alumno.nombre}" 
                                data-apellido="${alumno.apellido}" 
                                data-matricula="${alumno.matricula}" 
                                data-grupo="${alumno.nombre_grupo}"
                                ${yaSeleccionado ? 'disabled' : ''}>
                            ${yaSeleccionado ? 'Ya añadido' : '<i class="fas fa-plus"></i> Añadir'}
                        </button>
                    `;
                    resultadosAlumnos.appendChild(li);
                });
                
                // Añadir eventos a los botones de añadir alumno
                document.querySelectorAll('.btn-agregar-alumno').forEach(btn => {
                    btn.addEventListener('click', agregarAlumno);
                });
            })
            .catch(error => {
                console.error('Error:', error);
                resultadosAlumnos.innerHTML = '<li class="list-group-item text-danger">Error al buscar alumnos</li>';
            });
    }
    
    btnBuscarAlumno.addEventListener('click', buscarAlumnos);
    buscarAlumnoInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarAlumnos();
        }
    });
    
    // Añadir alumno a la lista de seleccionados
    const alumnosSeleccionados = document.getElementById('alumnos-seleccionados');
    const alertaSinAlumnos = document.getElementById('alerta-sin-alumnos');
    
    function agregarAlumno() {
        const btn = this;
        const id_alumno = btn.getAttribute('data-id-alumno');
        const nombre = btn.getAttribute('data-nombre');
        const apellido = btn.getAttribute('data-apellido');
        const matricula = btn.getAttribute('data-matricula');
        const grupo = btn.getAttribute('data-grupo');
        
        // Verificar si ya está añadido
        if (document.querySelector(`#alumno-seleccionado-${id_alumno}`)) {
            return;
        }
        
        // Crear elemento
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.id = `alumno-seleccionado-${id_alumno}`;
        li.innerHTML = `
            <span>
                <strong>${apellido} ${nombre}</strong> - 
                <small class="text-muted">${matricula} (${grupo})</small>
            </span>
            <input type="hidden" name="alumnos_seleccionados[]" value="${id_alumno}">
            <button type="button" class="btn btn-sm btn-danger btn-quitar-alumno" data-id-alumno="${id_alumno}">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Añadir a la lista
        alumnosSeleccionados.appendChild(li);
        
        // Ocultar alerta de sin alumnos
        alertaSinAlumnos.classList.add('d-none');
        
        // Deshabilitar botón en resultados
        btn.classList.replace('btn-primary', 'btn-secondary');
        btn.classList.add('disabled');
        btn.disabled = true;
        btn.innerHTML = 'Ya añadido';
        
        // Añadir evento al botón de quitar
        li.querySelector('.btn-quitar-alumno').addEventListener('click', quitarAlumno);
    }
    
    // Eliminar alumno de la lista de seleccionados
    function quitarAlumno() {
        const btn = this;
        const id_alumno = btn.getAttribute('data-id-alumno');
        const li = document.querySelector(`#alumno-seleccionado-${id_alumno}`);
        
        // Eliminar de la lista
        if (li) {
            li.remove();
        }
        
        // Habilitar botón en resultados si existe
        const btnAgregar = document.querySelector(`.btn-agregar-alumno[data-id-alumno="${id_alumno}"]`);
        if (btnAgregar) {
            btnAgregar.classList.replace('btn-secondary', 'btn-primary');
            btnAgregar.classList.remove('disabled');
            btnAgregar.disabled = false;
            btnAgregar.innerHTML = '<i class="fas fa-plus"></i> Añadir';
        }
        
        // Mostrar alerta si no hay alumnos
        if (alumnosSeleccionados.children.length === 0) {
            alertaSinAlumnos.classList.remove('d-none');
        }
    }
    
    // Añadir eventos a los botones de quitar alumno existentes
    document.querySelectorAll('.btn-quitar-alumno').forEach(btn => {
        btn.addEventListener('click', quitarAlumno);
    });
    
    // Configurar modal de eliminación de adjunto
    const btnsEliminarAdjunto = document.querySelectorAll('.btn-eliminar-adjunto');
    const modalEliminarAdjunto = document.getElementById('eliminarAdjuntoModal');
    const idAdjunto = document.getElementById('id-adjunto');
    const nombreAdjunto = document.getElementById('nombre-adjunto');
    
    btnsEliminarAdjunto.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-nombre');
            
            idAdjunto.value = id;
            nombreAdjunto.textContent = nombre;
            
            const modal = new bootstrap.Modal(modalEliminarAdjunto);
            modal.show();
        });
    });
    
    // Vista previa
    const btnVistaPrevia = document.getElementById('btn-vista-previa');
    const modalVistaPrevia = document.getElementById('vistaPreviaModal');
    const vistaPreviewTitulo = document.getElementById('vista-previa-titulo');
    const vistaPreviewContenido = document.getElementById('vista-previa-contenido');
    const listaAdjuntosVistaPrevia = document.getElementById('lista-adjuntos-vista-previa');
    const vistaPreviaAdjuntos = document.getElementById('vista-previa-adjuntos');
    
    btnVistaPrevia.addEventListener('click', function() {
        const titulo = document.getElementById('titulo').value;
        const contenido = tinymce.get('contenido').getContent();
        
        if (!titulo) {
            alert('Ingrese un título para el comunicado');
            return;
        }
        
        if (!contenido) {
            alert('Ingrese contenido para el comunicado');
            return;
        }
        
        vistaPreviewTitulo.textContent = titulo;
        vistaPreviewContenido.innerHTML = contenido;
        
        // Añadir adjuntos a la vista previa
        listaAdjuntosVistaPrevia.innerHTML = '';
        
        // Añadir adjuntos existentes
        <?php if (!empty($adjuntos)): ?>
        <?php foreach ($adjuntos as $adjunto): ?>
        listaAdjuntosVistaPrevia.innerHTML += `
            <li class="list-group-item">
                <i class="fas fa-file me-2"></i> <?= htmlspecialchars($adjunto['nombre_original']) ?> 
                <small class="text-muted">(<?= formatear_tamano($adjunto['tamano']) ?>)</small>
            </li>
        `;
        <?php endforeach; ?>
        <?php endif; ?>
        
        // Añadir nuevos adjuntos
        const input_adjuntos = document.getElementById('adjuntos');
        if (input_adjuntos.files.length > 0) {
            for (let i = 0; i < input_adjuntos.files.length; i++) {
                const file = input_adjuntos.files[i];
                listaAdjuntosVistaPrevia.innerHTML += `
                    <li class="list-group-item">
                        <i class="fas fa-file me-2"></i> ${file.name} 
                        <small class="text-muted">(${formatearTamano(file.size)})</small>
                    </li>
                `;
            }
        }
        
        if (listaAdjuntosVistaPrevia.children.length === 0) {
            vistaPreviaAdjuntos.style.display = 'none';
        } else {
            vistaPreviaAdjuntos.style.display = '';
        }
        
        const modal = new bootstrap.Modal(modalVistaPrevia);
        modal.show();
    });
    
    // Función para formatear tamaño de archivo
    function formatearTamano(bytes) {
        if (bytes < 1024) {
            return bytes + ' bytes';
        } else if (bytes < 1048576) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return (bytes / 1048576).toFixed(2) + ' MB';
        }
    }
    
    // Validación del formulario
    const formComunicado = document.getElementById('form-comunicado');
    
    formComunicado.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const titulo = document.getElementById('titulo').value;
        const contenido = tinymce.get('contenido').getContent();
        
        if (!titulo) {
            alert('Ingrese un título para el comunicado');
            return;
        }
        
        if (!contenido) {
            alert('Ingrese contenido para el comunicado');
            return;
        }
        
        // Validar destinatarios
        const tipoDestinatario = document.querySelector('input[name="tipo_destinatario"]:checked').value;
        
        if (tipoDestinatario === 'grupo' && !document.getElementById('id_grupo').value) {
            alert('Seleccione un grupo para enviar el comunicado');
            return;
        }
        
        if (tipoDestinatario === 'alumnos' && alumnosSeleccionados.children.length === 0) {
            alert('Seleccione al menos un alumno para enviar el comunicado');
            return;
        }
        
        // Validar adjuntos
        const input_adjuntos = document.getElementById('adjuntos');
        if (input_adjuntos.files.length > 5) {
            alert('No puede adjuntar más de 5 archivos');
            return;
        }
        
        // Validar tamaño de archivos adjuntos (máximo 5MB por archivo)
        for (let i = 0; i < input_adjuntos.files.length; i++) {
            if (input_adjuntos.files[i].size > 5 * 1024 * 1024) {
                alert(`El archivo "${input_adjuntos.files[i].name}" excede el tamaño máximo permitido (5MB)`);
                return;
            }
        }
        
        // Validar programación
        if (document.getElementById('programar').checked) {
            const fechaProgramada = document.getElementById('fecha_programada').value;
            const horaProgramada = document.getElementById('hora_programada').value;
            
            if (!fechaProgramada || !horaProgramada) {
                alert('Ingrese fecha y hora para programar el envío');
                return;
            }
            
            const fechaHoraProgramada = new Date(`${fechaProgramada}T${horaProgramada}`);
            const ahora = new Date();
            
            if (fechaHoraProgramada <= ahora) {
                alert('La fecha y hora programada debe ser futura');
                return;
            }
        }
        
        // Todo válido, enviar formulario
        this.submit();
    });
});
</script>