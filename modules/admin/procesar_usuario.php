<?php
/**
 * Archivo: procesar_usuario.php
 * Ubicación: modules/admin/procesar_usuario.php
 * Propósito: Procesamiento de formularios relacionados con usuarios
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();

// Verificar que se envió una acción
if (!isset($_POST['accion'])) {
    header('Location: usuarios.php?mensaje=Acción no especificada&tipo=danger');
    exit;
}

$accion = $_POST['accion'];

switch ($accion) {
    case 'crear':
        // Verificar permisos
        if (!tienePermiso('admin_usuarios_crear')) {
            header('Location: ../../index.php?error=acceso_denegado');
            exit;
        }
        
        // Verificar datos obligatorios
        if (empty($_POST['username']) || empty($_POST['nombre_completo']) || empty($_POST['email']) || empty($_POST['tipo_usuario'])) {
            header('Location: crear_usuario.php?mensaje=Todos los campos marcados con * son obligatorios&tipo=danger');
            exit;
        }
        
        // Preparar datos
        $datos = [
            'username' => $_POST['username'],
            'nombre_completo' => $_POST['nombre_completo'],
            'email' => $_POST['email'],
            'tipo_usuario' => $_POST['tipo_usuario'],
            'password' => $_POST['password'] ?? '',
            'activo' => isset($_POST['activo']) ? intval($_POST['activo']) : 1
        ];
        
        // Crear usuario
        $enviar_email = isset($_POST['enviar_email']) && $_POST['enviar_email'] == '1';
        $resultado = crear_usuario($datos, $enviar_email);
        
        if ($resultado['exito']) {
            $mensaje = "Usuario creado correctamente. ";
            $tipo = "success";
            
            if (isset($resultado['password_generada'])) {
                $mensaje .= "Contraseña generada: <strong>{$resultado['password_generada']}</strong>";
            }
            
            if (isset($resultado['aviso'])) {
                $mensaje .= " " . $resultado['aviso'];
            }
            
            header("Location: usuarios.php?mensaje=" . urlencode($mensaje) . "&tipo=$tipo");
        } else {
            header("Location: crear_usuario.php?mensaje=" . urlencode($resultado['mensaje']) . "&tipo=danger");
        }
        break;
        
    case 'actualizar':
        // Verificar permisos
        if (!tienePermiso('admin_usuarios_editar')) {
            header('Location: ../../index.php?error=acceso_denegado');
            exit;
        }
        
        // Verificar ID de usuario
        if (!isset($_POST['usuario_id']) || empty($_POST['usuario_id'])) {
            header('Location: usuarios.php?mensaje=Usuario no especificado&tipo=danger');
            exit;
        }
        
        $id_usuario = intval($_POST['usuario_id']);
        
        // Verificar datos obligatorios
        if (empty($_POST['nombre_completo']) || empty($_POST['email'])) {
            header("Location: editar_usuario.php?id=$id_usuario&mensaje=Todos los campos marcados con * son obligatorios&tipo=danger");
            exit;
        }
        
        // Preparar datos
        $datos = [
            'nombre_completo' => $_POST['nombre_completo'],
            'email' => $_POST['email']
        ];
        
        // Si no es el propio usuario, permitir cambiar tipo y estado
        if ($id_usuario != $_SESSION['user_id']) {
            if (isset($_POST['tipo_usuario']) && !empty($_POST['tipo_usuario'])) {
                $datos['tipo_usuario'] = $_POST['tipo_usuario'];
            }
            
            if (isset($_POST['activo'])) {
                $datos['activo'] = intval($_POST['activo']);
            }
        }
        
        // Si se proporcionó una nueva contraseña
        if (isset($_POST['nueva_password']) && !empty($_POST['nueva_password'])) {
            $datos['nueva_password'] = $_POST['nueva_password'];
        }
        
        // Actualizar usuario
        $resultado = actualizar_usuario($id_usuario, $datos);
        
        if ($resultado['exito']) {
            $mensaje = $resultado['mensaje'];
            $tipo = "success";
            
            // Enviar notificación si se solicitó
            if (isset($_POST['enviar_email']) && $_POST['enviar_email'] == '1' && !empty($datos['email'])) {
                $subject = "Actualización de cuenta - ESCUELA SECUNDARIA TECNICA #82";
                
                $html_body = "<h2>Sistema Escolar - EST #82</h2>";
                $html_body .= "<p>Estimado/a {$datos['nombre_completo']},</p>";
                $html_body .= "<p>Tu información de usuario en el sistema escolar ha sido actualizada.</p>";
                
                if (isset($datos['nueva_password'])) {
                    $html_body .= "<p>Se ha establecido una nueva contraseña para tu cuenta.</p>";
                    $html_body .= "<p>Por favor, ingresa al sistema con tus nuevas credenciales.</p>";
                }
                
                $html_body .= "<p>Accede al sistema desde: <a href='" . BASE_URL . "'>" . BASE_URL . "</a></p>";
                $html_body .= "<p>Atentamente,<br>Administración - EST #82</p>";
                
                $enviado = sendEmail(
                    $datos['email'],
                    $datos['nombre_completo'],
                    $subject,
                    $html_body
                );
                
                if (!$enviado) {
                    $mensaje .= " Sin embargo, no se pudo enviar la notificación por correo electrónico.";
                }
            }
            
            header("Location: editar_usuario.php?id=$id_usuario&mensaje=" . urlencode($mensaje) . "&tipo=$tipo");
        } else {
            header("Location: editar_usuario.php?id=$id_usuario&mensaje=" . urlencode($resultado['mensaje']) . "&tipo=danger");
        }
        break;
        
    case 'cambiar_estado':
        // Verificar permisos
        if (!tienePermiso('admin_usuarios_editar')) {
            header('Location: ../../index.php?error=acceso_denegado');
            exit;
        }
        
        // Verificar datos
        if (!isset($_POST['usuario_id']) || !isset($_POST['nuevo_estado'])) {
            header('Location: usuarios.php?mensaje=Datos incompletos&tipo=danger');
            exit;
        }
        
        $id_usuario = intval($_POST['usuario_id']);
        $nuevo_estado = intval($_POST['nuevo_estado']);
        
        // No permitir cambiar el estado del propio usuario
        if ($id_usuario == $_SESSION['user_id']) {
            header('Location: usuarios.php?mensaje=No puedes cambiar tu propio estado&tipo=danger');
            exit;
        }
        
        // Actualizar estado
        $datos = [
            'activo' => $nuevo_estado
        ];
        
        $resultado = actualizar_usuario($id_usuario, $datos);
        
        if ($resultado['exito']) {
            $mensaje = "Estado del usuario actualizado correctamente.";
            $tipo = "success";
        } else {
            $mensaje = $resultado['mensaje'];
            $tipo = "danger";
        }
        
        header("Location: usuarios.php?mensaje=" . urlencode($mensaje) . "&tipo=$tipo");
        break;
        
    case 'reset_intentos':
        // Verificar permisos
        if (!tienePermiso('admin_usuarios_reset')) {
            header('Location: ../../index.php?error=acceso_denegado');
            exit;
        }
        
        // Verificar datos
        if (!isset($_POST['usuario_id'])) {
            header('Location: usuarios.php?mensaje=Usuario no especificado&tipo=danger');
            exit;
        }
        
        $id_usuario = intval($_POST['usuario_id']);
        
        // Resetear intentos fallidos
        $query = "UPDATE usuarios SET intentos_fallidos = 0 WHERE id_usuario = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("i", $id_usuario);
        
        if ($stmt->execute()) {
            // Registrar en log
            registrarLog(
                'operacion',
                $_SESSION['user_id'],
                null,
                "Reinicio de intentos fallidos para usuario ID: $id_usuario"
            );
            
            $mensaje = "Intentos fallidos reiniciados correctamente.";
            $tipo = "success";
        } else {
            $mensaje = "Error al reiniciar intentos fallidos.";
            $tipo = "danger";
        }
        
        header("Location: editar_usuario.php?id=$id_usuario&mensaje=" . urlencode($mensaje) . "&tipo=$tipo");
        break;
        
    default:
        header('Location: usuarios.php?mensaje=Acción no válida&tipo=danger');
        break;
}