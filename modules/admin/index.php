<?php
/**
 * Archivo: index.php
 * Ubicación: modules/admin/index.php
 * Propósito: Panel de administración principal del sistema escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_ver')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Obtener estadísticas del sistema
$estadisticas = obtener_estadisticas_sistema();

// Obtener actividad reciente
$query = "SELECT * FROM logs_sistema ORDER BY fecha DESC LIMIT 10";
$actividad_reciente = $conexion->query($query)->fetch_all(MYSQLI_ASSOC);

// Incluir encabezado
$titulo_pagina = "Panel de Administración";
include_once '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h1 class="mb-4"><i class="fas fa-cogs mr-2"></i>Panel de Administración</h1>
    
    <div class="row">
        <!-- Estadísticas rápidas -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Usuarios</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['usuarios']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Alumnos Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['alumnos']['activos']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Grupos Activos</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['grupos']['activos']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Ingresos Hoy</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $estadisticas['actividad']['logins_hoy']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-sign-in-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Salud del sistema -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Salud del Sistema</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                            <a class="dropdown-item" href="mantenimiento.php">Mantenimiento</a>
                            <a class="dropdown-item" href="backup.php">Crear Respaldo</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <h4 class="small font-weight-bold">Espacio en Disco <span class="float-right">
                        <?php 
                        $porcentaje_usado = round(($estadisticas['sistema']['espacio_disco']['usado'] / $estadisticas['sistema']['espacio_disco']['total']) * 100); 
                        echo $porcentaje_usado . '%';
                        ?>
                    </span></h4>
                    <div class="progress mb-4">
                        <div class="progress-bar <?php echo ($porcentaje_usado > 80) ? 'bg-danger' : 'bg-info'; ?>" role="progressbar" style="width: <?php echo $porcentaje_usado; ?>%" aria-valuenow="<?php echo $porcentaje_usado; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    
                    <p><strong>PHP:</strong> <?php echo $estadisticas['sistema']['version_php']; ?></p>
                    <p><strong>MySQL:</strong> <?php echo $estadisticas['sistema']['version_mysql']; ?></p>
                    <p><strong>Tiempo Activo:</strong> <?php echo $estadisticas['sistema']['tiempo_actividad'] ?: 'No disponible'; ?></p>
                    <p><strong>Memoria Límite:</strong> <?php echo $estadisticas['sistema']['memoria']['limit']; ?></p>
                    
                    <a href="mantenimiento.php" class="btn btn-sm btn-primary mt-2">
                        <i class="fas fa-tools mr-1"></i> Mantenimiento
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Actividad reciente -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Actividad Reciente</h6>
                    <a href="logs.php" class="btn btn-sm btn-link">Ver todos</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividad_reciente as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($log['descripcion']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($log['fecha'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Accesos rápidos -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Accesos Rápidos</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="usuarios.php" class="card bg-primary text-white h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x mb-3"></i>
                                    <h5 class="card-title">Gestión de Usuarios</h5>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="configuracion.php" class="card bg-success text-white h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-cog fa-3x mb-3"></i>
                                    <h5 class="card-title">Configuración Global</h5>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="backup.php" class="card bg-info text-white h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-database fa-3x mb-3"></i>
                                    <h5 class="card-title">Respaldos</h5>
                                </div>
                            </a>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <a href="logs.php" class="card bg-warning text-white h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                    <h5 class="card-title">Registros del Sistema</h5>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>