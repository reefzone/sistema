<?php
/**
 * Gestión de Plantillas de Comunicados
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin y organizador pueden gestionar plantillas)
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Procesar formulario de creación/edición de plantilla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Verificar token CSRF
    if (!verificar_token_csrf($_POST['csrf_token'])) {
        redireccionar_con_mensaje('plantillas.php', 'Token de seguridad inválido', 'danger');
    }
    
    if ($_POST['accion'] === 'guardar') {
        // Obtener datos del formulario
        $id_plantilla = isset($_POST['id_plantilla']) ? intval($_POST['id_plantilla']) : 0;
        $nombre = sanitizar_texto($_POST['nombre']);
        $descripcion = sanitizar_texto($_POST['descripcion']);
        $contenido = $_POST['contenido']; // No sanitizamos para permitir HTML
        $es_default = isset($_POST['es_default']) ? 1 : 0;
        
        // Validar datos
        if (empty($nombre) || empty($contenido)) {
            redireccionar_con_mensaje('plantillas.php', 'Nombre y contenido son obligatorios', 'danger');
        }
        
        // Iniciar transacción
        $conexion->begin_transaction();
        
        try {
            // Si es plantilla por defecto, desmarcar las demás
            if ($es_default) {
                $query_reset = "UPDATE comunicados_plantillas SET es_default = 0";
                $conexion->query($query_reset);
            }
            
            if ($id_plantilla > 0) {
                // Actualizar plantilla existente
                $query = "UPDATE comunicados_plantillas SET 
                          nombre = ?, 
                          descripcion = ?, 
                          contenido = ?, 
                          es_default = ?
                          WHERE id_plantilla = ?";
                $stmt = $conexion->prepare($query);
                $stmt->bind_param("sssii", $nombre, $descripcion, $contenido, $es_default, $id_plantilla);
            } else {
                // Crear nueva plantilla
                $query = "INSERT INTO comunicados_plantillas (
                          nombre, 
                          descripcion, 
                          contenido, 
                          creado_por, 
                          fecha_creacion,
                          es_default
                         ) VALUES (?, ?, ?, ?, NOW(), ?)";
                $stmt = $conexion->prepare($query);
                $stmt->bind_param("sssis", $nombre, $descripcion, $contenido, $_SESSION['id_usuario'], $es_default);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar la plantilla: " . $conexion->error);
            }
            
            // Confirmar transacción
            $conexion->commit();
            
            // Redireccionar con mensaje de éxito
            redireccionar_con_mensaje('plantillas.php', 'Plantilla guardada correctamente', 'success');
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conexion->rollback();
            
            // Redireccionar con mensaje de error
            redireccionar_con_mensaje('plantillas.php', 'Error: ' . $e->getMessage(), 'danger');
        }
    } elseif ($_POST['accion'] === 'eliminar') {
        // Eliminar plantilla
        $id_plantilla = isset($_POST['id_plantilla']) ? intval($_POST['id_plantilla']) : 0;
        
        if ($id_plantilla <= 0) {
            redireccionar_con_mensaje('plantillas.php', 'Plantilla no válida', 'danger');
        }
        
        // Verificar que la plantilla no esté en uso
        $query_check = "SELECT COUNT(*) as total FROM comunicados WHERE id_plantilla = ?";
        $stmt_check = $conexion->prepare($query_check);
        $stmt_check->bind_param("i", $id_plantilla);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $row_check = $result_check->fetch_assoc();
        
        if ($row_check['total'] > 0) {
            redireccionar_con_mensaje('plantillas.php', 'No se puede eliminar esta plantilla porque está siendo utilizada por ' . $row_check['total'] . ' comunicado(s)', 'warning');
        }
        
        // Eliminar plantilla
        $query = "DELETE FROM comunicados_plantillas WHERE id_plantilla = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("i", $id_plantilla);
        
        if ($stmt->execute()) {
            redireccionar_con_mensaje('plantillas.php', 'Plantilla eliminada correctamente', 'success');
        } else {
            redireccionar_con_mensaje('plantillas.php', 'Error al eliminar la plantilla', 'danger');
        }
    }
}

// Obtener detalles de plantilla específica
$plantilla = null;
$es_edicion = false;

if (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $id_plantilla = intval($_GET['id']);
    $es_edicion = true;
    
    $query = "SELECT * FROM comunicados_plantillas WHERE id_plantilla = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_plantilla);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $plantilla = $result->fetch_assoc();
    } else {
        redireccionar_con_mensaje('plantillas.php', 'La plantilla no existe', 'danger');
    }
}

// Obtener todas las plantillas
$plantillas = [];
$query_plantillas = "SELECT p.*, 
                    CONCAT(u.nombre, ' ', u.apellido_paterno) as creado_por_nombre,
                    (SELECT COUNT(*) FROM comunicados WHERE id_plantilla = p.id_plantilla) as total_uso
                    FROM comunicados_plantillas p
                    LEFT JOIN usuarios u ON p.creado_por = u.id_usuario
                    ORDER BY p.es_default DESC, p.nombre";
$result_plantillas = $conexion->query($query_plantillas);

while ($row = $result_plantillas->fetch_assoc()) {
    $plantillas[] = $row;
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-file-alt"></i> Plantillas de Comunicados</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Comunicados
            </a>
            <?php if ($es_edicion): ?>
            <a href="plantillas.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nueva Plantilla
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Columna izquierda: Formulario -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-<?= $es_edicion ? 'edit' : 'plus-circle' ?>"></i> 
                        <?= $es_edicion ? 'Editar' : 'Nueva' ?> Plantilla
                    </h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        <input type="hidden" name="accion" value="guardar">
                        <?php if ($es_edicion): ?>
                        <input type="hidden" name="id_plantilla" value="<?= $plantilla['id_plantilla'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Plantilla</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?= $es_edicion ? htmlspecialchars($plantilla['nombre']) : '' ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción (opcional)</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"><?= $es_edicion ? htmlspecialchars($plantilla['descripcion']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contenido" class="form-label">Contenido de la Plantilla</label>
                            <textarea class="form-control" id="contenido" name="contenido" rows="15"><?= $es_edicion ? $plantilla['contenido'] : '' ?></textarea>
                            <div class="form-text">
                                Puede utilizar las siguientes variables en su plantilla:
                                <span class="badge bg-secondary">{{NOMBRE_ALUMNO}}</span>
                                <span class="badge bg-secondary">{{NOMBRE_CONTACTO}}</span>
                                <span class="badge bg-secondary">{{GRADO}}</span>
                                <span class="badge bg-secondary">{{GRUPO}}</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="es_default" name="es_default" 
                                       <?= ($es_edicion && $plantilla['es_default']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="es_default">
                                    Establecer como plantilla predeterminada
                                </label>
                            </div>
                            <small class="text-muted">Si marca esta opción, esta plantilla se seleccionará por defecto al crear nuevos comunicados.</small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?= $es_edicion ? 'Actualizar' : 'Guardar' ?> Plantilla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Columna derecha: Listado de plantillas -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list"></i> Plantillas Disponibles
                            </h5>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-primary"><?= count($plantillas) ?> plantillas</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($plantillas)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th style="width: 40%;">Nombre</th>
                                    <th>Creado por</th>
                                    <th>Uso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plantillas as $item): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($item['nombre']) ?>
                                        <?php if ($item['es_default']): ?>
                                        <span class="badge bg-success">Predeterminada</span>
                                        <?php endif; ?>
                                        <?php if ($item['descripcion']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($item['descripcion']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($item['creado_por_nombre']) ?>
                                        <br><small class="text-muted"><?= date('d/m/Y', strtotime($item['fecha_creacion'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= $item['total_uso'] ?> usos</span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="plantillas.php?id=<?= $item['id_plantilla'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-info btn-vista-previa" 
                                                    data-id="<?= $item['id_plantilla'] ?>" 
                                                    data-nombre="<?= htmlspecialchars($item['nombre']) ?>"
                                                    title="Vista previa">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($item['total_uso'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-danger btn-eliminar" 
                                                    data-id="<?= $item['id_plantilla'] ?>" 
                                                    data-nombre="<?= htmlspecialchars($item['nombre']) ?>"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> No hay plantillas de comunicados disponibles.
                    </div>
                    <?php endif; ?>
                </div>
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
                    Vista Previa de Plantilla
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 id="vista-previa-nombre"></h4>
                <hr>
                <div id="vista-previa-contenido"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
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
                <p>¿Está seguro que desea eliminar la plantilla <strong id="nombre-plantilla"></strong>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form action="" method="post">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id_plantilla" id="id-plantilla-eliminar">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
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
    
    // Configurar modal de vista previa
    const btnsVistaPrevia = document.querySelectorAll('.btn-vista-previa');
    const modalVistaPrevia = document.getElementById('vistaPreviaModal');
    const vistaPreviewNombre = document.getElementById('vista-previa-nombre');
    const vistaPreviewContenido = document.getElementById('vista-previa-contenido');
    
    btnsVistaPrevia.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-nombre');
            
            vistaPreviewNombre.textContent = nombre;
         vistaPreviewContenido.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Cargando contenido...</p></div>';
            
            fetch('../../includes/ajax/obtener_plantilla.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        vistaPreviewContenido.innerHTML = data.contenido;
                    } else {
                        vistaPreviewContenido.innerHTML = '<div class="alert alert-danger">Error al cargar la plantilla: ' + data.error + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    vistaPreviewContenido.innerHTML = '<div class="alert alert-danger">Error de conexión al cargar la plantilla</div>';
                });
            
            const modal = new bootstrap.Modal(modalVistaPrevia);
            modal.show();
        });
    });
    
    // Configurar modal de eliminación
    const btnsEliminar = document.querySelectorAll('.btn-eliminar');
    const modalEliminar = document.getElementById('eliminarModal');
    const idPlantillaEliminar = document.getElementById('id-plantilla-eliminar');
    const nombrePlantilla = document.getElementById('nombre-plantilla');
    
    btnsEliminar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-nombre');
            
            idPlantillaEliminar.value = id;
            nombrePlantilla.textContent = nombre;
            
            const modal = new bootstrap.Modal(modalEliminar);
            modal.show();
        });
    });
});
</script>