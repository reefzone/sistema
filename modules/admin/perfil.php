<?php
/**
 * Archivo: perfil.php
 * Ubicación: modules/admin/perfil.php
 * Propósito: Gestión de perfil del usuario actual
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa
verificarSesion();

// Mensaje inicial
$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'actualizar_datos':
            // Validar datos
            if (empty($_POST['nombre_completo']) || empty($_POST['email'])) {
                $mensaje = "Los campos marcados con * son obligatorios.";
                $tipo_mensaje = 'danger';
            } else {
                // Preparar datos
                $datos = [
                    'nombre_completo' => $_POST['nombre_completo'],
                    'email' => $_POST['email']
                ];
                
                // Actualizar usuario
                $resultado = actualizar_usuario($_SESSION['user_id'], $datos);
                
                if ($resultado['exito']) {
                    // Actualizar datos en sesión
                    $_SESSION['nombre_completo'] = $_POST['nombre_completo'];
                    $_SESSION['email'] = $_POST['email'];
                    
                    $mensaje = "Datos actualizados correctamente.";
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = $resultado['mensaje'];
                    $tipo_mensaje = 'danger';
                }
            }
            break;
            
        case 'cambiar_password':
            // Validar contraseña actual
            $password_actual = $_POST['password_actual'];
            $nueva_password = $_POST['nueva_password'];
            $confirmar_password = $_POST['confirmar_password'];
            
            if (empty($password_actual) || empty($nueva_password) || empty($confirmar_password)) {
                $mensaje = "Todos los campos son obligatorios para cambiar la contraseña.";
                $tipo_mensaje = 'danger';
                break;
            }
            
            if ($nueva_password !== $confirmar_password) {
                $mensaje = "Las contraseñas nuevas no coinciden.";
                $tipo_mensaje = 'danger';
                break;
            }
            
            // Verificar política de contraseñas
            $longitud_minima = intval(obtener_configuracion('longitud_minima_password') ?: 8);
            if (strlen($nueva_password) < $longitud_minima) {
                $mensaje = "La contraseña debe tener al menos $longitud_minima caracteres.";
                $tipo_mensaje = 'danger';
                break;
            }
            
            if (obtener_configuracion('politica_password_mayusculas') == '1') {
                if (!preg_match('/[A-Z]/', $nueva_password)) {
                    $mensaje = "La contraseña debe contener al menos una letra mayúscula.";
                    $tipo_mensaje = 'danger';
                    break;
                }
            }
            
            if (obtener_configuracion('politica_password_numeros') == '1') {
                if (!preg_match('/[0-9]/', $nueva_password)) {
                    $mensaje = "La contraseña debe contener al menos un número.";
                    $tipo_mensaje = 'danger';
                    break;
                }
            }
            
            if (obtener_configuracion('politica_password_especial') == '1') {
                if (!preg_match('/[^A-Za-z0-9]/', $nueva_password)) {
                    $mensaje = "La contraseña debe contener al menos un carácter especial.";
                    $tipo_mensaje = 'danger';
                    break;
                }
            }
            
            // Verificar contraseña actual
            $query = "SELECT password FROM usuarios WHERE id_usuario = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $resultado = $stmt->get_result()->fetch_assoc();
            
            if (!password_verify($password_actual, $resultado['password'])) {
                $mensaje = "La contraseña actual es incorrecta.";
                $tipo_mensaje = 'danger';
                break;
            }
            
            // Actualizar contraseña
            $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            
            $query = "UPDATE usuarios SET password = ?, ultimo_cambio_password = NOW() WHERE id_usuario = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("si", $password_hash, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $mensaje = "Contraseña actualizada correctamente.";
                $tipo_mensaje = 'success';
                
                // Registrar en log
                registrarLog(
                    'operacion',
                    $_SESSION['user_id'],
                    null,
                    "Cambio de contraseña realizado"
                );
            } else {
                $mensaje = "Error al actualizar la contraseña.";
                $tipo_mensaje = 'danger';
            }
            break;
    }
}

// Obtener datos del usuario
$query = "SELECT * FROM usuarios WHERE id_usuario = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Obtener actividad reciente del usuario
$query = "SELECT * FROM logs_sistema 
         WHERE usuario = ? 
         ORDER BY fecha DESC 
         LIMIT 10";
$stmt = $conexion->prepare($query);
$stmt->bind_param("s", $usuario['username']);
$stmt->execute();
$actividad_reciente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir encabezado
$titulo_pagina = "Mi Perfil";
include_once '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-cog mr-2"></i>Mi Perfil</h1>
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
        <div class="col-lg-8">
            <!-- Datos personales -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Datos Personales</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="accion" value="actualizar_datos">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">Nombre de Usuario:</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($usuario['username']); ?>" readonly>
                                    <small class="form-text text-muted">El nombre de usuario no se puede modificar.</small>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="tipo_usuario">Rol en el Sistema:</label>
                                    <input type="text" class="form-control" id="tipo_usuario" value="<?php echo ucfirst(htmlspecialchars($usuario['tipo_usuario'])); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nombre_completo">Nombre Completo: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                           value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Correo Electrónico: <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Cambiar contraseña -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cambiar Contraseña</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="formCambiarPassword">
                        <input type="hidden" name="accion" value="cambiar_password">
                        
                        <div class="form-group">
                            <label for="password_actual">Contraseña Actual: <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="nueva_password">Nueva Contraseña: <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="nueva_password" name="nueva_password" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirmar_password">Confirmar Nueva Contraseña: <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        // Mostrar política de contraseñas
                        $requisitos = [];
                        $requisitos[] = "Al menos " . obtener_configuracion('longitud_minima_password') . " caracteres";
                        
                        if (obtener_configuracion('politica_password_mayusculas') == '1') {
                            $requisitos[] = "Al menos una letra mayúscula";
                        }
                        
                        if (obtener_configuracion('politica_password_numeros') == '1') {
                            $requisitos[] = "Al menos un número";
                        }
                        
                        if (obtener_configuracion('politica_password_especial') == '1') {
                            $requisitos[] = "Al menos un carácter especial (!@#$%&)";
                        }
                        ?>
                        
                        <div class="alert alert-info">
                            <strong>Requisitos de la contraseña:</strong>
                            <ul class="mb-0">
                                <?php foreach ($requisitos as $requisito): ?>
                                <li><?php echo $requisito; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div id="mensaje_validacion" class="alert" style="display: none;"></div>
                        
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary" id="btnCambiarPassword">
                                <i class="fas fa-key mr-1"></i> Cambiar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Información de la cuenta -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información de la Cuenta</h6>
                </div>
                <div class="card-body">
                    <p><strong>Fecha de creación:</strong> <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></p>
                    <p><strong>Último acceso:</strong> 
                        <?php echo ($usuario['ultimo_acceso']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                    </p>
                    <p><strong>Último cambio de contraseña:</strong> 
                        <?php echo ($usuario['ultimo_cambio_password']) ? date('d/m/Y H:i', strtotime($usuario['ultimo_cambio_password'])) : 'Nunca'; ?>
                    </p>
                    <p><strong>Estado:</strong> 
                        <?php if ($usuario['activo']): ?>
                            <span class="badge badge-success">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactivo</span>
                        <?php endif; ?>
                    </p>
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
                            <i class="fas fa-search mr-1"></i> Ver todo mi historial
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Validar formulario de cambio de contraseña
    $('#formCambiarPassword').on('submit', function(e) {
        var password = $('#nueva_password').val();
        var passwordConfirm = $('#confirmar_password').val();
        var mensaje = $('#mensaje_validacion');
        
        // Validar que las contraseñas coincidan
        if (password !== passwordConfirm) {
            e.preventDefault();
            mensaje.removeClass('alert-success').addClass('alert-danger').html('Las contraseñas no coinciden.').show();
            return false;
        }
        
        <?php 
        // Generar validaciones JavaScript según política
        $js_validaciones = [];
        
        // Longitud mínima
        $longitud_minima = intval(obtener_configuracion('longitud_minima_password') ?: 8);
        $js_validaciones[] = "if (password.length < $longitud_minima) {
            e.preventDefault();
            mensaje.removeClass('alert-success').addClass('alert-danger').html('La contraseña debe tener al menos $longitud_minima caracteres.').show();
            return false;
        }";
        
        // Mayúsculas
        if (obtener_configuracion('politica_password_mayusculas') == '1') {
            $js_validaciones[] = "if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                mensaje.removeClass('alert-success').addClass('alert-danger').html('La contraseña debe contener al menos una letra mayúscula.').show();
                return false;
            }";
        }
        
        // Números
        if (obtener_configuracion('politica_password_numeros') == '1') {
            $js_validaciones[] = "if (!/[0-9]/.test(password)) {
                e.preventDefault();
                mensaje.removeClass('alert-success').addClass('alert-danger').html('La contraseña debe contener al menos un número.').show();
                return false;
            }";
        }
        
        // Caracteres especiales
        if (obtener_configuracion('politica_password_especial') == '1') {
            $js_validaciones[] = "if (!/[^A-Za-z0-9]/.test(password)) {
                e.preventDefault();
                mensaje.removeClass('alert-success').addClass('alert-danger').html('La contraseña debe contener al menos un carácter especial.').show();
                return false;
            }";
        }
        
        // Imprimir validaciones
        echo implode("\n        ", $js_validaciones);
        ?>
        
        // Si todo está bien
        mensaje.removeClass('alert-danger').addClass('alert-success').html('Contraseña válida.').show();
    });
    
    // Validar en tiempo real
    $('#nueva_password, #confirmar_password').on('input', function() {
        var password = $('#nueva_password').val();
        var passwordConfirm = $('#confirmar_password').val();
        var mensaje = $('#mensaje_validacion');
        
        if (password.length === 0 && passwordConfirm.length === 0) {
            mensaje.hide();
            return;
        }
        
        var errores = [];
        
        <?php
        // Generar validaciones en tiempo real
        $js_validaciones_tr = [];
        
        // Longitud mínima
        $js_validaciones_tr[] = "if (password.length < $longitud_minima) {
            errores.push('Al menos $longitud_minima caracteres');
        }";
        
        // Mayúsculas
        if (obtener_configuracion('politica_password_mayusculas') == '1') {
            $js_validaciones_tr[] = "if (!/[A-Z]/.test(password)) {
                errores.push('Al menos una letra mayúscula');
            }";
        }
        
        // Números
        if (obtener_configuracion('politica_password_numeros') == '1') {
            $js_validaciones_tr[] = "if (!/[0-9]/.test(password)) {
                errores.push('Al menos un número');
            }";
        }
        
        // Caracteres especiales
        if (obtener_configuracion('politica_password_especial') == '1') {
            $js_validaciones_tr[] = "if (!/[^A-Za-z0-9]/.test(password)) {
                errores.push('Al menos un carácter especial');
            }";
        }
        
        // Imprimir validaciones
        echo implode("\n        ", $js_validaciones_tr);
        ?>
        
        // Coincidencia
        if (password !== passwordConfirm && passwordConfirm.length > 0) {
            errores.push('Las contraseñas deben coincidir');
        }
        
        if (errores.length > 0) {
            var html = '<strong>La contraseña debe cumplir con:</strong><ul>';
            for (var i = 0; i < errores.length; i++) {
                html += '<li>' + errores[i] + '</li>';
            }
            html += '</ul>';
            mensaje.removeClass('alert-success').addClass('alert-danger').html(html).show();
        } else if (password.length > 0) {
            mensaje.removeClass('alert-danger').addClass('alert-success').html('Contraseña válida.').show();
        } else {
            mensaje.hide();
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>