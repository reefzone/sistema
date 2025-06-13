<?php
/**
 * Reenviar Comunicado
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

// Obtener ID del comunicado original
$id_comunicado = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_comunicado <= 0) {
    redireccionar_con_mensaje('index.php', 'Comunicado no válido', 'danger');
}

// Obtener datos del comunicado original
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

// Verificar permisos (solo el creador o superadmin pueden reenviar)
if ($_SESSION['tipo_usuario'] != 'superadmin' && $comunicado['enviado_por'] != $_SESSION['id_usuario']) {
    redireccionar_con_mensaje('index.php', 'No tienes permisos para reenviar este comunicado', 'danger');
}

// Verificar que el comunicado esté en estado enviado
if ($comunicado['estado'] !== 'enviado') {
    redireccionar_con_mensaje('index.php', 'Solo se pueden reenviar comunicados que ya han sido enviados', 'warning');
}

// Obtener adjuntos del comunicado original
$adjuntos = [];
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

// Obtener destinatarios originales
$destinatarios_originales = [];
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
        $destinatarios_originales[$row['id_alumno']] = $row;
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

// Procesar formulario de reenvío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!verificar_token_csrf($_POST['csrf_token'])) {
        redireccionar_con_mensaje('index.php', 'Token de seguridad inválido', 'danger');
    }
    
    // Obtener datos del formulario
    $tipo_destinatario = sanitizar_texto($_POST['tipo_destinatario']);
    $editar_contenido = isset($_POST['editar_contenido']) ? true : false;
    $titulo = $editar_contenido ? sanitizar_texto($_POST['titulo']) : $comunicado['titulo'];
    $contenido = $editar_contenido ? $_POST['contenido'] : $comunicado['contenido'];
    $programar_envio = isset($_POST['programar_envio']) ? true : false;
    
    // Determinar id_grupo y grupo_especifico según tipo de destinatario
    $id_grupo = null;
    $grupo_especifico = 0;
    
    switch ($tipo_destinatario) {
        case 'todos':
            $id_grupo = null;
            $grupo_especifico = 0;
            break;
        case 'grupo':
            $id_grupo = isset($_POST['id_grupo']) ? intval($_POST['id_grupo']) : null;
            $grupo_especifico = 0;
            break;
        case 'alumnos':
            $id_grupo = null;
            $grupo_especifico = 1;
            break;
        case 'originales':
            $id_grupo = $comunicado['id_grupo'];
            $grupo_especifico = $comunicado['grupo_especifico'];
            break;
    }
    
    // Verificar fecha de envío
    $fecha_envio = null;
    if ($programar_envio) {
        $fecha_programada = sanitizar_texto($_POST['fecha_programada']);
        $hora_programada = sanitizar_texto($_POST['hora_programada']);
        $fecha_envio = $fecha_programada . ' ' . $hora_programada . ':00';
        $estado = 'programado';
    } else {
        $fecha_envio = date('Y-m-d H:i:s');
        $estado = 'enviado';
    }
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Crear nuevo comunicado basado en el original
        $query = "INSERT INTO comunicados (
                  titulo, 
                  contenido, 
                  id_grupo, 
                  fecha_creacion, 
                  fecha_envio, 
                  enviado_por, 
                  estado, 
                  tiene_adjuntos, 
                  id_plantilla, 
                  prioridad, 
                  grupo_especifico,
                  id_comunicado_original
                 ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("ssssissiiii", 
            $titulo, 
            $contenido, 
            $id_grupo, 
            $fecha_envio, 
           $_SESSION['id_usuario'], 
            $estado, 
            $comunicado['tiene_adjuntos'], 
            $comunicado['id_plantilla'], 
            $comunicado['prioridad'], 
            $grupo_especifico,
            $id_comunicado
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear el nuevo comunicado: " . $conexion->error);
        }
        
        $nuevo_id_comunicado = $conexion->insert_id;
        
        // Copiar adjuntos si existen
        if ($comunicado['tiene_adjuntos']) {
            $year_month = date('Y-m');
            $dir_base = "../../uploads/comunicados/adjuntos/$year_month/";
            $dir_nuevo = $dir_base . $nuevo_id_comunicado;
            
            // Crear directorio para el nuevo comunicado
            if (!is_dir($dir_base)) {
                mkdir($dir_base, 0755, true);
            }
            if (!is_dir($dir_nuevo)) {
                mkdir($dir_nuevo, 0755, true);
            }
            
            // Copiar archivos adjuntos
            $query_adjuntos_nuevos = "INSERT INTO comunicados_adjuntos (
                                    id_comunicado, 
                                    nombre_original, 
                                    nombre_archivo, 
                                    ruta, 
                                    tipo, 
                                    tamano, 
                                    fecha_subida
                                   ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt_adjuntos = $conexion->prepare($query_adjuntos_nuevos);
            
            foreach ($adjuntos as $adjunto) {
                // Copiar archivo físico
                $nombre_archivo = basename($adjunto['ruta']);
                $nueva_ruta = $dir_nuevo . '/' . $nombre_archivo;
                
                if (copy($adjunto['ruta'], $nueva_ruta)) {
                    $stmt_adjuntos->bind_param("issssi", 
                        $nuevo_id_comunicado, 
                        $adjunto['nombre_original'], 
                        $nombre_archivo, 
                        $nueva_ruta, 
                        $adjunto['tipo'], 
                        $adjunto['tamano']
                    );
                    
                    if (!$stmt_adjuntos->execute()) {
                        throw new Exception("Error al guardar información de adjunto: " . $conexion->error);
                    }
                } else {
                    throw new Exception("Error al copiar archivo adjunto: " . $nombre_archivo);
                }
            }
        }
        
        // Guardar destinatarios
        if ($grupo_especifico == 1) {
            if ($tipo_destinatario === 'originales' && !empty($destinatarios_originales)) {
                // Copiar destinatarios originales
                $query_dest = "INSERT INTO comunicados_destinatarios (
                              id_comunicado, 
                              id_alumno, 
                              estado
                             ) VALUES (?, ?, 'pendiente')";
                
                $stmt_dest = $conexion->prepare($query_dest);
                
                foreach ($destinatarios_originales as $id_alumno => $datos) {
                    $stmt_dest->bind_param("ii", 
                        $nuevo_id_comunicado, 
                        $id_alumno
                    );
                    
                    if (!$stmt_dest->execute()) {
                        throw new Exception("Error al guardar destinatario: " . $conexion->error);
                    }
                }
            } else if (isset($_POST['alumnos_seleccionados']) && !empty($_POST['alumnos_seleccionados'])) {
                // Usar nuevos destinatarios seleccionados
                $query_dest = "INSERT INTO comunicados_destinatarios (
                              id_comunicado, 
                              id_alumno, 
                              estado
                             ) VALUES (?, ?, 'pendiente')";
                
                $stmt_dest = $conexion->prepare($query_dest);
                
                foreach ($_POST['alumnos_seleccionados'] as $id_alumno) {
                    $stmt_dest->bind_param("ii", 
                        $nuevo_id_comunicado, 
                        $id_alumno
                    );
                    
                    if (!$stmt_dest->execute()) {
                        throw new Exception("Error al guardar destinatario: " . $conexion->error);
                    }
                }
            } else {
                throw new Exception("No se han seleccionado destinatarios.");
            }
        }
        
        // Si el estado es 'enviado', procesar envío
        if ($estado === 'enviado') {
            // Procesar envío del comunicado (función en includes/mail_functions.php)
            $resultado_envio = enviar_comunicado($nuevo_id_comunicado);
            
            if (!$resultado_envio['success']) {
                throw new Exception("Error al enviar el comunicado: " . $resultado_envio['error']);
            }
        }
        
        // Confirmar transacción
        $conexion->commit();
        
        // Redireccionar con mensaje de éxito
        if ($estado === 'enviado') {
            redireccionar_con_mensaje('index.php', 'Comunicado reenviado correctamente', 'success');
        } else {
            redireccionar_con_mensaje('index.php', 'Comunicado programado correctamente', 'success');
        }
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        
        // Redireccionar con mensaje de error
        redireccionar_con_mensaje('enviar.php?id=' . $id_comunicado, 'Error: ' . $e->getMessage(), 'danger');
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-share"></i> Reenviar Comunicado</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="ver.php?id=<?= $id_comunicado ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Detalle
            </a>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="card-title mb-0"><i class="fas fa-info-circle"></i> Comunicado Original</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-9">
                    <h4><?= htmlspecialchars($comunicado['titulo']) ?></h4>
                    <p class="text-muted">
                        Enviado por <?= htmlspecialchars($comunicado['enviado_por_nombre']) ?> el 
                        <?= date('d/m/Y H:i', strtotime($comunicado['fecha_envio'])) ?>
                    </p>
                    
                    <?php if (!empty($adjuntos)): ?>
                    <p>
                        <strong>Archivos adjuntos:</strong>
                        <?php foreach ($adjuntos as $index => $adjunto): ?>
                            <?= $index > 0 ? ', ' : '' ?>
                            <span class="text-primary"><?= htmlspecialchars($adjunto['nombre_original']) ?></span>
                        <?php endforeach; ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#contenidoOriginal">
                        <i class="fas fa-eye"></i> Ver contenido original
                    </button>
                </div>
            </div>
            
            <div class="collapse mt-3" id="contenidoOriginal">
                <div class="card card-body">
                    <?= $comunicado['contenido'] ?>
                </div>
            </div>
        </div>
    </div>
    
    <form action="" method="post">
        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
        
        <div class="row">
            <!-- Columna izquierda: Opciones de reenvío -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-cog"></i> Opciones de Reenvío</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Destinatarios</label>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" 
                                       id="tipo-originales" value="originales" checked>
                                <label class="form-check-label" for="tipo-originales">
                                    Mismos destinatarios originales
                                    <?php if ($comunicado['grupo_especifico'] == 0 && $comunicado['id_grupo'] === null): ?>
                                    <span class="badge bg-info">Todos los grupos</span>
                                    <?php elseif ($comunicado['grupo_especifico'] == 0): ?>
                                    <span class="badge bg-info">Grupo específico</span>
                                    <?php else: ?>
                                    <span class="badge bg-info"><?= count($destinatarios_originales) ?> alumnos específicos</span>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" 
                                       id="tipo-todos" value="todos">
                                <label class="form-check-label" for="tipo-todos">
                                    Todos los alumnos
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" 
                                       id="tipo-grupo" value="grupo">
                                <label class="form-check-label" for="tipo-grupo">
                                    Un grupo específico
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input tipo-destinatario" type="radio" name="tipo_destinatario" 
                                       id="tipo-alumnos" value="alumnos">
                                <label class="form-check-label" for="tipo-alumnos">
                                    Alumnos específicos
                                </label>
                            </div>
                        </div>
                        
                        <!-- Selector de grupo -->
                        <div id="seccion-grupo" class="mb-3 d-none">
                            <label for="id_grupo" class="form-label">Seleccionar Grupo</label>
                            <select class="form-select" id="id_grupo" name="id_grupo">
                                <option value="">-- Seleccione un grupo --</option>
                                <?php foreach ($grupos as $id => $nombre): ?>
                                <option value="<?= $id ?>">
                                    <?= htmlspecialchars($nombre) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="editar_contenido" name="editar_contenido" value="1">
                                <label class="form-check-label" for="editar_contenido">
                                    <i class="fas fa-edit"></i> Editar contenido del comunicado
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="programar_envio" name="programar_envio" value="1">
                                <label class="form-check-label" for="programar_envio">
                                    <i class="fas fa-calendar-alt"></i> Programar envío
                                </label>
                            </div>
                            
                            <div id="seccion-programacion" class="mt-2 d-none">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="date" class="form-control" id="fecha_programada" name="fecha_programada" 
                                               value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="time" class="form-control" id="hora_programada" name="hora_programada" 
                                               value="<?= date('H:i') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sección alumnos específicos -->
                <div id="seccion-alumnos" class="card mb-4 d-none">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-users"></i> Seleccionar Alumnos</h5>
                    </div>
                    <div class="card-body">
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
                                    <div id="alerta-sin-alumnos" class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i> No hay alumnos seleccionados. Utilice los filtros o búsqueda para añadir alumnos.
                                    </div>
                                    
                                    <ul class="list-group list-group-flush" id="alumnos-seleccionados">
                                        <!-- Aquí se muestran los alumnos seleccionados -->
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Columna derecha: Contenido editable -->
            <div class="col-md-6">
                <div id="seccion-edicion" class="card mb-4 d-none">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-edit"></i> Editar Contenido</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="titulo" class="form-label">Título del Comunicado</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" 
                                   value="<?= htmlspecialchars($comunicado['titulo']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="contenido" class="form-label">Contenido</label>
                            <textarea class="form-control" id="contenido" name="contenido" rows="15"><?= $comunicado['contenido'] ?></textarea>
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
                
                <!-- Vista previa -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-eye"></i> Vista Previa</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h4 id="vista-previa-titulo"><?= htmlspecialchars($comunicado['titulo']) ?></h4>
                            <hr>
                        </div>
                        
                        <div id="vista-previa-contenido">
                            <?= $comunicado['contenido'] ?>
                        </div>
                        
                        <?php if (!empty($adjuntos)): ?>
                        <div class="mt-3">
                            <h5><i class="fas fa-paperclip"></i> Archivos Adjuntos</h5>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($adjuntos as $adjunto): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-file me-2"></i>
                                    <?= htmlspecialchars($adjunto['nombre_original']) ?>
                                    <small class="text-muted">(<?= formatear_tamano($adjunto['tamano']) ?>)</small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Reenviar Comunicado
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>

<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar TinyMCE (solo si se activa la edición)
    let editorInitialized = false;
    
    function initEditor() {
        if (!editorInitialized) {
            tinymce.init({
                selector: '#contenido',
                plugins: 'autolink lists link image charmap print preview anchor pagebreak',
                toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image',
                height: 400,
                setup: function (editor) {
                    editor.on('change', function () {
                        editor.save();
                        updateVistaPrevia();
                    });
                }
            });
            editorInitialized = true;
        }
    }
    
    // Control de checkbox de edición
    const checkEditar = document.getElementById('editar_contenido');
    const seccionEdicion = document.getElementById('seccion-edicion');
    
    checkEditar.addEventListener('change', function() {
        if (this.checked) {
            seccionEdicion.classList.remove('d-none');
            initEditor();
        } else {
            seccionEdicion.classList.add('d-none');
        }
    });
    
    // Control de programación de envío
    const checkProgramar = document.getElementById('programar_envio');
    const seccionProgramacion = document.getElementById('seccion-programacion');
    
    checkProgramar.addEventListener('change', function() {
        if (this.checked) {
            seccionProgramacion.classList.remove('d-none');
        } else {
            seccionProgramacion.classList.add('d-none');
        }
    });
    
    // Control de tipo de destinatarios
    const radiosTipoDestinatario = document.querySelectorAll('.tipo-destinatario');
    const seccionGrupo = document.getElementById('seccion-grupo');
    const seccionAlumnos = document.getElementById('seccion-alumnos');
    
    radiosTipoDestinatario.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'originales' || this.value === 'todos') {
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
    
    // Actualización de vista previa
    const inputTitulo = document.getElementById('titulo');
    const vistaPreviewTitulo = document.getElementById('vista-previa-titulo');
    const vistaPreviewContenido = document.getElementById('vista-previa-contenido');
    
    function updateVistaPrevia() {
        if (checkEditar.checked) {
            vistaPreviewTitulo.textContent = inputTitulo.value;
            if (editorInitialized) {
                vistaPreviewContenido.innerHTML = tinymce.get('contenido').getContent();
            }
        }
    }
    
    if (inputTitulo) {
        inputTitulo.addEventListener('input', updateVistaPrevia);
    }
    
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
});
</script>