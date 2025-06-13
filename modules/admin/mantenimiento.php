<?php
/**
 * Archivo: mantenimiento.php
 * Ubicación: modules/admin/mantenimiento.php
 * Propósito: Herramientas de mantenimiento y optimización del sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_mantenimiento')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Inicializar variables
$mensaje = '';
$tipo_mensaje = '';
$resultado_operacion = null;

// Procesar acciones de mantenimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'limpiar_temp':
            // Limpiar archivos temporales
            $resultado_operacion = limpiar_archivos_temporales();
            
            if ($resultado_operacion['exito']) {
                $mensaje = "Se han eliminado {$resultado_operacion['archivos_eliminados']} archivos temporales. Espacio liberado: {$resultado_operacion['espacio_liberado']}.";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Error al limpiar archivos temporales.";
                $tipo_mensaje = 'danger';
            }
            break;
            
        case 'optimizar_bd':
            // Optimizar tablas de la base de datos
            $tablas_optimizadas = 0;
            $tablas_con_error = 0;
            
            // Obtener todas las tablas
            $resultado = $conexion->query("SHOW TABLES");
            $tablas = [];
            while ($fila = $resultado->fetch_row()) {
                $tablas[] = $fila[0];
            }
            
            // Optimizar cada tabla
            foreach ($tablas as $tabla) {
                $optimizar = $conexion->query("OPTIMIZE TABLE $tabla");
                if ($optimizar) {
                    $tablas_optimizadas++;
                } else {
                    $tablas_con_error++;
                }
            }
            
            if ($tablas_con_error == 0) {
                $mensaje = "Se han optimizado $tablas_optimizadas tablas correctamente.";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Se han optimizado $tablas_optimizadas tablas. Hubo errores en $tablas_con_error tablas.";
                $tipo_mensaje = 'warning';
            }
            
            // Registrar en log
            registrarLog(
                'operacion',
                $_SESSION['user_id'],
                null,
                "Optimización de base de datos. Tablas optimizadas: $tablas_optimizadas, Errores: $tablas_con_error"
            );
            break;
            
        case 'verificar_integridad':
            // Verificar integridad de datos
            $problemas_encontrados = [];
            
            // Verificar registros huérfanos en tabla alumnos_grupos
            $query = "SELECT ag.id_alumno_grupo, ag.id_alumno, ag.id_grupo 
                     FROM alumnos_grupos ag 
                     LEFT JOIN alumnos a ON ag.id_alumno = a.id_alumno 
                     LEFT JOIN grupos g ON ag.id_grupo = g.id_grupo 
                     WHERE a.id_alumno IS NULL OR g.id_grupo IS NULL";
            $resultado = $conexion->query($query);
            
            if ($resultado->num_rows > 0) {
                $problemas_encontrados[] = "Se encontraron {$resultado->num_rows} registros huérfanos en la tabla de asignación de alumnos a grupos.";
            }
            
            // Verificar registros huérfanos en tabla evaluaciones
            $query = "SELECT e.id_evaluacion, e.id_alumno, e.id_materia 
                     FROM evaluaciones e 
                     LEFT JOIN alumnos a ON e.id_alumno = a.id_alumno 
                     LEFT JOIN materias m ON e.id_materia = m.id_materia 
                     WHERE a.id_alumno IS NULL OR m.id_materia IS NULL";
            $resultado = $conexion->query($query);
            
            if ($resultado->num_rows > 0) {
                $problemas_encontrados[] = "Se encontraron {$resultado->num_rows} evaluaciones con referencias inexistentes a alumnos o materias.";
            }
            
            // Verificar duplicados en tabla alumnos_grupos
            $query = "SELECT id_alumno, id_grupo, COUNT(*) as total 
                     FROM alumnos_grupos 
                     GROUP BY id_alumno, id_grupo 
                     HAVING total > 1";
            $resultado = $conexion->query($query);
            
            if ($resultado->num_rows > 0) {
                $problemas_encontrados[] = "Se encontraron {$resultado->num_rows} asignaciones duplicadas de alumnos a grupos.";
            }
            
            // Verificar consistencia de calificaciones
            $query = "SELECT * FROM evaluaciones 
                     WHERE calificacion < 0 OR calificacion > 10 OR calificacion IS NULL";
            $resultado = $conexion->query($query);
            
            if ($resultado->num_rows > 0) {
                $problemas_encontrados[] = "Se encontraron {$resultado->num_rows} calificaciones con valores incorrectos (nulas o fuera de rango).";
            }
            
            if (empty($problemas_encontrados)) {
                $mensaje = "No se encontraron problemas de integridad en la base de datos.";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Se encontraron problemas de integridad en la base de datos.";
                $tipo_mensaje = 'warning';
                $resultado_operacion = [
                    'problemas' => $problemas_encontrados
                ];
            }
            
            // Registrar en log
            registrarLog(
                'operacion',
                $_SESSION['user_id'],
                null,
                "Verificación de integridad de datos. Problemas encontrados: " . count($problemas_encontrados)
            );
            break;
            
        case 'borrar_logs':
            // Borrar logs antiguos
            $dias = isset($_POST['dias_antiguedad']) ? intval($_POST['dias_antiguedad']) : 90;
            
            $query = "DELETE FROM logs_sistema WHERE fecha < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("i", $dias);
            $stmt->execute();
            
            $registros_eliminados = $stmt->affected_rows;
            
            if ($registros_eliminados >= 0) {
                $mensaje = "Se han eliminado $registros_eliminados registros de log con más de $dias días de antigüedad.";
                $tipo_mensaje = 'success';
            } else {
                $mensaje = "Error al eliminar registros de log.";
                $tipo_mensaje = 'danger';
            }
            
            // Registrar en log
            registrarLog(
                'operacion',
                $_SESSION['user_id'],
                null,
                "Limpieza de logs antiguos. Registros eliminados: $registros_eliminados"
            );
            break;
    }
}

// Obtener estadísticas de mantenimiento
$estadisticas = [
    'archivos_temp' => [
        'cantidad' => 0,
        'espacio' => 0
    ],
    'logs' => [
        'total' => 0,
        'ultimo_mes' => 0,
        'accesos' => 0,
        'operaciones' => 0,
        'errores' => 0
    ],
    'db' => [
        'tablas' => 0,
        'tamano' => 0,
        'registros' => []
    ]
];

// Contar archivos temporales
$directorios_temp = [
    __DIR__ . '/../../uploads/temp/',
    __DIR__ . '/../../system/temp/'
];

foreach ($directorios_temp as $directorio) {
    if (is_dir($directorio)) {
        $files = glob($directorio . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $estadisticas['archivos_temp']['cantidad']++;
                $estadisticas['archivos_temp']['espacio'] += filesize($file);
            }
        }
    }
}

// Estadísticas de logs
$query = "SELECT COUNT(*) as total FROM logs_sistema";
$estadisticas['logs']['total'] = $conexion->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM logs_sistema WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$estadisticas['logs']['ultimo_mes'] = $conexion->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM logs_sistema WHERE tipo = 'acceso'";
$estadisticas['logs']['accesos'] = $conexion->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM logs_sistema WHERE tipo = 'operacion'";
$estadisticas['logs']['operaciones'] = $conexion->query($query)->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as total FROM logs_sistema WHERE tipo = 'error'";
$estadisticas['logs']['errores'] = $conexion->query($query)->fetch_assoc()['total'];

// Información de la base de datos
$query = "SHOW TABLE STATUS";
$resultado = $conexion->query($query);

while ($tabla = $resultado->fetch_assoc()) {
    $estadisticas['db']['tablas']++;
    $estadisticas['db']['tamano'] += $tabla['Data_length'] + $tabla['Index_length'];
    
    $estadisticas['db']['registros'][] = [
        'nombre' => $tabla['Name'],
        'registros' => $tabla['Rows'],
        'tamano' => $tabla['Data_length'] + $tabla['Index_length']
    ];
}

// Ordenar tablas por tamaño (descendente)
usort($estadisticas['db']['registros'], function($a, $b) {
    return $b['tamano'] - $a['tamano'];
});

// Incluir encabezado
$titulo_pagina = "Mantenimiento del Sistema";
include_once '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-tools mr-2"></i>Mantenimiento del Sistema</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 mr-1"></i> Volver al Panel
        </a>
    </div>
    
    <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensaje; ?>
        <?php if (isset($resultado_operacion['problemas'])): ?>
            <ul>
                <?php foreach ($resultado_operacion['problemas'] as $problema): ?>
                    <li><?php echo htmlspecialchars($problema); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- Estadísticas rápidas -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Archivos Temporales</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $estadisticas['archivos_temp']['cantidad']; ?> archivos
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?php echo formatear_tamano($estadisticas['archivos_temp']['espacio']); ?> de espacio
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Base de Datos</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $estadisticas['db']['tablas']; ?> tablas
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?php echo formatear_tamano($estadisticas['db']['tamano']); ?> de tamaño
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-database fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Registros de Sistema</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $estadisticas['logs']['total']; ?> logs
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?php echo $estadisticas['logs']['ultimo_mes']; ?> en el último mes
                                    </div>
                                </div>
                                <div class="col-auto">
                                 <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Errores Registrados</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $estadisticas['logs']['errores']; ?> errores
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?php 
                                        if ($estadisticas['logs']['total'] > 0) {
                                            echo round(($estadisticas['logs']['errores'] / $estadisticas['logs']['total']) * 100, 1) . '% del total';
                                        } else {
                                            echo "0% del total";
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Operaciones de limpieza -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Operaciones de Limpieza</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="" onsubmit="return confirm('¿Está seguro de limpiar los archivos temporales?');">
                                <input type="hidden" name="accion" value="limpiar_temp">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-trash mr-1"></i> Limpiar Archivos Temporales
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="" onsubmit="return confirm('¿Está seguro de optimizar las tablas de la base de datos?');">
                                <input type="hidden" name="accion" value="optimizar_bd">
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-database mr-1"></i> Optimizar Base de Datos
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <form method="POST" action="" onsubmit="return confirm('¿Está seguro de verificar la integridad de los datos?');">
                                <input type="hidden" name="accion" value="verificar_integridad">
                                <button type="submit" class="btn btn-info btn-block">
                                    <i class="fas fa-check-circle mr-1"></i> Verificar Integridad de Datos
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <button type="button" class="btn btn-warning btn-block" data-toggle="modal" data-target="#modalBorrarLogs">
                                <i class="fas fa-eraser mr-1"></i> Borrar Logs Antiguos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tablas más grandes -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tablas Más Grandes</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Tabla</th>
                                    <th>Registros</th>
                                    <th>Tamaño</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Mostrar solo las 10 tablas más grandes
                                $tablas_mostradas = array_slice($estadisticas['db']['registros'], 0, 10);
                                foreach ($tablas_mostradas as $tabla): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tabla['nombre']); ?></td>
                                    <td><?php echo number_format($tabla['registros']); ?></td>
                                    <td><?php echo formatear_tamano($tabla['tamano']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Estado del sistema -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Estado del Sistema</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Obtener información del sistema
                    $stats_sistema = obtener_estadisticas_sistema();
                    
                    // Calcular porcentaje de uso de disco
                    $porcentaje_disco = round(($stats_sistema['sistema']['espacio_disco']['usado'] / $stats_sistema['sistema']['espacio_disco']['total']) * 100);
                    
                    // Verificar estado del sistema
                    $estado_general = 'success';
                    $mensaje_estado = 'El sistema funciona correctamente';
                    
                    if ($porcentaje_disco > 90) {
                        $estado_general = 'danger';
                        $mensaje_estado = 'Espacio en disco crítico';
                    } elseif ($porcentaje_disco > 80) {
                        $estado_general = 'warning';
                        $mensaje_estado = 'Espacio en disco bajo';
                    }
                    
                    if ($estadisticas['logs']['errores'] > 100) {
                        $estado_general = 'warning';
                        $mensaje_estado = 'Número elevado de errores';
                    }
                    ?>
                    
                    <div class="alert alert-<?php echo $estado_general; ?> mb-4">
                        <strong>Estado general:</strong> <?php echo $mensaje_estado; ?>
                    </div>
                    
                    <h5 class="mb-2">Espacio en Disco</h5>
                    <div class="mb-4">
                        <h4 class="small font-weight-bold">
                            Uso de disco: <?php echo $porcentaje_disco; ?>%
                            <span class="float-right">
                                <?php echo formatear_tamano($stats_sistema['sistema']['espacio_disco']['usado']); ?> / 
                                <?php echo formatear_tamano($stats_sistema['sistema']['espacio_disco']['total']); ?>
                            </span>
                        </h4>
                        <div class="progress mb-4">
                            <div class="progress-bar bg-<?php echo ($porcentaje_disco > 90) ? 'danger' : (($porcentaje_disco > 80) ? 'warning' : 'info'); ?>" 
                                 role="progressbar" style="width: <?php echo $porcentaje_disco; ?>%" 
                                 aria-valuenow="<?php echo $porcentaje_disco; ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-2">Información del Servidor</h5>
                    <ul class="list-group mb-4">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Servidor Web
                            <span class="badge badge-primary"><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            PHP
                            <span class="badge badge-primary"><?php echo htmlspecialchars(PHP_VERSION); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            MySQL
                            <span class="badge badge-primary"><?php echo htmlspecialchars($stats_sistema['sistema']['version_mysql']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Límite de Memoria PHP
                            <span class="badge badge-primary"><?php echo htmlspecialchars($stats_sistema['sistema']['memoria']['limit']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Tiempo de Actividad
                            <span class="badge badge-primary"><?php echo htmlspecialchars($stats_sistema['sistema']['tiempo_actividad'] ?: 'No disponible'); ?></span>
                        </li>
                    </ul>
                    
                    <h5 class="mb-2">Características PHP</h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Extensión</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $extensiones = [
                                    'mysqli' => 'MySQL Improved',
                                    'gd' => 'GD Graphics',
                                    'mbstring' => 'Multibyte String',
                                    'curl' => 'cURL',
                                    'zip' => 'ZIP',
                                    'openssl' => 'OpenSSL',
                                    'fileinfo' => 'Fileinfo'
                                ];
                                
                                foreach ($extensiones as $ext => $nombre):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($nombre); ?></td>
                                    <td>
                                        <?php if (extension_loaded($ext)): ?>
                                            <span class="badge badge-success">Habilitada</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">No Disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Actividad reciente -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Errores Recientes</h6>
                    <a href="logs.php?tipo=error" class="btn btn-sm btn-link">Ver todos</a>
                </div>
                <div class="card-body">
                    <?php
                    // Obtener errores recientes
                    $query = "SELECT * FROM logs_sistema WHERE tipo = 'error' ORDER BY fecha DESC LIMIT 10";
                    $errores_recientes = $conexion->query($query)->fetch_all(MYSQLI_ASSOC);
                    ?>
                    
                    <?php if (empty($errores_recientes)): ?>
                        <p class="text-center">No hay errores recientes registrados.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Usuario</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($errores_recientes as $error): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($error['fecha'])); ?></td>
                                        <td><?php echo htmlspecialchars($error['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($error['descripcion']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Borrar Logs Antiguos -->
<div class="modal fade" id="modalBorrarLogs" tabindex="-1" role="dialog" aria-labelledby="modalBorrarLogsLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBorrarLogsLabel">Borrar Logs Antiguos</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="" onsubmit="return confirm('¿Está seguro de borrar los logs antiguos?');">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="borrar_logs">
                    
                    <div class="form-group">
                        <label for="dias_antiguedad">Eliminar logs con más de:</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="dias_antiguedad" name="dias_antiguedad" 
                                   value="90" min="30" max="365">
                            <div class="input-group-append">
                                <span class="input-group-text">días</span>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            Se recomienda mantener al menos 30 días de registros.
                        </small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Esta acción eliminará definitivamente los registros de log antiguos.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-eraser mr-1"></i> Borrar Logs Antiguos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>