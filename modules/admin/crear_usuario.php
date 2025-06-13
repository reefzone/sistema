<?php
/**
 * Archivo: crear_usuario.php
 * Ubicación: modules/admin/crear_usuario.php
 * Propósito: Formulario para agregar nuevos usuarios al sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_usuarios_crear')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Incluir encabezado
$titulo_pagina = "Crear Usuario";
include_once '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-plus mr-2"></i>Crear Nuevo Usuario</h1>
        <a href="usuarios.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Volver a Usuarios
        </a>
    </div>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Datos del Nuevo Usuario</h6>
        </div>
        <div class="card-body">
            <form id="formCrearUsuario" method="POST" action="procesar_usuario.php" class="needs-validation" novalidate>
                <input type="hidden" name="accion" value="crear">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nombre_completo">Nombre Completo: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" required>
                            <div class="invalid-feedback">
                                Por favor ingrese el nombre completo.
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Correo Electrónico: <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback">
                                Por favor ingrese un correo electrónico válido.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="username">Nombre de Usuario: <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback">
                                Por favor ingrese un nombre de usuario.
                            </div>
                            <small class="form-text text-muted">
                                Solo letras, números y guiones bajos. Mínimo 4 caracteres.
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tipo_usuario">Rol del Usuario: <span class="text-danger">*</span></label>
                            <select class="form-control" id="tipo_usuario" name="tipo_usuario" required>
                                <option value="">Seleccione un rol</option>
                                <option value="superadmin">Superadmin</option>
                                <option value="organizador">Organizador</option>
                                <option value="consulta">Consulta</option>
                            </select>
                            <div class="invalid-feedback">
                                Por favor seleccione un rol para el usuario.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Contraseña:</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password">
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
                                Si se deja en blanco, se generará automáticamente.
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="activo">Estado inicial:</label>
                            <select class="form-control" id="activo" name="activo">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="enviar_email" name="enviar_email" value="1" checked>
                        <label class="custom-control-label" for="enviar_email">
                            Enviar credenciales por correo electrónico
                        </label>
                    </div>
                </div>
                
                <hr>
                
                <div class="form-group text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Guardar Usuario
                    </button>
                    <a href="usuarios.php" class="btn btn-secondary ml-2">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Validación del formulario
    $('#formCrearUsuario').on('submit', function(event) {
        if (this.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Mostrar/ocultar contraseña
    $('#togglePassword').click(function() {
        var passwordField = $('#password');
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
        $('#password').attr('type', 'text').val(password);
        $('#togglePassword').html('<i class="fas fa-eye-slash"></i>');
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?>