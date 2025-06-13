<?php
/**
 * Página de login
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../includes/session_config.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Limpiar sesión si viene del logout
if (isset($_GET['logout']) || isset($_GET['force_logout'])) {
    session_unset();
    session_destroy();
    session_start();
}

// Si el usuario ya está logueado Y NO viene del logout, redirigir a panel de inicio
if (isset($_SESSION['user_id']) && !isset($_GET['logout']) && !isset($_GET['force_logout'])) {
    header('Location: ../panel_inicio/index.php');
    exit;
}

// Generar token CSRF
$csrf_token = generar_token_csrf();

// Mensaje de éxito de logout
$success_message = '';
if (isset($_GET['logout'])) {
    $success_message = 'Sesión cerrada exitosamente';
}

// Mensaje de error
$error = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'credenciales':
            $error = 'Usuario o contraseña incorrectos';
            break;
        case 'sesion_expirada':
            $error = 'La sesión ha expirado, por favor inicie sesión nuevamente';
            break;
        case 'acceso_denegado':
            $error = 'No tiene permisos para acceder a esa sección';
            break;
        case 'cuenta_inactiva':
            $error = 'Su cuenta está inactiva, contacte al administrador';
            break;
        case 'cuenta_bloqueada':
            $error = 'Su cuenta ha sido bloqueada por múltiples intentos fallidos';
            break;
        default:
            $error = 'Error al iniciar sesión';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - ESCUELA SECUNDARIA TECNICA #82</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            padding: 2rem;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .login-logo img {
            max-width: 120px;
            height: auto;
        }
        .login-title {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="../../assets/images/logo.png" alt="Logo Escuela">
        </div>
        <h2 class="login-title">ESCUELA SECUNDARIA TECNICA #82</h2>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="procesar.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3">
                <label for="username" class="form-label">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Ingrese su usuario" required autofocus>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Ingrese su contraseña" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </button>
            </div>
        </form>
        
        <div class="mt-4 text-center text-muted">
            <small>© <?php echo date('Y'); ?> ESCUELA SECUNDARIA TECNICA #82</small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar contraseña
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>