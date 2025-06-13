<?php
// Obtener nombre de la página actual para marcar en el menú
$pagina_actual = basename($_SERVER['PHP_SELF']);
$seccion_actual = dirname($_SERVER['PHP_SELF']);
$partes_ruta = explode('/', $seccion_actual);
$modulo_actual = end($partes_ruta);

// Función para verificar si un menú está activo
function menu_activo($modulo) {
    global $modulo_actual;
    return ($modulo_actual == $modulo) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESCUELA SECUNDARIA TECNICA #82 - Sistema Escolar</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <!-- Favicon -->
    <link rel="icon" href="<?= BASE_URL ?>assets/images/favicon.ico" type="image/x-icon">
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="<?= BASE_URL ?>modules/panel_inicio/index.php">
                    <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Logo" height="40" class="d-inline-block align-text-top me-2">
                    EST #82
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?= menu_activo('panel_inicio') ?>" href="<?= BASE_URL ?>modules/panel_inicio/index.php">
                                <i class="fas fa-home"></i> Inicio
                            </a>
                        </li>

                        <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= in_array($modulo_actual, ['alumnos', 'registro_masivo']) ? 'active' : '' ?>"
                               href="#" id="alumnosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-graduate"></i> Alumnos
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="alumnosDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/alumnos/index.php">Ver Alumnos</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/alumnos/crear.php">Agregar Alumno</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/registro_masivo/index.php">Registro Masivo</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= in_array($modulo_actual, ['grupos', 'credenciales']) ? 'active' : '' ?>"
                               href="#" id="gruposDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-users"></i> Grupos
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="gruposDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/grupos/index.php">Ver Grupos</a></li>
                                <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/grupos/crear.php">Crear Grupo</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/credenciales/index.php">Credenciales</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= in_array($modulo_actual, ['asistencia', 'comunicados']) ? 'active' : '' ?>"
                               href="#" id="asistenciaDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-clipboard-check"></i> Control Escolar
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="asistenciaDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/asistencia/index.php">Pase de Lista</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/asistencia/reporte.php">Reporte de Asistencia</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/comunicados/index.php">Comunicados</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= in_array($modulo_actual, ['historial_escolar', 'seguimiento_emocional']) ? 'active' : '' ?>"
                               href="#" id="historialDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-history"></i> Seguimiento
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="historialDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/historial_escolar/index.php">Historial Escolar</a></li>
                                <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin'])): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/seguimiento_emocional/index.php">Seguimiento Emocional</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= $modulo_actual == 'admin' ? 'active' : '' ?>"
                               href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-cogs"></i> Administración
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/admin/usuarios.php">Usuarios</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/admin/configuracion.php">Configuración</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/admin/logs.php">Registros</a></li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i> <?= $_SESSION['nombre_completo'] ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>modules/admin/perfil.php">
                                    <i class="fas fa-id-card"></i> Mi Perfil
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <a class="dropdown-item" href="../login/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container-fluid main-container">
        <?php echo mostrar_mensaje(); ?>