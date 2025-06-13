<?php
/**
 * Archivo: editar_usuario.php
 * Ubicación: modules/admin/editar_usuario.php
 * Propósito: Formulario para modificar usuarios existentes
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_usuarios_editar')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Verificar ID de usuario
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: usuarios.php?mensaje=Usuario no especificado&tipo=danger');
    exit;
}

$id_usuario = intval($_GET['id']);

// Obtener datos del usuario
$query = "SELECT * FROM usuarios WHERE id_usuario = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header('Location: usuarios.php?mensaje=Usuario no encontrado&tipo=danger');
    exit;
}

$usuario = $resultado->fetch_assoc();

// Obtener registros de actividad reciente
$query = "SELECT * FROM logs_sistema 
         WHERE usuario = ? 
         ORDER BY fecha DESC 
         LIMIT 10";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $usuario['username']);
$stmt->execute();
$actividad_reciente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir encabezado
$titulo_pagina = "Editar Usuario";
include_once '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-edit mr-2"></i>Editar Usuario</h1>
        <a href="usuarios.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Volver a Usuarios
        </a>
    </div>
    
    <?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-<?php echo ($_GET['tipo'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($_GET['mensaje']); ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Formulario de edición -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Datos del Usuario</h6>
                </div>
                <div class="card-body">
                    <form id="formEditarUsuario" method="POST" action="procesar_usuario.php" class="needs-validation" novalidate>
                        <input type="hidden" name="accion" value="actualizar">
                        <input type="hidden" name="usuario_id" value="<?php echo $id_usuario; ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nombre_completo">Nombre Completo: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                           value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el nombre completo.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Correo Electrónico: <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                    <div class="invalid-feedback">
                                        Por favor ingrese un correo electrónico válido.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">Nombre de Usuario:</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['username']); ?>" readonly>
                                    <small class="form-text text-muted">
                                        El nombre de usuario no se puede modificar.
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tipo_usuario">Rol del Usuario: <span class="text-danger">*</span></label>
                                    <select class="form-control" id="tipo_usuario" name="tipo_usuario" required
                                            <?php echo ($usuario['id_usuario'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                        <option value="superadmin" <?php echo ($usuario['tipo_usuario'] == 'superadmin') ? 'selected' : ''; ?>>Superadmin</option>
                                        <option value="organizador" <?php echo ($usuario['tipo_usuario'] == 'organizador') ? 'selected' : ''; ?>>Organizador</option>
                                        <option value="consulta" <?php echo ($usuario['tipo_usuario'] == 'consulta') ? 'selected' : ''; ?>>Consulta</option>
                                    </select>
                                    <?php if ($usuario['id_usuario'] == $_SESSION['user_id']): ?>
                                        <small class="form-text text-warning">
                                            No puedes cambiar tu propio rol.
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nueva_password">Nueva Contraseña:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="nueva_password" name="nueva_password">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" type="button" id="generatePassword">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Dejar en blanco para mantener la contraseña actual.
                                    </small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="activo">Estado:</label>
                                    <select class="form-control" id="activo" name="activo"
                                            <?php echo ($usuario['id_usuario'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                        <option value="1" <?php echo ($usuario['activo'] == 1) ? 'selected' : ''; ?>>Activo</option>
                                        <option value="0" <?php echo ($usuario['activo'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                    <?php if ($usuario['id_usuario'] == $_SESSION['user_id']): ?>
                                        <small class="form-text text-warning">
                                            No puedes desactivar tu propia cuenta.
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="enviar_email" name="enviar_email" value="1">
                                <label class="custom-control-label" for="enviar_email">
                                    Enviar notificación de cambios por correo electrónico
                                </label>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Guardar Cambios
                            </button>
                            <a href="usuarios.php" class="btn btn-secondary ml-2">
                                <i class="fas fa-times mr-1"></i> Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Información del usuario -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información Adicional</h6>
                </div>
                <div class="card-body">
                    <p><strong>Fecha de creación:</strong> <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></p>
                    <p><strong>Último acceso:</strong> 
                        <?php echo ($usuario['ultimo_acceso']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                    </p>
                    <p><strong>Intentos fallidos:</strong> <?php echo $usuario['intentos_fallidos']; ?></p>
                    
                    <?php if ($usuario['intentos_fallidos'] > 0 && tienePermiso('admin_usuarios_reset')): ?>
                    <button type="button" class="btn btn-sm btn-warning" id="resetIntentos" data-id="<?php echo $id_usuario; ?>">
                        <i class="fas fa-redo mr-1"></i> Reiniciar intentos fallidos
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actividad reciente -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Actividad Reciente</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($actividad_reciente)): ?>
                        <p class="text-center">No hay actividad registrada.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Acción</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($actividad_reciente as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['descripcion']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($log['fecha'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <a href="logs.php?usuario=<?php echo urlencode($usuario['username']); ?>" class="btn btn-sm btn-info mt-2">
                            <i class="fas fa-search mr-1"></i> Ver todos los registros
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar reinicio de intentos fallidos -->
<div class="modal fade" id="modalResetIntentos" tabindex="-1" role="dialog" aria-labelledby="modalResetIntentosLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalResetIntentosLabel">Confirmar Acción</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro que deseas reiniciar los intentos fallidos de este usuario?</p>
            </div>
            <div class="modal-footer">
                <form id="formResetIntentos" method="POST" action="procesar_usuario.php">
                    <input type="hidden" id="reset_usuario_id" name="usuario_id" value="">
                    <input type="hidden" name="accion" value="reset_intentos">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Validación del formulario
    $('#formEditarUsuario').on('submit', function(event) {
        if (this.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Mostrar/ocultar contraseña
    $('#togglePassword').click(function() {
        var passwordField = $('#nueva_password');
        var passwordFieldType = passwordField.attr('type');
        
        if (passwordFieldType === 'password') {
            passwordField.attr('type', 'text');
            $(this).html('<i class="fas fa-eye-slash"></i>');
        } else {
            passwordField.attr('type', 'password');
            $(this).html('<i class="fas fa-eye"></i>');
        }
    });
    
    // Generar contraseña aleatoria
    $('#generatePassword').click(function() {
        // Función para generar contraseña segura
        function generatePassword(length = 10) {
            const uppercaseChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const lowercaseChars = 'abcdefghijkmnopqrstuvwxyz';
            const numberChars = '23456789';
            const specialChars = '!@#$%&';
            
            const allChars = uppercaseChars + lowercaseChars + numberChars + specialChars;
            
            // Asegurar al menos un carácter de cada tipo
            let password = '';
            password += uppercaseChars.charAt(Math.floor(Math.random() * uppercaseChars.length));
            password += lowercaseChars.charAt(Math.floor(Math.random() * lowercaseChars.length));
            password += numberChars.charAt(Math.floor(Math.random() * numberChars.length));
            password += specialChars.charAt(Math.floor(Math.random() * specialChars.length));
            
            // Completar el resto de la contraseña
            for (let i = 4; i < length; i++) {
                password += allChars.charAt(Math.floor(Math.random() * allChars.length));
            }
            
            // Mezclar todos los caracteres
            return password.split('').sort(() => 0.5 - Math.random()).join('');
        }
        
        var password = generatePassword();
        $('#nueva_password').attr('type', 'text').val(password);
        $('#togglePassword').html('<i class="fas fa-eye-slash"></i>');
    });
    
    // Manejar reinicio de intentos fallidos
    $('#resetIntentos').click(function() {
        var id = $(this).data('id');
        $('#reset_usuario_id').val(id);
        $('#modalResetIntentos').modal('show');
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>