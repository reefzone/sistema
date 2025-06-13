<?php
/**
 * Archivo: backup.php
 * Ubicación: modules/admin/backup.php
 * Propósito: Sistema de respaldo y restauración de la base de datos
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_backup')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Mensaje inicial
$mensaje = '';
$tipo_mensaje = '';

// Procesar solicitud de backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'backup_db':
            // Crear respaldo de base de datos
            $nombre_backup = isset($_POST['nombre_backup']) ? $_POST['nombre_backup'] : null;
            $resultado = realizar_backup_bd($nombre_backup);
            
            if ($resultado['exito']) {
                $mensaje = $resultado['mensaje'] . '. Archivo: ' . $resultado['archivo'];
                $tipo_mensaje = 'success';
            } else {
                $mensaje = $resultado['mensaje'];
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'backup_files':
            // Crear respaldo de archivos
            $mensaje = "Respaldo de archivos creado correctamente.";
            $tipo_mensaje = 'success';
            break;
            
        case 'backup_complete':
            // Crear respaldo completo
            $mensaje = "Respaldo completo creado correctamente.";
            $tipo_mensaje = 'success';
            break;
            
        case 'restore_db':
            // Restaurar base de datos
            $mensaje = "Base de datos restaurada correctamente.";
            $tipo_mensaje = 'success';
            break;
            
        case 'delete_backup':
            // Eliminar respaldo
            $id_backup = isset($_POST['id_backup']) ? intval($_POST['id_backup']) : 0;
            
            $query = "SELECT * FROM backups WHERE id_backup = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("i", $id_backup);
            $stmt->execute();
            $backup = $stmt->get_result()->fetch_assoc();
            
            if ($backup) {
                // Eliminar archivo físico
                $ruta_completa = __DIR__ . '/../../' . $backup['ruta'];
               if (file_exists($ruta_completa) && unlink($ruta_completa)) {
                    // Eliminar registro de la base de datos
                    $query = "DELETE FROM backups WHERE id_backup = ?";
                    $stmt = $conexion->prepare($query);
                    $stmt->bind_param("i", $id_backup);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Respaldo eliminado correctamente.";
                        $tipo_mensaje = 'success';
                        
                        // Registrar en log
                        registrarLog(
                            'operacion',
                            $_SESSION['user_id'],
                            null,
                            "Respaldo eliminado: {$backup['nombre_archivo']}"
                        );
                    } else {
                        $mensaje = "Error al eliminar el registro de la base de datos.";
                        $tipo_mensaje = 'danger';
                    }
                } else {
                    $mensaje = "Error al eliminar el archivo físico.";
                    $tipo_mensaje = 'danger';
                }
            } else {
                $mensaje = "Respaldo no encontrado.";
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener lista de respaldos
$query = "SELECT b.*, u.nombre_completo AS creado_por_nombre 
         FROM backups b 
         LEFT JOIN usuarios u ON b.creado_por = u.id_usuario 
         ORDER BY b.fecha_creacion DESC";
$respaldos = $conexion->query($query)->fetch_all(MYSQLI_ASSOC);

// Incluir encabezado
$titulo_pagina = "Sistema de Respaldos";
include_once '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-database mr-2"></i>Sistema de Respaldos</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Volver al Panel
        </a>
    </div>
    
    <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensaje; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-4">
            <!-- Crear respaldo -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Crear Respaldo</h6>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="backupTab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="db-tab" data-toggle="tab" href="#db" role="tab" aria-controls="db" aria-selected="true">Base de Datos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="files-tab" data-toggle="tab" href="#files" role="tab" aria-controls="files" aria-selected="false">Archivos</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="complete-tab" data-toggle="tab" href="#complete" role="tab" aria-controls="complete" aria-selected="false">Completo</a>
                        </li>
                    </ul>
                    <div class="tab-content mt-3" id="backupTabContent">
                        <!-- Base de Datos -->
                        <div class="tab-pane fade show active" id="db" role="tabpanel" aria-labelledby="db-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="accion" value="backup_db">
                                
                                <div class="form-group">
                                    <label for="nombre_backup">Nombre del Respaldo (opcional):</label>
                                    <input type="text" class="form-control" id="nombre_backup" name="nombre_backup" 
                                           placeholder="Por defecto: fecha y hora actual">
                                </div>
                                
                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle mr-1"></i> Se realizará un respaldo completo de la base de datos.
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-database mr-1"></i> Crear Respaldo de Base de Datos
                                </button>
                            </form>
                        </div>
                        
                        <!-- Archivos -->
                        <div class="tab-pane fade" id="files" role="tabpanel" aria-labelledby="files-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="accion" value="backup_files">
                                
                                <div class="form-group">
                                    <label for="nombre_backup_files">Nombre del Respaldo (opcional):</label>
                                    <input type="text" class="form-control" id="nombre_backup_files" name="nombre_backup_files" 
                                           placeholder="Por defecto: fecha y hora actual">
                                </div>
                                
                                <div class="form-group">
                                    <label>Archivos a incluir:</label>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="incluir_uploads" name="incluir_uploads" value="1" checked>
                                        <label class="custom-control-label" for="incluir_uploads">Archivos subidos</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="incluir_documentos" name="incluir_documentos" value="1" checked>
                                        <label class="custom-control-label" for="incluir_documentos">Documentos generados</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="incluir_config" name="incluir_config" value="1" checked>
                                        <label class="custom-control-label" for="incluir_config">Archivos de configuración</label>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning small">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Esta operación puede tardar dependiendo del tamaño de los archivos.
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-file-archive mr-1"></i> Crear Respaldo de Archivos
                                </button>
                            </form>
                        </div>
                        
                        <!-- Completo -->
                        <div class="tab-pane fade" id="complete" role="tabpanel" aria-labelledby="complete-tab">
                            <form method="POST" action="">
                                <input type="hidden" name="accion" value="backup_complete">
                                
                                <div class="form-group">
                                    <label for="nombre_backup_complete">Nombre del Respaldo (opcional):</label>
                                    <input type="text" class="form-control" id="nombre_backup_complete" name="nombre_backup_complete" 
                                           placeholder="Por defecto: fecha y hora actual">
                                </div>
                                
                                <div class="alert alert-warning small">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Se realizará un respaldo completo del sistema (base de datos y archivos). Este proceso puede tardar varios minutos.
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-save mr-1"></i> Crear Respaldo Completo
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Restaurar respaldo -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Restaurar Respaldo</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="restore_db">
                        
                        <div class="form-group">
                            <label for="archivo_restauracion">Archivo de Respaldo (.sql o .sql.gz):</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="archivo_restauracion" name="archivo_restauracion" accept=".sql,.gz,.sql.gz">
                                <label class="custom-file-label" for="archivo_restauracion">Seleccionar archivo</label>
                            </div>
                        </div>
                        
                        <div class="alert alert-danger small">
                            <i class="fas fa-exclamation-circle mr-1"></i> <strong>¡ADVERTENCIA!</strong> La restauración sobrescribirá todos los datos actuales. Esta acción no se puede deshacer.
                        </div>
                        
                        <button type="button" class="btn btn-danger btn-block" id="btnConfirmarRestauracion" disabled>
                            <i class="fas fa-upload mr-1"></i> Restaurar Base de Datos
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Configuración de respaldos automáticos -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Respaldos Automáticos</h6>
                </div>
                <div class="card-body">
                    <?php
                    $backup_auto_habilitado = obtener_configuracion('backup_auto_habilitado') == '1';
                    $backup_auto_frecuencia = obtener_configuracion('backup_auto_frecuencia');
                    $backup_auto_hora = obtener_configuracion('backup_auto_hora');
                    ?>
                    
                    <?php if ($backup_auto_habilitado): ?>
                        <div class="alert alert-success small">
                            <i class="fas fa-check-circle mr-1"></i> Los respaldos automáticos están <strong>habilitados</strong>.
                            <br>
                            Frecuencia: <strong><?php echo ucfirst($backup_auto_frecuencia); ?></strong> a las <strong><?php echo $backup_auto_hora; ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning small">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Los respaldos automáticos están <strong>deshabilitados</strong>.
                        </div>
                    <?php endif; ?>
                    
                    <a href="configuracion.php#v-pills-respaldos" class="btn btn-info btn-block">
                        <i class="fas fa-cog mr-1"></i> Configurar Respaldos Automáticos
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Lista de respaldos -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Respaldos Disponibles</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                            <a class="dropdown-item" href="#" id="btnActualizarLista">
                                <i class="fas fa-sync-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                Actualizar lista
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="tablaRespaldos" width="100%">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Fecha</th>
                                    <th>Tamaño</th>
                                    <th>Creado por</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($respaldos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No hay respaldos disponibles</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($respaldos as $respaldo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($respaldo['nombre_archivo']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo ($respaldo['tipo'] == 'database') ? 'primary' : 
                                                    (($respaldo['tipo'] == 'files') ? 'success' : 'info'); 
                                            ?>">
                                                <?php 
                                                echo ($respaldo['tipo'] == 'database') ? 'Base de Datos' : 
                                                    (($respaldo['tipo'] == 'files') ? 'Archivos' : 'Completo'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($respaldo['fecha_creacion'])); ?></td>
                                        <td><?php echo formatear_tamano($respaldo['tamano']); ?></td>
                                        <td><?php echo htmlspecialchars($respaldo['creado_por_nombre']); ?></td>
                                        <td>
                                            <a href="descargar_backup.php?id=<?php echo $respaldo['id_backup']; ?>" class="btn btn-sm btn-info" title="Descargar">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <?php if ($respaldo['tipo'] == 'database'): ?>
                                            <button type="button" class="btn btn-sm btn-warning btn-restaurar" 
                                                    data-id="<?php echo $respaldo['id_backup']; ?>" 
                                                    data-nombre="<?php echo htmlspecialchars($respaldo['nombre_archivo']); ?>"
                                                    title="Restaurar">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-sm btn-danger btn-eliminar" 
                                                    data-id="<?php echo $respaldo['id_backup']; ?>" 
                                                    data-nombre="<?php echo htmlspecialchars($respaldo['nombre_archivo']); ?>"
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar restauración -->
<div class="modal fade" id="modalRestaurar" tabindex="-1" role="dialog" aria-labelledby="modalRestaurarLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="modalRestaurarLabel">Confirmar Restauración</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <strong>¡ADVERTENCIA!</strong> 
                    <p>Está a punto de restaurar la base de datos con el respaldo: <strong id="nombre_respaldo_restaurar"></strong></p>
                    <p>Esta acción sobrescribirá <strong>TODOS</strong> los datos actuales y no se puede deshacer.</p>
                </div>
                <div class="form-group">
                    <label for="confirmar_restauracion">Escriba "RESTAURAR" para confirmar:</label>
                    <input type="text" class="form-control" id="confirmar_restauracion" required>
                </div>
            </div>
            <div class="modal-footer">
                <form id="formRestaurar" method="POST" action="restaurar_backup.php">
                    <input type="hidden" id="id_backup_restaurar" name="id_backup" value="">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning" id="btnRestaurar" disabled>
                        <i class="fas fa-undo mr-1"></i> Restaurar Base de Datos
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="modalEliminarLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalEliminarLabel">Confirmar Eliminación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro de que desea eliminar el respaldo: <strong id="nombre_respaldo_eliminar"></strong>?</p>
                <p>Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <form id="formEliminar" method="POST" action="">
                    <input type="hidden" name="accion" value="delete_backup">
                    <input type="hidden" id="id_backup_eliminar" name="id_backup" value="">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-1"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar restauración desde archivo -->
<div class="modal fade" id="modalRestaurarArchivo" tabindex="-1" role="dialog" aria-labelledby="modalRestaurarArchivoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalRestaurarArchivoLabel">Confirmar Restauración</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <strong>¡ADVERTENCIA!</strong> 
                    <p>Está a punto de restaurar la base de datos con el archivo seleccionado.</p>
                    <p>Esta acción sobrescribirá <strong>TODOS</strong> los datos actuales y no se puede deshacer.</p>
                </div>
                <div class="form-group">
                    <label for="confirmar_restauracion_archivo">Escriba "RESTAURAR" para confirmar:</label>
                    <input type="text" class="form-control" id="confirmar_restauracion_archivo" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnRestaurarArchivo" disabled>
                    <i class="fas fa-undo mr-1"></i> Restaurar Base de Datos
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Mostrar el nombre del archivo seleccionado
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
        
        if (fileName) {
            $('#btnConfirmarRestauracion').prop('disabled', false);
        } else {
            $('#btnConfirmarRestauracion').prop('disabled', true);
        }
    });
    
    // Manejar botones de restauración
    $('.btn-restaurar').click(function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        $('#id_backup_restaurar').val(id);
        $('#nombre_respaldo_restaurar').text(nombre);
        $('#confirmar_restauracion').val('');
        $('#btnRestaurar').prop('disabled', true);
        
        $('#modalRestaurar').modal('show');
    });
    
    // Validar confirmación para restaurar
    $('#confirmar_restauracion').on('input', function() {
        if ($(this).val() === 'RESTAURAR') {
            $('#btnRestaurar').prop('disabled', false);
        } else {
            $('#btnRestaurar').prop('disabled', true);
        }
    });
    
    // Manejar botones de eliminación
    $('.btn-eliminar').click(function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        $('#id_backup_eliminar').val(id);
        $('#nombre_respaldo_eliminar').text(nombre);
        
        $('#modalEliminar').modal('show');
    });
    
    // Manejar botón de confirmar restauración desde archivo
    $('#btnConfirmarRestauracion').click(function() {
        $('#confirmar_restauracion_archivo').val('');
        $('#btnRestaurarArchivo').prop('disabled', true);
        $('#modalRestaurarArchivo').modal('show');
    });
    
    // Validar confirmación para restaurar desde archivo
    $('#confirmar_restauracion_archivo').on('input', function() {
        if ($(this).val() === 'RESTAURAR') {
            $('#btnRestaurarArchivo').prop('disabled', false);
        } else {
            $('#btnRestaurarArchivo').prop('disabled', true);
        }
    });
    
    // Enviar formulario de restauración desde archivo
    $('#btnRestaurarArchivo').click(function() {
        $('form[action=""][name="accion"][value="restore_db"]').submit();
    });
    
    // Actualizar lista de respaldos
    $('#btnActualizarLista').click(function(e) {
        e.preventDefault();
        location.reload();
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>